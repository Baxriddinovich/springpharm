<?php
require_once 'db.php';

 $columns = [
    'department_id' => "INT NULL",
    'position_id' => "INT NULL",
    'hire_date' => "DATE NULL",
    'is_active' => "BOOLEAN DEFAULT TRUE"
];

echo "<h3>Users jadvali yangilanmoqda...</h3>";

foreach ($columns as $name => $definition) {
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `$name` $definition");
        echo "<p style='color:green;'>✅ <b>$name</b> ustuni muvaffaqiyatli qo'shildi.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange;'>⚠️ <b>$name</b> ustuni allaqachon mavjud (o'tkazib yuborildi).</p>";
        } else {
            echo "<p style='color:red;'>❌ Xatolik: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<p><b>Tayyor! Bu faylni endi o'chirib tashlang.</b></p>";
?>