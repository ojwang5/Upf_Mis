<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/branch.php';

$user = require_login();
$page = 'suspension';
$pdo = db();

// Older DBs might not have the officer_suspensions table yet.
$tableExists = function(string $table) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :t LIMIT 1");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
};

if (!$tableExists('officer_suspensions')) {
    $page_title = 'Officer Suspension';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="card"><div class="muted">Suspension module is unavailable (missing officer_suspensions table).</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

function enforce_suspension_status(PDO $pdo): void {
    $today = date('Y-m-d');

    // Ensure all rows are consistent with their date range.
    // - If end_date < today OR start_date > today => ended
    // - If start_date <= today AND (end_date IS NULL OR end_date >= today) => active
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE officer_suspensions SET status='ended'
            WHERE end_date IS NOT NULL AND end_date < :d")->execute([':d' => $today]);

        $pdo->prepare("UPDATE officer_suspensions SET status='ended'
            WHERE start_date > :d")->execute([':d' => $today]);

        $pdo->prepare("UPDATE officer_suspensions SET status='active'
            WHERE start_date <= :d AND (end_date IS NULL OR end_date >= :d)")->execute([':d' => $today]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

enforce_suspension_status($pdo);

// Helper: permission check for a suspension row
function can_edit_suspension(array $u, array $susp): bool {
    if ($u['role'] === 'admin') return true;
    // managers/officers can only edit within their own branch
    return isset($u['branch_id']) && (int)$u['branch_id'] === (int)($susp['branch_id'] ?? 0);
}

function recompute_status_from_dates(string $startDate, ?string $endDate, string $today): string {
    if ($startDate > $today) return 'ended';
    if ($endDate !== null && $endDate !== '' && $endDate < $today) return 'ended';
    return 'active';
}

// (audit_log calls are included in handlers when available)

$page_title = 'Officer Suspension';


// ---------- History view ----------
$historyEmployeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$historySuspensionId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ---------- POST handlers (create/edit) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    // CSRF
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }

    $today = date('Y-m-d');

    // Lookup employee
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, service_no, full_name, rank, branch_id FROM employees WHERE id = ? AND active=1");
    $stmt->execute([$employeeId]);
    $emp = $stmt->fetch();
    if (!$emp) {
        flash('msg', 'Invalid employee.');
        header('Location: /suspension.php');
        exit;
    }

    $branchId = $emp['branch_id'];
    if ($user['role'] === 'admin') {
        $branchId = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : (int)$branchId;
    }

    $reason = trim((string)($_POST['reason'] ?? ''));
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : null;
    if ($endDate === '') $endDate = null;

    if ($reason === '' || $startDate === '') {
        flash('msg', 'Reason and Start date are required.');
        header('Location: /suspension.php');
        exit;
    }

    // Permission check based on effective branch.
    if ($user['role'] !== 'admin' && (int)$user['branch_id'] !== (int)$branchId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $status = recompute_status_from_dates($startDate, $endDate, $today);

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM officer_suspensions WHERE id = ?");
        $s->execute([$id]);
        $susp = $s->fetch();
        if (!$susp) {
            flash('msg', 'Suspension record not found.');
            header('Location: /suspensions.php');
            exit;
        }
        if (!can_edit_suspension($user, $susp)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $pdo->prepare("UPDATE officer_suspensions
            SET employee_id = ?, branch_id = ?, reason = ?, start_date = ?, end_date = ?, status = ?
            WHERE id = ?")
            ->execute([
                $employeeId,
                $branchId,
                $reason,
                $startDate,
                $endDate,
                $status,
                $id
            ]);

        // audit
        if (function_exists('audit_log')) {
            require_once __DIR__ . '/../includes/audit.php';
            audit_log($user, 'suspension.edit', 'officer_suspension', (string)$id, [
                'employee_id' => $employeeId,
                'branch_id' => (int)$branchId,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        }

        flash('msg', 'Suspension updated.');
        header('Location: /suspension.php?id=' . (int)$id);
        exit;
    }

    // Create
    $pdo->prepare("INSERT INTO officer_suspensions (employee_id, branch_id, reason, start_date, end_date, status, created_at, created_by)
        VALUES (?,?,?,?,?,?,?,?)")
        ->execute([
            $employeeId,
            $branchId,
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
        audit_log($user, 'suspension.create', 'officer_suspension', (string)$newId, [
            'employee_id' => $employeeId,
            'branch_id' => (int)$branchId,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    flash('msg', 'Suspension recorded.');
    header('Location: /suspension.php?id=' . $newId);
    exit;
}

// ---------- Resolve edit record ----------
$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editing = false;
$edit = null;
if ($editId) {
    $st = $pdo->prepare("SELECT * FROM officer_suspensions WHERE id = ?");
    $st->execute([$editId]);
    $edit = $st->fetch();
    if ($edit) {
        if (!can_edit_suspension($user, $edit)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        $editing = true;
    }
}

// ---------- Dropdown data ----------
$branchIdForEmployee = user_branch_filter($user);
$search = trim((string)($_GET['q'] ?? ''));

$empRows = [];
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

// ---------- History listing ----------
$historyRows = [];
$historyTitle = null;
if ($historyEmployeeId) {
    $st = $pdo->prepare("SELECT s.*, e.service_no, e.full_name, e.rank, b.name AS branch_name,
        u.full_name AS recorded_by_name
        FROM officer_suspensions s
        JOIN employees e ON e.id = s.employee_id
        JOIN branches b ON b.id = s.branch_id
        LEFT JOIN users u ON u.id = s.created_by
        WHERE s.employee_id = ?
        ORDER BY s.start_date DESC, s.created_at DESC
    ");
    $st->execute([$historyEmployeeId]);
    $historyRows = $st->fetchAll();
    $historyTitle = 'Suspension history — ' . ($historyRows[0]['full_name'] ?? ('Employee #' . $historyEmployeeId));

    if ($user['role'] !== 'admin' && !empty($historyRows) && (int)$historyRows[0]['branch_id'] !== (int)$user['branch_id']) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
} elseif ($historySuspensionId) {
    $st = $pdo->prepare("SELECT s.*, e.service_no, e.full_name, e.rank, b.name AS branch_name,
        u.full_name AS recorded_by_name
        FROM officer_suspensions s
        JOIN employees e ON e.id = s.employee_id
        JOIN branches b ON b.id = s.branch_id
        LEFT JOIN users u ON u.id = s.created_by
        WHERE s.id = ?");
    $st->execute([$historySuspensionId]);
    $historyRow = $st->fetch();
    if (!$historyRow) {
        flash('msg', 'Suspension record not found.');
        header('Location: /suspensions.php');
        exit;
    }
    if ($user['role'] !== 'admin' && (int)$historyRow['branch_id'] !== (int)$user['branch_id']) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $employeeId = (int)$historyRow['employee_id'];
    $st2 = $pdo->prepare("SELECT s.*, e.service_no, e.full_name, e.rank, b.name AS branch_name,
        u.full_name AS recorded_by_name
        FROM officer_suspensions s
        JOIN employees e ON e.id = s.employee_id
        JOIN branches b ON b.id = s.branch_id
        LEFT JOIN users u ON u.id = s.created_by
        WHERE s.employee_id = ?
        ORDER BY s.start_date DESC, s.created_at DESC
    ");
    $st2->execute([$employeeId]);
    $historyRows = $st2->fetchAll();
    $historyTitle = 'Suspension history — ' . ($historyRow['full_name'] ?? ('Employee #' . $employeeId));
}

// If neither editing nor history, show create form.
$selectedEmployeeId = $editing ? (int)$edit['employee_id'] : null;
$selectedBranchId = $editing ? (int)$edit['branch_id'] : (user_branch_filter($user) ?? null);
$selectedReason = $editing ? (string)$edit['reason'] : '';
$selectedStart = $editing ? (string)$edit['start_date'] : '';
$selectedEnd = $editing ? ($edit['end_date'] ?? '') : '';

include __DIR__ . '/../includes/header.php';

?>

<div class="page-header">
  <div>
    <h1><?= $editing ? 'Edit Suspension' : 'Record Officer Suspension' ?></h1>
    <div class="desc">Interdiction: suspension reason and duration</div>
  </div>
  <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;justify-content:flex-end">
    <a class="btn btn-secondary" href="/suspensions.php">&larr; Back to Suspensions</a>
  </div>
</div>

<?php if ($m = flash('msg')): ?>
  <div class="alert alert-success"><?= e($m) ?></div>
<?php endif; ?>

<?php if (!empty($historyRows) && $historyTitle): ?>
  <div class="card" style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start">
      <div>
        <h3 style="margin:0 0 6px 0"><?= e($historyTitle) ?></h3>
        <div class="muted">Showing all recorded suspensions for this officer</div>
      </div>
      <div>
        <?php $hidEmployee = (int)($historyRows[0]['employee_id'] ?? 0); ?>
        <a class="btn btn-secondary" href="/suspension.php?employee_id=<?= $hidEmployee ?>">View history</a>
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
              <td><?= status_badge($r['status']) ?></td>
              <td><?= e($r['reason']) ?></td>
              <td><?= e($r['start_date']) ?></td>
              <td><?= e($r['end_date'] ?? '') ?></td>
              <td><?= e($r['recorded_by_name'] ?? '—') ?></td>
              <td><?= e($r['created_at'] ?? '') ?></td>
              <td>
                <a class="btn btn-sm btn-secondary" href="/suspension.php?id=<?= (int)$r['id'] ?>">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <form method="post" style="display:grid;grid-template-columns:repeat(12,1fr);gap:12px" onsubmit="return true">
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
      <div class="muted" style="font-size:12px;margin-top:6px">Search via <code>?q=</code> in URL (e.g. <code>/suspension.php?q=UPF</code>).</div>
    </div>

    <?php if ($user['role'] === 'admin'): ?>
      <div style="grid-column: span 3">
        <label>Branch</label>
        <select name="branch_id" required>
          <?php
          $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
          foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ($selectedBranchId !== null && (int)$b['id'] === (int)$selectedBranchId) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div style="grid-column: span 12">
      <label>Reason for suspension</label>
      <input type="text" name="reason" value="<?= e($selectedReason) ?>" required>
    </div>

    <div style="grid-column: span 3">
      <label>Start date</label>
      <input type="date" name="start_date" value="<?= e($selectedStart) ?>" required>
    </div>

    <div style="grid-column: span 3">
      <label>End date (optional)</label>
      <input type="date" name="end_date" value="<?= e($selectedEnd) ?>">
    </div>

    <div style="grid-column: span 12; display:flex; gap:10px; align-items:center; flex-wrap:wrap">
      <button class="btn" type="submit"><?= $editing ? 'Save Changes' : 'Record Suspension' ?></button>
      <?php if ($editing): ?>
        <?php $eid = (int)$edit['employee_id']; ?>
        <a class="btn btn-secondary" href="/suspension.php?employee_id=<?= $eid ?>">View History</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
// Compatibility endpoint: /suspension.php -> /suspensions.php
require_once __DIR__ . '/suspensions.php';

