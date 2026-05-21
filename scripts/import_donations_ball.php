<?php
// One-time import — รายชื่อผู้ร่วมทำบุญงาน บอล(ใต้) นายสรรค์ชัย เส้งขาว
// Run: php scripts/import_donations_ball.php
// ลบไฟล์นี้ได้หลังรันเสร็จ

if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }

define('LG_BOOTSTRAPPED', true);
$GLOBALS['CONFIG'] = require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$camp = db_one('SELECT id, deceased_name FROM campaigns ORDER BY created_at DESC LIMIT 1');
if (!$camp) { exit("ไม่มี campaign ใน DB\n"); }
$cid = (int)$camp['id'];
echo "campaign: #$cid {$camp['deceased_name']}\n";

$existing = db_one('SELECT COUNT(*) c FROM donations WHERE campaign_id = ?', [$cid]);
echo "existing donations: {$existing['c']}\n";

// ใครเป็น admin คนแรก → verified_by
$admin = db_one('SELECT id FROM admins ORDER BY id LIMIT 1');
$admin_id = $admin ? (int)$admin['id'] : null;

$donors = [
    // name, room, amount
    ['เอ้',              'B', 500],
    ['เจน',              'A', 500],
    ['เนย์',             'A', 500],
    ['เฟียต',            'B', 1000],
    ['ก้อย',             'A', 500],
    ['กิ่ง',              'B', 1000],
    ['เบียร์',           'A', 500],
    ['เหน่ง',            'B', 2000],
    ['ต้อง',             'B', 500],
    ['ตูน',              'B', 500],
    ['จอย & หนุ่ม',      'B', 500],
    ['นาเดียร์',         'B', 1000],
    ['โน้ต & ปาล์ม',     'A', 1000],
    ['อาร์ท',            'B', 500],
    ['ฝ้าย',             'A', 500],
    ['สุ่ย',             'A', 500],
    ['จูน',              'B', 500],
    ['หมี',              'B', 500],
    ['อ้อย',             'B', 500],
    ['อ้อย',             'A', 1000],
    ['โอ๋',              'B', 1000],
    ['ปิ๊ก',             'A', 500],
    ['กบ',               'B', 1000],
    ['แยม',              'B', 500],
    ['เหลิม',            'A', 300],
    ['วิทย์',            'B', 1000],
    ['จอย',              'A', 1000],
    ['ฝน',               'A', 500],
    ['ศิษ',              'A', 1000],
    ['เจี๊ยบ',           'B', 1000],
    ['แป้ง',             'A', 300],
    ['นุชเนตร',          'A', 500],
    ['เอส',              'A', 500],
    ['บู & นุ้ย',        'B', 2000],
    ['ก็อต',             'B', 1000],
    ['พี่เสก',           'B', 500],
    ['ชาร์ป',            'A', 700],
];

$total = array_sum(array_column($donors, 2));
echo "จะ insert " . count($donors) . " รายการ รวม ฿" . number_format($total, 2) . "\n";

db()->beginTransaction();
$inserted = 0;
foreach ($donors as $d) {
    list($name, $room, $amount) = $d;
    db_run(
        'INSERT INTO donations
            (campaign_id, donor_name, room, amount, status, created_at, verified_at, verified_by)
         VALUES (?, ?, ?, ?, "verified", NOW(), NOW(), ?)',
        [$cid, $name, $room, $amount, $admin_id]
    );
    $inserted++;
}
db()->commit();
echo "✓ inserted $inserted records\n";

$sum = db_one('SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM donations WHERE campaign_id = ? AND status = "verified"', [$cid]);
echo "campaign total: {$sum['c']} verified รายการ, ฿" . number_format($sum['s'], 2) . "\n";
