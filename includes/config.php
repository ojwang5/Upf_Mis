<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Kampala');

define('APP_NAME', 'MDD MANAGEMENT SYSTEM');
define('APP_ORG', 'UGANDA POLICE FORCE');
define('APP_MOTTO', 'PROTECT & SERVE');
define('BASE_PATH', dirname(__DIR__));
define('DB_PATH', BASE_PATH . '/data/mdd.sqlite');

// Session hardening
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
// Secure cookies require HTTPS. Keep enabled by default; can be disabled via environment/config later if needed.
ini_set('session.cookie_secure', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

