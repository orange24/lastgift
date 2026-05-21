<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_check();

$campaign_id = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;
$campaign = db_one('SELECT * FROM campaigns WHERE id = ?', [$campaign_id]);
if (!$campaign) {
    flash('err', 'ไม่พบแคมเปญ');
    redirect('index.php');
}
if ($campaign['status'] !== 'active') {
    flash('err', 'แคมเปญนี้ปิดรับแล้ว');
    redirect('campaign.php?slug=' . urlencode($campaign['slug']));
}

// rate limit: นับ submission ต่อ ip_hash ใน 5 นาที
$ip_hash = client_ip_hash();
$rl = db_one(
    'SELECT COUNT(*) AS c FROM donations
      WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 5 MINUTE)',
    [$ip_hash]
);
if ($rl && (int)$rl['c'] >= 5) {
    flash('err', 'ส่งบ่อยเกินไป กรุณารอสักครู่');
    redirect('campaign.php?slug=' . urlencode($campaign['slug']));
}

$donor_name = trim(isset($_POST['donor_name']) ? $_POST['donor_name'] : '');
$room       = isset($_POST['room']) ? $_POST['room'] : '';
$amount     = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$message    = trim(isset($_POST['message']) ? $_POST['message'] : '');
$tat_raw    = trim(isset($_POST['transferred_at']) ? $_POST['transferred_at'] : '');

$errs = [];
if ($donor_name === '' || mb_strlen($donor_name) > 160)         $errs[] = 'กรุณากรอกชื่อให้ถูกต้อง';
if (!in_array($room, ['A', 'B'], true))                          $errs[] = 'กรุณาเลือกห้อง';
if ($amount <= 0 || $amount > 9999999)                           $errs[] = 'จำนวนเงินไม่ถูกต้อง';
if (mb_strlen($message) > 500)                                   $errs[] = 'ข้อความยาวเกิน';

// transferred_at — แปลงค่าจาก datetime-local "YYYY-MM-DDTHH:MM" เป็น MySQL DATETIME
$transferred_at = null;
if ($tat_raw !== '') {
    $ts = strtotime(str_replace('T', ' ', $tat_raw));
    if ($ts === false || $ts > time() + 600 || $ts < strtotime('-5 years')) {
        $errs[] = 'เวลาที่โอนไม่ถูกต้อง';
    } else {
        $transferred_at = date('Y-m-d H:i:s', $ts);
    }
} else {
    $errs[] = 'กรุณากรอกเวลาที่โอน';
}

$up = handle_upload('slip', $GLOBALS['CONFIG']['upload_dir'], $GLOBALS['CONFIG']['upload_url']);
if ($up === null) {
    $errs[] = 'กรุณาแนบรูปสลิป';
} elseif (isset($up['error'])) {
    $errs[] = $up['error'];
}

if ($errs) {
    flash('err', implode(' / ', $errs));
    redirect('campaign.php?slug=' . urlencode($campaign['slug']));
}

db_run(
    'INSERT INTO donations
        (campaign_id, donor_name, room, amount, transferred_at, slip_path, message, status, ip_hash)
     VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?)',
    [$campaign_id, $donor_name, $room, $amount, $transferred_at, $up['path'], $message, $ip_hash]
);

flash('thanks', '1');
redirect('campaign.php?slug=' . urlencode($campaign['slug']) . '#donors');
