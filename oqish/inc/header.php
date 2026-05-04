<?php
if (!isset($_SESSION['reader_user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'GMP O\'quv Tizimi'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="min-h-screen overflow-x-hidden" data-user-id="<?php echo intval($_SESSION['reader_user_id']); ?>">
    <!-- SIDEBAR OVERLAY (MOBILE) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/60 z-40 hidden lg:hidden" onclick="toggleSidebar()"></div>
    
    <!-- MOBILE TOP BAR -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-30 glass-card border-t-0 border-x-0 px-4 py-3 flex items-center justify-between">
        <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-slate-800 transition">
            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <span class="text-sm font-semibold text-slate-200">GMP O'quv</span>
        </div>
        <div class="w-8 h-8 rounded-full bg-cyan-500/20 flex items-center justify-center">
            <span class="text-xs font-bold text-cyan-400"><?php echo mb_substr($fullName, 0, 1); ?></span>
        </div>
    </div>
