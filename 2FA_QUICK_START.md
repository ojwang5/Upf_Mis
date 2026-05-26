# 🚀 2FA Quick Start Guide

## What Was Built
✅ Email-based Two-Factor Authentication (2FA) system  
✅ SendGrid SMTP integration  
✅ Secure OTP generation and verification  
✅ Rate limiting and account lockout  
✅ Complete documentation and diagnostic tools  

## Setup (5 Minutes)

### Option 1: PowerShell Setup (Windows - Recommended)
```powershell
.\setup-2fa.ps1
```
Follow the interactive prompts to configure SendGrid credentials.

### Option 2: Manual Configuration
Set environment variables or create `.env` file:
```
EMAIL_HOST=smtp.sendgrid.net
EMAIL_PORT=587
EMAIL_HOST_USER=apikey
EMAIL_HOST_PASSWORD=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
DEFAULT_FROM_EMAIL=ojwangsamuel1@gmail.com
```

## Testing (2 Minutes)

### 1. Start the Server
```bash
.\start-server.bat
```

### 2. Test Configuration (Browser)
Navigate to: **http://localhost:8000/admin/email-test.php**
- Login as admin
- Click "Test Configuration"
- Send a test email to verify

### 3. Test Full Login Flow
1. Go to: http://localhost:8000/login.php
2. Enter valid username and password
3. Check your email for 6-digit code
4. Enter code on verification page
5. ✅ You're logged in!

## Files Created

| File | Purpose |
|------|---------|
| `includes/email.php` | Email sending via SendGrid SMTP |
| `public/admin/email-test.php` | Email configuration diagnostic tool |
| `.env.example` | Configuration template |
| `setup-2fa.ps1` | Windows setup script |
| `setup-2fa.sh` | Linux/Mac setup script |
| `2FA_IMPLEMENTATION.md` | Complete technical documentation |
| `2FA_IMPLEMENTATION_SUMMARY.md` | Implementation details and reference |

## Files Modified

| File | Changes |
|------|---------|
| `includes/config.php` | Added SendGrid SMTP configuration |
| `includes/auth.php` | Integrated email.php for 2FA |

## Security Features

- ✅ **Hashed OTPs**: SHA-256 hashing
- ✅ **Rate Limiting**: 5 failures = 15-minute lockout
- ✅ **TLS Encryption**: Secure SMTP via STARTTLS
- ✅ **IP Binding**: OTP verified from same IP
- ✅ **Audit Logging**: All auth attempts logged
- ✅ **Session Security**: HTTPOnly, SameSite cookies
- ✅ **OTP Expiration**: 10-minute validity window
- ✅ **Attempt Limiting**: 5 failed OTP attempts = code expires

## Common Tasks

### Send Test Email
```
http://localhost:8000/admin/email-test.php
→ Enter email → Click "Send Test Email"
```

### Check Failed Login Attempts
```sql
sqlite3 data/mdd.sqlite "SELECT * FROM login_attempts ORDER BY id DESC LIMIT 10;"
```

### View Active OTPs
```sql
sqlite3 data/mdd.sqlite "SELECT * FROM login_otps;"
```

### Reset User (Clear OTPs)
```sql
sqlite3 data/mdd.sqlite "DELETE FROM login_otps WHERE user_id = 123;"
sqlite3 data/mdd.sqlite "DELETE FROM login_attempts WHERE username = 'john';"
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Email not sending | Visit `/admin/email-test.php` to diagnose |
| Cannot connect to SMTP | Check firewall allows port 587 |
| AUTH failed | Verify API key and username is "apikey" |
| OTP not received | Check spam folder, verify email in database |
| Session expires too quickly | Default is 30 minutes, configured in config.php |

## API Quick Reference

```php
// Check if logged in
$user = current_user();

// Verify password
$user = login_password_verify($username, $password);

// Start 2FA
begin_login_2fa($user, $_SERVER['REMOTE_ADDR']);

// Verify OTP
if (verify_login_2fa($userId, $code, $_SERVER['REMOTE_ADDR'])) {
    $_SESSION['user_id'] = $userId;
}

// Send email
send_login_2fa_code_email('user@example.com', $code);

// Test config
$results = test_email_configuration();
```

## Production Checklist

- [ ] All SendGrid credentials configured
- [ ] `public/admin/email-test.php` deleted (optional but recommended)
- [ ] HTTPS enabled (required for secure cookies)
- [ ] Database backups configured
- [ ] API key rotated periodically
- [ ] `.env` file in `.gitignore`
- [ ] Tested login flow end-to-end
- [ ] Tested email delivery timing
- [ ] Audit logs being recorded
- [ ] Error handling tested (invalid code, expired code, etc.)

## Support

📖 **Full Documentation**: See `2FA_IMPLEMENTATION.md`  
🔧 **Technical Details**: See `2FA_IMPLEMENTATION_SUMMARY.md`  
❓ **Setup Help**: Run `setup-2fa.ps1` or `setup-2fa.sh`  
📧 **SendGrid Docs**: https://sendgrid.com/docs/

## Next Steps

1. ✅ Run setup script
2. ✅ Test email configuration
3. ✅ Test login flow
4. ✅ Review logs to ensure audit trail is working
5. 🚀 Deploy to production

---

**Status**: Ready for immediate use  
**Version**: 1.0  
**Last Updated**: 2026-05-26
