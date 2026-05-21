<?php
$page_title = 'แจ้งเตือน LINE';
require __DIR__ . '/_layout.php';

$test_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = isset($_POST['op']) ? $_POST['op'] : '';

    if ($op === 'save') {
        $bool_keys = ['line_enabled', 'line_notify_on_verify', 'line_notify_on_reject', 'line_notify_on_expense'];
        foreach ($bool_keys as $k) {
            setting_set($k, !empty($_POST[$k]) ? '1' : '0');
        }
        setting_set('line_channel_token',  trim(isset($_POST['line_channel_token'])  ? $_POST['line_channel_token']  : ''));
        setting_set('line_channel_secret', trim(isset($_POST['line_channel_secret']) ? $_POST['line_channel_secret'] : ''));
        setting_set('line_target_id',      trim(isset($_POST['line_target_id'])      ? $_POST['line_target_id']      : ''));
        $tt = isset($_POST['line_target_type']) ? $_POST['line_target_type'] : 'group';
        if (!in_array($tt, ['group','user','room'], true)) $tt = 'group';
        setting_set('line_target_type', $tt);
        setting_set('site_base_url', trim(isset($_POST['site_base_url']) ? $_POST['site_base_url'] : ''));
        flash('ok', 'บันทึกแล้ว');
        redirect('line.php');
    }

    if ($op === 'test_text') {
        $res = line_push_text('✦ ทดสอบการแจ้งเตือนจาก ' . setting('site_title') . ' — ส่งสำเร็จ');
        $test_result = $res;
    }

    if ($op === 'test_flex') {
        // ใช้แคมเปญ active ตัวล่าสุดเป็นข้อมูล demo; ถ้าไม่มี campaign เลย → mock
        $c = db_one('SELECT * FROM campaigns WHERE status = "active" ORDER BY created_at DESC LIMIT 1');
        if (!$c) {
            $c = db_one('SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 1');
        }
        if (!$c) {
            $c = ['id' => 0, 'slug' => '', 'deceased_name' => 'ตัวอย่างชื่อแคมเปญ', 'relation' => 'ห้อง A'];
            $total = 15300.00; $donors = 42; $exp_total = 3500.00;
        } else {
            $total     = campaign_total_verified((int)$c['id']);
            $donors    = campaign_count_verified((int)$c['id']);
            $exp_total = campaign_expense_total((int)$c['id']);
        }
        $msg = line_build_flex([
            'event_label'   => '(ทดสอบ) มีผู้ร่วมทำบุญใหม่',
            'campaign'      => $c,
            'total'         => $total,
            'donors'        => $donors,
            'expense_total' => $exp_total,
            'net_total'     => $total - $exp_total,
            'now_label'     => thai_datetime(time()),
            'detail_url'    => line_campaign_url(isset($c['slug']) ? $c['slug'] : ''),
        ]);
        $res = line_push_raw([$msg]);
        $test_result = $res;
    }
}

$line_enabled        = setting('line_enabled') === '1';
$line_token          = setting('line_channel_token');
$line_secret         = setting('line_channel_secret');
$line_target_id      = setting('line_target_id');
$line_target_type    = setting('line_target_type');
if ($line_target_type === '') $line_target_type = 'group';
$notify_on_verify    = setting('line_notify_on_verify');
if ($notify_on_verify === '') $notify_on_verify = '1'; // default เปิด
$notify_on_reject    = setting('line_notify_on_reject');
$notify_on_expense   = setting('line_notify_on_expense');
if ($notify_on_expense === '') $notify_on_expense = '1'; // default เปิด
$site_base_url       = setting('site_base_url');
// "ทดสอบ" ใช้แค่ token + target พร้อม (ไม่ต้องเปิด enabled) — admin จะได้ลอง config ก่อน enable จริง
$can_test            = trim($line_token) !== '' && trim($line_target_id) !== '';
?>
<h1>แจ้งเตือนผ่าน LINE</h1>
<p class="muted">เมื่อ admin กดยืนยันรายการบริจาค ระบบจะส่งการ์ดสรุปยอดไปยังกลุ่ม/บัญชี LINE ที่ระบุ</p>

<?php if ($test_result !== null): ?>
  <?php if ($test_result['ok']): ?>
    <div class="flash flash-ok">✓ ส่งข้อความทดสอบสำเร็จ (HTTP <?= (int)$test_result['status'] ?>)</div>
  <?php else: ?>
    <div class="flash flash-err">
      ✗ ส่งไม่สำเร็จ (HTTP <?= (int)$test_result['status'] ?>)<br>
      <small><?= e($test_result['error']) ?></small>
    </div>
  <?php endif; ?>
<?php endif; ?>

<form method="post" class="adm-form">
  <?= csrf_field() ?>
  <input type="hidden" name="op" value="save">

  <label class="checkbox-line">
    <input type="checkbox" name="line_enabled" value="1" <?= $line_enabled?'checked':'' ?>>
    <span>เปิดใช้งานแจ้งเตือน LINE</span>
  </label>

  <label>
    <span>Channel Access Token (Long-lived)</span>
    <input type="password" name="line_channel_token" autocomplete="off"
           value="<?= e($line_token) ?>"
           placeholder="วาง token จาก LINE Developers Console">
    <small class="muted">ใน LINE Developers → Messaging API → Channel access token (long-lived)</small>
  </label>

  <label>
    <span>Channel Secret (สำหรับ webhook /ยอด)</span>
    <input type="password" name="line_channel_secret" autocomplete="off"
           value="<?= e($line_secret) ?>"
           placeholder="วาง channel secret จาก LINE Developers Console">
    <small class="muted">ใน LINE Developers → Basic settings → Channel secret — ใช้ verify ว่า request จาก LINE จริงๆ</small>
  </label>

  <div class="row">
    <label>
      <span>ปลายทาง ID</span>
      <input type="text" name="line_target_id"
             value="<?= e($line_target_id) ?>"
             placeholder="Cxxxxxxxxxxxx (group) / Uxxxxxx (user) / Rxxxxx (room)">
    </label>
    <label>
      <span>ประเภทปลายทาง</span>
      <select name="line_target_type">
        <?php foreach (['group'=>'กลุ่ม (group)','user'=>'ผู้ใช้ (user)','room'=>'ห้อง (room)'] as $v=>$lb): ?>
          <option value="<?= e($v) ?>" <?= $line_target_type===$v?'selected':'' ?>><?= e($lb) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <fieldset>
    <legend>เงื่อนไขการส่ง</legend>
    <label class="checkbox-line">
      <input type="checkbox" name="line_notify_on_verify" value="1" <?= $notify_on_verify==='1'?'checked':'' ?>>
      <span>ส่งเมื่อ <strong>ยืนยัน</strong> รายการบริจาค (default)</span>
    </label>
    <label class="checkbox-line">
      <input type="checkbox" name="line_notify_on_reject" value="1" <?= $notify_on_reject==='1'?'checked':'' ?>>
      <span>ส่งเมื่อ <strong>ปฏิเสธ</strong> รายการบริจาค</span>
    </label>
    <label class="checkbox-line">
      <input type="checkbox" name="line_notify_on_expense" value="1" <?= $notify_on_expense==='1'?'checked':'' ?>>
      <span>ส่งเมื่อ <strong>เพิ่มค่าใช้จ่ายใหม่</strong> (default)</span>
    </label>
  </fieldset>

  <label>
    <span>URL หน้าเว็บ (สำหรับปุ่ม "ดูรายละเอียดแคมเปญ") — optional</span>
    <input type="text" name="site_base_url"
           value="<?= e($site_base_url) ?>"
           placeholder="เว้นว่างได้ — ระบบจะ detect จาก request ของ admin อัตโนมัติ">
    <small class="muted">
      เว้นว่าง = ระบบจะ detect URL จากโดเมนที่ admin เข้ามาตอนกดยืนยัน (รองรับทั้ง root และ subfolder)
      <br>ใส่เองเฉพาะเมื่ออยู่หลัง proxy ที่ส่ง host header แปลก หรืออยากบังคับให้ลิงก์เป็นโดเมนหลักเสมอ
    </small>
  </label>

  <button class="btn btn-primary" type="submit">บันทึก</button>
</form>

<h2 style="margin-top:28px">ทดสอบการส่ง</h2>
<?php if (!$can_test): ?>
  <div class="notice">
    ยังตั้งค่าไม่ครบ — ต้องใส่ token + target ID และกด "บันทึก" ก่อน จึงจะส่งทดสอบได้
    (ไม่ต้องเปิด "เปิดใช้งาน" ก็ทดสอบได้)
  </div>
<?php else: ?>
  <div class="line-test-row">
    <form method="post" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="test_text">
      <button type="submit" class="btn">ส่งข้อความ text ทดสอบ</button>
    </form>
    <form method="post" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="test_flex">
      <button type="submit" class="btn btn-primary">ส่ง Flex Card ทดสอบ</button>
    </form>
  </div>
<?php endif; ?>

<h2 style="margin-top:28px">Webhook (สำหรับ /ยอด — ฟรี ไม่กิน quota)</h2>
<?php
$wh_base = trim(setting('site_base_url'));
if ($wh_base === '' && !empty($_SERVER['HTTP_HOST'])) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    $path = preg_replace('#/(admin/)?[^/]+\.php$#', '', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    $wh_base = $proto . '://' . $_SERVER['HTTP_HOST'] . $path;
}
$wh_url = rtrim($wh_base, '/') . '/line_webhook.php';
?>
<div class="webhook-box">
  <p><strong>Webhook URL:</strong></p>
  <code class="webhook-url"><?= e($wh_url) ?></code>
  <p class="small muted" style="margin-top:8px">คัด URL นี้ไปวางใน LINE Developers Console</p>
</div>

<ol class="howto">
  <li>เปิด <a href="https://developers.line.biz/console/" target="_blank" rel="noopener">LINE Developers Console</a> → channel ของคุณ</li>
  <li>แท็บ <strong>Messaging API</strong> → ช่อง <strong>Webhook URL</strong> → ใส่ URL ด้านบน แล้วกด <strong>Verify</strong></li>
  <li>เปิด <strong>Use webhook</strong> (toggle ON)</li>
  <li>ปิด <strong>Auto-reply messages</strong> (response settings) เพื่อไม่ให้ขัดกับ webhook</li>
  <li>เชิญบอทเข้ากลุ่ม → ใน LINE OA Manager → Settings → Response settings → เปิด <strong>Allow joining groups and multi-person chats</strong></li>
  <li>ในกลุ่ม พิมพ์ <code>/ยอด</code> หรือ <code>ยอด</code> → บอทจะ reply Flex card ทันที</li>
</ol>

<p class="muted small">คำสั่งที่ใช้ได้: <code>/ยอด</code> (สรุปแคมเปญล่าสุด) · <code>/แคมเปญ</code> (รายชื่อทั้งหมด) · <code>/help</code> (เมนู)</p>

<h2 style="margin-top:28px">วิธีหา Group ID</h2>
<ol class="howto">
  <li>สร้าง LINE Official Account / Messaging API channel ใน <a href="https://developers.line.biz/console/" target="_blank" rel="noopener">LINE Developers Console</a></li>
  <li>เปิด <strong>Allow bot to join group chats</strong> ใน Channel settings</li>
  <li>เชิญบอทเข้ากลุ่ม</li>
  <li>ตั้ง Webhook URL ชั่วคราว (เช่นใช้ <a href="https://webhook.site/" target="_blank" rel="noopener">webhook.site</a>) แล้วเปิด <strong>Use webhook</strong></li>
  <li>พิมพ์อะไรในกลุ่ม → ดู payload ใน webhook → คัด <code>source.groupId</code> (ขึ้นต้นด้วย C) มาวางช่อง "ปลายทาง ID"</li>
  <li>เอา Webhook URL ออก/ปิด หลังได้ ID แล้ว (ระบบนี้ไม่ใช้ webhook — push อย่างเดียว)</li>
</ol>

<style>
.checkbox-line{display:flex;align-items:center;gap:10px}
.checkbox-line input[type=checkbox]{width:18px;height:18px;margin:0}
.checkbox-line > span{margin:0 !important;font-size:14px !important;color:var(--ink) !important}
.line-test-row{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}
.howto{line-height:1.9;color:var(--ink-soft);font-size:14px}
.howto code{background:#f4f1ea;padding:1px 6px;border-radius:4px;font-size:13px}
.webhook-box{background:#fdf6e3;border:1px solid #e6d9a7;border-radius:8px;padding:14px 16px;margin:12px 0}
.webhook-url{display:inline-block;background:#fff;border:1px solid var(--line);
  padding:6px 10px;border-radius:6px;font-size:13px;color:var(--accent-dark);
  word-break:break-all;user-select:all}
</style>

<?php require __DIR__ . '/_footer.php'; ?>
