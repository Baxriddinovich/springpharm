<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('Asia/Tashkent');
require_once '../db.php';
requireLogin(); // Sessiyani tekshirish
$user = getCurrentUser();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_user_position') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: employees.php");
        exit;
    }

    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $positionId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $isActive = isset($_POST['status']) ? 1 : 0;

    if ($userId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET position_id = ?, is_active = ? WHERE id = ? AND role = 'reader'");
        $stmt->execute([$positionId, $isActive, $userId]);
        $posName = $positionId ? $pdo->query("SELECT name FROM positions WHERE id=$positionId")->fetchColumn() : 'Belgilanmagan';
        logActivity('user_edited', "Xodim lavozimi o'zgartirildi (ID: $userId) → $posName", 'employees');
    }

    header("Location: employees.php");
    exit;
}

// --- LOGIKA (BACKEND) ---

// 1. Qidiruv va Filtrlash logikasi
$search = $_GET['search'] ?? '';
$dept_filter = $_GET['dept'] ?? 'all';

// WHERE shartlari
$whereConditions = ["u.role = 'reader'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($dept_filter !== 'all' && is_numeric($dept_filter)) {
    $whereConditions[] = "u.position_id = ?";
    $params[] = $dept_filter;
}

$whereSql = implode(' AND ', $whereConditions);

// 2. Xodimlarni olish (Pagination va Trening progressi bilan birga)
$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.position_id,
        u.is_active,
        u.created_at,
        p.name as position_name,
        (SELECT COUNT(*) FROM training_matrix tm WHERE tm.position_id = u.position_id) as total_trainings,
        (SELECT COUNT(*) FROM training_matrix tm WHERE tm.position_id = u.position_id) as completed_trainings
    FROM users u
    LEFT JOIN positions p ON u.position_id = p.id 
    WHERE $whereSql
    ORDER BY u.created_at DESC 
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Lavozimlarni olish (Filter va Modal uchun)
$positions = $pdo->query("SELECT * FROM positions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xodimlar - GMP Audit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* --- GLOBAL STYLES (Copy from Dashboard) --- */
        :root {
            --bg-primary: #0a0f1a;
            --accent-cyan: #06b6d4;
            --glass-bg: rgba(26, 35, 50, 0.7);
            --glass-border: rgba(51, 65, 85, 0.5);
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-primary);
            color: #f1f5f9;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #0f172a;
        }

        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .nav-item {
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(6, 182, 212, 0.1);
            border-left-color: var(--accent-cyan);
            color: #fff;
        }

        /* Badges */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .badge-info {
            background: rgba(6, 182, 212, 0.2);
            color: #06b6d4;
            border: 1px solid rgba(6, 182, 212, 0.3);
        }

        /* Progress Bar */
        .progress-track {
            background-color: #1e293b;
            border-radius: 999px;
            height: 6px;
            width: 100%;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 1s ease-in-out;
        }
    </style>
</head>

<body class="min-h-screen flex text-slate-100 relative">

    <!-- SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- Mobil fon (Backdrop) -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity"></div>

    <!-- Main Content -->
    <main class="flex-1 min-h-screen w-full">

        <!-- Header -->
        <header class="hidden lg:flex sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-8 py-4 justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold">Xodimlar Bazasi</h2>
                <p class="text-sm text-slate-500">Barcha xodimlar, ularning lavozimlari va trening progressi</p>
            </div>
            <div class="flex items-center gap-4">
                <button class="text-slate-400 hover:text-white transition flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Excel Export
                </button>
                <span class="text-xs text-slate-400">Lavozim biriktirish uchun xodim qatoridagi tahrirlash tugmasini bosing.</span>
            </div>
        </header>

        <div class="p-6 lg:p-8">

            <!-- Filters & Search -->
            <div class="glass-card rounded-xl p-4 mb-6 flex flex-col md:flex-row gap-4 justify-between items-center">
                <form action="" method="GET" class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
                    <div class="relative w-full md:w-80">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Xodim qidirish (ISM, ID)..." class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:border-cyan-500 text-white transition">
                        <svg class="w-4 h-4 text-slate-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    <div class="flex items-center gap-2">
                        <select name="dept" onchange="this.form.submit()" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-cyan-500 cursor-pointer">
                            <option value="all" <?php echo $dept_filter == 'all' ? 'selected' : ''; ?>>Barcha lavozimlar</option>
                            <?php foreach ($positions as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $dept_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($search) || $dept_filter != 'all'): ?>
                            <a href="employees.php" class="text-xs text-slate-400 hover:text-white underline">Tozalash</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="text-sm text-slate-400">
                    Jami: <span class="text-white font-bold"><?php echo count($employees); ?></span> ta xodim topildi
                </div>
            </div>

            <!-- Table -->
            <div class="glass-card rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-800/80 text-slate-400 text-xs uppercase tracking-wider">
                                <th class="p-4 font-medium border-b border-slate-700">Xodim</th>
                                <th class="p-4 font-medium border-b border-slate-700">Lavozim</th>
                                <th class="p-4 font-medium border-b border-slate-700">Qo'shilgan sana</th>
                                <th class="p-4 font-medium border-b border-slate-700">Trening Progress</th>
                                <th class="p-4 font-medium border-b border-slate-700 text-center">Status</th>
                                <th class="p-4 font-medium border-b border-slate-700 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50 text-sm">
                            <?php if (count($employees) > 0): ?>
                                <?php foreach ($employees as $emp):
                                    // Progress Calculation
                                    $total = (int)$emp['total_trainings'];
                                    $completed = (int)$emp['completed_trainings'];
                                    $percent = ($total > 0) ? round(($completed / $total) * 100) : 0;

                                    // Color logic for progress
                                    if ($percent == 100) $progressColor = 'bg-emerald-500';
                                    elseif ($percent > 50) $progressColor = 'bg-cyan-500';
                                    else $progressColor = 'bg-amber-500';

                                    // Status Logic
                                    $statusBadge = ((int)$emp['is_active'] === 1)
                                        ? '<span class="badge badge-success">Faol</span>'
                                        : '<span class="badge badge-danger">Nofaol</span>';
                                ?>
                                    <tr class="hover:bg-slate-800/30 transition group">
                                        <!-- User Info -->
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-white border border-slate-600">
                                                    <?php echo strtoupper(mb_substr($emp['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-white text-sm"><?php echo htmlspecialchars($emp['full_name']); ?></p>
                                                    <p class="text-xs text-slate-500 font-mono">Login: <?php echo htmlspecialchars($emp['username'] ?? '---'); ?></p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Dept/Pos -->
                                        <td class="p-4">
                                            <div class="text-slate-300 text-sm font-medium"><?php echo htmlspecialchars($emp['position_name'] ?? 'Belgilanmagan'); ?></div>
                                        </td>

                                        <!-- Date -->
                                        <td class="p-4 text-slate-400 text-xs">
                                            <?php echo date('d.m.Y', strtotime($emp['created_at'])); ?>
                                        </td>

                                        <!-- Progress -->
                                        <td class="p-4 w-64">
                                            <div class="flex justify-between text-xs mb-1">
                                                <span class="text-slate-400">Treninglar</span>
                                                <span class="<?php echo $percent == 100 ? 'text-emerald-400 font-bold' : 'text-slate-300'; ?>">
                                                    <?php echo $completed; ?>/<?php echo $total; ?> (<?php echo $percent; ?>%)
                                                </span>
                                            </div>
                                            <div class="progress-track">
                                                <div class="progress-fill <?php echo $progressColor; ?>" style="width: <?php echo $percent; ?>%;"></div>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td class="p-4 text-center">
                                            <?php echo $statusBadge; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="p-4 text-right">
                                            <div class="flex items-center justify-end gap-2 opacity-100 lg:opacity-0 group-hover:opacity-100 transition">
                                                <!-- Edit -->
                                                <button onclick="editEmployee(<?php echo htmlspecialchars(json_encode($emp)); ?>)" class="p-1.5 rounded hover:bg-slate-700 text-slate-400 hover:text-white" title="Tahrirlash">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-12 h-12 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <p>Ma'lumot topilmadi.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination (Visual Only for Demo) -->
                <div class="bg-slate-800/50 p-4 border-t border-slate-700 flex items-center justify-between">
                    <span class="text-xs text-slate-500">1-50 of <?php echo count($employees); ?> rows</span>
                    <div class="flex gap-1">
                        <button class="px-3 py-1 rounded bg-slate-700 text-slate-400 text-xs hover:bg-slate-600 disabled:opacity-50" disabled>Oldingi</button>
                        <button class="px-3 py-1 rounded bg-cyan-600 text-white text-xs">1</button>
                        <button class="px-3 py-1 rounded bg-slate-700 text-slate-400 text-xs hover:bg-slate-600">2</button>
                        <button class="px-3 py-1 rounded bg-slate-700 text-slate-400 text-xs hover:bg-slate-600">Keyingi</button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL: Add/Edit Employee -->
    <div id="addEmployeeModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm transition-opacity" onclick="closeModal('addEmployeeModal')"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-slate-900 border border-slate-700 w-full max-w-lg rounded-xl shadow-2xl p-0 overflow-hidden animate-in fade-in zoom-in duration-200">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-800 flex justify-between items-center bg-slate-800/50">
                <h3 class="text-lg font-bold text-white" id="modalTitle">Yangi xodim qo'shish</h3>
                <button onclick="closeModal('addEmployeeModal')" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <form id="employeeForm" action="" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_user_position">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id" id="employee_id">

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1 uppercase">F.I.Sh.</label>
                        <input type="text" id="full_name" readonly class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2.5 text-sm outline-none text-slate-300">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1 uppercase">Lavozim</label>
                        <select name="position_id" id="position_id" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-sm focus:border-cyan-500 outline-none text-white">
                            <option value="">Tanlanmagan</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo $pos['id']; ?>"><?php echo htmlspecialchars($pos['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="checkbox" name="status" id="status" checked class="w-4 h-4 rounded border-slate-600 text-cyan-600 focus:ring-cyan-500 bg-slate-800">
                        <label for="status" class="text-sm text-slate-300">Xodim faol holatda</label>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-800">
                        <button type="button" onclick="closeModal('addEmployeeModal')" class="px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 text-sm font-medium transition">Bekor qilish</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium shadow-lg shadow-cyan-500/20 transition">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        // Modal Functions
        function showModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Edit Employee Logic
        function editEmployee(employee) {
            document.getElementById('modalTitle').innerText = "Xodimni tahrirlash: " + employee.full_name;
            document.getElementById('employee_id').value = employee.id;
            document.getElementById('full_name').value = employee.full_name;
            document.getElementById('position_id').value = employee.position_id || '';
            document.getElementById('status').checked = Number(employee.is_active) === 1;

            showModal('addEmployeeModal');
        }

        const addBtn = document.querySelector('button[onclick="showModal(\'addEmployeeModal\')"]');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                document.getElementById('employeeForm').reset();
                document.getElementById('employee_id').value = '';
                document.getElementById('modalTitle').innerText = "Yangi xodim qo'shish";
                document.getElementById('status').checked = true;
            });
        }
    </script>
</body>

</html>