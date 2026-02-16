<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$db = db($cfg);
$accCount = $isAdmin ? (int)$db->querySingle('SELECT COUNT(*) FROM dnshe_accounts') : (int)$db->querySingle('SELECT COUNT(*) FROM dnshe_accounts WHERE user_id='.(int)$u['id']);
$logCount = $isAdmin ? (int)$db->querySingle('SELECT COUNT(*) FROM logs') : (int)$db->querySingle('SELECT COUNT(*) FROM logs WHERE user_id='.(int)$u['id']);

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">仪表盘</h3>
</div>
<div class="row g-3">
  <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">DNSHE 账户数</div><div class="display-6"><?= $accCount ?></div></div></div></div>
  <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">日志条数</div><div class="display-6"><?= $logCount ?></div></div></div></div>
  <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">自动续期</div><div class="small">使用带签名的 HTTP 链接触发（适配宝塔/cron）。</div></div></div></div>
</div>
<hr>
<div class="card shadow-sm"><div class="card-body">
  <h5>快速入口</h5>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary" href="index.php?page=accounts">管理 DNSHE 账户</a>
    <a class="btn btn-outline-primary" href="index.php?page=domains">域名管理</a>
    <a class="btn btn-outline-secondary" href="index.php?page=logs">日志</a>
  </div>
</div></div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
