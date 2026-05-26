<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/audit.php';

$user = require_admin();
$pdo = db();

if (session_status() === PHP_SESSION_ACTIVE) {
    // keep existing session; nothing else required
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

// Strongly require explicit confirmation token.
$confirm = (string)($_POST['confirm'] ?? '');
$expected = 'SEND_OTP_TO_ALL_USERS';
if ($confirm !== $expected) {
    http_response_code(400);
    echo 'Confirmation failed.';
    exit;
}

$dryRun = ((string)($_POST['dry_run'] ?? '0') === '1');

$results = [
    'dry_run' => $dryRun,
    'attempted' => 0,
    'sent' => 0,
    'skipped_no_email' => 0,
    'failed' => 0,
    'failures' => [],
];

// Use a stable IP value; OTP verification binds to OTP issuance IP.
// Since this is a system-triggered send, we store the server REMOTE_ADDR if available; otherwise "unknown".
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

try {
    // Fetch all users that have an email.
    // Some older DBs may not have users.email; handle gracefully.
    $hasEmailCol = false;
    foreach ($pdo->query("PRAGMA table_info(users)") as $c) {
        if (($c['name'] ?? null) === 'email') {
            $hasEmailCol = true;
            break;
        }
    }

    if (!$hasEmailCol) {
        throw new RuntimeException('users.email column not found in database. Update users via the UI first.');
    }

    $stmt = $pdo->query("SELECT id, username, email, full_name, role, branch_id FROM users ORDER BY role, full_name");
    $allUsers = $stmt->fetchAll();

    $results['attempted'] = count($allUsers);

    foreach ($allUsers as $u) {
        $toEmail = isset($u['email']) ? trim((string)$u['email']) : '';
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $results['skipped_no_email']++;
            continue;
        }

        if ($dryRun) {
            $results['sent']++;
            continue;
        }

        try {
            // This generates a fresh OTP, stores it in login_otps, and emails it.
            begin_login_2fa($u, $ip);

            audit_log($user, 'auth.send_2fa_otp_bulk', 'user', (string)((int)$u['id']), [
                'target_username' => $u['username'] ?? null,
                'target_email' => $toEmail,
            ]);

            $results['sent']++;
        } catch (Throwable $e) {
            $results['failed']++;
            $results['failures'][] = [
                'user_id' => (int)$u['id'],
                'username' => $u['username'] ?? null,
                'email' => $toEmail,
                'error' => $e->getMessage(),
            ];

            audit_log($user, 'auth.send_2fa_otp_bulk_failed', 'user', (string)((int)$u['id']), [
                'target_username' => $u['username'] ?? null,
                'target_email' => $toEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    flash('msg', 'OTP send completed. Sent: ' . (string)$results['sent'] . ', Skipped(no email): ' . (string)$results['skipped_no_email'] . ', Failed: ' . (string)$results['failed'] . '.');
    if (!empty($results['failures'])) {
        // Avoid dumping too much in flash. Store first few.
        flash('err', 'Some OTP sends failed (showing first ' . min(5, count($results['failures'])) . '). Open logs/audit for details.');
    }

} catch (Throwable $e) {
    flash('err', 'OTP send failed: ' . $e->getMessage());
    audit_log($user, 'auth.send_2fa_otp_bulk_failed_fatal', 'system', null, [
        'error' => $e->getMessage(),
    ]);
}

header('Location: /users.php');
exit;

