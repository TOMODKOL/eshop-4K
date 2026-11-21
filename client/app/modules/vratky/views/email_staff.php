<?php
use App\Modules\Vratky\Service;
/** E-mail prodejně o nové vratce / reklamaci. @var array $ret @var array $lines @var ?array $order @var string $admin @var int $photos */
$w = Service::words($lines);
$noHelp = true;
$label = 'font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#777';
$row = fn(string $k, string $v) => '<tr><td style="padding:6px 12px 6px 0;color:#777;white-space:nowrap;vertical-align:top">' . e($k) . '</td><td style="padding:6px 0;font-weight:700">' . $v . '</td></tr>';
require __DIR__ . '/email_head.php'; ?>
<tr><td style="padding:28px 28px 6px">
  <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#2e9d4f">Nová <?= e($w['n']) ?> <?= e(Service::displayNo($ret)) ?></p>
  <p style="margin:0;font-size:22px;font-weight:700"><?= e($ret['customer_name'] ?: $ret['email']) ?></p>
</td></tr>
<tr><td style="padding:12px 28px 0">
  <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.45">
    <?= $row('E-mail', '<a href="mailto:' . e($ret['email']) . '" style="color:#231F20">' . e($ret['email']) . '</a>') ?>
    <?php if ($ret['phone'] !== ''): ?><?= $row('Telefon', e($ret['phone'])) ?><?php endif; ?>
    <?php if ($order): ?><?= $row('Objednávka', e(trim(($order['order_no'] ?: '') . ($order['invoice_no'] ? ' · faktura ' . $order['invoice_no'] : ''), ' ·'))) ?><?php endif; ?>
    <?= $row('Doprava k nám', e(Service::SHIP[$ret['ship_method']] ?? $ret['ship_method']) . ($ret['packeta_password'] ? ' · kód ' . e($ret['packeta_password']) : '')) ?>
    <?php $bal = Service::balance($ret); if ($bal > 0): ?><?= $row('Vrátit', e(money($bal)) . ($ret['bank_account'] !== '' ? ' na účet ' . e($ret['bank_account']) : '')) ?><?php endif; ?>
    <?php if ($bal < 0): ?><?= $row('Doplatí', e(money(-$bal)) . ' · ' . e(mb_strtolower(Service::PAY[$ret['pay_method']] ?? ''))) ?><?php endif; ?>
  </table>
</td></tr>
<tr><td style="padding:16px 28px 0">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.45;border-top:1px solid #ececea">
  <?php foreach ($lines as $l): ?>
    <tr><td style="padding:12px 0;border-bottom:1px solid #ececea">
      <span style="<?= $label ?>"><?= e(Service::actionLabel($l)) ?></span><br>
      <strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong><span style="color:#777"><?= $l['item_code'] ? ' · ' . e($l['item_code']) : '' ?> · <?= e(money($l['unit_price'])) ?></span><br>
      <?php if ($l['action'] === 'vratit'): ?><span style="color:#444">Důvod: <?= e($l['reason']) ?></span>
      <?php elseif ($l['action'] === 'vymenit'): ?><span style="color:#444"><?php if (($l['new_name'] ?? '') !== ''): ?>Za jiné zboží: <strong><?= e($l['new_name']) ?></strong> · <?= e(money($l['new_price'])) ?><?php else: ?>Za velikost <strong><?= e($l['size_to']) ?></strong><?php endif; ?><?= ($l['new_code'] ?? '') !== '' ? ' · kód ' . e($l['new_code']) : '' ?></span>
      <?php else: ?><span style="color:#444"><?= nl2br(e(mb_strimwidth((string) $l['defect_text'], 0, 600, '…'))) ?></span><?php endif; ?>
    </td></tr>
  <?php endforeach; ?>
  </table>
  <?php if ($photos > 0): ?><p style="margin:10px 0 0;font-size:13px;color:#777">Fotky vady: <?= $photos ?> – najdete je v administraci.</p><?php endif; ?>
  <?php if (trim((string) $ret['note']) !== ''): ?><div style="margin-top:14px;background:#f7f7f7;border-radius:10px;padding:12px 14px;font-size:14px;line-height:1.5"><span style="<?= $label ?>">Poznámka zákazníka</span><br><?= nl2br(e((string) $ret['note'])) ?></div><?php endif; ?>
</td></tr>
<tr><td style="padding:20px 28px 0"><a href="<?= e($admin) ?>" style="display:inline-block;background:#231F20;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 20px;border-radius:10px">Otevřít v administraci →</a></td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
