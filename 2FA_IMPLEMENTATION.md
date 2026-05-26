# 2FA (Two-Factor Authentication) Implementation

## Overview

This system implements email-based Two-Factor Authentication (2FA) for login security using SendGrid SMTP.

### Features

- **Email-based OTP (One-Time Password)**: 6-digit verification codes sent via email
- **Secure SMTP**: Uses TLS encryption via SendGrid
- **Rate Limiting**: Prevents brute-force attacks on OTP verification
- **Session Management**: Secure session handling with IP binding
- **Audit Logging**: All authentication attempts are logged
- **Configurable**: Easy setup via environment variables

## How It Works

### Login Flow

1. User enters username and password on `/login.php`
2. Credentials are verified against the database
3. If valid, a 6-digit OTP code is generated and sent to the user's email
4. User is redirected to `/2fa.php` to enter the OTP
5. OTP is verified, session is created, and user is logged in
6. If OTP verification fails after 5 attempts, the code expires

### Security Features

- **OTP Expiration**: Codes expire after 10 minutes
- **IP Binding**: OTP must be verified from the same IP where login was initiated
- **Attempt Limiting**: Maximum 5 failed OTP attempts before code expires
- **Hash Storage**: OTP codes are hashed before storage (SHA-256)
- **Account Lockout**: Failed login attempts trigger rate limiting (5 failures in 15 minutes = 15-minute lockout)

## Configuration

### Environment Variables

Set these environment variables or configure them in `includes/config.php`:

```
EMAIL_HOST=smtp.sendgrid.net
EMAIL_PORT=587
EMAIL_HOST_USER=apikey
EMAIL_HOST_PASSWORD=SG.your_sendgrid_api_key
DEFAULT_FROM_EMAIL=ojwangsamuel1@gmail.com
```

### Using SendGrid API Key

1. Sign up for SendGrid: https://sendgrid.com
2. Get your API key from: https://app.sendgrid.com/settings/api_keys
3. Create an API key with "Mail Send" permission
4. Set `EMAIL_HOST_PASSWORD` to your API key
5. Set `EMAIL_HOST_USER` to `apikey` (literal value)

### Configuration Example (Docker/Production)

In your Docker environment variables:

```dockerfile
ENV EMAIL_HOST=smtp.sendgrid.net
ENV EMAIL_PORT=587
ENV EMAIL_HOST_USER=apikey
ENV EMAIL_HOST_PASSWORD=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
ENV DEFAULT_FROM_EMAIL=ojwangsamuel1@gmail.com
```

### Configuration Example (PHP .env file)

Create a `.env` file in the project root:

```
EMAIL_HOST=smtp.sendgrid.net
EMAIL_PORT=587
EMAIL_HOST_USER=apikey
EMAIL_HOST_PASSWORD=SG.your_api_key_here
DEFAULT_FROM_EMAIL=ojwangsamuel1@gmail.com
```

Load it in your application initialization:

```php
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("{$key}={$value}");
    }
}
```

## File Structure

### Key Files

- **`includes/auth.php`**: Authentication logic including 2FA functions
  - `begin_login_2fa()`: Initiates 2FA, generates OTP, sends email
  - `verify_login_2fa()`: Verifies OTP entered by user
  - `login_password_verify()`: Validates password and applies rate limiting

- **`includes/email.php`**: Email sending utilities
  - `send_login_2fa_code_email()`: Sends OTP code to user's email
  - `send_smtp_email()`: Generic SMTP sender (uses SendGrid)
  - `get_email_config()`: Retrieves email configuration
  - `test_email_configuration()`: Diagnostic function for testing setup

- **`public/login.php`**: Login page
  - Password verification form
  - Initiates 2FA process
  - Handles rate limiting display

- **`public/2fa.php`**: OTP verification page
  - User enters 6-digit code
  - Session is finalized after verification

- **`public/admin/email-test.php`**: Diagnostic/testing tool
  - Test email configuration
  - Send test emails
  - Verify SendGrid SMTP connectivity

### Database Tables

#### `login_otps`

Stores pending OTP codes for users:

```sql
CREATE TABLE login_otps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    otp_hash TEXT NOT NULL,           -- SHA-256 hash of OTP code
    expires_at TEXT NOT NULL,         -- ISO 8601 timestamp (10 minutes from creation)
    created_at TEXT NOT NULL,         -- ISO 8601 timestamp
    attempts INTEGER NOT NULL DEFAULT 0,  -- Failed verification attempts
    last_attempt_at TEXT,             -- ISO 8601 timestamp of last attempt
    ip_address TEXT                   -- IP address where OTP was requested
);
```

#### `login_attempts`

Tracks failed login attempts for rate limiting:

```sql
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    first_attempt_at TEXT NOT NULL,
    locked_until TEXT
);
```

#### `users`

Standard users table (requires `email` column):

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT,                       -- Required for 2FA
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin','manager','officer')),
    branch_id INTEGER REFERENCES branches(id)
);
```

## Testing

### Method 1: Using the Diagnostic Page

1. Navigate to `/admin/email-test.php`
2. Login as admin
3. Click "Test Configuration" to verify settings
4. Enter a test email and click "Send Test Email"
5. Check the recipient inbox

### Method 2: Manual Testing

1. Open `/login.php`
2. Enter a valid username and password
3. You should receive an OTP code via email
4. Enter the code on `/2fa.php`
5. If valid, you'll be logged in

### Common Issues

#### "Email configuration incomplete"

**Problem**: One or more email settings are missing.

**Solution**: Verify all environment variables are set:
```bash
echo $EMAIL_HOST
echo $EMAIL_PORT
echo $EMAIL_HOST_USER
echo $EMAIL_HOST_PASSWORD
echo $DEFAULT_FROM_EMAIL
```

#### "SMTP connection failed"

**Problem**: Cannot connect to SendGrid SMTP server.

**Solution**:
1. Verify firewall allows outbound connections on port 587
2. Check that `EMAIL_HOST=smtp.sendgrid.net` is set correctly
3. Test connectivity: `telnet smtp.sendgrid.net 587`

#### "SMTP AUTH failed"

**Problem**: Authentication failed with SendGrid.

**Solution**:
1. Verify `EMAIL_HOST_USER=apikey` (literal value)
2. Verify `EMAIL_HOST_PASSWORD` is a valid SendGrid API key
3. Check API key has "Mail Send" permission
4. Regenerate API key if needed

#### "Email not received"

**Problem**: User doesn't receive OTP email.

**Solution**:
1. Check user's email address is in database: `SELECT email FROM users WHERE id = ?`
2. Check spam/junk folder
3. Verify sender email is verified in SendGrid dashboard
4. Check SendGrid email activity logs: https://app.sendgrid.com/email_activity

#### "Invalid email address after OTP check"

**Problem**: Session expires between password verification and OTP entry.

**Solution**:
1. Verify session timeout is not too short (default: 30 minutes)
2. Ensure OTP code is entered within 10 minutes
3. Verify user's clock is synchronized (NTP)

## API Reference

### Authentication Functions

#### `current_user(): ?array`

Returns the currently logged-in user or null.

```php
$user = current_user();
if ($user) {
    echo "Welcome, " . $user['full_name'];
}
```

#### `begin_login_2fa(array $user, string $ip): void`

Initiates 2FA for a user. Generates OTP, saves it, and sends email.

```php
try {
    begin_login_2fa($user_array, $_SERVER['REMOTE_ADDR']);
    header('Location: /2fa.php');
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
```

#### `verify_login_2fa(int $userId, string $code, string $ip): bool`

Verifies the OTP code. Returns true if valid.

```php
if (verify_login_2fa($user_id, $entered_code, $_SERVER['REMOTE_ADDR'])) {
    $_SESSION['user_id'] = $user_id;
    header('Location: /');
} else {
    echo "Invalid code";
}
```

#### `login_password_verify(string $username, string $password): ?array`

Verifies username/password. Returns user array if valid, null otherwise.

```php
$user = login_password_verify($username, $password);
if ($user) {
    // Valid credentials, proceed with 2FA
} else {
    // Invalid credentials
}
```

#### `login_lock_remaining_seconds(string $username, string $ip): ?int`

Gets remaining lockout time in seconds (due to rate limiting).

```php
$remaining = login_lock_remaining_seconds($username, $_SERVER['REMOTE_ADDR']);
if ($remaining) {
    echo "Try again in " . $remaining . " seconds";
}
```

### Email Functions

#### `send_login_2fa_code_email(string $toEmail, string $code): void`

Sends OTP code via email to user.

```php
try {
    send_login_2fa_code_email('user@example.com', '123456');
} catch (RuntimeException $e) {
    echo "Failed to send email: " . $e->getMessage();
}
```

#### `send_smtp_email(string $to_email, string $subject, string $body, array $config): void`

Generic SMTP email sender.

```php
send_smtp_email(
    to_email: 'recipient@example.com',
    subject: 'Test Subject',
    body: 'Email body content',
    config: get_email_config()
);
```

#### `get_email_config(): array`

Gets email configuration from environment.

```php
$config = get_email_config();
echo $config['host'];      // smtp.sendgrid.net
echo $config['port'];      // 587
echo $config['from_email']; // ojwangsamuel1@gmail.com
```

#### `test_email_configuration(): array`

Tests email configuration and returns diagnostic results.

```php
$results = test_email_configuration();
if ($results['success']) {
    echo "Email configuration is valid";
} else {
    foreach ($results['messages'] as $msg) {
        echo $msg;
    }
}
```

## Security Best Practices

1. **Keep API Keys Secret**: Never commit `EMAIL_HOST_PASSWORD` to version control
2. **Use .env Files**: Store sensitive data in `.env` (add to `.gitignore`)
3. **Use HTTPS Only**: Cookie settings require HTTPS in production
4. **Regular Key Rotation**: Rotate SendGrid API keys periodically
5. **Monitor Logs**: Check audit logs for suspicious authentication patterns
6. **Notify Users**: Consider alerting users of suspicious login attempts
7. **Test Regularly**: Verify email delivery is working

## Troubleshooting Commands

### Check Database Migrations

```bash
sqlite3 data/mdd.sqlite ".schema login_otps"
sqlite3 data/mdd.sqlite ".schema login_attempts"
```

### View Failed Login Attempts

```bash
sqlite3 data/mdd.sqlite "SELECT * FROM login_attempts ORDER BY id DESC LIMIT 10;"
```

### View Active OTPs

```bash
sqlite3 data/mdd.sqlite "SELECT u.username, l.otp_hash, l.expires_at, l.attempts FROM login_otps l JOIN users u ON u.id = l.user_id;"
```

### View Authentication Logs

```bash
sqlite3 data/mdd.sqlite "SELECT * FROM audit_logs WHERE action LIKE 'auth.%' ORDER BY id DESC LIMIT 20;"
```

## Support

For issues with:
- **SendGrid**: Check https://sendgrid.com/docs/
- **Email not sending**: Use `/admin/email-test.php`
- **OTP verification**: Check audit logs in database
- **Session issues**: Verify browser cookies are enabled

## Notes

- OTP codes are randomly generated between 100000-999999
- All timestamps are stored in ISO 8601 format (UTC)
- The system uses SQLite for local development (replace with PostgreSQL/MySQL in production)
- Session hijacking is prevented through IP binding and CSRF tokens
