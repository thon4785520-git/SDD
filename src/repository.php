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

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}

function initializeStorage(): void
{
    $pdo = db();
    if (!$pdo) {
        return;
    }

    $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($sql !== false) {
        $pdo->exec($sql);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['ชุดโต๊ะหมู่บูชา 9', 'เครื่องบูชา', 2, 'available'],
            ['พานแว่นฟ้า', 'ภาชนะพิธี', 8, 'available'],
            ['เทียนพรรษาขนาดใหญ่', 'เครื่องแสงสว่าง', 12, 'available'],
            ['ไมโครโฟนไร้สาย', 'ระบบเสียง', 4, 'available'],
            ['ชุดพานพุ่มดอกไม้', 'เครื่องตกแต่ง', 6, 'available'],
        ];

        $stmt = $pdo->prepare('INSERT INTO equipment (name, category, quantity, status) VALUES (?, ?, ?, ?)');
        foreach ($seed as $item) {
            $stmt->execute($item);
        }
    }
}

function getEquipmentList(): array
{
    $pdo = db();
    if (!$pdo) {
        return demoEquipment();
    }

    return $pdo->query('SELECT * FROM equipment ORDER BY name')->fetchAll();
}

function getActiveTransactions(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    $sql = 'SELECT t.id, t.borrower, e.name AS equipment_name
            FROM transactions t
            INNER JOIN equipment e ON e.id = t.equipment_id
            WHERE t.status = "active"
            ORDER BY t.borrowed_at DESC';

    return $pdo->query($sql)->fetchAll();
}

function getRecentTransactions(): array
{
    $pdo = db();
    if (!$pdo) {
        return demoTransactions();
    }

    $sql = 'SELECT t.borrower, t.borrowed_at, t.due_date, t.status, e.name AS equipment_name
            FROM transactions t
            INNER JOIN equipment e ON e.id = t.equipment_id
            ORDER BY t.borrowed_at DESC
            LIMIT 10';

    return $pdo->query($sql)->fetchAll();
}

function getDashboardStats(): array
{
    $items = getEquipmentList();

    $total = count($items);
    $available = count(array_filter($items, static function ($item) {
        return $item['status'] === 'available';
    }));

    return [
        'total_equipment' => $total,
        'available_equipment' => $available,
        'borrowed_equipment' => max(0, $total - $available),
    ];
}

function createBorrowTransaction(int $equipmentId, string $borrower, string $phone, string $purpose, string $dueDate): array
{
    if ($equipmentId <= 0 || $borrower === '' || $phone === '' || $purpose === '' || $dueDate === '') {
        return ['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'];
    }

    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล MySQL ได้ กรุณาตรวจสอบค่า DB_*'];
    }

    $pdo->beginTransaction();

    $equipment = $pdo->prepare('SELECT * FROM equipment WHERE id = ? FOR UPDATE');
    $equipment->execute([$equipmentId]);
    $item = $equipment->fetch();

    if (!$item || $item['status'] !== 'available') {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'อุปกรณ์รายการนี้ไม่พร้อมให้ยืม'];
    }

    $insert = $pdo->prepare('INSERT INTO transactions (equipment_id, borrower, phone, purpose, due_date, status) VALUES (?, ?, ?, ?, ?, "active")');
    $insert->execute([$equipmentId, $borrower, $phone, $purpose, $dueDate]);

    $update = $pdo->prepare('UPDATE equipment SET status = "borrowed" WHERE id = ?');
    $update->execute([$equipmentId]);

    $pdo->commit();

    return ['success' => true, 'message' => 'บันทึกการยืมสำเร็จ'];
}

function returnEquipment(int $transactionId): array
{
    if ($transactionId <= 0) {
        return ['success' => false, 'message' => 'ไม่พบรายการที่ต้องการคืน'];
    }

    $pdo = db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล MySQL ได้'];
    }

    $pdo->beginTransaction();

    $txStmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ? FOR UPDATE');
    $txStmt->execute([$transactionId]);
    $tx = $txStmt->fetch();

    if (!$tx || $tx['status'] !== 'active') {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'รายการนี้ถูกคืนไปแล้ว หรือไม่พบข้อมูล'];
    }

    $updateTx = $pdo->prepare('UPDATE transactions SET status = "returned", returned_at = NOW() WHERE id = ?');
    $updateTx->execute([$transactionId]);

    $updateEq = $pdo->prepare('UPDATE equipment SET status = "available" WHERE id = ?');
    $updateEq->execute([(int) $tx['equipment_id']]);

    $pdo->commit();

    return ['success' => true, 'message' => 'บันทึกการคืนอุปกรณ์เรียบร้อย'];
}

function demoEquipment(): array
{
    return [
        ['id' => 1, 'name' => 'ชุดโต๊ะหมู่บูชา 9', 'category' => 'เครื่องบูชา', 'quantity' => 2, 'status' => 'available'],
        ['id' => 2, 'name' => 'พานแว่นฟ้า', 'category' => 'ภาชนะพิธี', 'quantity' => 8, 'status' => 'available'],
        ['id' => 3, 'name' => 'เทียนพรรษาขนาดใหญ่', 'category' => 'เครื่องแสงสว่าง', 'quantity' => 12, 'status' => 'borrowed'],
        ['id' => 4, 'name' => 'ไมโครโฟนไร้สาย', 'category' => 'ระบบเสียง', 'quantity' => 4, 'status' => 'available'],
    ];
}

function demoTransactions(): array
{
    return [
        [
            'borrower' => 'วัดศรีธรรมาราม',
            'borrowed_at' => date('Y-m-d H:i:s', strtotime('-2 day')),
            'due_date' => date('Y-m-d', strtotime('+1 day')),
            'status' => 'active',
            'equipment_name' => 'เทียนพรรษาขนาดใหญ่',
        ],
        [
            'borrower' => 'ชุมชนบ้านทุ่งใหญ่',
            'borrowed_at' => date('Y-m-d H:i:s', strtotime('-5 day')),
            'due_date' => date('Y-m-d', strtotime('-2 day')),
            'status' => 'returned',
            'equipment_name' => 'พานแว่นฟ้า',
        ],
    ];
}
