<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$error = null;
$info = null;

// If already logged in, go home.
if (current_user()) {
    header('Location: /');
    exit;
}

// (Optional hardening) logout stale pending 2FA session.
// If a previous attempt set pending but session variables are missing/expired, redirect to login.


// Require pending 2FA.
$pendingUserId = (int)($_SESSION['2fa_user_id'] ?? 0);
$pendingExpiresAt = $_SESSION['2fa_expires_at'] ?? null;
if ($pendingUserId <= 0 || !$pendingExpiresAt) {
    $error = 'Your verification session is missing or expired. Please log in again.';
}

if (!empty($error)) {
    header('Location: /login.php');
    exit;
}


// Optional: if expired in session, force logout.
$expiresTs = strtotime((string)$pendingExpiresAt);
if ($expiresTs === false || time() >= $expiresTs) {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid code.';
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        try {
            if (verify_login_2fa($pendingUserId, $code, $ip)) {
                // Finalize login
                $_SESSION['user_id'] = $pendingUserId;
                $_SESSION['last_activity'] = time();
                unset($_SESSION['2fa_user_id'], $_SESSION['2fa_expires_at'], $_SESSION['2fa_ip']);

                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                header('Location: /');
                exit;
            }

            $error = 'Invalid or expired code.';
        } catch (Throwable $e) {
            $error = 'Invalid or expired code.';
        }
    }
}

?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css">
<style>
  .otp-container {
    background: linear-gradient(135deg, var(--navy-50) 0%, #f0f6ff 100%);
    padding: 32px;
    border-radius: 14px;
    margin-bottom: 24px;
    border-left: 4px solid var(--gold);
  }
  
  .otp-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .otp-title::before {
    content: "✉️";
    font-size: 18px;
  }
  
  .otp-description {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.6;
    margin-bottom: 4px;
  }
  
  .otp-timer {
    font-size: 12px;
    color: var(--amber);
    font-weight: 600;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  
  .otp-timer::before {
    content: "⏱️";
  }
  
  .otp-input-wrapper {
    position: relative;
    margin: 24px 0;
  }
  
  .otp-input-wrapper label {
    display: block;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 10px;
    font-size: 14px;
  }
  
  .otp-input-group {
    display: flex;
    gap: 8px;
    justify-content: center;
  }
  
  .otp-digit {
    width: 48px;
    height: 56px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 24px;
    font-weight: 700;
    text-align: center;
    background: #fff;
    color: var(--primary);
    transition: all 0.2s ease;
    font-family: 'Monaco', 'Courier New', monospace;
  }
  
  .otp-digit:hover {
    border-color: var(--primary);
  }
  
  .otp-digit:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--navy-50);
    box-shadow: 0 0 0 3px rgba(58, 90, 143, 0.1);
  }
  
  .otp-digit.filled {
    border-color: var(--primary);
    background: var(--navy-50);
  }
  
  #otp-code-hidden {
    display: none;
  }
  
  .otp-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 28px;
  }
  
  .btn-verify {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--navy-700) 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  
  .btn-verify:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(58, 90, 143, 0.3);
  }
  
  .btn-verify:active {
    transform: translateY(0);
  }
  
  .btn-resend {
    background: transparent;
    color: var(--primary);
    border: 1px solid var(--border);
    padding: 10px;
    font-size: 13px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
  }
  
  .btn-resend:hover:not(:disabled) {
    background: var(--navy-50);
    border-color: var(--primary);
  }
  
  .btn-resend:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .otp-help {
    background: var(--amber-soft);
    border-left: 3px solid var(--amber);
    padding: 12px 14px;
    border-radius: 6px;
    font-size: 12px;
    color: #7c4300;
    line-height: 1.5;
    margin-top: 16px;
  }
  
  .otp-help::before {
    content: "💡 ";
  }
  
  .resend-info {
    font-size: 12px;
    color: var(--muted);
    text-align: center;
    margin-top: 12px;
  }
  
  .back-link {
    display: inline-block;
    margin-top: 16px;
    font-size: 13px;
    color: var(--primary);
    text-decoration: none;
    transition: all 0.2s ease;
  }
  
  .back-link:hover {
    text-decoration: underline;
  }
</style>
</head><body class="login-page">
<form class="login-card" method="post" action="/2fa.php" id="otp-form">
  <?= csrf_field() ?>
  
  <div class="brand-block">
    <img src="/assets/logo.jpg" alt="UPF">
    <div class="org"><?= e(APP_ORG) ?></div>
    <div class="sys"><?= e(APP_NAME) ?></div>
    <div class="motto"><?= e(APP_MOTTO) ?></div>
  </div>

  <!-- Error Alert -->
  <?php if ($error): ?>
    <div class="alert alert-error" id="otp-error">
      <strong>❌ Error:</strong> <?= e($error) ?>
    </div>
  <?php endif; ?>

  <!-- OTP Info Container -->
  <div class="otp-container">
    <div class="otp-title">Verify Your Identity</div>
    <div class="otp-description">
      A 6-digit verification code has been sent to your registered email address. 
      Enter it below to complete your login.
    </div>
    <div class="otp-timer">
      Expires in: <span id="otp-countdown">10:00</span>
    </div>
  </div>

  <!-- OTP Input -->
  <div class="otp-input-wrapper">
    <label for="otp-input-0">Enter Verification Code</label>
    <div class="otp-input-group" id="otp-inputs">
      <input type="text" class="otp-digit" id="otp-input-0" maxlength="1" inputmode="numeric" required>
      <input type="text" class="otp-digit" id="otp-input-1" maxlength="1" inputmode="numeric" required>
      <input type="text" class="otp-digit" id="otp-input-2" maxlength="1" inputmode="numeric" required>
      <input type="text" class="otp-digit" id="otp-input-3" maxlength="1" inputmode="numeric" required>
      <input type="text" class="otp-digit" id="otp-input-4" maxlength="1" inputmode="numeric" required>
      <input type="text" class="otp-digit" id="otp-input-5" maxlength="1" inputmode="numeric" required>
    </div>
    <!-- Hidden input to store complete code -->
    <input type="hidden" id="otp-code-hidden" name="code" value="">
  </div>

  <!-- Help Text -->
  <div class="otp-help">
    Each box accepts one digit. The code will auto-focus to the next field as you type.
  </div>

  <!-- Actions -->
  <div class="otp-actions">
    <button class="btn-verify" type="submit" id="verify-btn">Verify & Sign in</button>
    <button class="btn-resend" type="button" id="resend-btn" disabled>Resend Code (wait...)</button>
  </div>

  <div class="resend-info">
    Didn't receive the code? Check your spam folder or request a new one.
  </div>

  <a href="/login.php" class="back-link">← Back to Login</a>
</form>

<script>
(function() {
  // OTP Timer
  function initTimer() {
    const countdownEl = document.getElementById('otp-countdown');
    let remaining = 10 * 60; // 10 minutes in seconds
    
    function updateTimer() {
      remaining--;
      const mm = Math.floor(remaining / 60);
      const ss = remaining % 60;
      const mmStr = String(mm).padStart(2, '0');
      const ssStr = String(ss).padStart(2, '0');
      countdownEl.textContent = mmStr + ':' + ssStr;
      
      if (remaining > 0) {
        setTimeout(updateTimer, 1000);
      } else {
        countdownEl.textContent = '0:00';
        document.getElementById('verify-btn').disabled = true;
      }
    }
    updateTimer();
  }
  
  // OTP Input Handler
  function initOTPInput() {
    const inputs = document.querySelectorAll('.otp-digit');
    const codeHidden = document.getElementById('otp-code-hidden');
    const form = document.getElementById('otp-form');
    
    function updateHiddenCode() {
      const code = Array.from(inputs).map(el => el.value).join('');
      codeHidden.value = code;
    }
    
    function handleInput(e) {
      const input = e.target;
      const index = Array.from(inputs).indexOf(input);
      
      // Only allow numeric input
      input.value = input.value.replace(/[^0-9]/g, '');
      
      // Mark as filled
      if (input.value) {
        input.classList.add('filled');
      } else {
        input.classList.remove('filled');
      }
      
      // Move to next input
      if (input.value && index < inputs.length - 1) {
        inputs[index + 1].focus();
      }
      
      updateHiddenCode();
      
      // Auto-submit if all fields filled
      const code = Array.from(inputs).map(el => el.value).join('');
      if (code.length === 6) {
        setTimeout(() => {
          form.submit();
        }, 300);
      }
    }
    
    function handleKeyDown(e) {
      const input = e.target;
      const index = Array.from(inputs).indexOf(input);
      
      if (e.key === 'Backspace' && !input.value && index > 0) {
        inputs[index - 1].focus();
      } else if (e.key === 'ArrowLeft' && index > 0) {
        inputs[index - 1].focus();
      } else if (e.key === 'ArrowRight' && index < inputs.length - 1) {
        inputs[index + 1].focus();
      }
    }
    
    function handlePaste(e) {
      e.preventDefault();
      const paste = (e.clipboardData || window.clipboardData).getData('text');
      const digits = paste.replace(/[^0-9]/g, '').split('');
      
      digits.forEach((digit, idx) => {
        if (idx < inputs.length) {
          inputs[idx].value = digit;
          inputs[idx].classList.add('filled');
        }
      });
      
      updateHiddenCode();
      
      // Focus on next empty or last
      const nextEmpty = Array.from(inputs).findIndex(el => !el.value);
      if (nextEmpty >= 0) {
        inputs[nextEmpty].focus();
      } else if (digits.length === 6) {
        form.submit();
      }
    }
    
    inputs.forEach((input, idx) => {
      input.addEventListener('input', handleInput);
      input.addEventListener('keydown', handleKeyDown);
      input.addEventListener('paste', handlePaste);
    });
    
    // Auto-focus first input
    inputs[0].focus();
  }
  
  // Resend Code Handler
  function initResend() {
    const resendBtn = document.getElementById('resend-btn');
    let resendTimeout = 60; // 60 seconds before can resend
    
    function updateResendBtn() {
      if (resendTimeout > 0) {
        resendBtn.textContent = 'Resend Code (' + resendTimeout + 's)';
        resendBtn.disabled = true;
        resendTimeout--;
        setTimeout(updateResendBtn, 1000);
      } else {
        resendBtn.textContent = 'Resend Code';
        resendBtn.disabled = false;
      }
    }
    
    updateResendBtn();
    
    resendBtn.addEventListener('click', function(e) {
      e.preventDefault();
      // Redirect to login to request new code
      window.location.href = '/login.php';
    });
  }
  
  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    initTimer();
    initOTPInput();
    initResend();
  });
})();
</script>
</body></html>

