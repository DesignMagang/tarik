<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'db_connect.php';

$quiz_id = $_GET['quiz_id'] ?? null;
if (!$quiz_id) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = $_POST['question_text'];
    $question_type = $_POST['question_type'];
    $option_a = $_POST['option_a'] ?? null;
    $option_b = $_POST['option_b'] ?? null;
    $option_c = $_POST['option_c'] ?? null;
    $option_d = $_POST['option_d'] ?? null;
    $correct_answer = $_POST['correct_answer'];
    $time_limit = $_POST['time_limit'];

    $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, time_limit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssi", $quiz_id, $question_text, $question_type, $option_a, $option_b, $option_c, $option_d, $correct_answer, $time_limit);
    $stmt->execute();
    $stmt->close();

    $success = "Pertanyaan berhasil ditambahkan!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Tambah Pertanyaan</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-200 flex justify-center items-center min-h-screen">
<div class="bg-white p-8 rounded-2xl shadow-md w-[500px]">
    <h1 class="text-2xl font-bold mb-4 text-blue-600">Tambah Pertanyaan</h1>
    <?php if (!empty($success)): ?>
        <p class="text-green-600 font-medium mb-3"><?= $success ?></p>
    <?php endif; ?>

    <form method="POST">
        <label class="block mb-2 font-semibold text-gray-700">Teks Pertanyaan</label>
        <textarea name="question_text" required class="w-full p-2 border rounded-lg mb-4"></textarea>

        <label class="block mb-2 font-semibold text-gray-700">Tipe Pertanyaan</label>
        <select name="question_type" id="question_type" class="w-full p-2 border rounded-lg mb-4" onchange="toggleOptions()">
            <option value="multiple_choice">Pilihan Ganda</option>
            <option value="true_false">True / False</option>
        </select>

        <div id="mc_options">
            <label class="block mb-2 font-semibold text-gray-700">Opsi Jawaban</label>
            <input type="text" name="option_a" placeholder="Opsi A" class="w-full p-2 border rounded-lg mb-2">
            <input type="text" name="option_b" placeholder="Opsi B" class="w-full p-2 border rounded-lg mb-2">
            <input type="text" name="option_c" placeholder="Opsi C" class="w-full p-2 border rounded-lg mb-2">
            <input type="text" name="option_d" placeholder="Opsi D" class="w-full p-2 border rounded-lg mb-4">
        </div>

        <label class="block mb-2 font-semibold text-gray-700">Jawaban Benar</label>
        <input type="text" name="correct_answer" placeholder="Contoh: A / True" required class="w-full p-2 border rounded-lg mb-4">

        <label class="block mb-2 font-semibold text-gray-700">Batas Waktu (detik)</label>
        <select name="time_limit" class="w-full p-2 border rounded-lg mb-4">
            <option>10</option><option>20</option><option>30</option><option>40</option><option>50</option>
        </select>

        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 rounded-lg transition">💾 Simpan Pertanyaan</button>
        <a href="dashboard.php" class="block mt-4 text-center text-blue-600 hover:underline">⬅️ Kembali ke Dashboard</a>
    </form>
</div>

<script>
function toggleOptions() {
    const type = document.getElementById('question_type').value;
    document.getElementById('mc_options').style.display = type === 'multiple_choice' ? 'block' : 'none';
}
</script>
</body>
</html>
