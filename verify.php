<?php
// verify.php - Audit haqiqiyligini tekshirish
require_once 'db.php';

 $code = $_GET['code'] ?? '';

if (!$code) {
    die("Kod berilmagan.");
}

 $stmt = $pdo->prepare("
    SELECT a.audit_code, a.title, a.start_date, a.progress_percent, a.status, 
           s.name as site_name, u.full_name as auditor_name
    FROM audits a
    JOIN sites s ON a.site_id = s.id
    JOIN users u ON a.created_by = u.id
    WHERE a.audit_code = ?
");
 $stmt->execute([$code]);
 $audit = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hujjat haqiqiyligi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8 text-center">
        <?php if ($audit): ?>
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h1 class="text-2xl font-bold text-green-600 mb-2">Hujjat Haqiqiy</h1>
            <p class="text-slate-500 mb-6">Bu audit hisoboti tizimda ro'yxatdan o'tgan</p>
            
            <div class="bg-slate-50 rounded-lg p-4 text-left space-y-2">
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="text-slate-500">Hisobot kodi:</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($audit['audit_code']); ?></span>
                </div>
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="text-slate-500">Korxona:</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($audit['site_name']); ?></span>
                </div>
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="text-slate-500">Sana:</span>
                    <span class="font-bold text-slate-800"><?php echo date('d.m.Y', strtotime($audit['start_date'])); ?></span>
                </div>
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="text-slate-500">Natija:</span>
                    <span class="font-bold text-slate-800"><?php echo $audit['progress_percent']; ?>%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Auditor:</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($audit['auditor_name']); ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h1 class="text-2xl font-bold text-red-600 mb-2">Topilmadi</h1>
            <p class="text-slate-500">Bunday kodli hisobot tizimda mavjud emas.</p>
        <?php endif; ?>
    </div>
</body>
</html>