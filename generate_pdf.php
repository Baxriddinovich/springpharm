<?php
ini_set('memory_limit', '512M');
set_time_limit(300);
require_once 'db.php';


if (file_exists('libs/phpqrcode.php')) {
    require_once 'libs/phpqrcode.php';
}
requireLogin();

$auditId = (int)($_GET['audit'] ?? 0);

if (!$auditId) {
    die("Audit ID berilmagan!");
}

$stmt = $pdo->prepare("
    SELECT a.*, s.name as site_name, s.address, 
           u.full_name as creator_name
    FROM audits a
    JOIN sites s ON a.site_id = s.id
    JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
");
$stmt->execute([$auditId]);
$audit = $stmt->fetch();
$signaturesStmt = $pdo->prepare("
    SELECT u.full_name, u.role, aus.signed_at
    FROM audit_signatures aus
    JOIN users u ON u.id = aus.user_id
    WHERE aus.audit_id = ?
    ORDER BY u.role = 'bosh_auditor' DESC, aus.signed_at
");
$signaturesStmt->execute([$auditId]);
$signatures = $signaturesStmt->fetchAll();
if (!$audit) {
    die("Audit topilmadi!");
}
$auditorsStmt = $pdo->prepare("
    SELECT DISTINCT u.full_name, u.role
    FROM audit_assignments aa
    JOIN users u ON u.id = aa.auditor_id
    WHERE aa.audit_id = ?
    ORDER BY u.role = 'bosh_auditor' DESC, u.full_name
");
$auditorsStmt->execute([$auditId]);
$auditAuditors = $auditorsStmt->fetchAll();
// 1. Bo'limlar bo'yicha statistikani olish (1-sahifa uchun)
$sectionStatsStmt = $pdo->prepare("
    SELECT gs.id, gs.section_number, gs.section_name,
           COUNT(cq.id) as total_questions,
           SUM(CASE WHEN aa.answer = 'ha' THEN 1 ELSE 0 END) as yes_count,
           SUM(CASE WHEN aa.answer = 'yoq' THEN 1 ELSE 0 END) as no_count,
           COALESCE(SUM(aa.score), 0) as earned_score,
           SUM(cq.score) as max_score
    FROM gmp_sections gs
    LEFT JOIN checklist_questions cq ON cq.section_id = gs.id AND cq.is_active = 1
    LEFT JOIN audit_answers aa ON aa.question_id = cq.id AND aa.audit_id = ?
    GROUP BY gs.id
    ORDER BY gs.sort_order
");
$sectionStatsStmt->execute([$auditId]);
$sectionsStats = $sectionStatsStmt->fetchAll();

// 2. Batafsil savollar va javoblar (Keyingi sahifalar uchun)
$detailsStmt = $pdo->prepare("
    SELECT 
        gs.id as section_id,
        gs.section_number,
        gs.section_name,
        cq.id as question_id,
        cq.question_text,
        cq.score as max_score,
        aa.answer,
        aa.score as earned_score,
        aa.comment,
        aa.image_path,
        nc.nc_code,
        nc.nc_number,
        nc.description as nc_description,
        st.name as severity_name,
        st.color_code
    FROM gmp_sections gs
    JOIN checklist_questions cq ON cq.section_id = gs.id AND cq.is_active = 1
    LEFT JOIN audit_answers aa ON aa.question_id = cq.id AND aa.audit_id = ?
    LEFT JOIN non_conformities nc ON nc.question_id = cq.id AND nc.audit_id = ?
    LEFT JOIN severity_types st ON nc.severity_id = st.id
    ORDER BY gs.sort_order, cq.sort_order
");
$detailsStmt->execute([$auditId, $auditId]);
$allDetails = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

// Ma'lumotlarni bo'limlarga guruhlash
$groupedDetails = [];
foreach ($allDetails as $row) {
    $groupedDetails[$row['section_id']][] = $row;
}

// 3. Nomuvofiqliklar ro'yxati
$ncsStmt = $pdo->prepare("
    SELECT nc.*, st.name as severity_name, st.color_code, 
           cq.question_text, gs.section_number,
           aa.comment
    FROM non_conformities nc
    JOIN severity_types st ON nc.severity_id = st.id
    JOIN checklist_questions cq ON nc.question_id = cq.id
    JOIN gmp_sections gs ON cq.section_id = gs.id
    LEFT JOIN audit_answers aa ON aa.question_id = nc.question_id AND aa.audit_id = nc.audit_id
    WHERE nc.audit_id = ?
    ORDER BY nc.nc_number
");
$ncsStmt->execute([$auditId]);
$nonConformities = $ncsStmt->fetchAll();

// Umumiy hisob-kitoblar
$totalEarned = array_sum(array_column($sectionsStats, 'earned_score'));
$totalMax = array_sum(array_column($sectionsStats, 'max_score'));
$percentage = $totalMax > 0 ? round(($totalEarned / $totalMax) * 100, 1) : 0;

// Sana formatlash
$dateFormatted = date('l d F Y', strtotime($audit['start_date'])); // Sunday 05 April 2026
$shortDate = date('d M y', strtotime($audit['start_date'])); // 05 APR 26

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <title>GMP Audit Hisoboti - <?php echo htmlspecialchars($audit['audit_code']); ?></title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: white;
            margin: 0;
            padding: 0;
        }

        /* Sahifa tuzilishi */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            margin: 0 auto;
            background: white;
            box-sizing: border-box;
            page-break-after: always;
            position: relative;
        }

        .last-page {
            page-break-after: avoid;
        }

        /* Header qismlari */
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0e7490;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header-main h1 {
            color: #0e7490;
            font-size: 20px;
            margin: 0;
            font-weight: 800;
        }

        .header-main h2 {
            color: #64748b;
            font-size: 12px;
            margin: 2px 0 0 0;
            font-weight: 500;
        }

        .header-date {
            text-align: right;
            font-size: 11px;
            color: #333;
            font-weight: bold;
        }

        .page-header-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f1f5f9;
            padding: 5px 10px;
            border-left: 4px solid #0e7490;
            margin-bottom: 15px;
        }

        .page-header-detail .site-name {
            font-weight: bold;
            font-size: 12px;
        }

        .page-header-detail .audit-title {
            color: #0e7490;
        }

        /* Umumiy Statistika (1-sahifa) */
        .stats-container {
            text-align: center;
            margin: 30px 0;
        }

        .score-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 12px solid <?php echo $percentage >= 80 ? '#10b981' : ($percentage >= 60 ? '#f59e0b' : '#ef4444'); ?>;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            margin: 0 auto;
            color: #333;
        }

        .score-circle span {
            font-size: 12px;
            font-weight: normal;
            color: #666;
        }

        /* Jadval uslublari */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            font-weight: bold;
            color: #475569;
        }

        .section-title {
            margin-top: 20px;
            font-size: 14px;
            font-weight: bold;
            color: #0e7490;
            padding: 4px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-percent {
            float: right;
            font-weight: bold;
        }

        /* Status belgilari */
        .status-yes {
            color: #16a34a;
            font-weight: bold;
        }

        .status-no {
            color: #dc2626;
            font-weight: bold;
        }

        .status-na {
            color: #64748b;
        }

        .nc-badge {
            display: inline-block;
            font-size: 9px;
            padding: 2px 5px;
            border-radius: 4px;
            color: white;
            background: #dc2626;
            margin-left: 5px;
        }

        /* Footer */
        .footer {
            position: absolute;
            bottom: 15mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }

        /* Declaratsiya */
        .declaration-box {
            margin-top: 40px;
            border: 1px solid #cbd5e1;
            padding: 20px;
            background: #f8fafc;
        }

        .declaration-title {
            text-align: center;
            letter-spacing: 4px;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #334155;
        }

        .signature-area {
            margin-top: 40px;
            border-top: 1px dotted #94a3b8;
            width: 200px;
            text-align: center;
            padding-top: 5px;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>

<body>

    <!-- 1. BIRINCHI SAHIFA: Umumiy Hisobot -->
    <div class="page">
        <div class="header-main">
            <div>
                <h1><?php echo htmlspecialchars($audit['site_name']); ?></h1>
                <h2><?php echo htmlspecialchars($audit['title']); ?></h2>
            </div>
            <div class="header-date">
                <?php echo $dateFormatted; ?>
            </div>
        </div>

        <!-- Korxona manzili va auditorlar -->
        <div style="display: flex; justify-content: space-between; gap: 20px; margin-bottom: 25px;">

            <!-- Chap: Korxona manzili -->
            <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; background: #f8fafc;">
                <div style="font-size: 10px; font-weight: 700; color: #0e7490; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Korxona ma'lumotlari</div>
                <div style="font-size: 12px; font-weight: bold; color: #1e293b; margin-bottom: 4px;">
                    <?php echo htmlspecialchars($audit['site_name']); ?>
                </div>
                <?php if (!empty($audit['address'])): ?>
                    <div style="font-size: 10px; color: #475569; display: flex; align-items: flex-start; gap: 4px;">
                        <span style="color: #0e7490;">📍</span>
                        <?php echo htmlspecialchars($audit['address']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- O'ng: Auditorlar ro'yxati -->
            <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; background: #f8fafc;">
                <div style="font-size: 10px; font-weight: 700; color: #0e7490; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Auditorlar ro'yxati</div>
                <table style="width: 100%; border: none; border-collapse: collapse;">
                    <?php foreach ($auditAuditors as $aud):
                        $roleName = $aud['role'] === 'bosh_auditor' ? 'Bosh auditor' : 'Auditor';
                        // Familiya va ismni qisqartirish: "Ashurov Javohir" → "J. Ashurov"
                        $nameParts = explode(' ', trim($aud['full_name']));
                        $shortName = count($nameParts) >= 2
                            ? $nameParts[0] . ' ' . mb_strtoupper(mb_substr($nameParts[1], 0, 1)) . '.'
                            : $aud['full_name'];
                    ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="border: none; padding: 5px 0; font-size: 11px; color: #475569; width: 50%;">
                                <?php echo $roleName; ?>
                            </td>
                            <td style="border: none; padding: 5px 0; font-size: 11px; font-weight: 600; color: #1e293b;">
                                <?php echo htmlspecialchars($shortName); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="stats-container">
            <div class="score-circle">
                <?php echo $percentage; ?>%
                <span>(<?php echo number_format($totalEarned, 1); ?>/<?php echo number_format($totalMax, 1); ?>)</span>
            </div>
            <p style="margin-top: 10px; font-weight: bold; color: #475569;">Umumiy moslik darajasi</p>
        </div>

        <h3 style="text-align: center; margin-bottom: 10px;">B O ' L I M L A R B O ' Y I C H A B A L L</h3>

        <table>
            <thead>
                <tr>
                    <th>Bo'lim</th>
                    <th style="text-align: center;">Haqiqiy</th>
                    <th style="text-align: center;">Maqsad</th>
                    <th style="text-align: center;">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sectionsStats as $sec):
                    $secPercent = $sec['max_score'] > 0 ? round(($sec['earned_score'] / $sec['max_score']) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><strong><?php echo $sec['section_number']; ?></strong> - <?php echo htmlspecialchars($sec['section_name']); ?></td>
                        <td style="text-align: center;"><?php echo number_format($sec['earned_score'], 1); ?></td>
                        <td style="text-align: center;"><?php echo number_format($sec['max_score'], 1); ?></td>
                        <td style="text-align: center; font-weight: bold; color: <?php echo $secPercent >= 80 ? '#16a34a' : '#dc2626'; ?>;"><?php echo $secPercent; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: bold;">
                    <td>JAMI</td>
                    <td style="text-align: center;"><?php echo number_format($totalEarned, 1); ?></td>
                    <td style="text-align: center;"><?php echo number_format($totalMax, 1); ?></td>
                    <td style="text-align: center;"><?php echo $percentage; ?>%</td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <p>Ref: <?php echo $audit['audit_code']; ?> : <?php echo date('d,F Y H:i:s'); ?> UZT    GMP Audit System</p>
        </div>
    </div>

    <!-- 2. BATAFSIL SAVOLLAR (Har bo'lim uchun) -->
    <?php
    $pageCounter = 1;
    foreach ($groupedDetails as $secId => $questions):
        $firstQ = $questions[0];

        // Bo'lim statistikasini hisoblash
        $secEarned = 0;
        $secMax = 0;
        $secYes = 0;
        $secTotal = count($questions);
        foreach ($questions as $q) {
            $secEarned += (float)$q['earned_score'];
            $secMax += (float)$q['max_score'];
            if ($q['answer'] === 'ha') $secYes++;
        }
        $secPercent = $secMax > 0 ? round(($secEarned / $secMax) * 100, 1) : 0;
    ?>
        <div class="page">
            <div class="page-header-detail">
                <div>
                    <div class="site-name">SITE: <?php echo htmlspecialchars($audit['site_name']); ?></div>
                    <div class="audit-title"><?php echo htmlspecialchars($audit['title']); ?></div>
                </div>
                <div style="text-align: right; font-weight: bold;">
                    <?php echo $shortDate; ?>
                </div>
            </div>

            <div class="section-title">
                <?php echo $firstQ['section_number']; ?>. <?php echo htmlspecialchars($firstQ['section_name']); ?>
                <span class="section-percent">(<?php echo $secYes; ?>/<?php echo $secTotal; ?>) <?php echo $secPercent; ?> %</span>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-family: 'DejaVu Sans', sans-serif; table-layout: fixed; border: 1px solid #94a3b8;">
                <thead>
                    <tr style="background-color: #f1f5f9;">
                        <th style="width: 5%; padding: 8px; border: 1px solid #94a3b8; text-align: center; font-size: 10px;">№</th>
                        <th style="width: 65%; padding: 8px; border: 1px solid #94a3b8; text-align: left; font-size: 10px;">Savollar</th>
                        <th style="width: 15%; padding: 8px; border: 1px solid #94a3b8; text-align: center; font-size: 10px;">Ball</th>
                        <th style="width: 15%; padding: 8px; border: 1px solid #94a3b8; text-align: center; font-size: 10px;">Javob</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $qNum = 1;
                    foreach ($questions as $q): ?>
                        <tr style="page-break-inside: avoid;">
                            <!-- 1. Tartib raqami -->
                            <td style="padding: 10px; border: 1px solid #e2e8f0; vertical-align: top; text-align: center; font-size: 11px;">
                                <?php echo $qNum++; ?>.
                            </td>

                            <td style="padding: 10px; border: 1px solid #e2e8f0; vertical-align: top;">
                                <!-- Savol matni -->
                                <div style="font-size: 11px; color: #1e293b; font-weight: bold; margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($q['question_text'], ENT_COMPAT, 'UTF-8'); ?>
                                </div>

                                <?php
                                // 1. Bazadan kelgan yo'lni tekshirish
                                $dbImg = trim($q['image_path'] ?? '');

                                if (!empty($dbImg)) {
                                    // 2. Serverdagi TO'LIQ (ABSOLYUT) manzil
                                    $serverRoot = '/var/www/fastuser/data/www/springpharmaceutic.uz/';
                                    $fullPath = $serverRoot . ltrim($dbImg, '/');

                                    // 3. Fayl borligini tekshiramiz
                                    if (file_exists($fullPath) && is_file($fullPath)) {
                                        // PDF uchun eng ishonchli usul - Base64 ga o'girish
                                        $imgData = base64_encode(file_get_contents($fullPath));
                                        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                                        $mime = ($ext == 'png') ? 'image/png' : 'image/jpeg';
                                        $src = 'data:' . $mime . ';base64,' . $imgData;

                                        echo '<div style="margin: 5px 0;">';
                                        echo '<img src="' . $src . '" style="width: 250px; height: auto; display: block; border: 1px solid #ccc;">';
                                        echo '</div>';
                                    } else {
                                        // Agar rasm chiqmasa, bizga sababini aytadi (faqat tahrir paytida ko'rinadi)
                                        echo '<div style="font-size: 8px; color: red;">Fayl topilmadi: ' . $dbImg . '</div>';
                                    }
                                }
                                ?>
                                <!-- NC raqami faqat YO'Q javobida chiqadi -->
                                <?php if (!empty($q['nc_code']) && $q['answer'] === 'yoq'): ?>
                                    <div style="font-size: 10px; color: #dc2626; margin-top: 5px; padding: 6px 8px; 
            background: #fef2f2; border-left: 3pt solid #dc2626; border-radius: 0 4px 4px 0;">
                                        <div style="font-weight: bold; margin-bottom: 3px; font-size: 9px; color: #991b1b;">
                                            ⚠ Nomuvofiqlik <?php echo (int)$q['nc_number']; ?>
                                        </div>
                                        <?php if (!empty($q['comment'])): ?>
                                            <strong>Izoh:</strong> <?php echo html_entity_decode($q['comment'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- HA yoki TEGISHLI EMAS + izoh bo'lsa yashil -->
                                <?php elseif (!empty($q['comment'])): ?>
                                    <div style="font-size: 10px; color: #15803d; margin-top: 5px; padding: 6px 8px; 
            background: #f0fdf4; border-left: 3pt solid #16a34a; border-radius: 0 4px 4px 0;">
                                        <strong>Izoh:</strong> <?php echo html_entity_decode($q['comment'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <!-- Ball -->
                            <td style="padding: 10px; border: 1px solid #e2e8f0; vertical-align: middle; text-align: center; font-size: 11px;">
                                <span style="font-weight: 600; color: #1e293b;">
                                    <?php echo number_format($q['earned_score'] ?? 0, 1); ?> / <?php echo number_format($q['max_score'] ?? 0, 1); ?>
                                </span>
                            </td>

                            <!-- Javob holati -->
                            <td style="padding: 10px; border: 1px solid #e2e8f0; vertical-align: middle; text-align: center; font-size: 11px;">
                                <?php
                                $ans = strtolower(trim($q['answer'] ?? ''));
                                if ($ans === 'ha'): ?>
                                    <span style="color: #16a34a; font-weight: bold; background: #f0fdf4; padding: 4px 8px; border: 1px solid #bbf7d0; border-radius: 4px;">HA</span>
                                <?php elseif ($ans === 'yoq' || $ans === "yo'q"): ?>
                                    <span style="color: #dc2626; font-weight: bold; background: #fef2f2; padding: 4px 8px; border: 1px solid #fecaca; border-radius: 4px;">YO'Q</span>
                                <?php else: ?>
                                    <span style="color: #64748b; font-style: italic;">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="footer">
                Sahifa <?php echo ++$pageCounter; ?>    Ref:<?php echo $audit['audit_code']; ?> : <?php echo date('d,F Y H:i:s'); ?> UZT    GMP Audit System
            </div>
        </div>
    <?php endforeach; ?>

    <!-- 3. NOMUVOFIQLIKLAR SAHIFASI -->
    <?php if (!empty($nonConformities)): ?>
        <div class="page">
            <div class="page-header-detail">
                <div>
                    <div class="site-name">SITE: <?php echo htmlspecialchars($audit['site_name']); ?></div>
                    <div class="audit-title"><?php echo htmlspecialchars($audit['title']); ?></div>
                </div>
                <div style="text-align: right; font-weight: bold;">
                    <?php echo $shortDate; ?>
                </div>
            </div>

            <div class="section-title">Nomuvofiqliklar Ro'yxati</div>
            <p style="font-size: 11px; margin-bottom: 10px;">Jami aniqlangan nomuvofiqliklar: <strong><?php echo count($nonConformities); ?></strong></p>

            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">№</th>
                        <th style="width: 35%;">Savol</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 20%;">Tavsif</th>
                        <th style="width: 25%;">Izoh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $ncNum = 1;
                    foreach ($nonConformities as $nc): ?>
                        <tr>
                            <td><?php echo $ncNum++; ?></td>
                            <td style="font-size: 9px;"><?php echo htmlspecialchars(substr($nc['question_text'], 0, 80)); ?>...</td>
                            <td><span style="color: <?php echo $nc['color_code']; ?>; font-weight: bold;"><?php echo $nc['severity_name']; ?></span></td>
                            <td style="font-size: 9px;"><?php echo html_entity_decode($nc['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="font-size: 9px; color: #dc2626;"><?php echo html_entity_decode($nc['comment'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 10px; display: flex; gap: 10px;">
                <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 10px; font-size: 9px;">Jiddiy bo'lmagan</span>
                <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 9px;">Jiddiy</span>
                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 9px;">O'ta jiddiy</span>
            </div>

            <div class="footer">
                Sahifa <?php echo ++$pageCounter; ?>    Ref:<?php echo $audit['audit_code']; ?> : <?php echo date('d,F Y H:i:s'); ?> UZT    GMP Audit System
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. DECLARATSIYA SAHIFASI -->
    <div class="page last-page">
        <div class="page-header-detail">
            <div>
                <div class="site-name">SITE: <?php echo htmlspecialchars($audit['site_name']); ?></div>
                <div class="audit-title"><?php echo htmlspecialchars($audit['title']); ?></div>
            </div>
            <div style="text-align: right; font-weight: bold;">
                <?php echo $shortDate; ?>
            </div>
        </div>

        <div class="declaration-box">
            <div class="declaration-title">D E K L A R A T S I Y A</div>

            <p style="line-height: 1.6; text-align: justify;">
                Ushbu hisobot <?php echo htmlspecialchars($audit['site_name']); ?> korxonasida
                <?php echo date('d.m.Y', strtotime($audit['start_date'])); ?> sanasi va
                <?php echo date('d.m.Y', strtotime($audit['end_date'] ?? $audit['start_date'])); ?> sanalari oralig'ida o'tkazilgan
                GMP (Yaxshi Ishlab Chiqarish Amaliyoti) auditi natijalari asosida tuzildi.
            </p>

            <p style="line-height: 1.6; text-align: justify; margin-top: 15px;">
                Audit jarayonida korxona faoliyati standart talablariga mosligi tekshirildi va
                natijada umumiy moslik darajasi <strong><?php echo $percentage; ?>%</strong> tashkil etdi.
                Hisobotda keltirilgan barcha ma'lumotlar haqqoniy va ishonchli manbalarga asoslangan.
            </p>

            <table style="width: 100%; border: none; margin-top: 40px; border-collapse: collapse;">
                <?php 
                $sigGroups = [
                    'auditor' => ['title' => 'Auditor(lar)', 'items' => []],
                    'bosh_auditor' => ['title' => 'Bosh Auditor', 'items' => []],
                    'super_admin' => ['title' => 'Tizim Administratori', 'items' => []]
                ];

                foreach ($signatures as $sig) {
                    if (isset($sigGroups[$sig['role']])) {
                        $sigGroups[$sig['role']]['items'][] = $sig;
                    }
                }

                if (!empty($signatures)): ?>
                    <?php foreach (['auditor', 'bosh_auditor', 'super_admin'] as $roleKey): 
                        if (empty($sigGroups[$roleKey]['items'])) continue;
                        
                        foreach ($sigGroups[$roleKey]['items'] as $sig):
                            $roleName = $sigGroups[$roleKey]['title'];
                            $nameParts = explode(' ', trim($sig['full_name']));
                            $shortName = count($nameParts) >= 2
                                ? $nameParts[0] . ' ' . mb_strtoupper(mb_substr($nameParts[1], 0, 1)) . '.'
                                : $sig['full_name'];

                            // QR kod mazmuni
                            $qrContent = "GMP AUDIT REPORT\n";
                            $qrContent .= "Code: " . $audit['audit_code'] . "\n";
                            $qrContent .= "Signed by: " . $sig['full_name'] . "\n";
                            $qrContent .= "Role: " . $roleName . "\n";
                            $qrContent .= "Date: " . date('d.m.Y H:i', strtotime($sig['signed_at'])) . "\n";
                            $qrContent .= "Score: " . $percentage . "%";

                            $qrImageTag = '';
                            if (class_exists('QRcode')) {
                                ob_start();
                                QRcode::png($qrContent, null, QR_ECLEVEL_M, 4, 1);
                                $qrData = base64_encode(ob_get_clean());
                                $qrImageTag = '<img src="data:image/png;base64,' . $qrData . '" 
                                   style="width: 60px; height: 60px; border: 1px solid #ddd; padding: 2px;">';
                            }
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="border: none; width: 70%; vertical-align: middle; padding: 12px 0;">
                                <div style="font-weight: 700; color: #64748b; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                                    <?php echo $roleName; ?>
                                </div>
                                <div style="font-size: 13px; font-weight: 800; color: #1e293b; border-bottom: 1px solid #cbd5e1; display: inline-block; padding-bottom: 2px; min-width: 250px;">
                                    <?php echo htmlspecialchars($sig['full_name']); ?>
                                </div>
                                <div style="font-size: 10px; color: #10b981; margin-top: 5px; font-weight: 500;">
                                    <span style="border: 1px solid #10b981; border-radius: 3px; padding: 1px 4px; font-size: 8px; margin-right: 4px;">VERIFIED</span>
                                    Raqamli imzo qo'yildi: <?php echo date('d.m.Y H:i', strtotime($sig['signed_at'])); ?>
                                </div>
                            </td>
                            <td style="border: none; width: 30%; text-align: right; vertical-align: middle; padding: 12px 0;">
                                <div style="display: inline-block; text-align: center;">
                                    <?php echo $qrImageTag; ?>
                                    <div style="font-size: 7px; color: #94a3b8; margin-top: 2px; font-weight: bold; font-family: 'Courier New', Courier, monospace;">
                                        SECURE ID: <?php echo substr(md5($sig['signed_at'] . $sig['full_name']), 0, 8); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="2" style="border: none; color: #ef4444; font-size: 11px; padding: 20px 0; text-align: center; background: #fef2f2; border-radius: 8px;">
                            ⚠ Ushbu hisobot hali raqamli imzo bilan tasdiqlanmagan.
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <div class="footer">
                Sahifa <?php echo ++$pageCounter; ?>    Ref:<?php echo $audit['audit_code']; ?> : <?php echo date('d,F Y H:i:s'); ?> UZT    GMP Audit System
            </div>
        </div>

        <script>
            // Agar avtomatik chop etish kerak bo'lsa, quyidagi qator kommentariyadan oling
            // window.onload = function() { window.print(); }
        </script>
</body>

</html>