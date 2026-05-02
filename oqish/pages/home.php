<div class="p-4 md:p-8 fade-in">
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-white mb-1">
            Assalomu alaykum, <span
                class="bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 to-teal-400"><?php echo htmlspecialchars($fullName); ?></span>
        </h1>
        <p class="text-sm text-slate-500">GMP o'quv tizimiga xush kelibsiz. Sizga biriktirilgan modullar quyida.</p>
    </div>

    <?php if ($totalModules > 0): ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-8">
            <div class="stat-card glass-card rounded-xl p-4 md:p-5">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <p class="text-2xl font-bold text-white"><?php echo $totalModules; ?></p>
                <p class="text-xs text-slate-500 mt-0.5">Jami modullar</p>
            </div>
            <div class="stat-card glass-card rounded-xl p-4 md:p-5">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-2xl font-bold text-white"><?php echo $passedModules; ?></p>
                <p class="text-xs text-slate-500 mt-0.5">Muvaffaqiyatli</p>
            </div>
            <div class="stat-card glass-card rounded-xl p-4 md:p-5">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-2xl font-bold text-white"><?php echo $inProgressCount; ?></p>
                <p class="text-xs text-slate-500 mt-0.5">Jarayonda</p>
            </div>
            <div class="stat-card glass-card rounded-xl p-4 md:p-5">
                <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <p class="text-2xl font-bold text-white"><?php echo $totalModules - $passedModules - $inProgressCount; ?>
                </p>
                <p class="text-xs text-slate-500 mt-0.5">Boshlanmagan</p>
            </div>
        </div>

        <div class="glass-card rounded-xl p-5 mb-8">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-medium text-slate-300">Umumiy progress</p>
                <p class="text-sm font-bold text-cyan-400">
                    <?php echo $totalModules > 0 ? round(($passedModules / $totalModules) * 100) : 0; ?>%</p>
            </div>
            <div class="progress-bar h-2.5">
                <div class="progress-fill h-full"
                    style="width: <?php echo $totalModules > 0 ? ($passedModules / $totalModules) * 100 : 0; ?>%"></div>
            </div>
            <p class="text-xs text-slate-600 mt-2"><?php echo $passedModules; ?> / <?php echo $totalModules; ?> modul
                tugallangan</p>
        </div>

        <div class="mb-4">
            <h2 class="text-lg font-bold text-white">Biriktirilgan modullar</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($assignedModules as $m):
                $st = getModuleStatus($m['id']);
                $matCount = intval($m['material_count']);
                $viewed = count($_SESSION['reader_materials_viewed'][$m['id']] ?? []);
                $matProgress = $matCount > 0 ? round(($viewed / $matCount) * 100) : 0;
                ?>
                <a href="?page=module&id=<?php echo $m['id']; ?>" class="module-card glass-card rounded-xl p-5 block">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-white truncate mb-1">
                                <?php echo htmlspecialchars($m['title']); ?></h3>
                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($m['code']); ?></p>
                        </div>
                        <?php echo statusBadge($st); ?>
                    </div>
                    <?php if ($m['description']): ?>
                        <p class="text-xs text-slate-500 mb-4 line-clamp-2"><?php echo htmlspecialchars($m['description']); ?></p>
                    <?php else: ?>
                        <div class="mb-4"></div>
                    <?php endif; ?>
                    <?php if ($matCount > 0): ?>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[11px] text-slate-500">Materiallar</span>
                                <span class="text-[11px] text-slate-400"><?php echo $viewed; ?>/<?php echo $matCount; ?></span>
                            </div>
                            <div class="progress-bar h-1.5">
                                <div class="progress-fill h-full" style="width: <?php echo $matProgress; ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center gap-3 mt-3 pt-3 border-t border-slate-800/50">
                        <span class="flex items-center gap-1 text-[11px] text-slate-600">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <?php echo $matCount; ?> fayl
                        </span>
                        <span class="flex items-center gap-1 text-[11px] text-slate-600">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <?php echo $m['question_count']; ?> savol
                        </span>
                        <span class="text-[11px] text-slate-600 ml-auto"><?php echo $m['type'] ?? 'GMP'; ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card rounded-2xl p-12 text-center max-w-lg mx-auto mt-12">
            <div class="w-20 h-20 rounded-2xl bg-slate-800/50 flex items-center justify-center mx-auto mb-5">
                <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">Modullar biriktirilmagan</h3>
            <p class="text-sm text-slate-500 leading-relaxed">
                Sizning lavozimingizga (<span
                    class="text-slate-400"><?php echo htmlspecialchars($userPositionName); ?></span>) hali hech qanday o'quv
                moduli biriktirilmagan. Iltimos, administrator bilan bog'laning.
            </p>
        </div>
    <?php endif; ?>
</div>