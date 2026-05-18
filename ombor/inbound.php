<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Faqat Omborchi va Admin kirishi mumkin
if (!in_array($_SESSION['role'], ['Admin', 'Ombor mudiri'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Materiallarni olish
$materials = $pdo->query("SELECT * FROM materials WHERE status = 1 ORDER BY material_name")->fetchAll();

// Ta'minotchilarni olish
$suppliers = $pdo->query("SELECT * FROM suppliers WHERE status = 1 ORDER BY company_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $material_id = $_POST['material_id'];
    $batch_number = trim($_POST['batch_number']);
    $supplier_id = $_POST['supplier_id'] ?? null;
    $manufacturer = trim($_POST['manufacturer']);
    $received_date = $_POST['received_date'];
    $production_date = $_POST['production_date'];
    $exp_date = $_POST['exp_date'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    $storage_location = trim($_POST['storage_location']);
    $storage_temp = trim($_POST['storage_temp']);
    $notes = trim($_POST['notes']);
    
    // Validatsiya
    if (empty($batch_number)) {
        $message = "Seriya raqami (Batch No) to'ldirilishi shart!";
        $messageType = 'danger';
    } elseif (empty($exp_date)) {
        $message = "Yaroqlilik muddati to'ldirilishi shart!";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // QR code generatsiya
            $qr_data = "GXP|MAT{$material_id}|BATCH{$batch_number}|QTY{$quantity}|EXP{$exp_date}";
            $qr_code = "uploads/qrcodes/inbound_" . time() . "_" . uniqid() . ".png";
            
            // QR code yaratish (simple implementation)
            $qr_path = __DIR__ . '/' . $qr_code;
            if (!is_dir(dirname($qr_path))) {
                mkdir(dirname($qr_path), 0777, true);
            }
            
            // QR code generatsiya (qrcode library ishlatilishi mumkin)
            // Hozircha faqat path saqlaymiz
            $qr_code = "qrcodes/inbound_" . time() . "_" . uniqid() . ".png";
            
            $stmt = $pdo->prepare("
                INSERT INTO inventory 
                (material_id, batch_number, supplier_id, manufacturer, received_date, production_date, exp_date, 
                 quantity, current_quantity, unit, storage_location, storage_temp, status, received_by, qr_code, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'KARANTIN', ?, ?, ?)
            ");
            
            $stmt->execute([
                $material_id, $batch_number, $supplier_id, $manufacturer, $received_date, $production_date, $exp_date,
                $quantity, $quantity, $unit, $storage_location, $storage_temp, $_SESSION['user_id'], $qr_code, $notes
            ]);
            
            $inventory_id = $pdo->lastInsertId();
            
            // Audit trail
            logAuditTrail($pdo, 'Kirim qilish', 'inventory', $inventory_id, null, [
                'batch_number' => $batch_number,
                'quantity' => $quantity,
                'exp_date' => $exp_date
            ]);
            
            $pdo->commit();
            
            $message = "Mahsulot muvaffaqiyatli kiritildi! Partiya: {$batch_number}";
            $messageType = 'success';
            
            // Qayta kirim formasi
            $material_id = '';
            $batch_number = '';
            $quantity = '';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Xatolik: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Kirim (Inbound)</title>
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
        .form-section { background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .bg-karantin { background: #fef3c7; color: #92400e; }
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
        <a href="inbound.php" class="nav-link active"><i class="fas fa-truck-loading"></i> Kirim (Inbound)</a>
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
        <h4 class="m-0 font-weight-bold text-secondary">Kirim (Inbound)</h4>
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

    <div class="form-section">
        <h5 class="mb-4"><i class="fas fa-plus-circle text-primary me-2"></i> Yangi kirim qilish</h5>
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Mahsulot nomi <span class="text-danger">*</span></label>
                    <select name="material_id" class="form-select" required>
                        <option value="">Tanlang...</option>
                        <?php foreach($materials as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $material_id == $m['id'] ? 'selected' : '' ?>>
                                <?= $m['material_name'] ?> (<?= $m['material_type'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Seriya raqami (Batch No) <span class="text-danger">*</span></label>
                    <input type="text" name="batch_number" class="form-control" value="<?= $batch_number ?? '' ?>" placeholder="MAS: BATCH-2024-001" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Ta'minotchi</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">Tanlang...</option>
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $supplier_id == $s['id'] ? 'selected' : '' ?>>
                                <?= $s['company_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ishlab chiqaruvchi</label>
                    <input type="text" name="manufacturer" class="form-control" value="<?= $manufacturer ?? '' ?>" placeholder="Ishlab chiqaruvchi nomi">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Kirim sanasi <span class="text-danger">*</span></label>
                    <input type="date" name="received_date" class="form-control" value="<?= $received_date ?? date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ishlab chiqarilgan sana <span class="text-danger">*</span></label>
                    <input type="date" name="production_date" class="form-control" value="<?= $production_date ?? '' ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Yaroqlilik muddati <span class="text-danger">*</span></label>
                    <input type="date" name="exp_date" class="form-control" value="<?= $exp_date ?? '' ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Miqdor <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" class="form-control" value="<?= $quantity ?? '' ?>" step="0.01" placeholder="0.00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">O'lchov birligi <span class="text-danger">*</span></label>
                    <select name="unit" class="form-select" required>
                        <option value="kg" <?= $unit == 'kg' ? 'selected' : '' ?>>kg</option>
                        <option value="g" <?= $unit == 'g' ? 'selected' : '' ?>>g</option>
                        <option value="dona" <?= $unit == 'dona' ? 'selected' : '' ?>>dona</option>
                        <option value="litr" <?= $unit == 'litr' ? 'selected' : '' ?>>litr</option>
                        <option value="ml" <?= $unit == 'ml' ? 'selected' : '' ?>>ml</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ombordagi joylashuv</label>
                    <input type="text" name="storage_location" class="form-control" value="<?= $storage_location ?? '' ?>" placeholder="Stellaj A-1, Zona 2">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Saqlash harorati</label>
                    <input type="text" name="storage_temp" class="form-control" value="<?= $storage_temp ?? '' ?>" placeholder="MAS: 15-25°C">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Izoh</label>
                    <textarea name="notes" class="form-control" rows="1" placeholder="Qo'shimcha ma'lumotlar..."><?= $notes ?? '' ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i> Saqlash</button>
                <a href="dashboard.php" class="btn btn-secondary px-4"><i class="fas fa-times me-2"></i> Bekor qilish</a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>So'nggi kirimlar</h6>
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
                            <th>Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("
                            SELECT i.*, m.material_name, m.material_type, s.company_name as supplier_name
                            FROM inventory i
                            JOIN materials m ON i.material_id = m.id
                            LEFT JOIN suppliers s ON i.supplier_id = s.id
                            ORDER BY i.id DESC LIMIT 10
                        ");
                        while($row = $stmt->fetch()):
                            $st_class = ($row['status'] == 'KARANTIN') ? 'bg-karantin' : '';
                        ?>
                        <tr>
                            <td class="text-start ps-4">
                                <strong><?= $row['material_name'] ?></strong><br>
                                <small class="text-muted"><?= $row['material_type'] ?></small>
                            </td>
                            <td><code><?= $row['batch_number'] ?></code></td>
                            <td><?= number_format($row['quantity'], 2) ?> <?= $row['unit'] ?></td>
                            <td><?= $row['exp_date'] ?></td>
                            <td><span class="status-badge <?= $st_class ?>"><?= $row['status'] ?></span></td>
                            <td><?= $row['created_at'] ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-info" title="QR Code"><i class="fas fa-qrcode"></i></a>
                                <a href="#" class="btn btn-sm btn-warning" title="Tahrirlash"><i class="fas fa-edit"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>