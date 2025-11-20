<?php
session_start();
require_once 'db_connect.php';

$id = $_GET['id'] ?? null;
if (!$id) { 
    header("Location: create_quiz.php"); 
    exit(); 
}

// Pengecekan Akses Admin Persisten
if (!isset($_SESSION['user_id']) || !isset($_SESSION['quiz_admin_access'])) { 
    header("Location: secure_create_quiz.php"); 
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$current_data = null; // Inisialisasi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_id = $_POST['id'];
    $question_text = $_POST['question_text'];
    $type = $_POST['type'];
    $correct_answer = $_POST['correct_answer'];

    // VALIDASI OPSI KOSONG (Poin 3)
    $option_a = $_POST['option_a'];
    $option_b = $_POST['option_b'];
    $option_c = $_POST['option_c'] ?? null;
    $option_d = $_POST['option_d'] ?? null;

    $options = ['A' => $option_a, 'B' => $option_b, 'C' => $option_c, 'D' => $option_d];
    
    if (isset($options[$correct_answer]) && empty(trim($options[$correct_answer])) && $type !== 'truefalse') {
        $success_message = "❌ Gagal memperbarui! Jawaban benar ('{$correct_answer}') tidak boleh kosong.";
    } else {
        // PROSES UPDATE KE DB
        if ($type === 'truefalse') {
            $option_a = 'TRUE';
            $option_b = 'FALSE';
            $option_c = null;
            $option_d = null;
        } 

        // PENTING: Tambahkan user_id ke WHERE clause untuk keamanan
        $stmt = $conn->prepare("UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, type=? WHERE id=? AND user_id=?");
        $stmt->bind_param("ssssssisi", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $type, $question_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "✅ Pertanyaan ID " . htmlspecialchars($question_id) . " berhasil diperbarui!";
        } else {
            $success_message = "❌ Gagal memperbarui pertanyaan ID " . htmlspecialchars($question_id) . ": " . $conn->error;
        }
        $stmt->close();
    }
    
    $conn->close();
    
    // PENTING: Pola PRG - Selalu redirect ke halaman daftar (create_quiz.php)
    $_SESSION['management_message'] = $success_message;
    header("Location: create_quiz.php"); 
    exit();
}

// --- LOGIKA GET: Tampilan Form Edit ---

// Ambil data pertanyaan untuk form (Tambahkan user_id ke WHERE clause untuk keamanan)
$stmt_select = $conn->prepare("SELECT * FROM questions WHERE id = ? AND user_id = ?");
$stmt_select->bind_param("ii", $id, $user_id);
$stmt_select->execute();
$q = $stmt_select->get_result()->fetch_assoc();
$stmt_select->close();
$conn->close();

if (!$q) {
    // Jika ID tidak ditemukan atau bukan milik user ini, kembali ke daftar
    header("Location: create_quiz.php");
    exit();
}

$current_data = json_encode($q);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Edit Pertanyaan #<?= htmlspecialchars($id); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-100 via-blue-200 to-blue-300 flex flex-col items-center justify-center py-10 font-[Poppins]">

<div class="w-full max-w-xl p-4">
    <h1 class="text-3xl font-extrabold text-blue-700 mb-6 text-center">✍️ Edit Pertanyaan #<?= htmlspecialchars($id); ?></h1>

    <?php if ($message): ?>
        <div class="bg-white/90 p-4 rounded-xl shadow-md mb-4 text-center text-sm <?= str_contains($message, '✅') ? 'text-green-600' : 'text-red-600'; ?>">
            <?= $message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white/90 backdrop-blur-lg shadow-xl p-8 rounded-3xl border border-blue-200">
        <form method="POST" class="space-y-4">
            <input type="hidden" name="id" value="<?= htmlspecialchars($id); ?>">
            
            <textarea name="question_text" id="questionText" placeholder="Tulis pertanyaan di sini..." class="w-full p-3 border rounded-xl" required></textarea>
            
            <select name="type" id="typeSelect" class="w-full p-2 border rounded-lg">
                <option value="multiple">Pilihan Ganda</option>
                <option value="truefalse">True / False</option>
            </select>
            
            <div id="optionFields" class="grid grid-cols-2 gap-3">
                <input id="optA" name="option_a" placeholder="Pilihan A" class="border p-2 rounded-lg" required>
                <input id="optB" name="option_b" placeholder="Pilihan B" class="border p-2 rounded-lg" required>
                <input id="optC" name="option_c" placeholder="Pilihan C (opsional)" class="border p-2 rounded-lg">
                <input id="optD" name="option_d" placeholder="Pilihan D (opsional)" class="border p-2 rounded-lg">
            </div>

            <select name="correct_answer" id="correctAnswerSelect" class="w-full p-2 border rounded-lg" required>
                </select>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-xl transition">
                💾 Simpan Perubahan
            </button>
        </form>
    </div>

    <div class="mt-8 text-center">
        <a href="create_quiz.php" class="text-blue-600 hover:underline font-semibold">⬅️ Kembali ke Daftar Pertanyaan</a>
    </div>
</div>

<script>
    const typeSelect = document.getElementById('typeSelect');
    const optionFields = document.getElementById('optionFields');
    const correctAnswerSelect = document.getElementById('correctAnswerSelect');
    const questionText = document.getElementById('questionText');
    const inputA = document.getElementById('optA');
    const inputB = document.getElementById('optB');
    const inputC = document.querySelector('input[name="option_c"]');
    const inputD = document.querySelector('input[name="option_d"]');
    
    const initialData = <?= $current_data; ?>;
    let currentCorrectAnswer = initialData.correct_answer;

    function preFillForm() {
        questionText.value = initialData.question_text;
        typeSelect.value = initialData.type;
        inputA.value = initialData.option_a || '';
        inputB.value = initialData.option_b || '';
        inputC.value = initialData.option_c || '';
        inputD.value = initialData.option_d || '';
        
        updateForm();
    }

    function updateForm() {
        const type = typeSelect.value;
        let optionsHTML = '';
        
        if (type === 'truefalse') {
            optionsHTML = `
                <option value="TRUE">BENAR</option>
                <option value="FALSE">SALAH</option>
            `;
        } else {
            optionsHTML = `
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            `;
        }
        
        correctAnswerSelect.innerHTML = optionsHTML;
        
        const currentOption = document.querySelector(`#correctAnswerSelect option[value="${currentCorrectAnswer}"]`);
        if (currentOption) {
            currentOption.selected = true;
        } else {
            correctAnswerSelect.insertAdjacentHTML('afterbegin', `<option value="" selected>Pilih Ulang Jawaban</option>`);
        }

        if (type === 'truefalse') {
            optionFields.style.display = 'none';
            inputA.required = false;
            inputB.required = false;
        } else {
            optionFields.style.display = 'grid';
            inputA.required = true;
            inputB.required = true;
        }
    }

    typeSelect.addEventListener('change', updateForm);
    document.addEventListener('DOMContentLoaded', preFillForm);
</script>

</body>
</html>