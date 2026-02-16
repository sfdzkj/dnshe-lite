<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/accounts.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/log.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$db = db($cfg);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';

    if ($act === 'export_accounts') {
        $password = (string)($_POST['password'] ?? '');
        $hash = db_row($db,'SELECT password_hash FROM users WHERE id=:id',['id'=>(int)$u['id']])['password_hash'] ?? '';
        if (!password_verify($password, $hash)) {
            $msg = '密码验证失败';
        } else {
            $accounts = list_accounts($cfg, (int)$u['id'], false);
            $plain = [];
            foreach($accounts as $a){
                $plain[] = [
                    'remark'=>$a['remark'],
                    'api_key'=>account_decrypt($cfg, $a['api_key_enc']),
                    'api_secret'=>account_decrypt($cfg, $a['api_secret_enc']),
                    'auto_renew'=>(int)$a['auto_renew'],
                ];
            }
            $json = json_encode(['ver'=>1,'ts'=>time(),'accounts'=>$plain], JSON_UNESCAPED_UNICODE);
            $salt = bin2hex(random_bytes(16));
            $key = pbkdf2_key($password, $salt);
            $cipher = encrypt_gcm($json, $key);
            $blob = json_encode(['salt'=>$salt,'cipher'=>$cipher], JSON_UNESCAPED_UNICODE);

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="accounts_backup_' . date('Ymd_His') . '.json"');
            echo $blob;
            add_log($cfg, (int)$u['id'], null, null, 'system', 'export_accounts', 'success', '');
            exit;
        }
    }

    if ($act === 'import_accounts') {
        $password = (string)($_POST['password'] ?? '');
        $hash = db_row($db,'SELECT password_hash FROM users WHERE id=:id',['id'=>(int)$u['id']])['password_hash'] ?? '';
        if (!password_verify($password, $hash)) {
            $msg = '密码验证失败';
        } else if (empty($_FILES['file']['tmp_name'])) {
            $msg = '请选择备份文件';
        } else {
            $raw = file_get_contents($_FILES['file']['tmp_name']);
            $obj = json_decode($raw, true);
            if (!is_array($obj) || empty($obj['salt']) || empty($obj['cipher'])) {
                $msg = '备份文件格式错误';
            } else {
                try {
                    $key = pbkdf2_key($password, (string)$obj['salt']);
                    $json = decrypt_gcm((string)$obj['cipher'], $key);
                    $data = json_decode($json, true);
                    if (!is_array($data) || empty($data['accounts'])) throw new RuntimeException('内容解析失败');
                    foreach($data['accounts'] as $a){
                        upsert_account($cfg, (int)$u['id'], null, (string)($a['remark']??''), (string)$a['api_key'], (string)$a['api_secret'], (int)($a['auto_renew']??1));
                    }
                    add_log($cfg, (int)$u['id'], null, null, 'system', 'import_accounts', 'success', '');
                    $msg = '导入成功';
                } catch (Throwable $e) {
                    $msg = '导入失败：' . $e->getMessage();
                }
            }
        }
    }

    if ($isAdmin && $act === 'backup_db') {
        $src = $cfg['db_path'];
        $dst = __DIR__ . '/../backups/db_backup_' . date('Ymd_His') . '.db';
        if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0775, true);
        copy($src, $dst);
        add_log($cfg, (int)$u['id'], null, null, 'system', 'backup_db', 'success', basename($dst));
        $msg = '备份成功：' . basename($dst);
    }

    if ($isAdmin && $act === 'restore_db') {
        $confirm = (string)($_POST['confirm'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $hash = db_row($db,'SELECT password_hash FROM users WHERE id=:id',['id'=>(int)$u['id']])['password_hash'] ?? '';
        if ($confirm !== 'RESTORE') $msg = '请输入 RESTORE 以确认覆盖恢复';
        else if (!password_verify($password, $hash)) $msg = '密码验证失败';
        else if (empty($_FILES['dbfile']['tmp_name'])) $msg = '请选择数据库文件';
        else {
            $db->close();
            copy($_FILES['dbfile']['tmp_name'], $cfg['db_path']);
            add_log($cfg, (int)$u['id'], null, null, 'system', 'restore_db', 'success', '');
            $msg = '恢复完成，请刷新页面';
        }
    }
}

$backupFiles = [];
if ($isAdmin) {
    $dir = __DIR__ . '/../backups';
    for ($i=0;$i<1;$i++){} // keep php syntax simple
    if (is_dir($dir)) {
        $backupFiles = array_reverse(array_map('basename', glob($dir.'/*.db') ?: []));
    }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<h3>备份 / 恢复</h3>
<?php if($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-body">
      <h5>普通用户：导出/导入 DNSHE 账户</h5>
      <form method="post" class="mb-3">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="act" value="export_accounts">
        <div class="mb-2"><label class="form-label">输入登录密码（二次验证）</label><input type="password" class="form-control" name="password" required></div>
        <button class="btn btn-primary">导出（加密 JSON）</button>
      </form>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="act" value="import_accounts">
        <div class="mb-2"><label class="form-label">备份文件</label><input type="file" class="form-control" name="file" accept="application/json" required></div>
        <div class="mb-2"><label class="form-label">输入登录密码（用于解密）</label><input type="password" class="form-control" name="password" required></div>
        <button class="btn btn-success">导入</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-body">
      <h5>管理员：数据库备份/恢复</h5>
      <?php if(!$isAdmin): ?>
        <div class="text-muted">仅管理员可用</div>
      <?php else: ?>
        <form method="post" class="mb-3">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="act" value="backup_db">
          <button class="btn btn-outline-primary">一键备份数据库</button>
        </form>

        <div class="mb-3">
          <div class="small text-muted">备份列表</div>
          <ul class="list-group">
            <?php foreach($backupFiles as $f): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="secret-mask"><?= h($f) ?></span>
                <a class="btn btn-sm btn-outline-secondary" href="backups/<?= rawurlencode($f) ?>" target="_blank">下载</a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <form method="post" enctype="multipart/form-data" onsubmit="return confirmAction('恢复将覆盖当前数据库，确认继续？')">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="act" value="restore_db">
          <div class="mb-2"><label class="form-label">选择 .db 文件</label><input type="file" class="form-control" name="dbfile" accept=".db" required></div>
          <div class="mb-2"><label class="form-label">输入登录密码（二次验证）</label><input type="password" class="form-control" name="password" required></div>
          <div class="mb-2"><label class="form-label">输入 RESTORE 确认覆盖</label><input class="form-control" name="confirm" placeholder="RESTORE" required></div>
          <button class="btn btn-danger">执行恢复</button>
        </form>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
