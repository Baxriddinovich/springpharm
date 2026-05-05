<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Tashkent');
require_once '../db.php';
requireLogin();
$user = getCurrentUser();

$selectedName = isset($_GET['employee_name']) ? trim($_GET['employee_name']) : null;
$results = [];
$summary = null;

try {
    if ($selectedName) {
        $detailStmt = $pdo->prepare("SELECT 
            ta.score_percent AS score,
            ta.status,
            ta.completed_at,
            tt.employee_name AS full_name,
            t.name AS module_title,
            t.passing_percent AS pass_score
        FROM training_attempts ta
        JOIN training_tokens tt ON ta.token_id = tt.id
        JOIN trainings t ON tt.training_id = t.id
        WHERE tt.employee_name = ?
        ORDER BY ta.completed_at DESC");
        $detailStmt->execute([$selectedName]);
        $results = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

        $summaryStmt = $pdo->prepare("SELECT 
            COUNT(*) AS attempts,
            AVG(ta.score_percent) AS average_score,
            MAX(ta.completed_at) AS last_date,
            SUM(ta.status = 'passed') AS passed_count,
            SUM(ta.status != 'passed') AS failed_count
        FROM training_attempts ta
        JOIN training_tokens tt ON ta.token_id = tt.id
        WHERE tt.employee_name = ?");
        $summaryStmt->execute([$selectedName]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $listStmt = $pdo->query("SELECT 
            tt.employee_name AS full_name,
            COUNT(*) AS attempts,
            AVG(ta.score_percent) AS average_score,
            MAX(ta.completed_at) AS last_date,
            SUM(ta.status = 'passed') AS passed_count
        FROM training_attempts ta
        JOIN training_tokens tt ON ta.token_id = tt.id
        GROUP BY tt.employee_name
        ORDER BY MAX(ta.completed_at) DESC");
        $results = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $results = [];
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Natijalar - GMP Learning</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0f1a;
            --glass-bg: rgba(26,35,50,0.72);
            --glass-border: rgba(51,65,85,0.5);
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); color: #f8fafc; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); }
        .nav-item { transition: all .2s ease; border-left: 3px solid transparent; }
        .nav-item.active, .nav-item:hover { background: rgba(6,182,212,.1); border-left-color: #06b6d4; color: #fff; }
    </style>
</head>
<body class="min-h-screen flex text-slate-100 relative">
    <?php include 'sidebar.php'; ?>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity"></div>
    <main class="flex-1 min-h-screen w-full">
        <header class="hidden lg:flex sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-8 py-5 justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold">Test Natijalari</h2>
                <p class="text-sm text-slate-400 mt-1">Har bir F.I.Sh uchun test natijalari yagona satrda ko'rsatiladi.</p>
            </div>
            <?php if ($selectedName): ?>
                <a href="results.php" class="text-sm text-cyan-400 hover:text-cyan-300">Barcha natijalar ro'yxatini ko'rish</a>
            <?php endif; ?>
        </header>

        <div class="p-6 lg:p-8">
            <div class="glass-card rounded-xl p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold text-white">Natijalar</h1>
                        <p class="text-sm text-slate-400 mt-1">F.I.Sh ustiga bosilganda shu nomga tegishli barcha testlar ochiladi.</p>
                    </div>
                    <?php if ($selectedName && $summary): ?>
                        <div class="rounded-2xl bg-slate-800/80 border border-slate-700 p-4 space-y-2 text-sm">
                            <div class="text-slate-400">Tanlangan xodim:</div>
                            <div class="text-white font-semibold"><?php echo htmlspecialchars($selectedName); ?></div>
                            <div class="grid grid-cols-2 gap-3 mt-3 text-xs text-slate-400">
                                <div class="bg-slate-900/80 p-3 rounded-xl">
                                    <div class="text-slate-400">Testlar soni</div>
                                    <div class="text-white font-semibold"><?php echo (int)$summary['attempts']; ?></div>
                                </div>
                                <div class="bg-slate-900/80 p-3 rounded-xl">
                                    <div class="text-slate-400">O'rtacha ball</div>
                                    <div class="text-white font-semibold"><?php echo $summary['average_score'] !== null ? round($summary['average_score'], 1) . '%' : '—'; ?></div>
                                </div>
                                <div class="bg-slate-900/80 p-3 rounded-xl">
                                    <div class="text-slate-400">O'tganlari</div>
                                    <div class="text-white font-semibold"><?php echo (int)$summary['passed_count']; ?></div>
                                </div>
                                <div class="bg-slate-900/80 p-3 rounded-xl">
                                    <div class="text-slate-400">Oxirgi topshirilgan</div>
                                    <div class="text-white font-semibold"><?php echo $summary['last_date'] ? date('d.m.Y H:i', strtotime($summary['last_date'])) : '—'; ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedName): ?>
                <div class="glass-card rounded-xl overflow-hidden p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($selectedName); ?> uchun barcha test natijalari</h3>
                            <p class="text-sm text-slate-400">Barcha modul bo'yicha topshirilgan testlar.</p>
                        </div>
                        <a href="results.php" class="inline-flex items-center gap-2 text-sm text-cyan-400 hover:text-cyan-300">← Barcha ismlar</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead class="bg-slate-800/70 text-slate-400 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">Modul</th>
                                    <th class="px-4 py-3">Ball</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Sanasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php if (empty($results)): ?>
                                    <tr><td colspan="4" class="py-8 text-center text-slate-500">Bu nom bo'yicha natija topilmadi.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($results as $row):
                                        $isPassed = strtolower($row['status']) === 'passed' || strtolower($row['status']) === 'success';
                                        $statusClass = $isPassed ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20' : 'text-red-400 bg-red-400/10 border-red-400/20';
                                        $statusText = $isPassed ? "O'tgan" : 'Qoniqarsiz';
                                    ?>
                                        <tr class="hover:bg-slate-800/40 transition">
                                            <td class="px-4 py-3 text-slate-100"><?php echo htmlspecialchars($row['module_title']); ?></td>
                                            <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-1 rounded bg-slate-900 text-white font-medium"><?php echo $row['score']; ?>%</span></td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs border <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-slate-400"><?php echo $row['completed_at'] ? date('d.m.Y H:i', strtotime($row['completed_at'])) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="glass-card rounded-xl overflow-hidden p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-white">F.I.Sh bo'yicha umumiy natijalar</h3>
                            <p class="text-sm text-slate-400">Ayni damdagi barcha xodimlar test natijalari.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead class="bg-slate-800/70 text-slate-400 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">F.I.Sh</th>
                                    <th class="px-4 py-3">Testlar soni</th>
                                    <th class="px-4 py-3">O'rtacha ball</th>
                                    <th class="px-4 py-3">Oxirgi sanasi</th>
                                    <th class="px-4 py-3">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php if (empty($results)): ?>
                                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Hozircha hech qanday natija mavjud emas.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($results as $row):
                                        $avgScore = $row['average_score'] !== null ? round($row['average_score'], 1) : 0;
                                        $statusClass = $avgScore >= 80 ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20' : 'text-amber-400 bg-amber-400/10 border-amber-400/20';
                                        $statusText = $avgScore >= 80 ? 'Yaxshi' : "Ehtiyot bo'lish kerak";
                                    ?>
                                        <tr class="hover:bg-slate-800/40 transition">
                                            <td class="px-4 py-3 font-medium text-white">
                                                <a href="results.php?employee_name=<?php echo urlencode($row['full_name']); ?>" class="hover:text-cyan-300 transition">
                                                    <?php echo htmlspecialchars($row['full_name']); ?>
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-slate-200"><?php echo (int)$row['attempts']; ?></td>
                                            <td class="px-4 py-3 text-slate-200"><?php echo $avgScore; ?>%</td>
                                            <td class="px-4 py-3 text-slate-400"><?php echo $row['last_date'] ? date('d.m.Y H:i', strtotime($row['last_date'])) : '-'; ?></td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs border <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
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
