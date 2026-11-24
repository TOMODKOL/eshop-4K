<?php /* Podpis a patička – stejné jako e-maily e-shopu (master2). Interní e-maily prodejně ($noHelp) bez podpisu. */ ?>
<?php if (empty($noHelp)): ?>
<tr><td style="padding:24px 28px 30px;font-size:15px;line-height:1.6;color:#444444">
  Máte dotaz? Stačí odpovědět na tento e-mail, nebo zavolejte na <strong style="color:#111111;white-space:nowrap"><?= e(cfg('shop.phone')) ?></strong> (<?= e(cfg('shop.hours')) ?>).<br><br>
  Ať to jede!<br><strong style="color:#111111">4 KLUCI OD KOL</strong>
</td></tr>
<?php else: ?><tr><td style="padding:14px"></td></tr><?php endif; ?>
<tr><td align="center" style="background-color:#f7f7f7;padding:22px 28px;font-size:12px;line-height:1.7;color:#888888;text-align:center">
  <strong style="color:#444444"><?= e(cfg('shop.name')) ?> s.r.o.</strong> · <?= e(cfg('shop.address')) ?><br>
  Telefon: <?= e(cfg('shop.phone')) ?> · <a href="mailto:eshop@4kluciodkol.cz" style="color:#444444;text-decoration:none">eshop@4kluciodkol.cz</a><br>
  <a href="https://client.4kluciodkol.cz/vratky" style="color:#444444">Vrácení, výměna a reklamace</a><br>
  <a href="https://www.4kluciodkol.cz/" style="color:#444444">www.4kluciodkol.cz</a> · <a href="https://www.facebook.com/4kluciodkol" style="color:#444444">Facebook</a> · <a href="https://www.instagram.com/4kluciodkol/" style="color:#444444">Instagram</a>
</td></tr>
</table></td></tr></table></body></html>
