<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Config;
use App\Core\Mailer;
use App\Core\View;

/** E-maily zákazníkovi a prodejně. */
final class Notify
{
    public static function statusUrl(array $ret): string
    {
        return rtrim((string) Config::get('app_url'), '/') . '/vratky/stav/' . $ret['token'];
    }

    public static function created(array $ret, bool $byStaff = false): void
    {
        $lines = Service::lines((int) $ret['id']);
        if ($byStaff) {
            if ($ret['email'] === '') return;
            $html = View::render('vratky:email', [
                'kicker' => Service::title($ret, $lines),
                'heading' => 'Reklamaci jsme přijali',
                'ret' => $ret, 'lines' => $lines, 'showCode' => Service::usesPacketa((string) $ret['ship_method']),
                'text' => !Service::usesPacketa((string) $ret['ship_method'])
                    ? 'Reklamaci vyřídíme nejpozději do ' . date_cz((string) $ret['claim_deadline']) . '. O průběhu vám dáme vědět.'
                    : self::nextStepText($ret),
                'url' => self::statusUrl($ret),
            ]);
            Mailer::send($ret['email'], Service::title($ret, $lines) . ' – potvrzení', $html);
            self::packetaMissing($ret);
            return;
        }
        $w = Service::words($lines);
        $html = View::render('vratky:email', [
            'kicker' => Service::title($ret, $lines),
            'heading' => 'Děkujeme, máme to',
            'ret' => $ret, 'lines' => $lines, 'showCode' => true,
            'text' => self::nextStepText($ret),
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], Service::title($ret, $lines) . ' – ' . self::shortNext($ret), $html);
        self::packetaMissing($ret);

        $staff = (string) Config::get('vratky.notify_staff', '');
        if ($staff !== '') {
            $admin = rtrim((string) Config::get('app_url'), '/') . '/admin/vratky/' . $ret['no'];
            $photos = 0;
            foreach ($lines as $l) $photos += count(Photos::forLine((int) $l['id']));
            $html = View::render('vratky:email_staff', ['ret' => $ret, 'lines' => $lines, 'admin' => $admin, 'photos' => $photos,
                'order' => $ret['order_id'] ? Service::order((int) $ret['order_id']) : null]);
            Mailer::send($staff, Service::title($ret, $lines) . ' (' . Service::displayNo($ret) . ') – ' . ($ret['customer_name'] ?: $ret['email']), $html);
        }
    }

    /** Zálohová faktura na doplatek výměny je v Pohodě → zákazníkovi platební údaje (podruhé jako upomínka po splatnosti). */
    public static function advance(?array $ret, bool $reminder = false): void
    {
        if (!$ret || $ret['email'] === '' || !$ret['zf_no']) return;
        $lines = Service::lines((int) $ret['id']);
        $amount = Service::advanceAmount($ret);
        $html = View::render('vratky:email', [
            'kicker' => Service::title($ret, $lines),
            'heading' => $reminder ? 'Připomínáme doplatek' : 'Doplatek za výměnu',
            'ret' => $ret, 'lines' => $lines, 'showCode' => false,
            'text' => $reminder
                ? 'Doplatek za výměnu jsme zatím nedostali. Pošlete ho prosím převodem podle údajů níže, nové zboží pro vás pořád držíme. Pokud jste už platili, děkujeme – platby se nám propojí do pár dnů.'
                : 'Nové zboží máme pro vás zarezervované. Doplatek prosím pošlete převodem do ' . Service::PAY_DUE_DAYS . ' dnů podle údajů níže. '
                    . 'Nové zboží odešleme, jakmile k nám dorazí vaše zboží a platba.',
            'pay' => ['amount' => $amount, 'account' => (string) Config::get('shop.bank_account', ''), 'vs' => Service::payVs($ret),
                'qr' => Service::spayd($ret) !== '' ? rtrim((string) Config::get('app_url'), '/') . '/vratky/qr/' . $ret['token'] : ''],
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], Service::title($ret, $lines) . ($reminder ? ' – připomínáme doplatek ' : ' – doplatek ') . money($amount), $html);
    }

    /** Doplatek přišel (pomocník ho uvidí zlikvidovaný v Pohodě). */
    public static function paid(?array $ret): void
    {
        if (!$ret || $ret['email'] === '') return;
        $lines = Service::lines((int) $ret['id']);
        $html = View::render('vratky:email', [
            'kicker' => Service::title($ret, $lines),
            'heading' => 'Doplatek jsme přijali',
            'ret' => $ret, 'lines' => $lines, 'showCode' => false,
            'text' => 'Doplatek ' . money(Service::advanceAmount($ret)) . ' za výměnu jsme přijali. Děkujeme.',
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], Service::title($ret, $lines) . ' – doplatek přijat', $html);
    }

    /** Zákazník po 7 dnech zboží neposlal → připomínka s postupem. */
    public static function remindSend(array $ret): void
    {
        if ($ret['email'] === '') return;
        $lines = Service::lines((int) $ret['id']);
        $html = View::render('vratky:email', [
            'kicker' => Service::title($ret, $lines),
            'heading' => 'Zboží jsme od vás zatím nedostali',
            'ret' => $ret, 'lines' => $lines, 'showCode' => true,
            'text' => 'Připomínáme, že zboží k nám ještě nedorazilo. ' . self::nextStepText($ret) . ' Pokud jste ho už poslali, děkujeme – tuto zprávu prosím neřešte.',
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], Service::title($ret, $lines) . ' – zboží zatím nedorazilo', $html);
    }

    /** Po 14 dnech pořád nic → prodejně upozornění (u výměny drží rezervaci nového zboží). */
    public static function staleStaff(array $ret): void
    {
        $staff = (string) Config::get('vratky.notify_staff', '');
        if ($staff === '') return;
        $lines = Service::lines((int) $ret['id']);
        $html = View::render('vratky:email_staff_stale', ['ret' => $ret, 'lines' => $lines, 'days' => Service::STALE_DAYS,
            'admin' => rtrim((string) Config::get('app_url'), '/') . '/admin/vratky/' . $ret['no']]);
        Mailer::send($staff, '⚠ Zboží nedorazilo ' . Service::STALE_DAYS . ' dní – ' . Service::title($ret, $lines), $html);
    }

    /** Zákazník dostal „kód pošleme e-mailem“ → prodejně upozornění s postupem. */
    public static function packetaMissing(array $ret): void
    {
        if (!Service::usesPacketa((string) $ret['ship_method']) || $ret['packeta_password']) return;
        $staff = (string) Config::get('vratky.notify_staff', '');
        if ($staff === '') return;
        $ret = Service::get((int) $ret['id']) ?? $ret; // aktuální chyba od Zásilkovny
        $html = View::render('vratky:email_staff_packeta', ['ret' => $ret, 'enabled' => Packeta::enabled(),
            'admin' => rtrim((string) Config::get('app_url'), '/') . '/admin/vratky/' . $ret['no']]);
        Mailer::send($staff, '⚠ Chybí podací kód Zásilkovny – ' . Service::displayNo($ret), $html);
    }

    public static function statusChanged(array $ret, string $note): void
    {
        if ($ret['email'] === '') return;
        $label = Service::STATUS[$ret['status']] ?? $ret['status'];
        $lines = Service::lines((int) $ret['id']);
        $w = Service::words($lines);
        $html = View::render('vratky:email', [
            'kicker' => Service::title($ret, $lines),
            'heading' => $label,
            'ret' => $ret, 'lines' => $lines, 'showCode' => false,
            'text' => $ret['status'] === 'zamitnuto' ? self::statusText('zamitnuto', $w, $note) : ($note !== '' ? $note : self::statusText($ret['status'], $w)),
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], Service::title($ret, $lines) . ': ' . $label, $html);
    }

    /** „číslo objednávky 8810012345“ (7. pád: „číslem objednávky …“). */
    public static function refText(array $ret, bool $instr = false): string
    {
        $r = Service::ref($ret);
        $o = $r !== Service::displayNo($ret);
        return ($instr ? ($o ? 'číslem objednávky ' : 'číslem ') : ($o ? 'číslo objednávky ' : 'číslo ')) . $r;
    }

    public static function shortNext(array $ret): string
    {
        if ($ret['ship_method'] === 'osobne') return 'přineste zboží na prodejnu';
        if ($ret['ship_method'] === 'vlastni') return 'pošlete zboží na naši adresu';
        return $ret['packeta_password'] ? 'podací kód ' . $ret['packeta_password'] : 'pošleme vám podací kód';
    }

    public static function nextStepText(array $ret): string
    {
        if ($ret['ship_method'] === 'osobne') {
            return 'Zboží přineste na prodejnu ' . Config::get('shop.address') . ' (' . Config::get('shop.hours') . ') a řekněte ' . self::refText($ret) . '.';
        }
        if ($ret['ship_method'] === 'vlastni') {
            return 'Zboží zabalte, přiložte papír s ' . self::refText($ret, true)
                . ' a pošlete ho na vlastní náklady (ne na dobírku) na adresu ' . Config::get('shop.name') . ', ' . Config::get('shop.address') . '.';
        }
        if ($ret['packeta_password']) {
            // postup krok za krokem je v červeném boxu Zásilkovny pod textem
            return 'Zboží pošlete přes Zásilkovnu – podání je zdarma a nic nemusíte tisknout.';
        }
        return 'Podací kód Zásilkovny vám pošleme e-mailem během pracovního dne.';
    }

    public static function statusText(string $s, array $w = [], string $reason = ''): string
    {
        return match ($s) {
            'na_ceste' => 'Balík je na cestě k nám. Jakmile dorazí, zkontrolujeme ho.',
            'dorazilo' => 'Balík dorazil a kontrolujeme ho. Ozveme se, jak to dopadlo.',
            'vyrizeno' => 'Vše je vyřízené. Děkujeme.',
            'zamitnuto' => $reason !== '' ? 'Bohužel to nemůžeme přijmout. Důvod: ' . rtrim($reason, " .") . '. Máte-li dotaz, zavolejte nám.' : 'Bohužel to nemůžeme přijmout. Podrobnosti vám napíšeme nebo zavoláme.',
            default => 'Stav se změnil.',
        };
    }
}
