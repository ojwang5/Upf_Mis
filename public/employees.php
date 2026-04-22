<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();
$page = 'employees';
$page_title = 'Employees';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $service_no = trim($_POST['service_no'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $gender = $_POST['gender'] ?? 'M';
        $rank = trim($_POST['rank'] ?? '');
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');

        if ($user['role'] === 'manager') $branch_id = (int)$user['branch_id'];
        if (!can_access_branch($user, $branch_id)) { http_response_code(403); exit('Forbidden'); }

        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id, phone) VALUES (?,?,?,?,?,?)");
            try {
                $stmt->execute([$service_no, $full_name, $gender, $rank, $branch_id, $phone]);
                flash('msg', 'Employee added.');
            } catch (PDOException $e) {
                flash('err', 'Could not add employee: ' . $e->getMessage());
            }
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE employees SET service_no=?, full_name=?, gender=?, rank=?, branch_id=?, phone=? WHERE id=?");
            $stmt->execute([$service_no, $full_name, $gender, $rank, $branch_id, $phone, $id]);
            flash('msg', 'Employee updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $emp = $pdo->prepare("SELECT branch_id FROM employees WHERE id=?");
        $emp->execute([$id]); $row = $emp->fetch();
        if ($row && can_access_branch($user, (int)$row['branch_id'])) {
            $pdo->prepare("DELETE FROM employees WHERE id=?")->execute([$id]);
            flash('msg', 'Employee removed.');
        }
    }
    header('Location: /employees.php'); exit;
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
$where = '1=1'; $params = [];
if ($user['role'] === 'manager') { $where .= ' AND e.branch_id = ?'; $params[] = $user['branch_id']; }
elseif (!empty($_GET['branch'])) { $where .= ' AND e.branch_id = ?'; $params[] = (int)$_GET['branch']; }
if (!empty($_GET['q'])) { $where .= ' AND (e.full_name LIKE ? OR e.service_no LIKE ?)'; $params[] = '%'.$_GET['q'].'%'; $params[] = '%'.$_GET['q'].'%'; }

$stmt = $pdo->prepare("SELECT e.*, b.name AS branch_name FROM employees e JOIN branches b ON b.id=e.branch_id WHERE $where ORDER BY e.full_name");
$stmt->execute($params);
$employees = $stmt->fetchAll();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = null;
if ($editId) {
    $s = $pdo->prepare("SELECT * FROM employees WHERE id=?"); $s->execute([$editId]);
    $editing = $s->fetch();
    if ($editing && !can_access_branch($user, (int)$editing['branch_id'])) $editing = null;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Employees</h1><div class="desc">Manage personnel records</div></div>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <h3><?= $editing ? 'Edit Employee' : 'Add Employee' ?></h3>
  <form method="post">
    <input type="hidden" name="action" value="<?= $editing?'update':'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div class="form-group"><label>Service No</label><input type="text" name="service_no" required value="<?= e($editing['service_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required value="<?= e($editing['full_name'] ?? '') ?>"></div>
      <div class="form-group"><label>Rank</label><input type="text" name="rank" required value="<?= e($editing['rank'] ?? '') ?>"></div>
      <div class="form-group"><label>Gender</label>
        <select name="gender"><option value="M" <?= ($editing['gender']??'')==='M'?'selected':'' ?>>Male</option><option value="F" <?= ($editing['gender']??'')==='F'?'selected':'' ?>>Female</option></select>
      </div>
      <div class="form-group"><label>Branch</label>
        <?php if ($user['role']==='admin'): ?>
        <select name="branch_id">
          <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= ($editing['branch_id']??0)==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php else: ?>
          <input type="text" disabled value="<?= e($user['branch_name']) ?>">
        <?php endif; ?>
      </div>
      <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($editing['phone'] ?? '') ?>"></div>
      <div class="form-group" style="flex:0">
        <label>&nbsp;</label>
        <button class="btn" type="submit"><?= $editing?'Update':'Add' ?></button>
        <?php if ($editing): ?> <a class="btn btn-secondary" href="/employees.php">Cancel</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <form method="get" class="form-row" style="margin-bottom:12px">
    <div class="form-group"><label>Search</label><input type="text" name="q" placeholder="Name or Service No" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <?php if ($user['role']==='admin'): ?>
    <div class="form-group"><label>Branch</label>
      <select name="branch"><option value="">All</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= (($_GET['branch']??'')==$b['id'])?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group" style="flex:0"><label>&nbsp;</label><button class="btn btn-secondary">Filter</button></div>
  </form>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Service No</th><th>Name</th><th>Rank</th><th>Gender</th><th>Branch</th><th>Phone</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($employees as $e): ?>
        <tr>
          <td><?= e($e['service_no']) ?></td>
          <td><?= e($e['full_name']) ?></td>
          <td><?= e($e['rank']) ?></td>
          <td><?= $e['gender']==='M'?'Male':'Female' ?></td>
          <td><?= e($e['branch_name']) ?></td>
          <td><?= e($e['phone']) ?></td>
          <td>
            <a class="btn btn-sm btn-secondary" href="/employees.php?edit=<?= $e['id'] ?>">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this employee?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $e['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($employees)): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px">No employees found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
