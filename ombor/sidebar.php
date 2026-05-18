<?php
// OMBOR + QC TIZIMI SIDEBAR
// Tayyorlovchi: GXP Service Pharm

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_role = $_SESSION['role'];
$fullname = $_SESSION['fullname'];
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h5 class="m-0 font-weight-bold">GXP SYSTEM</h5>
        <small class="text-info"><?= $user_role ?></small>
    </div>
    <div class="mt-3">
        <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="inbound.php" class="nav-link"><i class="fas fa-truck-loading"></i> Kirim (Inbound)</a>
        <a href="inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Ombor Qoldig'i</a>
        <a href="outbound.php" class="nav-link"><i class="fas fa-dolly"></i> Chiqim (Outbound)</a>
        <a href="qc_panel.php" class="nav-link"><i class="fas fa-microscope"></i> QC Laboratoriya</a>
        <a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Hisobotlar</a>
        
        <?php if($user_role == 'Admin'): ?>
            <a href="admin_users.php" class="nav-link"><i class="fas fa-users-gear"></i> Xodimlar</a>
            <a href="audit_trail.php" class="nav-link"><i class="fas fa-file-shield"></i> Audit Trail</a>
        <?php endif; ?>
        
        <?php if(in_array($user_role, ['Admin', 'Ishlab chiqarish'])): ?>
            <a href="production_orders.php" class="nav-link"><i class="fas fa-file-invoice"></i> Buyurtmalar</a>
        <?php endif; ?>
        
        <?php if(in_array($user_role, ['Admin', 'Ta\'minotchi'])): ?>
            <a href="supplier_portal.php" class="nav-link"><i class="fas fa-warehouse"></i> Ta'minotchi Portal</a>
        <?php endif; ?>
        
        <div class="px-3 mt-4">
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </div>
    </div>
</div>