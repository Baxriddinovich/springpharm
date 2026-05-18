<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Faqat QC xodimi, QC rahbari va Admin kirishi mumkin
if (!in_array($_SESSION['role'], ['Admin', 'QC xodimi', 'QC rahbari'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Karantindagi mahsulotlarni olish
$stmt = $pdo->query("
    SELECT i.*, m.material_name, m.material_type, s.company_name as supplier_name
    FROM inventory i
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE i.status = 'KARANTIN'
    ORDER BY i.id DESC
");
$karantin_items = $stmt->fetchAll();

// QC testlarni olish
$tests_stmt = $pdo->query("
    SELECT qt.*, i.batch_number, m.material_name
    FROM qc_tests qt
    JOIN inventory i ON qt.inventory_id = i.id
    JOIN materials m ON i.material_id = m.id
    ORDER BY qt.id DESC LIMIT 20
");
$tests = $tests_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'sample') {
    $inventory_id = $_POST['inventory_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Namuna olish logi
        $stmt = $pdo->prepare("
            INSERT INTO qc_tests (inventory_id, test_type, sample_taken_date, sample_taken_by, status)
            VALUES (?, 'Mikrobiologik', CURDATE(), ?, 'Kutilmoqda')
        ");
        $stmt->execute([$inventory_id, $_SESSION['user_id']]);
        
        // Audit trail
        logAuditTrail($pdo, 'Namuna olish', 'qc_tests', $pdo->lastInsertId(), null, [
            'inventory_id' => $inventory_id,
            'sample_taken_by' => $_SESSION['user_id']
        ]);
        
        $pdo->commit();
        
        $message = "Namuna olish ro'yxatga olindi!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Xatolik: " . $e->getMessage();
        $messageType = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'approve') {
    $test_id = $_POST['test_id'];
    $result = $_POST['result'];
    $comments = trim($_POST['comments']);
    
    try {
        $pdo->beginTransaction();
        
        // Test natijasini yangilash
        $stmt = $pdo->prepare("
            UPDATE qc_tests 
            SET test_result = ?, test_report_file = ?, comments = ?, status = ?, approved_by = ?, approved_date = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$result, '', $comments, $result == 'Qabul qilish' ? 'Natija keltirilgan' : 'Rad etilgan', $_SESSION['user_id'], $test_id]);
        
        // Inventory statusini yangilash
        $test_stmt = $pdo->query("SELECT inventory_id FROM qc_tests WHERE id = $test_id");
        $test = $test_stmt->fetch();
        $inventory_id = $test['inventory_id'];
        
        $new_status = $result == 'Qabul qilish' ? 'RUXSAT ETILGAN' : 'RAD ETILGAN';
        
        $inv_stmt = $pdo->prepare("UPDATE inventory SET status = ? WHERE id = ?");
        $inv_stmt->execute([$new_status, $inventory_id]);
        
        // Audit trail
        logAuditTrail($pdo, 'QC natija kiritish', 'inventory', $inventory_id, null, [
            'new_status' => $new_status,
            'test_result' => $result
        ]);
        
        $pdo->commit();
        
        $message = "QC natija muvaffaqiyatli kiritildi!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Xatolik: " . $e->getMessage();
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | QC Laboratoriya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        .sidebar { width: 260px; height: 100vh; background: #1e293b; color: #fff; position: fixed; left: 0; top: 0; z-index: 1000; }
        .sidebar-header { padding: 20px; text-align: center; background: #0f172a; border-bottom: 1px solid #334155; }
        .nav-link { color: #94a3b8; padding: 12px 20px; display: flex; align-items: center; transition: 0.3s; border-radius: 8px; margin: 4px 15px; }
        .nav-link:hover, .nav-link.active { background: #334155; color: #fff; }
        .nav-link i { width: 25px; font-size: 18px; margin-right: 10px; }
        .main-content { margin-left: 260px; padding: 30px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .bg-karantin { background: #fef3c7; color: #92400e; }
        .bg-approved { background: #dcfce7; color: #166534; }
        .bg-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h5 class="m-0 font-weight-bold">GXP SYSTEM</h5>
        <small class="text-info"><?= $_SESSION['role'] ?></small>
    </div>
    <div class="mt-3">
        <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="inbound.php" class="nav-link"><i class="fas fa-truck-loading"></i> Kirim (Inbound)</a>
        <a href="inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Ombor Qoldig'i</a>
        <a href="outbound.php" class="nav-link"><i class="fas fa-dolly"></i> Chiqim (Outbound)</a>
        <a href="qc_panel.php" class="nav-link active"><i class="fas fa-microscope"></i> QC Laboratoriya</a>
        <?php if($_SESSION['role'] == 'Admin'): ?>
            <a href="admin_users.php" class="nav-link"><i class="fas fa-users-gear"></i> Xodimlar</a>
            <a href="audit_trail.php" class="nav-link"><i class="fas fa-file-shield"></i> Audit Trail</a>
        <?php endif; ?>
        <div class="px-3 mt-4">
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-microscope me-2 text-primary"></i>QC Laboratoriya</h4>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small"><i class="far fa-clock"></i> <?= date('d.m.Y H:i') ?></span>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle border" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1"></i> <?= $_SESSION['fullname'] ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Chiqish</a></li>
                </ul>
            </div>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="fas <?= $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Karantindagi mahsulotlar -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-user-shield me-2 text-warning"></i>Karantindagi mahsulotlar (<?= count($karantin_items) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mahsulot</th>
                                    <th>Seriya</th>
                                    <th>Miqdor</th>
                                    <th>Amal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($karantin_items as $item): ?>
                                <tr>
                                    <td class="text-start ps-4">
                                        <strong><?= $item['material_name'] ?></strong><br>
                                        <small class="text-muted"><?= $item['material_type'] ?></small>
                                    </td>
                                    <td><code><?= $item['batch_number'] ?></code></td>
                                    <td><?= number_format($item['quantity'], 2) ?> <?= $item['unit'] ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="inventory_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="action" value="sample">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Namuna olish">
                                                <i class="fas fa-syringe"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- QC Test Natijalari -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-file-medical me-2 text-primary"></i>QC Test Natijalari</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mahsulot</th>
                                    <th>Seriya</th>
                                    <th>Natija</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tests as $test): ?>
                                <?php
                                $result_class = ($test['test_result'] == 'Qabul qilish') ? 'bg-approved' : 'bg-rejected';
                                $status_class = ($test['status'] == 'Kutilmoqda') ? 'bg-karantin' : $result_class;
                                ?>
                                <tr>
                                    <td class="text-start ps-4">
                                        <strong><?= $test['material_name'] ?></strong>
                                    </td>
                                    <td><code><?= $test['batch_number'] ?></code></td>
                                    <td>
                                        <span class="status-badge <?= $result_class ?>">
                                            <?= $test['test_result'] == 'Qabul qilish' ? 'Ruxsat etilgan' : 'Rad etilgan' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $status_class ?>">
                                            <?= $test['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QC Natija Kiritish Modal -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-check-double me-2 text-success"></i>QC Natija Kiritish</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Test tanlang</label>
                        <select name="test_id" class="form-select" required>
                            <option value="">Tanlang...</option>
                            <?php foreach($tests as $test): ?>
                                <?php if($test['status'] == 'Natija keltirilgan'): ?>
                                <option value="<?= $test['id'] ?>">
                                    <?= $test['material_name'] ?> - <?= $test['batch_number'] ?> (<?= $test['test_result'] ?>)
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Natija</label>
                        <select name="result" class="form-select" required>
                            <option value="">Tanlang...</option>
                            <option value="Qabul qilish">Qabul qilish (Ruxsat etilgan)</option>
                            <option value="Rad etish">Rad etish</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Izoh</label>
                        <input type="text" name="comments" class="form-control" placeholder="Qaror sababi...">
                    </div>
                </div>
                <div class="mt-3">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success px-4"><i class="fas fa-check-circle me-2"></i>Natija saqlash</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>