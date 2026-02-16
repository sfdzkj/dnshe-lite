<?php
// api/records.php

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
        $payload = [
            'subdomain_id'=>(int)($_POST['subdomain_id'] ?? 0),
            'type'=>trim((string)($_POST['type'] ?? '')),
            'content'=>trim((string)($_POST['content'] ?? '')),
        ];
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') $payload['name'] = $name;
        $ttl = trim((string)($_POST['ttl'] ?? ''));
        if ($ttl !== '') $payload['ttl'] = (int)$ttl;
        $prio = trim((string)($_POST['priority'] ?? ''));
        if ($prio !== '') $payload['priority'] = (int)$prio;

        $resp = $client->records_create($payload);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], null, 'op', 'create_record', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($act === 'update') {
        $payload = ['record_id'=>(int)($_POST['record_id'] ?? 0)];
        if (isset($_POST['content'])) $payload['content'] = (string)$_POST['content'];
        if (isset($_POST['ttl']) && $_POST['ttl'] !== '') $payload['ttl'] = (int)$_POST['ttl'];
        $resp = $client->records_update($payload);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], null, 'op', 'update_record', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($act === 'delete') {
        $rid = (int)($_POST['record_id'] ?? 0);
        $resp = $client->records_delete($rid);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], null, 'op', 'delete_record', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown act']);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
