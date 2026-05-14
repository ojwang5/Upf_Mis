<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();
$page = 'dashboard';
$pdo = db();

$status = $_GET['status'] ?? 'present';
if (!in_array($status, ['present','awol','leave','onleave','sick','unrecorded'], true)) $status = 'present';
$date = $_GET['date'] ?? date('Y-m-d');
$branchId = user_branch_filter($user);
if ($user['role'] === 'admin' && !empty($_GET['branch'])) $branchId = (int)$_GET['branch'];

$where = '1=1'; $params = [':d' => $date];
if ($branchId !== null) { $where .= ' AND e.branch_id = :b'; $params[':b'] = $branchId; }

if ($status === 'unrecorded') {
    $where .= ' AND ds.status IS NULL';
} else {
    if ($status === 'onleave') {
        // computed onleave from approved leave_requests (includes start/end date days)
        $where .= " AND (
            ds.status = 'onleave' OR
            EXISTS (
                SELECT 1 FROM leave_requests lr
                WHERE lr.employee_id = e.id
                  AND lr.status='approved'
                  AND lr.start_date <= :d
                  AND lr.end_date >= :d
                  AND lr.destination IS NOT NULL
            )
        )";
    } else {
        $where .= ' AND ds.status = :s';
        $params[':s'] = $status;
    }
}

$sql = "SELECT e.*, b.name AS branch_name, ds.status, ds.notes
        FROM employees e
        JOIN branches b ON b.id = e.branch_id
        LEFT JOIN daily_status ds ON ds.employee_id = e.id AND ds.date = :d
        LEFT JOIN leave_requests lr ON lr.employee_id = e.id
             AND lr.status='approved'
             AND lr.start_date <= :d
             AND lr.end_date >= :d
        WHERE $where AND e.active = 1
        ORDER BY b.name, e.full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();

// When showing onleave/leave, we use leave_requests to compute destination + onleave days.
// (We purposely keep daily_status as-is for manual daily overrides.)

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
      <thead><tr><th>Force/File No</th><th>Name</th><th>Rank</th><th>Gender</th><th>Branch</th><th>Phone</th><th>Status</th><th>Leave Destination</th><th>Notes</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['service_no']) ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['rank']) ?></td>
            <td><?= $r['gender']==='M'?'Male':'Female' ?></td>
            <td><?= e($r['branch_name']) ?></td>
<td><?= e($r['phone']) ?></td>
            <td>
              <?php
                $computedOnLeave = false;
                if ($status === 'onleave') {
                    $computedOnLeave = true;
                }
                if ($status === 'leave') {
                    // legacy label view; treat leave as onleave if approved.
                    $computedOnLeave = true;
                }
                if ($computedOnLeave && (empty($r['status']) || $r['status']==='onleave' || $r['status']==='leave')) {
                    echo status_badge('onleave');
                } else {
                    echo ($r['status'] ? status_badge($r['status']) : '<span class="muted">Unrecorded</span>');
                }
              ?>
            </td>
            <td>
              <?php
                $dest = '';
                if ($status === 'onleave' || $status === 'leave') {
                    $dStmt = $pdo->prepare("SELECT destination FROM leave_requests
                        WHERE employee_id=? AND status='approved'
                          AND start_date <= :d AND end_date >= :d
                        ORDER BY submitted_at DESC LIMIT 1");
                    $dStmt->execute([(int)$r['id'], ':d' => $date]);
                    $rowDest = $dStmt->fetch();
                    $dest = $rowDest['destination'] ?? '';
                }
              ?>
              <?= e($dest) ?>
            </td>
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
