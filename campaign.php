<?php
require __DIR__ . '/includes/bootstrap.php';
$base_url = '';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') redirect('index.php');

$c = db_one('SELECT * FROM campaigns WHERE slug = ?', [$slug]);
if (!$c) {
    http_response_code(404);
    $page_title = 'ไม่พบแคมเปญ';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty">ไม่พบแคมเปญที่ระบุ — <a href="index.php">กลับหน้าหลัก</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = $c['deceased_name'] . ' — ' . setting('site_title');

$total  = campaign_total_verified($c['id']);
$donors = campaign_count_verified($c['id']);

$rows = db_all(
    'SELECT id, donor_name, room, amount, message, status,
            COALESCE(transferred_at, created_at) AS at_time
       FROM donations
      WHERE campaign_id = ?
   ORDER BY at_time DESC, id DESC',
    [$c['id']]
);

$gallery = db_all(
    'SELECT * FROM campaign_images WHERE campaign_id = ? ORDER BY sort_order, id',
    [$c['id']]
);

$expenses = db_all(
    'SELECT * FROM expenses WHERE campaign_id = ? ORDER BY COALESCE(spent_at, created_at) DESC, id DESC',
    [$c['id']]
);
$expense_total = 0;
foreach ($expenses as $ex) $expense_total += (float)$ex['amount'];
$net_total = $total - $expense_total;

// เลือก mode ของ QR: 'promptpay' = generate ฝั่ง browser, 'image' = รูปที่ admin อัป, 'none' = ไม่มี
$pp_id    = trim(setting('promptpay_id'));
$pp_type  = setting('promptpay_type');
$qr_image = trim(setting('qr_image'));
if ($pp_id !== '') {
    $qr_mode = 'promptpay';
} elseif ($qr_image !== '' && is_file(__DIR__ . '/' . $qr_image)) {
    $qr_mode = 'image';
} else {
    $qr_mode = 'none';
}

require __DIR__ . '/includes/header.php';
?>
<a class="back-link" href="index.php">← กลับหน้าหลัก</a>

<article class="campaign">
  <header class="campaign-header">
    <?php if (!empty($c['hero_image'])): ?>
      <img class="campaign-hero" src="<?= e($c['hero_image']) ?>" alt="">
    <?php endif; ?>
    <h1><?= e($c['deceased_name']) ?></h1>
    <?php if (!empty($c['relation'])): ?>
      <p class="campaign-relation"><?= e($c['relation']) ?></p>
    <?php endif; ?>
    <?php if (!empty($c['eulogy'])): ?>
      <div class="eulogy"><?= nl2br(e($c['eulogy'])) ?></div>
    <?php endif; ?>
    <div class="campaign-stats stats-3">
      <div>
        <span class="big">฿<?= thb($total) ?></span>
        <span class="muted">ยอดบริจาคทั้งหมด</span>
        <span class="tiny muted"><?= (int)$donors ?> ผู้ร่วมทำบุญ</span>
      </div>
      <div>
        <span class="big">฿<?= thb($expense_total) ?></span>
        <span class="muted">ค่าใช้จ่าย</span>
        <span class="tiny muted"><?= count($expenses) ?> รายการ</span>
      </div>
      <div class="stat-net">
        <span class="big">฿<?= thb($net_total) ?></span>
        <span class="muted">ยอดปิดซอง</span>
        <span class="tiny muted">ส่งมอบให้ครอบครัว</span>
      </div>
    </div>
  </header>

  <?php if ($gallery): ?>
    <section class="gallery">
      <?php foreach ($gallery as $img): ?>
        <a class="gallery-cell" href="<?= e($img['path']) ?>" target="_blank" rel="noopener">
          <img src="<?= e($img['path']) ?>" alt="" loading="lazy">
        </a>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if ($c['status'] === 'closed'): ?>
    <div class="notice">แคมเปญนี้ปิดรับแล้ว ขอบคุณทุกน้ำใจครับ/ค่ะ</div>
  <?php else: ?>
    <section class="donate-section">
      <h2>ร่วมทำบุญ</h2>
      <div class="donate-grid<?= $qr_mode === 'none' ? ' no-qr' : '' ?>">
        <?php if ($qr_mode === 'promptpay'): ?>
          <div class="qr-box" id="qrBox" data-pp-id="<?= e($pp_id) ?>" data-pp-type="<?= e($pp_type) ?>">
            <div id="qrcode" aria-label="PromptPay QR"></div>
            <p class="qr-amount muted">ยอด: <span id="qrAmountLabel">ไม่ระบุ</span></p>
            <p class="muted small">สแกนด้วยแอปธนาคารใดก็ได้</p>
            <button type="button" class="btn btn-sm" id="downloadQR">⬇ บันทึก QR ลงเครื่อง</button>
          </div>
        <?php elseif ($qr_mode === 'image'): ?>
          <div class="qr-box">
            <img src="<?= e($qr_image) ?>" alt="QR code" class="qr-image">
            <p class="muted small">สแกน QR แล้วใส่ยอดเงินในแอปเอง</p>
            <a class="btn btn-sm" href="<?= e($qr_image) ?>" download="qr-code.png">⬇ บันทึก QR ลงเครื่อง</a>
          </div>
        <?php endif; ?>
        <div class="bank-box">
          <h3>หรือโอนตามเลขบัญชี</h3>
          <dl class="bank-info">
            <dt>ธนาคาร</dt><dd><?= e(setting('bank_name')) ?></dd>
            <dt>เลขบัญชี</dt>
            <dd>
              <span id="bankNo"><?= e(setting('bank_account_no')) ?></span>
              <button type="button" class="copy-btn" data-copy-target="bankNo">คัดลอก</button>
            </dd>
            <dt>ชื่อบัญชี</dt><dd><?= e(setting('bank_account_name')) ?></dd>
          </dl>
        </div>
      </div>

      <form id="donateForm" class="donate-form" method="post" action="submit.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">

        <h3>แจ้งการโอน</h3>

        <div class="row">
          <label>
            <span>ชื่อเล่น <em>*</em></span>
            <input type="text" name="donor_name" required maxlength="160">
          </label>
          <div class="room-field">
            <span class="room-label">ห้อง <em>*</em></span>
            <div class="room-pills">
              <label class="room-pill">
                <input type="radio" name="room" value="A" required>
                <span>ห้อง A</span>
              </label>
              <label class="room-pill">
                <input type="radio" name="room" value="B" required>
                <span>ห้อง B</span>
              </label>
            </div>
          </div>
        </div>

        <div class="row">
          <label class="amount-field">
            <span>จำนวนเงิน (บาท) <em>*</em></span>
            <input type="number" name="amount" id="amount" required min="1" step="0.01" inputmode="decimal">
          </label>
          <label class="slip-field">
            <span>รูปสลิป <em>*</em></span>
            <input type="file" name="slip" id="slip" accept="image/*" required>
            <small class="muted">(ระบบจะดึงยอด + เวลา ให้อัตโนมัติ ถ้าไม่ถูกต้องให้แก้ไข)</small>
          </label>
        </div>

        <label>
          <span>เวลาที่โอน <em>*</em></span>
          <input type="datetime-local" name="transferred_at" id="transferredAt"
                 value="<?= e(date('Y-m-d\TH:i')) ?>" required>
        </label>

        <button type="submit" class="btn btn-primary">บันทึกการทำบุญ</button>
        <p class="small muted">หลังกดส่ง รายการจะขึ้นในสถานะ "รอตรวจสอบ" — admin จะตรวจกับรูปสลิปและเปลี่ยนเป็น "ยืนยันแล้ว" ภายหลัง</p>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($expenses): ?>
    <section class="expenses">
      <h2>รายการค่าใช้จ่าย (<?= count($expenses) ?> รายการ)</h2>
      <table class="exp-table">
        <thead>
          <tr><th>วันที่</th><th>รายการ</th><th>รูป</th><th class="num">จำนวน</th></tr>
        </thead>
        <tbody>
          <?php foreach ($expenses as $ex): ?>
            <tr>
              <td class="muted"><?= e($ex['spent_at'] ? thai_datetime($ex['spent_at']) : '—') ?></td>
              <td><?= e($ex['description']) ?></td>
              <td>
                <?php if (!empty($ex['image_path'])): ?>
                  <a class="exp-thumb" href="<?= e($ex['image_path']) ?>" target="_blank" rel="noopener" title="คลิกดูเต็ม">
                    <img src="<?= e($ex['image_path']) ?>" alt="รูปประกอบ" loading="lazy">
                  </a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="num">฿<?= thb($ex['amount']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th colspan="3" class="num">รวมค่าใช้จ่าย</th><th class="num">฿<?= thb($expense_total) ?></th></tr>
        </tfoot>
      </table>
    </section>
  <?php endif; ?>

  <section class="donors">
    <h2>รายชื่อผู้ร่วมทำบุญ (<?= count($rows) ?> รายการ)</h2>
    <?php if (!$rows): ?>
      <div class="empty">ยังไม่มีผู้ร่วมทำบุญ — เป็นคนแรกได้เลย</div>
    <?php else: ?>
      <table class="donors-table">
        <thead>
          <tr>
            <th>ชื่อ</th>
            <th>ห้อง</th>
            <th class="num">จำนวน</th>
            <th>เวลา</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="status-<?= e($r['status']) ?>">
              <td>
                <?= e($r['donor_name']) ?>
                <?php if (!empty($r['message'])): ?>
                  <div class="donor-msg">"<?= e($r['message']) ?>"</div>
                <?php endif; ?>
              </td>
              <td><?= e($r['room']) ?></td>
              <td class="num">฿<?= thb($r['amount']) ?></td>
              <td class="muted"><?= e(thai_datetime($r['at_time'])) ?></td>
              <td>
                <?php if ($r['status'] === 'verified'): ?>
                  <span class="badge badge-ok">ยืนยันแล้ว</span>
                <?php elseif ($r['status'] === 'rejected'): ?>
                  <span class="badge badge-no">ปฏิเสธ</span>
                <?php else: ?>
                  <span class="badge badge-wait">รอตรวจสอบ</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</article>

<script>
window.LG = {
  qrMode: <?= json_encode($qr_mode) ?>,
  ppId:   <?= json_encode($pp_id) ?>,
  ppType: <?= json_encode($pp_type) ?>
};
</script>
<?php if ($qr_mode === 'promptpay'): ?>
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js"></script>
<script src="<?= e(asset('assets/js/donate.js')) ?>"></script>

<?php if (flash('thanks')): ?>
<div id="thanksModal" class="thanks-overlay">
  <div class="thanks-card" role="dialog" aria-modal="true" aria-labelledby="thanksTitle">
    <button type="button" class="thanks-close" aria-label="ปิด">×</button>
    <div class="thanks-mark">✦</div>
    <h2 id="thanksTitle">ขอขอบคุณแทน<br><?= e($c['deceased_name']) ?><br>ด้วยนะคะ</h2>
    <p class="muted">รายการของคุณอยู่ในสถานะ "รอตรวจสอบ" — admin จะตรวจกับรูปสลิปและเปลี่ยนเป็น "ยืนยันแล้ว" ภายหลัง</p>
    <button type="button" class="btn btn-primary thanks-dismiss">ปิด</button>
  </div>
</div>
<script>
(function(){
  var m = document.getElementById('thanksModal');
  if (!m) return;
  function close(){
    m.classList.add('closing');
    setTimeout(function(){ if (m.parentNode) m.parentNode.removeChild(m); }, 200);
    document.body.style.overflow = '';
  }
  m.addEventListener('click', function(e){
    if (e.target === m
      || e.target.classList.contains('thanks-close')
      || e.target.classList.contains('thanks-dismiss')) close();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && document.body.contains(m)) close();
  });
  document.body.style.overflow = 'hidden';
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
