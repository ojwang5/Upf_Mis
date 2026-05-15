<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        require_once __DIR__ . '/../includes/audit.php';
        $actor = audit_actor_from_session();
        audit_log($actor, 'auth.login', 'user', $actor ? (string)$actor['id'] : null, ['username' => $username]);
        header('Location: /');
        exit;
    }
    $error = 'Invalid username or password.';
}
if (current_user()) { header('Location: /'); exit; }
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head><body class="login-page">
<form class="login-card" method="post" action="/login.php">
  <div class="brand-block">
    <img src="/assets/logo.jpg" alt="UPF">
    <div class="org"><?= e(APP_ORG) ?></div>
    <div class="sys"><?= e(APP_NAME) ?></div>
    <div class="motto"><?= e(APP_MOTTO) ?></div>
  </div>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <div class="form-group" style="margin-bottom:12px">
    <label>Username</label>
    <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
  </div>
  <div class="form-group" style="margin-bottom:18px">
    <label>Password</label>
    <input type="password" name="password" required>
  </div>
  <button class="btn" style="width:100%" type="submit">Sign in</button>
  <div class="muted" style="margin-top:14px;font-size:11px;text-align:center">
    Failed to login contact system administrators
  </div>
</form>
</body></html>
