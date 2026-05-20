<?php
<<<<<<< HEAD
declare(strict_types=1);

=======
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/branch.php';

$user = require_login();
$page = 'disciplinary';
$page_title = 'Officer Disciplinary';
$pdo = db();

<<<<<<< HEAD
// Older DBs might not have the officer_disciplinary table yet.
$tableExists = function (string $table) use ($pdo): bool {
=======
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
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :t LIMIT 1");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
};

<<<<<<< HEAD
if (!$tableExists('officer_disciplinary')) {
    $page_title = 'Officer Disciplinary';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="card"><div class="muted">Disciplinary module is unavailable (missing officer_disciplinary table).</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// --- Enforce active/closed from date range (similar temporal enforcement to suspensions) ---
function enforce_disciplinary_status(PDO $pdo): void
{
    $today = date('Y-m-d');

    $pdo->beginTransaction();
    try {
        // If end_date < today OR start_date > today => closed
        $pdo->prepare("UPDATE officer_disciplinary SET status='closed'
            WHERE end_date IS NOT NULL AND end_date < :d")->execute([':d' => $today]);

        $pdo->prepare("UPDATE officer_disciplinary SET status='closed'
            WHERE start_date > :d")->execute([':d' => $today]);

        // If within range => active
        $pdo->prepare("UPDATE officer_disciplinary SET status='active'
            WHERE start_date <= :d AND (end_date IS NULL OR end_date >= :d)")->execute([':d' => $today]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

enforce_disciplinary_status($pdo);

// --- UI helpers ---
function status_badge_disciplinary(string $status): string
{
    $map = [
        'active' => ['Active', 'badge-sick'],
        'closed' => ['Closed', 'badge-awol'],
    ];
    [$lab, $cls] = $map[$status] ?? [$status, 'badge-admin'];
    return '<span class="badge ' . htmlspecialchars($cls, ENT_QUOTES) . '">' . htmlspecialchars($lab, ENT_QUOTES) . '</span>';
}

// Permission helper (branch-scoped editing for non-admin)
function can_edit_disciplinary(array $u, array $row): bool
{
    if (($u['role'] ?? null) === 'admin') return true;
    $uBranch = isset($u['branch_id']) ? (int)$u['branch_id'] : null;
    $rBranch = isset($row['branch_id']) ? (int)$row['branch_id'] : null;
    return $uBranch !== null && $rBranch !== null && $uBranch === $rBranch;
}

function recompute_disciplinary_status(string $startDate, ?string $endDate, string $today): string
{
    if ($startDate > $today) return 'closed';
    if ($endDate !== null && $endDate !== '' && $endDate < $today) return 'closed';
    return 'active';
}

// --- Branch scoping ---
$branchId = user_branch_filter($user);
$branches = [];
if (($user['role'] ?? null) === 'admin') {
    $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
    if (!empty($_GET['branch'] ?? '') || ($_GET['branch'] ?? '') === '0') {
        $branchId = (int)($_GET['branch'] ?? 0);
    }
}

// --- POST: create/edit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }

    $today = date('Y-m-d');

    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, service_no, full_name, rank, branch_id FROM employees WHERE id = ? AND active=1");
    $stmt->execute([$employeeId]);
    $emp = $stmt->fetch();
    if (!$emp) {
        flash('msg', 'Invalid employee.');
        header('Location: /disciplinary.php');
        exit;
    }

    $effectiveBranchId = (int)$emp['branch_id'];
    if (($user['role'] ?? null) === 'admin') {
        $effectiveBranchId = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : $effectiveBranchId;
    }

    $reason = trim((string)($_POST['reason'] ?? ''));
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : null;
    if ($endDate === '') $endDate = null;

    if ($reason === '' || $startDate === '') {
        flash('msg', 'Reason and Start date are required.');
        header('Location: /disciplinary.php');
        exit;
    }

    if (($user['role'] ?? null) !== 'admin' && (int)$user['branch_id'] !== (int)$effectiveBranchId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $status = recompute_disciplinary_status($startDate, $endDate, $today);

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM officer_disciplinary WHERE id = ?");
        $s->execute([$id]);
        $disc = $s->fetch();
        if (!$disc) {
            flash('msg', 'Disciplinary record not found.');
            header('Location: /disciplinary.php');
            exit;
        }
        if (!can_edit_disciplinary($user, $disc)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $pdo->prepare("UPDATE officer_disciplinary
            SET employee_id = ?, branch_id = ?, reason = ?, start_date = ?, end_date = ?, status = ?
            WHERE id = ?")
            ->execute([
                $employeeId,
                $effectiveBranchId,
                $reason,
                $startDate,
                $endDate,
                $status,
                $id
            ]);

        if (function_exists('audit_log')) {
            require_once __DIR__ . '/../includes/audit.php';
            audit_log($user, 'disciplinary.edit', 'officer_disciplinary', (string)$id, [
                'employee_id' => $employeeId,
                'branch_id' => (int)$effectiveBranchId,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        }

        flash('msg', 'Disciplinary record updated.');
        header('Location: /disciplinary.php?id=' . (int)$id);
        exit;
    }

    // Create
    $pdo->prepare("INSERT INTO officer_disciplinary (employee_id, branch_id, reason, start_date, end_date, status, created_at, created_by)
        VALUES (?,?,?,?,?,?,?,?)")
        ->execute([
            $employeeId,
            $effectiveBranchId,
            $reason,
            $startDate,
            $endDate,
            $status,
            date('c'),
            (int)$user['id'],
        ]);

    $newId = (int)$pdo->lastInsertId();

    if (function_exists('audit_log')) {
        require_once __DIR__ . '/../includes/audit.php';
        audit_log($user, 'disciplinary.create', 'officer_disciplinary', (string)$newId, [
            'employee_id' => $employeeId,
            'branch_id' => (int)$effectiveBranchId,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    flash('msg', 'Disciplinary record recorded.');
    header('Location: /disciplinary.php?id=' . $newId);
    exit;
}

// --- GET: editing and history ---
$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editing = false;
$edit = null;
if ($editId) {
    $st = $pdo->prepare("SELECT * FROM officer_disciplinary WHERE id = ?");
    $st->execute([$editId]);
    $edit = $st->fetch();
    if ($edit) {
        if (!can_edit_disciplinary($user, $edit)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        $editing = true;
    }
}

$historyEmployeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$historyRows = [];
$historyTitle = null;

if ($historyEmployeeId) {
    $st = $pdo->prepare("SELECT d.*, e.service_no, e.full_name, e.rank, b.name AS branch_name,
        u.full_name AS recorded_by_name
        FROM officer_disciplinary d
        JOIN employees e ON e.id = d.employee_id
        JOIN branches b ON b.id = d.branch_id
        LEFT JOIN users u ON u.id = d.created_by
        WHERE d.employee_id = ?
        ORDER BY d.start_date DESC, d.created_at DESC
    ");
    $st->execute([$historyEmployeeId]);
    $historyRows = $st->fetchAll();

    if (!empty($historyRows)) {
        $historyTitle = 'Disciplinary history — ' . ($historyRows[0]['full_name'] ?? ('Officer #' . $historyEmployeeId));

        if (($user['role'] ?? null) !== 'admin' && (int)$historyRows[0]['branch_id'] !== (int)$user['branch_id']) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}

// When editing, prefill selected values; also treat edit as implicit officer
$selectedEmployeeId = $editing ? (int)$edit['employee_id'] : ($historyEmployeeId ? (int)$historyEmployeeId : null);
$selectedBranchId = $editing ? (int)$edit['branch_id'] : user_branch_filter($user);
$selectedReason = $editing ? (string)$edit['reason'] : '';
$selectedStart = $editing ? (string)$edit['start_date'] : '';
$selectedEnd = $editing ? ($edit['end_date'] ?? '') : '';

// --- Dropdown data for create/edit ---
$search = trim((string)($_GET['q'] ?? ''));

$branchIdForEmployee = user_branch_filter($user);
$empWhere = 'e.active = 1';
$params = [];
if ($branchIdForEmployee !== null) {
    $empWhere .= ' AND e.branch_id = :b';
    $params[':b'] = $branchIdForEmployee;
}
if ($search !== '') {
    $empWhere .= ' AND (e.service_no LIKE :q OR e.full_name LIKE :q OR e.rank LIKE :q OR e.id LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$sqlEmp = "SELECT e.id, e.service_no, e.full_name, e.rank, b.name AS branch_name
    FROM employees e
    JOIN branches b ON b.id = e.branch_id
    WHERE $empWhere
    ORDER BY b.name, e.rank, e.full_name
    LIMIT 60";
$stmtEmp = $pdo->prepare($sqlEmp);
$stmtEmp->execute($params);
$empRows = $stmtEmp->fetchAll();

// --- List filters (for dashboard-like table) ---
$statusFilter = $_GET['status'] ?? 'active';
if (!in_array($statusFilter, ['active', 'closed', 'all'], true)) {
    $statusFilter = 'active';
}

$where = '1=1';
$listParams = [];
if ($statusFilter !== 'all') {
    $where .= ' AND d.status = :s';
    $listParams[':s'] = $statusFilter;
}
if ($branchId !== null) {
    $where .= ' AND d.branch_id = :b';
    $listParams[':b'] = $branchId;
}
if ($search !== '') {
    $where .= ' AND (e.service_no LIKE :q OR e.full_name LIKE :q OR e.rank LIKE :q OR d.reason LIKE :q)';
    $listParams[':q'] = '%' . $search . '%';
}

$rows = [];
$sql = "
    SELECT d.id, d.reason, d.start_date, d.end_date, d.status,
           e.id AS employee_id, e.service_no, e.full_name, e.rank,
           b.name AS branch_name
    FROM officer_disciplinary d
    JOIN employees e ON e.id = d.employee_id
    JOIN branches b ON b.id = d.branch_id
    WHERE $where
    ORDER BY b.name, e.rank, e.full_name, d.start_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($listParams);
$rows = $stmt->fetchAll();

// Dashboard active count
$activeCount = 0;
$countWhere = "d.status='active'";
$countParams = [];
if (($branchId !== null) && $user['role'] !== 'admin') {
    $countWhere .= ' AND d.branch_id = :b';
    $countParams[':b'] = $branchId;
}
if (($user['role'] ?? null) === 'admin' && $branchId !== null) {
    $countWhere .= ' AND d.branch_id = :b';
    $countParams[':b'] = $branchId;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM officer_disciplinary d WHERE $countWhere");
$stmt->execute($countParams);
$activeCount = (int)$stmt->fetchColumn();
=======
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

>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Officer Disciplinary</h1>
<<<<<<< HEAD
    <div class="desc">Auto-enforced active/closed actions · Active: <?= (int)$activeCount ?></div>
  </div>

  <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;justify-content:flex-end">
    <form method="get" class="form-row" style="margin:0;gap:10px;display:flex;align-items:flex-end;flex-wrap:wrap">
      <?php if (($user['role'] ?? null) === 'admin'): ?>
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

      <div class="form-group" style="min-width:240px">
        <label>Search</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Service no / name / rank / reason">
      </div>

      <div class="form-group" style="min-width:140px">
        <label>Status</label>
        <select name="status" onchange="this.form.submit()">
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
        </select>
      </div>

      <div style="display:flex;gap:10px;align-items:flex-end">
        <button class="btn btn-secondary" type="submit">Apply</button>
        <a class="btn btn-secondary" href="/disciplinary.php">+ Record</a>
      </div>
    </form>
  </div>
</div>

<?php if (flash('msg')): ?>
  <div class="alert alert-success"><?= e(flash('msg')) ?></div>
<?php endif; ?>

<?php if (!empty($historyRows) && $historyTitle): ?>
  <div class="card" style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start">
      <div>
        <h3 style="margin:0 0 6px 0"><?= e($historyTitle) ?></h3>
        <div class="muted">All recorded disciplinary actions for this officer</div>
      </div>
      <div>
        <a class="btn btn-secondary" href="/disciplinary.php">Close</a>
      </div>
    </div>
    <div class="table-wrap" style="margin-top:10px">
      <table>
        <thead>
          <tr>
            <th>Branch</th>
            <th>Status</th>
            <th>Reason</th>
            <th>Start</th>
            <th>End</th>
            <th>Recorded by</th>
            <th>Recorded at</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historyRows as $r): ?>
            <tr>
              <td><?= e($r['branch_name'] ?? '') ?></td>
              <td><?= status_badge_disciplinary((string)$r['status']) ?></td>
              <td><?= e($r['reason']) ?></td>
              <td><?= e($r['start_date']) ?></td>
              <td><?= e($r['end_date'] ?? '') ?></td>
              <td><?= e($r['recorded_by_name'] ?? '—') ?></td>
              <td><?= e($r['created_at'] ?? '') ?></td>
              <td>
                <a class="btn btn-sm btn-secondary" href="/disciplinary.php?id=<?= (int)$r['id'] ?>">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$historyRows): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px">No disciplinary history found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:10px;flex-wrap:wrap">
    <div>
      <h3 style="margin:0 0 6px 0"><?= $editing ? 'Edit Disciplinary Record' : 'Record Officer Disciplinary Action' ?></h3>
      <div class="muted">Reason + duration · status is computed from dates</div>
    </div>
    <?php if ($selectedEmployeeId): ?>
      <a class="btn btn-secondary" href="/disciplinary.php?employee_id=<?= (int)$selectedEmployeeId ?>">View history</a>
    <?php endif; ?>
  </div>

  <form method="post" style="display:grid;grid-template-columns:repeat(12,1fr);gap:12px;margin-top:12px" onsubmit="return true">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'create' ?>">
    <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
    <?php endif; ?>

    <div style="grid-column: span 6">
      <label>Officer</label>
      <input type="text" name="employee_search" value="<?= e($search) ?>" placeholder="Search service no / name" disabled>
      <select name="employee_id" required>
        <option value="">— Select Officer —</option>
        <?php foreach ($empRows as $eRow): ?>
          <option value="<?= (int)$eRow['id'] ?>" <?= ($selectedEmployeeId !== null && (int)$eRow['id'] === (int)$selectedEmployeeId) ? 'selected' : '' ?>>
            <?= e($eRow['service_no']) ?> — <?= e($eRow['full_name']) ?> (<?= e($eRow['rank']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <div class="muted" style="font-size:12px;margin-top:6px">Search via <code>?q=</code> in URL.</div>
    </div>

    <?php if (($user['role'] ?? null) === 'admin'): ?>
      <div style="grid-column: span 3">
        <label>Branch</label>
        <select name="branch_id" required>
          <?php
          $allBranches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
          foreach ($allBranches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ($selectedBranchId !== null && (int)$b['id'] === (int)$selectedBranchId) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
=======
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
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
<<<<<<< HEAD

    <div style="grid-column: span 12">
      <label>Reason for disciplinary action</label>
      <input type="text" name="reason" value="<?= e($selectedReason) ?>" required>
    </div>

    <div style="grid-column: span 3">
      <label>Start date (effective)</label>
      <input type="date" name="start_date" value="<?= e($selectedStart) ?>" required>
    </div>

    <div style="grid-column: span 3">
      <label>End date (optional)</label>
      <input type="date" name="end_date" value="<?= e($selectedEnd) ?>">
    </div>

    <div style="grid-column: span 12; display:flex; gap:10px; align-items:center; flex-wrap:wrap">
      <button class="btn" type="submit"><?= $editing ? 'Save Changes' : 'Record Disciplinary Action' ?></button>
    </div>
=======
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
  </form>
</div>

<?php if (empty($rows)): ?>
<<<<<<< HEAD
  <div class="card"><div class="muted">No disciplinary records found for the selected filters.</div></div>
=======
  <div class="card"><div class="muted">No active disciplinary records found.</div></div>
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
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
<<<<<<< HEAD
            <th>Status</th>
            <th>Reason</th>
            <th>Start</th>
            <th>End</th>
            <th>History</th>
=======
            <th>Reason</th>
            <th>Start</th>
            <th>End</th>
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['service_no']) ?></td>
              <td><?= e($r['full_name']) ?></td>
              <td><?= e($r['rank']) ?></td>
              <td><?= e($r['branch_name']) ?></td>
<<<<<<< HEAD
              <td><?= status_badge_disciplinary((string)$r['status']) ?></td>
              <td><?= e($r['reason']) ?></td>
              <td><?= e($r['start_date']) ?></td>
              <td><?= e($r['end_date'] ?? '') ?></td>
              <td>
                <a class="btn btn-sm btn-secondary" href="/disciplinary.php?employee_id=<?= (int)$r['employee_id'] ?>">View</a>
              </td>
=======
              <td><?= e($r['reason']) ?></td>
              <td><?= e($r['start_date']) ?></td>
              <td><?= e($r['end_date'] ?? '') ?></td>
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<<<<<<< HEAD

=======
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d
