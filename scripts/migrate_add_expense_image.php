<?php
// One-shot migration: เพิ่ม column image_path ลงตาราง expenses
// เปิดผ่าน browser ครั้งเดียวหลังอัปไฟล์ใหม่ — รัน idempotent (ปลอดภัยรันซ้ำ)
// หลัง migrate สำเร็จแล้ว แนะนำลบไฟล์นี้ทิ้ง

require __DIR__ . '/../includes/bootstrap.php';

// อนุญาตเฉพาะ admin ที่ login แล้ว
admin_required();

header('Content-Type: text/plain; charset=utf-8');

echo "== Migration: add expenses.image_path ==\n";

$dbName = $GLOBALS['CONFIG']['db']['name'];

$col = db_one(
    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "expenses" AND COLUMN_NAME = "image_path"',
    [$dbName]
);

if ($col) {
    echo "✓ column expenses.image_path มีอยู่แล้ว — ไม่ต้อง migrate\n";
} else {
    try {
        db_run('ALTER TABLE expenses ADD COLUMN image_path VARCHAR(255) NULL AFTER amount');
        echo "✓ เพิ่ม column expenses.image_path สำเร็จ\n";
    } catch (Exception $e) {
        echo "✗ ALTER TABLE ล้มเหลว: " . $e->getMessage() . "\n";
        exit;
    }
}

// สร้างโฟลเดอร์ upload + .htaccess กัน PHP exec
$dir = $GLOBALS['CONFIG']['expense_dir'];
if (!is_dir($dir)) {
    if (@mkdir($dir, 0775, true)) {
        echo "✓ สร้างโฟลเดอร์ " . $dir . "\n";
    } else {
        echo "✗ สร้างโฟลเดอร์ไม่ได้: " . $dir . " — chmod 775 ที่ uploads/ ก่อน\n";
    }
} else {
    echo "✓ โฟลเดอร์ " . $dir . " มีอยู่แล้ว\n";
}

$ht = rtrim($dir, '/') . '/.htaccess';
if (!file_exists($ht) && is_dir($dir)) {
    @file_put_contents($ht, "php_flag engine off\n<FilesMatch \"\\.(php|phtml|phps|cgi|pl|py)$\">\nRequire all denied\n</FilesMatch>\n");
    echo "✓ สร้าง .htaccess กัน PHP exec\n";
}

echo "\nเสร็จแล้ว — กลับไปหน้า admin แล้วลองเพิ่มค่าใช้จ่ายพร้อมรูปได้เลย\n";
echo "*** ลบไฟล์นี้ทิ้ง (scripts/migrate_add_expense_image.php) เพื่อความปลอดภัย ***\n";
