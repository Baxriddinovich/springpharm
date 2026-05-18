<?php
session_start();

// Audit trail - Chiqish
if (isset($_SESSION['user_id'])) {
    try {
        require 'db.php';
        $pdo->query("UPDATE users SET last_login = NOW() WHERE id = " . $_SESSION['user_id']);
        logAuditTrail($pdo, 'Tizimdan chiqish', 'users', $_SESSION['user_id'], null, null);
    } catch (Exception $e) {
        // Audit trail xatosi tizim ishlashini to'xtatmasin
    }
}

// Sessionni tozalash
$_SESSION = [];
session_destroy();

// Bosh sahifaga yo'naltirish
header("Location: index.php");
exit;
?>