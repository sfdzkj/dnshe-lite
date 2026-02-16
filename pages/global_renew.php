<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';

$u = require_admin($cfg);
$accounts = list_accounts($cfg, (int)$u['id'], true);
$ids = array_values(array_map(fn($a)=>(int)$a['id'], array_filter($accounts, fn($a)=>(int)$a['auto_renew']===1)));

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<h3>全局续期（管理员）</h3>
<div class="alert alert-warning">将对所有<strong>开启自动续期</strong>的账户执行批量续期（HTTP 调用 api/renew.php）。</div>

<div class="card shadow-sm"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center">
    <div class="text-muted small">符合条件账户数：<?= count($ids) ?></div>
    <button class="btn btn-primary" id="btnRun">开始执行</button>
  </div>
  <div class="progress mt-3" style="height:22px; display:none;" id="wrap"><div class="progress-bar" id="bar" style="width:0%">0%</div></div>
  <div class="small text-muted mt-2" id="st"></div>
</div></div>

<script src="assets/js/renew_progress.js"></script>
<script>
$(function(){
  const ids = <?= json_encode($ids, JSON_UNESCAPED_UNICODE) ?>;
  $('#btnRun').on('click', function(){
    if(!confirmAction('确认执行全局续期？')) return;
    $('#wrap').show();
    runBatchRenew(ids, 'api/renew.php?mode=account&id={id}&from_ui=1', $('#bar'), $('#st'));
  });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
