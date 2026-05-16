<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/audit.php';
$user = require_role(['admin','manager','officer']);
$page = 'employees';
$page_title = 'Employees';
$pdo = db();

// Field officers can view personnel but must not edit/update/delete.
$can_modify_personnel = in_array($user['role'], ['admin','manager'], true);

function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


function normalize_gender(string $g): ?string {
    $g = strtoupper(trim($g));
    if ($g === 'M' || $g === 'MALE') return 'M';
    if ($g === 'F' || $g === 'FEMALE') return 'F';
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk_import') {
        if (!$can_modify_personnel) { http_response_code(403); exit('Forbidden'); }
        if (!verify_csrf($_POST['_csrf'] ?? null)) { http_response_code(403); exit('Invalid CSRF token'); }

        $mode = trim((string)($_POST['dup_mode'] ?? 'skip_duplicates'));
        $allowedModes = ['skip_duplicates','upsert','fail_on_duplicate'];
        if (!in_array($mode, $allowedModes, true)) $mode = 'skip_duplicates';

        if (empty($_FILES['csv_file']['tmp_name'])) {
            flash('err', 'Please select a CSV file to upload.');
            header('Location: /employees.php'); exit;
        }

        $tmpPath = (string)$_FILES['csv_file']['tmp_name'];
        $maxBytes = 5 * 1024 * 1024; // 5MB safety (adjust if needed)
        if (isset($_FILES['csv_file']['size']) && (int)$_FILES['csv_file']['size'] > $maxBytes) {
            flash('err', 'CSV file too large (max 5MB).');
            header('Location: /employees.php'); exit;
        }

        $handle = fopen($tmpPath, 'rb');
        if (!$handle) {
            flash('err', 'Could not read uploaded file.');
            header('Location: /employees.php'); exit;
        }

        // Expect header row; map columns by header name.
        $header = fgetcsv($handle);
        if (!$header || !is_array($header)) {
            fclose($handle);
            flash('err', 'CSV header row is missing or invalid.');
            header('Location: /employees.php'); exit;
        }
        $headerMap = [];
        foreach ($header as $idx => $col) {
            $col = strtolower(trim((string)$col));
            if ($col !== '') $headerMap[$col] = (int)$idx;
        }

        $required = ['service_no','full_name','gender','rank'];
        foreach ($required as $req) {
            if (!isset($headerMap[$req])) {
                fclose($handle);
                flash('err', 'CSV must contain column: ' . $req);
                header('Location: /employees.php'); exit;
            }
        }

        $branchCol = null;
        foreach (['branch_name','branch_code','branch_id'] as $bc) {
            if (isset($headerMap[$bc])) { $branchCol = $bc; break; }
        }

        $allowedUpsertBy = 'service_no';

        $total = 0; $inserted = 0; $updated = 0; $skipped = 0;
        $failures = [];
        $failureCap = 50;

        // Preload branch lookup by code/name for admin imports.
        $branchLookup = [];
        if ($user['role'] === 'admin') {
            $rows = $pdo->query('SELECT id, code, name FROM branches')->fetchAll();
            foreach ($rows as $r) {
                $branchLookup[(string)$r['id']] = (int)$r['id'];
                $branchLookup[strtoupper((string)$r['code'])] = (int)$r['id'];
                $branchLookup[strtolower((string)$r['name'])] = (int)$r['id'];
            }
        }

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $total++;
                if ($total === 1 && empty(array_filter($row, fn($v)=>trim((string)$v)!==''))) continue; // ignore empty lines

                $get = function(string $key) use ($row, $headerMap): string {
                    if (!isset($headerMap[$key])) return '';
                    $val = $row[$headerMap[$key]] ?? '';
                    return is_string($val) ? $val : (string)$val;
                };

                $service_no = trim($get('service_no'));
                $full_name  = trim($get('full_name'));
                $genderIn   = $get('gender');
                $rank        = trim($get('rank'));
                $phone       = trim($get('phone'));
                $email       = trim($get('email'));

                if ($service_no === '' || $full_name === '' || $rank === '') {
                    $failures[] = 'Line ' . ($total+1) . ': missing required fields.';
                    if (count($failures) >= $failureCap) break;
                    continue;
                }

                $gender = normalize_gender($genderIn);
                if ($gender === null) {
                    $failures[] = 'Line ' . ($total+1) . ': invalid gender (' . $genderIn . ').';
                    if (count($failures) >= $failureCap) break;
                    continue;
                }

                if ($email === '' || !is_valid_email($email)) {
                    $failures[] = 'Line ' . ($total+1) . ': invalid email.';
                    if (count($failures) >= $failureCap) break;
                    continue;
                }

                // Branch resolution
                if ($user['role'] !== 'admin') {
                    $branch_id = (int)$user['branch_id'];
                } else {
                    $branch_id = null;
                    if ($branchCol !== null) {
                        $raw = trim($get($branchCol));
                        if ($raw !== '') {
                            $key = $branchCol === 'branch_code' ? strtoupper($raw) : ($branchCol === 'branch_id' ? $raw : strtolower($raw));
                            $branch_id = $branchLookup[$key] ?? null;
                        }
                    }
                    if ($branch_id === null) {
                        $failures[] = 'Line ' . ($total+1) . ': branch not found/invalid.';
                        if (count($failures) >= $failureCap) break;
                        continue;
                    }
                }

                // Handle duplicates based on service_no (UNIQUE)
                $existingIdStmt = $pdo->prepare('SELECT id FROM employees WHERE service_no = ?');
                $existingIdStmt->execute([$service_no]);
                $existingId = $existingIdStmt->fetchColumn();

                if ($existingId !== false && $existingId !== null) {
                    if ($mode === 'skip_duplicates') {
                        $skipped++;
                        continue;
                    }
                    if ($mode === 'fail_on_duplicate') {
                        $failures[] = 'Line ' . ($total+1) . ': duplicate service_no ' . $service_no . '.';
                        if (count($failures) >= $failureCap) break;
                        continue;
                    }
                    // upsert
                    $stmt = $pdo->prepare("UPDATE employees SET full_name=?, gender=?, rank=?, branch_id=?, phone=?, email=? WHERE service_no=?");
                    $stmt->execute([$full_name, $gender, $rank, $branch_id, $phone, $email, $service_no]);
                    $updated++;
                    continue;
                }

                $ins = $pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id, phone, email) VALUES (?,?,?,?,?,?,?)");
                $ins->execute([$service_no, $full_name, $gender, $rank, $branch_id, $phone, $email]);
                $inserted++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            fclose($handle);
            flash('err', 'Bulk import failed: ' . $e->getMessage());
            header('Location: /employees.php'); exit;
        }

        fclose($handle);

        audit_log(
            $user,
            'bulk_import_personnel',
            'employees',
            null,
            [
                'mode' => $mode,
                'total_rows' => $total,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
            ]
        );

        $msg = 'Bulk import complete. Total: ' . $total . ', Added: ' . $inserted . ', Updated: ' . $updated . ', Skipped: ' . $skipped . '.';
        flash('msg', $msg);
        if (!empty($failures)) {
            // keep failure list short
            flash('err', 'Some rows failed to import (showing first ' . min(count($failures), $failureCap) . '): ' . implode(' | ', array_slice($failures, 0, $failureCap)));
        }

        header('Location: /employees.php'); exit;
    }

    if ($action === 'create' || $action === 'update') {
        if (!$can_modify_personnel) { http_response_code(403); exit('Forbidden'); }

        $service_no = trim($_POST['service_no'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $gender = $_POST['gender'] ?? 'M';
        $rank = trim($_POST['rank'] ?? '');
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !is_valid_email($email)) {
            flash('err', 'Please provide a valid email.');
            header('Location: /employees.php');
            exit;
        }

        if (in_array($user['role'], ['manager','officer'], true)) $branch_id = (int)$user['branch_id'];
        if (!can_access_branch($user, $branch_id)) { http_response_code(403); exit('Forbidden'); }

        if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO employees (service_no, full_name, gender, rank, branch_id, phone, email) VALUES (?,?,?,?,?,?,?)");
            try {
                $stmt->execute([$service_no, $full_name, $gender, $rank, $branch_id, $phone, $email]);
                flash('msg', 'Employee added.');
            } catch (PDOException $e) {
                flash('err', 'Could not add employee: ' . $e->getMessage());
            }
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE employees SET service_no=?, full_name=?, gender=?, rank=?, branch_id=?, phone=?, email=? WHERE id=?");
            $stmt->execute([$service_no, $full_name, $gender, $rank, $branch_id, $phone, $email, $id]);
            flash('msg', 'Employee updated.');
        }
    } elseif ($action === 'delete') {
        if (!$can_modify_personnel) { http_response_code(403); exit('Forbidden'); }

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
if (in_array($user['role'], ['manager','officer'], true)) { $where .= ' AND e.branch_id = ?'; $params[] = $user['branch_id']; }
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
  <div><h1>Personnel</h1><div class="desc"><?= is_admin($user) ? 'Manage personnel across all branches' : 'Manage personnel of ' . e($user['branch_name']) ?></div></div>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <h3><?= $editing ? 'Edit Employee' : 'Add Employee' ?></h3>

  <?php if (!$can_modify_personnel): ?>
    <div class="muted" style="padding:12px 0">Field officers cannot create or edit personnel.</div>
  <?php endif; ?>

  <div style="margin-top:12px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <button id="toggle-personnel-form" class="btn btn-secondary" type="button">Add Personnel</button>
      <div class="muted" style="font-size:12px;">Collapse/expand the employee fields</div>
    </div>

    <?php
      // Keep open when editing; collapsed by default when adding.
      $formDisplay = $editing ? 'block' : 'none';
    ?>

    <form method="post" <?= $can_modify_personnel ? '' : 'style="display:none"' ?> style="margin-top:12px;display:<?= $formDisplay ?>" id="personnel-form">
      <input type="hidden" name="action" value="<?= $editing?'update':'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>

      <div class="form-row">
        <div class="form-group"><label>Force/File No</label><input type="text" name="service_no" required value="<?= e($editing['service_no'] ?? '') ?>"></div>
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
        <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= e($editing['email'] ?? '') ?>"></div>
        <div class="form-group" style="flex:0">
          <label>&nbsp;</label>
          <button class="btn" type="submit"><?= $editing?'Update':'Add' ?></button>
          <?php if ($editing && $can_modify_personnel): ?> <a class="btn btn-secondary" href="/employees.php">Cancel</a><?php endif; ?>
        </div>
      </div>
    </form>

    <script>
      (function(){
        const btn = document.getElementById('toggle-personnel-form');
        const form = document.getElementById('personnel-form');
        if (!btn || !form) return;

        function syncLabel(){
          const isOpen = form.style.display !== 'none';
          btn.textContent = isOpen ? 'Hide Personnel' : 'Add Personnel';
        }

        btn.addEventListener('click', function(){
          form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none';
          syncLabel();
        });

        // Initial label
        syncLabel();
      })();
    </script>
  </div>
</div>
<div class="card">
<?php if ($can_modify_personnel): ?>
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;justify-content:space-between;padding:12px 0">

      <div class="card" style="padding:12px;flex:1;min-width:320px">
        <h3 style="margin:0 0 8px 0">Bulk Upload Personnel (CSV)</h3>
        <div class="muted" style="font-size:12px;margin-bottom:10px">Duplicate handling is based on <b>Force/File No (service_no)</b>.</div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
          <form method="post" action="/employees.php" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_import">

            <div class="form-group" style="margin:0">
              <label>CSV file</label>
              <input type="file" name="csv_file" accept=".csv,text/csv" required>
              <div class="muted" style="font-size:12px;margin-top:6px">
                <a class="btn btn-secondary" href="/templates/personnel_import_template.csv" download>Download template</a>
              </div>
            </div>

            <div class="form-group" style="margin:0">
              <label>Duplicate mode</label>
              <select name="dup_mode">
                <option value="skip_duplicates" selected>Skip duplicates</option>
                <option value="upsert">Update existing</option>
                <option value="fail_on_duplicate">Fail row on duplicate</option>
              </select>
            </div>

            <button class="btn" type="submit" style="height:38px">Import</button>
          </form>
        </div>
      </div>

      <div class="form-group" style="margin:0;flex:1;min-width:260px">
        <label>Export columns</label>

        <select id="export-cols" multiple style="min-width:240px;height:120px">
          <option value="service_no" selected>Force/File No</option>
          <option value="full_name" selected>Name</option>
          <option value="rank" selected>Rank</option>
          <option value="gender" selected>Gender</option>
          <option value="branch_name" selected>Branch</option>
          <option value="phone" selected>Phone</option>
          <option value="email" selected>Email</option>
        </select>
        <div class="muted" style="font-size:12px;margin-top:6px">Hold Ctrl/⌘ to select multiple</div>
      </div>

      <div class="form-group" style="margin:0">
        <label>&nbsp;</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <a id="export-csv" class="btn btn-secondary" target="_blank" rel="noopener" href="/export-personnel.php?type=csv">Export CSV</a>
          <a id="export-pdf" class="btn btn-secondary" target="_blank" rel="noopener" href="/export-personnel.php?type=html">Export PDF</a>
        </div>
      </div>
    </div>

    <script>
      (function(){
        const sel = document.getElementById('export-cols');
        const csv = document.getElementById('export-csv');
        const pdf = document.getElementById('export-pdf');

        function getSelectedCols(){
          const out = [];
          if (!sel) return out;
          for (const opt of sel.options) {
            if (opt.selected) out.push(opt.value);
          }
          return out;
        }

        function sync(){
          const cols = getSelectedCols().join(',');
          const q = new URLSearchParams(window.location.search).get('q') || '';
          const branch = new URLSearchParams(window.location.search).get('branch') || '';
          const base = '/export-personnel.php?cols=' + encodeURIComponent(cols);
          const qPart = q ? ('&q=' + encodeURIComponent(q)) : '';
          const bPart = (branch && branch !== '') ? ('&branch=' + encodeURIComponent(branch)) : '';
          const url = base + '&type=csv' + qPart + bPart;
          csv.setAttribute('href', url);
          pdf.setAttribute('href', base + '&type=html' + qPart + bPart);
        }

        if (sel) {
          sel.addEventListener('change', sync);
        }
        sync();
      })();
    </script>
  <?php endif; ?>
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
      <thead><tr><th>Force/File No</th><th>Name</th><th>Rank</th><th>Gender</th><th>Branch</th><th>Phone</th><th>Email</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($employees as $e): ?>
        <tr>
          <td><?= e($e['service_no']) ?></td>
          <td><?= e($e['full_name']) ?></td>
          <td><?= e($e['rank']) ?></td>
          <td><?= $e['gender']==='M'?'Male':'Female' ?></td>
          <td><?= e($e['branch_name']) ?></td>
          <td><?= e($e['phone']) ?></td>
          <td><?= e($e['email']) ?></td>
          <td>
            <?php if ($can_modify_personnel): ?>
              <a class="btn btn-sm btn-secondary" href="/employees.php?edit=<?= $e['id'] ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this employee?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $e['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($employees)): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px">No employees found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
