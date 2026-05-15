<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';

$user = require_admin();
$pdo = db();

function json_safe_encode($v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

if (!verify_csrf($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

$mode = (string)($_POST['mode'] ?? '');
$confirm = (string)($_POST['confirm'] ?? '');

// Required confirmation token.
if ($confirm !== 'DELETE') {
    flash('msg', 'Deletion not confirmed.');
    header('Location: /admin-audit.php');
    exit;
}

try {
    if ($mode === 'older_than_days') {
        $days = (int)($_POST['days'] ?? 90);
        if ($days < 1) {
            throw new RuntimeException('Invalid retention days.');
        }
        $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('c');

        $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < ?');
        $stmt->execute([$cutoff]);
        $deleted = $stmt->rowCount();

        // Log the purge itself.
        audit_log($user, 'audit.purge_older_than_days', 'system', null, [
            'days' => $days,
            'cutoff' => $cutoff,
            'deleted' => $deleted,
        ]);

        flash('msg', 'Deleted ' . (string)$deleted . ' audit log(s older than ' . (string)$days . ' days.');
        header('Location: /admin-audit.php');
        exit;
    }

    if ($mode === 'one') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Invalid audit id.');

        $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE id = ?');
        $stmt->execute([$id]);
        $deleted = $stmt->rowCount();

        audit_log($user, 'audit.purge_one', 'audit_logs', (string)$id, [
            'deleted' => $deleted,
        ]);

        flash('msg', 'Deleted audit log id #' . (string)$id . (': ' . (string)$deleted . ' row(s).')); 
        header('Location: /admin-audit.php');
        exit;
    }

    if ($mode === 'bulk') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            throw new RuntimeException('Invalid ids.');
        }
        $ids = array_values(array_filter(array_map(static function($v) { return (int)$v; }, $ids), static function($v) { return $v > 0; }));
        if (!$ids) {
            throw new RuntimeException('No ids selected.');
        }

        // Build placeholders.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'DELETE FROM audit_logs WHERE id IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();

        audit_log($user, 'audit.purge_bulk', 'audit_logs', null, [
            'count_selected' => count($ids),
            'deleted' => $deleted,
            'ids' => array_slice($ids, 0, 50),
        ]);

        flash('msg', 'Deleted ' . (string)$deleted . ' selected audit log(s).');
        header('Location: /admin-audit.php');
        exit;
    }

    throw new RuntimeException('Unknown purge mode.');
} catch (Throwable $e) {
    flash('msg', 'Purge failed: ' . $e->getMessage());
    header('Location: /admin-audit.php');
    exit;
}

