<?php
if (!defined('LG_BOOTSTRAPPED')) { require __DIR__ . '/bootstrap.php'; }
$page_title = isset($page_title) ? $page_title : setting('site_title');
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($page_title) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(($base_url ? $base_url : '') . 'favicon.svg') ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css', $base_url ? $base_url : '')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a class="brand" href="<?= e($base_url ? $base_url : '') ?>index.php">
      <span class="brand-mark">✦</span>
      <span class="brand-text">
        <strong><?= e(setting('site_title')) ?></strong>
        <small><?= e(setting('site_subtitle')) ?></small>
      </span>
    </a>
  </div>
</header>
<main class="container">
<?php
$_flash_ok  = flash('ok');
$_flash_err = flash('err');
if ($_flash_ok):  ?><div class="flash flash-ok"><?= e($_flash_ok) ?></div><?php endif;
if ($_flash_err): ?><div class="flash flash-err"><?= e($_flash_err) ?></div><?php endif;
?>
