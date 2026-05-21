<?php
require __DIR__ . '/../includes/bootstrap.php';
$base_url = '../';
$page_title = isset($page_title) ? $page_title : 'Admin';
admin_required();
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($page_title) ?> · Admin · <?= e(setting('site_title')) ?></title>
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css', '../')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin">
<header class="admin-nav">
  <div class="container">
    <div class="admin-brand">Admin · <?= e(setting('site_title')) ?></div>
    <nav>
      <a href="campaigns.php">แคมเปญ</a>
      <a href="donations.php">รายการบริจาค</a>
      <a href="settings.php">ตั้งค่า</a>
      <a href="line.php">LINE</a>
      <a href="logout.php" class="logout">ออก (<?= e(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '') ?>)</a>
    </nav>
  </div>
</header>
<main class="container admin-main">
<?php
$_ok  = flash('ok');
$_err = flash('err');
if ($_ok):  ?><div class="flash flash-ok"><?= e($_ok) ?></div><?php endif;
if ($_err): ?><div class="flash flash-err"><?= e($_err) ?></div><?php endif;
