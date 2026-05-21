<?php
require __DIR__ . '/../includes/bootstrap.php';
$base_url = '../';

if (admin_id()) redirect('campaigns.php');

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $p =       isset($_POST['password']) ? $_POST['password'] : '';

    // brute-force throttle เบาๆ ผ่าน session
    $tries = isset($_SESSION['login_tries']) ? (int)$_SESSION['login_tries'] : 0;
    $lockUntil = isset($_SESSION['login_lock']) ? (int)$_SESSION['login_lock'] : 0;
    if ($lockUntil > time()) {
        $err = 'ลองมากเกินไป กรุณารอ ' . ($lockUntil - time()) . ' วินาที';
    } elseif (admin_login($u, $p)) {
        unset($_SESSION['login_tries'], $_SESSION['login_lock']);
        $back = isset($_GET['back']) ? $_GET['back'] : 'campaigns.php';
        if (!preg_match('#^[a-zA-Z0-9_\-/\.\?\=\&]+$#', $back)) $back = 'campaigns.php';
        redirect($back);
    } else {
        $tries++;
        $_SESSION['login_tries'] = $tries;
        if ($tries >= 5) {
            $_SESSION['login_lock'] = time() + 60;
            $_SESSION['login_tries'] = 0;
        }
        $err = 'username/password ไม่ถูก';
    }
}
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login · <?= e(setting('site_title')) ?></title>
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css', '../')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin">
<main class="login-wrap">
  <form method="post" class="login-card">
    <h1>Admin Login</h1>
    <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label><span>Username</span><input name="username" required autofocus></label>
    <label><span>Password</span><input name="password" type="password" required></label>
    <button class="btn btn-primary" type="submit">เข้าสู่ระบบ</button>
    <p class="small muted"><a href="../index.php">← กลับหน้าสาธารณะ</a></p>
  </form>
</main>
</body>
</html>
