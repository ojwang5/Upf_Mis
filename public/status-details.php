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
  <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;justify-content:flex-end">
    <?php $branchParam = ($user['role'] === 'admin' && !empty($_GET['branch'])) ? (int)$_GET['branch'] : ''; ?>
    <?php $branchQS = ($branchParam !== '') ? ('&branch='.(int)$branchParam) : ''; ?>
    <a class="btn btn-secondary" href="/export-status-details.php?type=csv&status=<?= e($status) ?>&date=<?= e($date) ?><?= $branchQS ?>">Export CSV</a>
    <a class="btn btn-secondary" href="/export-status-details.php?type=html&status=<?= e($status) ?>&date=<?= e($date) ?><?= $branchQS ?>" target="_blank" rel="noopener">Export PDF</a>
    <a class="btn btn-secondary" href="/?<?= e($backQS) ?>">&larr; Back to Dashboard</a>
  </div>
</div>


<div class="card">
  <div class="table-wrap">
<table>
<thead><tr><th>Force/File No</th><th>Name</th><th>Rank</th><th>Gender</th><th>Branch</th><th>Phone</th><th>Status</th><th>Leave Destination</th><th>Leave Start</th><th>Leave End</th><th>Leave Duration (Days)</th><th>Notes</th></tr></thead>
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
                $leaveStart = '';
                $leaveEnd = '';
                $daysOnLeave = '';

                if ($status === 'onleave' || $status === 'leave') {
                    $dStmt = $pdo->prepare("SELECT destination, start_date, end_date FROM leave_requests
                        WHERE employee_id=? AND status='approved'
                          AND start_date <= :d AND end_date >= :d
                        ORDER BY submitted_at DESC LIMIT 1");
                    $dStmt->execute([(int)$r['id'], ':d' => $date]);
                    $rowDest = $dStmt->fetch();
                    $dest = $rowDest['destination'] ?? '';
                    $leaveStart = $rowDest['start_date'] ?? '';
                    $leaveEnd = $rowDest['end_date'] ?? '';

                    if (!empty($leaveStart) && !empty($leaveEnd)) {
                        try {
                            $ds = new DateTimeImmutable($leaveStart);
                            $de = new DateTimeImmutable($leaveEnd);
                            $diff = $ds->diff($de);
                            $daysOnLeave = (string)($diff->days + 1); // inclusive
                        } catch (Throwable $t) {
                            $daysOnLeave = '';
                        }
                    }
                }
              ?>
              <?= e($dest) ?>
            </td>
            <td><?= e($leaveStart) ?></td>
            <td><?= e($leaveEnd) ?></td>
            <td>
              <span><?= $daysOnLeave !== '' ? e($daysOnLeave) : '<span class="muted">—</span>' ?></span>
              <?php
                // Real-time countdown for approved leave (inclusive days, based on current date parameter $date)
                $daysRemaining = '';
                $endTs = null;
                if (($status === 'onleave' || $status === 'leave') && !empty($leaveEnd)) {
                    try {
                        $dNow = new DateTimeImmutable($date);
                        $dEnd = new DateTimeImmutable($leaveEnd);
                        // inclusive remaining days: if end is today => 1 day remaining
                        $diffRem = $dNow->diff($dEnd);
                        $rem = (int)$diffRem->days + 1;
                        if ($dEnd < $dNow) $rem = 0;
                        $daysRemaining = (string)$rem;
                        $endTs = $dEnd->setTime(23,59,59)->getTimestamp();
                    } catch (Throwable $t) {
                        $daysRemaining = '';
                    }
                }
              ?>
              <?php if ($daysRemaining !== ''): ?>
                <div class="muted" style="font-size:12px;margin-top:2px">
                  Remaining: <span class="leave-countdown" data-endts="<?= (int)($endTs ?? 0) ?>"><?= e($daysRemaining) ?></span>
                </div>
              <?php endif; ?>
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
<script>
(function(){
  const els = document.querySelectorAll('.leave-countdown[data-endts]');
  if (!els || !els.length) return;

  function tick(){
    const now = Date.now();
    els.forEach(el => {
      const endTs = parseInt(el.getAttribute('data-endts') || '0', 10);
      if (!endTs) return;
      // Remaining inclusive days based on end-of-day timestamp.
      const msLeft = endTs * 1000 - now;
      const days = Math.ceil(msLeft / 86400000);
      el.textContent = String(Math.max(0, days));
    });
  }

  tick();
  setInterval(tick, 1000);
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
