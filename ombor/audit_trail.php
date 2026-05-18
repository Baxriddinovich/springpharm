<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Faqat Admin va Auditor kirishi mumkin
if (!in_array($_SESSION['role'], ['Admin', 'Auditor'])) {
    header("Location: dashboard.php");
    exit;
}

// Audit trail loglarni olish
$stmt = $pdo->query("
    SELECT at.*, u.fullname as user_fullname
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    ORDER BY at.id DESC LIMIT 100
");
$audit_logs = $stmt->fetchAll();

// Statistika
$total_logs = $pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn();
$today_logs = $pdo->query("SELECT COUNT(*) FROM audit_trail WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$actions = $pdo->query("SELECT DISTINCT action FROM audit_trail ORDER BY action")->fetchAll();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Audit Trail</title>
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
        .log-card { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid #334155; }
        .log-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .log-action { font-weight: 600; color: #1e293b; }
        .log-table { font-size: 13px; }
        .log-table th { background: #f8fafc; }
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
        <?php endif; ?>
        <a href="audit_trail.php" class="nav-link active"><i class="fas fa-file-shield"></i> Audit Trail</a>
        <div class="px-3 mt-4">
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-file-shield me-2 text-primary"></i>Audit Trail (Log Yozuvlari)</h4>
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

    <!-- Statistika -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase">Jami Loglar</h6>
                            <h2 class="m-0 font-weight-bold"><?= $total_logs ?></h2>
                        </div>
                        <div class="text-primary fs-1"><i class="fas fa-file-alt"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase">Bugungi Loglar</h6>
                            <h2 class="m-0 font-weight-bold text-success"><?= $today_logs ?></h2>
                        </div>
                        <div class="text-success fs-1"><i class="fas fa-calendar-day"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted small text-uppercase">Harakatlar Turi</h6>
                            <h2 class="m-0 font-weight-bold"><?= count($actions) ?></h2>
                        </div>
                        <div class="text-info fs-1"><i class="fas fa-list"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Trail Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Log yozuvlari (100 ta so'nggi)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 log-table">
                    <thead class="table-light">
                        <tr>
                            <th>Sana</th>
                            <th>Foydalanuvchi</th>
                            <th>Harakat</th>
                            <th>Jadval</th>
                            <th>IP Manzil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($audit_logs as $log): ?>
                        <tr>
                            <td><?= $log['created_at'] ?></td>
                            <td><?= $log['user_fullname'] ?? $log['username'] ?? 'Tizim' ?></td>
                            <td><span class="badge bg-primary"><?= $log['action'] ?></span></td>
                            <td><?= $log['table_name'] ?? '-' ?></td>
                            <td><code><?= $log['ip_address'] ?? '-' ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-center py-3">
            <small class="text-muted">Barcha tizim harakatlari avtomatik loglanadi (GMP Data Integrity)</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>