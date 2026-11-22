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
        'v_reseni'  => 'Posuzujeme u dodavatele',
        'vyrizeno'  => 'Vyřízeno',
        'zamitnuto' => 'Zamítnuto',
    ];

    public const REASONS = ['Nesedí velikost', 'Nelíbí se mi', 'Jiné, než jsem čekal/a', 'Rozmyslel/a jsem si to', 'Přišlo poškozené nebo jiné zboží'];

    public const SHIP = [
        'zbox'   => 'Z-BOX Zásilkovny',
        'vm'     => 'Výdejní místo Zásilkovny',
        'osobne' => 'Osobně na prodejně',
        'vlastni' => 'Vlastní dopravou',
    ];

    /** Zásilkovna (podací kód, poštovné za vrácení) jen u Z-BOXu a výdejního místa. */
    public static function usesPacketa(string $ship): bool
    {
        return in_array($ship, ['zbox', 'vm'], true);
    }

    // ---------- objednávky ----------

    public static function normalizeNumber(string $s): string
    {
        return preg_replace('/\s+/', '', trim($s)) ?? '';
    }

    public static function findOrder(string $email, string $number): ?array
    {
        $email = mb_strtolower(trim($email));
        // „FV 2601 01234“, „č. 8810123456“ → jen číslice (čísla e-shopových objednávek i faktur jsou číselná)
        $num = preg_replace('/\D+/', '', $number) ?? '';
        if ($email === '' || $num === '') return null;
        return Db::one(
            "SELECT * FROM orders WHERE source = 'eshop' AND LOWER(email) = ? AND (order_no = ? OR invoice_no = ?) ORDER BY invoice_date DESC LIMIT 1",
            [$email, $num, $num]
        );
    }

    public static function order(int $id): ?array
    {
        return Db::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    /** Položky objednávky + kolik kusů z nich ještě jde vrátit/reklamovat. $eshop = načíst i velikosti z e-shopu (formulář zákazníka). */
    public static function items(int $orderId, bool $eshop = false): array
    {
        $items = Db::all('SELECT * FROM order_items WHERE order_id = ? AND qty > 0 AND unit_price >= 0 ORDER BY line_no, id', [$orderId]);
        foreach ($items as &$i) {
            $used = (int) Db::value(
                "SELECT COALESCE(SUM(l.qty),0) FROM return_lines l JOIN returns r ON r.id = l.return_id
                 WHERE l.item_id = ? AND r.status <> 'zamitnuto'", [$i['id']]);
            $i['qty_int'] = (int) floor((float) $i['qty']);
            $i['qty_left'] = max(0, $i['qty_int'] - $used);
            $i['sizes'] = self::sizeOptions($i['name']);
            $i['eshop'] = $eshop && $i['qty_left'] > 0 && !self::isServiceLine($i) ? Eshop::sizes(isset($i['stock_id']) ? (int) $i['stock_id'] : null) : null;
            $i['image'] = $eshop && !self::isServiceLine($i) ? Eshop::image(isset($i['stock_id']) ? (int) $i['stock_id'] : null) : '';
            // zboží bez velikostí: e-shop ho ukazuje bez výběru velikosti, nebo (když e-shop nic neřekl) název nekončí velikostí
            $i['no_size'] = $eshop && empty($i['eshop']) && !$i['sizes'] && (Eshop::noSizes(isset($i['stock_id']) ? (int) $i['stock_id'] : null) || !self::nameHasSize($i['name']));
        }
        return array_values(array_filter($items, fn($i) => !self::isServiceLine($i)));
    }

    /** Doprava, platba, poukázky apod. se vracet nedají. */
    public static function isServiceLine(array $i): bool
    {
        $n = mb_strtolower($i['name']);
        foreach (['doprava', 'dopravné', 'poštovné', 'zásilkovna', 'gls', 'ppl', 'dpd', 'balíkovna', 'česká pošta', 'osobní odběr', 'dobírka', 'platba', 'zaokrouhlení', 'sleva', 'poukaz'] as $w) {
            if (str_starts_with($n, $w)) return true;
        }
        return (float) $i['unit_price'] <= 0;
    }

    /** Řádek faktury za dopravu (ne dobírka, ne platba). */
    public static function isShippingLine(array $i): bool
    {
        $n = mb_strtolower($i['name']);
        foreach (['doprava', 'dopravné', 'poštovné', 'zásilkovna', 'gls', 'ppl', 'dpd', 'balíkovna', 'česká pošta'] as $w) {
            if (str_starts_with($n, $w)) return true;
        }
        return false;
    }

    /** Lhůty z data faktury. */
    /**
     * Lhůty: vrácení/výměna 14 dní od převzetí zásilky (vratky.return_days), reklamace 24 měsíců.
     * basis: delivered = známe datum převzetí, waiting = balík ještě nebyl převzat, invoice = odhad podle data faktury.
     */
    public static function deadlines(array $order): array
    {
        if (isset($order['id']) && ($order['parcel_no'] ?? '') !== '') $order = Tracking::refresh($order);
        $today = new \DateTimeImmutable('today');
        $claimM = (int) Config::get('vratky.claim_months', 24);
        if (!empty($order['delivered_date'])) {
            $d = new \DateTimeImmutable($order['delivered_date']);
            $ret = $d->modify('+' . (int) Config::get('vratky.return_days', 14) . ' days');
            $basis = 'delivered';
        } elseif (($order['parcel_carrier'] ?? '') === 'zas' && !empty($order['tracking_checked_at'])) {
            // balík je na cestě nebo čeká na výdejním místě – lhůta ještě neběží
            $d = $today;
            $ret = $today->modify('+' . (int) Config::get('vratky.return_days', 14) . ' days');
            $basis = 'waiting';
        } else {
            $d = new \DateTimeImmutable($order['invoice_date']);
            $ret = $d->modify('+' . (int) Config::get('vratky.return_days_from_invoice', 21) . ' days');
            $basis = 'invoice';
        }
        $claim = $d->modify('+' . $claimM . ' months');
        return [
            'return_until' => $ret->format('Y-m-d'),
            'claim_until' => $claim->format('Y-m-d'),
            'can_return' => $today <= $ret,
            'can_claim' => $today <= $claim,
            'basis' => $basis,
            'delivered' => $basis === 'delivered' ? $d->format('Y-m-d') : null,
        ];
    }

    /** Nabídka velikostí pro výměnu podle velikosti na konci názvu položky. */
    /** Končí název něčím, co vypadá jako velikost (M, L/XL, 43, 42,5, XS-S…)? */
    public static function nameHasSize(string $name): bool
    {
        if (!preg_match('/\s(\S+)$/u', trim($name), $m)) return false;
        return (bool) preg_match('~^(X{0,3}[SML]|XXS|[2-4]XL|\d{1,2}([.,]5)?|[A-Z]{1,3}[/-][A-Z]{1,3}|\d{2}[/-]\d{2})$~u', mb_strtoupper($m[1]));
    }

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
            $line = ['item' => $it, 'action' => $act, 'qty' => $qty, 'reason' => '', 'size_to' => '', 'new_code' => '', 'new_name' => '', 'new_price' => null, 'new_url' => '', 'new_qty' => 0, 'defect_text' => '', 'photos' => []];
            if ($act === 'vratit') {
                $r = (string) ($post['reason'][$id] ?? '');
                // na důvod se neptáme (do 14 dnů lze vrátit bez udání důvodu)
                if (!in_array($r, self::REASONS, true)) $r = '';
                $line['reason'] = $r;
            } elseif ($act === 'vymenit' && (($post['swap_kind'][$id] ?? '') === 'jine' || !empty($it['no_size']))) {
                // výměna za jiné zboží z e-shopu (jen skladem nebo skladem u dodavatele)
                $prod = Eshop::product((string) ($post['other_url'][$id] ?? ''));
                $opt = $prod ? Eshop::option($prod, (string) ($post['other_pick'][$id] ?? '')) : null;
                if (!$prod) $errors[] = 'U „' . $it['name'] . '“ vložte odkaz na zboží z 4kluciodkol.cz, za které ho chcete vyměnit.';
                elseif (!$opt) $errors[] = 'U „' . $it['name'] . '“ vyberte velikost nového zboží.';
                elseif (!$opt['ok']) $errors[] = '„' . $prod['name'] . ($opt['value'] !== '' ? ' ' . $opt['value'] : '') . '“ teď není skladem ani u dodavatele. Vyberte prosím jiné.';
                elseif ($opt['sku'] === $it['code']) $errors[] = 'U „' . $it['name'] . '“ jste vybrali stejné zboží. Pro jinou velikost zvolte „Jinou velikost“.';
                else {
                    $line['size_to'] = $opt['value'];
                    $line['new_code'] = mb_substr($opt['sku'], 0, 60);
                    $line['new_name'] = mb_substr(trim($prod['name'] . ($opt['value'] !== '' && !$prod['single'] ? ' ' . $opt['value'] : '')), 0, 255);
                    $line['new_price'] = (float) $opt['price'];
                    $line['new_qty'] = max(1, min(20, (int) ($post['other_qty'][$id] ?? $qty)));
                    $line['new_url'] = mb_substr($prod['url'], 0, 500);
                }
            } elseif ($act === 'vymenit') {
                $s = trim((string) ($post['size_to'][$id] ?? ''));
                if ($s === '' || mb_strlen($s) > 40) $errors[] = 'Napište, za jakou velikost chcete „' . $it['name'] . '“ vyměnit.';
                elseif (!empty($it['eshop'])) {
                    $tile = Eshop::find($it['eshop'], $s);
                    if (!$tile) $errors[] = 'Vyberte velikost u „' . $it['name'] . '“.';
                    elseif ($tile['current']) $errors[] = 'U „' . $it['name'] . '“ jste vybrali stejnou velikost, jakou máte. Vyberte jinou.';
                    elseif ($tile['cls'] !== 'zelena') $errors[] = 'Velikost ' . $tile['value'] . ' u „' . $it['name'] . '“ teď není skladem ani u dodavatele. Vyberte jinou, nebo vyměňte za jiné zboží.';
                    else $line['new_code'] = mb_substr($tile['sku'], 0, 60);
                }
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

        $pay = (string) ($post['pay_method'] ?? '');
        $calc = self::calc($lines, $ship, $order, $pay);
        $account = self::normalizeAccount((string) ($post['account'] ?? ''));
        if ($calc['balance'] > 0 && !self::validAccount($account)) $errors[] = 'Vyplňte číslo účtu pro vrácení peněz (např. 123456789/0800).';
        if ($calc['balance'] < 0 && !in_array($pay, ['prevod', 'dobirka'], true)) $errors[] = 'Vyberte, jak doplatíte rozdíl v ceně.';

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
     * Peníze: vracené zboží; rozdíl ceny při výměně za jiné zboží; poštovné jen u čistého vrácení zaslaného Zásilkovnou;
     * doprava, když zbytek objednávky klesne pod hranici dopravy zdarma. balance > 0 vracíme, < 0 zákazník doplácí.
     */
    public static function calc(array $lines, string $ship, ?array $order = null, string $pay = ''): array
    {
        $refund = 0.0; $diff = 0.0;
        foreach ($lines as $l) {
            if ($l['action'] === 'vratit') $refund += (float) $l['item']['unit_price'] * $l['qty'];
            if ($l['action'] === 'vymenit' && ($l['new_price'] ?? null) !== null) $diff += (float) $l['item']['unit_price'] * $l['qty'] - (float) $l['new_price'] * ((int) ($l['new_qty'] ?? 0) ?: $l['qty']);
        }
        $onlyReturn = $lines && count(array_filter($lines, fn($l) => $l['action'] !== 'vratit')) === 0;
        $postageApplies = $onlyReturn && self::usesPacketa($ship);
        $postage = Config::get('vratky.return_postage');
        $c = [
            'refund' => round($refund, 2),
            'swap_diff' => round($diff, 2),
            'postage_applies' => $postageApplies,
            'postage' => $postageApplies && $postage !== null ? (float) $postage : null,
            'ship_charge' => $order ? self::freeShippingCharge($order, $refund) : 0.0,
        ];
        $c['balance'] = round($c['refund'] + $c['swap_diff'] - (float) ($c['postage'] ?? 0) - $c['ship_charge'], 2);
        $c['pay_method'] = $c['balance'] < 0 && in_array($pay, ['prevod', 'dobirka'], true) ? $pay : '';
        $c['cod_fee'] = $c['pay_method'] === 'dobirka' ? (float) Config::get('vratky.cod_fee', 69) : 0.0;
        return $c;
    }

    /** Pravidla dopravy (ceník + hranice dopravy zdarma) platná k datu objednávky. */
    public static function shippingRule(string $date): ?array
    {
        $pick = null;
        foreach ((array) Config::get('vratky.shipping_rules', []) as $r) if ($r['from'] <= $date) $pick = $r;
        return $pick;
    }

    /** Doprava na faktuře: [zaplaceno, název]. */
    public static function orderShipping(int $orderId): array
    {
        $paid = 0.0; $name = '';
        foreach (Db::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY line_no, id', [$orderId]) as $i) {
            if (!self::isShippingLine($i)) continue;
            if ($name === '') $name = $i['name'];
            $paid += max(0, (float) $i['unit_price']) * (float) $i['qty'];
        }
        return [$paid, $name];
    }

    /** Údaje pro formulář a výpočet: [cena dopravy navíc, zboží zbývající před touto vratkou, hranice]. */
    public static function freeShippingInfo(array $order): array
    {
        $rule = self::shippingRule((string) $order['invoice_date']);
        [$paid, $name] = self::orderShipping((int) $order['id']);
        if (!$rule || $name === '') return [0.0, 0.0, 0.0];
        $prices = (array) $rule['prices'];
        uksort($prices, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $price = null; $n = mb_strtolower($name);
        foreach ($prices as $k => $v) if (str_starts_with($n, $k)) { $price = (float) $v; break; }
        if ($price === null || $price <= $paid) return [0.0, 0.0, 0.0];
        $total = 0.0;
        foreach (Db::all('SELECT * FROM order_items WHERE order_id = ? AND qty > 0', [$order['id']]) as $i) {
            if (!self::isServiceLine($i)) $total += (float) $i['unit_price'] * (float) $i['qty'];
        }
        if ($total < (float) $rule['free_from']) return [0.0, 0.0, 0.0];
        if ((float) Db::value("SELECT COALESCE(SUM(ship_charge),0) FROM returns WHERE order_id = ? AND status <> 'zamitnuto'", [$order['id']]) > 0) return [0.0, 0.0, 0.0];
        $back = (float) Db::value("SELECT COALESCE(SUM(l.unit_price * l.qty),0) FROM return_lines l JOIN returns r ON r.id = l.return_id
            WHERE r.order_id = ? AND l.action = 'vratit' AND r.status <> 'zamitnuto'", [$order['id']]);
        return [round($price - $paid, 2), round($total - $back, 2), (float) $rule['free_from']];
    }

    /** Zbytek objednávky po vrácení klesne pod hranici dopravy zdarma → doprava podle ceníku. Při vrácení všeho se neúčtuje. */
    public static function freeShippingCharge(array $order, float $refundNow): float
    {
        [$charge, $left, $limit] = self::freeShippingInfo($order);
        if ($charge <= 0 || $refundNow <= 0) return 0.0;
        $after = $left - $refundNow;
        return ($after > 0.005 && $after < $limit) ? $charge : 0.0;
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

    /** Číslo přijaté objednávky v Pohodě pro výměnu: 991 + 7 číslic, nezávislé na roce (Pohoda ho přijme jako numberRequested). */
    public static function nextXorder(): string
    {
        $pre = (string) Config::get('vratky.xorder_prefix', '991');
        $last = (string) Db::value('SELECT xorder_no FROM returns WHERE xorder_no LIKE ? ORDER BY xorder_no DESC LIMIT 1', [$pre . '%']);
        $n = $last !== '' ? (int) substr($last, strlen($pre)) + 1 : 1;
        return $pre . str_pad((string) $n, 10 - strlen($pre), '0', STR_PAD_LEFT);
    }

    /** Uloží vratku. Vrací její řádek z databáze. */
    public static function create(array $order, array $data, ?array $byUser = null): array
    {
        $id = Db::tx(function () use ($order, $data, $byUser) {
            $hasClaim = (bool) array_filter($data['lines'], fn($l) => $l['action'] === 'reklamovat');
            // reklamaci od zákazníka z webu založí prodejna v Pohodě (agenda Reklamace) a pomocník je propojí podle čísla V-…
            $pohoda = $hasClaim ? 'cekame' : '';
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
                'ship_charge' => (float) ($data['calc']['ship_charge'] ?? 0),
                'swap_diff' => (float) ($data['calc']['swap_diff'] ?? 0),
                'pay_method' => (string) ($data['calc']['pay_method'] ?? ''),
                'cod_fee' => (float) ($data['calc']['cod_fee'] ?? 0),
                'claim_deadline' => $hasClaim ? date('Y-m-d', strtotime('+' . (int) Config::get('vratky.claim_resolve_days', 30) . ' days')) : null,
                'pohoda_state' => $pohoda,
                // výměna: nové zboží → přijatá objednávka v Pohodě (rezervace), doplatek převodem → zálohová faktura
                'xorder_no' => array_filter($data['lines'], fn($l) => $l['action'] === 'vymenit') ? self::nextXorder() : null,
                'xorder_state' => array_filter($data['lines'], fn($l) => $l['action'] === 'vymenit') ? 'ceka' : '',
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
                    'new_code' => $l['new_code'] ?? '',
                    'new_name' => $l['new_name'] ?? '',
                    'new_price' => $l['new_price'] ?? null,
                    'new_qty' => (int) ($l['new_qty'] ?? 0),
                    'new_url' => $l['new_url'] ?? '',
                    'defect_text' => $l['defect_text'],
                ]);
                foreach ($l['photos'] as $p) Photos::attach($lid, $p);
            }
            Db::insert('return_events', ['return_id' => $rid, 'status' => $status,
                'note' => 'Vytvořil zákazník na webu.',
                'public' => 1, 'user_id' => $byUser['id'] ?? null, 'created_at' => $now]);
            return $rid;
        });
        return self::get($id);
    }

    /** Číslo, které vidí lidé: u reklamace zapsané v Pohodě její číslo (26RZ…), jinak číslo z portálu. */
    public static function displayNo(array $ret): string
    {
        return (string) ($ret['pohoda_no'] ?? '') !== '' ? (string) $ret['pohoda_no'] : (string) $ret['no'];
    }

    /** Bilance vratky: kladná = vracíme zákazníkovi, záporná = zákazník doplácí (výměna za dražší zboží, i s dobírkou). */
    public static function balance(array $ret): float
    {
        $p = (float) $ret['refund_total'] + (float) ($ret['swap_diff'] ?? 0);
        if ($ret['postage_applies'] && $ret['postage'] !== null) $p -= (float) $ret['postage'];
        $p -= (float) ($ret['ship_charge'] ?? 0);
        $p += (float) ($ret['ship_refund'] ?? 0);
        $p -= (float) ($ret['cod_fee'] ?? 0);
        return round($p, 2);
    }

    /** Kolik zákazníkovi skutečně pošleme. */
    public static function payout(array $ret): float { return max(0.0, self::balance($ret)); }

    /** Kolik zákazník doplatí. */
    public static function toPay(array $ret): float { return max(0.0, -self::balance($ret)); }

    public const PAY = ['prevod' => 'Převodem', 'dobirka' => 'Na dobírku'];

    public static function actionLabel(array $l): string
    {
        if ($l['action'] === 'vymenit' && ($l['new_name'] ?? '') !== '') return 'Výměna za jiné zboží';
        return self::ACTIONS[$l['action']] ?? (string) $l['action'];
    }

    /** Text „za co se měnilo“ u řádku výměny. */
    public static function swapText(array $l): string
    {
        $nq = (int) ($l['new_qty'] ?? 0);
        return ($l['new_name'] ?? '') !== '' ? 'Za: ' . $l['new_name'] . ($nq > 1 ? ' × ' . $nq . ' ks' : '') : 'Za velikost ' . $l['size_to'];
    }

    // ---------- Pohoda: doklad k vratce (opravný daňový doklad / faktura) ----------

    public const DOC_STATE = ['ceka' => 'Čeká na vystavení v Pohodě', 'navrh' => 'Návrh čeká na schválení', 'hotovo' => 'Vystaveno v Pohodě', 'chyba' => 'Doklad se nepodařilo vystavit'];
    public const DOC_TYPE = ['odd' => 'Opravný daňový doklad', 'fv' => 'Faktura'];

    /**
     * Vrácení dopravy: vrací se celá objednávka (všechno zboží v nezamítnutých vratkách) → doprava,
     * kterou zákazník zaplatil, nejvýš nejlevnější způsob dopravy (vratky.refund_shipping_max). Jen jednou na objednávku.
     */
    public static function shippingRefund(array $ret): float
    {
        if (!$ret['order_id']) return 0.0;
        $all = Db::all('SELECT * FROM order_items WHERE order_id = ? AND qty > 0 ORDER BY line_no, id', [$ret['order_id']]);
        $paid = 0.0;
        foreach ($all as $i) if (self::isShippingLine($i) && (float) $i['unit_price'] > 0) $paid += (float) $i['unit_price'] * (float) $i['qty'];
        if ($paid <= 0) return 0.0;
        foreach ($all as $i) {
            if (self::isServiceLine($i)) continue;
            $back = (int) Db::value("SELECT COALESCE(SUM(l.qty),0) FROM return_lines l JOIN returns r ON r.id = l.return_id
                WHERE l.item_id = ? AND l.action = 'vratit' AND r.status <> 'zamitnuto'", [$i['id']]);
            if ($back < (int) floor((float) $i['qty'])) return 0.0;
        }
        $already = (float) Db::value("SELECT COALESCE(SUM(ship_refund),0) FROM returns WHERE order_id = ? AND id <> ? AND status <> 'zamitnuto'", [$ret['order_id'], $ret['id']]);
        if ($already > 0) return 0.0;
        $max = Config::get('vratky.refund_shipping_max');
        return round($max === null ? $paid : min($paid, (float) $max), 2);
    }

    /**
     * Co bude na dokladu v Pohodě. Ceny s DPH. Záporný součet = opravný daňový doklad (vracíme peníze),
     * 0 a víc = faktura (výměna, doplatek). Reklamované položky se řeší v agendě Reklamace, sem nepatří.
     */
    public static function docPlan(array $ret, ?array $lines = null): array
    {
        $lines ??= self::lines((int) $ret['id']);
        $items = [];
        foreach ($lines as $l) {
            if ($l['action'] === 'vratit') {
                $items[] = ['kind' => 'zbozi', 'code' => $l['item_code'], 'name' => $l['item_name'], 'qty' => -(int) $l['qty'], 'unit_price' => (float) $l['unit_price']];
            } elseif ($l['action'] === 'vymenit') {
                $items[] = ['kind' => 'zbozi', 'code' => $l['item_code'], 'name' => $l['item_name'], 'qty' => -(int) $l['qty'], 'unit_price' => (float) $l['unit_price'], 'swap' => true];
                $other = ($l['new_name'] ?? '') !== '' && ($l['new_price'] ?? null) !== null;
                $items[] = ['kind' => 'nove', 'code' => (string) ($l['new_code'] ?? ''),
                    'name' => $other ? (string) $l['new_name'] : self::swapSize($l['item_name'], (string) $l['size_to']),
                    'qty' => $other ? ((int) ($l['new_qty'] ?? 0) ?: (int) $l['qty']) : (int) $l['qty'], 'unit_price' => $other ? (float) $l['new_price'] : (float) $l['unit_price'], 'for_code' => $l['item_code'], 'other' => $other];
            }
        }
        if (!$items) return ['type' => null, 'total' => 0.0, 'items' => []];
        if ($ret['postage_applies'] && $ret['postage'] !== null && (float) $ret['postage'] > 0) {
            $items[] = ['kind' => 'postovne', 'code' => '', 'name' => 'Poštovné za vrácení zboží', 'qty' => 1, 'unit_price' => (float) $ret['postage']];
        }
        if ((float) ($ret['ship_charge'] ?? 0) > 0) {
            $items[] = ['kind' => 'doprava_navic', 'code' => '', 'name' => 'Doprava – objednávka po vrácení nesplňuje dopravu zdarma', 'qty' => 1, 'unit_price' => (float) $ret['ship_charge']];
        }
        if ((float) ($ret['cod_fee'] ?? 0) > 0) {
            $items[] = ['kind' => 'dobirka', 'code' => '', 'name' => 'Dobírka – doplatek za výměnu', 'qty' => 1, 'unit_price' => (float) $ret['cod_fee']];
        }
        if ((float) ($ret['ship_refund'] ?? 0) > 0) {
            $items[] = ['kind' => 'doprava', 'code' => '', 'name' => 'Vrácení dopravy', 'qty' => -1, 'unit_price' => (float) $ret['ship_refund']];
        }
        $total = 0.0;
        foreach ($items as $i) $total += $i['qty'] * $i['unit_price'];
        return ['type' => $total < 0 ? 'odd' : 'fv', 'total' => round($total, 2), 'items' => $items];
    }

    /** Název nové velikosti: velikost na konci názvu nahradí za novou. */
    public static function swapSize(string $name, string $size): string
    {
        $name = trim($name);
        if ($size === '') return $name;
        return preg_match('/^(.*\s)\S+$/u', $name, $m) ? $m[1] . $size : $name . ' ' . $size;
    }

    /** Lze požádat o doklad? Vrací chybu nebo null. */
    public static function docCheck(array $ret, array $lines): ?string
    {
        if (!in_array($ret['status'], ['dorazilo', 'v_reseni'], true)) return 'Doklad jde vystavit, až zboží dorazí (stav „Dorazilo“).';
        if (in_array((string) ($ret['doc_state'] ?? ''), ['ceka', 'navrh', 'hotovo'], true)) return 'Doklad už je zadaný.';
        if (!$ret['order_id']) return 'Vratka nemá doklad nákupu.';
        $plan = self::docPlan($ret, $lines);
        if (!$plan['type']) return 'Ve vratce není nic k vrácení ani výměně (reklamace se řeší v Pohodě v agendě Reklamace).';
        foreach ($plan['items'] as $i) {
            if ($i['kind'] === 'zbozi' && $i['code'] === '') return 'Položka „' . $i['name'] . '“ nemá kód zásoby – doklad vystavte ručně v Pohodě.';
            if ($i['kind'] === 'nove' && $i['code'] === '') return 'Doplňte kód zásoby nové velikosti u „' . $i['name'] . '“.';
        }
        if ($plan['type'] === 'odd' && !self::validAccount((string) $ret['bank_account'])) return 'Chybí číslo účtu zákazníka pro vrácení peněz.';
        if (!empty($ret['xorder_no']) && !in_array($ret['xorder_state'], ['hotovo', 'navrh'], true)) return 'Nejdřív se musí v Pohodě založit objednávka ' . $ret['xorder_no'] . ' na nové zboží (pomocník ji založí ' . self::pohodaEta() . ').';
        if (!empty($ret['zf_no']) && empty($ret['zf_paid'])) return 'Doplatek k zálohové faktuře ' . $ret['zf_no'] . ' zatím nepřišel – doklad vystavte až po zaplacení.';
        return null;
    }

    /** Prodejna potvrdila, že zboží je v pořádku → pomocník na KANCL vystaví doklad v Pohodě. $newCodes = [line_id => kód nové velikosti]. */
    public static function requestDoc(array $ret, array $newCodes, ?int $userId): ?string
    {
        $lines = self::lines((int) $ret['id']);
        foreach ($lines as &$l) {
            if ($l['action'] === 'vymenit' && isset($newCodes[$l['id']])) $l['new_code'] = mb_substr(trim((string) $newCodes[$l['id']]), 0, 60);
        }
        unset($l);
        $ret['ship_refund'] = self::shippingRefund($ret);
        $err = self::docCheck($ret, $lines);
        if ($err) return $err;
        $plan = self::docPlan($ret, $lines);
        Db::tx(function () use ($ret, $lines, $plan, $userId) {
            foreach ($lines as $l) if ($l['action'] === 'vymenit') Db::exec('UPDATE return_lines SET new_code = ? WHERE id = ?', [$l['new_code'], $l['id']]);
            Db::exec("UPDATE returns SET ship_refund = ?, doc_state = 'ceka', doc_type = ?, doc_error = NULL, updated_at = ? WHERE id = ?",
                [$ret['ship_refund'], $plan['type'], Db::now(), $ret['id']]);
            Db::insert('return_events', ['return_id' => $ret['id'], 'status' => null,
                'note' => 'Zboží v pořádku – ' . mb_strtolower(self::DOC_TYPE[$plan['type']]) . ' na ' . money($plan['total']) . ' se vystaví v Pohodě.',
                'public' => 0, 'user_id' => $userId, 'created_at' => Db::now()]);
        });
        return null;
    }

    /** Vratky čekající na doklad – pro pomocníka na KANCL. */
    public static function docPending(bool $withDrafts = false): array
    {
        $out = [];
        $states = $withDrafts ? "('ceka','navrh')" : "('ceka')";
        foreach (Db::all("SELECT r.*, o.invoice_no, o.order_no FROM returns r JOIN orders o ON o.id = r.order_id WHERE r.doc_state IN $states ORDER BY r.id") as $r) {
            $plan = self::docPlan($r);
            $out[] = ['no' => $r['no'], 'invoice_no' => $r['invoice_no'], 'order_no' => $r['order_no'], 'type' => $plan['type'], 'pay_method' => (string) ($r['pay_method'] ?? ''), 'total' => $plan['total'],
                'xorder_no' => $r['xorder_no'], 'xorder_state' => $r['xorder_state'], 'zf_no' => $r['zf_no'], 'zf_paid' => (bool) $r['zf_paid'], 'advance' => self::advanceAmount($r),
                'bank_account' => $r['bank_account'], 'customer_name' => $r['customer_name'], 'email' => $r['email'], 'items' => $plan['items']];
        }
        return $out;
    }

    // ---------- Pohoda: přijatá objednávka 991… a zálohová faktura k výměně ----------

    public const XORDER_STATE = ['ceka' => 'Čeká na založení v Pohodě', 'navrh' => 'Návrh čeká na schválení', 'hotovo' => 'Založeno v Pohodě', 'chyba' => 'Nepodařilo se založit'];

    /** České číslo účtu (předčíslí-číslo/banka) → IBAN, '' když nejde. */
    public static function iban(string $acc): string
    {
        if (!preg_match('~^(?:(\d{1,6})-)?(\d{2,10})/(\d{4})$~', preg_replace('~\s+~', '', $acc), $m)) return '';
        $bban = $m[3] . str_pad($m[1] ?: '0', 6, '0', STR_PAD_LEFT) . str_pad($m[2], 10, '0', STR_PAD_LEFT);
        $num = $bban . '123500'; $mod = 0;   // C=12, Z=35, 00
        foreach (str_split($num, 7) as $part) $mod = (int) (($mod . $part) % 97);
        return 'CZ' . str_pad((string) (98 - $mod), 2, '0', STR_PAD_LEFT) . $bban;
    }

    /** QR Platba (SPAYD) k doplatku výměny, '' když se neplatí převodem nebo zatím není záloha. */
    /** Variabilní symbol doplatku = číslo nové objednávky 991… (zálohová faktura ho má jako VS). */
    public static function payVs(array $ret): string
    {
        return preg_replace('~\D~', '', (string) (($ret['xorder_no'] ?? '') ?: ($ret['zf_no'] ?? '')));
    }

    public static function spayd(array $ret): string
    {
        $amount = self::advanceAmount($ret);
        $iban = self::iban((string) Config::get('shop.bank_account', ''));
        if ($amount <= 0.005 || !$ret['zf_no'] || $ret['zf_paid'] || $iban === '') return '';
        $msg = 'Doplatek vymena obj ' . self::ref($ret);
        return 'SPD*1.0*ACC:' . $iban . '*AM:' . number_format($amount, 2, '.', '') . '*CC:CZK*X-VS:' . self::payVs($ret)
            . '*MSG:' . strtoupper(preg_replace('~[^A-Za-z0-9 -]~', '', iconv('UTF-8', 'ASCII//TRANSLIT', $msg) ?: $msg));
    }

    /** Náhledy zboží k řádkům vratky: [id řádku => ['old' => url, 'new' => url]] (malé obrázky z CDN e-shopu, cache 30 min). */
    public static function lineImages(array $lines): array
    {
        $ids = array_filter(array_map(fn($l) => (int) ($l['item_id'] ?? 0), $lines));
        $stock = [];
        if ($ids) foreach (Db::all('SELECT id, stock_id FROM order_items WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $r) $stock[(int) $r['id']] = $r['stock_id'];
        $out = [];
        foreach ($lines as $l) {
            $sid = $stock[(int) ($l['item_id'] ?? 0)] ?? null;
            $old = $sid ? Eshop::image((int) $sid) : '';
            $new = '';
            if ($l['action'] === 'vymenit') {
                if (($l['new_url'] ?? '') !== '') $new = Eshop::productImage((string) $l['new_url']);
                else $new = $old;
            }
            $out[$l['id']] = ['old' => $old, 'new' => $new];
        }
        return $out;
    }

    /** Doplatek převodem → zálohová faktura (částka), jinak 0. */
    public static function advanceAmount(array $ret): float
    {
        return ($ret['pay_method'] ?? '') === 'prevod' ? self::toPay($ret) : 0.0;
    }

    /** Pro pomocníka: objednávky k založení (+ návrhy) a zálohy, u kterých hlídá zaplacení. */
    public static function xorderPending(bool $withDrafts = false): array
    {
        $out = [];
        $states = $withDrafts ? "('ceka','navrh')" : "('ceka')";
        $rows = Db::all("SELECT r.*, o.invoice_no, o.order_no FROM returns r JOIN orders o ON o.id = r.order_id
            WHERE r.xorder_no IS NOT NULL AND r.status <> 'zamitnuto' AND (r.xorder_state IN $states OR (r.xorder_state = 'hotovo' AND r.zf_no IS NOT NULL AND r.zf_paid = 0)) ORDER BY r.id");
        foreach ($rows as $r) {
            // objednávka = vracené zboží v minusu (textová položka, nehýbe skladem ani rezervací) + nové zboží; součet = doplatek / přeplatek
            $items = array_values(array_map(fn($i) => ['kind' => $i['kind'] === 'nove' ? 'nove' : 'stare', 'code' => $i['code'], 'name' => $i['name'], 'qty' => $i['qty'], 'unit_price' => $i['unit_price'],
                'for_code' => $i['kind'] === 'nove' ? ($i['for_code'] ?? '') : $i['code'], 'other' => !empty($i['other'])],
                array_filter(self::docPlan($r)['items'], fn($i) => $i['kind'] === 'nove' || !empty($i['swap']))));
            $out[] = ['no' => $r['no'], 'state' => $r['xorder_state'], 'xorder_no' => $r['xorder_no'], 'invoice_no' => $r['invoice_no'], 'order_no' => $r['order_no'],
                'date' => substr((string) $r['created_at'], 0, 10), 'customer_name' => $r['customer_name'], 'email' => $r['email'], 'phone' => $r['phone'],
                'pay_method' => (string) $r['pay_method'], 'advance' => self::advanceAmount($r), 'zf_no' => $r['zf_no'], 'items' => $items];
        }
        return $out;
    }

    /** Výsledek z KANCL: {no, state: hotovo|navrh|chyba|zaplaceno, zf_no, error}. */
    public static function xorderResult(array $x): bool
    {
        $r = self::byNo((string) ($x['no'] ?? ''));
        if (!$r || !$r['xorder_no']) return false;
        $state = (string) ($x['state'] ?? '');
        if ($state === 'zaplaceno') {
            if (!$r['zf_no'] || $r['zf_paid']) return false;
            Db::exec('UPDATE returns SET zf_paid = 1, updated_at = ? WHERE id = ?', [Db::now(), $r['id']]);
            Db::insert('return_events', ['return_id' => $r['id'], 'status' => null, 'note' => 'Doplatek ' . money(self::advanceAmount($r)) . ' jsme přijali. Děkujeme.',
                'public' => 1, 'user_id' => null, 'created_at' => Db::now()]);
            return true;
        }
        if (!in_array($r['xorder_state'], ['ceka', 'navrh'], true) || !in_array($state, ['hotovo', 'navrh', 'chyba'], true)) return false;
        $zf = mb_substr(trim((string) ($x['zf_no'] ?? '')), 0, 20);
        $err = mb_substr(trim((string) ($x['error'] ?? '')), 0, 2000);
        Db::exec('UPDATE returns SET xorder_state = ?, xorder_error = ?, zf_no = ?, updated_at = ? WHERE id = ?',
            [$state, $state === 'chyba' ? ($err ?: 'neznámá chyba') : null, $state === 'hotovo' && $zf !== '' ? $zf : null, Db::now(), $r['id']]);
        if ($state === 'hotovo') {
            Db::insert('return_events', ['return_id' => $r['id'], 'status' => null,
                'note' => 'Přijatá objednávka ' . $r['xorder_no'] . ' založena v Pohodě (nové zboží zarezervováno)' . ($zf !== '' ? ', zálohová faktura ' . $zf . ' na doplatek.' : '.'),
                'public' => 0, 'user_id' => null, 'created_at' => Db::now()]);
            if ($zf !== '') Notify::advance(self::get((int) $r['id']));
        } elseif ($state === 'chyba') {
            Db::insert('return_events', ['return_id' => $r['id'], 'status' => null, 'note' => 'Objednávku ' . $r['xorder_no'] . ' se nepodařilo založit v Pohodě: ' . $err,
                'public' => 0, 'user_id' => null, 'created_at' => Db::now()]);
        }
        return true;
    }

    /** Výsledek z KANCL: {no, state: hotovo|navrh|chyba, doc_no, error}. */
    public static function docResult(array $x): bool
    {
        $r = self::byNo((string) ($x['no'] ?? ''));
        if (!$r || !in_array($r['doc_state'], ['ceka', 'navrh'], true)) return false;
        $state = (string) ($x['state'] ?? '');
        if (!in_array($state, ['hotovo', 'navrh', 'chyba'], true)) return false;
        $docNo = mb_substr(trim((string) ($x['doc_no'] ?? '')), 0, 30);
        $err = mb_substr(trim((string) ($x['error'] ?? '')), 0, 2000);
        Db::exec('UPDATE returns SET doc_state = ?, doc_no = ?, doc_error = ?, updated_at = ? WHERE id = ?',
            [$state, $docNo ?: null, $state === 'chyba' ? ($err ?: 'neznámá chyba') : null, Db::now(), $r['id']]);
        if ($state !== 'navrh') {
            Db::insert('return_events', ['return_id' => $r['id'], 'status' => null,
                'note' => $state === 'hotovo' ? (self::DOC_TYPE[$r['doc_type']] ?? 'Doklad') . ' ' . $docNo . ' vystaven v Pohodě.' : 'Doklad v Pohodě se nepodařilo vystavit: ' . $err,
                'public' => 0, 'user_id' => null, 'created_at' => Db::now()]);
        }
        return true;
    }

    // ---------- Pohoda: agenda Reklamace ----------

    public const POHODA_STATE = ['cekame' => 'Čeká na založení v Pohodě', 'propojeno' => 'V Pohodě'];

    /**
     * Reklamace z Pohody (agenda Reklamace, posílá pomocník z KANCL). Každá reklamace v Pohodě = jedna položka.
     *  1. známé číslo 26RZ… → jen stav,
     *  2. v textu/poznámce je číslo požadavku V-RRRR-NNNN → propojí s požadavkem zákazníka z webu,
     *  3. jinak založí novou reklamaci (zboží převzaté na prodejně), doklad nákupu dohledá podle pole Doklad.
     * $initial = první načtení: nic se neposílá zákazníkům.
     * Vrací seznam ['ret' => …, 'kind' => 'created'|'status', 'text' => …] pro e-maily.
     */
    public static function importPohodaClaims(array $claims, bool $initial = false): array
    {
        $out = []; $touched = []; $now = Db::now();
        foreach ($claims as $c) {
            $num = trim((string) ($c['number'] ?? '')); $st = trim((string) ($c['status'] ?? ''));
            if ($num === '') continue;
            $line = Db::one('SELECT * FROM return_lines WHERE pohoda_no = ?', [$num]);
            if ($line) {
                if ($st !== '' && $line['pohoda_status'] !== $st) {
                    Db::exec('UPDATE return_lines SET pohoda_status = ? WHERE id = ?', [$st, $line['id']]);
                    Db::insert('return_events', ['return_id' => $line['return_id'], 'status' => null, 'note' => 'Stav v Pohodě (' . $num . '): ' . $st,
                        'public' => 0, 'user_id' => null, 'created_at' => $now]);
                    $touched[(int) $line['return_id']] = true;
                }
                continue;
            }
            $received = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($c['received'] ?? '')) ? $c['received'] : date('Y-m-d');
            $fresh = !$initial && $received >= date('Y-m-d', strtotime('-3 days'));
            $settle = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($c['settle_until'] ?? '')) ? $c['settle_until'] : null;
            $hay = implode(' ', [(string) ($c['text'] ?? ''), (string) ($c['note'] ?? ''), (string) ($c['int_note'] ?? ''), (string) ($c['defect'] ?? '')]);
            $rid = null;
            // požadavek zákazníka z webu: podle čísla dokladu nákupu (pole Doklad v Pohodě), případně podle V-… v textu
            $r = null;
            if (preg_match('/\bV-\d{4}-\d{3,5}\b/u', $hay, $m)) $r = Db::one('SELECT * FROM returns WHERE no = ?', [$m[0]]);
            $docNo = trim((string) ($c['document_no'] ?? ''));
            if (!$r && $docNo !== '') {
                $r = Db::one("SELECT r.* FROM returns r JOIN orders o ON o.id = r.order_id
                    WHERE r.pohoda_state = 'cekame' AND (o.invoice_no = ? OR o.order_no = ?) AND r.created_at <= ?
                    ORDER BY r.id LIMIT 1", [$docNo, $docNo, $received . ' 23:59:59']);
            }
            if ($r) {
                $cands = Db::all("SELECT * FROM return_lines WHERE return_id = ? AND action = 'reklamovat' AND (pohoda_no IS NULL OR pohoda_no = '') ORDER BY id", [$r['id']]);
                if ($cands) {
                    $pick = $cands[0]; $best = -1;
                    foreach ($cands as $cl) {
                        $score = ($cl['item_code'] !== '' && $cl['item_code'] === (string) ($c['code'] ?? '')) ? 1000 : similar_text(mb_strtolower($cl['item_name']), mb_strtolower((string) ($c['text'] ?? '')));
                        if ($score > $best) { $best = $score; $pick = $cl; }
                    }
                    Db::exec('UPDATE return_lines SET pohoda_no = ?, pohoda_status = ? WHERE id = ?', [$num, $st, $pick['id']]);
                    $rid = (int) $r['id'];
                    if (in_array($r['status'], ['novy', 'na_ceste'], true)) self::setStatus($r, 'dorazilo', '', false, null);
                    if ($settle) Db::exec('UPDATE returns SET claim_deadline = ? WHERE id = ?', [$settle, $rid]);
                    Db::insert('return_events', ['return_id' => $rid, 'status' => null, 'note' => 'Propojeno s reklamací ' . $num . ' v Pohodě.',
                        'public' => 0, 'user_id' => null, 'created_at' => $now]);
                }
            }
            if ($rid === null) $rid = self::createFromPohoda($c, $num, $st, $received, $settle);
            self::refreshPohodaNo($rid);
            $ret = self::get($rid);
            if ($fresh && $ret['email'] !== '') $out[] = ['ret' => $ret, 'kind' => 'created', 'text' => ''];
            $touched[$rid] = true;
        }
        foreach (array_keys($touched) as $rid) {
            $ch = self::recomputeFromPohoda($rid);
            if ($ch && !$initial) $out[] = ['ret' => $ch['ret'], 'kind' => 'status', 'text' => $ch['text']];
        }
        return $out;
    }

    /** Reklamace zadaná jen v Pohodě (zboží převzaté na prodejně) → nová reklamace v portálu. */
    private static function createFromPohoda(array $c, string $num, string $st, string $received, ?string $settle): int
    {
        $docNo = trim((string) ($c['document_no'] ?? ''));
        $order = $docNo !== '' ? Db::one('SELECT * FROM orders WHERE invoice_no = ? OR order_no = ? ORDER BY id LIMIT 1', [$docNo, $docNo]) : null;
        $item = null;
        if ($order) {
            $best = -1;
            foreach (self::items((int) $order['id']) as $it) {
                $score = ($it['code'] !== '' && $it['code'] === (string) ($c['code'] ?? '')) ? 1000 : similar_text(mb_strtolower($it['name']), mb_strtolower((string) ($c['text'] ?? '')));
                if ($score > $best) { $best = $score; $item = $it; }
            }
            if ($best < 10) $item = null;
        }
        $email = mb_strtolower(trim((string) ($c['email'] ?? '')));
        $name = trim((string) ($c['name'] ?? '')) ?: ($order['customer_name'] ?? '');
        $now = Db::now();
        return (int) Db::tx(function () use ($c, $num, $st, $received, $settle, $order, $item, $email, $name, $now) {
            $rid = Db::insert('returns', [
                'no' => self::nextNumber(), 'token' => bin2hex(random_bytes(20)), 'order_id' => $order['id'] ?? null,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
                'customer_name' => mb_substr($name, 0, 190), 'phone' => mb_substr(trim((string) ($c['phone'] ?? '')), 0, 40),
                'status' => 'dorazilo', 'ship_method' => 'osobne', 'bank_account' => '', 'note' => '',
                'refund_total' => 0, 'postage' => null, 'postage_applies' => 0,
                'claim_deadline' => $settle ?? date('Y-m-d', strtotime($received . ' +30 days')),
                'pohoda_state' => 'propojeno', 'created_at' => $received . ' 12:00:00', 'updated_at' => $now,
            ]);
            Db::insert('return_lines', [
                'return_id' => $rid, 'item_id' => $item['id'] ?? null,
                'item_code' => mb_substr((string) ($item['code'] ?? ($c['code'] ?? '')), 0, 60),
                'item_name' => mb_substr((string) ($item['name'] ?? (($c['text'] ?? '') ?: 'Reklamované zboží')), 0, 255),
                'unit_price' => (float) ($item['unit_price'] ?? 0), 'action' => 'reklamovat', 'qty' => max(1, (int) round((float) ($c['qty'] ?? 1))),
                'reason' => '', 'size_to' => '', 'defect_text' => (string) ($c['defect'] ?? ''),
                'pohoda_no' => $num, 'pohoda_status' => $st,
            ]);
            Db::insert('return_events', ['return_id' => $rid, 'status' => 'dorazilo', 'note' => 'Reklamaci jsme převzali na prodejně.',
                'public' => 1, 'user_id' => null, 'created_at' => $received . ' 12:00:00']);
            return $rid;
        });
    }

    /** Souhrnné číslo z Pohody na vratce a stav propojení. */
    private static function refreshPohodaNo(int $rid): void
    {
        $lines = array_filter(self::lines($rid), fn($l) => $l['action'] === 'reklamovat');
        $nums = array_values(array_filter(array_map(fn($l) => (string) ($l['pohoda_no'] ?? ''), $lines)));
        $state = $lines ? (count($nums) === count($lines) ? 'propojeno' : 'cekame') : '';
        Db::exec('UPDATE returns SET pohoda_no = ?, pohoda_state = ?, updated_at = ? WHERE id = ?',
            [$nums ? mb_substr(implode(', ', $nums), 0, 120) : null, $state, Db::now(), $rid]);
    }

    /**
     * Stav reklamace v Pohodě → stav v portálu. Serv_… je interní výsledek od dodavatele (zákazník nic nevidí),
     * Servis = předáno dodavateli/do servisu, Zák_… = předáno zákazníkovi (vyřízeno).
     * Vrací [stav portálu nebo null, text pro zákazníka].
     */
    public static function fromPohodaStatus(string $s): array
    {
        $vyrizeni = [
            'Zák_oprava' => 'zboží jsme opravili', 'Zák_výměna' => 'zboží jsme vyměnili za nové',
            'Zák_dobropis' => 'vystavili jsme opravný daňový doklad (dobropis) a peníze vám vrátíme',
            'Zák_opr.daň.dokl.' => 'vystavili jsme opravný daňový doklad a peníze vám vrátíme',
            'Zák_převod' => 'peníze vám vracíme převodem na účet', 'Zák_hotovost' => 'peníze jsme vám vrátili v hotovosti',
        ];
        if ($s === 'Servis') return ['v_reseni', 'reklamaci jsme předali k posouzení dodavateli'];
        if ($s === 'Zák_neuznaná') return ['zamitnuto', 'reklamace bohužel nebyla uznána'];
        if (isset($vyrizeni[$s])) return ['vyrizeno', $vyrizeni[$s]];
        return [null, ''];
    }

    /** Stav pro zákazníka spočítaný ze stavů všech reklamovaných položek v Pohodě. Vrací ['ret','text'] při změně, jinak null. */
    private static function recomputeFromPohoda(int $rid): ?array
    {
            $r = self::get($rid);
            $lines = array_values(array_filter(self::lines($rid), fn($l) => $l['action'] === 'reklamovat'));
            $parts = []; $states = [];
            foreach ($lines as $l) {
                [$st, $txt] = self::fromPohodaStatus((string) $l['pohoda_status']);
                $states[] = $st;
                if ($txt !== '') $parts[] = (count($lines) > 1 ? $l['item_name'] . ': ' : '') . $txt;
            }
            $final = fn($x) => in_array($x, ['vyrizeno', 'zamitnuto'], true);
            if ($states && count(array_filter($states, $final)) === count($states)) {
                $new = in_array('vyrizeno', $states, true) ? 'vyrizeno' : 'zamitnuto';
            } elseif (in_array('v_reseni', $states, true) || ($states && array_filter($states, $final))) {
                $new = 'v_reseni';
            } else {
                $new = null;
            }
            if ($new === null || $new === $r['status']) return null;
            $ucfirst = fn($t) => mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
            $text = implode(' ', array_map(fn($p) => $ucfirst($p) . '.', $parts));
            if ($new === 'vyrizeno') $text = 'Reklamace je vyřízená. ' . $text;
            if ($new === 'zamitnuto') $text .= ' Podrobnosti vám řekneme na prodejně nebo po telefonu ' . Config::get('shop.phone') . '.';
            if ($new === 'v_reseni') $text .= ' Jakmile se dodavatel vyjádří, ozveme se.';
            self::setStatus($r, $new, $text, true, null);
            return ['ret' => self::get($rid), 'text' => $text];
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

    /**
     * Jak doklad nazývat: jen reklamované položky = „reklamace“, jinak „vratka“ (obojí ženský rod).
     * n = 1. pád, g = 2. pád, a = 4. pád, l = 6. pád, N = 1. pád s velkým písmenem.
     */
    /** Kdy pomocník zapíše do Pohody: mServer běží 5:00–0:45, pomocník mu posílá 5:01–23:59. */
    public static function pohodaEta(): string
    {
        $h = (int) date('G');
        return $h >= 5 ? 'do pár minut' : 'ráno po 5. hodině';
    }

    /** Co zákazník řeší – bez slova „vratka“: Vrácení / Výměna / Reklamace (i kombinace). */
    public static function kind(array $lines): string
    {
        $k = [];
        foreach ([['vratit', 'vrácení'], ['vymenit', 'výměna'], ['reklamovat', 'reklamace']] as [$a, $n]) if (self::hasAction($lines, $a)) $k[] = $n;
        $t = $k ? implode(' a ', $k) : 'vrácení';
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    /** Číslo, které zákazník zná: číslo objednávky z e-shopu (jinak faktury, jinak naše interní). */
    public static function ref(array $ret): string
    {
        static $c = [];
        $oid = (int) ($ret['order_id'] ?? 0);
        if ($oid && !array_key_exists($oid, $c)) { $o = Db::one('SELECT order_no, invoice_no FROM orders WHERE id = ?', [$oid]); $c[$oid] = $o ? (string) ($o['order_no'] ?: $o['invoice_no']) : ''; }
        return ($oid ? $c[$oid] : '') ?: self::displayNo($ret);
    }

    /** Nadpis pro zákazníka: „Výměna k objednávce 8810012345“. */
    public static function title(array $ret, array $lines): string
    {
        $r = self::ref($ret);
        return self::kind($lines) . ($r !== self::displayNo($ret) ? ' k objednávce ' : ' ') . $r;
    }

    public static function words(array $lines): array
    {
        $claim = $lines && count(array_filter($lines, fn($l) => $l['action'] !== 'reklamovat')) === 0;
        $w = $claim ? ['n' => 'reklamace', 'g' => 'reklamace', 'a' => 'reklamaci', 'l' => 'reklamaci']
                    : ['n' => 'vratka', 'g' => 'vratky', 'a' => 'vratku', 'l' => 'vratce'];
        $w['N'] = mb_strtoupper(mb_substr($w['n'], 0, 1)) . mb_substr($w['n'], 1);
        $w['A'] = mb_strtoupper(mb_substr($w['a'], 0, 1)) . mb_substr($w['a'], 1);
        return $w;
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

    /** Vratky a reklamace už zadané k objednávce – zákazník se přes ně dostane na stránku se stavem. */
    public static function returnsForOrder(int $orderId): array
    {
        return Db::all('SELECT r.id, r.no, r.token, r.status, r.created_at, (SELECT l.action FROM return_lines l WHERE l.return_id = r.id ORDER BY l.id LIMIT 1) AS action
            FROM returns r WHERE r.order_id = ? ORDER BY r.id DESC', [$orderId]);
    }

    public static function ordersByEmail(string $email): array
    {
        $since = (new \DateTimeImmutable('today'))->modify('-' . (int) Config::get('vratky.claim_months', 24) . ' months')->format('Y-m-d');
        return Db::all("SELECT * FROM orders WHERE source = 'eshop' AND LOWER(email) = ? AND invoice_date >= ? ORDER BY invoice_date DESC LIMIT 50",
            [mb_strtolower(trim($email)), $since]);
    }
}
