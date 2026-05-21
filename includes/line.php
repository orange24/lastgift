<?php
// LINE Messaging API — push Flex Message ตอน admin verify donation
// อ่าน setting จาก table settings (key/value) — แก้ผ่าน admin/line.php

// คืน true ถ้าเปิดใช้งานและ config ครบ
function line_is_configured() {
    if (setting('line_enabled') !== '1') return false;
    if (trim(setting('line_channel_token')) === '') return false;
    if (trim(setting('line_target_id')) === '') return false;
    return true;
}

// ส่ง raw messages array ไปยัง LINE Push API
// return ['ok' => bool, 'status' => int, 'body' => string, 'error' => string|null]
function line_push_raw($messages) {
    $token  = trim(setting('line_channel_token'));
    $target = trim(setting('line_target_id'));
    if ($token === '' || $target === '') {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'ยังไม่ได้ตั้งค่า token หรือ target_id'];
    }
    if (!is_array($messages) || !$messages) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'messages ว่าง'];
    }
    if (count($messages) > 5) {
        $messages = array_slice($messages, 0, 5);
    }

    $payload = json_encode(
        ['to' => $target, 'messages' => $messages],
        JSON_UNESCAPED_UNICODE
    );

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $err ? $err : 'curl failed'];
    }
    return [
        'ok'     => ($status >= 200 && $status < 300),
        'status' => $status,
        'body'   => $body,
        'error'  => ($status >= 200 && $status < 300) ? null : $body,
    ];
}

// ส่งข้อความ text ธรรมดา (ใช้กับปุ่มทดสอบ)
function line_push_text($text) {
    return line_push_raw([
        ['type' => 'text', 'text' => (string)$text],
    ]);
}

// ----- Flex Message builder -----
// $context = [
//   'event_label'  => 'มีผู้ร่วมทำบุญใหม่' | 'รายการบริจาคถูกปฏิเสธ' ...
//   'campaign'     => array จาก table campaigns (ต้องมี deceased_name, relation, slug)
//   'total'        => float ยอด verified รวม
//   'donors'       => int จำนวน verified
//   'now_label'    => 'วันเวลาตอนนี้ ภาษาไทย'
//   'detail_url'   => URL ของหน้าแคมเปญ (จาก site_base_url) — null = ไม่โชว์ปุ่ม
// ]
function line_build_flex($context) {
    $event_label  = isset($context['event_label']) ? $context['event_label'] : 'แจ้งเตือน';
    $c            = isset($context['campaign']) ? $context['campaign'] : [];
    $name         = isset($c['deceased_name']) ? $c['deceased_name'] : '';
    $relation     = isset($c['relation']) ? trim($c['relation']) : '';
    $title        = $relation !== '' ? ($name . ' — ' . $relation) : $name;
    $total        = isset($context['total']) ? (float)$context['total'] : 0;
    $donors       = isset($context['donors']) ? (int)$context['donors'] : 0;
    $exp_total    = isset($context['expense_total']) ? (float)$context['expense_total'] : 0;
    $net_total    = isset($context['net_total']) ? (float)$context['net_total'] : ($total - $exp_total);
    $now_label    = isset($context['now_label']) ? $context['now_label'] : '';
    $detail_url   = isset($context['detail_url']) && $context['detail_url'] ? $context['detail_url'] : null;

    $alt = '✦ ' . $event_label . ' — ' . $name . ' ยอดปิดซอง ฿' . number_format($net_total, 2, '.', ',');

    $header_contents = [
        [
            'type'   => 'text',
            'text'   => '✦  แจ้งเตือนการบริจาค',
            'color'  => '#F7F5F1',
            'size'   => 'sm',
            'weight' => 'bold',
        ],
        [
            'type'   => 'text',
            'text'   => $event_label,
            'color'  => '#FFFFFF',
            'size'   => 'xl',
            'weight' => 'bold',
            'wrap'   => true,
            'margin' => 'sm',
        ],
    ];

    $body_contents = [
        [
            'type'   => 'text',
            'text'   => $title !== '' ? $title : '—',
            'weight' => 'bold',
            'size'   => 'lg',
            'wrap'   => true,
            'color'  => '#2B2A26',
        ],
        ['type' => 'separator', 'margin' => 'md', 'color' => '#E7E3DA'],
        [
            'type'   => 'box',
            'layout' => 'horizontal',
            'margin' => 'lg',
            'contents' => [
                ['type'=>'text','text'=>'ยอดบริจาค','size'=>'sm','color'=>'#8E8B81','flex'=>4,'gravity'=>'center'],
                ['type'=>'text','text'=>'฿' . number_format($total, 2, '.', ','),'size'=>'md','weight'=>'bold','color'=>'#2B2A26','align'=>'end','flex'=>5],
            ],
        ],
        [
            'type'   => 'box',
            'layout' => 'horizontal',
            'contents' => [
                ['type'=>'text','text'=>'ค่าใช้จ่าย','size'=>'sm','color'=>'#8E8B81','flex'=>4],
                ['type'=>'text','text'=>'฿' . number_format($exp_total, 2, '.', ','),'size'=>'md','weight'=>'bold','color'=>'#2B2A26','align'=>'end','flex'=>5],
            ],
        ],
        ['type' => 'separator', 'margin' => 'md', 'color' => '#E7E3DA'],
        [
            'type'   => 'box',
            'layout' => 'horizontal',
            'margin' => 'md',
            'contents' => [
                ['type'=>'text','text'=>'ยอดปิดซอง','size'=>'sm','color'=>'#8E8B81','flex'=>4,'gravity'=>'center'],
                ['type'=>'text','text'=>'฿' . number_format($net_total, 2, '.', ','),'size'=>'xl','weight'=>'bold','color'=>'#6D5128','align'=>'end','flex'=>5],
            ],
        ],
        [
            'type'   => 'box',
            'layout' => 'horizontal',
            'contents' => [
                ['type'=>'text','text'=>'ผู้บริจาค','size'=>'sm','color'=>'#8E8B81','flex'=>3],
                ['type'=>'text','text'=>number_format($donors) . ' ราย','size'=>'sm','color'=>'#5D5B54','align'=>'end','flex'=>6],
            ],
        ],
        [
            'type'   => 'box',
            'layout' => 'horizontal',
            'contents' => [
                ['type'=>'text','text'=>'ณ เวลา','size'=>'sm','color'=>'#8E8B81','flex'=>3],
                ['type'=>'text','text'=>$now_label,'size'=>'sm','color'=>'#5D5B54','align'=>'end','flex'=>6,'wrap'=>true],
            ],
        ],
    ];

    $bubble = [
        'type' => 'bubble',
        'size' => 'kilo',
        'header' => [
            'type' => 'box',
            'layout' => 'vertical',
            'backgroundColor' => '#8A6A3A',
            'paddingAll' => '20px',
            'contents' => $header_contents,
        ],
        'body' => [
            'type' => 'box',
            'layout' => 'vertical',
            'spacing' => 'md',
            'paddingAll' => '20px',
            'backgroundColor' => '#FFFFFF',
            'contents' => $body_contents,
        ],
    ];

    if ($detail_url) {
        $bubble['footer'] = [
            'type' => 'box',
            'layout' => 'vertical',
            'paddingAll' => '12px',
            'backgroundColor' => '#FFFFFF',
            'contents' => [
                [
                    'type'   => 'button',
                    'style'  => 'primary',
                    'color'  => '#8A6A3A',
                    'height' => 'sm',
                    'action' => [
                        'type'  => 'uri',
                        'label' => 'ดูรายละเอียด',
                        'uri'   => $detail_url,
                    ],
                ],
            ],
        ];
    }

    return [
        'type'     => 'flex',
        'altText'  => mb_substr($alt, 0, 400, 'UTF-8'),
        'contents' => $bubble,
    ];
}

// build URL ของหน้าแคมเปญจาก setting site_base_url + slug
// ถ้า site_base_url ว่าง → fallback auto-detect จาก $_SERVER (ใช้ host + ตำแหน่งโปรเจกต์ของ request ปัจจุบัน)
function line_campaign_url($slug) {
    if ($slug === '') return null;

    $base = trim(setting('site_base_url'));
    if ($base === '') {
        // auto-detect — ใช้ได้เฉพาะตอนที่มี HTTP request (admin หน้าเว็บ) ไม่ใช่ CLI
        if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) return null;

        $proto = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $proto = 'https';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))               $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];

        $host = $_SERVER['HTTP_HOST'];
        // ตัดชื่อไฟล์ + โฟลเดอร์ admin/ ออก ให้เหลือ base path ของโปรเจกต์
        // เช่น /lastgift/admin/donations.php → /lastgift
        //      /admin/donations.php         → ''
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $path   = preg_replace('#/(admin/)?[^/]+\.php$#', '', $script);

        $base = $proto . '://' . $host . $path;
    }
    return rtrim($base, '/') . '/campaign.php?slug=' . rawurlencode($slug);
}

// builder สำหรับ Flex Card เหตุการณ์ค่าใช้จ่ายใหม่
// $context = [
//   'campaign'   => row campaigns
//   'expense'    => ['description'=>..., 'amount'=>...]
//   'exp_total'  => float — รวมค่าใช้จ่ายทั้งหมดของแคมเปญหลัง insert
//   'net_total'  => float — verified - exp_total
//   'now_label'  => 'วันเวลาตอนนี้ ภาษาไทย'
//   'detail_url' => URL | null
// ]
function line_build_flex_expense($context) {
    $c          = isset($context['campaign']) ? $context['campaign'] : [];
    $name       = isset($c['deceased_name']) ? $c['deceased_name'] : '';
    $relation   = isset($c['relation']) ? trim($c['relation']) : '';
    $title      = $relation !== '' ? ($name . ' — ' . $relation) : $name;
    $exp        = isset($context['expense']) ? $context['expense'] : [];
    $desc       = isset($exp['description']) ? $exp['description'] : '';
    $amount     = isset($exp['amount']) ? (float)$exp['amount'] : 0;
    $exp_total  = isset($context['exp_total']) ? (float)$context['exp_total'] : 0;
    $net_total  = isset($context['net_total']) ? (float)$context['net_total'] : 0;
    $now_label  = isset($context['now_label']) ? $context['now_label'] : '';
    $detail_url = isset($context['detail_url']) && $context['detail_url'] ? $context['detail_url'] : null;

    $alt = '✦ บันทึกค่าใช้จ่ายใหม่ — ' . $name . ' ' . $desc . ' ฿' . number_format($amount, 2, '.', ',');

    $bubble = [
        'type' => 'bubble',
        'size' => 'kilo',
        'header' => [
            'type' => 'box', 'layout' => 'vertical',
            'backgroundColor' => '#8A6A3A', 'paddingAll' => '20px',
            'contents' => [
                ['type'=>'text','text'=>'✦  แจ้งเตือนค่าใช้จ่าย','color'=>'#F7F5F1','size'=>'sm','weight'=>'bold'],
                ['type'=>'text','text'=>'บันทึกค่าใช้จ่ายใหม่','color'=>'#FFFFFF','size'=>'xl','weight'=>'bold','wrap'=>true,'margin'=>'sm'],
            ],
        ],
        'body' => [
            'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md',
            'paddingAll' => '20px', 'backgroundColor' => '#FFFFFF',
            'contents' => [
                ['type'=>'text','text'=>$title !== '' ? $title : '—','weight'=>'bold','size'=>'lg','wrap'=>true,'color'=>'#2B2A26'],
                ['type'=>'separator','margin'=>'md','color'=>'#E7E3DA'],
                [
                    'type'=>'box','layout'=>'horizontal','margin'=>'lg',
                    'contents'=>[
                        ['type'=>'text','text'=>'รายการ','size'=>'sm','color'=>'#8E8B81','flex'=>3],
                        ['type'=>'text','text'=>$desc !== '' ? $desc : '—','size'=>'sm','weight'=>'bold','color'=>'#2B2A26','align'=>'end','flex'=>6,'wrap'=>true],
                    ],
                ],
                [
                    'type'=>'box','layout'=>'horizontal',
                    'contents'=>[
                        ['type'=>'text','text'=>'จำนวน','size'=>'sm','color'=>'#8E8B81','flex'=>4,'gravity'=>'center'],
                        ['type'=>'text','text'=>'฿' . number_format($amount, 2, '.', ','),'size'=>'xl','weight'=>'bold','color'=>'#6D5128','align'=>'end','flex'=>5],
                    ],
                ],
                ['type'=>'separator','margin'=>'md','color'=>'#E7E3DA'],
                [
                    'type'=>'box','layout'=>'horizontal','margin'=>'md',
                    'contents'=>[
                        ['type'=>'text','text'=>'รวมค่าใช้จ่าย','size'=>'sm','color'=>'#8E8B81','flex'=>4],
                        ['type'=>'text','text'=>'฿' . number_format($exp_total, 2, '.', ','),'size'=>'md','weight'=>'bold','color'=>'#2B2A26','align'=>'end','flex'=>5],
                    ],
                ],
                [
                    'type'=>'box','layout'=>'horizontal',
                    'contents'=>[
                        ['type'=>'text','text'=>'ยอดคงเหลือ','size'=>'sm','color'=>'#8E8B81','flex'=>4],
                        ['type'=>'text','text'=>'฿' . number_format($net_total, 2, '.', ','),'size'=>'md','weight'=>'bold','color'=>'#6D5128','align'=>'end','flex'=>5],
                    ],
                ],
                [
                    'type'=>'box','layout'=>'horizontal',
                    'contents'=>[
                        ['type'=>'text','text'=>'ณ เวลา','size'=>'sm','color'=>'#8E8B81','flex'=>3],
                        ['type'=>'text','text'=>$now_label,'size'=>'sm','color'=>'#5D5B54','align'=>'end','flex'=>6,'wrap'=>true],
                    ],
                ],
            ],
        ],
    ];

    if ($detail_url) {
        $bubble['footer'] = [
            'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '12px',
            'backgroundColor' => '#FFFFFF',
            'contents' => [
                [
                    'type'=>'button','style'=>'primary','color'=>'#8A6A3A','height'=>'sm',
                    'action'=>['type'=>'uri','label'=>'ดูรายละเอียด','uri'=>$detail_url],
                ],
            ],
        ];
    }

    return [
        'type'     => 'flex',
        'altText'  => mb_substr($alt, 0, 400, 'UTF-8'),
        'contents' => $bubble,
    ];
}

// Reply API — ใช้ reply token จาก webhook event (FREE, ไม่กิน quota)
// $replyToken: token จาก webhook event (อายุ 1 นาที ใช้ครั้งเดียว)
// $messages: array ของ messages (max 5)
function line_reply($replyToken, $messages) {
    $token = trim(setting('line_channel_token'));
    if ($token === '' || $replyToken === '' || !is_array($messages) || !$messages) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'reply args invalid'];
    }
    if (count($messages) > 5) $messages = array_slice($messages, 0, 5);

    $payload = json_encode([
        'replyToken' => $replyToken,
        'messages'   => $messages,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    $ok = ($status >= 200 && $status < 300);
    if (!$ok) {
        error_log('[LINE reply] failed (' . $status . '): ' . ($err ? $err : $body));
    }
    return [
        'ok'     => $ok,
        'status' => $status,
        'body'   => $body,
        'error'  => $ok ? null : ($err ? $err : $body),
    ];
}

// ส่งสรุปยอด ณ ขณะนี้ของแคมเปญ — กดเองจาก admin (ข้าม notify setting)
// return result จาก line_push_raw หรือ ['ok'=>false,'error'=>...] ถ้า config ไม่พร้อม
function line_push_summary($campaign) {
    if (!line_is_configured()) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'ยังไม่ได้ตั้งค่า LINE'];
    }
    if (!$campaign || empty($campaign['id'])) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'ไม่พบแคมเปญ'];
    }
    $cid       = (int)$campaign['id'];
    $total     = campaign_total_verified($cid);
    $donors    = campaign_count_verified($cid);
    $exp_total = campaign_expense_total($cid);
    $net_total = $total - $exp_total;
    $now_label = thai_datetime(time());
    $detail    = line_campaign_url(isset($campaign['slug']) ? $campaign['slug'] : '');

    $msg = line_build_flex([
        'event_label'   => 'สรุปยอดทำบุญ ณ ปัจจุบัน',
        'campaign'      => $campaign,
        'total'         => $total,
        'donors'        => $donors,
        'expense_total' => $exp_total,
        'net_total'     => $net_total,
        'now_label'     => $now_label,
        'detail_url'    => $detail,
    ]);
    return line_push_raw([$msg]);
}

// แจ้ง LINE หลัง insert ค่าใช้จ่ายใหม่
// return result จาก line_push_raw หรือ null ถ้า config ปิด/notify ปิด
function line_notify_expense($campaign, $expense) {
    if (!line_is_configured()) return null;
    // default = ON — บล็อกเฉพาะเมื่อ admin ตั้งเป็น "0" ชัดเจน
    if (setting('line_notify_on_expense') === '0') return null;
    if (!$campaign || empty($campaign['id'])) return null;

    $cid       = (int)$campaign['id'];
    $exp_total = campaign_expense_total($cid);
    $net_total = campaign_total_verified($cid) - $exp_total;
    $detail    = line_campaign_url(isset($campaign['slug']) ? $campaign['slug'] : '');

    $msg = line_build_flex_expense([
        'campaign'   => $campaign,
        'expense'    => $expense,
        'exp_total'  => $exp_total,
        'net_total'  => $net_total,
        'now_label'  => thai_datetime(time()),
        'detail_url' => $detail,
    ]);

    $res = line_push_raw([$msg]);
    if (!$res['ok']) {
        error_log('[LINE] expense push failed (' . $res['status'] . '): ' . $res['error']);
    }
    return $res;
}

// ส่ง flex แจ้งเตือนหลังเปลี่ยน status (verify/reject)
// $campaign  = row campaigns
// $event_kind = 'verified' | 'rejected'
// return result เดียวกับ line_push_raw หรือ null ถ้าไม่ได้ส่ง (config ปิด/ไม่ตั้งค่า/notify ปิด)
function line_notify_donation($campaign, $event_kind) {
    if (!line_is_configured()) return null;

    // verified: default ON (block เมื่อ "0"); rejected: default OFF (เปิดเฉพาะเมื่อ "1")
    if ($event_kind === 'verified' && setting('line_notify_on_verify') === '0') return null;
    if ($event_kind === 'rejected' && setting('line_notify_on_reject') !== '1') return null;
    if (!in_array($event_kind, ['verified', 'rejected'], true)) return null;
    if (!$campaign || empty($campaign['id'])) return null;

    $event_label = $event_kind === 'verified' ? 'มีผู้ร่วมทำบุญใหม่' : 'รายการบริจาคถูกปฏิเสธ';
    $cid         = (int)$campaign['id'];
    $total       = campaign_total_verified($cid);
    $donors      = campaign_count_verified($cid);
    $exp_total   = campaign_expense_total($cid);
    $net_total   = $total - $exp_total;
    $now_label   = thai_datetime(time());
    $detail_url  = line_campaign_url(isset($campaign['slug']) ? $campaign['slug'] : '');

    $msg = line_build_flex([
        'event_label'   => $event_label,
        'campaign'      => $campaign,
        'total'         => $total,
        'donors'        => $donors,
        'expense_total' => $exp_total,
        'net_total'     => $net_total,
        'now_label'     => $now_label,
        'detail_url'    => $detail_url,
    ]);

    $res = line_push_raw([$msg]);
    if (!$res['ok']) {
        error_log('[LINE] push failed (' . $res['status'] . '): ' . $res['error']);
    }
    return $res;
}
