<?php
if (!isset($_SESSION['reader_materials_completed'][$moduleId]) && count($moduleMaterials) > 0) {
    header("Location: ?page=module&id=$moduleId");
    exit;
}
if (isset($_SESSION['reader_test_results'][$moduleId])) {
    header("Location: ?page=test_result&id=$moduleId");
    exit;
}
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
        <span class="text-slate-300">Test</span>
    </div>

    <?php if (count($testQuestions) > 0): ?>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-white mb-1">
                    <?php echo htmlspecialchars($currentModule['title']); ?> — Test</h1>
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span><?php echo count($testQuestions); ?> ta savol</span>
                    <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                    <span>O'tish balli: <?php echo $currentModule['passing_percent']; ?>%</span>
                </div>
            </div>
            <div class="glass-card rounded-xl px-4 py-2 flex items-center gap-2 flex-shrink-0">
                <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span id="timerText" class="text-sm font-mono font-semibold text-white">00:00</span>
            </div>
        </div>

        <form id="testForm" onsubmit="submitTest(event, <?php echo $moduleId; ?>)">
            <div class="space-y-6 mb-8">
                <?php $qNum = 0;
                foreach ($testQuestions as $qId => $q):
                    $qNum++; ?>
                    <div class="glass-card rounded-xl p-5 md:p-6">
                        <div class="flex items-start gap-3 mb-4">
                            <span
                                class="flex-shrink-0 w-7 h-7 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-xs font-bold text-cyan-400"><?php echo $qNum; ?></span>
                            <p class="text-sm font-medium text-white leading-relaxed pt-0.5">
                                <?php echo htmlspecialchars($q['text']); ?></p>
                        </div>
                        <div class="space-y-2 ml-10">
                            <?php foreach ($q['answers'] as $ans): ?>
                                <label
                                    class="radio-option flex items-center gap-3 p-3 rounded-lg border border-slate-800/50 cursor-pointer"
                                    data-question="<?php echo $qId; ?>">
                                    <input type="radio" name="answers[<?php echo $qId; ?>]" value="<?php echo $ans['id']; ?>"
                                        class="hidden" onchange="selectOption(this)">
                                    <span
                                        class="w-5 h-5 rounded-full border-2 border-slate-600 flex items-center justify-center flex-shrink-0 transition">
                                        <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 scale-0 transition-transform"></span>
                                    </span>
                                    <span class="text-sm text-slate-300"><?php echo htmlspecialchars($ans['text']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-600" id="answerCount">0 / <?php echo count($testQuestions); ?> savolga javob
                    berildi</p>
                <button type="submit" id="submitTestBtn"
                    class="btn-primary px-8 py-3 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Testni yakunlash
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="glass-card rounded-2xl p-12 text-center max-w-lg mx-auto mt-8">
            <div class="w-20 h-20 rounded-2xl bg-slate-800/50 flex items-center justify-center mx-auto mb-5">
                <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">Test savollari mavjud emas</h3>
            <p class="text-sm text-slate-500 mb-6">Ushbu modul uchun hali test savollari qo'shilmagan.</p>
            <a href="?page=module&id=<?php echo $moduleId; ?>"
                class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Orqaga qaytish
            </a>
        </div>
    <?php endif; ?>
</div>
<script>
    let timerSeconds = 0;
    let timerInterval = setInterval(() => {
        timerSeconds++;
        const m = Math.floor(timerSeconds / 60).toString().padStart(2, '0');
        const s = (timerSeconds % 60).toString().padStart(2, '0');
        const el = document.getElementById('timerText');
        if (el) el.textContent = m + ':' + s;
    }, 1000);
    window.onbeforeunload = () => clearInterval(timerInterval);
</script>