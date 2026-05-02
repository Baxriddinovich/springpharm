<?php
// sidebar.php — Universal sidebar (barcha sahifalar uchun)
$currentPage = basename($_SERVER['PHP_SELF']);
function navClass($page, $current) {
    $base = 'nav-item w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200';
    if ($page === $current) {
        return $base . ' active bg-cyan-900/20 text-white border-l-transparent';
    }
    return $base . ' text-slate-400 hover:text-white';
}
?>
<!-- sidebar.php -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 transform -translate-x-full transition-transform duration-300 lg:relative lg:translate-x-0 lg:flex flex-col">
    
    <!-- Logo qismi -->
    <div class="p-6 border-b border-slate-800 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 to-teal-400">GMP Learning</h1>
            <p class="text-xs text-slate-500 mt-1">O'quv Boshqaruv Tizimi</p>
        </div>
        <!-- Mobil yopish tugmasi -->
        <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Menyular -->
    <nav class="flex-1 overflow-y-auto p-4 space-y-1">

        <!-- Bosh Panel -->
        <a href="index.php" class="<?php echo navClass('index.php', $currentPage); ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            Bosh Panel
        </a>

        <!-- Bo'limlar -->
        <a href="departments.php" class="<?php echo navClass('departments.php', $currentPage); ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            Bo'limlar
        </a>

        <!-- Lavozimlar -->
        <a href="positions.php" class="<?php echo navClass('positions.php', $currentPage); ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            Lavozimlar
        </a>

        <!-- Xodimlar -->
        <a href="employees.php" class="<?php echo navClass('employees.php', $currentPage); ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            Xodimlar
        </a>

        <div class="border-t border-slate-700 my-2"></div>

        <!-- Trening Modullari -->
        <a href="modules.php" class="<?php echo navClass('modules.php', $currentPage); ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            Trening Modullari
        </a>

        <!-- Matritsa -->
        <a href="matrix.php" class="<?php echo navClass('matrix.php', $currentPage); ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
            Matritsa
        </a>

        <!-- Natijalar -->
        <a href="results.php" class="<?php echo navClass('results.php', $currentPage); ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Natijalar
        </a>
    </nav>

    <!-- Foydalanuvchi profili (Pastki qism) -->
    <div class="p-4 border-t border-slate-800">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-600 to-blue-600 flex items-center justify-center font-bold text-white">
                <?php echo strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($user['full_name']); ?></p>
                <p class="text-xs text-slate-500 truncate">Admin</p>
            </div>
            <a href="../dashboard.php" class="ml-auto text-slate-500 hover:text-white" title="Dashboardga qaytish">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
        </div>
    </div>
</aside>