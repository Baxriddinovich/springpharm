<?php
// upload_image.php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$auditId = (int)($_POST['audit_id'] ?? 0);
$questionId = (int)($_POST['question_id'] ?? 0);
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

if (!$auditId || !$questionId || empty($_FILES['image']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Ma\'lumot yetarli emas']);
    exit;
}

// 1. ABSOLYUT YO'LNI ANIQLASH (Eng muhim joyi)
$baseDir = $_SERVER['DOCUMENT_ROOT']; // /var/www/fastuser/data/www/springpharmaceutic.uz
$uploadDirRelative = 'uploads/answers/';
$uploadDirFull = rtrim($baseDir, '/') . '/' . $uploadDirRelative;

// Papka mavjudligini tekshirish
if (!is_dir($uploadDirFull)) {
    mkdir($uploadDirFull, 0777, true);
}

// 2. Fayl nomini shifrlash
$ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$fileName = time() . "_audit{$auditId}_q{$questionId}." . $ext;
$targetPathFull = $uploadDirFull . $fileName; // Serverdagi to'liq yo'l
$dbPath = $uploadDirRelative . $fileName;     // Bazaga yoziladigan yo'l

// 3. Faylni ko'chirishga urinish
if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPathFull)) {
    try {
        // 4. BAZA BILAN ISHLASH
        $stmt = $pdo->prepare("SELECT id FROM audit_answers WHERE audit_id = ? AND question_id = ?");
        $stmt->execute([$auditId, $questionId]);
        $row = $stmt->fetch();

        if ($row) {
            $sql = "UPDATE audit_answers SET image_path = ?, auditor_id = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$dbPath, $userId, $row['id']]);
        } else {
            $sql = "INSERT INTO audit_answers (audit_id, question_id, auditor_id, image_path, answer) VALUES (?, ?, ?, ?, 'na')";
            $pdo->prepare($sql)->execute([$auditId, $questionId, $userId, $dbPath]);
        }

        echo json_encode(['success' => true, 'path' => $dbPath]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'SQL xatosi: ' . $e->getMessage()]);
    }
} else {
    // Agar ko'chirish o'xshama-sa, sababini JSONda ko'rsatamiz
    echo json_encode([
        'success' => false, 
        'message' => 'Faylni ko\'chirib bo\'lmadi',
        'debug' => [
            'target_dir' => $uploadDirFull,
            'is_writable' => is_writable($uploadDirFull),
            'tmp_name' => $_FILES['image']['tmp_name']
        ]
    ]);
}