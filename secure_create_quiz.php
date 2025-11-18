<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'db_connect.php';

$error_message = "";
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_pin = $_POST['admin_pin'];

    // Ambil PIN Hash dari database untuk pengguna yang sedang login
    $stmt = $conn->prepare("SELECT quiz_pin_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Pengecekan: Jika PIN Hash ditemukan dan PIN yang dimasukkan sesuai
    // Catatan: Jika quiz_pin_hash kosong, Anda mungkin ingin ini menjadi proses pembuatan PIN pertama.
    if ($user && isset($user['quiz_pin_hash']) && $user['quiz_pin_hash'] !== null && $user['quiz_pin_hash'] !== '') {
        if (password_verify($input_pin, $user['quiz_pin_hash'])) {
            // PIN Benar! Beri izin dan redirect ke halaman pembuatan kuis yang sebenarnya
            $_SESSION['quiz_admin_access'] = true;
            header("Location: create_quiz.php");
            exit();
        } else {
            $error_message = "PIN salah. Akses ditolak.";
        }
    } else {
        // Jika belum ada PIN, anggap input ini adalah PIN baru (setup awal)
        $new_pin_hash = password_hash($input_pin, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET quiz_pin_hash = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_pin_hash, $user_id);
        
        if ($update_stmt->execute()) {
             $error_message = "✅ PIN berhasil dibuat! Silakan coba lagi untuk masuk.";
             $_SESSION['quiz_admin_access'] = true;
             header("Location: create_quiz.php");
             exit();
        } else {
            $error_message = "❌ Gagal menyimpan PIN baru.";
        }
        $update_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Akses Admin Kuis</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-100 via-blue-200 to-blue-300 flex items-center justify-center font-[Poppins]">

<div class="bg-white/90 backdrop-blur-lg shadow-xl p-8 rounded-3xl w-[400px] border border-blue-200 text-center">
    <h1 class="text-2xl font-bold text-blue-700 mb-4">🔒 Akses Admin Kuis</h1>
    <p class="text-gray-600 mb-6">Masukkan PIN rahasia Anda untuk membuat/mengedit kuis.</p>
    
    <?php if ($error_message): ?>
        <div class="mb-4 text-center text-sm <?= str_contains($error_message, '✅') ? 'text-green-600' : 'text-red-600'; ?>">
            <?= $error_message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="secure_create_quiz.php" class="space-y-4">
        <input type="password" name="admin_pin" placeholder="Masukkan PIN" class="w-full p-3 border rounded-xl text-center" required>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-xl transition">
            Verifikasi PIN
        </button>
    </form>
    
    <?php if ($user && (!isset($user['quiz_pin_hash']) || $user['quiz_pin_hash'] === null || $user['quiz_pin_hash'] === '')): ?>
        <p class="mt-4 text-sm text-yellow-600">Catatan: PIN yang Anda masukkan pertama kali akan disimpan sebagai PIN Admin Anda.</p>
    <?php endif; ?>

    <a href="tarik.php" class="block mt-4 text-sm text-gray-600 hover:underline">⬅️ Kembali</a>
</div>

</body>
</html>