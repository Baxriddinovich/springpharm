<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Tashkent');



require_once 'db.php';
requireLogin();

$user = getCurrentUser();

$permissions = [
    'super_admin' => ['create' => true, 'edit' => true, 'delete' => true, 'view_all' => true],
    'bosh_auditor' => ['create' => true, 'edit' => true, 'delete' => true, 'view_all' => true],
    'auditor' => ['create' => false, 'edit' => false, 'delete' => false, 'view_all' => false],
];
$perm = $permissions[$user['role']] ?? $permissions['auditor'];

$message = '';
$messageType = 'success';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function verifyCsrf(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

// function buildFilterUrl($status) {
//     $params = $_GET;
//     if ($status === 'all') { unset($params['status']); } else { $params['status'] = $status; }
//     unset($params['page']);
//     return 'audits.php?' . http_build_query($params);
// }

// function buildPageUrl($page) {
//     $params = $_GET;
//     $params['page'] = $page;
//     return 'audits.php?' . http_build_query($params);
// }

function redirect($url)
{
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    } else {
        echo "<script>window.top.location.href='" . $url . "';</script>";
        exit;
    }
}

// ----------------- POST LOGIC -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf()) {
        $message = "Xavfsizlik xatosi!";
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];

        // 1. AUDIT YARATISH
        if ($action === 'create' && $perm['create']) {
            $siteId = (int) ($_POST['site_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $startDate = $_POST['start_date'] ?? '';
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $assignments = $_POST['assignments'] ?? [];

            if ($siteId > 0 && $title !== '' && $startDate !== '' && !empty(array_filter($assignments, fn($v) => (int) $v > 0))) {

                try {
                    $pdo->beginTransaction();

                    $year = date('Y');
                    $maxStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(audit_code, '-', -1) AS UNSIGNED)) FROM audits WHERE audit_code LIKE ?");
                    $maxStmt->execute(["AUD-$year-%"]);
                    $maxNum = (int) $maxStmt->fetchColumn();
                    $nextNum = $maxNum + 1;
                    $auditCode = sprintf("AUD-%s-%04d", $year, $nextNum);

                    $stmt = $pdo->prepare("INSERT INTO audits (audit_code, site_id, title, start_date, end_date, created_by, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'draft')");
                    $stmt->execute([$auditCode, $siteId, $title, $startDate, $endDate, $user['id']]);

                    $auditId = $pdo->lastInsertId();

                    $assignStmt = $pdo->prepare("INSERT INTO audit_assignments (audit_id, auditor_id, section_id, assigned_by) VALUES (?, ?, ?, ?)");
                    $assignedCount = 0;
                    foreach ($assignments as $sectionId => $auditorId) {
                        if ((int) $auditorId > 0) {
                            $assignStmt->execute([$auditId, (int) $auditorId, (int) $sectionId, $user['id']]);
                            $assignedCount++;
                        }
                    }

                    $pdo->commit();

                    logActivity('audit_created', "Yangi audit yaratildi: $auditCode ($title)", 'audit');

                    $_SESSION['flash_message'] = "Audit muvaffaqiyatli yaratildi! Kod: <strong>$auditCode</strong>";
                    $_SESSION['flash_type'] = 'success';
                    redirect("audits.php");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Bazaga saqlashda xatolik: " . $e->getMessage();
                    $messageType = 'danger';
                }
            } else {
                $errors = [];
                if ($siteId <= 0)
                    $errors[] = "Korxona tanlanmagan";
                if ($title === '')
                    $errors[] = "Audit nomi kiritilmagan";
                if ($startDate === '')
                    $errors[] = "Sana kiritilmagan";
                if (empty(array_filter($assignments, fn($v) => (int) $v > 0)))
                    $errors[] = "Bo'limga auditor biriktirilmagan";
                $message = implode(', ', $errors) . "!";
                $messageType = 'danger';
            }
        } else if ($action === 'create' && !$perm['create']) {
            $message = "Sizda audit yaratish uchun ruxsat yo'q!";
            $messageType = 'danger';
        }

        // 2. AUDIT O'CHIRISH
        if ($action === 'delete_audit' && $perm['delete']) {
            $auditId = (int) ($_POST['audit_id'] ?? 0);
            if ($auditId > 0) {
                try {
                    $stmt = $pdo->prepare("SELECT audit_code, status FROM audits WHERE id = ?");
                    $stmt->execute([$auditId]);
                    $audit = $stmt->fetch();

                    if ($audit) {
                        if ($audit['status'] === 'in_progress') {
                            $message = "Jarayondagi auditni o'chirib bo'lmaydi!";
                            $messageType = 'danger';
                        } else {
                            $pdo->beginTransaction();

                            $pdo->prepare("DELETE FROM non_conformities WHERE audit_id = ?")->execute([$auditId]);
                            $pdo->prepare("DELETE FROM audit_answers WHERE audit_id = ?")->execute([$auditId]);
                            $pdo->prepare("DELETE FROM audit_assignments WHERE audit_id = ?")->execute([$auditId]);
                            $pdo->prepare("DELETE FROM audits WHERE id = ?")->execute([$auditId]);

                            $pdo->commit();

                            logActivity('audit_deleted', "Audit o'chirildi (ID: $auditId): " . ($audit['audit_code'] ?? ''));

                            $_SESSION['flash_message'] = "Audit o'chirildi!";
                            $_SESSION['flash_type'] = 'success';
                            redirect("audits.php");
                        }
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction())
                        $pdo->rollBack();
                    $message = "O'chirishda xatolik: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }

        // 3. HOLAT O'ZGARISHI
        if ($action === 'update_status' && $perm['edit']) {
            $auditId = (int) ($_POST['audit_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            $allowedStatuses = ['draft', 'in_progress', 'completed', 'cancelled'];
            $statusTransitions = [
                'draft' => ['in_progress', 'cancelled'],
                'in_progress' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => ['draft']
            ];

            if ($auditId > 0 && in_array($status, $allowedStatuses)) {
                $stmt = $pdo->prepare("SELECT status FROM audits WHERE id = ?");
                $stmt->execute([$auditId]);
                $current = $stmt->fetch();

                if ($current && in_array($status, $statusTransitions[$current['status']] ?? [])) {
                    try {
                        $pdo->prepare("UPDATE audits SET status = ? WHERE id = ?")->execute([$status, $auditId]);

                        $_SESSION['flash_message'] = "Audit holati o'zgartirildi!";
                        $_SESSION['flash_type'] = 'success';
                        redirect("audits.php");
                    } catch (Exception $e) {
                        $message = "Holatni o'zgartirishda xatolik.";
                        $messageType = 'danger';
                    }
                } else {
                    $message = "Bu holatga o'tish mumkin emas!";
                    $messageType = 'danger';
                }
            }
        }
    }
}

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$whereClauses = [];
$params = [];

if (!$perm['view_all']) {
    $whereClauses[] = "EXISTS (SELECT 1 FROM audit_assignments aa WHERE aa.audit_id = a.id AND aa.auditor_id = ?)";
    $params[] = $user['id'];
}

if (in_array($filterStatus, ['draft', 'in_progress', 'completed', 'cancelled'])) {
    $whereClauses[] = "a.status = ?";
    $params[] = $filterStatus;
}

if ($filterSearch !== '') {
    $whereClauses[] = "(a.audit_code LIKE ? OR a.title LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$filterSearch%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($filterDateFrom !== '') {
    $whereClauses[] = "a.start_date >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $whereClauses[] = "a.start_date <= ?";
    $params[] = $filterDateTo;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audits a JOIN sites s ON a.site_id = s.id $whereSQL");
$countStmt->execute($params);
$totalAudits = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalAudits / $perPage));
$offset = ($currentPage - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT a.*, s.name as site_name, u.full_name as creator_name,
           (SELECT COUNT(*) FROM audit_answers WHERE audit_id = a.id AND answer != 'na') as answered_count,
           (SELECT COUNT(*) FROM checklist_questions WHERE is_active = 1) as total_questions,
           (SELECT COUNT(DISTINCT nc.id) FROM non_conformities nc WHERE nc.audit_id = a.id) as nc_count
    FROM audits a 
    JOIN sites s ON a.site_id = s.id 
    JOIN users u ON a.created_by = u.id 
    $whereSQL
    ORDER BY CASE a.status WHEN 'in_progress' THEN 1 WHEN 'draft' THEN 2 WHEN 'completed' THEN 3 WHEN 'cancelled' THEN 4 END, a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$audits = $stmt->fetchAll();

$sites = $pdo->query("SELECT * FROM sites WHERE is_active = 1 ORDER BY name")->fetchAll();
$auditors = $pdo->query("SELECT * FROM users WHERE role IN ('bosh_auditor', 'auditor') AND is_active = 1 ORDER BY full_name")->fetchAll();
$sections = $pdo->query("SELECT * FROM gmp_sections ORDER BY sort_order")->fetchAll();

$statusCounts = ['all' => $totalAudits, 'draft' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];

if ($perm['view_all']) {
    $countAll = $pdo->query("SELECT status, COUNT(*) as cnt FROM audits GROUP BY status")->fetchAll();
} else {
    $countAll = $pdo->prepare("SELECT a.status, COUNT(*) as cnt FROM audits a WHERE EXISTS (SELECT 1 FROM audit_assignments aa WHERE aa.audit_id = a.id AND aa.auditor_id = ?) GROUP BY a.status");
    $countAll->execute([$user['id']]);
}
foreach ($countAll as $row) {
    $statusCounts[$row['status']] = (int) $row['cnt'];
}

$showCreateForm = isset($_GET['action']) && $_GET['action'] === 'new' && $perm['create'];
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditlar - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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

        .btn-primary {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4);
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-info {
            background: rgba(6, 182, 212, 0.15);
            color: #06b6d4;
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .badge-neutral {
            background: rgba(100, 116, 139, 0.15);
            color: #94a3b8;
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        .progress-bar {
            background: rgba(30, 41, 59, 0.8);
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(90deg, #06b6d4, #14b8a6);
            transition: width 0.8s ease;
        }

        .progress-fill-high {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .progress-fill-low {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .filter-tab {
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .filter-tab:hover {
            background: rgba(51, 65, 85, 0.5);
        }

        .filter-tab.active {
            background: rgba(6, 182, 212, 0.1);
            border-color: rgba(6, 182, 212, 0.3);
            color: white;
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

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
        }

        .animate-fade {
            animation: fadeIn 0.3s ease forwards;
        }

        .audit-card {
            transition: all 0.3s ease;
        }

        .audit-card:hover {
            border-color: rgba(6, 182, 212, 0.3);
            transform: translateX(4px);
        }

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

        ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
    </style>
</head>

<body class="min-h-screen text-slate-100">

    <!-- Mobile Header -->
<?php $activePage = "audits"; include "inc/sidebar.php"; ?>

<main class="flex-1 lg:ml-64 w-full">
            <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50">
                <div class="px-4 lg:px-8 py-4 flex justify-between items-center">
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold text-white">
                            <?php echo $showCreateForm ? 'Yangi Audit Yaratish' : 'Auditlar'; ?></h1>
                        <p class="text-slate-500 text-sm">
                            <?php echo $showCreateForm ? 'Yangi audit jarayonini boshlash' : "Jami {$statusCounts['all']} ta audit"; ?>
                        </p>
                    </div>
                    <?php if (!$showCreateForm): ?>
                        <div class="flex items-center gap-3">
                            <a href="sites.php"
                                class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 hover:text-white transition-colors font-medium"><svg
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg><span class="hidden sm:inline">Korxonalar</span></a>
                            <?php if ($perm['create']): ?>
                                <a href="?action=new"
                                    class="flex items-center gap-2 btn-primary px-4 py-2.5 rounded-xl font-medium text-white"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg><span class="hidden sm:inline">Yangi Audit</span></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <div class="p-4 lg:p-8">
                <?php if ($message): ?>
                    <div
                        class="mb-6 p-4 rounded-xl border animate-fade <?php echo $messageType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-300'; ?>">
                        <div class="flex items-center gap-3">
                            <?php if ($messageType === 'success'): ?>
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            <?php else: ?>
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            <?php endif; ?>
                            <span><?php echo $message; ?></span>
                            <button onclick="this.closest('div').remove()" class="ml-auto opacity-50 hover:opacity-100"><svg
                                    class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg></button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($showCreateForm): ?>
                    <div class="stat-card rounded-2xl p-6 lg:p-8 animate-in">
                        <form method="POST" action="" id="createForm">
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="grid lg:grid-cols-2 gap-6 mb-8">
                                <div>
                                    <label class="block text-slate-300 text-sm font-medium mb-2">Audit nomi <span
                                            class="text-red-400">*</span></label>
                                    <input type="text" name="title" class="input-field w-full px-4 py-3 rounded-xl"
                                        placeholder="Masalan: 2024-yil 1-chorak GMP auditi" required maxlength="200">
                                </div>
                                <div>
                                    <label class="block text-slate-300 text-sm font-medium mb-2">Korxona <span
                                            class="text-red-400">*</span></label>
                                    <select name="site_id" class="input-field w-full px-4 py-3 rounded-xl" required>
                                        <option value="">Tanlang...</option>
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?php echo $site['id']; ?>">
                                                <?php echo htmlspecialchars($site['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-slate-300 text-sm font-medium mb-2">Boshlanish sanasi <span
                                            class="text-red-400">*</span></label>
                                    <input type="date" name="start_date" class="input-field w-full px-4 py-3 rounded-xl"
                                        value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div>
                                    <label class="block text-slate-300 text-sm font-medium mb-2">Tugatish sanasi
                                        (Reja)</label>
                                    <input type="date" name="end_date" class="input-field w-full px-4 py-3 rounded-xl">
                                </div>
                            </div>

                            <div class="border-t border-slate-700/50 pt-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-white">Bo'limlarni biriktirish</h3>
                                    <button type="button" onclick="assignAllSections()"
                                        class="text-cyan-400 hover:text-cyan-300 text-sm">Barchasini birinchi
                                        auditorga</button>
                                </div>
                                <div
                                    class="overflow-x-auto max-h-[400px] overflow-y-auto rounded-xl border border-slate-700/50">
                                    <table class="w-full text-left">
                                        <thead class="bg-slate-800/80 sticky top-0 z-10">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider rounded-tl-xl">
                                                    Bo'lim</th>
                                                <th
                                                    class="px-4 py-3 text-slate-400 text-xs font-medium uppercase tracking-wider rounded-tr-xl">
                                                    Mas'ul Auditor</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-700/30">
                                            <?php if (empty($sections)): ?>
                                                <tr>
                                                    <td colspan="2" class="px-4 py-8 text-center text-slate-500">Bo'limlar
                                                        mavjud emas.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($sections as $sec): ?>
                                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                                        <td class="px-4 py-3">
                                                            <span
                                                                class="font-mono text-cyan-400 text-sm mr-2"><?php echo htmlspecialchars($sec['section_number']); ?></span>
                                                            <span
                                                                class="text-white text-sm"><?php echo htmlspecialchars($sec['section_name']); ?></span>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <select name="assignments[<?php echo $sec['id']; ?>]"
                                                                class="input-field w-full px-3 py-2 rounded-lg text-sm section-auditor">
                                                                <option value="0">— Biriktirilmagan —</option>
                                                                <?php foreach ($auditors as $auditor): ?>
                                                                    <option value="<?php echo $auditor['id']; ?>">
                                                                        <?php echo htmlspecialchars($auditor['full_name']); ?>
                                                                        (<?php echo $auditor['role'] === 'bosh_auditor' ? 'Bosh' : 'Aud'; ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-8 border-t border-slate-700/50 mt-6">
                                <a href="audits.php"
                                    class="px-6 py-3 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-all text-center">Bekor
                                    qilish</a>
                                <button type="submit"
                                    class="btn-primary px-8 py-3 rounded-xl text-white font-semibold flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    Auditni Yaratish
                                </button>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="flex flex-wrap gap-2 mb-6">
                        <?php
                        $tabs = [
                            'all' => ['label' => 'Barchasi', 'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16', 'badge' => 'neutral'],
                            'in_progress' => ['label' => 'Jarayonda', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'badge' => 'warning'],
                            'draft' => ['label' => 'Draft', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'badge' => 'info'],
                            'completed' => ['label' => 'Tugatilgan', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'badge' => 'success'],
                            'cancelled' => ['label' => 'Bekor', 'icon' => 'M6 18L18 6M6 6l12 12', 'badge' => 'danger'],
                        ];
                        foreach ($tabs as $key => $tab):
                            $isActive = ($filterStatus === '' && $key === 'all') || $filterStatus === $key;
                            $url = buildFilterUrl($key);
                            ?>
                            <a href="<?php echo $url; ?>"
                                class="filter-tab <?php echo $isActive ? 'active' : ''; ?> flex items-center gap-2 px-4 py-2 rounded-xl text-sm <?php echo $isActive ? 'text-white' : 'text-slate-400'; ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="<?php echo $tab['icon']; ?>" />
                                </svg>
                                <?php echo $tab['label']; ?>
                                <span
                                    class="badge badge-<?php echo $tab['badge']; ?> ml-1"><?php echo $statusCounts[$key]; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($audits)): ?>
                            <div class="stat-card rounded-2xl p-12 text-center">
                                <h3 class="text-xl font-semibold text-white mb-2">Auditlar yo'q</h3>
                                <p class="text-slate-500 mb-6">Hali hech qanday audit yaratilmagan.</p>
                                <?php if ($perm['create']): ?>
                                    <a href="?action=new"
                                        class="px-5 py-2.5 rounded-xl btn-primary text-white font-medium inline-flex items-center gap-2"><svg
                                            class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>Yangi Audit</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($audits as $audit):
                                $progress = $audit['total_questions'] > 0 ? round(($audit['answered_count'] / $audit['total_questions']) * 100, 1) : 0;
                                $statusClasses = ['draft' => 'badge-info', 'in_progress' => 'badge-warning', 'completed' => 'badge-success', 'cancelled' => 'badge-danger'];
                                $statusLabels = ['draft' => 'Draft', 'in_progress' => 'Jarayonda', 'completed' => 'Tugatilgan', 'cancelled' => 'Bekor qilingan'];
                                $progressClass = $progress >= 80 ? 'progress-fill-high' : ($progress < 30 ? 'progress-fill-low' : '');
                                ?>
                                <div class="audit-card stat-card rounded-2xl p-5 lg:p-6">
                                    <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                <a href="conduct_audit.php?id=<?php echo $audit['id']; ?>"
                                                    class="font-mono text-cyan-400 text-sm hover:text-cyan-300"><?php echo htmlspecialchars($audit['audit_code']); ?></a>
                                                <span
                                                    class="badge <?php echo $statusClasses[$audit['status']] ?? 'badge-info'; ?>"><?php echo $statusLabels[$audit['status']] ?? $audit['status']; ?></span>
                                            </div>
                                            <h3 class="text-lg font-semibold text-white mb-2 truncate">
                                                <?php echo htmlspecialchars($audit['title']); ?></h3>
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-400">
                                                <span><?php echo htmlspecialchars($audit['site_name']); ?></span>
                                                <span><?php echo date('d.m.Y', strtotime($audit['start_date'])); ?></span>
                                                <span><?php echo htmlspecialchars($audit['creator_name']); ?></span>
                                            </div>
                                        </div>
                                        <div class="lg:w-48 flex-shrink-0">
                                            <div class="flex items-center justify-between text-sm mb-2"><span
                                                    class="text-slate-500">Progress</span><span
                                                    class="text-white font-semibold font-mono"><?php echo $progress; ?>%</span>
                                            </div>
                                            <div class="progress-bar h-2 rounded-full">
                                                <div class="progress-fill <?php echo $progressClass; ?> h-full rounded-full"
                                                    style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <?php if ($audit['status'] === 'draft' && $perm['edit']): ?>
                                                <form method="POST" action=""><input type="hidden" name="csrf_token"
                                                        value="<?php echo $csrfToken; ?>"><input type="hidden" name="action"
                                                        value="update_status"><input type="hidden" name="audit_id"
                                                        value="<?php echo $audit['id']; ?>"><input type="hidden" name="status"
                                                        value="in_progress"><button type="submit"
                                                        class="px-4 py-2 rounded-xl bg-amber-500/15 text-amber-400 hover:bg-amber-500/25 border border-amber-500/20 text-sm font-medium">Boshlash</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($audit['status'] === 'in_progress'): ?>
                                                <a href="conduct_audit.php?id=<?php echo $audit['id']; ?>"
                                                    class="px-4 py-2 rounded-xl bg-cyan-500/15 text-cyan-400 hover:bg-cyan-500/25 border border-cyan-500/20 text-sm font-medium">Davom
                                                    etish</a>
                                                <?php if ($perm['edit']): ?>
                                                    <form method="POST" action=""
                                                        onsubmit="return confirm('Yakunlashga ishonchingiz komilmi?')"><input type="hidden"
                                                            name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden"
                                                            name="action" value="update_status"><input type="hidden" name="audit_id"
                                                            value="<?php echo $audit['id']; ?>"><input type="hidden" name="status"
                                                            value="completed"><button type="submit"
                                                            class="px-4 py-2 rounded-xl bg-emerald-500/15 text-emerald-400 hover:bg-emerald-500/25 border border-emerald-500/20 text-sm font-medium">Yakunlash</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($perm['delete'] && $audit['status'] !== 'in_progress'): ?>
                                                <button type="button"
                                                    onclick="showDeleteModal(<?php echo $audit['id']; ?>, '<?php echo htmlspecialchars(addslashes($audit['audit_code'])); ?>')"
                                                    class="p-2 rounded-xl bg-red-500/10 text-red-400 hover:bg-red-500/20 border border-red-500/10"
                                                    title="O'chirish"><svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($totalPages > 1): ?>
                                <div class="flex items-center justify-center gap-2 pt-6">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="<?php echo buildPageUrl($i); ?>"
                                            class="w-10 h-10 rounded-lg flex items-center justify-center text-sm <?php echo $i === $currentPage ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800'; ?>"><?php echo $i; ?></a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0" onclick="hideDeleteModal()"></div>
        <div class="modal-content relative rounded-2xl p-6 max-w-md w-full mx-auto">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4"><svg
                        class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg></div>
                <h3 class="text-xl font-bold text-white mb-2">Auditni o'chirish</h3>
                <p id="deleteAuditName" class="text-cyan-400 font-medium mb-4"></p>
            </div>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="delete_audit">
                <input type="hidden" name="audit_id" id="deleteAuditId">
                <div class="flex gap-3">
                    <button type="button" onclick="hideDeleteModal()"
                        class="flex-1 px-4 py-3 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-all">Bekor
                        qilish</button>
                    <button type="submit"
                        class="flex-1 px-4 py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white font-medium transition-all">O'chirish</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        function showDeleteModal(id, name) {
            document.getElementById('deleteAuditId').value = id;
            document.getElementById('deleteAuditName').textContent = name;
            const m = document.getElementById('deleteModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }

        function hideDeleteModal() {
            const m = document.getElementById('deleteModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }

        function assignAllSections() {
            const s = document.querySelectorAll('.section-auditor');
            if (!s.length) return;
            let v = null;
            for (let i = 1; i < s[0].options.length; i++) {
                if (s[0].options[i].value > 0) {
                    v = s[0].options[i].value;
                    break;
                }
            }
            if (v) s.forEach(sel => sel.value = v);
        }
    </script>
</body>

</html>