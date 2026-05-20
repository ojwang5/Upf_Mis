<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/audit.php';

$user = require_role(['admin','manager']);
$page = 'history';
$page_title = 'History';
$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    $rid = (int)($_POST['id'] ?? 0);
    $r = $pdo->prepare("SELECT * FROM reports WHERE id=?"); $r->execute([$rid]); $rep = $r->fetch();
    if ($rep) {
        if ($action==='manager_approve' && is_manager($user) && (int)$rep['branch_id']===(int)$user['branch_id'] && $rep['status']==='pending_manager') {
            $pdo->prepare("UPDATE reports SET status='pending_admin', reviewed_by=?, reviewed_at=?, review_notes=? WHERE id=?")
                ->execute([$user['id'], date('c'), $_POST['notes'] ?? '', $rid]);

            audit_log($user, 'report.manager_approve', 'report', (string)$rid, [
                'from_status' => 'pending_manager',
                'to_status' => 'pending_admin',
                'branch_id' => (int)$rep['branch_id'],
                'generated_by' => (int)$rep['generated_by'],
            ]);

            notify_admins('Branch report forwarded to HQ',
                ($user['branch_name'] ?? '') . ' — ' . date('j M Y', strtotime($rep['date'])),
                ['link'=>'/history.php?id='.$rid,'created_by'=>$user['id'],'kind'=>'report']);
            flash('msg','Report approved and forwarded to HQ.');
        } elseif ($action==='manager_reject' && is_manager($user) && (int)$rep['branch_id']===(int)$user['branch_id'] && $rep['status']==='pending_manager') {
            $pdo->prepare("UPDATE reports SET status='rejected', reviewed_by=?, reviewed_at=?, review_notes=? WHERE id=?")
                ->execute([$user['id'], date('c'), $_POST['notes'] ?? '', $rid]);

            audit_log($user, 'report.manager_reject', 'report', (string)$rid, [
                'from_status' => 'pending_manager',
                'to_status' => 'rejected',
                'branch_id' => (int)$rep['branch_id'],
                'generated_by' => (int)$rep['generated_by'],
            ]);

            notify('Report rejected by manager','Your report for '.date('j M Y',strtotime($rep['date'])).' was rejected.','user',
                ['target_user_id'=>(int)$rep['generated_by'],'created_by'=>$user['id'],'kind'=>'report','link'=>'/history.php?id='.$rid]);
            flash('msg','Report rejected.');
        } elseif ($action==='admin_approve' && is_admin($user) && $rep['status']==='pending_admin') {
            $pdo->prepare("UPDATE reports SET status='approved', reviewed_by=?, reviewed_at=?, review_notes=? WHERE id=?")
                ->execute([$user['id'], date('c'), $_POST['notes'] ?? '', $rid]);

            audit_log($user, 'report.admin_approve', 'report', (string)$rid, [
                'from_status' => 'pending_admin',
                'to_status' => 'approved',
                'branch_id' => (int)($rep['branch_id'] ?? 0),
                'generated_by' => (int)$rep['generated_by'],
            ]);

            if ($rep['branch_id']) {
                notify('Report approved by HQ','Report for '.date('j M Y',strtotime($rep['date'])).' approved.','branch',
                    ['target_branch_id'=>(int)$rep['branch_id'],'created_by'=>$user['id'],'kind'=>'report']);
            }
            flash('msg','Report approved.');
        } elseif ($action==='admin_reject' && is_admin($user) && in_array($rep['status'],['pending_admin','pending_manager'],true)) {
            $pdo->prepare("UPDATE reports SET status='rejected', reviewed_by=?, reviewed_at=?, review_notes=? WHERE id=?")
                ->execute([$user['id'], date('c'), $_POST['notes'] ?? '', $rid]);

            audit_log($user, 'report.admin_reject', 'report', (string)$rid, [
                'from_status' => (string)$rep['status'],
                'to_status' => 'rejected',
                'branch_id' => (int)($rep['branch_id'] ?? 0),
                'generated_by' => (int)$rep['generated_by'],
            ]);

            flash('msg','Report rejected.');
        }
    }
    header('Location: /history.php' . (!empty($_POST['return_id'])?'?id='.(int)$_POST['return_id']:'')); exit;
}

$where = '1=1'; $params = [];
if (is_manager($user)) {
    $where .= ' AND (r.branch_id = ? OR r.branch_id IS NULL)';
    $params[] = $user['branch_id'];
}
if (!empty($_GET['status']) && in_array($_GET['status'],['pending_manager','pending_admin','approved','rejected'],true)) {
    $where .= ' AND r.status = ?'; $params[] = $_GET['status'];
}

$stmt = $pdo->prepare("SELECT r.*, u.full_name AS generator, b.name AS branch_name, rv.full_name AS reviewer
    FROM reports r
    LEFT JOIN users u ON u.id=r.generated_by
    LEFT JOIN branches b ON b.id=r.branch_id
    LEFT JOIN users rv ON rv.id=r.reviewed_by
    WHERE $where ORDER BY
      CASE r.status WHEN 'pending_manager' THEN 1 WHEN 'pending_admin' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END,
      r.generated_at DESC LIMIT 200");
$stmt->execute($params);
$reports = $stmt->fetchAll();

$detail = null;
if (!empty($_GET['id'])) {
    $s = $pdo->prepare("SELECT r.*, u.full_name AS generator, b.name AS branch_name, rv.full_name AS reviewer
        FROM reports r LEFT JOIN users u ON u.id=r.generated_by
        LEFT JOIN branches b ON b.id=r.branch_id
        LEFT JOIN users rv ON rv.id=r.reviewed_by WHERE r.id=?");
    $s->execute([(int)$_GET['id']]);
    $detail = $s->fetch();
    if ($detail) $detail['summary'] = json_decode($detail['summary_json'], true) ?: [];
}

function rep_pill(string $s): string {
    $map = [
      'pending_manager'=>['Pending Manager','badge-sick'],
      'pending_admin'  =>['Pending HQ','badge-leave'],
      'approved'       =>['Approved','badge-present'],
      'rejected'       =>['Rejected','badge-awol'],
    ];
    [$lab,$cls] = $map[$s] ?? [$s,'badge-admin'];
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($lab) . '</span>';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Report History</h1><div class="desc">Submitted reports with approval status</div></div>
  <form method="get" class="form-row" style="margin:0">
    <div class="form-group"><label>Status</label>
      <select name="status" onchange="this.form.submit()">
        <option value="">All</option>
        <?php foreach (['pending_manager'=>'Pending Manager','pending_admin'=>'Pending HQ','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= (($_GET['status']??'')===$k)?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
  <div style="display:flex;gap:10px;align-items:flex-end">
    <?php $qs = 'status='.(urlencode($_GET['status'] ?? '')); ?>
    <a class="btn btn-secondary" href="/export-history.php?type=csv&status=<?= e($_GET['status'] ?? '') ?>">Export CSV</a>
    <a class="btn btn-secondary" href="/export-history.php?type=html&status=<?= e($_GET['status'] ?? '') ?>" target="_blank" rel="noopener">Export PDF</a>
  </div>
</div>


<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Branch</th><th>Status</th><th>Generated By</th><th>Reviewed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($reports as $r): ?>
          <tr>
            <td><?= e(date('j M Y', strtotime($r['date']))) ?></td>
            <td><?= e($r['branch_name'] ?: 'All Branches') ?></td>
            <td><?= rep_pill($r['status']) ?></td>
            <td><?= e($r['generator'] ?? '—') ?><br><span class="muted"><?= e(date('M j H:i', strtotime($r['generated_at']))) ?></span></td>
            <td><?= $r['reviewer'] ? e($r['reviewer']) . '<br><span class="muted">' . e(date('M j H:i', strtotime($r['reviewed_at']))) . '</span>' : '<span class="muted">—</span>' ?></td>
            <td><a class="btn btn-sm btn-secondary" href="/history.php?id=<?= $r['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$reports): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">No reports yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($detail): ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">Report — <?= e(date('j F Y', strtotime($detail['date']))) ?> · <?= rep_pill($detail['status']) ?></h3>
    <a class="btn btn-secondary" href="/history.php">Close</a>
  </div>
  <div class="muted" style="margin:6px 0 14px">
    Generated by <?= e($detail['generator']) ?> on <?= e(date('Y-m-d H:i', strtotime($detail['generated_at']))) ?>
    <?= $detail['reviewer'] ? ' · Reviewed by ' . e($detail['reviewer']) : '' ?>
    <?= $detail['review_notes'] ? '<br>Notes: ' . e($detail['review_notes']) : '' ?>
  </div>

  <?php
  $canMgr = is_manager($user) && (int)$detail['branch_id']===(int)$user['branch_id'] && $detail['status']==='pending_manager';
  $canAdm = is_admin($user) && $detail['status']==='pending_admin';
  if ($canMgr || $canAdm): ?>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;background:var(--navy-50);padding:12px;border-radius:8px;margin-bottom:14px">
      <input type="hidden" name="id" value="<?= $detail['id'] ?>">
      <input type="hidden" name="return_id" value="<?= $detail['id'] ?>">
      <input type="text" name="notes" placeholder="Review notes (optional)" style="flex:1;min-width:200px">
      <?php if ($canMgr): ?>
        <button class="btn" name="action" value="manager_approve">Approve &amp; forward to HQ</button>
        <button class="btn btn-danger" name="action" value="manager_reject">Reject</button>
      <?php else: ?>
        <button class="btn" name="action" value="admin_approve">Approve (HQ Final)</button>
        <button class="btn btn-danger" name="action" value="admin_reject">Reject</button>
      <?php endif; ?>
    </form>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Branch</th><th>Total</th><th>Present</th><th>AWOL</th><th>Leave</th><th>Sick</th><th>Unrecorded</th></tr></thead>
      <tbody>
        <?php foreach ($detail['summary'] as $s): ?>
          <tr><td><?= e($s['branch_name']) ?></td><td><?= $s['total'] ?></td><td><?= $s['present'] ?></td>
            <td><?= $s['awol'] ?></td><td><?= $s['on_leave'] ?></td><td><?= $s['sick'] ?></td><td><?= $s['unrecorded'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
