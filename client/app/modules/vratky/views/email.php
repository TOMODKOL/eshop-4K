<?php
use App\Modules\Vratky\Service;
$w = Service::words($lines);
/** @var array $ret @var array $lines @var string $kicker @var string $heading @var string $text @var string $url @var bool $showCode */
require __DIR__ . '/email_head.php'; ?>
<tr><td style="padding:28px 28px 6px">
  <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:<?= ($ret['status'] ?? '') === 'zamitnuto' ? '#c0392b' : '#2e9d4f' ?>"><?= e($kicker) ?></p>
  <p style="margin:0 0 12px;font-size:22px;font-weight:700"><?= e($heading) ?></p>
  <p style="margin:0;font-size:15px;line-height:1.6;color:#444"><?= nl2br(e($text)) ?></p>
</td></tr>
<?php if ($showCode && $ret['packeta_password']): ?>
<tr><td style="padding:16px 28px 0">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#BA1B02;color:#ffffff;border-radius:12px;border-collapse:separate">
    <tr><td style="padding:4px 0 0"><img src="<?= e(rtrim((string) cfg('app_url'), '/')) ?>/assets/zasilkovna.png" width="170" height="60" alt="Zásilkovna" style="display:block;border:0;width:170px;height:auto;border-radius:12px 0 0 0"></td></tr>
    <tr><td style="padding:0 20px">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;color:#202020;border-radius:10px;border-collapse:separate"><tr><td style="padding:14px 16px 16px;text-align:center">
        <div style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#707070">Podací kód</div>
        <div style="font-size:36px;font-weight:700;letter-spacing:4px;margin-top:4px"><?= e($ret['packeta_password']) ?></div>
      </td></tr></table>
    </td></tr>
    <tr><td style="padding:14px 20px 20px;font-size:14px;line-height:1.5;color:#ffffff">
      <b>1.</b> Zboží zabalte, ideálně do původní krabice.<br>
      <b>2.</b> Na balík napište kód a „4 KLUCI OD KOL“.<br>
      <b>3.</b> <?= $ret['ship_method'] === 'zbox' ? 'V Z-BOXu kód zadejte a balík vložte.' : 'Na výdejním místě kód nadiktujte obsluze.' ?>
    </td></tr>
  </table></td></tr>
<?php endif; ?>
<tr><td style="padding:18px 28px 0">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.45">
  <?php $imgs = Service::lineImages($lines); $thumb = fn(string $u) => $u !== '' ? '<img src="' . e($u) . '" width="56" height="56" alt="" style="display:block;width:56px;height:56px;object-fit:contain;border:1px solid #ececea;border-radius:8px;background:#ffffff">' : '<div style="width:56px;height:56px;border-radius:8px;background:#f2f2f0"></div>'; ?>
  <?php $rej = ($ret['status'] ?? '') === 'zamitnuto'; foreach ($lines as $l): $im = $imgs[$l['id']] ?? ['old' => '', 'new' => '']; ?>
    <tr><td style="padding:10px 0;border-bottom:1px solid #ececea">
      <span style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#777"><?= e(Service::actionLabel($l)) ?></span>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px"><tr>
        <td width="68" valign="top" style="width:68px"><?= $thumb($im['old']) ?></td>
        <td valign="top"><strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong><?php if (!$rej): ?><br>
          <span style="color:#777"><?= e($l['action'] === 'vratit' ? ($rej ? 'Vrácení' : 'Vrátíme vám ' . money((float) $l['unit_price'] * (int) $l['qty']) . ($l['reason'] !== '' ? ' · ' . $l['reason'] : '')) : ($l['action'] === 'vymenit' ? ($rej ? 'Výměna' : 'Posíláte nám zpět') : mb_strimwidth((string) $l['defect_text'], 0, 160, '…'))) ?></span><?php endif; ?></td>
      </tr>
      <?php if ($l['action'] === 'vymenit' && !$rej):
        $other = ($l['new_name'] ?? '') !== '' && $l['new_price'] !== null;
        $dd = $other ? (float) $l['new_price'] * max(1, (int) ($l['new_qty'] ?? 1)) - (float) $l['unit_price'] * (int) $l['qty'] : 0.0;
        $get = !$other ? 'Od nás dostanete – stejné zboží, jiná velikost' : ($dd > 0.5 ? 'Od nás dostanete s doplatkem ' . money($dd) : ($dd < -0.5 ? 'Od nás dostanete a rozdíl ' . money(-$dd) . ' vám vrátíme' : 'Od nás dostanete bez doplatku')); ?>
      <tr><td width="68" valign="top" style="width:68px;padding-top:8px"><?= $thumb($im['new']) ?></td>
        <td valign="top" style="padding-top:8px"><strong><?= e(($l['new_name'] ?? '') !== '' ? $l['new_name'] : 'Velikost ' . $l['size_to']) ?><?= (int) ($l['new_qty'] ?? 0) > 1 ? ' × ' . (int) $l['new_qty'] : '' ?></strong><br><span style="color:#777"><?= e($get) ?></span></td></tr>
      <?php endif; ?>
      </table>
    </td></tr>
  <?php endforeach; ?>
  </table>
  <?php
    $bal = Service::balance($ret); $rows = [];
    if (!$rej) {
      if ((float) ($ret['postage_applies'] ?? 0) && $ret['postage'] !== null) $rows[] = ['Odečteme poštovné', '− ' . money((float) $ret['postage'])];
      if ((float) ($ret['ship_charge'] ?? 0) > 0) $rows[] = ['Odečteme dopravu objednávky', '− ' . money((float) $ret['ship_charge'])];
      if ((float) ($ret['ship_refund'] ?? 0) > 0) $rows[] = ['Vrátíme i dopravu', money((float) $ret['ship_refund'])];
      if ((float) ($ret['cod_fee'] ?? 0) > 0) $rows[] = ['Poplatek za dobírku', money((float) $ret['cod_fee'])];
    }
    if (!$rej && (count($lines) > 1 || $rows || abs($bal) > 0.005)): ?>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.45;margin-top:6px">
    <?php foreach ($rows as [$k, $v]): ?><tr><td style="padding:4px 0;color:#777"><?= e($k) ?></td><td style="padding:4px 0;text-align:right;white-space:nowrap"><?= e($v) ?></td></tr><?php endforeach; ?>
    <tr><td style="padding:12px 0 0;border-top:2px solid #231F20;font-size:16px;font-weight:700"><?= $bal < -0.005 ? (!empty($ret['zf_paid']) ? 'Celkem jste doplatili' : 'Celkem doplatíte') : ($bal > 0.005 ? 'Celkem vám vrátíme' : 'Celkem') ?></td>
      <td style="padding:12px 0 0;border-top:2px solid #231F20;font-size:20px;font-weight:700;text-align:right;white-space:nowrap"><?= abs($bal) > 0.005 ? e(money(abs($bal))) : 'vychází nastejno' ?></td></tr>
    <?php if ($bal < -0.005): ?><tr><td colspan="2" style="padding:4px 0 0;font-size:13px;color:#777"><?= ($ret['pay_method'] ?? '') === 'dobirka' ? 'Zaplatíte na dobírku při převzetí nového zboží.' : (!empty($pay) ? 'Zaplatíte převodem do ' . Service::PAY_DUE_DAYS . ' dnů podle platebních údajů níže.' : (!empty($ret['zf_paid']) ? 'Doplatek jsme přijali, děkujeme.' : 'Zaplatíte převodem – platební údaje vám pošleme e-mailem.')) ?></td></tr>
    <?php elseif ($bal > 0.005 && ($ret['bank_account'] ?? '') !== ''): ?><tr><td colspan="2" style="padding:4px 0 0;font-size:13px;color:#777">Pošleme na účet <?= e($ret['bank_account']) ?>, jakmile zboží zkontrolujeme.</td></tr><?php endif; ?>
  </table>
  <?php endif; ?>
</td></tr>
<?php if (!empty($pay)): ?>
<tr><td style="padding:16px 28px 0">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f5;border-radius:12px;border-collapse:separate;font-size:15px;line-height:1.5">
    <tr><td style="padding:16px 18px 4px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#777" colspan="2">Platební údaje</td></tr>
    <tr><td style="padding:4px 18px;color:#777">Částka</td><td style="padding:4px 18px;font-weight:700;text-align:right"><?= e(money($pay['amount'])) ?></td></tr>
    <tr><td style="padding:4px 18px;color:#777">Číslo účtu</td><td style="padding:4px 18px;font-weight:700;text-align:right"><?= e($pay['account']) ?></td></tr>
    <tr><td style="padding:4px 18px <?= !empty($pay['qr']) ? '4px' : '16px' ?>;color:#777">Variabilní symbol</td><td style="padding:4px 18px <?= !empty($pay['qr']) ? '4px' : '16px' ?>;font-weight:700;text-align:right"><?= e($pay['vs']) ?></td></tr>
    <?php if (!empty($pay['qr'])): ?><tr><td colspan="2" style="padding:10px 18px 18px;text-align:center"><img src="<?= e($pay['qr']) ?>" width="180" height="180" alt="QR platba" style="display:inline-block;width:180px;height:180px;border-radius:8px;background:#ffffff"><br><span style="font-size:13px;color:#777">QR platba – naskenujte v bankovní aplikaci</span></td></tr><?php endif; ?>
  </table></td></tr>
<?php endif; ?>
<tr><td style="padding:20px 28px 0"><a href="<?= e($url) ?>" style="display:inline-block;background:#231F20;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 20px;border-radius:10px">Zobrazit stav →</a></td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
