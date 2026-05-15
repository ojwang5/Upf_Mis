<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/audit.php';

$user = require_admin();
$pdo = db();

$pageTitle = 'Admin Activity Log';
$page = 'admin-audit';

// Filters
$q = trim((string)($_GET['q'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$targetType = trim((string)($_GET['target_type'] ?? ''));
$actorId = (int)($_GET['actor_id'] ?? 0);
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$where = '1=1';
$params = [];

if ($q !== '') {
    $where .= ' AND (al.action LIKE :q OR al.target_type LIKE :q OR al.target_id LIKE :q OR u.full_name LIKE :q OR u.username LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($action !== '') {
    $where .= ' AND al.action = :action';
    $params[':action'] = $action;
}
if ($targetType !== '') {
    $where .= ' AND al.target_type = :tt';
    $params[':tt'] = $targetType;
}
if ($actorId > 0) {
    $where .= ' AND al.actor_user_id = :aid';
    $params[':aid'] = $actorId;
}
if ($from !== '') {
    $where .= ' AND al.created_at >= :from';
    $params[':from'] = $from . 'T00:00:00';
}
if ($to !== '') {
    $where .= ' AND al.created_at <= :to';
    $params[':to'] = $to . 'T23:59:59';
}

$limit = 100;
$offset = 0;
if (!empty($_GET['page'])) {
    $p = max(1, (int)$_GET['page']);
    $offset = ($p - 1) * $limit;
}

$totalStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs al
    LEFT JOIN users u ON u.id = al.actor_user_id
    WHERE $where"
);
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT al.*, u.full_name AS actor_full_name, u.username AS actor_username
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.actor_user_id
     WHERE $where
     ORDER BY al.id DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pages = (int)max(1, ceil($total / $limit));

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Admin Activity Log</h1>
    <div class="desc">Audit trail of user actions (admin monitoring).</div>
  </div>
</div>

<?php if ($m = flash('msg')): ?>
  <div class="alert alert-success"><?= e($m) ?></div>
<?php endif; ?>

<div class="card">
  <h3>Filters</h3>
  <form method="get" class="form-row" style="gap:12px">
    <div class="form-group" style="flex:1;min-width:220px">
      <label>Search</label>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="action, target, user...">
    </div>
    <div class="form-group">
      <label>Action (exact)</label>
      <input type="text" name="action" value="<?= e($action) ?>" placeholder="e.g. user.create">
    </div>
    <div class="form-group">
      <label>Target Type</label>
      <input type="text" name="target_type" value="<?= e($targetType) ?>" placeholder="user/employee/branch/report">
    </div>
    <div class="form-group">
      <label>Actor User ID</label>
      <input type="number" name="actor_id" value="<?= (int)$actorId ?>" min="0">
    </div>
    <div class="form-group">
      <label>From</label>
      <input type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="form-group">
      <label>To</label>
      <input type="date" name="to" value="<?= e($to) ?>">
    </div>
    <div class="form-group" style="flex:0;align-self:flex-end">
      <button class="btn btn-secondary" type="submit">Apply</button>
    </div>
  </form>
</div>

<div class="card" style="margin-top:14px">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <div>
      <div class="muted" style="margin-bottom:10px">
        Showing <?= (string)count($rows) ?> of <?= (string)$total ?> records
      </div>
      <div class="muted">Retention default: <b>90 days</b>. Use purge below to delete older records.</div>
    </div>

    <form method="post" action="/admin-audit-purge.php" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="older_than_days">
      <input type="hidden" name="confirm" value="DELETE">
      <div class="form-group">
        <label>Delete audit logs older than</label>
        <input type="number" name="days" value="90" min="1" style="width:110px">
      </div>
      <button class="btn btn-danger" type="submit" onclick="return confirm('This will permanently delete audit logs older than the selected number of days. Continue?')">Purge</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:14px">
  <div class="table-wrap">
    <form method="post" action="/admin-audit-purge.php">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="bulk">
      <input type="hidden" name="confirm" value="DELETE">

      <table>
        <thead>
          <tr>
            <th>Select</th>
            <th>#</th>
            <th>When</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Target</th>
            <th>IP</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>">
              </td>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e(date('Y-m-d H:i:s', strtotime((string)$r['created_at']))) ?></td>
              <td>
                <?= $r['actor_full_name'] ? e($r['actor_full_name']) : '<span class="muted">—</span>' ?><br>
                <span class="muted"><?= $r['actor_username'] ? e($r['actor_username']) : '' ?></span>
              </td>
              <td><?= e($r['action']) ?></td>
              <td>
                <?= e($r['target_type'] ?? '—') ?><br>
                <span class="muted"><?= e($r['target_id'] ?? '—') ?></span>
                <?php if (!empty($r['meta_json'])): ?>
                  <details>
                    <summary class="muted" style="cursor:pointer">meta</summary>
                    <pre style="white-space:pre-wrap;margin:8px 0;font-size:12px"><?= e((string)json_decode($r['meta_json'], true) ?: $r['meta_json']) ?></pre>
                  </details>
                <?php endif; ?>
              </td>
              <td><?= e((string)($r['ip_address'] ?? '')) ?></td>
              <td style="text-align:right">
                <form method="post" action="/admin-audit-purge.php" onsubmit="return confirm('Delete audit log id #<?= (int)$r['id'] ?> permanently?');" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="mode" value="one">
                  <input type="hidden" name="confirm" value="DELETE">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px">No audit records.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div style="margin-top:12px;display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">
        <div class="muted">Bulk delete is enabled for selected records on this page.</div>
        <button class="btn btn-danger" type="submit" onclick="return confirm('Delete selected audit logs permanently?');">Delete selected</button>
      </div>
    </form>
  </div>

  <div style="margin-top:12px;display:flex;justify-content:center;gap:10px;align-items:center">
    <?php
      $base = $_GET;
      $cur = (int)($_GET['page'] ?? 1);
      $prev = max(1, $cur - 1);
      $next = min($pages, $cur + 1);
    ?>
    <?php if ($cur > 1): ?>
      <a class="btn btn-secondary" href="?<?= e(http_build_query(array_merge($base, ['page'=>$prev]))) ?>">Prev</a>
    <?php endif; ?>
    <span class="muted">Page <?= (int)$cur ?> / <?= (int)$pages ?></span>
    <?php if ($cur < $pages): ?>
      <a class="btn btn-secondary" href="?<?= e(http_build_query(array_merge($base, ['page'=>$next]))) ?>">Next</a>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


