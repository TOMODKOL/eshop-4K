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
                'kicker' => 'Reklamace ' . $ret['no'] . ' přijata',
                'heading' => 'Reklamaci jsme přijali',
                'ret' => $ret, 'lines' => $lines, 'showCode' => $ret['ship_method'] !== 'osobne',
                'text' => $ret['ship_method'] === 'osobne'
                    ? 'Reklamaci vyřídíme nejpozději do ' . date_cz((string) $ret['claim_deadline']) . '. O průběhu vám dáme vědět.'
                    : self::nextStepText($ret),
                'url' => self::statusUrl($ret),
            ]);
            Mailer::send($ret['email'], 'Reklamace ' . $ret['no'] . ' – potvrzení', $html);
            return;
        }
        $html = View::render('vratky:email', [
            'kicker' => 'Vratka ' . $ret['no'] . ' vytvořena',
            'heading' => 'Děkujeme, vratku máme',
            'ret' => $ret, 'lines' => $lines, 'showCode' => true,
            'text' => self::nextStepText($ret),
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], 'Vratka ' . $ret['no'] . ' – ' . self::shortNext($ret), $html);

        $staff = (string) Config::get('vratky.notify_staff', '');
        if ($staff !== '') {
            $admin = rtrim((string) Config::get('app_url'), '/') . '/admin/vratky/' . $ret['no'];
            $sum = implode("\n", array_map(fn($l) => Service::ACTIONS[$l['action']] . ': ' . $l['item_name'] . ' × ' . $l['qty'], $lines));
            Mailer::send($staff, 'Nová vratka ' . $ret['no'] . ' (' . $ret['customer_name'] . ')',
                '<p>Nová vratka ' . e($ret['no']) . ' od ' . e($ret['customer_name']) . ' (' . e($ret['email']) . ').</p><pre>' . e($sum) . '</pre><p><a href="' . e($admin) . '">Otevřít v administraci</a></p>');
        }
    }

    public static function statusChanged(array $ret, string $note): void
    {
        if ($ret['email'] === '') return;
        $label = Service::STATUS[$ret['status']] ?? $ret['status'];
        $html = View::render('vratky:email', [
            'kicker' => 'Vratka ' . $ret['no'],
            'heading' => $label,
            'ret' => $ret, 'lines' => Service::lines((int) $ret['id']), 'showCode' => false,
            'text' => $note !== '' ? $note : self::statusText($ret['status']),
            'url' => self::statusUrl($ret),
        ]);
        Mailer::send($ret['email'], 'Vratka ' . $ret['no'] . ': ' . $label, $html);
    }

    public static function shortNext(array $ret): string
    {
        if ($ret['ship_method'] === 'osobne') return 'přineste zboží na prodejnu';
        return $ret['packeta_password'] ? 'podací kód ' . $ret['packeta_password'] : 'pošleme vám podací kód';
    }

    public static function nextStepText(array $ret): string
    {
        if ($ret['ship_method'] === 'osobne') {
            return 'Zboží přineste na prodejnu ' . Config::get('shop.address') . ' (' . Config::get('shop.hours') . ') a řekněte číslo vratky ' . $ret['no'] . '.';
        }
        if ($ret['packeta_password']) {
            $where = $ret['ship_method'] === 'zbox' ? 'V Z-BOXu zadejte podací kód a balík vložte.' : 'Na výdejním místě Zásilkovny nadiktujte podací kód obsluze.';
            return 'Zboží zabalte, na balík napište kód a „4 KLUCI OD KOL“. ' . $where . ' Nic nemusíte tisknout.';
        }
        return 'Podací kód Zásilkovny vám pošleme e-mailem během pracovního dne.';
    }

    public static function statusText(string $s): string
    {
        return match ($s) {
            'na_ceste' => 'Balík je na cestě k nám. Jakmile dorazí, zkontrolujeme ho.',
            'dorazilo' => 'Balík dorazil a kontrolujeme ho. Ozveme se, jak to dopadlo.',
            'vyrizeno' => 'Vratka je vyřízená. Děkujeme.',
            'zamitnuto' => 'Vratku jsme bohužel nemohli přijmout. Podrobnosti vám napíšeme nebo zavoláme.',
            default => 'Stav vratky se změnil.',
        };
    }
}
