<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/**
 * Create a notification.
 * @param string $audience 'user'|'role'|'branch'|'all'
 */
function notify(string $title, string $message, string $audience, array $opts = []): int {
    $stmt = db()->prepare("INSERT INTO notifications
        (title, message, link, kind, audience, target_user_id, target_role, target_branch_id, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $title, $message,
        $opts['link'] ?? null,
        $opts['kind'] ?? 'info',
        $audience,
        $opts['target_user_id'] ?? null,
        $opts['target_role'] ?? null,
        $opts['target_branch_id'] ?? null,
        $opts['created_by'] ?? null,
        date('c'),
    ]);
    return (int)db()->lastInsertId();
}

function notifications_for(array $user, bool $unreadOnly = false, int $limit = 50): array {
    $sql = "SELECT n.*, u.full_name AS sender,
                   r.read_at AS read_at_user
            FROM notifications n
            LEFT JOIN users u ON u.id = n.created_by
            LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = :uid
            WHERE (
                n.audience = 'all'
                OR (n.audience = 'user' AND n.target_user_id = :uid)
                OR (n.audience = 'role' AND n.target_role = :role)
                OR (n.audience = 'branch' AND n.target_branch_id = :bid)
            )";
    if ($unreadOnly) $sql .= " AND r.read_at IS NULL";
    $sql .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit;
    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':uid'  => (int)$user['id'],
        ':role' => $user['role'],
        ':bid'  => $user['branch_id'] !== null ? (int)$user['branch_id'] : -1,
    ]);
    return $stmt->fetchAll();
}

function unread_notification_count(array $user): int {
    $rows = notifications_for($user, true, 100);
    return count($rows);
}

function mark_notification_read(int $notificationId, int $userId): void {
    $stmt = db()->prepare("INSERT OR IGNORE INTO notification_reads (notification_id, user_id, read_at) VALUES (?,?,?)");
    $stmt->execute([$notificationId, $userId, date('c')]);
}

function mark_all_read(array $user): void {
    foreach (notifications_for($user, true, 500) as $n) {
        mark_notification_read((int)$n['id'], (int)$user['id']);
    }
}

/** Notify all managers of a branch (and admins) */
function notify_branch_managers(int $branchId, string $title, string $msg, array $opts = []): void {
    notify($title, $msg, 'branch', array_merge($opts, ['target_branch_id' => $branchId]));
    notify($title, $msg, 'role', array_merge($opts, ['target_role' => 'admin']));
}

/** Notify all admins */
function notify_admins(string $title, string $msg, array $opts = []): void {
    notify($title, $msg, 'role', array_merge($opts, ['target_role' => 'admin']));
}
