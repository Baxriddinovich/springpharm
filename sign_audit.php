<?php
require_once 'db.php';
requireLogin();

header('Content-Type: application/json');

$user = getCurrentUser();
$auditId = (int)($_POST['audit_id'] ?? 0);
$password = $_POST['password'] ?? '';

if (!$auditId) {
    echo json_encode(['success' => false, 'message' => 'Audit ID berilmagan!']);
    exit;
}

// Faqat auditor va bosh_auditor imzolaya oladi
if (!in_array($user['role'], ['auditor', 'bosh_auditor', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Sizda imzo qo\'yish huquqi yo\'q!']);
    exit;
}
// Parolni tekshirish
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$dbUser = $stmt->fetch();

// Hash va oddiy parol ikkalasini tekshirish
$passwordValid = false;

if (password_verify($password, $dbUser['password'])) {
    // Hashlanган parol
    $passwordValid = true;
} elseif ($password === $dbUser['password']) {
    // Hashlanmagan (oddiy text) parol
    $passwordValid = true;
}

if (!$passwordValid) {
    echo json_encode(['success' => false, 'message' => 'Parol noto\'g\'ri!']);
    exit;
}
// Audit mavjudligini tekshirish
$stmt = $pdo->prepare("SELECT id, status FROM audits WHERE id = ?");
$stmt->execute([$auditId]);
$audit = $stmt->fetch();

if (!$audit) {
    echo json_encode(['success' => false, 'message' => 'Audit topilmadi!']);
    exit;
}

// Allaqachon imzolangan tekshirish
$stmt = $pdo->prepare("SELECT id FROM audit_signatures WHERE audit_id = ? AND user_id = ?");
$stmt->execute([$auditId, $user['id']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Siz allaqachon imzolagansiz!']);
    exit;
}

// Imzoni saqlash
try {
    $stmt = $pdo->prepare("
        INSERT INTO audit_signatures (audit_id, user_id, signed_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$auditId, $user['id']]);

    // Log activity
    logActivity('audit_signed', "Audit #{$auditId} raqamli imzo bilan tasdiqlandi", 'audit');

    // Imzolagan foydalanuvchi ma'lumotlari
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.role, aus.signed_at 
        FROM audit_signatures aus
        JOIN users u ON u.id = aus.user_id
        WHERE aus.audit_id = ? AND aus.user_id = ?
    ");
    $stmt->execute([$auditId, $user['id']]);
    $signature = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Muvaffaqiyatli imzolandi!',
        'signature' => [
            'full_name' => $signature['full_name'],
            'role' => $signature['role'],
            'signed_at' => date('d.m.Y H:i', strtotime($signature['signed_at']))
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Xatolik: ' . $e->getMessage()]);
}
exit;
