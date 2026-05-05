<?php
date_default_timezone_set('Asia/Tashkent');
// users.php - Foydalanuvchilarni boshqarish (MUKAMMAL VERSIYA)
require_once 'db.php';
requireLogin();

$user = getCurrentUser();

// в­ђ Role-based access (graceful redirect)
if (!in_array($user['role'], ['super_admin', 'bosh_auditor'])) {
    $_SESSION['flash_message'] = "Bu sahifaga kirish uchun admin huquqi talab etiladi!";
    $_SESSION['flash_type'] = 'danger';
    header("Location: dashboard.php");
    exit;
}

// в­ђ CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$positions = $pdo->query("SELECT id, name FROM positions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

function verifyCsrf(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

$message = '';
$error = '';
$messageType = 'success';

// ----------------- PHP LOGIC -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!verifyCsrf()) {
        $error = "Xavfsizlik xatosi! Sahifani qayta yuklang.";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];

        // 1. Yangi foydalanuvchi qo'shish
        if ($action === 'add_user') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fullName = sanitize($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? '';
            $positionId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
            $password = $_POST['password'] ?? '';

            if ($role === 'super_admin' && $user['role'] !== 'super_admin') {
                $error = "Faqat Super Admin yangi Super Admin yarata oladi!";
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                $error = "Username faqat harflar, raqamlar va pastki chiziqdan iborat bo'lishi kerak (3-30 belgi)!";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email manzili noto'g'ri formatda!";
            } elseif (strlen($password) < 4) {
                $error = "Parol kamida 4 ta belgidan iborat bo'lishi kerak!";
            } elseif (empty($fullName)) {
                $error = "F.I.Sh maydonini to'ldiring!";
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                if ($check->fetch()) {
                    $error = "Bu username yoki email allaqachon mavjud!";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, role, password, position_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $fullName, $role, $hashedPassword, $positionId]);
                    logActivity('user_added', "Yangi foydalanuvchi qo'shildi: $fullName ($role)");
                    $_SESSION['flash_message'] = "Foydalanuvchi muvaffaqiyatli qo'shildi!";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: users.php");
                    exit;
                }
            }
        }

        // 2. Foydalanuvchini tahrirlash
        if ($action === 'edit_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $fullName = sanitize($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? '';
            $positionId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
            $password = $_POST['password'] ?? '';

            if ($userId === $user['id'] && $role !== $user['role'] && $user['role'] === 'super_admin') {
                // Super Admin o'z rolini o'zgartirishi mumkin (lekin ehtiyot bo'lsin)
            }

            $targetUser = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $targetUser->execute([$userId]);
            $targetRole = $targetUser->fetchColumn();

            if ($targetRole === 'super_admin' && $user['role'] !== 'super_admin') {
                $error = "Super Admin ma'lumotlarini faqat Super Admin o'zgartira oladi!";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email manzili noto'g'ri formatda!";
            } else {
                $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, position_id = ?";
                $params = [$fullName, $email, $role, $positionId];

                if (!empty($password)) {
                    if (strlen($password) < 4) {
                        $error = "Yangi parol kamida 4 ta belgidan iborat bo'lishi kerak!";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $sql .= ", password = ?";
                        $params[] = $hashedPassword;
                    }
                }

                if (!$error) {
                    $sql .= " WHERE id = ?";
                    $params[] = $userId;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    logActivity('user_edited', "Foydalanuvchi yangilandi: $fullName (ID: $userId)");
                    $_SESSION['flash_message'] = "Ma'lumotlar muvaffaqiyatli yangilandi!";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: users.php");
                    exit;
                }
            }
        }

        // 3. Foydalanuvchini o'chirish (Soft Delete yoki Hard Delete)
        if ($action === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId === $user['id']) {
                $error = "O'zingizni o'chira olmaysiz!";
            } else {
                $targetStmt = $pdo->prepare("SELECT role, full_name FROM users WHERE id = ?");
                $targetStmt->execute([$userId]);
                $targetUser = $targetStmt->fetch();

                if ($targetUser && $targetUser['role'] === 'super_admin' && $user['role'] !== 'super_admin') {
                    $error = "Super Adminni faqat Super Admin o'chira oladi!";
                } else {
                    try {
                        $pdo->beginTransaction();

                        // в­ђ Bog'liq ma'lumotlarni tozalash (agar cascade yo'q bo'lsa)
                        $pdo->prepare("DELETE FROM audit_answers WHERE auditor_id = ?")->execute([$userId]);
                        $pdo->prepare("DELETE FROM audit_assignments WHERE auditor_id = ?")->execute([$userId]);
                        $pdo->prepare("UPDATE audits SET created_by = ? WHERE created_by = ?")->execute([$user['id'], $userId]);

                        // Foydalanuvchini o'chirish
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);

                        $pdo->commit();

                        logActivity('user_deleted', "Foydalanuvchi o'chirildi: {$targetUser['full_name']}");
                        $_SESSION['flash_message'] = "Foydalanuvchi tizimdan o'chirildi!";
                        $_SESSION['flash_type'] = 'success';
                        header("Location: users.php");
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "O'chirishda xatolik yuz berdi! Foydalanuvchi bog'liq ma'lumotlarga ega bo'lishi mumkin.";
                    }
                }
            }
        }
    }
}

// в­ђ Flash message ni o'qish
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Foydalanuvchilar ro'yxati
$usersList = $pdo->query("
    SELECT u.*, p.name AS position_name,
           (SELECT COUNT(*) FROM audit_assignments aa WHERE aa.auditor_id = u.id) as audit_count,
           (SELECT COUNT(*) FROM audit_answers aaa WHERE aaa.auditor_id = u.id) as answers_count
    FROM users u 
    LEFT JOIN positions p ON p.id = u.position_id
    ORDER BY 
        CASE u.role 
            WHEN 'super_admin' THEN 1 
            WHEN 'bosh_auditor' THEN 2 
            WHEN 'auditor' THEN 3 
            WHEN 'reader' THEN 4
            ELSE 5
        END,
        u.created_at DESC
")->fetchAll();

// в­ђ Statistika
$stats = [
    'total' => count($usersList),
    'admins' => count(array_filter($usersList, fn($u) => $u['role'] === 'super_admin')),
    'lead_auditors' => count(array_filter($usersList, fn($u) => $u['role'] === 'bosh_auditor')),
    'auditors' => count(array_filter($usersList, fn($u) => $u['role'] === 'auditor')),
    'reader' => count(array_filter($usersList, fn($u) => $u['role'] === 'reader')),
];
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foydalanuvchilar - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0f1a;
            --accent-cyan: #06b6d4;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-primary);
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #111827 0%, #0f172a 100%);
            border-right: 1px solid rgba(51, 65, 85, 0.5);
            transition: transform 0.3s ease-in-out;
        }

        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
            }

            .sidebar.active {
                transform: translateX(0);
            }
        }

        .nav-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(6, 182, 212, 0.1);
            border-left-color: var(--accent-cyan);
        }

        .nav-item.active {
            background: rgba(6, 182, 212, 0.15);
            border-left-color: var(--accent-cyan);
        }

        /* Cards & Forms */
        .stat-card {
            background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.5);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: rgba(6, 182, 212, 0.3);
        }

        .input-field {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid #334155;
            transition: all 0.3s ease;
            color: white;
        }

        .input-field:focus {
            border-color: var(--accent-cyan);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2);
            outline: none;
        }

        .input-field::placeholder {
            color: #475569;
        }

        .input-field.error {
            border-color: #ef4444;
        }

        .btn-primary {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4);
        }

        /* Modals */
        .modal-backdrop {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease;
        }

        .modal-content {
            background: linear-gradient(135deg, #1a2332 0%, #111827 100%);
            border: 1px solid rgba(51, 65, 85, 0.5);
            animation: modalIn 0.3s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

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

        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
        }

        .delay-1 {
            animation-delay: 0.1s;
            opacity: 0;
        }

        .delay-2 {
            animation-delay: 0.2s;
            opacity: 0;
        }

        .delay-3 {
            animation-delay: 0.3s;
            opacity: 0;
        }

        .user-row {
            transition: all 0.2s ease;
        }

        .user-row:hover {
            background: rgba(30, 41, 59, 0.3);
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 3px;
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="min-h-screen text-slate-100">

    <!-- Mobile Header -->
<?php $activePage = "users"; include "inc/sidebar.php"; ?>



        <!-- Main Content -->
        <main class="flex-1 lg:ml-64 w-full">
            <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50">
                <div class="px-4 lg:px-8 py-4 flex justify-between items-center">
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold text-white">Foydalanuvchilar</h1>
                        <p class="text-slate-500 text-sm">Tizim foydalanuvchilarini boshqarish</p>
                    </div>
                    <button onclick="openModal('add')" class="flex items-center gap-2 btn-primary px-4 py-2.5 rounded-xl font-medium text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="hidden sm:inline">Yangi qo'shish</span>
                    </button>
                </div>
            </header>

            <div class="p-4 lg:p-8">
                <!-- в­ђ Flash Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-xl border animate-in <?php echo $messageType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-300'; ?>">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm"><?php echo $message; ?></span>
                            <button onclick="this.closest('div').remove()" class="ml-auto opacity-50 hover:opacity-100"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg></button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- в­ђ Statistikalar -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['total']; ?></div>
                        <div class="text-slate-500 text-sm">Jami foydalanuvchilar</div>
                    </div>
                    <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                        <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['admins']; ?></div>
                        <div class="text-slate-500 text-sm">Super Admin</div>
                    </div>
                    <div class="stat-card rounded-2xl p-5 animate-in delay-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['lead_auditors']; ?></div>
                        <div class="text-slate-500 text-sm">Bosh Auditor</div>
                    </div>
                    <div class="stat-card rounded-2xl p-5 animate-in delay-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['auditors']; ?></div>
                        <div class="text-slate-500 text-sm">Auditor</div>
                    </div>
                </div>

                <!-- Foydalanuvchilar Jadvali -->
                <div class="stat-card rounded-2xl overflow-hidden animate-in delay-3">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-800/30">
                                <tr>
                                    <th class="px-6 py-4 text-slate-400 text-xs font-medium uppercase tracking-wider">Foydalanuvchi</th>
                                    <th class="px-6 py-4 text-slate-400 text-xs font-medium uppercase tracking-wider hidden md:table-cell">Username</th>
                                    <th class="px-6 py-4 text-slate-400 text-xs font-medium uppercase tracking-wider hidden lg:table-cell">Email</th>
                                    <th class="px-6 py-4 text-slate-400 text-xs font-medium uppercase tracking-wider hidden lg:table-cell">Lavozim</th>
                                    <th class="px-6 py-4 text-slate-400 text-xs font-medium uppercase tracking-wider text-center">Rol</th>
                                    <th class="px-6 py-4 text-slate-400 text-xs font-medium uppercase tracking-wider text-center hidden sm:table-cell">Faoliyat</th>
                                    <th class="px-6 py-4 text-slate-400 text-xs font-medium uppercase tracking-wider text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach ($usersList as $u):
                                    $roleColors = [
                                        'super_admin' => 'bg-red-500/15 text-red-400 border-red-500/20',
                                        'bosh_auditor' => 'bg-amber-500/15 text-amber-400 border-amber-500/20',
                                        'auditor' => 'bg-cyan-500/15 text-cyan-400 border-cyan-500/20',
                                        'viewer' => 'bg-slate-500/15 text-slate-400 border-slate-500/20',
                                        'reader' => 'bg-slate-500/15 text-slate-400 border-slate-500/20'
                                    ];
                                    $roleLabels = [
                                        'super_admin' => 'Super Admin',
                                        'bosh_auditor' => 'Bosh Auditor',
                                        'auditor' => 'Auditor',
                                        'viewer' => 'Ko\'ruvchi',
                                        'reader' => 'reader'
                                    ];
                                    $isMe = $u['id'] === $user['id'];
                                ?>
                                    <tr class="user-row <?php echo $isMe ? 'bg-cyan-500/5' : ''; ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500/30 to-teal-500/30 flex items-center justify-center text-cyan-400 font-semibold text-sm flex-shrink-0">
                                                    <?php echo strtoupper(mb_substr($u['full_name'], 0, 1)); ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-white font-medium truncate"><?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <div class="text-xs text-slate-500 md:hidden font-mono"><?php echo htmlspecialchars($u['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-mono text-cyan-400 text-sm hidden md:table-cell"><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td class="px-6 py-4 text-slate-400 text-sm hidden lg:table-cell"><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td class="px-6 py-4 text-slate-400 text-sm hidden lg:table-cell"><?php echo htmlspecialchars($u['position_name'] ?? 'Belgilanmagan'); ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo $roleColors[$u['role']] ?? ''; ?>">
                                                <?php echo $roleLabels[$u['role']] ?? $u['role']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center hidden sm:table-cell">
                                            <div class="text-xs text-slate-500">
                                                <span title="Biriktirilgan auditlar"><?php echo $u['audit_count']; ?> audit</span> В·
                                                <span title="Berilgan javoblar"><?php echo $u['answers_count']; ?> javob</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <?php if ($isMe): ?>
                                                    <span class="text-xs text-slate-600 px-2 py-1">O'zgartirish cheklangan</span>
                                                <?php else: ?>
                                                    <button onclick='openModal("edit", <?php echo htmlspecialchars(json_encode(['id' => $u['id'], 'full_name' => $u['full_name'], 'username' => $u['username'], 'email' => $u['email'], 'role' => $u['role'], 'position_id' => $u['position_id']], JSON_UNESCAPED_UNICODE)); ?>)'
                                                        class="p-2 text-slate-500 hover:text-cyan-400 hover:bg-cyan-500/10 rounded-lg transition-colors" title="Tahrirlash">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['full_name'])); ?>')"
                                                        class="p-2 text-slate-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors" title="O'chirish">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Modal -->
    <div id="userModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="closeModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="modal-content relative w-full max-w-md rounded-2xl p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-white">Yangi Foydalanuvchi</h3>
                    <button onclick="closeModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="" id="userForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" id="formAction" value="add_user">
                    <input type="hidden" name="user_id" id="userId" value="">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">F.I.Sh <span class="text-red-400">*</span></label>
                            <input type="text" name="full_name" id="formName" required class="input-field w-full px-4 py-2.5 rounded-xl" placeholder="Ism Familiya" maxlength="150">
                        </div>
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Username <span class="text-red-400">*</span></label>
                            <input type="text" name="username" id="formUsername" required class="input-field w-full px-4 py-2.5 rounded-xl font-mono" placeholder="login123" maxlength="30">
                            <p class="text-xs text-slate-600 mt-1">Harflar, raqamlar va _ (3-30 belgi)</p>
                        </div>
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Email <span class="text-red-400">*</span></label>
                            <input type="email" name="email" id="formEmail" required class="input-field w-full px-4 py-2.5 rounded-xl" placeholder="email@example.com" maxlength="150">
                        </div>

                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">
                                Parol <span class="text-red-400" id="pwdReqStar">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" name="password" id="formPassword" class="input-field w-full px-4 py-2.5 pr-12 rounded-xl" placeholder="Min. 4 ta belgi" minlength="4">
                                <button type="button" onclick="togglePwd()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-cyan-400 transition-colors" aria-label="Parolni ko'rsatish">
                                    <svg id="pwdEyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-xs text-slate-600 mt-1 hidden" id="pwdHint">O'zgartirmasangiz bo'sh qoldiring</p>
                        </div>

                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Rol <span class="text-red-400">*</span></label>
                            <select name="role" id="formRole" required class="input-field w-full px-4 py-2.5 rounded-xl">
                                <?php if ($user['role'] === 'super_admin'): ?>
                                    <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                                <option value="bosh_auditor">Bosh Auditor</option>
                                <option value="auditor">Auditor</option>
                                <option value="viewer">Ko'ruvchi</option>
                                <option value="reader">Reader</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Lavozim</label>
                            <select name="position_id" id="formPositionId" class="input-field w-full px-4 py-2.5 rounded-xl">
                                <option value="">Belgilanmagan</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo (int)$position['id']; ?>"><?php echo htmlspecialchars($position['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="closeModal()" class="flex-1 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-colors font-medium">Bekor</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl btn-primary text-white font-semibold flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="hideDeleteModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="modal-content relative w-full max-w-sm rounded-2xl p-6 text-center">
                <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Foydalanuvchini o'chirish</h3>
                <p class="text-slate-400 mb-1">Siz ushbu foydalanuvchini o'chirmoqchisiz:</p>
                <p id="deleteUserName" class="text-cyan-400 font-medium mb-4"></p>
                <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-3 mb-6 text-xs text-red-300">
                    Diqqat: Foydalanuvchi auditlari va javoblari qayta taqsimlanadi yoki tizim tomonidan bog'lanadi.
                </div>

                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId" value="">
                    <div class="flex gap-3">
                        <button type="button" onclick="hideDeleteModal()" class="flex-1 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-colors">Bekor</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white font-medium transition-colors">O'chirish</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        function openModal(mode, data = null) {
            const modal = document.getElementById('userModal');
            const title = document.getElementById('modalTitle');
            const pwdInput = document.getElementById('formPassword');
            const pwdStar = document.getElementById('pwdReqStar');
            const pwdHint = document.getElementById('pwdHint');

            document.getElementById('userForm').reset();

            if (mode === 'add') {
                title.textContent = 'Yangi Foydalanuvchi';
                document.getElementById('formAction').value = 'add_user';
                document.getElementById('userId').value = '';
                pwdInput.required = true;
                pwdInput.placeholder = 'Min. 4 ta belgi';
                pwdStar.classList.remove('hidden');
                pwdHint.classList.add('hidden');
            } else if (mode === 'edit' && data) {
                title.textContent = "Foydalanuvchini Tahrirlash";
                document.getElementById('formAction').value = 'edit_user';
                document.getElementById('userId').value = data.id;
                document.getElementById('formName').value = data.full_name;
                document.getElementById('formUsername').value = data.username;
                document.getElementById('formEmail').value = data.email;
                document.getElementById('formRole').value = data.role;
                document.getElementById('formPositionId').value = data.position_id || '';
                pwdInput.required = false;
                pwdInput.placeholder = "O'zgartirmasangiz bo'sh qoldiring";
                pwdStar.classList.add('hidden');
                pwdHint.classList.remove('hidden');
            }
            modal.classList.remove('hidden');
            setTimeout(() => document.getElementById('formName').focus(), 100);
        }

        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        function confirmDelete(id, name) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function togglePwd() {
            const input = document.getElementById('formPassword');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
                hideDeleteModal();
            }
        });

        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 1024) toggleSidebar();
            });
        });
    </script>
</body>

</html>
