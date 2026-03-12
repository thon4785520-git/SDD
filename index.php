<?php
require_once __DIR__ . '/src/bootstrap.php';

$flash = null;
$currentUser = $_SESSION['auth_user'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $auth = authenticateUser($username, $password);

        if ($auth) {
            $_SESSION['auth_user'] = $auth;
            header('Location: index.php');
            exit;
        }

        $flash = ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
    }

    if ($action === 'logout') {
        unset($_SESSION['auth_user']);
        header('Location: index.php');
        exit;
    }

    $currentUser = $_SESSION['auth_user'] ?? null;
    $isAdmin = $currentUser && $currentUser['role'] === 'admin';

    if ($currentUser && $action === 'create_user' && $isAdmin) {
        $flash = createUser(
            trim($_POST['name'] ?? ''),
            trim($_POST['username'] ?? ''),
            trim($_POST['password'] ?? ''),
            trim($_POST['role'] ?? 'user'),
            trim($_POST['phone'] ?? '')
        );
    }

    if ($currentUser && $action === 'toggle_user' && $isAdmin) {
        $flash = toggleUserStatus((int) ($_POST['user_id'] ?? 0));
    }

    if ($currentUser && $action === 'create_equipment' && $isAdmin) {
        $flash = createEquipment(
            trim($_POST['name'] ?? ''),
            trim($_POST['category'] ?? ''),
            (int) ($_POST['quantity'] ?? 0),
            trim($_POST['detail'] ?? '')
        );
    }

    if ($currentUser && $action === 'update_equipment_qty' && $isAdmin) {
        $flash = updateEquipmentQty((int) ($_POST['equipment_id'] ?? 0), (int) ($_POST['quantity'] ?? -1));
    }

    if ($currentUser && $action === 'borrow') {
        $flash = borrowEquipment(
            (int) $currentUser['id'],
            (int) ($_POST['equipment_id'] ?? 0),
            (int) ($_POST['quantity'] ?? 0),
            trim($_POST['purpose'] ?? ''),
            trim($_POST['due_date'] ?? '')
        );
    }

    if ($currentUser && $action === 'return') {
        $flash = returnTransaction((int) ($_POST['transaction_id'] ?? 0));
    }
}

$currentUser = $_SESSION['auth_user'] ?? null;

if (!$currentUser):
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | ระบบยืมคืนอุปกรณ์ศาสนพิธี</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <h3 class="text-center mb-2">ระบบยืมคืนอุปกรณ์ศาสนพิธี</h3>
                    <p class="text-muted text-center mb-4">เข้าสู่ระบบเพื่อใช้งาน Full Stack</p>
                    <?php if ($flash): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($flash['message']) ?></div>
                    <?php endif; ?>
                    <?php if (!isDbReady()): ?>
                        <div class="alert alert-warning small">กำลังทำงานในโหมดสาธิต (MySQL ไม่พร้อมใช้งาน): ลองเข้า admin/admin123 หรือ user/user123</div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label>ชื่อผู้ใช้</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>รหัสผ่าน</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-brand btn-block" type="submit">เข้าสู่ระบบ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
exit;
endif;

$isAdmin = $currentUser['role'] === 'admin';
$module = $_GET['module'] ?? 'dashboard';

$users = $isAdmin ? listUsers() : [];
$equipment = listEquipment();
$available = listAvailableEquipment();
$transactions = listBorrowTransactions($isAdmin ? null : (int) $currentUser['id']);
$activeTx = array_values(array_filter($transactions, static function ($tx) {
    return $tx['status'] === 'borrowed';
}));
$stats = getStatistics();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบยืมคืนอุปกรณ์ศาสนพิธี - Full Stack</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
    <a class="navbar-brand" href="index.php"><i class="fas fa-praying-hands mr-2"></i>ระบบยืมคืนอุปกรณ์ศาสนพิธี</a>
    <div class="ml-auto d-flex align-items-center text-white">
        <span class="mr-3 badge badge-light text-dark"><?= htmlspecialchars($currentUser['name']) ?> (<?= $isAdmin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน' ?>)</span>
        <form method="post" class="mb-0">
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-sm btn-outline-light" type="submit">ออกจากระบบ</button>
        </form>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 sidebar py-4">
            <a href="?module=dashboard" class="menu-item <?= $module === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-line mr-2"></i>แดชบอร์ด</a>
            <?php if ($isAdmin): ?>
                <a href="?module=users" class="menu-item <?= $module === 'users' ? 'active' : '' ?>"><i class="fas fa-users-cog mr-2"></i>จัดการผู้ใช้</a>
                <a href="?module=equipment" class="menu-item <?= $module === 'equipment' ? 'active' : '' ?>"><i class="fas fa-boxes mr-2"></i>จัดการอุปกรณ์</a>
            <?php endif; ?>
            <a href="?module=borrow" class="menu-item <?= $module === 'borrow' ? 'active' : '' ?>"><i class="fas fa-hand-holding mr-2"></i>ยืมคืนอุปกรณ์</a>
            <a href="?module=reports" class="menu-item <?= $module === 'reports' ? 'active' : '' ?>"><i class="fas fa-file-alt mr-2"></i>รายงานสถิติ</a>
        </aside>

        <main class="col-lg-10 p-4">
            <?php if (!isDbReady()): ?>
                <div class="alert alert-warning">ระบบอยู่ในโหมดสาธิต (Demo) เนื่องจาก MySQL ยังไม่พร้อมใช้งาน</div>
            <?php endif; ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['success'] ? 'success' : 'danger' ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($module === 'dashboard'): ?>
                <div class="row mb-4">
                    <div class="col-md-3 mb-3"><div class="stat-card"><h6>ผู้ใช้ทั้งหมด</h6><h3><?= (int) $stats['users'] ?></h3></div></div>
                    <div class="col-md-3 mb-3"><div class="stat-card"><h6>ประเภทอุปกรณ์</h6><h3><?= (int) $stats['equipment_types'] ?></h3></div></div>
                    <div class="col-md-3 mb-3"><div class="stat-card"><h6>กำลังยืม</h6><h3><?= (int) $stats['active_borrows'] ?></h3></div></div>
                    <div class="col-md-3 mb-3"><div class="stat-card"><h6>คืนแล้วสะสม</h6><h3><?= (int) $stats['returned_total'] ?></h3></div></div>
                </div>
                <div class="card border-0 shadow">
                    <div class="card-body">
                        <h5>ธุรกรรมล่าสุด</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead><tr><th>ผู้ยืม</th><th>อุปกรณ์</th><th>จำนวน</th><th>กำหนดคืน</th><th>สถานะ</th></tr></thead>
                                <tbody>
                                <?php foreach (array_slice($transactions, 0, 8) as $tx): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tx['user_name']) ?></td>
                                        <td><?= htmlspecialchars($tx['equipment_name']) ?></td>
                                        <td><?= (int) $tx['quantity'] ?></td>
                                        <td><?= htmlspecialchars($tx['due_date']) ?></td>
                                        <td><span class="badge badge-<?= $tx['status'] === 'borrowed' ? 'warning' : 'success' ?>"><?= $tx['status'] === 'borrowed' ? 'กำลังยืม' : 'คืนแล้ว' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($module === 'users' && $isAdmin): ?>
                <div class="row">
                    <div class="col-lg-5 mb-4">
                        <div class="card border-0 shadow">
                            <div class="card-header bg-gradient-brand text-white">เพิ่มผู้ใช้งาน</div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="create_user">
                                    <div class="form-group"><label>ชื่อ</label><input name="name" class="form-control" required></div>
                                    <div class="form-group"><label>Username</label><input name="username" class="form-control" required></div>
                                    <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                                    <div class="form-group"><label>เบอร์โทร</label><input name="phone" class="form-control"></div>
                                    <div class="form-group"><label>สิทธิ์</label>
                                        <select name="role" class="form-control"><option value="user">ผู้ใช้งาน</option><option value="admin">ผู้ดูแลระบบ</option></select>
                                    </div>
                                    <button class="btn btn-brand btn-block">บันทึกผู้ใช้</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 mb-4">
                        <div class="card border-0 shadow">
                            <div class="card-body">
                                <h5>รายการผู้ใช้</h5>
                                <table class="table table-hover">
                                    <thead><tr><th>ชื่อ</th><th>username</th><th>สิทธิ์</th><th>สถานะ</th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($u['name']) ?></td>
                                            <td><?= htmlspecialchars($u['username']) ?></td>
                                            <td><?= $u['role'] === 'admin' ? 'ผู้ดูแล' : 'ผู้ใช้งาน' ?></td>
                                            <td><span class="badge badge-<?= (int) $u['active'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $u['active'] === 1 ? 'ใช้งาน' : 'ปิดใช้งาน' ?></span></td>
                                            <td>
                                                <form method="post" class="mb-0">
                                                    <input type="hidden" name="action" value="toggle_user">
                                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-dark">สลับสถานะ</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($module === 'equipment' && $isAdmin): ?>
                <div class="row">
                    <div class="col-lg-5 mb-4">
                        <div class="card border-0 shadow">
                            <div class="card-header bg-gradient-brand text-white">เพิ่มอุปกรณ์</div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="create_equipment">
                                    <div class="form-group"><label>ชื่ออุปกรณ์</label><input name="name" class="form-control" required></div>
                                    <div class="form-group"><label>หมวดหมู่</label><input name="category" class="form-control" required></div>
                                    <div class="form-group"><label>จำนวน</label><input type="number" name="quantity" min="1" class="form-control" required></div>
                                    <div class="form-group"><label>รายละเอียด</label><textarea name="detail" class="form-control"></textarea></div>
                                    <button class="btn btn-brand btn-block">บันทึกอุปกรณ์</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 mb-4">
                        <div class="card border-0 shadow">
                            <div class="card-body">
                                <h5>รายการอุปกรณ์</h5>
                                <table class="table table-hover">
                                    <thead><tr><th>ชื่อ</th><th>หมวดหมู่</th><th>คงเหลือ</th><th>สถานะ</th><th>ปรับจำนวน</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($equipment as $eq): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($eq['name']) ?></td>
                                            <td><?= htmlspecialchars($eq['category']) ?></td>
                                            <td><?= (int) $eq['quantity'] ?></td>
                                            <td><span class="badge badge-<?= $eq['status'] === 'maintenance' ? 'secondary' : ($eq['status'] === 'borrowed' ? 'warning' : 'success') ?>"><?= htmlspecialchars($eq['status']) ?></span></td>
                                            <td>
                                                <form method="post" class="form-inline">
                                                    <input type="hidden" name="action" value="update_equipment_qty">
                                                    <input type="hidden" name="equipment_id" value="<?= (int) $eq['id'] ?>">
                                                    <input type="number" name="quantity" class="form-control form-control-sm mr-2" style="width:80px" min="0" value="<?= (int) $eq['quantity'] ?>">
                                                    <button class="btn btn-sm btn-outline-primary">บันทึก</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($module === 'borrow'): ?>
                <div class="row">
                    <div class="col-lg-5 mb-4">
                        <div class="card border-0 shadow mb-4">
                            <div class="card-header bg-gradient-brand text-white">ทำรายการยืม</div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="borrow">
                                    <div class="form-group"><label>อุปกรณ์</label>
                                        <select name="equipment_id" class="form-control" required>
                                            <option value="">-- เลือก --</option>
                                            <?php foreach ($available as $eq): ?>
                                                <option value="<?= (int) $eq['id'] ?>"><?= htmlspecialchars($eq['name']) ?> (คงเหลือ <?= (int) $eq['quantity'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group"><label>จำนวนที่ยืม</label><input type="number" name="quantity" min="1" class="form-control" required></div>
                                    <div class="form-group"><label>วัตถุประสงค์</label><input name="purpose" class="form-control" required></div>
                                    <div class="form-group"><label>กำหนดคืน</label><input type="date" name="due_date" class="form-control" required></div>
                                    <button class="btn btn-brand btn-block">ยืนยันการยืม</button>
                                </form>
                            </div>
                        </div>
                        <div class="card border-0 shadow">
                            <div class="card-header bg-dark text-white">คืนอุปกรณ์</div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="return">
                                    <div class="form-group"><label>เลือกรายการ</label>
                                        <select name="transaction_id" class="form-control" required>
                                            <option value="">-- เลือก --</option>
                                            <?php foreach ($activeTx as $tx): ?>
                                                <option value="<?= (int) $tx['id'] ?>"><?= htmlspecialchars($tx['equipment_name']) ?> x <?= (int) $tx['quantity'] ?> (<?= htmlspecialchars($tx['user_name']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button class="btn btn-outline-dark btn-block">บันทึกการคืน</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 mb-4">
                        <div class="card border-0 shadow">
                            <div class="card-body">
                                <h5>ประวัติยืมคืน</h5>
                                <table class="table table-striped">
                                    <thead><tr><th>ผู้ยืม</th><th>อุปกรณ์</th><th>จำนวน</th><th>กำหนดคืน</th><th>สถานะ</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tx['user_name']) ?></td>
                                            <td><?= htmlspecialchars($tx['equipment_name']) ?></td>
                                            <td><?= (int) $tx['quantity'] ?></td>
                                            <td><?= htmlspecialchars($tx['due_date']) ?></td>
                                            <td><span class="badge badge-<?= $tx['status'] === 'borrowed' ? 'warning' : 'success' ?>"><?= $tx['status'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($module === 'reports'): ?>
                <div class="card border-0 shadow mb-4">
                    <div class="card-body">
                        <h5>รายงานสถิติการยืมคืน</h5>
                        <div class="row mt-4">
                            <div class="col-md-3 mb-3"><div class="report-pill">ผู้ใช้: <strong><?= (int) $stats['users'] ?></strong></div></div>
                            <div class="col-md-3 mb-3"><div class="report-pill">อุปกรณ์: <strong><?= (int) $stats['equipment_types'] ?></strong></div></div>
                            <div class="col-md-3 mb-3"><div class="report-pill">ยืมค้าง: <strong><?= (int) $stats['active_borrows'] ?></strong></div></div>
                            <div class="col-md-3 mb-3"><div class="report-pill">คืนสะสม: <strong><?= (int) $stats['returned_total'] ?></strong></div></div>
                        </div>
                        <h6 class="mt-3">สรุปรายเดือน</h6>
                        <table class="table table-bordered">
                            <thead><tr><th>เดือน</th><th>จำนวนรายการยืม</th></tr></thead>
                            <tbody>
                            <?php foreach ($stats['recent'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($row['month_key'] ?? '-')) ?></td>
                                    <td><?= (int) ($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
