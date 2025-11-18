<?php
session_start();
// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Cek Akses PIN Admin
if (!isset($_SESSION['quiz_admin_access'])) { 
    header("Location: secure_create_quiz.php"); 
    exit();
}

require_once 'db_connect.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->bind_param("i", $id); 
    
    if ($stmt->execute()) {
        $message = "✅ Pertanyaan ID " . htmlspecialchars($id) . " berhasil dihapus!";
    } else {
        $message = "❌ Gagal menghapus pertanyaan: " . $conn->error;
    }
    $stmt->close();
} else {
    $message = "❌ ID Pertanyaan tidak valid.";
}

// Gunakan variabel pesan umum dan redirect (Poin 2)
$_SESSION['management_message'] = $message;
header("Location: create_quiz.php");
exit();
?>