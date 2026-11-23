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
 * Druh dokladu: 'faktury' | 'objednavky' | 'zalohy'.
 */
final class Invoices
{
    public static function dir(string $kind = 'faktury'): string
    {
        return APP_ROOT . '/storage/' . (in_array($kind, ['objednavky', 'zalohy'], true) ? $kind : 'faktury');
    }

    public static function path(string $no, string $kind = 'faktury'): string
    {
        return self::dir($kind) . '/' . preg_replace('/\D+/', '', $no) . '.pdf';
    }

    public static function has(string $no, string $kind = 'faktury'): bool
    {
        $no = preg_replace('/\D+/', '', $no) ?? '';
        return $no !== '' && is_file(self::path($no, $kind));
    }

    /** Doklady z portálu, ke kterým ještě není PDF (nejnovější první). */
    public static function missing(int $limit, string $kind = 'faktury'): array
    {
        $rows = $kind === 'objednavky'
            ? Db::all("SELECT order_no AS no FROM orders WHERE source = 'eshop' AND order_no LIKE '8810%' ORDER BY invoice_date DESC, id DESC")
            : Db::all("SELECT invoice_no AS no FROM orders WHERE source IN ('eshop','faktura') AND invoice_no <> '' ORDER BY invoice_date DESC, id DESC");
        $out = [];
        foreach ($rows as $r) {
            $no = (string) $r['no'];
            if (!preg_match('/^\d{6,12}$/', $no) || self::has($no, $kind) || in_array($no, $out, true)) continue;
            $out[] = $no;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Uloží PDF poslané pomocníkem (stejné číslo se přepíše). Vrací chybu nebo null. */
    public static function save(string $no, string $base64, string $kind = 'faktury', array $meta = []): ?string
    {
        $no = preg_replace('/\D+/', '', $no) ?? '';
        if (!preg_match('/^\d{6,12}$/', $no)) return 'neplatné číslo dokladu';
        $pdf = base64_decode($base64, true);
        if ($pdf === false || !str_starts_with($pdf, '%PDF')) return 'není PDF';
        if (strlen($pdf) > 5 * 1024 * 1024) return 'PDF je větší než 5 MB';
        if (!is_dir(self::dir($kind))) @mkdir(self::dir($kind), 0775, true);
        $tmp = self::path($no, $kind) . '.tmp';
        if (file_put_contents($tmp, $pdf, LOCK_EX) === false || !rename($tmp, self::path($no, $kind))) return 'nelze uložit';
        if ($kind === 'zalohy') {
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
        $num = preg_replace('/\D+/', '', $number) ?? '';
        if ($email === '' || $num === '') return null;
        return Db::one(
            "SELECT * FROM orders WHERE source IN ('eshop','faktura') AND LOWER(email) = ? AND (invoice_no = ? OR order_no = ?) ORDER BY invoice_date DESC LIMIT 1",
            [$email, $num, $num]
        );
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
