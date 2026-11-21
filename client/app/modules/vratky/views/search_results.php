<?php
/** Výsledky hledání na e-shopu pro výměnu za jiné zboží. @var ?array $res @var int $id @var string $q */
if ($res === null): ?>
  <div class="flash err" style="margin:0">E-shop se teď nepodařilo prohledat. Zkuste to prosím znovu, nebo vložte odkaz na zboží.</div>
<?php elseif (!$res): ?>
  <div class="grey">Pro „<?= e($q) ?>“ jsme skladem nic nenašli. Zkuste jiné slovo, třeba značku nebo typ zboží.</div>
<?php else: ?>
  <div class="results">
  <?php foreach ($res as $r): ?>
    <button type="button" class="res" data-pick-url="<?= e($r['url']) ?>" data-pick-id="<?= $id ?>">
      <?php if ($r['image']): ?><img src="<?= e($r['image']) ?>" alt="" width="56" height="56" loading="lazy"><?php else: ?><span class="noimg"></span><?php endif; ?>
      <span class="rt"><strong><?= e($r['name']) ?></strong><span><?= $r['price'] !== null ? e(money($r['price'])) . ' · ' : '' ?><em class="zel"><?= e($r['avail']) ?></em></span></span>
      <span aria-hidden="true" class="go">Vybrat →</span>
    </button>
  <?php endforeach; ?>
  </div>
<?php endif;
