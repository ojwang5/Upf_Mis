<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Create a new branch.
 * Returns the new branch id on success.
 * Throws an Exception on failure (including uniqueness violation).
 */
function create_branch(?PDO $pdo = null, string $name, string $code, string $location): int {
    $pdo = $pdo ?? db();
    // pre-check uniqueness
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM branches WHERE name = ? OR code = ?');
    $stmt->execute([$name, $code]);
    if ((int)$stmt->fetchColumn() > 0) {
        throw new Exception('Branch name or code already exists.');
    }

    $sql = "INSERT INTO branches (name, code, location) VALUES (?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $code, $location]);
    return (int)$pdo->lastInsertId();
}

function get_branch(?PDO $pdo = null, int $id): ?array {
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function find_branch_by_code(?PDO $pdo = null, string $code): ?array {
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE code = ?');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function list_branches(?PDO $pdo = null): array {
    $pdo = $pdo ?? db();
    // include employee counts for each branch to help UI decisions
    $sql = "SELECT b.*, COUNT(e.id) AS employee_count FROM branches b LEFT JOIN employees e ON e.branch_id = b.id GROUP BY b.id ORDER BY b.name ASC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) { $r['employee_count'] = (int)($r['employee_count'] ?? 0); }
    return $rows;
}

function update_branch(?PDO $pdo = null, int $id, string $name, string $code, string $location): bool {
    $pdo = $pdo ?? db();
    // pre-check uniqueness excluding current id
    $chk = $pdo->prepare('SELECT COUNT(*) FROM branches WHERE (name = ? OR code = ?) AND id != ?');
    $chk->execute([$name, $code, $id]);
    if ((int)$chk->fetchColumn() > 0) {
        throw new Exception('Branch name or code already exists.');
    }

    $stmt = $pdo->prepare('UPDATE branches SET name = ?, code = ?, location = ? WHERE id = ?');
    return $stmt->execute([$name, $code, $location, $id]);
}

function delete_branch(?PDO $pdo = null, int $id): bool {
    $pdo = $pdo ?? db();
    // Prevent deletion if there are related records in other tables
    $checks = [
        ['table' => 'employees', 'col' => 'branch_id', 'msg' => 'employees'],
        ['table' => 'reports', 'col' => 'branch_id', 'msg' => 'reports'],
        ['table' => 'leave_requests', 'col' => 'branch_id', 'msg' => 'leave requests'],
        ['table' => 'notifications', 'col' => 'target_branch_id', 'msg' => 'notifications'],
    ];
    foreach ($checks as $c) {
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $c['table'], $c['col']);
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $cnt = (int)$stmt->fetchColumn();
        if ($cnt > 0) {
            throw new Exception(sprintf('Cannot delete branch because it has related %s.', $c['msg']));
        }
    }

    $stmt = $pdo->prepare('DELETE FROM branches WHERE id = ?');
    return $stmt->execute([$id]);
}
