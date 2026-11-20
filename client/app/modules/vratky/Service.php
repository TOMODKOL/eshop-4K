<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Config;
use App\Core\Db;

/** Logika vratek: hledání objednávky, lhůty, založení vratky, stavy. */
final class Service
{
    public const ACTIONS = ['vratit' => 'Vrácení', 'vymenit' => 'Výměna velikosti', 'reklamovat' => 'Reklamace'];

    /** Stavy vratky v pořadí, jak jdou za sebou. */
    public const STATUS = [
        'novy'      => 'Čeká na odeslání',
        'na_ceste'  => 'Na cestě k nám',
        'dorazilo'  => 'Dorazilo – kontrolujeme',
        'vyrizeno'  => 'Vyřízeno',
        'zamitnuto' => 'Zamítnuto',
    ];

    public const REASONS = ['Nesedí velikost', 'Nelíbí se mi', 'Jiné, než jsem čekal/a', 'Rozmyslel/a jsem si to', 'Přišlo poškozené nebo jiné zboží'];

    public const SHIP = [
        'zbox'   => 'Z-BOX Zásilkovny',
        'vm'     => 'Výdejní místo Zásilkovny',
        'osobne' => 'Osobně na prodejně',
    ];

    // ---------- objednávky ----------

    public static function normalizeNumber(string $s): string
    {
        return preg_replace('/\s+/', '', trim($s)) ?? '';
    }

    public static function findOrder(string $email, string $number): ?array
    {
        $email = mb_strtolower(trim($email));
        $num = self::normalizeNumber($number);
        if ($email === '' || $num === '') return null;
        return Db::one(
            "SELECT * FROM orders WHERE source = 'eshop' AND LOWER(email) = ? AND (order_no = ? OR invoice_no = ?) ORDER BY invoice_date DESC LIMIT 1",
            [$email, $num, $num]
        );
    }

    /**
     * Hledání dokladu v administraci: číslo faktury/prodejky/objednávky, jméno, telefon, e-mail,
     * nebo název či kód zboží (u prodejek je to jediná stopa – zákazník na nich není).
     */
    public static function searchOrders(string $q, string $from = '', string $till = ''): array
    {
        $q = trim($q);
        $where = [];
        $p = [];
        if ($q !== '') {
            $num = self::normalizeNumber($q);
            $like = '%' . $q . '%';
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            $w = '(o.invoice_no LIKE ? OR o.order_no LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ?'
               . ' OR o.id IN (SELECT order_id FROM order_items WHERE name LIKE ? OR code LIKE ?)';
            array_push($p, '%' . $num . '%', '%' . $num . '%', $like, $like, $like, $like);
            if (strlen($digits) >= 6) { $w .= ' OR REPLACE(REPLACE(o.phone, \' \', \'\'), \'+420\', \'\') LIKE ?'; $p[] = '%' . substr($digits, -9) . '%'; }
            $where[] = $w . ')';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'o.invoice_date >= ?'; $p[] = $from; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $till)) { $where[] = 'o.invoice_date <= ?'; $p[] = $till; }
        if (!$where) return [];
        $rows = Db::all('SELECT o.* FROM orders o WHERE ' . implode(' AND ', $where) . ' ORDER BY o.invoice_date DESC, o.id DESC LIMIT 40', $p);
        foreach ($rows as &$r) {
            $its = array_filter(Db::all('SELECT name, unit_price FROM order_items WHERE order_id = ? ORDER BY line_no, id LIMIT 8', [$r['id']]),
                fn($i) => !self::isServiceLine($i));
            $r['item_names'] = array_slice(array_column($its, 'name'), 0, 6);
        }
        return $rows;
    }

    public static function order(int $id): ?array
    {
        return Db::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    /** Položky objednávky + kolik kusů z nich ještě jde vrátit/reklamovat. */
    public static function items(int $orderId): array
    {
        $items = Db::all('SELECT * FROM order_items WHERE order_id = ? AND qty > 0 AND unit_price >= 0 ORDER BY line_no, id', [$orderId]);
        foreach ($items as &$i) {
            $used = (int) Db::value(
                "SELECT COALESCE(SUM(l.qty),0) FROM return_lines l JOIN returns r ON r.id = l.return_id
                 WHERE l.item_id = ? AND r.status <> 'zamitnuto'", [$i['id']]);
            $i['qty_int'] = (int) floor((float) $i['qty']);
            $i['qty_left'] = max(0, $i['qty_int'] - $used);
            $i['sizes'] = self::sizeOptions($i['name']);
        }
        return array_values(array_filter($items, fn($i) => !self::isServiceLine($i)));
    }

    /** Doprava, platba, poukázky apod. se vracet nedají. */
    public static function isServiceLine(array $i): bool
    {
        $n = mb_strtolower($i['name']);
        foreach (['doprava', 'dopravné', 'poštovné', 'zásilkovna', 'ppl', 'dobírka', 'platba', 'zaokrouhlení', 'sleva', 'poukaz'] as $w) {
            if (str_starts_with($n, $w)) return true;
        }
        return (float) $i['unit_price'] <= 0;
    }

    /** Lhůty z data faktury. */
    public static function deadlines(array $order): array
    {
        $d = new \DateTimeImmutable($order['invoice_date']);
        $ret = $d->modify('+' . (int) Config::get('vratky.return_days_from_invoice', 21) . ' days');
        $claim = $d->modify('+' . (int) Config::get('vratky.claim_months', 24) . ' months');
        $today = new \DateTimeImmutable('today');
        return [
            'return_until' => $ret->format('Y-m-d'),
            'claim_until' => $claim->format('Y-m-d'),
            'can_return' => $today <= $ret,
            'can_claim' => $today <= $claim,
        ];
    }

    /** Nabídka velikostí pro výměnu podle velikosti na konci názvu položky. */
    public static function sizeOptions(string $name): array
    {
        $apparel = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'];
        $combo = ['XS/S', 'S/M', 'M/L', 'L/XL', 'XL/XXL'];
        if (!preg_match('/\s(\S+)$/u', trim($name), $m)) return [];
        $last = mb_strtoupper($m[1]);
        if (in_array($last, $apparel, true)) return array_values(array_diff($apparel, [$last]));
        if (in_array($last, $combo, true)) return array_values(array_diff($combo, [$last]));
        if (preg_match('/^(\d{2})([.,]5)?$/', $last, $mm)) {
            $n = (float) str_replace(',', '.', $last);
            if ($n >= 30 && $n <= 50) {
                $out = [];
                for ($x = max(30, $n - 3); $x <= min(50, $n + 3); $x += 0.5) {
                    if (abs($x - $n) < 0.01) continue;
                    $out[] = str_replace('.', ',', (string) $x);
                }
                return $out;
            }
        }
        return [];
    }

    // ---------- založení vratky ----------

    /**
     * Ověří vstup z formuláře a vrátí [chyby, data].
     * $post = $_POST, $files = $_FILES
     */
    public static function validate(array $order, array $items, array $post, array $files): array
    {
        $errors = [];
        $lines = [];
        $dl = self::deadlines($order);
        $byId = [];
        foreach ($items as $i) $byId[(int) $i['id']] = $i;

        foreach ((array) ($post['act'] ?? []) as $id => $act) {
            $id = (int) $id;
            if ($act === '' || !isset($byId[$id])) continue;
            if (!isset(self::ACTIONS[$act])) { $errors[] = 'Neznámá volba u položky.'; continue; }
            $it = $byId[$id];
            $qty = max(1, (int) ($post['qty'][$id] ?? 1));
            if ($qty > $it['qty_left']) { $errors[] = 'U položky „' . $it['name'] . '“ lze vybrat nejvýš ' . $it['qty_left'] . ' ks.'; continue; }
            if (in_array($act, ['vratit', 'vymenit'], true) && !$dl['can_return']) { $errors[] = 'Lhůta pro vrácení a výměnu u této objednávky už uplynula. Zboží můžete reklamovat.'; continue; }
            if ($act === 'reklamovat' && !$dl['can_claim']) { $errors[] = 'Lhůta pro reklamaci (24 měsíců) už uplynula.'; continue; }
            $line = ['item' => $it, 'action' => $act, 'qty' => $qty, 'reason' => '', 'size_to' => '', 'defect_text' => '', 'photos' => []];
            if ($act === 'vratit') {
                $r = (string) ($post['reason'][$id] ?? '');
                if (!in_array($r, self::REASONS, true)) $errors[] = 'Vyberte důvod vrácení u „' . $it['name'] . '“.';
                $line['reason'] = $r;
            } elseif ($act === 'vymenit') {
                $s = trim((string) ($post['size_to'][$id] ?? ''));
                if ($s === '' || mb_strlen($s) > 40) $errors[] = 'Napište, za jakou velikost chcete „' . $it['name'] . '“ vyměnit.';
                $line['size_to'] = $s;
            } else {
                $t = trim((string) ($post['defect'][$id] ?? ''));
                if (mb_strlen($t) < 5) $errors[] = 'Popište vadu u „' . $it['name'] . '“.';
                $line['defect_text'] = mb_substr($t, 0, 3000);
                [$photos, $perr] = Photos::fromUpload($files['photos'] ?? null, $id);
                $errors = array_merge($errors, $perr);
                $line['photos'] = $photos;
            }
            $lines[] = $line;
        }
        if (!$lines) $errors[] = 'Vyberte aspoň jednu položku a co s ní chcete udělat.';

        $ship = (string) ($post['ship'] ?? '');
        if (!isset(self::SHIP[$ship])) $errors[] = 'Vyberte, jak nám zboží pošlete.';

        $calc = self::calc($lines, $ship);
        $account = self::normalizeAccount((string) ($post['account'] ?? ''));
        if ($calc['refund'] > 0 && !self::validAccount($account)) $errors[] = 'Vyplňte číslo účtu pro vrácení peněz (např. 123456789/0800).';

        if (empty($post['agree'])) $errors[] = 'Potvrďte prosím souhlas s podmínkami vrácení a reklamace.';

        $phone = preg_replace('/[^\d+ ]/', '', (string) ($post['phone'] ?? $order['phone'])) ?? '';

        return [$errors, [
            'lines' => $lines, 'ship' => $ship, 'account' => $account,
            'note' => mb_substr(trim((string) ($post['note'] ?? '')), 0, 2000),
            'phone' => mb_substr(trim($phone), 0, 30),
            'calc' => $calc,
        ]];
    }

    /**
     * Reklamace zakládaná prodejnou v administraci (zboží z prodejny i z e-shopu).
     * Jen reklamace, bez souhlasu s podmínkami a bez čísla účtu. Kontakt zadá prodejna.
     */
    public static function validateAdmin(array $order, array $items, array $post, array $files): array
    {
        $acts = [];
        foreach ((array) ($post['act'] ?? []) as $id => $a) if ($a === 'reklamovat') $acts[$id] = 'reklamovat';
        $post['act'] = $acts;
        $post['agree'] = '1';
        $post['ship'] = isset(self::SHIP[(string) ($post['ship'] ?? '')]) ? $post['ship'] : 'osobne';
        [$errors, $data] = self::validate($order, $items, $post, $files);
        $errors = array_values(array_filter($errors, fn($e) => !str_starts_with($e, 'Vyberte aspoň jednu položku')));
        if (!$data['lines']) $errors[] = 'Zaškrtněte aspoň jednu reklamovanou položku.';
        $name = mb_substr(trim((string) ($post['customer_name'] ?? '')), 0, 190);
        $email = mb_strtolower(trim((string) ($post['email'] ?? '')));
        if ($name === '') $errors[] = 'Vyplňte jméno zákazníka.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail zákazníka nemá správný tvar.';
        if ($email === '' && trim((string) ($post['phone'] ?? '')) === '') $errors[] = 'Vyplňte telefon nebo e-mail, ať se zákazníkovi můžeme ozvat.';
        if ($post['ship'] !== 'osobne' && $email === '') $errors[] = 'Pro zaslání Zásilkovnou je potřeba e-mail (dostane na něj podací kód).';
        $data['customer_name'] = $name;
        $data['email'] = $email;
        return [$errors, $data];
    }

    /** Peníze: vracené zboží; poštovné jen u čistého vrácení zaslaného Zásilkovnou. */
    public static function calc(array $lines, string $ship): array
    {
        $refund = 0.0;
        foreach ($lines as $l) if ($l['action'] === 'vratit') $refund += (float) $l['item']['unit_price'] * $l['qty'];
        $onlyReturn = $lines && count(array_filter($lines, fn($l) => $l['action'] !== 'vratit')) === 0;
        $postageApplies = $onlyReturn && $ship !== 'osobne';
        $postage = Config::get('vratky.return_postage');
        return [
            'refund' => round($refund, 2),
            'postage_applies' => $postageApplies,
            'postage' => $postageApplies && $postage !== null ? (float) $postage : null,
        ];
    }

    public static function normalizeAccount(string $s): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($s)) ?? '');
    }

    public static function validAccount(string $a): bool
    {
        if (preg_match('/^(\d{1,6}-)?\d{2,10}\/\d{4}$/', $a)) return true;
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $a)) return true; // IBAN
        return false;
    }

    public static function nextNumber(): string
    {
        $y = date('Y');
        $last = (string) Db::value("SELECT no FROM returns WHERE no LIKE ? ORDER BY no DESC LIMIT 1", ['V-' . $y . '-%']);
        $n = $last ? (int) substr($last, -4) + 1 : 1;
        return sprintf('V-%s-%04d', $y, $n);
    }

    /** Uloží vratku. Vrací její řádek z databáze. */
    public static function create(array $order, array $data, ?array $byUser = null): array
    {
        $id = Db::tx(function () use ($order, $data, $byUser) {
            $hasClaim = (bool) array_filter($data['lines'], fn($l) => $l['action'] === 'reklamovat');
            $now = Db::now();
            // reklamace převzatá na prodejně je rovnou u nás
            $status = ($byUser && $data['ship'] === 'osobne') ? 'dorazilo' : 'novy';
            $rid = Db::insert('returns', [
                'no' => self::nextNumber(),
                'token' => bin2hex(random_bytes(20)),
                'order_id' => $order['id'],
                'email' => $data['email'] ?? $order['email'],
                'customer_name' => $data['customer_name'] ?? $order['customer_name'],
                'phone' => $data['phone'],
                'status' => $status,
                'ship_method' => $data['ship'],
                'bank_account' => $data['account'],
                'note' => $data['note'],
                'refund_total' => $data['calc']['refund'],
                'postage' => $data['calc']['postage'],
                'postage_applies' => $data['calc']['postage_applies'] ? 1 : 0,
                'claim_deadline' => $hasClaim ? date('Y-m-d', strtotime('+' . (int) Config::get('vratky.claim_resolve_days', 30) . ' days')) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($data['lines'] as $l) {
                $lid = Db::insert('return_lines', [
                    'return_id' => $rid,
                    'item_id' => $l['item']['id'],
                    'item_code' => $l['item']['code'],
                    'item_name' => $l['item']['name'],
                    'unit_price' => $l['item']['unit_price'],
                    'action' => $l['action'],
                    'qty' => $l['qty'],
                    'reason' => $l['reason'],
                    'size_to' => $l['size_to'],
                    'defect_text' => $l['defect_text'],
                ]);
                foreach ($l['photos'] as $p) Photos::attach($lid, $p);
            }
            Db::insert('return_events', ['return_id' => $rid, 'status' => $status,
                'note' => $byUser ? ($status === 'dorazilo' ? 'Reklamaci jsme převzali na prodejně.' : 'Reklamaci založila prodejna.') : 'Vratku vytvořil zákazník.',
                'public' => 1, 'user_id' => $byUser['id'] ?? null, 'created_at' => $now]);
            return $rid;
        });
        return self::get($id);
    }

    public static function get(int $id): ?array { return Db::one('SELECT * FROM returns WHERE id = ?', [$id]); }
    public static function byToken(string $t): ?array { return Db::one('SELECT * FROM returns WHERE token = ?', [$t]); }
    public static function byNo(string $no): ?array { return Db::one('SELECT * FROM returns WHERE no = ?', [$no]); }
    public static function lines(int $rid): array { return Db::all('SELECT * FROM return_lines WHERE return_id = ? ORDER BY id', [$rid]); }
    public static function events(int $rid, bool $publicOnly = false): array
    {
        return Db::all('SELECT e.*, u.name AS user_name FROM return_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.return_id = ?' .
            ($publicOnly ? ' AND e.public = 1' : '') . ' ORDER BY e.id', [$rid]);
    }

    public static function setStatus(array $ret, string $status, string $note, bool $public, ?int $userId): void
    {
        $now = Db::now();
        if ($status !== '' && $status !== $ret['status']) {
            Db::exec('UPDATE returns SET status = ?, updated_at = ? WHERE id = ?', [$status, $now, $ret['id']]);
        } else {
            $status = '';
        }
        if ($status !== '' || $note !== '') {
            Db::insert('return_events', ['return_id' => $ret['id'], 'status' => $status ?: null, 'note' => $note,
                'public' => $public ? 1 : 0, 'user_id' => $userId, 'created_at' => $now]);
        }
    }

    public static function hasAction(array $lines, string $a): bool
    {
        foreach ($lines as $l) if ($l['action'] === $a) return true;
        return false;
    }

    // ---------- odkaz e-mailem (zákazník si nepamatuje číslo objednávky) ----------

    public static function linkToken(string $email, int $validHours = 24): string
    {
        $email = mb_strtolower(trim($email));
        $exp = time() + $validHours * 3600;
        $payload = $email . '|' . $exp;
        $sig = hash_hmac('sha256', $payload, self::key());
        return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
    }

    /** Vrací e-mail z platného odkazu, jinak null. */
    public static function linkEmail(string $token): ?string
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false) return null;
        $parts = explode('|', $raw);
        if (count($parts) !== 3) return null;
        [$email, $exp, $sig] = $parts;
        if (!hash_equals(hash_hmac('sha256', $email . '|' . $exp, self::key()), $sig)) return null;
        if ((int) $exp < time()) return null;
        return $email;
    }

    private static function key(): string
    {
        $k = (string) Config::get('app_key', '');
        if ($k === '') throw new \RuntimeException('V config.php chybí app_key.');
        return $k;
    }

    public static function ordersByEmail(string $email): array
    {
        $since = (new \DateTimeImmutable('today'))->modify('-' . (int) Config::get('vratky.claim_months', 24) . ' months')->format('Y-m-d');
        return Db::all("SELECT * FROM orders WHERE source = 'eshop' AND LOWER(email) = ? AND invoice_date >= ? ORDER BY invoice_date DESC LIMIT 50",
            [mb_strtolower(trim($email)), $since]);
    }
}
