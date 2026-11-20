<div class="col">
  <h1 class="h2">MŮJ ÚČET</h1>
  <form class="card" method="post" action="<?= url('/admin/heslo') ?>">
    <?= csrf_field() ?>
    <div class="field"><label class="l" for="old">Současné heslo</label><input class="f" id="old" name="old" type="password" autocomplete="current-password" required></div>
    <div class="field"><label class="l" for="pass">Nové heslo (aspoň 10 znaků)</label><input class="f" id="pass" name="pass" type="password" minlength="10" autocomplete="new-password" required></div>
    <button class="btn">Změnit heslo</button>
  </form>
</div>
