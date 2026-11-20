<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    /** Moduly, které aplikace zná. Každý má app/modules/<název>/routes.php */
    public const MODULES = ['vratky' => 'Vratky a reklamace', 'servis' => 'Servis'];

    public static function boot(): void
    {
        require_once APP_ROOT . '/app/core/helpers.php';
        Config::load();
        $debug = (bool) Config::get('debug');
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);
        set_exception_handler(function (\Throwable $e) use ($debug) {
            self::logError($e);
            http_response_code(500);
            if ($debug) { echo '<pre>' . e((string) $e) . '</pre>'; return; }
            View::page('layout/message', ['title' => 'Něco se pokazilo',
                'text' => 'Omlouváme se, na stránce nastala chyba. Zkuste to prosím za chvíli, nebo nám zavolejte na ' . Config::get('shop.phone') . '.']);
        });
    }

    public static function run(): void
    {
        self::boot();
        Installer::migrate();
        Session::start();
        self::routes();
        Http::dispatch();
    }

    public static function routes(): void
    {
        // úvod – rozcestník pro zákazníky
        Http::get('/', function () {
            if (Config::module('vratky') && !Config::module('servis')) Http::redirect('/vratky');
            View::page('layout/home', ['title' => 'Klientská zóna']);
        });

        // administrace – společné
        require APP_ROOT . '/app/admin_routes.php';

        foreach (array_keys(self::MODULES) as $m) {
            $file = APP_ROOT . '/app/modules/' . $m . '/routes.php';
            if (Config::module($m) && is_file($file)) require $file;
        }
    }

    public static function logError(\Throwable $e): void
    {
        $dir = APP_ROOT . '/storage/log';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/error.log', '[' . date('Y-m-d H:i:s') . '] ' . $e . "\n\n", FILE_APPEND | LOCK_EX);
    }
}
