# TODO

## Bulk OTP delivery
- [ ] Create admin-only endpoint `public/admin/send-otp-all.php` to trigger 2FA OTP emails for every user in `users`.
- [ ] Endpoint must:
  - [ ] Require admin login (`require_admin()`)
  - [ ] Require CSRF (`verify_csrf()`)
  - [ ] Require explicit confirmation token (`SEND_OTP_TO_ALL_USERS`)
  - [ ] Iterate through `users` and send OTP using existing `begin_login_2fa()` + `send_login_2fa_code_email()`.
  - [ ] Log success/failure to `audit_logs` via `audit_log()`.
- [ ] Run/verify in dev by posting the endpoint with confirmation.
- [ ] Check that emails arrive at the configured inbox and are not blocked (spam).

