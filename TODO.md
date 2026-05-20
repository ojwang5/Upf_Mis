<<<<<<< HEAD
# TODO

## Login rate-limit UX (notify + 15-minute countdown)
- [x] Update `includes/auth.php` to expose remaining lock time for the current username+IP.
- [x] Update `public/login.php` to display a clear lockout message and a live 15-minute countdown.
- [x] Sanity test: fail login 5x then ensure lock message shows with countdown; ensure normal failures still show generic error.


=======
## TODO (for blackboxai changes)

- [ ] Update button styling: make “Add Personnel” and “Add Leave” use new CSS classes while inheriting existing `.btn` behavior.
- [ ] Toggle “Create Notification” button on Notifications page.
- [ ] Wrap admin “Send Broadcast” form in a hidden container, show/hide via JS.
- [ ] Sanity check with PHP syntax lint on modified files.
>>>>>>> d7b0ca01eb9f334d5c76a0199d57c4d7dc622e5d

