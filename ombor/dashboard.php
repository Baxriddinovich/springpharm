<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_role = $_SESSION['role'];
$fullname = $_SESSION['fullname'];

// STATISTIKALARNI OLISH
try {
    // Umumiy partiyalar soni
    $total_batches = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    
    // Statuslar bo'yicha sonlar
    $karantin = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status='KARANTIN'")->fetchColumn();
    $approved = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status='RUXSAT ETILGAN'")->fetchColumn();
    $rejected = $pdo->query("SELECT COUNT(*) FROM inventory WHERE status='RAD ETILGAN'")->fetchColumn();
    
    // FEFO: Muddati 6 oydan kam qolgan mahsulotlar soni
    $fefo_alert = $pdo->query("SELECT COUNT(*) FROM inventory WHERE exp_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) AND current_quantity > 0 AND status='RUXSAT ETILGAN'")->fetchColumn();

} catch (PDOException $e) {
    die("Xatolik: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Boshqaruv Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f9; }
        .sidebar { width: 260px; height: 100vh; background: #1e293b; color: #fff; position: fixed; left: 0; top: 0; z-index: 1000; transition: all 0.3s; }
        .sidebar-header { padding: 20px; text-align: center; background: #0f172a; border-bottom: 1px solid #334155; }
        .nav-link { color: #94a3b8; padding: 12px 20px; display: flex; align-items: center; transition: 0.3s; border-radius: 8px; margin: 4px 15px; }
        .nav-link:hover, .nav-link.active { background: #334155; color: #fff; }
        .nav-link i { width: 25px; font-size: 18px; margin-right: 10px; }
        
        .main-content { margin-left: 260px; padding: 30px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .stat-card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .bg-karantin { background: #fef3c7; color: #92400e; }
        .bg-approved { background: #dcfce7; color: #166534; }
        .bg-rejected { background: #fee2e2; color: #991b1b; }
        
        .footer-brand { position: absolute; bottom: 20px; width: 100%; text-align: center; font-size: 10px; color: #64748b; text-transform: uppercase; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h5 class="m-0 font-weight-bold">GXP SYSTEM</h5>
        <small class="text-info"><?= $user_role ?></small>
    </div>
    <div class="mt-3">
        <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
        
        <!-- Faqat Omborchi va Admin uchun -->
        <?php if(in_array($user_role, ['Admin', 'Ombor mudiri'])): ?>
            <a href="inbound.php" class="nav-link"><i class="fas fa-truck-loading"></i> Kirim (Inbound)</a>
        <?php endif; ?>

        <!-- Faqat QC uchun -->
        <?php if(in_array($user_role, ['Admin', 'QC xodimi', 'QC rahbari'])): ?>
            <a href="qc_panel.php" class="nav-link"><i class="fas fa-microscope"></i> QC Laboratoriya</a>
        <?php endif; ?>

        <a href="inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Ombor Qoldig'i</a>
        <a href="outbound.php" class="nav-link"><i class="fas fa-dolly"></i> Chiqim (Outbound)</a>
        
        <?php if($user_role == 'Admin'): ?>
            <a href="admin_users.php" class="nav-link"><i class="fas fa-users-gear"></i> Xodimlar</a>
            <a href="audit_trail.php" class="nav-link"><i class="fas fa-file-shield"></i> Audit Trail</a>
        <?php endif; ?>

        <div class="px-3 mt-4">
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </div>
    </div>
    <div class="footer-brand">Tayyorlovchi: GXP Service Pharm</div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="top-header">
        <h4 class="m-0 font-weight-bold text-secondary">Tizim holati</h4>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small"><i class="far fa-clock"></i> <?= date('d.m.Y H:i') ?></span>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle border" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1"></i> <?= $fullname ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Chiqish</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card stat-card bg-white p-3 border-start border-primary border-5">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted small text-uppercase font-weight-bold">Umumiy Partiyalar</h6>
                        <h2 class="m-0 font-weight-bold"><?= $total_batches ?></h2>
                    </div>
                    <div class="text-primary fs-1"><i class="fas fa-layer-group"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-white p-3 border-start border-warning border-5">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted small text-uppercase font-weight-bold">Karantinda</h6>
                        <h2 class="m-0 font-weight-bold"><?= $karantin ?></h2>
                    </div>
                    <div class="text-warning fs-1"><i class="fas fa-user-shield"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-white p-3 border-start border-success border-5">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted small text-uppercase font-weight-bold">Ruxsat Etilgan</h6>
                        <h2 class="m-0 font-weight-bold"><?= $approved ?></h2>
                    </div>
                    <div class="text-success fs-1"><i class="fas fa-check-double"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-white p-3 border-start border-danger border-5">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted small text-uppercase font-weight-bold">FEFO Alert (6 oy)</h6>
                        <h2 class="m-0 font-weight-bold text-danger"><?= $fefo_alert ?></h2>
                    </div>
                    <div class="text-danger fs-1"><i class="fas fa-calendar-times"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- So'nggi Kirimlar -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-history me-2 text-primary"></i>So'nggi kirim qilingan partiyalar</h6>
                </div>
                <div class="card-body p-0 text-center">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mahsulot nomi</th>
                                    <th>Seriya No</th>
                                    <th>Miqdor</th>
                                    <th>Yaroqlilik muddati</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->query("SELECT i.*, m.material_name FROM inventory i JOIN materials m ON i.material_id = m.id ORDER BY i.id DESC LIMIT 5");
                                while($row = $stmt->fetch()):
                                    $st_class = ($row['status'] == 'KARANTIN') ? 'bg-karantin' : (($row['status'] == 'RUXSAT ETILGAN') ? 'bg-approved' : 'bg-rejected');
                                ?>
                                <tr>
                                    <td class="text-start ps-4"><strong><?= $row['material_name'] ?></strong></td>
                                    <td><code><?= $row['batch_number'] ?></code></td>
                                    <td><?= number_format($row['current_quantity'], 2) ?></td>
                                    <td><?= $row['exp_date'] ?></td>
                                    <td><span class="status-badge <?= $st_class ?>"><?= $row['status'] ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-center py-3">
                    <a href="inventory.php" class="btn btn-sm btn-link text-decoration-none">Barcha partiyalarni ko'rish</a>
                </div>
            </div>
        </div>

        <!-- Role base Quick Actions -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white mb-4">
                <div class="card-body p-4">
                    <h5>Tezkor harakatlar</h5>
                    <p class="small opacity-75">Sizning rolingiz: <?= $user_role ?></p>
                    <div class="d-grid gap-2 mt-3">
                        <?php if($user_role == 'Ombor mudiri' || $user_role == 'Admin'): ?>
                            <a href="inbound.php" class="btn btn-light text-primary btn-sm"><i class="fas fa-plus-circle me-2"></i> Yangi kirim qilish</a>
                        <?php endif; ?>
                        <?php if($user_role == 'QC xodimi' || $user_role == 'QC rahbari' || $user_role == 'Admin'): ?>
                            <a href="qc_panel.php" class="btn btn-light text-primary btn-sm"><i class="fas fa-flask me-2"></i> QC tahlillarini ko'rish</a>
                        <?php endif; ?>
                        <a href="inventory.php" class="btn btn-primary border-white btn-sm"><i class="fas fa-search me-2"></i> Material qidirish</a>
                    </div>
                </div>
            </div>
            
            <!-- FEFO Warning List -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>FEFO (Muddati yaqinlar)</h6>
                </div>
                <div class="card-body p-3">
                    <?php
                    $fefo_list = $pdo->query("SELECT i.batch_number, i.exp_date, m.material_name FROM inventory i JOIN materials m ON i.material_id = m.id WHERE exp_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) AND current_quantity > 0 AND status='RUXSAT ETILGAN' LIMIT 3")->fetchAll();
                    if($fefo_list):
                        foreach($fefo_list as $f): ?>
                            <div class="d-flex align-items-center mb-3 border-bottom pb-2">
                                <div class="bg-light-danger p-2 rounded text-danger me-3"><i class="fas fa-calendar-day"></i></div>
                                <div>
                                    <div class="small font-weight-bold"><?= $f['material_name'] ?></div>
                                    <div class="text-muted" style="font-size: 11px;">Seriya: <?= $f['batch_number'] ?> | <span class="text-danger"><?= $f['exp_date'] ?></span></div>
                                </div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <p class="text-muted small text-center">Yaqin orada muddati o'tadigan mahsulotlar yo'q.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>