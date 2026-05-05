<?php
if (!isset($activePage)) {
    $activePage = '';
}
if (!isset($sidebarExtraClasses)) {
    $sidebarExtraClasses = '';
}
if (!isset($overlayExtraClasses)) {
    $overlayExtraClasses = '';
}
function navClass(string $page): string {
    global $activePage;
    return 'nav-item flex items-center gap-3 px-4 py-3 rounded-lg ' . ($activePage === $page ? 'text-white active' : 'text-slate-400 hover:text-white');
}
function ariaCurrent(string $page): string {
    global $activePage;
    return $activePage === $page ? 'aria-current="page"' : '';
}
?>
<!-- Mobile Header -->
<div class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50 px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        </div>
        <span class="font-bold text-white text-sm">GMP Audit</span>
    </div>
    <button onclick="toggleSidebar()" class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors" aria-label="Menyu ochish">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
</div>

<div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden <?php echo $overlayExtraClasses; ?>"></div>

<div class="flex min-h-screen pt-14 lg:pt-0">
    <aside id="sidebar" class="sidebar w-64 fixed h-full z-50 <?php echo $sidebarExtraClasses; ?>" role="navigation" aria-label="Asosiy navigatsiya">
        <div class="p-6 h-full flex flex-col">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white">GMP Audit</h1>
                    <p class="text-xs text-slate-500 font-mono">v2.0 Pro</p>
                </div>
            </div>

            <nav class="space-y-1 flex-1 overflow-y-auto">
                <a href="dashboard.php" class="<?php echo navClass('dashboard'); ?>" <?php echo ariaCurrent('dashboard'); ?> >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Bosh panel
                </a>
                <a href="audits.php" class="<?php echo navClass('audits'); ?>" <?php echo ariaCurrent('audits'); ?> >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Auditlar
                </a>
                <a href="reports.php" class="<?php echo navClass('reports'); ?>" <?php echo ariaCurrent('reports'); ?> >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Hisobotlar
                </a>

                <div class="pt-4 mt-4 border-t border-slate-700/50">
                    <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Boshqaruv</p>
                    <a href="sections.php" class="<?php echo navClass('sections'); ?>" <?php echo ariaCurrent('sections'); ?> >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Bo'limlar
                    </a>
                    <a href="checklists.php" class="<?php echo navClass('checklists'); ?>" <?php echo ariaCurrent('checklists'); ?> >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Checklistlar
                    </a>
                    <a href="users.php" class="<?php echo navClass('users'); ?>" <?php echo ariaCurrent('users'); ?> >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Auditorlar
                    </a>
                    <a href="logs.php" class="<?php echo navClass('logs'); ?>" <?php echo ariaCurrent('logs'); ?> >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Tizim Tarixi
                    </a>
                </div>
            </nav>

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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </aside>
