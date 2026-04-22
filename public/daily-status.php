<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();
$page = 'daily';
$page_title = 'Daily Status';
$pdo = db();

$date = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    $notes = $_POST['notes'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO daily_status (employee_id, date, status, notes, recorded_by) VALUES (?,?,?,?,?)
        ON CONFLICT(employee_id, date) DO UPDATE SET status=excluded.status, notes=excluded.notes, recorded_by=excluded.recorded_by
    ");
    foreach ($statuses as $eid => $st) {
        $eid = (int)$eid;
        $emp = $pdo->prepare("SELECT branch_id FROM employees WHERE id=?"); $emp->execute([$eid]); $r = $emp->fetch();
        if (!$r || !can_access_branch($user, (int)$r['branch_id'])) continue;
        if (!in_array($st, ['present','awol','leave','sick'], true)) continue;
        $stmt->execute([$eid, $date, $st, $notes[$eid] ?? null, $user['id']]);
    }
    flash('msg', 'Daily status saved for ' . $date);
    header('Location: /daily-status.php?date=' . urlencode($date)); exit;
}

$where = '1=1'; $params = [':d' => $date];
if ($user['role'] === 'manager') { $where .= ' AND e.branch_id = :b'; $params[':b'] = $user['branch_id']; }
elseif (!empty($_GET['branch'])) { $where .= ' AND e.branch_id = :b'; $params[':b'] = (int)$_GET['branch']; }

$sql = "SELECT e.*, b.name AS branch_name, ds.status, ds.notes
        FROM employees e
        JOIN branches b ON b.id=e.branch_id
        LEFT JOIN daily_status ds ON ds.employee_id=e.id AND ds.date=:d
        WHERE $where AND e.active=1
        ORDER BY b.name, e.full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();

$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Daily Status</h1><div class="desc">Record personnel attendance for a given date</div></div>
  <form method="get" class="form-row" style="margin:0">
    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
    <?php if ($user['role']==='admin'): ?>
    <div class="form-group"><label>Branch</label>
      <select name="branch" onchange="this.form.submit()"><option value="">All</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= (($_GET['branch']??'')==$b['id'])?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="date" value="<?= e($date) ?>">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Service No</th><th>Name</th><th>Rank</th><th>Branch</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['service_no']) ?></td>
              <td><?= e($r['full_name']) ?></td>
              <td><?= e($r['rank']) ?></td>
              <td><?= e($r['branch_name']) ?></td>
              <td>
                <select name="status[<?= $r['id'] ?>]">
                  <?php foreach (['present','awol','leave','sick'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($r['status']??'')===$s?'selected':'' ?>><?= status_label($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="text" name="notes[<?= $r['id'] ?>]" value="<?= e($r['notes'] ?? '') ?>" placeholder="Optional"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:14px"><button class="btn" type="submit">Save Status</button></div>
  </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
