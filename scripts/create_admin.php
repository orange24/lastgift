<?php
// Usage: php scripts/create_admin.php <username> <display_name>
// password อ่านจาก stdin (silent ถ้าเป็น tty)

if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php <username> <display_name>\n");
    exit(1);
}
$username = trim($argv[1]);
$display  = trim($argv[2]);

fwrite(STDOUT, "Password (≥ 8 ตัว): ");
// disable echo
system('stty -echo 2>/dev/null');
$pass = trim(fgets(STDIN));
system('stty echo 2>/dev/null');
fwrite(STDOUT, "\n");
if (strlen($pass) < 8) { exit("password ต้อง ≥ 8 ตัว\n"); }

define('LG_BOOTSTRAPPED', true);
$GLOBALS['CONFIG'] = require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$exists = db_one('SELECT id FROM admins WHERE username = ?', [$username]);
if ($exists) { exit("username '$username' มีอยู่แล้ว\n"); }

db_run('INSERT INTO admins (username, password_hash, display_name) VALUES (?, ?, ?)',
    [$username, password_hash($pass, PASSWORD_DEFAULT), $display]);
echo "✓ created admin: $username (id=", db_insert_id(), ")\n";
echo "  ลบไฟล์ setup_admin.php ทิ้งได้เลย\n";
