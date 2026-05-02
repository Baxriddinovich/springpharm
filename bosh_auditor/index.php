<?php
require_once '../db.php';
requireLogin();
 $user = getCurrentUser();
if ($user['role'] !== 'bosh_auditor' && $user['role'] !== 'admin') {
    header('Location: index.php'); 
    exit;
}

define('STATUS_DRAFT', 'draft');
define('STATUS_IN_PROGRESS', 'in_progress');
define('STATUS_COMPLETED', 'completed');

define('SEVERITY_CRITICAL', 3);
define('SEVERITY_MAJOR', 2);
define('SEVERITY_MINOR', 1);

 $stats = [
    'total_audits' => $pdo->query("SELECT COUNT(*) FROM audits")->fetchColumn(),
    
    'active_audits' => $pdo->query("
        SELECT COUNT(*) FROM audits 
        WHERE status IN ('" . STATUS_DRAFT . "', '" . STATUS_IN_PROGRESS . "')
    ")->fetchColumn(),

    'completed_audits' => $pdo->query("
        SELECT COUNT(*) FROM audits 
        WHERE status = '" . STATUS_COMPLETED . "'
    ")->fetchColumn(),

    'total_nc' => $pdo->query("SELECT COUNT(*) FROM non_conformities")->fetchColumn(),

    'critical_nc' => $pdo->query("
        SELECT COUNT(*) FROM non_conformities 
        WHERE severity_id = " . SEVERITY_CRITICAL
    )->fetchColumn(),

    'major_nc' => $pdo->query("
        SELECT COUNT(*) FROM non_conformities 
        WHERE severity_id = " . SEVERITY_MAJOR
    )->fetchColumn(),

    'minor_nc' => $pdo->query("
        SELECT COUNT(*) FROM non_conformities 
        WHERE severity_id = " . SEVERITY_MINOR
    )->fetchColumn(),
];

 $recentAudits = $pdo->query("
    SELECT a.*, s.name as site_name, u.full_name as creator_name 
    FROM audits a 
    JOIN sites s ON a.site_id = s.id 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

 $ncBySeverity = $pdo->query("
    SELECT st.name, st.name_en, st.color_code, COUNT(nc.id) as count 
    FROM severity_types st 
    LEFT JOIN non_conformities nc ON st.id = nc.severity_id 
    GROUP BY st.id
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

 $recentActivity = $pdo->query("
    SELECT 
        CASE 
            WHEN action_type = 'audit_created' THEN 'Audit yaratildi'
            WHEN action_type = 'audit_completed' THEN 'Audit tugallandi'
            WHEN action_type = 'nc_created' THEN 'Nomuvofiqlik qo\'shildi'
            WHEN action_type = 'nc_closed' THEN 'Nomuvofiqlik yopildi'
            ELSE action_type
        END as action_text,
        details,
        al.created_at,
        u.full_name as user_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC 
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

 $monthlyStats = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM audits 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

 $pendingCapa = $pdo->query("
    SELECT COUNT(*) FROM non_conformities 
    WHERE status IN ('open', 'in_progress') 
    AND due_date < CURDATE()
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bosh Auditor Paneli - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        /* CSS styles (Admin dagidan farq yo'q) */
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

        .stat-card {
            background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.5);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(6, 182, 212, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .progress-ring {
            transform: rotate(-90deg);
        }

        .progress-ring-circle {
            transition: stroke-dasharray 1s ease-in-out;
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
            animation: fadeInUp 0.6s ease forwards;
        }

        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
        .delay-4 { animation-delay: 0.4s; opacity: 0; }
        .delay-5 { animation-delay: 0.5s; opacity: 0; }

        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }

        .badge-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge-info { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        .bar-chart-bar {
            transition: height 0.8s ease-in-out;
            border-radius: 4px 4px 0 0;
        }

        .activity-item {
            position: relative;
            padding-left: 2rem;
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 24px;
            bottom: -8px;
            width: 2px;
            background: rgba(51, 65, 85, 0.5);
        }

        .activity-item:last-child::before {
            display: none;
        }

        .activity-dot {
            position: absolute;
            left: 0;
            top: 6px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid;
        }

        @keyframes pulse-red {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        }

        .pulse-danger {
            animation: pulse-red 2s infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="min-h-screen text-slate-100">

    <!-- Mobile Menu Button -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <span class="font-bold text-white text-sm">GMP Audit</span>
        </div>
        <button onclick="toggleSidebar()" id="menuBtn" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors" aria-label="Menyu ochish">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-500 font-mono">Bosh Auditor</p>
                    </div>
                </div>

                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <a href="./" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Bosh panel
                    </a>

                    <a href="auditss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Auditlar
                    </a>

                    <a href="reportss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Hisobotlar
                    </a>

                    <div class="pt-4 mt-4 border-t border-slate-700/50">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>

                        <a href="sectionss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Bo'limlar
                        </a>

                        <a href="checklistss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            Checklistlar
                        </a>
                        
                        <!-- Auditorlar va Tizim Tarixi OLIB TASHLANDI -->
                    </div>
                </nav>

                <div class="border-t border-slate-700/50 pt-4 mt-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center text-white font-semibold text-sm">
                            <?php echo strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </p>
                            <p class="text-xs text-slate-500">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                            </p>
                        </div>
                        <a href="../logout.php" class="text-slate-500 hover:text-red-400 transition-colors p-2" aria-label="Chiqish">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
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
                    <h1 class="text-2xl font-bold text-white">Bosh Auditor Paneli</h1>
                    <p class="text-slate-500 text-sm">
                        Xush kelibsiz, <?php echo htmlspecialchars($user['full_name']); ?> ·
                        <span class="text-slate-600"><?php echo date('d.m.Y, H:i'); ?></span>
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <?php if ($pendingCapa > 0): ?>
                        <a href="non_conformities.php?filter=overdue" class="flex items-center gap-2 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-2.5 rounded-xl font-medium transition-all hover:bg-red-500/20 pulse-danger">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <?php echo $pendingCapa; ?> muddati o'tgan
                        </a>
                    <?php endif; ?>

                    <a href="audits.php?action=new" class="flex items-center gap-2 bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-lg shadow-cyan-500/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Yangi Audit
                    </a>
                </div>
            </header>

            <!-- Mobile Action Buttons -->
            <div class="lg:hidden flex gap-2 p-4 overflow-x-auto">
                <?php if ($pendingCapa > 0): ?>
                    <a href="non_conformities.php?filter=overdue" class="flex-shrink-0 flex items-center gap-2 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-2 rounded-xl text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <?php echo $pendingCapa; ?> muddati o'tgan
                    </a>
                <?php endif; ?>
                <a href="audits.php?action=new" class="flex-shrink-0 flex items-center gap-2 bg-gradient-to-r from-cyan-500 to-teal-500 text-white px-4 py-2 rounded-xl text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Yangi Audit
                </a>
            </div>

            <div class="p-4 lg:p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                    <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white mb-1" data-count="<?php echo $stats['total_audits']; ?>">0</div>
                        <div class="text-slate-500 text-sm">Jami Auditlar</div>
                    </div>

                    <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white mb-1" data-count="<?php echo $stats['active_audits']; ?>">0</div>
                        <div class="text-slate-500 text-sm">Jarayondagi</div>
                    </div>

                    <div class="stat-card rounded-2xl p-5 animate-in delay-3">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white mb-1" data-count="<?php echo $stats['completed_audits']; ?>">0</div>
                        <div class="text-slate-500 text-sm">Yakunlangan</div>
                    </div>

                    <div class="stat-card rounded-2xl p-5 animate-in delay-4">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center">
                                <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-white mb-1" data-count="<?php echo $stats['total_nc']; ?>">0</div>
                        <div class="text-slate-500 text-sm">Nomuvofiqliklar</div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid lg:grid-cols-3 gap-6 mb-8">
                    <!-- NC by Severity Chart -->
                    <div class="stat-card rounded-2xl p-6 animate-in delay-3">
                        <h3 class="text-lg font-semibold text-white mb-6">Nomuvofiqliklar turlari</h3>
                        <div class="flex justify-center mb-6">
                            <div class="relative">
                                <svg class="progress-ring w-40 h-40" viewBox="0 0 120 120" id="donutChart">
                                    <circle cx="60" cy="60" r="50" fill="none" stroke="#1e293b" stroke-width="12" />
                                    <?php
                                    $total = max($stats['total_nc'], 1);
                                    $colors = ['#10b981', '#f59e0b', '#ef4444'];
                                    $values = [$stats['minor_nc'], $stats['major_nc'], $stats['critical_nc']];
                                    $circumference = 2 * pi() * 50;
                                    for ($i = 0; $i < 3; $i++):
                                        $percent = ($values[$i] / $total) * 100;
                                        $dashLength = ($percent / 100) * $circumference;
                                        $gapLength = $circumference - $dashLength;
                                    ?>
                                        <circle cx="60" cy="60" r="50" fill="none" stroke="<?php echo $colors[$i]; ?>"
                                            stroke-width="12" stroke-dasharray="0 <?php echo $circumference; ?>"
                                            data-target-dasharray="<?php echo $dashLength; ?> <?php echo $gapLength; ?>"
                                            class="progress-ring-circle" stroke-linecap="round" />
                                    <?php endfor; ?>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-white" data-count="<?php echo $stats['total_nc']; ?>">0</div>
                                        <div class="text-xs text-slate-500">Jami</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($ncBySeverity as $nc): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-3 h-3 rounded-full" style="background: <?php echo $nc['color_code']; ?>"></div>
                                        <span class="text-slate-400"><?php echo htmlspecialchars($nc['name']); ?></span>
                                    </div>
                                    <span class="text-white font-semibold"><?php echo $nc['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Monthly Bar Chart -->
                    <div class="stat-card rounded-2xl p-6 animate-in delay-4">
                        <h3 class="text-lg font-semibold text-white mb-6">Oylik auditlar</h3>
                        <?php if (!empty($monthlyStats)): ?>
                            <div class="flex items-end justify-between h-40 gap-2" id="barChart">
                                <?php
                                $monthlyStats = array_reverse($monthlyStats);
                                $maxCount = max(array_column($monthlyStats, 'count')) ?: 1;
                                foreach ($monthlyStats as $stat):
                                    $heightPercent = ($stat['count'] / $maxCount) * 100;
                                    $monthLabel = date('M', strtotime($stat['month'] . '-01'));
                                ?>
                                    <div class="flex-1 flex flex-col items-center gap-2">
                                        <div class="text-xs text-slate-500"><?php echo $stat['count']; ?></div>
                                        <div class="w-full bg-slate-800 rounded-t relative" style="height: 100px;">
                                            <div class="bar-chart-bar absolute bottom-0 left-0 right-0 bg-gradient-to-t from-cyan-500 to-cyan-400" data-height="<?php echo $heightPercent; ?>" style="height: 0%;"></div>
                                        </div>
                                        <div class="text-xs text-slate-600"><?php echo $monthLabel; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center justify-center h-40 text-slate-600">Ma'lumotlar yo'q</div>
                        <?php endif; ?>
                        <div class="mt-4 pt-4 border-t border-slate-700/50 flex items-center justify-between text-sm">
                            <span class="text-slate-500">Oxirgi 6 oy</span>
                            <span class="text-cyan-400">O'rtacha: <?php echo !empty($monthlyStats) ? round(array_sum(array_column($monthlyStats, 'count')) / count($monthlyStats), 1) : 0; ?> / oy</span>
                        </div>
                    </div>

                    <!-- Recent Activity Timeline -->
                    <div class="stat-card rounded-2xl p-6 animate-in delay-5">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-white">So'nggi faoliyatlar</h3>
                            <!-- "Barchasi" linki olib tashlandi, chunki Tizim Tarixi yo'q -->
                        </div>
                        <div class="space-y-4 max-h-[280px] overflow-y-auto pr-2">
                            <?php if (empty($recentActivity)): ?>
                                <div class="text-center text-slate-600 py-8">Hali faoliyatlar yo'q</div>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity):
                                    $dotColor = (strpos($activity['action_text'], 'yaratildi') !== false)
                                        ? 'border-cyan-400 bg-cyan-400/20'
                                        : ((strpos($activity['action_text'], 'tugallandi') !== false || strpos($activity['action_text'], 'yopildi') !== false)
                                            ? 'border-emerald-400 bg-emerald-400/20'
                                            : 'border-amber-400 bg-amber-400/20');
                                    $timeAgo = getTimeAgo($activity['created_at']);
                                ?>
                                    <div class="activity-item">
                                        <div class="activity-dot <?php echo $dotColor; ?>"></div>
                                        <div>
                                            <p class="text-sm text-slate-300"><?php echo htmlspecialchars($activity['action_text']); ?></p>
                                            <p class="text-xs text-slate-600 mt-1"><?php echo htmlspecialchars($activity['user_name']); ?> · <?php echo $timeAgo; ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Audits Table -->
                <div class="stat-card rounded-2xl p-6 mb-8 animate-in delay-4">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-white">So'nggi auditlar</h3>
                        <a href="audits.php" class="text-cyan-400 hover:text-cyan-300 text-sm flex items-center gap-1">
                            Barchasi
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                    <!-- Table code remains same -->
                    <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full min-w-[600px]">
                            <thead>
                                <tr class="border-b border-slate-700/50">
                                    <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider pb-3">Kod</th>
                                    <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider pb-3">Korxona</th>
                                    <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider pb-3">Auditor</th>
                                    <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider pb-3">Holat</th>
                                    <th class="text-right text-xs font-medium text-slate-500 uppercase tracking-wider pb-3">Progress</th>
                                    <th class="text-right text-xs font-medium text-slate-500 uppercase tracking-wider pb-3">Sana</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php if (empty($recentAudits)): ?>
                                    <tr>
                                        <td colspan="6" class="py-12 text-center">
                                            <p class="text-slate-500">Hali auditlar yo'q</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentAudits as $audit):
                                        $statusClasses = ['draft' => 'badge-info', 'in_progress' => 'badge-warning', 'completed' => 'badge-success', 'cancelled' => 'badge-danger'];
                                        $statusLabels = ['draft' => 'Draft', 'in_progress' => 'Jarayonda', 'completed' => 'Tugatilgan', 'cancelled' => 'Bekor qilindi'];
                                    ?>
                                        <tr class="hover:bg-slate-800/30 transition-colors cursor-pointer" onclick="window.location='audits.php?action=view&id=<?php echo $audit['id']; ?>'">
                                            <td class="py-4 font-mono text-cyan-400 text-sm"><?php echo htmlspecialchars($audit['audit_code']); ?></td>
                                            <td class="py-4 text-white text-sm font-medium"><?php echo htmlspecialchars($audit['site_name']); ?></td>
                                            <td class="py-4 text-slate-400 text-sm"><?php echo htmlspecialchars($audit['creator_name']); ?></td>
                                            <td class="py-4"><span class="badge <?php echo $statusClasses[$audit['status']] ?? 'badge-info'; ?>"><?php echo $statusLabels[$audit['status']] ?? $audit['status']; ?></span></td>
                                            <td class="py-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <div class="w-16 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                                        <div class="h-full bg-cyan-500 rounded-full" style="width: <?php echo $audit['progress_percent']; ?>%"></div>
                                                    </div>
                                                    <span class="text-sm text-slate-400 w-10 text-right font-mono"><?php echo number_format($audit['progress_percent'], 0); ?>%</span>
                                                </div>
                                            </td>
                                            <td class="py-4 text-right text-slate-500 text-sm"><?php echo date('d.m.Y', strtotime($audit['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <footer class="px-8 py-6 border-t border-slate-800 mt-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-600">
                    <span>GMP Audit Tizimi - Bosh Auditor © <?php echo date('Y'); ?></span>
                    <span>Oxirgi yangilanish: <?php echo date('d.m.Y H:i'); ?></span>
                </div>
            </footer>
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

        function animateCounters() {
            document.querySelectorAll('[data-count]').forEach(el => {
                const target = parseInt(el.dataset.count);
                if (target === 0) return;
                let current = 0;
                const increment = Math.ceil(target / 30);
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) { current = target; clearInterval(timer); }
                    el.textContent = current.toLocaleString();
                }, 30);
            });
        }

        function animateDonutChart() {
            const circles = document.querySelectorAll('.progress-ring-circle');
            circles.forEach((circle, index) => {
                const targetDasharray = circle.dataset.targetDasharray;
                if (!targetDasharray) return;
                setTimeout(() => { circle.style.strokeDasharray = targetDasharray; }, 200 + (index * 150));
            });
        }

        function animateBarChart() {
            document.querySelectorAll('.bar-chart-bar').forEach((bar, index) => {
                const targetHeight = bar.dataset.height;
                setTimeout(() => { bar.style.height = targetHeight + '%'; }, 300 + (index * 100));
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(animateCounters, 300);
            setTimeout(animateDonutChart, 500);
            setTimeout(animateBarChart, 600);
        });
    </script>
</body>
</html>