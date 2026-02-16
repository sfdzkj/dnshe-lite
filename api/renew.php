<?php
// api/renew.php
// 带签名验证的 HTTP 触发续期接口（无需登录）。
// mode=account&id=账户ID&ts=时间戳&sig=签名
// mode=global&ts=时间戳&sig=签名
// 签名：HMAC_SHA256("{mode}|{id}|{ts}", signing_key)

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/log.php';

$mode = (string)($_GET['mode'] ?? '');
$ts = (int)($_GET['ts'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');
$id = (int)($_GET['id'] ?? 0);

$ttl = (int)($cfg['renew_link_ttl'] ?? 300);
if ($ts <= 0 || abs(time() - $ts) > $ttl) {
    echo json_encode(['success'=>false,'error'=>'签名已过期'], JSON_UNESCAPED_UNICODE);
    exit;
}

$signKey = get_signing_key($cfg);
$payload = $mode . '|' . $id . '|' . $ts;
$expected = sign_payload($payload, $signKey);
if (!hash_equals($expected, $sig)) {
    echo json_encode(['success'=>false,'error'=>'签名验证失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db($cfg);

try {
    if ($mode === 'account') {
        $acc = get_account($cfg, $id);
        if (!$acc) { echo json_encode(['success'=>false,'error'=>'账户不存在']); exit; }
        $res = renew_account_domains($cfg, $acc, null);
        echo json_encode(['success'=>true,'mode'=>'account','result'=>$res], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($mode === 'global') {
        $accounts = db_all($db, 'SELECT * FROM dnshe_accounts WHERE auto_renew=1 ORDER BY id ASC');
        $all = [];
        foreach ($accounts as $acc) {
            $all[] = renew_account_domains($cfg, $acc, null);
        }
        echo json_encode(['success'=>true,'mode'=>'global','results'=>$all], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown mode'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
