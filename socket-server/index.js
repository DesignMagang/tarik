// index.js (Versi FINAL - Integrasi Skor Ronde)
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');

// --- Koneksi MySQL ---
const db = mysql.createPool({ host: '127.0.0.1', port: 3307, user: 'root', password: '', database: 'tarik' });
const app = express();
const server = http.createServer(app);
const io = new Server(server, { cors: { origin: "*", methods: ["GET", "POST"] } });

// --- Constants & Data In-memory ---
const PULL_UNIT = 5;
const WIN_THRESHOLD = 90;
const sessions = {};
const roles = {};
const questionSets = {};

function getOppositeRole(role) { return role === 'player1' ? 'player2' : 'player1'; }

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

async function loadQuestions(userId) {
    try {
        const [rows] = await db.query("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, type, round_number FROM questions WHERE user_id = ? ORDER BY round_number ASC, id ASC", [userId]);

        const groupedQuestions = {};
        rows.forEach(q => {
            const roundNum = q.round_number;
            if (!groupedQuestions[roundNum]) {
                groupedQuestions[roundNum] = [];
            }
            groupedQuestions[roundNum].push(q);
        });

        questionSets[userId] = groupedQuestions;
        return groupedQuestions;
    } catch (error) { return {}; }
}

function calculateNewPosition(currentPosition, winnerRole) {
    if (winnerRole === 'player1') { return Math.max(0, currentPosition - PULL_UNIT); }
    if (winnerRole === 'player2') { return Math.min(100, currentPosition + PULL_UNIT); }
    return currentPosition;
}

function determineTieBreakerWinner(session) {
    const p1Pulls = session.pulls.player1;
    const p2Pulls = session.pulls.player2;

    if (p1Pulls > p2Pulls) {
        return 'player1';
    } else if (p2Pulls > p1Pulls) {
        return 'player2';
    } else {
        return 'tie'; // Seri/Seimbang
    }
}

function gameOver(sessionId, winner, reason) {
    const session = sessions[sessionId];
    if (session.state === 'finished') return;

    let finalWinner = winner;
    let finalReason = reason;

    // Tentukan pemenang akhir
    if (finalWinner === null) {
        finalWinner = determineTieBreakerWinner(session);
        if (finalWinner === 'tie') {
            finalReason = 'Seri! Skor Tarikan Seimbang.';
        } else {
            finalReason = `Menang berdasarkan keunggulan jumlah tarikan (${finalWinner.toUpperCase()}).`;
        }
    }

    // --- PERUBAHAN KRITIS: Melacak Skor Ronde ---
    if (!session.roundScores) {
        session.roundScores = {};
    }
    session.roundScores[session.currentRound] = finalWinner;
    // --- Akhir Perubahan Kritis ---

    session.state = 'finished';
    const loser = (finalWinner === 'player1' || finalWinner === 'tie') ? 'player2' : 'player1';

    // Kirim event game_over
    io.to(`session_${sessionId}`).emit('game_over', {
        winner: finalWinner,
        loser,
        reason: finalReason,
        finalPosition: session.position,
        pulls: session.pulls,
        roundScores: session.roundScores // PENTING: Kirim skor kumulatif
    });

    // Sinyal ronde selesai
    setTimeout(() => {
        io.to(`session_${sessionId}`).emit('game_state_change', { state: 'round_finished', round: session.currentRound, bgm: session.bgmFile });
    }, 3000);
}

// --- Socket.IO Handlers ---

io.on('connection', (socket) => {
    console.log('✅ New socket connected:', socket.id);

    socket.on('join_session', async(payload) => {
        const { sessionId, userId, deviceName, role, ownerUsername } = payload;
        if (!sessionId || !userId) return;
        socket.join(`session_${sessionId}`);

        if (!sessions[sessionId]) {
            sessions[sessionId] = {
                state: 'waiting',
                players: {},
                currentQuestionIndex: -1,
                currentQuestion: null,
                currentRound: 0,
                pulls: { player1: 0, player2: 0 },
                position: 50,
                answeredQuestions: {},
                currentQuestionSet: [],
                bgmFile: 'milikku.mp3',
                sessionOwner: ownerUsername || null,
                ownerUserId: userId,
                masterQuestionGroups: await loadQuestions(userId),
                roundScores: {} // BARU: Inisialisasi skor ronde
            };
        }

        sessions[sessionId].players[socket.id] = { id: socket.id, deviceName, role };

        if (!roles[sessionId]) roles[sessionId] = { host: null, player1: null, player2: null };

        io.to(socket.id).emit('roles_update', roles[sessionId]);
        io.to(socket.id).emit('game_state_change', { state: sessions[sessionId].state, round: sessions[sessionId].currentRound, bgm: sessions[sessionId].bgmFile });
        io.to(socket.id).emit('update_tug', { position: sessions[sessionId].position, pulls: sessions[sessionId].pulls });

        // PENTING: Kirim skor ronde saat bergabung
        io.to(socket.id).emit('update_round_scores', sessions[sessionId].roundScores);
    });

    socket.on('choose_role', (data) => {
        const { sessionId, role, username } = data;
        const session = sessions[sessionId];

        if (!roles[sessionId]) roles[sessionId] = { host: null, player1: null, player2: null };

        if (session && session.sessionOwner && username !== session.sessionOwner) {
            socket.emit('role_taken', { role: role, username: session.sessionOwner, reason: 'Akses Dibatasi' });
            return;
        }

        if (!roles[sessionId][role]) {
            roles[sessionId][role] = username;
        } else if (roles[sessionId][role] !== username) {
            socket.emit('role_taken', { role: role, username: roles[sessionId][role] });
            return;
        }

        io.to(`session_${sessionId}`).emit("roles_update", roles[sessionId]);
    });

    socket.on('select_bgm', (data) => {
        const session = sessions[data.sessionId];
        if (session) {
            session.bgmFile = data.bgmFile;
            io.to(socket.id).emit("status_update", { status: `Musik Latar: ${data.bgmFile} terpilih.` });
        }
    });

    socket.on('start_game', async(data) => {
        const { sessionId } = data;
        const session = sessions[sessionId];
        const masterGroups = session.masterQuestionGroups;

        const nextRoundNum = session.currentRound + 1;
        const questionsForNextRound = masterGroups ? masterGroups[nextRoundNum] : null;

        if (!session || session.state === 'playing' || !masterGroups) {
            io.to(socket.id).emit("status_update", { status: `❌ Game Gagal START: Cek status sesi.` });
            return;
        }

        if (!questionsForNextRound || questionsForNextRound.length === 0) {
            io.to(socket.id).emit("status_update", { status: `❌ Game Over: Semua ronde yang dibuat sudah selesai.` });
            if (session.currentRound > 0) {
                gameOver(sessionId, null, 'Semua Ronde telah diselesaikan. Pemenang ditentukan oleh skor tarikan ronde terakhir!');
            }
            return;
        }

        const shuffledQuestions = shuffleArray(questionsForNextRound);
        session.currentQuestionSet = shuffledQuestions;

        session.currentRound = nextRoundNum;
        session.state = 'playing';
        session.answeredQuestions = {};
        session.currentQuestionIndex = 0;

        session.pulls = { player1: 0, player2: 0 };
        session.position = 50;

        const firstQuestion = shuffledQuestions[0];

        if (firstQuestion) {
            session.currentQuestion = firstQuestion.id;
            io.to(`session_${sessionId}`).emit("question", firstQuestion);
            io.to(`session_${sessionId}`).emit("status_update", { status: `Ronde ${nextRoundNum} Dimulai! Soal 1 aktif.` });

            io.to(`session_${sessionId}`).emit('game_state_change', {
                state: 'playing',
                round: session.currentRound,
                bgm: session.bgmFile
            });

            io.to(`session_${sessionId}`).emit('update_tug', { position: 50, pulls: session.pulls });
        }
    });

    socket.on('submit_answer', async(data) => {
        const { sessionId, questionId, selected, role } = data;
        const session = sessions[sessionId];

        if (!session || session.state !== 'playing') {
            io.to(socket.id).emit("answer_feedback", { accepted: false, reason: "❌ Game belum aktif." });
            return;
        }

        const currentQ = session.currentQuestionSet.find(q => q.id === questionId);
        if (!currentQ) {
            io.to(socket.id).emit("answer_feedback", { accepted: false, reason: "❌ Soal tidak ditemukan." });
            return;
        }

        const correctAnswer = currentQ.correct_answer;
        const correct = (selected.toString().toUpperCase() === correctAnswer.toString().toUpperCase());

        if (!session.answeredQuestions[questionId]) {
            session.answeredQuestions[questionId] = [];
        }

        if (session.answeredQuestions[questionId].includes(role)) {
            io.to(socket.id).emit("answer_feedback", { accepted: false, reason: "⚠️ Anda sudah menjawab soal ini. Soal akan dilanjutkan." });
            io.to(socket.id).emit('request_next_question', {});
            return;
        }

        session.answeredQuestions[questionId].push(role);

        if (correct) {
            session.pulls[role]++;
            session.position = calculateNewPosition(session.position, role);

            io.to(socket.id).emit("answer_feedback", { accepted: true, reason: "✅ Benar! Tali ditarik. Tunggu soal berikutnya." });
        } else {
            const oppositeRole = getOppositeRole(role);
            session.pulls[oppositeRole]++;
            session.position = calculateNewPosition(session.position, oppositeRole);

            io.to(socket.id).emit("answer_feedback", { accepted: false, reason: "❌ Salah! Tali ditarik lawan. Tunggu soal berikutnya." });
        }

        io.to(`session_${sessionId}`).emit('update_tug', { position: session.position, pulls: session.pulls, puller: correct ? role : getOppositeRole(role) });

        const WIN_THRESHOLD_MIN = 100 - WIN_THRESHOLD;
        const WIN_THRESHOLD_MAX = WIN_THRESHOLD;

        // Kemenangan Mutlak Tali
        if (session.position <= WIN_THRESHOLD_MIN) {
            gameOver(sessionId, 'player1', 'Pemenang ditentukan oleh tali ditarik ke batas maksimal!');
            return;
        }

        if (session.position >= WIN_THRESHOLD_MAX) {
            gameOver(sessionId, 'player2', 'Pemenang ditentukan oleh tali ditarik ke batas maksimal!');
            return;
        }

        io.to(socket.id).emit('request_next_question', {});
    });

    // --- Player: Meminta soal berikutnya (next_question) ---
    socket.on('next_question', (data) => {
        const { sessionId } = data;
        const session = sessions[sessionId];
        const questions = session.currentQuestionSet;

        const lastIndex = session.currentQuestionSet.findIndex(q => q.id === session.currentQuestion);
        let nextIndex = lastIndex + 1;

        if (nextIndex < questions.length) {
            // Lanjut ke soal berikutnya di set yang sama
            session.currentQuestionIndex = nextIndex;
            const nextQuestion = questions[nextIndex];
            session.currentQuestion = nextQuestion.id;
            io.to(socket.id).emit("question", nextQuestion);
            io.to(`session_${sessionId}`).emit("status_update", { status: `Soal #${nextIndex + 1} aktif.` });
        } else {
            // PENTING: SOAL HABIS, GAME OVER, dan terapkan Tie-breaker
            gameOver(sessionId, null, 'Semua soal di ronde ini telah dijawab. Menentukan pemenang berdasarkan tarikan!');
        }
    });

    socket.on('end_round_manual', (data) => {
        const { sessionId } = data;
        const session = sessions[sessionId];
        if (session.state === 'playing') {
            gameOver(sessionId, null, 'Ronde diakhiri secara manual/waktu habis. Menentukan pemenang berdasarkan tarikan!');
        }
    });


    // --- reset_game dan disconnect (SAMA) ---
    socket.on('reset_game', (data) => {
        const { sessionId } = data;
        if (!sessions[sessionId]) return;

        sessions[sessionId].state = 'waiting';
        sessions[sessionId].pulls = { player1: 0, player2: 0 };
        sessions[sessionId].position = 50;
        sessions[sessionId].currentRound = 0;
        sessions[sessionId].answeredQuestions = {};
        sessions[sessionId].currentQuestion = null;
        sessions[sessionId].currentQuestionIndex = -1;
        sessions[sessionId].currentQuestionSet = [];
        sessions[sessionId].allQuestionsUsedIds = new Set();
        sessions[sessionId].roundScores = {}; // PENTING: Reset skor ronde saat reset game

        io.to(`session_${sessionId}`).emit('update_tug', { position: 50, pulls: sessions[sessionId].pulls });
        io.to(`session_${sessionId}`).emit("status_update", { status: `Game direset, menunggu host memulai.` });
        io.to(`session_${sessionId}`).emit('game_state_change', { state: 'waiting', round: 0, bgm: sessions[sessionId].bgmFile });
        io.to(`session_${sessionId}`).emit('update_round_scores', sessions[sessionId].roundScores); // Kirim skor kosong
    });

    socket.on('disconnect', () => {
        // Loop melalui semua sesi aktif
        for (const sid in sessions) {
            // Cek apakah socket yang terputus adalah bagian dari sesi ini
            if (sessions[sid] && sessions[sid].players && sessions[sid].players[socket.id]) {
                const disconnectedDeviceName = sessions[sid].players[socket.id].deviceName;

                // 1. Hapus socket dari daftar players sesi
                delete sessions[sid].players[socket.id];

                // 2. Cek apakah perangkat yang terputus memegang peran (Host/P1/P2)
                for (const roleKey in roles[sid]) {
                    // Jika deviceName yang terputus cocok dengan nama yang memegang peran
                    if (roles[sid][roleKey] === disconnectedDeviceName) {
                        roles[sid][roleKey] = null; // Kosongkan peran tersebut
                        console.log(`🔌 Peran ${roleKey.toUpperCase()} dikosongkan di sesi ${sid}.`);
                    }
                }

                // 3. Kirim update status peran ke semua klien yang tersisa di sesi
                io.to(`session_${sid}`).emit("roles_update", roles[sid]);
            }
        }
        console.log('❌ Socket disconnected:', socket.id);
    });
});

// --- Jalankan Server ---
const PORT = 3000;
server.listen(PORT, () => {
    console.log(`Server is running on http://localhost:${PORT}`);
});