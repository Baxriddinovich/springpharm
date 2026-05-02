<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Tashkent');

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
        if (ob_get_level())
            ob_end_clean();
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

$userId = $_SESSION['reader_user_id'];
$fullName = $_SESSION['reader_full_name'] ?? '';
$username = $_SESSION['reader_username'] ?? '';

// Session tracking initialization
if (!isset($_SESSION['reader_materials_viewed']))
    $_SESSION['reader_materials_viewed'] = [];
if (!isset($_SESSION['reader_materials_completed']))
    $_SESSION['reader_materials_completed'] = [];
if (!isset($_SESSION['reader_test_results']))
    $_SESSION['reader_test_results'] = [];

// ═══════════════════════════════════════════════════════════
// DATA LOADING
// ═══════════════════════════════════════════════════════════
$stmt = $pdo->prepare("SELECT u.position_id, p.name as position_name FROM users u LEFT JOIN positions p ON u.position_id = p.id WHERE u.id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$userPositionId = $userData['position_id'] ?? null;
$userPositionName = $userData['position_name'] ?? 'Belgilanmagan';

$assignedModules = [];
if ($userPositionId) {
    $stmt = $pdo->prepare("
        SELECT tm.*,
            (SELECT COUNT(*) FROM module_materials mm WHERE mm.module_id = tm.id AND mm.file_path IS NOT NULL AND mm.file_path != '') as material_count,
            (SELECT COUNT(*) FROM test_questions tq WHERE tq.module_id = tm.id) as question_count
        FROM training_modules tm
        INNER JOIN training_matrix tx ON tm.id = tx.module_id
        WHERE tx.position_id = ? AND tm.status = 'active'
        ORDER BY tm.title ASC
    ");
    $stmt->execute([$userPositionId]);
    $assignedModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Page parameters
$page = $_GET['page'] ?? 'dashboard';
$moduleId = intval($_GET['id'] ?? 0);

if ($page === 'test' && isset($_GET['retake'])) {
    unset($_SESSION['reader_test_results'][$moduleId]);
}

// AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];

    if ($action === 'mark_viewed') {
        $matId = intval($_POST['material_id'] ?? 0);
        $modId = intval($_POST['module_id'] ?? 0);
        if ($modId && $matId) {
            if (!isset($_SESSION['reader_materials_viewed'][$modId]))
                $_SESSION['reader_materials_viewed'][$modId] = [];
            if (!in_array($matId, $_SESSION['reader_materials_viewed'][$modId])) {
                $_SESSION['reader_materials_viewed'][$modId][] = $matId;
                logActivity('material_viewed', "Material (ID: $matId) ko'rildi", 'oqish');
            }

            $totalMats = 0;
            foreach ($assignedModules as $m) {
                if ($m['id'] == $modId) {
                    $totalMats = $m['material_count'];
                    break;
                }
            }
            $viewedCount = count($_SESSION['reader_materials_viewed'][$modId]);

            $response = ['success' => true, 'viewed' => $viewedCount, 'total' => $totalMats, 'all_viewed' => ($viewedCount >= $totalMats)];
        }
    }

    if ($action === 'complete_materials') {
        $modId = intval($_POST['module_id'] ?? 0);
        if ($modId) {
            $_SESSION['reader_materials_completed'][$modId] = true;
            logActivity('module_completed', "Modul (ID: $modId) o'qib tugatildi", 'oqish');
            $response = ['success' => true, 'redirect' => "?page=test&id=$modId"];
        }
    }

    if ($action === 'submit_test') {
        $modId = intval($_POST['module_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];

        $stmt = $pdo->prepare("SELECT tq.id, tq.question_text, ta.id as ans_id, ta.answer_text, ta.is_correct FROM test_questions tq LEFT JOIN test_answers ta ON tq.id = ta.question_id WHERE tq.module_id = ? ORDER BY tq.order_index");
        $stmt->execute([$modId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $questions = [];
        foreach ($rows as $r) {
            if (!isset($questions[$r['id']]))
                $questions[$r['id']] = ['text' => $r['question_text'], 'correct_id' => null, 'answers' => []];
            if ($r['ans_id']) {
                $questions[$r['id']]['answers'][$r['ans_id']] = $r['answer_text'];
                if ($r['is_correct'])
                    $questions[$r['id']]['correct_id'] = $r['ans_id'];
            }
        }

        $correct = 0;
        $details = [];
        foreach ($questions as $qId => $q) {
            $selected = intval($answers[$qId] ?? 0);
            $isCorrect = ($selected == $q['correct_id']);
            if ($isCorrect)
                $correct++;
            $details[] = ['question' => $q['text'], 'selected' => $selected, 'correct_id' => $q['correct_id'], 'is_correct' => $isCorrect, 'answers' => $q['answers']];
        }

        $score = count($questions) > 0 ? round(($correct / count($questions)) * 100, 1) : 0;
        $stmt = $pdo->prepare("SELECT passing_percent FROM training_modules WHERE id = ?");
        $stmt->execute([$modId]);
        $passing = $stmt->fetchColumn() ?: 80;

        $_SESSION['reader_test_results'][$modId] = [
            'score' => $score,
            'correct' => $correct,
            'total' => count($questions),
            'status' => ($score >= $passing ? 'passed' : 'failed'),
            'passing' => $passing,
            'time' => date('d.m.Y H:i'),
            'details' => $details
        ];

        $status = ($score >= $passing ? 'o\'tdi' : 'o\'ta olmadi');
        logActivity('test_submitted', "Test topshirildi (ID: $modId). Natija: $score%. Holati: $status", 'oqish');

        $response = ['success' => true, 'redirect' => "?page=test_result&id=$modId"];
    }

    echo json_encode($response);
    exit;
}

// Module validation
$validModule = false;
$currentModule = null;
if ($moduleId) {
    foreach ($assignedModules as $m) {
        if ($m['id'] == $moduleId) {
            $validModule = true;
            $currentModule = $m;
            break;
        }
    }
}

$moduleMaterials = [];
$testQuestions = [];
if ($validModule) {
    // REDIRECTION: If module is completed (Tugatish pressed), don't allow going back to reading
    if ($page === 'module' && isset($_SESSION['reader_materials_completed'][$moduleId])) {
        header("Location: ?page=test&id=$moduleId");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM module_materials WHERE module_id = ? AND file_path IS NOT NULL AND file_path != ''");
    $stmt->execute([$moduleId]);
    $moduleMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load questions for test and result pages
    if (in_array($page, ['test', 'test_result'])) {
        try {
            // Removed ORDER BY order_index just in case it doesn't exist
            $stmt = $pdo->prepare("SELECT tq.id, tq.question_text, ta.id as ans_id, ta.answer_text, ta.is_correct 
                                   FROM test_questions tq 
                                   LEFT JOIN test_answers ta ON tq.id = ta.question_id 
                                   WHERE tq.module_id = ?");
            $stmt->execute([$moduleId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                if (!isset($testQuestions[$r['id']]))
                    $testQuestions[$r['id']] = ['text' => $r['question_text'], 'answers' => []];
                if ($r['ans_id'])
                    $testQuestions[$r['id']]['answers'][] = ['id' => $r['ans_id'], 'text' => $r['answer_text']];
            }
        } catch (Exception $e) {
            // Silently fail or log
        }
    }
}

// Statistics
$totalModules = count($assignedModules);
$passedModules = 0;
$inProgressCount = 0;
foreach ($assignedModules as $m) {
    $st = getModuleStatus($m['id']);
    if ($st === 'passed')
        $passedModules++;
    elseif (in_array($st, ['in_progress', 'test_ready']))
        $inProgressCount++;
}

// Progress for current module
$viewedCount = $validModule ? count($_SESSION['reader_materials_viewed'][$moduleId] ?? []) : 0;
$allMaterialsViewed = ($validModule && count($moduleMaterials) > 0) ? ($viewedCount >= count($moduleMaterials)) : ($validModule ? true : false);

$testResult = ($page === 'test_result' && $validModule) ? ($_SESSION['reader_test_results'][$moduleId] ?? null) : null;

// Header & Navigation
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
            include 'pages/home.php'; // Default or 404
        ?>
    </div>
</main>

<?php require_once 'inc/footer.php'; ?>