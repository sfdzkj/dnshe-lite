<?php
// api/subdomains.php

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
    if ($act === 'renew') {
        $sid = (int)($_POST['subdomain_id'] ?? 0);
        $resp = $client->subdomains_renew($sid);
        if (($resp['success'] ?? false) === true) {
            cache_domain_from_api($cfg, (int)$acc['id'], array_merge(['id'=>$sid,'full_domain'=>null], $resp));
        }
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($act === 'register') {
        $sub = trim((string)($_POST['subdomain'] ?? ''));
        $root = trim((string)($_POST['rootdomain'] ?? ''));
        $resp = $client->subdomains_register($sub, $root);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], ($resp['full_domain'] ?? null), 'op', 'register_subdomain', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($act === 'delete') {
        $sid = (int)($_POST['subdomain_id'] ?? 0);
        $resp = $client->subdomains_delete($sid);
        add_log($cfg, (int)$u['id'], (int)$acc['id'], null, 'op', 'delete_subdomain', ($resp['success']??false)?'success':'fail', json_encode($resp, JSON_UNESCAPED_UNICODE));
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown act']);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, '<!DOCTYPE') !== false || stripos($msg, '<html') !== false) {
        $msg = 'Access Denied（可能原因：未到续期窗口/权限不足/API Key IP 白名单/风控拦截）';
    }
    echo json_encode(['success'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
}
