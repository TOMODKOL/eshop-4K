<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $rel = substr($class, 4);
    // App\Core\Db -> app/core/Db.php ; App\Modules\Vratky\Service -> app/modules/vratky/Service.php
    $parts = explode('\\', $rel);
    $file = array_pop($parts);
    $dir = strtolower(implode('/', $parts));
    $path = APP_ROOT . '/app/' . ($dir ? $dir . '/' : '') . $file . '.php';
    if (is_file($path)) require $path;
});

// knihovna pro QR kódy (chillerlan/php-qrcode 4.4, MIT) – bez composeru
spl_autoload_register(function (string $class): void {
    foreach (['chillerlan\\QRCode\\' => 'qrcode', 'chillerlan\\Settings\\' => 'settings'] as $ns => $dir) {
        if (str_starts_with($class, $ns)) {
            $path = APP_ROOT . '/app/lib/' . $dir . '/' . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (is_file($path)) require $path;
            return;
        }
    }
});

date_default_timezone_set('Europe/Prague');
mb_internal_encoding('UTF-8');
