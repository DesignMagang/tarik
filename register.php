<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Cek apakah username sudah ada
    $check = $conn->prepare("SELECT * FROM users WHERE username=?");
    $check->bind_param("s", $username);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Username sudah terdaftar!'); window.location='register.php';</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $password);

        if ($stmt->execute()) {
            echo "<script>alert('Registrasi berhasil! Silakan login.'); window.location='login.php';</script>";
        } else {
            echo "Terjadi kesalahan: " . $stmt->error;
        }
        $stmt->close();
    }

    $check->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Akun - Tarik Tambang Kuis Alkitab</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-md w-96">
        <h2 class="text-2xl font-bold mb-6 text-center">Daftar Akun</h2>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required class="w-full mb-3 p-2 border rounded-md">
            <input type="password" name="password" placeholder="Password" required class="w-full mb-5 p-2 border rounded-md">
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600">Daftar</button>
        </form>
        <p class="text-center mt-4 text-sm">Sudah punya akun? <a href="login.php" class="text-blue-600">Login</a></p>
    </div>
</body>
</html>
