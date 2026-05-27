<?php
$page_title = 'รายการบริจาค';
require __DIR__ . '/_layout.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$gid    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = isset($_POST['op']) ? $_POST['op'] : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($op === 'save_new') {
        $cid    = (int)(isset($_POST['campaign_id']) ? $_POST['campaign_id'] : 0);
        $camp   = $cid > 0 ? db_one('SELECT id FROM campaigns WHERE id = ?', [$cid]) : null;
        if (!$camp) { flash('err', 'เลือกแคมเปญก่อน'); redirect('donations.php?action=new'); }

        $donor_name = trim(isset($_POST['donor_name']) ? $_POST['donor_name'] : '');
        $room       = isset($_POST['room']) ? $_POST['room'] : '';
        $amount     = (float)(isset($_POST['amount']) ? $_POST['amount'] : 0);
        $status     = isset($_POST['status']) ? $_POST['status'] : 'verified';
        $message    = trim(isset($_POST['message']) ? $_POST['message'] : '');
        $tat        = trim(isset($_POST['transferred_at']) ? $_POST['transferred_at'] : '');
        $tat_db = null;
        if ($tat !== '') {
            $ts = strtotime(str_replace('T', ' ', $tat));
            if ($ts !== false) $tat_db = date('Y-m-d H:i:s', $ts);
        }

        $errs = [];
        if ($donor_name === '' || mb_strlen($donor_name) > 160) $errs[] = 'ชื่อไม่ถูกต้อง';
        if (!in_array($room, ['A','B'], true))                   $errs[] = 'เลือกห้อง';
        if ($amount <= 0 || $amount > 9999999)                   $errs[] = 'จำนวนเงินไม่ถูกต้อง';
        if (!in_array($status, ['pending','verified','rejected'], true)) $errs[] = 'สถานะไม่ถูก';

        // slip upload optional — ไม่มีก็ได้
        $slip_path = null;
        $new_slip_abs = null;
        if (isset($_FILES['slip']) && $_FILES['slip']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = handle_upload('slip', $GLOBALS['CONFIG']['upload_dir'], $GLOBALS['CONFIG']['upload_url']);
            if (is_array($up) && isset($up['error'])) {
                $errs[] = $up['error'];
            } elseif ($up) {
                $slip_path = $up['path'];
                $new_slip_abs = $up['abs'];
            }
        }

        if ($errs) {
            if ($new_slip_abs && is_file($new_slip_abs)) @unlink($new_slip_abs);
            flash('err', implode(' / ', $errs));
            redirect('donations.php?action=new');
        }

        $verified_at = ($status === 'pending') ? null : date('Y-m-d H:i:s');
        $verified_by = ($status === 'pending') ? null : admin_id();
        $created_at  = $tat_db ? $tat_db : date('Y-m-d H:i:s');

        db_run(
            'INSERT INTO donations
                (campaign_id, donor_name, room, amount, transferred_at, slip_path, message,
                 status, created_at, verified_at, verified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$cid, $donor_name, $room, $amount, $tat_db, $slip_path, $message,
             $status, $created_at, $verified_at, $verified_by]
        );
        flash('ok', 'เพิ่มรายการแล้ว');
        redirect('donations.php?campaign=' . $cid);
    }

    if ($op === 'save_edit' && $id > 0) {
        $cur = db_one('SELECT * FROM donations WHERE id = ?', [$id]);
        if (!$cur) { flash('err', 'ไม่พบรายการ'); redirect('donations.php'); }

        $donor_name = trim(isset($_POST['donor_name']) ? $_POST['donor_name'] : '');
        $room       = isset($_POST['room']) ? $_POST['room'] : '';
        $amount     = (float)(isset($_POST['amount']) ? $_POST['amount'] : 0);
        $status     = isset($_POST['status']) ? $_POST['status'] : '';
        $message    = trim(isset($_POST['message']) ? $_POST['message'] : '');
        $tat        = trim(isset($_POST['transferred_at']) ? $_POST['transferred_at'] : '');
        $tat_db = null;
        if ($tat !== '') {
            $ts = strtotime(str_replace('T', ' ', $tat));
            if ($ts !== false) $tat_db = date('Y-m-d H:i:s', $ts);
        }

        $errs = [];
        if ($donor_name === '' || mb_strlen($donor_name) > 160) $errs[] = 'ชื่อไม่ถูกต้อง';
        if (!in_array($room, ['A','B'], true))                   $errs[] = 'เลือกห้องไม่ถูก';
        if ($amount <= 0 || $amount > 9999999)                   $errs[] = 'จำนวนเงินไม่ถูกต้อง';
        if (!in_array($status, ['pending','verified','rejected'], true)) $errs[] = 'สถานะไม่ถูก';
        if (mb_strlen($message) > 500)                           $errs[] = 'ข้อความยาวเกิน';

        // slip upload (optional — ถ้าไม่ส่งมาเก็บของเดิม)
        $slip_path = $cur['slip_path'];
        $new_slip_abs = null;
        if (isset($_FILES['slip']) && $_FILES['slip']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = handle_upload('slip', $GLOBALS['CONFIG']['upload_dir'], $GLOBALS['CONFIG']['upload_url']);
            if (is_array($up) && isset($up['error'])) {
                $errs[] = $up['error'];
            } elseif ($up) {
                $slip_path = $up['path'];
                $new_slip_abs = $up['abs'];
            }
        }

        // ลบสลิปเดิม (เมื่อสั่งลบ — และไม่ได้อัปใหม่)
        $remove_slip = !empty($_POST['remove_slip']);
        if ($remove_slip && $new_slip_abs === null) {
            $slip_path = null;
        }

        if ($errs) {
            // ถ้า upload สำเร็จแล้วแต่มี error อื่น → ลบไฟล์ที่เพิ่งย้ายมา
            if ($new_slip_abs && is_file($new_slip_abs)) @unlink($new_slip_abs);
            flash('err', implode(' / ', $errs));
            redirect('donations.php?action=edit&id=' . $id);
        }

        // status เปลี่ยน → อัปเดต verified_at/by
        $verified_at = $cur['verified_at'];
        $verified_by = $cur['verified_by'];
        if ($status !== $cur['status']) {
            if ($status === 'pending') {
                $verified_at = null; $verified_by = null;
            } else {
                $verified_at = date('Y-m-d H:i:s');
                $verified_by = admin_id();
            }
        }

        db_run(
            'UPDATE donations
                SET donor_name = ?, room = ?, amount = ?, message = ?,
                    transferred_at = ?, status = ?,
                    slip_path = ?, verified_at = ?, verified_by = ?
              WHERE id = ?',
            [$donor_name, $room, $amount, $message, $tat_db, $status, $slip_path, $verified_at, $verified_by, $id]
        );

        // ลบไฟล์สลิปเก่าออกจาก disk (เมื่อ path เปลี่ยน)
        if ($cur['slip_path'] && $cur['slip_path'] !== $slip_path) {
            $oldAbs = __DIR__ . '/../' . $cur['slip_path'];
            if (is_file($oldAbs)) @unlink($oldAbs);
        }

        // แจ้ง LINE เฉพาะตอน status เปลี่ยนเข้าสู่ verified/rejected (กัน spam ตอน edit เฉยๆ)
        if ($status !== $cur['status'] && in_array($status, ['verified','rejected'], true)) {
            $camp = db_one('SELECT * FROM campaigns WHERE id = ?', [(int)$cur['campaign_id']]);
            if ($camp) {
                $line_res = line_notify_donation($camp, $status);
                if (is_array($line_res) && !$line_res['ok']) {
                    flash('err', 'LINE ส่งไม่สำเร็จ (HTTP ' . (int)$line_res['status'] . '): '
                        . mb_substr((string)$line_res['error'], 0, 140, 'UTF-8'));
                }
            }
        }

        flash('ok', 'บันทึกแล้ว');
        redirect('donations.php');
    }

    if ($id > 0 && in_array($op, ['verify','reject','reset','delete'], true)) {
        // ดึง row เดิมก่อน update เพื่อตัดสินใจว่าจะแจ้ง LINE หรือไม่ (กันยิงซ้ำ)
        $prev = db_one('SELECT campaign_id, status FROM donations WHERE id = ?', [$id]);

        if ($op === 'verify') {
            db_run('UPDATE donations SET status = "verified", verified_at = NOW(), verified_by = ? WHERE id = ?',
                [admin_id(), $id]);
            flash('ok', 'ยืนยันแล้ว');
            if ($prev && $prev['status'] !== 'verified') {
                $camp = db_one('SELECT * FROM campaigns WHERE id = ?', [(int)$prev['campaign_id']]);
                if ($camp) {
                    $line_res = line_notify_donation($camp, 'verified');
                    if (is_array($line_res) && !$line_res['ok']) {
                        flash('err', 'LINE ส่งไม่สำเร็จ (HTTP ' . (int)$line_res['status'] . '): '
                            . mb_substr((string)$line_res['error'], 0, 140, 'UTF-8'));
                    }
                }
            }
        } elseif ($op === 'reject') {
            db_run('UPDATE donations SET status = "rejected", verified_at = NOW(), verified_by = ? WHERE id = ?',
                [admin_id(), $id]);
            flash('ok', 'ปฏิเสธแล้ว');
            if ($prev && $prev['status'] !== 'rejected') {
                $camp = db_one('SELECT * FROM campaigns WHERE id = ?', [(int)$prev['campaign_id']]);
                if ($camp) {
                    $line_res = line_notify_donation($camp, 'rejected');
                    if (is_array($line_res) && !$line_res['ok']) {
                        flash('err', 'LINE ส่งไม่สำเร็จ (HTTP ' . (int)$line_res['status'] . '): '
                            . mb_substr((string)$line_res['error'], 0, 140, 'UTF-8'));
                    }
                }
            }
        } elseif ($op === 'reset') {
            db_run('UPDATE donations SET status = "pending", verified_at = NULL, verified_by = NULL WHERE id = ?',
                [$id]);
            flash('ok', 'ตั้งเป็นรอตรวจสอบ');
        } elseif ($op === 'delete') {
            $row = db_one('SELECT slip_path FROM donations WHERE id = ?', [$id]);
            db_run('DELETE FROM donations WHERE id = ?', [$id]);
            if ($row && !empty($row['slip_path'])) {
                $abs = __DIR__ . '/../' . $row['slip_path'];
                if (is_file($abs)) @unlink($abs);
            }
            flash('ok', 'ลบรายการแล้ว');
        }
    }
    $qs = [];
    if (!empty($_POST['return_status']))   $qs['status']   = $_POST['return_status'];
    if (!empty($_POST['return_campaign'])) $qs['campaign'] = (int)$_POST['return_campaign'];
    redirect('donations.php' . ($qs ? '?' . http_build_query($qs) : ''));
}

// ---------- NEW view ----------
if ($action === 'new') {
    $all_campaigns = db_all('SELECT id, deceased_name FROM campaigns ORDER BY status = "active" DESC, created_at DESC');
    $preselect_cid = isset($_GET['campaign']) ? (int)$_GET['campaign'] : 0;
    ?>
    <a class="back-link" href="donations.php">← กลับ</a>
    <h1>เพิ่มรายการบริจาค (manual)</h1>
    <p class="muted">สำหรับรายการที่อาจไม่มีรูปสลิป (เช่น import จากเดิม หรือเพื่อนแจ้งผ่าน LINE ตรงๆ)</p>

    <form method="post" class="adm-form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save_new">

      <label><span>แคมเปญ <em>*</em></span>
        <select name="campaign_id" required>
          <option value="">— เลือก —</option>
          <?php foreach ($all_campaigns as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>" <?= $preselect_cid === (int)$cc['id'] ? 'selected' : '' ?>><?= e($cc['deceased_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="row">
        <label><span>ชื่อผู้บริจาค <em>*</em></span>
          <input name="donor_name" required maxlength="160"></label>
        <label><span>ห้อง <em>*</em></span>
          <select name="room" required>
            <option value="">— เลือก —</option>
            <option value="A">ห้อง A</option>
            <option value="B">ห้อง B</option>
          </select></label>
      </div>

      <div class="row">
        <label><span>จำนวนเงิน <em>*</em></span>
          <input type="number" name="amount" step="0.01" min="0.01" required></label>
        <label><span>เวลาที่โอน (optional)</span>
          <input type="datetime-local" name="transferred_at"></label>
      </div>

      <label><span>สถานะ</span>
        <select name="status">
          <option value="verified" selected>ยืนยันแล้ว</option>
          <option value="pending">รอตรวจสอบ</option>
          <option value="rejected">ปฏิเสธ</option>
        </select>
        <small class="muted">รายการที่ admin add เอง default = "ยืนยันแล้ว"</small>
      </label>

      <label><span>ข้อความ (optional)</span>
        <textarea name="message" rows="2" maxlength="500"></textarea></label>

      <fieldset>
        <legend>รูปสลิป (optional)</legend>
        <label><span>เลือกไฟล์รูป — ไม่มีก็ได้</span>
          <input type="file" name="slip" accept="image/*">
        </label>
      </fieldset>

      <div>
        <button class="btn btn-primary" type="submit">+ เพิ่มรายการ</button>
        <a class="btn" href="donations.php">ยกเลิก</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// ---------- EDIT view ----------
if ($action === 'edit' && $gid > 0) {
    $d = db_one('SELECT d.*, c.deceased_name FROM donations d JOIN campaigns c ON c.id = d.campaign_id WHERE d.id = ?', [$gid]);
    if (!$d) { flash('err', 'ไม่พบ'); redirect('donations.php'); }
    $tat_value = $d['transferred_at'] ? date('Y-m-d\TH:i', strtotime($d['transferred_at'])) : '';
    ?>
    <a class="back-link" href="donations.php">← กลับ</a>
    <h1>แก้ไขรายการบริจาค #<?= (int)$d['id'] ?></h1>
    <p class="muted">แคมเปญ: <strong><?= e($d['deceased_name']) ?></strong></p>

    <form method="post" class="adm-form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save_edit">
      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">

      <div class="row">
        <label><span>ชื่อ *</span>
          <input name="donor_name" required maxlength="160" value="<?= e($d['donor_name']) ?>"></label>
        <label><span>ห้อง *</span>
          <select name="room" required>
            <option value="A" <?= $d['room']==='A'?'selected':'' ?>>ห้อง A</option>
            <option value="B" <?= $d['room']==='B'?'selected':'' ?>>ห้อง B</option>
          </select></label>
      </div>

      <div class="row">
        <label><span>จำนวนเงิน *</span>
          <input type="number" name="amount" step="0.01" min="0.01" required value="<?= e($d['amount']) ?>"></label>
        <label><span>เวลาที่โอน</span>
          <input type="datetime-local" name="transferred_at" value="<?= e($tat_value) ?>"></label>
      </div>

      <label><span>สถานะ *</span>
        <select name="status" required>
          <option value="pending"  <?= $d['status']==='pending' ?'selected':'' ?>>รอตรวจสอบ</option>
          <option value="verified" <?= $d['status']==='verified'?'selected':'' ?>>ยืนยันแล้ว</option>
          <option value="rejected" <?= $d['status']==='rejected'?'selected':'' ?>>ปฏิเสธ</option>
        </select></label>

      <label><span>ข้อความ (optional)</span>
        <textarea name="message" rows="2" maxlength="500"><?= e($d['message']) ?></textarea></label>

      <fieldset>
        <legend>รูปสลิป</legend>
        <?php if (!empty($d['slip_path'])): ?>
          <div class="thumb"><img src="../<?= e($d['slip_path']) ?>" alt="slip" style="max-width:240px"></div>
          <label class="small">
            <input type="checkbox" name="remove_slip" value="1"> ลบรูปสลิปนี้ (จะลบไฟล์จาก disk ด้วย)
          </label>
        <?php else: ?>
          <p class="small muted">ยังไม่มีรูปสลิป</p>
        <?php endif; ?>
        <label><span>อัปโหลดรูปสลิปใหม่ (optional — เลือกแล้วจะแทนของเดิม)</span>
          <input type="file" name="slip" accept="image/*">
        </label>
      </fieldset>

      <div>
        <button class="btn btn-primary" type="submit">บันทึก</button>
        <a class="btn" href="donations.php">ยกเลิก</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// ---------- LIST view ----------
$status = isset($_GET['status']) ? $_GET['status'] : '';
$camp   = isset($_GET['campaign']) ? (int)$_GET['campaign'] : 0;

$where = []; $params = [];
if (in_array($status, ['pending','verified','rejected'], true)) {
    $where[] = 'd.status = ?'; $params[] = $status;
}
if ($camp > 0) {
    $where[] = 'd.campaign_id = ?'; $params[] = $camp;
}
$sql = 'SELECT d.*, c.deceased_name, c.slug,
               COALESCE(d.transferred_at, d.created_at) AS at_time
          FROM donations d
          JOIN campaigns c ON c.id = d.campaign_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY at_time DESC, d.id DESC LIMIT 500';
$rows = db_all($sql, $params);

$campaigns = db_all('SELECT id, deceased_name FROM campaigns ORDER BY created_at DESC');
?>
<div class="page-head">
  <h1>รายการบริจาค</h1>
  <a href="donations.php?action=new<?= $camp > 0 ? '&campaign=' . $camp : '' ?>" class="btn btn-primary btn-sm">+ เพิ่มรายการ</a>
</div>

<form method="get" class="filter-bar">
  <select name="status">
    <option value="">— ทุกสถานะ —</option>
    <option value="pending"  <?= $status==='pending' ?'selected':'' ?>>รอตรวจสอบ</option>
    <option value="verified" <?= $status==='verified'?'selected':'' ?>>ยืนยันแล้ว</option>
    <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>ปฏิเสธ</option>
  </select>
  <select name="campaign">
    <option value="0">— ทุกแคมเปญ —</option>
    <?php foreach ($campaigns as $cc): ?>
      <option value="<?= (int)$cc['id'] ?>" <?= $camp===(int)$cc['id']?'selected':'' ?>><?= e($cc['deceased_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">กรอง</button>
  <a href="donations.php" class="btn btn-sm">ล้าง</a>
</form>

<?php if (!$rows): ?>
  <div class="empty">ไม่มีรายการ</div>
<?php else: ?>
<table class="adm-table">
  <thead><tr><th>เวลา</th><th>แคมเปญ</th><th>ผู้บริจาค</th><th>ห้อง</th><th class="num">เงิน</th><th>สลิป</th><th>สถานะ</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="row-<?= e($r['status']) ?>">
        <td class="muted small"><?= e(thai_datetime($r['at_time'])) ?></td>
        <td><a href="../campaign.php?slug=<?= e($r['slug']) ?>" target="_blank"><?= e($r['deceased_name']) ?></a></td>
        <td>
          <?= e($r['donor_name']) ?>
          <?php if (!empty($r['message'])): ?>
            <div class="small muted">"<?= e($r['message']) ?>"</div>
          <?php endif; ?>
        </td>
        <td><?= e($r['room']) ?></td>
        <td class="num">฿<?= thb($r['amount']) ?></td>
        <td>
          <?php if (!empty($r['slip_path'])): ?>
            <button type="button" class="slip-link" data-slip="../<?= e($r['slip_path']) ?>" title="คลิกดูเต็ม">
              <img src="../<?= e($r['slip_path']) ?>" alt="slip" loading="lazy">
            </button>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><span class="badge badge-<?= $r['status']==='verified'?'ok':($r['status']==='rejected'?'no':'wait') ?>">
          <?= e($r['status']) ?></span></td>
        <td class="actions">
          <a class="btn btn-sm" href="donations.php?action=edit&id=<?= (int)$r['id'] ?>">แก้ไข</a>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="return_status" value="<?= e($status) ?>">
            <input type="hidden" name="return_campaign" value="<?= (int)$camp ?>">
            <?php if ($r['status'] !== 'verified'): ?>
              <button name="op" value="verify"  class="btn btn-sm btn-ok">ยืนยัน</button>
            <?php endif; ?>
            <?php if ($r['status'] !== 'rejected'): ?>
              <button name="op" value="reject"  class="btn btn-sm">ปฏิเสธ</button>
            <?php endif; ?>
            <?php if ($r['status'] !== 'pending'): ?>
              <button name="op" value="reset"   class="btn btn-sm">รีเซ็ต</button>
            <?php endif; ?>
            <button name="op" value="delete" class="btn btn-sm btn-danger"
                    onclick="return confirm('ลบรายการนี้?')">ลบ</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
