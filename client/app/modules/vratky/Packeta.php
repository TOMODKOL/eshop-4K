<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Config;
use App\Core\Db;

/**
 * Zásilkovna – zpětná zásilka s podacím kódem (createPacketClaimWithPassword).
 * Dokumentace: https://docs.packeta.com/docs/packet-creation/returns
 */
final class Packeta
{
    public static function enabled(): bool
    {
        return (bool) Config::get('packeta.enabled') && Config::get('packeta.api_password') !== '';
    }

    /** Vytvoří zpětnou zásilku a uloží kód k vratce. Vrací true/false, chybu zapíše k vratce. */
    public static function createClaim(array $ret): bool
    {
        if (!self::enabled() || !Service::usesPacketa((string) $ret['ship_method']) || $ret['packeta_password']) return false;
        $value = max(1, (float) Db::value('SELECT COALESCE(SUM(unit_price * qty),0) FROM return_lines WHERE return_id = ?', [$ret['id']]));
        $xml = self::requestXml([
            'number' => $ret['no'],
            'email' => $ret['email'],
            'phone' => self::phone($ret['phone']),
            'value' => number_format($value, 2, '.', ''),
            'currency' => 'CZK',
            'eshop' => (string) Config::get('packeta.eshop'),
            'consignCountry' => 'CZ',
            'sendEmailToCustomer' => 'false',
        ]);
        try {
            $res = self::post($xml);
            $parsed = self::parseResponse($res);
        } catch (\Throwable $e) {
            $parsed = ['ok' => false, 'error' => $e->getMessage()];
        }
        if (!$parsed['ok']) {
            Db::exec('UPDATE returns SET packeta_error = ?, updated_at = ? WHERE id = ?', [$parsed['error'], Db::now(), $ret['id']]);
            return false;
        }
        Db::exec('UPDATE returns SET packeta_id = ?, packeta_barcode = ?, packeta_password = ?, packeta_error = NULL, updated_at = ? WHERE id = ?',
            [$parsed['id'], $parsed['barcodeText'] ?: $parsed['barcode'], $parsed['password'], Db::now(), $ret['id']]);
        return true;
    }

    /** Zruší zpětnou zásilku (cancelPacket). Vrací null = OK, jinak text chyby. */
    public static function cancel(string $packetId): ?string
    {
        if ($packetId === '' || !self::enabled()) return null;
        $x = new \XMLWriter();
        $x->openMemory();
        $x->startElement('cancelPacket');
        $x->writeElement('apiPassword', (string) Config::get('packeta.api_password'));
        $x->writeElement('packetId', $packetId);
        $x->endElement();
        try {
            $r = self::parseResponse(self::post($x->outputMemory()));
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        return $r['ok'] ? null : $r['error'];
    }

    public static function requestXml(array $attrs): string
    {
        $x = new \XMLWriter();
        $x->openMemory();
        $x->startElement('createPacketClaimWithPassword');
        $x->writeElement('apiPassword', (string) Config::get('packeta.api_password'));
        $x->startElement('claimWithPasswordAttributes');
        foreach ($attrs as $k => $v) if ($v !== '' && $v !== null) $x->writeElement($k, (string) $v);
        $x->endElement();
        $x->endElement();
        return $x->outputMemory();
    }

    /** @return array{ok:bool,error?:string,id?:string,barcode?:string,barcodeText?:string,password?:string} */
    public static function parseResponse(string $body): array
    {
        $prev = libxml_use_internal_errors(true);
        $r = simplexml_load_string($body);
        libxml_use_internal_errors($prev);
        if (!$r) return ['ok' => false, 'error' => 'Neplatná odpověď Zásilkovny.'];
        if ((string) $r->status !== 'ok') {
            $msg = trim((string) ($r->string ?? '') . ' ' . (string) ($r->fault ?? ''));
            if (isset($r->detail)) $msg .= ' ' . trim(strip_tags($r->detail->asXML() ?: ''));
            return ['ok' => false, 'error' => 'Zásilkovna: ' . trim($msg ?: 'neznámá chyba')];
        }
        $res = $r->result;
        return ['ok' => true, 'id' => (string) $res->id, 'barcode' => (string) $res->barcode,
            'barcodeText' => (string) $res->barcodeText, 'password' => (string) $res->password];
    }

    private static function post(string $xml): string
    {
        $ch = curl_init((string) Config::get('packeta.endpoint'));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException('Spojení se Zásilkovnou selhalo: ' . $err);
        return (string) $body;
    }

    private static function phone(string $p): string
    {
        $d = preg_replace('/\D/', '', $p) ?? '';
        if (strlen($d) === 9) return '+420' . $d;
        if (strlen($d) === 12 && str_starts_with($d, '420')) return '+' . $d;
        return '';
    }
}
