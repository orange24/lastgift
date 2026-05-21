<?php
// bootstrap.php — โหลด config, db, helper, session
// ทุกหน้า (public + admin) เริ่มจาก require ไฟล์นี้

if (!defined('LG_BOOTSTRAPPED')) {
    define('LG_BOOTSTRAPPED', true);

    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        http_response_code(500);
        echo 'ยังไม่ได้ตั้งค่า: copy includes/config.example.php เป็น includes/config.php แล้วแก้ค่าให้ตรง';
        exit;
    }

    $CONFIG = require $configPath;
    $GLOBALS['CONFIG'] = $CONFIG;

    date_default_timezone_set(isset($CONFIG['timezone']) ? $CONFIG['timezone'] : 'Asia/Bangkok');

    // บังคับ HTTPS ถ้าเปิดไว้
    if (!empty($CONFIG['force_https'])) {
        $proto = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }
        if ($proto !== 'https' && PHP_SAPI !== 'cli') {
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            header('Location: https://' . $host . $uri, true, 301);
            exit;
        }
    }

    // session cookie ปลอดภัย
    if (session_status() === PHP_SESSION_NONE) {
        $secure = !empty($CONFIG['session_secure']);
        session_set_cookie_params(0, '/', '', $secure, true);
        session_name('LGSESS');
        session_start();
    }

    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/promptpay.php';
    require_once __DIR__ . '/line.php';
}
