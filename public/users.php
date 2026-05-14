<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_role(['admin','manager']);
$page = 'users';
$page_title = 'User Accounts';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $username   = trim($_POST['username'] ?? '');
        $full_name  = trim($_POST['full_name'] ?? '');
        $password   = $_POST['password'] ?? '';
        $role       = $_POST['role'] ?? 'officer';
        $branch_id  = $_POST['branch_id'] ?? null;

        // Manager can only create officers in their own branch
        if (is_manager($user)) {
            $role = 'officer';
            $branch_id = (int)$user['branch_id'];
        } else {
            // Admin restrictions
            if (!in_array($role, ['admin','manager','officer'], true)) $role = 'officer';
            $branch_id = $role === 'admin' ? null : ($branch_id ? (int)$branch_id : null);
            if ($role !== 'admin' && !$branch_id) {
                flash('err', 'Please select a branch for this user.');
                header('Location: /users.php'); exit;
            }
        }
        if ($username === '' || $full_name === '' || strlen($password) < 4) {
            flash('err', 'Username, full name, and password (min 4 chars) are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, branch_id) VALUES (?,?,?,?,?)");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $role, $branch_id]);
                flash('msg', 'Account created for ' . $full_name . '.');
            } catch (PDOException $e) {
                flash('err', 'Could not create user: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'reset') {
        $id = (int)$_POST['id']; $newpw = $_POST['password'] ?? '';
        $target = $pdo->prepare("SELECT * FROM users WHERE id=?"); $target->execute([$id]); $t = $target->fetch();
        if ($t && (is_admin($user) || (is_manager($user) && $t['role']==='officer' && (int)$t['branch_id']===(int)$user['branch_id'])) && strlen($newpw) >= 4) {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($newpw, PASSWORD_DEFAULT), $id]);
            flash('msg', 'Password reset.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$user['id']) {
            $target = $pdo->prepare("SELECT * FROM users WHERE id=?"); $target->execute([$id]); $t = $target->fetch();
            if ($t && (is_admin($user) || (is_manager($user) && $t['role']==='officer' && (int)$t['branch_id']===(int)$user['branch_id']))) {
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                flash('msg', 'Account removed.');
            }
        }
    }
    header('Location: /users.php'); exit;
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
if (is_admin($user)) {
    $users = $pdo->query("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON b.id=u.branch_id ORDER BY u.role, u.full_name")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON b.id=u.branch_id WHERE u.branch_id=? AND u.role='officer' ORDER BY u.full_name");
    $stmt->execute([$user['branch_id']]); $users = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>User Accounts</h1>
    <div class="desc"><?= is_admin($user) ? 'Manage admins, branch managers, and field officers' : 'Manage field officers in your branch' ?></div>
  </div>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <h3>Create Account</h3>
  <form method="post">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
      <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="4"></div>
      <div class="form-group"><label>Role</label>
        <?php if (is_admin($user)): ?>
        <select name="role" id="role-select" onchange="document.getElementById('br-row').style.display=this.value==='admin'?'none':''">
          <option value="officer">Duty Officer </option>
          <option value="manager">Branch Manager</option>
          <option value="admin">Admin (HQ)</option>
        </select>
        <?php else: ?>
        <input type="text" disabled value="Officer">
        <?php endif; ?>
      </div>
      <div class="form-group" id="br-row">
        <label>Branch</label>
        <?php if (is_admin($user)): ?>
        <select name="branch_id">
          <option value="">— select —</option>
          <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="text" disabled value="<?= e($user['branch_name']) ?>">
        <?php endif; ?>
      </div>
      <div class="form-group" style="flex:0"><label>&nbsp;</label><button class="btn">Create</button></div>
    </div>
  </form>
</div>

<div class="card">
  <h3>Existing Users</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Branch</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['username']) ?></td>
          <td><span class="badge badge-admin"><?= e(ucfirst($u['role'])) ?></span></td>
          <td><?= e($u['branch_name'] ?? '—') ?></td>
          <td>
            <details><summary class="btn btn-sm btn-secondary" style="display:inline-block">Reset password</summary>
              <form method="post" style="display:inline-flex;gap:6px;margin-top:8px">
                <input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="password" name="password" placeholder="New password" required minlength="4">
                <button class="btn btn-sm">Save</button>
              </form>
            </details>
            <?php if ($u['id'] != $user['id']): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this user?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-danger">Delete</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
