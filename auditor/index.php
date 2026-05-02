<?php
require_once '../db.php';
requireLogin();

 $user = getCurrentUser();

// Faqat oddiy auditorlar uchun
if (!in_array($user['role'], ['auditor'])) {
    // Agar boshqa rol bo'lsa, o'z paneliga qaytaradi
    header("Location: dashboard.php"); 
    exit;
}

// ⭐ Statistikalar (Faqat o'ziga tegishli auditlar)
 $stats = [
    'total' => 0,
    'active' => 0,
    'completed' => 0,
    'pending_nc' => 0
];

// Auditorga biriktirilgan auditlar soni
 $stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total,
        SUM(CASE WHEN a.status IN ('draft', 'in_progress') THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM audits a
    JOIN audit_assignments aa ON a.id = aa.audit_id
    WHERE aa.auditor_id = ?
");
 $stmt->execute([$user['id']]);
 $statData = $stmt->fetch();
if ($statData) {
    $stats['total'] = $statData['total'];
    $stats['active'] = $statData['active'];
    $stats['completed'] = $statData['completed'];
}

// Topshiriqlar (Ochiq NClar bo'yicha)
 $ncStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM non_conformities nc
    JOIN audits a ON nc.audit_id = a.id
    JOIN audit_assignments aa ON a.id = aa.audit_id
    WHERE aa.auditor_id = ? AND nc.status IN ('open', 'in_progress')
");
 $ncStmt->execute([$user['id']]);
 $stats['pending_nc'] = $ncStmt->fetchColumn();

// ⭐ So'nggi biriktirilgan auditlar
 $myAudits = $pdo->prepare("
    SELECT a.*, s.name as site_name,
           (SELECT COUNT(*) FROM audit_answers ans WHERE ans.audit_id = a.id AND ans.answer != 'na') as answered,
           (SELECT COUNT(*) FROM checklist_questions WHERE is_active = 1) as total_q
    FROM audits a
    JOIN sites s ON a.site_id = s.id
    JOIN audit_assignments aa ON a.id = aa.audit_id
    WHERE aa.auditor_id = ?
    ORDER BY 
        CASE a.status 
            WHEN 'in_progress' THEN 1 
            WHEN 'draft' THEN 2 
            ELSE 3 
        END, 
        a.created_at DESC
");
 $myAudits->execute([$user['id']]);
 $audits = $myAudits->fetchAll(PDO::FETCH_ASSOC);

// Ustunlar uchun yordamchi funksiyalar
 $statusClasses = [
    'draft' => 'badge-info',
    'in_progress' => 'badge-warning',
    'completed' => 'badge-success',
    'cancelled' => 'badge-danger'
];
 $statusLabels = [
    'draft' => 'Draft',
    'in_progress' => 'Jarayonda',
    'completed' => 'Tugatilgan',
    'cancelled' => 'Bekor qilingan'
];
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent-cyan: #06b6d4; }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); }
        
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
        
        .stat-card {
            background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.5);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-4px); border-color: rgba(6, 182, 212, 0.3); }
        
        .audit-card {
            background: rgba(26, 35, 50, 0.6);
            border: 1px solid rgba(51, 65, 85, 0.5);
            transition: all 0.3s ease;
        }
        .audit-card:hover { border-color: rgba(6, 182, 212, 0.3); transform: translateX(4px); }
        
        .badge { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-info { background: rgba(6, 182, 212, 0.15); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.2); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .progress-bar { background: rgba(30, 41, 59, 0.8); overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #06b6d4, #14b8a6); transition: width 0.8s ease; }
        
        .btn-primary { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-in { animation: fadeInUp 0.5s ease forwards; }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
        .delay-4 { animation-delay: 0.4s; opacity: 0; }
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-500 font-mono">Auditor</p>
                    </div>
                </div>
                
                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <a href="index.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Bosh panel
                    </a>
                    <a href="bro.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
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
                        <a href="../logout.php" class="text-slate-500 hover:text-red-400 transition-colors p-2" aria-label="Chiqish">
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
            <header class="hidden lg:flex sticky top-0 z-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50 px-8 py-4 items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">Bosh Panel</h1>
                    <p class="text-slate-500 text-sm">
                        Xush kelibsiz, <?php echo htmlspecialchars($user['full_name']); ?> ·
                        <span class="text-slate-600"><?php echo date('d.m.Y'); ?></span>
                    </p>
                </div>
            </header>

            <div class="p-4 lg:p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['total']; ?></div>
                        <div class="text-slate-500 text-sm">Menga biriktirilgan</div>
                    </div>

                    <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['active']; ?></div>
                        <div class="text-slate-500 text-sm">Faol auditlar</div>
                    </div>

                    <div class="stat-card rounded-2xl p-5 animate-in delay-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['completed']; ?></div>
                        <div class="text-slate-500 text-sm">Yakunlangan</div>
                    </div>

                    <div class="stat-card rounded-2xl p-5 animate-in delay-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white"><?php echo $stats['pending_nc']; ?></div>
                        <div class="text-slate-500 text-sm">Kutilayotgan ishlar</div>
                    </div>
                </div>

                <!-- My Audits List -->
                <div class="stat-card rounded-2xl p-6 mb-8 animate-in delay-3">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-white">Mening Auditlarim</h3>
                        <!-- <a href="auditss.php" class="text-cyan-400 hover:text-cyan-300 text-sm flex items-center gap-1">
                            Barchasi
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a> -->
                    </div>

                    <?php if (empty($audits)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <h3 class="text-xl font-semibold text-white mb-2">Hali topshiriqlar yo'q</h3>
                        <p class="text-slate-500">Sizga hali hech qanday audit topshirilmagan.</p>
                    </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($audits as $audit): 
                                $progress = $audit['total_q'] > 0 ? round(($audit['answered'] / $audit['total_q']) * 100, 1) : 0;
                            ?>
                            <div class="audit-card rounded-xl p-4 lg:p-5 flex flex-col lg:flex-row lg:items-center gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-mono text-cyan-400 text-sm"><?php echo $audit['audit_code']; ?></span>
                                        <span class="badge <?php echo $statusClasses[$audit['status']] ?? 'badge-info'; ?>">
                                            <?php echo $statusLabels[$audit['status']] ?? $audit['status']; ?>
                                        </span>
                                    </div>
                                    <h4 class="text-white font-medium mb-1"><?php echo htmlspecialchars($audit['title']); ?></h4>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                                        <span><?php echo htmlspecialchars($audit['site_name']); ?></span>
                                        <span><?php echo date('d.m.Y', strtotime($audit['start_date'])); ?></span>
                                    </div>
                                </div>

                                <div class="lg:w-48">
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="text-slate-500">Progress</span>
                                        <span class="text-white font-mono"><?php echo $progress; ?>%</span>
                                    </div>
                                    <div class="progress-bar h-2 rounded-full">
                                        <div class="progress-fill h-full rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <?php if ($audit['status'] === 'in_progress'): ?>
                                        <a href="conduct_audit.php?id=<?php echo $audit['id']; ?>" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap">
                                            Davom etish
                                        </a>
                                    <?php elseif ($audit['status'] === 'draft'): ?>
                                        <span class="text-slate-600 text-sm italic px-4 py-2">Kutilmoqda...</span>
                                    <?php elseif ($audit['status'] === 'completed'): ?>
                                        <!-- <a href="reports.php?audit=<?php echo $audit['id']; ?>" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap">
                                            Hisobot
                                        </a> -->
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Help Section -->
                <div class="stat-card rounded-2xl p-6 animate-in delay-4">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold mb-1">Qanday ishlaydi?</h3>
                            <p class="text-slate-400 text-sm leading-relaxed">
                                Sizga bosh auditor tomonidan audit topshiriladi. "Jarayonda" statusidagi auditlarni boshlashingiz yoki davom ettirishingiz mumkin. 
                                Har bir savolga baho berib boring va nomuvofiqlik topsangiz, darhol qayd qiling.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="px-8 py-6 border-t border-slate-800 mt-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-600">
                    <span>GMP Audit Tizimi © <?php echo date('Y'); ?></span>
                </div>
            </footer>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }
    </script>
</body>
</html>