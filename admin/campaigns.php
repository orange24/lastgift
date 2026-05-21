<?php
$page_title = 'แคมเปญ';
require __DIR__ . '/_layout.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// helper: process a single file from a multi-file input ($_FILES['key'])
function _save_one_gallery_image($idx, $destDir, $destUrlBase) {
    $f = $_FILES['gallery'];
    if (!isset($f['error'][$idx]) || $f['error'][$idx] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'][$idx] !== UPLOAD_ERR_OK) return ['error' => 'อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . $f['error'][$idx] . ')'];
    $max = isset($GLOBALS['CONFIG']['max_upload_bytes']) ? $GLOBALS['CONFIG']['max_upload_bytes'] : 5*1024*1024;
    if ($f['size'][$idx] > $max) return ['error' => 'ไฟล์ใหญ่เกิน ' . ($max / 1024 / 1024) . ' MB'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name'][$idx]);
    $allow = $GLOBALS['CONFIG']['allowed_mime'];
    if (!isset($allow[$mime])) return ['error' => 'รองรับเฉพาะรูป JPG / PNG / WEBP / GIF'];
    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0775, true)) return ['error' => 'สร้างโฟลเดอร์ไม่ได้: ' . $destDir];
    }
    if (!is_writable($destDir)) return ['error' => 'โฟลเดอร์เขียนไม่ได้ (chmod 777): ' . $destDir];
    $ext  = $allow[$mime];
    $name = date('Ymd_His') . '_' . bin2hex(openssl_random_pseudo_bytes(8)) . '.' . $ext;
    $abs  = rtrim($destDir, '/') . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'][$idx], $abs)) {
        $err = error_get_last();
        return ['error' => 'ย้ายไฟล์ไม่สำเร็จ: ' . (isset($err['message']) ? $err['message'] : 'unknown')];
    }
    @chmod($abs, 0644);
    return ['path' => rtrim($destUrlBase, '/') . '/' . $name];
}

// ----- POST handler -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = isset($_POST['op']) ? $_POST['op'] : '';

    if ($op === 'save') {
        $cid           = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $deceased_name = trim(isset($_POST['deceased_name']) ? $_POST['deceased_name'] : '');
        $relation      = trim(isset($_POST['relation']) ? $_POST['relation'] : '');
        $eulogy        = trim(isset($_POST['eulogy']) ? $_POST['eulogy'] : '');
        $status        = isset($_POST['status']) && $_POST['status'] === 'closed' ? 'closed' : 'active';
        $slug          = trim(isset($_POST['slug']) ? $_POST['slug'] : '');
        if ($slug === '') $slug = slugify($deceased_name);

        if ($deceased_name === '') { flash('err', 'ชื่อต้องไม่ว่าง'); redirect('campaigns.php?action=edit&id=' . $cid); }

        // unique slug
        $dup = db_one('SELECT id FROM campaigns WHERE slug = ? AND id <> ?', [$slug, $cid]);
        if ($dup) $slug .= '-' . bin2hex(openssl_random_pseudo_bytes(2));

        // hero upload (optional)
        $hero = null;
        $up = handle_upload('hero_image', $GLOBALS['CONFIG']['hero_dir'], $GLOBALS['CONFIG']['hero_url']);
        if (is_array($up) && isset($up['error'])) { flash('err', $up['error']); redirect('campaigns.php?action=edit&id=' . $cid); }
        if ($up) $hero = $up['path'];

        if ($cid > 0) {
            $cur = db_one('SELECT hero_image FROM campaigns WHERE id = ?', [$cid]);
            $heroVal = $hero !== null ? $hero : $cur['hero_image'];
            db_run('UPDATE campaigns SET slug = ?, deceased_name = ?, relation = ?, eulogy = ?, status = ?, hero_image = ? WHERE id = ?',
                [$slug, $deceased_name, $relation, $eulogy, $status, $heroVal, $cid]);
            flash('ok', 'บันทึกแคมเปญแล้ว');
        } else {
            db_run('INSERT INTO campaigns (slug, deceased_name, relation, eulogy, status, hero_image) VALUES (?, ?, ?, ?, ?, ?)',
                [$slug, $deceased_name, $relation, $eulogy, $status, $hero]);
            $cid = (int)db_insert_id();
            flash('ok', 'สร้างแคมเปญแล้ว');
        }

        // gallery upload (multi-file) — เพิ่มเข้าไป ไม่แทน
        $gallery_errs = [];
        if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
            $maxOrder = db_one('SELECT COALESCE(MAX(sort_order),0) AS m FROM campaign_images WHERE campaign_id = ?', [$cid]);
            $order = (int)$maxOrder['m'];
            $count = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $count; $i++) {
                $r = _save_one_gallery_image($i, $GLOBALS['CONFIG']['gallery_dir'], $GLOBALS['CONFIG']['gallery_url']);
                if ($r === null) continue;
                if (isset($r['error'])) { $gallery_errs[] = $r['error']; continue; }
                $order += 10;
                db_run('INSERT INTO campaign_images (campaign_id, path, sort_order) VALUES (?, ?, ?)',
                    [$cid, $r['path'], $order]);
            }
        }
        if ($gallery_errs) flash('err', 'รูปบางรูปอัปไม่สำเร็จ: ' . implode(' / ', $gallery_errs));
        redirect('campaigns.php?action=edit&id=' . $cid);
    }

    if ($op === 'del_image') {
        $iid = (int)(isset($_POST['image_id']) ? $_POST['image_id'] : 0);
        $img = db_one('SELECT * FROM campaign_images WHERE id = ?', [$iid]);
        if ($img) {
            $abs = __DIR__ . '/../' . $img['path'];
            if (is_file($abs)) @unlink($abs);
            db_run('DELETE FROM campaign_images WHERE id = ?', [$iid]);
            flash('ok', 'ลบรูปแล้ว');
            redirect('campaigns.php?action=edit&id=' . (int)$img['campaign_id']);
        }
        redirect('campaigns.php');
    }

    if ($op === 'save_expense') {
        $cid    = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $eid    = (int)(isset($_POST['expense_id']) ? $_POST['expense_id'] : 0);
        $desc   = trim(isset($_POST['description']) ? $_POST['description'] : '');
        $amount = (float)(isset($_POST['amount']) ? $_POST['amount'] : 0);
        $sat    = trim(isset($_POST['spent_at']) ? $_POST['spent_at'] : '');
        $from   = isset($_POST['from']) ? $_POST['from'] : '';
        $sat_db = null;
        if ($sat !== '') {
            $ts = strtotime(str_replace('T', ' ', $sat));
            if ($ts !== false) $sat_db = date('Y-m-d H:i:s', $ts);
        }

        $redirect_to = $from === 'modal'
            ? 'campaigns.php?expenses_open=' . $cid
            : 'campaigns.php?action=edit&id=' . $cid . '#expenses';

        if ($cid <= 0 || $desc === '' || $amount <= 0) {
            flash('err', 'กรอกรายการ + จำนวนเงิน (>0) ให้ครบ');
            redirect($redirect_to);
        }

        // ถ้าแก้ไข: ต้องดึง row เดิมเพื่อรู้ image_path เดิม
        $cur_exp = null;
        if ($eid > 0) {
            $cur_exp = db_one('SELECT * FROM expenses WHERE id = ?', [$eid]);
            if (!$cur_exp || (int)$cur_exp['campaign_id'] !== $cid) {
                flash('err', 'ไม่พบค่าใช้จ่าย');
                redirect($redirect_to);
            }
        }

        // upload รูปประกอบ (optional)
        $image_path  = $cur_exp ? $cur_exp['image_path'] : null;
        $new_img_abs = null;
        $up = handle_upload('image', $GLOBALS['CONFIG']['expense_dir'], $GLOBALS['CONFIG']['expense_url']);
        if (is_array($up) && isset($up['error'])) {
            flash('err', $up['error']);
            redirect($redirect_to);
        } elseif ($up) {
            $image_path  = $up['path'];
            $new_img_abs = $up['abs'];
        }

        // ลบรูปเดิมเมื่อสั่งลบ และไม่ได้อัปใหม่
        if (!empty($_POST['remove_image']) && $new_img_abs === null) {
            $image_path = null;
        }

        if ($eid > 0) {
            db_run('UPDATE expenses SET description = ?, amount = ?, spent_at = ?, image_path = ? WHERE id = ?',
                [$desc, $amount, $sat_db, $image_path, $eid]);
            // ลบไฟล์เก่าจาก disk ถ้า path เปลี่ยน
            if ($cur_exp && $cur_exp['image_path'] && $cur_exp['image_path'] !== $image_path) {
                $oldAbs = __DIR__ . '/../' . $cur_exp['image_path'];
                if (is_file($oldAbs)) @unlink($oldAbs);
            }
            flash('ok', 'แก้ไขค่าใช้จ่ายแล้ว');
        } else {
            db_run('INSERT INTO expenses (campaign_id, description, amount, spent_at, image_path) VALUES (?, ?, ?, ?, ?)',
                [$cid, $desc, $amount, $sat_db, $image_path]);
            flash('ok', 'เพิ่มค่าใช้จ่ายแล้ว');

            // แจ้ง LINE — เฉพาะตอน insert ใหม่ ไม่นับ edit (กัน spam)
            $camp = db_one('SELECT * FROM campaigns WHERE id = ?', [$cid]);
            if ($camp) {
                $line_res = line_notify_expense($camp, [
                    'description' => $desc,
                    'amount'      => $amount,
                ]);
                if (is_array($line_res) && !$line_res['ok']) {
                    flash('err', 'LINE ส่งไม่สำเร็จ (HTTP ' . (int)$line_res['status'] . '): '
                        . mb_substr((string)$line_res['error'], 0, 140, 'UTF-8'));
                }
            }
        }
        redirect($redirect_to);
    }

    if ($op === 'del_expense') {
        $eid  = (int)(isset($_POST['expense_id']) ? $_POST['expense_id'] : 0);
        $from = isset($_POST['from']) ? $_POST['from'] : '';
        $exp = db_one('SELECT campaign_id, image_path FROM expenses WHERE id = ?', [$eid]);
        if ($exp) {
            db_run('DELETE FROM expenses WHERE id = ?', [$eid]);
            // ลบไฟล์รูปจาก disk ด้วย
            if (!empty($exp['image_path'])) {
                $abs = __DIR__ . '/../' . $exp['image_path'];
                if (is_file($abs)) @unlink($abs);
            }
            flash('ok', 'ลบค่าใช้จ่ายแล้ว');
            if ($from === 'modal') {
                redirect('campaigns.php?expenses_open=' . (int)$exp['campaign_id']);
            }
            redirect('campaigns.php?action=edit&id=' . (int)$exp['campaign_id'] . '#expenses');
        }
        redirect('campaigns.php');
    }

    if ($op === 'push_summary') {
        $cid    = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $return = isset($_POST['return_to']) ? $_POST['return_to'] : 'campaigns.php';
        if (!in_array($return, ['campaigns.php', 'index.php'], true)) $return = 'campaigns.php';
        $camp = db_one('SELECT * FROM campaigns WHERE id = ?', [$cid]);
        if (!$camp) {
            flash('err', 'ไม่พบแคมเปญ');
            redirect($return);
        }
        $res = line_push_summary($camp);
        if ($res['ok']) {
            flash('ok', 'ส่งสรุปยอด LINE สำเร็จ (HTTP ' . (int)$res['status'] . ')');
        } else {
            flash('err', 'ส่งไม่สำเร็จ (HTTP ' . (int)$res['status'] . '): ' . $res['error']);
        }
        redirect($return);
    }

    if ($op === 'delete') {
        $cid = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        if ($cid > 0) {
            // ลบรูป gallery จาก disk ก่อน
            foreach (db_all('SELECT path FROM campaign_images WHERE campaign_id = ?', [$cid]) as $img) {
                $abs = __DIR__ . '/../' . $img['path'];
                if (is_file($abs)) @unlink($abs);
            }
            db_run('DELETE FROM campaigns WHERE id = ?', [$cid]);
            flash('ok', 'ลบแคมเปญแล้ว');
        }
        redirect('campaigns.php');
    }
}

// ----- views -----
if ($action === 'edit' || $action === 'new') {
    $c = $id > 0 ? db_one('SELECT * FROM campaigns WHERE id = ?', [$id]) : null;
    if ($action === 'edit' && !$c) { flash('err', 'ไม่พบ'); redirect('campaigns.php'); }
    $images = $c ? db_all('SELECT * FROM campaign_images WHERE campaign_id = ? ORDER BY sort_order, id', [$c['id']]) : [];
    ?>
    <a class="back-link" href="campaigns.php">← กลับ</a>
    <h1><?= $c ? 'แก้ไขแคมเปญ' : 'สร้างแคมเปญใหม่' ?></h1>
    <form method="post" enctype="multipart/form-data" class="adm-form">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= $c ? (int)$c['id'] : 0 ?>">

      <label><span>ชื่อผู้จากไป *</span>
        <input name="deceased_name" required maxlength="160" value="<?= e($c ? $c['deceased_name'] : '') ?>"></label>

      <label><span>ความสัมพันธ์ / ห้อง (เช่น "คุณพ่อของเพื่อน A — ห้อง B")</span>
        <input name="relation" maxlength="120" value="<?= e($c ? $c['relation'] : '') ?>"></label>

      <label><span>URL slug (เว้นว่างให้ระบบสร้างให้)</span>
        <input name="slug" maxlength="80" value="<?= e($c ? $c['slug'] : '') ?>"></label>

      <label><span>คำไว้อาลัย / รายละเอียด</span>
        <textarea name="eulogy" rows="5"><?= e($c ? $c['eulogy'] : '') ?></textarea></label>

      <label><span>รูปประจำแคมเปญ — รูปหลัก (optional)</span>
        <input type="file" name="hero_image" accept="image/*">
        <?php if ($c && !empty($c['hero_image'])): ?>
          <div class="thumb"><img src="../<?= e($c['hero_image']) ?>" alt=""></div>
        <?php endif; ?>
      </label>

      <label><span>รูปประกอบเพิ่มเติม (เลือกได้หลายรูป — ไม่จำกัดจำนวน)</span>
        <input type="file" name="gallery[]" accept="image/*" multiple>
        <small class="muted">แต่ละรูป ≤ 5 MB — กดบันทึกเพื่ออัปโหลด</small>
      </label>

      <label><span>สถานะ</span>
        <select name="status">
          <option value="active"  <?= ($c && $c['status']==='active')?'selected':'' ?>>เปิดรับ</option>
          <option value="closed"  <?= ($c && $c['status']==='closed')?'selected':'' ?>>ปิดรับ</option>
        </select></label>

      <button class="btn btn-primary" type="submit">บันทึก</button>
    </form>

    <?php if ($images): ?>
      <h2 style="margin-top:24px">รูปประกอบที่อัปแล้ว (<?= count($images) ?>)</h2>
      <div class="gallery-admin">
        <?php foreach ($images as $img): ?>
          <div class="gallery-item">
            <img src="../<?= e($img['path']) ?>" alt="">
            <form method="post" onsubmit="return confirm('ลบรูปนี้?')">
              <?= csrf_field() ?>
              <input type="hidden" name="op" value="del_image">
              <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($c): ?>
      <?php
        $expenses = db_all('SELECT * FROM expenses WHERE campaign_id = ? ORDER BY COALESCE(spent_at, created_at) DESC, id DESC', [$c['id']]);
        $exp_total = 0; foreach ($expenses as $ex) $exp_total += (float)$ex['amount'];
        $edit_eid = isset($_GET['edit_expense']) ? (int)$_GET['edit_expense'] : 0;
        $edit_ex = null;
        if ($edit_eid > 0) {
            foreach ($expenses as $ex) if ((int)$ex['id'] === $edit_eid) { $edit_ex = $ex; break; }
        }
        $form_spent_at = $edit_ex && $edit_ex['spent_at']
            ? date('Y-m-d\TH:i', strtotime($edit_ex['spent_at']))
            : '';
      ?>
      <h2 id="expenses" style="margin-top:32px">ค่าใช้จ่าย<?= $expenses ? ' (' . count($expenses) . ' รายการ ฿' . thb($exp_total) . ')' : '' ?></h2>

      <form method="post" class="adm-form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="save_expense">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <input type="hidden" name="expense_id" value="<?= $edit_ex ? (int)$edit_ex['id'] : 0 ?>">
        <?php if ($edit_ex): ?>
          <div class="small muted">กำลังแก้ไขค่าใช้จ่าย #<?= (int)$edit_ex['id'] ?></div>
        <?php endif; ?>
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:end">
          <label><span>รายการ *</span>
            <input name="description" required maxlength="255" placeholder="เช่น พวงหรีด"
                   value="<?= e($edit_ex ? $edit_ex['description'] : '') ?>"></label>
          <label><span>จำนวนเงิน *</span>
            <input type="number" name="amount" step="0.01" min="0.01" required
                   value="<?= e($edit_ex ? $edit_ex['amount'] : '') ?>"></label>
          <label><span>วันที่ใช้จ่าย (optional)</span>
            <input type="datetime-local" name="spent_at" value="<?= e($form_spent_at) ?>"></label>
          <button class="btn btn-primary" type="submit"><?= $edit_ex ? 'บันทึก' : '+ เพิ่ม' ?></button>
        </div>
        <div style="margin-top:10px">
          <label><span>รูปประกอบ (optional — เช่นรูปใบเสร็จ)</span>
            <input type="file" name="image" accept="image/*">
          </label>
          <?php if ($edit_ex && !empty($edit_ex['image_path'])): ?>
            <div class="thumb" style="margin-top:6px">
              <img src="../<?= e($edit_ex['image_path']) ?>" alt="รูปประกอบ" style="max-width:160px">
            </div>
            <label class="small" style="display:block;margin-top:4px">
              <input type="checkbox" name="remove_image" value="1"> ลบรูปประกอบนี้
            </label>
          <?php endif; ?>
        </div>
        <?php if ($edit_ex): ?>
          <p><a href="campaigns.php?action=edit&id=<?= (int)$c['id'] ?>#expenses" class="btn btn-sm">ยกเลิกการแก้ไข</a></p>
        <?php endif; ?>
      </form>

      <?php if ($expenses): ?>
        <table class="adm-table" style="margin-top:12px">
          <thead><tr><th>วันที่</th><th>รายการ</th><th>รูป</th><th class="num">จำนวน</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($expenses as $ex): ?>
              <tr <?= ($edit_ex && (int)$edit_ex['id'] === (int)$ex['id']) ? 'style="background:#fcf3d9"' : '' ?>>
                <td class="muted small"><?= e($ex['spent_at'] ? thai_datetime($ex['spent_at']) : '—') ?></td>
                <td><?= e($ex['description']) ?></td>
                <td>
                  <?php if (!empty($ex['image_path'])): ?>
                    <button type="button" class="slip-link" data-slip="../<?= e($ex['image_path']) ?>" title="คลิกดูเต็ม">
                      <img src="../<?= e($ex['image_path']) ?>" alt="รูป" loading="lazy">
                    </button>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td class="num">฿<?= thb($ex['amount']) ?></td>
                <td>
                  <a class="btn btn-sm" href="campaigns.php?action=edit&id=<?= (int)$c['id'] ?>&edit_expense=<?= (int)$ex['id'] ?>#expenses">แก้ไข</a>
                  <form method="post" style="display:inline" onsubmit="return confirm('ลบ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="del_expense">
                    <input type="hidden" name="expense_id" value="<?= (int)$ex['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><th colspan="3" class="num">รวม</th><th class="num">฿<?= thb($exp_total) ?></th><th></th></tr>
          </tfoot>
        </table>
      <?php endif; ?>
    <?php endif; ?>
    <?php
} else {
    $rows = db_all(
        'SELECT c.*,
                (SELECT COALESCE(SUM(amount),0) FROM donations d WHERE d.campaign_id = c.id AND d.status = "verified") AS total,
                (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id) AS rows_total,
                (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id AND d.status = "pending") AS pending,
                (SELECT COALESCE(SUM(amount),0) FROM expenses  e WHERE e.campaign_id = c.id) AS expense_total
           FROM campaigns c
       ORDER BY c.created_at DESC'
    );
    ?>
    <?php
      $auto_open_cid = isset($_GET['expenses_open']) ? (int)$_GET['expenses_open'] : 0;
      $edit_exp_id   = isset($_GET['edit_expense']) ? (int)$_GET['edit_expense'] : 0;
    ?>
    <div class="page-head">
      <h1>แคมเปญ</h1>
      <a href="campaigns.php?action=new" class="btn btn-primary btn-sm">+ สร้างใหม่</a>
    </div>
    <?php if (!$rows): ?>
      <div class="empty">ยังไม่มี — กด "สร้างใหม่"</div>
    <?php else: ?>
    <table class="adm-table">
      <thead><tr>
        <th>ชื่อ</th>
        <th>สถานะ</th>
        <th class="num">ยอดบริจาค</th>
        <th class="num">ค่าใช้จ่าย</th>
        <th class="num">ยอดปิดซอง</th>
        <th class="num">รวม/รอ</th>
        <th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $c):
          $donation_total = (float)$c['total'];
          $exp_total      = (float)$c['expense_total'];
          $net            = $donation_total - $exp_total;
        ?>
          <tr>
            <td>
              <a href="../campaign.php?slug=<?= e($c['slug']) ?>" target="_blank"><?= e($c['deceased_name']) ?></a>
              <?php if (!empty($c['relation'])): ?><div class="small muted"><?= e($c['relation']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $c['status']==='active'?'ok':'closed' ?>"><?= e($c['status']) ?></span></td>
            <td class="num">฿<?= thb($donation_total) ?></td>
            <td class="num">฿<?= thb($exp_total) ?></td>
            <td class="num"><strong>฿<?= thb($net) ?></strong></td>
            <td class="num"><?= (int)$c['rows_total'] ?> / <?= (int)$c['pending'] ?></td>
            <td class="actions">
              <div class="kebab">
                <button type="button" class="kebab-trigger" aria-label="เมนู">⋮</button>
                <div class="kebab-menu">
                  <a href="campaigns.php?action=edit&id=<?= (int)$c['id'] ?>">แก้ไข</a>
                  <button type="button" data-exp-modal="<?= (int)$c['id'] ?>">ค่าใช้จ่าย</button>
                  <a href="donations.php?campaign=<?= (int)$c['id'] ?>">ดูบริจาค</a>
                  <div class="kebab-sep"></div>
                  <form method="post" action="campaigns.php" onsubmit="return confirm('ส่งสรุปยอดแคมเปญนี้ไปยัง LINE?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="push_summary">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="return_to" value="campaigns.php">
                    <button type="submit">ส่ง LINE</button>
                  </form>
                  <a href="summary_card.php?campaign=<?= (int)$c['id'] ?>">การ์ดสรุปยอด PNG</a>
                  <a href="donor_card.php?campaign=<?= (int)$c['id'] ?>">การ์ดผู้บริจาคใหม่ PNG</a>
                  <a href="expense_card.php?campaign=<?= (int)$c['id'] ?>">การ์ดค่าใช้จ่าย PNG</a>
                  <div class="kebab-sep"></div>
                  <form method="post" onsubmit="return confirm('ลบแคมเปญและรายการบริจาคทั้งหมด?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="kebab-danger">ลบแคมเปญ</button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php // ----- expense modals ----- ?>
    <?php foreach ($rows as $c): ?>
      <?php
        $modal_exps = db_all('SELECT * FROM expenses WHERE campaign_id = ? ORDER BY COALESCE(spent_at, created_at) DESC, id DESC', [$c['id']]);
        $modal_tot = 0; foreach ($modal_exps as $ex) $modal_tot += (float)$ex['amount'];
        $modal_edit = null;
        if ($auto_open_cid === (int)$c['id'] && $edit_exp_id > 0) {
            foreach ($modal_exps as $ex) if ((int)$ex['id'] === $edit_exp_id) { $modal_edit = $ex; break; }
        }
        $modal_spent_at = $modal_edit && $modal_edit['spent_at']
            ? date('Y-m-d\TH:i', strtotime($modal_edit['spent_at'])) : '';
      ?>
      <div class="exp-modal" id="exp-modal-<?= (int)$c['id'] ?>" hidden>
        <div class="exp-modal-card">
          <button type="button" class="exp-modal-close" aria-label="ปิด">×</button>
          <h2>ค่าใช้จ่าย</h2>
          <p class="muted"><?= e($c['deceased_name']) ?> <?= $modal_exps ? ' · ' . count($modal_exps) . ' รายการ ฿' . thb($modal_tot) : '' ?></p>

          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="save_expense">
            <input type="hidden" name="from" value="modal">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <input type="hidden" name="expense_id" value="<?= $modal_edit ? (int)$modal_edit['id'] : 0 ?>">
            <?php if ($modal_edit): ?>
              <div class="small muted">กำลังแก้ไขค่าใช้จ่าย #<?= (int)$modal_edit['id'] ?></div>
            <?php endif; ?>
            <div class="exp-form-grid">
              <label><span>รายการ *</span>
                <input name="description" required maxlength="255" placeholder="เช่น พวงหรีด"
                       value="<?= e($modal_edit ? $modal_edit['description'] : '') ?>"></label>
              <label><span>จำนวนเงิน *</span>
                <input type="number" name="amount" step="0.01" min="0.01" required
                       value="<?= e($modal_edit ? $modal_edit['amount'] : '') ?>"></label>
              <label><span>วันที่</span>
                <input type="datetime-local" name="spent_at" value="<?= e($modal_spent_at) ?>"></label>
              <button class="btn btn-primary" type="submit"><?= $modal_edit ? 'บันทึก' : '+ เพิ่ม' ?></button>
            </div>
            <div style="margin-top:10px">
              <label><span>รูปประกอบ (optional)</span>
                <input type="file" name="image" accept="image/*">
              </label>
              <?php if ($modal_edit && !empty($modal_edit['image_path'])): ?>
                <div class="thumb" style="margin-top:6px">
                  <img src="../<?= e($modal_edit['image_path']) ?>" alt="รูปประกอบ" style="max-width:140px">
                </div>
                <label class="small" style="display:block;margin-top:4px">
                  <input type="checkbox" name="remove_image" value="1"> ลบรูปประกอบนี้
                </label>
              <?php endif; ?>
            </div>
            <?php if ($modal_edit): ?>
              <p style="margin-top:8px"><a href="campaigns.php?expenses_open=<?= (int)$c['id'] ?>" class="btn btn-sm">ยกเลิกการแก้ไข</a></p>
            <?php endif; ?>
          </form>

          <?php if ($modal_exps): ?>
            <table class="adm-table" style="margin-top:14px">
              <thead><tr><th>วันที่</th><th>รายการ</th><th>รูป</th><th class="num">จำนวน</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($modal_exps as $ex): ?>
                  <tr <?= ($modal_edit && (int)$modal_edit['id'] === (int)$ex['id']) ? 'style="background:#fcf3d9"' : '' ?>>
                    <td class="muted small"><?= e($ex['spent_at'] ? thai_datetime($ex['spent_at']) : '—') ?></td>
                    <td><?= e($ex['description']) ?></td>
                    <td>
                      <?php if (!empty($ex['image_path'])): ?>
                        <button type="button" class="slip-link" data-slip="../<?= e($ex['image_path']) ?>" title="คลิกดูเต็ม">
                          <img src="../<?= e($ex['image_path']) ?>" alt="รูป" loading="lazy">
                        </button>
                      <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="num">฿<?= thb($ex['amount']) ?></td>
                    <td>
                      <a class="btn btn-sm" href="campaigns.php?expenses_open=<?= (int)$c['id'] ?>&edit_expense=<?= (int)$ex['id'] ?>">แก้ไข</a>
                      <form method="post" style="display:inline" onsubmit="return confirm('ลบ?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="del_expense">
                        <input type="hidden" name="from" value="modal">
                        <input type="hidden" name="expense_id" value="<?= (int)$ex['id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr><th colspan="3" class="num">รวม</th><th class="num">฿<?= thb($modal_tot) ?></th><th></th></tr>
              </tfoot>
            </table>
          <?php else: ?>
            <p class="muted small" style="margin-top:12px">ยังไม่มีค่าใช้จ่าย</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <script>
    (function(){
      function open(id){
        var m = document.getElementById('exp-modal-' + id);
        if (!m) return;
        m.hidden = false;
        document.body.style.overflow = 'hidden';
      }
      function close(m){
        m.hidden = true;
        document.body.style.overflow = '';
      }
      document.querySelectorAll('[data-exp-modal]').forEach(function(btn){
        btn.addEventListener('click', function(){ open(btn.dataset.expModal); });
      });
      document.querySelectorAll('.exp-modal').forEach(function(m){
        m.addEventListener('click', function(e){
          if (e.target === m || e.target.classList.contains('exp-modal-close')) close(m);
        });
      });
      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
          document.querySelectorAll('.exp-modal').forEach(function(m){ if (!m.hidden) close(m); });
        }
      });
      // auto-open ถ้ามาจาก redirect หลัง save/delete
      <?php if ($auto_open_cid > 0): ?>
        open(<?= (int)$auto_open_cid ?>);
      <?php endif; ?>
    })();
    </script>
    <?php endif; ?>
    <?php
}

require __DIR__ . '/_footer.php';
?>
