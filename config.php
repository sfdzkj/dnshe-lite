<?php
// config.php
// 绿色版配置文件：修改后立即生效。

return [
    // 基础
    'app_name' => 'DNSHE Lite Manager',

    // SQLite 数据库路径（单文件）
    'db_path' => __DIR__ . '/data/app.db',

    // DNSHE API 基址（文档示例：https://api005.dnshe.com/index.php?m=domain_hub）
    'dnshe_api_base' => 'https://api005.dnshe.com/index.php',

    // 安全：用于加密存储 API Key/Secret 的主密钥（建议 32+ 字符随机字符串）
    'app_key' => 'CHANGE_ME_TO_32+_CHARS_RANDOM_STRING',

    // HTTP 触发续期签名的默认密钥（可在后台“系统设置”中在线修改，保存到 SQLite settings 表）
    'default_signing_key' => 'CHANGE_ME_SIGNING_KEY',

    // 会话与安全策略
    'session_timeout' => 1800,        // 30 分钟无操作自动登出
    'login_max_attempts' => 5,        // 登录失败最大次数
    'login_lock_minutes' => 10,       // 锁定分钟

    // API 限流（官方默认 60 次/分钟：采用最小间隔 + 429 退避重试）
    'api_min_interval_ms' => 1200,
    'api_max_retries' => 5,

    // 续期 HTTP 链接签名有效期（秒）
    'renew_link_ttl' => 300,

    // 时区
    'timezone' => 'Asia/Shanghai',
];
