<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('allow_url_fopen', 1);

date_default_timezone_set('Asia/Tashkent');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'springuser');
define('DB_PASS', 'Boburbek13@!');
define('DB_NAME', 'springpharm_db');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("DB xato: " . $e->getMessage());
}

// ================= AUTH =================

function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id'])) return false;

    if (isset($_SESSION['login_ip']) && $_SESSION['login_ip'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        return false;
    }
    return true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function getCurrentUser() {
    global $pdo;

    if (!isLoggedIn()) return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    return $stmt->fetch();
}

// ================= UTILS =================

function sanitize(string $input): string {
    return trim(strip_tags($input));
}

function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function getTimeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' yil oldin';
    if ($diff->m > 0) return $diff->m . ' oy oldin';
    if ($diff->d > 0) return $diff->d . ' kun oldin';
    if ($diff->h > 0) return $diff->h . ' soat oldin';
    if ($diff->i > 0) return $diff->i . ' daqiqa oldin';

    return 'Hozirgina';
}

// ================= LOG =================

function logActivity($action, $details = '') {
    global $pdo;

    if (!isset($_SESSION['user_id'])) return;

    $stmt = $pdo->prepare("
        INSERT INTO audit_trail (user_id, action, details, ip_address)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

function logAudit($user_id, $action, $table_name, $record_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->execute([$user_id, $action, $table_name, $record_id]);
}

// ================= SMART QUERY =================

function smartQuery(string $sql, array $params = [], string $customDetail = '') {
    global $pdo;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $command = strtoupper(explode(' ', trim($sql))[0]);

    if (in_array($command, ['INSERT', 'UPDATE', 'DELETE'])) {

        preg_match('/(?:INTO|FROM|UPDATE)\s+`?(\w+)`?/i', $sql, $matches);
        $table = $matches[1] ?? 'unknown';

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = $_SESSION['user_id'] ?? 0;

        logActivity($command . '_' . $table, $customDetail ?: 'Auto log');
    }

    return $stmt;
}