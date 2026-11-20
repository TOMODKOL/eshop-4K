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
                'claim_deadline' => $hasClaim ? date('Y-m-d', strtotime('+' . (int) Config::get('vratky.claim_resolve_days', 30) . ' days')) : null,
                'pohoda_state' => $pohoda,
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

    public static function ordersByEmail(string $email): array
    {
        $since = (new \DateTimeImmutable('today'))->modify('-' . (int) Config::get('vratky.claim_months', 24) . ' months')->format('Y-m-d');
        return Db::all("SELECT * FROM orders WHERE source = 'eshop' AND LOWER(email) = ? AND invoice_date >= ? ORDER BY invoice_date DESC LIMIT 50",
            [mb_strtolower(trim($email)), $since]);
    }
}
