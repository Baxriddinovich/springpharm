<?php
require_once 'db.php';
function generateCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function checkRateLimit(string $key): array
{
    $k = 'rl_' . md5($key);

    if (!isset($_SESSION[$k])) {
        $_SESSION[$k] = ['n' => 0, 't' => 0];
    }
    $d = &$_SESSION[$k];

    if ($d['n'] >= 5 && (time() - $d['t']) < 900) {
        return ['ok' => false, 'sec' => 900 - (time() - $d['t'])];
    }
    if ($d['n'] >= 5) {
        $d = ['n' => 0, 't' => 0];
    }
    return ['ok' => true];
}
function failAttempt(string $key): void
{
    $k = 'rl_' . md5($key);
    if (!isset($_SESSION[$k])) {
        $_SESSION[$k] = ['n' => 0, 't' => 0];
    }
    $_SESSION[$k]['n']++;
    $_SESSION[$k]['t'] = time();
}
function clearAttempts(string $key): void
{
    unset($_SESSION['rl_' . md5($key)]);
}

function dashUrl(string $role): string
{
    $urls = [
        'super_admin' => 'dashboard.php',
        'bosh_auditor' => 'bosh_auditor/',
        'auditor' => 'auditor/',
        'viewer' => 'viewer/',
        'reader' => 'reader/'
    ];
    return $urls[$role] ?? 'dashboard.php';
}

$csrf = generateCSRF();

if (isLoggedIn()) {
    header('Location: ' . dashUrl($_SESSION['user_role'] ?? ''));
    exit;
}

$error = '';
$errType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik xatosi! Sahifani qayta yuklang.';
        $errType = 'error';

    } else {

        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username !== '' && $password !== '') {

            $rl = checkRateLimit($username);
            if (!$rl['ok']) {
                $m = ceil($rl['sec'] / 60);
                $error = "Juda ko'p urinish! {$m} daqiqadan so‘ng urinib ko‘ring.";
                $errType = 'locked';

            } else {

                $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user) {


                    $passwordOk = false;

                    if (preg_match('/^\$2y\$/', $user['password'])) {
                        if (password_verify($password, $user['password'])) {
                            $passwordOk = true;
                        }
                    } else {
                        if ($password === $user['password']) {
                            $passwordOk = true;
                        }
                    }

                    if ($passwordOk) {

                        clearAttempts($username);
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'];

                        // Log activity
                        logActivity('login', 'Tizimga muvaffaqiyatli kirdi', 'login');

                        header('Location: ' . dashUrl($user['role']));
                        exit;

                    } else {
                        failAttempt($username);
                        $error = 'Login yoki parol noto‘g‘ri!';
                        $errType = 'error';
                    }

                } else {
                    failAttempt($username);
                    $error = 'Login yoki parol noto‘g‘ri!';
                    $errType = 'error';
                }
            }

        } else {
            $error = 'Barcha maydonlarni to‘ldiring!';
            $errType = 'warning';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMP Audit Tizimi - Kirish</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0f1a;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --accent-cyan: #06b6d4;
            --accent-teal: #14b8a6;
            --accent-blue: #3b82f6;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .bg-grid {
            position: fixed;
            inset: 0;
            background-image: linear-gradient(rgba(6, 182, 212, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(6, 182, 212, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% {
                transform: translate(0, 0);
            }

            100% {
                transform: translate(50px, 50px);
            }
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 15s ease-in-out infinite;
        }

        .orb-1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #06b6d4, transparent 70%);
            top: -100px;
            right: -100px;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, #14b8a6, transparent 70%);
            bottom: -50px;
            left: -50px;
            animation-delay: -5s;
        }

        .orb-3 {
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, #3b82f6, transparent 70%);
            top: 50%;
            left: 30%;
            animation-delay: -10s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(30px, -30px) scale(1.1);
            }

            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }
        }

        .login-card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(51, 65, 85, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(6, 182, 212, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            border-color: rgba(6, 182, 212, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(6, 182, 212, 0.1);
        }

        .input-field {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .input-field:focus {
            border-color: var(--accent-cyan);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2);
            outline: none;
        }

        .input-field::placeholder {
            color: #64748b;
        }

        .btn-primary {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 50%, #0e7490 100%);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.2) 50%, transparent 100%);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .btn-primary:hover::before {
            transform: translateX(100%);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .logo-icon {
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                filter: drop-shadow(0 0 10px rgba(6, 182, 212, 0.5));
            }

            50% {
                filter: drop-shadow(0 0 20px rgba(6, 182, 212, 0.8));
            }
        }

        .feature-card {
            background: rgba(26, 35, 50, 0.4);
            border: 1px solid rgba(51, 65, 85, 0.3);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            background: rgba(26, 35, 50, 0.6);
            border-color: rgba(6, 182, 212, 0.3);
            transform: translateY(-4px);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            animation: shake 0.5s ease;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            animation: shake 0.5s ease;
        }

        .alert-locked {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            animation: shake 0.5s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease forwards;
        }

        .fade-in-delay-1 {
            animation-delay: 0.2s;
        }

        .fade-in-delay-2 {
            animation-delay: 0.4s;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-grid"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="relative z-10 w-full max-w-6xl mx-auto grid lg:grid-cols-2 gap-8 items-center">

        <!-- Chap tomon -->
        <div class="hidden lg:block px-8 fade-in">
            <div class="mb-8">
                <div class="flex items-center gap-4 mb-6">
                    <div
                        class="logo-icon w-16 h-16 rounded-2xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-white">GMP Audit</h1>
                        <p class="text-cyan-400 font-mono text-sm">v2.0 Professional</p>
                    </div>
                </div>
                <h2 class="text-4xl font-bold text-white mb-4 leading-tight">
                    Farmatsevtika<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-teal-400">Sifat
                        Nazorati</span><br>
                    Tizimi
                </h2>
                <p class="text-slate-400 text-lg mb-8">GMP talablariga mos audit jarayonlarini to'liq
                    avtomatlashtiruvchi professional boshqaruv tizimi.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="feature-card rounded-xl p-4">
                    <div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <h3 class="text-white font-semibold mb-1">Smart Checklist</h3>
                    <p class="text-slate-500 text-sm">Avtomatik savollar va baholash</p>
                </div>
                <div class="feature-card rounded-xl p-4">
                    <div class="w-10 h-10 rounded-lg bg-teal-500/20 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-white font-semibold mb-1">Real-time</h3>
                    <p class="text-slate-500 text-sm">Real vaqtli monitoring</p>
                </div>
                <div class="feature-card rounded-xl p-4">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-white font-semibold mb-1">PDF Hisobot</h3>
                    <p class="text-slate-500 text-sm">Avtomatik hisobot generatsiya</p>
                </div>
                <div class="feature-card rounded-xl p-4">
                    <div class="w-10 h-10 rounded-lg bg-amber-500/20 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-white font-semibold mb-1">Xavfsiz</h3>
                    <p class="text-slate-500 text-sm">Rol asosidagi kirish</p>
                </div>
            </div>
        </div>

        <!-- O'ng tomon - Forma -->
        <div class="w-full max-w-md mx-auto fade-in fade-in-delay-2">
            <div class="login-card rounded-3xl p-8 md:p-10">
                <div class="lg:hidden flex items-center justify-center gap-3 mb-8">
                    <div
                        class="logo-icon w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-white">GMP Audit</h1>
                </div>

                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-white mb-2">Tizimga kirish</h2>
                    <p class="text-slate-400">Hisob ma'lumotlaringizni kiriting</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-<?php echo $errType; ?> rounded-xl p-4 mb-6 flex items-center gap-3">
                        <?php if ($errType === 'locked'): ?>
                            <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="text-amber-300 text-sm"><?php echo $error; ?></span>
                        <?php elseif ($errType === 'warning'): ?>
                            <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <span class="text-amber-300 text-sm"><?php echo $error; ?></span>
                        <?php else: ?>
                            <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-red-300 text-sm"><?php echo $error; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

                    <div class="space-y-5">
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-2">Foydalanuvchi nomi</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </span>
                                <input type="text" name="username"
                                    class="input-field w-full pl-12 pr-4 py-3.5 rounded-xl text-white"
                                    placeholder="username yoki email" required autocomplete="username">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-2">Parol</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </span>
                                <input type="password" name="password" id="passwordInput"
                                    class="input-field w-full pl-12 pr-12 py-3.5 rounded-xl text-white"
                                    placeholder="••••••••" required autocomplete="current-password">
                                <button type="button" id="togglePassword"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-cyan-400 transition-colors">
                                    <svg class="w-5 h-5" id="eyeIcon" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="remember"
                                    class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-0">
                                <span class="text-slate-400 group-hover:text-slate-300 transition-colors">Eslab
                                    qolish</span>
                            </label>
                            <a href="#" class="text-cyan-400 hover:text-cyan-300 transition-colors">Parolni
                                unutdingizmi?</a>
                        </div>

                        <button type="submit" id="submitBtn"
                            class="btn-primary w-full py-4 rounded-xl text-white font-semibold text-lg mt-4">
                            <span class="flex items-center justify-center gap-2" id="btnText">
                                Kirish
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </form>

                <div class="mt-8 p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                    <p class="text-slate-400 text-sm mb-2 font-medium">Demo kirish ma'lumotlari:</p>
                    <div class="font-mono text-xs space-y-1">
                        <p class="text-slate-500">Username: <span class="text-cyan-400">admin</span></p>
                        <p class="text-slate-500">Password: <span class="text-cyan-400">password</span></p>
                    </div>
                </div>
            </div>

            <p class="text-center text-slate-600 text-sm mt-6">© 2024 GMP Audit System. Barcha huquqlar himoyalangan.
            </p>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            eyeIcon.innerHTML = isPassword
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        });

        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');

        loginForm.addEventListener('submit', () => {
            submitBtn.disabled = true;
            btnText.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Tekshirilmoqda...';
        });

        submitBtn.addEventListener('click', function (e) {
            const rect = submitBtn.getBoundingClientRect();
            const ripple = document.createElement('span');
            const size = Math.max(rect.width, rect.height);
            ripple.style.cssText = `position:absolute;background:rgba(255,255,255,0.3);border-radius:50%;transform:scale(0);animation:ripple 0.6s linear;pointer-events:none;width:${size}px;height:${size}px;left:${e.clientX - rect.left - size / 2}px;top:${e.clientY - rect.top - size / 2}px;`;
            submitBtn.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    </script>
</body>

</html>