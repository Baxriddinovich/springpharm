<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../db.php';
requireLogin();
$user = getCurrentUser();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ═══════════════════════════════════════════════════════════
// FILTER PARAMETRLARI
// ═══════════════════════════════════════════════════════════
$filterModule = intval($_GET['module_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$filterUser   = intval($_GET['user_id'] ?? 0);
$search       = trim($_GET['search'] ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// ═══════════════════════════════════════════════════════════
// MODULLAR RO'YXATI (filter uchun)
// ═══════════════════════════════════════════════════════════
$modules = $pdo->query("SELECT id, title, code FROM training_modules ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// ═══════════════════════════════════════════════════════════
// FOYDALANUVCHILAR RO'YXATI (filter uchun)
// ═══════════════════════════════════════════════════════════
$users = $pdo->query("SELECT id, full_name, username FROM users WHERE role='reader' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ═══════════════════════════════════════════════════════════
// ASOSIY SO'ROV
// ═══════════════════════════════════════════════════════════
$where  = ['1=1'];
$params = [];

if ($filterModule) {
    $where[]  = 'rta.module_id = ?';
    $params[] = $filterModule;
}
if ($filterStatus === 'passed' || $filterStatus === 'failed') {
    $where[]  = 'rta.status = ?';
    $params[] = $filterStatus;
}
if ($filterUser) {
    $where[]  = 'rta.user_id = ?';
    $params[] = $filterUser;
}
if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.username LIKE ? OR tm.title LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereStr = implode(' AND ', $where);

// Jami soni
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reader_test_attempts rta
    JOIN users u ON rta.user_id = u.id
    JOIN training_modules tm ON rta.module_id = tm.id
    WHERE $whereStr
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Natijalar
$dataStmt = $pdo->prepare("
    SELECT
        rta.id,
        rta.score,
        rta.correct_count,
        rta.total_count,
        rta.status,
        rta.passing_percent,
        rta.attempted_at,
        rta.next_allowed_at,
        u.id        AS user_id,
        u.full_name,
        u.username,
        tm.id       AS module_id,
        tm.title    AS module_title,
        tm.code     AS module_code,
        p.name      AS position_name
    FROM reader_test_attempts rta
    JOIN users u ON rta.user_id = u.id
    JOIN training_modules tm ON rta.module_id = tm.id
    LEFT JOIN positions p ON u.position_id = p.id
    WHERE $whereStr
    ORDER BY rta.attempted_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$results = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// ═══════════════════════════════════════════════════════════
// UMUMIY STATISTIKA
// ═══════════════════════════════════════════════════════════
try {
    $stats = [
        'total'   => $pdo->query("SELECT COUNT(*) FROM reader_test_attempts")->fetchColumn(),
        'passed'  => $pdo->query("SELECT COUNT(*) FROM reader_test_attempts WHERE status='passed'")->fetchColumn(),
        'failed'  => $pdo->query("SELECT COUNT(*) FROM reader_test_attempts WHERE status='failed'")->fetchColumn(),
        'avg'     => $pdo->query("SELECT ROUND(AVG(score),1) FROM reader_test_attempts")->fetchColumn() ?? 0,
    ];
} catch (Exception $e) {
    $stats = ['total' => 0, 'passed' => 0, 'failed' => 0, 'avg' => 0];
}

// ═══════════════════════════════════════════════════════════
// URL HELPER
// ═══════════════════════════════════════════════════════════
function buildUrl(array $override = []): string {
    $params = array_merge([
        'module_id' => $_GET['module_id'] ?? '',
        'status'    => $_GET['status'] ?? '',
        'user_id'   => $_GET['user_id'] ?? '',
        'search'    => $_GET['search'] ?? '',
        'page'      => $_GET['page'] ?? 1,
    ], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== '0' && $v !== 0);
    return 'results.php' . ($params ? '?' . http_build_query($params) : '');
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Natijalari - GMP Learning</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent-cyan: #06b6d4; --glass-bg: rgba(26,35,50,0.7); --glass-border: rgba(51,65,85,0.5); }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); color: #f1f5f9; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); }
        .nav-item { transition: all 0.2s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { background: rgba(6,182,212,0.1); border-left-color: var(--accent-cyan); color: #fff; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #0f172a; } ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        #mobile-sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; }
        #mobile-sidebar.active { transform: translateX(0); }
    </style>
</head>
<body class="min-h-screen flex text-slate-100 relative">

<div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm"></div>

<?php include 'sidebar.php'; ?>

<main class="flex-1 min-h-screen w-full overflow-x-hidden">

    <!-- Header -->
    <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-4 lg:px-8 py-4 flex items-center gap-4">
        <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="flex-1">
            <h2 class="text-xl font-bold text-white">Test Natijalari</h2>
            <p class="text-xs text-slate-500">/oqish tizimida topshirilgan barcha test natijalari</p>
        </div>
        <a href="results.php" class="text-xs text-slate-400 hover:text-cyan-400 transition flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Tozalash
        </a>
    </header>

    <div class="p-4 lg:p-8">

        <!-- STATISTIKA -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="glass-card rounded-xl p-4">
                <p class="text-xs text-slate-500 mb-1">Jami urinishlar</p>
                <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total']); ?></p>
            </div>
            <div class="glass-card rounded-xl p-4 border-emerald-500/20">
                <p class="text-xs text-slate-500 mb-1">Muvaffaqiyatli</p>
                <p class="text-2xl font-bold text-emerald-400"><?php echo number_format($stats['passed']); ?></p>
                <?php if ($stats['total'] > 0): ?>
                <p class="text-xs text-slate-600 mt-0.5"><?php echo round($stats['passed'] / $stats['total'] * 100); ?>%</p>
                <?php endif; ?>
            </div>
            <div class="glass-card rounded-xl p-4 border-red-500/20">
                <p class="text-xs text-slate-500 mb-1">Muvaffaqiyatsiz</p>
                <p class="text-2xl font-bold text-red-400"><?php echo number_format($stats['failed']); ?></p>
                <?php if ($stats['total'] > 0): ?>
                <p class="text-xs text-slate-600 mt-0.5"><?php echo round($stats['failed'] / $stats['total'] * 100); ?>%</p>
                <?php endif; ?>
            </div>
            <div class="glass-card rounded-xl p-4 border-cyan-500/20">
                <p class="text-xs text-slate-500 mb-1">O'rtacha ball</p>
                <p class="text-2xl font-bold text-cyan-400"><?php echo $stats['avg']; ?>%</p>
            </div>
        </div>

        <!-- FILTERLAR -->
        <form method="GET" class="glass-card rounded-xl p-4 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Qidiruv -->
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Ism yoki modul..."
                        class="w-full bg-slate-800 border border-slate-700 rounded-lg pl-9 pr-3 py-2 text-sm text-white focus:border-cyan-500 outline-none">
                </div>
                <!-- Modul -->
                <select name="module_id" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-cyan-500 outline-none">
                    <option value="">Barcha modullar</option>
                    <?php foreach ($modules as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo $filterModule == $m['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['code'] . ' — ' . $m['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <!-- Status -->
                <select name="status" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-cyan-500 outline-none">
                    <option value="">Barcha statuslar</option>
                    <option value="passed"  <?php echo $filterStatus === 'passed'  ? 'selected' : ''; ?>>✅ Muvaffaqiyatli</option>
                    <option value="failed"  <?php echo $filterStatus === 'failed'  ? 'selected' : ''; ?>>❌ Muvaffaqiyatsiz</option>
                </select>
                <!-- Foydalanuvchi -->
                <select name="user_id" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-cyan-500 outline-none">
                    <option value="">Barcha foydalanuvchilar</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $filterUser == $u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['full_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Qidirish
                </button>
                <a href="results.php" class="bg-slate-700 hover:bg-slate-600 text-slate-300 px-4 py-2 rounded-lg text-sm transition">
                    Tozalash
                </a>
            </div>
        </form>

        <!-- JADVAL -->
        <?php if (empty($results)): ?>
        <div class="glass-card rounded-xl p-16 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-slate-400 text-lg font-medium">Natijalar topilmadi</p>
            <p class="text-slate-600 text-sm mt-1">Hali hech kim test topshirmagan yoki filter bo'yicha natija yo'q</p>
        </div>
        <?php else: ?>

        <div class="glass-card rounded-xl overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
                <p class="text-sm text-slate-400">
                    Jami <span class="text-white font-semibold"><?php echo number_format($totalRows); ?></span> ta natija
                    <?php if ($totalPages > 1): ?>
                    — <?php echo $page; ?>/<?php echo $totalPages; ?> sahifa
                    <?php endif; ?>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/60 text-xs text-slate-400 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Foydalanuvchi</th>
                            <th class="px-4 py-3 text-left">Modul</th>
                            <th class="px-4 py-3 text-center">Ball</th>
                            <th class="px-4 py-3 text-center">To'g'ri/Jami</th>
                            <th class="px-4 py-3 text-center">O'tish balli</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-left">Sana</th>
                            <th class="px-4 py-3 text-left">Keyingi urinish</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($results as $i => $r):
                            $isPassed    = $r['status'] === 'passed';
                            $rowNum      = $offset + $i + 1;
                            $isBlocked   = !$isPassed && $r['next_allowed_at'] && strtotime($r['next_allowed_at']) > time();
                        ?>
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="px-4 py-3 text-slate-600 text-xs"><?php echo $rowNum; ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-cyan-500/20 flex items-center justify-center text-xs font-bold text-cyan-400 flex-shrink-0">
                                        <?php echo mb_strtoupper(mb_substr($r['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-white text-xs"><?php echo htmlspecialchars($r['full_name']); ?></p>
                                        <p class="text-slate-500 text-[10px]"><?php echo htmlspecialchars($r['position_name'] ?? '—'); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-white text-xs font-medium"><?php echo htmlspecialchars($r['module_title']); ?></p>
                                <p class="text-slate-500 text-[10px] font-mono"><?php echo htmlspecialchars($r['module_code']); ?></p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-lg font-bold <?php echo $isPassed ? 'text-emerald-400' : 'text-red-400'; ?>">
                                    <?php echo $r['score']; ?>%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-slate-300">
                                <span class="text-emerald-400 font-semibold"><?php echo $r['correct_count']; ?></span>
                                <span class="text-slate-600">/</span>
                                <span><?php echo $r['total_count']; ?></span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-slate-400">
                                <?php echo $r['passing_percent']; ?>%
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($isPassed): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    O'tdi
                                </span>
                                <?php elseif ($isBlocked): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold bg-orange-500/10 text-orange-400 border border-orange-500/20">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                    Bloklangan
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    O'tmadi
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                                <?php echo date('d.m.Y', strtotime($r['attempted_at'])); ?>
                                <span class="text-slate-600 block"><?php echo date('H:i', strtotime($r['attempted_at'])); ?></span>
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <?php if ($isPassed): ?>
                                <span class="text-slate-600">—</span>
                                <?php elseif ($r['next_allowed_at']): ?>
                                    <?php if ($isBlocked): ?>
                                    <span class="text-orange-400"><?php echo date('d.m.Y H:i', strtotime($r['next_allowed_at'])); ?></span>
                                    <?php else: ?>
                                    <span class="text-emerald-400">Ochiq</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                <span class="text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2">
            <?php if ($page > 1): ?>
            <a href="<?php echo buildUrl(['page' => $page - 1]); ?>" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm transition">
                ← Oldingi
            </a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($p = $start; $p <= $end; $p++):
            ?>
            <a href="<?php echo buildUrl(['page' => $p]); ?>"
               class="px-3 py-2 rounded-lg text-sm transition <?php echo $p === $page ? 'bg-cyan-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-300'; ?>">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="<?php echo buildUrl(['page' => $page + 1]); ?>" class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm transition">
                Keyingi →
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<script>
function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        backdrop.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        backdrop.classList.add('hidden');
    }
}
</script>
</body>
</html>
