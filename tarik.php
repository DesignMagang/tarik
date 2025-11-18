<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard - Tarik Tambang Kuis Alkitab</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-100 via-blue-200 to-blue-300 flex flex-col justify-center items-center font-[Poppins]">

<div class="bg-white/90 backdrop-blur-lg shadow-xl p-10 rounded-3xl w-[380px] text-center border border-blue-200 transition-transform hover:scale-[1.02]">
    <h1 class="text-3xl font-extrabold text-blue-700 mb-3">🎮 Tarik Tambang Kuis</h1>
    <p class="text-gray-600 mb-8">Halo, <span class="font-semibold text-blue-600"><?= htmlspecialchars($_SESSION['username']); ?></span> 👋<br>Selamat datang kembali!</p>

    <div class="flex flex-col space-y-4">
        <a href="secure_create_quiz.php" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white py-2 rounded-xl shadow-md font-semibold hover:opacity-90 transition">
            ✏️ Buat Kuis (Perlu PIN)
        </a>
        <a href="main_game.php" class="bg-gradient-to-r from-green-500 to-green-600 text-white py-2 rounded-xl shadow-md font-semibold hover:opacity-90 transition">
            🕹️ Main Game
        </a>
        <a href="logout.php" class="text-red-600 hover:underline mt-2 font-medium">Logout</a>
    </div>

    <div id="status" class="mt-6 text-sm text-gray-600">🔄 Menyambungkan ke server...</div>
</div>

<script>
const socket = io("http://localhost:3000");
const user_id = "<?= $_SESSION['user_id']; ?>";
const username = "<?= htmlspecialchars($_SESSION['username']); ?>";

// Gabung ke session akun
socket.emit("join_session", {
    sessionId: user_id,
    userId: user_id,
    deviceName: username + " - " + navigator.userAgent
});

socket.on("connect", () => {
    document.getElementById("status").innerHTML = "🟢 Terhubung ke WebSocket server!";
    document.getElementById("status").classList.replace("text-gray-600", "text-green-600");
});
socket.on("disconnect", () => {
    document.getElementById("status").innerHTML = "🔴 Terputus dari server.";
    document.getElementById("status").classList.replace("text-green-600", "text-red-600");
});
</script>
</body>
</html>