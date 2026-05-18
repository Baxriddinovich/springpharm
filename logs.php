<?php
date_default_timezone_set('Asia/Tashkent');
// logs.php - Tizim tarixi (MUKAMMAL VERSIYA)
require_once 'db.php';
requireLogin();

 $user = getCurrentUser();

// в­ђ Role-based access
if (!in_array($user['role'], ['super_admin'])) {
    $_SESSION['flash_message'] = "Bu sahifaga kirish uchun admin huquqi talab etiladi!";
    $_SESSION['flash_type'] = 'danger';
    header("Location: dashboard.php");
    exit;
}

// в­ђ Filtirlash parametrlari
 $filterUser = (int)($_GET['user'] ?? 0);
 $filterType = $_GET['type'] ?? '';
 $filterDateFrom = $_GET['date_from'] ?? '';
 $filterDateTo = $_GET['date_to'] ?? '';
 $filterSearch = trim($_GET['search'] ?? '');
 $currentPage = max(1, (int)($_GET['page'] ?? 1));
 $perPage = 25;

// в­ђ WHERE shartlarini yig'ish
 $whereClauses = ["1=1"];
 $params = [];

if ($filterUser > 0) {
    $whereClauses[] = "al.user_id = ?";
    $params[] = $filterUser;
}

if ($filterType !== '') {
    $allowedTypes = [
        'audit_created', 'audit_deleted', 'audit_status_changed', 'audit_signed', 'audit_answer_saved',
        'section_added', 'section_edited', 'section_deleted', 
        'question_added', 'question_edited', 'question_deleted', 
        'user_added', 'user_edited', 'user_deleted',
        'login', 'logout',
        'material_viewed', 'module_completed', 'test_submitted'
    ];
    if (in_array($filterType, $allowedTypes)) {
        $whereClauses[] = "al.action_type = ?";
        $params[] = $filterType;
    }
}

if ($filterDateFrom !== '') {
    $whereClauses[] = "al.created_at >= ?";
    $params[] = $filterDateFrom . ' 00:00:00';
}

if ($filterDateTo !== '') {
    $whereClauses[] = "al.created_at <= ?";
    $params[] = $filterDateTo . ' 23:59:59';
}

if ($filterSearch !== '') {
    $whereClauses[] = "(al.details LIKE ? OR u.full_name LIKE ?)";
    $searchParam = "%$filterSearch%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

 $whereSQL = implode(' AND ', $whereClauses);

// в­ђ Umumiy son (pagination)
 $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al WHERE $whereSQL");
 $countStmt->execute($params);
 $totalLogs = $countStmt->fetchColumn();
 $totalPages = max(1, ceil($totalLogs / $perPage));
 $offset = ($currentPage - 1) * $perPage;

// в­ђ Loglarni olish (activity_logs jadvalidan, chunki logActivity shu yerda yoziladi)
 $stmt = $pdo->prepare("
    SELECT al.*, u.full_name, u.role, u.username
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    WHERE $whereSQL
    ORDER BY al.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
 $stmt->execute($params);
 $logs = $stmt->fetchAll();

// в­ђ Foydalanuvchilar ro'yxati (filter uchun)
 $usersForFilter = $pdo->query("
    SELECT DISTINCT u.id, u.full_name, u.role 
    FROM users u 
    JOIN activity_logs al ON al.user_id = u.id
    ORDER BY u.full_name
")->fetchAll();

// в­ђ Harakat turlari statistikasi (filter uchun)
 $actionTypes = $pdo->query("
    SELECT action_type, COUNT(*) as count 
    FROM activity_logs 
    GROUP BY action_type 
    ORDER BY count DESC
")->fetchAll();

// в­ђ Umumiy statistika
 $todayCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
 $weekCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tizim Tarixi - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
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
        .stat-card:hover { border-color: rgba(6, 182, 212, 0.3); }
        
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
        
        .filter-tab { transition: all 0.2s ease; cursor: pointer; border: 1px solid transparent; }
        .filter-tab:hover { background: rgba(51, 65, 85, 0.5); }
        .filter-tab.active { background: rgba(6, 182, 212, 0.15); border-color: rgba(6, 182, 212, 0.3); color: white; }
        
        .log-row { transition: background 0.2s ease; }
        .log-row:hover { background: rgba(30, 41, 59, 0.3); }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.5s ease forwards; }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }

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
<?php $activePage = "logs"; include "inc/sidebar.php"; ?>


        
        <!-- Main Content -->
        <main class="flex-1 lg:ml-64 w-full">
            <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50">
                <div class="px-4 lg:px-8 py-4">
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold text-white">Tizim Tarixi</h1>
                        <p class="text-slate-500 text-sm">Barcha foydalanuvchilarning harakatlari</p>
                    </div>
                </div>
            </header>
            
            <div class="p-4 lg:p-8">
                <!-- в­ђ Statistikalar -->
                <div class="grid grid-cols-3 gap-4 mb-8">
                    <div class="stat-card rounded-2xl p-5 animate-in delay-1">
                        <div class="text-xs text-slate-500 mb-2 uppercase tracking-wider">Jami yozuvlar</div>
                        <div class="text-3xl font-bold text-white"><?php echo number_format($totalLogs); ?></div>
                    </div>
                    <div class="stat-card rounded-2xl p-5 animate-in delay-2">
                        <div class="text-xs text-slate-500 mb-2 uppercase tracking-wider">Bugun</div>
                        <div class="text-3xl font-bold text-cyan-400"><?php echo number_format($todayCount); ?></div>
                    </div>
                    <div class="stat-card rounded-2xl p-5 animate-in delay-3">
                        <div class="text-xs text-slate-500 mb-2 uppercase tracking-wider">So'ngi 7 kun</div>
                        <div class="text-3xl font-bold text-amber-400"><?php echo number_format($weekCount); ?></div>
                    </div>
                </div>

                <!-- в­ђ Filterlar -->
                <div class="stat-card rounded-2xl p-4 mb-6 animate-in delay-3">
                    <form method="GET" action="" class="flex flex-col lg:flex-row gap-3">
                        <div class="flex-1 relative">
                            <svg class="w-5 h-5 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filterSearch); ?>" class="input-field w-full pl-10 pr-4 py-2.5 rounded-xl text-sm" placeholder="Qidirish (tajriba, foydalanuvchi...)">
                        </div>
                        <select name="user" class="input-field px-3 py-2.5 rounded-xl text-sm">
                            <option value="0">Barcha foydalanuvchilar</option>
                            <?php foreach ($usersForFilter as $uf): ?>
                            <option value="<?php echo $uf['id']; ?>" <?php echo $filterUser == $uf['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($uf['full_name'] . ' (' . ucfirst(str_replace('_', ' ', $uf['role'])) . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date_from" value="<?php echo $filterDateFrom; ?>" class="input-field px-3 py-2.5 rounded-xl text-sm" placeholder="Dan">
                        <input type="date" name="date_to" value="<?php echo $filterDateTo; ?>" class="input-field px-3 py-2.5 rounded-xl text-sm" placeholder="Gacha">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-white transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 01-.707.293l-6.414-6.414A1 1 0 013 10V4z"/></svg>
                        </button>
                        <?php if ($filterUser || $filterType || $filterDateFrom || $filterDateTo || $filterSearch): ?>
                        <a href="logs.php" class="px-4 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-700 transition-colors text-sm whitespace-nowrap">
                            Tozalash
                        </a>
                        <?php endif; ?>
                        <?php if ($filterType !== ''): ?>
                        <input type="hidden" name="type" value="<?php echo $filterType; ?>">
                        <?php endif; ?>
                    </form>
                </div>

                <!-- в­ђ Harakat turlari bo'yicha filter -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <a href="logs.php<?php echo $filterUser ? "?user=$filterUser" : ''; ?>" 
                       class="filter-tab px-3 py-2 rounded-xl text-xs font-medium <?php echo !$filterType ? 'active' : 'text-slate-500 border border-slate-700/50'; ?>">
                        Barchasi
                    </a>
                    <?php 
                    $typeLabels = [
                        'audit_created' => ['label' => 'Audit yaratildi', 'color' => 'cyan'],
                        'audit_deleted' => ['label' => 'Audit o\'chirildi', 'color' => 'red'],
                        'audit_status_changed' => ['label' => 'Audit holati o\'zgardi', 'color' => 'amber'],
                        'audit_signed' => ['label' => 'Audit imzolandi', 'color' => 'emerald'],
                        'audit_answer_saved' => ['label' => 'Javob saqlandi', 'color' => 'blue'],
                        'section_added' => ['label' => 'Bo\'lim qo\'shildi', 'color' => 'purple'],
                        'section_edited' => ['label' => 'Bo\'lim tahrirlandi', 'color' => 'purple'],
                        'section_deleted' => ['label' => 'Bo\'lim o\'chirildi', 'color' => 'red'],
                        'question_added' => ['label' => 'Savol qo\'shildi', 'color' => 'cyan'],
                        'question_edited' => ['label' => 'Savol tahrirlandi', 'color' => 'cyan'],
                        'question_deleted' => ['label' => 'Savol o\'chirildi', 'color' => 'red'],
                        'user_added' => ['label' => 'Foydalanuvchi qo\'shildi', 'color' => 'emerald'],
                        'user_edited' => ['label' => 'Foydalanuvchi yangilandi', 'color' => 'emerald'],
                        'user_deleted' => ['label' => 'Foydalanuvchi o\'chirildi', 'color' => 'red'],
                        'login' => ['label' => 'Tizimga kirdi', 'color' => 'cyan'],
                        'logout' => ['label' => 'Tizimdan chiqdi', 'color' => 'slate'],
                        'material_viewed' => ['label' => 'Material ko\'rildi', 'color' => 'indigo'],
                        'module_completed' => ['label' => 'Modul tugatildi', 'color' => 'teal'],
                        'test_submitted' => ['label' => 'Test topshirildi', 'color' => 'amber'],
                    ];
                    
                    foreach ($actionTypes as $at): 
                        $label = $typeLabels[$at['action_type']]['label'] ?? str_replace('_', ' ', $at['action_type']);
                        $color = $typeLabels[$at['action_type']]['color'] ?? 'slate';
                        $baseFilterUrl = $filterUser ? "?user=$filterUser" : '';
                    ?>
                    <a href="?type=<?php echo $at['action_type']; ?><?php echo $filterUser ? "&user=$filterUser" : ''; ?>" 
                       class="filter-tab px-3 py-2 rounded-xl text-xs font-medium border border-slate-700/50 flex items-center gap-1.5 <?php echo $filterType === $at['action_type'] ? "active text-$color-400" : "text-slate-500"; ?>">
                        <span class="w-2 h-2 rounded-full bg-<?php echo $color; ?>-500"></span>
                        <?php echo htmlspecialchars($label); ?>
                        <span class="opacity-50">(<?php echo $at['count']; ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- в­ђ Loglar Jadvali -->
                <?php if (!empty($logs)): ?>
                <div class="stat-card rounded-2xl overflow-hidden animate-in delay-3">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-800/30">
                                <tr>
                                    <th class="px-4 lg:px-6 py-3 text-slate-500 text-xs font-medium uppercase tracking-wider w-44">Vaqt</th>
                                    <th class="px-4 lg:px-6 py-3 text-slate-500 text-xs font-medium uppercase tracking-wider w-44">Foydalanuvchi</th>
                                    <th class="px-4 lg:px-6 py-3 text-slate-500 text-xs font-medium uppercase tracking-wider">Harakat</th>
                                    <th class="px-4 lg:px-6 py-3 text-slate-500 text-xs font-medium uppercase tracking-wider hidden lg:table-cell">Tafsilotlar</th>
                                    <th class="px-4 lg:px-6 py-3 text-slate-500 text-xs font-medium uppercase tracking-wider text-right w-32">IP Manzil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/20">
                                <?php foreach ($logs as $log): 
                                    $typeColor = $typeLabels[$log['action_type']]['color'] ?? 'slate';
                                    $typeLabel = $typeLabels[$log['action_type']]['label'] ?? str_replace('_', ' ', $log['action_type']);
                                ?>
                                <tr class="log-row">
                                    <td class="px-4 lg:px-6 py-3">
                                        <div class="text-xs text-slate-400"><?php echo date('d.m.Y', strtotime($log['created_at'])); ?></div>
                                        <div class="text-xs text-slate-600 font-mono"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                    </td>
                                    <td class="px-4 lg:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-cyan-500/30 to-teal-500/30 flex items-center justify-center text-cyan-400 text-xs font-bold flex-shrink-0">
                                                <?php echo strtoupper(mb_substr($log['full_name'], 0, 1)); ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-white text-sm truncate"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                                <div class="text-xs text-slate-600"><?php echo ucfirst(str_replace('_', ' ', $log['role'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 lg:px-6 py-3">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-<?php echo $typeColor; ?>-500/15 text-<?php echo $typeColor; ?>-400 border border-<?php echo $typeColor; ?>-500/20">
                                            <?php echo htmlspecialchars($typeLabel); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 lg:px-6 py-3 hidden lg:table-cell">
                                        <p class="text-sm text-slate-300 truncate max-w-xs"><?php echo htmlspecialchars($log['details'] ?? ''); ?></p>
                                    </td>
                                    <td class="px-4 lg:px-6 py-3 text-right">
                                        <span class="text-xs text-slate-600 font-mono"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="flex items-center justify-between px-6 py-4 border-t border-slate-700/30">
                        <p class="text-xs text-slate-500">
                            <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalLogs); ?> / <?php echo $totalLogs; ?>
                        </p>
                        <div class="flex items-center gap-1">
                            <?php if ($currentPage > 1): ?>
                            <a href="<?php echo buildPageUrl($currentPage - 1); ?>" class="w-9 h-9 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <a href="<?php echo buildPageUrl($i); ?>" 
                               class="w-9 h-9 rounded-lg flex items-center justify-center text-sm transition-colors <?php echo $i === $currentPage ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-slate-800'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                            <a href="<?php echo buildPageUrl($currentPage + 1); ?>" class="w-9 h-9 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php else: ?>
                <!-- Empty State -->
                <div class="stat-card rounded-2xl p-12 text-center animate-in">
                    <div class="w-20 h-20 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Loglar yo'q</h3>
                    <p class="text-slate-500 max-w-md mx-auto">
                        <?php echo ($filterSearch || $filterUser || $filterType || $filterDateFrom || $filterDateTo) 
                            ? 'Berilgan filtrlar bo\'yicha natijalar topilmadi.' 
                            : 'Hali tizimda hech qanday harakatlar qayd etilmagan.'; ?>
                    </p>
                    <?php if ($filterSearch || $filterUser || $filterType || $filterDateFrom || $filterDateTo): ?>
                    <a href="logs.php" class="inline-flex items-center gap-2 text-cyan-400 hover:text-cyan-300 text-sm mt-4">
                        Filtrlarni tozalash
                    </a>
                    <?php endif; ?>
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

        // Close sidebar on nav click (mobile)
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 1024) toggleSidebar();
            });
        });
    </script>
</body>
</html>
