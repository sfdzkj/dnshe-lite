<?php
// lib/install.php

declare(strict_types=1);

function install_db(SQLite3 $db, array $cfg): void {
    $db->exec('PRAGMA journal_mode=WAL;');
    $db->exec('PRAGMA synchronous=NORMAL;');

    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT "user", -- admin/user
        disabled INTEGER NOT NULL DEFAULT 0,
        must_change_password INTEGER NOT NULL DEFAULT 0,
        failed_attempts INTEGER NOT NULL DEFAULT 0,
        locked_until INTEGER DEFAULT NULL,
        created_at INTEGER NOT NULL,
        last_login_at INTEGER DEFAULT NULL
    );');

    $db->exec('CREATE TABLE IF NOT EXISTS dnshe_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        remark TEXT,
        api_key_enc TEXT NOT NULL,
        api_secret_enc TEXT NOT NULL,
        auto_renew INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    );');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_accounts_user ON dnshe_accounts(user_id);');

    $db->exec('CREATE TABLE IF NOT EXISTS domain_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        subdomain_id INTEGER NOT NULL,
        full_domain TEXT,
        expires_at TEXT,
        remaining_days INTEGER,
        last_sync_at INTEGER,
        UNIQUE(account_id, subdomain_id)
    );');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_domain_cache_account ON domain_cache(account_id);');

    $db->exec('CREATE TABLE IF NOT EXISTS settings (
        k TEXT PRIMARY KEY,
        v TEXT NOT NULL
    );');

    $db->exec('CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        account_id INTEGER,
        domain TEXT,
        action_type TEXT NOT NULL, -- op/renew/auth/system
        action TEXT NOT NULL,
        result TEXT NOT NULL, -- success/fail/skip
        message TEXT,
        created_at INTEGER NOT NULL,
        ip TEXT,
        ua TEXT
    );');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_logs_time ON logs(created_at);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_logs_user ON logs(user_id);');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_logs_account ON logs(account_id);');

    // settings: signing key
    $signKey = (string)($cfg['default_signing_key'] ?? '');
    $stmt = $db->prepare('INSERT OR IGNORE INTO settings(k,v) VALUES(:k,:v)');
    $stmt->bindValue(':k', 'signing_key', SQLITE3_TEXT);
    $stmt->bindValue(':v', $signKey, SQLITE3_TEXT);
    $stmt->execute();

    // 默认管理员：admin / 123456Aa（首次登录强制修改密码）
    $adminCount = (int)$db->querySingle('SELECT COUNT(*) FROM users WHERE role="admin"');
    if ($adminCount === 0) {
        $hash = password_hash('123456Aa', PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users(username,password_hash,role,disabled,must_change_password,created_at) VALUES(:u,:p,"admin",0,1,:t)');
        $stmt->bindValue(':u', 'admin', SQLITE3_TEXT);
        $stmt->bindValue(':p', $hash, SQLITE3_TEXT);
        $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }
}
