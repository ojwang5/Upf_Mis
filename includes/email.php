<?php
declare(strict_types=1);

/**
 * Email sending utilities using SendGrid SMTP
 * Handles 2FA code delivery and other email operations
 */

function get_email_config(): array {
    return [
        'host' => getenv('EMAIL_HOST') ?: 'smtp.sendgrid.net',
        'port' => (int)(getenv('EMAIL_PORT') ?: 587),
        'username' => getenv('EMAIL_HOST_USER') ?: 'apikey',
        'password' => getenv('EMAIL_HOST_PASSWORD') ?: '',
        'from_email' => getenv('DEFAULT_FROM_EMAIL') ?: '',
    ];
}

/**
 * Send 2FA code via email using SendGrid SMTP
 * 
 * @param string $toEmail Recipient email address
 * @param string $code 6-digit OTP code
 * @throws RuntimeException if email sending fails
 */
function send_login_2fa_code_email(string $toEmail, string $code): void {
    $config = get_email_config();
    
    if (empty($config['host']) || empty($config['username']) || 
        empty($config['password']) || empty($config['from_email'])) {
        throw new RuntimeException('Email configuration incomplete. Check EMAIL_HOST, EMAIL_HOST_USER, EMAIL_HOST_PASSWORD, and DEFAULT_FROM_EMAIL environment variables.');
    }

    $subject = 'Your MDD Management System Login Verification Code';
    $body = <<<BODY
Hello,

Your login verification code is: {$code}

This code expires in 10 minutes. Please do not share this code with anyone.

If you did not request this code, please ignore this email.

---
MDD Management System
Uganda Police Force
BODY;

    send_smtp_email(
        to_email: $toEmail,
        subject: $subject,
        body: $body,
        config: $config
    );
}

/**
 * Generic SMTP email sender using SendGrid
 * Implements SMTP protocol with TLS over sockets (no external dependencies)
 * 
 * @param string $to_email Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (plain text)
 * @param array $config Email configuration array
 * @throws RuntimeException if SMTP operations fail
 */
function send_smtp_email(
    string $to_email,
    string $subject,
    string $body,
    array $config
): void {
    $host = $config['host'] ?? 'smtp.sendgrid.net';
    $port = $config['port'] ?? 587;
    $username = $config['username'] ?? 'apikey';
    $password = $config['password'] ?? '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';
    $from_email = $config['from_email'] ?? 'ojwangsamuel1@gmail.com';

    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Invalid recipient email: {$to_email}");
    }

    if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Invalid from email: {$from_email}");
    }

    // Connect to SMTP server with error handling
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 15);
    
    if (!$fp) {
        throw new RuntimeException("SMTP connection failed to {$host}:{$port} - {$errstr} (errno: {$errno})");
    }

    try {
        stream_set_timeout($fp, 15);

        // Helper functions for SMTP communication
        $read_response = function() use ($fp): string {
            $data = '';
            $line_count = 0;
            while (!feof($fp) && $line_count < 50) {
                $line = fgets($fp, 512);
                if ($line === false) break;
                $data .= $line;
                $line_count++;
                // SMTP response ends with "XYZ " where XYZ is the code
                if (preg_match('/^\d{3} /', $line)) break;
            }
            return $data;
        };

        $send_command = function(string $cmd) use ($fp): void {
            fwrite($fp, $cmd . "\r\n");
        };

        // Read SMTP banner
        $banner = $read_response();
        if (!preg_match('/^220 /', $banner)) {
            throw new RuntimeException("Invalid SMTP banner: {$banner}");
        }

        // Send EHLO
        $send_command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $read_response();

        // Initiate TLS
        $send_command('STARTTLS');
        $starttls_response = $read_response();
        
        if (!preg_match('/^220 /', $starttls_response)) {
            throw new RuntimeException('SMTP STARTTLS not supported or failed');
        }

        // Enable TLS encryption
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Failed to enable TLS encryption');
        }

        // Send EHLO again after TLS
        $send_command('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $read_response();

        // Authenticate using AUTH LOGIN
        $send_command('AUTH LOGIN');
        $auth_response = $read_response();

        // Send base64-encoded username (apikey for SendGrid)
        $send_command(base64_encode($username));
        $read_response();

        // Send base64-encoded password (SendGrid API key)
        $send_command(base64_encode($password));
        $auth_final = $read_response();

        if (!preg_match('/^2\d\d /', $auth_final)) {
            throw new RuntimeException('SMTP authentication failed. Check EMAIL_HOST_USER and EMAIL_HOST_PASSWORD.');
        }

        // Send email
        $send_command("MAIL FROM:<{$from_email}>");
        $read_response();

        $send_command("RCPT TO:<{$to_email}>");
        $read_response();

        $send_command('DATA');
        $read_response();

        // Compose email headers and body
        $headers = [
            'From: ' . $from_email,
            'To: ' . $to_email,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: MDD Management System',
        ];

        $email_message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n";

        // Send message (dot on its own line ends message)
        fwrite($fp, $email_message . "\r\n.\r\n");
        $send_result = $read_response();

        if (!preg_match('/^2\d\d /', $send_result)) {
            throw new RuntimeException("Email sending failed: {$send_result}");
        }

        // Quit SMTP session
        $send_command('QUIT');
        $read_response();

    } finally {
        fclose($fp);
    }
}

/**
 * Test email configuration without sending an actual email
 * Returns detailed diagnostic information
 * 
 * @return array Diagnostic results
 */
function test_email_configuration(): array {
    $results = [
        'success' => true,
        'messages' => [],
        'config' => [
            'host' => getenv('EMAIL_HOST') ?: 'NOT SET',
            'port' => getenv('EMAIL_PORT') ?: 'NOT SET',
            'username' => getenv('EMAIL_HOST_USER') ?: 'NOT SET',
            'from_email' => getenv('DEFAULT_FROM_EMAIL') ?: 'NOT SET',
            'password_set' => !empty(getenv('EMAIL_HOST_PASSWORD')),
        ],
    ];

    $config = get_email_config();

    // Validate configuration
    if (empty($config['host'])) {
        $results['success'] = false;
        $results['messages'][] = 'EMAIL_HOST not configured';
    }

    if (empty($config['username'])) {
        $results['success'] = false;
        $results['messages'][] = 'EMAIL_HOST_USER not configured';
    }

    if (empty($config['password'])) {
        $results['success'] = false;
        $results['messages'][] = 'EMAIL_HOST_PASSWORD not configured';
    }

    if (empty($config['from_email'])) {
        $results['success'] = false;
        $results['messages'][] = 'DEFAULT_FROM_EMAIL not configured';
    }

    // Try to connect to SMTP
    if ($results['success']) {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($config['host'], $config['port'], $errno, $errstr, 5);
        
        if ($fp) {
            $results['messages'][] = 'Successfully connected to SMTP server';
            fclose($fp);
        } else {
            $results['success'] = false;
            $results['messages'][] = "Cannot connect to SMTP server: {$errstr}";
        }
    }

    return $results;
}
