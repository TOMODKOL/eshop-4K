<?php
use App\Modules\Vratky\Service;
/** Upozornění prodejně: zákazník nedostal podací kód Zásilkovny. @var array $ret @var string $admin @var bool $enabled */
$noHelp = true;
require __DIR__ . '/email_head.php'; ?>
<tr><td style="padding:28px 28px 6px">
  <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#BA1B02">Chybí podací kód · <?= e(Service::displayNo($ret)) ?></p>
  <p style="margin:0;font-size:22px;font-weight:700">Zákazník čeká na podací kód Zásilkovny</p>
</td></tr>
<tr><td style="padding:12px 28px 0;font-size:15px;line-height:1.6;color:#444">
  <?= e($ret['customer_name'] ?: $ret['email']) ?> zadal <?= e(Service::displayNo($ret)) ?> s podáním <?= $ret['ship_method'] === 'zbox' ? 'v Z-BOXu' : 'na výdejním místě Zásilkovny' ?>, ale kód se nepodařilo vytvořit. Zákazník dostal e-mail, že mu kód pošleme během pracovního dne.
  <?php if (!empty($ret['packeta_error'])): ?><div style="margin-top:12px;background:#fceff0;border-radius:10px;padding:12px 14px;font-size:14px;color:#821301"><strong>Odpověď Zásilkovny:</strong> <?= e((string) $ret['packeta_error']) ?></div><?php endif; ?>
</td></tr>
<tr><td style="padding:16px 28px 0;font-size:15px;line-height:1.6">
  <strong>Co udělat</strong><br>
  <?php if ($enabled): ?>
  1. Otevřete vratku v administraci tlačítkem níže.<br>
  2. Klikněte na „Vytvořit podací kód a poslat zákazníkovi“ – kód odejde zákazníkovi e-mailem sám.<br>
  3. Když to znovu nevyjde, Zásilkovna má asi výpadek. Zkuste to za hodinu, nebo kód vytvořte v klientské sekci Zásilkovny a pošlete ho zákazníkovi poznámkou u vratky.
  <?php else: ?>
  Napojení na Zásilkovnu je v klientovi vypnuté. Kód vytvořte v klientské sekci Zásilkovny a pošlete ho zákazníkovi poznámkou u vratky.
  <?php endif; ?>
</td></tr>
<tr><td style="padding:20px 28px 0"><a href="<?= e($admin) ?>" style="display:inline-block;background:#231F20;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 20px;border-radius:10px">Otevřít v administraci (<?= e(Service::displayNo($ret)) ?>) →</a></td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
