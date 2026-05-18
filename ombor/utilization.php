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

// Utilizatsiya qilingan mahsulotlarni olish
$stmt = $pdo->query("
    SELECT u.*, i.batch_number, m.material_name, u2.fullname as decision_by_name, u3.fullname as approved_by_name
    FROM utilization u
    JOIN inventory i ON u.inventory_id = i.id
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN users u2 ON u.decision_by = u2.id
    LEFT JOIN users u3 ON u.approved_by = u3.id
    ORDER BY u.id DESC
");
$utilizations = $stmt->fetchAll();

// Utilizatsiya qilish
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $inventory_id = $_POST['inventory_id'];
    $reason = trim($_POST['reason']);
    $utilization_method = $_POST['utilization_method'];
    $utilization_date = $_POST['utilization_date'];
    
    try {
        $pdo->beginTransaction();
        
        // Utilizatsiya yaratish
        $stmt = $pdo->prepare("
            INSERT INTO utilization (inventory_id, reason, decision_by, decision_date, utilization_date, utilization_method, documents_attached)
            VALUES (?, ?, ?, NOW(), ?, ?, '')
        ");
        $stmt->execute([$inventory_id, $reason, $_SESSION['user_id'], $utilization_date, $utilization_method]);
        
        $utilization_id = $pdo->lastInsertId();
        
        // Inventory statusini UTILIZATSIYA ga o'tkazish
        $inv_stmt = $pdo->prepare("UPDATE inventory SET status = 'UTILIZATSIYA' WHERE id = ?");
        $inv_stmt->execute([$inventory_id]);
        
        // Audit trail
        logAuditTrail($pdo, 'Utilizatsiya qilish', 'utilization', $utilization_id, null, [
            'inventory_id' => $inventory_id,
            'reason' => $reason,
            'utilization_method' => $utilization_method
        ]);
        
        $pdo->commit();
        
        $message = "Utilizatsiya muvaffaqiyatli ro'yxatga olindi!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Xatolik: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Rad etilgan mahsulotlarni olish (utilizatsiya qilish uchun)
$rejected_stmt = $pdo->query("
    SELECT i.*, m.material_name
    FROM inventory i
    JOIN materials m ON i.material_id = m.id
    WHERE i.status = 'RAD ETILGAN'
    ORDER BY i.id DESC
");
$rejected_items = $rejected_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Utilizatsiya</title>
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
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-trash me-2 text-danger"></i>Utilizatsiya (Rad etilgan mahsulotlar)</h4>
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
        <!-- Utilizatsiya Form -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-file-medical me-2 text-danger"></i>Utilizatsiya qilish</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Rad etilgan mahsulot tanlang</label>
                            <select name="inventory_id" class="form-select" required>
                                <option value="">Tanlang...</option>
                                <?php foreach($rejected_items as $item): ?>
                                    <option value="<?= $item['id'] ?>">
                                        <?= $item['material_name'] ?> - <?= $item['batch_number'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Utilizatsiya sababi <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Rad etilgan sababini yozing..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Utilizatsiya usuli <span class="text-danger">*</span></label>
                            <select name="utilization_method" class="form-select" required>
                                <option value="">Tanlang...</option>
                                <option value="Yo'q qilish">Yo'q qilish</option>
                                <option value="Qayta ishlash">Qayta ishlash</option>
                                <option value="Boshqa">Boshqa</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Utilizatsiya sana <span class="text-danger">*</span></label>
                            <input type="date" name="utilization_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-danger w-100 py-2"><i class="fas fa-trash me-2"></i>Utilizatsiya qilish</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Utilizatsiya ro'yxati -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-danger"></i>Utilizatsiya ro'yxati</h6>
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
                                    <th>Amal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($utilizations as $util): ?>
                                <tr>
                                    <td class="text-start ps-4">
                                        <strong><?= $util['material_name'] ?></strong>
                                    </td>
                                    <td><code><?= $util['batch_number'] ?></code></td>
                                    <td><?= substr($util['reason'], 0, 50) ?>...</td>
                                    <td><?= $util['utilization_method'] ?></td>
                                    <td><?= $util['utilization_date'] ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-info" title="Tafsilotlar">
                                            <i class="fas fa-eye"></i>
                                        </a>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>