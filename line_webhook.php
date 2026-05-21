<?php
// LINE Webhook receiver — รับ event จาก LINE แล้วตอบ reply (ฟรี ไม่กิน quota)
//
// Public URL ตัวอย่าง: https://yourdomain/line_webhook.php
// ตั้งใน LINE Developers Console → Messaging API → Webhook URL
// แล้วเปิด "Use webhook" + ปิด "Auto-reply messages"
//
// คำสั่งที่ตอบ:
//   /ยอด, ยอด, /summary, สรุป, สรุปยอด → ส่ง Flex card สรุปแคมเปญล่าสุด
//   /แคมเปญ, แคมเปญ                    → list แคมเปญทั้งหมด
//   /help, ?, ช่วย                       → list คำสั่ง

require __DIR__ . '/includes/bootstrap.php';

// LINE retry ทุก non-200 → ต้องส่ง 200 เสมอ ห้าม throw
header('Content-Type: text/plain; charset=utf-8');

$raw = file_get_contents('php://input');
$sig = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : '';

$secret = trim(setting('line_channel_secret'));
if ($secret === '') {
    error_log('[LINE webhook] missing line_channel_secret in settings');
    http_response_code(200);
    echo 'config missing';
    exit;
}

// verify HMAC-SHA256 signature
$expected = base64_encode(hash_hmac('sha256', $raw, $secret, true));
if (!hash_equals($expected, $sig)) {
    error_log('[LINE webhook] signature mismatch');
    http_response_code(403);
    echo 'bad signature';
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data) || empty($data['events'])) {
    http_response_code(200);
    echo 'no events';
    exit;
}

foreach ($data['events'] as $event) {
    try {
        handle_event($event);
    } catch (Exception $e) {
        error_log('[LINE webhook] event error: ' . $e->getMessage());
    }
}

http_response_code(200);
echo 'ok';
exit;

// ----- handlers -----

function handle_event($event) {
    if (!isset($event['type']) || $event['type'] !== 'message') return;
    if (!isset($event['message']['type']) || $event['message']['type'] !== 'text') return;
    if (empty($event['replyToken'])) return;

    $text = trim((string)$event['message']['text']);
    $reply_token = $event['replyToken'];

    // normalize: strip leading /, spaces
    $cmd = ltrim($text, '/');
    $cmd = preg_replace('/\s+/u', '', $cmd);
    $cmd = mb_strtolower($cmd, 'UTF-8');

    if (in_array($cmd, ['ยอด', 'สรุป', 'สรุปยอด', 'summary', 'total'], true)) {
        reply_summary($reply_token);
    } elseif (in_array($cmd, ['แคมเปญ', 'campaigns', 'list'], true)) {
        reply_campaign_list($reply_token);
    } elseif (in_array($cmd, ['help', 'ช่วย', '?', 'commands', 'คำสั่ง'], true)) {
        reply_help($reply_token);
    }
    // else: ignore (ไม่ตอบกลับเมื่อพิมพ์อะไรอื่น)
}

function reply_summary($reply_token) {
    // หยิบแคมเปญ active ตัวล่าสุด ถ้าไม่มี → ตัวล่าสุดทั่วไป
    $c = db_one('SELECT * FROM campaigns WHERE status = "active" ORDER BY created_at DESC LIMIT 1');
    if (!$c) $c = db_one('SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 1');
    if (!$c) {
        line_reply($reply_token, [['type' => 'text', 'text' => 'ยังไม่มีแคมเปญในระบบ']]);
        return;
    }

    $cid       = (int)$c['id'];
    $total     = campaign_total_verified($cid);
    $donors    = campaign_count_verified($cid);
    $exp_total = campaign_expense_total($cid);
    $net_total = $total - $exp_total;
    $detail    = line_campaign_url(isset($c['slug']) ? $c['slug'] : '');

    $msg = line_build_flex([
        'event_label'   => 'สรุปยอดทำบุญ',
        'campaign'      => $c,
        'total'         => $total,
        'donors'        => $donors,
        'expense_total' => $exp_total,
        'net_total'     => $net_total,
        'now_label'     => thai_datetime(time()),
        'detail_url'    => $detail,
    ]);
    line_reply($reply_token, [$msg]);
}

function reply_campaign_list($reply_token) {
    $rows = db_all(
        'SELECT c.deceased_name, c.relation, c.status,
                (SELECT COALESCE(SUM(amount),0) FROM donations d WHERE d.campaign_id = c.id AND d.status = "verified") AS total,
                (SELECT COALESCE(SUM(amount),0) FROM expenses  e WHERE e.campaign_id = c.id) AS expense_total
           FROM campaigns c
       ORDER BY c.status = "active" DESC, c.created_at DESC
          LIMIT 10'
    );
    if (!$rows) {
        line_reply($reply_token, [['type' => 'text', 'text' => 'ยังไม่มีแคมเปญในระบบ']]);
        return;
    }
    $lines = ['✦ แคมเปญในระบบ'];
    foreach ($rows as $r) {
        $net = (float)$r['total'] - (float)$r['expense_total'];
        $name = $r['deceased_name'];
        if (!empty($r['relation'])) $name .= ' — ' . $r['relation'];
        $status_mark = $r['status'] === 'active' ? '●' : '○';
        $lines[] = $status_mark . ' ' . $name;
        $lines[] = '   ยอดปิดซอง ฿' . number_format($net, 2, '.', ',');
    }
    $lines[] = '';
    $lines[] = '(พิมพ์ /ยอด เพื่อดูการ์ดสรุปแคมเปญล่าสุด)';
    line_reply($reply_token, [['type' => 'text', 'text' => implode("\n", $lines)]]);
}

function reply_help($reply_token) {
    $text = "คำสั่งที่ใช้ได้:\n\n"
          . "/ยอด — สรุปยอดแคมเปญล่าสุด (Flex card)\n"
          . "/แคมเปญ — รายชื่อแคมเปญทั้งหมด\n"
          . "/help — แสดงเมนูนี้";
    line_reply($reply_token, [['type' => 'text', 'text' => $text]]);
}
