<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('k4client');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $k, mixed $d = null): mixed { return $_SESSION[$k] ?? $d; }
    public static function set(string $k, mixed $v): void { $_SESSION[$k] = $v; }
    public static function forget(string $k): void { unset($_SESSION[$k]); }

    public static function flash(string $msg, string $type = 'ok'): void { $_SESSION['_flash'][] = [$type, $msg]; }
    public static function takeFlash(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

    public static function csrf(): string
    {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['_csrf'];
    }

    public static function checkCsrf(): void
    {
        $t = $_POST['_csrf'] ?? '';
        if (!is_string($t) || !hash_equals($_SESSION['_csrf'] ?? '', $t)) {
            http_response_code(419);
            View::page('layout/message', ['title' => 'Platnost formuláře vypršela',
                'text' => 'Stránka byla otevřená příliš dlouho. Vraťte se prosím zpět a zkuste to znovu.']);
            exit;
        }
    }
}
