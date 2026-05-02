<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// reports.php - Hisobotlar (Bosh Auditor uchun moslashtirilgan)
require_once '../db.php';
requireLogin();

 $user = getCurrentUser();

// Dashboard linkini aniqlash (Bosh auditor uchun alohida)
 $dashboardLink = ($user['role'] === 'bosh_auditor') ? 'index.php' : 'a.php';

 $auditId = (int)($_GET['audit'] ?? 0);
 $viewMode = $_GET['view'] ?? 'report'; 
 $error = '';

// ⭐ Role-based access: Bosh auditor ham barchasini ko'radi
 $canViewAll = in_array($user['role'], ['super_admin', 'bosh_auditor', 'admin']);

// ⭐ GMP Gradlash funksiyasi
function getGmpGrade(float $percentage): array {
    if ($percentage >= 90) return ['grade' => 'A', 'label' => 'A\'lo', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)', 'desc' => 'Yuqori darajada mos kelish'];
    if ($percentage >= 80) return ['grade' => 'B', 'label' => 'Yaxshi', 'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,0.15)', 'desc' => 'Mos kelish, kichik tuzatishlar kerak'];
    if ($percentage >= 60) return ['grade' => 'C', 'label' => 'Qoniqarli', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.15)', 'desc' => 'Qabul qilinadigan, muhim tuzatishlar talab etiladi'];
    return ['grade' => 'D', 'label' => 'Qoniqarsiz', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.15)', 'desc' => 'Qabul qilinmaydi, jiddiy chora-tadbirlar zarur'];
}

// ⭐ Hisobot ma'lumotlarini olish
if ($auditId) {
    // Access tekshirish
    $accessCheck = $pdo->prepare("
        SELECT a.id FROM audits a
        LEFT JOIN audit_assignments aa ON aa.audit_id = a.id AND aa.auditor_id = ?
        WHERE a.id = ? AND (a.created_by = ? OR aa.id IS NOT NULL OR ? = 1)
    ");
    $accessCheck->execute([$user['id'], $auditId, $user['id'], $canViewAll ? 1 : 0]);
    
    if (!$accessCheck->fetch()) {
        $error = "Bu hisobotni ko'rish uchun ruxsat yo'q!";
        $auditId = 0;
    } else {
        // Audit ma'lumotlari
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as site_name, s.address, s.country, s.phone, s.director_name,
                   u.full_name as creator_name
            FROM audits a
            JOIN sites s ON a.site_id = s.id
            JOIN users u ON a.created_by = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$auditId]);
        $audit = $stmt->fetch();
        
        if (!$audit) {
            $error = "Audit topilmadi!";
            $auditId = 0;
        } else {
            // Audit jamoasi
            $teamStmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.full_name, u.role, u.phone, u.position,
                       GROUP_CONCAT(DISTINCT gs.section_name SEPARATOR ', ') as assigned_sections
                FROM audit_assignments aa
                JOIN users u ON aa.auditor_id = u.id
                LEFT JOIN gmp_sections gs ON gs.id = aa.section_id
                WHERE aa.audit_id = ?
                GROUP BY u.id
                ORDER BY u.role DESC
            ");
            $teamStmt->execute([$auditId]);
            $teamMembers = $teamStmt->fetchAll();
            
            // Bo'limlar bo'yicha natijalar
            $sectionResults = $pdo->prepare("
                SELECT 
                    gs.id, gs.section_number, gs.section_name,
                    COUNT(cq.id) as total_questions,
                    SUM(CASE WHEN aa.answer = 'ha' THEN 1 ELSE 0 END) as yes_count,
                    SUM(CASE WHEN aa.answer = 'yoq' THEN 1 ELSE 0 END) as no_count,
                    SUM(CASE WHEN aa.answer = 'na' OR aa.answer IS NULL THEN 1 ELSE 0 END) as na_count,
                    COALESCE(SUM(aa.score), 0) as earned_score,
                    SUM(cq.score) as max_score
                FROM gmp_sections gs
                LEFT JOIN checklist_questions cq ON cq.section_id = gs.id AND cq.is_active = 1
                LEFT JOIN audit_answers aa ON aa.question_id = cq.id AND aa.audit_id = ?
                GROUP BY gs.id
                ORDER BY gs.sort_order
            ");
            $sectionResults->execute([$auditId]);
            $sections = $sectionResults->fetchAll();
            
            // Nomuvofiqliklar
            $ncs = $pdo->prepare("
                SELECT nc.*, st.name as severity_name, st.color_code, 
                       cq.question_text, gs.section_number, gs.section_name as section_name,
                       nc.status as capa_status
                FROM non_conformities nc
                JOIN severity_types st ON nc.severity_id = st.id
                JOIN checklist_questions cq ON nc.question_id = cq.id
                JOIN gmp_sections gs ON cq.section_id = gs.id
                WHERE nc.audit_id = ?
                ORDER BY st.id DESC, nc.nc_number
            ");
            $ncs->execute([$auditId]);
            $nonConformities = $ncs->fetchAll();
            
            // Umumiy natijalar
            $totalScore = array_sum(array_column($sections, 'earned_score'));
            $maxScore = array_sum(array_column($sections, 'max_score'));
            $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;
            $gmpGrade = getGmpGrade($percentage);
            
            // NC statistikasi
            $ncStats = [
                'total' => count($nonConformities),
                'critical' => count(array_filter($nonConformities, fn($nc) => $nc['severity_id'] == 3)),
                'major' => count(array_filter($nonConformities, fn($nc) => $nc['severity_id'] == 2)),
                'minor' => count(array_filter($nonConformities, fn($nc) => $nc['severity_id'] == 1)),
                'open' => count(array_filter($nonConformities, fn($nc) => $nc['capa_status'] === 'open')),
                'in_progress' => count(array_filter($nonConformities, fn($nc) => $nc['capa_status'] === 'in_progress')),
                'closed' => count(array_filter($nonConformities, fn($nc) => $nc['capa_status'] === 'closed')),
            ];
            
            // Avvalgi audit
            $prevAuditStmt = $pdo->prepare("
                SELECT id, audit_code, percentage_score 
                FROM audits 
                WHERE site_id = ? AND status = 'completed' AND id < ? 
                ORDER BY id DESC LIMIT 1
            ");
            $prevAuditStmt->execute([$audit['site_id'], $auditId]);
            $prevAudit = $prevAuditStmt->fetch();
        }
    }
}

// Auditlar ro'yxati
if ($canViewAll) {
    $auditsStmt = $pdo->query("
        SELECT a.*, s.name as site_name 
        FROM audits a 
        JOIN sites s ON a.site_id = s.id 
        WHERE a.status = 'completed'
        ORDER BY a.end_date IS NULL, a.end_date DESC
    ");
} else {
    $auditsStmt = $pdo->prepare("
        SELECT a.*, s.name as site_name 
        FROM audits a 
        JOIN sites s ON a.site_id = s.id 
        JOIN audit_assignments aa ON aa.audit_id = a.id
        WHERE a.status = 'completed' AND aa.auditor_id = ?
        GROUP BY a.id
        ORDER BY a.completed_at DESC NULLS LAST, a.end_date DESC
    ");
    $auditsStmt->execute([$user['id']]);
}
 $audits = $auditsStmt->fetchAll();

// Umumiy statistika
 $globalStats = [
    'total_completed' => $pdo->query("SELECT COUNT(*) FROM audits WHERE status = 'completed'")->fetchColumn(),
    'avg_score' => $pdo->query("SELECT ROUND(AVG(percentage_score), 1) FROM audits WHERE status = 'completed' AND percentage_score > 0")->fetchColumn() ?: 0,
    'total_nc' => $pdo->query("SELECT COUNT(*) FROM non_conformities nc JOIN audits a ON nc.audit_id = a.id WHERE a.status = 'completed'")->fetchColumn(),
    'grade_a' => $pdo->query("SELECT COUNT(*) FROM audits WHERE status = 'completed' AND percentage_score >= 90")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hisobotlar - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --bg-secondary: #111827; --accent-cyan: #06b6d4; }
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
        
        .progress-ring { transform: rotate(-90deg); }
        .grade-badge { font-size: 4rem; font-weight: 800; line-height: 1; text-shadow: 0 0 40px currentColor; }
        .nc-card { transition: all 0.2s ease; }
        .nc-card:hover { transform: translateX(4px); }
        .signature-line { border-bottom: 2px solid #334155; min-width: 200px; display: inline-block; }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
        .animate-in { animation: fadeInUp 0.5s ease forwards; }
        .animate-scale { animation: scaleIn 0.6s ease forwards; }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
        .delay-4 { animation-delay: 0.4s; opacity: 0; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        
        @media print {
            .sidebar, .no-print, #overlay { display: none !important; }
            body { background: white !important; color: #1a1a1a !important; }
            main { margin-left: 0 !important; }
            .stat-card { border: 1px solid #ddd !important; background: white !important; backdrop-filter: none !important; }
            .text-white, .text-slate-100, .text-slate-200, .text-slate-300 { color: #1a1a1a !important; }
            .text-slate-400, .text-slate-500, .text-slate-600 { color: #555 !important; }
            .text-cyan-400 { color: #0891b2 !important; }
            .bg-slate-800\/50, .bg-slate-800\/30 { background: #f5f5f5 !important; }
            .border-slate-700\/50 { border-color: #ddd !important; }
            .grade-badge { text-shadow: none !important; }
        }
        
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } }
    </style>
</head>
<body class="min-h-screen text-slate-100">
    
    <!-- Mobile Header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between no-print">
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
    
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden no-print"></div>

    <div class="flex min-h-screen pt-14 lg:pt-0">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 fixed h-full z-50 no-print" role="navigation">
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
                    <a href="reportss.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Hisobotlar
                    </a>
                    
                    <div class="pt-4 mt-4 border-t border-slate-700/50">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>
                        <a href="sectionss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Bo'limlar
                        </a>
                        <a href="checklistss.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
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
            <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50 no-print">
                <div class="px-4 lg:px-8 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-xl lg:text-2xl font-bold text-white">
                                <?php echo $auditId && isset($audit) ? 'Audit Hisoboti' : 'Hisobotlar'; ?>
                            </h1>
                            <p class="text-slate-500 text-sm">
                                <?php echo $auditId && isset($audit) ? htmlspecialchars($audit['audit_code']) : 'Audit natijalari va analitikalar'; ?>
                            </p>
                        </div>
                        
                        <?php if ($auditId && isset($audit)): ?>
                        <div class="flex items-center gap-2">
                            <a href="reports.php" class="flex items-center gap-1.5 text-slate-400 hover:text-white px-3 py-2 rounded-lg hover:bg-slate-800 transition-all text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                Orqaga
                            </a>
                            <button onclick="window.print()" class="flex items-center gap-2 bg-slate-700/50 hover:bg-slate-700 text-white px-4 py-2 rounded-lg transition-all text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                <span class="hidden sm:inline">Chop etish</span>
                            </button>
                            <a href="generate_pdf.php?audit=<?php echo $auditId; ?>" class="flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span class="hidden sm:inline">PDF</span>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            
            <div class="p-4 lg:p-8">
                <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 animate-in">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><?php echo $error; ?></span>
                        <a href="reports.php" class="ml-auto text-sm hover:underline">← Orqaga</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!$auditId || isset($error)): ?>
                <!-- AUDIT TANLASH -->
                
                <!-- Umumiy statistika -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-white"><?php echo $globalStats['total_completed']; ?></div>
                        <div class="text-slate-500 text-sm">Yakunlangan auditlar</div>
                    </div>
                    
                    <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-white"><?php echo $globalStats['avg_score']; ?>%</div>
                        <div class="text-slate-500 text-sm">O'rtacha ball</div>
                    </div>
                    
                    <div class="stat-card rounded-2xl p-5 animate-in delay-3">
                        <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-white"><?php echo $globalStats['total_nc']; ?></div>
                        <div class="text-slate-500 text-sm">Jami NC</div>
                    </div>
                    
                    <div class="stat-card rounded-2xl p-5 animate-in delay-4">
                        <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </div>
                        <div class="text-2xl font-bold text-white"><?php echo $globalStats['grade_a']; ?></div>
                        <div class="text-slate-500 text-sm">A sinf auditlar</div>
                    </div>
                </div>
                
                <!-- Audit tanlash -->
                <div class="stat-card rounded-2xl p-6 mb-6 animate-in delay-2">
                    <h2 class="text-lg font-semibold text-white mb-4">Hisobot ko'rish uchun audit tanlang</h2>
                    <select onchange="if(this.value) window.location.href='?audit='+this.value" class="input-field w-full px-4 py-3 rounded-xl text-base">
                        <option value="">— Audit tanlang —</option>
                        <?php foreach ($audits as $a): $aGrade = getGmpGrade((float)$a['percentage_score']); ?>
                        <option value="<?php echo $a['id']; ?>">
                            <?php echo htmlspecialchars($a['audit_code']); ?> — <?php echo htmlspecialchars($a['title']); ?> (<?php echo htmlspecialchars($a['site_name']); ?>) [<?php echo $aGrade['grade']; ?> — <?php echo $a['percentage_score']; ?>%]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Yakunlangan auditlar jadvali -->
                <?php if (!empty($audits)): ?>
                <div class="stat-card rounded-2xl p-6 animate-in delay-3">
                    <h3 class="text-lg font-semibold text-white mb-4">Yakunlangan auditlar</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-slate-700/50">
                                    <th class="text-left py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">Kod</th>
                                    <th class="text-left py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">Sarlavha</th>
                                    <th class="text-left py-3 text-slate-400 text-xs font-medium uppercase tracking-wider hidden lg:table-cell">Korxona</th>
                                    <th class="text-center py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">Sinf</th>
                                    <th class="text-center py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">Ball</th>
                                    <th class="text-right py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">Amal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach ($audits as $a): $aGrade = getGmpGrade((float)$a['percentage_score']); ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3 font-mono text-cyan-400 text-sm"><?php echo htmlspecialchars($a['audit_code']); ?></td>
                                    <td class="py-3 text-white text-sm"><?php echo htmlspecialchars($a['title']); ?></td>
                                    <td class="py-3 text-slate-400 text-sm hidden lg:table-cell"><?php echo htmlspecialchars($a['site_name']); ?></td>
                                    <td class="py-3 text-center">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm font-bold" style="background: <?php echo $aGrade['bg']; ?>; color: <?php echo $aGrade['color']; ?>"><?php echo $aGrade['grade']; ?></span>
                                    </td>
                                    <td class="py-3 text-center text-white font-mono text-sm"><?php echo $a['percentage_score']; ?>%</td>
                                    <td class="py-3 text-right">
                                        <a href="?audit=<?php echo $a['id']; ?>" class="text-cyan-400 hover:text-cyan-300 text-sm font-medium">Hisobot →</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <!-- HISOBOT KONTENTI -->
                <div class="space-y-6">
                    
                    <!-- Report Header -->
                    <div class="stat-card rounded-2xl p-6 lg:p-8 animate-in">
                        <div class="grid lg:grid-cols-3 gap-8">
                            <div class="lg:col-span-2">
                                <div class="flex flex-wrap items-center gap-3 mb-3">
                                    <span class="font-mono text-cyan-400 text-lg font-bold"><?php echo htmlspecialchars($audit['audit_code']); ?></span>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">Tugatilgan</span>
                                </div>
                                <h2 class="text-2xl lg:text-3xl font-bold text-white mb-4"><?php echo htmlspecialchars($audit['title']); ?></h2>
                                
                                <div class="grid sm:grid-cols-2 gap-4 text-sm">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2 text-slate-400">
                                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                                            <span class="text-slate-500">Korxona:</span>
                                            <span class="text-white font-medium"><?php echo htmlspecialchars($audit['site_name']); ?></span>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2 text-slate-400">
                                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span class="text-slate-500">Sana:</span>
                                            <span class="text-white"><?php echo date('d.m.Y', strtotime($audit['start_date'])); ?> — <?php echo date('d.m.Y', strtotime($audit['end_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right: Grade -->
                            <div class="flex flex-col items-center justify-center">
                                <div class="relative inline-flex items-center justify-center mb-4">
                                    <svg class="progress-ring w-36 h-36" viewBox="0 0 120 120">
                                        <circle cx="60" cy="60" r="50" fill="none" stroke="#1e293b" stroke-width="10"/>
                                        <circle cx="60" cy="60" r="50" fill="none" stroke="<?php echo $gmpGrade['color']; ?>" stroke-width="10" stroke-dasharray="<?php echo ($percentage / 100) * 314.16; ?> 314.16" stroke-linecap="round" style="transition: stroke-dasharray 1s ease;"/>
                                    </svg>
                                    <div class="absolute text-center">
                                        <div class="text-3xl font-bold text-white"><?php echo $percentage; ?>%</div>
                                        <div class="text-xs text-slate-500">Umumiy ball</div>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="grade-badge" style="color: <?php echo $gmpGrade['color']; ?>"><?php echo $gmpGrade['grade']; ?></div>
                                    <div class="text-sm font-medium mt-1" style="color: <?php echo $gmpGrade['color']; ?>"><?php echo $gmpGrade['label']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- NC Summary -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 animate-in delay-1">
                        <div class="stat-card rounded-xl p-4 text-center"><div class="text-2xl font-bold text-white"><?php echo $ncStats['total']; ?></div><div class="text-xs text-slate-500">Jami NC</div></div>
                        <div class="stat-card rounded-xl p-4 text-center border-l-2 border-l-red-500"><div class="text-2xl font-bold text-red-400"><?php echo $ncStats['critical']; ?></div><div class="text-xs text-slate-500">Kritik</div></div>
                        <div class="stat-card rounded-xl p-4 text-center border-l-2 border-l-amber-500"><div class="text-2xl font-bold text-amber-400"><?php echo $ncStats['major']; ?></div><div class="text-xs text-slate-500">Asosiy</div></div>
                        <div class="stat-card rounded-xl p-4 text-center border-l-2 border-l-emerald-500"><div class="text-2xl font-bold text-emerald-400"><?php echo $ncStats['minor']; ?></div><div class="text-xs text-slate-500">Kichik</div></div>
                        <div class="stat-card rounded-xl p-4 text-center"><div class="text-2xl font-bold text-cyan-400"><?php echo $ncStats['closed']; ?>/<?php echo $ncStats['total']; ?></div><div class="text-xs text-slate-500">CAPA yopilgan</div></div>
                    </div>
                    
                    <!-- Section Results Table -->
                    <div class="stat-card rounded-2xl p-6 animate-in delay-3">
                        <h3 class="text-lg font-semibold text-white mb-4">Bo'limlar bo'yicha natijalar</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-700/50">
                                        <th class="py-3 text-left text-slate-400 text-xs font-medium uppercase tracking-wider">Bo'lim</th>
                                        <th class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">Ball</th>
                                        <th class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">Foiz</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700/30">
                                    <?php foreach ($sections as $sec): $secPercent = $sec['max_score'] > 0 ? round(($sec['earned_score'] / $sec['max_score']) * 100, 1) : 0; $secColor = $secPercent >= 80 ? '#10b981' : ($secPercent >= 60 ? '#f59e0b' : '#ef4444'); ?>
                                    <tr class="hover:bg-slate-800/20 transition-colors">
                                        <td class="py-3"><span class="text-white"><?php echo htmlspecialchars($sec['section_name']); ?></span></td>
                                        <td class="py-3 text-center text-white font-mono"><?php echo number_format($sec['earned_score'], 1); ?><span class="text-slate-500">/<?php echo number_format($sec['max_score'], 1); ?></span></td>
                                        <td class="py-3 text-center"><span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold" style="background: <?php echo $secColor; ?>20; color: <?php echo $secColor; ?>"><?php echo $secPercent; ?>%</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Signatures -->
                    <div class="stat-card rounded-2xl p-6 animate-in delay-4">
                        <h3 class="text-lg font-semibold text-white mb-6">Imzolar</h3>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                            <div class="text-center">
                                <div class="h-16 border-b-2 border-dashed border-slate-600 mb-3"></div>
                                <div class="signature-line mx-auto"></div>
                                <p class="text-white text-sm font-medium mt-2">Bosh Auditor</p>
                            </div>
                            <div class="text-center">
                                <div class="h-16 border-b-2 border-dashed border-slate-600 mb-3"></div>
                                <div class="signature-line mx-auto"></div>
                                <p class="text-white text-sm font-medium mt-2">Korxona Rahbari</p>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <?php endif; ?>
            </div>
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