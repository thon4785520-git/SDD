# ระบบยืมคืนอุปกรณ์ศาสนพิธี (Full Stack)

พัฒนาด้วย **PHP 8 + MySQL + Bootstrap 4** รองรับผู้ใช้งาน 2 บทบาท
- ผู้ดูแลระบบ (Admin)
- ผู้ใช้งาน (User)

## โมดูลครบตามระบบ
1. **จัดการข้อมูลผู้ใช้** (Admin)
   - เพิ่มผู้ใช้
   - กำหนดสิทธิ์ admin/user
   - เปิด/ปิดสถานะผู้ใช้งาน
2. **จัดการข้อมูลอุปกรณ์** (Admin)
   - เพิ่มอุปกรณ์
   - แก้ไขจำนวนคงเหลือ
   - สถานะ available / borrowed / maintenance
3. **จัดการการยืมคืน** (Admin + User)
   - สร้างรายการยืม
   - คืนอุปกรณ์
   - ดูประวัติยืมคืน
4. **รายงานสถิติ**
   - จำนวนผู้ใช้
   - จำนวนประเภทอุปกรณ์
   - จำนวนรายการค้างคืน
   - จำนวนคืนสะสม + สรุปรายเดือน

## ข้อมูลเข้าสู่ระบบเริ่มต้น
> ระบบจะสร้างให้อัตโนมัติเมื่อเชื่อม MySQL สำเร็จ
- Admin: `admin / admin123`
- User: `user / user123`

## โครงสร้างไฟล์
- `index.php` หน้าเว็บหลัก (login + dashboard + modules)
- `src/bootstrap.php` เริ่ม session และ initialize database
- `src/repository.php` logic ฝั่ง backend (auth, CRUD, transactions, reports)
- `database/schema.sql` โครงสร้างฐานข้อมูล
- `assets/style.css` ธีม Bootstrap 4

## วิธีติดตั้ง
1. สร้างฐานข้อมูล เช่น `sdd_borrow`
2. ตั้งค่า environment

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=sdd_borrow
export DB_USER=root
export DB_PASS=your_password
```

3. รันระบบ

```bash
php -S 0.0.0.0:8080
```

4. เข้าใช้งาน
- `http://localhost:8080/index.php`

## หมายเหตุ
- หาก MySQL ไม่พร้อม ระบบจะเข้าโหมดสาธิต (demo) สำหรับดูหน้าจอและ flow โดยคำสั่งบันทึกข้อมูลจริงจะถูกปิดไว้
