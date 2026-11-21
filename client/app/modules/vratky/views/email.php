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
  <?php foreach ($lines as $l): ?>
    <tr><td style="padding:10px 0;border-bottom:1px solid #ececea">
      <span style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#777"><?= e(Service::actionLabel($l)) ?></span><br>
      <strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong><br>
      <span style="color:#777"><?= e($l['action'] === 'vratit' ? $l['reason'] : ($l['action'] === 'vymenit' ? Service::swapText($l) : mb_strimwidth((string) $l['defect_text'], 0, 160, '…'))) ?></span>
    </td></tr>
  <?php endforeach; ?>
  </table>
</td></tr>
<tr><td style="padding:20px 28px 0"><a href="<?= e($url) ?>" style="display:inline-block;background:#231F20;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 20px;border-radius:10px">Stav <?= e($w['g']) ?> <?= e(Service::displayNo($ret)) ?> →</a></td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
