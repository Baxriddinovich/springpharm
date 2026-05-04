<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Tashkent');
// @Baxriddinovich_dev
require_once '../db.php';
require_once 'inc/functions.php';

// ═══════════════════════════════════════════════════════════
// FAYL KO'RISH PROKSI
// ═══════════════════════════════════════════════════════════
if (isset($_GET['serve_file'])) {
    if (!isset($_SESSION['reader_user_id'])) {
        http_response_code(403);
        exit('Ruxsat berilmagan');
    }
    $raw = str_replace(chr(0), '', $_GET['serve_file']);
    $fileName = basename($raw);
    $foundPath = null;
    $baseDir = dirname(__DIR__);

    $candidates = [
        $baseDir . '/uploads/modules/' . $fileName,
        $baseDir . '/uploads/' . $fileName,
        dirname(__FILE__) . '/../uploads/modules/' . $fileName
    ];

    foreach ($candidates as $c) {
        $norm = realpath($c);
        if ($norm && file_exists($norm) && is_file($norm)) {
            $foundPath = $norm;
            break;
        }
    }

    if (!$foundPath) {
        $searchDir = realpath($baseDir . '/uploads');
        if ($searchDir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($searchDir));
            foreach ($it as $file) {
                if ($file->isFile() && strcasecmp($file->getFilename(), $fileName) === 0) {
                    $foundPath = $file->getPathname();
                    break;
                }
            }
        }
    }

    if ($foundPath) {
        $mimeType = mime_content_type($foundPath);
        header("Content-Type: $mimeType");
        header("Content-Disposition: inline; filename=\"" . basename($foundPath) . "\"");
        header("Content-Length: " . filesize($foundPath));
        if (ob_get_level()) ob_end_clean();
        readfile($foundPath);
        exit;
    }
    http_response_code(404);
    exit("Fayl topilmadi");
}

// ═══════════════════════════════════════════════════════════
// SESSION TEKSHIRISH
// ═══════════════════════════════════════════════════════════
if (!isset($_SESSION['reader_user_id'])) {
    header("Location: index.php");
    exit;
}

$userId   = $_SESSION['reader_user_id'];
$fullName = $_SESSION['reader_full_name'] ?? '';
$username = $_SESSION['reader_username'] ?? '';

// Session tracking (fallback uchun saqlanadi)
if (!isset($_SESSION['reader_materials_viewed']))    $_SESSION['reader_materials_viewed']    = [];
if (!isset($_SESSION['reader_materials_completed'])) $_SESSION['reader_materials_completed'] = [];
if (!isset($_SESSION['reader_test_results']))        $_SESSION['reader_test_results']        = [];

// ═══════════════════════════════════════════════════════════
// DB HELPER FUNKSIYALAR
// ═══════════════════════════════════════════════════════════

/** Foydalanuvchi uchun modul materiallarini ko'rganlarini DB dan olish */
function getViewedMaterials(PDO $pdo, int $userId, int $moduleId): array {
    try {
        $s = $pdo->prepare("SELECT material_id FROM reader_material_progress WHERE user_id=? AND module_id=?");
        $s->execute([$userId, $moduleId]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { return []; }
}

/** Modul tugatilganmi (materiallar o'qildi tugmasi bosilganmi) */
function isModuleCompleted(PDO $pdo, int $userId, int $moduleId): bool {
    try {
        $s = $pdo->prepare("SELECT id FROM reader_module_completions WHERE user_id=? AND module_id=?");
        $s->execute([$userId, $moduleId]);
        return (bool)$s->fetchColumn();
    } catch (Exception $e) { return false; }
}

/** Oxirgi test urinishini olish */
function getLastAttempt(PDO $pdo, int $userId, int $moduleId): ?array {
    try {
        $s = $pdo->prepare("SELECT * FROM reader_test_attempts WHERE user_id=? AND module_id=? ORDER BY attempted_at DESC LIMIT 1");
        $s->execute([$userId, $moduleId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    } catch (Exception $e) { return null; }
}

/** Test hozir ochiqmi (3 kunlik blok tekshiruvi) */
function isTestUnlocked(PDO $pdo, int $userId, int $moduleId): array {
    $attempt = getLastAttempt($pdo, $userId, $moduleId);
    if (!$attempt) return ['unlocked' => true, 'attempt' => null];
    if ($attempt['status'] === 'passed') return ['unlocked' => false, 'passed' => true, 'attempt' => $attempt];
    // Muvaffaqiyatsiz — next_allowed_at tekshirish
    if ($attempt['next_allowed_at']) {
        $now  = new DateTime();
        $next = new DateTime($attempt['next_allowed_at']);
        if ($now < $next) {
            return ['unlocked' => false, 'blocked' => true, 'next_at' => $attempt['next_allowed_at'], 'attempt' => $attempt];
        }
    }
    return ['unlocked' => true, 'attempt' => $attempt];
}

/** Modul uchun test savollarini random yuklash */
function loadTestQuestions(PDO $pdo, int $moduleId, int $limit = 0): array {
    try {
        $s = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option FROM training_questions WHERE training_id=? ORDER BY id ASC");
        $s->execute([$moduleId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);

        // Random tartib
        shuffle($rows);

        // Limit qo'llash
        if ($limit > 0 && $limit < count($rows)) {
            $rows = array_slice($rows, 0, $limit);
        }

        $questions = [];
        foreach ($rows as $r) {
            $base = $r['id'] * 10;
            $opts = [
                ['id' => $base + 1, 'text' => $r['option_a'], 'key' => 'a'],
                ['id' => $base + 2, 'text' => $r['option_b'], 'key' => 'b'],
                ['id' => $base + 3, 'text' => $r['option_c'], 'key' => 'c'],
                ['id' => $base + 4, 'text' => $r['option_d'], 'key' => 'd'],
            ];
            // Variantlarni ham aralashtirish
            shuffle($opts);
            $correctId = null;
            foreach ($opts as $o) {
                if ($o['key'] === $r['correct_option']) { $correctId = $o['id']; break; }
            }
            $questions[$r['id']] = [
                'text'       => $r['question_text'],
                'correct_id' => $correctId,
                'answers'    => $opts,
            ];
        }
        return $questions;
    } catch (Exception $e) { return []; }
}

// ═══════════════════════════════════════════════════════════
// DATA LOADING
// ═══════════════════════════════════════════════════════════
$stmt = $pdo->prepare("SELECT u.position_id, p.name as position_name FROM users u LEFT JOIN positions p ON u.position_id = p.id WHERE u.id = ?");
$stmt->execute([$userId]);
$userData      = $stmt->fetch(PDO::FETCH_ASSOC);
$userPositionId   = $userData['position_id'] ?? null;
$userPositionName = $userData['position_name'] ?? 'Belgilanmagan';

$assignedModules = [];
if ($userPositionId) {
    $stmt = $pdo->prepare("
        SELECT tm.*,
            (SELECT COUNT(*) FROM module_materials mm WHERE mm.module_id = tm.id AND mm.file_path IS NOT NULL AND mm.file_path != '') as material_count,
            (SELECT COUNT(*) FROM training_questions tq WHERE tq.training_id = tm.id) as question_count
        FROM training_modules tm
        INNER JOIN training_matrix tx ON tm.id = tx.module_id
        WHERE tx.position_id = ? AND tm.status = 'active'
        ORDER BY tm.title ASC
    ");
    $stmt->execute([$userPositionId]);
    $assignedModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page     = $_GET['page'] ?? 'dashboard';
$moduleId = intval($_GET['id'] ?? 0);

// ═══════════════════════════════════════════════════════════
// AJAX ACTIONS (POST)
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action   = $_POST['action'] ?? '';
    $response = ['success' => false];

    // --- Material ko'rildi ---
    if ($action === 'mark_viewed') {
        $matId = intval($_POST['material_id'] ?? 0);
        $modId = intval($_POST['module_id'] ?? 0);
        if ($modId && $matId) {
            try {
                $pdo->prepare("INSERT IGNORE INTO reader_material_progress (user_id, module_id, material_id) VALUES (?,?,?)")
                    ->execute([$userId, $modId, $matId]);
                logActivity('material_viewed', "Material (ID: $matId) ko'rildi", 'oqish');
            } catch (Exception $e) {}

            // Session ham yangilash (fallback)
            if (!isset($_SESSION['reader_materials_viewed'][$modId])) $_SESSION['reader_materials_viewed'][$modId] = [];
            if (!in_array($matId, $_SESSION['reader_materials_viewed'][$modId])) $_SESSION['reader_materials_viewed'][$modId][] = $matId;

            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM module_materials WHERE module_id=? AND file_path IS NOT NULL AND file_path != ''");
            $totalStmt->execute([$modId]);
            $totalMats   = (int)$totalStmt->fetchColumn();
            $viewedItems = getViewedMaterials($pdo, $userId, $modId);
            $viewedCount = count($viewedItems);

            $response = ['success' => true, 'viewed' => $viewedCount, 'total' => $totalMats, 'all_viewed' => ($viewedCount >= $totalMats && $totalMats > 0)];
        }
    }

    // --- Materiallarni tugatish ---
    if ($action === 'complete_materials') {
        $modId = intval($_POST['module_id'] ?? 0);
        if ($modId) {
            try {
                $pdo->prepare("INSERT IGNORE INTO reader_module_completions (user_id, module_id) VALUES (?,?)")
                    ->execute([$userId, $modId]);
                logActivity('module_completed', "Modul (ID: $modId) o'qib tugatildi", 'oqish');
            } catch (Exception $e) {}
            $_SESSION['reader_materials_completed'][$modId] = true;
            $response = ['success' => true, 'redirect' => "?page=test&id=$modId"];
        }
    }

    // --- Test topshirish ---
    if ($action === 'submit_test') {
        $modId   = intval($_POST['module_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];

        // Test ochiqmi tekshirish
        $lockInfo = isTestUnlocked($pdo, $userId, $modId);
        if (!$lockInfo['unlocked']) {
            $response = ['success' => false, 'error' => 'locked'];
            echo json_encode($response); exit;
        }

        // Modul sozlamalarini olish
        $modStmt = $pdo->prepare("SELECT passing_percent, test_question_count FROM training_modules WHERE id=?");
        $modStmt->execute([$modId]);
        $modInfo = $modStmt->fetch(PDO::FETCH_ASSOC);
        $passing  = intval($modInfo['passing_percent'] ?? 80);
        $qLimit   = intval($modInfo['test_question_count'] ?? 0);

        // Savollarni yuklash (session da saqlangan bo'lishi kerak — test boshlanganda)
        // Javoblarni tekshirish uchun DB dan o'qiymiz
        $allQStmt = $pdo->prepare("SELECT id, correct_option FROM training_questions WHERE training_id=?");
        $allQStmt->execute([$modId]);
        $allQRows = $allQStmt->fetchAll(PDO::FETCH_ASSOC);

        // correct_id ni hisoblash (base formula: id*10 + offset)
        $correctMap = [];
        foreach ($allQRows as $r) {
            $base = $r['id'] * 10;
            $offsets = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
            $correctMap[$r['id']] = $base + ($offsets[$r['correct_option']] ?? 1);
        }

        // Faqat topshirilgan savollarni hisoblash
        $correct = 0;
        $total   = count($answers);
        $details = [];

        // Savol matnlarini olish
        $qTexts = [];
        $qOptsStmt = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option FROM training_questions WHERE training_id=?");
        $qOptsStmt->execute([$modId]);
        foreach ($qOptsStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $qTexts[$r['id']] = $r;
        }

        foreach ($answers as $qId => $selectedId) {
            $qId       = intval($qId);
            $selectedId = intval($selectedId);
            $correctId  = $correctMap[$qId] ?? null;
            $isCorrect  = ($selectedId === $correctId);
            if ($isCorrect) $correct++;

            $qRow = $qTexts[$qId] ?? null;
            if ($qRow) {
                $base = $qId * 10;
                $answersFlat = [
                    $base + 1 => $qRow['option_a'],
                    $base + 2 => $qRow['option_b'],
                    $base + 3 => $qRow['option_c'],
                    $base + 4 => $qRow['option_d'],
                ];
                $details[] = [
                    'question'   => $qRow['question_text'],
                    'selected'   => $selectedId,
                    'correct_id' => $correctId,
                    'is_correct' => $isCorrect,
                    'answers'    => $answersFlat,
                ];
            }
        }

        $score  = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
        $status = ($score >= $passing) ? 'passed' : 'failed';

        // Keyingi urinish vaqtini hisoblash (muvaffaqiyatsiz bo'lsa 3 kun)
        $nextAllowedAt = null;
        if ($status === 'failed') {
            $nextAllowedAt = date('Y-m-d H:i:s', strtotime('+3 days'));
        }

        // DB ga saqlash
        try {
            $pdo->prepare("INSERT INTO reader_test_attempts (user_id, module_id, score, correct_count, total_count, status, passing_percent, next_allowed_at, details) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$userId, $modId, $score, $correct, $total, $status, $passing, $nextAllowedAt, json_encode($details)]);
        } catch (Exception $e) {}

        // Muvaffaqiyatsiz bo'lsa — materiallarni qayta o'qish uchun completion va viewed ni o'chirish
        if ($status === 'failed') {
            try {
                $pdo->prepare("DELETE FROM reader_module_completions WHERE user_id=? AND module_id=?")->execute([$userId, $modId]);
                $pdo->prepare("DELETE FROM reader_material_progress WHERE user_id=? AND module_id=?")->execute([$userId, $modId]);
            } catch (Exception $e) {}
            unset($_SESSION['reader_materials_completed'][$modId]);
            unset($_SESSION['reader_materials_viewed'][$modId]);
        }

        // Session ga ham saqlash
        $_SESSION['reader_test_results'][$modId] = [
            'score'   => $score,
            'correct' => $correct,
            'total'   => $total,
            'status'  => $status,
            'passing' => $passing,
            'time'    => date('d.m.Y H:i'),
            'details' => $details,
        ];

        $statusText = $status === 'passed' ? "o'tdi" : "o'ta olmadi";
        logActivity('test_submitted', "Test topshirildi (ID: $modId). Natija: $score%. Holati: $statusText", 'oqish');

        $response = ['success' => true, 'redirect' => "?page=test_result&id=$modId"];
    }

    echo json_encode($response);
    exit;
}

// ═══════════════════════════════════════════════════════════
// MODULE VALIDATION
// ═══════════════════════════════════════════════════════════
$validModule   = false;
$currentModule = null;
if ($moduleId) {
    foreach ($assignedModules as $m) {
        if ($m['id'] == $moduleId) {
            $validModule   = true;
            $currentModule = $m;
            break;
        }
    }
}

$moduleMaterials = [];
$testQuestions   = [];
$viewedMaterials = [];
$testLockInfo    = ['unlocked' => true];
$lastAttempt     = null;

if ($validModule) {
    // Materiallar
    $stmt = $pdo->prepare("SELECT * FROM module_materials WHERE module_id=? AND file_path IS NOT NULL AND file_path != ''");
    $stmt->execute([$moduleId]);
    $moduleMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ko'rilgan materiallar (DB dan)
    $viewedMaterials = getViewedMaterials($pdo, $userId, $moduleId);

    // Session ham yangilash
    $_SESSION['reader_materials_viewed'][$moduleId] = $viewedMaterials;

    // Modul tugatilganmi (DB dan)
    if (isModuleCompleted($pdo, $userId, $moduleId)) {
        $_SESSION['reader_materials_completed'][$moduleId] = true;
    }

    // Test lock info
    $testLockInfo = isTestUnlocked($pdo, $userId, $moduleId);
    $lastAttempt  = $testLockInfo['attempt'] ?? null;

    // Test natijasini session ga yuklash (agar DB da bo'lsa)
    if ($lastAttempt && !isset($_SESSION['reader_test_results'][$moduleId])) {
        $detailsRaw = $lastAttempt['details'];
        $details    = is_string($detailsRaw) ? json_decode($detailsRaw, true) : ($detailsRaw ?? []);
        $_SESSION['reader_test_results'][$moduleId] = [
            'score'   => $lastAttempt['score'],
            'correct' => $lastAttempt['correct_count'],
            'total'   => $lastAttempt['total_count'],
            'status'  => $lastAttempt['status'],
            'passing' => $lastAttempt['passing_percent'],
            'time'    => date('d.m.Y H:i', strtotime($lastAttempt['attempted_at'])),
            'details' => $details ?? [],
        ];
    }

    // Module sahifasiga qaytarish (agar tugatilgan bo'lsa)
    if ($page === 'module' && isset($_SESSION['reader_materials_completed'][$moduleId])) {
        // Agar test muvaffaqiyatsiz va blok bo'lsa — module sahifasida qolsin
        if (!isset($testLockInfo['blocked'])) {
            header("Location: ?page=test&id=$moduleId");
            exit;
        }
    }

    // Test savollarini yuklash
    if (in_array($page, ['test', 'test_result'])) {
        $qLimit      = intval($currentModule['test_question_count'] ?? 0);
        $testQuestions = loadTestQuestions($pdo, $moduleId, $qLimit);
    }
}

// ═══════════════════════════════════════════════════════════
// STATISTICS
// ═══════════════════════════════════════════════════════════
$totalModules    = count($assignedModules);
$passedModules   = 0;
$inProgressCount = 0;
foreach ($assignedModules as $m) {
    $st = getModuleStatus($m['id']);
    if ($st === 'passed')      $passedModules++;
    elseif (in_array($st, ['in_progress', 'test_ready'])) $inProgressCount++;
}

$viewedCount       = count($viewedMaterials);
$allMaterialsViewed = ($validModule && count($moduleMaterials) > 0)
    ? ($viewedCount >= count($moduleMaterials))
    : ($validModule ? true : false);

$testResult = ($page === 'test_result' && $validModule)
    ? ($_SESSION['reader_test_results'][$moduleId] ?? null)
    : null;

// ═══════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════
$pageTitle = ($page === 'dashboard' ? 'Boshqaruv paneli' : ($currentModule['title'] ?? 'Modul')) . " — GMP O'quv Tizimi";
require_once 'inc/header.php';
require_once 'inc/sidebar.php';
?>

<main class="lg:ml-72 min-h-screen">
    <div class="pt-16 lg:pt-0">
        <?php
        if ($page === 'dashboard')
            include 'pages/home.php';
        elseif ($page === 'module' && $validModule)
            include 'pages/module.php';
        elseif ($page === 'test' && $validModule)
            include 'pages/test.php';
        elseif ($page === 'test_result' && $validModule && $testResult)
            include 'pages/test_result.php';
        else
            include 'pages/home.php';
        ?>
    </div>
</main>

<?php require_once 'inc/footer.php'; ?>
