<?php
// PDO singleton
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['CONFIG']['db'];
        $dsn = 'mysql:host=' . $c['host']
             . (isset($c['port']) && $c['port'] ? ';port=' . (int)$c['port'] : '')
             . ';dbname=' . $c['name']
             . ';charset=' . $c['charset'];
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone='+07:00'",
            ]);
        } catch (PDOException $e) {
            error_log('DB connect failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed');
        }
    }
    return $pdo;
}

function db_one($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_all($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_run($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_insert_id() {
    return db()->lastInsertId();
}
