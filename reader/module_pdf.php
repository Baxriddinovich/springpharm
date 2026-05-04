<?php
ini_set('memory_limit', '256M');
set_time_limit(120);
require_once '../db.php';
requireLogin();
$user = getCurrentUser();

$moduleId = intval($_GET['module_id'] ?? 0);
$userId   = intval($_GET['user_id'] ?? 0);

if (!$moduleId || !$userId) {
    die("Parametrlar yetishmayapti.");
}

// Modul ma'lumotlari
$modStmt = $pdo->prepare("SELECT * FROM training_modules WHERE id = ?");
$modStmt->execute([$moduleId]);
$module = $modStmt->fetch(PDO::FETCH_ASSOC);
if (!$module) die("Modul topilmadi.");

// Foydalanuvchi ma'lumotlari
$userStmt = $pdo->prepare("SELECT u.*, p.name as position_name FROM users u LEFT JOIN positions p ON u.position_id = p.id WHERE u.id = ?");
$userStmt->execute([$userId]);
$targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$targetUser) die("Foydalanuvchi topilmadi.");

// Oxirgi test urinishi
$attemptStmt = $pdo->prepare("SELECT * FROM reader_test_attempts WHERE user_id = ? AND module_id = ? ORDER BY attempted_at DESC LIMIT 1");
$attemptStmt->execute([$userId, $moduleId]);
$attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
if (!$attempt) die("Bu foydalanuvchi uchun test natijasi topilmadi.");

// Test savollari (details JSON dan)
$details = [];
if (!empty($attempt['details'])) {
    $details = json_decode($attempt['details'], true) ?? [];
}

$isPassed   = $attempt['status'] === 'passed';
$score      = $attempt['score'];
$correct    = $attempt['correct_count'];
$total      = $attempt['total_count'];
$passing    = $attempt['passing_percent'];
$attemptDate = date('d.m.Y H:i', strtotime($attempt['attempted_at']));
$printDate  = date('d.m.Y H:i');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($module['title']); ?> — Test Natijasi</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
    font-size: 11px;
    color: #1e293b;
    background: #fff;
    line-height: 1.5;
}
.page {
    width: 210mm;
    min-height: 297mm;
    padding: 14mm 18mm 18mm 18mm;
    margin: 0 auto;
    background: #fff;
    position: relative;
}

/* ── HEADER ── */
.header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid #0e7490;
    padding-bottom: 10px;
    margin-bottom: 18px;
}
.header-left h1 {
    font-size: 18px;
    font-weight: 800;
    color: #0e7490;
    letter-spacing: -0.5px;
}
.header-left p {
    font-size: 10px;
    color: #64748b;
    margin-top: 2px;
}
.header-right {
    text-align: right;
    font-size: 10px;
    color: #475569;
}
.header-right .date {
    font-weight: 700;
    font-size: 11px;
    color: #1e293b;
}

/* ── INFO GRID ── */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 18px;
}
.info-box {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 12px;
    background: #f8fafc;
}
.info-box .label {
    font-size: 9px;
    font-weight: 700;
    color: #0e7490;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 6px;
}
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 10px;
}
.info-row:last-child { border-bottom: none; }
.info-row .key { color: #64748b; }
.info-row .val { font-weight: 600; color: #1e293b; text-align: right; max-width: 55%; }

/* ── RESULT BANNER ── */
.result-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 18px;
    border: 2px solid;
}
.result-banner.passed {
    background: #f0fdf4;
    border-color: #16a34a;
}
.result-banner.failed {
    background: #fef2f2;
    border-color: #dc2626;
}
.result-banner .verdict {
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 1px;
}
.result-banner.passed .verdict { color: #15803d; }
.result-banner.failed .verdict { color: #b91c1c; }
.result-banner .score-big {
    font-size: 28px;
    font-weight: 900;
    line-height: 1;
}
.result-banner.passed .score-big { color: #16a34a; }
.result-banner.failed .score-big { color: #dc2626; }
.result-banner .score-sub {
    font-size: 10px;
    color: #64748b;
    margin-top: 2px;
}
.stats-row {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
}
.stat-box {
    flex: 1;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 10px;
    text-align: center;
    background: #f8fafc;
}
.stat-box .num {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.1;
}
.stat-box .lbl {
    font-size: 9px;
    color: #64748b;
    margin-top: 2px;
}
.stat-box.green .num { color: #16a34a; }
.stat-box.red .num   { color: #dc2626; }
.stat-box.blue .num  { color: #0e7490; }
.stat-box.gray .num  { color: #475569; }

/* ── SECTION TITLE ── */
.section-title {
    font-size: 12px;
    font-weight: 700;
    color: #0e7490;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 5px;
    margin-bottom: 10px;
    margin-top: 18px;
}

/* ── QUESTIONS TABLE ── */
table.q-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    margin-bottom: 6px;
}
table.q-table th {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    padding: 6px 8px;
    font-weight: 700;
    color: #475569;
    text-align: left;
}
table.q-table td {
    border: 1px solid #e2e8f0;
    padding: 7px 8px;
    vertical-align: top;
}
table.q-table tr.correct-row td { background: #f0fdf4; }
table.q-table tr.wrong-row td   { background: #fef2f2; }

.q-num {
    font-weight: 700;
    color: #0e7490;
    white-space: nowrap;
}
.q-text { color: #1e293b; font-weight: 500; }
.q-answer { font-size: 9px; color: #475569; margin-top: 3px; }
.q-answer .ans-item {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 3px;
    margin: 1px 2px 1px 0;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}
.q-answer .ans-correct {
    background: #dcfce7;
    border-color: #86efac;
    color: #15803d;
    font-weight: 700;
}
.q-answer .ans-wrong {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #b91c1c;
    text-decoration: line-through;
}
.badge-correct {
    display: inline-block;
    background: #16a34a;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    white-space: nowrap;
}
.badge-wrong {
    display: inline-block;
    background: #dc2626;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    white-space: nowrap;
}

/* ── FOOTER ── */
.footer {
    position: fixed;
    bottom: 12mm;
    left: 18mm;
    right: 18mm;
    border-top: 1px solid #e2e8f0;
    padding-top: 5px;
    display: flex;
    justify-content: space-between;
    font-size: 8px;
    color: #94a3b8;
}

/* ── SIGNATURE ── */
.signature-section {
    margin-top: 30px;
    display: flex;
    justify-content: space-between;
    gap: 20px;
}
.sig-box {
    flex: 1;
    border-top: 1px dotted #94a3b8;
    padding-top: 6px;
    text-align: center;
    font-size: 9px;
    color: #64748b;
}

@media print {
    body { margin: 0; }
    .page { margin: 0; padding: 14mm 18mm 18mm 18mm; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════
     PRINT BUTTON (ekranda ko'rinadi, chop etishda yo'q)
══════════════════════════════════════════════════════ -->
<div class="no-print" style="position:fixed;top:16px;right:16px;z-index:999;display:flex;gap:8px;">
    <button onclick="window.print()"
        style="background:#0e7490;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(14,116,144,0.3);">
        ⬇ PDF Yuklash / Chop etish
    </button>
    <button onclick="window.close()"
        style="background:#475569;color:#fff;border:none;padding:10px 16px;border-radius:8px;font-size:13px;cursor:pointer;">
        ✕ Yopish
    </button>
</div>

<div class="page">

    <!-- ── HEADER ── -->
    <div class="header">
        <div class="header-left">
            <h1>Spring Pharmaceutical</h1>
            <p>GMP O'quv Tizimi — Test Natijasi Hisoboti</p>
        </div>
        <div class="header-right">
            <div class="date"><?php echo $printDate; ?></div>
            <div>Hisobot sanasi</div>
        </div>
    </div>

    <!-- ── INFO GRID ── -->
    <div class="info-grid">
        <!-- Modul ma'lumotlari -->
        <div class="info-box">
            <div class="label">Modul ma'lumotlari</div>
            <div class="info-row">
                <span class="key">Mavzu nomi</span>
                <span class="val"><?php echo htmlspecialchars($module['title']); ?></span>
            </div>
            <div class="info-row">
                <span class="key">Modul kodi</span>
                <span class="val" style="font-family:monospace;"><?php echo htmlspecialchars($module['code']); ?></span>
            </div>
            <div class="info-row">
                <span class="key">Murabbiy</span>
                <span class="val"><?php echo htmlspecialchars($module['tutor_name'] ?: '—'); ?></span>
            </div>
            <div class="info-row">
                <span class="key">O'qish turi</span>
                <span class="val"><?php echo htmlspecialchars($module['type'] ?: '—'); ?></span>
            </div>
            <div class="info-row">
                <span class="key">O'qish shakli</span>
                <span class="val"><?php echo htmlspecialchars($module['style'] ?: '—'); ?></span>
            </div>
            <div class="info-row">
                <span class="key">Kategoriya</span>
                <span class="val"><?php echo htmlspecialchars($module['training_type'] ?: '—'); ?></span>
            </div>
        </div>

        <!-- Xodim ma'lumotlari -->
        <div class="info-box">
            <div class="label">Xodim ma'lumotlari</div>
            <div class="info-row">
                <span class="key">F.I.Sh</span>
                <span class="val"><?php echo htmlspecialchars($targetUser['full_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="key">Foydalanuvchi nomi</span>
                <span class="val"><?php echo htmlspecialchars($targetUser['username']); ?></span>
            </div>
            <div class="info-row">
                <span class="key">Lavozim</span>
                <span class="val"><?php echo htmlspecialchars($targetUser['position_name'] ?: '—'); ?></span>
            </div>
            <div class="info-row">
                <span class="key">Test topshirilgan sana</span>
                <span class="val"><?php echo $attemptDate; ?></span>
            </div>
            <div class="info-row">
                <span class="key">O'tish balli</span>
                <span class="val"><?php echo $passing; ?>%</span>
            </div>
        </div>
    </div>

    <!-- ── RESULT BANNER ── -->
    <div class="result-banner <?php echo $isPassed ? 'passed' : 'failed'; ?>">
        <div>
            <div class="verdict"><?php echo $isPassed ? '✓  MUVAFFAQIYATLI O\'TDI' : '✗  O\'TMADI'; ?></div>
            <div style="font-size:10px;color:#64748b;margin-top:4px;">
                O'tish balli: <?php echo $passing; ?>% &nbsp;|&nbsp;
                Topshirilgan: <?php echo $attemptDate; ?>
            </div>
        </div>
        <div style="text-align:right;">
            <div class="score-big"><?php echo $score; ?>%</div>
            <div class="score-sub"><?php echo $correct; ?> / <?php echo $total; ?> to'g'ri</div>
        </div>
    </div>

    <!-- ── STATS ROW ── -->
    <div class="stats-row">
        <div class="stat-box blue">
            <div class="num"><?php echo $total; ?></div>
            <div class="lbl">Jami savol</div>
        </div>
        <div class="stat-box green">
            <div class="num"><?php echo $correct; ?></div>
            <div class="lbl">To'g'ri javob</div>
        </div>
        <div class="stat-box red">
            <div class="num"><?php echo $total - $correct; ?></div>
            <div class="lbl">Noto'g'ri javob</div>
        </div>
        <div class="stat-box <?php echo $isPassed ? 'green' : 'red'; ?>">
            <div class="num"><?php echo $score; ?>%</div>
            <div class="lbl">Natija</div>
        </div>
        <div class="stat-box gray">
            <div class="num"><?php echo $passing; ?>%</div>
            <div class="lbl">O'tish balli</div>
        </div>
    </div>

    <!-- ── QUESTIONS TABLE ── -->
    <?php if (!empty($details)): ?>
    <div class="section-title">Savollar tahlili</div>
    <table class="q-table">
        <thead>
            <tr>
                <th style="width:4%;">№</th>
                <th style="width:46%;">Savol</th>
                <th style="width:38%;">Variantlar</th>
                <th style="width:12%;text-align:center;">Natija</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($details as $i => $d):
            $isCorrect = $d['is_correct'];
            $rowClass  = $isCorrect ? 'correct-row' : 'wrong-row';
            $answers   = $d['answers'] ?? [];
            $selectedId = $d['selected'];
            $correctId  = $d['correct_id'];
        ?>
        <tr class="<?php echo $rowClass; ?>">
            <td class="q-num"><?php echo $i + 1; ?>.</td>
            <td>
                <div class="q-text"><?php echo htmlspecialchars($d['question']); ?></div>
            </td>
            <td>
                <div class="q-answer">
                <?php
                $letters = ['A', 'B', 'C', 'D'];
                $li = 0;
                foreach ($answers as $aId => $aText):
                    $letter = $letters[$li] ?? chr(65 + $li);
                    $li++;
                    $isThisCorrect  = ($aId == $correctId);
                    $isThisSelected = ($aId == $selectedId);
                    $cls = '';
                    if ($isThisCorrect)  $cls = 'ans-correct';
                    elseif ($isThisSelected && !$isThisCorrect) $cls = 'ans-wrong';
                ?>
                <span class="ans-item <?php echo $cls; ?>">
                    <strong><?php echo $letter; ?>)</strong> <?php echo htmlspecialchars($aText); ?>
                    <?php if ($isThisCorrect): ?> ✓<?php endif; ?>
                    <?php if ($isThisSelected && !$isThisCorrect): ?> ✗<?php endif; ?>
                </span>
                <?php endforeach; ?>
                </div>
            </td>
            <td style="text-align:center;vertical-align:middle;">
                <?php if ($isCorrect): ?>
                <span class="badge-correct">To'g'ri</span>
                <?php else: ?>
                <span class="badge-wrong">Xato</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="padding:20px;text-align:center;color:#94a3b8;font-size:11px;">
        Savol tafsilotlari mavjud emas.
    </div>
    <?php endif; ?>

    <!-- ── SIGNATURE ── -->
    <div class="signature-section" style="margin-top:<?php echo empty($details) ? '40px' : '24px'; ?>;">
        <div class="sig-box">
            <div style="height:30px;"></div>
            Xodim imzosi
        </div>
        <div class="sig-box">
            <div style="height:30px;"></div>
            Murabbiy imzosi
        </div>
        <div class="sig-box">
            <div style="height:30px;"></div>
            Mas'ul shaxs imzosi
        </div>
    </div>

    <!-- ── FOOTER ── -->
    <div class="footer">
        <span>Spring Pharmaceutical — GMP O'quv Tizimi</span>
        <span>Modul: <?php echo htmlspecialchars($module['code']); ?> | Xodim: <?php echo htmlspecialchars($targetUser['full_name']); ?></span>
        <span>Chop etildi: <?php echo $printDate; ?></span>
    </div>

</div>

<script>
// Avtomatik print dialog ochish (ixtiyoriy)
// window.onload = () => window.print();
</script>
</body>
</html>
