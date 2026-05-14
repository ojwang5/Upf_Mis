<?php
declare(strict_types=1);

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function status_label(string $s): string {
    switch ($s) {
        case 'present':
            return 'Present';
        case 'awol':
            return 'AWOL';
        case 'leave':
            return 'On Leave';
        case 'sick':
            return 'Sick';
        default:
            return ucfirst($s);
    }
}

function status_badge(string $s): string {
    switch ($s) {
        case 'present':
            $cls = 'badge badge-present';
            break;
        case 'awol':
            $cls = 'badge badge-awol';
            break;
        case 'leave':
            $cls = 'badge badge-leave';
            break;
        case 'onleave':
            $cls = 'badge badge-leave';
            break;
        case 'sick':
            $cls = 'badge badge-sick';
            break;
        default:
            $cls = 'badge';
    }
    return '<span class="' . $cls . '">' . e(status_label($s)) . '</span>';
}

function branch_summary(PDO $pdo, ?string $date = null, ?int $branchId = null): array {
    $date = $date ?: date('Y-m-d');
    $where = '';
    $params = [':d' => $date];
    if ($branchId !== null) {
        $where = 'AND b.id = :b';
        $params[':b'] = $branchId;
    }
    $sql = "
        SELECT b.id AS branch_id, b.name AS branch_name, b.code AS branch_code, b.location,
               COUNT(e.id) AS total,
               SUM(CASE WHEN ds.status='present' THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN ds.status='awol' THEN 1 ELSE 0 END) AS awol,
               SUM(CASE WHEN ds.status='leave' THEN 1 ELSE 0 END) AS on_leave,
               SUM(CASE WHEN ds.status='sick' THEN 1 ELSE 0 END) AS sick,
               SUM(CASE WHEN e.gender='M' THEN 1 ELSE 0 END) AS male,
               SUM(CASE WHEN e.gender='F' THEN 1 ELSE 0 END) AS female
        FROM branches b
        LEFT JOIN employees e ON e.branch_id = b.id AND e.active = 1
        LEFT JOIN daily_status ds ON ds.employee_id = e.id AND ds.date = :d
        WHERE 1=1 $where
        GROUP BY b.id
        ORDER BY b.name
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        foreach (['total','present','awol','on_leave','sick','male','female'] as $k) {
            $r[$k] = (int)$r[$k];
        }
        $r['unrecorded'] = $r['total'] - ($r['present'] + $r['awol'] + $r['on_leave'] + $r['sick']);
    }
    return $rows;
}

function flash(string $key, ?string $msg = null): ?string {
    if ($msg !== null) {
        $_SESSION['_flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['_flash'][$key] ?? null;
    if (isset($_SESSION['_flash'][$key])) unset($_SESSION['_flash'][$key]);
    return $val;
}

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    $t = csrf_token();
    return '<input type="hidden" name="_csrf" value="' . e($t) . '">';
}

function verify_csrf(?string $token): bool {
    if (empty($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], (string)$token);
}
