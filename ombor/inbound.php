<?php
session_start();
require 'db.php'; 

// --- QR KUTUBXONASINI YUKLASH (Xavfsiz usul) ---
// Agar phpqrcode papkasi mavjud bo'lsa, uni yuklaymiz
if (file_exists('libs/phpqrcode.php')) {
    require_once 'libs/phpqrcode.php';
}
// -------------------------------------------------

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
 $warning = ''; // QR yoki papka xatoliklari uchun

// Dropdown uchun ma'lumotlarni olish (datalistda ko'rsatish uchun)
 $materials = $pdo->query("SELECT material_name FROM materials WHERE status = 1 ORDER BY material_name")->fetchAll();
 $suppliers = $pdo->query("SELECT company_name FROM suppliers WHERE status = 1 ORDER BY company_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Inputdan matnni olish
    $material_name = trim($_POST['material_name'] ?? ''); // Xatolik oldini olish uchun ?? '' qo'shildi
    $batch_number = trim($_POST['batch_number'] ?? '');
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $received_date = $_POST['received_date'] ?? date('Y-m-d');
    $production_date = $_POST['production_date'] ?? '';
    $exp_date = $_POST['exp_date'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $unit = $_POST['unit'] ?? 'kg';
    $storage_location = trim($_POST['storage_location'] ?? '');
    $storage_temp = trim($_POST['storage_temp'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validatsiya
    if (empty($material_name)) {
        $message = "Mahsulot nomi kiritilishi shart!";
        $messageType = 'danger';
    } elseif (empty($batch_number)) {
        $message = "Seriya raqami (Batch No) to'ldirilishi shart!";
        $messageType = 'danger';
    } elseif (empty($exp_date)) {
        $message = "Yaroqlilik muddati to'ldirilishi shart!";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // --- 1. Materialni qidirish yoki yaratish ---
            $stmt = $pdo->prepare("SELECT id FROM materials WHERE material_name = ?");
            $stmt->execute([$material_name]);
            $mat = $stmt->fetch();
            
            if ($mat) {
                $material_id = $mat['id'];
            } else {
                // Yangi material yaratish
                $stmtNew = $pdo->prepare("INSERT INTO materials (material_name, material_type, status) VALUES (?, 'Boshqa', 1)");
                $stmtNew->execute([$material_name]);
                $material_id = $pdo->lastInsertId();
            }

            // --- 2. Ta'minotchini qidirish yoki yaratish ---
            $supplier_id = null;
            if (!empty($supplier_name)) {
                $stmtSup = $pdo->prepare("SELECT id FROM suppliers WHERE company_name = ?");
                $stmtSup->execute([$supplier_name]);
                $sup = $stmtSup->fetch();
                
                if ($sup) {
                    $supplier_id = $sup['id'];
                } else {
                    // Yangi supplier yaratish
                    $stmtNewSup = $pdo->prepare("INSERT INTO suppliers (company_name, status) VALUES (?, 1)");
                    $stmtNewSup->execute([$supplier_name]);
                    $supplier_id = $pdo->lastInsertId();
                }
            }

            // --- 3. QR kod generatsiyasi (Xatoliklarga chidamli) ---
            $filename = null;
            
            // Papka mavjudligini va yozish huquqini tekshiramiz
            $dir = 'uploads/qrcodes/';
            
            if (!is_dir($dir)) {
                // Xatolikni bostirish uchun @ ishlatildi
                if (!@mkdir($dir, 0777, true)) {
                    $warning = "QR kod papkasi yaratib bo'lmadi (Server ruxsatlari). Ma'lumotlar saqlandi, lekin QR yo'q.";
                }
            }

            // Agar klass mavjud bo'lsa va papka yozishga ochiq bo'lsa
            if (class_exists('QRcode') && is_writable($dir)) {
                try {
                    $qr_text = "GXP|MAT{$material_id}|BATCH{$batch_number}|EXP{$exp_date}|QTY{$quantity}";
                    $filename = $dir . "inbound_" . time() . "_" . uniqid() . ".png";
                    QRcode::png($qr_text, $filename, 'H', 4, 2); 
                } catch (Exception $qr_e) {
                    $warning = "QR kod yaratishda xatolik: " . $qr_e->getMessage();
                }
            } elseif (!class_exists('QRcode')) {
                $warning = "QR kutubxonasi topilmadi. Ma'lumotlar saqlandi.";
            } elseif (!is_writable($dir)) {
                $warning = "QR kod papkasiga yozish huquqi yo'q. Ma'lumotlar saqlandi.";
            }
            
            // --- 4. Ma'lumotlarni inventory ga kiritish ---
            $stmt = $pdo->prepare("
                INSERT INTO inventory 
                (material_id, batch_number, supplier_id, manufacturer, received_date, production_date, exp_date, 
                 quantity, current_quantity, unit, storage_location, storage_temp, status, received_by, qr_code, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'KARANTIN', ?, ?, ?)
            ");
            
            $stmt->execute([
                $material_id, $batch_number, $supplier_id, $manufacturer, $received_date, $production_date, $exp_date,
                $quantity, $quantity, $unit, $storage_location, $storage_temp, $_SESSION['user_id'], $filename, $notes
            ]);
            
            $inventory_id = $pdo->lastInsertId();
            
            // Audit trail
            if (function_exists('logAuditTrail')) {
                logAuditTrail($pdo, 'Kirim qilish (Inbound)', 'inventory', $inventory_id, null, [
                    'batch_number' => $batch_number,
                    'quantity' => $quantity,
                    'exp_date' => $exp_date
                ]);
            }
            
            $pdo->commit();
            
            $message = "Mahsulot muvaffaqiyatli kiritildi! Partiya: {$batch_number}";
            $messageType = 'success';
            
            // Formani tozalash
            $_POST = array();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Xatolik: " . $e->getMessage();
            $messageType = 'danger';
        } catch (Exception $e) {
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
        .nav-link { color: #94a3b8; padding: 12px 20px; display: flex; align-items: center; transition: 0.3s; border-radius: 8px; margin: 4px 15px; text-decoration: none;}
        .nav-link:hover, .nav-link.active { background: #334155; color: #fff; }
        .nav-link i { width: 25px; font-size: 18px; margin-right: 10px; }
        .main-content { margin-left: 260px; padding: 30px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .form-section { background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .bg-karantin { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .bg-approved { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h5 class="m-0 font-weight-bold">GXP SYSTEM</h5>
        <small class="text-info"><?= htmlspecialchars($_SESSION['role']) ?></small>
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
        <div>
            <h4 class="m-0 font-weight-bold text-secondary">Kirim (Inbound)</h4>
            <small class="text-muted">Yangi mahsulotlarni omborga qabul qilish va ro'yxatga olish</small>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small"><i class="far fa-clock"></i> <?= date('d.m.Y H:i') ?></span>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle border" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['fullname']) ?>
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
            <?php if($warning): ?>
                <div class="mt-2 text-muted small border-top pt-2"><i class="fas fa-exclamation-triangle me-1"></i> <?= $warning ?></div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="row">
            <!-- Asosiy ma'lumotlar -->
            <div class="col-lg-8">
                <div class="form-section">
                    <h6 class="mb-3 text-primary border-bottom pb-2">Mahsulot tafsilotlari</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Mahsulot nomi <span class="text-danger">*</span></label>
                            <input type="text" name="material_name" class="form-control" list="materials_list" value="<?= $_POST['material_name'] ?? '' ?>" placeholder="Nomini yozing..." required autocomplete="off">
                            <datalist id="materials_list">
                                <?php foreach($materials as $m): ?>
                                    <option value="<?= htmlspecialchars($m['material_name']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Seriya raqami (Batch No) <span class="text-danger">*</span></label>
                            <input type="text" name="batch_number" class="form-control" value="<?= $_POST['batch_number'] ?? '' ?>" placeholder="MAS: BATCH-2024-001" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Ta'minotchi</label>
                            <input type="text" name="supplier_name" class="form-control" list="suppliers_list" value="<?= $_POST['supplier_name'] ?? '' ?>" placeholder="Nomini yozing..." autocomplete="off">
                            <datalist id="suppliers_list">
                                <?php foreach($suppliers as $s): ?>
                                    <option value="<?= htmlspecialchars($s['company_name']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ishlab chiqaruvchi</label>
                            <input type="text" name="manufacturer" class="form-control" value="<?= $_POST['manufacturer'] ?? '' ?>" placeholder="Ishlab chiqaruvchi nomi">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h6 class="mb-3 text-primary border-bottom pb-2">Sana va Miqdor</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kirim sanasi <span class="text-danger">*</span></label>
                            <input type="date" name="received_date" class="form-control" value="<?= $_POST['received_date'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ishlab chiqarilgan sana <span class="text-danger">*</span></label>
                            <input type="date" name="production_date" class="form-control" value="<?= $_POST['production_date'] ?? '' ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Yaroqlilik muddati <span class="text-danger">*</span></label>
                            <input type="date" name="exp_date" class="form-control" value="<?= $_POST['exp_date'] ?? '' ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Miqdor <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="<?= $_POST['quantity'] ?? '' ?>" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">O'lchov birligi <span class="text-danger">*</span></label>
                            <select name="unit" class="form-select" required>
                                <option value="kg" <?= (isset($_POST['unit']) && $_POST['unit'] == 'kg') ? 'selected' : '' ?>>kg</option>
                                <option value="g" <?= (isset($_POST['unit']) && $_POST['unit'] == 'g') ? 'selected' : '' ?>>g</option>
                                <option value="dona" <?= (isset($_POST['unit']) && $_POST['unit'] == 'dona') ? 'selected' : '' ?>>dona</option>
                                <option value="litr" <?= (isset($_POST['unit']) && $_POST['unit'] == 'litr') ? 'selected' : '' ?>>litr</option>
                                <option value="ml" <?= (isset($_POST['unit']) && $_POST['unit'] == 'ml') ? 'selected' : '' ?>>ml</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Qo'shimcha ma'lumotlar -->
            <div class="col-lg-4">
                <div class="form-section h-100">
                    <h6 class="mb-3 text-primary border-bottom pb-2">Ombor ma'lumotlari</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Ombordagi joylashuv</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <input type="text" name="storage_location" class="form-control" value="<?= $_POST['storage_location'] ?? '' ?>" placeholder="Stellaj A-1, Zona 2">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Saqlash harorati</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-temperature-low"></i></span>
                            <input type="text" name="storage_temp" class="form-control" value="<?= $_POST['storage_temp'] ?? '' ?>" placeholder="MAS: 15-25°C">
                        </div>
                    </div>
                    
                    <div class="alert alert-info mb-3 p-2 small">
                        <i class="fas fa-info-circle me-1"></i> 
                        <strong>Avtomatik:</strong> Status "KARANTIN". Agar mahsulot/ta'minotchi bo'lmasa, yangi yaratiladi.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Izoh</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Qo'shimcha ma'lumotlar..."><?= $_POST['notes'] ?? '' ?></textarea>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i> Saqlash</button>
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i> Bekor qilish</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>So'nggi kirimlar (Jadval)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Mahsulot (Turi)</th>
                            <th>Seriya No</th>
                            <th>Miqdor</th>
                            <th>Yaroqlilik muddati</th>
                            <th>Status</th>
                            <th>Qabul qildi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("
                            SELECT i.*, m.material_name, m.material_type, u.fullname as receiver_name
                            FROM inventory i
                            JOIN materials m ON i.material_id = m.id
                            LEFT JOIN users u ON i.received_by = u.id
                            ORDER BY i.id DESC LIMIT 5
                        ");
                        while($row = $stmt->fetch()):
                            $st_class = ($row['status'] == 'KARANTIN') ? 'bg-karantin' : 'bg-approved';
                        ?>
                        <tr>
                            <td><span class="badge bg-secondary">#<?= $row['id'] ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($row['material_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($row['material_type']) ?></small>
                            </td>
                            <td><code><?= htmlspecialchars($row['batch_number']) ?></code></td>
                            <td><?= number_format($row['quantity'], 2) ?> <?= htmlspecialchars($row['unit']) ?></td>
                            <td><?= htmlspecialchars($row['exp_date']) ?></td>
                            <td><span class="status-badge <?= $st_class ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td><?= htmlspecialchars($row['receiver_name'] ?? 'Noma\'lum') ?></td>
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