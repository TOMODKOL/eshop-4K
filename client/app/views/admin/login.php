<div class="col">
  <h1 class="h2">PŘIHLÁŠENÍ</h1>
  <form class="card" method="post" action="<?= url('/admin/prihlaseni') ?>">
    <?= csrf_field() ?>
    <div class="field"><label class="l" for="email">E-mail</label><input class="f" id="email" name="email" type="email" autocomplete="username" required></div>
    <div class="field"><label class="l" for="pass">Heslo</label><input class="f" id="pass" name="pass" type="password" autocomplete="current-password" required></div>
    <button class="btn">Přihlásit</button>
  </form>
</div>
