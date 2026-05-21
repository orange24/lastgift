# LastGift — เว็บรวมน้ำใจทำบุญรุ่น

เว็บแอป PHP สำหรับให้เพื่อนๆ ในรุ่นโอนเงินร่วมทำบุญให้เพื่อนผู้จากไป
หรือพ่อแม่ของเพื่อน — รองรับ PromptPay QR + แจ้งสลิป + admin verify

## Tech stack
- PHP 5.6 + (PDO, openssl, fileinfo เปิดอยู่ — มากับ PHP มาตรฐาน)
- MySQL 5.6+ / MariaDB 10.x
- ฝั่ง browser ใช้ qrcode.js + Tesseract.js (โหลดจาก CDN — ไม่ต้อง install)

## โครงสร้างไฟล์

```
/
├── index.php                  หน้าหลัก list campaigns
├── campaign.php               หน้าแคมเปญ + form บริจาค + รายชื่อ
├── submit.php                 รับ POST บันทึก donation
├── setup_admin.php            ใช้ครั้งเดียวสร้าง admin คนแรก แล้วลบทิ้ง
├── schema.sql
├── .htaccess                  block sensitive files
├── .gitignore
├── includes/
│   ├── bootstrap.php          โหลด config + session + helpers ทั้งหมด
│   ├── config.example.php     copy เป็น config.php
│   ├── db.php                 PDO
│   ├── auth.php               admin login/logout
│   ├── functions.php          helpers (csrf, upload, escape, ฯลฯ)
│   ├── promptpay.php          PromptPay QR payload (server-side)
│   ├── header.php / footer.php
├── admin/
│   ├── login.php / logout.php
│   ├── index.php              dashboard
│   ├── campaigns.php          CRUD แคมเปญ
│   ├── donations.php          verify / reject / ลบ
│   ├── settings.php           แก้บัญชี + เปลี่ยน password
│   ├── _layout.php / _footer.php
├── assets/
│   ├── css/style.css
│   ├── js/donate.js           QR render + OCR slip
├── uploads/
│   ├── slips/                 รูปสลิป (ห้าม PHP run)
│   ├── heroes/                รูปประกอบแคมเปญ
│   ├── .htaccess
```

## ขั้นตอน deploy

1. **อัปไฟล์ทั้งหมด** ขึ้น shared host
2. **สร้าง database** บน hosting (เช่นผ่าน DirectAdmin/cPanel) แล้ว import `schema.sql`
3. **copy `includes/config.example.php` → `includes/config.php`** แล้วแก้ค่า
   - DB host / name / user / pass
   - `ip_hash_salt` ใส่ random string สัก 32 ตัว
   - `force_https = true` (ถ้า host มี SSL)
4. **เปิดสิทธิ์เขียน** ที่ `uploads/slips/` และ `uploads/heroes/` (chmod 0775 หรือเท่าที่ host อนุญาต)
5. **เปิด `https://yourdomain/setup_admin.php`** กรอก username/password ของ admin คนแรก
6. **ลบไฟล์ `setup_admin.php`** ทันทีหลังสร้างเสร็จ (สำคัญมาก)
7. **login ที่ `/admin/login.php`** แล้วเข้า "ตั้งค่า" แก้
   - ชื่อบัญชีรับเงิน / เลขบัญชี / ธนาคาร
   - PromptPay ID + ประเภท (phone/nid/ewallet)
   - ชื่อเว็บ / คำโปรย
8. **สร้างแคมเปญแรก** ที่หน้า admin → แคมเปญ → สร้างใหม่

## Flow ผู้บริจาค (เพื่อนๆ ในรุ่น)

1. เข้าหน้าหลัก → คลิกแคมเปญที่ต้องการ
2. กรอก amount → QR PromptPay อัปเดต amount อัตโนมัติ
3. สแกน QR ด้วยแอปธนาคาร โอนเงิน
4. กลับมาที่หน้า → เลือกไฟล์รูปสลิป
5. ระบบจะลอง OCR อ่านยอดเงินมาเติมให้ (ถ้าอ่านได้)
6. กรอกชื่อ + ห้อง (A/B) + ข้อความ → กดบันทึก
7. รายการขึ้นในสถานะ "รอตรวจสอบ"

## Flow admin

1. login → Dashboard เห็นจำนวนรอตรวจ
2. ไปหน้า "รายการบริจาค" → คลิกรูปสลิปดูใหญ่
3. ตรวจกับยอดจริงที่เข้าบัญชี → กด "ยืนยัน" → ยอดถูกรวมในยอดรวม
4. ถ้าผิด → กด "ปฏิเสธ" (รายการยังโชว์ในรายชื่อด้วยสถานะ rejected แต่ไม่นับเงิน)

## ความปลอดภัยที่ใส่ไว้

- PDO prepared statements (กัน SQL injection)
- htmlspecialchars output ทุก field
- CSRF token ทุก POST
- session secure + httponly cookie
- บังคับ HTTPS (ปรับใน config)
- Upload: ตรวจ MIME ด้วย finfo, rename เป็น random hash, จำกัดขนาด, block PHP exec ใน `/uploads/`
- Rate-limit submit (5 ครั้ง / 5 นาที / IP)
- Login throttle (5 ครั้ง → lock 60 วินาที)
- password_hash + password_verify (bcrypt)
- ลบไฟล์ `setup_admin.php` หลังใช้

## หมายเหตุเรื่อง OCR สลิป

- ใช้ Tesseract.js ฝั่ง browser — ครั้งแรกโหลด ~5 MB (cache แล้วครั้งต่อไปเร็ว)
- ความแม่นยำ ~80% สำหรับสลิปไทยมาตรฐาน
- **ถือเป็น auto-fill เท่านั้น** — admin ยังต้อง verify จากรูปทุกครั้ง
- ถ้าอ่านไม่ได้ ผู้บริจาคกรอกเองได้

## ทดสอบ PromptPay QR

ลองสแกน QR ที่เว็บสร้างด้วยแอปธนาคารใดก็ได้ ต้องเห็น:
- ปลายทาง = ชื่อบัญชีที่ผูก PromptPay ID
- ยอดเงิน = ยอดที่กรอกในช่อง (ถ้ากรอก)

ถ้าไม่ขึ้นชื่อหรือเงินผิด — ตรวจ `promptpay_id` และ `promptpay_type` ในหน้าตั้งค่า
