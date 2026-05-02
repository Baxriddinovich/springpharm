<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../db.php';
requireLogin();
 $user = getCurrentUser();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// URL dan kelgan 'module_id' ni olamiz. 
// Bu yerda 'module_id' nomi URLda qoladi, lekin bazaga 'training_id' deb yozamiz.
 $module_id = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;
if (!$module_id) {
    header("Location: modules.php");
    exit;
}

// Modul ma'lumotlari (bu yerda ham module_id ishlatamiz, chunki training_modules jadvalida id bor)
 $stmt = $pdo->prepare("SELECT * FROM training_modules WHERE id=?");
 $stmt->execute([$module_id]);
 $module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    die("Modul topilmadi.");
}

// ===== SAVOL QO'SHISH / TAHRIRLASH =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: questions.php?module_id=$module_id");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_question') {
        $q_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
        if ($q_id > 0) {
            $pdo->prepare("DELETE FROM training_questions WHERE id=? AND training_id=?")->execute([$q_id, $module_id]);
        }
        header("Location: questions.php?module_id=$module_id");
        exit;
    }

    $question_id = $_POST['question_id'] ?? null;
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_option = $_POST['correct_option']; // bazada correct_option

    if ($question_id) {
        // UPDATE
        $sql = "UPDATE training_questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $question_id]);
    } else {
        // INSERT - E'tibor bering: training_id sifatida $module_id ni yozamiz
        $sql = "INSERT INTO training_questions (training_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$module_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option]);
    }
    
    header("Location: questions.php?module_id=$module_id");
    exit;
}

// ===== SAVOLNI O'CHIRISH =====
// Savollarni olish - training_id orqali qidiramiz
 $stmt = $pdo->prepare("SELECT * FROM training_questions WHERE training_id=? ORDER BY id ASC");
 $stmt->execute([$module_id]);
 $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savollar: <?php echo htmlspecialchars($module['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent-cyan: #06b6d4; --glass-bg: rgba(26, 35, 50, 0.7); --glass-border: rgba(51, 65, 85, 0.5); }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); color: #f1f5f9; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); }
    </style>
</head>
<body class="min-h-screen">

    <!-- Header -->
    <header class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="modules.php" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-white">Test Savollari</h1>
                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($module['title']); ?></p>
                </div>
            </div>
            <button onclick="openModal()" class="bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Yangi Savol
            </button>
        </div>
    </header>

    <main class="container mx-auto p-4 lg:p-8">
        <?php if($questions): ?>
        <div class="space-y-4">
            <?php $num = 1; foreach($questions as $q): ?>
                <div class="glass-card rounded-xl p-5 hover:border-cyan-500/50 transition group relative">
                    <!-- Action Buttons -->
                    <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition flex gap-1">
                        <button onclick="editQuestion(<?php echo htmlspecialchars(json_encode($q)); ?>)" class="p-1.5 bg-slate-800/80 rounded text-slate-300 hover:text-white hover:bg-slate-700" title="Tahrirlash">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </button>
                        <form method="POST" class="inline-block" onsubmit="return confirm('Savolni o\\'chirmoqchimisiz?')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?php echo (int)$q['id']; ?>">
                            <button type="submit" class="p-1.5 bg-red-900/50 rounded text-red-400 hover:text-red-300 hover:bg-red-900/70" title="O'chirish">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>

                    <p class="font-semibold text-white mb-4 pr-16"><span class="text-cyan-400"><?php echo $num++; ?>.</span> <?php echo htmlspecialchars($q['question_text']); ?></p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <?php 
                        // Bazadan kelgan correct_option kichik harfda bo'ladi ('a', 'b', ...)
                        $options = ['a' => $q['option_a'], 'b' => $q['option_b'], 'c' => $q['option_c'], 'd' => $q['option_d']];
                        foreach($options as $key => $val):
                            $isCorrect = $q['correct_option'] == $key;
                        ?>
                            <div class="p-3 rounded-lg border <?php echo $isCorrect ? 'bg-emerald-900/20 border-emerald-500/50 text-emerald-300' : 'bg-slate-800/50 border-slate-700 text-slate-300'; ?>">
                                <span class="font-bold mr-2 uppercase"><?php echo $key; ?>)</span> <?php echo htmlspecialchars($val); ?>
                                <?php if($isCorrect): ?>
                                    <span class="float-right text-xs font-semibold uppercase tracking-wider">To'g'ri</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="glass-card rounded-xl p-16 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="text-slate-400 text-lg">Hozircha savollar mavjud emas</p>
                <p class="text-slate-500 text-sm mt-1">Yuqoridagi "Yangi Savol" tugmasini bosing</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal -->
    <div id="questionModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-slate-900 border border-slate-700 w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh]">
            
            <div class="p-6 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-xl font-bold" id="modalTitle">Yangi Savol Qo'shish</h3>
                <button onclick="closeModal()" class="text-slate-500 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form action="" method="POST" class="p-6 space-y-4 overflow-y-auto">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="question_id" id="question_id">
                
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Savol matni</label>
                    <textarea name="question_text" id="question_text" rows="2" required class="w-full bg-slate-950 border border-slate-700 rounded p-3 text-white text-sm focus:border-cyan-500 outline-none resize-none"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">A) Variant</label>
                        <input type="text" name="option_a" id="option_a" required class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">B) Variant</label>
                        <input type="text" name="option_b" id="option_b" required class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">C) Variant</label>
                        <input type="text" name="option_c" id="option_c" required class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">D) Variant</label>
                        <input type="text" name="option_d" id="option_d" required class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-2">To'g'ri javobni belgilang</label>
                    <div class="flex gap-4">
                        <!-- Qiymatlar kichik harflarda (bazadagi enum ga mos) -->
                        <label class="flex items-center gap-2 cursor-pointer bg-slate-800 px-4 py-2 rounded-lg hover:bg-slate-700 transition">
                            <input type="radio" name="correct_option" value="a" id="ans_a" class="accent-cyan-500" checked> A
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer bg-slate-800 px-4 py-2 rounded-lg hover:bg-slate-700 transition">
                            <input type="radio" name="correct_option" value="b" id="ans_b" class="accent-cyan-500"> B
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer bg-slate-800 px-4 py-2 rounded-lg hover:bg-slate-700 transition">
                            <input type="radio" name="correct_option" value="c" id="ans_c" class="accent-cyan-500"> C
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer bg-slate-800 px-4 py-2 rounded-lg hover:bg-slate-700 transition">
                            <input type="radio" name="correct_option" value="d" id="ans_d" class="accent-cyan-500"> D
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800 mt-4">
                    <button type="button" onclick="closeModal()" class="px-5 py-2 rounded-lg text-slate-300 hover:bg-slate-800 text-sm font-medium transition">Bekor qilish</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium transition">Saqlash</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('questionModal');

        function openModal() {
            document.getElementById('question_id').value = '';
            document.getElementById('question_text').value = '';
            document.getElementById('option_a').value = '';
            document.getElementById('option_b').value = '';
            document.getElementById('option_c').value = '';
            document.getElementById('option_d').value = '';
            document.getElementById('ans_a').checked = true;
            document.getElementById('modalTitle').innerText = 'Yangi Savol Qo\'shish';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        function editQuestion(data) {
            document.getElementById('question_id').value = data.id;
            document.getElementById('question_text').value = data.question_text;
            document.getElementById('option_a').value = data.option_a;
            document.getElementById('option_b').value = data.option_b;
            document.getElementById('option_c').value = data.option_c;
            document.getElementById('option_d').value = data.option_d;
            
            // Bazadan kelgan qiymat kichik harfda ('a', 'b', ...)
            if(data.correct_option == 'a') document.getElementById('ans_a').checked = true;
            else if(data.correct_option == 'b') document.getElementById('ans_b').checked = true;
            else if(data.correct_option == 'c') document.getElementById('ans_c').checked = true;
            else if(data.correct_option == 'd') document.getElementById('ans_d').checked = true;

            document.getElementById('modalTitle').innerText = 'Savolni Tahrirlash';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    </script>
</body>
</html>