<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../db.php';
requireLogin();
 $user = getCurrentUser();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if (($user['role'] ?? '') === 'reader') {
    $stmt = $pdo->prepare("
        SELECT tm.*
        FROM training_modules tm
        INNER JOIN training_matrix mx ON mx.module_id = tm.id
        INNER JOIN users u ON u.position_id = mx.position_id
        WHERE u.id = ?
        ORDER BY tm.id DESC
    ");
    $stmt->execute([$user['id']]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("SELECT * FROM training_modules ORDER BY id DESC");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== MIME TYPE CHECK FUNCTION =====
function getMimeType($filePath)
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    return $mime;
}

// ===== ALLOWED MIME TYPES =====
 $allowed_mimes = [
    'application/pdf',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'video/mp4',
    'image/jpeg',
    'image/png'
];

// =======================================================
// 1. CREATE / UPDATE MODULE
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-red-500/50 bg-red-900/40 px-4 py-3 text-sm text-red-200">Xavfsizlik tekshiruvi muvaffaqiyatsiz tugadi. Sahifani yangilab qayta urinib ko\'ring.</div>';
        header("Location: modules.php");
        exit;
    }

    $action = $_POST['action'] ?? 'save_module';
    if ($action === 'delete_module') {
        $id = isset($_POST['module_id']) ? (int)$_POST['module_id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-red-500/50 bg-red-900/40 px-4 py-3 text-sm text-red-200">Noto\'g\'ri modul tanlandi.</div>';
            header("Location: modules.php");
            exit;
        }

        $stmt = $pdo->prepare("SELECT file_path FROM training_modules WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['file_path'])) {
            $file = '../uploads/modules/' . $row['file_path'];
            if (is_file($file)) {
                unlink($file);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM training_modules WHERE id=?");
        $stmt->execute([$id]);
        $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-900/30 px-4 py-3 text-sm text-emerald-200">Modul muvaffaqiyatli o\'chirildi.</div>';
        header("Location: modules.php");
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'GMP';
    $style = $_POST['style'] ?? 'Nazariy';
    $training_type = $_POST['training_type'] ?? 'boshlangich';
    $tutor_name = trim($_POST['tutor_name'] ?? '');
    $test_duration = (int)($_POST['test_duration'] ?? 10);
    $passing_percent = (int)($_POST['passing_percent'] ?? 80);
    $module_id = !empty($_POST['module_id']) ? (int)$_POST['module_id'] : null;
    $file_path = $_POST['current_file'] ?? '';

    if ($title === '' || $code === '') {
        $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-red-500/50 bg-red-900/40 px-4 py-3 text-sm text-red-200">Trening nomi va kod maydoni majburiy.</div>';
        header("Location: modules.php");
        exit;
    }

    if ($test_duration < 1 || $test_duration > 600 || $passing_percent < 1 || $passing_percent > 100) {
        $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-red-500/50 bg-red-900/40 px-4 py-3 text-sm text-red-200">Test parametrlari noto\'g\'ri. Vaqt 1-600 daqiqa, o\'tish foizi 1-100 oralig\'ida bo\'lishi kerak.</div>';
        header("Location: modules.php");
        exit;
    }

    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === 0) {
        $tmp = $_FILES['material_file']['tmp_name'];
        $mime = getMimeType($tmp);

        if (!in_array($mime, $allowed_mimes, true)) {
            $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-red-500/50 bg-red-900/40 px-4 py-3 text-sm text-red-200">Fayl formati ruxsat etilmagan.</div>';
            header("Location: modules.php");
            exit;
        }

        $uploadDir = '../uploads/modules/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = strtolower(pathinfo($_FILES['material_file']['name'], PATHINFO_EXTENSION));
        $newFile = uniqid('mod_', true) . ($extension ? '.' . $extension : '');

        if (!empty($file_path) && is_file($uploadDir . $file_path)) {
            unlink($uploadDir . $file_path);
        }

        if (!move_uploaded_file($tmp, $uploadDir . $newFile)) {
            $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-red-500/50 bg-red-900/40 px-4 py-3 text-sm text-red-200">Faylni saqlashda xatolik yuz berdi.</div>';
            header("Location: modules.php");
            exit;
        }

        $file_path = $newFile;
    }

    if ($module_id) {
        $check = $pdo->prepare("SELECT id FROM training_modules WHERE code = ? AND id != ?");
        $check->execute([$code, $module_id]);
    } else {
        $check = $pdo->prepare("SELECT id FROM training_modules WHERE code = ?");
        $check->execute([$code]);
    }

    if ($check->fetch()) {
        $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-red-500/50 bg-red-900/40 px-4 py-3 text-sm text-red-200">Bunday kod allaqachon mavjud. Iltimos boshqa kod tanlang.</div>';
        header("Location: modules.php");
        exit;
    }

    if ($module_id) {
        $sql = "UPDATE training_modules SET title=?, code=?, description=?, type=?, style=?, training_type=?, tutor_name=?, file_path=?, test_duration=?, passing_percent=?, updated_at=NOW() WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $code, $description, $type, $style, $training_type, $tutor_name, $file_path, $test_duration, $passing_percent, $module_id]);
    } else {
        $sql = "INSERT INTO training_modules (title, code, description, type, style, training_type, tutor_name, file_path, test_duration, passing_percent, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $code, $description, $type, $style, $training_type, $tutor_name, $file_path, $test_duration, $passing_percent, $user['id']]);
    }

    $_SESSION['flash_message'] = '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-900/30 px-4 py-3 text-sm text-emerald-200">Modul muvaffaqiyatli saqlandi.</div>';
    header("Location: modules.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O'quv Modullari - GMP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .nav-item {
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(6, 182, 212, 0.1);
            border-left-color: var(--accent-cyan);
            color: #fff;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-info {
            background: rgba(6, 182, 212, 0.2);
            color: #06b6d4;
            border: 1px solid rgba(6, 182, 212, 0.3);
        }

        #mobile-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }

        #mobile-sidebar.active {
            transform: translateX(0);
        }

        #sidebar-overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        #sidebar-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
    </style>
</head>

<body class="min-h-screen flex text-slate-100 relative">

    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity"></div>

    <!-- SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 min-h-screen w-full">
        <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-4 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4 w-full">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="flex items-center justify-between w-full">
                    <a href="/reader" class="flex items-center gap-2 text-sm text-slate-400 hover:text-cyan-400 transition group">
                        <svg class="w-5 h-5 transform group-hover:-translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Orqaga
                    </a>
                    <div class="text-right">
                        <h2 class="text-lg lg:text-xl font-semibold text-white">O'quv Modullari</h2>
                        <p class="text-xs lg:text-sm text-slate-500 hidden sm:block">Bu yerga qo'shimcha ma'lumot yozishingiz mumkin</p>
                    </div>
                </div>
            </div>
            <?php if (($user['role'] ?? '') !== 'reader'): ?>
            <button onclick="openModal()" class="bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span class="hidden sm:inline">Yangi Modul</span>
                <span class="sm:hidden">+ Yangi</span>
            </button>
            <?php endif; ?>
        </header>

        <div class="p-4 lg:p-8">
            <?php echo $flashMessage; ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 lg:gap-6">
                <?php foreach ($modules as $mod): ?>
                    <div class="glass-card rounded-xl p-5 hover:border-cyan-500/50 transition group relative flex flex-col">
                        <?php if (($user['role'] ?? '') !== 'reader'): ?>
                        <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition flex gap-1">
                            <button onclick="editModule(<?php echo $mod['id']; ?>)" class="p-1.5 bg-slate-800/80 rounded text-slate-300 hover:text-white hover:bg-slate-700" title="Tahrirlash">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </button>
                            <button onclick="deleteModule(<?php echo $mod['id']; ?>)" class="p-1.5 bg-red-900/50 rounded text-red-400 hover:text-red-300 hover:bg-red-900/70" title="O'chirish">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                        <?php endif; ?>

                        <div class="flex items-start gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-cyan-400 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <div class="overflow-hidden">
                                <span class="badge badge-info text-xs"><?php echo htmlspecialchars($mod['type']); ?></span>
                                <h3 class="font-bold text-white leading-tight mt-1 truncate" title="<?php echo htmlspecialchars($mod['title']); ?>"><?php echo htmlspecialchars($mod['title']); ?></h3>
                            </div>
                        </div>

                        <div class="space-y-1.5 text-xs text-slate-400 mt-auto mb-4">
                            <div class="flex justify-between"><span>Kod:</span> <span class="text-slate-200 font-mono"><?php echo htmlspecialchars($mod['code'] ?? '-'); ?></span></div>
                            <div class="flex justify-between"><span>Turi:</span> <span class="text-slate-200"><?php echo htmlspecialchars($mod['training_type'] ?? '-'); ?></span></div>
                            <div class="flex justify-between"><span>Murabbiy:</span> <span class="text-slate-200 truncate ml-2"><?php echo htmlspecialchars($mod['tutor_name'] ?? '-'); ?></span></div>
                            <div class="flex justify-between"><span>Test vaqti:</span> <span class="text-slate-200"><?php echo $mod['test_duration'] ?? 0; ?> min</span></div>
                            <div class="flex justify-between"><span>O'tish:</span> <span class="text-emerald-400 font-medium"><?php echo $mod['passing_percent'] ?? 0; ?>%</span></div>
                        </div>

                        <?php if (($user['role'] ?? '') !== 'reader'): ?>
                        <div class="mt-auto pt-3 border-t border-slate-700/50 flex gap-2">
                            <a href="materials.php?module_id=<?php echo $mod['id']; ?>" class="flex-1 bg-slate-800 hover:bg-slate-700 text-xs py-2 px-2 rounded text-center text-slate-300 transition truncate">
                                Materiallar
                            </a>
                            <a href="questions.php?module_id=<?php echo $mod['id']; ?>" class="flex-1 bg-cyan-900/30 hover:bg-cyan-900/50 border border-cyan-800 text-cyan-400 text-xs py-2 px-2 rounded transition text-center truncate">
                                Savollar
                            </a>
                        </div>
                        <?php endif; ?>

                        <script id="data-mod-<?php echo $mod['id']; ?>" type="application/json">
                            <?php echo json_encode($mod); ?>
                        </script>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($modules)): ?>
                    <div class="col-span-full text-center py-16 text-slate-500">
                        <p>Hozircha modullar mavjud emas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php if (($user['role'] ?? '') !== 'reader'): ?>
    <div id="moduleModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-slate-900 border border-slate-700 w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh]">
            <div class="p-6 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-xl font-bold" id="modalTitle">Yangi Modul Qo'shish</h3>
                <button onclick="closeModal()" class="text-slate-500 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form action="modules.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_module">
                <input type="hidden" name="module_id" id="module_id">
                <input type="hidden" name="current_file" id="current_file">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-slate-400 mb-1">Trening nomi *</label>
                        <input type="text" name="title" id="title" required class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Kod</label>
                        <input type="text" name="code" id="code" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Murabbiy</label>
                        <input type="text" name="tutor_name" id="tutor_name" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Turi</label>
                        <select name="type" id="type" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                            <option value="GMP">GMP</option>
                            <option value="SOP">SOP</option>
                            <option value="Xavfsizlik">Xavfsizlik</option>
                            <option value="Gigiyena">Gigiyena</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Usul</label>
                        <select name="style" id="style" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                            <option value="Nazariy">Nazariy</option>
                            <option value="Amaliy">Amaliy</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Kategoriya</label>
                        <select name="training_type" id="training_type" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none">
                            <option value="boshlangich">Boshlang'ich</option>
                            <option value="davriy">Davriy</option>
                            <option value="favqulotda">Favqulotda</option>
                            <option value="maxsus">Maxsus</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Tavsif</label>
                    <textarea name="description" id="description" rows="2" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm focus:border-cyan-500 outline-none resize-none"></textarea>
                </div>

                <div class="border-t border-slate-800 pt-4">
                    <h4 class="text-sm font-semibold text-white mb-3">Test Sozlamalari</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Vaqt (Daqiqa)</label>
                            <input type="number" name="test_duration" id="test_duration" value="10" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">O'tish foizi (%)</label>
                            <input type="number" name="passing_percent" id="passing_percent" value="80" class="w-full bg-slate-950 border border-slate-700 rounded p-2.5 text-white text-sm">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Material (PDF/PPT/DOC/Video)</label>
                    <input type="file" name="material_file" accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.jpg,.png" class="w-full text-slate-400 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-cyan-400 hover:file:bg-slate-700 cursor-pointer">
                    <p class="text-xs text-slate-500 mt-1" id="file_name_display">Hozircha fayl tanlanmagan</p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800 mt-4">
                    <button type="button" onclick="closeModal()" class="px-5 py-2 rounded-lg text-slate-300 hover:bg-slate-800 text-sm font-medium transition">Bekor qilish</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium transition">Saqlash</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (($user['role'] ?? '') !== 'reader'): ?>
    <form id="deleteForm" action="modules.php" method="POST" class="hidden">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="delete_module">
        <input type="hidden" name="module_id" id="delete_module_id">
    </form>
    <?php endif; ?>

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

        const modal = document.getElementById('moduleModal');

        function openModal() {
            if (!modal) return;
            resetForm();
            document.getElementById('modalTitle').innerText = 'Yangi Modul Qo\'shish';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        function resetForm() {
            if (!modal) return;
            document.getElementById('module_id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('code').value = '';
            document.getElementById('tutor_name').value = '';
            document.getElementById('type').value = 'GMP';
            document.getElementById('style').value = 'Nazariy';
            document.getElementById('training_type').value = 'boshlangich';
            document.getElementById('description').value = '';
            document.getElementById('test_duration').value = '10';
            document.getElementById('passing_percent').value = '80';
            document.getElementById('current_file').value = '';
            document.getElementById('file_name_display').innerText = 'Hozircha fayl tanlanmagan';
        }

        function editModule(id) {
            if (!modal) return;
            const dataTag = document.getElementById('data-mod-' + id);
            if (!dataTag) return alert('Ma\'lumot topilmadi!');

            const data = JSON.parse(dataTag.textContent);

            resetForm();

            document.getElementById('module_id').value = data.id;
            document.getElementById('title').value = data.title;
            document.getElementById('code').value = data.code || '';
            document.getElementById('tutor_name').value = data.tutor_name || '';
            document.getElementById('type').value = data.type || 'GMP';
            document.getElementById('style').value = data.style || 'Nazariy';
            document.getElementById('training_type').value = data.training_type || 'boshlangich';
            document.getElementById('description').value = data.description || '';
            document.getElementById('test_duration').value = data.test_duration || 10;
            document.getElementById('passing_percent').value = data.passing_percent || 80;
            document.getElementById('current_file').value = data.file_path || '';

            if (data.file_path) {
                document.getElementById('file_name_display').innerHTML = `<a href="../uploads/modules/${data.file_path}" target="_blank" class="text-cyan-400 underline">${data.file_path}</a>`;
            } else {
                document.getElementById('file_name_display').innerText = 'Fayl biriktirilmagan';
            }

            document.getElementById('modalTitle').innerText = 'Modulni Tahrirlash';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function deleteModule(id) {
            if (confirm('Rostdan ham bu modulni o\'chirmoqchimisiz?')) {
                const deleteIdInput = document.getElementById('delete_module_id');
                const deleteForm = document.getElementById('deleteForm');
                if (!deleteIdInput || !deleteForm) return;
                deleteIdInput.value = id;
                deleteForm.submit();
            }
        }

        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
        }
    </script>
</body>

</html>