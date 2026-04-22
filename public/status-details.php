<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();
$page = 'dashboard';
$pdo = db();

$status = $_GET['status'] ?? 'present';
if (!in_array($status, ['present','awol','leave','sick','unrecorded'], true)) $status = 'present';
$date = $_GET['date'] ?? date('Y-m-d');
$branchId = user_branch_filter($user);
if ($user['role'] === 'admin' && !empty($_GET['branch'])) $branchId = (int)$_GET['branch'];

$where = '1=1'; $params = [':d' => $date];
if ($branchId !== null) { $where .= ' AND e.branch_id = :b'; $params[':b'] = $branchId; }

if ($status === 'unrecorded') {
    $where .= ' AND ds.status IS NULL';
} else {
    $where .= ' AND ds.status = :s';
    $params[':s'] = $status;
}

$sql = "SELECT e.*, b.name AS branch_name, ds.status, ds.notes
        FROM employees e
        JOIN branches b ON b.id = e.branch_id
        LEFT JOIN daily_status ds ON ds.employee_id = e.id AND ds.date = :d
        WHERE $where AND e.active = 1
        ORDER BY b.name, e.full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = status_label($status) . ' — Details';
$backQS = 'date=' . urlencode($date) . ($branchId ? '&branch=' . $branchId : '');
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><?= e(status_label($status)) ?> Personnel</h1>
    <div class="desc"><?= e(date('l, j F Y', strtotime($date))) ?> · <?= count($rows) ?> personnel</div>
  </div>
  <a class="btn btn-secondary" href="/?<?= e($backQS) ?>">&larr; Back to Dashboard</a>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Service No</th><th>Name</th><th>Rank</th><th>Gender</th><th>Branch</th><th>Phone</th><th>Status</th><th>Notes</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['service_no']) ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['rank']) ?></td>
            <td><?= $r['gender']==='M'?'Male':'Female' ?></td>
            <td><?= e($r['branch_name']) ?></td>
            <td><?= e($r['phone']) ?></td>
            <td><?= $r['status'] ? status_badge($r['status']) : '<span class="muted">Unrecorded</span>' ?></td>
            <td><?= e($r['notes'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px">No personnel with this status on <?= e($date) ?>.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
