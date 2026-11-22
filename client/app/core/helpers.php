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

/** Počet kusů jako na e-shopu: [−  1 ks  +]. */
function qty_stepper(string $name, string $id, int $val, int $max, string $attrs = ''): string
{
    $val = max(1, min($max, $val));
    return '<div class="stepper" data-stepper>'
        . '<button type="button" class="st-b" data-step="-1" aria-label="Méně"' . ($val <= 1 ? ' disabled' : '') . '>−</button>'
        . '<label class="st-v"><input type="number" inputmode="numeric" id="' . e($id) . '" name="' . e($name) . '" value="' . $val . '" min="1" max="' . $max . '"' . $attrs . '><span>ks</span></label>'
        . '<button type="button" class="st-b" data-step="1" aria-label="Více"' . ($val >= $max ? ' disabled' : '') . '>+</button></div>';
}
