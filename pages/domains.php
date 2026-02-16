<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/security.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$db = db($cfg);

$accounts = list_accounts($cfg, (int)$u['id'], $isAdmin);
$selected = isset($_GET['account_id']) ? (int)$_GET['account_id'] : (int)($accounts[0]['id'] ?? 0);
$acc = $selected ? get_account($cfg, $selected) : null;
if ($acc && !$isAdmin && (int)$acc['user_id'] !== (int)$u['id']) $acc = null;

$subs = [];
$error = '';
$quota = null;
$keys = null;

if ($acc) {
    $client = dnshe_client_from_account($cfg, $acc);
    try {
        $list = $client->subdomains_list();
        $subs = $list['subdomains'] ?? [];
        // 尝试更新本地缓存（如果 API 返回 expires_at/remaining_days）
        foreach ($subs as $s) {
            cache_domain_from_api($cfg, (int)$acc['id'], $s);
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }

    try { $quota = $client->quota(); } catch (Throwable $e) { $quota = ['success'=>false,'error'=>$e->getMessage()]; }
    try { $keys = $client->keys_list(); } catch (Throwable $e) { $keys = ['success'=>false,'error'=>$e->getMessage()]; }
}

$cache = [];
if ($selected) {
    foreach (db_all($db,'SELECT * FROM domain_cache WHERE account_id=:a', ['a'=>$selected]) as $c) {
        $cache[(int)$c['subdomain_id']] = $c;
    }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">域名管理</h3>
</div>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <input type="hidden" name="page" value="domains">
    <div class="col-md-4">
      <label class="form-label">选择账户</label>
      <select class="form-select" name="account_id" onchange="this.form.submit()">
        <?php foreach($accounts as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id']===$selected ? 'selected' : '' ?>>#<?= (int)$a['id'] ?> <?= h($a['remark'] ?? '') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-8">
      <?php if($quota): ?>
        <div class="small text-muted">配额：<?php if(($quota['success']??false) && isset($quota['quota'])){ $q=$quota['quota']; ?>已注册 <b><?= (int)($q['used']??0) ?></b> 个，剩余 <b><?= (int)($q['available']??0) ?></b> 个注册名额<?php } else { ?><span class="text-danger">无法获取</span><?php } ?></div>
      <?php endif; ?>
    </div>
  </form>
</div></div>

<?php if($error): ?><div class="alert alert-danger">API 调用失败：<?= h($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm"><div class="card-body">
      <div class="d-flex justify-content-between">
        <h5 class="mb-0">子域名列表</h5>
        <?php if($acc): ?>
          <button class="btn btn-sm btn-primary" id="btnRenewAll">批量续期（该账户）</button>
        <?php endif; ?>
      </div>
      <div class="progress mt-2" style="height:22px; display:none;" id="renewProgWrap"><div class="progress-bar" id="renewBar" style="width:0%">0%</div></div>
      <div class="small text-muted mt-1" id="renewStatus"></div>

      <div class="table-responsive mt-3">
        <table class="table table-hover align-middle">
          <thead><tr><th>ID</th><th>域名</th><th>状态</th><th>注册/剩余</th><th>操作</th></tr></thead>
          <tbody>
          <?php foreach($subs as $s):
            $sid = (int)($s['id'] ?? 0);
            $dom = (string)($s['full_domain'] ?? ($s['subdomain'] ?? ''));
            $st = (string)($s['status'] ?? '');
            $c = $cache[$sid] ?? null;
            // 方案2：到期显示为注册时间(created_at)，剩余天数按 365 天倒计时计算
            $reg = (string)($s['created_at'] ?? '');
            $exp = $reg !== '' ? $reg : '-';
            $rem = null;
            if ($reg !== '') {
                $tsReg = strtotime($reg);
                if ($tsReg !== false) {
                    $daysUsed = (int)floor((time() - $tsReg) / 86400);
                    $rem = max(0, 365 - $daysUsed);
                }
            }
            $danger = ($rem !== null && (int)$rem <= 7);
          ?>
            <tr>
              <td><?= $sid ?></td>
              <td><?= h($dom) ?> <?= $danger ? '<span class="badge badge-expire">≤7天</span>' : '' ?></td>
              <td><?= h($st) ?></td>
              <td>
                <div class="small">注册：<?= h((string)$exp) ?></div>
                <div class="small text-muted">剩余：<?= $rem!==null ? (int)$rem.'天' : '-' ?></div>
              </td>
              <td>
                <a class="btn btn-sm btn-outline-secondary" href="index.php?page=records&account_id=<?= (int)$selected ?>&subdomain_id=<?= $sid ?>">DNS记录</a>
                <button class="btn btn-sm btn-outline-success btnRenewOne" data-sid="<?= $sid ?>" data-dom="<?= h($dom) ?>">续期</button>
                <button class="btn btn-sm btn-outline-danger btnDelOne" data-sid="<?= $sid ?>" data-dom="<?= h($dom) ?>">删除</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm"><div class="card-body">
      <h5>API Keys</h5>
      <?php if($keys && ($keys['success'] ?? false)): ?>
        <div class="small text-muted">共 <?= (int)($keys['count'] ?? 0) ?> 个</div>
        <div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="index.php?page=api_keys&account_id=<?= (int)$selected ?>">管理 API Keys</a></div>
      <?php else: ?>
        <div class="text-muted small">无法拉取 Keys：<?= h($keys['error'] ?? '') ?></div>
      <?php endif; ?>
    </div></div>

    <div class="card shadow-sm mt-3"><div class="card-body">
      <h5>注册新子域名</h5>
      <form id="frmReg">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="account_id" value="<?= (int)$selected ?>">
        <div class="mb-2"><label class="form-label">子域名前缀</label><input class="form-control" name="subdomain" required></div>
        <div class="mb-2"><label class="form-label">根域名(rootdomain)</label><input class="form-control" name="rootdomain" placeholder="例如 de5.net" required></div>
        <button class="btn btn-primary w-100">注册</button>
      </form>
      <div class="small text-muted mt-2" id="regMsg"></div>
    </div></div>
  </div>
</div>

<script>
$(function(){
  $('#frmReg').on('submit', function(e){
    e.preventDefault();
    $.post('api/subdomains.php', $(this).serialize() + '&act=register', function(resp){
      $('#regMsg').text(resp.message || (resp.success ? '成功' : ('失败：'+(resp.error||''))));
      if(resp.success) location.reload();
    }, 'json');
  });

  function startProg(total){
    $('#renewProgWrap').show();
    $('#renewBar').css('width','0%').text('0%');
    $('#renewStatus').text('开始...');
    return {total: total, done:0};
  }
  function updateProg(p){
    p.done++;
    const pct = Math.round(p.done/p.total*100);
    $('#renewBar').css('width', pct+'%').text(pct+'%');
  }

  $('.btnRenewOne').on('click', function(){
    const sid = $(this).data('sid');
    const dom = $(this).data('dom');
    if(!confirmAction('确认续期 '+dom+' ?')) return;
    const p = startProg(1);
    $.post('api/subdomains.php', {csrf:'<?= h(csrf_token()) ?>', act:'renew', account_id:'<?= (int)$selected ?>', subdomain_id: sid}, function(resp){
      $('#renewStatus').text(dom + ' -> ' + (resp.success ? '续期成功' : ('跳过/失败：'+(resp.message||resp.error||''))));
      updateProg(p);
      setTimeout(()=>location.reload(), 800);
    }, 'json');
  });

  $('.btnDelOne').on('click', function(){
    const sid = $(this).data('sid');
    const dom = $(this).data('dom');
    if(!confirmAction('确认删除 '+dom+' ? 此操作不可恢复。')) return;
    $.post('api/subdomains.php', {csrf:'<?= h(csrf_token()) ?>', act:'delete', account_id:'<?= (int)$selected ?>', subdomain_id: sid}, function(resp){
      if(resp.success) location.reload();
      else alert(resp.error||resp.message||'删除失败');
    }, 'json');
  });

  $('#btnRenewAll').on('click', function(){
    if(!confirmAction('确认续期该账户下所有域名？')) return;
    const ids = $('.btnRenewOne').map(function(){return $(this).data('sid');}).get();
    const p = startProg(ids.length);
    function next(i){
      if(i>=ids.length){
        $('#renewStatus').text('批量续期完成');
        setTimeout(()=>location.reload(), 900);
        return;
      }
      $('#renewStatus').text('处理中 '+(i+1)+'/'+ids.length);
      $.post('api/subdomains.php', {csrf:'<?= h(csrf_token()) ?>', act:'renew', account_id:'<?= (int)$selected ?>', subdomain_id: ids[i]}, function(resp){
      }, 'json').always(function(){
        updateProg(p);
        setTimeout(()=>next(i+1), 350);
      });
    }
    next(0);
  });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
