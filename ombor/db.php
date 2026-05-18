<?php
// SERVER SOZLAMALARI (ombor_db uchun)
$host = 'localhost';
$db   = 'ombor_db'; 
$user = 'bobur_admin'; 
$pass = 'Boburbek13@@'; // Siz ko'rsatgan parol

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Baza bilan ulanishda xato: " . $e->getMessage());
}
?>