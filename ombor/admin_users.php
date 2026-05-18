<?php
session_start();
require 'db.php';

// Tizimga kirganini tekshirish
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Faqat Admin kirishi mumkin
if ($_SESSION['role'] != 'Admin') {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Foydalanuvchilarni olish
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();

// Yangi foydalanuvchi qo'shish
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $status = $_POST['status'] ?? 0;
    
    if (empty($username) || empty($password) || empty($fullname)) {
        $message = "Login, parol va F.I.Sh to'ldirilishi shart!";
        $messageType = 'danger';
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, fullname, email, phone, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hashed_password, $fullname, $email, $phone, $role, $status]);
            
            logAuditTrail($pdo, 'Yangi foydalanuvchi qo\'shish', 'users', $pdo->lastInsertId(), null, [
                'username' => $username,
                'role' => $role
            ]);
            
            $message = "Foydalanuvchi muvaffaqiyatli qo'shildi!";
            $messageType = 'success';
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                $message = "Bu login allaqachon mavjud!";
            } else {
                $message = "Xatolik: " . $e->getMessage();
            }
            $messageType = 'danger';
        }
    }
}

// Foydalanuvchini yangilash
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $user_id = $_POST['user_id'];
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $status = $_POST['status'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?
        ");
        $stmt->execute([$fullname, $email, $phone, $role, $status, $user_id]);
        
        logAuditTrail($pdo, 'Foydalanuvchi ma\'lumotlarini yangilash', 'users', $user_id, null, [
            'fullname' => $fullname,
            'role' => $role
        ]);
        
        $message = "Foydalanuvchi ma'lumotlari yangilandi!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = "Xatolik: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Foydalanuvchini o'chirish
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        logAuditTrail($pdo, 'Foydalanuvchini o\'chirish', 'users', $user_id, null, null);
        
        $message = "Foydalanuvchi o'chirildi!";
        $messageType = 'success';
        
    } catch (PDOException $e) {
        $message = "Bu foydalanuvchini o'chirib bo'lmaydi (bog'liqligi bor)!";
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GXP PHARM | Xodimlar Boshqaruvi</title>
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
        .bg-active { background: #dcfce7; color: #166534; }
        .bg-inactive { background: #fee2e2; color: #991b1b; }
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
        <a href="admin_users.php" class="nav-link active"><i class="fas fa-users-gear"></i> Xodimlar</a>
        <a href="audit_trail.php" class="nav-link"><i class="fas fa-file-shield"></i> Audit Trail</a>
        <div class="px-3 mt-4">
            <a href="logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        <h4 class="m-0 font-weight-bold text-secondary"><i class="fas fa-users me-2 text-primary"></i>Xodimlar Boshqaruvi</h4>
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
        <!-- Yangi foydalanuvchi qo'shish -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Yangi xodim qo'shish</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Login <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parol <span class="text-danger">*</span></label>
                            <input type="text" name="password" class="form-control" placeholder="Parol" required>
                            <small class="text-muted">Parol avtomatik shifrlanadi</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">F.I.Sh <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@domain.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="text" name="phone" class="form-control" placeholder="+998901234567">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rol <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Tanlang...</option>
                                <option value="Admin">Admin</option>
                                <option value="Ombor mudiri">Ombor mudiri</option>
                                <option value="QC xodimi">QC xodimi</option>
                                <option value="QC rahbari">QC rahbari</option>
                                <option value="Ishlab chiqarish">Ishlab chiqarish</option>
                                <option value="Ta'minotchi">Ta'minotchi</option>
                                <option value="Auditor">Auditor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Holat</label>
                            <select name="status" class="form-select">
                                <option value="1">Faol</option>
                                <option value="0">Bloklangan</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> Saqlash</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Foydalanuvchilar ro'yxati -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2 text-primary"></i>Xodimlar ro'yxati (<?= count($users) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>F.I.Sh</th>
                                    <th>Login</th>
                                    <th>Email</th>
                                    <th>Telefon</th>
                                    <th>Rol</th>
                                    <th>Holat</th>
                                    <th>So'nggi kirish</th>
                                    <th>Amal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                <?php
                                $status_class = $user['status'] ? 'bg-active' : 'bg-inactive';
                                ?>
                                <tr>
                                    <td class="text-start ps-4">
                                        <strong><?= $user['fullname'] ?></strong>
                                    </td>
                                    <td><code><?= $user['username'] ?></code></td>
                                    <td><?= $user['email'] ?? '-' ?></td>
                                    <td><?= $user['phone'] ?? '-' ?></td>
                                    <td><?= $user['role'] ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= $user['status'] ? 'Faol' : 'Bloklangan' ?></span></td>
                                    <td><?= $user['last_login'] ?? 'Kirmagan' ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" data-user='<?= json_encode($user) ?>'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Haqiqatan ham o\'chirmoqchimisiz?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foydalanuvchini tahrirlash</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">F.I.Sh</label>
                        <input type="text" name="fullname" id="edit_fullname" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="Admin">Admin</option>
                            <option value="Ombor mudiri">Ombor mudiri</option>
                            <option value="QC xodimi">QC xodimi</option>
                            <option value="QC rahbari">QC rahbari</option>
                            <option value="Ishlab chiqarish">Ishlab chiqarish</option>
                            <option value="Ta'minotchi">Ta'minotchi</option>
                            <option value="Auditor">Auditor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Holat</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="1">Faol</option>
                            <option value="0">Bloklangan</option>
                        </select>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary w-100">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Edit modalni ochish
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('[data-bs-target="#editModal"]');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const user = JSON.parse(this.getAttribute('data-user'));
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_fullname').value = user.fullname;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
        });
    });
});
</script>
</body>
</html>