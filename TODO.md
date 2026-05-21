# TODO

## Step 1: Fix merge-conflict syntax errors
- [ ] Remove Git conflict markers in `includes/auth.php`.
- [ ] Remove Git conflict markers in `includes/config.php`.

## Step 2: Verify app boots
- [ ] Run a PHP syntax check on the fixed files.

## Step 3: Regression check
- [ ] Ensure `login_lock_remaining_seconds()` exists if `public/login.php` references it.

