<?php
date_default_timezone_set('Asia/Tashkent');
require_once '../db.php';

// Reader session ma'lumotlarini tozalash
$keysToRemove = [
    'reader_user_id',
    'reader_username',
    'reader_full_name',
    'reader_role',
    'reader_email',
    'reader_login_time',
    'reader_materials_viewed',
    'reader_materials_completed',
    'reader_test_results',
];

foreach ($keysToRemove as $key) {
    unset($_SESSION[$key]);
}

// Agar boshqa session ma'lumotlari bo'lmasa, sessiyani to'liq yo'q qilish
if (empty($_SESSION)) {
    session_destroy();
}

header("Location: index.php");
exit;
