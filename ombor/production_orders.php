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

// Foydalanuvchi roli
$user_role = $_SESSION['role'];

// Buyurtmalarni olish
if ($user_role == 'Ishlab chiqarish') {
    $stmt = $pdo->query("
        SELECT po.*, u.fullname as created_by_name
        FROM production_orders po
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.created_by = " . $_SESSION['user_id'] . "
        ORDER BY po.id DESC
    ");
} else {
    $stmt = $pdo->query("
        SELECT po.*, u.fullname as created_by_name
        FROM production_orders po
        LEFT JOIN users u ON po.created_by = u.id
        ORDER BY po.id DESC
    ");
}
$orders = $stmt->fetchAll();

// Buyurtma qo'shish
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $order_no = trim($_POST['order_no']);
    $product_name = trim($_POST['product_name']);
    $quantity = $_POST['quantity'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO production_orders (order_no, product_name, quantity, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$order_no, $product_name, $quantity, $_SESSION['user_id']]);
        
        logAuditTrail($pdo, 'Yangi ishlab chiqarish buyurtmasi', 'production_orders', $pdo->lastInsertId(), null, [
            'order_no' => $order_no,
            'product_name' => $product_name,
            'quantity' => $quantity
        ]);
        
        $message = "Buyurtma muvaffaqiyatli yaratildi!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            $message = "Bu buyurtma raqami allaqachon mavjud!";
        } else {
            $message = "Xatolik: " . $e->getMessage();
        }
        $messageType = 'danger';
    }
}

// Buyurtma materiallari
$order_materials_stmt = $pdo->query("
    SELECT pom.*, m.material_name, po.order_no
    FROM production_order_materials pom
    JOIN materials m ON pom.material_id = m.id
    JOIN production_orders po ON pom.production_order_id = po.id
    ORDER BY pom.id DESC LIMIT 20
");
$order_materials = $order_materials_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Ishlab chiqarish Buyurtmalari</title>
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
        .bg-kutilmoqda { background: #fef3c7; color: #92400e; }
        .bg-bajarilmoqda { background: #dbeafe; color: #1e40af; }
        .bg-tayyor { background: #dcfce7; color: #166534; }
        .bg-bekor { background: #fee2e2; color: #991b1b; }
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
        <?php if($user_role == 'Admin'): ?>
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
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-file-invoice me-2 text-primary"></i>Ishlab chiqarish Buyurtmalari</h4>
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
        <!-- Yangi buyurtma qo'shish -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>Yangi buyurtma</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Buyurtma raqami <span class="text-danger">*</span></label>
                            <input type="text" name="order_no" class="form-control" placeholder="MAS: PO-2024-001" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mahsulot nomi <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control" placeholder="Mahsulot nomi" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Miqdor <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> Saqlash</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Buyurtmalar ro'yxati -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Buyurtmalar ro'yxati</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Buyurtma raqami</th>
                                    <th>Mahsulot</th>
                                    <th>Miqdor</th>
                                    <th>Status</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Amal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): ?>
                                <?php
                                $status_class = match($order['status']) {
                                    'Kutilmoqda' => 'bg-kutilmoqda',
                                    'Bajarilmoqda' => 'bg-bajarilmoqda',
                                    'Tayyor' => 'bg-tayyor',
                                    'Bekor qilingan' => 'bg-bekor',
                                    default => 'bg-kutilmoqda'
                                };
                                ?>
                                <tr>
                                    <td><code><?= $order['order_no'] ?></code></td>
                                    <td class="text-start ps-4">
                                        <strong><?= $order['product_name'] ?></strong>
                                    </td>
                                    <td><?= number_format($order['quantity'], 2) ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= $order['status'] ?></span></td>
                                    <td><?= $order['created_at'] ?></td>
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

    <!-- Buyurtma materiallari -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Buyurtma materiallari</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Buyurtma</th>
                            <th>Mahsulot</th>
                            <th>Talab qilingan</th>
                            <th>Ajratilgan</th>
                            <th>Chiqarilgan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($order_materials as $om): ?>
                        <?php
                        $status_class = match($om['status']) {
                            'Kutilmoqda' => 'bg-kutilmoqda',
                            'Ajratilgan' => 'bg-bajarilmoqda',
                            'Chiqarilgan' => 'bg-tayyor',
                            default => 'bg-kutilmoqda'
                        };
                        ?>
                        <tr>
                            <td><code><?= $om['order_no'] ?></code></td>
                            <td class="text-start ps-4">
                                <strong><?= $om['material_name'] ?></strong>
                            </td>
                            <td><?= number_format($om['required_quantity'], 2) ?></td>
                            <td><?= number_format($om['allocated_quantity'], 2) ?></td>
                            <td><?= number_format($om['issued_quantity'], 2) ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $om['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>