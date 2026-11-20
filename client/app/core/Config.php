<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static ?array $data = null;

    public static function load(?array $override = null): void
    {
        if ($override !== null) { self::$data = $override; return; }
        $file = APP_ROOT . '/config/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Chybí config/config.php – zkopírujte config.example.php a vyplňte ho.');
        }
        self::$data = require $file;
    }

    /** Hodnota podle tečkové cesty, např. get('mail.enabled') */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) self::load();
        $v = self::$data;
        foreach (explode('.', $key) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) return $default;
            $v = $v[$k];
        }
        return $v;
    }

    public static function module(string $name): bool
    {
        return (bool) self::get('modules.' . $name, false);
    }
}
