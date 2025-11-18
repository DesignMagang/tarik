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

// Pastikan koneksi dan query berjalan
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

    <div class="bg-white p-6 rounded-xl shadow-xl border border-gray-200">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-700">Status Game</h2>
            <div id="status" class="text-lg font-semibold text-gray-600">Menunggu pemain...</div>
        </div>
        
        <div id="win_message_host" class="mt-4 mb-4 text-center hidden p-3 rounded-lg border-2 border-green-400 bg-green-50 text-green-800 font-bold"></div>

        <div class="flex justify-around items-center space-x-4">
            <button id="startGameBtn" onclick="startGame()" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-5 py-3 rounded-xl shadow-md font-bold transition disabled:bg-gray-400" disabled>
                ▶️ Tombol PLAY
            </button>
            <button onclick="resetGame()" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-5 py-3 rounded-xl shadow-md font-bold transition">
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
        
        <a href="select_role.php?quiz_id=<?= $quiz_id ?>" class="block mt-4 text-center text-blue-600 hover:underline">⬅️ Kembali ke Pemilihan Peran</a>
    </div>

</div>

<audio id="bgm_player" loop></audio>
<audio id="win_player" src="sound/hore.mp3"></audio>


<script>
const socket = io("http://localhost:3000");
const quiz_id = "<?= $quiz_id; ?>";
const user_id = "<?= $_SESSION['user_id']; ?>";
const username = "<?= htmlspecialchars($username); ?>"; 

const ALL_QUESTIONS = <?= json_encode($all_questions); ?>;
let gameState = 'waiting';

// Audio Elements
const bgmPlayer = document.getElementById('bgm_player');
const winPlayer = document.getElementById('win_player');
const winMessageDiv = document.getElementById('win_message_host');


// --- FUNGSI AUDIO ---
function playBGM(file) {
    bgmPlayer.src = 'sound/' + file;
    // PENTING: play() hanya akan bekerja jika ada interaksi pengguna (UX browser)
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


// --- SOCKET LISTENERS ---
socket.on("connect", () => {
    socket.emit("join_session", { sessionId: quiz_id, userId: user_id, deviceName: "HOST - " + username, role: "host" });
    socket.emit("choose_role", { sessionId: quiz_id, role: 'host', username: username });
    setInterval(() => { socket.emit("request_roles_status", { sessionId: quiz_id }); }, 2000); 
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

// PENTING: Update status ronde dan tombol
socket.on('game_state_change', (data) => {
    gameState = data.state;
    const startBtn = document.getElementById('startGameBtn');
    document.getElementById('round_number').innerText = data.round || 0;
    
    winMessageDiv.classList.add('hidden'); // Sembunyikan pesan saat transisi state
    stopAllAudio(); // Hentikan semua audio saat transisi state

    if (gameState === 'playing') {
         startBtn.disabled = true;
         startBtn.innerText = 'Game Sedang Berlangsung (Ronde ' + data.round + ')...';
         if (data.bgm) playBGM(data.bgm); // Putar BGM yang dipilih
         
    } else if (gameState === 'round_finished') {
         startBtn.disabled = false; 
         startBtn.innerText = '▶️ NEXT ROUND (Ronde ' + (data.round + 1) + ')'; 
         animateRope(50); // Reset Visual Tali ke Tengah saat Ronde Selesai
         playWinAudio(); // Putar audio kemenangan
         
    } else if (gameState === 'waiting') {
         startBtn.innerText = '▶️ Tombol PLAY (Menunggu P1 & P2)';
         startBtn.disabled = true;
         animateRope(50); // Reset Visual Tali ke Tengah
    }
});


// Update daftar peran yang terhubung dan TITIK STATUS
socket.on("roles_update", (roles) => {
    const p1Dot = document.getElementById('p1_status_dot');
    const p2Dot = document.getElementById('p2_status_dot');
    const startBtn = document.getElementById('startGameBtn');

    // Visual ON/OFF
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

    // Aktivasi tombol PLAY/NEXT ROUND
    if (gameState === 'waiting' && roles.player1 && roles.player2) {
        startBtn.disabled = false;
        startBtn.innerText = `▶️ Tombol PLAY (Ronde 1)`;
    } else if (gameState === 'waiting') {
        startBtn.disabled = true;
        startBtn.innerText = "▶️ Tombol PLAY (Menunggu P1 & P2)";
    }
});

socket.on("game_over", (data) => {
    const winnerName = data.winner.toUpperCase();
    const roundNumber = document.getElementById('round_number').innerText;
    
    // Tampilkan pesan kemenangan PERMANEN di Host
    const message = `🎉 SELAMAT KEPADA ${winnerName}! TELAH MEMENANGKAN RONDE ${roundNumber}.`;
    winMessageDiv.innerText = message;
    winMessageDiv.classList.remove('hidden');
    
    // Status utama hanya untuk ringkasan
    document.getElementById("status").innerText = data.reason; 
    document.getElementById('startGameBtn').disabled = true;
});


function startGame() {
    const startBtn = document.getElementById('startGameBtn');
    if (startBtn.disabled) return;
    
    // PENTING: Kirim event untuk memilih BGM saat ini ke server sebelum start game
    selectBGM(); 

    socket.emit("start_game", { sessionId: quiz_id });
    startBtn.disabled = true;
    
    if (gameState === 'round_finished') {
         startBtn.innerText = 'Memulai Ronde Baru...';
         animateRope(50); // Reset Visual Tali ke Tengah (Di sini lebih responsif)
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

function resetGame() {
    stopAllAudio(); // Hentikan semua audio saat reset
    winMessageDiv.classList.add('hidden'); // Sembunyikan pesan kemenangan
    
    document.getElementById("status").innerText = "🔄 Game direset!";
    document.getElementById('startGameBtn').innerText = "▶️ Tombol PLAY (Menunggu P1 & P2)";
    document.getElementById('startGameBtn').disabled = true;
    
    socket.emit("reset_game", { sessionId: quiz_id });
}
</script>