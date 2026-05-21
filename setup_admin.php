<?php
// One-time setup: สร้าง super admin คนแรก
// 1) เปิดหน้านี้ใน browser
// 2) กรอก username + password ที่ต้องการ
// 3) เมื่อสร้างสำเร็จ ระบบจะลบสิทธิ์การใช้หน้านี้ (ลบ/เปลี่ยนชื่อไฟล์)
//
// ถ้ามี admin อยู่แล้วในตาราง หน้านี้จะไม่ทำงาน เพื่อกันเปิดทิ้งไว้

require __DIR__ . '/includes/bootstrap.php';

$existing = db_one('SELECT COUNT(*) AS c FROM admins');
if ($existing && (int)$existing['c'] > 0) {
    http_response_code(403);
    exit('มี admin อยู่แล้ว — กรุณาลบไฟล์ setup_admin.php ทันที');
}

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $p =       isset($_POST['password']) ? $_POST['password'] : '';
    $n = trim(isset($_POST['display_name']) ? $_POST['display_name'] : $u);
    if ($u === '' || strlen($p) < 8) {
        $msg = 'username + password (อย่างน้อย 8 ตัว)';
    } else {
        db_run('INSERT INTO admins (username, password_hash, display_name) VALUES (?, ?, ?)',
            [$u, password_hash($p, PASSWORD_DEFAULT), $n]);
        echo '<!doctype html><meta charset="utf-8"><h2>สร้าง admin สำเร็จ ✓</h2>';
        echo '<p><strong>กรุณาลบไฟล์ <code>setup_admin.php</code> ทันที</strong> เพื่อความปลอดภัย</p>';
        echo '<p><a href="admin/login.php">→ เข้าหน้า admin</a></p>';
        exit;
    }
}
?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><title>Setup admin · <?= e(setting('site_title')) ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>"></head>
<body class="admin">
<main class="login-wrap">
  <form class="login-card" method="post">
    <h1>สร้าง Admin คนแรก</h1>
    <?php if ($msg): ?><div class="flash flash-err"><?= e($msg) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label><span>Username</span><input name="username" required></label>
    <label><span>ชื่อแสดง</span><input name="display_name" required></label>
    <label><span>Password (≥ 8 ตัว)</span><input name="password" type="password" minlength="8" required></label>
    <button class="btn btn-primary" type="submit">สร้าง</button>
    <p class="small muted">หลังสร้างเสร็จ <strong>ลบไฟล์นี้ออก</strong> ทันที</p>
  </form>
</main></body></html>
