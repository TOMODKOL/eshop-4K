<?php
use App\Modules\Vratky\Service;
/** Kontrola zboží z odkazu v e-mailu (bez přihlášení). @var array $ret @var array $lines @var string $a @var string $e @var string $s @var ?string $done @var ?string $err */
$imgs = Service::lineImages($lines); ?>
<div class="col" style="max-width:640px;margin:0 auto">
  <span class="kicker"><?= e(Service::title($ret, $lines)) ?> · <?= e(Service::displayNo($ret)) ?></span>
  <h1 class="h2"><?= $done ? 'HOTOVO' : ($a === 'problem' ? 'PROBLÉM SE ZBOŽÍM' : 'ZBOŽÍ V POŘÁDKU?') ?></h1>
  <?php if ($done): ?><div class="flash ok"><?= e($done) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <div class="card">
    <?php foreach ($lines as $l): if ($l['action'] === 'reklamovat') continue; $u = $imgs[$l['id']]['old'] ?? ''; ?>
      <div class="pline"><?php if ($u !== ''): ?><img class="pthumb" src="<?= e($u) ?>" alt=""><?php else: ?><span class="pthumb ph"></span><?php endif; ?>
        <div style="min-width:0"><span class="hint"><?= e(Service::actionLabel($l)) ?></span><br><strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong></div></div>
    <?php endforeach; ?>
  </div>
  <?php if (!$done): ?>
  <form class="card" method="post" data-once>
    <input type="hidden" name="e" value="<?= e($e) ?>"><input type="hidden" name="s" value="<?= e($s) ?>"><input type="hidden" name="a" value="<?= e($a) ?>">
    <?php if ($a === 'problem'): ?>
      <div class="field"><label class="l" for="why">Co je špatně? (dostane to zákazník)</label><textarea class="f" id="why" name="why" rows="3" required placeholder="Např. Zboží je použité / chybí původní krabice…"></textarea></div>
      <label class="chk"><input type="radio" name="what" value="zamitnout" checked><span>Zamítnout – zákazník dostane e-mail s důvodem</span></label>
      <label class="chk"><input type="radio" name="what" value="vyresit"><span>Jen poznamenat, vyřeším s ním sám</span></label>
      <button class="btn" style="align-self:flex-start">Uložit</button>
    <?php else: ?>
      <p style="margin:0">Potvrzením se v Pohodě vystaví doklad a zákazník dostane e-mail.</p>
      <button class="btn" style="align-self:flex-start;background:var(--green);color:#fff">Ano, zboží je v pořádku ✓</button>
      <a class="link" href="?e=<?= e($e) ?>&amp;s=<?= e($s) ?>&amp;a=problem">Ne, je tam problém</a>
    <?php endif; ?>
  </form>
  <?php endif; ?>
  <a class="link" href="<?= url('/admin/vratky/' . $ret['no']) ?>">Otevřít v administraci</a>
</div>
