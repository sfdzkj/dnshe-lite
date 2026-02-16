<?php
$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/log.php';

$u = require_admin($cfg);
$db = db($cfg);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'user');
        if (!password_strong_enough($password)) {
            $msg = '密码强度不足';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db_exec($db, 'INSERT INTO users(username,password_hash,role,disabled,must_change_password,created_at) VALUES(:u,:p,:r,0,1,:t)', [
                    'u'=>$username,'p'=>$hash,'r'=>$role,'t'=>time()
                ]);
                add_log($cfg, (int)$u['id'], null, null, 'system', 'create_user', 'success', $username);
                $msg = '创建成功（首次登录强制改密）';
            } catch (Throwable $e) {
                $msg = '创建失败：' . $e->getMessage();
            }
        }
    }
    if ($act === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $role = (string)($_POST['role'] ?? 'user');
        $password = (string)($_POST['password'] ?? '');
        
        if (!$username) {
            $msg = '用户名不能为空';
        } else if ($id === (int)$u['id']) {
            $msg = '无法编辑自己的账号';
        } else if ($id) {
            // 检查目标用户是否为Admin（不能编辑Admin）
            $targetUser = db_row($db, 'SELECT role FROM users WHERE id=:id', ['id'=>$id]);
            if ($targetUser && (string)$targetUser['role'] === 'admin') {
                $msg = '无法编辑Admin用户';
            } else {
                try {
                    if ($password) {
                        if (!password_strong_enough($password)) {
                            $msg = '密码强度不足';
                        } else {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            db_exec($db, 'UPDATE users SET username=:u, role=:r, password_hash=:p WHERE id=:id', [
                                'u'=>$username, 'r'=>$role, 'p'=>$hash, 'id'=>$id
                            ]);
                            add_log($cfg, (int)$u['id'], null, null, 'system', 'edit_user', 'success', "用户ID:{$id}，用户名:{$username}");
                            $msg = '更新成功';
                        }
                    } else {
                        db_exec($db, 'UPDATE users SET username=:u, role=:r WHERE id=:id', [
                            'u'=>$username, 'r'=>$role, 'id'=>$id
                        ]);
                        add_log($cfg, (int)$u['id'], null, null, 'system', 'edit_user', 'success', "用户ID:{$id}，用户名:{$username}");
                        $msg = '更新成功';
                    }
                } catch (Throwable $e) {
                    if (strpos((string)$e->getMessage(), 'UNIQUE constraint failed') !== false) {
                        $msg = '用户名已存在';
                    } else {
                        $msg = '更新失败：' . $e->getMessage();
                    }
                }
            }
        }
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$u['id']) {
            $msg = '无法删除自己的账号';
        } else if ($id) {
            // 检查目标用户是否为Admin（不能删除Admin）
            $targetUser = db_row($db, 'SELECT username, role FROM users WHERE id=:id', ['id'=>$id]);
            if (!$targetUser) {
                $msg = '用户不存在';
            } else if ((string)$targetUser['role'] === 'admin') {
                $msg = '无法删除Admin用户';
            } else {
                try {
                    $delUsername = (string)$targetUser['username'];
                    // 先记录删除日志
                    add_log($cfg, (int)$u['id'], null, null, 'system', 'delete_user', 'success', "用户ID:{$id}，用户名:{$delUsername}");
                    
                    // 级联删除用户相关数据
                    // 1. 获取用户的所有账户
                    $accounts = db_all($db, 'SELECT id FROM dnshe_accounts WHERE user_id=:u', ['u'=>$id]);
                    foreach ($accounts as $acc) {
                        $accId = (int)$acc['id'];
                        // 删除该账户的相关日志
                        db_exec($db, 'DELETE FROM logs WHERE account_id=:a', ['a'=>$accId]);
                        // 删除域名缓存
                        db_exec($db, 'DELETE FROM domain_cache WHERE account_id=:a', ['a'=>$accId]);
                        // 删除账户
                        db_exec($db, 'DELETE FROM dnshe_accounts WHERE id=:id', ['id'=>$accId]);
                    }
                    // 2. 删除用户相关日志
                    db_exec($db, 'DELETE FROM logs WHERE user_id=:u', ['u'=>$id]);
                    // 3. 删除用户
                    db_exec($db, 'DELETE FROM users WHERE id=:id', ['id'=>$id]);
                    $msg = '删除成功';
                } catch (Throwable $e) {
                    $msg = '删除失败：' . $e->getMessage();
                }
            }
        }
    }
    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $disabled = (int)($_POST['disabled'] ?? 0);
        if ($id) {
            db_exec($db, 'UPDATE users SET disabled=:d WHERE id=:id AND role<>"admin"', ['d'=>$disabled,'id'=>$id]);
            add_log($cfg, (int)$u['id'], null, null, 'system', 'toggle_user', 'success', (string)$id);
        }
        header('Location: index.php?page=users');
        exit;
    }
}

$users = db_all($db, 'SELECT id,username,role,disabled,created_at,last_login_at FROM users ORDER BY id DESC');

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/sidebar.php';
?>
<h3>用户管理</h3>
<?php if($msg): ?><div class="alert alert-info"><?= h($msg) ?></div><?php endif; ?>

<!-- 编辑用户模态框 -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">编辑用户</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" onsubmit="return confirmAction('确认修改用户信息？')">
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="act" value="edit">
          <input type="hidden" name="id" id="editUserId">
          <div class="mb-3">
            <label class="form-label">用户名</label>
            <input type="text" class="form-control" name="username" id="editUsername" required>
          </div>
          <div class="mb-3">
            <label class="form-label">角色</label>
            <select class="form-select" name="role" id="editRole">
              <option value="user">普通用户</option>
              <option value="admin">管理员</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">新密码（留空则不修改）</label>
            <input type="password" class="form-control" name="password" id="editPassword">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
          <button type="submit" class="btn btn-primary">更新</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 删除用户确认模态框 -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">删除用户</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="id" id="deleteUserId">
          <p class="text-danger">确定要删除用户 <strong id="deleteUsername"></strong> 吗？</p>
          <p class="text-muted">此操作不可撤销，用户的所有数据也将被删除。</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
          <button type="submit" class="btn btn-danger">删除</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card shadow-sm"><div class="card-body">
      <h5>新增用户</h5>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="act" value="create">
        <div class="mb-2"><label class="form-label">用户名</label><input class="form-control" name="username" required></div>
        <div class="mb-2"><label class="form-label">初始密码（首次登录强制改密）</label><input type="password" class="form-control" name="password" required></div>
        <div class="mb-2"><label class="form-label">角色</label><select class="form-select" name="role"><option value="user">普通用户</option><option value="admin">管理员</option></select></div>
        <button class="btn btn-primary">创建</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm"><div class="card-body">
      <h5>用户列表</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead><tr><th>ID</th><th>用户名</th><th>角色</th><th>状态</th><th>创建</th><th>最近登录</th><th>操作</th></tr></thead>
          <tbody>
            <?php foreach($users as $x): ?>
            <tr>
              <td><?= (int)$x['id'] ?></td>
              <td><?= h($x['username']) ?></td>
              <td><?= h($x['role']) ?></td>
              <td><?= (int)$x['disabled']===1?'<span class="badge bg-secondary">禁用</span>':'<span class="badge bg-success">正常</span>' ?></td>
              <td class="small"><?= date('Y-m-d', (int)$x['created_at']) ?></td>
              <td class="small"><?= $x['last_login_at']?date('Y-m-d H:i', (int)$x['last_login_at']):'-' ?></td>
              <td>
                <?php if($x['role']!=='admin'): ?>
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="editUser(<?= (int)$x['id'] ?>, '<?= h($x['username']) ?>', '<?= h($x['role']) ?>')">编辑</button>
                  <button type="button" class="btn btn-outline-warning" onclick="toggleUser(<?= (int)$x['id'] ?>, <?= (int)$x['disabled']===1?0:1 ?>)">
                    <?= (int)$x['disabled']===1?'启用':'禁用' ?>
                  </button>
                  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal" onclick="deleteUser(<?= (int)$x['id'] ?>, '<?= h($x['username']) ?>')">删除</button>
                </div>
                <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>

<script>
function editUser(id, username, role) {
  document.getElementById('editUserId').value = id;
  document.getElementById('editUsername').value = username;
  document.getElementById('editRole').value = role;
  document.getElementById('editPassword').value = '';
}

function deleteUser(id, username) {
  document.getElementById('deleteUserId').value = id;
  document.getElementById('deleteUsername').textContent = username;
}

function toggleUser(id, disabled) {
  if (!confirm('确认变更用户状态？')) return;
  const form = document.createElement('form');
  form.method = 'post';
  form.innerHTML = `
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="act" value="toggle">
    <input type="hidden" name="id" value="${id}">
    <input type="hidden" name="disabled" value="${disabled}">
  `;
  document.body.appendChild(form);
  form.submit();
}

function confirmAction(msg) {
  return confirm(msg);
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
