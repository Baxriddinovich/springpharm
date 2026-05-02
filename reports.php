<?php
date_default_timezone_set('Asia/Tashkent');
// reports.php - Hisobotlar (MUKAMMAL VERSIYA)
require_once 'db.php';
requireLogin();

$user = getCurrentUser();
$auditId = (int) ($_GET['audit'] ?? 0);
$viewMode = $_GET['view'] ?? 'report'; // report, summary, comparison
$error = '';

// ⭐ Role-based access: auditor o'z auditlarigina ko'radi
$canViewAll = in_array($user['role'], ['super_admin', 'bosh_auditor']);

// ⭐ GMP Gradlash funksiyasi
function getGmpGrade(float $percentage): array
{
    if ($percentage >= 90)
        return ['grade' => 'A', 'label' => 'A\'lo', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)', 'desc' => 'Yuqori darajada mos kelish'];
    if ($percentage >= 80)
        return ['grade' => 'B', 'label' => 'Yaxshi', 'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,0.15)', 'desc' => 'Mos kelish, kichik tuzatishlar kerak'];
    if ($percentage >= 60)
        return ['grade' => 'C', 'label' => 'Qoniqarli', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.15)', 'desc' => 'Qabul qilinadigan, muhim tuzatishlar talab etiladi'];
    return ['grade' => 'D', 'label' => 'Qoniqarsiz', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.15)', 'desc' => 'Qabul qilinmaydi, jiddiy chora-tadbirlar zarur'];
}
// Imzolar ro'yxatini olish
$signaturesStmt = $pdo->prepare("
    SELECT u.full_name, u.role, aus.signed_at
    FROM audit_signatures aus
    JOIN users u ON u.id = aus.user_id
    WHERE aus.audit_id = ?
    ORDER BY u.role = 'bosh_auditor' DESC, aus.signed_at
");
$signaturesStmt->execute([$auditId]);
$signatures = $signaturesStmt->fetchAll();

// Joriy user imzolaganmi?
$alreadySigned = false;
foreach ($signatures as $sig) {
    if ($sig['user_id'] === $user['id']) {
        $alreadySigned = true;
        break;
    }
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
        $error = "Sizda ushbu hisobotni ko'rish uchun ruxsat yo'q!";
        $auditId = 0;
    } else {
        // Log activity
        logActivity('report_viewed', "Audit #{$auditId} hisoboti ko'rildi", 'report');

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

            // ⭐ Nomuvofiqliklar (CAPA holati bilan)
            $ncs = $pdo->prepare("
                SELECT nc.*, st.name as severity_name, st.color_code, 
                       cq.question_text, gs.section_number, gs.section_name as section_name,
                       nc.status as capa_status,
                       (SELECT COUNT(*) FROM capa_actions ca WHERE ca.nc_id = nc.id) as action_count,
                       (SELECT COUNT(*) FROM capa_actions ca WHERE ca.nc_id = nc.id AND ca.status = 'completed') as completed_actions
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

            // ⭐ NC statistikasi
            $ncStats = [
                'total' => count($nonConformities),
                'critical' => count(array_filter($nonConformities, fn($nc) => $nc['severity_id'] == 3)),
                'major' => count(array_filter($nonConformities, fn($nc) => $nc['severity_id'] == 2)),
                'minor' => count(array_filter($nonConformities, fn($nc) => $nc['severity_id'] == 1)),
                'open' => count(array_filter($nonConformities, fn($nc) => $nc['capa_status'] === 'open')),
                'in_progress' => count(array_filter($nonConformities, fn($nc) => $nc['capa_status'] === 'in_progress')),
                'closed' => count(array_filter($nonConformities, fn($nc) => $nc['capa_status'] === 'closed')),
            ];

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

// ⭐ Umumiy statistika kartochkalari uchun
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
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0f1a;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
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

        /* Cards */
        .stat-card {
            background: linear-gradient(135deg, rgba(26, 35, 50, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.5);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: rgba(6, 182, 212, 0.3);
        }

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

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4);
        }

        /* Progress ring */
        .progress-ring {
            transform: rotate(-90deg);
        }

        /* Grade badge */
        .grade-badge {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1;
            text-shadow: 0 0 40px currentColor;
        }

        /* NC severity indicator */
        .nc-card {
            transition: all 0.2s ease;
        }

        .nc-card:hover {
            transform: translateX(4px);
        }

        /* Signature line */
        .signature-line {
            border-bottom: 2px solid #334155;
            min-width: 200px;
            display: inline-block;
        }

        /* Animations */
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

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
        }

        .animate-scale {
            animation: scaleIn 0.6s ease forwards;
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

        .delay-4 {
            animation-delay: 0.4s;
            opacity: 0;
        }

        /* Scrollbar */
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

        /* ⭐ Print Styles */
        @media print {

            .sidebar,
            .no-print,
            #overlay {
                display: none !important;
            }

            body {
                background: white !important;
                color: #1a1a1a !important;
                font-size: 11pt;
            }

            main {
                margin-left: 0 !important;
            }

            .stat-card {
                border: 1px solid #ddd !important;
                background: white !important;
                backdrop-filter: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .text-white,
            .text-slate-100,
            .text-slate-200,
            .text-slate-300 {
                color: #1a1a1a !important;
            }

            .text-slate-400,
            .text-slate-500,
            .text-slate-600 {
                color: #555 !important;
            }

            .text-cyan-400 {
                color: #0891b2 !important;
            }

            .text-emerald-400 {
                color: #059669 !important;
            }

            .text-red-400 {
                color: #dc2626 !important;
            }

            .text-amber-400 {
                color: #d97706 !important;
            }

            .bg-slate-800\/50,
            .bg-slate-800\/30 {
                background: #f5f5f5 !important;
            }

            .border-slate-700\/50,
            .border-slate-700\/30 {
                border-color: #ddd !important;
            }

            .grade-badge {
                text-shadow: none !important;
            }

            .progress-ring circle {
                stroke-width: 8 !important;
            }

            a {
                color: #0891b2 !important;
                text-decoration: underline;
            }
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
    <div
        class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between no-print">
        <div class="flex items-center gap-2">
            <div
                class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <span class="font-bold text-white text-sm">GMP Audit</span>
        </div>
        <button onclick="toggleSidebar()"
            class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors"
            aria-label="Menyu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden no-print"></div>

    <div class="flex min-h-screen pt-14 lg:pt-0">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 fixed h-full z-50 no-print" role="navigation"
            aria-label="Asosiy navigatsiya">
            <div class="p-6 h-full flex flex-col">
                <div class="flex items-center gap-3 mb-8">
                    <div
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-500 font-mono">v2.0 Pro</p>
                    </div>
                </div>

                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <a href="dashboard.php"
                        class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Bosh panel
                    </a>
                    <a href="audits.php"
                        class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Auditlar
                    </a>
                    <!-- <a href="non_conformities.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Nomuvofiqliklar
                    </a> -->
                    <a href="reports.php"
                        class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white"
                        aria-current="page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Hisobotlar
                    </a>

                    <div class="pt-4 mt-4 border-t border-slate-700/50">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>
                        <a href="sections.php"
                            class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Bo'limlar
                        </a>
                        <a href="checklists.php"
                            class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            Checklistlar
                        </a>
                        <a href="users.php"
                            class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Auditorlar
                        </a>
                        <a href="logs.php"
                            class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            Tizim Tarixi
                        </a>
                    </div>
                </nav>

                <div class="border-t border-slate-700/50 pt-4 mt-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center text-white font-semibold text-sm">
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
                        <a href="logout.php" class="text-slate-500 hover:text-red-400 transition-colors p-2"
                            aria-label="Chiqish">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
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
                                <a href="reports.php"
                                    class="flex items-center gap-1.5 text-slate-400 hover:text-white px-3 py-2 rounded-lg hover:bg-slate-800 transition-all text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 19l-7-7 7-7" />
                                    </svg>
                                    Orqaga
                                </a>
                                <button onclick="window.print()"
                                    class="flex items-center gap-2 bg-slate-700/50 hover:bg-slate-700 text-white px-4 py-2 rounded-lg transition-all text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    <span class="hidden sm:inline">Chop etish</span>
                                </button>

                                <!-- ⭐ IMZO TUGMASI -->
                                <?php if (in_array($user['role'], ['auditor', 'bosh_auditor', 'super_admin'])): ?>
                                    <?php if (!$alreadySigned): ?>
                                        <button onclick="openSignModal()" class="flex items-center gap-2 bg-emerald-500/15 hover:bg-emerald-500/25 
                       text-emerald-400 border border-emerald-500/20 px-4 py-2 rounded-lg text-sm transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                            <span class="hidden sm:inline">Imzo qo'yish</span>
                                        </button>
                                    <?php else: ?>
                                        <span class="flex items-center gap-1.5 text-emerald-400 text-sm px-3 py-2 
                     bg-emerald-500/10 rounded-lg border border-emerald-500/20">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span class="hidden sm:inline">Imzolangansiz</span>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <a href="generate_pdf.php?audit=<?php echo $auditId; ?>"
                                    class="flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span class="hidden sm:inline">PDF</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="p-4 lg:p-8">
                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 animate-in">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?php echo $error; ?></span>
                            <a href="reports.php" class="ml-auto text-sm hover:underline">← Orqaga</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$auditId || isset($error)): ?>
                    <!-- ==================== AUDIT TANLASH ==================== -->

                    <!-- ⭐ Umumiy statistika -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="text-2xl font-bold text-white"><?php echo $globalStats['total_completed']; ?></div>
                            <div class="text-slate-500 text-sm">Yakunlangan auditlar</div>
                        </div>

                        <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="text-2xl font-bold text-white"><?php echo $globalStats['avg_score']; ?>%</div>
                            <div class="text-slate-500 text-sm">O'rtacha ball</div>
                        </div>

                        <div class="stat-card rounded-2xl p-5 animate-in delay-3">
                            <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="text-2xl font-bold text-white"><?php echo $globalStats['total_nc']; ?></div>
                            <div class="text-slate-500 text-sm">Jami NC</div>
                        </div>

                        <div class="stat-card rounded-2xl p-5 animate-in delay-4">
                            <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </div>
                            <div class="text-2xl font-bold text-white"><?php echo $globalStats['grade_a']; ?></div>
                            <div class="text-slate-500 text-sm">A sinf auditlar</div>
                        </div>
                    </div>

                    <!-- Audit tanlash -->
                    <div class="stat-card rounded-2xl p-6 mb-6 animate-in delay-2">
                        <h2 class="text-lg font-semibold text-white mb-4">Hisobot ko'rish uchun audit tanlang</h2>
                        <select onchange="if(this.value) window.location.href='?audit='+this.value"
                            class="input-field w-full px-4 py-3 rounded-xl text-base">
                            <option value="">— Audit tanlang —</option>
                            <?php foreach ($audits as $a):
                                $aGrade = getGmpGrade((float) $a['percentage_score']);
                                ?>
                                <option value="<?php echo $a['id']; ?>">
                                    <?php echo htmlspecialchars($a['audit_code']); ?> —
                                    <?php echo htmlspecialchars($a['title']); ?>
                                    (<?php echo htmlspecialchars($a['site_name']); ?>)
                                    [<?php echo $aGrade['grade']; ?> — <?php echo $a['percentage_score']; ?>%]
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
                                            <th
                                                class="text-left py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Kod</th>
                                            <th
                                                class="text-left py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Sarlavha</th>
                                            <th
                                                class="text-left py-3 text-slate-400 text-xs font-medium uppercase tracking-wider hidden lg:table-cell">
                                                Korxona</th>
                                            <th
                                                class="text-center py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Sinf</th>
                                            <th
                                                class="text-center py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Ball</th>
                                            <th
                                                class="text-center py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                NC</th>
                                            <th
                                                class="text-left py-3 text-slate-400 text-xs font-medium uppercase tracking-wider hidden sm:table-cell">
                                                Sana</th>
                                            <th
                                                class="text-right py-3 text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Amal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-700/30">
                                        <?php foreach ($audits as $a):
                                            $aGrade = getGmpGrade((float) $a['percentage_score']);
                                            $aNC = $pdo->prepare("SELECT COUNT(*) FROM non_conformities WHERE audit_id = ?");
                                            $aNC->execute([$a['id']]);
                                            $aNCCount = $aNC->fetchColumn();
                                            ?>
                                            <tr class="hover:bg-slate-800/30 transition-colors">
                                                <td class="py-3 font-mono text-cyan-400 text-sm">
                                                    <?php echo htmlspecialchars($a['audit_code']); ?>
                                                </td>
                                                <td class="py-3 text-white text-sm"><?php echo htmlspecialchars($a['title']); ?>
                                                </td>
                                                <td class="py-3 text-slate-400 text-sm hidden lg:table-cell">
                                                    <?php echo htmlspecialchars($a['site_name']); ?>
                                                </td>
                                                <td class="py-3 text-center">
                                                    <span
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm font-bold"
                                                        style="background: <?php echo $aGrade['bg']; ?>; color: <?php echo $aGrade['color']; ?>">
                                                        <?php echo $aGrade['grade']; ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 text-center text-white font-mono text-sm">
                                                    <?php echo $a['percentage_score']; ?>%
                                                </td>
                                                <td class="py-3 text-center">
                                                    <?php if ($aNCCount > 0): ?>
                                                        <span class="text-red-400 font-semibold"><?php echo $aNCCount; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-emerald-400">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 text-slate-500 text-sm hidden sm:table-cell">
                                                    <?php echo date('d.m.Y', strtotime($a['end_date'] ?? $a['completed_at'] ?? $a['created_at'])); ?>
                                                </td>
                                                <td class="py-3 text-right">
                                                    <a href="?audit=<?php echo $a['id']; ?>"
                                                        class="text-cyan-400 hover:text-cyan-300 text-sm font-medium">
                                                        Hisobot →
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php elseif (empty($error)): ?>
                        <div class="stat-card rounded-2xl p-12 text-center animate-in">
                            <div class="w-20 h-20 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-6">
                                <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-white mb-2">Hisobotlar mavjud emas</h3>
                            <p class="text-slate-500 max-w-md mx-auto">Yakunlangan auditlar bo'lganda shu yerda hisobotlar
                                ko'rinadi.</p>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- ==================== HISOBOT ==================== -->
                    <div class="space-y-6">

                        <!-- ⭐ Report Header -->
                        <div class="stat-card rounded-2xl p-6 lg:p-8 animate-in">
                            <div class="grid lg:grid-cols-3 gap-8">
                                <!-- Left: Audit Info -->
                                <div class="lg:col-span-2">
                                    <div class="flex flex-wrap items-center gap-3 mb-3">
                                        <span
                                            class="font-mono text-cyan-400 text-lg font-bold"><?php echo htmlspecialchars($audit['audit_code']); ?></span>
                                        <span
                                            class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">Tugatilgan</span>
                                    </div>
                                    <h2 class="text-2xl lg:text-3xl font-bold text-white mb-4">
                                        <?php echo htmlspecialchars($audit['title']); ?>
                                    </h2>

                                    <div class="grid sm:grid-cols-2 gap-4 text-sm">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2 text-slate-400">
                                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                                                </svg>
                                                <span class="text-slate-500">Korxona:</span>
                                                <span
                                                    class="text-white font-medium"><?php echo htmlspecialchars($audit['site_name']); ?></span>
                                            </div>
                                            <?php if ($audit['address']): ?>
                                                <div class="flex items-start gap-2 text-slate-400">
                                                    <svg class="w-4 h-4 text-slate-500 mt-0.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    <span class="text-slate-500">Manzil:</span>
                                                    <span
                                                        class="text-white"><?php echo htmlspecialchars($audit['address']); ?><?php if ($audit['country'])
                                                               echo ', ' . htmlspecialchars($audit['country']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2 text-slate-400">
                                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span class="text-slate-500">Muddat:</span>
                                                <span
                                                    class="text-white"><?php echo date('d.m.Y', strtotime($audit['start_date'])); ?>
                                                    — <?php echo date('d.m.Y', strtotime($audit['end_date'])); ?></span>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-400">
                                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <span class="text-slate-500">Tashkilotchi:</span>
                                                <span
                                                    class="text-white"><?php echo htmlspecialchars($audit['creator_name']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Grade & Score -->
                                <div class="flex flex-col items-center justify-center">
                                    <div class="relative inline-flex items-center justify-center mb-4">
                                        <svg class="progress-ring w-36 h-36" viewBox="0 0 120 120">
                                            <circle cx="60" cy="60" r="50" fill="none" stroke="#1e293b" stroke-width="10" />
                                            <circle cx="60" cy="60" r="50" fill="none"
                                                stroke="<?php echo $gmpGrade['color']; ?>" stroke-width="10"
                                                stroke-dasharray="<?php echo ($percentage / 100) * 314.16; ?> 314.16"
                                                stroke-linecap="round" style="transition: stroke-dasharray 1s ease;" />
                                        </svg>
                                        <div class="absolute text-center">
                                            <div class="text-3xl font-bold text-white"><?php echo $percentage; ?>%</div>
                                            <div class="text-xs text-slate-500">Umumiy ball</div>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="grade-badge" style="color: <?php echo $gmpGrade['color']; ?>">
                                            <?php echo $gmpGrade['grade']; ?>
                                        </div>
                                        <div class="text-sm font-medium mt-1"
                                            style="color: <?php echo $gmpGrade['color']; ?>">
                                            <?php echo $gmpGrade['label']; ?>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1"><?php echo $gmpGrade['desc']; ?></div>
                                    </div>

                                    <!-- ⭐ Previous comparison -->
                                    <?php if ($prevAudit): ?>
                                        <div class="mt-4 text-center text-xs">
                                            <?php $diff = round($percentage - (float) $prevAudit['percentage_score'], 1); ?>
                                            <?php if ($diff > 0): ?>
                                                <span class="text-emerald-400">↑ +<?php echo $diff; ?>% dan oldingi</span>
                                            <?php elseif ($diff < 0): ?>
                                                <span class="text-red-400">↓ <?php echo $diff; ?>% dan oldingi</span>
                                            <?php else: ?>
                                                <span class="text-slate-500">→ O'zgarmadi</span>
                                            <?php endif; ?>
                                            <div class="text-slate-600 mt-0.5">
                                                <?php echo htmlspecialchars($prevAudit['audit_code']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ⭐ NC Summary Cards -->
                        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 animate-in delay-1">
                            <div class="stat-card rounded-xl p-4 text-center">
                                <div class="text-2xl font-bold text-white"><?php echo $ncStats['total']; ?></div>
                                <div class="text-xs text-slate-500">Jami NC</div>
                            </div>
                            <div class="stat-card rounded-xl p-4 text-center border-l-2 border-l-red-500">
                                <div class="text-2xl font-bold text-red-400"><?php echo $ncStats['critical']; ?></div>
                                <div class="text-xs text-slate-500">Kritik</div>
                            </div>
                            <div class="stat-card rounded-xl p-4 text-center border-l-2 border-l-amber-500">
                                <div class="text-2xl font-bold text-amber-400"><?php echo $ncStats['major']; ?></div>
                                <div class="text-xs text-slate-500">Asosiy</div>
                            </div>
                            <div class="stat-card rounded-xl p-4 text-center border-l-2 border-l-emerald-500">
                                <div class="text-2xl font-bold text-emerald-400"><?php echo $ncStats['minor']; ?></div>
                                <div class="text-xs text-slate-500">Kichik</div>
                            </div>
                            <div class="stat-card rounded-xl p-4 text-center">
                                <div class="text-2xl font-bold text-cyan-400">
                                    <?php echo $ncStats['closed']; ?>/<?php echo $ncStats['total']; ?>
                                </div>
                                <div class="text-xs text-slate-500">CAPA yopilgan</div>
                            </div>
                        </div>

                        <!-- ⭐ Audit Team -->
                        <?php if (!empty($teamMembers)): ?>
                            <div class="stat-card rounded-2xl p-6 animate-in delay-2">
                                <h3 class="text-lg font-semibold text-white mb-4">Audit jamoasi</h3>
                                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($teamMembers as $member): ?>
                                        <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-800/30">
                                            <div
                                                class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500/30 to-teal-500/30 flex items-center justify-center text-cyan-400 font-semibold text-sm">
                                                <?php echo strtoupper(mb_substr($member['full_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-white text-sm font-medium truncate">
                                                    <?php echo htmlspecialchars($member['full_name']); ?>
                                                </div>
                                                <div class="text-xs text-slate-500">
                                                    <?php echo ucfirst(str_replace('_', ' ', $member['role'])); ?>
                                                    <?php if ($member['position'])
                                                        echo ' · ' . htmlspecialchars($member['position']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Section Results -->
                        <div class="stat-card rounded-2xl p-6 animate-in delay-3">
                            <h3 class="text-lg font-semibold text-white mb-4">Bo'limlar bo'yicha natijalar</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-700/50">
                                            <th
                                                class="py-3 text-left text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Bo'lim</th>
                                            <th
                                                class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Jami</th>
                                            <th
                                                class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Ha</th>
                                            <th
                                                class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Yo'q</th>
                                            <th
                                                class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                N/A</th>
                                            <th
                                                class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Ball</th>
                                            <th
                                                class="py-3 text-center text-slate-400 text-xs font-medium uppercase tracking-wider">
                                                Foiz</th>
                                            <th class="py-3 text-slate-400 text-xs font-medium uppercase tracking-wider hidden lg:table-cell"
                                                style="width: 120px;">Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-700/30">
                                        <?php foreach ($sections as $sec):
                                            $secPercent = $sec['max_score'] > 0 ? round(($sec['earned_score'] / $sec['max_score']) * 100, 1) : 0;
                                            $secColor = $secPercent >= 80 ? '#10b981' : ($secPercent >= 60 ? '#f59e0b' : '#ef4444');
                                            ?>
                                            <tr class="hover:bg-slate-800/20 transition-colors">
                                                <td class="py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="w-7 h-7 rounded bg-slate-700/50 flex items-center justify-center text-xs font-mono font-bold text-slate-400">
                                                            <?php echo htmlspecialchars($sec['section_number']); ?>
                                                        </span>
                                                        <span
                                                            class="text-white"><?php echo htmlspecialchars($sec['section_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="py-3 text-center text-slate-400">
                                                    <?php echo $sec['total_questions']; ?>
                                                </td>
                                                <td class="py-3 text-center text-emerald-400 font-semibold">
                                                    <?php echo $sec['yes_count']; ?>
                                                </td>
                                                <td class="py-3 text-center text-red-400 font-semibold">
                                                    <?php echo $sec['no_count']; ?>
                                                </td>
                                                <td class="py-3 text-center text-slate-500"><?php echo $sec['na_count']; ?></td>
                                                <td class="py-3 text-center text-white font-mono">
                                                    <?php echo number_format($sec['earned_score'], 1); ?><span
                                                        class="text-slate-500">/<?php echo number_format($sec['max_score'], 1); ?></span>
                                                </td>
                                                <td class="py-3 text-center">
                                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold"
                                                        style="background: <?php echo $secColor; ?>20; color: <?php echo $secColor; ?>">
                                                        <?php echo $secPercent; ?>%
                                                    </span>
                                                </td>
                                                <td class="py-3 hidden lg:table-cell">
                                                    <div class="w-full h-2 bg-slate-700 rounded-full overflow-hidden">
                                                        <div class="h-full rounded-full transition-all"
                                                            style="width: <?php echo $secPercent; ?>%; background: <?php echo $secColor; ?>">
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <!-- Jami qator -->
                                        <tr class="bg-slate-800/30 font-semibold">
                                            <td class="py-3 text-white">JAMI</td>
                                            <td class="py-3 text-center text-white">
                                                <?php echo array_sum(array_column($sections, 'total_questions')); ?>
                                            </td>
                                            <td class="py-3 text-center text-emerald-400">
                                                <?php echo array_sum(array_column($sections, 'yes_count')); ?>
                                            </td>
                                            <td class="py-3 text-center text-red-400">
                                                <?php echo array_sum(array_column($sections, 'no_count')); ?>
                                            </td>
                                            <td class="py-3 text-center text-slate-500">
                                                <?php echo array_sum(array_column($sections, 'na_count')); ?>
                                            </td>
                                            <td class="py-3 text-center text-white font-mono">
                                                <?php echo number_format($totalScore, 1); ?><span
                                                    class="text-slate-500">/<?php echo number_format($maxScore, 1); ?></span>
                                            </td>
                                            <td class="py-3 text-center">
                                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold"
                                                    style="background: <?php echo $gmpGrade['color']; ?>20; color: <?php echo $gmpGrade['color']; ?>">
                                                    <?php echo $percentage; ?>%
                                                </span>
                                            </td>
                                            <td class="py-3 hidden lg:table-cell">
                                                <div class="w-full h-2 bg-slate-700 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full"
                                                        style="width: <?php echo $percentage; ?>%; background: <?php echo $gmpGrade['color']; ?>">
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ⭐ Non-Conformities -->
                        <div class="stat-card rounded-2xl p-6 animate-in delay-4">
                            <h3 class="text-lg font-semibold text-white mb-4">
                                Aniqlangan nomuvofiqliklar
                                <span class="ml-2 text-sm font-normal text-slate-500">(<?php echo $ncStats['total']; ?>
                                    ta)</span>
                            </h3>

                            <?php if (!empty($nonConformities)): ?>
                                <!-- CAPA progress -->
                                <?php if ($ncStats['total'] > 0): ?>
                                    <div class="mb-6 p-4 rounded-xl bg-slate-800/30">
                                        <div class="flex items-center justify-between text-sm mb-2">
                                            <span class="text-slate-400">CAPA bajarilishi</span>
                                            <span
                                                class="text-white font-semibold"><?php echo $ncStats['closed']; ?>/<?php echo $ncStats['total']; ?>
                                                (<?php echo $ncStats['total'] > 0 ? round(($ncStats['closed'] / $ncStats['total']) * 100) : 0; ?>%)</span>
                                        </div>
                                        <div class="w-full h-3 bg-slate-700 rounded-full overflow-hidden">
                                            <?php
                                            $capaPercent = $ncStats['total'] > 0 ? ($ncStats['closed'] / $ncStats['total']) * 100 : 0;
                                            $remaining = 100 - $capaPercent;
                                            $openPercent = $ncStats['total'] > 0 ? ($ncStats['open'] / $ncStats['total']) * 100 : 0;
                                            $inProgressPercent = $ncStats['total'] > 0 ? ($ncStats['in_progress'] / $ncStats['total']) * 100 : 0;
                                            ?>
                                            <div class="h-full flex">
                                                <div class="bg-emerald-500 transition-all"
                                                    style="width: <?php echo $capaPercent; ?>%"></div>
                                                <div class="bg-amber-500 transition-all"
                                                    style="width: <?php echo $inProgressPercent; ?>%"></div>
                                                <div class="bg-red-500/50 transition-all"
                                                    style="width: <?php echo $openPercent; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 mt-2 text-xs text-slate-500">
                                            <span class="flex items-center gap-1"><span
                                                    class="w-2 h-2 rounded-full bg-emerald-500"></span> Yopilgan</span>
                                            <span class="flex items-center gap-1"><span
                                                    class="w-2 h-2 rounded-full bg-amber-500"></span> Jarayonda</span>
                                            <span class="flex items-center gap-1"><span
                                                    class="w-2 h-2 rounded-full bg-red-500/50"></span> Ochiq</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="space-y-3">
                                    <?php foreach ($nonConformities as $nc):
                                        $capaStatusLabels = ['open' => 'Ochiq', 'in_progress' => 'Jarayonda', 'closed' => 'Yopilgan'];
                                        $capaStatusColors = ['open' => '#ef4444', 'in_progress' => '#f59e0b', 'closed' => '#10b981'];
                                        $capaStatusColor = $capaStatusColors[$nc['capa_status']] ?? '#94a3b8';
                                        ?>
                                        <div class="nc-card flex items-start gap-4 p-4 rounded-xl bg-slate-800/30 border-l-4"
                                            style="border-color: <?php echo $nc['color_code']; ?>">
                                            <div class="flex-shrink-0 mt-1">
                                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold"
                                                    style="background: <?php echo $nc['color_code']; ?>20; color: <?php echo $nc['color_code']; ?>">
                                                    <?php echo htmlspecialchars($nc['nc_code']); ?>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                                        style="background: <?php echo $nc['color_code']; ?>15; color: <?php echo $nc['color_code']; ?>">
                                                        <?php echo htmlspecialchars($nc['severity_name']); ?>
                                                    </span>
                                                    <span class="text-xs px-2 py-0.5 rounded-full"
                                                        style="background: <?php echo $capaStatusColor; ?>15; color: <?php echo $capaStatusColor; ?>">
                                                        <?php echo $capaStatusLabels[$nc['capa_status']] ?? $nc['capa_status']; ?>
                                                    </span>
                                                    <?php if ($nc['action_count'] > 0): ?>
                                                        <span class="text-xs text-slate-500">
                                                            <?php echo $nc['completed_actions']; ?>/<?php echo $nc['action_count']; ?>
                                                            ta choralar
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-slate-300 text-sm mb-1">
                                                    <?php echo htmlspecialchars($nc['description']); ?>
                                                </p>
                                                <p class="text-xs text-slate-500">
                                                    Bo'lim <?php echo htmlspecialchars($nc['section_number']); ?> ·
                                                    <?php echo htmlspecialchars(mb_substr($nc['question_text'], 0, 80)); ?>             <?php if (mb_strlen($nc['question_text']) > 80)
                                                                          echo '...'; ?>
                                                </p>
                                            </div>
                                            <a href="non_conformities.php?action=view&id=<?php echo $nc['id']; ?>"
                                                class="flex-shrink-0 text-slate-500 hover:text-cyan-400 transition-colors"
                                                title="Batafsil">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 5l7 7-7 7" />
                                                </svg>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div
                                        class="w-16 h-16 rounded-full bg-emerald-500/15 flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-emerald-400 font-semibold text-lg">Nomuvofiqliklar aniqlanmadi!</p>
                                    <p class="text-slate-500 text-sm mt-1">Korxona barcha talablarga mos keladi</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="stat-card rounded-2xl p-6 animate-in delay-4">
                            <h3 class="text-lg font-semibold text-white mb-6">Imzolar</h3>

                            <?php if (!empty($signatures)): ?>
                                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                                    <?php
                                    $sigGroups = [
                                        'auditor' => ['title' => 'Auditor(lar)', 'items' => []],
                                        'bosh_auditor' => ['title' => 'Bosh Auditor', 'items' => []],
                                        'super_admin' => ['title' => 'Tizim Administratori', 'items' => []]
                                    ];
                                    foreach ($signatures as $sig) {
                                        if (isset($sigGroups[$sig['role']])) {
                                            $sigGroups[$sig['role']]['items'][] = $sig;
                                        }
                                    }

                                    foreach (['auditor', 'bosh_auditor', 'super_admin'] as $roleKey):
                                        if (empty($sigGroups[$roleKey]['items']))
                                            continue;
                                        foreach ($sigGroups[$roleKey]['items'] as $sig):
                                            $roleName = $sigGroups[$roleKey]['title'];
                                            ?>
                                            <div class="stat-card p-4 rounded-xl border-l-4 border-emerald-500 animate-in">
                                                <p class="text-white font-bold text-sm mb-1">
                                                    <?php echo htmlspecialchars($sig['full_name']); ?>
                                                </p>
                                                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">
                                                    <?php echo $roleName; ?>
                                                </p>
                                                <div class="flex items-center gap-2 mt-3 text-emerald-400 text-xs">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Imzolangan:
                                                        <?php echo date('d.m.Y H:i', strtotime($sig['signed_at'])); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Imzolanmagan bo'sh joylar -->
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                                <div class="text-center">
                                    <div class="h-16 border-b-2 border-dashed border-slate-600 mb-3"></div>
                                    <p class="text-white text-sm font-medium">Bosh Auditor</p>
                                    <p class="text-xs text-slate-500">Sana: <?php echo date('d.m.Y'); ?></p>
                                </div>
                                <div class="text-center">
                                    <div class="h-16 border-b-2 border-dashed border-slate-600 mb-3"></div>
                                    <p class="text-white text-sm font-medium">Korxona Rahbari</p>
                                    <p class="text-xs text-slate-500">Sana: _____ / _____ / 20____</p>
                                </div>
                                <div class="text-center">
                                    <div class="h-16 border-b-2 border-dashed border-slate-600 mb-3"></div>
                                    <p class="text-white text-sm font-medium">Sifat bo'limi boshlig'i</p>
                                    <p class="text-xs text-slate-500">Sana: _____ / _____ / 20____</p>
                                </div>
                            </div>
                        </div>
                        <!-- Korxona rahbari -->
                        <div class="text-center">
                            <div
                                class="h-24 border-b-2 border-dashed border-slate-600 mb-3 flex items-end justify-center pb-2">
                                <span class="text-slate-600 text-sm italic">Imzo</span>
                            </div>
                            <div class="signature-line mx-auto"></div>
                            <p class="text-white text-sm font-medium mt-2">Korxona Rahbari</p>
                            <p class="text-xs text-slate-500">F.I.O.:
                                <?php echo $audit['director_name'] ? htmlspecialchars($audit['director_name']) : '_________________________'; ?>
                            </p>
                            <p class="text-xs text-slate-500">Sana: _____ / _____ / 20____</p>
                        </div>

                        <!-- Sifat bo'limi -->
                        <div class="text-center">
                            <div
                                class="h-24 border-b-2 border-dashed border-slate-600 mb-3 flex items-end justify-center pb-2">
                                <span class="text-slate-600 text-sm italic">Imzo</span>
                            </div>
                            <div class="signature-line mx-auto"></div>
                            <p class="text-white text-sm font-medium mt-2">Sifat bo'limi boshlig'i</p>
                            <p class="text-xs text-slate-500">F.I.O.: _________________________</p>
                            <p class="text-xs text-slate-500">Sana: _____ / _____ / 20____</p>
                        </div>
                    </div>
                </div>

                <!-- ⭐ Conclusion -->
                <div class="stat-card rounded-2xl p-6 animate-in delay-4">
                    <h3 class="text-lg font-semibold text-white mb-4">Xulosa va Tavsiyalar</h3>
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-sm font-medium text-slate-300 mb-2">Xulosa:</h4>
                            <p class="text-sm text-slate-400">
                                <?php echo $percentage >= 80
                                    ? 'Korxona GMP talablariga asosan mos kelmoqda. Aniqlangan kamchiliklar tizimli xarakterga ega emas va CAPA rejasiga muvofiq bartaraf etilmoqda.'
                                    : 'Korxona GMP talablariga to\'liq mos kelmayapti. Jiddiy nomuvofiqliklar aniqlangan bo\'lib, ularni bartaraf etish bo\'yicha shoshilinch choralar ko\'rilishi talab etiladi.'; ?>
                            </p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-slate-300 mb-2">Tavsiyalar:</h4>
                            <ul class="text-sm text-slate-400 space-y-1 list-disc list-inside">
                                <?php if ($ncStats['critical'] > 0): ?>
                                    <li>Kritik nomuvofiqliklarni <strong class="text-red-400">3 kun</strong> ichida bartaraf
                                        etish</li>
                                <?php endif; ?>
                                <?php if ($ncStats['major'] > 0): ?>
                                    <li>Asosiy nomuvofiqliklar bo\'yicha CAPA rejasini <strong class="text-amber-400">7
                                            kun</strong> ichida tasdiqlash</li>
                                <?php endif; ?>
                                <li>CAPA rejasi bajarilishini muntazam monitoring qilish (har hafta)</li>
                                <?php if ($percentage < 80): ?>
                                    <li>Keyingi monitoring auditini <strong class="text-cyan-400">30 kun</strong> ichida
                                        o\'tkazish</li>
                                <?php else: ?>
                                    <li>Keyingi rejalashtirilgan auditga qadar CAPA rejasini to\'liq bajarish</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-800/30 text-xs text-slate-500">
                            <strong>Hisobot tayyorlangan sana:</strong> <?php echo date('d.m.Y H:i'); ?><br>
                            <strong>Tizimda ro\'yxatdan o\'tkazilgan:</strong>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </div>
                    </div>
                </div>

        </div>
    <?php endif; ?>
    </div>
    </main>
    </div>

    <script>
        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        // Close sidebar on nav click (mobile)
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });

        // Keyboard shortcut: Ctrl+P for print when viewing report
        <?php if ($auditId && isset($audit)): ?>
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        <?php endif; ?>
    </script>
    <!-- Imzo Modal -->
    <div id="signModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeSignModal()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="relative w-full max-w-sm rounded-2xl p-6" style="background: linear-gradient(135deg, #1a2332 0%, #111827 100%); 
                    border: 1px solid rgba(51,65,85,0.5);">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-white">Auditni Imzolash</h3>
                    <button onclick="closeSignModal()" class="text-slate-400 hover:text-white text-2xl">&times;</button>
                </div>

                <div class="mb-4 p-3 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-300 text-sm">
                    Imzolash uchun parolingizni kiriting
                </div>

                <div>
                    <label class="text-slate-300 text-sm block mb-1">Parol</label>
                    <input type="password" id="signPassword" class="w-full p-3 rounded-xl text-white"
                        style="background: rgba(15,23,42,0.8); border: 1px solid #334155;" placeholder="Parolingiz...">
                </div>

                <div id="signMessage" class="mt-3 hidden p-3 rounded-xl text-sm"></div>

                <div class="flex gap-3 mt-6">
                    <button onclick="closeSignModal()"
                        class="flex-1 py-2 rounded-xl border border-slate-600 text-slate-300">
                        Bekor
                    </button>
                    <button onclick="submitSign()" class="flex-1 py-2 rounded-xl text-white font-semibold"
                        style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                        Imzolash
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openSignModal() {
            document.getElementById('signPassword').value = '';
            document.getElementById('signMessage').classList.add('hidden');
            document.getElementById('signModal').classList.remove('hidden');
            setTimeout(() => document.getElementById('signPassword').focus(), 100);
        }

        function closeSignModal() {
            document.getElementById('signModal').classList.add('hidden');
        }

        function submitSign() {
            const password = document.getElementById('signPassword').value;
            if (!password) return alert('Parolni kiriting!');

            const formData = new FormData();
            formData.append('audit_id', <?php echo $auditId; ?>);
            formData.append('password', password);

            fetch('sign_audit.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    const msgEl = document.getElementById('signMessage');
                    msgEl.classList.remove('hidden');
                    if (data.success) {
                        msgEl.className = 'mt-3 p-3 rounded-xl text-sm bg-emerald-500/10 border border-emerald-500/20 text-emerald-300';
                        msgEl.textContent = '✓ ' + data.message;
                        setTimeout(() => {
                            closeSignModal();
                            location.reload();
                        }, 1500);
                    } else {
                        msgEl.className = 'mt-3 p-3 rounded-xl text-sm bg-red-500/10 border border-red-500/20 text-red-300';
                        msgEl.textContent = '✗ ' + data.message;
                    }
                });
        }
    </script>
</body>

</html>