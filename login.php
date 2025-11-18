<?php
session_start();
include 'db_connect.php';

$error_message = ""; // Variabel untuk menyimpan pesan error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. Prepared Statement
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username=?");
    // Tambahkan error checking jika prepare gagal
    if (!$stmt) {
        $error_message = "Kesalahan internal server saat menyiapkan query.";
    } else {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 2. Verifikasi Password Aman
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                // TIDAK PERLU close() di sini, karena kita akan redirect
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Password salah!";
            }
        } else {
            $error_message = "Username tidak ditemukan!";
        }
        $stmt->close();
    }
    // $conn->close(); // Pindah ke akhir skrip jika diperlukan
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Tarik Tambang Kuis Alkitab</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-md w-96">
        <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required class="w-full mb-3 p-2 border rounded-md">
            <input type="password" name="password" placeholder="Password" required class="w-full mb-5 p-2 border rounded-md">
            <button type="submit" class="w-full bg-green-500 text-white py-2 rounded-md hover:bg-green-600">Login</button>
        </form>
        <p class="text-center mt-4 text-sm">Belum punya akun? <a href="register.php" class="text-blue-600">Daftar</a></p>
    </div>
</body>
</html>
<?php 
// Pastikan koneksi ditutup hanya setelah semua eksekusi selesai
if (isset($conn)) {
    $conn->close();
}
?>