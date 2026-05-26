<?php
// Built-in PHP server router.
// - Serve real static files from /public (e.g. /login.php, /assets/...)
// - Route everything else to /public/index.php

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uriPath = $uriPath ?: '/';

// Normalize: prevent weird paths like "/../"
$path = '/' . ltrim($uriPath, '/');

// Ensure built-in server maps both " / " and "/index.php" to the dashboard.
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return;
}

$file = __DIR__ . $path;

// Let the built-in server serve existing static files directly.
if (file_exists($file) && !is_dir($file)) {
    return false;
}

require __DIR__ . '/index.php';


