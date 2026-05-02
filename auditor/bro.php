<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// audits.php - Auditor uchun Auditlar ro'yxati
require_once '../db.php';
requireLogin();

 $user = getCurrentUser();

// Ruxsatni tekshirish (faqat auditor emas, balki boshqa rollar ham kirsa redirect)
if (!in_array($user['role'], ['auditor', 'super_admin', 'bosh_auditor'])) {
    header("Location: index.php");
    exit;
}

// Dashboard linkini aniqlash
 $dashboardLink = ($user['role'] === 'auditor') ? 'index.php' : 'index.php';

// Auditor huquqlari (oddiy auditor faqat ko'radi va ishlaydi)
 $canManage = in_array($user['role'], ['super_admin', 'bosh_auditor']); 

 $message = '';
 $messageType = 'success';

// Flash message
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// ----------------- FILTERLAR -----------------
 $filterStatus = $_GET['status'] ?? '';
 $filterSearch = trim($_GET['search'] ?? '');
 $currentPage = max(1, (int)($_GET['page'] ?? 1));
 $perPage = 15;

 $whereClauses = [];
 $params = [];

// ⭐ ASOSIY FARQ: Faqat o'ziga biriktirilgan auditlarni ko'rsatish
if (!$canManage) {
    $whereClauses[] = "EXISTS (SELECT 1 FROM audit_assignments aa WHERE aa.audit_id = a.id AND aa.auditor_id = ?)";
    $params[] = $user['id'];
}

// Status filter
if (in_array($filterStatus, ['draft', 'in_progress', 'completed', 'cancelled'])) {
    $whereClauses[] = "a.status = ?";
    $params[] = $filterStatus;
}

// Qidiruv
if ($filterSearch !== '') {
    $whereClauses[] = "(a.audit_code LIKE ? OR a.title LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$filterSearch%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

 $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Umumiy son
 $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audits a JOIN sites s ON a.site_id = s.id $whereSQL");
 $countStmt->execute($params);
 $totalAudits = $countStmt->fetchColumn();
 $totalPages = max(1, ceil($totalAudits / $perPage));
 $offset = ($currentPage - 1) * $perPage;

// Auditlar ro'yxati
 $stmt = $pdo->prepare("
    SELECT a.*, s.name as site_name, u.full_name as creator_name,
           (SELECT COUNT(*) FROM audit_answers WHERE audit_id = a.id AND answer != 'na') as answered_count,
           (SELECT COUNT(*) FROM checklist_questions WHERE is_active = 1) as total_questions,
           (SELECT COUNT(DISTINCT nc.id) FROM non_conformities nc WHERE nc.audit_id = a.id) as nc_count
    FROM audits a 
    JOIN sites s ON a.site_id = s.id 
    JOIN users u ON a.created_by = u.id 
    $whereSQL
    ORDER BY 
        CASE a.status 
            WHEN 'in_progress' THEN 1 
            WHEN 'draft' THEN 2 
            WHEN 'completed' THEN 3 
            WHEN 'cancelled' THEN 4 
        END,
        a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
 $stmt->execute($params);
 $audits = $stmt->fetchAll();

// Status bo'yicha statistika
 $statusCounts = ['all' => $totalAudits, 'draft' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];

// Statistika uchun query
 $countQuery = "
    SELECT a.status, COUNT(*) as cnt 
    FROM audits a 
    " . (!$canManage ? "JOIN audit_assignments aa ON aa.audit_id = a.id AND aa.auditor_id = {$user['id']}" : "") . "
    GROUP BY a.status
";
 $countAll = $pdo->query($countQuery);
if ($countAll) {
    foreach ($countAll->fetchAll() as $row) {
        $statusCounts[$row['status']] = (int)$row['cnt'];
    }
}




?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mening Auditlarim - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent-cyan: #06b6d4; }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); }
        
        .sidebar { background: linear-gradient(180deg, #111827 0%, #0f172a 100%); border-right: 1px solid rgba(51, 65, 85, 0.5); transition: transform 0.3s ease-in-out; }
        @media (max-width: 1023px) { .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; } .sidebar.active { transform: translateX(0); } }
        
        .nav-item { transition: all 0.3s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: rgba(6, 182, 212, 0.1); border-left-color: var(--accent-cyan); }
        .nav-item.active { background: rgba(6, 182, 212, 0.15); border-left-color: var(--accent-cyan); }
        
        .stat-card { background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%); backdrop-filter: blur(10px); border: 1px solid rgba(51, 65, 85, 0.5); transition: all 0.3s ease; }
        .stat-card:hover { border-color: rgba(6, 182, 212, 0.3); }
        
        .input-field { background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; transition: all 0.3s ease; color: white; }
        .input-field:focus { border-color: var(--accent-cyan); box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2); outline: none; }
        
        .btn-primary { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4); }
        
        .badge { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-info { background: rgba(6, 182, 212, 0.15); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.2); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .badge-neutral { background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.2); }
        
        .progress-bar { background: rgba(30, 41, 59, 0.8); overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #06b6d4, #14b8a6); transition: width 0.8s ease; }
        
        .filter-tab { transition: all 0.2s ease; cursor: pointer; border: 1px solid transparent; }
        .filter-tab:hover { background: rgba(51, 65, 85, 0.5); }
        .filter-tab.active { background: rgba(6, 182, 212, 0.1); border-color: rgba(6, 182, 212, 0.3); color: white; }
        
        .audit-card { transition: all 0.3s ease; }
        .audit-card:hover { border-color: rgba(6, 182, 212, 0.3); transform: translateX(4px); }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-in { animation: fadeInUp 0.5s ease forwards; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    </style>
</head>
<body class="min-h-screen text-slate-100">
    
    <!-- Mobile Header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-500 font-mono">Auditor</p>
                    </div>
                </div>
                
                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <a href="<?php echo $dashboardLink; ?>" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Bosh panel
                    </a>
                    <a href="bro.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        Mening Auditlarim
                    </a>
                        <!-- <a href="reports.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Hisobotlar
                        </a> -->
                </nav>
                
                <div class="border-t border-slate-700/50 pt-4 mt-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center text-white font-semibold text-sm">
                            <?php echo strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-slate-500">Auditor</p>
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
                        <h1 class="text-xl lg:text-2xl font-bold text-white">Mening Auditlarim</h1>
                        <p class="text-slate-500 text-sm">Jami <?php echo $statusCounts['all']; ?> ta topshiriq</p>
                    </div>
                    <!-- Auditor yangi audit yarata olmaydi -->
                </div>
            </header>
            
            <div class="p-4 lg:p-8">
                <!-- Flash Messages -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl border animate-in <?php echo $messageType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-300'; ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $messageType === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; ?>"/></svg>
                        <span><?php echo $message; ?></span>
                        <button onclick="this.closest('div').remove()" class="ml-auto opacity-50 hover:opacity-100"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filter Tabs -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <?php 
                    $tabs = [
                        'all' => ['label' => 'Barchasi', 'badge' => 'neutral'],
                        'in_progress' => ['label' => 'Jarayonda', 'badge' => 'warning'],
                        'draft' => ['label' => 'Kutilmoqda', 'badge' => 'info'],
                        'completed' => ['label' => 'Tugatilgan', 'badge' => 'success'],
                    ];
                    foreach ($tabs as $key => $tab):
                        $isActive = ($filterStatus === '' && $key === 'all') || $filterStatus === $key;
                        $url = buildFilterUrl($key);
                    ?>
                    <a href="<?php echo $url; ?>" class="filter-tab <?php echo $isActive ? 'active' : ''; ?> flex items-center gap-2 px-4 py-2 rounded-xl text-sm <?php echo $isActive ? 'text-white' : 'text-slate-400'; ?>">
                        <?php echo $tab['label']; ?>
                        <span class="badge badge-<?php echo $tab['badge']; ?>"><?php echo $statusCounts[$key]; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Search -->
                <div class="stat-card rounded-2xl p-4 mb-6">
                    <form method="GET" action="" class="flex gap-3">
                        <div class="flex-1 relative">
                            <svg class="w-5 h-5 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filterSearch); ?>" class="input-field w-full pl-10 pr-4 py-2.5 rounded-xl text-sm" placeholder="Qidirish...">
                        </div>
                        <?php if ($filterStatus): ?>
                        <input type="hidden" name="status" value="<?php echo $filterStatus; ?>">
                        <?php endif; ?>
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-white transition-colors text-sm">Qidirish</button>
                    </form>
                </div>
                
                <!-- Audit Cards -->
                <div class="space-y-4">
                    <?php if (empty($audits)): ?>
                    <div class="stat-card rounded-2xl p-12 text-center">
                        <div class="w-20 h-20 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">Topshiriqlar topilmadi</h3>
                        <p class="text-slate-500">Sizga hali hech qanday audit topshirilmagan yoki filtr mos kelmayapti.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($audits as $audit): 
                            $progress = $audit['total_questions'] > 0 ? round(($audit['answered_count'] / $audit['total_questions']) * 100, 1) : 0;
                        ?>
                        <div class="audit-card stat-card rounded-2xl p-5 lg:p-6">
                            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="font-mono text-cyan-400 text-sm"><?php echo htmlspecialchars($audit['audit_code']); ?></span>
                                        <span class="badge <?php echo $statusClasses[$audit['status']] ?? 'badge-info'; ?>">
                                            <?php echo $statusLabels[$audit['status']] ?? $audit['status']; ?>
                                        </span>
                                        <?php if ($audit['nc_count'] > 0): ?>
                                        <span class="badge badge-danger"><?php echo $audit['nc_count']; ?> NC</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-lg font-semibold text-white mb-2 truncate"><?php echo htmlspecialchars($audit['title']); ?></h3>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-400">
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                                            <?php echo htmlspecialchars($audit['site_name']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="lg:w-48 flex-shrink-0">
                                    <div class="flex items-center justify-between text-sm mb-2">
                                        <span class="text-slate-500">Progress</span>
                                        <span class="text-white font-semibold font-mono"><?php echo $progress; ?>%</span>
                                    </div>
                                    <div class="progress-bar h-2 rounded-full">
                                        <div class="progress-fill h-full rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <?php if ($audit['status'] === 'draft'): ?>
                                        <span class="text-slate-500 text-sm italic px-4 py-2">Kutilmoqda...</span>
                                    <?php elseif ($audit['status'] === 'in_progress'): ?>
                                        <a href="conduct_audit.php?id=<?php echo $audit['id']; ?>" class="px-4 py-2 rounded-xl bg-cyan-500/15 text-cyan-400 hover:bg-cyan-500/25 border border-cyan-500/20 transition-all text-sm font-medium whitespace-nowrap flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Davom etish
                                        </a>
                                    <?php elseif ($audit['status'] === 'completed'): ?>
                                        <!-- <a href="reports.php?audit=<?php echo $audit['id']; ?>" class="px-4 py-2 rounded-xl bg-blue-500/15 text-blue-400 hover:bg-blue-500/25 border border-blue-500/20 transition-all text-sm font-medium whitespace-nowrap flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Hisobot
                                        </a> -->
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="flex items-center justify-center gap-2 pt-6">
                            <?php if ($currentPage > 1): ?>
                            <a href="<?php echo buildPageUrl($currentPage - 1); ?>" class="px-3 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <a href="<?php echo buildPageUrl($i); ?>" class="w-10 h-10 rounded-lg flex items-center justify-center text-sm transition-colors <?php echo $i === $currentPage ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800'; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                            <a href="<?php echo buildPageUrl($currentPage + 1); ?>" class="px-3 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }
        function buildFilterUrl($status) {
    $params = $_GET;

    if ($status === 'all') {
        unset($params['status']);
    } else {
        $params['status'] = $status;
    }

    // pagination reset
    unset($params['page']);

    return '?' . http_build_query($params);
}
    </script>
</body>
</html>