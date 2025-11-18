<?php
// buat_kuis.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    // insert quiz
    $stmt = $conn->prepare("INSERT INTO quizzes (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $title, $description);
    $stmt->execute();
    $quiz_id = $stmt->insert_id;
    $stmt->close();

    // expect questions as arrays: question_text[], question_type[], time_limit[]
    // and for options: options[question_index] => array of option_text
    if (!empty($_POST['question_text'])) {
        foreach ($_POST['question_text'] as $i => $qtext) {
            $qtext = trim($qtext);
            $qtype = $_POST['question_type'][$i];
            $tlimit = intval($_POST['time_limit'][$i]) ?: 20;

            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, question_type, time_limit) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $quiz_id, $qtext, $qtype, $tlimit);
            $stmt->execute();
            $question_id = $stmt->insert_id;
            $stmt->close();

            // options for this question
            if ($qtype === 'multiple_choice' && !empty($_POST['options'][$i])) {
                foreach ($_POST['options'][$i] as $optIndex => $optText) {
                    $optText = trim($optText);
                    $is_correct = (isset($_POST['correct'][$i]) && $_POST['correct'][$i] == $optIndex) ? 1 : 0;
                    $stmt = $conn->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $optText, $is_correct);
                    $stmt->execute();
                    $stmt->close();
                }
            } elseif ($qtype === 'true_false') {
                // store True and False as options; expect $_POST['correct'][$i] = 'True' or 'False'
                foreach (['True','False'] as $opt) {
                    $is_correct = (isset($_POST['correct'][$i]) && $_POST['correct'][$i] === $opt) ? 1 : 0;
                    $stmt = $conn->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $opt, $is_correct);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    echo "<script>alert('Kuis berhasil dibuat'); window.location='dashboard.php';</script>";
    exit();
}
?>

<!-- Sederhana: form manual. Untuk kenyamanan, nanti bisa ditingkatkan dengan JS dinamis -->
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Buat Kuis</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-6">
  <div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-xl font-bold mb-4">Buat Kuis</h1>
    <form method="post" action="">
      <label class="block mb-2">Judul</label>
      <input name="title" required class="w-full mb-3 p-2 border rounded" />

      <label class="block mb-2">Deskripsi</label>
      <textarea name="description" class="w-full mb-3 p-2 border rounded"></textarea>

      <!-- Contoh 1 soal (kamu bisa copy-block untuk tambah soal) -->
      <div class="mb-4 border p-3 rounded">
        <label class="block font-medium">Soal 1</label>
        <textarea name="question_text[]" required class="w-full mb-2 p-2 border rounded"></textarea>

        <label class="block mb-1">Tipe</label>
        <select name="question_type[]" class="mb-2 p-2 border rounded">
          <option value="multiple_choice">Pilihan Ganda</option>
          <option value="true_false">True / False</option>
        </select>

        <label class="block mb-1">Time limit (detik)</label>
        <input type="number" name="time_limit[]" value="20" class="mb-2 p-2 border rounded" />

        <!-- Opsi (example untuk multiple choice) -->
        <label class="block mb-1">Opsi (pilihan ganda):</label>
        <input name="options[0][]" placeholder="Opsi A" class="w-full mb-1 p-2 border rounded" />
        <input name="options[0][]" placeholder="Opsi B" class="w-full mb-1 p-2 border rounded" />
        <input name="options[0][]" placeholder="Opsi C (opsional)" class="w-full mb-1 p-2 border rounded" />
        <input name="options[0][]" placeholder="Opsi D (opsional)" class="w-full mb-1 p-2 border rounded" />

        <label class="block mt-2">Jawaban benar:
          <!-- untuk multiple choice gunakan index (0..n), untuk true_false gunakan 'True'/'False' -->
          <input name="correct[0]" placeholder="index (mis: 0) atau True/False" class="w-full mt-1 p-2 border rounded" />
        </label>
      </div>

      <button class="bg-blue-600 text-white px-4 py-2 rounded">Simpan Kuis</button>
    </form>
  </div>
</body>
</html>
