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
<tr><td style="padding:16px 28px 0"><div style="background:#ffc524;border-radius:12px;padding:18px;text-align:center">
  <div style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase">Podací kód Zásilkovny</div>
  <div style="font-size:34px;font-weight:700;letter-spacing:3px;margin-top:4px"><?= e($ret['packeta_password']) ?></div></div></td></tr>
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
