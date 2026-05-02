<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'springpharm_db');
define('DB_USER', 'springuser');
define('DB_PASS', 'Boburbek13@!');


define('BASE_URL', '/');

define('MATERIAL_PATH', __DIR__ . '/uploads/materials/');

date_default_timezone_set('Asia/Tashkent');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDB()
{
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
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}


function getCurrentUser()
{
    if (!isLoggedIn())
        return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}