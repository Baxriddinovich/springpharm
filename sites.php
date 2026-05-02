<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Tashkent');

require_once 'db.php';
requireLogin();

 $user = getCurrentUser();

// Faqat super_admin va bosh_auditor boshqarishi mumkin
if (!in_array($user['role'], ['super_admin', 'bosh_auditor'])) {
    header("Location: audits.php");
    exit;
}

 $message = '';
 $messageType = 'success';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
 $csrfToken = $_SESSION['csrf_token'];

function verifyCsrf(): bool {
    return isset($_POST['csrf_token']) 
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

// ----------------- PHP LOGIC -----------------

// 1. Korxona qo'shish / Tahrirlash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrf()) {
        $message = "Xavfsizlik xatosi!";
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];

        // CREATE
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($name !== '') {
                $stmt = $pdo->prepare("INSERT INTO sites (name, address, is_active) VALUES (?, ?, 1)");
                $stmt->execute([$name, $address]);
                $message = "Korxona muvaffaqiyatli qo'shildi!";
                $messageType = 'success';
            } else {
                $message = "Korxona nomini kiriting!";
                $messageType = 'danger';
            }
        }

        // UPDATE
        if ($action === 'update') {
            $id = (int)($_POST['site_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0 && $name !== '') {
                $stmt = $pdo->prepare("UPDATE sites SET name = ?, address = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $address, $isActive, $id]);
                $message = "Ma'lumotlar yangilandi!";
                $messageType = 'success';
            } else {
                $message = "Ma'lumotlar to'liq emas!";
                $messageType = 'danger';
            }
        }

        // DELETE
        if ($action === 'delete') {
            $id = (int)($_POST['site_id'] ?? 0);
            if ($id > 0) {
                // Bog'liq auditlar bor-yo'qligini tekshirish
                $check = $pdo->prepare("SELECT COUNT(*) FROM audits WHERE site_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    // Agar audit bo'lsa, faqat faol emasligini belgilash (Soft Delete)
                    $pdo->prepare("UPDATE sites SET is_active = 0 WHERE id = ?")->execute([$id]);
                    $message = "Korxonada auditlar mavjud. Korxona 'Nofaol' ga o'tkazildi!";
                    $messageType = 'warning';
                } else {
                    // Audit yo'q bo'lsa, butunlay o'chirish
                    $pdo->prepare("DELETE FROM sites WHERE id = ?")->execute([$id]);
                    $message = "Korxona butunlay o'chirildi!";
                    $messageType = 'success';
                }
            }
        }
    }
}

// Korxonalar ro'yxati
 $sites = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM audits a WHERE a.site_id = s.id) as audit_count FROM sites s ORDER BY s.is_active DESC, s.name ASC")->fetchAll();

?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Korxonalar - GMP Audit Tizimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        .btn-primary {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(6, 182, 212, 0.4); }
        
        .badge { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .modal-backdrop { background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); animation: fadeIn 0.2s ease; }
        .modal-content {
            background: linear-gradient(135deg, #1a2332 0%, #111827 100%);
            border: 1px solid rgba(51, 65, 85, 0.5);
            animation: modalIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    </style>
</head>
<body class="min-h-screen text-slate-100">
    
    <!-- Mobile Header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">
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
    
    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <div class="flex min-h-screen pt-14 lg:pt-0">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 fixed h-full z-50" role="navigation">
            <div class="p-6 h-full flex flex-col">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                        <p class="text-xs text-slate-500 font-mono">v2.0 Pro</p>
                    </div>
                </div>
                
                <nav class="space-y-1 flex-1 overflow-y-auto">
                    <a href="dashboard.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Bosh panel
                    </a>
                    <a href="audits.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Auditlar
                    </a>
                    
                    <!-- Bu yerda Korxonalar aktiv bo'ladi -->
                    <a href="sites.php" class="nav-item active flex items-center gap-3 px-4 py-3 rounded-lg text-white" aria-current="page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Korxonalar
                    </a>

                    <a href="reports.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Hisobotlar
                    </a>
                    
                    <div class="pt-4 mt-4 border-t border-slate-700/50">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>
                        <a href="sections.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Bo'limlar
                        </a>
                        <a href="checklists.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Checklistlar
                        </a>
                        <a href="users.php" class="nav-item flex items-center gap-3 px-4 py-3 rounded-lg text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            Auditorlar
                        </a>
                    </div>
                </nav>
                
                <!-- User Info -->
                <div class="border-t border-slate-700/50 pt-4 mt-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center text-white font-semibold text-sm">
                            <?php echo strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
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
            <!-- Header -->
            <header class="sticky top-0 z-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50">
                <div class="px-4 lg:px-8 py-4 flex justify-between items-center">
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold text-white">Korxonalar</h1>
                        <p class="text-slate-500 text-sm">Audit o'tkaziladigan ob'ektlar ro'yxati</p>
                    </div>
                    <button onclick="openModal('create')" class="flex items-center gap-2 btn-primary px-4 py-2.5 rounded-xl font-medium text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="hidden sm:inline">Yangi Korxona</span>
                    </button>
                </div>
            </header>
            
            <div class="p-4 lg:p-8">
                <!-- Message -->
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl border animate-fade <?php echo $messageType === 'success' 
                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' 
                    : 'bg-red-500/10 border-red-500/30 text-red-300'; ?>">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <?php if ($messageType === 'success'): ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            <?php else: ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            <?php endif; ?>
                        </svg>
                        <span><?php echo $message; ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sites List -->
                <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($sites as $site): ?>
                    <div class="stat-card rounded-2xl p-5 flex flex-col">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-cyan-500/10 flex items-center justify-center text-cyan-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-white"><?php echo htmlspecialchars($site['name']); ?></h3>
                                    <span class="badge <?php echo $site['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $site['is_active'] ? 'Faol' : 'Nofaol'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($site['address'])): ?>
                        <p class="text-slate-400 text-sm mb-3 flex-1">
                            <svg class="w-4 h-4 inline mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <?php echo htmlspecialchars($site['address']); ?>
                        </p>
                        <?php else: ?>
                        <p class="text-slate-600 text-sm mb-3 italic flex-1">Manzil kiritilmagan</p>
                        <?php endif; ?>

                        <div class="text-xs text-slate-500 mb-3">
                            Bog'langan auditlar: <?php echo $site['audit_count']; ?> ta
                        </div>

                        <div class="flex gap-2 mt-auto border-t border-slate-700/50 pt-3">
                            <button onclick='openModal("edit", <?php echo json_encode($site); ?>)' class="flex-1 flex items-center justify-center gap-1 px-3 py-2 rounded-lg bg-slate-700/30 hover:bg-slate-700/50 text-slate-300 text-sm transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Tahrirlash
                            </button>
                            <form method="POST" action="" onsubmit="return confirm('Rostdan ochirmoqchimisiz?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <button type="submit" class="flex items-center justify-center gap-1 px-3 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    O'chirish
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($sites)): ?>
                    <div class="col-span-full text-center py-16">
                        <svg class="w-16 h-16 text-slate-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <p class="text-slate-500">Korxonalar mavjud emas</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div id="siteModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="modal-backdrop absolute inset-0" onclick="closeModal()"></div>
        <div class="modal-content relative rounded-2xl p-6 max-w-md w-full mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 id="modalTitle" class="text-xl font-bold text-white">Yangi Korxona</h3>
                <button onclick="closeModal()" class="text-slate-500 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="site_id" id="modalSiteId" value="">

                <div class="space-y-4">
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-2">Korxona nomi <span class="text-red-400">*</span></label>
                        <input type="text" name="name" id="modalName" class="input-field w-full px-4 py-3 rounded-xl" placeholder="Masalan: 'Bio Active' MChJ" required>
                    </div>
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-2">Manzil</label>
                        <textarea name="address" id="modalAddress" rows="2" class="input-field w-full px-4 py-3 rounded-xl" placeholder="To'liq manzilni kiriting"></textarea>
                    </div>
                    
                    <!-- Faollik holati (faqat editda ko'rinadi) -->
                    <div id="statusField" class="hidden">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_active" id="modalStatus" checked class="w-5 h-5 rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-slate-800">
                            <span class="text-slate-300">Korxona faol</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 mt-8">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-3 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700/50 transition-all">Bekor qilish</button>
                    <button type="submit" class="flex-1 px-4 py-3 rounded-xl btn-primary text-white font-medium transition-all">Saqlash</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        function openModal(type, data = null) {
            const modal = document.getElementById('siteModal');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('modalAction');
            const siteId = document.getElementById('modalSiteId');
            const nameInput = document.getElementById('modalName');
            const addressInput = document.getElementById('modalAddress');
            const statusField = document.getElementById('statusField');
            const statusInput = document.getElementById('modalStatus');

            // Reset
            nameInput.value = '';
            addressInput.value = '';
            statusInput.checked = true;
            statusField.classList.add('hidden');

            if (type === 'create') {
                title.textContent = 'Yangi Korxona Qo\'shish';
                action.value = 'create';
            } else if (type === 'edit' && data) {
                title.textContent = 'Korxonani Tahrirlash';
                action.value = 'update';
                siteId.value = data.id;
                nameInput.value = data.name;
                addressInput.value = data.address || '';
                statusInput.checked = data.is_active == 1;
                statusField.classList.remove('hidden');
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            const modal = document.getElementById('siteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>