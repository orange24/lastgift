<?php
// Copy นี้เป็น config.php แล้วแก้ค่าให้ตรงกับ host จริง
// อย่า commit config.php ขึ้น git

return [
    'db' => [
        'host'     => 'localhost',
        'name'     => 'your_db_name',
        'user'     => 'your_db_user',
        'pass'     => 'your_db_password',
        'charset'  => 'utf8mb4',
    ],

    // สำหรับ hash IP ของผู้บริจาค (rate-limit) ห้ามใช้ค่าเดียวกับ key อื่น
    'ip_hash_salt' => 'CHANGE-ME-TO-RANDOM-32-CHARS',

    // upload limits
    'max_upload_bytes' => 5 * 1024 * 1024, // 5 MB
    'allowed_mime' => [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ],

    // คุกกี้ session — เปิด secure ถ้า host ใช้ https
    'session_secure' => true,

    // ตั้งเป็น false ถ้าอยู่หลัง proxy ที่ส่ง X-Forwarded-Proto
    'force_https' => true,

    // path เก็บสลิป (relative จาก document root โปรเจกต์)
    'upload_dir' => __DIR__ . '/../uploads/slips',
    'upload_url' => 'uploads/slips',

    'hero_dir'   => __DIR__ . '/../uploads/heroes',
    'hero_url'   => 'uploads/heroes',
    'qr_dir'     => __DIR__ . '/../uploads/qr',
    'qr_url'     => 'uploads/qr',
    'gallery_dir' => __DIR__ . '/../uploads/campaigns',
    'gallery_url' => 'uploads/campaigns',
    'expense_dir' => __DIR__ . '/../uploads/expenses',
    'expense_url' => 'uploads/expenses',

    'timezone' => 'Asia/Bangkok',
];
