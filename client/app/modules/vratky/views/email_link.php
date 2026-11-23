<?php require __DIR__ . '/email_head.php'; ?>
<tr><td style="padding:32px 28px 8px 28px">
  <div style="font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#2e9d4f">Vrácení, výměna a reklamace</div>
  <div style="margin:8px 0 12px 0;font-size:26px;line-height:1.25;font-weight:bold;color:#111111">Vaše objednávky</div>
  <div style="font-size:15px;line-height:1.6;color:#444444;margin:0 0 18px">Dobrý den, požádali jste o vrácení, výměnu nebo reklamaci zboží. Kliknutím na tlačítko uvidíte své objednávky z 4kluciodkol.cz.</div>
  <a href="<?= e($url) ?>" style="display:inline-block;background:#231F20;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 20px;border-radius:6px">Zobrazit moje objednávky</a>
  <div style="margin:18px 0 0;font-size:12px;line-height:1.6;color:#888888">Odkaz platí 24 hodin. Pokud jste o nic nežádali, e-mail ignorujte.</div>
</td></tr>
<?php require __DIR__ . '/email_foot.php'; ?>
