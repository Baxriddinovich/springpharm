<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$messageType = '';

// Qidiruv
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Query yaratish
$query = "
    SELECT i.*, m.material_name, m.material_type, s.company_name as supplier_name
    FROM inventory i
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (i.batch_number LIKE ? OR m.material_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY i.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inventory_items = $stmt->fetchAll();

// Statuslar
$statuses = ['KARANTIN', 'RUXSAT ETILGAN', 'RAD ETILGAN', 'UTILIZATSIYA'];
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Ombor Qoldig'i</title>
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
        <a href="inventory.php" class="nav-link active"><i class="fas fa-boxes-stacked"></i> Ombor Qoldig'i</a>
        <a href="outbound.php" class="nav-link"><i class="fas fa-dolly"></i> Chiqim (Outbound)</a>
        <a href="qc_panel.php" class="nav-link"><i class="fas fa-microscope"></i> QC Laboratoriya</a>
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
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-boxes me-2 text-primary"></i>Ombor Qoldig'i</h4>
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

    <!-- Filter va Qidiruv -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Qidiruv</label>
                    <input type="text" name="search" class="form-control" value="<?= $search ?>" placeholder="Batch No yoki Mahsulot nomi...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Barchasi</option>
                        <?php foreach($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $status_filter == $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Qidirish</button>
                </div>
                <div class="col-md-2">
                    <a href="inventory.php" class="btn btn-secondary w-100"><i class="fas fa-redo me-2"></i>Tizimlash</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Partiyalar ro'yxati (<?= count($inventory_items) ?>)</h6>
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
                            <th>Qoldiq</th>
                            <th>Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($inventory_items as $item): ?>
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
                            <td><?= number_format($item['quantity'], 2) ?> <?= $item['unit'] ?></td>
                            <td><?= $item['exp_date'] ?></td>
                            <td><span class="status-badge <?= $st_class ?>"><?= $item['status'] ?></span></td>
                            <td><?= number_format($item['current_quantity'], 2) ?> <?= $item['unit'] ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <?php if($item['status'] == 'RUXSAT ETILGAN'): ?>
                                        <a href="outbound.php?inventory_id=<?= $item['id'] ?>" class="btn btn-sm btn-success" title="Chiqim">
                                            <i class="fas fa-dolly"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="#" class="btn btn-sm btn-info" title="QR Code">
                                        <i class="fas fa-qrcode"></i>
                                    </a>
                                    <a href="#" class="btn btn-sm btn-secondary" title="Tafsilotlar" data-bs-toggle="modal" data-bs-target="#detailModal">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-center py-3">
            <small class="text-muted">Jami: <?= count($inventory_items) ?> partiya</small>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mahsulot Tafsilotlari</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded via JS -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Detail modalni ochish
document.addEventListener('DOMContentLoaded', function() {
    const detailBtns = document.querySelectorAll('[title="Tafsilotlar"]');
    detailBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            // Modalni ochish uchun JS kodini qo'shish kerak
            alert('Tafsilotlar modalini ochish uchun JavaScript kodini qo\'shing');
        });
    });
});
</script>
</body>
</html>