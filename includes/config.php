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
// Secure cookies require HTTPS.
ini_set('session.cookie_secure', '1');

// Shorter session lifetime to reduce hijack window (seconds)
ini_set('session.gc_maxlifetime', '1800');
if (ini_get('session.gc_probability') === '') ini_set('session.gc_probability', '1');
if (ini_get('session.gc_divisor') === '') ini_set('session.gc_divisor', '100');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Idle timeout
if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > 1800) {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php');
    exit;
}


