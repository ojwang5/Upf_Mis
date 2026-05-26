<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/audit.php';

$user = require_role(['admin','manager']);
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$target = null;

if ($id <= 0) {
    flash('err', 'Missing or invalid user id.');
    header('Location: /users.php');
    exit;
}

// Fetch target user
$stmt = $pdo->prepare("SELECT u.*, b.name AS branch_name, b.code AS branch_code FROM users u LEFT JOIN branches b ON b.id=u.branch_id WHERE u.id=?");
$stmt->execute([$id]);
$target = $stmt->fetch();

if (!$target) {
    flash('err', 'User not found.');
    header('Location: /users.php');
    exit;
}

$canEdit = is_admin($user) || (
    is_manager($user)
    && $target['role'] === 'officer'
    && (int)$target['branch_id'] === (int)$user['branch_id']
);

if (!$canEdit) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = $_POST['role'] ?? 'officer';
        $branch_id = $_POST['branch_id'] ?? null;

        // Manager can only edit officers within their branch.
        if (is_manager($user)) {
            $role = 'officer';
            $branch_id = (int)$user['branch_id'];
        } else {
            // Admin constraints
            if (!in_array($role, ['admin', 'manager', 'officer'], true)) {
                $role = 'officer';
            }
            if ($role === 'admin') {
                $branch_id = null;
            } else {
                $branch_id = $branch_id ? (int)$branch_id : null;
                if (!$branch_id) {
                    flash('err', 'Please select a branch for this user.');
                    header('Location: /user-edit.php?id=' . $id);
                    exit;
                }
            }
        }

        // Validate
        if ($full_name === '') {
            flash('err', 'Full name is required.');
        } else {
            // Email is REQUIRED so OTP can be sent to the correct address.
            // If the DB has no email column, we cannot reliably send OTP.
            $cols = [];
            foreach ($pdo->query("PRAGMA table_info(users)") as $c) {
                $cols[$c['name']] = true;
            }

            if (!isset($cols['email'])) {
                // DB already supports login OTP delivery, so ensure email column exists.
                flash('err', 'Email column not available in this database schema.');
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('err', 'Please enter a valid email address.');
            }

        }


        // Persist if no validation error
        if (!flash('err')) {
            try {
                $cols = [];
                foreach ($pdo->query("PRAGMA table_info(users)") as $c) {
                    $cols[$c['name']] = true;
                }

                if (isset($cols['email'])) {
                    $pdo->prepare(
                        "UPDATE users SET full_name=?, email=?, role=?, branch_id=? WHERE id=?"
                    )->execute([
                        $full_name,
                        $email,
                        $role,
                        $branch_id,
                        $id,
                    ]);
                } else {
                    // Should not happen because we validate above, but keep safe for older schemas.
                    $pdo->prepare(
                        "UPDATE users SET full_name=?, role=?, branch_id=? WHERE id=?"
                    )->execute([
                        $full_name,
                        $role,
                        $branch_id,
                        $id,
                    ]);
                }

                audit_log($user, 'user.update', 'user', (string)$id, [
                    'target_username' => $target['username'] ?? null,
                    'old' => [
                        'full_name' => $target['full_name'] ?? null,
                        'email' => $target['email'] ?? null,
                        'role' => $target['role'] ?? null,
                        'branch_id' => $target['branch_id'] ?? null,
                    ],
                    'new' => [
                        'full_name' => $full_name,
                        'email' => $email,
                        'role' => $role,
                        'branch_id' => $branch_id,
                    ]
                ]);

                flash('msg', 'Account updated for ' . $full_name . '.');
                header('Location: /users.php');
                exit;
            } catch (Throwable $e) {
                flash('err', 'Could not update user: ' . $e->getMessage());
            }
        }
    }
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Edit User</h1>
    <div class="desc">Update profile, role, and branch (with permission restrictions)</div>
  </div>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <h3>Account Details</h3>

  <form method="post">
    <input type="hidden" name="action" value="update">

    <div class="form-row">
      <div class="form-group"><label>Username</label>
        <input type="text" value="<?= e($target['username']) ?>" disabled>
      </div>

      <div class="form-group"><label>Full Name</label>
        <input type="text" name="full_name" value="<?= e($target['full_name']) ?>" required>
      </div>

      <div class="form-group"><label>Email</label>
        <?php
          $hasEmailCol = false;
          foreach ($pdo->query("PRAGMA table_info(users)") as $c) {
              if (($c['name'] ?? '') === 'email') { $hasEmailCol = true; break; }
          }
        ?>
        <input type="email" name="email" value="<?= e($target['email'] ?? '') ?>" <?= $hasEmailCol ? 'required' : '' ?> placeholder="<?= $hasEmailCol ? '' : 'Not supported by this DB schema' ?>">
      </div>

      <div class="form-group"><label>Role</label>
        <?php if (is_admin($user)): ?>
          <select name="role" id="role-select" onchange="document.getElementById('br-row').style.display=this.value==='admin'?'none':''">
            <option value="officer" <?= $target['role']==='officer'?'selected':''; ?>>Duty Officer</option>
            <option value="manager" <?= $target['role']==='manager'?'selected':''; ?>>Branch Manager</option>
            <option value="admin" <?= $target['role']==='admin'?'selected':''; ?>>Admin (HQ)</option>
          </select>
        <?php else: ?>
          <input type="text" disabled value="Officer">
          <input type="hidden" name="role" value="officer">
        <?php endif; ?>
      </div>

      <div class="form-group" id="br-row" style="<?= (is_admin($user) && $target['role']!=='admin') ? '' : (is_admin($user)?'display:none':'display:block') ?>">
        <label>Branch</label>
        <?php if (is_admin($user)): ?>
          <select name="branch_id">
            <option value="">— select —</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= (int)$target['branch_id']===(int)$b['id']?'selected':''; ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" disabled value="<?= e($target['branch_name'] ?? '') ?>">
          <input type="hidden" name="branch_id" value="<?= (int)$user['branch_id'] ?>">
        <?php endif; ?>
      </div>

      <div class="form-group" style="flex:0"><label>&nbsp;</label>
        <div style="display:flex;gap:10px;align-items:center">
          <a class="btn btn-secondary" href="/users.php">Cancel</a>
          <button class="btn" type="submit">Save changes</button>
        </div>
      </div>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


