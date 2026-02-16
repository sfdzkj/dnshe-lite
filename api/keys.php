<?php
// api/keys.php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/log.php';

$u = require_login($cfg);
csrf_check();
$isAdmin = ($u['role']==='admin');

$act = $_POST['act'] ?? '';
$accountId = (int)($_POST['account_id'] ?? 0);
$acc = $accountId ? get_account($cfg, $accountId) : null;
if ($acc && !$isAdmin && (int)$acc['user_id'] !== (int)$u['id']) $acc = null;
if (!$acc) { echo json_encode(['success'=>false,'error'=>'账户不存在或无权限']); exit; }

$client = dnshe_client_from_account($cfg, $acc);

try {
    if ($act === 'create') {
        $name = trim((string)($_POST['key_name'] ?? ''));
        $ipw = trim((string)($_POST['ip_whitelist'] ?? ''));
        $resp = $client->keys_create($name, $ipw);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], null, 'op', 'create_api_key', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($act === 'delete') {
        $kid = (int)($_POST['key_id'] ?? 0);
        $resp = $client->keys_delete($kid);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], null, 'op', 'delete_api_key', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($act === 'regenerate') {
        $kid = (int)($_POST['key_id'] ?? 0);
        $resp = $client->keys_regenerate($kid);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], null, 'op', 'regenerate_api_secret', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown act']);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
