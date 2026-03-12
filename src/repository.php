<?php

declare(strict_types=1);

function db(): ?PDO
{
    static $pdo = false;

    if ($pdo !== false) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'sdd_borrow';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}

function isDbReady(): bool
{
    return db() instanceof PDO;
}

function initializeStorage(): void
{
    $pdo = db();
    if (!$pdo) {
        return;
    }

    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema !== false) {
        $pdo->exec($schema);
    }

    $countUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($countUsers === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (name, username, password_hash, role, phone, active) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(['ผู้ดูแลระบบ', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', '0811111111', 1]);
        $stmt->execute(['ผู้ใช้งานทั่วไป', 'user', password_hash('user123', PASSWORD_DEFAULT), 'user', '0822222222', 1]);
    }

    $countEquip = (int) $pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
    if ($countEquip === 0) {
        $seed = [
            ['โต๊ะหมู่บูชา 9', 'เครื่องบูชา', 3, 'available', 'ชุดโต๊ะหมู่บูชางานพิธีทั่วไป'],
            ['พานแว่นฟ้า', 'ภาชนะพิธี', 10, 'available', 'ใช้ในพิธีทางศาสนา'],
            ['เทียนพรรษาใหญ่', 'เครื่องแสงสว่าง', 20, 'available', 'งานเข้าพรรษาและพิธีสำคัญ'],
            ['ไมโครโฟนไร้สาย', 'ระบบเสียง', 6, 'available', 'สำหรับพิธีที่มีผู้ร่วมงานจำนวนมาก'],
        ];

        $stmt = $pdo->prepare('INSERT INTO equipment (name, category, quantity, status, detail) VALUES (?, ?, ?, ?, ?)');
        foreach ($seed as $row) {
            $stmt->execute($row);
        }
    }
}

function authenticateUser(string $username, string $password): ?array
{
    $pdo = db();
    if (!$pdo) {
        if ($username === 'admin' && $password === 'admin123') {
            return ['id' => 1, 'name' => 'ผู้ดูแลระบบ', 'username' => 'admin', 'role' => 'admin'];
        }
        if ($username === 'user' && $password === 'user123') {
            return ['id' => 2, 'name' => 'ผู้ใช้งานทั่วไป', 'username' => 'user', 'role' => 'user'];
        }
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, name, username, password_hash, role, active FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['active'] !== 1) {
        return null;
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        return null;
    }

    return [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];
}

function listUsers(): array
{
    $pdo = db();
    if (!$pdo) {
        return demoUsers();
    }

    return $pdo->query('SELECT id, name, username, role, phone, active, created_at FROM users ORDER BY created_at DESC')->fetchAll();
}

function createUser(string $name, string $username, string $password, string $role, string $phone): array
{
    if ($name === '' || $username === '' || $password === '' || !in_array($role, ['admin', 'user'], true)) {
        return ['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'];
    }

    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'ระบบยังไม่เชื่อมต่อ MySQL (โหมดสาธิตไม่รองรับบันทึกข้อมูล)'];
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $check->execute([$username]);
    if ((int) $check->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'];
    }

    $stmt = $pdo->prepare('INSERT INTO users (name, username, password_hash, role, phone, active) VALUES (?, ?, ?, ?, ?, 1)');
    $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $phone]);

    return ['success' => true, 'message' => 'เพิ่มผู้ใช้สำเร็จ'];
}

function toggleUserStatus(int $userId): array
{
    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'โหมดสาธิตไม่รองรับการปรับสถานะผู้ใช้'];
    }

    $stmt = $pdo->prepare('UPDATE users SET active = IF(active = 1, 0, 1) WHERE id = ?');
    $stmt->execute([$userId]);

    return ['success' => true, 'message' => 'อัปเดตสถานะผู้ใช้แล้ว'];
}

function listEquipment(): array
{
    $pdo = db();
    if (!$pdo) {
        return demoEquipment();
    }

    return $pdo->query('SELECT * FROM equipment ORDER BY created_at DESC')->fetchAll();
}

function createEquipment(string $name, string $category, int $quantity, string $detail): array
{
    if ($name === '' || $category === '' || $quantity < 1) {
        return ['success' => false, 'message' => 'ข้อมูลอุปกรณ์ไม่ครบถ้วน'];
    }

    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'โหมดสาธิตไม่รองรับการเพิ่มอุปกรณ์'];
    }

    $stmt = $pdo->prepare('INSERT INTO equipment (name, category, quantity, status, detail) VALUES (?, ?, ?, "available", ?)');
    $stmt->execute([$name, $category, $quantity, $detail]);

    return ['success' => true, 'message' => 'เพิ่มอุปกรณ์สำเร็จ'];
}

function updateEquipmentQty(int $equipmentId, int $quantity): array
{
    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'โหมดสาธิตไม่รองรับการแก้ไขอุปกรณ์'];
    }
    if ($quantity < 0) {
        return ['success' => false, 'message' => 'จำนวนต้องไม่น้อยกว่า 0'];
    }

    $stmt = $pdo->prepare('UPDATE equipment SET quantity = ?, status = IF(? > 0, "available", "maintenance") WHERE id = ?');
    $stmt->execute([$quantity, $quantity, $equipmentId]);

    return ['success' => true, 'message' => 'ปรับจำนวนอุปกรณ์แล้ว'];
}

function listAvailableEquipment(): array
{
    $all = listEquipment();
    return array_values(array_filter($all, static function ($item) {
        return in_array($item['status'], ['available', 'borrowed'], true) && (int) $item['quantity'] > 0;
    }));
}

function borrowEquipment(int $userId, int $equipmentId, int $qty, string $purpose, string $dueDate): array
{
    if ($equipmentId <= 0 || $qty <= 0 || $purpose === '' || $dueDate === '') {
        return ['success' => false, 'message' => 'กรุณากรอกข้อมูลการยืมให้ครบถ้วน'];
    }

    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'โหมดสาธิตไม่รองรับการบันทึกยืมคืน'];
    }

    $pdo->beginTransaction();

    $eqStmt = $pdo->prepare('SELECT * FROM equipment WHERE id = ? FOR UPDATE');
    $eqStmt->execute([$equipmentId]);
    $eq = $eqStmt->fetch();

    if (!$eq || (int) $eq['quantity'] < $qty || $eq['status'] === 'maintenance') {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'อุปกรณ์ไม่เพียงพอหรือไม่พร้อมใช้งาน'];
    }

    $ins = $pdo->prepare('INSERT INTO borrow_transactions (equipment_id, user_id, quantity, purpose, due_date, status) VALUES (?, ?, ?, ?, ?, "borrowed")');
    $ins->execute([$equipmentId, $userId, $qty, $purpose, $dueDate]);

    $remaining = (int) $eq['quantity'] - $qty;
    $up = $pdo->prepare('UPDATE equipment SET quantity = ?, status = IF(? > 0, "available", "borrowed") WHERE id = ?');
    $up->execute([$remaining, $remaining, $equipmentId]);

    $pdo->commit();

    return ['success' => true, 'message' => 'ทำรายการยืมสำเร็จ'];
}

function returnTransaction(int $transactionId): array
{
    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'โหมดสาธิตไม่รองรับการคืนอุปกรณ์'];
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM borrow_transactions WHERE id = ? FOR UPDATE');
    $stmt->execute([$transactionId]);
    $tx = $stmt->fetch();

    if (!$tx || $tx['status'] !== 'borrowed') {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'ไม่พบรายการยืมที่ยังไม่คืน'];
    }

    $upTx = $pdo->prepare('UPDATE borrow_transactions SET status = "returned", returned_at = NOW() WHERE id = ?');
    $upTx->execute([$transactionId]);

    $upEq = $pdo->prepare('UPDATE equipment SET quantity = quantity + ?, status = "available" WHERE id = ?');
    $upEq->execute([(int) $tx['quantity'], (int) $tx['equipment_id']]);

    $pdo->commit();

    return ['success' => true, 'message' => 'คืนอุปกรณ์เรียบร้อย'];
}

function listBorrowTransactions(?int $userId = null): array
{
    $pdo = db();
    if (!$pdo) {
        return demoBorrowTransactions();
    }

    $sql = 'SELECT bt.*, u.name AS user_name, e.name AS equipment_name
            FROM borrow_transactions bt
            INNER JOIN users u ON u.id = bt.user_id
            INNER JOIN equipment e ON e.id = bt.equipment_id';

    if ($userId !== null) {
        $sql .= ' WHERE bt.user_id = :user_id';
    }

    $sql .= ' ORDER BY bt.borrowed_at DESC';

    $stmt = $pdo->prepare($sql);
    if ($userId !== null) {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function getStatistics(): array
{
    $pdo = db();
    if (!$pdo) {
        return [
            'users' => count(demoUsers()),
            'equipment_types' => count(demoEquipment()),
            'active_borrows' => 1,
            'returned_total' => 4,
            'recent' => demoBorrowTransactions(),
        ];
    }

    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $equipmentTypes = (int) $pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
    $activeBorrows = (int) $pdo->query('SELECT COUNT(*) FROM borrow_transactions WHERE status = "borrowed"')->fetchColumn();
    $returnedTotal = (int) $pdo->query('SELECT COUNT(*) FROM borrow_transactions WHERE status = "returned"')->fetchColumn();

    $recent = $pdo->query('SELECT DATE_FORMAT(borrowed_at, "%Y-%m") AS month_key, COUNT(*) AS total
                           FROM borrow_transactions
                           GROUP BY DATE_FORMAT(borrowed_at, "%Y-%m")
                           ORDER BY month_key DESC
                           LIMIT 6')->fetchAll();

    return [
        'users' => $users,
        'equipment_types' => $equipmentTypes,
        'active_borrows' => $activeBorrows,
        'returned_total' => $returnedTotal,
        'recent' => $recent,
    ];
}

function demoUsers(): array
{
    return [
        ['id' => 1, 'name' => 'ผู้ดูแลระบบ', 'username' => 'admin', 'role' => 'admin', 'phone' => '0811111111', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 2, 'name' => 'ผู้ใช้งานทั่วไป', 'username' => 'user', 'role' => 'user', 'phone' => '0822222222', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')],
    ];
}

function demoEquipment(): array
{
    return [
        ['id' => 1, 'name' => 'โต๊ะหมู่บูชา 9', 'category' => 'เครื่องบูชา', 'quantity' => 3, 'status' => 'available', 'detail' => 'ชุดมาตรฐาน'],
        ['id' => 2, 'name' => 'พานแว่นฟ้า', 'category' => 'ภาชนะพิธี', 'quantity' => 10, 'status' => 'available', 'detail' => 'สีทอง'],
        ['id' => 3, 'name' => 'เทียนพรรษาใหญ่', 'category' => 'เครื่องแสงสว่าง', 'quantity' => 0, 'status' => 'borrowed', 'detail' => 'กำลังยืมใช้งาน'],
    ];
}

function demoBorrowTransactions(): array
{
    return [
        [
            'id' => 1,
            'user_name' => 'ผู้ใช้งานทั่วไป',
            'equipment_name' => 'เทียนพรรษาใหญ่',
            'quantity' => 2,
            'purpose' => 'พิธีวันสำคัญทางศาสนา',
            'due_date' => date('Y-m-d', strtotime('+1 day')),
            'borrowed_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'returned_at' => null,
            'status' => 'borrowed',
            'user_id' => 2,
        ],
    ];
}
