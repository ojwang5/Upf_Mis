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

<div class="grid grid-4">
  <div class="stat present"><div><div class="label">Present</div><div class="value"><?= $totals['present'] ?></div></div><div class="icon">P</div></div>
  <div class="stat awol"><div><div class="label">AWOL</div><div class="value"><?= $totals['awol'] ?></div></div><div class="icon">A</div></div>
  <div class="stat leave"><div><div class="label">On Leave</div><div class="value"><?= $totals['on_leave'] ?></div></div><div class="icon">L</div></div>
  <div class="stat sick"><div><div class="label">Sick</div><div class="value"><?= $totals['sick'] ?></div></div><div class="icon">S</div></div>
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
