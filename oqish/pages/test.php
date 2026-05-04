<?php
// Redirect logikasi dashboard.php da bajarilgan (include dan oldin)
// Bu yerda faqat blok holati tekshiriladi
$isBlocked = !empty($testLockInfo['blocked']);
$isPassed  = !empty($testLockInfo['passed']);
?>
<div class="p-4 md:p-8 fade-in">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-slate-500 mb-6">
        <a href="?page=dashboard" class="hover:text-cyan-400 transition">Boshqaruv paneli</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <a href="?page=module&id=<?php echo $moduleId; ?>" class="hover:text-cyan-400 transition"><?php echo htmlspecialchars($currentModule['title']); ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-slate-300">Test</span>
    </div>

    <?php if ($isBlocked): ?>
    <!-- ═══ BLOKLANGAN HOLAT ═══ -->
    <?php
        $nextAt   = new DateTime($testLockInfo['next_at']);
        $now      = new DateTime();
        $diff     = $now->diff($nextAt);
        $daysLeft = $diff->days;
        $hoursLeft = $diff->h;
        $minsLeft  = $diff->i;
    ?>
    <div class="max-w-lg mx-auto mt-8">
        <div class="glass-card rounded-2xl p-8 text-center border-orange-500/20">
            <div class="w-20 h-20 rounded-2xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center mx-auto mb-5">
                <svg class="w-10 h-10 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">Test vaqtincha bloklangan</h2>
            <p class="text-sm text-slate-400 mb-6">Siz testdan o'ta olmadingiz. Qayta urinish uchun kutishingiz kerak.</p>

            <div class="grid grid-cols-3 gap-3 mb-6">
                <div class="bg-slate-800/50 rounded-xl p-3 border border-slate-700/50">
                    <p class="text-2xl font-bold text-orange-400"><?php echo $daysLeft; ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">kun</p>
                </div>
                <div class="bg-slate-800/50 rounded-xl p-3 border border-slate-700/50">
                    <p class="text-2xl font-bold text-orange-400"><?php echo $hoursLeft; ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">soat</p>
                </div>
                <div class="bg-slate-800/50 rounded-xl p-3 border border-slate-700/50">
                    <p class="text-2xl font-bold text-orange-400"><?php echo $minsLeft; ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">daqiqa</p>
                </div>
            </div>

            <div class="bg-slate-800/30 rounded-xl p-4 mb-6 text-left">
                <p class="text-xs text-slate-500 mb-1">Ochilish vaqti:</p>
                <p class="text-sm font-semibold text-white"><?php echo $nextAt->format('d.m.Y H:i'); ?></p>
            </div>

            <?php if ($lastAttempt): ?>
            <div class="bg-red-500/5 border border-red-500/20 rounded-xl p-4 mb-6 text-left">
                <p class="text-xs text-slate-500 mb-2">Oxirgi natija:</p>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-300">Ball:</span>
                    <span class="text-sm font-bold text-red-400"><?php echo $lastAttempt['score']; ?>%</span>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-sm text-slate-300">O'tish balli:</span>
                    <span class="text-sm text-slate-400"><?php echo $lastAttempt['passing_percent']; ?>%</span>
                </div>
            </div>
            <?php endif; ?>

            <p class="text-xs text-slate-500 mb-4">Bu vaqt ichida o'quv materiallarini qayta o'qishingiz mumkin.</p>
            <a href="?page=module&id=<?php echo $moduleId; ?>" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                Materiallarni o'qish
            </a>
        </div>
    </div>

    <?php elseif (count($testQuestions) > 0): ?>
    <!-- ═══ TEST SAVOLLARI ═══ -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-white mb-1"><?php echo htmlspecialchars($currentModule['title']); ?> — Test</h1>
            <div class="flex items-center gap-3 text-xs text-slate-500">
                <span><?php echo count($testQuestions); ?> ta savol</span>
                <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                <span>O'tish balli: <?php echo $currentModule['passing_percent']; ?>%</span>
                <?php
                $totalQ = intval($currentModule['question_count'] ?? 0);
                $showQ  = count($testQuestions);
                if ($totalQ > $showQ):
                ?>
                <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                <span class="text-cyan-400"><?php echo $showQ; ?> ta tasodifiy tanlandi</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="glass-card rounded-xl px-4 py-2 flex items-center gap-2 flex-shrink-0">
            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span id="timerText" class="text-sm font-mono font-semibold text-white">00:00</span>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="glass-card rounded-xl p-3 mb-6 flex items-center gap-4">
        <span class="text-xs text-slate-400 whitespace-nowrap">Javob berildi:</span>
        <div class="flex-1 progress-bar h-2">
            <div class="progress-fill h-full transition-all" id="answerProgressBar" style="width:0%"></div>
        </div>
        <span class="text-xs font-semibold text-cyan-400 whitespace-nowrap" id="answerCount">0 / <?php echo count($testQuestions); ?></span>
    </div>

    <form id="testForm" onsubmit="submitTest(event, <?php echo $moduleId; ?>)">
        <div class="space-y-5 mb-8">
            <?php $qNum = 0; foreach ($testQuestions as $qId => $q): $qNum++; ?>
            <div class="question-card glass-card rounded-2xl p-5 md:p-6 transition-all" id="qcard_<?php echo $qId; ?>">
                <!-- Savol header -->
                <div class="flex items-start gap-3 mb-5">
                    <span class="flex-shrink-0 w-8 h-8 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-xs font-bold text-cyan-400"><?php echo $qNum; ?></span>
                    <p class="text-sm font-medium text-white leading-relaxed pt-1"><?php echo htmlspecialchars($q['text']); ?></p>
                </div>
                <!-- Variantlar -->
                <div class="space-y-2.5 ml-11">
                    <?php foreach ($q['answers'] as $idx => $ans):
                        $letter = chr(65 + $idx); // A, B, C, D
                    ?>
                    <label class="answer-option group flex items-center gap-3 p-3.5 rounded-xl border border-slate-700/60 cursor-pointer transition-all hover:border-cyan-500/40 hover:bg-cyan-500/5"
                           data-question="<?php echo $qId; ?>"
                           data-value="<?php echo $ans['id']; ?>">
                        <input type="radio"
                               name="answers[<?php echo $qId; ?>]"
                               value="<?php echo $ans['id']; ?>"
                               class="sr-only"
                               onchange="selectAnswer(this, <?php echo $qId; ?>, <?php echo count($testQuestions); ?>)">
                        <!-- Custom radio -->
                        <span class="answer-radio flex-shrink-0 w-6 h-6 rounded-full border-2 border-slate-600 flex items-center justify-center transition-all">
                            <span class="answer-dot w-2.5 h-2.5 rounded-full bg-cyan-400 scale-0 transition-transform duration-200"></span>
                        </span>
                        <!-- Letter badge -->
                        <span class="flex-shrink-0 w-6 h-6 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-400 group-hover:border-cyan-500/40 transition-all answer-letter"><?php echo $letter; ?></span>
                        <!-- Text -->
                        <span class="text-sm text-slate-300 leading-relaxed"><?php echo htmlspecialchars($ans['text']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex items-center justify-between sticky bottom-4">
            <div class="glass-card rounded-xl px-4 py-2.5 text-xs text-slate-500">
                Barcha savollarga javob bering
            </div>
            <button type="submit" id="submitTestBtn"
                class="btn-primary px-8 py-3 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2 shadow-lg shadow-cyan-500/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Testni yakunlash
            </button>
        </div>
    </form>

    <?php else: ?>
    <!-- ═══ SAVOLLAR YO'Q ═══ -->
    <div class="glass-card rounded-2xl p-12 text-center max-w-lg mx-auto mt-8">
        <div class="w-20 h-20 rounded-2xl bg-slate-800/50 flex items-center justify-center mx-auto mb-5">
            <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-white mb-2">Test savollari mavjud emas</h3>
        <p class="text-sm text-slate-500 mb-6">Ushbu modul uchun hali test savollari qo'shilmagan.</p>
        <a href="?page=module&id=<?php echo $moduleId; ?>" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold text-white inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Orqaga qaytish
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
/* Answer option selected state */
.answer-option.selected {
    border-color: rgba(6, 182, 212, 0.6) !important;
    background: rgba(6, 182, 212, 0.08) !important;
}
.answer-option.selected .answer-radio {
    border-color: #06b6d4 !important;
}
.answer-option.selected .answer-dot {
    transform: scale(1) !important;
}
.answer-option.selected .answer-letter {
    background: rgba(6, 182, 212, 0.15) !important;
    border-color: rgba(6, 182, 212, 0.4) !important;
    color: #06b6d4 !important;
}
.answer-option.selected span.text-slate-300 {
    color: #e2e8f0 !important;
}
/* Question card answered */
.question-card.answered {
    border-color: rgba(6, 182, 212, 0.2);
}
</style>

<script>
// Har bir modul + foydalanuvchi uchun alohida kalit
const TIMER_KEY = 'test_timer_<?php echo $moduleId; ?>_<?php echo $userId; ?>';
const totalSeconds = <?php echo max(1, intval($currentModule['test_duration'] ?? 30)) * 60; ?>;

// Boshlanish vaqtini localStorage dan olish yoki yangi yozish
let startTime = localStorage.getItem(TIMER_KEY);
if (!startTime) {
    startTime = Date.now();
    localStorage.setItem(TIMER_KEY, startTime);
} else {
    startTime = parseInt(startTime);
}

const timerEl = document.getElementById('timerText');

function getRemainingSeconds() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    return Math.max(0, totalSeconds - elapsed);
}

function updateTimer() {
    const remaining = getRemainingSeconds();
    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
    const s = (remaining % 60).toString().padStart(2, '0');
    if (timerEl) {
        timerEl.textContent = m + ':' + s;
        if (remaining <= 60) {
            timerEl.classList.add('text-red-400');
            timerEl.classList.remove('text-white');
        } else {
            timerEl.classList.remove('text-red-400');
            timerEl.classList.add('text-white');
        }
    }
    if (remaining <= 0) {
        clearInterval(timerInterval);
        localStorage.removeItem(TIMER_KEY);
        doSubmitTest(<?php echo $moduleId; ?>);
    }
}

updateTimer();
let timerInterval = setInterval(updateTimer, 1000);

window.onbeforeunload = () => clearInterval(timerInterval);

// ── PROGRESS & ANSWER SELECTION ──
let answeredCount = 0;
const totalQuestions = <?php echo count($testQuestions); ?>;

function selectAnswer(radio, questionId, total) {
    document.querySelectorAll(`.answer-option[data-question="${questionId}"]`).forEach(opt => {
        opt.classList.remove('selected');
    });
    const label = radio.closest('.answer-option');
    label.classList.add('selected');
    const card = document.getElementById('qcard_' + questionId);
    if (card && !card.classList.contains('answered')) {
        card.classList.add('answered');
        answeredCount++;
        updateProgress();
    }
}

function updateProgress() {
    const pct = totalQuestions > 0 ? (answeredCount / totalQuestions * 100) : 0;
    const bar = document.getElementById('answerProgressBar');
    const cnt = document.getElementById('answerCount');
    if (bar) bar.style.width = pct + '%';
    if (cnt) cnt.textContent = answeredCount + ' / ' + totalQuestions;
}
</script>
