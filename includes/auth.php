<?php
// auth helpers (super admin เดียว)

function admin_login($username, $password) {
    $row = db_one('SELECT * FROM admins WHERE username = ?', [$username]);
    if (!$row) return false;
    if (!password_verify($password, $row['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['admin_id']   = (int)$row['id'];
    $_SESSION['admin_name'] = $row['display_name'];
    return true;
}

function admin_logout() {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    session_regenerate_id(true);
}

function admin_id() {
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
}

function admin_required() {
    if (!admin_id()) {
        $back = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/admin/';
        redirect('login.php?back=' . urlencode($back));
    }
}
