<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['quiz_admin_access'])) { 
    header("Location: secure_create_quiz.php"); 
    exit();
}

require_once 'db_connect.php';

$message = "";
$user_id = $_SESSION['user_id'];

// Variabel untuk menahan pesan dari SESSION (untuk PRG)
if (isset($_SESSION['management_message'])) {
    $message = $_SESSION['management_message'];
    unset($_SESSION['management_message']);
}

// Data yang dimuat dari POST (gagal) atau DB (tersimpan)
$current_rounds_data = [];
$total_rounds_db = 1;
$total_questions_db = 0;

// --- 1. LOGIKA UTAMA: BATCH SAVE & VALIDASI KRITIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_success = false;
    $rounds_data = $_POST['rounds'] ?? [];
    $total_rounds = (int)($_POST['total_rounds'] ?? 1);
    $valid_questions_count = 0;
    
    if ($total_rounds < 1) {
        $message = "❌ Jumlah ronde minimal adalah 1.";
    } elseif (empty($rounds_data)) {
        $message = "❌ Tidak ada ronde atau soal yang terdeteksi untuk disimpan.";
    } else {
        $conn->begin_transaction();
        
        try {
            // Hapus semua soal lama milik user ini sebelum menyimpan yang baru
            $stmt_delete = $conn->prepare("DELETE FROM questions WHERE user_id = ?");
            $stmt_delete->bind_param("i", $user_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // INSERT query
            $stmt_insert = $conn->prepare("INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, type, user_id, round_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssssssii", $text, $op_a, $op_b, $op_c, $op_d, $correct, $type, $user_id, $round);

            $round_counter = 1;

            foreach ($rounds_data as $round_content) {
                if (!isset($round_content['questions']) || empty($round_content['questions'])) continue;
                
                foreach ($round_content['questions'] as $q) {
                    $text = htmlspecialchars(trim($q['question_text'] ?? ''));
                    $type = htmlspecialchars(trim($q['type'] ?? 'multiple'));
                    $correct = htmlspecialchars(trim($q['correct_answer'] ?? ''));
                    $round = $round_counter;
                    
                    $op_a = htmlspecialchars(trim($q['option_a'] ?? ''));
                    $op_b = htmlspecialchars(trim($q['option_b'] ?? ''));
                    $op_c = htmlspecialchars(trim($q['option_c'] ?? ''));
                    $op_d = htmlspecialchars(trim($q['option_d'] ?? ''));
                    
                    if (empty($text) || empty($correct)) continue;

                    // Logika True/False (T/F)
                    if ($type === 'truefalse') {
                        if ($correct !== 'TRUE' && $correct !== 'FALSE') continue;
                        $op_a = 'TRUE'; $op_b = 'FALSE'; $op_c = null; $op_d = null;
                    } else {
                        // Validasi Pilihan Ganda
                        $options_check = ['A' => $op_a, 'B' => $op_b, 'C' => $op_c, 'D' => $op_d];
                        if (empty($op_a) || empty($op_b)) continue;
                        if (isset($options_check[$correct]) && empty(trim($options_check[$correct]))) continue;
                    }
                    
                    // Eksekusi
                    if ($stmt_insert->execute()) {
                        $valid_questions_count++;
                    }
                }
                $round_counter++;
            }
            
            if ($valid_questions_count > 0) {
                $conn->commit();
                $message = "✅ Berhasil menyimpan {$valid_questions_count} pertanyaan untuk " . ($round_counter - 1) . " ronde!";
                $is_success = true;
            } else {
                $conn->rollback();
                $message = "❌ Gagal menyimpan. Tidak ada soal yang valid yang terdeteksi.";
            }

            $stmt_insert->close();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Kesalahan transaksi: " . $e->getMessage();
        }
        
        // Tutup koneksi di blok POST
        if ($conn) {
            $conn->close();
        }
    }
    
    // PENTING: Pola PRG - SELALU REDIRECT SETELAH POST (Sukses atau Gagal)
    // Ini memastikan histori browser bersih dan menghilangkan ERR_CACHE_MISS.
    $_SESSION['management_message'] = $message;
    header("Location: create_quiz.php");
    exit();
}


// --- 2. LOGIKA UNTUK MUAT ULANG FORM DARI DB (Mode GET) ---

// Muat data dari Database (Mode GET/Initial Load)
if (!isset($conn) || !$conn->ping()) {
    require 'db_connect.php'; // Coba sambungkan kembali
}

if (isset($conn) && $conn->ping()) {
    $stmt_select = $conn->prepare("SELECT question_text, option_a, option_b, option_c, option_d, correct_answer, type, round_number FROM questions WHERE user_id = ? ORDER BY round_number ASC, id ASC");
    $stmt_select->bind_param("i", $user_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();

    $temp_rounds = [];
    $max_rounds = 1;
    $total_questions_db = 0;
    
    if ($result) {
        while ($q = $result->fetch_assoc()) {
            $round_num = (int)$q['round_number'];
            if (!isset($temp_rounds[$round_num])) {
                $temp_rounds[$round_num] = ['questions' => []];
            }
            $temp_rounds[$round_num]['questions'][] = $q;
            $max_rounds = max($max_rounds, $round_num);
            $total_questions_db++;
        }
    }
    $current_rounds_data = $temp_rounds;
    $total_rounds_db = $max_rounds;
    
    // Tutup koneksi setelah selesai membaca data
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
} 
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Buat Pertanyaan & Ronde</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
/* Kustomisasi Estetika Dark Theme */
.dark-input {
    background-color: #374151; /* gray-700 */
    border-color: #4b5563; /* gray-600 */
    color: #f3f4f6; /* gray-100 */
}
.dark-input:focus {
    border-color: #3b82f6; /* blue-500 */
    box-shadow: 0 0 0 1px #3b82f6;
}
.btn-primary {
    background-color: #3b82f6;
    color: white;
}
.btn-primary:hover {
    background-color: #2563eb;
}
.btn-success {
    background-color: #10b981;
    color: white;
}
.btn-success:hover {
    background-color: #059669;
}
</style>
</head>
<body class="min-h-screen bg-gray-900 text-gray-100 flex flex-col items-center py-10 font-[Poppins]">

<div class="w-full max-w-4xl p-4">
    <div class="bg-gray-800 shadow-2xl p-6 rounded-xl border border-gray-700">
        
        <h1 class="text-3xl font-extrabold text-white mb-4 text-center">
            <span class="text-yellow-400">🏆</span> Buat Pertanyaan & Ronde
        </h1>

        <?php if ($message): ?>
            <div id="statusMessage" class="p-4 rounded-xl shadow-md mb-4 text-sm text-center <?= str_contains($message, '✅') ? 'bg-green-700 text-white' : 'bg-red-700 text-white'; ?>">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="quizForm" class="space-y-6">
            <input type="hidden" id="total_rounds" name="total_rounds" value="<?= $total_rounds_db; ?>">

            <div id="roundsContainer" class="space-y-4">
                </div>

            <div class="mt-8 flex justify-center space-x-4 p-4 border-t border-gray-700 pt-6">
                <button type="button" onclick="addRound(null)" class="btn-success px-6 py-3 rounded-xl font-bold">
                    + Ronde
                </button>
                
                <a href="list_question.php?quiz_id=<?= $user_id ?>" class="btn-primary px-6 py-3 rounded-xl font-bold flex items-center justify-center">
                    Daftar
                </a>
                
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 px-6 py-3 rounded-xl font-bold">
                    💾 Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let roundCounter = 0;
let questionCounterMap = {}; 
const roundsContainer = document.getElementById('roundsContainer');
const totalRoundsInput = document.getElementById('total_rounds');
const initialData = <?= json_encode($current_rounds_data); ?>;


document.addEventListener('DOMContentLoaded', () => {
    // 1. Logic for Transient Success Message
    const statusMessage = document.getElementById('statusMessage');
    if (statusMessage) {
        setTimeout(() => {
            statusMessage.style.display = 'none';
        }, 2000); // Sembunyikan setelah 2 detik
    }
    
    // 2. Muat data yang sudah ada (dari DB atau POST gagal)
    if (Object.keys(initialData).length > 0) {
        Object.keys(initialData).forEach(roundNum => {
            const roundData = initialData[roundNum];
            addRound(roundData.questions, parseInt(roundNum));
        });
    } else {
        addRound(null, 1); // Tambahkan Ronde 1 default
    }
});


function addRound(questionsData = null, roundNum = null) {
    const currentRoundNum = roundNum || ++roundCounter;
    
    if (roundNum === null) {
        roundCounter = currentRoundNum;
    } else if (roundNum > roundCounter) {
        roundCounter = roundNum;
    }
    
    questionCounterMap[currentRoundNum] = 0; 
    
    const roundElements = document.querySelectorAll('.round-item');
    totalRoundsInput.value = roundElements.length + 1; 

    const roundHtml = `
        <div class="round-item bg-gray-700 p-4 rounded-xl border border-gray-600" data-round-num="${currentRoundNum}">
            <div class="flex justify-between items-center cursor-pointer" onclick="toggleCollapse(this)">
                <h2 class="text-xl font-bold flex items-center">
                    <span class="arrow-icon mr-2">&#x25BC;</span> Ronde ${currentRoundNum}
                </h2>
                <div class="space-x-3">
                    ${currentRoundNum > 1 ? `<button type="button" onclick="event.stopPropagation(); removeRound(${currentRoundNum})" class="text-red-400 hover:text-red-300">🗑️ Ronde</button>` : ''}
                </div>
            </div>
            
            <div class="questions-list mt-3 space-y-4" style="display: block;">
                </div>
        </div>
    `;
    roundsContainer.insertAdjacentHTML('beforeend', roundHtml);

    const questionsList = document.querySelector(`.round-item[data-round-num="${currentRoundNum}"] .questions-list`);
    
    if (questionsData && questionsData.length > 0) {
        questionsData.forEach(qData => addQuestionForm(currentRoundNum, qData));
    } else {
        addQuestionForm(currentRoundNum); // Tambahkan 1 soal default
    }
}

function removeRound(roundNum) {
    if (confirm(`Apakah Anda yakin ingin menghapus Ronde ${roundNum} beserta semua soal di dalamnya?`)) {
        const element = document.querySelector(`.round-item[data-round-num="${roundNum}"]`);
        if (element) {
            element.remove();
            const remainingRounds = document.querySelectorAll('.round-item').length;
            totalRoundsInput.value = remainingRounds;
        }
    }
}

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

function addQuestionForm(roundNum, data = null) {
    const questionIndex = questionCounterMap[roundNum] || 0;
    questionCounterMap[roundNum] = questionIndex + 1;
    
    const defaultData = data || {
        question_text: '', correct_answer: 'A', type: 'multiple',
        option_a: '', option_b: '', option_c: '', option_d: ''
    };
    
    const questionsList = document.querySelector(`.round-item[data-round-num="${roundNum}"] .questions-list`);
    const isTrueFalse = defaultData.type === 'truefalse';
    
    const formHtml = `
        <div class="question-item p-4 border border-gray-600 rounded-xl bg-gray-900 shadow-lg" data-round-num="${roundNum}" data-index="${questionIndex}">
            
            <div class="flex justify-between mb-2">
                <select name="rounds[${roundNum}][questions][${questionIndex}][type]" 
                        class="type-selector dark-input p-1 rounded text-sm mr-auto"
                        onchange="updateOptionFields(this, ${roundNum}, ${questionIndex}, true)">
                    <option value="multiple" ${!isTrueFalse ? 'selected' : ''}>Pilihan Ganda</option>
                    <option value="truefalse" ${isTrueFalse ? 'selected' : ''}>True / False</option>
                </select>
                <button type="button" onclick="removeQuestionItem(${roundNum}, ${questionIndex})" class="text-red-400 hover:text-red-300">&times; Hapus</button>
            </div>
            
            <div class="space-y-3">
                <label class="block font-semibold">Pertanyaan:</label>
                <textarea name="rounds[${roundNum}][questions][${questionIndex}][question_text]" 
                          class="w-full p-2 rounded-lg dark-input" 
                          rows="2" required>${defaultData.question_text || ''}</textarea>

                <input type="hidden" name="rounds[${roundNum}][questions][${questionIndex}][correct_answer]" class="correct-answer-input" value="${defaultData.correct_answer}">
                
                <div class="options-container flex flex-col space-y-2" data-round-num="${roundNum}" data-index="${questionIndex}">
                    ${generateOptionInputs(roundNum, questionIndex, isTrueFalse, defaultData)}
                </div>
            </div>
            
            <div class="mt-4 text-right">
                 <button type="button" onclick="addQuestionForm(${roundNum})" class="text-green-400 hover:text-green-300 font-semibold border border-green-600 px-3 py-1 rounded">
                    + Pertanyaan
                 </button>
            </div>
        </div>
    `;
    questionsList.insertAdjacentHTML('beforeend', formHtml);
}

function removeQuestionItem(roundNum, index) {
    const element = document.querySelector(`.question-item[data-round-num="${roundNum}"][data-index="${index}"]`);
    if (element) {
        element.remove();
    }
}

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

function updateOptionFields(selectElement, roundNum, questionIndex, isManualChange) {
    const type = selectElement.value;
    const isTrueFalse = type === 'truefalse';
    const optionsContainer = document.querySelector(`.options-container[data-round-num="${roundNum}"][data-index="${questionIndex}"]`);
    
    let defaultData = {
        question_text: '', correct_answer: isTrueFalse ? 'TRUE' : 'A', type: type,
        option_a: isTrueFalse ? 'TRUE' : '', 
        option_b: isTrueFalse ? 'FALSE' : '',
        option_c: '',
        option_d: ''
    };

    if (isManualChange) {
        setCorrectAnswer(roundNum, questionIndex, isTrueFalse ? 'TRUE' : 'A');
    }
    
    optionsContainer.innerHTML = generateOptionInputs(roundNum, questionIndex, isTrueFalse, defaultData);
}

function generateOptionInputs(roundNum, index, isTrueFalse, data) {
    if (isTrueFalse) {
        // True / False
        const options = [
            { key: 'TRUE', label: 'BENAR' },
            { key: 'FALSE', label: 'SALAH' }
        ];
        return `
            <label class="block font-semibold text-gray-300 pt-2">Jawaban Pilihan:</label>
            <div class="flex space-x-4">
                ${options.map(opt => `
                    <div class="flex items-center p-2 border border-gray-600 rounded-lg bg-gray-700">
                        <input type="radio" 
                            name="correct_radio_${roundNum}_${index}" 
                            value="${opt.key}" 
                            class="form-radio h-4 w-4 text-yellow-400 dark-input mr-2"
                            onclick="setCorrectAnswer(${roundNum}, ${index}, '${opt.key}')"
                            ${data.correct_answer === opt.key ? 'checked' : ''} required>
                        <span class="font-semibold text-gray-200">${opt.label}</span>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        // Pilihan Ganda (A, B, C, D)
        const options = ['A', 'B', 'C', 'D'];
        
        return `
            <label class="block font-semibold text-gray-300 pt-2">Opsi Jawaban:</label>
            <div class="space-y-2">
            ${options.map(optKey => {
                const isRequired = (optKey === 'A' || optKey === 'B');
                const name = `rounds[${roundNum}][questions][${index}][option_${optKey.toLowerCase()}]`;
                const value = data[`option_${optKey.toLowerCase()}`] || '';
                
                return `
                    <div class="flex items-center space-x-2">
                        <input type="radio" 
                            name="correct_radio_${roundNum}_${index}" 
                            value="${optKey}" 
                            class="form-radio h-4 w-4 text-yellow-400 dark-input"
                            onclick="setCorrectAnswer(${roundNum}, ${index}, '${optKey}')"
                            ${data.correct_answer === optKey ? 'checked' : ''} ${isRequired ? 'required' : ''}>
                        <input name="${name}" 
                               value="${value}" 
                               placeholder="Opsi ${optKey}" 
                               class="border p-2 rounded-lg flex-grow dark-input" ${isRequired ? 'required' : ''}>
                    </div>
                `;
            }).join('')}
            </div>
        `;
    }
}

// PENTING: Fungsi untuk mengatur Jawaban Benar dari Radio Button ke input hidden
function setCorrectAnswer(roundNum, index, value) {
    const hiddenInput = document.querySelector(`.question-item[data-round-num="${roundNum}"][data-index="${index}"] .correct-answer-input`);
    if (hiddenInput) {
        hiddenInput.value = value;
    }
}
</script>

</body>
</html>