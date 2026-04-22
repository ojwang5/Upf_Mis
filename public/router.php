<?php
// Built-in PHP server router. Serves static files; routes "/" to index.php.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // serve as static
}
require __DIR__ . '/index.php';
