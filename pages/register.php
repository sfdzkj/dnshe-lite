<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/security.php';

$msg = '';
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = register_user($cfg, trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($res['ok']) $ok = true;
    else $msg = $res['msg'] ?? '注册失败';
}
require __DIR__ . '/../partials/header.php';
?>
<div class="container py-5" style="max-width:460px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">注册</h4>
      <?php if($ok): ?>
        <div class="alert alert-success">注册成功！<a href="index.php?page=login">去登录</a></div>
      <?php else: ?>
        <?php if($msg): ?><div class="alert alert-danger"><?= h($msg) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <div class="mb-3"><label class="form-label">用户名</label><input class="form-control" name="username" required></div>
          <div class="mb-3"><label class="form-label">密码（至少8位，含大小写字母和数字）</label><input type="password" class="form-control" name="password" required></div>
          <button class="btn btn-primary w-100">注册</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
