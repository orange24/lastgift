<?php
// One-shot helper — รับ password เป็น argv ตรงๆ
// Usage: php scripts/create_admin_oneshot.php <username> <password> <display_name>
// ใช้แค่ครั้งเดียวแล้วลบไฟล์ทิ้ง

if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/create_admin_oneshot.php <username> <password> <display_name>\n");
    fwrite(STDERR, "ตัวอย่าง: php scripts/create_admin_oneshot.php watcha MyPass1234 \"Watcharaster\"\n");
    exit(1);
}
$username = trim($argv[1]);
$pass     = $argv[2];
$display  = trim($argv[3]);

if (strlen($pass) < 8) { exit("password ต้อง ≥ 8 ตัว (มีแค่ " . strlen($pass) . ")\n"); }

define('LG_BOOTSTRAPPED', true);
$GLOBALS['CONFIG'] = require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$exists = db_one('SELECT id FROM admins WHERE username = ?', [$username]);
if ($exists) {
    echo "พบ '$username' อยู่แล้ว — อัปเดต password ใหม่แทน\n";
    db_run('UPDATE admins SET password_hash = ?, display_name = ? WHERE username = ?',
        [password_hash($pass, PASSWORD_DEFAULT), $display, $username]);
    echo "✓ updated\n";
} else {
    db_run('INSERT INTO admins (username, password_hash, display_name) VALUES (?, ?, ?)',
        [$username, password_hash($pass, PASSWORD_DEFAULT), $display]);
    echo "✓ created admin: $username (id=", db_insert_id(), ")\n";
}

// verify ทันทีว่า hash + verify match กัน
$row = db_one('SELECT password_hash FROM admins WHERE username = ?', [$username]);
if (password_verify($pass, $row['password_hash'])) {
    echo "✓ password_verify ผ่าน — login ได้แน่นอน\n";
} else {
    echo "✗ password_verify FAIL — มี bug บางอย่าง แจ้ง dev\n";
}
