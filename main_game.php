<?php
session_start();
// Memastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Tidak perlu lagi require_once 'db_connect.php' di sini
// Tidak perlu lagi query untuk mengambil daftar pertanyaan

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username']; 
$quiz_id = $_GET['quiz_id'] ?? 'global'; // Mengambil quiz_id jika ada
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Main Game Lobby - Tarik Tambang Kuis</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 flex flex-col items-center justify-center font-[Poppins]">

<div class="w-full max-w-4xl p-4">
    <h1 class="text-3xl font-extrabold text-blue-700 mb-2 text-center">🏆 Tarik Tambang Kuis Alkitab</h1>
    <p class="text-gray-600 mb-8 text-center">Halo, <?= htmlspecialchars($username); ?>. Siap memulai kuis?</p>

    <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200 text-center">
        <h2 class="text-2xl font-bold text-gray-700 mb-4">Mulai Pertandingan</h2>
        <p class="text-gray-500 mb-6">Pilih peran Anda (Host, Player 1, atau Player 2) untuk memulai sesi kuis.</p>
        
        <a href="select_role.php?quiz_id=<?= htmlspecialchars($quiz_id); ?>"
           class="inline-block bg-green-500 hover:bg-green-600 text-white px-8 py-3 rounded-xl shadow-md font-bold transition transform hover:scale-105">
            Mulai Game Tarik Tambang Sekarang
        </a>
    </div>

    <div class="mt-8 text-center">
        <a href="dashboard.php" class="text-blue-600 hover:underline font-semibold">⬅️ Kembali ke Dashboard</a>
    </div>
</div>

</body>
</html>