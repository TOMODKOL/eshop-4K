<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Config;

/**
 * Velikosti zboží přímo z e-shopu 4kluciodkol.cz – stejné dlaždice i dostupnost, jako vidí zákazník u produktu.
 * Adresa /x-p{ID zásoby}/ přesměruje na hlavní kartu s vybranou variantou. Dlaždice jsou v #PDVariantSelector,
 * kód zásoby (sku) jednotlivých velikostí je v JSON-LD (hasVariant). Výsledek se drží 30 minut v storage/cache.
 */
final class Eshop
{
    private const TTL = 1800;

    /**
     * @return array{sizes: list<array{value:string, avail:string, cls:string, sku:string, current:bool}>}|null
     *   null = velikosti nejde zjistit (karta bez variant, web nedostupný…) → formulář použije náhradní výběr
     */
    public static function sizes(?int $stockId): ?array
    {
        if (!$stockId) return null;
        $dir = APP_ROOT . '/storage/cache';
        $file = "$dir/eshop_$stockId.json";
        if (is_file($file) && filemtime($file) > time() - self::TTL) {
            $c = json_decode((string) file_get_contents($file), true);
            return is_array($c) && !empty($c['sizes']) ? $c : null;
        }
        $html = self::fetch(rtrim((string) (Config::get('vratky.eshop_url') ?: 'https://www.4kluciodkol.cz'), '/') . "/x-p$stockId/");
        $res = $html === null ? null : self::parse($html);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode($res ?? ['sizes' => []], JSON_UNESCAPED_UNICODE));
        return $res;
    }

    private static function fetch(string $url): ?string
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8, CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'client.4kluciodkol.cz (vratky)',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return is_string($body) && $code === 200 ? $body : null;
    }

    /** Rozebere stránku produktu. Veřejné kvůli testům. */
    public static function parse(string $html): ?array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $has = fn(string $c) => "contains(concat(' ', normalize-space(@class), ' '), ' $c ')";

        // skupina dlaždic „Velikost“ (u více vlastností jen ta; jinak jediná skupina)
        $groups = $xp->query("//*[@id='PDVariantSelector']//*[{$has('buttons')}]");
        if (!$groups || $groups->length === 0) return null;
        $group = null;
        foreach ($groups as $g) {
            $cap = $xp->query("preceding-sibling::*[{$has('caption')}][1]", $g)->item(0);
            if ($cap && stripos($cap->textContent, 'velikost') !== false) { $group = $g; break; }
        }
        if (!$group && $groups->length === 1) $group = $groups->item(0);
        if (!$group) return null;

        $sizes = [];
        foreach ($xp->query("*[{$has('value')}]", $group) as $t) {
            /** @var \DOMElement $t */
            $value = trim($t->getAttribute('data-value')) ?: trim((string) $xp->query(".//*[{$has('labelValue')}]", $t)->item(0)?->textContent);
            if ($value === '') continue;
            $av = $xp->query(".//*[{$has('availabilityInfo')}]", $t)->item(0);
            $cls = '';
            if ($av instanceof \DOMElement) foreach (preg_split('/\s+/', $av->getAttribute('class')) as $c) if ($c !== '' && $c !== 'availabilityInfo') $cls = $c;
            $sizes[] = ['value' => $value, 'avail' => trim((string) $av?->textContent), 'cls' => preg_replace('/[^a-z0-9_-]/i', '', $cls),
                'sku' => '', 'current' => (bool) preg_match('/(^|\s)selected(\s|$)/', $t->getAttribute('class'))];
        }
        if (count($sizes) < 2) return null;

        // kód zásoby každé velikosti z JSON-LD
        $variants = [];
        foreach ($xp->query("//script[@type='application/ld+json']") as $s) {
            $j = json_decode($s->textContent, true);
            if (is_array($j) && isset($j['hasVariant']) && is_array($j['hasVariant'])) $variants = $j['hasVariant'];
        }
        foreach ($variants as $v) {
            $sku = trim((string) ($v['sku'] ?? ''));
            if ($sku === '') continue;
            $i = self::matchSize($sizes, (string) ($v['name'] ?? ''), (string) ($v['image'] ?? ''));
            if ($i !== null && $sizes[$i]['sku'] === '') $sizes[$i]['sku'] = $sku;
        }
        return ['sizes' => $sizes];
    }

    /** Ke které dlaždici varianta patří: podle konce názvu, jinak podle konce názvu obrázku (…-43-5.jpg). */
    private static function matchSize(array $sizes, string $name, string $image): ?int
    {
        $last = mb_strtolower((string) preg_replace('/^vel\.?/iu', '', (string) (preg_match('/(\S+)$/u', trim($name), $m) ? $m[1] : '')));
        foreach ($sizes as $i => $s) if (mb_strtolower($s['value']) === $last) return $i;
        $file = mb_strtolower((string) preg_replace('/\.[a-z0-9]+$/i', '', basename((string) parse_url($image, PHP_URL_PATH))));
        $best = null; $len = 0;
        foreach ($sizes as $i => $s) {
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(self::ascii($s['value']))), '-');
            if ($slug !== '' && str_ends_with($file, '-' . $slug) && strlen($slug) > $len) { $best = $i; $len = strlen($slug); }
        }
        return $best;
    }

    private static function ascii(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        return $t === false ? $s : $t;
    }

    /** Najde vybranou velikost mezi dlaždicemi. */
    public static function find(?array $data, string $value): ?array
    {
        foreach ($data['sizes'] ?? [] as $s) if ($s['value'] === $value) return $s;
        return null;
    }
}
