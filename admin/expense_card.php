<?php
$page_title = 'การ์ดค่าใช้จ่าย PNG';
require __DIR__ . '/_layout.php';

$cid = isset($_GET['campaign']) ? (int)$_GET['campaign'] : 0;
$eid = isset($_GET['expense']) ? (int)$_GET['expense'] : 0;

$c = $cid > 0 ? db_one('SELECT * FROM campaigns WHERE id = ?', [$cid]) : null;
if (!$c) { flash('err', 'ไม่พบแคมเปญ'); redirect('campaigns.php'); }

// ถ้าไม่ระบุ expense_id → หยิบรายการล่าสุดของแคมเปญ
if ($eid > 0) {
    $ex = db_one('SELECT * FROM expenses WHERE id = ? AND campaign_id = ?', [$eid, $cid]);
} else {
    $ex = db_one(
        'SELECT * FROM expenses WHERE campaign_id = ?
         ORDER BY COALESCE(spent_at, created_at) DESC, id DESC LIMIT 1',
        [$cid]
    );
}
if (!$ex) {
    flash('err', 'แคมเปญนี้ยังไม่มีค่าใช้จ่าย');
    redirect('campaigns.php?action=edit&id=' . $cid . '#expenses');
}

$donation_total = campaign_total_verified($cid);
$expense_total  = campaign_expense_total($cid);
$net_total      = $donation_total - $expense_total;
$exp_when       = $ex['spent_at'] ? thai_datetime($ex['spent_at']) : thai_datetime($ex['created_at']);

// list expense ทั้งหมดของ campaign นี้ — ให้ admin เลือก ถ้าอยากดูตัวอื่น
$all_expenses = db_all(
    'SELECT id, description, amount FROM expenses WHERE campaign_id = ?
     ORDER BY COALESCE(spent_at, created_at) DESC, id DESC',
    [$cid]
);
?>

<div class="page-head">
  <h1>การ์ดค่าใช้จ่าย</h1>
  <a class="btn btn-sm" href="campaigns.php">← กลับ</a>
</div>

<p class="muted">คลิก "ดาวน์โหลด PNG" → save รูปไว้ → เปิด LINE group แล้วส่งรูปนี้</p>

<?php if (count($all_expenses) > 1): ?>
  <form method="get" class="filter-bar">
    <input type="hidden" name="campaign" value="<?= $cid ?>">
    <label>เลือกค่าใช้จ่าย:</label>
    <select name="expense" onchange="this.form.submit()">
      <?php foreach ($all_expenses as $opt): ?>
        <option value="<?= (int)$opt['id'] ?>" <?= (int)$opt['id'] === (int)$ex['id'] ? 'selected' : '' ?>>
          <?= e($opt['description']) ?> · ฿<?= thb($opt['amount']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
<?php endif; ?>

<div id="cardWrap">
  <div id="card" class="lg-card">
    <div class="lg-card-header">
      <div class="lg-card-mark">✦  หักค่าใช้จ่ายใหม่</div>
      <div class="lg-card-event"><?= e($ex['description']) ?></div>
    </div>
    <div class="lg-card-body">
      <div class="lg-row-title">
        <?= e($c['deceased_name']) ?>
        <?php if (!empty($c['relation'])): ?>
          <span class="lg-row-title-sub"><?= e($c['relation']) ?></span>
        <?php endif; ?>
      </div>
      <div class="lg-sep"></div>
      <div class="lg-row lg-row-deduct">
        <span class="lg-row-label">หักไป</span>
        <span class="lg-row-deduct-val">฿<?= thb($ex['amount']) ?></span>
      </div>
      <div class="lg-sep"></div>
      <div class="lg-row">
        <span class="lg-row-label">ยอดบริจาคทั้งหมด</span>
        <span class="lg-row-val">฿<?= thb($donation_total) ?></span>
      </div>
      <div class="lg-row">
        <span class="lg-row-label">ค่าใช้จ่ายรวม</span>
        <span class="lg-row-val">฿<?= thb($expense_total) ?></span>
      </div>
      <div class="lg-sep"></div>
      <div class="lg-row lg-row-net">
        <span class="lg-row-label">ยอดปิดซอง</span>
        <span class="lg-row-net-val">฿<?= thb($net_total) ?></span>
      </div>
      <div class="lg-sep"></div>
      <div class="lg-row lg-row-sub">
        <span class="lg-row-label">วันที่ใช้จ่าย</span>
        <span class="lg-row-sub-val"><?= e($exp_when) ?></span>
      </div>
    </div>
    <div class="lg-card-footer">
      <?= e(setting('site_title')) ?>
    </div>
  </div>
</div>

<div class="card-actions">
  <button type="button" class="btn btn-primary" id="downloadBtn">⬇ ดาวน์โหลด PNG</button>
  <a class="btn" href="expense_card.php?campaign=<?= $cid ?>">🔄 รายการล่าสุด</a>
  <a class="btn" href="summary_card.php?campaign=<?= $cid ?>">การ์ดสรุปยอด</a>
  <a class="btn" href="campaigns.php">เสร็จ</a>
</div>

<style>
#cardWrap{padding:24px 0;display:flex;justify-content:center}
.lg-card{
  width:420px;background:#fff;border-radius:16px;overflow:hidden;
  box-shadow:0 12px 36px rgba(43,42,38,.18);
  font-family:"Sarabun",-apple-system,sans-serif;
}
.lg-card-header{background:#8a6a3a;color:#fff;padding:24px 24px 20px}
.lg-card-mark{font-size:13px;font-weight:600;color:#f7f5f1;letter-spacing:.5px;margin-bottom:8px}
.lg-card-event{font-size:22px;font-weight:700;line-height:1.3;color:#fff}
.lg-card-body{padding:20px 24px;background:#fff}
.lg-row-title{font-size:18px;font-weight:700;color:#2b2a26;line-height:1.4}
.lg-row-title-sub{display:block;font-size:13px;font-weight:400;color:#8e8b81;margin-top:2px}
.lg-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0}
.lg-row-label{font-size:14px;color:#8e8b81}
.lg-row-val{font-size:17px;font-weight:600;color:#2b2a26}
.lg-row-deduct{padding:14px 0}
.lg-row-deduct .lg-row-label{font-size:15px;color:#5d5b54}
.lg-row-deduct-val{font-size:28px;font-weight:800;color:#6d5128}
.lg-row-net{padding:10px 0}
.lg-row-net .lg-row-label{font-size:15px;color:#5d5b54}
.lg-row-net-val{font-size:26px;font-weight:800;color:#6d5128}
.lg-row-sub-val{font-size:13px;color:#5d5b54}
.lg-sep{height:1px;background:#e7e3da;margin:8px 0}
.lg-card-footer{
  padding:14px 24px;background:#faf8f3;text-align:center;
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
  html2canvas(card, {
    scale: 2,
    backgroundColor: null,
    useCORS: true,
  }).then(function (canvas) {
    // clip มุมโค้ง — html2canvas ไม่ respect border-radius
    var w = canvas.width, h = canvas.height, r = 32;
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
    a.download = 'ค่าใช้จ่าย_' + slug + '_' + Date.now() + '.png';
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
