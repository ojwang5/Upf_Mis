<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
$user = require_login();
$page = 'reports';
$page_title = 'Reports';
$pdo = db();

$date = $_GET['date'] ?? date('Y-m-d');
$branchId = user_branch_filter($user);
if (is_admin($user) && !empty($_GET['branch'])) $branchId = (int)$_GET['branch'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='generate') {
    if (is_officer($user) || is_manager($user)) {
        $branchId = (int)$user['branch_id'];
    }
    $summaries = branch_summary($pdo, $date, $branchId);
    if (is_admin($user))      $status = 'approved';
    elseif (is_manager($user)) $status = 'pending_admin';
    else                       $status = 'pending_manager';

    $stmt = $pdo->prepare("INSERT INTO reports (branch_id, date, generated_by, generated_at, summary_json, status) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$branchId, $date, $user['id'], date('c'), json_encode($summaries), $status]);
    $rid = (int)$pdo->lastInsertId();

    if ($status === 'pending_manager' && $branchId) {
        notify_branch_managers($branchId, 'New report awaiting approval',
            'Officer ' . $user['full_name'] . ' submitted a report for ' . date('j M Y', strtotime($date)),
            ['link'=>'/history.php?id='.$rid,'created_by'=>$user['id'],'kind'=>'report']);
    } elseif ($status === 'pending_admin') {
        notify_admins('New report awaiting HQ approval',
            ($user['branch_name'] ?? 'Branch') . ' — ' . date('j M Y', strtotime($date)) . ' (by ' . $user['full_name'] . ')',
            ['link'=>'/history.php?id='.$rid,'created_by'=>$user['id'],'kind'=>'report']);
    }

    flash('msg', $status==='approved' ? 'Report saved.' : 'Report submitted for approval.');
    header('Location: /reports.php?date=' . urlencode($date) . ($branchId?'&branch='.$branchId:'')); exit;
}

$summaries = branch_summary($pdo, $date, $branchId);
$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Reports</h1><div class="desc">Generate, submit and export attendance reports</div></div>
  <form method="get" class="form-row" style="margin:0">
    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
    <?php if (is_admin($user)): ?>
    <div class="form-group"><label>Branch</label>
      <select name="branch" onchange="this.form.submit()"><option value="">All</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= ($branchId==$b['id'])?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">Attendance Report — <?= e(date('j F Y', strtotime($date))) ?></h3>
    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
      <form method="post" style="display:inline"><input type="hidden" name="action" value="generate">
        <button class="btn"><?= is_officer($user) ? 'Submit to Manager' : (is_manager($user) ? 'Submit to HQ' : 'Save to History') ?></button>
      </form>
      <a class="btn btn-secondary" href="/export.php?type=csv&date=<?= e($date) ?><?= $branchId?'&branch='.$branchId:'' ?>">Export CSV</a>
      <a class="btn btn-secondary" href="/export.php?type=print&date=<?= e($date) ?><?= $branchId?'&branch='.$branchId:'' ?>" target="_blank">Print / PDF</a>
    </div>
  </div>
  <?php if (is_officer($user)): ?>
    <div class="muted" style="margin-top:6px">Your report will be sent to the <?= e($user['branch_name']) ?> branch manager for review before reaching HQ.</div>
  <?php elseif (is_manager($user)): ?>
    <div class="muted" style="margin-top:6px">Submitting will forward this report to HQ administration.</div>
  <?php endif; ?>
  <div class="table-wrap" style="margin-top:14px">
    <table>
      <thead><tr><th>Branch</th><th>Total</th><th>Present</th><th>AWOL</th><th>Leave</th><th>Sick</th><th>Unrecorded</th><th>Attendance %</th></tr></thead>
      <tbody>
        <?php $tot=['total'=>0,'present'=>0,'awol'=>0,'on_leave'=>0,'sick'=>0,'unrecorded'=>0]; ?>
        <?php foreach ($summaries as $s): foreach ($tot as $k=>$_) $tot[$k]+=$s[$k]; ?>
          <tr>
            <td><?= e($s['branch_name']) ?> <span class="muted">(<?= e($s['location']) ?>)</span></td>
            <td><?= $s['total'] ?></td><td><?= $s['present'] ?></td><td><?= $s['awol'] ?></td>
            <td><?= $s['on_leave'] ?></td><td><?= $s['sick'] ?></td><td><?= $s['unrecorded'] ?></td>
            <td><?= $s['total']?round(($s['present']/$s['total'])*100):0 ?>%</td>
          </tr>
        <?php endforeach; ?>
        <tr style="font-weight:700;background:var(--navy-50)">
          <td>TOTAL</td><td><?= $tot['total'] ?></td><td><?= $tot['present'] ?></td><td><?= $tot['awol'] ?></td>
          <td><?= $tot['on_leave'] ?></td><td><?= $tot['sick'] ?></td><td><?= $tot['unrecorded'] ?></td>
          <td><?= $tot['total']?round(($tot['present']/$tot['total'])*100):0 ?>%</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
