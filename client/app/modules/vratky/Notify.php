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
                'kicker' => 'Reklamace ' . Service::displayNo($ret) . ' přijata',
                'heading' => 'Reklamaci jsme přijali',
                'ret' => $ret, 'lines' => $lines, 'showCode' => Service::usesPacketa((string) $ret['ship_method']),
                'text' => !Service::usesPacketa((string) $ret['ship_method'])
                    ? 'Reklamaci vyřídíme nejpozději do ' . date_cz((string) $ret['claim_deadline']) . '. O průběhu vám dáme vědět.'
                    : self::nextStepText($ret),
                'url' => self::statusUrl($ret),
            ]);
            Mailer::send($ret['email'], 'Reklamace ' . Service::displayNo($ret) . ' – potvrzení', $html);
            return;
        }
        $w = Service::words($lines);
        $html = View::render('vratky:email', [
            'kicker' => $w['N'] . ' ' . Service::displayNo($ret) . ' vytvořena',
            'heading' => 'Děkujeme, ' . $w['a'] . ' máme',
            'ret' => $ret, 'lines' => $lines, 'showCode' => true,
            'text' => self::nextStepText($ret),
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], $w['N'] . ' ' . Service::displayNo($ret) . ' – ' . self::shortNext($ret), $html);

        $staff = (string) Config::get('vratky.notify_staff', '');
        if ($staff !== '') {
            $admin = rtrim((string) Config::get('app_url'), '/') . '/admin/vratky/' . $ret['no'];
            $photos = 0;
            foreach ($lines as $l) $photos += count(Photos::forLine((int) $l['id']));
            $html = View::render('vratky:email_staff', ['ret' => $ret, 'lines' => $lines, 'admin' => $admin, 'photos' => $photos,
                'order' => $ret['order_id'] ? Service::order((int) $ret['order_id']) : null]);
            Mailer::send($staff, 'Nová ' . $w['n'] . ' ' . Service::displayNo($ret) . ' – ' . ($ret['customer_name'] ?: $ret['email']), $html);
        }
    }

    public static function statusChanged(array $ret, string $note): void
    {
        if ($ret['email'] === '') return;
        $label = Service::STATUS[$ret['status']] ?? $ret['status'];
        $lines = Service::lines((int) $ret['id']);
        $w = Service::words($lines);
        $html = View::render('vratky:email', [
            'kicker' => $w['N'] . ' ' . Service::displayNo($ret),
            'heading' => $label,
            'ret' => $ret, 'lines' => $lines, 'showCode' => false,
            'text' => $note !== '' ? $note : self::statusText($ret['status'], $w),
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], $w['N'] . ' ' . Service::displayNo($ret) . ': ' . $label, $html);
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
            return 'Zboží přineste na prodejnu ' . Config::get('shop.address') . ' (' . Config::get('shop.hours') . ') a řekněte číslo ' . Service::words(Service::lines((int) $ret['id']))['g'] . ' ' . Service::displayNo($ret) . '.';
        }
        if ($ret['ship_method'] === 'vlastni') {
            return 'Zboží zabalte, přiložte papír s číslem ' . Service::words(Service::lines((int) $ret['id']))['g'] . ' ' . Service::displayNo($ret)
                . ' a pošlite ho na vlastní náklady (ne na dobírku) na adresu ' . Config::get('shop.name') . ', ' . Config::get('shop.address') . '.';
        }
        if ($ret['packeta_password']) {
            $where = $ret['ship_method'] === 'zbox' ? 'V Z-BOXu zadejte podací kód a balík vložte.' : 'Na výdejním místě Zásilkovny nadiktujte podací kód obsluze.';
            return 'Zboží zabalte, na balík napište kód a „4 KLUCI OD KOL“. ' . $where . ' Nic nemusíte tisknout.';
        }
        return 'Podací kód Zásilkovny vám pošleme e-mailem během pracovního dne.';
    }

    public static function statusText(string $s, array $w = ['N' => 'Vratka', 'A' => 'Vratku', 'g' => 'vratky']): string
    {
        return match ($s) {
            'na_ceste' => 'Balík je na cestě k nám. Jakmile dorazí, zkontrolujeme ho.',
            'dorazilo' => 'Balík dorazil a kontrolujeme ho. Ozveme se, jak to dopadlo.',
            'vyrizeno' => $w['N'] . ' je vyřízená. Děkujeme.',
            'zamitnuto' => $w['A'] . ' jsme bohužel nemohli přijmout. Podrobnosti vám napíšeme nebo zavoláme.',
            default => 'Stav ' . $w['g'] . ' se změnil.',
        };
    }
}
