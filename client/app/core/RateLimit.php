<?php
declare(strict_types=1);

namespace App\Core;

/** Omezení počtu pokusů (hledání objednávky, přihlášení) podle IP adresy. */
final class RateLimit
{
    public static function hit(string $bucket, int $max, int $windowSec): bool
    {
        $dir = APP_ROOT . '/storage/ratelimit';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . '/' . hash('sha256', $bucket . '|' . Http::ip()) . '.json';
        $now = time();
        $hits = [];
        if (is_file($file)) {
            $hits = json_decode((string) file_get_contents($file), true) ?: [];
            $hits = array_values(array_filter($hits, fn($t) => $t > $now - $windowSec));
        }
        if (count($hits) >= $max) return false;
        $hits[] = $now;
        file_put_contents($file, json_encode($hits), LOCK_EX);
        return true;
    }
}
