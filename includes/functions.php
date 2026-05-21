<?php
// helpers

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// asset URL with version (cache-bust ตามไฟล์ mtime)
// $rel = path relative to project root, e.g. "assets/css/style.css"
// $prefix = สำหรับหน้า admin ต้องส่ง "../" มา
function asset($rel, $prefix = '') {
    $abs = __DIR__ . '/../' . $rel;
    $v   = file_exists($abs) ? filemtime($abs) : time();
    return $prefix . $rel . '?v=' . $v;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check() {
    $given = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if (!is_string($given) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $given)) {
        http_response_code(400);
        exit('CSRF token invalid');
    }
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT name, value FROM settings') as $row) {
            $cache[$row['name']] = $row['value'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function setting_set($key, $value) {
    db_run(
        'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
        [$key, $value]
    );
}

function thb($n) {
    return number_format((float)$n, 2, '.', ',');
}

function thai_datetime($ts) {
    if (!$ts) return '';
    $t = is_numeric($ts) ? (int)$ts : strtotime($ts);
    if ($t === false) return '';
    $months = ['', 'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $d  = (int)date('j', $t);
    $m  = (int)date('n', $t);
    $y  = (int)date('Y', $t) + 543;
    $hm = date('H:i', $t);
    return $d . ' ' . $months[$m] . ' ' . $y . ' ' . $hm . ' น.';
}

function slugify($text) {
    // เก็บอักษรไทย ตัด space เป็น dash, ลบสัญลักษณ์
    $text = trim($text);
    $text = preg_replace('/\s+/u', '-', $text);
    $text = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    if ($text === '') $text = 'campaign-' . bin2hex(openssl_random_pseudo_bytes(3));
    return mb_strtolower(mb_substr($text, 0, 80, 'UTF-8'), 'UTF-8');
}

function flash($key, $msg = null) {
    if ($msg === null) {
        if (isset($_SESSION['_flash'][$key])) {
            $v = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $v;
        }
        return null;
    }
    $_SESSION['_flash'][$key] = $msg;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function client_ip_hash() {
    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
        : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $salt = isset($GLOBALS['CONFIG']['ip_hash_salt']) ? $GLOBALS['CONFIG']['ip_hash_salt'] : '';
    return hash('sha256', $ip . '|' . $salt);
}

function handle_upload($field, $destDir, $destUrlBase) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . $f['error'] . ')'];
    }
    $max = isset($GLOBALS['CONFIG']['max_upload_bytes']) ? $GLOBALS['CONFIG']['max_upload_bytes'] : 5*1024*1024;
    if ($f['size'] > $max) {
        return ['error' => 'ไฟล์ใหญ่เกิน ' . ($max / 1024 / 1024) . ' MB'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']);
    $allow = $GLOBALS['CONFIG']['allowed_mime'];
    if (!isset($allow[$mime])) {
        return ['error' => 'รองรับเฉพาะรูป JPG / PNG / WEBP / GIF'];
    }
    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0775, true)) {
            return ['error' => 'สร้างโฟลเดอร์ไม่ได้: ' . $destDir];
        }
    }
    if (!is_writable($destDir)) {
        return ['error' => 'โฟลเดอร์เขียนไม่ได้ (chmod 777): ' . $destDir];
    }
    $ext  = $allow[$mime];
    $name = date('Ymd_His') . '_' . bin2hex(openssl_random_pseudo_bytes(8)) . '.' . $ext;
    $abs  = rtrim($destDir, '/') . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $abs)) {
        $err = error_get_last();
        return ['error' => 'ย้ายไฟล์ที่อัปโหลดไม่สำเร็จ: ' . (isset($err['message']) ? $err['message'] : 'unknown')];
    }
    @chmod($abs, 0644);
    return ['path' => rtrim($destUrlBase, '/') . '/' . $name, 'abs' => $abs];
}

function campaign_total_verified($campaignId) {
    $r = db_one('SELECT COALESCE(SUM(amount),0) AS total FROM donations WHERE campaign_id = ? AND status = ?',
        [$campaignId, 'verified']);
    return (float)$r['total'];
}

function campaign_count_verified($campaignId) {
    $r = db_one('SELECT COUNT(*) AS c FROM donations WHERE campaign_id = ? AND status = ?',
        [$campaignId, 'verified']);
    return (int)$r['c'];
}

function campaign_expense_total($campaignId) {
    $r = db_one('SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE campaign_id = ?',
        [$campaignId]);
    return (float)$r['total'];
}
