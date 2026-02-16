<?php
// lib/accounts.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/dnshe.php';
require_once __DIR__ . '/log.php';

function account_encrypt(array $cfg, string $plain): string {
    return encrypt_gcm($plain, app_key_bytes($cfg));
}
function account_decrypt(array $cfg, string $enc): string {
    return decrypt_gcm($enc, app_key_bytes($cfg));
}

function get_signing_key(array $cfg): string {
    $db = db($cfg);
    $row = db_row($db, 'SELECT v FROM settings WHERE k="signing_key"');
    return $row ? (string)$row['v'] : (string)($cfg['default_signing_key'] ?? '');
}

function set_signing_key(array $cfg, string $newKey): void {
    $db = db($cfg);
    db_exec($db, 'INSERT INTO settings(k,v) VALUES("signing_key",:v)
                  ON CONFLICT(k) DO UPDATE SET v=excluded.v', ['v'=>$newKey]);
}

function dnshe_client_from_account(array $cfg, array $acc): DnsheApi {
    $apiKey = account_decrypt($cfg, (string)$acc['api_key_enc']);
    $apiSecret = account_decrypt($cfg, (string)$acc['api_secret_enc']);
    return new DnsheApi(
        (string)$cfg['dnshe_api_base'],
        $apiKey,
        $apiSecret,
        (int)$cfg['api_min_interval_ms'],
        (int)$cfg['api_max_retries']
    );
}

function validate_dnshe_credentials(array $cfg, string $apiKey, string $apiSecret): array {
    $api = new DnsheApi((string)$cfg['dnshe_api_base'], $apiKey, $apiSecret, (int)$cfg['api_min_interval_ms'], (int)$cfg['api_max_retries']);
    try {
        $q = $api->quota();
        return ['ok'=> (bool)($q['success'] ?? false), 'data'=>$q];
    } catch (Throwable $e) {
        return ['ok'=>false, 'error'=>$e->getMessage()];
    }
}

function list_accounts(array $cfg, int $userId, bool $admin=false): array {
    $db = db($cfg);
    if ($admin) return db_all($db, 'SELECT * FROM dnshe_accounts ORDER BY id DESC');
    return db_all($db, 'SELECT * FROM dnshe_accounts WHERE user_id=:u ORDER BY id DESC', ['u'=>$userId]);
}

function get_account(array $cfg, int $accountId): ?array {
    $db = db($cfg);
    return db_row($db, 'SELECT * FROM dnshe_accounts WHERE id=:id', ['id'=>$accountId]);
}

function upsert_account(array $cfg, int $userId, ?int $id, string $remark, string $apiKey, string $apiSecret, int $autoRenew): array {
    $db = db($cfg);
    $now = time();
    $apiKeyEnc = account_encrypt($cfg, $apiKey);
    $apiSecretEnc = account_encrypt($cfg, $apiSecret);

    if ($id === null) {
        db_exec($db, 'INSERT INTO dnshe_accounts(user_id,remark,api_key_enc,api_secret_enc,auto_renew,created_at,updated_at)
                      VALUES(:u,:r,:k,:s,:a,:t,:t)', [
            'u'=>$userId,'r'=>$remark,'k'=>$apiKeyEnc,'s'=>$apiSecretEnc,'a'=>$autoRenew,'t'=>$now
        ]);
        return ['ok'=>true,'id'=>(int)$db->lastInsertRowID()];
    }

    db_exec($db, 'UPDATE dnshe_accounts SET remark=:r, api_key_enc=:k, api_secret_enc=:s, auto_renew=:a, updated_at=:t
                  WHERE id=:id AND user_id=:u', [
        'r'=>$remark,'k'=>$apiKeyEnc,'s'=>$apiSecretEnc,'a'=>$autoRenew,'t'=>$now,'id'=>$id,'u'=>$userId
    ]);
    return ['ok'=>true,'id'=>$id];
}

function delete_account(array $cfg, int $userId, int $accountId, bool $admin=false): void {
    $db = db($cfg);
    if ($admin) db_exec($db, 'DELETE FROM dnshe_accounts WHERE id=:id', ['id'=>$accountId]);
    else db_exec($db, 'DELETE FROM dnshe_accounts WHERE id=:id AND user_id=:u', ['id'=>$accountId,'u'=>$userId]);
    db_exec($db, 'DELETE FROM domain_cache WHERE account_id=:a', ['a'=>$accountId]);
}

function set_accounts_auto_renew(array $cfg, int $userId, array $accountIds, int $enabled, bool $admin=false): void {
    $db = db($cfg);
    foreach ($accountIds as $aid) {
        $aid = (int)$aid;
        if ($admin) db_exec($db,'UPDATE dnshe_accounts SET auto_renew=:e, updated_at=:t WHERE id=:id', ['e'=>$enabled,'t'=>time(),'id'=>$aid]);
        else db_exec($db,'UPDATE dnshe_accounts SET auto_renew=:e, updated_at=:t WHERE id=:id AND user_id=:u', ['e'=>$enabled,'t'=>time(),'id'=>$aid,'u'=>$userId]);
    }
}

function cache_domain_from_api(array $cfg, int $accountId, array $subRow): void {
    // 尝试从 API 列表中读取 expires_at/remaining_days（若 API 返回）；不存在则仅写入域名。
    $db = db($cfg);
    $sid = (int)($subRow['id'] ?? 0);
    $domain = (string)($subRow['full_domain'] ?? ($subRow['subdomain'] ?? ''));
    $exp = $subRow['expires_at'] ?? ($subRow['new_expires_at'] ?? null);
    $rem = $subRow['remaining_days'] ?? null;

    db_exec($db, 'INSERT INTO domain_cache(account_id,subdomain_id,full_domain,expires_at,remaining_days,last_sync_at)
                  VALUES(:a,:sid,:d,:exp,:rem,:t)
                  ON CONFLICT(account_id,subdomain_id) DO UPDATE SET
                    full_domain=excluded.full_domain,
                    expires_at=COALESCE(excluded.expires_at, domain_cache.expires_at),
                    remaining_days=COALESCE(excluded.remaining_days, domain_cache.remaining_days),
                    last_sync_at=excluded.last_sync_at', [
        'a'=>$accountId,
        'sid'=>$sid,
        'd'=>$domain,
        'exp'=>$exp,
        'rem'=>$rem,
        't'=>time(),
    ]);
}

function renew_account_domains(array $cfg, array $acc, ?int $actorUserId): array {
    // 复刻开源脚本核心思路：列出所有子域名 -> 逐个调用 renew -> 汇总结果（成功/失败/跳过）
    $client = dnshe_client_from_account($cfg, $acc);
    $accountId = (int)$acc['id'];
    $out = ['account_id'=>$accountId,'renewed'=>[],'skipped'=>[],'failed'=>[]];

    try {
        $list = $client->subdomains_list();
        $subs = $list['subdomains'] ?? [];
    } catch (Throwable $e) {
        add_log($cfg, $actorUserId, $accountId, null, 'renew', 'list_subdomains', 'fail', $e->getMessage());
        $out['failed'][] = ['domain'=>null,'error'=>$e->getMessage()];
        return $out;
    }

    foreach ($subs as $s) {
        $sid = (int)($s['id'] ?? 0);
        $domain = (string)($s['full_domain'] ?? ($s['subdomain'] ?? ''));
        try {
            $resp = $client->subdomains_renew($sid);
            if (($resp['success'] ?? false) === true) {
                $info = [
                    'subdomain_id'=>$sid,
                    'domain'=>$domain,
                    'previous_expires_at'=>$resp['previous_expires_at'] ?? null,
                    'new_expires_at'=>$resp['new_expires_at'] ?? null,
                    'remaining_days'=>$resp['remaining_days'] ?? null,
                ];
                $out['renewed'][] = $info;
                add_log($cfg, $actorUserId, $accountId, $domain, 'renew', 'renew_subdomain', 'success', json_encode($info, JSON_UNESCAPED_UNICODE));
                cache_domain_from_api($cfg, $accountId, array_merge($s, $resp));
            } else {
                $msg = (string)($resp['message'] ?? ($resp['error'] ?? 'Not in renewal window'));
                $out['skipped'][] = ['subdomain_id'=>$sid,'domain'=>$domain,'reason'=>$msg];
                add_log($cfg, $actorUserId, $accountId, $domain, 'renew', 'renew_subdomain', 'skip', $msg);
            }
        } catch (Throwable $e) {
            $out['failed'][] = ['subdomain_id'=>$sid,'domain'=>$domain,'error'=>$e->getMessage()];
            add_log($cfg, $actorUserId, $accountId, $domain, 'renew', 'renew_subdomain', 'fail', $e->getMessage());
        }
    }

    return $out;
}
