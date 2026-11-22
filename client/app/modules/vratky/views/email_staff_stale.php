<?php
use App\Modules\Vratky\Service;
/** Upozornění prodejně: zákazník 14 dní neposlal zboží (u výměny je nové zboží zarezervované). @var array $ret @var array $lines @var string $admin @var int $days */
$noHelp = true;
require __DIR__ . '/email_head.php'; ?>
<tr><td style="padding:28px 28px 6px">
  <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#BA1B02">Zboží nedorazilo · <?= e(Service::title($ret, $lines)) ?></p>
  <p style="margin:0;font-size:22px;font-weight:700"><?= e($ret['customer_name'] ?: $ret['email']) ?> zboží <?= (int) $days ?> dní neposlal</p>
</td></tr>
<tr><td style="padding:12px 28px 0;font-size:15px;line-height:1.6;color:#444">
  Zadáno <?= e(date_cz($ret['created_at'], true)) ?>, stav pořád „<?= e(Service::STATUS[$ret['status']] ?? $ret['status']) ?>“. Zákazník dostal po <?= Service::REMIND_DAYS ?> dnech připomínku e-mailem.
  <?php if (!empty($ret['xorder_no'])): ?><div style="margin-top:12px;background:#fceff0;border-radius:10px;padding:12px 14px;font-size:14px;color:#821301"><strong>Nové zboží je zarezervované</strong> přijatou objednávkou <?= e($ret['xorder_no']) ?> – dokud ji nezrušíte, na e-shopu ho nikdo nekoupí.</div><?php endif; ?>
</td></tr>
<tr><td style="padding:16px 28px 0;font-size:15px;line-height:1.6">
  <strong>Co udělat</strong><br>
  1. Zavolejte zákazníkovi<?= ($ret['phone'] ?? '') !== '' ? ' (' . e($ret['phone']) . ')' : '' ?>, jestli zboží ještě pošle.<br>
  2. Když ne: v administraci dejte stav Zamítnuto s důvodem<?= !empty($ret['xorder_no']) ? ' a v Pohodě smažte objednávku ' . e($ret['xorder_no']) . ($ret['zf_no'] ? ' a zálohovou fakturu ' . e($ret['zf_no']) : '') : '' ?>.<br>
  3. Když ano: nic nedělejte, pokračuje se normálně.
</td></tr>
<tr><td style="padding:20px 28px 0"><a href="<?= e($admin) ?>" style="display:inline-block;background:#231F20;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 20px;border-radius:10px">Otevřít v administraci →</a></td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
