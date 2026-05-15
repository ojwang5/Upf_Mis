# TODO

- [x] Add audit log schema/table (`audit_logs`) in `includes/db.php`.
- [x] Create `includes/audit.php` with `audit_log()` helper.
- [x] Wire logging into `public/login.php` and `public/logout.php`.
- [x] Wire logging into `public/users.php` (create/reset/delete).

- [ ] Wire logging into `public/employees.php` (create/update/delete).
- [ ] Wire logging into `public/branches.php` (create/update/delete/delete_cascade).
- [ ] Wire logging into `public/history.php` (manager_approve/reject, admin_approve/reject).
- [x] Create admin UI `public/admin-audit.php` (filter/search/pagination) requiring admin.
- [ ] Run quick server/test to verify audit rows are created and page works.

