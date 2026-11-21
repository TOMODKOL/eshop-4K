<?php $byEmail = ($_GET['zpusob'] ?? '') === 'email'; ?>
<div class="starthero">
  <span class="kicker">Vrácení, výměna a reklamace</span>
  <h1 class="h1">4 KLUCI → <span class="out">VRACÍ</span></h1>
  <p>Vrácení, výměna velikosti i reklamace na pár kliknutí. Balík odnesete na výdejní místo Zásilkovny nebo do Z-BOXu, tisknout nic nemusíte.</p>
</div>
<div class="startgrid">
  <div class="col">
  <?php if (!$byEmail): ?>
    <form class="card findcard" method="post" action="<?= url('/vratky') ?>" style="padding:24px">
      <?= csrf_field() ?>
      <strong style="font-size:17px">Najděte svou objednávku</strong>
      <div class="field"><label class="l" for="em">E-mail z objednávky</label><input class="f" id="em" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required></div>
      <div class="field"><label class="l" for="ob">Číslo objednávky nebo faktury</label><input class="f" id="ob" name="number" inputmode="numeric" autocomplete="off" required><span class="hint">Číslo objednávky (začíná 8810…) najdete v potvrzení objednávky, číslo faktury (začíná 2601…) na faktuře. Stačí jedno z nich.</span></div>
      <a class="link" href="<?= url('/vratky?zpusob=email') ?>">Nemám číslo objednávky ani faktury</a>
      <div style="flex:1"></div>
      <button class="btn">Najít objednávku →</button>
    </form>
  <?php else: ?>
    <form class="card findcard" method="post" action="<?= url('/vratky/odkaz') ?>" style="padding:24px">
      <?= csrf_field() ?>
      <strong style="font-size:17px">Najděte objednávku podle e-mailu</strong>
      <p class="sub">Pošleme vám odkaz, přes který uvidíte všechny své objednávky a vyberete tu, které se vratka týká.</p>
      <div class="field"><label class="l" for="em2">E-mail, se kterým jste objednávali</label><input class="f" id="em2" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required></div>
      <div style="flex:1"></div>
      <button class="btn">Poslat odkaz e-mailem →</button>
      <a class="link" href="<?= url('/vratky') ?>">Mám číslo objednávky</a>
    </form>
  <?php endif; ?>
  </div>
  <div class="col">
    <div class="card" style="padding:24px"><strong style="font-size:17px">Jak to funguje</strong><div class="steps">
      <div><b>1</b><span>Vyberete zboží a co s ním chcete udělat.</span></div>
      <div><b>2</b><span>Dostanete podací kód Zásilkovny.</span></div>
      <div><b>3</b><span>Balík odnesete, ozveme se, jakmile k nám dorazí.</span></div></div></div>
    <div class="note"><strong>Chcete zjistit stav vratky nebo reklamace?</strong> Odkaz je v e-mailu, který jsme vám poslali. Nebo najděte objednávku tady vlevo – zadané vratky a reklamace uvidíte hned nahoře.</div>
    <div class="note">Kupovali jste na prodejně? Stačí přijít s účtenkou: <?= e(cfg('shop.address')) ?>, <?= e(cfg('shop.hours')) ?>.</div>
  </div>
</div>
