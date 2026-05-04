<?php
/**
 * Bu faylni brauzerda BIR MARTA oching: /setup_reader_db.php
 * Keyin o'chirib tashlang!
 */
require_once 'db.php';
$results = [];

try {
    $pdo->exec("ALTER TABLE training_modules ADD COLUMN test_question_count INT DEFAULT 0");
    $results[] = ['ok', 'training_modules.test_question_count ustuni qoshildi'];
} catch(Exception $e) {
    $results[] = [strpos($e->getMessage(), 'Duplicate') !== false ? 'skip' : 'err', 'test_question_count: ' . $e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reader_test_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module_id INT NOT NULL,
        score DECIMAL(5,2) DEFAULT 0,
        correct_count INT DEFAULT 0,
        total_count INT DEFAULT 0,
        status ENUM('passed','failed') DEFAULT 'failed',
        passing_percent INT DEFAULT 80,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        next_allowed_at TIMESTAMP NULL,
        details LONGTEXT,
        INDEX idx_user_module (user_id, module_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'reader_test_attempts jadvali yaratildi'];
} catch(Exception $e) {
    $results[] = ['err', 'reader_test_attempts: ' . $e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reader_material_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module_id INT NOT NULL,
        material_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_progress (user_id, module_id, material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'reader_material_progress jadvali yaratildi'];
} catch(Exception $e) {
    $results[] = ['err', 'reader_material_progress: ' . $e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reader_module_completions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module_id INT NOT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_completion (user_id, module_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'reader_module_completions jadvali yaratildi'];
} catch(Exception $e) {
    $results[] = ['err', 'reader_module_completions: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="uz">
<head><meta charset="UTF-8"><title>DB Setup</title>
<style>body{font-family:monospace;background:#0a0f1a;color:#f1f5f9;padding:2rem;} .ok{color:#34d399;} .skip{color:#fbbf24;} .err{color:#f87171;} li{margin:0.5rem 0;font-size:14px;}</style>
</head>
<body>
<h2 style="color:#06b6d4">Reader DB Setup</h2>
<ul>
<?php foreach ($results as [$type, $msg]): ?>
<li class="<?php echo $type; ?>">[<?php echo strtoupper($type); ?>] <?php echo htmlspecialchars($msg); ?></li>
<?php endforeach; ?>
</ul>
<p style="color:#64748b;margin-top:2rem;font-size:12px;">⚠️ Bu faylni ishlatib bo'lgach o'chirib tashlang!</p>
</body>
</html>
