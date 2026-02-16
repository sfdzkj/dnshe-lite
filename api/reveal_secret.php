<?php
// api/reveal_secret.php
// 二次验证显示敏感信息（示例：解密某账户的 API Secret）

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';

$u = require_login($cfg);
csrf_check();
$isAdmin = ($u['role']==='admin');

$accountId = (int)($_POST['account_id'] ?? 0);
$password = (string)($_POST['password'] ?? '');
$acc = $accountId ? get_account($cfg, $accountId) : null;
if ($acc && !$isAdmin && (int)$acc['user_id'] !== (int)$u['id']) $acc = null;
if (!$acc) { echo json_encode(['success'=>false,'error'=>'账户不存在或无权限']); exit; }

$db = db($cfg);
$hash = db_row($db, 'SELECT password_hash FROM users WHERE id=:id', ['id'=>(int)$u['id']])['password_hash'] ?? '';
if (!password_verify($password, (string)$hash)) {
    echo json_encode(['success'=>false,'error'=>'密码验证失败']);
    exit;
}

try {
    $secret = account_decrypt($cfg, (string)$acc['api_secret_enc']);
    echo json_encode(['success'=>true,'api_secret'=>$secret], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
