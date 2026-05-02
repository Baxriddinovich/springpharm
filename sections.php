<?php
date_default_timezone_set('Asia/Tashkent');

// sections.php - GMP Bo'limlarini boshqarish (SMART LOG VERSIYA)
require_once 'db.php';
requireLogin();

 $user = getCurrentUser();

// ⭐ Role-based access (graceful redirect)
if (!in_array($user['role'], ['super_admin', 'bosh_auditor'])) {
    $_SESSION['flash_message'] = "Bu sahifaga kirish uchun admin huquqi talab etiladi!";
    $_SESSION['flash_type'] = 'danger';
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
 $csrfToken = $_SESSION['csrf_token'];

function verifyCsrf(): bool {
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

        // 1. Bo'lim qo'shish
        if ($action === 'add_section') {
            $number = sanitize($_POST['section_number'] ?? '');
            $name = sanitize($_POST['section_name'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');
            $order = (int)($_POST['sort_order'] ?? 0);

            if ($number !== '' && $name !== '') {
                // ⭐ Dublikat tekshirish
                $dupCheck = $pdo->prepare("SELECT id FROM gmp_sections WHERE section_number = ?");
                $dupCheck->execute([$number]);
                if ($dupCheck->fetch()) {
                    $error = "'$number' raqamli bo'lim allaqachon mavjud!";
                    $messageType = 'danger';
                } else {
                    // ⭐⭐⭐ SMART QUERY: INSERT
                    smartQuery("INSERT INTO gmp_sections (section_number, section_name, description, sort_order) VALUES (?, ?, ?, ?)", [$number, $name, $desc, $order], "Yangi bo'lim qo'shildi: $number - $name");
                    
                    $_SESSION['flash_message'] = "Bo'lim muvaffaqiyatli qo'shildi!";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: sections.php");
                    exit;
                }
            } else {
                $error = "Bo'lim raqami va nomi majburiy maydonlar!";
                $messageType = 'danger';
            }
        }

        // 2. Bo'limni tahrirlash
        if ($action === 'edit_section') {
            $id = (int)($_POST['section_id'] ?? 0);
            $number = sanitize($_POST['section_number'] ?? '');
            $name = sanitize($_POST['section_name'] ?? '');
            $desc = sanitize($_POST['description'] ?? '');
            $order = (int)($_POST['sort_order'] ?? 0);

            if ($id > 0 && $number !== '' && $name !== '') {
                // Dublikat tekshirish (o'zini hisobga olmasdan)
                $dupCheck = $pdo->prepare("SELECT id FROM gmp_sections WHERE section_number = ? AND id != ?");
                $dupCheck->execute([$number, $id]);
                if ($dupCheck->fetch()) {
                    $error = "'$number' raqamli boshqa bo'lim allaqachon mavjud!";
                    $messageType = 'danger';
                } else {
                    // ⭐⭐⭐ SMART QUERY: UPDATE
                    smartQuery("UPDATE gmp_sections SET section_number = ?, section_name = ?, description = ?, sort_order = ? WHERE id = ?", [$number, $name, $desc, $order, $id], "Bo'lim tahrirlandi: $number - $name (ID: $id)");
                    
                    $_SESSION['flash_message'] = "Bo'lim muvaffaqiyatli yangilandi!";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: sections.php");
                    exit;
                }
            } else {
                $error = "Ma'lumotlar to'liq emas!";
                $messageType = 'danger';
            }
        }

        // 3. Bo'limni o'chirish (Force Delete - Savollari bilan birga)
        if ($action === 'delete_section') {
            $id = (int)($_POST['section_id'] ?? 0);

            if ($id > 0) {
                // Bo'lim nomini log uchun oldin olish (chunki o'chirgandan keyin nom yo'qoladi)
                $nameStmt = $pdo->prepare("SELECT section_number, section_name FROM gmp_sections WHERE id = ?");
                $nameStmt->execute([$id]);
                $secInfo = $nameStmt->fetch();

                if ($secInfo) {
                    // 1. Bog'liq audit javoblarini o'chiramiz (Foreign Key xatosi bo'lmasligi uchun)
                    // Savollarni topib, ularning ID lari orqali javoblarni o'chiramiz
                    $delAnswers = $pdo->prepare("DELETE aa FROM audit_answers aa INNER JOIN checklist_questions cq ON aa.question_id = cq.id WHERE cq.section_id = ?");
                    $delAnswers->execute([$id]);

                    // 2. Bo'limdagi barcha savollarni o'chiramiz
                    $delQuestions = $pdo->prepare("DELETE FROM checklist_questions WHERE section_id = ?");
                    $delQuestions->execute([$id]);

                    // 3. Bo'limni o'chiramiz
                    smartQuery("DELETE FROM gmp_sections WHERE id = ?", [$id], "Bo'lim va ichidagi barcha ma'lumotlar o'chirildi: {$secInfo['section_number']} - {$secInfo['section_name']}");
                }
                
                $_SESSION['flash_message'] = "Bo'lim va uning ichidagi barcha savollar muvaffaqiyatli o'chirildi!";
                $_SESSION['flash_type'] = 'success';
                header("Location: sections.php");
                exit;
            }
        }

        // 4. Tartibni yangilash
        if ($action === 'update_order') {
            $id = (int)($_POST['section_id'] ?? 0);
            $newOrder = (int)($_POST['new_order'] ?? 0);

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE gmp_sections SET sort_order = ? WHERE id = ?");
                $stmt->execute([$newOrder, $id]);
            }
        }
    }
}

// ⭐ Flash message ni o'qish
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Bo'limlar ro'yxati
 $sections = $pdo->query("
    SELECT gs.*, 
           (SELECT COUNT(*) FROM checklist_questions cq WHERE cq.section_id = gs.id AND cq.is_active = 1) as question_count,
           (SELECT COUNT(DISTINCT aa.audit_id) FROM audit_answers aa 
            JOIN checklist_questions cq ON aa.question_id = cq.id 
            WHERE cq.section_id = gs.id) as audit_usage_count
    FROM gmp_sections gs 
    ORDER BY gs.sort_order ASC, gs.id ASC
")->fetchAll();

 $totalQuestions = array_sum(array_column($sections, 'question_count'));
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bo'limlar - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent-cyan: #06b6d4; }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #111827 0%, #0f172a 100%);
            border-right: 1px solid rgba(51, 65, 85, 0.5);
            transition: transform 0.3s ease-in-out;
        }
        @media (max-width: 1023px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; }
            .sidebar.active { transform: translateX(0); }
        }
        .nav-item { transition: all 0.3s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: rgba(6, 182, 212, 0.1); border-left-color: var(--accent-cyan); }
        .nav-item.active { background: rgba(6, 182, 212, 0.15); border-left-color: var(--accent-cyan); }
        
        /* Cards */
        .stat-card {
            background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.5);
            transition: all 0.3s ease;
        }
        .stat-card:hover { border-color: rgba(6, 182, 212, 0.3); }
        
        /* Form elements */
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
        .input-field::placeholder { color: #475569; }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4); }
        
        /* Modal */
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
            from { opacity: 0; transform: scale(0.95) translateY(10px); } 
            to { opacity: 1; transform: scale(1) translateY(0); } 
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        /* Section row */
        .section-row { transition: all 0.2s ease; }
        .section-row:hover { background: rgba(30, 41, 59, 0.3); }
        
        /* Sort buttons */
        .sort-btn {
            opacity: 0;
            transition: opacity 0.2s ease, background 0.2s ease;
        }
        .section-row:hover .sort-btn { opacity: 1; }
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.5s ease forwards; }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="min-h-screen text-slate-100">
    
    <!-- Mobile Header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <span class="font-bold text-white text-sm">GMP Audit</span>
        </div>
        <button onclick="toggleSidebar()" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors" aria-label="Menyu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <div class="flex min-h-screen pt-14 lg:pt-0">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 fixed h-full z-50" role="navigation" aria-label="Asosiy navigatsiya">
            <div class="p-6 h-full flex flex-col">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-500 font-mono">v2.0 Pro</p>
                    </div>
                </div>
                
                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <a href="dashboard.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Bosh panel
                    </a>
                    <a href="audits.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Auditlar
                    </a>
                    <a href="reports.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Hisobotlar
                    </a>
                    
                    <div class="pt-4 mt-4 border-t border-slate-700/50">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>
                        <a href="sections.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Bo'limlar
                        </a>
                        <a href="checklists.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Checklistlar
                        </a>
                        <a href="users.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            Auditorlar
                        </a>
                        <a href="logs.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            Tizim Tarixi
                        </a>
                    </div>
                </nav>
                
                <!-- User Info -->
                <div class="border-t border-slate-700/50 pt-4 mt-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center text-white font-semibold text-sm">
                            <?php echo strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                        </div>
                        <a href="logout.php" class="text-slate-500 hover:text-red-400 transition-colors p-2" aria-label="Chiqish">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 lg:ml-64 w-full">
            <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50">
                <div class="px-4 lg:px-8 py-4 flex justify-between items-center">
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold text-white">GMP Bo'limlari</h1>
                        <p class="text-slate-500 text-sm">Talablar bo'limlarini boshqarish</p>
                    </div>
                    <button onclick="openModal('add')" class="flex items-center gap-2 btn-primary px-4 py-2.5 rounded-xl font-medium text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="hidden sm:inline">Yangi bo'lim</span>
                    </button>
                </div>
            </header>
            
            <div class="p-4 lg:p-8">
                <!-- ⭐ Flash Messages -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl border animate-in <?php echo $messageType === 'success' 
                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' 
                    : 'bg-red-500/10 border-red-500/30 text-red-300'; ?>">
                    <div class="flex items-center gap-3">
                        <?php if ($messageType === 'success'): ?>
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php else: ?>
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php endif; ?>
                        <span><?php echo $message; ?></span>
                        <button onclick="this.closest('div').remove()" class="ml-auto opacity-50 hover:opacity-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-xl border animate-in bg-red-500/10 border-red-500/30 text-red-300">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ⭐ Statistikalar -->
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                    <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo count($sections); ?></div>
                        <div class="text-slate-500 text-sm">Jami bo'limlar</div>
                    </div>
                    
                    <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $totalQuestions; ?></div>
                        <div class="text-slate-500 text-sm">Jami savollar</div>
                    </div>
                    
                    <div class="stat-card rounded-2xl p-5 animate-in delay-3 col-span-2 lg:col-span-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo round($totalQuestions / max(count($sections), 1), 1); ?></div>
                        <div class="text-slate-500 text-sm">O'rtacha savollar/bo'lim</div>
                    </div>
                </div>
                
                <!-- Bo'limlar Ro'yxati -->
                <?php if (!empty($sections)): ?>
                <div class="stat-card rounded-2xl overflow-hidden animate-in delay-3">
                    <div class="px-6 py-4 border-b border-slate-700/50 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Bo'limlar ro'yxati</h3>
                        <span class="text-xs text-slate-500">Tartib: sort_order bo'yicha</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-800/30">
                                <tr>
                                    <th class="px-6 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider w-12">#</th>
                                    <th class="px-6 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider w-20">Raqam</th>
                                    <th class="px-6 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">Nomi</th>
                                    <th class="px-6 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider hidden md:table-cell">Tavsif</th>
                                    <th class="px-6 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider text-center">Savollar</th>
                                    <th class="px-6 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider text-center hidden lg:table-cell">Foydalanilgan</th>
                                    <th class="px-6 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30" id="sectionsTable">
                                <?php $index = 0; foreach ($sections as $i => $sec): $index++; ?>
                                <tr class="section-row" data-id="<?php echo $sec['id']; ?>" data-order="<?php echo $sec['sort_order']; ?>">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-1">
                                            <button onclick="moveSection(<?php echo $sec['id']; ?>, 'up', <?php echo $i; ?>)" 
                                                    class="sort-btn p-1 rounded hover:bg-slate-700 text-slate-500 hover:text-white <?php echo $i === 0 ? 'invisible' : ''; ?>"
                                                    title="Yuqoriga">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            </button>
                                            <button onclick="moveSection(<?php echo $sec['id']; ?>, 'down', <?php echo $i; ?>)" 
                                                    class="sort-btn p-1 rounded hover:bg-slate-700 text-slate-500 hover:text-white <?php echo $i === count($sections) - 1 ? 'invisible' : ''; ?>"
                                                    title="Pastga">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center justify-center w-12 h-8 rounded-lg bg-cyan-500/15 text-cyan-400 font-mono font-bold text-sm border border-cyan-500/20">
                                            <?php echo htmlspecialchars($sec['section_number']); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <span class="text-white font-medium"><?php echo htmlspecialchars($sec['section_name']); ?></span>
                                    </td>
                                    
                                    <td class="px-6 py-4 hidden md:table-cell">
                                        <span class="text-slate-400 text-sm line-clamp-2">
                                            <?php echo htmlspecialchars($sec['description'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($sec['question_count'] > 0): ?>
                                        <a href="checklists.php?section=<?php echo $sec['id']; ?>" 
                                           class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/25 transition-colors"
                                           title="Checklistni ko'rish">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                            <?php echo $sec['question_count']; ?> ta
                                        </a>
                                        <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-slate-500/15 text-slate-500 border border-slate-500/20">
                                            0 ta
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center hidden lg:table-cell">
                                        <?php if ($sec['audit_usage_count'] > 0): ?>
                                        <span class="text-cyan-400 font-medium text-sm"><?php echo $sec['audit_usage_count']; ?> audit</span>
                                        <?php else: ?>
                                        <span class="text-slate-600 text-sm">—</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick='openModal("edit", <?php echo htmlspecialchars(json_encode(['id' => $sec['id'], 'section_number' => $sec['section_number'], 'section_name' => $sec['section_name'], 'description' => $sec['description'], 'sort_order' => $sec['sort_order']], JSON_UNESCAPED_UNICODE)); ?>)' 
                                                    class="p-2 rounded-lg text-cyan-400 hover:bg-cyan-500/10 hover:text-cyan-300 transition-colors" title="Tahrirlash">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            
                                            <!-- FORCE DELETE BUTTON (Always Active) -->
                                            <button onclick="confirmDelete(<?php echo $sec['id']; ?>, '<?php echo htmlspecialchars(addslashes($sec['section_number'] . ' - ' . $sec['section_name'])); ?>')" 
                                                    class="p-2 rounded-lg text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors" title="O'chirish (Savollar bilan birga)">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-slate-700/50 flex items-center justify-between text-sm text-slate-500">
                        <span>Jami: <?php echo count($sections); ?> bo'lim, <?php echo $totalQuestions; ?> savol</span>
                        <span>OXIRGI YANGILANISH: <?php echo date('d.m.Y H:i'); ?></span>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="stat-card rounded-2xl p-12 text-center animate-in">
                    <div class="w-20 h-20 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Hali bo'limlar yo'q</h3>
                    <p class="text-slate-500 mb-6 max-w-md mx-auto">GMP talablarini boshqarish uchun bo'limlarni yarating.</p>
                    <button onclick="openModal('add')" class="inline-flex items-center gap-2 btn-primary px-6 py-3 rounded-xl text-white font-medium">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Birinchi bo'limni yaratish
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Yordamchi ma'lumotlar -->
                <div class="mt-8 stat-card rounded-2xl p-6 animate-in delay-3">
                    <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Qo'llanma
                    </h3>
                    <div class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-800/30">
                            <div class="w-6 h-6 rounded-full bg-cyan-500/20 flex items-center justify-center flex-shrink-0 mt-0.5"><span class="text-cyan-400 text-xs font-bold">1</span></div>
                            <div><p class="text-white font-medium">Bo'lim raqami</p><p class="text-slate-500">Har bir raqam noyob bo'lishi kerak.</p></div>
                        </div>
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-800/30">
                            <div class="w-6 h-6 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0 mt-0.5"><span class="text-red-400 text-xs font-bold">!</span></div>
                            <div><p class="text-white font-medium">O'chirish tartibi</p><p class="text-slate-500">Eslatma: Bo'limni o'chirish uning ichidagi barcha savollarni ham o'chirib yuboradi.</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- ADD/EDIT MODAL -->
    <div id="sectionModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="closeModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="modal-content relative w-full max-w-md rounded-2xl p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-white">Yangi Bo'lim</h3>
                    <button onclick="closeModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" action="" id="sectionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" id="formAction" value="add_section">
                    <input type="hidden" name="section_id" id="sectionId" value="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Bo'lim raqami <span class="text-red-400">*</span></label>
                            <input type="text" name="section_number" id="formNumber" required class="input-field w-full px-4 py-2.5 rounded-xl" placeholder="Masalan: I, II, III" maxlength="10">
                        </div>
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Bo'lim nomi <span class="text-red-400">*</span></label>
                            <input type="text" name="section_name" id="formName" required class="input-field w-full px-4 py-2.5 rounded-xl" placeholder="Masalan: Xodimlar" maxlength="200">
                        </div>
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Tavsif</label>
                            <textarea name="description" id="formDesc" rows="3" class="input-field w-full px-4 py-2.5 rounded-xl" placeholder="Qisqacha ma'lumot" maxlength="500"></textarea>
                        </div>
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Tartib raqami</label>
                            <input type="number" name="sort_order" id="formOrder" value="0" class="input-field w-full px-4 py-2.5 rounded-xl" min="0" max="999">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="closeModal()" class="flex-1 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-colors font-medium">Bekor qilish</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl btn-primary text-white font-semibold flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- DELETE CONFIRM MODAL -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="hideDeleteModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="modal-content relative w-full max-w-sm rounded-2xl p-6">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Bo'limni o'chirish</h3>
                    <p class="text-slate-400 mb-1">Siz ushbu bo'limni o'chirmoqchisiz:</p>
                    <p id="deleteSectionName" class="text-cyan-400 font-medium mb-2"></p>
                    <p class="text-red-400 text-xs font-semibold bg-red-500/10 p-2 rounded border border-red-500/20">
                        Diqqat: Bu bo'lim ichidagi barcha savollar ham o'chiriladi!
                    </p>
                </div>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete_section">
                    <input type="hidden" name="section_id" id="deleteSectionId">
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="hideDeleteModal()" class="flex-1 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-colors">Bekor qilish</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white font-medium transition-colors">Ha, o'chirish</button>
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
            const modal = document.getElementById('sectionModal');
            const title = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');

            if (mode === 'add') {
                title.textContent = 'Yangi Bo\'lim';
                formAction.value = 'add_section';
                document.getElementById('sectionForm').reset();
                document.getElementById('sectionId').value = '';
                const rows = document.querySelectorAll('#sectionsTable tr');
                document.getElementById('formOrder').value = rows.length;
            } else if (mode === 'edit' && data) {
                title.textContent = 'Bo\'limni Tahrirlash';
                formAction.value = 'edit_section';
                document.getElementById('sectionId').value = data.id;
                document.getElementById('formNumber').value = data.section_number;
                document.getElementById('formName').value = data.section_name;
                document.getElementById('formDesc').value = data.description || '';
                document.getElementById('formOrder').value = data.sort_order || 0;
            }
            modal.classList.remove('hidden');
            setTimeout(() => document.getElementById('formNumber').focus(), 100);
        }

        function closeModal() { document.getElementById('sectionModal').classList.add('hidden'); }

        function confirmDelete(id, name) {
            document.getElementById('deleteSectionId').value = id;
            document.getElementById('deleteSectionName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function hideDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }

        function moveSection(id, direction, currentIndex) {
            const rows = Array.from(document.querySelectorAll('#sectionsTable tr'));
            const currentRow = rows[currentIndex];
            if (!currentRow) return;

            let targetRow;
            if (direction === 'up' && currentIndex > 0) targetRow = rows[currentIndex - 1];
            else if (direction === 'down' && currentIndex < rows.length - 1) targetRow = rows[currentIndex + 1];
            if (!targetRow) return;

            const currentOrder = parseInt(currentRow.dataset.order);
            const targetOrder = parseInt(targetRow.dataset.order);
            const targetId = targetRow.dataset.id;
            
            const formData = new FormData();
            formData.append('action', 'update_order');
            formData.append('csrf_token', '<?php echo $csrfToken; ?>');
            formData.append('section_id', id);
            formData.append('new_order', targetOrder);

            fetch('sections.php', { method: 'POST', body: formData }).then(() => {
                const formData2 = new FormData();
                formData2.append('action', 'update_order');
                formData2.append('csrf_token', '<?php echo $csrfToken; ?>');
                formData2.append('section_id', targetId);
                formData2.append('new_order', currentOrder);
                return fetch('sections.php', { method: 'POST', body: formData2 });
            }).then(() => window.location.reload());
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { closeModal(); hideDeleteModal(); }
            if (e.ctrlKey && e.key === 'n') { e.preventDefault(); openModal('add'); }
        });

        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => { if (window.innerWidth < 1024) toggleSidebar(); });
        });
    </script>
</body>
</html>