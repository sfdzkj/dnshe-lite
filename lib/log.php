<?php
// lib/log.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function add_log(array $cfg, ?int $userId, ?int $accountId, ?string $domain, string $type, string $action, string $result, string $message=''): void {
    $db = db($cfg);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    db_exec($db, 'INSERT INTO logs(user_id,account_id,domain,action_type,action,result,message,created_at,ip,ua)
                  VALUES(:uid,:aid,:dom,:t,:a,:r,:m,:c,:ip,:ua)', [
        'uid'=>$userId,
        'aid'=>$accountId,
        'dom'=>$domain,
        't'=>$type,
        'a'=>$action,
        'r'=>$result,
        'm'=>$message,
        'c'=>time(),
        'ip'=>$ip,
        'ua'=>$ua,
    ]);
}
