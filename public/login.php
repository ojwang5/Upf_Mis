<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection (required)
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid username or password.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        $user = null;
        try {
            $user = login_password_verify($username, $password);
        } catch (Throwable $e) {
            $user = null;
        }

        if ($user) {
            // Start 2FA and redirect to OTP screen.
            try {
                begin_login_2fa($user, $ip);
                require_once __DIR__ . '/../includes/audit.php';
                audit_log(audit_actor_from_session(), 'auth.login_password_ok', 'user', (int)($user['id'] ?? 0), ['username' => $username]);
                header('Location: /2fa.php');
                exit;
            } catch (Throwable $e) {
                // If OTP email cannot be sent, do not keep user blocked.
                $error = 'Could not send the verification code. Please try again.';
                try {
                    require_once __DIR__ . '/../includes/audit.php';
                    audit_log(null, 'auth.login_2fa_send_failed', 'user', null, [
                        'username' => $username,
                        'error' => $e->getMessage(),
                    ]);
                } catch (Throwable $e2) {
                    // ignore
                }
            }

        }

        // Best-effort audit on failed login
        try {
            require_once __DIR__ . '/../includes/audit.php';
            audit_log(null, 'auth.login_failed', 'user', null, ['username' => $username, 'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
        } catch (Throwable $e) {
            // never break login flow
        }

        $error = 'Invalid username or password.';

        // If rate-limited, show lockout countdown (username + current IP)
        try {
            $usernameForLimit = trim($username);
            $remaining = login_lock_remaining_seconds($usernameForLimit, $ip);
            if ($remaining !== null) {
                // Match UI: countdown shows MM:SS
                $error = 'Too many failed attempts. Try again in ';
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (current_user()) { header('Location: /'); exit; }

?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head><body class="login-page">
<form class="login-card" method="post" action="/login.php">
  <?= csrf_field() ?>
  <div class="brand-block">

    <img src="/assets/logo.jpg" alt="UPF">
    <div class="org"><?= e(APP_ORG) ?></div>
    <div class="sys"><?= e(APP_NAME) ?></div>
    <div class="motto"><?= e(APP_MOTTO) ?></div>
  </div>
  <?php if ($error): ?>
    <div class="alert alert-error" id="login-error">
      <?= e($error) ?>
      <?php if (isset($remaining) && is_int($remaining) && $remaining > 0): ?>
          <span><strong><span id="lock-countdown" data-remaining="<?= (int)$remaining ?>"></span></strong></span>
      <?php endif; ?>

    </div>
  <?php endif; ?>

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
    Failed to login contact system administrators  +256781068086
  </div>
</form>
<script>
(function(){
  const el = document.getElementById('lock-countdown');
  if (!el) return;
  const end = parseInt(el.getAttribute('data-remaining') || '0', 10);
  if (!end || end <= 0) return;

  function pad(n){ return String(n).padStart(2,'0'); }

  let remaining = end;
  const tick = () => {
    remaining -= 1;
    if (remaining < 0) remaining = 0;
    const mm = Math.floor(remaining / 60);
    const ss = remaining % 60;
    el.textContent = pad(mm) + ':' + pad(ss);
    if (remaining === 0) return;
    setTimeout(tick, 1000);
  };

  // set immediately, then tick
  const mm0 = Math.floor(remaining / 60);
  const ss0 = remaining % 60;
  el.textContent = pad(mm0) + ':' + pad(ss0);
  setTimeout(tick, 1000);
})();
</script>
</body></html>

