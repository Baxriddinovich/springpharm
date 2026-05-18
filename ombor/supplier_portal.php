<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Faqat Ta'minotchi va Admin kirishi mumkin
if (!in_array($_SESSION['role'], ['Admin', 'Ta\'minotchi'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Ta'minotchi ma'lumotlari
$supplier_stmt = $pdo->query("SELECT * FROM suppliers WHERE id = (SELECT supplier_id FROM users WHERE id = " . $_SESSION['user_id'] . ")");
$supplier = $supplier_stmt->fetch();

// Yuklangan hujjatlar
$docs_stmt = $pdo->query("
    SELECT qd.*, i.batch_number, m.material_name
    FROM qc_documents qd
    JOIN inventory i ON qd.inventory_id = i.id
    JOIN materials m ON i.material_id = m.id
    WHERE qd.uploaded_by = " . $_SESSION['user_id'] . "
    ORDER BY qd.id DESC
");
$documents = $docs_stmt->fetchAll();

// Hujjat yuklash
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
    $inventory_id = $_POST['inventory_id'];
    $document_type = $_POST['document_type'];
    $description = trim($_POST['description']);
    
    $file = $_FILES['document'];
    $file_name = $file['name'];
    $file_type = $file['type'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    
    // Validatsiya
    if ($file_type != 'application/pdf' && $file_size > 5000000) {
        $message = "Faqat PDF formatda va 5MB dan kichik fayl yuklash mumkin!";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Faylni yuklash
            $upload_dir = __DIR__ . '/uploads/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_file_name = time() . '_' . uniqid() . '_' . basename($file_name);
            $file_path = 'uploads/documents/' . $new_file_name;
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Hujjatni saqlash
                $stmt = $pdo->prepare("
                    INSERT INTO qc_documents (inventory_id, document_type, file_path, uploaded_by, description)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$inventory_id, $document_type, $file_path, $_SESSION['user_id'], $description]);
                
                logAuditTrail($pdo, 'Hujjat yuklash', 'qc_documents', $pdo->lastInsertId(), null, [
                    'document_type' => $document_type,
                    'file_path' => $file_path
                ]);
                
                $pdo->commit();
                
                $message = "Hujjat muvaffaqiyatli yuklandi!";
                $messageType = 'success';
            } else {
                $message = "Faylni yuklashda xatolik!";
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Xatolik: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Inventory ID larni olish (faqat ta'minotchi mahsulotlari)
$inventory_stmt = $pdo->query("
    SELECT i.*, m.material_name
    FROM inventory i
    JOIN materials m ON i.material_id = m.id
    WHERE i.supplier_id = (SELECT supplier_id FROM users WHERE id = " . $_SESSION['user_id'] . ")
    ORDER BY i.id DESC
");
$inventory_items = $inventory_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Ta'minotchi Portal</title>
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
        <small class="text-info">Ta'minotchi Portal</small>
    </div>
    <div class="mt-3">
        <a href="supplier_portal.php" class="nav-link active"><i class="fas fa-warehouse"></i> Bosh sahifa</a>
        <a href="#" class="nav-link"><i class="fas fa-file-upload"></i> Hujjat yuklash</a>
        <a href="#" class="nav-link"><i class="fas fa-file-invoice"></i> Buyurtmalar</a>
        <div class="px-3 mt-4">
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-warehouse me-2 text-primary"></i>Ta'minotchi Portal</h4>
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
        <!-- Ta'minotchi ma'lumotlari -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-building me-2 text-primary"></i>Ta'minotchi ma'lumotlari</h6>
                </div>
                <div class="card-body">
                    <?php if($supplier): ?>
                        <div class="small">
                            <div class="mb-2"><strong>Company Name:</strong> <?= $supplier['company_name'] ?></div>
                            <div class="mb-2"><strong>Contact Person:</strong> <?= $supplier['contact_person'] ?></div>
                            <div class="mb-2"><strong>Phone:</strong> <?= $supplier['phone'] ?></div>
                            <div class="mb-2"><strong>Email:</strong> <?= $supplier['email'] ?></div>
                            <div class="mb-2"><strong>Address:</strong> <?= $supplier['address'] ?></div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Ta'minotchi ma'lumotlari topilmadi.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hujjat yuklash -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-file-upload me-2 text-primary"></i>Hujjat yuklash</h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Mahsulot (Batch) tanlang</label>
                                <select name="inventory_id" class="form-select" required>
                                    <option value="">Tanlang...</option>
                                    <?php foreach($inventory_items as $item): ?>
                                        <option value="<?= $item['id'] ?>">
                                            <?= $item['material_name'] ?> - <?= $item['batch_number'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hujjat turi</label>
                                <select name="document_type" class="form-select" required>
                                    <option value="">Tanlang...</option>
                                    <option value="CoA">CoA (Sifat guvohnoma)</option>
                                    <option value="GMP sertifikat">GMP sertifikat</option>
                                    <option value="Invoice">Invoice (Faktura)</option>
                                    <option value="Packing list">Packing list</option>
                                    <option value="Transport">Transport hujjatlari</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Hujjat (PDF formatda) <span class="text-danger">*</span></label>
                                <input type="file" name="document" class="form-control" accept=".pdf" required>
                                <small class="text-muted">Faqat PDF formatda, maksimal hajm: 5MB</small>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Izoh</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Qo'shimcha ma'lumotlar..."></textarea>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-upload me-2"></i>Hujjat yuklash</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Yuklangan hujjatlar -->
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-file-pdf me-2 text-danger"></i>Yuklangan hujjatlar</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mahsulot</th>
                            <th>Seriya</th>
                            <th>Hujjat turi</th>
                            <th>Sana</th>
                            <th>Amal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($documents as $doc): ?>
                        <tr>
                            <td class="text-start ps-4">
                                <strong><?= $doc['material_name'] ?></strong>
                            </td>
                            <td><code><?= $doc['batch_number'] ?></code></td>
                            <td><?= $doc['document_type'] ?></td>
                            <td><?= $doc['upload_date'] ?></td>
                            <td>
                                <a href="<?= $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-info" title="Ko'rish">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>