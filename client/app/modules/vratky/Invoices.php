<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Db;

/**
 * Faktury v PDF pro zákazníky. PDF tiskne pomocník na KANCL přímo z Pohody (sestava „Faktura“, ID 190)
 * a posílá je sem (POST /api/sync/faktury-pdf). Ukládají se mimo veřejnou část: storage/faktury/<číslo>.pdf.
 * Zákazník si fakturu stáhne odkazem z účtu na e-shopu: /faktura?c=<číslo faktury nebo objednávky>&email=<e-mail>.
 * Stejně potvrzení objednávky (sestava „Potvrzení přijetí objednávky“, ID 383): storage/objednavky/<číslo>.pdf,
 * /objednavka-pdf?c=…&email=…, API /api/sync/objednavky-pdf. Zálohové faktury (sestava „Zálohová faktura“, ID 240):
 * storage/zalohy/<číslo>.pdf + <číslo>.json (e-mail odběratele, číslo objednávky – portál je jinak nezná), /zaloha-pdf?c=…&email=….
 * Čerstvé objednávky (ještě bez faktury) posílá pomocník přímo z Pohody i s e-mailem: storage/objednavky/<číslo>.json.
 * Prodejky (sestava „Prodejka (daňový doklad)“, ID 170) k objednávkám z e-shopu vyzvednutým na prodejně: storage/prodejky/26PH….pdf,
 * stahují se stejnou adresou /faktura (podle čísla objednávky se pozná, jestli je doklad faktura, nebo prodejka).
 * Druh dokladu: 'faktury' | 'objednavky' | 'zalohy' | 'prodejky'.
 */
final class Invoices
{
    public static function dir(string $kind = 'faktury'): string
    {
        return APP_ROOT . '/storage/' . (in_array($kind, ['objednavky', 'zalohy', 'prodejky'], true) ? $kind : 'faktury');
    }

    /** Číslo dokladu jen z písmen a číslic, velkými písmeny (faktura 260102162, prodejka 26PH03224). */
    public static function norm(string $no): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]+/', '', $no) ?? '');
    }

    public static function valid(string $no): bool { return (bool) preg_match('/^[0-9A-Z]{6,14}$/', $no); }

    /** Druh daňového dokladu k objednávce: prodejka (26PH…), jinak faktura. */
    public static function kindOf(string $invoiceNo): string { return str_contains(self::norm($invoiceNo), 'PH') ? 'prodejky' : 'faktury'; }

    public static function path(string $no, string $kind = 'faktury'): string
    {
        return self::dir($kind) . '/' . self::norm($no) . '.pdf';
    }

    public static function has(string $no, string $kind = 'faktury'): bool
    {
        $no = self::norm($no);
        return $no !== '' && is_file(self::path($no, $kind));
    }

    /** Doklad, který se v PDF nenabízí (není e-shopový – středisko jiné než 4kluciodko). Pomocník ho označí, ať se nevrací v seznamu. */
    public static function skipped(string $no, string $kind = 'faktury'): bool
    {
        $no = self::norm($no);
        return $no !== '' && is_file(self::dir($kind) . '/' . $no . '.skip');
    }

    public static function skip(string $no, string $kind = 'faktury'): ?string
    {
        $no = self::norm($no);
        if (!self::valid($no)) return 'neplatné číslo dokladu';
        if (!is_dir(self::dir($kind))) @mkdir(self::dir($kind), 0775, true);
        return file_put_contents(self::dir($kind) . '/' . $no . '.skip', date('c')) === false ? 'nelze uložit' : null;
    }

    /** Doklady z portálu, ke kterým ještě není PDF (nejnovější první). */
    public static function missing(int $limit, string $kind = 'faktury'): array
    {
        $rows = $kind === 'objednavky'
            ? Db::all("SELECT order_no AS no FROM orders WHERE source = 'eshop' AND order_no LIKE '8810%' ORDER BY invoice_date DESC, id DESC")
            : ($kind === 'prodejky'
                ? Db::all("SELECT invoice_no AS no FROM orders WHERE source IN ('eshop','prodejna') AND UPPER(invoice_no) LIKE '%PH%' ORDER BY invoice_date DESC, id DESC")
                : Db::all("SELECT invoice_no AS no FROM orders WHERE source IN ('eshop','faktura') AND invoice_no <> '' AND UPPER(invoice_no) NOT LIKE '%PH%' ORDER BY invoice_date DESC, id DESC"));
        $out = [];
        foreach ($rows as $r) {
            $no = self::norm((string) $r['no']);
            if (!self::valid($no) || self::has($no, $kind) || self::skipped($no, $kind) || in_array($no, $out, true)) continue;
            $out[] = $no;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Uloží PDF poslané pomocníkem (stejné číslo se přepíše). Vrací chybu nebo null. */
    public static function save(string $no, string $base64, string $kind = 'faktury', array $meta = []): ?string
    {
        $no = self::norm($no);
        if (!self::valid($no)) return 'neplatné číslo dokladu';
        $pdf = base64_decode($base64, true);
        if ($pdf === false || !str_starts_with($pdf, '%PDF')) return 'není PDF';
        if (strlen($pdf) > 5 * 1024 * 1024) return 'PDF je větší než 5 MB';
        if (!is_dir(self::dir($kind))) @mkdir(self::dir($kind), 0775, true);
        $tmp = self::path($no, $kind) . '.tmp';
        if (file_put_contents($tmp, $pdf, LOCK_EX) === false || !rename($tmp, self::path($no, $kind))) return 'nelze uložit';
        if ($kind === 'zalohy' || ($kind === 'objednavky' && trim((string) ($meta['email'] ?? '')) !== '')) {
            $email = mb_strtolower(trim((string) ($meta['email'] ?? '')));
            $order = preg_replace('/\D+/', '', (string) ($meta['order_no'] ?? '')) ?? '';
            file_put_contents(self::dir($kind) . '/' . $no . '.json', json_encode(['email' => $email, 'order_no' => $order]), LOCK_EX);
        }
        return null;
    }

    /** Faktura zákazníka podle čísla faktury nebo objednávky a e-mailu z objednávky. */
    public static function find(string $number, string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        $num = self::norm($number);
        if ($email === '' || $num === '') return null;
        return Db::one(
            "SELECT * FROM orders WHERE source IN ('eshop','faktura','prodejna') AND LOWER(email) = ? AND (UPPER(invoice_no) = ? OR order_no = ?) ORDER BY invoice_date DESC LIMIT 1",
            [$email, $num, $num]
        );
    }

    /** Objednávka, kterou portál ještě nezná z faktury (čerstvá, nevyřízená): číslo z <číslo>.json vedle PDF, e-mail musí sedět. */
    public static function findOrderSidecar(string $number, string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        $num = preg_replace('/\D+/', '', $number) ?? '';
        if ($email === '' || $num === '') return null;
        $m = json_decode((string) @file_get_contents(self::dir('objednavky') . '/' . $num . '.json'), true);
        return is_array($m) && ($m['email'] ?? '') !== '' && $m['email'] === $email ? $num : null;
    }

    /** Zálohová faktura podle čísla zálohy nebo objednávky a e-mailu odběratele (z <číslo>.json). Vrací číslo zálohy. */
    public static function findAdvance(string $number, string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        $num = preg_replace('/\D+/', '', $number) ?? '';
        if ($email === '' || $num === '') return null;
        $hit = function (string $f) use ($email): bool {
            $m = json_decode((string) @file_get_contents($f), true);
            return is_array($m) && ($m['email'] ?? '') !== '' && $m['email'] === $email;
        };
        $f = self::dir('zalohy') . '/' . $num . '.json';
        if (is_file($f) && $hit($f)) return $num;
        foreach ((array) glob(self::dir('zalohy') . '/*.json') as $g) {
            $m = json_decode((string) @file_get_contents($g), true);
            if (is_array($m) && ($m['order_no'] ?? '') === $num && $hit($g)) return basename($g, '.json');
        }
        return null;
    }
}
