<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$accountId = (int)($_GET['account_id'] ?? 0);
$acc = $accountId ? get_account($cfg, $accountId) : null;
if ($acc && !$isAdmin && (int)$acc['user_id'] !== (int)$u['id']) $acc = null;

$keys = [];
$error = '';
if ($acc) {
    $client = dnshe_client_from_account($cfg, $acc);
    try { $keys = $client->keys_list(); }
    catch (Throwable $e) { $error = $e->getMessage(); }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<h3>API Key 管理</h3>
<div class="mb-2"><a href="index.php?page=domains&account_id=<?= (int)$accountId ?>">← 返回域名管理</a></div>
<?php if($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<div class="card shadow-sm"><div class="card-body">
  <div class="d-flex justify-content-between">
    <h5 class="mb-0">Key 列表</h5>
    <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#newKey">创建</button>
  </div>

  <div class="collapse mt-3" id="newKey">
    <form id="frmKey" class="row g-2">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="account_id" value="<?= (int)$accountId ?>">
      <div class="col-md-5"><input class="form-control" name="key_name" placeholder="Key Name" required></div>
      <div class="col-md-5"><input class="form-control" name="ip_whitelist" placeholder="IP 白名单(可选,逗号分隔)"></div>
      <div class="col-md-2"><button class="btn btn-success w-100">创建</button></div>
      <div class="col-12"><div class="small text-muted" id="keyMsg"></div></div>
    </form>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-striped align-middle">
      <thead><tr><th>ID</th><th>Name</th><th>Key</th><th>Status</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach(($keys['keys'] ?? []) as $k): ?>
        <tr>
          <td><?= (int)$k['id'] ?></td>
          <td><?= h($k['key_name'] ?? '') ?></td>
          <td><span class="secret-mask"><?= h($k['api_key'] ?? '') ?></span></td>
          <td><?= h($k['status'] ?? '') ?></td>
          <td>
            <button class="btn btn-sm btn-outline-warning btnRegen" data-id="<?= (int)$k['id'] ?>">重置Secret</button>
            <button class="btn btn-sm btn-outline-danger btnDel" data-id="<?= (int)$k['id'] ?>">删除</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<script>
$(function(){
  $('#frmKey').on('submit', function(e){
    e.preventDefault();
    $.post('api/keys.php', $(this).serialize()+'&act=create', function(resp){
      $('#keyMsg').text(resp.success ? ('创建成功：请保存 Secret（只显示一次） -> '+(resp.api_secret||'')) : ('失败：'+(resp.error||resp.message||'')));
      if(resp.success) setTimeout(()=>location.reload(), 1200);
    }, 'json');
  });

  $('.btnDel').on('click', function(){
    const id = $(this).data('id');
    if(!confirmAction('确认删除该 API Key？')) return;
    $.post('api/keys.php', {csrf:'<?= h(csrf_token()) ?>', act:'delete', account_id:'<?= (int)$accountId ?>', key_id:id}, function(resp){
      if(resp.success) location.reload();
      else alert(resp.error||resp.message||'删除失败');
    }, 'json');
  });

  $('.btnRegen').on('click', function(){
    const id = $(this).data('id');
    if(!confirmAction('确认重置该 Key 的 Secret？新 Secret 只会显示一次')) return;
    $.post('api/keys.php', {csrf:'<?= h(csrf_token()) ?>', act:'regenerate', account_id:'<?= (int)$accountId ?>', key_id:id}, function(resp){
      if(resp.success){
        alert('新的 Secret：' + (resp.api_secret||''));
        location.reload();
      } else alert(resp.error||resp.message||'失败');
    }, 'json');
  });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
