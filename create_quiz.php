<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['quiz_admin_access'])) { 
    header("Location: secure_create_quiz.php"); 
    exit();
}

require_once 'db_connect.php';

$message = "";
// Default values untuk retensi form
$post_data = ['question_text' => '', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'type' => 'multiple', 'correct_answer' => ''];
$user_id = $_SESSION['user_id'];

// --- TANGANI PESAN DARI SESI ---
if (isset($_SESSION['management_message'])) {
    $message = $_SESSION['management_message'];
    unset($_SESSION['management_message']);
}

// --- 1. LOGIKA POST (TAMBAH PERTANYAAN BARU) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_success = false;
    
    // Ambil data POST dan simpan untuk retensi jika gagal
    foreach ($_POST as $key => $value) {
        $post_data[$key] = htmlspecialchars(trim($value));
    }
    
    $question_text = $post_data['question_text'];
    $type = $post_data['type'];
    $correct_answer = $post_data['correct_answer'];
    
    $option_a = $post_data['option_a'];
    $option_b = $post_data['option_b'];
    $option_c = $post_data['option_c'] ?? null;
    $option_d = $post_data['option_d'] ?? null;

    // VALIDASI OPSI KOSONG (Poin 3)
    $options_check = ['A' => $option_a, 'B' => $option_b, 'C' => $option_c, 'D' => $option_d];
    
    if (isset($options_check[$correct_answer]) && empty(trim($options_check[$correct_answer])) && $type !== 'truefalse') {
        $message = "❌ Gagal menambahkan pertanyaan! Jawaban benar ('{$correct_answer}') tidak boleh kosong.";
        // Jika gagal, data POST dipertahankan untuk retensi
    } else {
        // PROSES SIMPAN KE DB JIKA VALIDASI BERHASIL
        if ($type === 'truefalse') {
            $option_a = 'TRUE';
            $option_b = 'FALSE';
            $option_c = null;
            $option_d = null;
        } 
        
        // PENTING: TAMBAHKAN user_id ke INSERT query
        $stmt = $conn->prepare("INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, type, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        // Asumsi user_id adalah INT (i)
        $stmt->bind_param("sssssssi", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $type, $user_id);

        if ($stmt->execute()) {
            $message = "✅ Pertanyaan berhasil ditambahkan!";
            $is_success = true;
        } else {
            $message = "❌ Gagal menambahkan pertanyaan: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Pola PRG diterapkan di sini: Jika sukses, REDIRECT agar data POST hilang.
    if ($is_success) {
        $_SESSION['management_message'] = $message;
        header("Location: create_quiz.php");
        exit();
    }
}

// --- 2. LOGIKA UNTUK MENAMPILKAN DAFTAR PERTANYAAN ---
$all_questions = [];
// PENTING: FILTER DAFTAR PERTANYAAN BERDASARKAN user_id
$stmt_select = $conn->prepare("SELECT id, question_text, option_a, option_b, correct_answer, type FROM questions WHERE user_id = ? ORDER BY id DESC");
$stmt_select->bind_param("i", $user_id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result) {
    $all_questions = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt_select->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen Pertanyaan Kuis</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-100 via-blue-200 to-blue-300 flex flex-col items-center py-10 font-[Poppins]">

<div class="w-full max-w-4xl p-4">
    <h1 class="text-3xl font-extrabold text-blue-700 mb-6 text-center">📝 Manajemen Pertanyaan Kuis</h1>

    <?php if ($message): ?>
        <div class="bg-white/90 p-4 rounded-xl shadow-md mb-4 text-center text-sm <?= str_contains($message, '✅') ? 'text-green-600' : 'text-red-600'; ?>">
            <?= $message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white/90 backdrop-blur-lg shadow-xl p-8 rounded-3xl border border-blue-200 mb-10">
        <h2 class="text-xl font-bold text-gray-700 mb-4">➕ Tambah Pertanyaan Baru</h2>
        
        <form method="POST" class="space-y-4">
            <textarea name="question_text" placeholder="Tulis pertanyaan di sini..." class="w-full p-3 border rounded-xl" required><?= htmlspecialchars($post_data['question_text']); ?></textarea>
            
            <select id="typeSelect" name="type" class="w-full p-2 border rounded-lg">
                <option value="multiple" <?= $post_data['type'] == 'multiple' ? 'selected' : ''; ?>>Pilihan Ganda</option>
                <option value="truefalse" <?= $post_data['type'] == 'truefalse' ? 'selected' : ''; ?>>True / False</option>
            </select>
            
            <div id="optionFields" class="grid grid-cols-2 gap-3">
                <input id="optA" name="option_a" value="<?= $post_data['option_a']; ?>" placeholder="Pilihan A (TRUE)" class="border p-2 rounded-lg" required>
                <input id="optB" name="option_b" value="<?= $post_data['option_b']; ?>" placeholder="Pilihan B (FALSE)" class="border p-2 rounded-lg" required>
                <input id="optC" name="option_c" value="<?= $post_data['option_c']; ?>" placeholder="Pilihan C (opsional)" class="border p-2 rounded-lg">
                <input id="optD" name="option_d" value="<?= $post_data['option_d']; ?>" placeholder="Pilihan D (opsional)" class="border p-2 rounded-lg">
            </div>

            <select id="correctAnswerSelect" name="correct_answer" class="w-full p-2 border rounded-lg" required>
                </select>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-xl transition">
                ➕ Tambah Pertanyaan
            </button>
        </form>
    </div>

    <div class="bg-white/90 backdrop-blur-lg shadow-xl p-8 rounded-3xl border border-blue-200">
        <h2 class="text-xl font-bold text-gray-700 mb-4">📋 Daftar Pertanyaan (Total: <?= count($all_questions); ?>)</h2>
        
        <?php if (count($all_questions) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-xl overflow-hidden shadow">
                    <thead class="bg-blue-500 text-white">
                        <tr>
                            <th class="py-2 px-3 text-left">ID</th>
                            <th class="py-2 px-3 text-left">Pertanyaan</th>
                            <th class="py-2 px-3 text-left">Jawaban Benar</th>
                            <th class="py-2 px-3 text-left">Tipe</th>
                            <th class="py-2 px-3 text-center" colspan="2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_questions as $q): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-3"><?= $q['id']; ?></td>
                            <td class="py-2 px-3 text-sm"><?= htmlspecialchars(substr($q['question_text'], 0, 50)) . (strlen($q['question_text']) > 50 ? '...' : ''); ?></td>
                            <td class="py-2 px-3 font-semibold text-green-600"><?= $q['correct_answer']; ?></td>
                            <td class="py-2 px-3"><?= ucfirst($q['type']); ?></td>
                            <td class="py-2 px-3 text-center">
                                <a href="edit_question.php?id=<?= $q['id']; ?>" class="text-blue-500 hover:underline text-sm">Edit</a>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <button type="button" 
                                   onclick="openDeleteModal(<?= $q['id']; ?>)"
                                   class="text-red-500 hover:underline text-sm">Hapus</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">Belum ada pertanyaan yang ditambahkan.</p>
        <?php endif; ?>
    </div>

    <div class="mt-8 text-center">
        <a href="tarik.php" class="text-blue-600 hover:underline font-semibold">⬅️ Kembali ke Menu Tarik Tambang</a>
    </div>

</div>

<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-96 transform scale-95 transition-transform duration-300">
        <h3 class="text-xl font-bold text-red-600 mb-3">⚠️ Konfirmasi Penghapusan</h3>
        <p class="text-gray-700 mb-6">Apakah Anda yakin ingin menghapus pertanyaan dengan ID: <span id="modalQuestionId" class="font-bold"></span>?</p>
        <div class="flex justify-end space-x-4">
            <button type="button" onclick="closeDeleteModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg">
                Batal
            </button>
            <a href="#" id="confirmDeleteButton" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                Ya, Hapus!
            </a>
        </div>
    </div>
</div>
<script>
    // PENTING: JavaScript membaca nilai $post_data untuk retensi
    const lastCorrectAnswer = "<?= $post_data['correct_answer']; ?>";
    const lastType = "<?= $post_data['type']; ?>";

    const typeSelect = document.getElementById('typeSelect');
    const optionFields = document.getElementById('optionFields');
    const correctAnswerSelect = document.getElementById('correctAnswerSelect');

    const inputA = document.getElementById('optA');
    const inputB = document.getElementById('optB');
    const inputC = document.querySelector('input[name="option_c"]');
    const inputD = document.querySelector('input[name="option_d"]');

    function updateForm() {
        const type = typeSelect.value;
        let optionsHTML = '';
        
        if (type === 'truefalse') {
            optionFields.style.display = 'none';
            inputA.required = false;
            inputB.required = false;
            
            optionsHTML = `
                <option value="">-- Pilih Jawaban Benar --</option>
                <option value="TRUE">BENAR</option>
                <option value="FALSE">SALAH</option>
            `;
        } else {
            optionFields.style.display = 'grid';
            inputA.required = true;
            inputB.required = true;
            
            optionsHTML = `
                <option value="">-- Pilih Jawaban Benar --</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            `;
        }
        
        correctAnswerSelect.innerHTML = optionsHTML;

        // 2. RETENSI DATA JAWABAN BENAR
        if (lastCorrectAnswer) {
            const selectedOption = document.querySelector(`#correctAnswerSelect option[value="${lastCorrectAnswer}"]`);
            if (selectedOption) {
                selectedOption.selected = true;
            }
        }
        
        // 3. Set placeholder
        if (type === 'truefalse') {
            inputA.placeholder = "Opsi A (TRUE)";
            inputB.placeholder = "Opsi B (FALSE)";
            inputC.placeholder = "Pilihan C (Abaikan)";
            inputD.placeholder = "Pilihan D (Abaikan)";
        } else {
            inputA.placeholder = "Pilihan A";
            inputB.placeholder = "Pilihan B";
            inputC.placeholder = "Pilihan C (opsional)";
            inputD.placeholder = "Pilihan D (opsional)";
        }
    }

    typeSelect.addEventListener('change', updateForm);
    
    // PENTING: Set nilai typeSelect dari POST data saat pertama kali load
    document.addEventListener('DOMContentLoaded', () => {
        typeSelect.value = lastType; 
        updateForm();
    });
    
    // --- Logika Modal ---
    const deleteModal = document.getElementById('deleteModal');
    const modalQuestionId = document.getElementById('modalQuestionId');
    const confirmDeleteButton = document.getElementById('confirmDeleteButton');

    function openDeleteModal(id) {
        modalQuestionId.innerText = id;
        confirmDeleteButton.href = 'delete_question.php?id=' + id;
        deleteModal.style.display = 'flex';
    }

    function closeDeleteModal() {
        deleteModal.style.display = 'none';
    }
</script>

</body>
</html>