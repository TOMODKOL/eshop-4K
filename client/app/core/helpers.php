<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Http;
use App\Core\Session;

function e(mixed $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function money(float|int|string|null $n): string
{
    $n = (float) $n;
    $dec = abs($n - round($n)) > 0.004 ? 2 : 0;
    return str_replace(' ', "\u{00A0}", number_format($n, $dec, ',', ' ')) . "\u{00A0}Kč";
}

function date_cz(?string $d, bool $time = false): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date($time ? 'j. n. Y H:i' : 'j. n. Y', $t) : '';
}

function url(string $p = '/'): string { return Http::url($p); }

function asset(string $p): string
{
    $file = APP_ROOT . '/assets/' . $p;
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return Http::url('/assets/' . $p) . '?v=' . $v;
}

function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(Session::csrf()) . '">'; }

function cfg(string $k, mixed $d = null): mixed { return Config::get($k, $d); }
