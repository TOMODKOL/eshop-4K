<?php
use App\Modules\Vratky\Service;
$w = Service::words($lines);
/** @var array $ret @var array $lines @var string $kicker @var string $heading @var string $text @var string $url @var bool $showCode */
require __DIR__ . '/email_head.php'; ?>
<tr><td style="padding:28px 28px 6px">
  <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#2e9d4f"><?= e($kicker) ?></p>
  <p style="margin:0 0 12px;font-size:22px;font-weight:700"><?= e($heading) ?></p>
  <p style="margin:0;font-size:15px;line-height:1.6;color:#444"><?= nl2br(e($text)) ?></p>
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
  <?php foreach ($lines as $l): $im = $imgs[$l['id']] ?? ['old' => '', 'new' => '']; ?>
    <tr><td style="padding:10px 0;border-bottom:1px solid #ececea">
      <span style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#777"><?= e(Service::actionLabel($l)) ?></span>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px"><tr>
        <td width="68" valign="top" style="width:68px"><?= $thumb($im['old']) ?></td>
        <td valign="top"><strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong><br>
          <span style="color:#777"><?= e($l['action'] === 'vratit' ? ($l['reason'] !== '' ? $l['reason'] : 'Vrácení') : ($l['action'] === 'vymenit' ? 'Posíláte zpět' : mb_strimwidth((string) $l['defect_text'], 0, 160, '…'))) ?></span></td>
      </tr>
      <?php if ($l['action'] === 'vymenit'): ?>
      <tr><td width="68" valign="top" style="width:68px;padding-top:8px"><?= $thumb($im['new']) ?></td>
        <td valign="top" style="padding-top:8px"><strong><?= e(($l['new_name'] ?? '') !== '' ? $l['new_name'] : 'Velikost ' . $l['size_to']) ?><?= (int) ($l['new_qty'] ?? 0) > 1 ? ' × ' . (int) $l['new_qty'] : '' ?></strong><br><span style="color:#777">Dostanete<?= $l['new_price'] !== null && ($l['new_name'] ?? '') !== '' ? ' · ' . e(money($l['new_price'])) . '/ks' : '' ?></span></td></tr>
      <?php endif; ?>
      </table>
    </td></tr>
  <?php endforeach; ?>
  </table>
</td></tr>
<tr><td style="padding:20px 28px 0"><a href="<?= e($url) ?>" style="display:inline-block;background:#231F20;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 20px;border-radius:10px">Stav <?= e($w['g']) ?> <?= e(Service::displayNo($ret)) ?> →</a></td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
