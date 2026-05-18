<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // Ikkita tekshiruv: 
        // 1. password_verify — shifrlangan parollar uchun
        // 2. $password === $user['password'] — shifrlanmagan (oddiy) parollar uchun
        if (password_verify($password, $user['password']) || $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['fullname'] = $user['fullname'];
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Parol xato!";
        }
    } else {
        $error = "Foydalanuvchi topilmadi yoki hisobingiz bloklangan!";
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirish - GXP PHARM SYSTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #2c3e50; --accent-color: #3498db; }
        body { background: #f4f7f6; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; }
        .login-box { background: #fff; padding: 45px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 420px; border-top: 5px solid var(--primary-color); }
        .logo-area { text-align: center; margin-bottom: 35px; }
        .logo-area i { font-size: 50px; color: var(--primary-color); }
        .logo-area h2 { font-weight: 800; color: var(--primary-color); margin-top: 10px; font-size: 24px; }
        .form-control { height: 50px; border-radius: 10px; border: 1px solid #e0e0e0; padding-left: 45px; }
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 15px; top: 17px; z-index: 10; color: #999; }
        .btn-login { background: var(--primary-color); border: none; height: 50px; border-radius: 10px; font-weight: 600; color: #fff; width: 100%; transition: 0.3s; }
        .btn-login:hover { background: #1a252f; transform: translateY(-2px); }
        .gxp-footer { text-align: center; margin-top: 30px; font-size: 11px; color: #bdc3c7; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>

<div class="login-box">
    <div class="logo-area">
        <i class="fas fa-shield-virus"></i>
        <h2>GXP SERVICE PHARM</h2>
        <p class="text-muted">Warehouse & Quality Control System</p>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger py-2 small text-center"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" class="form-control" placeholder="Login" required>
            </div>
        </div>
        <div class="mb-4">
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="Parol" required>
            </div>
        </div>
        <button type="submit" class="btn btn-login">TIZIMGA KIRISH</button>
    </form>

    <div class="gxp-footer">
        © <?= date('Y') ?> | GXP Service Pharm | System v1.0
    </div>
</div>

</body>
</html>