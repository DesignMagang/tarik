<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['quiz_admin_access'])) { 
    header("Location: secure_create_quiz.php"); 
    exit();
}

require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$quiz_id = $_GET['quiz_id'] ?? $user_id; 

$questions_by_round = [];
$total_rounds_db = 0;
$total_questions_db = 0;
$message = "";

// --- LOGIKA MUAT DATA SOAL ---
try {
    $stmt_select = $conn->prepare("SELECT id, question_text, correct_answer, type, round_number FROM questions WHERE user_id = ? ORDER BY round_number ASC, id ASC");
    $stmt_select->bind_param("i", $user_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();

    if ($result) {
        while ($q = $result->fetch_assoc()) {
            $round_num = (int)$q['round_number'];
            if (!isset($questions_by_round[$round_num])) {
                $questions_by_round[$round_num] = [];
            }
            $questions_by_round[$round_num][] = $q;
            $total_rounds_db = max($total_rounds_db, $round_num);
            $total_questions_db++;
        }
    }
    $stmt_select->close();
} catch (Exception $e) {
    $message = "❌ Error saat memuat data: " . $e->getMessage();
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Pertanyaan Kuis</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
/* Kustomisasi Estetika Dark Theme */
.dark-bg {
    background-color: #1f2937; /* gray-800 */
}
.dark-card {
    background-color: #374151; /* gray-700 */
}
.dark-text {
    color: #f3f4f6; /* gray-100 */
}
.dark-input {
    background-color: #4b5563; /* gray-600 */
    border-color: #6b7280; /* gray-500 */
    color: #f3f4f6;
}
.btn-primary {
    background-color: #3b82f6;
    color: white;
}
.btn-primary:hover {
    background-color: #2563eb;
}
</style>
</head>
<body class="min-h-screen dark-bg dark-text flex flex-col items-center py-10 font-[Poppins]">

<div class="w-full max-w-4xl p-4">
    <div class="dark-card shadow-2xl p-6 rounded-xl border border-gray-700">
        
        <h1 class="text-3xl font-extrabold text-white mb-6 text-center">
            📋 Daftar Pertanyaan Kuis Anda
        </h1>
        
        <?php if ($message): ?>
            <div class="p-4 rounded-xl shadow-md mb-4 text-sm text-center bg-red-700 text-white">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <div id="roundsListContainer" class="space-y-6">
            <?php if (!empty($questions_by_round)): ?>
                <?php foreach ($questions_by_round as $round_num => $questions): ?>
                    <div class="round-summary dark-card p-4 rounded-xl border border-gray-600">
                        <div class="flex justify-between items-center cursor-pointer" onclick="toggleCollapse(this)">
                            <h3 class="text-xl font-bold flex items-center">
                                <span class="arrow-icon mr-2">&#x25BC;</span> Ronde <?= $round_num; ?> (<?= count($questions); ?> Soal)
                            </h3>
                        </div>
                        
                        <div class="questions-table-container mt-3" style="display: block;">
                            <table class="min-w-full text-sm mt-2">
                                <thead class="bg-gray-600">
                                    <tr>
                                        <th class="py-2 px-2 text-left">#</th>
                                        <th class="py-2 px-2 text-left">Pertanyaan</th>
                                        <th class="py-2 px-2 text-left">Tipe</th>
                                        <th class="py-2 px-2 text-left">Jawaban</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($questions as $index => $q): ?>
                                    <tr class="border-b border-gray-700 hover:bg-gray-600">
                                        <td class="py-2 px-2 text-gray-400"><?= $index + 1; ?></td>
                                        <td class="py-2 px-2"><?= htmlspecialchars(substr($q['question_text'], 0, 70)); ?>...</td>
                                        <td class="py-2 px-2 text-yellow-400"><?= ucfirst($q['type']); ?></td>
                                        <td class="py-2 px-2 font-semibold text-green-400"><?= $q['correct_answer']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-400 italic text-center p-6">Anda belum memiliki pertanyaan tersimpan.</p>
            <?php endif; ?>
        </div>
        
        <div class="mt-8 flex justify-center space-x-4 p-4 border-t border-gray-700 pt-6">
            <a href="create_quiz.php" class="btn-primary px-6 py-3 rounded-xl font-bold flex items-center justify-center">
                ⬅️ Kembali ke Pembuatan Soal
            </a>
            <a href="select_role.php?quiz_id=<?= $user_id ?>" class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 px-6 py-3 rounded-xl font-bold flex items-center justify-center">
                ▶️ Mulai Mainkan
            </a>
        </div>
    </div>
</div>

<script>
function toggleCollapse(headerElement) {
    const list = headerElement.nextElementSibling;
    const arrow = headerElement.querySelector('.arrow-icon');
    if (list.style.display === "none") {
        list.style.display = "block";
        arrow.innerHTML = '&#x25BC;';
    } else {
        list.style.display = "none";
        arrow.innerHTML = '&#x25BA;';
    }
}
// Panggil toggleCollapse pada setiap header ronde saat dimuat
document.addEventListener('DOMContentLoaded', () => {
    const headers = document.querySelectorAll('.round-summary > div:first-child');
    headers.forEach(header => {
        // Biarkan hanya ronde pertama yang terbuka secara default
        if (header.parentElement.querySelector('.questions-table-container').style.display !== 'block') {
             toggleCollapse(header); 
        }
    });
});
</script>
</body>
</html>