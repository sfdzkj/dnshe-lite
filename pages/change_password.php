<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/security.php';

$u = require_login($cfg);
$msg = '';
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = change_password($cfg, (int)$u['id'], (string)($_POST['old'] ?? ''), (string)($_POST['new'] ?? ''));
    if ($res['ok']) $ok = true;
    else $msg = $res['msg'] ?? '修改失败';
}
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<h3>修改密码</h3>
<?php if($ok): ?>
  <div class="alert alert-success">修改成功！<a href="index.php?page=dashboard">返回仪表盘</a></div>
<?php else: ?>
  <?php if($msg): ?><div class="alert alert-danger"><?= h($msg) ?></div><?php endif; ?>
  <form method="post" style="max-width:520px;">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <div class="mb-3"><label class="form-label">旧密码</label><input type="password" class="form-control" name="old" required></div>
    <div class="mb-3"><label class="form-label">新密码</label><input type="password" class="form-control" name="new" required></div>
    <button class="btn btn-primary">保存</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/../partials/footer.php'; ?>
