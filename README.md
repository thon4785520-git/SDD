# ระบบยืมคืนอุปกรณ์ศาสนพิธี (PHP 8 + MySQL + Bootstrap 4)

เว็บแอปสำหรับบริหารการยืม/คืนอุปกรณ์ศาสนพิธี โดยออกแบบ UI สไตล์พรีเมียม รองรับการใช้งานผ่าน PHP 8, MySQL และ Bootstrap 4

## ฟีเจอร์หลัก
- แดชบอร์ดสรุปจำนวนอุปกรณ์ทั้งหมด / พร้อมใช้งาน / กำลังยืม
- ตารางคลังอุปกรณ์ พร้อมสถานะเรียลไทม์
- ฟอร์มบันทึกการยืมอุปกรณ์
- ฟอร์มบันทึกการคืนอุปกรณ์
- ประวัติรายการล่าสุด (ยืม/คืน)
- มีโหมดแสดงผลตัวอย่าง (Demo) หากเชื่อม MySQL ไม่สำเร็จ

## โครงสร้างไฟล์
- `index.php` หน้าเว็บหลัก
- `src/bootstrap.php` เริ่มต้นระบบ
- `src/repository.php` ชั้นจัดการข้อมูล (MySQL + demo fallback)
- `database/schema.sql` โครงสร้างตาราง
- `assets/style.css` สไตล์ UI

## วิธีใช้งาน
1. สร้างฐานข้อมูล MySQL (ตัวอย่างชื่อ `sdd_borrow`)
2. ตั้งค่า Environment Variables

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=sdd_borrow
export DB_USER=root
export DB_PASS=your_password
```

3. รันเซิร์ฟเวอร์ PHP

```bash
php -S 0.0.0.0:8080
```

4. เปิดใช้งานที่

```text
http://localhost:8080/index.php
```

> หมายเหตุ: ระบบจะสร้างตารางให้อัตโนมัติจาก `database/schema.sql` เมื่อเชื่อมต่อฐานข้อมูลสำเร็จ
