<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT u.*, b.name AS branch_name, b.code AS branch_code FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: /login.php');
        exit;
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
    return $u;
}

function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
    return $u;
}

function is_admin(array $u): bool { return $u['role'] === 'admin'; }
function is_manager(array $u): bool { return $u['role'] === 'manager'; }
function is_officer(array $u): bool { return $u['role'] === 'officer'; }

function login_lock_remaining_seconds(string $username, string $ip): ?int {
    $username = trim($username);
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        failed_attempts INTEGER NOT NULL DEFAULT 0,
        first_attempt_at TEXT NOT NULL,
        locked_until TEXT
    )");

    $st = $pdo->prepare(
        "SELECT locked_until
         FROM login_attempts
         WHERE username = ? AND ip_address = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $st->execute([$username, $ip]);
    $a = $st->fetch();

    if (!$a || empty($a['locked_until'])) return null;

    $lockedUntil = strtotime((string)$a['locked_until']);
    if ($lockedUntil === false) return null;

    $remaining = $lockedUntil - time();
    return $remaining > 0 ? (int)$remaining : null;
}

function login(string $username, string $password): bool {
    // Legacy login: verify username/password and finalize the session immediately.
    // Used by any older flow that expects session to be active right after POST /login.php.
    $user = login_password_verify($username, $password);
    if (!$user) {
        return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['last_activity'] = time();

    return true;
}




function get_user_by_username(string $username): ?array {
    $username = trim($username);
    $stmt = db()->prepare("SELECT u.*, b.name AS branch_name, b.code AS branch_code FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    return $u ?: null;
}


function login_password_verify(string $username, string $password): ?array {
    $username = trim($username);

    // Rate limiting on failures (username + IP)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        failed_attempts INTEGER NOT NULL DEFAULT 0,
        first_attempt_at TEXT NOT NULL,
        locked_until TEXT
    )");

    $now = date('c');
    $lockMaxTries = 5; // 5 failures
    $windowSeconds = 15 * 60; // per 15 minutes
    $lockSeconds = 15 * 60; // lock for 15 minutes

    $st = $pdo->prepare("SELECT * FROM login_attempts WHERE username = ? AND ip_address = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$username, $ip]);
    $a = $st->fetch();

    $locked = false;
    if ($a && !empty($a['locked_until'])) {
        $lockedUntil = strtotime((string)$a['locked_until']);
        if ($lockedUntil !== false && time() < $lockedUntil) {
            $locked = true;
        }
    }

    if ($locked) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch();

    if ($u && password_verify($password, $u['password_hash'])) {
        // Successful password: reset attempts, DO NOT create a logged-in session yet (2FA pending)
        $pdo->prepare("DELETE FROM login_attempts WHERE username = ? AND ip_address = ?")->execute([$username, $ip]);
        return $u ?: null;
    }

    // Debug logging for diagnosis (never shown to user)
    try {
        require_once __DIR__ . '/audit.php';
        audit_log(null, 'auth.password_verify_failed', 'user', null, [
            'username' => $username,
            'has_user_row' => $u ? 1 : 0,
        ]);
    } catch (Throwable $e) {
        // ignore
    }


    // Failure: update attempts and potentially lock
    $failed = (int)($a['failed_attempts'] ?? 0);
    $firstAt = (string)($a['first_attempt_at'] ?? $now);
    $firstTs = strtotime($firstAt) ?: time();

    if (time() - $firstTs > $windowSeconds) {
        $failed = 0;
        $firstAt = $now;
    }

    $failed++;
    $lockedUntil = null;
    if ($failed >= $lockMaxTries) {
        $lockedUntil = date('c', time() + $lockSeconds);
    }

    $pdo->prepare(
        "INSERT INTO login_attempts (username, ip_address, failed_attempts, first_attempt_at, locked_until)
         VALUES (?,?,?,?,?)"
    )->execute([
        $username,
        $ip,
        $failed,
        $firstAt,
        $lockedUntil,
    ]);

    return null;
}


function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function _env_int(string $key, int $default = 0): int {
    $v = getenv($key);
    if ($v === false || $v === null || $v === '') return $default;
    if (!is_numeric($v)) return $default;
    return (int)$v;
}

function _env_str(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v === false || $v === null) return $default;
    return (string)$v;
}

function begin_login_2fa(array $user, string $ip): void {
    $code = (string)random_int(100000, 999999);
    $otpHash = hash('sha256', $code);

    $expiresAt = time() + 10 * 60; // 10 minutes

    // Persist OTP (one active OTP at a time per user is fine; we allow multiple but verify latest)
    $pdo = db();
    $pdo->prepare(
        "INSERT INTO login_otps (user_id, otp_hash, expires_at, created_at, attempts, last_attempt_at, ip_address)
         VALUES (?,?,?,?,0,NULL,?)"
    )->execute([
        (int)$user['id'],
        $otpHash,
        date('c', $expiresAt),
        date('c'),
        (string)$ip,
    ]);

    // Set pending 2FA in session
    $_SESSION['2fa_user_id'] = (int)$user['id'];
    $_SESSION['2fa_expires_at'] = date('c', $expiresAt);
    $_SESSION['2fa_ip'] = (string)$ip;

    // Send email OTP via SendGrid SMTP
    $toEmail = '';
    if (isset($user['email']) && is_string($user['email'])) {
        $toEmail = trim($user['email']);
    }

    // Fallback to admin email if user row doesn't contain email
    if ($toEmail === '') {
        $toEmail = getenv('DEFAULT_TO_EMAIL') ?: 'ojwangsamuel1@gmail.com';
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Invalid recipient email for 2FA: {$toEmail}");
    }

    send_login_2fa_code_email($toEmail, $code);
}

function verify_login_2fa(int $userId, string $code, string $ip): bool {
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) return false;

    $pdo = db();

    $otpHash = hash('sha256', $code);

    // fetch latest OTP for user, regardless of hash first
    $stmt = $pdo->prepare(
        "SELECT * FROM login_otps
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $otp = $stmt->fetch();

    if (!$otp) return false;

    $expiresTs = strtotime((string)$otp['expires_at']);
    if ($expiresTs === false || time() >= $expiresTs) return false;

    // Optional: bind to IP used during OTP issuance
    if (!empty($otp['ip_address']) && (string)$otp['ip_address'] !== (string)$ip) {
        return false;
    }

    $matched = hash_equals((string)$otp['otp_hash'], $otpHash);

    if ($matched) {
        // Mark success by deleting OTPs
        $pdo->prepare("DELETE FROM login_otps WHERE user_id = ?")->execute([$userId]);
        return true;
    }

    // increment attempts, throttle by attempts
    $attempts = (int)($otp['attempts'] ?? 0) + 1;
    $pdo->prepare(
        "UPDATE login_otps SET attempts = ?, last_attempt_at = ? WHERE id = ?"
    )->execute([
        $attempts,
        date('c'),
        (int)$otp['id'],
    ]);

    if ($attempts >= 5) {
        $pdo->prepare("DELETE FROM login_otps WHERE user_id = ?")->execute([$userId]);
    }

    return false;
}


function user_branch_filter(array $user): ?int {
    return in_array($user['role'], ['manager', 'officer'], true) ? (int)$user['branch_id'] : null;
}

function can_access_branch(array $user, int $branchId): bool {
    return $user['role'] === 'admin' || (int)$user['branch_id'] === $branchId;
}

