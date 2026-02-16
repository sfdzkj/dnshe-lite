<?php
// lib/db.php

declare(strict_types=1);

function db(array $cfg): SQLite3 {
    static $db = null;
    if ($db instanceof SQLite3) return $db;

    $path = $cfg['db_path'];
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $isNew = !file_exists($path);
    $db = new SQLite3($path);
    $db->enableExceptions(true);

    // 如果文件存在但尚未初始化（例如打包了空 .db），也执行初始化
    if (!$isNew) {
        try {
            $chk = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            if ($chk === null) $isNew = true;
        } catch (Throwable $e) {
            $isNew = true;
        }
    }

    if ($isNew) {
        require_once __DIR__ . '/install.php';
        install_db($db, $cfg);
    }

    return $db;
}

function db_stmt(SQLite3 $db, string $sql, array $params=[]): SQLite3Stmt {
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $type = SQLITE3_TEXT;
        if (is_int($v)) $type = SQLITE3_INTEGER;
        elseif (is_float($v)) $type = SQLITE3_FLOAT;
        elseif ($v === null) $type = SQLITE3_NULL;
        $name = is_int($k) ? $k+1 : ':' . ltrim((string)$k, ':');
        $stmt->bindValue($name, $v, $type);
    }
    return $stmt;
}

function db_exec(SQLite3 $db, string $sql, array $params=[]): void {
    $stmt = db_stmt($db, $sql, $params);
    $stmt->execute();
}

function db_row(SQLite3 $db, string $sql, array $params=[]): ?array {
    $stmt = db_stmt($db, $sql, $params);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function db_all(SQLite3 $db, string $sql, array $params=[]): array {
    $stmt = db_stmt($db, $sql, $params);
    $res = $stmt->execute();
    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
    return $rows;
}
