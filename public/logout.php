<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
require_once __DIR__ . '/../includes/audit.php';
// actor no longer in session; log best-effort without actor
audit_log(null, 'auth.logout');
header('Location: /login.php');

