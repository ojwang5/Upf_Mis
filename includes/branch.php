<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Create a new branch.
 * Returns the new branch id on success.
 * Throws an Exception on failure (including uniqueness violation).
 */
function create_branch(string $name, string $code, string $location, ?PDO $pdo = null): int {
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

function get_branch(int $id, ?PDO $pdo = null): ?array {
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function find_branch_by_code(string $code, ?PDO $pdo = null): ?array {
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

function update_branch(int $id, string $name, string $code, string $location, ?PDO $pdo = null): bool {
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

function delete_branch(int $id, string $mode = 'restrict', ?PDO $pdo = null): bool {
    $pdo = $pdo ?? db();

    // Allow deletion by performing a cascade delete when there are related rows.
    // This removes the hard restriction / error: "Cannot delete branch because it has related users.".
    if ($mode === 'restrict') {
        $mode = 'cascade';
    }

    if ($mode !== 'cascade') {
        throw new InvalidArgumentException('Invalid delete mode.');
    }

    // Cascade delete dependent records (data loss risk)
    $pdo->beginTransaction();
    try {
        // Delete rows referencing employees in this branch (employee_id based FKs may cascade further)
        $stmt = $pdo->prepare('SELECT id FROM employees WHERE branch_id = ?');
        $stmt->execute([$id]);
        $employeeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $employeeIds = array_map('intval', $employeeIds);

        if (!empty($employeeIds)) {
            $in = implode(',', array_fill(0, count($employeeIds), '?'));

            // These tables have employee_id FK ON DELETE CASCADE, but we still delete explicitly
            $pdo->prepare("DELETE FROM notification_reads WHERE notification_id IN (SELECT n.id FROM notifications n WHERE n.target_branch_id = ?)")
                ->execute([$id]);

            $pdo->prepare("DELETE FROM leave_requests WHERE employee_id IN ($in)")->execute($employeeIds);
            $pdo->prepare("DELETE FROM officer_suspensions WHERE employee_id IN ($in)")->execute($employeeIds);
            $pdo->prepare("DELETE FROM officer_disciplinary WHERE employee_id IN ($in)")->execute($employeeIds);
            $pdo->prepare("DELETE FROM daily_status WHERE employee_id IN ($in)")->execute($employeeIds);

            // Now delete employees (may also cascade other employee_id dependents)
            $pdo->prepare("DELETE FROM employees WHERE id IN ($in)")->execute($employeeIds);
        }

        // Delete remaining branch-scoped dependents
        $pdo->prepare('DELETE FROM reports WHERE branch_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE branch_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM notifications WHERE target_branch_id = ?')->execute([$id]);

        // Safety cleanup for any leftover rows that still reference branch_id
        $pdo->prepare('DELETE FROM officer_suspensions WHERE branch_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM officer_disciplinary WHERE branch_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM leave_requests WHERE branch_id = ?')->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM branches WHERE id = ?');
        $ok = $stmt->execute([$id]);

        $pdo->commit();
        return $ok;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

