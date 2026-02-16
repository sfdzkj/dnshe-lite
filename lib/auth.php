<?php
// lib/auth.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/log.php';

function session_boot(array $cfg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }

    $now = time();
    if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > (int)$cfg['session_timeout']) {
        logout($cfg);
        return;
    }
    $_SESSION['last_activity'] = $now;
}

function current_user(array $cfg): ?array {
    session_boot($cfg);
    if (empty($_SESSION['uid'])) return null;
    $db = db($cfg);
    $u = db_row($db, 'SELECT id,username,role,disabled,must_change_password FROM users WHERE id=:id', ['id'=>(int)$_SESSION['uid']]);
    if (!$u || (int)$u['disabled'] === 1) {
        logout($cfg);
        return null;
    }
    return $u;
}

function require_login(array $cfg): array {
    $u = current_user($cfg);
    if (!$u) {
        header('Location: index.php?page=login');
        exit;
    }
    return $u;
}

function require_admin(array $cfg): array {
    $u = require_login($cfg);
    if (($u['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $u;
}

function login(array $cfg, string $username, string $password): array {
    $db = db($cfg);
    $u = db_row($db, 'SELECT * FROM users WHERE username=:u', ['u'=>$username]);
    if (!$u) {
        add_log($cfg, null, null, null, 'auth', 'login', 'fail', 'User not found');
        return ['ok'=>false,'msg'=>'用户名或密码错误'];
    }
    if ((int)$u['disabled'] === 1) {
        add_log($cfg, (int)$u['id'], null, null, 'auth', 'login', 'fail', 'User disabled');
        return ['ok'=>false,'msg'=>'账号已禁用'];
    }
    $now = time();
    if (!empty($u['locked_until']) && $now < (int)$u['locked_until']) {
        $mins = (int)ceil(((int)$u['locked_until'] - $now)/60);
        return ['ok'=>false,'msg'=>"登录失败次数过多，已锁定 {$mins} 分钟"]; 
    }

    if (!password_verify($password, (string)$u['password_hash'])) {
        $failed = (int)$u['failed_attempts'] + 1;
        $lockedUntil = null;
        if ($failed >= (int)$cfg['login_max_attempts']) {
            $lockedUntil = $now + ((int)$cfg['login_lock_minutes']*60);
            $failed = 0;
        }
        db_exec($db, 'UPDATE users SET failed_attempts=:f, locked_until=:lu WHERE id=:id', ['f'=>$failed,'lu'=>$lockedUntil,'id'=>(int)$u['id']]);
        add_log($cfg, (int)$u['id'], null, null, 'auth', 'login', 'fail', 'Bad password');
        return ['ok'=>false,'msg'=>'用户名或密码错误'];
    }

    db_exec($db, 'UPDATE users SET failed_attempts=0, locked_until=NULL, last_login_at=:t WHERE id=:id', ['t'=>$now,'id'=>(int)$u['id']]);
    session_boot($cfg);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['last_activity'] = $now;
    add_log($cfg, (int)$u['id'], null, null, 'auth', 'login', 'success', 'Login ok');
    return ['ok'=>true,'must_change'=>(int)$u['must_change_password']===1];
}

function logout(array $cfg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    @session_destroy();
}

function register_user(array $cfg, string $username, string $password): array {
    $db = db($cfg);
    $username = trim($username);
    if ($username === '' || strlen($username) < 3) return ['ok'=>false,'msg'=>'用户名至少 3 位'];
    if (!password_strong_enough($password)) return ['ok'=>false,'msg'=>'密码至少 8 位，包含大小写字母和数字'];
    $exists = db_row($db, 'SELECT id FROM users WHERE username=:u', ['u'=>$username]);
    if ($exists) return ['ok'=>false,'msg'=>'用户名已存在'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db_exec($db, 'INSERT INTO users(username,password_hash,role,disabled,must_change_password,created_at) VALUES(:u,:p,"user",0,0,:t)', [
        'u'=>$username,
        'p'=>$hash,
        't'=>time(),
    ]);
    $uid = (int)$db->lastInsertRowID();
    add_log($cfg, $uid, null, null, 'auth', 'register', 'success', 'User registered');
    return ['ok'=>true];
}

function change_password(array $cfg, int $userId, string $old, string $new): array {
    $db = db($cfg);
    $u = db_row($db, 'SELECT * FROM users WHERE id=:id', ['id'=>$userId]);
    if (!$u) return ['ok'=>false,'msg'=>'用户不存在'];
    if (!password_verify($old, (string)$u['password_hash'])) return ['ok'=>false,'msg'=>'旧密码错误'];
    if (!password_strong_enough($new)) return ['ok'=>false,'msg'=>'新密码强度不足'];
    $hash = password_hash($new, PASSWORD_DEFAULT);
    db_exec($db, 'UPDATE users SET password_hash=:p, must_change_password=0 WHERE id=:id', ['p'=>$hash,'id'=>$userId]);
    add_log($cfg, $userId, null, null, 'auth', 'change_password', 'success', 'Password changed');
    return ['ok'=>true];
}
