<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function audit_log(
    ?array $actor,
    string $action,
    ?string $target_type = null,
    ?string $target_id = null,
    array $meta = []
): void {
    try {
        $pdo = db();
        $actorId = $actor['id'] ?? null;
        $actorRole = $actor['role'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $createdAt = date('c');

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs
            (actor_user_id, actor_role, action, target_type, target_id, ip_address, user_agent, meta_json, created_at)
            VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $actorId !== null ? (int)$actorId : null,
            $actorRole,
            $action,
            $target_type,
            $target_id,
            $ip,
            $ua,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $createdAt,
        ]);
    } catch (Throwable $e) {
        // Best-effort logging: never break the main request.
    }
}

function audit_actor_from_session(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare(
        "SELECT id, role, full_name FROM users WHERE id = ?"
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

