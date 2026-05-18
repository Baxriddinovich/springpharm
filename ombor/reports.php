<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Faqat Admin, Ombor mudiri, QC rahbari va Auditor kirishi mumkin
if (!in_array($_SESSION['role'], ['Admin', 'Ombor mudiri', 'QC rahbari', 'Auditor'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Hisobot parametrlari
$report_type = $_GET['type'] ?? 'inventory';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Umumiy statistika
$total_batches = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$total_value = $pdo->query("SELECT SUM(current_quantity) FROM inventory WHERE status = 'RUXSAT ETILGAN'")->fetchColumn() ?: 0;

// Status bo'yicha
$karantin = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status = 'KARANTIN'")->fetchColumn();
$approved = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status = 'RUXSAT ETILGAN'")->fetchColumn();
$rejected = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status = 'RAD ETILGAN'")->fetchColumn();
$utilization = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status = 'UTILIZATSIYA'")->fetchColumn();

// FEFO alert (6 oy ichida muddati o'tadiganlar)
$fefo_alert = $pdo->query("
    SELECT COUNT(*) FROM inventory 
    WHERE exp_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) 
    AND current_quantity > 0 
    AND status = 'RUXSAT ETILGAN'
")->fetchColumn();

// Material turlari bo'yicha
$material_types = $pdo->query("
    SELECT m.material_type, COUNT(i.id) as count, SUM(i.current_quantity) as total_qty
    FROM materials m
    LEFT JOIN inventory i ON m.id = i.material_id AND i.status = 'RUXSAT ETILGAN'
    GROUP BY m.material_type
")->fetchAll();

// Ombor qoldig'i hisoboti
$inventory_stmt = $pdo->prepare("
    SELECT i.*, m.material_name, m.material_type, s.company_name as supplier_name
    FROM inventory i
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE i.created_at BETWEEN ? AND ?
    ORDER BY i.id DESC
");
$inventory_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$inventory_report = $inventory_stmt->fetchAll();

// Chiqim hisoboti
$outbound_stmt = $pdo->prepare("
    SELECT o.*, i.batch_number, m.material_name, u.fullname as issued_by_name
    FROM outbound o
    JOIN inventory i ON o.inventory_id = i.id
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN users u ON o.issued_by = u.id
    WHERE o.issue_date BETWEEN ? AND ?
    ORDER BY o.id DESC
");
$outbound_stmt->execute([$start_date, $end_date]);
$outbound_report = $outbound_stmt->fetchAll();

// Utilizatsiya hisoboti
$utilization_stmt = $pdo->prepare("
    SELECT u.*, i.batch_number, m.material_name, u2.fullname as decision_by_name
    FROM utilization u
    JOIN inventory i ON u.inventory_id = i.id
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN users u2 ON u.decision_by = u2.id
    WHERE u.utilization_date BETWEEN ? AND ?
    ORDER BY u.id DESC
");
$utilization_stmt->execute([$start_date, $end_date]);
$utilization_report = $utilization_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Ombor Hisobotlari</title>
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
        .bg-utilization { background: #e5e7eb; color: #374151; }
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
        <a href="qc_panel.php" class="nav-link"><i class="fas fa-microscope"></i> QC Laboratoriya</a>
        <a href="reports.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Hisobotlar</a>
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
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-chart-bar me-2 text-primary"></i>Ombor Hisobotlari</h4>
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

    <!-- Statistika Kartochkalari -->
    <div class="row g-4 mb-5">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center">
                    <div class="text-primary fs-1"><i class="fas fa-layer-group"></i></div>
                    <h3 class="mt-2 font-weight-bold"><?= $total_batches ?></h3>
                    <p class="text-muted small">Jami Partiyalar</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center">
                    <div class="text-success fs-1"><i class="fas fa-check-circle"></i></div>
                    <h3 class="mt-2 font-weight-bold"><?= $approved ?></h3>
                    <p class="text-muted small">Ruxsat Etilgan</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center">
                    <div class="text-warning fs-1"><i class="fas fa-user-shield"></i></div>
                    <h3 class="mt-2 font-weight-bold"><?= $karantin ?></h3>
                    <p class="text-muted small">Karantinda</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center">
                    <div class="text-danger fs-1"><i class="fas fa-trash"></i></div>
                    <h3 class="mt-2 font-weight-bold"><?= $utilization ?></h3>
                    <p class="text-muted small">Utilizatsiya</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center">
                    <div class="text-danger fs-1"><i class="fas fa-exclamation-triangle"></i></div>
                    <h3 class="mt-2 font-weight-bold text-danger"><?= $fefo_alert ?></h3>
                    <p class="text-muted small">FEFO Alert (6 oy)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center">
                    <div class="text-info fs-1"><i class="fas fa-boxes"></i></div>
                    <h3 class="mt-2 font-weight-bold"><?= number_format($total_value, 2) ?></h3>
                    <p class="text-muted small">Jami Qoldiq</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Hisobot Filter -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Hisobot turi</label>
                    <select name="type" class="form-select">
                        <option value="inventory" <?= $report_type == 'inventory' ? 'selected' : '' ?>>Ombor Qoldig'i</option>
                        <option value="outbound" <?= $report_type == 'outbound' ? 'selected' : '' ?>>Chiqim Hisoboti</option>
                        <option value="utilization" <?= $report_type == 'utilization' ? 'selected' : '' ?>>Utilizatsiya Hisoboti</option>
                        <option value="material_types" <?= $report_type == 'material_types' ? 'selected' : '' ?>>Material Turlari</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Boshlanish sanasi</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tugash sanasi</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Hisobot olish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Material Turlari Hisoboti -->
    <?php if($report_type == 'material_types'): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Material Turlari Bo'yicha</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Material Turi</th>
                            <th>Partiyalar Soni</th>
                            <th>Jami Miqdor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($material_types as $mt): ?>
                        <tr>
                            <td class="text-start ps-4">
                                <strong><?= $mt['material_type'] ?></strong>
                            </td>
                            <td><?= $mt['count'] ?></td>
                            <td><?= number_format($mt['total_qty'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ombor Qoldig'i Hisoboti -->
    <?php if($report_type == 'inventory'): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Ombor Qoldig'i Hisoboti (<?= count($inventory_report) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mahsulot</th>
                            <th>Seriya No</th>
                            <th>Miqdor</th>
                            <th>Yaroqlilik muddati</th>
                            <th>Status</th>
                            <th>Kirgan sana</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($inventory_report as $item): ?>
                        <?php
                        $st_class = match($item['status']) {
                            'KARANTIN' => 'bg-karantin',
                            'RUXSAT ETILGAN' => 'bg-approved',
                            'RAD ETILGAN' => 'bg-rejected',
                            'UTILIZATSIYA' => 'bg-utilization',
                            default => 'bg-karantin'
                        };
                        ?>
                        <tr>
                            <td class="text-start ps-4">
                                <strong><?= $item['material_name'] ?></strong><br>
                                <small class="text-muted"><?= $item['material_type'] ?></small>
                            </td>
                            <td><code><?= $item['batch_number'] ?></code></td>
                            <td><?= number_format($item['current_quantity'], 2) ?> <?= $item['unit'] ?></td>
                            <td><?= $item['exp_date'] ?></td>
                            <td><span class="status-badge <?= $st_class ?>"><?= $item['status'] ?></span></td>
                            <td><?= $item['created_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chiqim Hisoboti -->
    <?php if($report_type == 'outbound'): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Chiqim Hisoboti (<?= count($outbound_report) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mahsulot</th>
                            <th>Seriya</th>
                            <th>Miqdor</th>
                            <th>Chiqim sana</th>
                            <th>Qabul qiluvchi</th>
                            <th>Amal qilgan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($outbound_report as $out): ?>
                        <tr>
                            <td class="text-start ps-4">
                                <strong><?= $out['material_name'] ?></strong>
                            </td>
                            <td><code><?= $out['batch_number'] ?></code></td>
                            <td><span class="text-success fw-bold"><?= number_format($out['quantity'], 2) ?></span></td>
                            <td><?= $out['issue_date'] ?></td>
                            <td><?= $out['recipient_name'] ?></td>
                            <td><?= $out['issued_by_name'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Utilizatsiya Hisoboti -->
    <?php if($report_type == 'utilization'): ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-danger"></i>Utilizatsiya Hisoboti (<?= count($utilization_report) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mahsulot</th>
                            <th>Seriya</th>
                            <th>Sabab</th>
                            <th>Usul</th>
                            <th>Sana</th>
                            <th>Qaror qilgan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($utilization_report as $util): ?>
                        <tr>
                            <td class="text-start ps-4">
                                <strong><?= $util['material_name'] ?></strong>
                            </td>
                            <td><code><?= $util['batch_number'] ?></code></td>
                            <td><?= substr($util['reason'], 0, 50) ?>...</td>
                            <td><?= $util['utilization_method'] ?></td>
                            <td><?= $util['utilization_date'] ?></td>
                            <td><?= $util['decision_by_name'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>