<?php
session_start();
// Memastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Game - Pilih Permainan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-blue-100 to-blue-200 flex flex-col items-center justify-center font-[Poppins]">

    <div class="bg-white/90 backdrop-blur-lg shadow-xl p-8 rounded-3xl border border-blue-200 w-[600px] text-center">
        <h1 class="text-3xl font-extrabold text-blue-700 mb-2">Selamat Datang, <?= htmlspecialchars($username); ?>!</h1>
        <p class="text-gray-600 mb-8">Pilih permainan yang ingin Anda mainkan:</p>

        <div class="grid grid-cols-2 gap-6">
            
            <a href="tarik.php" 
               class="block p-5 bg-yellow-400 hover:bg-yellow-500 text-white font-bold rounded-xl shadow-lg transition transform hover:scale-105">
                <span class="text-4xl block mb-2">💪</span>
                <!-- <span class="text-xl">Tarik Tambang Kuis Alkitab</span> -->
                <span class="text-xl">Dia milikku bukan milikmu</span>

                <span class="block text-sm mt-1 opacity-80">(Kuis Real-time)</span>
            </a>

            <a href="lari.php" 
               class="block p-5 bg-red-400 hover:bg-red-500 text-white font-bold rounded-xl shadow-lg transition transform hover:scale-105">
                <span class="text-4xl block mb-2">🏃‍♂️</span>
                <span class="text-xl">Lari Karung</span>
                <span class="block text-sm mt-1 opacity-80">(Kuis Real-time)</span>
            </a>
            
        </div>   

        <div class="mt-8">
            <a href="logout.php" class="text-red-500 hover:text-red-700 hover:underline font-semibold">
                ➡️ Logout
            </a>
        </div>
    </div>

</body>
</html>