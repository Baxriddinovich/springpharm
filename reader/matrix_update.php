<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

require_once '../db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Tizimga kirilmagan']);
    exit;
}

 $user = getCurrentUser();
header('Content-Type: application/json');

 $pos_id = isset($_POST['pos_id']) ? (int)$_POST['pos_id'] : 0;
 $mod_id = isset($_POST['mod_id']) ? (int)$_POST['mod_id'] : 0;
 $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

if ($pos_id > 0 && $mod_id > 0) {
    try {
        global $pdo;
        
        if ($status == 1) {
            $sql = "INSERT IGNORE INTO training_matrix (position_id, module_id) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$pos_id, $mod_id]);
            
            // logAudit o'rniga logActivity ishlatamiz (audit_logs jadvali yo'q)
            logActivity('matrix_add', "Lavozim ID: $pos_id ga Modul ID: $mod_id biriktirildi", 'matrix');
        } else {
            $sql = "DELETE FROM training_matrix WHERE position_id = ? AND module_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$pos_id, $mod_id]);
            
            logActivity('matrix_remove', "Lavozim ID: $pos_id dan Modul ID: $mod_id olib tashlandi", 'matrix');
        }

        ob_end_clean();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
}
?>