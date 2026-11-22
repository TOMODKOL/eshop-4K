<?php
use App\Modules\Vratky\Service;
/** Prodejně: zboží dorazilo – zkontrolujte ho jedním kliknutím. @var array $ret @var array $lines @var string $url @var string $admin */
$noHelp = true;
$label = 'font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#777';
require __DIR__ . '/email_head.php'; ?>
<tr><td style="padding:28px 28px 6px">
  <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#2e9d4f">Dorazilo · <?= e(Service::title($ret, $lines)) ?> · <?= e(Service::displayNo($ret)) ?></p>
  <p style="margin:0;font-size:22px;font-weight:700">Zkontrolujte prosím zboží</p>
  <p style="margin:8px 0 0;font-size:15px;line-height:1.6;color:#444"><?= e($ret['customer_name'] ?: $ret['email']) ?>. Když je v pořádku, klikněte na tlačítko – doklad v Pohodě i e-mail zákazníkovi už půjdou samy.</p>
</td></tr>
<tr><td style="padding:16px 28px 0">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.45">
  <?php $imgs = Service::lineImages($lines); $th = fn(string $u) => $u !== '' ? '<img src="' . e($u) . '" width="56" height="56" alt="" style="display:block;width:56px;height:56px;object-fit:contain;border:1px solid #ececea;border-radius:8px;background:#ffffff">' : '<div style="width:56px;height:56px;border-radius:8px;background:#f2f2f0"></div>'; ?>
  <?php foreach ($lines as $l): if ($l['action'] === 'reklamovat') continue; $im = $imgs[$l['id']] ?? ['old' => '', 'new' => '']; ?>
    <tr><td style="padding:10px 0;border-bottom:1px solid #ececea"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
      <td width="68" valign="top" style="width:68px"><?= $th($im['old']) ?></td>
      <td valign="top"><span style="<?= $label ?>"><?= e(Service::actionLabel($l)) ?></span><br><strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong><span style="color:#777"><?= $l['item_code'] ? ' · ' . e($l['item_code']) : '' ?></span>
        <?php if ($l['action'] === 'vratit' && $l['reason'] !== ''): ?><br><span style="color:#444">Důvod: <?= e($l['reason']) ?></span><?php endif; ?></td></tr></table></td></tr>
  <?php endforeach; ?>
  </table>
</td></tr>
<tr><td style="padding:22px 28px 0">
  <a href="<?= e($url) ?>&amp;a=ok" style="display:inline-block;background:#2e9d4f;color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;padding:14px 22px;border-radius:10px;margin:0 8px 8px 0">Zboží je v pořádku ✓</a>
  <a href="<?= e($url) ?>&amp;a=problem" style="display:inline-block;background:#ffffff;color:#BA1B02;text-decoration:none;font-weight:700;font-size:16px;padding:12px 20px;border-radius:10px;border:2px solid #BA1B02;margin:0 0 8px">Problém</a>
</td></tr>
<tr><td style="padding:8px 28px 0;font-size:13px;line-height:1.5;color:#777">Po kliknutí ještě jednou potvrdíte. Podrobnosti najdete i <a href="<?= e($admin) ?>" style="color:#777">v administraci</a>.</td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
