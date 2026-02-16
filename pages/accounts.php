<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/log.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $id = ($_POST['id'] ?? '') !== '' ? (int)$_POST['id'] : null;
        $remark = trim((string)($_POST['remark'] ?? ''));
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        $apiSecret = trim((string)($_POST['api_secret'] ?? ''));
        $auto = isset($_POST['auto_renew']) ? 1 : 0;
        $val = validate_dnshe_credentials($cfg, $apiKey, $apiSecret);
        if (!$val['ok']) {
            $msg = 'API 无效：' . h($val['error'] ?? '');
        } else {
            $res = upsert_account($cfg, (int)$u['id'], $id, $remark, $apiKey, $apiSecret, $auto);
            add_log($cfg, (int)$u['id'], (int)$res['id'], null, 'op', 'save_account', 'success', $remark);
            header('Location: index.php?page=accounts');
            exit;
        }
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            delete_account($cfg, (int)$u['id'], $id, $isAdmin);
            add_log($cfg, (int)$u['id'], $id, null, 'op', 'delete_account', 'success');
        }
        header('Location: index.php?page=accounts');
        exit;
    }
}

$accounts = list_accounts($cfg, (int)$u['id'], $isAdmin);
$edit = null;
if (!empty($_GET['edit'])) {
    $edit = get_account($cfg, (int)$_GET['edit']);
    if ($edit && !$isAdmin && (int)$edit['user_id'] !== (int)$u['id']) $edit = null;
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">DNSHE 多账户管理</h3>
</div>
<?php if($msg): ?><div class="alert alert-danger"><?= $msg ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-body">
      <h5><?= $edit ? '编辑账户' : '新增账户' ?></h5>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="act" value="save">
        <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">
        <div class="mb-3"><label class="form-label">备注</label><input class="form-control" name="remark" value="<?= h($edit['remark'] ?? '') ?>"></div>
        <div class="mb-3">
          <label class="form-label">API Key</label>
          <?php if($edit): ?>
            <div class="input-group">
              <input class="form-control" name="api_key" placeholder="留空以保持现有值">
              <span class="input-group-text"><span class="badge bg-success">✓ 已保存</span></span>
            </div>
          <?php else: ?>
            <input class="form-control" name="api_key" required>
          <?php endif; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">API Secret</label>
          <?php if($edit): ?>
            <div class="input-group">
              <input class="form-control" name="api_secret" placeholder="留空以保持现有值">
              <span class="input-group-text"><span class="badge bg-success">✓ 已保存</span></span>
            </div>
          <?php else: ?>
            <input class="form-control" name="api_secret" required>
          <?php endif; ?>
        </div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="auto_renew" id="auto" <?= (!$edit || (int)$edit['auto_renew']===1) ? 'checked' : '' ?>><label class="form-check-label" for="auto">开启自动续期</label></div>
        <button class="btn btn-primary">保存并校验 API</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-body">
      <h5>账户列表</h5>
      <div class="table-responsive mt-3">
        <table class="table table-striped align-middle">
          <thead><tr><th>ID</th><th>备注</th><th>自动续期</th><th>续期链接</th><th>操作</th></tr></thead>
          <tbody>
            <?php foreach($accounts as $a): ?>
            <tr>
              <td><?= (int)$a['id'] ?></td>
              <td><?= h($a['remark'] ?? '') ?></td>
              <td><?= (int)$a['auto_renew']===1 ? '<span class="badge bg-success">ON</span>' : '<span class="badge bg-secondary">OFF</span>' ?></td>
              <td class="small link-buttons">
                <?php $ts=time(); $payload='account|'.(int)$a['id'].'|'.$ts; $sig=sign_payload($payload, get_signing_key($cfg));
                      $url='api/renew.php?mode=account&id='.(int)$a['id'].'&ts='.$ts.'&sig='.$sig;
                      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                      $host = $_SERVER['HTTP_HOST'] ?? '';
                      $basePath = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
                      $fullUrl = $scheme.'://'.$host.($basePath ? $basePath.'/' : '/').$url;
                      $b64u=rtrim(strtr(base64_encode($fullUrl), '+/', '-_'), '='); ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyUrlB64('<?= h($b64u) ?>')">复制链接</button>
              </td>
              <td>
                <a class="btn btn-sm btn-outline-primary" href="index.php?page=accounts&edit=<?= (int)$a['id'] ?>">编辑</a>
                <a class="btn btn-sm btn-outline-info" href="index.php?page=domains&account_id=<?= (int)$a['id'] ?>">域名</a>
                <form method="post" style="display:inline" onsubmit="return confirmAction('确认删除该账户？')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="act" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">删除</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="small text-muted">提示：复制链接到宝塔计划任务/cron，用 curl 访问即可触发续期。</div>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
