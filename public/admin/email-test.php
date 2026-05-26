<?php
/**
 * Email Configuration Diagnostic Page (for admin use only)
 * Tests SendGrid SMTP configuration and connectivity
 * 
 * Access: /admin/email-test.php (requires admin login)
 * DELETE THIS FILE after testing in production
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/email.php';

// Require admin login
$user = require_admin();

$test_results = null;
$test_email_sent = null;
$test_email_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_config') {
        $test_results = test_email_configuration();
    } elseif ($action === 'send_test_email') {
        $test_email = trim($_POST['test_email'] ?? '');
        if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            try {
                $test_body = "Hello,\n\n";
                $test_body .= "This is a test email from the MDD Management System.\n\n";
                $test_body .= "If you received this email, your SendGrid SMTP configuration is working correctly!\n\n";
                $test_body .= "Sent at: " . date('Y-m-d H:i:s') . "\n";
                $test_body .= "Server: " . ($_SERVER['SERVER_NAME'] ?? 'unknown') . "\n\n";
                $test_body .= "---\n";
                $test_body .= "MDD Management System\n";
                
                send_smtp_email(
                    to_email: $test_email,
                    subject: 'Test Email from MDD Management System',
                    body: $test_body,
                    config: get_email_config()
                );
                $test_email_sent = true;
            } catch (Throwable $e) {
                $test_email_error = $e->getMessage();
            }
        } else {
            $test_email_error = 'Invalid email address';
        }
    }
}

?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Email Configuration Test — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css">
<style>
.test-card {
    max-width: 600px;
    margin: 40px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.config-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}
.config-table td {
    padding: 8px;
    border-bottom: 1px solid #eee;
}
.config-table .key {
    font-weight: bold;
    width: 200px;
}
.status-ok { color: green; font-weight: bold; }
.status-error { color: red; font-weight: bold; }
.alert-success { background: #d4edda; color: #155724; padding: 12px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 15px 0; }
.alert-error { background: #f8d7da; color: #721c24; padding: 12px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 15px 0; }
.form-group { margin: 15px 0; }
.form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
.form-group input { padding: 8px; width: 100%; box-sizing: border-box; }
button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #0056b3; }
</style>
</head><body style="padding: 20px;">

<div class="test-card">
    <h1>Email Configuration Diagnostic</h1>
    <p><em>Logged in as: <?= e($user['full_name'] ?? $user['username']) ?> (Admin)</em></p>

    <h2>1. Configuration Status</h2>
    <form method="post">
        <input type="hidden" name="action" value="test_config">
        <button type="submit">Test Configuration</button>
    </form>

    <?php if ($test_results): ?>
        <table class="config-table">
            <tr>
                <td class="key">Overall Status:</td>
                <td class="<?= $test_results['success'] ? 'status-ok' : 'status-error' ?>">
                    <?= $test_results['success'] ? '✓ OK' : '✗ FAILED' ?>
                </td>
            </tr>
            <tr>
                <td class="key">Email Host:</td>
                <td><?= e($test_results['config']['host']) ?></td>
            </tr>
            <tr>
                <td class="key">Email Port:</td>
                <td><?= e((string)$test_results['config']['port']) ?></td>
            </tr>
            <tr>
                <td class="key">Username:</td>
                <td><?= e($test_results['config']['username']) ?></td>
            </tr>
            <tr>
                <td class="key">From Email:</td>
                <td><?= e($test_results['config']['from_email']) ?></td>
            </tr>
            <tr>
                <td class="key">Password Set:</td>
                <td class="<?= $test_results['config']['password_set'] ? 'status-ok' : 'status-error' ?>">
                    <?= $test_results['config']['password_set'] ? '✓ Yes' : '✗ No' ?>
                </td>
            </tr>
        </table>

        <?php if (!empty($test_results['messages'])): ?>
            <?php foreach ($test_results['messages'] as $msg): ?>
                <div class="<?= str_contains($msg, 'not') || str_contains($msg, 'cannot') ? 'alert-error' : 'alert-success' ?>">
                    <?= e($msg) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <hr style="margin: 30px 0;">

    <h2>2. Send Test Email</h2>
    <form method="post">
        <input type="hidden" name="action" value="send_test_email">
        <div class="form-group">
            <label for="test_email">Email Address:</label>
            <input type="email" id="test_email" name="test_email" required placeholder="recipient@example.com">
        </div>
        <button type="submit">Send Test Email</button>
    </form>

    <?php if ($test_email_sent): ?>
        <div class="alert-success">
            ✓ Test email sent successfully!
        </div>
    <?php endif; ?>

    <?php if ($test_email_error): ?>
        <div class="alert-error">
            ✗ Error: <?= e($test_email_error) ?>
        </div>
    <?php endif; ?>

    <hr style="margin: 30px 0;">

    <h2>3. Environment Variables</h2>
    <table class="config-table">
        <tr>
            <td class="key">EMAIL_HOST:</td>
            <td><code><?= e(getenv('EMAIL_HOST') ?: 'NOT SET') ?></code></td>
        </tr>
        <tr>
            <td class="key">EMAIL_PORT:</td>
            <td><code><?= e(getenv('EMAIL_PORT') ?: 'NOT SET') ?></code></td>
        </tr>
        <tr>
            <td class="key">EMAIL_HOST_USER:</td>
            <td><code><?= e(getenv('EMAIL_HOST_USER') ?: 'NOT SET') ?></code></td>
        </tr>
        <tr>
            <td class="key">EMAIL_HOST_PASSWORD:</td>
            <td>
                <code>
                    <?php
                        $pwd = getenv('EMAIL_HOST_PASSWORD');
                        if ($pwd) {
                            $len = strlen($pwd);
                            echo substr($pwd, 0, 8) . '...' . substr($pwd, -4) . ' (' . $len . ' chars)';
                        } else {
                            echo 'NOT SET';
                        }
                    ?>
                </code>
            </td>
        </tr>
        <tr>
            <td class="key">DEFAULT_FROM_EMAIL:</td>
            <td><code><?= e(getenv('DEFAULT_FROM_EMAIL') ?: 'NOT SET') ?></code></td>
        </tr>
    </table>

    <hr style="margin: 30px 0;">

    <h2>⚠️ Security Notice</h2>
    <p>This diagnostic page is only meant for testing and setup verification.</p>
    <p><strong>DELETE THIS FILE</strong> after confirming email is working in production.</p>
    <p>File location: <code>public/admin/email-test.php</code></p>

    <p><a href="/">← Back to Dashboard</a></p>
</div>

</body></html>
