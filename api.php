<?php

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Faqat POST so\'roflar qabul qilinadi');
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save_answer':
        saveAnswer();
        break;

    case 'save_nc':
        saveNonConformity();
        break;

    case 'get_progress':
        getProgress();
        break;

    default:
        jsonResponse(false, 'Noma\'lum amal');
}

function saveAnswer()
{
    global $pdo;

    $auditId = (int) ($_POST['audit_id'] ?? 0);
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $answer = $_POST['answer'] ?? 'na'; // ha, yoq, na
    $comment = sanitize($_POST['comment'] ?? '');
    $userId = $_SESSION['user_id'] ?? 0;

    if (!$auditId || !$questionId || !in_array($answer, ['ha', 'yoq', 'na'])) {
        jsonResponse(false, 'Noto\'g\'ri ma\'lumotlar');
    }

    try {
        $stmt = $pdo->prepare("SELECT score FROM checklist_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();

        if (!$question) {
            jsonResponse(false, 'Savol topilmadi');
        }

        $score = $answer === 'ha' ? $question['score'] : 0;

        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = 'uploads/answers/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imagePath = $targetFile;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_answers (audit_id, question_id, auditor_id, answer, score, comment, image_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                answer = VALUES(answer), 
                score = VALUES(score), 
                comment = VALUES(comment), 
                image_path = IF(VALUES(image_path) IS NOT NULL, VALUES(image_path), image_path),
                answered_at = NOW()
        ");
        $stmt->execute([$auditId, $questionId, $userId, $answer, $score, $comment, $imagePath]);

        logActivity('audit_answer_saved', "Audit #{$auditId}, Savol #{$questionId} javobi saqlandi: " . strtoupper($answer), 'audit');

        updateAuditProgress($auditId);

        if ($answer === 'yoq') {
            jsonResponse(true, 'Javob saqlandi. Nomuvofiqlik formasi ochilmoqda...', [
                'need_nc' => true,
                'progress' => getAuditProgress($auditId)
            ]);
        }

        jsonResponse(true, 'Saqlandi', ['progress' => getAuditProgress($auditId)]);

    } catch (Exception $e) {
        jsonResponse(false, 'Xatolik: ' . $e->getMessage());
    }
}

function saveNonConformity()
{
    global $pdo;

    $auditId = (int) ($_POST['audit_id'] ?? 0);
    $questionId = (int) ($_POST['question_id'] ?? 0);
    $severityId = (int) ($_POST['severity_id'] ?? 0);
    $description = sanitize($_POST['description'] ?? '');
    $ncId = (int) ($_POST['nc_id'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 0;

    if (!$auditId || !$questionId || !$severityId || !$description) {
        jsonResponse(false, 'Tavsif va Jiddiylik darajasi majburiy!');
    }

    try {
        $stmtAns = $pdo->prepare("SELECT id FROM audit_answers WHERE audit_id = ? AND question_id = ?");
        $stmtAns->execute([$auditId, $questionId]);
        $answerId = $stmtAns->fetchColumn();

        if (!$answerId) {
            $stmtIns = $pdo->prepare("INSERT INTO audit_answers (audit_id, question_id, auditor_id, answer, score) VALUES (?, ?, ?, 'yoq', 0)");
            $stmtIns->execute([$auditId, $questionId, $userId]);
            $answerId = $pdo->lastInsertId();
        }

        if ($ncId) {
            $stmt = $pdo->prepare("UPDATE non_conformities SET severity_id = ?, description = ? WHERE id = ?");
            $stmt->execute([$severityId, $description, $ncId]);
            $ncCode = $pdo->prepare("SELECT nc_code FROM non_conformities WHERE id = ?")->execute([$ncId]) ? $ncId : 'NC';
        } else {
            $ncNumber = getNextNCNumber($auditId);
            $ncCode = sprintf("NC-%s-%03d", date('Y'), $ncNumber);

            $stmt = $pdo->prepare("
                INSERT INTO non_conformities (nc_code, audit_id, question_id, answer_id, nc_number, severity_id, description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ncCode, $auditId, $questionId, $answerId, $ncNumber, $severityId, $description, $userId]);
        }

        $progress = getAuditProgress($auditId);

        logActivity('NC_SAVED', "Nomuvofiqlik saqlandi: $ncCode");
        jsonResponse(true, 'Nomuvofiqlik saqlandi', ['progress' => $progress, 'nc_code' => $ncCode]);

    } catch (Exception $e) {
        jsonResponse(false, 'Xatolik: ' . $e->getMessage());
    }
}


function getNextNCNumber($auditId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(nc_number), 0) + 1 FROM non_conformities WHERE audit_id = ?");
    $stmt->execute([$auditId]);
    return $stmt->fetchColumn();
}

function updateAuditProgress($auditId)
{
    global $pdo;
    $progress = getAuditProgress($auditId);
    $stmt = $pdo->prepare("UPDATE audits SET progress_percent = ? WHERE id = ?");
    $stmt->execute([$progress, $auditId]);
}

function getAuditProgress($auditId)
{
    global $pdo;
    $total = $pdo->query("SELECT COUNT(*) FROM checklist_questions WHERE is_active = 1")->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_answers WHERE audit_id = ? AND answer != 'na'");
    $stmt->execute([$auditId]);
    $answered = $stmt->fetchColumn();
    return $total > 0 ? round(($answered / $total) * 100, 1) : 0;
}

function getProgress()
{
    global $pdo;
    $auditId = (int) ($_POST['audit_id'] ?? 0);
    $progress = getAuditProgress($auditId);
    jsonResponse(true, 'OK', ['progress' => $progress]);
}
?>