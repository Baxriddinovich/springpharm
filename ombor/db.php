<?php
// OMBOR + QC TIZIMI DATABASE CONFIGURATION
// Tayyorlovchi: GXP Service Pharm

$host = 'localhost';
$dbname = 'gxpharm_ombor';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    die("Database ulanish xatosi: " . $e->getMessage());
}

// Audit trail funksiyasi
function logAuditTrail($pdo, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_trail (user_id, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $username,
            $action,
            $tableName,
            $recordId,
            is_array($oldValues) ? json_encode($oldValues) : $oldValues,
            is_array($newValues) ? json_encode($newValues) : $newValues,
            $ipAddress,
            $userAgent
        ]);
    } catch (PDOException $e) {
        // Audit trail xatosi tizim ishlashini to'xtatmasin
        error_log("Audit trail xatosi: " . $e->getMessage());
    }
}
?>