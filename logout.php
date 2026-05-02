<?php
// logout.php
require_once 'db.php';
logActivity('logout', 'Tizimdan chiqdi', 'logout');
session_destroy();
header('Location: index.php');
exit;
?>