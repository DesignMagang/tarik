<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// PENTING: Gunakan user_id sebagai kunci unik sesi kuis
$quiz_id = $_SESSION['user_id']; 

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pilih Peran - Tarik Tambang Kuis</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-yellow-100 via-orange-100 to-red-200 flex flex-col items-center justify-center font-[Poppins]">

<div class="bg-white/90 backdrop-blur-md p-8 rounded-3xl shadow-xl border border-yellow-200 w-[500px] text-center">
    <h1 class="text-2xl font-bold text-orange-700 mb-4">🎮 Pilih Peran untuk Kuis</h1>
    <p class="text-gray-600 mb-6">Kuis ID: <span class="font-semibold text-orange-600"><?= htmlspecialchars($quiz_id); ?></span> (Privat)</p>

    <div id="status" class="mb-4 text-sm text-gray-600">Menyambungkan ke server...</div>
    <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4"></div>

    <div class="flex flex-col space-y-4">
        <button id="btnHost" onclick="pilihRole('host')" class="role-btn bg-gradient-to-r from-yellow-400 to-yellow-500 hover:opacity-90 text-white font-semibold py-2 rounded-xl shadow-md transition">🖥️ Jadi Host</button>
        <button id="btnP1" onclick="pilihRole('player1')" class="role-btn bg-gradient-to-r from-green-400 to-green-500 hover:opacity-90 text-white font-semibold py-2 rounded-xl shadow-md transition">👤 Jadi Pemain 1</button>
        <button id="btnP2" onclick="pilihRole('player2')" class="role-btn bg-gradient-to-r from-blue-400 to-blue-500 hover:opacity-90 text-white font-semibold py-2 rounded-xl shadow-md transition">👤 Jadi Pemain 2</button>
    </div>

    <a href="main_game.php" class="block mt-6 text-blue-600 hover:underline">⬅️ Kembali</a>
</div>

<script>
const socket = io("http://localhost:3000");
const user_id = "<?= $user_id; ?>";
const username = "<?= htmlspecialchars($username); ?>";
const quiz_id = user_id; // Kunci sesi sekarang adalah user_id
const errorMessageDiv = document.getElementById("error-message");

socket.on("connect", () => {
    document.getElementById("status").innerText = "🟢 Terhubung ke server! Memuat status peran...";
    
    // PENTING: Kirim userId sebagai sessionId dan ownerUsername
    socket.emit("join_session", {
        sessionId: quiz_id, 
        userId: user_id,
        deviceName: username,
        ownerUsername: username // PENTING: Kirim ownerUsername
    });
    
    socket.emit("request_roles_status", { sessionId: quiz_id });
});
socket.on("disconnect", () => {
    document.getElementById("status").innerText = "🔴 Terputus dari server.";
});

// Listener untuk akses ditolak (Jika akun B atau C mencoba masuk)
socket.on('role_taken', (data) => {
    let message = `Peran **${data.role.toUpperCase()}** sudah diambil oleh **${data.username}**.`;
    
    if (data.reason === 'Akses Dibatasi') {
        message = `🔒 Sesi ini hanya dapat dimainkan oleh pengguna **${data.username}** (akun lain). Akses ditolak.`;
    }
    
    errorMessageDiv.innerHTML = message;
    errorMessageDiv.classList.remove('hidden');
    setTimeout(() => { errorMessageDiv.classList.add('hidden'); }, 8000);
});

socket.on("roles_update", (data) => {
    const updateButton = (id, roleName, statusUser) => {
        const btn = document.getElementById(id);
        if (!btn) return;
        
        if (statusUser !== null) {
            btn.disabled = true;
            btn.innerText = `✅ ${roleName} (Terisi)`;
            btn.classList.add("opacity-70");
        } else {
            btn.disabled = false;
            btn.innerText = `👤 Jadi ${roleName}`;
            btn.classList.remove("opacity-70");
        }
        
        if (statusUser === username) {
             btn.innerText = `✅ Anda (${roleName})`;
             btn.disabled = false;
        }
    };

    updateButton("btnHost", "Host", data.host);
    updateButton("btnP1", "Pemain 1", data.player1);
    updateButton("btnP2", "Pemain 2", data.player2);
});

// Fungsi pilih role (TANPA DELAY/setTimeout)
function pilihRole(role) {
    socket.emit("choose_role", { sessionId: quiz_id, role, username, userId: user_id }); // Kirim userId

    let target = "";
    if (role === "host") {
        target = "game_host.php?quiz_id=" + quiz_id;
    } else {
        target = "game_player.php?quiz_id=" + quiz_id + "&role=" + role;
    }
    
    window.location.href = target;
}
</script>
</body>
</html>