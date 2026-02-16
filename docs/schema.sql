-- schema.sql（可选）
-- 程序首次运行会自动创建表结构；此文件用于审计/备份。

CREATE TABLE users(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  disabled INTEGER NOT NULL DEFAULT 0,
  must_change_password INTEGER NOT NULL DEFAULT 0,
  failed_attempts INTEGER NOT NULL DEFAULT 0,
  locked_until INTEGER DEFAULT NULL,
  created_at INTEGER NOT NULL,
  last_login_at INTEGER DEFAULT NULL
);

CREATE TABLE dnshe_accounts(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  remark TEXT,
  api_key_enc TEXT NOT NULL,
  api_secret_enc TEXT NOT NULL,
  auto_renew INTEGER NOT NULL DEFAULT 1,
  created_at INTEGER NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX idx_accounts_user ON dnshe_accounts(user_id);

CREATE TABLE domain_cache(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER NOT NULL,
  subdomain_id INTEGER NOT NULL,
  full_domain TEXT,
  expires_at TEXT,
  remaining_days INTEGER,
  last_sync_at INTEGER,
  UNIQUE(account_id, subdomain_id)
);
CREATE INDEX idx_domain_cache_account ON domain_cache(account_id);

CREATE TABLE settings(
  k TEXT PRIMARY KEY,
  v TEXT NOT NULL
);

CREATE TABLE logs(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  account_id INTEGER,
  domain TEXT,
  action_type TEXT NOT NULL,
  action TEXT NOT NULL,
  result TEXT NOT NULL,
  message TEXT,
  created_at INTEGER NOT NULL,
  ip TEXT,
  ua TEXT
);
CREATE INDEX idx_logs_time ON logs(created_at);
