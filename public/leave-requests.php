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

    // Submitter actions: extend / renew for expired approved leaves
    if (in_array($action, ['extend', 'renew'], true)) {
        if (!is_officer($user)) {
            // submission is allowed for all submitters (officer role) only in this system
            // keep silent for non-officers
        }
        $rid = (int)($_POST['id'] ?? 0);
        $newEnd = $_POST['new_end_date'] ?? '';
        if ($rid > 0 && $newEnd && (is_officer($user))) {
            $reqStmt = $pdo->prepare("SELECT lr.*, e.full_name AS emp_name
                FROM leave_requests lr
                JOIN employees e ON e.id=lr.employee_id
                WHERE lr.id=? AND lr.submitted_by=?");
            $reqStmt->execute([$rid, (int)$user['id']]);
            $r = $reqStmt->fetch();
            $today = date('Y-m-d');
            if ($r && $r['status']==='approved' && $r['end_date'] < $today) {
                // Only the original submitter can extend/renew
                if ((int)$r['submitted_by'] !== (int)$user['id']) {
                    flash('err', 'You can only extend/renew your own leave application.');
                    header('Location: /leave-requests.php'); exit;
                }

                if ($action === 'extend') {
                    $pdo->prepare("UPDATE leave_requests SET end_date=?, expiry_renewal_status='extended', expires_notified_at=NULL WHERE id=?")
                        ->execute([$newEnd, $rid]);
                    flash('msg', 'Leave extended successfully.');
                } else {
                    // Renew = create a new pending request (reapplication)
                    $reason = $_POST['reason'] ?? $r['reason'];
                    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, branch_id, leave_type, start_date, end_date, reason, destination, status, submitted_by, submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([
                        (int)$r['employee_id'],
                        (int)$r['branch_id'],
                        $r['leave_type'],
                        $r['start_date'],
                        $newEnd,
                        $reason,
                        $r['destination'] ?? '',
                        'pending_manager',
                        (int)$user['id'],
                        date('c')
                    ]);
                    $newRid = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE leave_requests SET expiry_renewal_status='renewed' WHERE id=?")
                        ->execute([$rid]);
                    notify_branch_managers((int)$r['branch_id'],
                        'New leave renewal request',
                        $r['emp_name'] . ' — ' . $r['leave_type'] . ' renewal request submitted.',
                        ['link' => '/leave-requests.php#req-' . $newRid, 'created_by' => $user['id'], 'kind' => 'leave']
                    );
                    flash('msg', 'Leave renewed successfully. A new request was submitted for review.');
                    
                }
            }
        }
        header('Location: /leave-requests.php'); exit;
    }

    if ($action === 'submit') {

        $eid = (int)$_POST['employee_id'];
        $emp = $pdo->prepare("SELECT * FROM employees WHERE id=?"); $emp->execute([$eid]); $e = $emp->fetch();
        if ($e && can_access_branch($user, (int)$e['branch_id'])) {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, branch_id, leave_type, start_date, end_date, reason, destination, status, submitted_by, submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $eid, (int)$e['branch_id'], $_POST['leave_type'] ?? 'Annual',
                $_POST['start_date'], $_POST['end_date'], $_POST['reason'] ?? '',
                $_POST['destination'] ?? '',
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

// Expiry notification (notify submitter once)
$today = date('Y-m-d');

// Defensive: some DBs may not have expires_notified_at yet.
$hasExpiryNotifiedAt = false;
try {
    $cols = [];
    foreach ($pdo->query("PRAGMA table_info(leave_requests)") as $c) {
        $cols[$c['name']] = true;
    }
    $hasExpiryNotifiedAt = isset($cols['expires_notified_at']);
} catch (Throwable $e) {
    $hasExpiryNotifiedAt = false;
}

if ($hasExpiryNotifiedAt) {
    $notifStmt = $pdo->prepare("SELECT lr.*, e.full_name AS emp_name
        FROM leave_requests lr
        JOIN employees e ON e.id=lr.employee_id
        WHERE lr.status='approved' AND lr.end_date < ?
          AND (lr.expires_notified_at IS NULL OR lr.expires_notified_at='')");
    $notifStmt->execute([$today]);
    $expiredToNotify = $notifStmt->fetchAll();
    foreach ($expiredToNotify as $r) {
        notify('Leave expired',
            $r['emp_name'] . ' (' . $r['leave_type'] . ') has expired. Choose to extend or renew by re-application.',
            'user',
            [
                'target_user_id' => (int)$r['submitted_by'],
                'created_by' => $user['id'],
                'kind' => 'leave',
                'link' => '/leave-requests.php#req-' . (int)$r['id']
            ]
        );
        $pdo->prepare("UPDATE leave_requests SET expires_notified_at=? WHERE id=?")
            ->execute([date('c'), (int)$r['id']]);
    }
} else {
    // Fallback: notify without tracking (prevents fatal error on old schemas)
    $notifStmt = $pdo->prepare("SELECT lr.*, e.full_name AS emp_name
        FROM leave_requests lr
        JOIN employees e ON e.id=lr.employee_id
        WHERE lr.status='approved' AND lr.end_date < ?");
    $notifStmt->execute([$today]);
    $expiredToNotify = $notifStmt->fetchAll();

    foreach ($expiredToNotify as $r) {
        notify('Leave expired',
            $r['emp_name'] . ' (' . $r['leave_type'] . ') has expired. Choose to extend or renew by re-application.',
            'user',
            [
                'target_user_id' => (int)$r['submitted_by'],
                'created_by' => $user['id'],
                'kind' => 'leave',
                'link' => '/leave-requests.php#req-' . (int)$r['id']
            ]
        );
    }
}




include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">

  <div><h1>Leave Requests</h1><div class="desc">Submit and review personnel leave applications</div></div>
  <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;justify-content:flex-end">
    <a class="btn btn-secondary" href="/export-leave-types.php?type=csv">Export All (CSV)</a>
    <a class="btn btn-secondary" href="/export-leave-types.php?type=html" target="_blank" rel="noopener">Export All (PDF)</a>
  </div>
</div>


<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('err')): ?><div class="alert alert-error"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <h3>Submit New Leave Request</h3>

  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px">
    <button id="toggle-leave-form" class="btn btn-secondary" type="button">Add Leave</button>
    <div class="muted" style="font-size:12px;">Show/Hide leave request fields</div>
  </div>

  <form method="post" id="leave-form" style="display:none">
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
          <option>Annual</option>
          <option>Sick</option>
          <option>Compassionate</option>
          <option>Study leave</option>
          <option>Maternity leave</option>
          <option>Paternity leave</option>
          <option>Pass leave</option>
          <option>Other</option>
        </select>
      </div>

      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label>End Date</label><input type="date" name="end_date" required value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group" style="flex:2;min-width:240px"><label>Destination</label><input type="text" name="destination" placeholder="e.g. Home/City/Station" required></div>
      <div class="form-group" style="flex:2;min-width:240px"><label>Reason</label><input type="text" name="reason" required></div>
      <div class="form-group" style="flex:0"><label>&nbsp;</label><button class="btn">Submit</button></div>
    </div>
    <div class="muted" style="margin-top:6px">Submissions go first to the branch manager, then to HQ for final approval.</div>
  </form>

  <script>
    (function(){
      const btn = document.getElementById('toggle-leave-form');
      const form = document.getElementById('leave-form');
      if (!btn || !form) return;

      function syncLabel(){
        const isOpen = form.style.display !== 'none';
        btn.textContent = isOpen ? 'Hide Form' : 'Add Leave';
      }

      btn.addEventListener('click', function(){
        form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none';
        syncLabel();
      });

      syncLabel();
    })();
  </script>
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
            <?php
              $isExpiredApproved = ($r['status']==='approved' && $r['end_date'] < date('Y-m-d') && (int)$r['submitted_by']===(int)$user['id']);
            ?>

            <?php if ($isExpiredApproved): ?>
              <details><summary class="btn btn-sm btn-secondary" style="display:inline-block">Expired - Actions</summary>
                <form method="post" style="margin-top:8px;display:flex;flex-direction:column;gap:8px;min-width:240px">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="extend">
                  <div class="form-group" style="margin:0">
                    <label style="display:block">New End Date</label>
                    <input type="date" name="new_end_date" required value="<?= e(date('Y-m-d')) ?>" style="width:100%">
                  </div>
                  <button class="btn btn-sm">Extend</button>
                </form>

                <form method="post" style="margin-top:10px;display:flex;flex-direction:column;gap:8px;min-width:240px">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="renew">
                  <div class="form-group" style="margin:0">
                    <label style="display:block">New End Date</label>
                    <input type="date" name="new_end_date" required value="<?= e(date('Y-m-d')) ?>" style="width:100%">
                  </div>
                  <div class="form-group" style="margin:0">
                    <label style="display:block">Reason (optional)</label>
                    <input type="text" name="reason" placeholder="Optional" value="<?= e($r['reason'] ?? '') ?>" style="width:100%">
                  </div>
                  <button class="btn btn-sm" style="background:var(--danger);border-color:var(--danger)" type="submit">Renew (Re-apply)</button>
                </form>
              </details>

            <?php elseif ($r['status']==='pending_manager' && (is_manager($user) && (int)$r['branch_id']===(int)$user['branch_id']) || ($r['status']==='pending_manager' && is_admin($user))): ?>

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
