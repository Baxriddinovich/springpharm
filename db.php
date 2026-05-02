<?php
ini_set('allow_url_fopen', 1);

// 2. Vaqt mintaqasini sozlash (Hisobot sanasi to'g'ri chiqishi uchun)
date_default_timezone_set('Asia/Tashkent');
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
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
    die("Ma'lumotlar bazasi ulanish xatosi: " . $e->getMessage());
}

function isLoggedIn(): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (isset($_SESSION['login_ip']) && $_SESSION['login_ip'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        return false;
    }
    return true;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function hasRole($role)
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function getCurrentUser()
{
    global $pdo;
    if (!isLoggedIn())
        return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function jsonResponse($success, $message, $data = null)
{
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
function logActivity($type, $details = '', $action = '')
{
    global $pdo;
    $userId = $_SESSION['user_id'] ?? $_SESSION['reader_user_id'] ?? 0;
    if (!$userId)
        return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    try {
        // action_type ustuni activity_logs jadvalida bor
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, action_type, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $type, $details, $ip, $userAgent]);
    } catch (Exception $e) {
        // Xatolik bo'lsa indamaymiz
    }
}
function getTimeAgo($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0)
        return $diff->y . ' yil oldin';
    if ($diff->m > 0)
        return $diff->m . ' oy oldin';
    if ($diff->d > 0)
        return $diff->d . ' kun oldin';
    if ($diff->h > 0)
        return $diff->h . ' soat oldin';
    if ($diff->i > 0)
        return $diff->i . ' daqiqa oldin';
    return 'Hozirgina';
}

function buildFilterUrl(string $status): string
{
    $params = $_GET;
    if ($status === 'all') {
        unset($params['status']);
    } else {
        $params['status'] = $status;
    }
    unset($params['page']);
    return 'audits.php' . ($params ? '?' . http_build_query($params) : '');
}

function buildPageUrl(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    return 'audits.php?' . http_build_query($params);
}

function sanitize(string $input): string
{
    return trim(strip_tags($input));
}
function smartQuery(string $sql, array $params = [], string $customDetail = '')
{
    global $pdo;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $command = strtoupper(explode(' ', trim($sql))[0]);

        if (in_array($command, ['INSERT', 'UPDATE', 'DELETE'])) {

            preg_match('/(?:INTO|FROM|UPDATE)\s+`?(\w+)`?/i', $sql, $matches);
            $table = $matches[1] ?? 'unknown';

            $actions = [
                'users' => ['INSERT' => 'user_added', 'UPDATE' => 'user_edited', 'DELETE' => 'user_deleted'],
                'audits' => ['INSERT' => 'audit_created', 'UPDATE' => 'audit_status_changed', 'DELETE' => 'audit_deleted'],
                'sections' => ['INSERT' => 'section_added', 'UPDATE' => 'section_edited', 'DELETE' => 'section_deleted'],
                'questions' => ['INSERT' => 'question_added', 'UPDATE' => 'question_edited', 'DELETE' => 'question_deleted'],
            ];

            if (isset($actions[$table][$command])) {
                $actionType = $actions[$table][$command];
                $details = $customDetail;

                if (empty($details)) {
                    if (preg_match('/(title|name|full_name)\s*[=?,]/i', $sql)) {
                        foreach ($params as $p) {
                            if (is_string($p) && strlen($p) > 2) {
                                $details = $p;
                                break;
                            }
                        }
                    }
                    if (empty($details) && $command === 'INSERT') {
                        $details = "Yangi yozuv (ID: " . $pdo->lastInsertId() . ")";
                    }
                }

                logActivity($actionType, $details, strtolower($command));
            }
        }
        return $stmt;
    } catch (Exception $e) {
        throw $e; // Agar xatolik bo'lsa, log yozmaydi, xato haqida xabar beradi
    }
}

function logAudit($user_id, $action, $table_name, $record_id)
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $action, $table_name, $record_id]);
}
