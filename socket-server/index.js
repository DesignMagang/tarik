// index.js (Versi FINAL - Soal Tanpa Pengulangan Antar Ronde)
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
const sessions = {}; // Kunci sesi adalah user_id dari pemilik
const roles = {};
const questionSets = {}; // Master set soal (tidak diacak) disimpan berdasarkan USER_ID

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

async function loadQuestions(userId) {
    try {
        // PENTING: Memfilter soal HANYA milik user ini
        const [rows] = await db.query("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, type FROM questions WHERE user_id = ? ORDER BY id ASC", [userId]);
        questionSets[userId] = rows;
        return rows;
    } catch (error) {
        console.error("Error loading questions:", error);
        return [];
    }
}

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
    const loser = (winner === 'player1') ? 'player2' : 'player1';

    io.to(`session_${sessionId}`).emit('game_over', { winner, loser, reason, finalPosition: session.position, pulls: session.pulls });

    setTimeout(() => {
        io.to(`session_${sessionId}`).emit('game_state_change', { state: 'round_finished', round: session.currentRound, bgm: session.bgmFile });
    }, 3000);
}

io.on('connection', (socket) => {
    console.log('✅ New socket connected:', socket.id);

    // --- Join Session ---
    socket.on('join_session', async(payload) => {
        const { sessionId, userId, deviceName, role, ownerUsername } = payload;
        if (!sessionId || !userId) return;

        socket.join(`session_${sessionId}`);

        if (!sessions[sessionId]) {
            // Inisialisasi Sesi Baru
            sessions[sessionId] = {
                state: 'waiting',
                players: {},
                currentQuestionIndex: -1,
                currentQuestion: null,
                currentRound: 0,
                pulls: { player1: 0, player2: 0 },
                position: 50,
                answeredQuestions: {}, // Tracking jawaban dalam 1 soal
                currentQuestionSet: [], // Soal untuk ronde saat ini
                bgmFile: 'milikku.mp3',
                sessionOwner: ownerUsername || null,
                ownerUserId: userId,
                // --- PERUBAHAN KRITIS: Pelacakan soal yang sudah digunakan ---
                allQuestionsUsedIds: new Set()
            };
            await loadQuestions(userId); // Muat Master Set Soal
        }

        sessions[sessionId].players[socket.id] = { id: socket.id, deviceName, role };

        if (!roles[sessionId]) roles[sessionId] = { host: null, player1: null, player2: null };

        io.to(socket.id).emit('roles_update', roles[sessionId]);
        io.to(socket.id).emit('game_state_change', { state: sessions[sessionId].state, round: sessions[sessionId].currentRound, bgm: sessions[sessionId].bgmFile });
        io.to(socket.id).emit('update_tug', { position: sessions[sessionId].position, pulls: sessions[sessionId].pulls });
    });

    // --- Role Selection (TETAP SAMA) ---
    socket.on('choose_role', (data) => {
        const { sessionId, role, username } = data;
        const session = sessions[sessionId];

        if (!roles[sessionId]) roles[sessionId] = { host: null, player1: null, player2: null };

        // PENTING: Cek 1 - Cek Kepemilikan Sesi
        if (session && session.sessionOwner && username !== session.sessionOwner) {
            socket.emit('role_taken', {
                role: role,
                username: session.sessionOwner,
                reason: 'Akses Dibatasi'
            });
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

    // --- Host: Memulai game/ronde baru (start_game) ---
    socket.on('start_game', async(data) => {
        const { sessionId } = data;
        const session = sessions[sessionId];

        const masterQuestions = questionSets[session.ownerUserId];

        if (!session || (session.state !== 'waiting' && session.state !== 'finished' && session.state !== 'round_finished') || !masterQuestions || masterQuestions.length === 0) {
            io.to(socket.id).emit("status_update", { status: `❌ Game Gagal START: Tidak ada soal milik Anda.` });
            return;
        }

        // --- Logika Filtering dan Daur Ulang Soal ---
        let availableQuestions = masterQuestions.filter(q => !session.allQuestionsUsedIds.has(q.id));

        if (availableQuestions.length === 0) {
            // Semua soal sudah digunakan setidaknya sekali. Lakukan daur ulang.
            console.log(`♻️ Sesi ${sessionId}: Semua soal (${masterQuestions.length}) sudah digunakan. Me-recycle soal...`);
            session.allQuestionsUsedIds.clear(); // Bersihkan riwayat penggunaan
            availableQuestions = [...masterQuestions]; // Gunakan semua soal lagi
        }

        const shuffledQuestions = shuffleArray(availableQuestions);
        session.currentQuestionSet = shuffledQuestions;
        // --- Akhir Logika Filtering dan Daur Ulang ---

        session.currentRound++;
        session.state = 'playing';
        session.answeredQuestions = {};
        session.currentQuestionIndex = 0;

        // Reset posisi dan tarikan
        session.pulls = { player1: 0, player2: 0 };
        session.position = 50;

        const firstQuestion = shuffledQuestions[0];

        if (firstQuestion) {
            session.currentQuestion = firstQuestion.id;
            io.to(`session_${sessionId}`).emit("question", firstQuestion);
            io.to(`session_${sessionId}`).emit("status_update", { status: `Ronde Baru Dimulai! Soal 1 aktif.` });

            io.to(`session_${sessionId}`).emit('game_state_change', {
                state: 'playing',
                round: session.currentRound,
                bgm: session.bgmFile
            });

            io.to(`session_${sessionId}`).emit('update_tug', { position: 50, pulls: session.pulls });
            console.log(`▶️ Ronde ${session.currentRound} Dimulai, ${shuffledQuestions.length} soal di set ini.`);
        }
    });

    // --- Player: Mengirim jawaban (submit_answer) ---
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

        // --- PERUBAHAN KRITIS: Tandai Soal Sudah Digunakan ---
        if (!session.allQuestionsUsedIds.has(questionId)) {
            session.allQuestionsUsedIds.add(questionId);
            console.log(`✅ Soal ID ${questionId} ditambahkan ke daftar yang sudah digunakan. Total digunakan: ${session.allQuestionsUsedIds.size}`);
        }
        // --- Akhir Perubahan Kritis ---


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

        io.to(socket.id).emit('request_next_question', {});
    });

    // --- next_question ---
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
            // Semua soal dalam set ronde ini sudah habis
            const finalWinner = (session.position <= 50) ? 'player1' : 'player2';
            gameOver(sessionId, finalWinner, 'Semua Soal dalam ronde ini Sudah Dijawab!');
        }
    });

    // --- reset_game ---
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

        // --- PERUBAHAN KRITIS: Reset Riwayat Penggunaan Soal ---
        sessions[sessionId].allQuestionsUsedIds = new Set();
        // --- Akhir Perubahan Kritis ---

        io.to(`session_${sessionId}`).emit('update_tug', { position: 50, pulls: sessions[sessionId].pulls });
        io.to(`session_${sessionId}`).emit("status_update", { status: `Game direset, menunggu host memulai.` });
        io.to(`session_${sessionId}`).emit('game_state_change', { state: 'waiting', round: 0, bgm: sessions[sessionId].bgmFile });
    });

    // --- disconnect (TETAP SAMA) ---
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