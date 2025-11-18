// index.js (Versi FINAL Stabil - Integrasi Audio dan Ronde)
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');

// --- Koneksi MySQL ---
const db = mysql.createPool({
    host: '127.0.0.1',
    port: 3307,
    user: 'root',
    password: '',
    database: 'tarik'
});

// --- Setup Server ---
const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: { origin: "*", methods: ["GET", "POST"] }
});

// --- Constants & Data In-memory ---
const PULL_UNIT = 5;
const WIN_THRESHOLD = 90;
const sessions = {};
const roles = {};
const questionSets = {};

function getOppositeRole(role) {
    return role === 'player1' ? 'player2' : 'player1';
}

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

async function loadQuestions(sessionId) {
    try {
        const [rows] = await db.query("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, type FROM questions ORDER BY id ASC");
        questionSets[sessionId] = rows;
        return rows;
    } catch (error) {
        console.error("Error loading questions:", error);
        return [];
    }
}

// --- Game Logic Functions ---
function calculateNewPosition(currentPosition, winnerRole) {
    if (winnerRole === 'player1') {
        return Math.max(0, currentPosition - PULL_UNIT);
    } else if (winnerRole === 'player2') {
        return Math.min(100, currentPosition + PULL_UNIT);
    }
    return currentPosition;
}

function gameOver(sessionId, winner, reason) {
    const session = sessions[sessionId];
    if (session.state === 'finished') return;

    session.state = 'finished';

    // Tentukan siapa yang kalah
    const loser = (winner === 'player1') ? 'player2' : 'player1';

    // Kirim pesan ke semua klien
    io.to(`session_${sessionId}`).emit('game_over', { winner, loser, reason, finalPosition: session.position });

    // Siapkan untuk ronde berikutnya (jika ada)
    setTimeout(() => {
        io.to(`session_${sessionId}`).emit('game_state_change', { state: 'round_finished', round: session.currentRound, bgm: session.bgmFile });
    }, 3000);
}

// --- Socket.IO Handlers ---

io.on('connection', (socket) => {
    console.log('✅ New socket connected:', socket.id);

    // --- Join Session ---
    socket.on('join_session', async(payload) => {
        const { sessionId, userId, deviceName, role } = payload;
        if (!sessionId) return;
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
                bgmFile: 'milikku.mp3'
            };
            await loadQuestions(sessionId);
        }

        sessions[sessionId].players[socket.id] = { id: socket.id, deviceName, role };

        // Perbarui roles status
        if (!roles[sessionId]) roles[sessionId] = { host: null, player1: null, player2: null };

        // Kirim status awal ke klien yang baru bergabung
        io.to(socket.id).emit('roles_update', roles[sessionId]);
        io.to(socket.id).emit('game_state_change', { state: sessions[sessionId].state, round: sessions[sessionId].currentRound, bgm: sessions[sessionId].bgmFile });
        io.to(socket.id).emit('update_tug', { position: sessions[sessionId].position, pulls: sessions[sessionId].pulls });
    });

    // --- Role Selection (SAMA) ---
    socket.on('choose_role', (data) => {
        const { sessionId, role, username } = data;
        if (!roles[sessionId]) roles[sessionId] = { host: null, player1: null, player2: null };

        roles[sessionId][role] = username;
        io.to(`session_${sessionId}`).emit("roles_update", roles[sessionId]);
    });

    // --- Request Roles Status (SAMA) ---
    socket.on('request_roles_status', (data) => {
        const { sessionId } = data;
        if (roles[sessionId]) {
            io.to(socket.id).emit("roles_update", roles[sessionId]);
        }
    });

    // --- HOST: Memilih Musik (SAMA) ---
    socket.on('select_bgm', (data) => {
        const session = sessions[data.sessionId];
        if (session) {
            session.bgmFile = data.bgmFile;
            io.to(socket.id).emit("status_update", { status: `Musik Latar: ${data.bgmFile} terpilih.` });
        }
    });

    // --- Host: Memulai game/ronde baru (start_game) (SAMA) ---
    socket.on('start_game', async(data) => {
        const { sessionId } = data;
        const session = sessions[sessionId];

        const masterQuestions = questionSets[sessionId] || await loadQuestions(sessionId);

        if (!session || (session.state !== 'waiting' && session.state !== 'finished' && session.state !== 'round_finished') || !masterQuestions || masterQuestions.length === 0) {
            io.to(socket.id).emit("status_update", { status: `❌ Game Gagal START: Cek status atau soal DB.` });
            return;
        }

        session.currentRound++;

        const shuffledQuestions = shuffleArray([...masterQuestions]);
        session.currentQuestionSet = shuffledQuestions;

        // 1. Reset status ronde
        session.state = 'playing';
        session.answeredQuestions = {};
        session.currentQuestionIndex = 0;

        // PENTING: RESET POSISI DAN TARIKAN DI SETIAP RONDE
        session.pulls = { player1: 0, player2: 0 };
        session.position = 50;

        const firstQuestion = shuffledQuestions[0];

        if (firstQuestion) {
            session.currentQuestion = firstQuestion.id;
            io.to(`session_${sessionId}`).emit("question", firstQuestion);
            io.to(`session_${sessionId}`).emit("status_update", { status: `Ronde Baru Dimulai! Soal 1 aktif.` });

            // Kirim status dan BGM file
            io.to(`session_${sessionId}`).emit('game_state_change', {
                state: 'playing',
                round: session.currentRound,
                bgm: session.bgmFile
            });

            // Kirim update posisi 50 dan 0 pulls ke semua klien
            io.to(`session_${sessionId}`).emit('update_tug', { position: 50, pulls: session.pulls });
            console.log(`▶️ Ronde ${session.currentRound} Dimulai, Posisi direset ke 50.`);
        }
    });

    // --- Player: Mengirim jawaban ---
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

        // Tandai sebagai dijawab oleh pemain INI
        if (!session.answeredQuestions[questionId]) {
            session.answeredQuestions[questionId] = [];
        }

        // PENTING: Cek apakah pemain INI sudah menjawab soal INI
        if (session.answeredQuestions[questionId].includes(role)) {
            io.to(socket.id).emit("answer_feedback", { accepted: false, reason: "⚠️ Anda sudah menjawab soal ini. Soal akan dilanjutkan." });
            // Pindahkan soal agar Player tidak terjebak di soal yang sudah dijawab
            io.to(socket.id).emit('request_next_question', {});
            return;
        }

        // Tandai sebagai dijawab oleh pemain ini
        session.answeredQuestions[questionId].push(role);

        if (correct) {
            // Logika Benar: PULL
            session.pulls[role]++;
            session.position = calculateNewPosition(session.position, role);

            io.to(socket.id).emit("answer_feedback", { accepted: true, reason: "✅ Benar! Tali ditarik. Tunggu soal berikutnya." });
        } else {
            // Logika Salah: LAWAN PULL
            const oppositeRole = getOppositeRole(role);
            session.pulls[oppositeRole]++;
            session.position = calculateNewPosition(session.position, oppositeRole);

            io.to(socket.id).emit("answer_feedback", { accepted: false, reason: "❌ Salah! Tali ditarik lawan. Tunggu soal berikutnya." });
        }

        // Kirim update posisi tali ke semua klien (Host dan Player)
        io.to(`session_${sessionId}`).emit('update_tug', { position: session.position, pulls: session.pulls, puller: correct ? role : getOppositeRole(role) });


        // --- CEK KONDISI KEMENANGAN HANYA DARI POSISI TALI ---
        const WIN_THRESHOLD_MIN = 100 - WIN_THRESHOLD;
        const WIN_THRESHOLD_MAX = WIN_THRESHOLD;

        if (session.position <= WIN_THRESHOLD_MIN) {
            gameOver(sessionId, 'player1', 'Pemenang ditentukan oleh tali ditarik ke batas maksimal!');
            return;
        }

        if (session.position >= WIN_THRESHOLD_MAX) {
            gameOver(sessionId, 'player2', 'Pemenang ditentukan oleh tali ditarik ke batas maksimal!');
            return;
        }
        // --- AKHIR CEK KONDISI KEMENANGAN TALI ---


        // Lanjut ke soal berikutnya setelah jawaban (benar atau salah)
        io.to(socket.id).emit('request_next_question', {});
    });

    // --- next_question, reset_game, disconnect (SAMA) ---

    socket.on('next_question', (data) => {
        const { sessionId } = data;
        const session = sessions[sessionId];

        const lastIndex = session.currentQuestionSet.findIndex(q => q.id === session.currentQuestion);
        const nextIndex = lastIndex + 1;

        if (nextIndex < session.currentQuestionSet.length) {
            const nextQuestion = session.currentQuestionSet[nextIndex];
            session.currentQuestion = nextQuestion.id;
            io.to(socket.id).emit("question", nextQuestion);
            io.to(`session_${sessionId}`).emit("status_update", { status: `Soal #${nextIndex + 1} aktif.` });
        } else {
            const finalWinner = (session.position <= 50) ? 'player1' : 'player2';
            gameOver(sessionId, finalWinner, 'Semua Soal Sudah Dijawab!');
        }
    });

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

        io.to(`session_${sessionId}`).emit('update_tug', { position: 50, pulls: sessions[sessionId].pulls });
        io.to(`session_${sessionId}`).emit("status_update", { status: `Game direset, menunggu host memulai.` });
        io.to(`session_${sessionId}`).emit('game_state_change', { state: 'waiting', round: 0, bgm: sessions[sessionId].bgmFile });
    });

    socket.on('disconnect', () => {
        for (const sid in sessions) {
            if (sessions[sid] && sessions[sid].players && sessions[sid].players[socket.id]) {
                const disconnectedDeviceName = sessions[sid].players[socket.id].deviceName;

                delete sessions[sid].players[socket.id];

                for (const roleKey in roles[sid]) {
                    if (roles[sid][roleKey] === disconnectedDeviceName) {
                        roles[sid][roleKey] = null;
                    }
                }
                io.to(`session_${sid}`).emit("roles_update", roles[sid]);
            }
        }
    });
});

// --- Jalankan Server ---
const PORT = 3000;
server.listen(PORT, () => {
    console.log(`Server is running on http://localhost:${PORT}`);
});