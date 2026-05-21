<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

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
        return false;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch();

    if ($u && password_verify($password, $u['password_hash'])) {
        // Successful login: reset attempts + rotate session
        $pdo->prepare("DELETE FROM login_attempts WHERE username = ? AND ip_address = ?")->execute([$username, $ip]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['last_activity'] = time();

        return true;
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

    return false;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function user_branch_filter(array $user): ?int {
    return in_array($user['role'], ['manager', 'officer'], true) ? (int)$user['branch_id'] : null;
}

function can_access_branch(array $user, int $branchId): bool {
    return $user['role'] === 'admin' || (int)$user['branch_id'] === $branchId;
}

