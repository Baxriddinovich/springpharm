<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../db.php';

// Session ishlamayotgan bo'lsa, uni ishga tushiramiz
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

requireLogin();
 $user = getCurrentUser();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Modul ID sini olish
 $module_id = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;
if (!$module_id) {
    header("Location: modules.php");
    exit;
}

// Modul ma'lumotlari
 $stmt = $pdo->prepare("SELECT * FROM training_modules WHERE id=?");
 $stmt->execute([$module_id]);
 $module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    die("Modul topilmadi.");
}

// ===== SERVER CHEKLOVLARINI ANIQLASH =====
 $max_upload = ini_get('upload_max_filesize');
 $max_post = ini_get('post_max_size');
 $upload_limit = "Server cheklovi: Maks. Fayl: {$max_upload}, Maks. So'rov: {$max_post}";

// ===== FLASH XABARNI OLIB, O'CHIRISH =====
 $uploadMessage = '';
if (isset($_SESSION['flash_message'])) {
    $uploadMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// ===== FAYL YUKLASH LOGIC =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_files' && isset($_FILES['files'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_message'] = '<div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4">Xavfsizlik tekshiruvi muvaffaqiyatsiz tugadi.</div>';
        header("Location: materials.php?module_id=" . $module_id);
        exit;
    }

    $uploadDir = '../uploads/modules/';
    $errors = [];
    $count = 0;
    $allowedMimes = [
        'application/pdf',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'video/mp4',
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    // Papka tekshiruvi
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (is_writable($uploadDir)) {
        $files = $_FILES['files'];

        for ($i = 0; $i < count($files['name']); $i++) {
            $fileName = $files['name'][$i];
            $fileError = $files['error'][$i];

            // Xatolik kodini tekshirish
            if ($fileError !== UPLOAD_ERR_OK) {
                switch ($fileError) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errors[] = "$fileName: Fayl hajmi server cheklovidan oshib ketdi!";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errors[] = "$fileName: Fayl faqat qisman yuklandi.";
                        break;
                    default:
                        $errors[] = "$fileName: Yuklashda xatolik (Kod: $fileError).";
                }
                continue;
            }

            // Yuklash
            $tmpName = $files['tmp_name'][$i];
            $fileType = mime_content_type($tmpName);
            if (!in_array($fileType, $allowedMimes, true)) {
                $errors[] = htmlspecialchars($fileName) . ": ruxsat etilmagan fayl turi.";
                continue;
            }

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newFileName = uniqid('mat_', true) . ($extension ? '.' . $extension : '');
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $destination)) {
                $stmt = $pdo->prepare("INSERT INTO module_materials (module_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$module_id, basename($fileName), $newFileName, $fileType]);
                $count++;
            } else {
                $errors[] = "$fileName: Serverga yozib bo'lmadi.";
            }
        }
    } else {
        $errors[] = "Papkaga yozish uchun ruxsat yo'q.";
    }

    // Xabarlarni Sessionga yozamiz
    $messages = [];
    if ($count > 0) {
        $messages[] = '<div class="bg-emerald-900/50 border border-emerald-500 text-emerald-300 px-4 py-3 rounded mb-4">' . $count . ' ta fayl yuklandi!</div>';
    }
    if (!empty($errors)) {
        $messages[] = '<div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4"><b>Xatoliklar:</b><br>' . implode('<br>', $errors) . '</div>';
    }
    if (!empty($messages)) {
        $_SESSION['flash_message'] = implode('', $messages);
    }

    // MUHIM: Sahifani qayta yo'naltiramiz (Redirect)
    // Bu F5 bosilganda faylning qayta yuklanishining oldini oladi
    header("Location: materials.php?module_id=" . $module_id);
    exit;
}

// ===== FAYL O'CHIRISH LOGIC =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_message'] = '<div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4">Xavfsizlik tekshiruvi muvaffaqiyatsiz tugadi.</div>';
        header("Location: materials.php?module_id=" . $module_id);
        exit;
    }

    $fid = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $fstmt = $pdo->prepare("SELECT * FROM module_materials WHERE id=? AND module_id=?");
    $fstmt->execute([$fid, $module_id]);
    $fileRow = $fstmt->fetch();

    if ($fileRow) {
        if (file_exists('../uploads/modules/' . $fileRow['file_path'])) {
            unlink('../uploads/modules/' . $fileRow['file_path']);
        }
        $pdo->prepare("DELETE FROM module_materials WHERE id=? AND module_id=?")->execute([$fid, $module_id]);
    }
    header("Location: materials.php?module_id=" . $module_id);
    exit;
}

// Modulning barcha materiallari
 $materials = $pdo->prepare("SELECT * FROM module_materials WHERE module_id=? ORDER BY uploaded_at DESC");
 $materials->execute([$module_id]);
 $materials = $materials->fetchAll(PDO::FETCH_ASSOC);

// Ikonka funksiyasi
function getFileIcon($type, $extension) {
    $ext = strtolower($extension);
    if (strpos($type, 'pdf') !== false || $ext == 'pdf') return '<svg class="w-8 h-8 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M10.92,12.31C10.68,11.54 10.15,9.08 11.55,9.04C12.95,9 12.03,12.16 12.03,12.16C12.42,13.65 14.05,14.72 14.05,14.72C14.55,14.57 17.4,14.24 17,15.72C16.57,17.2 13.5,15.81 13.5,15.81C11.55,15.95 10.09,16.47 10.09,16.47C8.96,18.58 7.64,19.5 7.1,18.61C6.43,17.5 9.23,16.07 9.23,16.07C10.17,13.29 10.92,12.31 10.92,12.31Z" /></svg>';
    if (strpos($type, 'word') !== false || $ext == 'doc' || $ext == 'docx') return '<svg class="w-8 h-8 text-blue-400" fill="currentColor" viewBox="0 0 24 24"><path d="M6,2H14L20,8V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V4A2,2 0 0,1 6,2M13,3.5V9H18.5L13,3.5M7,13L8.5,20H10.5L12,17L13.5,20H15.5L17,13H15L14.1,17.2L12.5,14H11.5L10,17.2L9,13H7Z" /></svg>';
    if (strpos($type, 'presentation') !== false || $ext == 'ppt' || $ext == 'pptx') return '<svg class="w-8 h-8 text-orange-400" fill="currentColor" viewBox="0 0 24 24"><path d="M6,2H14L20,8V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V4A2,2 0 0,1 6,2M13,3.5V9H18.5L13,3.5M10,13V18H11V16H12A2,2 0 0,0 14,14A2,2 0 0,0 12,12H10M11,13H12A1,1 0 0,1 13,14A1,1 0 0,1 12,15H11V13Z" /></svg>';
    if (strpos($type, 'video') !== false || in_array($ext, ['mp4', 'avi', 'mov', 'mkv'])) return '<svg class="w-8 h-8 text-purple-400" fill="currentColor" viewBox="0 0 24 24"><path d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z" /></svg>';
    if (strpos($type, 'image') !== false || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return '<svg class="w-8 h-8 text-teal-400" fill="currentColor" viewBox="0 0 24 24"><path d="M21,3H3C2,3 1,4 1,5V19A2,2 0 0,0 3,21H21C22,21 23,20 23,19V5C23,4 22,3 21,3M5,17L8.5,12.5L11,15.5L14.5,11L19,17H5Z" /></svg>';
    if (in_array($ext, ['zip', 'rar', '7z', 'tar'])) return '<svg class="w-8 h-8 text-yellow-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2M12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20M13 7H15V11H13V7M9 7H11V11H9V7M15 13H17V17H15V13M13 13H15V17H13V13M9 13H11V17H9V13M7 13H9V17H7V13Z" /></svg>';
    return '<svg class="w-8 h-8 text-slate-400" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" /></svg>';
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiallar: <?php echo htmlspecialchars($module['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-primary: #0a0f1a; --accent-cyan: #06b6d4; --glass-bg: rgba(26, 35, 50, 0.7); --glass-border: rgba(51, 65, 85, 0.5); }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg-primary); color: #f1f5f9; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--glass-border); }
        .dropzone { border: 2px dashed rgba(51, 65, 85, 0.5); transition: all 0.3s; position: relative; overflow: hidden; }
        .dropzone.dragover { border-color: var(--accent-cyan); background: rgba(6, 182, 212, 0.05); }
        .dropzone input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    </style>
</head>
<body class="min-h-screen">
    <header class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="modules.php" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-white"><?php echo htmlspecialchars($module['title']); ?></h1>
                    <p class="text-xs text-slate-400">Modul kodu: <?php echo $module['code']; ?></p>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto p-4 lg:p-8">
        <?php echo $uploadMessage; ?>

        <div class="glass-card rounded-xl p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Yangi Material Yuklash</h3>
                <span class="text-xs bg-slate-800 px-2 py-1 rounded text-yellow-400"><?php echo $upload_limit; ?></span>
            </div>
            
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_files">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div id="dropzone" class="dropzone rounded-xl p-10 text-center cursor-pointer hover:border-cyan-500/50">
                    <input type="file" name="files[]" multiple>
                    <div class="pointer-events-none">
                        <svg class="w-12 h-12 mx-auto text-slate-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <p class="text-slate-400">Fayllarni sudrab keling yoki bu yerga bosing</p>
                        <p class="text-xs text-slate-500 mt-1">Istalgan turdagi fayllarni yuklash mumkin</p>
                    </div>
                </div>
                
                <div id="file-list" class="mt-4 space-y-2 text-sm text-slate-300"></div>
                
                <div class="mt-4 text-right">
                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white px-6 py-2 rounded-lg font-medium transition">
                        Yuklash
                    </button>
                </div>
            </form>
        </div>

        <div class="glass-card rounded-xl overflow-hidden">
            <div class="p-4 border-b border-slate-700 flex justify-between items-center">
                <h3 class="font-semibold">Biriktirilgan Materiallar</h3>
                <span class="text-xs text-slate-400"><?php echo count($materials); ?> ta fayl</span>
            </div>
            <div class="divide-y divide-slate-700/50">
                <?php if($materials): ?>
                    <?php foreach($materials as $mat): ?>
                        <?php $ext = pathinfo($mat['file_name'], PATHINFO_EXTENSION); ?>
                        <div class="p-4 flex items-center justify-between hover:bg-slate-800/30 transition group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-slate-800 rounded-lg flex items-center justify-center">
                                    <?php echo getFileIcon($mat['file_type'], $ext); ?>
                                </div>
                                <div>
                                    <p class="font-medium text-white truncate max-w-xs md:max-w-lg" title="<?php echo htmlspecialchars($mat['file_name']); ?>">
                                        <?php echo htmlspecialchars($mat['file_name']); ?>
                                    </p>
                                    <p class="text-xs text-slate-500 uppercase"><?php echo $ext; ?> • <?php echo date('d.m.Y H:i', strtotime($mat['uploaded_at'])); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                                <a href="../uploads/modules/<?php echo htmlspecialchars($mat['file_path']); ?>" target="_blank" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-cyan-400" title="Yuklab olish">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                </a>
                                <form method="post" class="inline-block" onsubmit="return confirm('O\\'chirmoqchimisiz?')">
                                    <input type="hidden" name="action" value="delete_file">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="file_id" value="<?php echo (int)$mat['id']; ?>">
                                    <button type="submit" class="p-2 bg-red-900/30 hover:bg-red-900/50 rounded-lg text-red-400" title="O'chirish">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-10 text-center text-slate-500">
                        <p>Hozircha materiallar yuklanmagan</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = dropzone.querySelector('input[type="file"]');
        const fileListDiv = document.getElementById('file-list');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
        });

        fileInput.addEventListener('change', () => {
            fileListDiv.innerHTML = '';
            if(fileInput.files.length > 0) {
                Array.from(fileInput.files).forEach(file => {
                    const p = document.createElement('p');
                    p.className = 'flex items-center gap-2 bg-slate-800 p-2 rounded';
                    p.innerHTML = `<svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg> <span class="truncate">${file.name}</span> <span class="text-slate-500 text-xs ml-auto">(${(file.size / 1024 / 1024).toFixed(2)} MB)</span>`;
                    fileListDiv.appendChild(p);
                });
            }
        });
    </script>
</body>
</html>