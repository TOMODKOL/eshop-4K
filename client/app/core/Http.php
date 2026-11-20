<?php
declare(strict_types=1);

namespace App\Core;

/** Jednoduchý požadavek, směrování a odpovědi. */
final class Http
{
    /** @var array<int,array{0:string,1:string,2:callable}> */
    private static array $routes = [];

    public static function get(string $pattern, callable $h): void { self::$routes[] = ['GET', $pattern, $h]; }
    public static function post(string $pattern, callable $h): void { self::$routes[] = ['POST', $pattern, $h]; }

    public static function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        // aplikace může běžet i v podsložce
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base)) ?: '/';
        $path = '/' . trim($path, '/');
        return $path;
    }

    public static function dispatch(): void
    {
        $m = self::method();
        $p = self::path();
        $allowed = false;
        foreach (self::$routes as [$rm, $pattern, $h]) {
            $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#u';
            if (!preg_match($re, $p, $mm)) continue;
            if ($rm !== $m) { $allowed = true; continue; }
            $args = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
            $h(...array_values($args));
            return;
        }
        if ($allowed) { http_response_code(405); echo 'Metoda není povolena.'; return; }
        http_response_code(404);
        View::page('layout/notfound', ['title' => 'Stránka nenalezena']);
    }

    public static function url(string $path = '/'): string
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        return $base . $path;
    }

    public static function redirect(string $path, int $code = 303): never
    {
        header('Location: ' . (str_starts_with($path, 'http') ? $path : self::url($path)), true, $code);
        exit;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function input(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
}
