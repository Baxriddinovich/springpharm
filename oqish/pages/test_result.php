<?php
$isPassed = $testResult['status'] === 'passed';
$circumference = 2 * M_PI * 54;
$offset = $circumference - ($testResult['score'] / 100) * $circumference;
$ringColor = $isPassed ? '#10b981' : '#ef4444';
?>
<div class="p-4 md:p-8 fade-in">
    <div class="flex items-center gap-2 text-xs text-slate-500 mb-6">
        <a href="?page=dashboard" class="hover:text-cyan-400 transition">Boshqaruv paneli</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="?page=module&id=<?php echo $moduleId; ?>"
            class="hover:text-cyan-400 transition"><?php echo htmlspecialchars($currentModule['title']); ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-slate-300">Natija</span>
    </div>

    <div class="glass-card rounded-2xl p-6 md:p-8 max-w-2xl mx-auto mb-8 text-center">
        <?php if ($isPassed): ?>
            <div
                class="w-16 h-16 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mx-auto mb-5">
                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Tabriklaymiz!</h1>
            <p class="text-sm text-slate-400 mb-8">Siz muvaffaqiyatli o'tdingiz</p>
        <?php else: ?>
            <div
                class="w-16 h-16 rounded-2xl bg-red-500/10 border border-red-500/20 flex items-center justify-center mx-auto mb-5">
                <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Afsuski o'tmadingiz</h1>
            <p class="text-sm text-slate-400 mb-8">O'tish balli: <?php echo $testResult['passing']; ?>% — Siz:
                <?php echo $testResult['score']; ?>%</p>
        <?php endif; ?>

        <div class="flex justify-center mb-8">
            <div class="relative w-36 h-36">
                <svg class="w-full h-full score-ring" viewBox="0 0 120 120">
                    <circle class="score-ring-bg" cx="60" cy="60" r="54" fill="none" stroke="#1e293b"
                        stroke-width="8" />
                    <circle class="score-ring-fill" cx="60" cy="60" r="54" fill="none"
                        stroke="<?php echo $ringColor; ?>" stroke-width="8" stroke-linecap="round"
                        stroke-dasharray="<?php echo $circumference; ?>" stroke-dashoffset="<?php echo $offset; ?>" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-bold text-white"><?php echo $testResult['score']; ?></span>
                    <span class="text-xs text-slate-500">foiz</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-8">
            <div class="bg-slate-800/30 rounded-xl p-3">
                <p class="text-lg font-bold text-white"><?php echo $testResult['correct']; ?></p>
                <p class="text-[11px] text-slate-500">To'g'ri</p>
            </div>
            <div class="bg-slate-800/30 rounded-xl p-3">
                <p class="text-lg font-bold text-white"><?php echo $testResult['total'] - $testResult['correct']; ?></p>
                <p class="text-[11px] text-slate-500">Noto'g'ri</p>
            </div>
            <div class="bg-slate-800/30 rounded-xl p-3">
                <p class="text-lg font-bold text-white"><?php echo $testResult['total']; ?></p>
                <p class="text-[11px] text-slate-500">Jami</p>
            </div>
        </div>
        <p class="text-xs text-slate-600"><?php echo $testResult['time']; ?></p>
    </div>

    <?php if (!$isPassed): ?>
        <div class="text-center mb-8 space-y-3">
            <?php
            // Blok vaqtini hisoblash
            global $testLockInfo;
            $isNowBlocked = !empty($testLockInfo['blocked']);
            ?>
            <?php if ($isNowBlocked): ?>
            <div class="glass-card rounded-xl p-4 max-w-sm mx-auto border-orange-500/20 mb-4">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-orange-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <div class="text-left">
                        <p class="text-sm font-semibold text-orange-300">Test bloklangan</p>
                        <p class="text-xs text-slate-400">Keyingi urinish: <?php echo date('d.m.Y H:i', strtotime($testLockInfo['next_at'])); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <a href="?page=module&id=<?php echo $moduleId; ?>"
                class="btn-primary px-8 py-3 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Materiallarni qayta o'qish
            </a>
        </div>

    <?php if (!empty($testResult['details'])): ?>
        <h2 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            Savollar tahlili
        </h2>
        <div class="space-y-3 mb-8">
            <?php foreach ($testResult['details'] as $d): ?>
                <div
                    class="glass-card rounded-xl p-5 <?php echo $d['is_correct'] ? 'border-emerald-500/10' : 'border-red-500/10'; ?>">
                    <div class="flex items-start gap-3 mb-3">
                        <span
                            class="flex-shrink-0 w-6 h-6 rounded-lg <?php echo $d['is_correct'] ? 'bg-emerald-500/10' : 'bg-red-500/10'; ?> flex items-center justify-center">
                            <?php if ($d['is_correct']): ?>
                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            <?php else: ?>
                                <svg class="w-3.5 h-3.5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            <?php endif; ?>
                        </span>
                        <p class="text-sm text-white"><?php echo htmlspecialchars($d['question']); ?></p>
                    </div>
                    <div class="ml-9 space-y-1.5">
                        <?php foreach ($d['answers'] as $aId => $aText):
                            $isSelected = ($d['selected'] == $aId);
                            $isCorrectAns = ($d['correct_id'] == $aId);
                            ?>
                            <div
                                class="flex items-center gap-2 text-xs px-3 py-2 rounded-lg <?php echo $isCorrectAns ? 'bg-emerald-500/10 border border-emerald-500/20' : ($isSelected ? 'bg-red-500/10 border border-red-500/20' : 'bg-slate-800/30 border border-transparent'); ?>">
                                <span
                                    class="<?php echo $isCorrectAns ? 'text-emerald-300 font-medium' : ($isSelected ? 'text-red-300' : 'text-slate-500'); ?>">
                                    <?php echo htmlspecialchars($aText); ?>
                                    <?php if ($isCorrectAns)
                                        echo ' (to\'g\'ri)'; ?>
                                    <?php if ($isSelected && !$isCorrectAns)
                                        echo ' (tanlangan)'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="text-center">
        <a href="?page=dashboard"
            class="text-sm text-cyan-400 hover:text-cyan-300 transition inline-flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Boshqaruv paneliga qaytish
        </a>
    </div>
</div>