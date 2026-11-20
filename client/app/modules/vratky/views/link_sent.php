<div class="col">
  <h1 class="h2">ZKONTROLUJTE E-MAIL</h1>
  <div class="card">
    <p style="margin:0">Pokud u nás máte na adrese <strong><?= e($email) ?></strong> objednávky, poslali jsme na ni odkaz. Po kliknutí na něj uvidíte své objednávky. Odkaz platí 24 hodin.</p>
    <p class="hint" style="margin:0">E-mail nepřišel? Podívejte se i do složky Hromadné nebo Spam, případně nám zavolejte na <?= e(cfg('shop.phone')) ?>.</p>
  </div>
  <a class="link" href="<?= url('/vratky?zpusob=email') ?>">Zadat jiný e-mail</a>
</div>
