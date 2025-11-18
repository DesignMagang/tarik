<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$quiz_id = $_GET['quiz_id'] ?? 'global';
$role = $_GET['role'] ?? 'player1';

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pemain - Tarik Tambang Kuis</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<style>
/* CSS Tambahan untuk feedback visual */
.bg-correct { background-color: #10B981 !important; }
.bg-wrong { background-color: #EF4444 !important; }
.bg-blue-500 { background-color: #3B82F6; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-blue-100 to-blue-200 flex flex-col items-center justify-center font-[Poppins]">

<div class="bg-white/90 backdrop-blur-lg shadow-xl p-8 rounded-3xl w-[550px] border border-blue-200">
    <h1 class="text-2xl font-bold text-blue-700 text-center mb-2">🎮 <?= ucfirst($role); ?> - Tarik Tambang Kuis</h1>
    <p class="text-center text-gray-600 mb-6">Ronde: <span id="round_number" class="font-bold text-red-600">0</span></p>

    <div id="game-area">
        <div id="question-box" class="text-center mb-6">
            <p id="question-text" class="text-xl font-bold text-gray-800">Menunggu Host memulai kuis...</p>
        </div>
        <div id="options" class="grid grid-cols-2 gap-3">
            </div>
    </div>

    <div id="status" class="mt-4 text-center text-sm text-gray-600 font-semibold">🔄 Menyambungkan ke server...</div>

    <a href="select_role.php?quiz_id=<?= $quiz_id ?>" class="block text-center mt-4 text-blue-600 hover:underline">⬅️ Kembali</a>
</div>

<audio id="bgm_player" loop></audio>
<audio id="win_player" src="sound/hore.mp3"></audio>


<div id="audio_modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-white p-8 rounded-xl text-center shadow-2xl">
        <p class="text-xl font-semibold mb-4">🎵 Tekan Lanjut untuk Mengizinkan Audio</p>
        <button id="audio_start_btn" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
            Lanjut ke Permainan
        </button>
    </div>
</div>
<script>
const socket = io("http://localhost:3000");
const quiz_id = "<?= $quiz_id; ?>";
const user_id = "<?= $_SESSION['user_id']; ?>";
const username = "<?= htmlspecialchars($username); ?>";
const role = "<?= $role; ?>";
let currentQuestionId = null;
let lastClickedButton = null; 
let isAudioInitialized = false; // Flag untuk melacak izin audio

// Audio Elements
const bgmPlayer = document.getElementById('bgm_player');
const winPlayer = document.getElementById('win_player');
const audioModal = document.getElementById('audio_modal');
const audioStartBtn = document.getElementById('audio_start_btn');


// --- FUNGSI AUDIO KRITIS ---
// Fungsi ini menjadi sangat penting dan harus bekerja
function stopAllAudio() {
    try {
        if (!bgmPlayer.paused) {
            bgmPlayer.pause();
        }
        bgmPlayer.currentTime = 0; // Reset posisi

        if (!winPlayer.paused) {
            winPlayer.pause();
        }
        winPlayer.currentTime = 0; // Reset posisi
    } catch (e) {
        console.error("Gagal menghentikan audio:", e);
    }
}

function playBGM(file) {
    if (!isAudioInitialized) {
        console.warn("Audio belum diinisialisasi melalui interaksi user. BGM mungkin diblokir.");
        return;
    }
    stopAllAudio(); // Pastikan berhenti sebelum ganti sumber
    bgmPlayer.src = 'sound/' + file;
    bgmPlayer.load(); // Load sumber baru
    bgmPlayer.play().catch(e => console.warn("BGM Play Error:", e));
}

function playWinAudio() {
    if (!isAudioInitialized) return;
    stopAllAudio();
    winPlayer.play().catch(e => console.warn("Win Audio Play Error:", e));
}

// PENTING: Mendapatkan izin audio setelah klik
audioStartBtn.addEventListener('click', () => {
    // Set sumber audio dummy (atau sumber apapun) untuk inisialisasi
    bgmPlayer.src = 'sound/hore.mp3'; 
    bgmPlayer.load();

    // Memutar dan menghentikan audio sekali untuk mendapatkan izin penuh dari browser
    bgmPlayer.play().then(() => {
        bgmPlayer.pause();
        bgmPlayer.currentTime = 0;
        isAudioInitialized = true; // Set flag berhasil
        audioModal.style.display = 'none';
        console.log("✅ Izin audio berhasil didapatkan.");
    }).catch(e => {
        isAudioInitialized = false; // Jika gagal, BGM mungkin tetap tidak berfungsi
        audioModal.style.display = 'none'; 
        console.warn("⚠️ Gagal mendapatkan izin putar audio pertama. BGM mungkin tidak akan berfungsi.", e);
    });
});
// --- AKHIR LOGIKA AUDIO BARU ---


socket.on("connect", () => {
    document.getElementById("status").innerText = "🟢 Terhubung ke server!";
    socket.emit("join_session", { sessionId: quiz_id, userId: user_id, deviceName: username + " (" + role + ")", role: role });
    socket.emit("choose_role", { sessionId: quiz_id, role: role, username: username });
});

socket.on("status_update", (data) => {
    document.getElementById("status").innerText = data.status;
});

// PENTING: Listener untuk status ronde dan reset tampilan
socket.on('game_state_change', (data) => {
    document.getElementById('round_number').innerText = data.round || 0;
    
    // --- STOP AUDIO SAAT TRANSISI STATE (Menghentikan BGM yang sedang berjalan) ---
    stopAllAudio(); 
    // --- END STOP AUDIO ---
    
    // Reset tampilan pertanyaan
    document.getElementById("question-text").classList.remove('text-green-700', 'text-red-700');


    if (data.state === 'waiting' || data.state === 'round_finished') {
        lastClickedButton = null; 
        document.getElementById('question-text').innerText = 'Menunggu Host memulai game.';
        document.getElementById('options').innerHTML = '';
        document.getElementById('status').innerText = `Ronde ${data.round} selesai. Tunggu Host untuk ronde berikutnya.`;
    } else if (data.state === 'playing' && data.bgm) {
         playBGM(data.bgm); // Putar BGM baru
    }
});


socket.on("question", (q) => {
    currentQuestionId = q.id;
    lastClickedButton = null; 
    showQuestion(q);
});

socket.on('request_next_question', (data) => {
    // Tunda sebentar agar player bisa melihat feedback.
    setTimeout(() => { socket.emit('next_question', { sessionId: quiz_id }); }, 500); 
});

function showQuestion(q) {
    document.getElementById("question-text").innerText = q.question_text;
    const optionsDiv = document.getElementById("options");
    optionsDiv.innerHTML = "";
    
    const options = [];
    const isTrueFalse = q.type === 'truefalse'; 

    if (isTrueFalse) {
        options.push({ key: "TRUE", text: "BENAR" }); 
        options.push({ key: "FALSE", text: "SALAH" });
    } else {
        if (q.option_a) options.push({ key: "A", text: q.option_a });
        if (q.option_b) options.push({ key: "B", text: q.option_b });
        if (q.option_c) options.push({ key: "C", text: q.option_c });
        if (q.option_d) options.push({ key: "D", text: q.option_d });
    }

    options.forEach(opt => {
        const btn = document.createElement("button");
        btn.className = "bg-blue-500 hover:opacity-90 text-white font-semibold py-3 rounded-xl shadow-md transition";
        
        if (isTrueFalse) {
            btn.innerText = opt.text; 
        } else {
            btn.innerText = opt.key + ". " + opt.text; 
        }
        
        btn.onclick = () => kirimJawaban(q.id, opt.key, btn); 
        btn.disabled = false;
        optionsDiv.appendChild(btn);
    });
    
    document.getElementById("options").querySelectorAll('button').forEach(b => b.disabled = false);
    document.getElementById("status").innerText = "Silakan Jawab Pertanyaan di Atas.";
}

// Fungsi kirimJawaban menerima elemen tombol yang diklik
function kirimJawaban(questionId, selected, clickedElement) { 
    // PENCEGAHAN KRITIS
    if (questionId !== currentQuestionId) {
        document.getElementById("status").innerText = "⚠️ Soal ini sudah tidak aktif di sesi Anda. Tunggu soal berikutnya.";
        document.getElementById("options").querySelectorAll('button').forEach(b => b.disabled = true);
        return; 
    }
    
    // --- PERBAIKAN KRITIS: Stop BGM saat jawaban dikirim (INI YANG PALING PENTING) ---
    stopAllAudio(); 
    // --- AKHIR PERBAIKAN KRITIS ---

    lastClickedButton = clickedElement; // Simpan elemen tombol yang diklik
    
    // NON-AKTIFKAN tombol segera setelah mengirim jawaban
    document.getElementById("options").querySelectorAll('button').forEach(b => b.disabled = true);
    
    const data = { sessionId: quiz_id, questionId, selected, userId: user_id, role };

    socket.emit("submit_answer", data);

    document.getElementById("status").innerText = "⏳ Jawaban Anda sedang diproses oleh server...";
}

// PENTING: Listener answer_feedback diperbarui untuk visual
socket.on("answer_feedback", (data) => {
    const statusDiv = document.getElementById("status");
    
    if (data.accepted) {
        // Jawaban Benar
        statusDiv.className = "mt-4 text-center text-sm font-bold text-green-700";
        // Ganti warna tombol yang diklik menjadi Hijau
        if (lastClickedButton) {
            lastClickedButton.classList.add('bg-correct');
            lastClickedButton.classList.remove('bg-blue-500');
        }
    } else {
        // Jawaban Salah
        statusDiv.className = "mt-4 text-center text-sm font-bold text-red-700";
        // Ganti warna tombol yang diklik menjadi Merah
        if (lastClickedButton) {
            lastClickedButton.classList.add('bg-wrong');
            lastClickedButton.classList.remove('bg-blue-500');
        }
    }
    statusDiv.innerText = data.reason; 
});

socket.on("game_over", (data) => {
    stopAllAudio(); // Hentikan BGM
    playWinAudio(); // Putar audio kemenangan
    
    const statusDiv = document.getElementById("status");
    const questionText = document.getElementById("question-box").querySelector('p');
    
    document.getElementById("options").innerHTML = "";
    
    const winnerMessage = (data.winner === role) 
        ? "🎉 SELAMAT! ANDA MEMENANGKAN RONDE INI!" 
        : "😢 MAAF! LAWAN ANDA MEMENANGKAN RONDE INI.";
    const loserMessage = (data.winner === role) 
        ? "" : "Tetap semangat kawan!";
    
    // Tampilkan pesan hasil ronde di questionText
    questionText.innerText = `${winnerMessage} ${loserMessage}\n(Hasil akhir: P1=${data.pulls.player1}, P2=${data.pulls.player2})`;

    questionText.className = "text-xl font-extrabold text-center mb-6 " + (data.winner === role ? "text-green-700" : "text-red-700");
    
    statusDiv.innerText = data.reason;
    statusDiv.className = "mt-4 text-center text-sm font-bold " + (data.winner === role ? "text-green-700" : "text-red-700");
});
</script>
</body>
</html>