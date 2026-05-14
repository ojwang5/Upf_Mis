<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/branch.php';

$user = require_login();
$page = 'disciplinary';
$page_title = 'Officer Disciplinary';
$pdo = db();

$branchId = user_branch_filter($user);
$branches = [];
if ($user['role'] === 'admin') {
    $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
    if (!empty($_GET['branch'])) {
        $branchId = (int)$_GET['branch'];
    }
}

$where = "d.status = 'active'";
$params = [];
if ($branchId !== null) {
    $where .= ' AND d.branch_id = :b';
    $params[':b'] = $branchId;
}

// Older DBs might not have the officer_disciplinary table yet.
$tableExists = function(string $table) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :t LIMIT 1");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
};

$rows = [];
if ($tableExists('officer_disciplinary')) {
    $sql = "
        SELECT d.id, d.reason, d.start_date, d.end_date, d.status,
               e.id AS employee_id, e.service_no, e.full_name, e.rank, e.gender,
               b.name AS branch_name
        FROM officer_disciplinary d
        JOIN employees e ON e.id = d.employee_id
        JOIN branches b ON b.id = d.branch_id
        WHERE $where
        ORDER BY b.name, e.rank, e.full_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}


include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Officer Disciplinary</h1>
    <div class="desc">Currently active disciplinary actions per officer</div>
  </div>
  <form method="get" class="form-row" style="margin:0">
    <?php if ($user['role'] === 'admin'): ?>
      <div class="form-group" style="min-width:160px">
        <label>Branch</label>
        <select name="branch" onchange="this.form.submit()">
          <option value="">All Branches</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ($branchId === (int)$b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($rows)): ?>
  <div class="card"><div class="muted">No active disciplinary records found.</div></div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Service No</th>
            <th>Name</th>
            <th>Rank</th>
            <th>Branch</th>
            <th>Reason</th>
            <th>Start</th>
            <th>End</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['service_no']) ?></td>
              <td><?= e($r['full_name']) ?></td>
              <td><?= e($r['rank']) ?></td>
              <td><?= e($r['branch_name']) ?></td>
              <td><?= e($r['reason']) ?></td>
              <td><?= e($r['start_date']) ?></td>
              <td><?= e($r['end_date'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

