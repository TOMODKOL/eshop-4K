<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Config;
use App\Core\Db;

/**
 * Platba doplatku výměny kartou přes GP webpay (HTTP API, operace CREATE_ORDER).
 *
 * - Obchodní místo je stejné jako e-shop (684240002). Jedno obchodní místo = jeden klíč, proto v ostrém
 *   provozu podepisujeme STEJNÝM soukromým klíčem jako BSSHOP. Testovací prostředí má vlastní testovací klíč.
 * - Platit se dá hned po odeslání vratky, ještě než pomocník založí zálohovou fakturu: platba se váže na číslo
 *   přijaté objednávky 991… (10 číslic), které dává Client a které je zároveň VS zálohové faktury v Pohodě.
 * - ORDERNUMBER = číslo objednávky 991… + pokus (01–99) – GP nedovolí číslo použít dvakrát.
 * - MERORDERNUM = číslo objednávky 991… → v avízu GP (RefNo) podle něj úloha „GP – denní výpis“ najde ZF podle VS.
 * - Podpis: RSA-SHA1 nad hodnotami spojenými „|“ v pořadí podle specifikace GP (LANG se nepodepisuje).
 * - Odpověď: DIGEST nad OPERATION|ORDERNUMBER|MERORDERNUM|MD|PRCODE|SRCODE|RESULTTEXT|… (jen přítomná pole),
 *   DIGEST1 = totéž + |MERCHANTNUMBER. Ověřuje se veřejným klíčem GP.
 */
final class GpWebpay
{
    public const URL_TEST = 'https://test.3dsecure.gpwebpay.com/pgw/order.do';
    public const URL_PROD = 'https://3dsecure.gpwebpay.com/pgw/order.do';

    /** Pořadí polí odpovědi pro ověření podpisu (specifikace GP webpay HTTP API). */
    private const RESPONSE_ORDER = ['OPERATION', 'ORDERNUMBER', 'MERORDERNUM', 'MD', 'PRCODE', 'SRCODE', 'RESULTTEXT', 'USERPARAM1',
        'ADDINFO', 'TOKEN', 'EXPIRY', 'ACSRES', 'ACCODE', 'PANPATTERN', 'DAYTOCAPTURE', 'TOKENREGSTATUS', 'ACRC', 'RRN', 'PAR', 'TRACEID'];

    public static function enabled(): bool
    {
        return (bool) Config::get('gpwebpay.enabled', false) && (string) Config::get('gpwebpay.merchant', '') !== ''
            && self::keyText('private_key') !== '' && self::keyText('gp_public_key') !== '';
    }

    public static function isTest(): bool { return (bool) Config::get('gpwebpay.test', true); }

    public static function gatewayUrl(): string { return self::isTest() ? self::URL_TEST : self::URL_PROD; }

    /** Klíč = cesta k PEM souboru (relativně k aplikaci) nebo rovnou text PEM. */
    private static function keyText(string $k): string
    {
        $v = (string) Config::get('gpwebpay.' . $k, '');
        if ($v === '' || str_contains($v, '-----BEGIN')) return $v;
        $p = str_starts_with($v, '/') ? $v : APP_ROOT . '/' . $v;
        return is_file($p) ? (string) file_get_contents($p) : '';
    }

    /** Lze u vratky platit kartou? (záloha vystavená, nezaplacená, kladná částka) */
    public static function canPay(array $ret): bool
    {
        return self::enabled() && ($ret['pay_method'] ?? '') === 'karta' && empty($ret['zf_paid']) && preg_match('~^\d{10}$~', (string) ($ret['xorder_no'] ?? ''))
            && Service::advanceAmount($ret) > 0.005 && !in_array($ret['status'], ['vyrizeno', 'zamitnuto'], true);
    }

    public static function sign(string $text): string
    {
        $key = openssl_pkey_get_private(self::keyText('private_key'), (string) Config::get('gpwebpay.key_password', ''));
        if ($key === false) throw new \RuntimeException('Soukromý klíč GP webpay nejde načíst (soubor nebo heslo).');
        if (!openssl_sign($text, $sig, $key, OPENSSL_ALGO_SHA1)) throw new \RuntimeException('Podpis pro GP webpay se nepovedl.');
        return base64_encode($sig);
    }

    public static function verifyText(string $text, string $digest): bool
    {
        $pub = openssl_pkey_get_public(self::keyText('gp_public_key'));
        if ($pub === false) return false;
        $sig = base64_decode($digest, true);
        return $sig !== false && openssl_verify($text, $sig, $pub, OPENSSL_ALGO_SHA1) === 1;
    }

    /** Založí pokus o platbu a vrátí adresu platební brány (přesměrování). */
    public static function createPaymentUrl(array $ret): string
    {
        if (!self::canPay($ret)) throw new \RuntimeException('Doplatek teď nejde zaplatit kartou.');
        $zf = (string) $ret['xorder_no'];   // reference platby (sloupec card_payments.zf_no)
        $amount = Service::advanceAmount($ret);
        $attempt = (int) Db::value('SELECT COUNT(*) FROM card_payments WHERE return_id = ?', [$ret['id']]) + 1;
        if ($attempt > 99) throw new \RuntimeException('Příliš mnoho pokusů o platbu. Zaplaťte prosím převodem.');
        $orderNo = $zf . str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);
        Db::insert('card_payments', ['return_id' => $ret['id'], 'order_number' => $orderNo, 'zf_no' => $zf, 'amount' => $amount,
            'status' => 'zalozeno', 'prcode' => null, 'srcode' => null, 'result_text' => '', 'test' => self::isTest() ? 1 : 0,
            'created_at' => Db::now(), 'finished_at' => null]);
        $p = [
            'MERCHANTNUMBER' => (string) Config::get('gpwebpay.merchant'),
            'OPERATION'      => 'CREATE_ORDER',
            'ORDERNUMBER'    => $orderNo,
            'AMOUNT'         => (string) (int) round($amount * 100),
            'CURRENCY'       => '203',
            'DEPOSITFLAG'    => '1',
            'MERORDERNUM'    => $zf,
            'URL'            => rtrim((string) Config::get('app_url'), '/') . '/vratky/platba-vysledek',
            'MD'             => (string) $ret['token'],
        ];
        $p['DIGEST'] = self::sign(implode('|', $p));
        $p['LANG'] = 'CZ';
        return self::gatewayUrl() . '?' . http_build_query($p, '', '&', PHP_QUERY_RFC3986);
    }

    /** Text pro ověření odpovědi (jen pole, která přišla). */
    public static function responseText(array $q, bool $withMerchant = false): string
    {
        $parts = [];
        foreach (self::RESPONSE_ORDER as $k) if (array_key_exists($k, $q)) $parts[] = (string) $q[$k];
        if ($withMerchant) $parts[] = (string) Config::get('gpwebpay.merchant');
        return implode('|', $parts);
    }

    /**
     * Zpracuje návrat z brány. Vrací ['ok' => bool, 'token' => ?string, 'message' => string].
     * Platí jen odpověď s platným podpisem DIGEST i DIGEST1. Opakované doručení stejné odpovědi nic nezdvojí.
     */
    public static function handleResponse(array $q): array
    {
        $q = array_change_key_case($q, CASE_UPPER);
        $orderNo = (string) ($q['ORDERNUMBER'] ?? '');
        $pay = preg_match('~^\d{11,12}$~', $orderNo) ? Db::one('SELECT * FROM card_payments WHERE order_number = ?', [$orderNo]) : null;
        $ret = $pay ? Service::get((int) $pay['return_id']) : null;
        $token = $ret['token'] ?? null;
        $valid = isset($q['DIGEST'], $q['DIGEST1']) && self::verifyText(self::responseText($q), (string) $q['DIGEST'])
            && self::verifyText(self::responseText($q, true), (string) $q['DIGEST1']);
        if (!$pay || !$ret || !$valid || ($q['OPERATION'] ?? '') !== 'CREATE_ORDER' || (string) ($q['MD'] ?? '') !== (string) $ret['token']) {
            if ($pay && $pay['status'] === 'zalozeno') Db::exec("UPDATE card_payments SET status = 'neplatna', result_text = ?, finished_at = ? WHERE id = ?",
                ['Neplatný podpis odpovědi', Db::now(), $pay['id']]);
            return ['ok' => false, 'token' => $token, 'message' => 'Odpověď platební brány se nepodařilo ověřit. Pokud vám byly peníze strženy, ozvěte se nám prosím.'];
        }
        $pr = (int) ($q['PRCODE'] ?? -1); $sr = (int) ($q['SRCODE'] ?? -1);
        $text = mb_substr((string) ($q['RESULTTEXT'] ?? ''), 0, 255);
        if ($pay['status'] === 'zaplaceno') return ['ok' => true, 'token' => $token, 'message' => 'Doplatek je zaplacený, děkujeme.'];
        if ($pr !== 0 || $sr !== 0) {
            Db::exec("UPDATE card_payments SET status = 'zamitnuto', prcode = ?, srcode = ?, result_text = ?, finished_at = ? WHERE id = ?",
                [$pr, $sr, $text, Db::now(), $pay['id']]);
            $msg = $pr === 50 ? 'Platbu jste zrušili. Můžete to zkusit znovu, nebo zaplatit převodem – stačí načíst QR kód v bankovní aplikaci.'
                : 'Platba kartou neprošla' . ($text !== '' ? ' (' . $text . ')' : '') . '. Zkuste to prosím znovu, nebo zaplaťte převodem – stačí načíst QR kód v bankovní aplikaci.';
            return ['ok' => false, 'token' => $token, 'message' => $msg];
        }
        Db::exec("UPDATE card_payments SET status = 'zaplaceno', prcode = 0, srcode = 0, result_text = ?, finished_at = ? WHERE id = ?", [$text, Db::now(), $pay['id']]);
        Service::markAdvancePaid($ret, 'kartou', (float) $pay['amount'], $orderNo);
        return ['ok' => true, 'token' => $token, 'message' => 'Děkujeme, doplatek ' . money((float) $pay['amount']) . ' je zaplacený kartou.'];
    }

    /** Zkoušel zákazník kartou a neprošlo to? (pak mu převod s QR kódem ukážeme rozbalený) */
    public static function hadFailure(int $returnId): bool
    {
        return (bool) Db::value("SELECT COUNT(*) FROM card_payments WHERE return_id = ? AND status IN ('zamitnuto', 'neplatna')", [$returnId]);
    }

    /** Poslední úspěšná platba kartou k vratce (pro administraci). */
    public static function paidPayment(int $returnId): ?array
    {
        return Db::one("SELECT * FROM card_payments WHERE return_id = ? AND status = 'zaplaceno' ORDER BY id DESC", [$returnId]);
    }
}
