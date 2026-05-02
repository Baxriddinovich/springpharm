<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../db.php';
requireLogin();

 $user = getCurrentUser();

// Rol tekshirish: Faqat 'viewer' yoki 'super_admin'/'bosh_auditor' (test uchun) ruxsat
if (!in_array($user['role'], ['viewer', 'super_admin', 'bosh_auditor'])) {
    // Agar viewer bo'lmasa, o'z dashboardiga qaytaradi
    header("Location: dashboard.php");
    exit;
}


function getGmpGrade(float $percentage): array {
    if ($percentage >= 90) return ['grade' => 'A', 'label' => 'A\'lo', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)'];
    if ($percentage >= 80) return ['grade' => 'B', 'label' => 'Yaxshi', 'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,0.15)'];
    if ($percentage >= 60) return ['grade' => 'C', 'label' => 'Qoniqarli', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.15)'];
    return ['grade' => 'D', 'label' => 'Qoniqarsiz', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.15)'];
}


// ==================== SAHIFA LOGIKASI ====================

 $page = $_GET['page'] ?? 'dashboard';
 $sectionId = (int)($_GET['section'] ?? 0);
 $auditId = (int)($_GET['audit'] ?? 0);

// Sahifa uchun kerakli ma'lumotlarni yuklash
switch ($page) {
    case 'audits':
        $auditsList = $pdo->query("
            SELECT a.*, s.name as site_name, u.full_name as creator_name,
                   (SELECT COUNT(*) FROM audit_answers WHERE audit_id = a.id AND answer != 'na') as answered_count,
                   (SELECT COUNT(*) FROM checklist_questions WHERE is_active = 1) as total_questions
            FROM audits a 
            JOIN sites s ON a.site_id = s.id 
            JOIN users u ON a.created_by = u.id 
            ORDER BY a.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'sections':
        $sectionsList = $pdo->query("
            SELECT gs.*, (SELECT COUNT(*) FROM checklist_questions cq WHERE cq.section_id = gs.id AND cq.is_active = 1) as question_count
            FROM gmp_sections gs 
            ORDER BY gs.sort_order
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'checklists':
        $sectionsFilter = $pdo->query("SELECT * FROM gmp_sections ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
        $where = $sectionId ? "AND cq.section_id = $sectionId" : "";
        $questionsList = $pdo->query("
            SELECT cq.*, gs.section_number, gs.section_name 
            FROM checklist_questions cq 
            JOIN gmp_sections gs ON cq.section_id = gs.id 
            WHERE cq.is_active = 1 $where
            ORDER BY gs.sort_order, cq.sort_order
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'users':
        $usersList = $pdo->query("
            SELECT u.*, 
                   (SELECT COUNT(*) FROM audit_assignments aa WHERE aa.auditor_id = u.id) as assignments_count
            FROM users u 
            ORDER BY u.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'logs':
        $logsList = $pdo->query("
            SELECT al.*, u.full_name as user_name
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'reports':
        $reportsList = $pdo->query("
            SELECT a.*, s.name as site_name 
            FROM audits a 
            JOIN sites s ON a.site_id = s.id 
            WHERE a.status = 'completed'
            ORDER BY a.completed_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'report_detail':
        if ($auditId) {
            $report = $pdo->prepare("
                SELECT a.*, s.name as site_name, s.address, u.full_name as creator_name
                FROM audits a
                JOIN sites s ON a.site_id = s.id
                JOIN users u ON a.created_by = u.id
                WHERE a.id = ?
            ")->execute([$auditId]) ? $pdo->query("SELECT * FROM audits WHERE id = $auditId")->fetch() : null;
            
            // Ma'lumotlarni qayta olish (prepare bilan ishlaganda reference muammosi bo'lgani uchun soddaroq yo'l)
            $report = $pdo->query("SELECT a.*, s.name as site_name, s.address, u.full_name as creator_name FROM audits a JOIN sites s ON a.site_id = s.id JOIN users u ON a.created_by = u.id WHERE a.id = $auditId")->fetch();
            
            if ($report) {
                $sectionsReport = $pdo->query("
                    SELECT gs.section_name, gs.section_number,
                           COUNT(cq.id) as total_questions,
                           SUM(CASE WHEN aa.answer = 'ha' THEN 1 ELSE 0 END) as yes_count,
                           COALESCE(SUM(aa.score), 0) as earned_score,
                           SUM(cq.score) as max_score
                    FROM gmp_sections gs
                    LEFT JOIN checklist_questions cq ON cq.section_id = gs.id
                    LEFT JOIN audit_answers aa ON aa.question_id = cq.id AND aa.audit_id = $auditId
                    GROUP BY gs.id
                    ORDER BY gs.sort_order
                ")->fetchAll(PDO::FETCH_ASSOC);

                $totalScore = array_sum(array_column($sectionsReport, 'earned_score'));
                $maxScore = array_sum(array_column($sectionsReport, 'max_score'));
                $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;
                $grade = getGmpGrade($percentage);

                $ncsReport = $pdo->query("
                    SELECT nc.*, st.name as severity_name, st.color_code
                    FROM non_conformities nc
                    JOIN severity_types st ON nc.severity_id = st.id
                    WHERE nc.audit_id = $auditId
                    ORDER BY st.id DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        break;

    case 'dashboard':
    default:
        // Dashboard Statistikasi
        $stats = [
            'total_audits' => $pdo->query("SELECT COUNT(*) FROM audits")->fetchColumn(),
            'active_audits' => $pdo->query("SELECT COUNT(*) FROM audits WHERE status IN ('draft', 'in_progress')")->fetchColumn(),
            'completed_audits' => $pdo->query("SELECT COUNT(*) FROM audits WHERE status = 'completed'")->fetchColumn(),
            'total_nc' => $pdo->query("SELECT COUNT(*) FROM non_conformities")->fetchColumn(),
        ];
        $recentAudits = $pdo->query("
            SELECT a.*, s.name as site_name 
            FROM audits a JOIN sites s ON a.site_id = s.id 
            ORDER BY a.created_at DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        $ncBySeverity = $pdo->query("
            SELECT st.name, st.color_code, COUNT(nc.id) as count 
            FROM severity_types st LEFT JOIN non_conformities nc ON st.id = nc.severity_id 
            GROUP BY st.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// ==================== HTML BOSHLANISHI ====================
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer Panel - GMP Audit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent: #06b6d4; }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); }
        
        .sidebar { background: linear-gradient(180deg, #111827 0%, #0f172a 100%); border-right: 1px solid rgba(51, 65, 85, 0.5); transition: transform 0.3s; }
        @media (max-width: 1023px) { .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; } .sidebar.active { transform: translateX(0); } }
        
        .nav-item { transition: all 0.3s; border-left: 3px solid transparent; }
        .nav-item:hover { background: rgba(6, 182, 212, 0.1); border-left-color: var(--accent); }
        .nav-item.active { background: rgba(6, 182, 212, 0.15); border-left-color: var(--accent); }
        
        .card { background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%); border: 1px solid rgba(51, 65, 85, 0.5); backdrop-filter: blur(10px); }
        .card:hover { border-color: rgba(6, 182, 212, 0.3); }
        
        .badge { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 500; }
        .bg-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .bg-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .bg-info { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }
        .bg-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    </style>
</head>
<body class="min-h-screen text-slate-100">

    <!-- Mobile Header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-slate-600 flex items-center justify-center text-white">👁️</div>
            <span class="font-bold text-white">GMP Viewer</span>
        </div>
        <button onclick="toggleSidebar()" class="text-slate-400 p-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <div class="flex min-h-screen pt-14 lg:pt-0">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 fixed h-full z-50">
            <div class="p-6 h-full flex flex-col">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 rounded-xl bg-slate-700 flex items-center justify-center text-xl">👁️</div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-400">Viewer Mode</p>
                    </div>
                </div>
                
                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <a href="?page=dashboard" class="nav-item <?php echo $page=='dashboard'?'active text-white':'text-slate-400'; ?> flex items-center gap-3 px-4 py-3 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Bosh panel
                    </a>
                    <a href="?page=audits" class="nav-item <?php echo $page=='audits'?'active text-white':'text-slate-400'; ?> flex items-center gap-3 px-4 py-3 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Auditlar
                    </a>
                    <a href="?page=reports" class="nav-item <?php echo $page=='reports'?'active text-white':'text-slate-400'; ?> flex items-center gap-3 px-4 py-3 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Hisobotlar
                    </a>
                    
                    <div class="pt-4 mt-4 border-t border-slate-700">
                        <p class="px-4 text-xs text-slate-600 uppercase mb-2">Ma'lumotlar</p>
                        <a href="?page=sections" class="nav-item <?php echo $page=='sections'?'active text-white':'text-slate-400'; ?> flex items-center gap-3 px-4 py-3 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Bo'limlar
                        </a>
                        <a href="?page=checklists" class="nav-item <?php echo $page=='checklists'?'active text-white':'text-slate-400'; ?> flex items-center gap-3 px-4 py-3 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Checklistlar
                        </a>
                    </div>

                    <div class="pt-4 mt-4 border-t border-slate-700">
                         <p class="px-4 text-xs text-slate-600 uppercase mb-2">Boshqaruv (Read Only)</p>
                        <a href="?page=users" class="nav-item <?php echo $page=='users'?'active text-white':'text-slate-400'; ?> flex items-center gap-3 px-4 py-3 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            Foydalanuvchilar
                        </a>
                        <a href="?page=logs" class="nav-item <?php echo $page=='logs'?'active text-white':'text-slate-400'; ?> flex items-center gap-3 px-4 py-3 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Tizim Tarixi
                        </a>
                    </div>
                </nav>
                
                <div class="border-t border-slate-700 pt-4 mt-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-white font-bold">
                            <?php echo mb_substr($user['full_name'], 0, 1); ?>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-slate-500">Viewer (Read Only)</p>
                        </div>
                        <a href="logout.php" class="text-slate-500 hover:text-red-400 p-2" title="Chiqish">🚪</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-64 w-full">
            <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur border-b border-slate-700/50 px-6 py-4 hidden lg:flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">
                        <?php 
                            $titles = [
                                'dashboard' => 'Bosh Panel',
                                'audits' => 'Auditlar',
                                'reports' => 'Hisobotlar',
                                'sections' => 'Bo\'limlar',
                                'checklists' => 'Checklistlar',
                                'users' => 'Foydalanuvchilar',
                                'logs' => 'Tizim Tarixi',
                                'report_detail' => 'Hisobot'
                            ];
                            echo $titles[$page] ?? 'Bosh Panel'; 
                        ?>
                        <span class="text-xs font-normal text-slate-500 ml-2">(Faqat ko'rish)</span>
                    </h1>
                </div>
                <div class="text-sm text-slate-500"><?php echo date('d.m.Y H:i'); ?></div>
            </header>

            <div class="p-4 lg:p-8">
                
                <!-- ==================== DASHBOARD ==================== -->
                <?php if ($page === 'dashboard'): ?>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="card rounded-xl p-5">
                            <div class="text-3xl font-bold text-white mb-1"><?php echo $stats['total_audits']; ?></div>
                            <div class="text-slate-500 text-sm">Jami Auditlar</div>
                        </div>
                        <div class="card rounded-xl p-5">
                            <div class="text-3xl font-bold text-amber-400 mb-1"><?php echo $stats['active_audits']; ?></div>
                            <div class="text-slate-500 text-sm">Jarayondagi</div>
                        </div>
                        <div class="card rounded-xl p-5">
                            <div class="text-3xl font-bold text-emerald-400 mb-1"><?php echo $stats['completed_audits']; ?></div>
                            <div class="text-slate-500 text-sm">Yakunlangan</div>
                        </div>
                        <div class="card rounded-xl p-5">
                            <div class="text-3xl font-bold text-red-400 mb-1"><?php echo $stats['total_nc']; ?></div>
                            <div class="text-slate-500 text-sm">Nomuvofiqliklar</div>
                        </div>
                    </div>
                    
                    <div class="grid lg:grid-cols-2 gap-6">
                        <div class="card rounded-xl p-6">
                            <h3 class="text-lg font-semibold text-white mb-4">So'nggi auditlar</h3>
                            <div class="space-y-3">
                                <?php foreach($recentAudits as $a): ?>
                                <div class="flex items-center justify-between p-3 bg-slate-800/30 rounded-lg">
                                    <div>
                                        <div class="font-mono text-cyan-400 text-sm"><?php echo $a['audit_code']; ?></div>
                                        <div class="text-white text-sm"><?php echo htmlspecialchars($a['title']); ?></div>
                                    </div>
                                    <span class="badge bg-<?php echo $a['status']=='completed'?'success':'warning'; ?>"><?php echo $a['status']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card rounded-xl p-6">
                            <h3 class="text-lg font-semibold text-white mb-4">NC Taqsimoti</h3>
                            <div class="space-y-3">
                                <?php foreach($ncBySeverity as $nc): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full" style="background:<?php echo $nc['color_code']; ?>"></div>
                                        <span class="text-slate-400"><?php echo $nc['name']; ?></span>
                                    </div>
                                    <span class="text-white font-bold"><?php echo $nc['count']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                <!-- ==================== AUDITS LIST ==================== -->
                <?php elseif ($page === 'audits'): ?>
                    <div class="card rounded-xl overflow-hidden">
                        <table class="w-full text-left">
                            <thead class="bg-slate-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-xs text-slate-500 uppercase">Kod</th>
                                    <th class="px-6 py-3 text-xs text-slate-500 uppercase">Sarlavha</th>
                                    <th class="px-6 py-3 text-xs text-slate-500 uppercase">Korxona</th>
                                    <th class="px-6 py-3 text-xs text-slate-500 uppercase">Holat</th>
                                    <th class="px-6 py-3 text-xs text-slate-500 uppercase text-right">Amal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach($auditsList as $a): ?>
                                <tr class="hover:bg-slate-800/20">
                                    <td class="px-6 py-4 font-mono text-cyan-400 text-sm"><?php echo $a['audit_code']; ?></td>
                                    <td class="px-6 py-4 text-white text-sm"><?php echo htmlspecialchars($a['title']); ?></td>
                                    <td class="px-6 py-4 text-slate-400 text-sm"><?php echo htmlspecialchars($a['site_name']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="badge bg-<?php echo $a['status']=='completed'?'success':($a['status']=='in_progress'?'warning':'info'); ?>">
                                            <?php echo $a['status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <?php if($a['status'] == 'completed'): ?>
                                        <a href="?page=report_detail&audit=<?php echo $a['id']; ?>" class="text-cyan-400 hover:underline text-sm">Hisobot</a>
                                        <?php else: ?>
                                        <span class="text-slate-600 text-sm">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- ==================== SECTIONS LIST ==================== -->
                <?php elseif ($page === 'sections'): ?>
                     <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($sectionsList as $s): ?>
                        <div class="card rounded-xl p-5 flex justify-between items-center">
                            <div>
                                <div class="font-mono text-cyan-400 text-sm mb-1"><?php echo $s['section_number']; ?></div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars($s['section_name']); ?></div>
                            </div>
                            <div class="bg-slate-800 px-3 py-1 rounded-full text-xs text-slate-400">
                                <?php echo $s['question_count']; ?> savol
                            </div>
                        </div>
                        <?php endforeach; ?>
                     </div>

                <!-- ==================== CHECKLISTS ==================== -->
                <?php elseif ($page === 'checklists'): ?>
                    <div class="mb-4 flex gap-2 flex-wrap">
                        <a href="?page=checklists" class="px-4 py-2 rounded-lg text-sm <?php echo !$sectionId?'bg-cyan-500/20 text-cyan-400':'bg-slate-800 text-slate-400'; ?>">Barchasi</a>
                        <?php foreach($sectionsFilter as $sf): ?>
                        <a href="?page=checklists&section=<?php echo $sf['id']; ?>" class="px-4 py-2 rounded-lg text-sm <?php echo $sectionId==$sf['id']?'bg-cyan-500/20 text-cyan-400':'bg-slate-800 text-slate-400'; ?>">
                            <?php echo $sf['section_number']; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="space-y-3">
                        <?php foreach($questionsList as $q): ?>
                        <div class="card rounded-lg p-4 flex items-start gap-4">
                            <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-xs font-bold flex-shrink-0">Q</div>
                            <div class="flex-1">
                                <p class="text-slate-300 text-sm mb-2"><?php echo htmlspecialchars($q['question_text']); ?></p>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-slate-500"><?php echo $q['section_name']; ?></span>
                                    <?php if($q['is_required']): ?>
                                    <span class="badge bg-danger text-xs">Majburiy</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                <!-- ==================== USERS ==================== -->
                <?php elseif ($page === 'users'): ?>
                    <div class="card rounded-xl overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-slate-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs text-slate-500 uppercase">Ism</th>
                                    <th class="px-6 py-3 text-left text-xs text-slate-500 uppercase">Rol</th>
                                    <th class="px-6 py-3 text-left text-xs text-slate-500 uppercase">Login</th>
                                    <th class="px-6 py-3 text-left text-xs text-slate-500 uppercase">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach($usersList as $u): ?>
                                <tr>
                                    <td class="px-6 py-4 text-white"><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td class="px-6 py-4 text-slate-400"><?php echo ucfirst($u['role']); ?></td>
                                    <td class="px-6 py-4 text-slate-400 font-mono text-sm"><?php echo $u['username']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="badge <?php echo $u['is_active']?'bg-success':'bg-danger'; ?>">
                                            <?php echo $u['is_active']?'Faol':'Nofaol'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- ==================== LOGS ==================== -->
                <?php elseif ($page === 'logs'): ?>
                     <div class="card rounded-xl overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-slate-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs text-slate-500 uppercase">Sana</th>
                                    <th class="px-6 py-3 text-left text-xs text-slate-500 uppercase">Foydalanuvchi</th>
                                    <th class="px-6 py-3 text-left text-xs text-slate-500 uppercase">Harakat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach($logsList as $l): ?>
                                <tr>
                                    <td class="px-6 py-4 text-slate-500 text-sm"><?php echo $l['created_at']; ?></td>
                                    <td class="px-6 py-4 text-white text-sm"><?php echo htmlspecialchars($l['user_name']); ?></td>
                                    <td class="px-6 py-4 text-slate-400 text-sm"><?php echo htmlspecialchars($l['action_type']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- ==================== REPORTS LIST ==================== -->
                <?php elseif ($page === 'reports'): ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($reportsList as $r): 
                            $grade = getGmpGrade((float)$r['percentage_score']);
                        ?>
                        <a href="?page=report_detail&audit=<?php echo $r['id']; ?>" class="card rounded-xl p-5 block hover:border-cyan-500/50 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <span class="font-mono text-cyan-400 text-sm"><?php echo $r['audit_code']; ?></span>
                                <span class="px-2 py-1 rounded text-xs font-bold" style="background:<?php echo $grade['bg']; ?>; color:<?php echo $grade['color']; ?>">
                                    <?php echo $grade['grade']; ?>
                                </span>
                            </div>
                            <h4 class="text-white font-medium mb-1"><?php echo htmlspecialchars($r['title']); ?></h4>
                            <p class="text-slate-500 text-xs"><?php echo htmlspecialchars($r['site_name']); ?></p>
                            <div class="mt-3 text-slate-400 text-sm font-mono">
                                <?php echo $r['percentage_score']; ?>%
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>

                <!-- ==================== REPORT DETAIL ==================== -->
                <?php elseif ($page === 'report_detail' && isset($report)): ?>
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 text-sm text-slate-500">
                            <a href="?page=reports" class="hover:text-white">Hisobotlar</a>
                            <span>/</span>
                            <span><?php echo $report['audit_code']; ?></span>
                        </div>

                        <div class="card rounded-xl p-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-bold text-white mb-1"><?php echo htmlspecialchars($report['title']); ?></h2>
                                    <div class="text-slate-400 text-sm"><?php echo htmlspecialchars($report['site_name']); ?></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-4xl font-bold" style="color:<?php echo $grade['color']; ?>"><?php echo $grade['grade']; ?></div>
                                    <div class="text-sm text-slate-500 mt-1"><?php echo $percentage; ?>% ball</div>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-xl p-6">
                            <h3 class="text-lg font-semibold text-white mb-4">Bo'limlar kesimi</h3>
                            <div class="space-y-2">
                                <?php foreach($sectionsReport as $sr): 
                                    $sp = $sr['max_score'] > 0 ? round(($sr['earned_score']/$sr['max_score'])*100,1) : 0;
                                    $spColor = $sp >= 80 ? 'bg-emerald-500' : ($sp >= 60 ? 'bg-amber-500' : 'bg-red-500');
                                ?>
                                <div class="flex items-center justify-between p-3 bg-slate-800/30 rounded-lg">
                                    <div>
                                        <div class="text-white text-sm"><?php echo $sr['section_name']; ?></div>
                                        <div class="text-xs text-slate-500"><?php echo $sr['section_number']; ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-white font-mono text-sm"><?php echo $sp; ?>%</div>
                                        <div class="w-24 h-1.5 bg-slate-700 rounded-full mt-1">
                                            <div class="h-full <?php echo $spColor; ?> rounded-full" style="width:<?php echo $sp; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if(!empty($ncsReport)): ?>
                        <div class="card rounded-xl p-6">
                            <h3 class="text-lg font-semibold text-white mb-4">Nomuvofiqliklar (<?php echo count($ncsReport); ?>)</h3>
                            <div class="space-y-2">
                                <?php foreach($ncsReport as $nc): ?>
                                <div class="p-3 rounded-lg bg-slate-800/30 border-l-2" style="border-color:<?php echo $nc['color_code']; ?>">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs font-bold" style="color:<?php echo $nc['color_code']; ?>"><?php echo $nc['severity_name']; ?></span>
                                        <span class="text-xs text-slate-500"><?php echo $nc['nc_code']; ?></span>
                                    </div>
                                    <p class="text-slate-300 text-sm mt-1"><?php echo htmlspecialchars($nc['description']); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="text-center text-slate-500 py-20">Sahifa topilmadi.</div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>