<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = require_login();
$page = 'dashboard';
$page_title = 'Dashboard';

$date = $_GET['date'] ?? date('Y-m-d');
$branchId = user_branch_filter($user);
if ($user['role'] === 'admin' && !empty($_GET['branch'])) {
    $branchId = (int)$_GET['branch'];
}
$summaries = branch_summary(db(), $date, $branchId);

$totals = ['total'=>0,'present'=>0,'awol'=>0,'on_leave'=>0,'sick'=>0,'male'=>0,'female'=>0,'unrecorded'=>0];
foreach ($summaries as $s) foreach ($totals as $k=>$_) $totals[$k] += $s[$k];

$branches = db()->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <div class="desc">Overview of personnel statistics and daily status</div>
  </div>
  <form method="get" class="form-row" style="margin:0">
    <?php if ($user['role']==='admin'): ?>
    <div class="form-group" style="min-width:160px">
      <label>Branch</label>
      <select name="branch" onchange="this.form.submit()">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= ($branchId===(int)$b['id'])?'selected':'' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group" style="min-width:160px">
      <label>Date</label>
      <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
    </div>
  </form>
</div>

<?php
$detailQS = 'date=' . urlencode($date) . ($branchId ? '&branch=' . $branchId : '');
$ic_present = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
$ic_awol = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
$ic_leave = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
$ic_sick = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>';
?>
<div class="grid grid-4">
  <a class="stat present stat-link" href="/status-details.php?status=present&<?= e($detailQS) ?>"><div><div class="label">Present</div><div class="value"><?= $totals['present'] ?></div><div class="hint">View details &rarr;</div></div><div class="icon"><?= $ic_present ?></div></a>
  <a class="stat awol stat-link" href="/status-details.php?status=awol&<?= e($detailQS) ?>"><div><div class="label">AWOL</div><div class="value"><?= $totals['awol'] ?></div><div class="hint">View details &rarr;</div></div><div class="icon"><?= $ic_awol ?></div></a>
  <a class="stat leave stat-link" href="/status-details.php?status=leave&<?= e($detailQS) ?>"><div><div class="label">On Leave</div><div class="value"><?= $totals['on_leave'] ?></div><div class="hint">View details &rarr;</div></div><div class="icon"><?= $ic_leave ?></div></a>
  <a class="stat sick stat-link" href="/status-details.php?status=sick&<?= e($detailQS) ?>"><div><div class="label">Sick</div><div class="value"><?= $totals['sick'] ?></div><div class="hint">View details &rarr;</div></div><div class="icon"><?= $ic_sick ?></div></a>
</div>

<div class="card" style="margin-top:18px">
  <h2>Branch Overview — <?= e(date('l, j F Y', strtotime($date))) ?></h2>
  <div class="grid grid-3">
    <?php foreach ($summaries as $s):
      $pct = $s['total'] ? round(($s['present']/$s['total'])*100) : 0;
    ?>
      <div class="branch-card">
        <h3><?= e($s['branch_name']) ?></h3>
        <div class="loc"><?= e($s['location']) ?> · <?= $s['total'] ?> personnel</div>
        <div class="kv"><span>Present</span><strong><?= $s['present'] ?></strong></div>
        <div class="kv"><span>AWOL</span><strong><?= $s['awol'] ?></strong></div>
        <div class="kv"><span>On Leave</span><strong><?= $s['on_leave'] ?></strong></div>
        <div class="kv"><span>Sick</span><strong><?= $s['sick'] ?></strong></div>
        <div class="kv"><span>Unrecorded</span><strong><?= $s['unrecorded'] ?></strong></div>
        <div style="margin-top:10px">
          <div class="muted">Attendance: <?= $pct ?>%</div>
          <div class="bar"><span style="width:<?= $pct ?>%"></span></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Gender Distribution</h3>
    <div class="kv"><span>Male</span><strong><?= $totals['male'] ?></strong></div>
    <div class="kv"><span>Female</span><strong><?= $totals['female'] ?></strong></div>
    <div class="kv"><span>Total Personnel</span><strong><?= $totals['total'] ?></strong></div>
  </div>
  <div class="card">
    <h3>Status Summary</h3>
    <div class="kv"><span>Recorded</span><strong><?= $totals['present']+$totals['awol']+$totals['on_leave']+$totals['sick'] ?></strong></div>
    <div class="kv"><span>Unrecorded</span><strong><?= $totals['unrecorded'] ?></strong></div>
    <div class="kv"><span>Attendance Rate</span><strong><?= $totals['total']?round(($totals['present']/$totals['total'])*100):0 ?>%</strong></div>
  </div>
</div>

<script>
// Auto-refresh every 30 seconds (only when on today's date and no form interaction)
setTimeout(()=>{ if (document.visibilityState==='visible') location.reload(); }, 30000);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
