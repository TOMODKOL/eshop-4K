<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Config;
use App\Core\Db;

/**
 * Datum převzetí zásilky k objednávce – lhůta pro vrácení je 14 dní od převzetí.
 * Číslo balíku je v poznámce faktury (Sync::parcel). Zásilkovna: API packetTracking, stav 7 = vyzvednuto.
 * GLS zatím neumíme (potřebuje klíč do GLS Track & Trace API) → zůstává odhad podle data faktury.
 */
final class Tracking
{
    private const RECHECK = 3600;     // nezjištěné doručení zkusit znovu nejdřív za hodinu
    private const MAX_AGE_DAYS = 90;  // starší objednávky už nesledujeme

    /** Doplní k objednávce datum převzetí, pokud ho jde zjistit. Vrací objednávku (případně s delivered_date). */
    public static function refresh(array $order): array
    {
        if (!empty($order['delivered_date']) || ($order['parcel_carrier'] ?? '') !== 'zas' || ($order['parcel_no'] ?? '') === '') return $order;
        if (!Packeta::enabled()) return $order;
        if (strtotime((string) $order['invoice_date']) < strtotime('-' . self::MAX_AGE_DAYS . ' days')) return $order;
        if (!empty($order['tracking_checked_at']) && strtotime((string) $order['tracking_checked_at']) > time() - self::RECHECK) return $order;
        $date = self::packetaDelivered((string) $order['parcel_no']);
        Db::exec('UPDATE orders SET delivered_date = ?, tracking_checked_at = ? WHERE id = ?', [$date, Db::now(), $order['id']]);
        $order['delivered_date'] = $date;
        $order['tracking_checked_at'] = Db::now();
        return $order;
    }

    /** Datum vyzvednutí (Y-m-d) ze Zásilkovny, null = zatím nevyzvednuto / nejde zjistit. */
    public static function packetaDelivered(string $barcode): ?string
    {
        $id = preg_replace('/\D/', '', $barcode) ?? '';
        if ($id === '') return null;
        $x = new \XMLWriter();
        $x->openMemory();
        $x->startElement('packetTracking');
        $x->writeElement('apiPassword', (string) Config::get('packeta.api_password'));
        $x->writeElement('packetId', $id);
        $x->endElement();
        $ch = curl_init((string) Config::get('packeta.endpoint'));
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $x->outputMemory(),
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? self::parsePacketa($body) : null;
    }

    /**
     * Zpětná zásilka (vratka k nám): kdy ji zákazník podal a kdy k nám dorazila.
     * Stavy packetTracking: 1 = jen data, 2–6 = v přepravě, 7 = doručeno (u zpětné zásilky = u nás), 11 = zrušeno.
     * @return array{sent:?string, delivered:?string}|null  null = nejde zjistit
     */
    public static function packetaReturn(string $packetId): ?array
    {
        $id = preg_replace('/\D/', '', $packetId) ?? '';
        if ($id === '' || !Packeta::enabled()) return null;
        $x = new \XMLWriter();
        $x->openMemory();
        $x->startElement('packetTracking');
        $x->writeElement('apiPassword', (string) Config::get('packeta.api_password'));
        $x->writeElement('packetId', $id);
        $x->endElement();
        $ch = curl_init((string) Config::get('packeta.endpoint'));
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $x->outputMemory(),
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? self::parseReturn($body) : null;
    }

    /** @return array{sent:?string, delivered:?string}|null */
    public static function parseReturn(string $body): ?array
    {
        $prev = libxml_use_internal_errors(true);
        $r = simplexml_load_string($body);
        libxml_use_internal_errors($prev);
        if (!$r || (string) $r->status !== 'ok') return null;
        $out = ['sent' => null, 'delivered' => null];
        foreach ($r->result->record ?? [] as $rec) {
            $c = (int) $rec->statusCode; $t = strtotime((string) $rec->dateTime);
            if (!$t) continue;
            if ($c >= 2 && $c <= 7 && $out['sent'] === null) $out['sent'] = date('Y-m-d H:i:s', $t);
            if ($c === 7) $out['delivered'] = date('Y-m-d H:i:s', $t);
        }
        return $out;
    }

    /** Z odpovědi packetTracking vezme datum stavu 7 (delivered). Veřejné kvůli testům. */
    public static function parsePacketa(string $body): ?string
    {
        $prev = libxml_use_internal_errors(true);
        $r = simplexml_load_string($body);
        libxml_use_internal_errors($prev);
        if (!$r || (string) $r->status !== 'ok') return null;
        $date = null;
        foreach ($r->result->record ?? [] as $rec) {
            if ((int) $rec->statusCode === 7 && ($t = strtotime((string) $rec->dateTime))) $date = date('Y-m-d', $t);
        }
        return $date;
    }
}
