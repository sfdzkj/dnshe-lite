<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/security.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = login($cfg, trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($res['ok']) {
        header('Location: index.php?page=' . (!empty($res['must_change']) ? 'change_password' : 'dashboard'));
        exit;
    }
    $msg = $res['msg'] ?? '登录失败';
}
require __DIR__ . '/../partials/header.php';
?>
<div class="container py-5" style="max-width:420px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">登录</h4>
      <?php if($msg): ?><div class="alert alert-danger"><?= h($msg) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div class="mb-3">
          <label class="form-label">用户名</label>
          <input class="form-control" name="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label">密码</label>
          <input type="password" class="form-control" name="password" required>
        </div>
        <button class="btn btn-primary w-100">登录</button>
      </form>
      <div class="mt-3 small">没有账号？<a href="index.php?page=register">注册</a></div>
      <hr>
      <div class="small text-muted">默认管理员：admin / 123456Aa（首次登录强制修改密码）</div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
