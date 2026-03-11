<?php
require_once __DIR__ . '/src/bootstrap.php';

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'borrow') {
        $borrower = trim($_POST['borrower'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $equipmentId = (int) ($_POST['equipment_id'] ?? 0);
        $purpose = trim($_POST['purpose'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? '');

        $result = createBorrowTransaction($equipmentId, $borrower, $phone, $purpose, $dueDate);
        $flash = $result;
    }

    if ($action === 'return') {
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        $result = returnEquipment($transactionId);
        $flash = $result;
    }
}

$equipmentList = getEquipmentList();
$availableEquipment = array_values(array_filter($equipmentList, static function ($item) {
    return $item['status'] === 'available';
}));

$activeTransactions = getActiveTransactions();
$recentTransactions = getRecentTransactions();
$stats = getDashboardStats();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบยืมคืนอุปกรณ์ศาสนพิธี</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
    <a class="navbar-brand" href="#"><i class="fas fa-praying-hands mr-2"></i>ระบบยืมคืนอุปกรณ์ศาสนพิธี</a>
</nav>

<header class="hero-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 text-white">
                <h1 class="display-4 font-weight-bold">ศูนย์บริหารอุปกรณ์ศาสนพิธีแบบครบวงจร</h1>
                <p class="lead mb-4">บริหารการยืม-คืนอุปกรณ์ได้อย่างมืออาชีพ โปร่งใส ตรวจสอบย้อนหลังได้ทันที</p>
                <div class="d-flex flex-wrap">
                    <div class="stat-pill mr-3 mb-2">
                        <span class="label">อุปกรณ์ทั้งหมด</span>
                        <strong><?= htmlspecialchars((string) $stats['total_equipment']) ?></strong>
                    </div>
                    <div class="stat-pill mr-3 mb-2">
                        <span class="label">พร้อมใช้งาน</span>
                        <strong><?= htmlspecialchars((string) $stats['available_equipment']) ?></strong>
                    </div>
                    <div class="stat-pill mb-2">
                        <span class="label">กำลังยืม</span>
                        <strong><?= htmlspecialchars((string) $stats['borrowed_equipment']) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="glass-card text-white">
                    <h5 class="mb-3"><i class="fas fa-star mr-2"></i>ฟีเจอร์เด่น</h5>
                    <ul class="mb-0 pl-3">
                        <li>บันทึกการยืมพร้อมผู้รับผิดชอบ</li>
                        <li>คืนอุปกรณ์ด้วยคลิกเดียว</li>
                        <li>ประวัติรายการล่าสุดแบบเรียลไทม์</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="container my-5">
    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= $flash['success'] ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card border-0 shadow h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="mb-0 text-primary-brand"><i class="fas fa-box-open mr-2"></i>คลังอุปกรณ์ศาสนพิธี</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ชื่ออุปกรณ์</th>
                                    <th>หมวดหมู่</th>
                                    <th>คงเหลือ</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipmentList as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td><?= htmlspecialchars($item['category']) ?></td>
                                        <td><?= htmlspecialchars((string) $item['quantity']) ?></td>
                                        <td>
                                            <?php if ($item['status'] === 'available'): ?>
                                                <span class="badge badge-success">พร้อมให้ยืม</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">กำลังใช้งาน</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="card border-0 shadow mb-4">
                <div class="card-header bg-gradient-brand text-white">
                    <h5 class="mb-0"><i class="fas fa-file-signature mr-2"></i>บันทึกการยืม</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="borrow">
                        <div class="form-group">
                            <label>ชื่อผู้ยืม</label>
                            <input type="text" name="borrower" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>เบอร์โทร</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>อุปกรณ์</label>
                            <select name="equipment_id" class="form-control" required>
                                <option value="">-- เลือกอุปกรณ์ --</option>
                                <?php foreach ($availableEquipment as $item): ?>
                                    <option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['category']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>วัตถุประสงค์</label>
                            <input type="text" name="purpose" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>กำหนดคืน</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-brand btn-block">ยืนยันการยืม</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-undo-alt mr-2"></i>คืนอุปกรณ์</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="return">
                        <div class="form-group">
                            <label>เลือกรายการที่ต้องการคืน</label>
                            <select name="transaction_id" class="form-control" required>
                                <option value="">-- เลือกรายการ --</option>
                                <?php foreach ($activeTransactions as $tx): ?>
                                    <option value="<?= (int) $tx['id'] ?>">
                                        <?= htmlspecialchars($tx['equipment_name']) ?> - <?= htmlspecialchars($tx['borrower']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-dark btn-block">บันทึกการคืน</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow mt-2">
        <div class="card-header bg-white border-0 pt-4 pb-0">
            <h4 class="mb-0 text-primary-brand"><i class="fas fa-history mr-2"></i>ประวัติรายการล่าสุด</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>ผู้ยืม</th>
                        <th>อุปกรณ์</th>
                        <th>ยืมเมื่อ</th>
                        <th>กำหนดคืน</th>
                        <th>สถานะ</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentTransactions as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['borrower']) ?></td>
                            <td><?= htmlspecialchars($tx['equipment_name']) ?></td>
                            <td><?= htmlspecialchars($tx['borrowed_at']) ?></td>
                            <td><?= htmlspecialchars($tx['due_date']) ?></td>
                            <td>
                                <?php if ($tx['status'] === 'active'): ?>
                                    <span class="badge badge-warning">กำลังยืม</span>
                                <?php else: ?>
                                    <span class="badge badge-success">คืนแล้ว</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
