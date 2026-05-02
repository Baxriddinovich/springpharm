<?php
// checklists.php - Checklist boshqaruvi (Bosh Auditor uchun moslashtirilgan)
require_once '../db.php';
requireLogin();

 $user = getCurrentUser();

// Dashboard linkini aniqlash
 $dashboardLink = ($user['role'] === 'bosh_auditor') ? 'index.php' : 'index.php';

// ⭐ Role-based ruxsatlar (Bosh Auditor ham boshqarishi mumkin)
 $canManage = in_array($user['role'], ['super_admin', 'bosh_auditor', 'admin']);

// ⭐ CSRF Token
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if (!verifyCsrf()) {
        $error = "Xavfsizlik xatosi! Sahifani qayta yuklang.";
        $messageType = 'danger';
    } elseif (!$canManage && in_array($_POST['action'], ['add', 'edit', 'delete'])) {
        $error = "Sizda bu amalni bajarish huquqi yo'q!";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];

        // 1. Qo'shish
        if ($action === 'add') {
            $sectionId = (int)($_POST['section_id'] ?? 0);
            $questionText = sanitize($_POST['question_text'] ?? '');
            $isRequired = isset($_POST['is_required']) ? 1 : 0;
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if ($questionText !== '' && $sectionId > 0) {
                $stmt = $pdo->prepare("INSERT INTO checklist_questions (section_id, question_text, is_required, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sectionId, $questionText, $isRequired, $sortOrder]);
                logActivity('question_added', "Savol qo'shildi (Bo'lim ID: $sectionId)");
                $_SESSION['flash_message'] = "Savol muvaffaqiyatli qo'shildi!";
                $_SESSION['flash_type'] = 'success';
                header("Location: checklists.php?section=$sectionId");
                exit;
            } else {
                $error = "Bo'lim va savol matni majburiy!";
                $messageType = 'danger';
            }
        }

        // 2. Tahrirlash
        if ($action === 'edit') {
            $id = (int)($_POST['question_id'] ?? 0);
            $sectionId = (int)($_POST['section_id'] ?? 0);
            $questionText = sanitize($_POST['question_text'] ?? '');
            $isRequired = isset($_POST['is_required']) ? 1 : 0;
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if ($id > 0 && $questionText !== '' && $sectionId > 0) {
                $stmt = $pdo->prepare("UPDATE checklist_questions SET section_id = ?, question_text = ?, is_required = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$sectionId, $questionText, $isRequired, $sortOrder, $id]);
                logActivity('question_edited', "Savol tahrirlandi (ID: $id)");
                $_SESSION['flash_message'] = "Savol muvaffaqiyatli yangilandi!";
                $_SESSION['flash_type'] = 'success';
                header("Location: checklists.php?section=$sectionId");
                exit;
            } else {
                $error = "Ma'lumotlar to'liq emas!";
                $messageType = 'danger';
            }
        }

        // 3. O'chirish (Soft delete)
        if ($action === 'delete') {
            $id = (int)($_POST['question_id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE checklist_questions SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity('question_deleted', "Savol o'chirildi (ID: $id)");
                $_SESSION['flash_message'] = "Savol muvaffaqiyatli o'chirildi!";
                $_SESSION['flash_type'] = 'success';
                header("Location: checklists.php");
                exit;
            }
        }
    }
}

// Flash message
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Bo'limlar
 $sections = $pdo->query("
    SELECT gs.*, 
           (SELECT COUNT(*) FROM checklist_questions cq WHERE cq.section_id = gs.id AND cq.is_active = 1) as question_count 
    FROM gmp_sections gs 
    ORDER BY gs.sort_order
")->fetchAll();

// Filter
 $selectedSection = $_GET['section'] ?? '';
 $params = [];
 $sectionFilter = "";

if ($selectedSection !== '' && is_numeric($selectedSection)) {
    $sectionFilter = "AND cq.section_id = ?";
    $params[] = (int)$selectedSection;
}

 $query = "
    SELECT cq.*, gs.section_number, gs.section_name 
    FROM checklist_questions cq 
    JOIN gmp_sections gs ON cq.section_id = gs.id 
    WHERE cq.is_active = 1 
    $sectionFilter
    ORDER BY gs.sort_order, cq.sort_order, cq.id
";

 $stmt = $pdo->prepare($query);
 $stmt->execute($params);
 $questions = $stmt->fetchAll();

// Statistika
 $totalQuestions = array_sum(array_column($sections, 'question_count'));
 $requiredCount = $pdo->query("SELECT COUNT(*) FROM checklist_questions WHERE is_active = 1 AND is_required = 1")->fetchColumn();
 $activeSections = count(array_filter($sections, fn($s) => $s['question_count'] > 0));
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklistlar - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent-cyan: #06b6d4; --border-color: #334155; }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); }
        
        .sidebar { background: linear-gradient(180deg, #111827 0%, #0f172a 100%); border-right: 1px solid rgba(51, 65, 85, 0.5); transition: transform 0.3s ease-in-out; }
        @media (max-width: 1023px) { .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; } .sidebar.active { transform: translateX(0); } }
        .nav-item { transition: all 0.3s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: rgba(6, 182, 212, 0.1); border-left-color: var(--accent-cyan); }
        .nav-item.active { background: rgba(6, 182, 212, 0.15); border-left-color: var(--accent-cyan); }
        
        .stat-card { background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%); backdrop-filter: blur(10px); border: 1px solid rgba(51, 65, 85, 0.5); transition: all 0.3s ease; }
        .stat-card:hover { border-color: rgba(6, 182, 212, 0.3); }
        
        .question-card { background: rgba(26, 35, 50, 0.6); border: 1px solid rgba(51, 65, 85, 0.5); transition: all 0.3s ease; }
        .question-card:hover { border-color: rgba(6, 182, 212, 0.3); transform: translateX(4px); }
        
        .section-tab { transition: all 0.2s ease; }
        .section-tab.active { background: rgba(6, 182, 212, 0.15); border-color: rgba(6, 182, 212, 0.4); color: #06b6d4; }
        
        .input-field { background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color); transition: all 0.3s ease; color: white; }
        .input-field:focus { border-color: var(--accent-cyan); box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2); outline: none; }
        
        .btn-primary { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4); }
        
        .modal-backdrop { background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); animation: fadeIn 0.2s ease; }
        .modal-content { background: linear-gradient(135deg, #1a2332 0%, #111827 100%); border: 1px solid rgba(51, 65, 85, 0.5); animation: modalIn 0.3s ease; }
        
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .animate-in { animation: fadeInUp 0.5s ease forwards; }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } }
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
        <aside id="sidebar" class="sidebar w-64 fixed h-full z-50" role="navigation">
            <div class="p-6 h-full flex flex-col">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-500 font-mono">
                            <?php echo $user['role'] === 'bosh_auditor' ? 'Bosh Auditor' : 'v2.0 Pro'; ?>
                        </p>
                    </div>
                </div>
                
                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <!-- Dashboard Link (Dynamic) -->
                    <a href="<?php echo $dashboardLink; ?>" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Bosh panel
                    </a>
                    <a href="auditss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Auditlar
                    </a>
                    <a href="reportss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Hisobotlar
                    </a>
                    
                    <div class="pt-4 mt-4 border-t border-slate-700/50">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>
                        <a href="sectionss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Bo'limlar
                        </a>
                        <a href="checklistss.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Checklistlar
                        </a>
                        
                        <!-- ⭐ FAQAT ADMIN UCHUN -->
                        <?php if ($user['role'] === 'super_admin' || $user['role'] === 'admin'): ?>
                        <a href="users.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            Auditorlar
                        </a>
                        <a href="logs.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            Tizim Tarixi
                        </a>
                        <?php endif; ?>
                    </div>
                </nav>
                
                <div class="border-t border-slate-700/50 pt-4 mt-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center text-white font-semibold text-sm">
                            <?php echo strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo $user['role'] === 'bosh_auditor' ? 'Bosh Auditor' : ucfirst($user['role']); ?></p>
                        </div>
                        <a href="logout.php" class="text-slate-500 hover:text-red-400 transition-colors p-2" aria-label="Chiqish">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
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
                        <h1 class="text-xl lg:text-2xl font-bold text-white">Checklist Boshqaruvi</h1>
                        <p class="text-slate-500 text-sm">GMP savollari boshqaruvi</p>
                    </div>
                    <?php if ($canManage): ?>
                    <button onclick="openModal('add')" class="flex items-center gap-2 btn-primary px-4 py-2.5 rounded-xl font-medium text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="hidden sm:inline">Savol qo'shish</span>
                    </button>
                    <?php endif; ?>
                </div>
            </header>
            
            <div class="p-4 lg:p-8">
                <!-- Flash Messages -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl border animate-in <?php echo $messageType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-300'; ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $messageType === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; ?>"/></svg>
                        <span class="text-sm"><?php echo $message; ?></span>
                        <button onclick="this.closest('div').remove()" class="ml-auto opacity-50 hover:opacity-100"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistikalar -->
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                    <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $totalQuestions; ?></div>
                        <div class="text-slate-500 text-sm">Jami savollar</div>
                    </div>
                    <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $activeSections; ?>/<?php echo count($sections); ?></div>
                        <div class="text-slate-500 text-sm">Faol bo'limlar</div>
                    </div>
                    <div class="stat-card rounded-2xl p-5 animate-in delay-4">
                        <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $requiredCount; ?></div>
                        <div class="text-slate-500 text-sm">Majburiy savollar</div>
                    </div>
                </div>

                <!-- Bo'limlar Filtri (Tabs) -->
                <div class="flex flex-wrap gap-2 mb-8 animate-in delay-2">
                    <a href="checklists.php" class="section-tab px-4 py-2.5 rounded-xl border border-slate-700/50 text-sm font-medium <?php echo !$selectedSection ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                        Barchasi <span class="ml-1 text-xs opacity-70">(<?php echo $totalQuestions; ?>)</span>
                    </a>
                    <?php foreach ($sections as $section): ?>
                    <a href="?section=<?php echo $section['id']; ?>" 
                       class="section-tab px-4 py-2.5 rounded-xl border border-slate-700/50 text-sm font-medium flex items-center gap-2 <?php echo $selectedSection == $section['id'] ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                        <span class="font-mono text-xs"><?php echo htmlspecialchars($section['section_number']); ?></span>
                        <?php echo htmlspecialchars($section['section_name']); ?>
                        <span class="ml-1 text-xs opacity-70">(<?php echo $section['question_count']; ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Savollar Ro'yxati -->
                <div class="space-y-3 animate-in delay-3">
                    <?php if (empty($questions)): ?>
                    <div class="stat-card rounded-2xl p-12 text-center">
                        <h3 class="text-xl font-semibold text-white mb-2">Savollar topilmadi</h3>
                        <p class="text-slate-500 mb-6">Tanlangan bo'limda hali savollar qo'shilmagan.</p>
                        <?php if ($canManage): ?>
                        <button onclick="openModal('add')" class="inline-flex items-center gap-2 btn-primary px-6 py-3 rounded-xl text-white font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Savol qo'shish
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <?php 
                        $currentSection = '';
                        $qIndex = 0;
                        foreach ($questions as $q): 
                            $qIndex++;
                            if ($currentSection !== $q['section_id']):
                                $currentSection = $q['section_id'];
                        ?>
                        <div class="flex items-center gap-3 mt-8 mb-4 <?php echo $qIndex === 1 ? 'mt-0' : ''; ?>">
                            <a href="?section=<?php echo $q['section_id']; ?>" class="w-9 h-9 rounded-lg bg-cyan-500/15 border border-cyan-500/20 flex items-center justify-center text-cyan-400 font-mono text-sm font-bold hover:bg-cyan-500/25 transition-colors">
                                <?php echo htmlspecialchars($q['section_number']); ?>
                            </a>
                            <h2 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($q['section_name']); ?></h2>
                        </div>
                        <?php endif; ?>
                        
                        <div class="question-card rounded-xl p-4 lg:p-5 ml-4 lg:ml-12">
                            <div class="flex items-start gap-4">
                                <div class="hidden sm:flex w-8 h-8 rounded-full bg-slate-800 items-center justify-center text-slate-500 text-xs font-bold flex-shrink-0 mt-0.5">
                                    <?php echo $qIndex; ?>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <p class="text-white text-sm lg:text-base mb-2 leading-relaxed"><?php echo htmlspecialchars($q['question_text']); ?></p>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                        <?php if ($q['is_required']): ?>
                                        <span class="px-2 py-0.5 rounded-full bg-red-500/15 text-red-300 border border-red-500/20">Majburiy</span>
                                        <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full bg-slate-500/15 text-slate-500 border border-slate-500/20">Ixtiyoriy</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($canManage): ?>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <button onclick='openModal("edit", <?php echo htmlspecialchars(json_encode(['id' => $q['id'], 'section_id' => $q['section_id'], 'question_text' => $q['question_text'], 'is_required' => $q['is_required'], 'sort_order' => $q['sort_order']], JSON_UNESCAPED_UNICODE)); ?>)' 
                                            class="p-2 text-slate-500 hover:text-cyan-400 hover:bg-cyan-500/10 rounded-lg transition-all" title="Tahrirlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $q['id']; ?>)" 
                                            class="p-2 text-slate-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-all" title="O'chirish">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- ADD/EDIT MODAL -->
    <div id="questionModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="closeModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="modal-content relative w-full max-w-lg rounded-2xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-white">Savol qo'shish</h3>
                    <button onclick="closeModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700/50 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <form method="POST" action="" id="questionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="question_id" id="questionId" value="">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Bo'lim <span class="text-red-400">*</span></label>
                            <select name="section_id" id="sectionId" class="input-field w-full px-4 py-2.5 rounded-xl" required>
                                <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>">
                                    <?php echo htmlspecialchars($section['section_number'] . ' - ' . $section['section_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-slate-300 text-sm font-medium mb-1.5">Savol matni <span class="text-red-400">*</span></label>
                            <textarea name="question_text" id="questionText" rows="4" class="input-field w-full px-4 py-2.5 rounded-xl" placeholder="Savolni kiriting..." required maxlength="1000"></textarea>
                        </div>
                        
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-800/30">
                            <input type="checkbox" name="is_required" id="questionRequired" checked class="w-5 h-5 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-0">
                            <div>
                                <label for="questionRequired" class="text-slate-300 text-sm font-medium cursor-pointer">Majburiy savol</label>
                                <p class="text-xs text-slate-500">Audit o'tkazishda javob berish shart</p>
                            </div>
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
    
    <!-- DELETE MODAL -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="closeDeleteModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="modal-content relative w-full max-w-sm rounded-2xl p-6 text-center">
                <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Savolni o'chirish</h3>
                <p class="text-slate-400 mb-6">Ushbu savolni o'chirishga ishonchingiz komilmi?</p>
                
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="question_id" id="deleteQuestionId" value="">
                    
                    <div class="flex gap-3">
                        <button type="button" onclick="closeDeleteModal()" class="flex-1 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-colors">Bekor qilish</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white font-medium transition-colors">O'chirish</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        function openModal(mode, data = null) {
            const modal = document.getElementById('questionModal');
            document.getElementById('formAction').value = mode;
            document.getElementById('modalTitle').textContent = mode === 'add' ? "Savol qo'shish" : 'Savolni tahrirlash';
            
            const urlParams = new URLSearchParams(window.location.search);
            const urlSection = urlParams.get('section');

            if (mode === 'edit' && data) {
                document.getElementById('questionId').value = data.id;
                document.getElementById('sectionId').value = data.section_id;
                document.getElementById('questionText').value = data.question_text;
                document.getElementById('questionRequired').checked = data.is_required == 1;
            } else {
                document.getElementById('questionForm').reset();
                document.getElementById('questionId').value = '';
                document.getElementById('questionRequired').checked = true;
                if (urlSection) document.getElementById('sectionId').value = urlSection;
            }

            modal.classList.remove('hidden');
            setTimeout(() => document.getElementById('questionText').focus(), 100);
        }

        function closeModal() { document.getElementById('questionModal').classList.add('hidden'); }

        function confirmDelete(id) {
            document.getElementById('deleteQuestionId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }

        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeModal(); closeDeleteModal(); } });
    </script>
</body>
</html>