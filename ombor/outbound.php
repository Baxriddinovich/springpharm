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

// Inventory ID ni olish
$inventory_id = $_GET['inventory_id'] ?? null;

// Ruxsat etilgan mahsulotlarni olish
$stmt = $pdo->query("
    SELECT i.*, m.material_name, m.material_type, s.company_name as supplier_name
    FROM inventory i
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE i.status = 'RUXSAT ETILGAN'
    ORDER BY i.exp_date ASC, i.id DESC
");
$inventory_items = $stmt->fetchAll();

// Chiqimlar ro'yxati
$outbound_stmt = $pdo->query("
    SELECT o.*, i.batch_number, m.material_name, u.fullname as issued_by_name
    FROM outbound o
    JOIN inventory i ON o.inventory_id = i.id
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN users u ON o.issued_by = u.id
    ORDER BY o.id DESC LIMIT 20
");
$outbounds = $outbound_stmt->fetchAll();

if ($inventory_id) {
    $item_stmt = $pdo->query("SELECT * FROM inventory WHERE id = $inventory_id AND status = 'RUXSAT ETILGAN'");
    $selected_item = $item_stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $inventory_id = $_POST['inventory_id'];
    $quantity = $_POST['quantity'];
    $production_order_no = trim($_POST['production_order_no']);
    $issue_date = $_POST['issue_date'];
    $recipient_name = trim($_POST['recipient_name']);
    $notes = trim($_POST['notes']);
    
    try {
        $pdo->beginTransaction();
        
        // Mahsulotni olish
        $item_stmt = $pdo->query("SELECT * FROM inventory WHERE id = $inventory_id");
        $item = $item_stmt->fetch();
        
        // Qoldiqni tekshirish
        if ($quantity > $item['current_quantity']) {
            $message = "Kechirasiz, mavjud qoldiqdan ko'p miqdor chiqim qilolmaysiz!";
            $messageType = 'danger';
        } elseif ($quantity <= 0) {
            $message = "Miqdor musbat bo'lishi kerak!";
            $messageType = 'danger';
        } else {
            // Chiqim yaratish
            $stmt = $pdo->prepare("
                INSERT INTO outbound (inventory_id, quantity, issued_to, issued_by, production_order_no, issue_date, recipient_name, notes, status)
                VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 'Tasdiqlangan')
            ");
            $stmt->execute([$inventory_id, $quantity, $_SESSION['user_id'], $issue_date, $recipient_name, $notes]);
            
            $outbound_id = $pdo->lastInsertId();
            
            // Qoldiqni yangilash
            $new_quantity = $item['current_quantity'] - $quantity;
            $inv_stmt = $pdo->prepare("UPDATE inventory SET current_quantity = ? WHERE id = ?");
            $inv_stmt->execute([$new_quantity, $inventory_id]);
            
            // Audit trail
            logAuditTrail($pdo, 'Chiqim qilish', 'outbound', $outbound_id, null, [
                'inventory_id' => $inventory_id,
                'quantity' => $quantity,
                'new_inventory_quantity' => $new_quantity
            ]);
            
            $pdo->commit();
            
            $message = "Chiqim muvaffaqiyatli amalga oshirildi!";
            $messageType = 'success';
            
            // Qayta chiqim formasi
            $selected_item = null;
        }
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
    <title>GXP PHARM | Chiqim (Outbound)</title>
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
        .bg-approved { background: #dcfce7; color: #166534; }
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
        <a href="outbound.php" class="nav-link active"><i class="fas fa-dolly"></i> Chiqim (Outbound)</a>
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
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-dolly me-2 text-primary"></i>Chiqim (Outbound)</h4>
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
        <!-- Chiqim Form -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Chiqim formasi</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Mahsulot tanlang (FEFO bo'yicha)</label>
                            <select name="inventory_id" class="form-select" required>
                                <option value="">Tanlang...</option>
                                <?php foreach($inventory_items as $item): ?>
                                    <option value="<?= $item['id'] ?>" <?= $selected_item && $selected_item['id'] == $item['id'] ? 'selected' : '' ?>>
                                        <?= $item['material_name'] ?> - <?= $item['batch_number'] ?> 
                                        (Qoldiq: <?= number_format($item['current_quantity'], 2) ?> <?= $item['unit'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if($selected_item): ?>
                        <div class="alert alert-info">
                            <h6 class="mb-2"><i class="fas fa-info-circle me-1"></i>Mahsulot ma'lumotlari:</h6>
                            <div class="small">
                                <div><strong>Mahsulot:</strong> <?= $selected_item['material_name'] ?></div>
                                <div><strong>Seriya No:</strong> <?= $selected_item['batch_number'] ?></div>
                                <div><strong>Yaroqlilik muddati:</strong> <?= $selected_item['exp_date'] ?></div>
                                <div><strong>Mavjud qoldiq:</strong> <span class="text-success fw-bold"><?= number_format($selected_item['current_quantity'], 2) ?> <?= $selected_item['unit'] ?></span></div>
                                <div><strong>Joylashuv:</strong> <?= $selected_item['storage_location'] ?></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Chiqim miqdori <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" step="0.01" placeholder="0.00" required>
                            <small class="text-muted">Mavjud qoldiqdan ko'p bo'lmasin</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ishlab chiqarish buyurtma raqami</label>
                            <input type="text" name="production_order_no" class="form-control" placeholder="MAS: PO-2024-001">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Chiqim sana <span class="text-danger">*</span></label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Qabul qiluvchi F.I.Sh <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_name" class="form-control" placeholder="Qabul qiluvchi F.I.Sh" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Izoh</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Qo'shimcha ma'lumotlar..."></textarea>
                        </div>
                        
                        <div class="mt-4">
                            <input type="hidden" name="action" value="submit">
                            <button type="submit" class="btn btn-success w-100 py-2"><i class="fas fa-check-circle me-2"></i>Chiqim qilish</button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Chiqimlar ro'yxati -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-history me-2 text-primary"></i>So'nggi chiqimlar</h6>
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
                                    <th>Amal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($outbounds as $out): ?>
                                <tr>
                                    <td class="text-start ps-4">
                                        <strong><?= $out['material_name'] ?></strong>
                                    </td>
                                    <td><code><?= $out['batch_number'] ?></code></td>
                                    <td><span class="text-success fw-bold"><?= number_format($out['quantity'], 2) ?></span></td>
                                    <td><?= $out['issue_date'] ?></td>
                                    <td><?= $out['recipient_name'] ?></td>
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