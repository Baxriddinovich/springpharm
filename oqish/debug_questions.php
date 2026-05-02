<?php
require_once 'db.php';
$moduleId = 8; // From previous logs
$stmt = $pdo->prepare("SELECT COUNT(*) FROM test_questions WHERE module_id = ?");
$stmt->execute([$moduleId]);
echo "Questions for Module 8: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->prepare("SELECT * FROM test_questions WHERE module_id = ?");
$stmt->execute([$moduleId]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
