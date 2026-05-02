<?php
// non_conformities.php - Nomuvofiqliklar ro'yxati (MUKAMMAL VERSIYA)
require_once 'db.php';
requireLogin();

 $user = getCurrentUser();

// ⭐ CAPA statuslari uchun statistika
 $ncStatusCounts = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN nc.status = 'open' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN nc.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN nc.status = 'in_review' THEN 1 ELSE 0 END) as in_review_count,
        SUM(CASE WHEN nc.status = 'closed' THEN 1 ELSE 0 END) as closed_count,
        SUM(CASE WHEN nc.due_date < CURDATE() AND nc.status != 'closed' THEN 1 ELSE 0 END) as overdue_count
    FROM non_conformities nc
")->fetch();

// ⭐ Filtrlar
 $auditFilter = (int)($_GET['audit'] ?? 0);
 $severityFilter = (int)($_GET['severity'] ?? 0);
 $statusFilter = $_GET['status'] ?? '';

 $whereClauses = ["1=1"];
 $params = [];

if ($auditFilter > 0) {
    $whereClauses[] = "nc.audit_id = ?";
    $params[] = $auditFilter;
}

if ($severityFilter > 0) {
    $whereClauses[] = "nc.severity_id = ?";
    $params[] = $severityFilter;
}

if (in_array($statusFilter, ['open', 'in_progress', 'in_review', 'closed'])) {
    $whereClauses[] = "nc.status = ?";
    $params[] = $statusFilter;
}

// ⭐ Auditor o'ziga tegishli auditlarni ko'radi
if ($user['role'] === 'auditor') {
    $whereClauses[] = "EXISTS (SELECT 1 FROM audit_assignments aa WHERE aa.audit_id = nc.audit_id AND aa.auditor_id = ?)";
    $params[] = $user['id'];
}

 $whereSQL = implode(' AND ', $whereClauses);

// ⭐ Pagination
 $currentPage = max(1, (int)($_GET['page'] ?? 1));
 $perPage = 15;
 $countStmt = $pdo->prepare("SELECT COUNT(*) FROM non_conformities nc WHERE $whereSQL");
 $countStmt->execute($params);
 $totalNC = $countStmt->fetchColumn();
 $totalPages = max(1, ceil($totalNC / $perPage));
 $offset = ($currentPage - 1) * $perPage;

// ⭐ NC ro'yxati
 $stmt = $pdo->prepare("
    SELECT nc.*,
           a.audit_code, a.title as audit_title, s.name as site_name,
           cq.question_text, gs.section_number, gs.section_name,
           st.name as severity_name, st.color_code,
           u.full_name as creator_name,
           (SELECT COUNT(*) FROM capa_actions ca WHERE ca.nc_id = nc.id) as capa_count,
           (SELECT COUNT(*) FROM capa_actions ca WHERE ca.nc_id = nc.id AND ca.status = 'completed') as capa_completed_count
    FROM non_conformities nc
    JOIN audits a ON nc.audit_id = a.id
    JOIN sites s ON a.site_id = s.id
    JOIN checklist_questions cq ON nc.question_id = cq.id
    JOIN gmp_sections gs ON cq.section_id = gs.id
    JOIN severity_types st ON nc.severity_id = st.id
    JOIN users u ON nc.created_by = u.id
    WHERE $whereSQL
    ORDER BY 
        CASE nc.status 
            WHEN 'open' THEN 1 
            WHEN 'in_progress' THEN 2 
            WHEN 'in_review' THEN 3 
            ELSE 4 
        END,
        st.id ASC,
        nc.created_at DESC
    LIMIT $perPage OFFSET $offset
");
 $stmt->execute($params);
 $nonConformities = $stmt->fetchAll();

// Auditlar ro'yxati (filter uchun)
 $audits = $pdo->query("
    SELECT a.id, a.audit_code, a.title, 
           (SELECT COUNT(*) FROM non_conformities nc WHERE nc.audit_id = a.id) as nc_count
    FROM audits a 
    WHERE a.status IN ('in_progress', 'completed')
    ORDER BY a.created_at DESC
")->fetchAll();

// Severity turlari
 $severities = $pdo->query("SELECT * FROM severity_types ORDER BY sort_order")->fetchAll();

// ⭐ URL builder funksiyasi (filterlarni saqlab qoladi)
function buildNCUrl($overrides = []) {
    $params = $_GET;
    unset($params['page']);
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $query = http_build_query($params);
    return 'non_conformities.php' . ($query ? '?'.$query : '');
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nomuvofiqliklar - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
        
        /* NC Card */
        .nc-card {
            background: rgba(26, 35, 50, 0.6);
            border: 1px solid rgba(51, 65, 85, 0.5);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .nc-card:hover { 
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .nc-card.severity-minor { border-left-color: #10b981 !important; }
        .nc-card.severity-major { border-left-color: #f59e0b !important; }
        .nc-card.severity-critical { border-left-color: #ef4444 !important; }
        .nc-card.is-overdue { border-color: rgba(239, 68, 68, 0.5) !important; background: rgba(239, 68, 68, 0.05); }
        
        /* Filter tabs */
        .filter-btn {
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid rgba(51, 65, 85, 0.5);
        }
        .filter-btn:hover { background: rgba(51, 65, 85, 0.5); }
        .filter-btn.active {
            background: rgba(6, 182, 212, 0.2);
            border-color: rgba(6, 182, 212, 0.5);
            color: #06b6d4;
        }
        
        /* Status badges */
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-weight: 500;
            border: 1px solid;
        }
        .status-open { background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }
        .status-in_progress { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2); }
        .status-in_review { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border-color: rgba(139, 92, 246, 0.2); }
        .status-closed { background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.2); }
        
        /* CAPA progress */
        .capa-bar { background: rgba(30, 41, 59, 0.8); overflow: hidden; }
        .capa-fill { transition: width 0.8s ease; border-radius: 9999px; }
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.5s ease forwards; }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
        .delay-4 { animation-delay: 0.4s; opacity: 0; }
        .delay-5 { animation-delay: 0.5s; opacity: 0; }
        
        /* Pulse animation for overdue */
        @keyframes pulse-red {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3); }
            50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        }
        .pulse-overdue { animation: pulse-red 2s infinite; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
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
                    <div><h1 class="text-lg font-bold text-white">GMP Audit</h1><p class="text-xs text-slate-500 font-mono">v2.0 Pro</p></div>
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
                    
                    <a href="non_conformities.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Nomuvofiqliklar
                        <?php if ($ncStatusCounts['total'] > 0): ?>
                        <span class="ml-auto bg-red-500/20 text-red-400 text-xs px-2 py-0.5 rounded-full"><?php echo $ncStatusCounts['total']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="reports.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Hisobotlar
                    </a>
                    
                    <div class="pt-4 mt-4 border-t border-slate-700/50">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>
                        <a href="sections.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
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
                        <h1 class="text-xl lg:text-2xl font-bold text-white">Nomuvofiqliklar</h1>
                        <p class="text-slate-500 text-sm">
                            <?php echo $ncStatusCounts['total']; ?> ta nomuvofiqlika
                            <?php if ($ncStatusCounts['overdue'] > 0): ?>
                                <span class="text-red-400 font-medium"> · <span class="underline"><?php echo $ncStatusCounts['overdue']; ?> ta muddati o'tgan</span></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="non_conformities.php?action=create<?php echo $auditFilter ? '&audit='.$auditFilter : ''; ?>" 
                       class="flex items-center gap-2 bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 text-white px-4 py-2.5 rounded-xl font-medium transition-all shadow-lg shadow-red-500/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="hidden sm:inline">Yangi NC</span>
                    </a>
                </div>
            </header>
            
            <div class="p-4 lg:p-8">
                <!-- ⭐ Statistikalar -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    <div class="stat-card rounded-2xl p-4 animate-in delay-1">
                        <div class="w-9 h-9 rounded-lg bg-cyan-500/20 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-white"><?php echo $ncStatusCounts['total']; ?></div>
                        <div class="text-xs text-slate-500">Jami</div>
                    </div>
                    <div class="stat-card rounded-2xl p-4 animate-in delay-2">
                        <div class="w-9 h-9 rounded-lg bg-red-500/20 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-red-400"><?php echo $ncStatusCounts['open']; ?></div>
                        <div class="text-xs text-slate-500">Ochiq</div>
                    </div>
                    <div class="stat-card rounded-2xl p-4 animate-in delay-3">
                        <div class="w-9 h-9 rounded-lg bg-amber-500/20 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-amber-400"><?php echo $ncStatusCounts['in_progress']; ?></div>
                        <div class="text-xs text-slate-500">Jarayonda</div>
                    </div>
                    <div class="stat-card rounded-2xl p-4 animate-in delay-4">
                        <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-emerald-400"><?php echo $ncStatusCounts['closed']; ?></div>
                        <div class="text-xs text-slate-500">Yopilgan</div>
                    </div>
                    <div class="stat-card rounded-2xl p-4 animate-in delay-5 <?php echo $ncStatusCounts['overdue'] > 0 ? 'border-red-500/30' : ''; ?>">
                        <div class="w-9 h-9 rounded-lg bg-red-500/30 flex items-center justify-center mb-2 pulse-overdue">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-red-400"><?php echo $ncStatusCounts['overdue']; ?></div>
                        <div class="text-xs text-red-300">Muddati o'tgan</div>
                    </div>
                </div>

                <!-- ⭐ Filters -->
                <div class="stat-card rounded-2xl p-4 mb-6 animate-in delay-2">
                    <div class="flex flex-col lg:flex-row gap-3">
                        <div class="flex-1 relative">
                            <svg class="w-5 h-5 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" id="searchInput" 
                                   placeholder="NC kodi yoki tavsif bo'yicha qidirish..."
                                   class="input-field w-full pl-10 pr-4 py-2.5 rounded-xl text-sm"
                                   onkeyup="if(event.key === 'Enter') { searchNC(); }">
                        </div>
                        <select id="auditFilter" onchange="applyFilters()" class="input-field px-3 py-2.5 rounded-xl text-sm min-w-[180px]">
                            <option value="0">Barcha auditlar</option>
                            <?php foreach ($audits as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $auditFilter == $a['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['audit_code'] . ' - ' . $a['title'] . ' (' . $a['nc_count'] . ' NC)'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="severityFilter" onchange="applyFilters()" class="input-field px-3 py-2.5 rounded-xl text-sm min-w-[140px]">
                            <option value="0">Barcha darajalar</option>
                            <?php foreach ($severities as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $severityFilter == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" onchange="applyFilters()" class="input-field px-3 py-2.5 rounded-xl text-sm min-w-[140px]">
                            <option value="">Barcha holatlar</option>
                            <option value="open">Ochiq</option>
                            <option value="in_progress">Jarayonda</option>
                            <option value="in_review">Tekshirilmoqda</option>
                            <option value="closed">Yopilgan</option>
                        </select>
                        <?php if ($auditFilter || $severityFilter || $statusFilter): ?>
                        <a href="non_conformities.php" class="flex items-center gap-1.5 text-slate-400 hover:text-white px-3 py-2.5 rounded-xl hover:bg-slate-700/50 transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Tozalash
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ⭐ Status Tabs (Mobile-friendly) -->
                <div class="flex flex-wrap gap-2 mb-6 lg:hidden">
                    <button onclick="applyStatusFilter('')" data-status="" class="filter-btn px-3 py-2 rounded-xl text-sm font-medium <?php !$statusFilter ? 'active' : 'text-slate-400'; ?>">
                        Barchasi
                    </button>
                    <button onclick="applyStatusFilter('open')" data-status="open" class="filter-btn px-3 py-2 rounded-xl text-sm font-medium <?php $statusFilter === 'open' ? 'active text-red-400' : 'text-slate-400'; ?>>
                        Ochiq (<?php echo $ncStatusCounts['open']; ?>)
                    </button>
                    <button onclick="applyStatusFilter('in_progress')" data-status="in_progress" class="filter-btn px-3 py-2 rounded-xl text-sm font-medium <?php $statusFilter === 'in_progress' ? 'active text-amber-400' : 'text-slate-400'; ?>>
                        Jarayonda (<?php echo $ncStatusCounts['in_progress']; ?>)
                    </button>
                    <button onclick="applyStatusFilter('in_review')" data-status="in_review" class="filter-btn px-3 py-2 rounded-xl text-sm font-medium <?php $statusFilter === 'in_review' ? 'active text-purple-400' : 'text-slate-400'; ?>">
                        Tekshirilmoqda (<?php echo $ncStatusCounts['in_review']; ?>)
                    </button>
                    <button onclick="applyStatusFilter('closed')" data-status="closed" class="filter-btn px-3 py-2 rounded-xl text-sm font-medium <?php $statusFilter === 'closed' ? 'active text-emerald-400' : 'text-slate-400'; ?>">
                        Yopilgan (<?php echo $ncStatusCounts['closed']; ?>)
                    </button>
                    <?php if ($ncStatusCounts['overdue'] > 0): ?>
                    <button onclick="applyStatusFilter('overdue')" data-status="overdue" class="filter-btn px-3 py-2 rounded-xl text-sm font-medium text-red-400 border-red-500/30">
                        Muddati o'tgan (<?php echo $ncStatusCounts['overdue']; ?> ⚠️
                    </button>
                    <?php endif; ?>
                </div>

                <!-- NC List -->
                <div class="space-y-4">
                    <?php if (empty($nonConformities)): ?>
                    <div class="stat-card rounded-2xl p-12 text-center animate-in">
                        <div class="w-20 h-20 rounded-full bg-emerald-500/20 flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">Nomuvofiqliklar yo'q</h3>
                        <p class="text-slate-500 mb-6 max-w-md mx-auto">
                            <?php echo ($auditFilter || $severityFilter || $statusFilter) 
                                ? 'Berilgan filtrlar bo\'yicha natijalar topilmadi.' 
                                : 'Hali hech qanday nomuvofiqliklar aniqlanmagan. Audit jarayonida "Yo\'q" tugmasi orqali NC qo\'shing mumkin.'; ?>
                        </p>
                        <?php if (!$auditFilter): ?>
                        <a href="audits.php?action=new" class="inline-flex items-center gap-2 btn-primary px-6 py-3 rounded-xl text-white font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Audit yarating
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <?php foreach ($nonConformities as $nc): 
                            $severityClass = $nc['severity_id'] == 1 ? 'severity-minor' : ($nc['severity_id'] == 2 ? 'severity-major' : 'severity-critical');
                            $isOverdue = $nc['due_date'] && $nc['status'] !== 'closed' && strtotime($nc['due_date']) < time();
                        ?>
                        <div class="nc-card <?php echo $severityClass; ?> <?php echo $isOverdue ? 'is-overdue' : ''; ?> rounded-xl p-5 animate-in">
                            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <!-- Header -->
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <a href="non_conformities.php?id=<?php echo $nc['id']; ?>" class="font-mono text-sm font-bold hover:underline" style="color: <?php echo $nc['color_code']; ?>">
                                            <?php echo htmlspecialchars($nc['nc_code']); ?>
                                        </a>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" 
                                              style="background: <?php echo $nc['color_code']; ?>15; color: <?php echo $nc['color_code']; ?>">
                                            <?php echo htmlspecialchars($nc['severity_name']); ?>
                                        </span>
                                        <?php if ($isOverdue): ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30 pulse-overdue">
                                            Muddati o'tgan!
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Description -->
                                    <p class="text-white text-sm mb-3 leading-relaxed"><?php echo htmlspecialchars($nc['description']); ?></p>
                                    
                                    <!-- Meta info -->
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            <?php echo htmlspecialchars($nc['site_name']); ?>
                                        </span>
                                        <span class="font-mono text-cyan-400"><?php echo $nc['audit_code']; ?></span>
                                        <span class="flex items-center gap-1">
                                            <span class="w-5 h-5 rounded bg-slate-700/50 flex items-center justify-center text-xs font-mono">
                                                <?php echo $nc['section_number']; ?>
                                            </span>
                                            <?php echo htmlspecialchars($nc['section_name']); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Question -->
                                    <details class="mt-3">
                                        <summary class="text-xs text-cyan-400 hover:text-cyan-300 cursor-pointer select-none">
                                            <span class="underline">Savol ko'rish</span>
                                        </summary>
                                        <div class="mt-2 p-3 rounded-lg bg-slate-800/30 text-sm">
                                            <?php echo htmlspecialchars($nc['question_text']); ?>
                                        </div>
                                    </details>
                                </div>
                                
                                <!-- Right side info -->
                                <div class="lg:w-56 flex-shrink-0 space-y-3">
                                    <!-- CAPA Progress -->
                                    <?php if ($nc['capa_count'] > 0): ?>
                                    <div>
                                        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                                            <span>CAPA: <?php echo $nc['capa_completed']; ?>/<?php echo $nc['capa_count']; ?></span>
                                        </div>
                                        <div class="capa-bar h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                            <?php 
                                            $capaPercent = $nc['capa_count'] > 0 ? ($nc['capa_completed'] / $nc['capa_count']) * 100 : 0;
                                            $capaColor = $capaPercent >= 100 ? '#10b981' : ($capaPercent >= 50 ? '#f59e0b' : '#64748b');
                                            ?>
                                            <div class="capa-fill h-full rounded-full" style="width: 0%;" data-width="<?php echo $capaPercent; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="p-2 rounded-lg bg-slate-800/30 text-xs text-slate-500 text-center">
                                        CAPA yaratilmagan
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status -->
                                    <?php
                                    $statusLabels = [
                                        'open' => ['label' => 'Ochiq', 'class' => 'status-open'],
                                        'in_progress' => ['label' => 'Jarayonda', 'class' => 'status-in_progress'],
                                        'in_review' => ['label' => 'Tekshirilmoqda', 'class' => 'status-in_review'],
                                        'closed' => ['label' => 'Yopilgan', 'class' => 'status-closed'],
                                    ];
                                    $statusInfo = $statusLabels[$nc['status']] ?? ['label' => $nc['status'], 'class' => 'status-open'];
                                    ?>
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="status-badge <?php echo $statusInfo['class']; ?>">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <?php if ($nc['status'] === 'open'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                <?php elseif ($nc['status'] === 'in_progress'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                <?php elseif ($nc['status'] === 'in_review'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                <?php endif; ?>
                                            </svg>
                                            <span><?php echo $statusInfo['label']; ?></span>
                                        </span>
                                    </div>
                                    
                                    <!-- Due Date -->
                                    <?php if ($nc['due_date']): ?>
                                    <div class="text-xs text-center <?php echo $isOverdue ? 'text-red-400' : 'text-slate-500'; ?>">
                                        <svg class="w-4 h-4 mx-auto mb-1 <?php echo $isOverdue ? 'text-red-400' : 'text-slate-600'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4h8m-4-4v4h8m-4 4h8M4 7v10m14 0h.01M16 7h.01M5 7h14m-7 14h.01"/>
                                    </svg>
                                    <span class="<?php echo $isOverdue ? 'text-red-400 font-semibold' : ''; ?>">
                                        <?php echo date('d.m.Y', strtotime($nc['due_date'])); ?>
                                    </span>
                                </div>
                                    <?php else: ?>
                                    <div class="text-xs text-center text-slate-600">
                                        Muddat belgilanmagan
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Creator & Date -->
                                    <div class="pt-3 border-t border-slate-700/30 mt-3 text-xs text-slate-600">
                                        <div class="flex items-center gap-1 justify-end">
                                            <span class="truncate"><?php echo htmlspecialchars($nc['creator_name']); ?></span>
                                        <span>·</span>
                                            <?php echo date('d.m.Y H:i', strtotime($nc['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between pt-6">
                    <p class="text-sm text-slate-500">
                        <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalNC); ?> / <?php echo $totalNC; ?>
                    </p>
                    <div class="flex items-center gap-1">
                        <?php if ($currentPage > 1): ?>
                        <a href="<?php echo buildNCUrl(['page' => $currentPage - 1]); ?>" class="w-9 h-9 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <a href="<?php echo buildNCUrl(['page' => $i]); ?>" 
                           class="w-9 h-9 rounded-lg flex items-center justify-center text-sm transition-colors <?php echo $i === $currentPage ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                        <a href="<?php echo buildNCUrl(['page' => $currentPage + 1]); ?>" class="w-9 h-9 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7 7"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        // ⭐ URL builder funksiyasi
        function buildNCUrl($overrides = []) {
            const params = <?php echo json_encode($_GET); ?>;
            delete params.page;
            Object.assign(params, $overrides);
            const query = new URLSearchParams(params).toString();
            return 'non_conformities.php' + (query ? '?' + query : '');
        }

        // ⭐ Filter funksiyasi
        function applyFilters(statusOverride = null) {
            const auditSelect = document.getElementById('auditFilter');
            const severitySelect = document.getElementById('severityFilter');
            const statusSelect = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            
            let url = 'non_conformities.php?';
            const params = new URLSearchParams(window.location.search);
            
            if (auditSelect && auditSelect.value !== '0') params.set('audit', auditSelect.value);
            else params.delete('audit');
            
            if (severitySelect && severitySelect.value !== '0') params.set('severity', severitySelect.value);
            else params.delete('severity');
            
            const status = statusOverride ?? statusSelect?.value;
            if (status) params.set('status', status);
            else params.delete('status');
            
            if (searchInput && searchInput.value.trim()) params.set('search', searchInput.value.trim());
            else params.delete('search');
            
            params.delete('page');
            window.location.href = url;
        }

        // ⭐ CAPA progress animatsiyasi
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.capa-fill').forEach(bar => {
                const width = bar.dataset.width;
                if (width > 0) {
                    setTimeout(() => { bar.style.width = width + '%'; }, 100);
                }
            });
        });

        // Mobile filter buttons
        function applyStatusFilter(status) {
            document.getElementById('statusFilter').value = status || '';
            applyFilters(status);
        }

        // Keyboard shortcut
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const overlay = document.getElementById('overlay');
                if (!overlay.classList.contains('hidden')) toggleSidebar();
            }
        });
    </script>
</body>
</html>