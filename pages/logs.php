<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/security.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$db = db($cfg);

$type = trim((string)($_GET['type'] ?? ''));
$result = trim((string)($_GET['result'] ?? ''));
$accountId = (int)($_GET['account_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$pageNo = max(1, (int)($_GET['p'] ?? 1));
$ps = 20;
$off = ($pageNo-1)*$ps;

$where = [];
$params = [];
if (!$isAdmin) { $where[]='user_id=:uid'; $params['uid']=(int)$u['id']; }
if ($type !== '') { $where[]='action_type=:t'; $params['t']=$type; }
if ($result !== '') { $where[]='result=:r'; $params['r']=$result; }
if ($accountId) { $where[]='account_id=:aid'; $params['aid']=$accountId; }
if ($q !== '') { $where[]='(domain LIKE :q OR action LIKE :q OR message LIKE :q)'; $params['q']='%'.$q.'%'; }
$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
$total = (int)db_row($db, 'SELECT COUNT(*) AS c FROM logs '.$wsql, $params)['c'];
$rows = db_all($db, 'SELECT * FROM logs '.$wsql.' ORDER BY id DESC LIMIT '.$ps.' OFFSET '.$off, $params);

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">日志</h3>
  <a class="btn btn-outline-secondary" href="api/export_logs.php?<?= http_build_query($_GET) ?>">导出 CSV</a>
</div>

<div class="card shadow-sm mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <input type="hidden" name="page" value="logs">
    <div class="col-md-2"><label class="form-label">类型</label><input class="form-control" name="type" value="<?= h($type) ?>" placeholder="op/renew/auth"></div>
    <div class="col-md-2"><label class="form-label">结果</label><input class="form-control" name="result" value="<?= h($result) ?>" placeholder="success/fail/skip"></div>
    <div class="col-md-2"><label class="form-label">账户ID</label><input class="form-control" name="account_id" value="<?= $accountId?:'' ?>"></div>
    <div class="col-md-3"><label class="form-label">搜索</label><input class="form-control" name="q" value="<?= h($q) ?>"></div>
    <div class="col-md-3 d-flex align-items-end gap-2">
      <button class="btn btn-primary">筛选</button>
      <a class="btn btn-outline-secondary" href="index.php?page=logs">清空</a>
    </div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="card-body">
  <div class="small text-muted mb-2">总计 <?= (int)$total ?> 条</div>
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead><tr><th>时间</th><th>用户</th><th>账户</th><th>域名</th><th>类型</th><th>动作</th><th>结果</th><th>信息</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td class="small"><?= date('Y-m-d H:i:s', (int)$r['created_at']) ?></td>
          <td><?= h((string)($r['user_id'] ?? '')) ?></td>
          <td><?= h((string)($r['account_id'] ?? '')) ?></td>
          <td><?= h((string)($r['domain'] ?? '')) ?></td>
          <td><?= h((string)$r['action_type']) ?></td>
          <td><?= h((string)$r['action']) ?></td>
          <td><?= h((string)$r['result']) ?></td>
          <td class="small text-muted" style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h((string)($r['message'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $pages=(int)ceil($total/$ps); if($pages>1): ?>
  <nav><ul class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <li class="page-item <?= $i===$pageNo?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>'logs','p'=>$i])) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div></div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
