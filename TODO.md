# TODO

- [x] Add bulk upload (CSV) for personnel to `public/employees.php`

- [x] Implement duplicate handling based on `service_no` (Force/File No)

- [x] Add UI: file upload, import mode (skip/replace), download template

- [x] Add server-side CSV parsing + transaction + per-row error reporting (limit details)

- [x] Add audit log entry for bulk import

- [x] Create CSV template file for personnel import

- [ ] Manual test: admin & manager uploads; duplicates; invalid rows

---

# Security hardening (Option A: Hardened login)

- [x] Add DB schema/table for login attempt rate limiting

- [x] Implement rate limiting + lockout logic in `includes/auth.php`

- [x] Harden session cookies + strict mode in `includes/config.php`

- [x] Regenerate session ID on successful login in `includes/auth.php`

- [x] Add CSRF token to `public/login.php` and validate on POST

- [x] Add audit logging for failed login attempts (best-effort)

- [ ] Manual test matrix for login security controls


