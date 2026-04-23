<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
$user = require_login();
$page = 'leave';
$page_title = 'Leave Requests';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'submit') {
        $eid = (int)$_POST['employee_id'];
        $emp = $pdo->prepare("SELECT * FROM employees WHERE id=?"); $emp->execute([$eid]); $e = $emp->fetch();
        if ($e && can_access_branch($user, (int)$e['branch_id'])) {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, branch_id, leave_type, start_date, end_date, reason, status, submitted_by, submitted_at) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $eid, (int)$e['branch_id'], $_POST['leave_type'] ?? 'Annual',
                $_POST['start_date'], $_POST['end_date'], $_POST['reason'] ?? '',
                'pending_manager', $user['id'], date('c')
            ]);
            $rid = (int)$pdo->lastInsertId();
            notify_branch_managers((int)$e['branch_id'],
                'New leave request',
                $e['full_name'] . ' — ' . ($_POST['leave_type'] ?? 'Annual') . ' leave request submitted.',
                ['link' => '/leave-requests.php#req-' . $rid, 'created_by' => $user['id'], 'kind' => 'leave']
            );
            flash('msg', 'Leave request submitted to branch manager.');
        }
    } elseif ($action === 'manager_review' && in_array($user['role'], ['manager','admin'], true)) {
        $rid = (int)$_POST['id']; $decision = $_POST['decision']; $notes = $_POST['notes'] ?? '';
        $req = $pdo->prepare("SELECT lr.*, e.full_name AS emp_name FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.id=?");
        $req->execute([$rid]); $r = $req->fetch();
        if ($r && can_access_branch($user, (int)$r['branch_id']) && $r['status']==='pending_manager') {
            $newStatus = $decision === 'approve' ? 'pending_admin' : 'rejected_manager';
            $pdo->prepare("UPDATE leave_requests SET status=?, manager_reviewed_by=?, manager_reviewed_at=?, manager_notes=? WHERE id=?")
                ->execute([$newStatus, $user['id'], date('c'), $notes, $rid]);
            if ($decision==='approve') {
                notify_admins('Leave request awaiting HQ approval',
                    $r['emp_name'] . " (" . $r['leave_type'] . ") forwarded by " . $user['full_name'],
                    ['link'=>'/leave-requests.php#req-'.$rid,'created_by'=>$user['id'],'kind'=>'leave']);
            }
            // notify submitter
            notify('Leave request ' . ($decision==='approve'?'forwarded':'rejected'),
                $r['emp_name'] . ' — manager has ' . $decision . 'd your request.',
                'user', ['target_user_id' => (int)$r['submitted_by'], 'created_by'=>$user['id'], 'kind'=>'leave', 'link'=>'/leave-requests.php#req-'.$rid]);
            flash('msg', 'Decision recorded.');
        }
    } elseif ($action === 'admin_review' && is_admin($user)) {
        $rid = (int)$_POST['id']; $decision = $_POST['decision']; $notes = $_POST['notes'] ?? '';
        $req = $pdo->prepare("SELECT lr.*, e.full_name AS emp_name FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.id=?");
        $req->execute([$rid]); $r = $req->fetch();
        if ($r && $r['status']==='pending_admin') {
            $newStatus = $decision === 'approve' ? 'approved' : 'rejected_admin';
            $pdo->prepare("UPDATE leave_requests SET status=?, admin_reviewed_by=?, admin_reviewed_at=?, admin_notes=? WHERE id=?")
                ->execute([$newStatus, $user['id'], date('c'), $notes, $rid]);
            // notify branch + submitter
            notify('Leave request ' . ($decision==='approve'?'approved by HQ':'rejected by HQ'),
                $r['emp_name'] . ' (' . $r['leave_type'] . ')',
                'branch', ['target_branch_id'=>(int)$r['branch_id'],'created_by'=>$user['id'],'kind'=>'leave','link'=>'/leave-requests.php#req-'.$rid]);
            notify('Leave request ' . ($decision==='approve'?'approved':'rejected'),
                $r['emp_name'], 'user', ['target_user_id'=>(int)$r['submitted_by'],'created_by'=>$user['id'],'kind'=>'leave','link'=>'/leave-requests.php#req-'.$rid]);
            flash('msg', 'Decision recorded.');
        }
    }
    header('Location: /leave-requests.php'); exit;
}

// Build employee list for submission scoped to user
$empWhere = '1=1'; $empParams = [];
if (!is_admin($user)) { $empWhere .= ' AND branch_id = ?'; $empParams[] = $user['branch_id']; }
$empStmt = $pdo->prepare("SELECT id, full_name, service_no FROM employees WHERE $empWhere AND active=1 ORDER BY full_name");
$empStmt->execute($empParams); $emps = $empStmt->fetchAll();

// Requests visible to user
$where = '1=1'; $params = [];
if (is_manager($user)) { $where .= ' AND lr.branch_id = ?'; $params[] = $user['branch_id']; }
elseif (is_officer($user)) { $where .= ' AND lr.submitted_by = ?'; $params[] = $user['id']; }

$sql = "SELECT lr.*, e.full_name AS emp_name, e.service_no, b.name AS branch_name,
              s.full_name AS submitter, m.full_name AS mgr_name, a.full_name AS adm_name
        FROM leave_requests lr
        JOIN employees e ON e.id=lr.employee_id
        JOIN branches b ON b.id=lr.branch_id
        LEFT JOIN users s ON s.id=lr.submitted_by
        LEFT JOIN users m ON m.id=lr.manager_reviewed_by
        LEFT JOIN users a ON a.id=lr.admin_reviewed_by
        WHERE $where
        ORDER BY CASE lr.status
          WHEN 'pending_manager' THEN 1
          WHEN 'pending_admin' THEN 2
          ELSE 3 END, lr.submitted_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$requests = $stmt->fetchAll();

function status_pill(string $s): string {
    $map = [
      'pending_manager' => ['Pending Manager','badge-sick'],
      'pending_admin'   => ['Pending HQ','badge-leave'],
      'approved'        => ['Approved','badge-present'],
      'rejected_manager'=> ['Rejected by Manager','badge-awol'],
      'rejected_admin'  => ['Rejected by HQ','badge-awol'],
    ];
    [$lab,$cls] = $map[$s] ?? [$s,'badge-admin'];
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($lab) . '</span>';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Leave Requests</h1><div class="desc">Submit and review personnel leave applications</div></div>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <h3>Submit New Leave Request</h3>
  <form method="post">
    <input type="hidden" name="action" value="submit">
    <div class="form-row">
      <div class="form-group"><label>Employee</label>
        <select name="employee_id" required>
          <option value="">— select —</option>
          <?php foreach ($emps as $e): ?>
            <option value="<?= $e['id'] ?>"><?= e($e['service_no']) ?> — <?= e($e['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Type</label>
        <select name="leave_type">
          <option>Annual</option><option>Sick</option><option>Compassionate</option><option>Maternity</option><option>Other</option>
        </select>
      </div>
      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label>End Date</label><input type="date" name="end_date" required value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group" style="flex:2;min-width:240px"><label>Reason</label><input type="text" name="reason" required></div>
      <div class="form-group" style="flex:0"><label>&nbsp;</label><button class="btn">Submit</button></div>
    </div>
    <div class="muted" style="margin-top:6px">Submissions go first to the branch manager, then to HQ for final approval.</div>
  </form>
</div>

<div class="card">
  <h3>Requests</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Employee</th><th>Branch</th><th>Type</th><th>Dates</th><th>Reason</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
        <tr id="req-<?= $r['id'] ?>">
          <td><strong><?= e($r['emp_name']) ?></strong><br><span class="muted"><?= e($r['service_no']) ?></span></td>
          <td><?= e($r['branch_name']) ?></td>
          <td><?= e($r['leave_type']) ?></td>
          <td><?= e($r['start_date']) ?> → <?= e($r['end_date']) ?></td>
          <td><?= e($r['reason']) ?></td>
          <td><?= status_pill($r['status']) ?>
            <?php if ($r['mgr_name']): ?><div class="muted">Mgr: <?= e($r['mgr_name']) ?></div><?php endif; ?>
            <?php if ($r['adm_name']): ?><div class="muted">HQ: <?= e($r['adm_name']) ?></div><?php endif; ?>
          </td>
          <td><span class="muted"><?= e(date('M j, H:i', strtotime($r['submitted_at']))) ?><br>by <?= e($r['submitter'] ?? '—') ?></span></td>
          <td>
            <?php if ($r['status']==='pending_manager' && (is_manager($user) && (int)$r['branch_id']===(int)$user['branch_id']) || ($r['status']==='pending_manager' && is_admin($user))): ?>
              <details><summary class="btn btn-sm btn-secondary" style="display:inline-block">Review</summary>
                <form method="post" style="margin-top:8px;display:flex;flex-direction:column;gap:6px;min-width:220px">
                  <input type="hidden" name="action" value="manager_review"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <input type="text" name="notes" placeholder="Notes (optional)">
                  <div style="display:flex;gap:6px">
                    <button class="btn btn-sm" name="decision" value="approve">Approve &amp; forward</button>
                    <button class="btn btn-sm btn-danger" name="decision" value="reject">Reject</button>
                  </div>
                </form>
              </details>
            <?php elseif ($r['status']==='pending_admin' && is_admin($user)): ?>
              <details><summary class="btn btn-sm btn-secondary" style="display:inline-block">HQ Decision</summary>
                <form method="post" style="margin-top:8px;display:flex;flex-direction:column;gap:6px;min-width:220px">
                  <input type="hidden" name="action" value="admin_review"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <input type="text" name="notes" placeholder="Notes (optional)">
                  <div style="display:flex;gap:6px">
                    <button class="btn btn-sm" name="decision" value="approve">Approve</button>
                    <button class="btn btn-sm btn-danger" name="decision" value="reject">Reject</button>
                  </div>
                </form>
              </details>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px">No leave requests yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
