<aside id="sidebar" class="sidebar fixed top-0 left-0 h-screen w-72 flex flex-col">
    <div class="px-5 py-5 border-b border-slate-800/50">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-500 flex items-center justify-center shadow-lg shadow-cyan-500/20">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <div>
                <h1 class="text-sm font-bold text-white">GMP O'quv Tizimi</h1>
                <p class="text-[10px] text-slate-500 uppercase tracking-wider">Reader Panel</p>
            </div>
        </div>
    </div>
    <div class="px-5 py-4 border-b border-slate-800/50">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-500/20 to-teal-500/20 border border-cyan-500/30 flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold text-cyan-400"><?php echo mb_substr($fullName, 0, 1); ?></span>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($fullName); ?></p>
                <p class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($userPositionName ?? 'Xodim'); ?></p>
            </div>
        </div>
    </div>
    <nav class="flex-1 overflow-y-auto py-3 px-3">
        <a href="?page=dashboard" class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
            <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-sm text-slate-300">Boshqaruv paneli</span>
        </a>

        <?php if (count($assignedModules) > 0): ?>
            <div class="mt-4 mb-2 px-3">
                <p class="text-[10px] font-semibold text-slate-600 uppercase tracking-wider">Modullar</p>
            </div>
            <?php foreach ($assignedModules as $m):
                $st = getModuleStatus($m['id']);
                $isActive = ($page !== 'dashboard' && $moduleId == $m['id']);
                ?>
                <a href="?page=module&id=<?php echo $m['id']; ?>"
                    class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg mb-0.5 group <?php echo $isActive ? 'active' : ''; ?>">
                    <span class="flex-shrink-0"><?php echo statusIcon($st); ?></span>
                    <span class="text-sm text-slate-300 truncate flex-1"><?php echo htmlspecialchars($m['title']); ?></span>
                    <?php if ($m['material_count'] > 0): ?>
                        <span class="text-[10px] text-slate-600 flex-shrink-0"><?php echo $m['material_count']; ?> fayl</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
    <div class="px-3 py-4 border-t border-slate-800/50">
        <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-500/10 transition group">
            <svg class="w-[18px] h-[18px] text-slate-500 group-hover:text-red-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span class="text-sm text-slate-500 group-hover:text-red-400 transition">Tizimdan chiqish</span>
        </a>
    </div>
</aside>
