<?php
$host = '127.0.0.1:3307';
$user = 'root';
$pass = '';
$dbname = 'tarik';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
