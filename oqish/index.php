<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Tashkent');

require_once '../db.php';

if (isset($_SESSION['reader_user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Iltimos, foydalanuvchi nomi va parolni kiriting.';
    } else {
        // Foydalanuvchini username bo'yicha qidiramiz
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Role tekshirish: faqat 'reader' kirishi mumkin
            if ($user['role'] !== 'reader') {
                $error = 'Sizga bu tizimga kirish huquqi berilmagan. Faqat o\'quvchilar (reader) kira oladi.';
            } else {
                // Parolni tekshirish: 1) Shifrlangan (bcrypt)  2) Shifrlanmagan (oddiy)
                $passwordValid = false;

                // 1-usul: password_verify() — bcrypt/hash bilan shifrlangan parol
                if (password_verify($password, $user['password'])) {
                    $passwordValid = true;
                }

                // 2-usul: Oddiy (shifrlanmagan) solishtirish
                if (!$passwordValid && $password === $user['password']) {
                    $passwordValid = true;

                    // XAVFSIZLIK: Agar parol shifrlanmagan bo'lsa, avtomatik shifrlaymiz
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$hashedPassword, $user['id']]);
                }

                // 3-usul: md5() bilan shifrlangan bo'lishi mumkin (eski tizimlar)
                if (!$passwordValid && md5($password) === $user['password']) {
                    $passwordValid = true;

                    // XAVFSIZLIK: md5 dan bcrypt ga o'tkazamiz
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$hashedPassword, $user['id']]);
                }

                if ($passwordValid) {
                    // Sessiyaga ma'lumotlarni yozamiz
                    $_SESSION['reader_user_id'] = $user['id'];
                    $_SESSION['reader_username'] = $user['username'];
                    $_SESSION['reader_full_name'] = $user['full_name'];
                    $_SESSION['reader_role'] = $user['role'];
                    $_SESSION['reader_email'] = $user['email'];
                    $_SESSION['reader_login_time'] = time();

                    // Oxirgi kirish vaqtini yangilash (ixtiyoriy)
                    $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")->execute([$user['id']]);

                    // Dashboardga yo'naltirish
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = 'Foydalanuvchi nomi yoki parol noto\'g\'ri.';
                }
            }
        } else {
            $error = 'Foydalanuvchi nomi yoki parol noto\'g\'ri.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirish - GMP O'quv Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0f1a;
            --accent-cyan: #06b6d4;
            --glass-bg: rgba(26, 35, 50, 0.7);
            --glass-border: rgba(51, 65, 85, 0.5);
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-primary);
            color: #f1f5f9;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #0f172a;
        }

        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
        }

        /* Animated gradient background */
        .login-bg {
            background: linear-gradient(135deg, #0a0f1a 0%, #0f172a 40%, #0c1524 70%, #0a0f1a 100%);
            position: relative;
            overflow: hidden;
        }

        .login-bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 20%, rgba(6, 182, 212, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 80%, rgba(20, 184, 166, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(6, 182, 212, 0.03) 0%, transparent 70%);
            animation: bgPulse 8s ease-in-out infinite alternate;
        }

        @keyframes bgPulse {
            0% {
                transform: scale(1) rotate(0deg);
                opacity: 0.8;
            }

            100% {
                transform: scale(1.1) rotate(2deg);
                opacity: 1;
            }
        }

        /* Floating particles */
        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(6, 182, 212, 0.4);
            border-radius: 50%;
            animation: float linear infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-10vh) scale(1);
                opacity: 0;
            }
        }

        /* Input focus glow */
        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15), 0 0 20px rgba(6, 182, 212, 0.1);
        }

        /* Button shine */
        .btn-shine {
            position: relative;
            overflow: hidden;
        }

        .btn-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 40%;
            height: 200%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: skewX(-25deg);
            transition: left 0.6s ease;
        }

        .btn-shine:hover::after {
            left: 120%;
        }

        /* Shake animation for errors */
        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-8px);
            }

            50% {
                transform: translateX(8px);
            }

            75% {
                transform: translateX(-4px);
            }
        }

        .shake {
            animation: shake 0.4s ease-in-out;
        }

        /* Fade in */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>

<body class="min-h-screen login-bg flex items-center justify-center p-4">

    <!-- Floating Particles -->
    <div id="particles" class="fixed inset-0 pointer-events-none z-0"></div>

    <!-- Login Card -->
    <div class="relative z-10 w-full max-w-md fade-in-up">
        <div class="glass-card rounded-2xl shadow-2xl shadow-cyan-500/5 overflow-hidden">

            <!-- Header -->
            <div class="px-8 pt-8 pb-6 text-center">
                <!-- Logo -->
                <div
                    class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                        </path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 to-teal-400">
                    GMP O'quv Tizimi
                </h1>
                <p class="text-sm text-slate-500 mt-1">Spring Pharmaceutical</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div id="errorMsg"
                    class="mx-8 mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg flex items-start gap-3 shake">
                    <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm text-red-300"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success)): ?>
                <div class="mx-8 mb-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 text-emerald-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm text-emerald-300"><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="" method="POST" class="px-8 pb-8 space-y-5" id="loginForm">

                <!-- Username -->
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2 uppercase tracking-wider">Foydalanuvchi
                        nomi</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <input type="text" name="username" id="username" required autocomplete="username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            class="input-glow w-full bg-slate-950/80 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-600 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                            placeholder="Username kiriting...">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2 uppercase tracking-wider">Parol</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                </path>
                            </svg>
                        </div>
                        <input type="password" name="password" id="password" required autocomplete="current-password"
                            class="input-glow w-full bg-slate-950/80 border border-slate-700 rounded-xl pl-10 pr-12 py-3 text-sm text-white placeholder-slate-600 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                            placeholder="Parolni kiriting...">
                        <!-- Parolni ko'rish/yashirish tugmasi -->
                        <button type="button" onclick="togglePassword()"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition">
                            <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                </path>
                            </svg>
                            <svg id="eyeOffIcon" class="w-4 h-4 hidden" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21">
                                </path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="loginBtn"
                    class="btn-shine w-full bg-gradient-to-r from-cyan-600 to-teal-600 hover:from-cyan-500 hover:to-teal-500 text-white py-3 rounded-xl text-sm font-semibold shadow-lg shadow-cyan-500/20 transition-all duration-300 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                        </path>
                    </svg>
                    <span id="loginBtnText">Tizimga kirish</span>
                    <svg id="loginSpinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </button>
            </form>

            <!-- Footer -->
            <div class="px-8 pb-6 text-center">
                <div class="border-t border-slate-800 pt-4">
                    <p class="text-xs text-slate-600">
                        Faqat <span class="text-cyan-500 font-medium">reader</span> rolidagi foydalanuvchilar kira oladi
                    </p>
                </div>
            </div>
        </div>

        <!-- Bottom info -->
        <div class="text-center mt-6">
            <p class="text-xs text-slate-600">
                &copy; <?php echo date('Y'); ?> Spring Pharmaceutical. GMP O'quv Boshqaruv Tizimi.
            </p>
        </div>
    </div>

    <script>
        // Parolni ko'rish/yashirish
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }

        // Login tugmasi loading holati
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('loginBtnText');
            const spinner = document.getElementById('loginSpinner');

            btn.disabled = true;
            btnText.textContent = 'Tekshirilmoqda...';
            spinner.classList.remove('hidden');
        });

        // Floating particles
        function createParticles() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 8 + 6) + 's';
                particle.style.animationDelay = (Math.random() * 5) + 's';
                particle.style.width = (Math.random() * 3 + 1) + 'px';
                particle.style.height = particle.style.width;
                container.appendChild(particle);
            }
        }
        createParticles();

        // Auto-focus username
        document.getElementById('username').focus();

        // Enter bilan keyingi maydonga o'tish
        document.getElementById('username').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
    </script>
</body>

</html>