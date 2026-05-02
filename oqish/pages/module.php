<?php
$moduleStatus = getModuleStatus($moduleId);
$canComplete = $allMaterialsViewed && !isset($_SESSION['reader_materials_completed'][$moduleId]);
$alreadyCompleted = isset($_SESSION['reader_materials_completed'][$moduleId]);
$alreadyTested = isset($_SESSION['reader_test_results'][$moduleId]);
?>
<div class="p-4 md:p-8 fade-in">
    <div class="flex items-center gap-2 text-xs text-slate-500 mb-6">
        <a href="?page=dashboard" class="hover:text-cyan-400 transition">Boshqaruv paneli</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-slate-300"><?php echo htmlspecialchars($currentModule['title']); ?></span>
    </div>

    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3 mb-2 flex-wrap">
                <h1 class="text-xl md:text-2xl font-bold text-white"><?php echo htmlspecialchars($currentModule['title']); ?></h1>
                <?php echo statusBadge($moduleStatus); ?>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                <span><?php echo htmlspecialchars($currentModule['code']); ?></span>
                <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                <span><?php echo $currentModule['type'] ?? 'GMP'; ?></span>
                <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                <span><?php echo $currentModule['style'] ?? 'Nazariy'; ?></span>
                <?php if ($currentModule['passing_percent']): ?>
                    <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                    <span>O'tish balli: <?php echo $currentModule['passing_percent']; ?>%</span>
                <?php endif; ?>
            </div>
            <?php if ($currentModule['description']): ?>
                <p class="text-sm text-slate-500 mt-3 max-w-2xl leading-relaxed"><?php echo htmlspecialchars($currentModule['description']); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($alreadyTested): ?>
            <a href="?page=test_result&id=<?php echo $moduleId; ?>" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2 whitespace-nowrap flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                Natijani ko'rish
            </a>
        <?php elseif ($alreadyCompleted): ?>
            <a href="?page=test&id=<?php echo $moduleId; ?>" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2 whitespace-nowrap flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                Testni boshlash
            </a>
        <?php elseif ($canComplete): ?>
            <button onclick="completeMaterials(<?php echo $moduleId; ?>)" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2 whitespace-nowrap flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Tugatish
            </button>
        <?php endif; ?>
    </div>

    <!-- ON-PAGE VIEWER -->
    <div id="materialViewer" class="hidden glass-card rounded-2xl overflow-hidden mb-8 border-cyan-500/30 fade-in">
        <div class="px-5 py-3 border-b border-slate-800/50 flex items-center justify-between bg-slate-900/50">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                </div>
                <span id="viewerFileName" class="text-sm font-medium text-white truncate max-w-xs md:max-w-md"></span>
            </div>
            <button onclick="closeViewer()" class="p-1.5 rounded-lg hover:bg-red-500/10 text-slate-400 hover:text-red-400 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="h-[600px] bg-black/20 relative">
            <iframe id="viewerFrame" class="w-full h-full border-0 hidden"></iframe>
            <video id="viewerVideo" class="w-full h-full hidden" controls></video>
            <img id="viewerImage" class="w-full h-full object-contain hidden">
            <div id="viewerLoading" class="absolute inset-0 flex items-center justify-center bg-slate-900/50">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-cyan-500 border-t-transparent"></div>
            </div>
        </div>
    </div>

    <?php if (count($moduleMaterials) > 0): ?>
        <div class="glass-card rounded-xl p-4 mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-slate-300">Materiallarni o'qish</span>
                <span class="text-sm font-semibold <?php echo $allMaterialsViewed ? 'text-emerald-400' : 'text-slate-400'; ?>"><?php echo $viewedCount; ?> / <?php echo count($moduleMaterials); ?></span>
            </div>
            <div class="progress-bar h-2">
                <div class="progress-fill h-full" style="width: <?php echo count($moduleMaterials) > 0 ? ($viewedCount / count($moduleMaterials)) * 100 : 0; ?>%"></div>
            </div>
        </div>

        <h2 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            O'quv materiallari
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <?php foreach ($moduleMaterials as $idx => $mat):
                $isViewed = in_array($mat['id'], $_SESSION['reader_materials_viewed'][$moduleId] ?? []);
                $iconColor = fileIconClass($mat['file_type']);
                ?>
                <div class="material-card glass-card rounded-2xl p-5 flex items-center gap-5 <?php echo $isViewed ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-slate-700/50'; ?> fade-in cursor-pointer"
                    style="animation-delay: <?php echo $idx * 0.05; ?>s"
                    onclick="openMaterial('?serve_file=<?php echo urlencode($mat['file_path']); ?>', '<?php echo addslashes($mat['file_name']); ?>', '<?php echo $mat['file_type']; ?>', <?php echo $mat['id']; ?>, <?php echo $moduleId; ?>)">

                    <div class="material-icon-box flex-shrink-0 w-14 h-14 rounded-2xl bg-slate-800/50 flex items-center justify-center border border-slate-700/50">
                        <svg class="w-7 h-7 <?php echo $iconColor; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-6 4h4" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-white truncate mb-1"><?php echo htmlspecialchars($mat['file_name']); ?></h3>
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-800/80 px-2 py-0.5 rounded-md border border-slate-700/50">
                                <?php $ext = pathinfo($mat['file_name'], PATHINFO_EXTENSION); echo $ext ? strtoupper($ext) : 'FILE'; ?>
                            </span>
                            <?php if ($isViewed): ?>
                                <span class="flex items-center gap-1 text-[11px] font-medium text-emerald-400">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                    Ko'rildi
                                </span>
                            <?php else: ?>
                                <span class="text-[11px] font-medium text-slate-500">O'qilmagan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full bg-slate-800/30 flex items-center justify-center text-slate-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="glass-card rounded-xl p-8 text-center mb-8">
            <svg class="w-12 h-12 text-slate-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-base font-semibold text-white mb-2">Materiallar yo'q</h3>
            <p class="text-sm text-slate-500 mb-4">Ushbu modulga o'quv materiali biriktirilmagan.</p>
            <button onclick="completeMaterials(<?php echo $moduleId; ?>)" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                To'g'ridan-to'g'ri testga o'tish
            </button>
        </div>
    <?php endif; ?>
</div>