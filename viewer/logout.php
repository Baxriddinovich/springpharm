<?php
session_start();

// Sessionni tozalash
$_SESSION = [];

// Sessionni o‘chirish
session_destroy();

// Cookie ham bo‘lsa o‘chiramiz (optional)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Login sahifaga yo‘naltirish
header("Location: ../index.php");
exit;