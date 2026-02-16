<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$accountId = (int)($_GET['account_id'] ?? 0);
$subId = (int)($_GET['subdomain_id'] ?? 0);

$acc = $accountId ? get_account($cfg, $accountId) : null;
if ($acc && !$isAdmin && (int)$acc['user_id'] !== (int)$u['id']) $acc = null;

$records = [];
$error = '';
$detail = null;
if ($acc && $subId) {
    $client = dnshe_client_from_account($cfg, $acc);
    try { $detail = $client->subdomains_get($subId); } catch (Throwable $e) { $detail = null; }
    try {
        $list = $client->records_list($subId);
        $records = $list['records'] ?? [];
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<h3>DNS 记录</h3>
<div class="mb-2"><a href="index.php?page=domains&account_id=<?= (int)$accountId ?>">← 返回域名列表</a></div>
<?php if($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

<div class="card shadow-sm"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <div class="text-muted small">Subdomain ID: <?= (int)$subId ?></div>
      <div class="fw-bold"><?= h($detail['subdomain']['full_domain'] ?? '') ?></div>
    </div>
    <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#newRec">新增记录</button>
  </div>

  <div class="collapse mt-3" id="newRec">
    <form id="frmCreate" class="row g-2">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="account_id" value="<?= (int)$accountId ?>">
      <input type="hidden" name="subdomain_id" value="<?= (int)$subId ?>">
      <div class="col-md-3"><select class="form-select" name="type" required><option value="A">A</option><option value="AAAA">AAAA</option><option value="CNAME">CNAME</option><option value="MX">MX</option><option value="TXT">TXT</option><option value="NS">NS</option><option value="SRV">SRV</option><option value="CAA">CAA</option></select></div>
      <div class="col-md-5"><input class="form-control" name="content" placeholder="记录值" required></div>
      <div class="col-md-4"><input class="form-control" name="name" placeholder="记录名(可选)"></div>
      <div class="col-md-3"><input class="form-control" name="ttl" placeholder="TTL(默认120)"></div>
      <div class="col-md-3"><input class="form-control" name="priority" placeholder="优先级(MX)"></div>
      <div class="col-md-3"><button class="btn btn-success w-100">创建</button></div>
      <div class="col-12"><div class="small text-muted" id="msg"></div></div>
    </form>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-striped align-middle">
      <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Content</th><th>TTL</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach($records as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['name'] ?? '') ?></td>
          <td><?= h($r['type'] ?? '') ?></td>
          <td><span class="secret-mask"><?= h($r['content'] ?? '') ?></span></td>
          <td><?= h((string)($r['ttl'] ?? '')) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary btnEdit" data-id="<?= (int)$r['id'] ?>" data-content="<?= h($r['content'] ?? '') ?>" data-ttl="<?= h((string)($r['ttl'] ?? '')) ?>">编辑</button>
            <button class="btn btn-sm btn-outline-danger btnDel" data-id="<?= (int)$r['id'] ?>">删除</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>

<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">编辑记录</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <form id="frmEdit">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="account_id" value="<?= (int)$accountId ?>">
        <input type="hidden" name="record_id" id="recId">
        <div class="mb-2"><label class="form-label">Content</label><input class="form-control" name="content" id="recContent"></div>
        <div class="mb-2"><label class="form-label">TTL</label><input class="form-control" name="ttl" id="recTtl"></div>
        <button class="btn btn-primary">保存</button>
      </form>
      <div class="small text-muted mt-2" id="editMsg"></div>
    </div>
  </div></div>
</div>

<script>
$(function(){
  $('#frmCreate').on('submit', function(e){
    e.preventDefault();
    $.post('api/records.php', $(this).serialize()+'&act=create', function(resp){
      $('#msg').text(resp.success ? '创建成功' : ('失败：'+(resp.error||resp.message||'')));
      if(resp.success) setTimeout(()=>location.reload(), 600);
    }, 'json');
  });

  $('.btnDel').on('click', function(){
    const id = $(this).data('id');
    if(!confirmAction('确认删除该记录？')) return;
    $.post('api/records.php', {csrf:'<?= h(csrf_token()) ?>', act:'delete', account_id:'<?= (int)$accountId ?>', record_id:id}, function(resp){
      if(resp.success) location.reload();
      else alert(resp.error||resp.message||'删除失败');
    }, 'json');
  });

  $('.btnEdit').on('click', function(){
    $('#recId').val($(this).data('id'));
    $('#recContent').val($(this).data('content'));
    $('#recTtl').val($(this).data('ttl'));
    $('#editMsg').text('');
    new bootstrap.Modal(document.getElementById('editModal')).show();
  });

  $('#frmEdit').on('submit', function(e){
    e.preventDefault();
    $.post('api/records.php', $(this).serialize()+'&act=update', function(resp){
      $('#editMsg').text(resp.success ? '保存成功' : ('失败：'+(resp.error||resp.message||'')));
      if(resp.success) setTimeout(()=>location.reload(), 600);
    }, 'json');
  });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
