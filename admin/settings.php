<?php
$page_title = 'ตั้งค่า';
require __DIR__ . '/_layout.php';

$keys = [
    'site_title'        => 'ชื่อเว็บ',
    'site_subtitle'     => 'คำโปรย',
    'bank_name'         => 'ธนาคาร',
    'bank_account_no'   => 'เลขบัญชี',
    'bank_account_name' => 'ชื่อบัญชี',
    'promptpay_id'      => 'PromptPay ID (เบอร์ / เลข ปชช. / เลข e-wallet) — เว้นว่างถ้าไม่ใช้',
    'promptpay_type'    => 'ประเภท PromptPay (phone / nid / ewallet)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // ลบรูป QR ที่อัปไว้
    if (!empty($_POST['remove_qr'])) {
        $cur = setting('qr_image');
        if ($cur) {
            $abs = __DIR__ . '/../' . $cur;
            if (is_file($abs)) @unlink($abs);
        }
        setting_set('qr_image', '');
        flash('ok', 'ลบรูป QR แล้ว');
        redirect('settings.php');
    }

    foreach ($keys as $k => $label) {
        if (isset($_POST[$k])) setting_set($k, trim($_POST[$k]));
    }

    // upload รูป QR ใหม่ (optional)
    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = handle_upload('qr_image', $GLOBALS['CONFIG']['qr_dir'], $GLOBALS['CONFIG']['qr_url']);
        if (is_array($up) && isset($up['error'])) {
            flash('err', $up['error']);
            redirect('settings.php');
        }
        if ($up) {
            // ลบไฟล์เก่าก่อน
            $old = setting('qr_image');
            if ($old) {
                $abs = __DIR__ . '/../' . $old;
                if (is_file($abs)) @unlink($abs);
            }
            setting_set('qr_image', $up['path']);
        }
    }

    // change password
    if (!empty($_POST['new_password'])) {
        $newp = $_POST['new_password'];
        if (strlen($newp) < 8) {
            flash('err', 'password ต้องอย่างน้อย 8 ตัวอักษร');
            redirect('settings.php');
        }
        db_run('UPDATE admins SET password_hash = ? WHERE id = ?',
            [password_hash($newp, PASSWORD_DEFAULT), admin_id()]);
        flash('ok', 'บันทึก + เปลี่ยน password แล้ว');
    } else {
        flash('ok', 'บันทึกแล้ว');
    }
    redirect('settings.php');
}

$values = [];
foreach ($keys as $k => $_) $values[$k] = setting($k);
$qr_image = setting('qr_image');
?>
<h1>ตั้งค่า</h1>

<form method="post" class="adm-form" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <?php foreach ($keys as $k => $label): ?>
    <label>
      <span><?= e($label) ?></span>
      <?php if ($k === 'promptpay_type'): ?>
        <select name="<?= e($k) ?>">
          <?php foreach (['phone','nid','ewallet'] as $opt): ?>
            <option value="<?= e($opt) ?>" <?= $values[$k]===$opt?'selected':'' ?>><?= e($opt) ?></option>
          <?php endforeach; ?>
        </select>
      <?php elseif ($k === 'site_subtitle'): ?>
        <textarea name="<?= e($k) ?>" rows="2"><?= e($values[$k]) ?></textarea>
      <?php else: ?>
        <input name="<?= e($k) ?>" value="<?= e($values[$k]) ?>">
      <?php endif; ?>
    </label>
  <?php endforeach; ?>

  <fieldset>
    <legend>รูป QR (ใช้แทน PromptPay ในกรณีที่ไม่ระบุ PromptPay ID)</legend>
    <?php if ($qr_image): ?>
      <div class="thumb">
        <img src="../<?= e($qr_image) ?>" alt="QR ปัจจุบัน">
      </div>
      <p class="small muted">รูป QR ปัจจุบัน — อัปโหลดรูปใหม่เพื่อแทน หรือ:</p>
    <?php else: ?>
      <p class="small muted">ยังไม่ได้อัปโหลดรูป QR</p>
    <?php endif; ?>

    <label>
      <span>อัปโหลดรูป QR (JPG / PNG / WEBP, ≤ 5 MB)</span>
      <input type="file" name="qr_image" accept="image/*">
    </label>
    <small class="muted">
      หมายเหตุ: ระบบจะเลือกแสดง QR ตามลำดับนี้ —
      ถ้ามี PromptPay ID จะใช้ QR ที่ระบบ generate เอง (ใส่ยอดอัตโนมัติได้)
      ถ้าไม่มี ใช้รูปนี้แทน
      ถ้าไม่มีทั้งคู่ ซ่อนช่อง QR เลย
    </small>
  </fieldset>

  <fieldset>
    <legend>เปลี่ยน password (เว้นว่าง = ไม่เปลี่ยน)</legend>
    <label><span>Password ใหม่</span><input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
  </fieldset>

  <button class="btn btn-primary" type="submit">บันทึก</button>
</form>

<?php if ($qr_image): ?>
  <form method="post" class="adm-form" style="margin-top:16px"
        onsubmit="return confirm('ลบรูป QR ปัจจุบัน?')">
    <?= csrf_field() ?>
    <input type="hidden" name="remove_qr" value="1">
    <button class="btn btn-danger" type="submit">ลบรูป QR ที่อัปโหลด</button>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
