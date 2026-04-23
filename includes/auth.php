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

function login(string $username, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['user_id'] = (int)$u['id'];
        return true;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function user_branch_filter(array $user): ?int {
    return in_array($user['role'], ['manager','officer'], true) ? (int)$user['branch_id'] : null;
}

function can_access_branch(array $user, int $branchId): bool {
    return $user['role'] === 'admin' || (int)$user['branch_id'] === $branchId;
}
