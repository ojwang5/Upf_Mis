<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/branch.php';

$user = require_login();
$page = 'suspensions';
$page_title = 'Officer Suspensions (Interdiction)';
$pdo = db();

$branchId = user_branch_filter($user);
$branches = [];
if ($user['role'] === 'admin') {
    $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
    if (!empty($_GET['branch'])) {
        $branchId = (int)$_GET['branch'];
    }
}

$where = 's.status = :status';
$params = [':status' => 'active'];
if ($branchId !== null) {
    $where .= ' AND s.branch_id = :b';
    $params[':b'] = $branchId;
}


// Older DBs might not have the officer_suspensions table yet.
$tableExists = function(string $table) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :t LIMIT 1");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
};

if ($tableExists('officer_suspensions')) {
    $today = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        // Mark ended when out of range
        $pdo->prepare("UPDATE officer_suspensions SET status='ended'
            WHERE end_date IS NOT NULL AND end_date < :d")->execute([':d' => $today]);
        $pdo->prepare("UPDATE officer_suspensions SET status='ended'
            WHERE start_date > :d")->execute([':d' => $today]);
        // Mark active within range
        $pdo->prepare("UPDATE officer_suspensions SET status='active'
            WHERE start_date <= :d AND (end_date IS NULL OR end_date >= :d)")->execute([':d' => $today]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$branchId = user_branch_filter($user);
$branches = [];
if ($user['role'] === 'admin') {
    $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
    if (!empty($_GET['branch']) && $_GET['branch'] !== '') {
        $branchId = (int)$_GET['branch'];
    }
}

$statusFilter = $_GET['status'] ?? 'active';
if (!in_array($statusFilter, ['active', 'ended', 'all'], true)) {
    $statusFilter = 'active';
}

$search = trim((string)($_GET['q'] ?? ''));

$where = '1=1';
$params = [];

if ($tableExists('officer_suspensions')) {
    if ($statusFilter !== 'all') {
        $where .= ' AND s.status = :status';
        $params[':status'] = $statusFilter;
    }
    if ($branchId !== null) {
        $where .= ' AND s.branch_id = :b';
        $params[':b'] = $branchId;
    }
    if ($search !== '') {
        $where .= ' AND (e.service_no LIKE :q OR e.full_name LIKE :q OR e.rank LIKE :q OR s.reason LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }
}

$rows = [];
if ($tableExists('officer_suspensions')) {
    $sql = "
        SELECT s.id, s.reason, s.start_date, s.end_date, s.status,
               e.id AS employee_id, e.service_no, e.full_name, e.rank, e.gender,
               b.name AS branch_name
        FROM officer_suspensions s
        JOIN employees e ON e.id = s.employee_id
        JOIN branches b ON b.id = s.branch_id
        WHERE $where
        ORDER BY b.name, e.rank, e.full_name, s.start_date DESC
        ORDER BY b.name, e.rank, e.full_name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

// Dashboard count of active suspensions (for branch scope if not admin)
$activeCount = 0;
if ($tableExists('officer_suspensions')) {
    $countWhere = "status='active'";
    $countParams = [];
    if ($branchId !== null && $user['role'] !== 'admin') {
        $countWhere .= ' AND s.branch_id = :b';
        $countParams[':b'] = $branchId;
    }
    if ($user['role'] === 'admin' && $branchId !== null) {
        // if admin has a branch filter in URL, reflect it
        $countWhere .= ' AND s.branch_id = :b';
        $countParams[':b'] = $branchId;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM officer_suspensions s WHERE $countWhere");
    $stmt->execute($countParams);
    $activeCount = (int)$stmt->fetchColumn();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Officer Suspensions (Interdiction)</h1>
    <div class="desc">Suspension records (auto-status enforced by date range) · Active: <?= (int)$activeCount ?></div>
  </div>

  <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;justify-content:flex-end">
    <form method="get" class="form-row" style="margin:0;gap:10px;display:flex;align-items:flex-end;flex-wrap:wrap">
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

      <div class="form-group" style="min-width:220px">
        <label>Search</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Service no / name / rank / reason">
      </div>

      <div class="form-group" style="min-width:140px">
        <label>Status</label>
        <select name="status" onchange="this.form.submit()">
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="ended" <?= $statusFilter === 'ended' ? 'selected' : '' ?>>Inactive</option>
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
        </select>
      </div>

      <div style="display:flex;gap:10px;align-items:flex-end">
        <button class="btn btn-secondary" type="submit">Apply</button>
        <a class="btn btn-secondary" href="/suspension.php">+ Record</a>
      </div>
    </form>
  </div>
</div>

<?php if (empty($rows)): ?>
  <div class="card"><div class="muted">No suspension records found.</div></div>
    <div class="desc">Currently active suspensions per officer</div>
  </div>
  <form method="get" class="form-row" style="margin:0">
    <?php if ($user['role'] === 'admin'): ?>
      <div class="form-group" style="min-width:160px">
        <label>Regions</label>
        <select name="branch" onchange="this.form.submit()">
          <option value="">All Regions</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ($branchId === (int)$b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($rows)): ?>
  <div class="card"><div class="muted">No active suspension records found.</div></div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Force/ File No</th>
            <th>Name</th>
            <th>Rank</th>
            <th>Branch</th>
            <th>Status</th>
            <th>Reason</th>
            <th>Start</th>
            <th>End</th>
            <th>History</th>
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
              <td><?= status_badge($r['status']) ?></td>
              <td><?= e($r['reason']) ?></td>
              <td><?= e($r['start_date']) ?></td>
              <td><?= e($r['end_date'] ?? '') ?></td>
              <td>
                <a class="btn btn-sm btn-secondary" href="/suspension.php?employee_id=<?= (int)$r['employee_id'] ?>">View</a>
              </td>
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

