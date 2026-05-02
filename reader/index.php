<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Tashkent');
require_once '../db.php';
requireLogin();
 $user = getCurrentUser();

// --- BACKEND LOGIC (STATISTIKA VA MA'LUMOTLAR) ---

// 1. Asosiy statistika
try {
    $stats = [
        'total_employees' => $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn() ?? 0,
        'active_modules' => $pdo->query("SELECT COUNT(*) FROM training_modules")->fetchColumn() ?? 0,
        'pending_trainings' => $pdo->query("
            SELECT COUNT(*) FROM training_assignments 
            WHERE status = 'pending' AND due_date >= CURDATE()
        ")->fetchColumn() ?? 0,
        'overdue_trainings' => $pdo->query("
            SELECT COUNT(*) FROM training_assignments 
            WHERE status = 'pending' AND due_date < CURDATE()
        ")->fetchColumn() ?? 0,
    ];
} catch (Exception $e) {
    // Jadvalar hali yaratilmagan bo'lishi mumkin
    $stats = ['total_employees' => 0, 'active_modules' => 0, 'pending_trainings' => 0, 'overdue_trainings' => 0];
}

// 2. Muddati yaqinlashayotgan treninglar (Top 5)
try {
    $upcoming = $pdo->query("
        SELECT ta.*, e.full_name, tm.title 
        FROM training_assignments ta
        JOIN employees e ON ta.employee_id = e.id
        JOIN training_modules tm ON ta.module_id = tm.id
        WHERE ta.status IN ('pending', 'failed') 
        AND ta.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY ta.due_date ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $upcoming = []; }

// 3. So'nggi test natijalari (Top 5)
try {
    $recentTests = $pdo->query("
        SELECT 
            ta.score_percent AS score,
            ta.status,
            ta.completed_at,
            tt.employee_name AS full_name,
            t.name AS module_title,
            t.passing_percent AS pass_score
        FROM training_attempts ta
        JOIN training_tokens tt ON ta.token_id = tt.id
        JOIN trainings t ON tt.training_id = t.id
        ORDER BY ta.completed_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $recentTests = []; }

// 4. Bo'limlar bo'yicha xodimlar soni (Piyogram yoki Progress bar uchun)
try {
    $deptStats = $pdo->query("
        SELECT d.name, COUNT(e.id) as emp_count 
        FROM departments d 
        LEFT JOIN employees e ON d.id = e.department_id 
        GROUP BY d.id 
        ORDER BY emp_count DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $deptStats = []; }
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
        :root { 
            --bg-primary: #0a0f1a; 
            --accent-cyan: #06b6d4; 
            --glass-bg: rgba(26, 35, 50, 0.7); 
            --glass-border: rgba(51, 65, 85, 0.5); 
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); color: #f1f5f9; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Glassmorphism */
        .glass-card { 
            background: var(--glass-bg); 
            backdrop-filter: blur(12px); 
            border: 1px solid var(--glass-border); 
        }

        /* Animations */
        .pulse-red { animation: pulse-red 2s infinite; }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
    </style>
</head>
<body class="min-h-screen flex text-slate-100 relative">

    <!-- 1. SIDEBARNI CHAQIRISH -->
    <?php include 'sidebar.php'; ?>

    <!-- Mobil fon (Backdrop) -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity"></div>

    <!-- Main Content -->
    <main class="flex-1 min-h-screen w-full">
        
        <!-- Mobil Header -->
        <header class="lg:hidden sticky top-0 z-30 bg-slate-900 border-b border-slate-800 p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-slate-300 hover:text-white p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <span class="font-bold text-lg text-cyan-400">Dashboard</span>
            </div>
            <div class="w-8 h-8 rounded-full bg-slate-700"></div>
        </header>

        <!-- Desktop Header -->
        <header class="hidden lg:flex sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-8 py-6 justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-400">Bosh Panel</h2>
                <p class="text-sm text-slate-500 mt-1">Xush kelibsiz, <?php echo htmlspecialchars($user['full_name']); ?>! Bugun tizim holati yaxshi.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="employees.php" class="text-sm font-medium text-slate-400 hover:text-cyan-400 transition">Batafsil hisobotlar &rarr;</a>
            </div>
        </header>

        <div class="p-6 lg:p-8">
            
            <!-- STATISTIKA KARTALARI -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- 1. Jami Xodimlar -->
                <div class="glass-card p-6 rounded-xl border-l-4 border-cyan-500 fade-in-up hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Jami Xodimlar</p>
                            <h3 class="text-3xl font-bold mt-2 text-white"><?php echo number_format($stats['total_employees']); ?></h3>
                        </div>
                        <div class="p-3 bg-cyan-500/20 rounded-lg text-cyan-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-green-400">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        <span>Faol holatda</span>
                    </div>
                </div>

                <!-- 2. Faol Modullar -->
                <div class="glass-card p-6 rounded-xl border-l-4 border-blue-500 fade-in-up delay-100 hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Trening Modullari</p>
                            <h3 class="text-3xl font-bold mt-2 text-white"><?php echo number_format($stats['active_modules']); ?></h3>
                        </div>
                        <div class="p-3 bg-blue-500/20 rounded-lg text-blue-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-slate-400">
                        <span>Jami kutubxona</span>
                    </div>
                </div>

                <!-- 3. Kutilmoqda -->
                <div class="glass-card p-6 rounded-xl border-l-4 border-amber-500 fade-in-up delay-200 hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Jarayonda</p>
                            <h3 class="text-3xl font-bold mt-2 text-white"><?php echo number_format($stats['pending_trainings']); ?></h3>
                        </div>
                        <div class="p-3 bg-amber-500/20 rounded-lg text-amber-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-amber-400">
                        <span>Tez orada tugashi kerak</span>
                    </div>
                </div>

                <!-- 4. Muddati o'tgan -->
                <div class="glass-card p-6 rounded-xl border-l-4 border-red-500 fade-in-up delay-300 hover:-translate-y-1 transition-transform duration-300 <?php echo $stats['overdue_trainings'] > 0 ? 'pulse-red' : ''; ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Muddati o'tgan</p>
                            <h3 class="text-3xl font-bold mt-2 text-white"><?php echo number_format($stats['overdue_trainings']); ?></h3>
                        </div>
                        <div class="p-3 bg-red-500/20 rounded-lg text-red-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-red-400">
                        <?php if($stats['overdue_trainings'] > 0): ?>
                            <span>E'tibor bering! Xodimlarni eslatish kerak.</span>
                        <?php else: ?>
                            <span class="text-emerald-400">Hammasi joyida.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ASOSIY KONTENT (GRID) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Chap tomon: So'nggi testlar (2/3 kenglik) -->
                <div class="lg:col-span-2 glass-card rounded-xl p-6 fade-in-up delay-200">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold flex items-center gap-2">
                            <span class="w-1 h-6 bg-cyan-500 rounded-full"></span>
                            So'nggi Test Natijalari
                        </h3>
                        <a href="results.php" class="text-xs text-cyan-400 hover:text-cyan-300">Barchasini ko'rish</a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-slate-400 uppercase bg-slate-800/50">
                                <tr>
                                    <th class="px-4 py-3 rounded-l-lg">Xodim</th>
                                    <th class="px-4 py-3">Modul</th>
                                    <th class="px-4 py-3 text-center">Ball</th>
                                    <th class="px-4 py-3 rounded-r-lg text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php if(empty($recentTests)): ?>
                                    <tr><td colspan="4" class="py-8 text-center text-slate-500">Hali test topshirilmagan</td></tr>
                                <?php else: ?>
                                    <?php foreach($recentTests as $test): 
                                        $isPass = $test['score'] >= ($test['pass_score'] ?? 80);
                                        $statusClass = $isPass ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20' : 'text-red-400 bg-red-400/10 border-red-400/20';
                                        $statusText = $isPass ? 'Muvaffaqiyatli' : 'Qoniqarli emas';
                                    ?>
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="px-4 py-3 font-medium text-white"><?php echo htmlspecialchars($test['full_name']); ?></td>
                                        <td class="px-4 py-3 text-slate-400"><?php echo htmlspecialchars($test['module_title']); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="inline-block px-2 py-1 rounded bg-slate-800 font-mono text-white">
                                                <?php echo $test['score']; ?>%
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="px-2 py-1 rounded-full text-xs border <?php echo $statusClass; ?>">
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

                <!-- O'ng tomon: Muddati yaqinlashmoqda (1/3 kenglik) -->
                <div class="lg:col-span-1 glass-card rounded-xl p-6 fade-in-up delay-300 flex flex-col">
                    <div class="mb-6">
                        <h3 class="text-lg font-bold flex items-center gap-2">
                            <span class="w-1 h-6 bg-amber-500 rounded-full"></span>
                            Muddati Tugash Arafasida
                        </h3>
                    </div>

                    <div class="flex-1 space-y-4">
                        <?php if(empty($upcoming)): ?>
                            <div class="flex flex-col items-center justify-center h-40 text-slate-500 text-sm">
                                <svg class="w-10 h-10 mb-2 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p>Barcha treninglar vaqtida</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($upcoming as $item): ?>
                                <div class="group p-3 rounded-lg bg-slate-800/40 border border-slate-700 hover:border-amber-500/50 transition flex gap-3">
                                    <div class="flex-shrink-0 w-12 text-center pt-1">
                                        <div class="text-xs text-amber-400 font-bold uppercase"><?php echo date('M', strtotime($item['due_date'])); ?></div>
                                        <div class="text-lg font-bold text-white leading-none"><?php echo date('d', strtotime($item['due_date'])); ?></div>
                                    </div>
                                    <div class="overflow-hidden">
                                        <p class="text-sm font-medium text-white truncate group-hover:text-cyan-400 transition"><?php echo htmlspecialchars($item['title']); ?></p>
                                        <p class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($item['full_name']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-slate-700">
                         <a href="results.php" class="block w-full text-center text-xs text-slate-400 hover:text-white py-2">
                            Jami taqvimni ko'rish
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bo'limlar bo'yicha oddiy statistika -->
            <div class="glass-card rounded-xl p-6 fade-in-up delay-400">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <span class="w-1 h-6 bg-purple-500 rounded-full"></span>
                    Bo'limlar bo'yicha xodimlar taqsimoti
                </h3>
                <?php if(!empty($deptStats)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <?php foreach($deptStats as $stat): ?>
                            <div class="bg-slate-800/50 rounded-lg p-4 flex items-center justify-between border border-slate-700">
                                <span class="text-sm text-slate-300 truncate"><?php echo htmlspecialchars($stat['name']); ?></span>
                                <span class="bg-slate-700 text-cyan-400 text-xs font-bold px-2 py-1 rounded-full">
                                    <?php echo $stat['emp_count']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 text-sm">Ma'lumot topilmadi.</p>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- RESPONSIVE LOGIC -->
    <script>
        // Sidebar toggle funksiyasi (sidebar.php bilan mos keladi)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                // Ochish
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
            } else {
                // Yopish
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                backdrop.classList.add('hidden');
            }
        }
    </script>
</body>
</html>