<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/log.php';
require_once __DIR__ . '/../lib/db.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$isAdmin) {
        $msg = '仅管理员可修改';
    } else {
        $newKey = trim((string)($_POST['signing_key'] ?? ''));
        if (strlen($newKey) < 16) $msg = '建议至少 16 位随机字符串';
        else {
            set_signing_key($cfg, $newKey);
            add_log($cfg, (int)$u['id'], null, null, 'system', 'update_signing_key', 'success', '');
            $msg = '保存成功';
        }
    }
}

$signKey = get_signing_key($cfg);
$ts = time();
$payload = 'global|0|'.$ts;
$sig = sign_payload($payload, $signKey);

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<h3>系统设置</h3>
<?php if($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-body">
      <h5>续期 HTTP 链接签名密钥</h5>
      <div class="small text-muted mb-2">用于 api/renew.php 的签名验证，修改后旧链接失效。</div>
      <?php if(!$isAdmin): ?>
        <div class="text-muted">仅管理员可修改。</div>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">Signing Key</label><input class="form-control" name="signing_key" value="<?= h($signKey) ?>"></div>
          <button class="btn btn-primary">保存</button>
        </form>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-body">
      <h5>全局续期链接（示例）</h5>
      <?php if(!$isAdmin): ?>
        <div class="text-muted">仅管理员可生成</div>
      <?php else: ?>
        <div class="small"><span class="secret-mask">api/renew.php?mode=global&ts=<?= $ts ?>&sig=<?= h($sig) ?></span></div>
        <div class="small text-muted mt-2">宝塔计划任务示例：curl -s "https://你的域名/路径/api/renew.php?mode=global&ts=...&sig=..."</div>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
