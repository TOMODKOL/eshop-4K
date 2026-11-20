<div class="col">
  <h1 class="h2">PRVNÍ SPUŠTĚNÍ</h1>
  <div class="grey">Vytvoří tabulky v databázi a prvního správce. Instalační klíč je hodnota <code>sync_token</code> z config/config.php.</div>
  <form class="card" method="post" action="<?= url('/admin/instalace') ?>">
    <?= csrf_field() ?>
    <div class="field"><label class="l" for="key">Instalační klíč</label><input class="f" id="key" name="key" type="password" required></div>
    <div class="field"><label class="l" for="name">Vaše jméno</label><input class="f" id="name" name="name" required></div>
    <div class="field"><label class="l" for="email">E-mail (přihlašovací)</label><input class="f" id="email" name="email" type="email" required></div>
    <div class="field"><label class="l" for="pass">Heslo (aspoň 10 znaků)</label><input class="f" id="pass" name="pass" type="password" minlength="10" autocomplete="new-password" required></div>
    <button class="btn">Vytvořit</button>
  </form>
</div>
