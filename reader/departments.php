<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../db.php';
requireLogin();
 $user = getCurrentUser();

// ===== BACKEND LOGIC =====

// 1. Bo'lim qo'shish / Tahrirlash
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $dept_id = $_POST['dept_id'] ?? null;

    if (!empty($name)) {
        if ($dept_id) {
            $stmt = $pdo->prepare("UPDATE departments SET name=? WHERE id=?");
            $stmt->execute([$name, $dept_id]);
            logActivity('department_edited', "Bo'lim tahrirlandi: $name (ID: $dept_id)", 'departments');
        } else {
            $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->execute([$name]);
            logActivity('department_added', "Yangi bo'lim qo'shildi: $name", 'departments');
        }
    }
    header("Location: departments.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
    $check->execute([$id]);
    $count = $check->fetchColumn();

    if ($count > 0) {
        echo "<script>alert('Diqqat: Ushbu bo\'limda xodimlar mavjud. Avval xodimlarni o\'chirishingiz yoki boshqa bo\'limga o\'tkazishingiz kerak.');</script>";
    } else {
        $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
        logActivity('department_deleted', "Bo'lim o'chirildi (ID: $id)", 'departments');
        header("Location: departments.php");
        exit;
    }
}

// 3. Bo'limlar ro'yxati
 $departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bo'limlar - GMP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg-primary: #0a0f1a; 
            --accent-cyan: #06b6d4; 
            --glass-bg: rgba(26, 35, 50, 0.7); 
            --glass-border: rgba(51, 65, 85, 0.5); 
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); color: #f1f5f9; }
        
        /* Umumiy Glass karta stili */
        .glass-card { 
            background: var(--glass-bg); 
            backdrop-filter: blur(12px); 
            border: 1px solid var(--glass-border); 
        }
    </style>
</head>
<body class="min-h-screen flex text-slate-100 relative">

    <!-- 1. SIDEBARNI CHAQIRISH -->
    <!-- Bu yerda sidebar.php fayli borligiga ishonch hosil qiling -->
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
                <span class="font-bold text-lg text-cyan-400">Bo'limlar</span>
            </div>
        </header>

        <!-- Desktop Header -->
        <header class="hidden lg:flex sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-8 py-4 justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold">Bo'limlar Ro'yxati</h2>
                <p class="text-sm text-slate-500">Tashkilot tarkibiy bo'linmalarini boshqarish</p>
            </div>
            <button onclick="openModal()" class="bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Yangi Bo'lim
            </button>
        </header>

        <div class="p-6 lg:p-8">
            <div class="glass-card rounded-xl overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-800 text-slate-300 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-4">Nomi</th>
                            <th class="px-6 py-4 text-center">Xodimlar soni</th> <!-- Qo'shimcha -->
                            <th class="px-6 py-4 text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach($departments as $dept): ?>
                        <tr class="hover:bg-slate-800/30 transition group">
                            <td class="px-6 py-4 font-medium text-white">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded bg-slate-700 flex items-center justify-center text-cyan-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    </div>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-400">
                                <?php 
                                    // Bo'lim → Lavozimlar → Xodimlar
                                    $empCount = $pdo->prepare("
                                        SELECT COUNT(DISTINCT u.id) FROM users u
                                        JOIN positions p ON u.position_id = p.id
                                        WHERE u.role='reader' AND u.is_active=1
                                        AND p.name LIKE ?
                                    ");
                                    $empCount->execute(['%' . $dept['name'] . '%']);
                                    $cnt = $empCount->fetchColumn();
                                    // Agar 0 bo'lsa, employees jadvalidan ham tekshiramiz
                                    if ($cnt == 0) {
                                        try {
                                            $ec2 = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id=?");
                                            $ec2->execute([$dept['id']]);
                                            $cnt = $ec2->fetchColumn();
                                        } catch (Exception $e) {}
                                    }
                                    echo $cnt;
                                ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition">
                                    <button onclick="editDept(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars(addslashes($dept['name'])); ?>')" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-slate-300 hover:text-white" title="Tahrirlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <a href="?delete=<?php echo $dept['id']; ?>" onclick="return confirm('Rostdan ham o\'chirmoqchimisiz? \n\nAgar bo\'limda xodimlar bo\'lsa, o\'chirilmaydi.')" class="p-2 bg-red-900/30 hover:bg-red-900/50 rounded-lg text-red-400 hover:text-red-300" title="O'chirish">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($departments)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-12 text-slate-500">
                                Bo'limlar mavjud emas
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal (Qo'shish/Tahrirlash) -->
    <div id="deptModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-slate-900 border border-slate-700 w-full max-w-md rounded-xl shadow-2xl overflow-hidden flex flex-col">
            <div class="p-6 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-xl font-bold" id="modalTitle">Yangi Bo'lim Qo'shish</h3>
                <button onclick="closeModal()" class="text-slate-500 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form action="" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="dept_id" id="dept_id">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Bo'lim nomi</label>
                    <input type="text" name="name" id="dept_name" required class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none" placeholder="Masalan: Sotish bo'limi">
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeModal()" class="px-5 py-2 rounded-lg text-slate-300 hover:bg-slate-800 text-sm font-medium transition">Bekor qilish</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium transition">Saqlash</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Responsive Script -->
    <script>
        // Sidebar toggle funksiyasi
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar'); // sidebar.php dagi ID
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

        // Modal boshqaruv
        const modal = document.getElementById('deptModal');
        
        function openModal() {
            document.getElementById('dept_id').value = '';
            document.getElementById('dept_name').value = '';
            document.getElementById('modalTitle').innerText = 'Yangi Bo\'lim Qo\'shish';
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        function editDept(id, name) {
            document.getElementById('dept_id').value = id;
            document.getElementById('dept_name').value = name;
            document.getElementById('modalTitle').innerText = 'Bo\'limni Tahrirlash';
            modal.classList.remove('hidden');
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    </script>
</body>
</html>