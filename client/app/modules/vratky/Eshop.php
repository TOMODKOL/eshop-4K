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

    /** Velikosti zboží jako na e-shopu, null = nejdou zjistit (karta bez variant, web nedostupný…) → formulář použije náhradní výběr. */
    public static function sizes(?int $stockId): ?array
    {
        $p = self::page($stockId);
        return $p && !empty($p['sizes']) ? ['sizes' => $p['sizes']] : null;
    }

    /** Zboží na e-shopu je, ale nemá výběr velikosti (prací prášek, doplňky…) → výměna jen za jiné zboží. */
    public static function noSizes(?int $stockId): bool
    {
        $p = self::page($stockId);
        return $p !== null && !empty($p['product']) && empty($p['sizes']);
    }

    /** Náhled zboží (malý obrázek z CDN e-shopu), '' = není. */
    public static function image(?int $stockId): string
    {
        $p = self::page($stockId);
        return (string) ($p['image'] ?? '');
    }

    /** Stránka zboží na e-shopu podle ID zásoby: velikosti + obrázek. Drží se 30 minut v storage/cache. */
    private static function page(?int $stockId): ?array
    {
        static $mem = [];
        if (!$stockId) return null;
        if (array_key_exists($stockId, $mem)) return $mem[$stockId];
        $dir = APP_ROOT . '/storage/cache';
        $file = "$dir/eshop_$stockId.json";
        if (is_file($file) && filemtime($file) > time() - self::TTL) {
            $c = json_decode((string) file_get_contents($file), true);
            return $mem[$stockId] = is_array($c) ? $c : null;
        }
        $html = self::fetch(rtrim((string) (Config::get('vratky.eshop_url') ?: 'https://www.4kluciodkol.cz'), '/') . "/x-p$stockId/");
        $res = $html === null ? null : ['sizes' => self::parse($html)['sizes'] ?? [], 'image' => self::parseImage($html, $stockId),
            'product' => (bool) preg_match('~"@type"\s*:\s*"(Product|ProductGroup)"~', $html)];
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode($res ?? ['sizes' => [], 'image' => ''], JSON_UNESCAPED_UNICODE));
        return $mem[$stockId] = $res;
    }

    /** Obrázek varianty (podle ID zásoby) nebo celého zboží z JSON-LD, zmenšený na náhled (CDN …/0/… = 220 px). */
    public static function parseImage(string $html, int $stockId = 0): string
    {
        if (!preg_match_all('~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~is', $html, $m)) return '';
        $img = '';
        foreach ($m[1] as $json) {
            $j = json_decode(trim($json), true) ?: json_decode(html_entity_decode(trim($json)), true);
            if (!is_array($j) || !in_array($j['@type'] ?? '', ['ProductGroup', 'Product'], true)) continue;
            $img = (string) (is_array($j['image'] ?? null) ? ($j['image'][0] ?? '') : ($j['image'] ?? ''));
            foreach ((array) ($j['hasVariant'] ?? []) as $v) {
                if ($stockId && (string) ($v['identifier'] ?? '') === (string) $stockId && !empty($v['image'])) { $img = (string) $v['image']; break; }
            }
            break;
        }
        if (!str_starts_with($img, 'https://')) return '';
        return (string) preg_replace('~(/images/\d+/[0-9a-f]+/)\d+/~', '${1}0/', $img);
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
                'sku' => '', 'price' => null, 'current' => (bool) preg_match('/(^|\s)selected(\s|$)/', $t->getAttribute('class'))];
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
            if ($i !== null && $sizes[$i]['sku'] === '') { $sizes[$i]['sku'] = $sku; $sizes[$i]['price'] = self::price($v); }
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

    private static function price(array $p): ?float
    {
        $o = $p['offers'] ?? null;
        if (is_array($o) && isset($o[0])) $o = $o[0];
        return is_array($o) && is_numeric($o['price'] ?? null) ? (float) $o['price'] : null;
    }

    /** Je odkaz stránkou zboží na našem e-shopu? Vrací čistou adresu, nebo null. */
    public static function productUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;
        if (!preg_match('~^https?://~i', $url)) $url = 'https://' . ltrim($url, '/');
        $p = parse_url($url);
        $host = strtolower((string) ($p['host'] ?? ''));
        if (!in_array($host, ['4kluciodkol.cz', 'www.4kluciodkol.cz'], true)) return null;
        $path = (string) ($p['path'] ?? '');
        if (!preg_match('~^/[a-z0-9-]*-p\d+/?$~i', $path)) return null;
        parse_str((string) ($p['query'] ?? ''), $q);
        return 'https://www.4kluciodkol.cz' . rtrim($path, '/') . '/' . (isset($q['vid']) && ctype_digit((string) $q['vid']) ? '?vid=' . $q['vid'] : '');
    }

    /**
     * Zboží z e-shopu pro výměnu za jiné zboží.
     * @return array{name:string, image:string, url:string, single:bool, options:list<array{value:string, avail:string, cls:string, sku:string, price:?float, ok:bool}>}|null
     *   ok = lze vybrat (skladem nebo skladem u dodavatele, se známou cenou a kódem)
     */
    public static function product(string $url): ?array
    {
        $url = self::productUrl($url);
        if (!$url) return null;
        $dir = APP_ROOT . '/storage/cache';
        $file = "$dir/eshop_url_" . md5($url) . '.json';
        if (is_file($file) && filemtime($file) > time() - self::TTL) {
            $c = json_decode((string) file_get_contents($file), true);
            return is_array($c) && !empty($c['options']) ? $c : null;
        }
        $html = self::fetch($url);
        $res = $html === null ? null : self::parseProduct($html, $url);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode($res ?? ['options' => []], JSON_UNESCAPED_UNICODE));
        return $res;
    }

    /** Rozebere stránku zboží (s velikostmi i bez nich). Veřejné kvůli testům. */
    public static function parseProduct(string $html, string $url = ''): ?array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $ld = null;
        foreach ($xp->query("//script[@type='application/ld+json']") as $sc) {
            $j = json_decode($sc->textContent, true);
            if (is_array($j) && in_array($j['@type'] ?? '', ['ProductGroup', 'Product'], true)) { $ld = $j; break; }
        }
        if (!$ld) return null;
        $ok = fn(array $o) => $o['cls'] === 'zelena' && $o['sku'] !== '' && $o['price'] !== null && $o['price'] > 0;
        $base = ['name' => trim((string) ($ld['name'] ?? '')), 'image' => (string) ($ld['image'] ?? ''), 'url' => $url];
        if (($ld['@type'] ?? '') === 'ProductGroup') {
            $v = self::parse($html);
            if (!$v) return null;
            $opts = array_map(fn($o) => ['value' => $o['value'], 'avail' => $o['avail'], 'cls' => $o['cls'], 'sku' => $o['sku'], 'price' => $o['price']], $v['sizes']);
            foreach ($opts as &$o) $o['ok'] = $ok($o);
            unset($o);
            return $base + ['single' => false, 'options' => $opts];
        }
        $av = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' PDAvailabilityInfo ')]")->item(0);
        $cls = '';
        if ($av instanceof \DOMElement && preg_match('/\bcs_([a-z0-9_-]+)/i', $av->getAttribute('class'), $m)) $cls = $m[1];
        $o = ['value' => '', 'avail' => trim((string) $av?->textContent), 'cls' => $cls, 'sku' => trim((string) ($ld['sku'] ?? '')), 'price' => self::price($ld)];
        $o['ok'] = $ok($o);
        return $base + ['single' => true, 'options' => [$o]];
    }

    /**
     * Hledání na e-shopu (stránka /hledat/?q=…). Vrací jen zboží skladem nebo skladem u dodavatele.
     * @return list<array{name:string, url:string, image:string, price:?float, avail:string}>|null  null = e-shop nejde načíst
     */
    public static function search(string $q): ?array
    {
        $q = trim(mb_substr($q, 0, 80));
        if (mb_strlen($q) < 2) return [];
        $dir = APP_ROOT . '/storage/cache';
        $file = "$dir/eshop_q_" . md5(mb_strtolower($q)) . '.json';
        if (is_file($file) && filemtime($file) > time() - 600) {
            $c = json_decode((string) file_get_contents($file), true);
            if (is_array($c)) return $c;
        }
        $html = self::fetch('https://www.4kluciodkol.cz/hledat/?q=' . rawurlencode($q));
        if ($html === null) return null;
        $res = self::parseSearch($html);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode($res, JSON_UNESCAPED_UNICODE));
        return $res;
    }

    /** Rozebere výsledky hledání. Veřejné kvůli testům. */
    public static function parseSearch(string $html, int $max = 12): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $has = fn(string $c) => "contains(concat(' ', normalize-space(@class), ' '), ' $c ')";
        $out = []; $seen = [];
        foreach ($xp->query("//*[{$has('ProductView')}]") as $it) {
            $a = $xp->query(".//h2//a[@href]", $it)->item(0);
            if (!$a instanceof \DOMElement) continue;
            $url = self::productUrl('https://www.4kluciodkol.cz' . $a->getAttribute('href'));
            if (!$url || isset($seen[$url])) continue;
            $av = $xp->query(".//*[{$has('AvailabilityInfo')}]", $it)->item(0);
            $cls = ($av instanceof \DOMElement && preg_match('/\bcs_([a-z0-9_-]+)/i', $av->getAttribute('class'), $m)) ? $m[1] : '';
            if ($cls !== 'zelena') continue;
            $img = $xp->query(".//img", $it)->item(0);
            $src = $img instanceof \DOMElement ? ($img->getAttribute('data-src') ?: $img->getAttribute('src')) : '';
            $price = $xp->query(".//*[{$has('price')} and {$has('user')}]//*[{$has('value')}]", $it)->item(0);
            $p = $price ? (float) preg_replace('/[^\d,]/u', '', str_replace(',', '.', str_replace("\u{a0}", '', $price->textContent))) : null;
            $seen[$url] = true;
            $out[] = ['name' => trim($a->textContent), 'url' => $url, 'image' => str_starts_with($src, 'https://') ? $src : '',
                'price' => $p ?: null, 'avail' => trim((string) $av->textContent)];
            if (count($out) >= $max) break;
        }
        return $out;
    }

    /** Vybraná možnost (velikost) zboží; u zboží bez velikostí prázdná hodnota. */
    public static function option(?array $product, string $value): ?array
    {
        foreach ($product['options'] ?? [] as $o) if ($o['value'] === $value) return $o;
        return null;
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
