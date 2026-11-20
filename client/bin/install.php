<?php
// Vytvoří tabulky v databázi podle config/config.php.   php bin/install.php
// (Bez SSH jde totéž přes prohlížeč: /admin/instalace)
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/core/helpers.php';
App\Core\Config::load();
echo 'Databáze: ' . App\Core\Config::get('db.dsn') . "\n";
App\Core\Installer::run();
echo "Tabulky jsou připravené.\n";
