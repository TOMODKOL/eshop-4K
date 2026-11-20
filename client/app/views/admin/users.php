<div class="col wide">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <h1 class="h2">KOLEGOVÉ</h1>
    <a href="<?= url('/admin/uzivatele/ja') ?>" class="link" style="padding:0">Změnit své heslo</a>
  </div>
  <div class="scroll"><table class="t">
    <tr><th>Jméno</th><th>E-mail</th><th>Role</th><th>Naposledy</th><th></th></tr>
    <?php foreach ($users as $u): ?>
    <tr style="<?= $u['active'] ? '' : 'color:var(--muted)' ?>">
      <td><strong><?= e($u['name']) ?></strong><?= $u['active'] ? '' : ' (vypnutý)' ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= $u['role'] === 'admin' ? 'správce' : 'kolega' ?></td>
      <td><?= e(date_cz($u['last_login'], true)) ?></td>
      <td>
        <form method="post" action="<?= url('/admin/uzivatele') ?>" style="display:flex;gap:6px;flex-wrap:wrap;margin:0">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <input class="f" name="pass" type="password" placeholder="nové heslo" minlength="10" style="height:38px;width:150px;font-size:13px" autocomplete="new-password" aria-label="Nové heslo pro <?= e($u['name']) ?>">
          <button class="pill" name="act" value="pass" style="min-height:38px">Nastavit heslo</button>
          <button class="pill" name="act" value="toggle" style="min-height:38px"><?= $u['active'] ? 'Vypnout' : 'Zapnout' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <form class="card" method="post" action="<?= url('/admin/uzivatele') ?>">
    <?= csrf_field() ?><input type="hidden" name="act" value="add">
    <strong>Přidat kolegu</strong>
    <div class="grid2">
      <div class="field"><label class="l" for="n">Jméno</label><input class="f" id="n" name="name" required></div>
      <div class="field"><label class="l" for="m">E-mail</label><input class="f" id="m" name="email" type="email" required></div>
      <div class="field"><label class="l" for="p">Heslo (aspoň 10 znaků)</label><input class="f" id="p" name="pass" type="password" minlength="10" autocomplete="new-password" required></div>
      <div class="field"><label class="l" for="r">Role</label><select class="f" id="r" name="role"><option value="staff">kolega</option><option value="admin">správce</option></select></div>
    </div>
    <button class="btn" style="align-self:flex-start">Přidat</button>
  </form>
</div>
