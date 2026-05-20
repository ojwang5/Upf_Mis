# TODO

## Login rate-limit UX (notify + 15-minute countdown)
- [x] Update `includes/auth.php` to expose remaining lock time for the current username+IP.
- [x] Update `public/login.php` to display a clear lockout message and a live 15-minute countdown.
- [x] Sanity test: fail login 5x then ensure lock message shows with countdown; ensure normal failures still show generic error.



