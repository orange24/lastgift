<?php
$page_title = 'การ์ดผู้บริจาค PNG';
require __DIR__ . '/_layout.php';

$cid = isset($_GET['campaign']) ? (int)$_GET['campaign'] : 0;
$c = $cid > 0 ? db_one('SELECT * FROM campaigns WHERE id = ?', [$cid]) : null;
if (!$c) { flash('err', 'ไม่พบแคมเปญ'); redirect('campaigns.php'); }

$donation_total = campaign_total_verified($cid);
$expense_total  = campaign_expense_total($cid);
$net_total      = $donation_total - $expense_total;
$donor_count    = campaign_count_verified($cid);
$now_label      = thai_datetime(time());
$title          = !empty($c['relation'])
    ? $c['deceased_name'] . ' — ' . $c['relation']
    : $c['deceased_name'];
?>

<div class="page-head">
  <h1>การ์ดผู้บริจาคใหม่</h1>
  <a class="btn btn-sm" href="campaigns.php">← กลับ</a>
</div>

<p class="muted">คลิก "ดาวน์โหลด PNG" → save รูป → เปิด LINE group แล้วส่งรูปนี้</p>

<div id="cardWrap">
  <div id="card" class="lg-card">
    <div class="lg-card-header">
      <div class="lg-card-mark">✦  แจ้งเตือนการบริจาค</div>
      <div class="lg-card-event">มีผู้ร่วมทำบุญใหม่</div>
    </div>
    <div class="lg-card-body">
      <div class="lg-row-title"><?= e($title) ?></div>
      <div class="lg-sep"></div>
      <div class="lg-row lg-row-amount">
        <span class="lg-row-label">ยอดร่วมทำบุญ<br><small style="font-weight:400">(หักค่าใช้จ่ายแล้ว)</small></span>
        <span class="lg-row-amount-val">฿<?= thb($net_total) ?></span>
      </div>
      <div class="lg-row">
        <span class="lg-row-label">จำนวนผู้บริจาค</span>
        <span class="lg-row-val"><?= (int)$donor_count ?> ราย</span>
      </div>
      <div class="lg-row">
        <span class="lg-row-label">ณ เวลา</span>
        <span class="lg-row-sub-val"><?= e($now_label) ?></span>
      </div>
    </div>
    <div class="lg-card-footer">
      <?= e(setting('site_title')) ?>
    </div>
  </div>
</div>

<div class="card-actions">
  <button type="button" class="btn btn-primary" id="downloadBtn">⬇ ดาวน์โหลด PNG</button>
  <a class="btn" href="summary_card.php?campaign=<?= $cid ?>">การ์ดสรุปยอด</a>
  <a class="btn" href="expense_card.php?campaign=<?= $cid ?>">การ์ดค่าใช้จ่าย</a>
  <a class="btn" href="campaigns.php">เสร็จ</a>
</div>

<style>
#cardWrap{padding:24px 0;display:flex;justify-content:center}
.lg-card{
  width:360px;background:#fff;border-radius:16px;overflow:hidden;
  box-shadow:0 12px 36px rgba(43,42,38,.18);
  font-family:"Sarabun",-apple-system,sans-serif;
}
.lg-card-header{background:#8a6a3a;color:#fff;padding:28px 28px 32px}
.lg-card-mark{font-size:14px;font-weight:600;color:#f7f5f1;letter-spacing:.3px;margin-bottom:10px}
.lg-card-event{font-size:32px;font-weight:800;line-height:1.25;color:#fff}
.lg-card-body{padding:24px 28px 28px;background:#fff}
.lg-row-title{font-size:20px;font-weight:700;color:#2b2a26;line-height:1.4;margin-bottom:6px}
.lg-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;gap:12px}
.lg-row-label{font-size:14px;color:#8e8b81;flex-shrink:0;line-height:1.4}
.lg-row-val{font-size:17px;font-weight:600;color:#2b2a26;text-align:right}
.lg-row-amount{padding:14px 0}
.lg-row-amount .lg-row-label{font-size:14px;color:#8e8b81}
.lg-row-amount-val{font-size:28px;font-weight:800;color:#6d5128;text-align:right;line-height:1.2}
.lg-row-sub-val{font-size:13px;color:#5d5b54;text-align:right;line-height:1.4}
.lg-sep{height:1px;background:#e7e3da;margin:10px 0}
.lg-card-footer{
  padding:16px 28px;background:#faf8f3;text-align:center;
  font-size:12px;color:#8e8b81;border-top:1px solid #e7e3da;
}
.card-actions{display:flex;gap:10px;justify-content:center;margin:8px 0 32px;flex-wrap:wrap}
</style>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
document.getElementById('downloadBtn').addEventListener('click', function () {
  var btn = this;
  var card = document.getElementById('card');
  btn.disabled = true;
  btn.textContent = 'กำลังสร้างรูป...';
  html2canvas(card, { scale: 2, backgroundColor: null, useCORS: true }).then(function (canvas) {
    // clip มุมโค้ง — html2canvas ไม่ respect border-radius เลยต้อง mask ด้วยตัวเอง
    var w = canvas.width, h = canvas.height, r = 32; // 16px × scale 2
    var out = document.createElement('canvas');
    out.width = w; out.height = h;
    var ctx = out.getContext('2d');
    ctx.beginPath();
    ctx.moveTo(r, 0);
    ctx.lineTo(w - r, 0);  ctx.quadraticCurveTo(w, 0, w, r);
    ctx.lineTo(w, h - r);  ctx.quadraticCurveTo(w, h, w - r, h);
    ctx.lineTo(r, h);      ctx.quadraticCurveTo(0, h, 0, h - r);
    ctx.lineTo(0, r);      ctx.quadraticCurveTo(0, 0, r, 0);
    ctx.closePath();
    ctx.clip();
    ctx.drawImage(canvas, 0, 0);

    var url = out.toDataURL('image/png');
    var a = document.createElement('a');
    var slug = <?= json_encode($c['slug']) ?>.replace(/[^a-zA-Z0-9_\-]/g, '_');
    a.href = url;
    a.download = 'ผู้บริจาคใหม่_' + slug + '_' + Date.now() + '.png';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    btn.disabled = false;
    btn.textContent = '⬇ ดาวน์โหลด PNG';
  }).catch(function (err) {
    console.error(err);
    alert('สร้างรูปไม่สำเร็จ: ' + err.message);
    btn.disabled = false;
    btn.textContent = '⬇ ดาวน์โหลด PNG';
  });
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>
