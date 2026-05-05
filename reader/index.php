<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Tashkent');
require_once '../db.php';
requireLogin();
$user = getCurrentUser();

// ── STATISTIKA ──
try {
    $stats = [
        'total_users'    => $pdo->query("SELECT COUNT(*) FROM users WHERE role='reader' AND is_active=1")->fetchColumn() ?? 0,
        'active_modules' => $pdo->query("SELECT COUNT(*) FROM training_modules WHERE status='active'")->fetchColumn() ?? 0,
        'total_attempts' => $pdo->query("SELECT COUNT(*) FROM reader_test_attempts")->fetchColumn() ?? 0,
        'passed_attempts'=> $pdo->query("SELECT COUNT(*) FROM reader_test_attempts WHERE status='passed'")->fetchColumn() ?? 0,
    ];
} catch (Exception $e) {
    $stats = ['total_users' => 0, 'active_modules' => 0, 'total_attempts' => 0, 'passed_attempts' => 0];
}

// ── SO'NGGI TEST NATIJALARI ──
try {
    $recentTests = $pdo->query("
        SELECT rta.score, rta.status, rta.attempted_at,
               u.full_name, tm.title AS module_title,
               rta.passing_percent AS pass_score
        FROM reader_test_attempts rta
        JOIN users u ON rta.user_id = u.id
        JOIN training_modules tm ON rta.module_id = tm.id
        ORDER BY rta.attempted_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $recentTests = []; }

// ── LAVOZIMLAR BO'YICHA XODIMLAR ──
try {
    $posStats = $pdo->query("
        SELECT p.name, COUNT(u.id) as emp_count
        FROM positions p
        LEFT JOIN users u ON u.position_id = p.id AND u.role='reader' AND u.is_active=1
        GROUP BY p.id, p.name
        ORDER BY emp_count DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $posStats = []; }

// ── TIZIM TARIXI (activity_logs) ──
try {
    $activityLogs = $pdo->query("
        SELECT al.action_type, al.details, al.created_at, u.full_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 15
    ")->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $activityLogs = []; }

// ── TIZIM TARIXI UCHUN ICON VA RANG ──
function activityIcon(string $type): array {
    $map = [
        'material_viewed'  => ['bg-cyan-500/10 text-cyan-400',    '👁'],
        'module_completed' => ['bg-emerald-500/10 text-emerald-400','✓'],
        'test_submitted'   => ['bg-blue-500/10 text-blue-400',     '📝'],
        'matrix_add'       => ['bg-violet-500/10 text-violet-400', '➕'],
        'matrix_remove'    => ['bg-red-500/10 text-red-400',       '➖'],
        'login'            => ['bg-amber-500/10 text-amber-400',   '🔑'],
        'user_added'       => ['bg-emerald-500/10 text-emerald-400','👤'],
        'user_edited'      => ['bg-cyan-500/10 text-cyan-400',     '✏️'],
        'user_deleted'     => ['bg-red-500/10 text-red-400',       '🗑'],
    ];
    return $map[$type] ?? ['bg-slate-700/50 text-slate-400', '•'];
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bosh Panel - GMP Learning</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary:#0a0f1a; --accent-cyan:#06b6d4; --glass-bg:rgba(26,35,50,0.7); --glass-border:rgba(51,65,85,0.5); }
        * { font-family:'Inter',sans-serif; }
        body { background:var(--bg-primary); color:#f1f5f9; }
        ::-webkit-scrollbar{width:6px} ::-webkit-scrollbar-track{background:#0f172a} ::-webkit-scrollbar-thumb{background:#334155;border-radius:4px}
        .glass-card { background:var(--glass-bg); backdrop-filter:blur(12px); border:1px solid var(--glass-border); }
        .fade-in { animation:fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .nav-item { transition:all 0.2s; border-left:3px solid transparent; }
        .nav-item:hover,.nav-item.active { background:rgba(6,182,212,0.1); border-left-color:var(--accent-cyan); color:#fff; }
        .progress-bar { background:rgba(6,182,212,0.15); border-radius:9999px; overflow:hidden; }
        .progress-fill { background:linear-gradient(90deg,#06b6d4,#14b8a6); border-radius:9999px; transition:width 0.6s ease; }
    </style>
</head>
<body class="min-h-screen flex text-slate-100 relative">

<?php include 'sidebar.php'; ?>
<div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm"></div>

<main class="flex-1 min-h-screen w-full">

    <!-- Header -->
    <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-4 lg:px-8 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div>
                <h2 class="text-xl font-bold text-white">Bosh Panel</h2>
                <p class="text-xs text-slate-500 hidden sm:block">Xush kelibsiz, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
            </div>
        </div>
        <a href="results.php" class="text-xs text-cyan-400 hover:text-cyan-300 transition">Barcha natijalar →</a>
    </header>

    <div class="p-4 lg:p-8">

        <!-- STAT CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 fade-in">
            <div class="glass-card rounded-xl p-5 border-l-4 border-cyan-500">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Faol xodimlar</p>
                <p class="text-3xl font-bold text-white"><?php echo $stats['total_users']; ?></p>
                <p class="text-xs text-cyan-400 mt-1">reader roli</p>
            </div>
            <div class="glass-card rounded-xl p-5 border-l-4 border-blue-500">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Faol modullar</p>
                <p class="text-3xl font-bold text-white"><?php echo $stats['active_modules']; ?></p>
                <p class="text-xs text-slate-400 mt-1">o'quv modullari</p>
            </div>
            <div class="glass-card rounded-xl p-5 border-l-4 border-emerald-500">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Muvaffaqiyatli</p>
                <p class="text-3xl font-bold text-emerald-400"><?php echo $stats['passed_attempts']; ?></p>
                <p class="text-xs text-slate-400 mt-1">test natijalari</p>
            </div>
            <div class="glass-card rounded-xl p-5 border-l-4 border-violet-500">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Jami urinishlar</p>
                <p class="text-3xl font-bold text-white"><?php echo $stats['total_attempts']; ?></p>
                <?php $pct = $stats['total_attempts'] > 0 ? round($stats['passed_attempts']/$stats['total_attempts']*100) : 0; ?>
                <p class="text-xs text-violet-400 mt-1"><?php echo $pct; ?>% o'tish darajasi</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- SO'NGGI TEST NATIJALARI -->
            <div class="lg:col-span-2 glass-card rounded-xl p-5 fade-in">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-white flex items-center gap-2">
                        <span class="w-1 h-5 bg-cyan-500 rounded-full"></span>
                        So'nggi Test Natijalari
                    </h3>
                    <a href="results.php" class="text-xs text-cyan-400 hover:text-cyan-300">Barchasini ko'rish</a>
                </div>
                <?php if (empty($recentTests)): ?>
                <div class="text-center py-10 text-slate-500 text-sm">Hali test topshirilmagan</div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-slate-500 uppercase">
                            <tr>
                                <th class="pb-3 text-left">Xodim</th>
                                <th class="pb-3 text-left">Modul</th>
                                <th class="pb-3 text-center">Ball</th>
                                <th class="pb-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($recentTests as $t):
                            $ok = $t['status'] === 'passed';
                        ?>
                        <tr class="hover:bg-slate-800/20 transition">
                            <td class="py-2.5 font-medium text-white text-xs"><?php echo htmlspecialchars($t['full_name']); ?></td>
                            <td class="py-2.5 text-slate-400 text-xs truncate max-w-[120px]"><?php echo htmlspecialchars($t['module_title']); ?></td>
                            <td class="py-2.5 text-center font-bold <?php echo $ok ? 'text-emerald-400' : 'text-red-400'; ?>"><?php echo $t['score']; ?>%</td>
                            <td class="py-2.5 text-right">
                                <?php if ($ok): ?>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">O'tdi</span>
                                <?php else: ?>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20">O'tmadi</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- LAVOZIMLAR BO'YICHA TAQSIMOT -->
            <div class="glass-card rounded-xl p-5 fade-in">
                <h3 class="font-bold text-white flex items-center gap-2 mb-4">
                    <span class="w-1 h-5 bg-violet-500 rounded-full"></span>
                    Lavozimlar bo'yicha xodimlar
                </h3>
                <?php if (empty($posStats)): ?>
                <div class="text-center py-10 text-slate-500 text-sm">Ma'lumot topilmadi</div>
                <?php else:
                    $maxCount = max(array_column($posStats, 'emp_count')) ?: 1;
                ?>
                <div class="space-y-3">
                <?php foreach ($posStats as $ps): ?>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-300 truncate"><?php echo htmlspecialchars($ps['name']); ?></span>
                            <span class="text-cyan-400 font-bold ml-2"><?php echo $ps['emp_count']; ?></span>
                        </div>
                        <div class="progress-bar h-1.5">
                            <div class="progress-fill h-full" style="width:<?php echo round($ps['emp_count']/$maxCount*100); ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TIZIM TARIXI -->
        <div class="glass-card rounded-xl p-5 fade-in">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-5 bg-amber-500 rounded-full"></span>
                    Tizim Tarixi
                </h3>
                <span class="text-xs text-slate-500">Oxirgi 15 ta amal</span>
            </div>
            <?php if (empty($activityLogs)): ?>
            <div class="text-center py-10 text-slate-500 text-sm">Hali hech qanday amal bajarilmagan</div>
            <?php else: ?>
            <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
            <?php foreach ($activityLogs as $log):
                [$cls, $icon] = activityIcon($log['action_type']);
            ?>
            <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-800/30 hover:bg-slate-800/50 transition">
                <div class="w-7 h-7 rounded-lg <?php echo $cls; ?> flex items-center justify-center text-sm flex-shrink-0 mt-0.5">
                    <?php echo $icon; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-slate-300 leading-relaxed"><?php echo htmlspecialchars($log['details'] ?: $log['action_type']); ?></p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] text-slate-600"><?php echo htmlspecialchars($log['full_name'] ?? 'Tizim'); ?></span>
                        <span class="text-[10px] text-slate-700">•</span>
                        <span class="text-[10px] text-slate-600"><?php echo date('d.m.Y H:i', strtotime($log['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

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
