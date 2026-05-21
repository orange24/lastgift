<?php
// dashboard page ถูกรวมเข้ากับ campaigns.php แล้ว — redirect ไปที่นั่นเสมอ
require __DIR__ . '/../includes/bootstrap.php';
admin_required();
redirect('campaigns.php');
