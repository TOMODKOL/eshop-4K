<?php
// Jen pro zkoušení na počítači:  php -S localhost:8080 bin/dev-router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/') && is_file(__DIR__ . '/..' . $path)) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../index.php';
