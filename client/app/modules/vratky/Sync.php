<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Db;

/**
 * Příjem objednávek z Pohody. Posílá je pomocník na KANCL (POST /api/sync/objednavky, JSON).
 *
 * {"orders":[{"invoice_no":"260101933","order_no":"8810007130","invoice_date":"2026-11-02",
 *   "email":"jana@email.cz","customer_name":"Jana Nováková","phone":"+420…","total":4749.23,
 *   "items":[{"code":"…","name":"…","qty":1,"unit_price":1990}]}]}
 * unit_price = cena za kus včetně DPH po slevě. Stejná faktura poslaná znovu se přepíše.
 * source: eshop (faktura s objednávkou 8810…, výchozí), prodejna (prodejka 26PH…), faktura (ostatní vydané faktury).
 * E-mail je povinný jen u e-shopu; prodejky ho nemají a reklamace k nim zakládá prodejna v administraci.
 */
final class Sync
{
    /** @return array{saved:int,errors:array<int,string>} */
    public static function import(array $payload): array
    {
        $orders = $payload['orders'] ?? null;
        if (!is_array($orders)) return ['saved' => 0, 'errors' => ['Chybí pole orders.']];
        $saved = 0;
        $errors = [];
        foreach ($orders as $k => $o) {
            $err = self::check($o);
            if ($err) { $errors[] = "#$k: $err"; continue; }
            Db::tx(function () use ($o) {
                $now = Db::now();
                $inv = Service::normalizeNumber((string) $o['invoice_no']);
                $email = mb_strtolower(trim((string) ($o['email'] ?? '')));
                $row = [
                    'order_no' => isset($o['order_no']) && $o['order_no'] !== '' ? Service::normalizeNumber((string) $o['order_no']) : null,
                    'invoice_date' => substr((string) $o['invoice_date'], 0, 10),
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
                    'customer_name' => mb_substr(trim((string) ($o['customer_name'] ?? '')), 0, 190),
                    'phone' => mb_substr(trim((string) ($o['phone'] ?? '')), 0, 40),
                    'total' => (float) ($o['total'] ?? 0),
                    'source' => self::source($o),
                    'updated_at' => $now,
                ];
                $id = Db::value('SELECT id FROM orders WHERE invoice_no = ?', [$inv]);
                if ($id) {
                    $sets = implode(',', array_map(fn($c) => "$c = :$c", array_keys($row)));
                    Db::exec("UPDATE orders SET $sets WHERE id = :id", $row + ['id' => $id]);
                    // položky přepsat, jen pokud k nim ještě nevznikla vratka
                    $hasReturn = Db::value('SELECT COUNT(*) FROM return_lines l JOIN order_items i ON i.id = l.item_id WHERE i.order_id = ?', [$id]);
                    if ((int) $hasReturn > 0) {
                        // jen doplnit ID zásoby (kvůli výběru velikosti), položky zůstanou
                        foreach (array_values($o['items']) as $n => $it) {
                            if (ctype_digit((string) ($it['stock_id'] ?? ''))) Db::exec('UPDATE order_items SET stock_id = ? WHERE order_id = ? AND line_no = ? AND stock_id IS NULL', [(int) $it['stock_id'], $id, $n]);
                        }
                        return;
                    }
                    Db::exec('DELETE FROM order_items WHERE order_id = ?', [$id]);
                } else {
                    $id = Db::insert('orders', $row + ['invoice_no' => $inv, 'created_at' => $now]);
                }
                foreach (array_values($o['items']) as $n => $it) {
                    Db::insert('order_items', [
                        'order_id' => $id,
                        'line_no' => $n,
                        'code' => mb_substr(trim((string) ($it['code'] ?? '')), 0, 60),
                        'stock_id' => ctype_digit((string) ($it['stock_id'] ?? '')) ? (int) $it['stock_id'] : null,
                        'name' => mb_substr(trim((string) $it['name']), 0, 255),
                        'qty' => (float) ($it['qty'] ?? 1),
                        'unit_price' => (float) ($it['unit_price'] ?? 0),
                    ]);
                }
            });
            $saved++;
        }
        Db::insert('sync_log', ['received_at' => Db::now(), 'orders' => $saved, 'note' => $errors ? mb_substr(implode('; ', $errors), 0, 250) : '']);
        return ['saved' => $saved, 'errors' => $errors];
    }

    public const SOURCES = ['eshop' => 'E-shop', 'prodejna' => 'Prodejna', 'faktura' => 'Faktura'];

    private static function source(array $o): string
    {
        $s = (string) ($o['source'] ?? 'eshop');
        return isset(self::SOURCES[$s]) ? $s : 'eshop';
    }

    private static function check(mixed $o): ?string
    {
        if (!is_array($o)) return 'není objekt';
        if (empty($o['invoice_no'])) return 'chybí invoice_no';
        if (empty($o['invoice_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $o['invoice_date'])) return 'chybí invoice_date (RRRR-MM-DD)';
        if (self::source($o) === 'eshop' && (empty($o['email']) || !filter_var(trim((string) $o['email']), FILTER_VALIDATE_EMAIL))) return 'chybí platný email';
        if (empty($o['items']) || !is_array($o['items'])) return 'chybí items';
        foreach ($o['items'] as $it) if (!is_array($it) || empty($it['name'])) return 'položka bez name';
        return null;
    }
}
