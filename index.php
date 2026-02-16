<?php
// index.php - 单入口

declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
date_default_timezone_set($cfg['timezone'] ?? 'Asia/Shanghai');

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/security.php';

session_boot($cfg);

$page = $_GET['page'] ?? 'dashboard';
$publicPages = ['login','register','logout'];

if (!in_array($page, $publicPages, true)) {
    $u = require_login($cfg);
    if ((int)($u['must_change_password'] ?? 0) === 1 && $page !== 'change_password') {
        header('Location: index.php?page=change_password');
        exit;
    }
}

$file = __DIR__ . '/pages/' . basename($page) . '.php';
if (!file_exists($file)) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

require $file;
