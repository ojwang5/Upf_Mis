<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
$user = require_login();
$page = 'notifications';
$page_title = 'Notifications';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        mark_all_read($user);
    } elseif ($action === 'mark_one' && !empty($_POST['id'])) {
        mark_notification_read((int)$_POST['id'], (int)$user['id']);
    } elseif ($action === 'delete_one' && !empty($_POST['id'])) {
        delete_notification_for_user((int)$_POST['id'], $user);
        flash('msg', 'Notification deleted.');
    } elseif ($action === 'broadcast' && is_admin($user)) {
        $title = trim($_POST['title'] ?? ''); $message = trim($_POST['message'] ?? '');
        $audience = $_POST['audience'] ?? 'all';
        if ($title && $message) {
            $opts = ['created_by' => $user['id']];
            if ($audience === 'branch') {
                notify($title, $message, 'branch', array_merge($opts, ['target_branch_id' => (int)$_POST['branch_id']]));
            } elseif ($audience === 'role') {
                notify($title, $message, 'role', array_merge($opts, ['target_role' => $_POST['role'] ?? 'manager']));
            } else {
                notify($title, $message, 'all', $opts);
            }
            flash('msg', 'Notification sent.');
        }
    }
    header('Location: /notifications.php'); exit;
}


$notifs = notifications_for($user, false, 100);
$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h1>Notifications</h1><div class="desc">Updates, approvals, and broadcasts</div></div>
  <form method="post"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-secondary">Mark all as read</button></form>
</div>

<?php if ($m = flash('msg')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>

<?php if (is_admin($user)): ?>
<div style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;justify-content:flex-end;margin-bottom:12px">
  <button id="toggle-broadcast-form" class="btn btn-secondary" type="button">Create Notification</button>
  <div class="muted" style="font-size:12px;">Show/Hide notification creation</div>
</div>

<div class="card" id="broadcast-form-wrapper" style="display:none">
  <h3>Send Broadcast</h3>
  <form method="post">
    <input type="hidden" name="action" value="broadcast">
    <div class="form-row">
      <div class="form-group" style="flex:1;min-width:200px"><label>Title</label><input type="text" name="title" required></div>
      <div class="form-group" style="flex:0"><label>Audience</label>
        <select name="audience" id="aud" onchange="document.getElementById('br').style.display=this.value==='branch'?'':'none';document.getElementById('rl').style.display=this.value==='role'?'':'none';">
          <option value="all">All users</option><option value="branch">A branch</option><option value="role">A role</option>
        </select>
      </div>
      <div class="form-group" id="br" style="flex:0;display:none"><label>Branch</label>
        <select name="branch_id"><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="form-group" id="rl" style="flex:0;display:none"><label>Role</label>
        <select name="role"><option value="manager">Managers</option><option value="officer">Officers</option><option value="admin">Admins</option></select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1"><label>Message</label><textarea name="message" rows="3" required></textarea></div>
      <div class="form-group" style="flex:0"><label>&nbsp;</label><button class="btn btn-gold">Send</button></div>
    </div>
  </form>
</div>

<script>
(function(){
  const btn = document.getElementById('toggle-broadcast-form');
  const wrap = document.getElementById('broadcast-form-wrapper');
  if(!btn || !wrap) return;

  function syncLabel(){
    const isOpen = wrap.style.display !== 'none';
    btn.textContent = isOpen ? 'Hide Notification' : 'Create Notification';
  }

  btn.addEventListener('click', function(){
    wrap.style.display = (wrap.style.display === 'none' || !wrap.style.display) ? 'block' : 'none';
    syncLabel();
  });

  syncLabel();
})();
</script>
<?php endif; ?>

<div class="card">
  <h3>Inbox</h3>
  <?php if (!$notifs): ?><div class="muted" style="padding:18px 0;text-align:center">No notifications yet.</div><?php endif; ?>
  <?php foreach ($notifs as $n): $unread = empty($n['read_at_user']); ?>
    <div class="notif <?= $unread ? 'unread' : '' ?>">
      <div class="notif-dot"></div>
      <div class="notif-body">
        <div class="notif-title">
          <?= e($n['title']) ?>
          <?php if ($unread): ?><span class="badge badge-leave" style="margin-left:8px">NEW</span><?php endif; ?>
        </div>
        <div class="notif-msg"><?= e($n['message']) ?></div>
        <div class="notif-meta">
          <?= e(date('M j, Y · H:i', strtotime($n['created_at']))) ?>
          <?= $n['sender'] ? ' · from ' . e($n['sender']) : '' ?>
          · audience: <?= e($n['audience']) ?>
          <?php if ($n['link']): ?> · <a href="<?= e($n['link']) ?>">Open</a><?php endif; ?>
<?php if ($unread): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="mark_one"><input type="hidden" name="id" value="<?= $n['id'] ?>">
              <button class="link-btn" type="submit">mark read</button>
            </form>
          <?php endif; ?>

          <form method="post" style="display:inline" onsubmit="return confirm('Delete this notification?');">
            <input type="hidden" name="action" value="delete_one"><input type="hidden" name="id" value="<?= $n['id'] ?>">
            <button class="link-btn link-btn-danger" type="submit">delete</button>
          </form>
        </div>


      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
