<?php
// partials/sidebar.php
$u = current_user($cfg);
$isAdmin = ($u && $u['role']==='admin');
?>
<div class="d-flex">
  <nav class="sidebar bg-white border-end">
    <div class="p-3 border-bottom">
      <div class="fw-bold">DNSHE Lite</div>
      <div class="small text-muted"><?= h($u['username'] ?? '') ?> · <?= h($u['role'] ?? '') ?></div>
    </div>
    <div class="list-group list-group-flush">
      <a class="list-group-item list-group-item-action" href="index.php?page=dashboard">仪表盘</a>
      <a class="list-group-item list-group-item-action" href="index.php?page=accounts">账户管理</a>
      <a class="list-group-item list-group-item-action" href="index.php?page=domains">域名管理</a>
      <a class="list-group-item list-group-item-action" href="index.php?page=logs">日志</a>
      <a class="list-group-item list-group-item-action" href="index.php?page=backup">备份/恢复</a>
      <a class="list-group-item list-group-item-action" href="index.php?page=settings">系统设置</a>
      <?php if ($isAdmin): ?>
        <div class="px-3 pt-3 text-uppercase small text-muted">管理员</div>
        <a class="list-group-item list-group-item-action" href="index.php?page=users">用户管理</a>
        <a class="list-group-item list-group-item-action" href="index.php?page=global_renew">全局续期</a>
      <?php endif; ?>
      <a class="list-group-item list-group-item-action text-danger" href="index.php?page=logout">退出</a>
    </div>
  </nav>
  <main class="flex-grow-1">
    <div class="container-fluid p-4">
