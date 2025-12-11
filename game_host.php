<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'db_connect.php'; 

$quiz_id = $_GET['quiz_id'] ?? 'global';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username']; 

$all_questions = []; 
$result = null; 

if (isset($conn)) {
    $result = $conn->query("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer FROM questions ORDER BY id ASC");
    if ($result) {
        $all_questions = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Host Game - Tarik Tambang Kuis Alkitab</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-100 via-blue-200 to-blue-300 flex flex-col items-center justify-center font-[Poppins]">

<div class="w-full max-w-4xl p-4">
    <h1 class="text-3xl font-extrabold text-blue-700 mb-2 text-center">🎮 Host Kontrol Kuis</h1>
    <p class="text-gray-600 mb-6 text-center">Kuis ID: <span class="font-semibold text-blue-600"><?= htmlspecialchars($quiz_id); ?></span> | Ronde: <span id="round_number" class="font-bold text-red-600">0</span></p>

    <div class="relative bg-white border-4 border-blue-300 rounded-2xl shadow-lg w-full h-[200px] flex items-center justify-center overflow-hidden mb-6">
        <div class="absolute left-1/2 top-0 bottom-0 w-1 bg-gray-300"></div>
        
        <img id="rope" src="tarik.png" 
             class="absolute w-[60%] h-full object-contain transition-all duration-300" 
             style="left: 50%; transform: translateX(-50%);">
        
        <div id="p1_label_container" class="absolute left-4 top-4 bg-blue-100 text-blue-800 px-3 py-1 rounded-lg font-semibold flex items-center space-x-2">
            <span id="p1_status_dot" class="w-3 h-3 rounded-full bg-red-500 opacity-0"></span> <span id="p1_label">👤 P1</span> 
        </div>

        <div id="p2_label_container" class="absolute right-4 top-4 bg-green-100 text-green-800 px-3 py-1 rounded-lg font-semibold flex items-center space-x-2">
            <span id="p2_status_dot" class="w-3 h-3 rounded-full bg-red-500 opacity-0"></span> <span id="p2_label">👤 P2</span>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-xl border border-gray-200 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-700">Status Game</h2>
            <div id="status" class="text-lg font-semibold text-gray-600">Menunggu pemain...</div>
        </div>
        
        <div id="win_message_host" class="mt-4 mb-4 text-center hidden p-3 rounded-lg border-2 border-green-400 bg-green-50 text-green-800 font-bold"></div>

        <div class="flex justify-around items-center space-x-4">
            <button id="startGameBtn" onclick="startGame()" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-5 py-3 rounded-xl shadow-md font-bold transition disabled:bg-gray-400" disabled>
                ▶️ Tombol PLAY
            </button>
            <button onclick="openResetModal()" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-5 py-3 rounded-xl shadow-md font-bold transition">
                🔄 Reset Game
            </button>
        </div>
        
        <div class="mt-4 text-center">
             <label for="bgm_select" class="text-sm font-semibold text-gray-600">Pilih Musik Latar Ronde:</label>
             <select id="bgm_select" onchange="selectBGM()" class="p-1 border rounded-lg text-sm ml-2">
                 <option value="milikku.mp3">milikku.mp3</option>
                 <option value="teriak.mp3">teriak.mp3</option>
             </select>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-xl border border-gray-200">
        <h2 class="text-xl font-bold text-gray-700 mb-4">🏆 Skor Ronde (P1 vs P2)</h2>
        <div id="scoreTableContainer" class="overflow-x-auto">
            <table id="scoreTable" class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-4 border-b">Ronde</th>
                        <th class="py-2 px-4 border-b text-blue-600">P1 Menang</th>
                        <th class="py-2 px-4 border-b text-green-600">P2 Menang</th>
                    </tr>
                </thead>
                <tbody id="scoreTableBody">
                    </tbody>
            </table>
        </div>
    </div>
    
    <a href="select_role.php?quiz_id=<?= $quiz_id ?>" class="block mt-4 text-center text-blue-600 hover:underline">⬅️ Kembali ke Pemilihan Peran</a>
</div>

<audio id="bgm_player" loop></audio>
<audio id="win_player" src="sound/hore.mp3"></audio>


<div id="resetModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-96 transform scale-95">
        <h3 class="text-xl font-bold text-red-600 mb-3">⚠️ Konfirmasi Reset Game</h3>
        <p class="text-gray-700 mb-6">Anda akan menghapus semua skor ronde dan mengembalikan posisi tali ke awal. Lanjutkan?</p>
        <div class="flex justify-end space-x-4">
            <button type="button" onclick="closeResetModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg">
                Batal
            </button>
            <button type="button" onclick="confirmResetGame()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                Ya, Reset Total!
            </button>
        </div>
    </div>
</div>
<script>
const socket = io("http://localhost:3000");
const quiz_id = "<?= $quiz_id; ?>";
const user_id = "<?= $_SESSION['user_id']; ?>";
const username = "<?= htmlspecialchars($username); ?>"; 

let gameState = 'waiting';

// Audio Elements
const bgmPlayer = document.getElementById('bgm_player');
const winPlayer = document.getElementById('win_player');
const winMessageDiv = document.getElementById('win_message_host');
const resetModal = document.getElementById('resetModal');


// --- FUNGSI MODAL BARU ---
function openResetModal() {
    resetModal.style.display = 'flex';
}

function closeResetModal() {
    resetModal.style.display = 'none';
}

function confirmResetGame() {
    closeResetModal();
    // Panggil logika reset yang sebenarnya di sini
    performReset();
}

function performReset() {
    stopAllAudio();
    winMessageDiv.classList.add('hidden');
    
    document.getElementById("status").innerText = "🔄 Game direset!";
    document.getElementById('startGameBtn').innerText = "▶️ Tombol PLAY (Menunggu P1 & P2)";
    document.getElementById('startGameBtn').disabled = true;
    
    socket.emit("reset_game", { sessionId: quiz_id });
}
// --- AKHIR FUNGSI MODAL BARU ---


// --- FUNGSI AUDIO ---
function playBGM(file) {
    bgmPlayer.src = 'sound/' + file;
    bgmPlayer.play().catch(e => console.error("BGM Play Error:", e));
}

function stopAllAudio() {
    bgmPlayer.pause();
    bgmPlayer.currentTime = 0;
    winPlayer.pause();
    winPlayer.currentTime = 0;
}

function playWinAudio() {
    stopAllAudio();
    winPlayer.play().catch(e => console.error("Win Audio Play Error:", e));
}

function selectBGM() {
    const selectedFile = document.getElementById('bgm_select').value;
    socket.emit('select_bgm', { sessionId: quiz_id, bgmFile: selectedFile });
    document.getElementById('status').innerText = `Musik ${selectedFile} dipilih.`;
}

// --- FUNGSI SKOR ---
function renderScoreTable(roundScores) {
    const tbody = document.getElementById('scoreTableBody');
    tbody.innerHTML = '';
    
    if (Object.keys(roundScores).length === 0) {
         tbody.innerHTML = '<tr><td colspan="3" class="py-2 px-4 text-center text-gray-500 italic">Belum ada ronde yang selesai.</td></tr>';
         return;
    }

    Object.keys(roundScores).sort((a, b) => a - b).forEach(round => {
        const winner = roundScores[round];
        let p1Status = '';
        let p2Status = '';
        
        if (winner === 'player1') {
            p1Status = '✅';
        } else if (winner === 'player2') {
            p2Status = '✅';
        } else if (winner === 'tie') {
            p1Status = p2Status = '➖';
        }

        const row = `
            <tr class="text-center border-b hover:bg-gray-50">
                <td class="py-2 px-4">${round}</td>
                <td class="py-2 px-4 font-bold text-blue-600">${p1Status}</td>
                <td class="py-2 px-4 font-bold text-green-600">${p2Status}</td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', row);
    });
}


// --- SOCKET LISTENERS ---
socket.on("connect", () => {
    socket.emit("join_session", {
        sessionId: quiz_id,
        userId: user_id,
        deviceName: "HOST - " + username,
        role: "host",
        ownerUsername: username
    });

    socket.emit("choose_role", {
        sessionId: quiz_id,
        role: 'host',
        username: username,
        userId: user_id
    });
    
    setInterval(() => { socket.emit("request_roles_status", { sessionId: quiz_id }); }, 2000); 
});

// Menerima skor ronde kumulatif
socket.on('update_round_scores', (roundScores) => {
    renderScoreTable(roundScores);
});


socket.on("update_tug", (data) => {
    animateRope(data.position);
    
    const pulls = data.pulls || { player1:0, player2:0 };
    document.getElementById('status').innerText = `Tarikan: P1: ${pulls.player1} | P2: ${pulls.player2}`;
});

socket.on("status_update", (data) => {
    if (gameState !== 'round_finished') {
        document.getElementById("status").innerText = data.status;
    }
});

socket.on('game_state_change', (data) => {
    gameState = data.state;
    const startBtn = document.getElementById('startGameBtn');
    document.getElementById('round_number').innerText = data.round || 0;
    
    winMessageDiv.classList.add('hidden');
    stopAllAudio();

    if (gameState === 'playing') {
         startBtn.disabled = true;
         startBtn.innerText = 'Game Sedang Berlangsung (Ronde ' + data.round + ')...';
         if (data.bgm) playBGM(data.bgm);
         
    } else if (gameState === 'round_finished') {
         startBtn.disabled = false; 
         startBtn.innerText = '▶️ NEXT ROUND (Ronde ' + (data.round + 1) + ')'; 
         animateRope(50);
         playWinAudio();
         
    } else if (gameState === 'waiting') {
         startBtn.innerText = '▶️ Tombol PLAY (Menunggu P1 & P2)';
         startBtn.disabled = true;
         animateRope(50);
    }
});


socket.on("roles_update", (roles) => {
    const p1Dot = document.getElementById('p1_status_dot');
    const p2Dot = document.getElementById('p2_status_dot');
    const startBtn = document.getElementById('startGameBtn');

    if (roles.player1) {
        p1Dot.classList.replace('bg-red-500', 'bg-green-500');
        p1Dot.classList.remove('opacity-0'); 
    } else {
        p1Dot.classList.replace('bg-green-500', 'bg-red-500');
        p1Dot.classList.add('opacity-0'); 
    }

    if (roles.player2) {
        p2Dot.classList.replace('bg-red-500', 'bg-green-500');
        p2Dot.classList.remove('opacity-0'); 
    } else {
        p2Dot.classList.replace('bg-green-500', 'bg-red-500');
        p2Dot.classList.add('opacity-0'); 
    }

    if ((gameState === 'waiting' || gameState === 'round_finished') && roles.player1 && roles.player2) {
        startBtn.disabled = false;
        startBtn.innerText = gameState === 'waiting' ? `▶️ Tombol PLAY (Ronde 1)` : `▶️ NEXT ROUND (Ronde ${parseInt(document.getElementById('round_number').innerText) + 1})`;
    } else if (gameState === 'waiting') {
        startBtn.disabled = true;
        startBtn.innerText = "▶️ Tombol PLAY (Menunggu P1 & P2)";
    }
});

socket.on("game_over", (data) => {
    renderScoreTable(data.roundScores); 
    
    const winnerName = data.winner === 'tie' ? 'SERI' : data.winner.toUpperCase();
    const roundNumber = document.getElementById('round_number').innerText;
    
    const message = data.winner === 'tie' ? 
        `🎉 RONDE ${roundNumber} BERAKHIR SERI! ${data.reason}` :
        `🎉 SELAMAT KEPADA ${winnerName}! TELAH MEMENANGKAN RONDE ${roundNumber}.`;
    
    winMessageDiv.innerText = message;
    winMessageDiv.classList.remove('hidden');
    
    document.getElementById("status").innerText = data.reason; 
    document.getElementById('startGameBtn').disabled = true;
});


// PENTING: Fungsi resetGame() sekarang hanya memicu modal
function resetGame() {
    openResetModal();
}

function startGame() {
    const startBtn = document.getElementById('startGameBtn');
    if (startBtn.disabled) return;
    
    selectBGM(); 

    socket.emit("start_game", { sessionId: quiz_id, userId: user_id });
    startBtn.disabled = true;
    
    if (gameState === 'round_finished') {
         startBtn.innerText = 'Memulai Ronde Baru...';
         animateRope(50);
    } else {
         startBtn.innerText = 'Game Sedang Berlangsung...';
    }
}

function animateRope(position) {
    const rope = document.getElementById("rope");
    const arenaWidth = rope.parentElement.offsetWidth; 
    
    const minLeft = arenaWidth * 0.20; 
    const maxLeft = arenaWidth * 0.80; 
    
    const finalLeft = minLeft + (position / 100) * (maxLeft - minLeft);

    rope.style.transition = "left 0.7s ease"; 
    rope.style.left = `${finalLeft}px`;
    rope.style.transform = "translateX(-50%)";
}
</script>