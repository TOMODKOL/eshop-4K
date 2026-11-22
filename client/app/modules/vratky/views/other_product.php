<?php
/** Zboží z e-shopu pro výměnu za jiné zboží (vkládá se do formuláře i přes /vratky/zbozi). @var ?array $p @var int $id @var string $sel @var string $url @var int $oqty */
$oqty = max(1, (int) ($oqty ?? 1));
if ($url === ''): return; endif;
if (!$p): ?>
  <div class="flash err" style="margin:0">Zboží se nepodařilo načíst. Vložte prosím celý odkaz na stránku zboží z 4kluciodkol.cz (adresa končí např. „-p188286/“).</div>
<?php return; endif;
$anyOk = (bool) array_filter($p['options'], fn($o) => $o['ok']); ?>
<div class="otherprod">
  <?php $one = $p['single'] ? $p['options'][0] : null; ?>
  <div class="op-head">
    <?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt="" width="64" height="64" loading="lazy"><?php endif; ?>
    <div class="op-t"><strong><?= e($p['name']) ?></strong>
      <?php if ($one): ?><span class="op-m"><?= $one['price'] !== null ? '<b>' . e(money($one['price'])) . '</b>' : '' ?><span class="a <?= e($one['cls']) ?>"><?= e($one['avail']) ?></span></span><?php endif; ?>
      <a class="link" href="<?= e($p['url']) ?>" target="_blank" rel="noopener">Zobrazit na e-shopu ↗</a></div>
  </div>
  <?php if ($one): ?>
    <input class="sr" type="radio" name="other_pick[<?= $id ?>]" value="" data-price="<?= e((string) ($one['price'] ?? '')) ?>" <?= $one['ok'] ? 'checked' : 'disabled' ?> hidden>
  <?php else: ?>
    <span class="l">Velikost</span>
    <div class="sizegrid">
    <?php foreach ($p['options'] as $o): ?>
      <label class="sz<?= $o['ok'] ? '' : ' na' ?>"><input class="sr" type="radio" name="other_pick[<?= $id ?>]" value="<?= e($o['value']) ?>" data-price="<?= e((string) ($o['price'] ?? '')) ?>" <?= $o['ok'] ? '' : 'disabled' ?> <?= $o['ok'] && $sel === $o['value'] ? 'checked' : '' ?>>
        <span class="v"><?= e($o['value']) ?></span><span class="a <?= e($o['cls']) ?>"><?= e($o['avail']) ?></span><?php if ($o['ok']): ?><span class="p"><?= e(money($o['price'])) ?></span><?php endif; ?></label>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($anyOk): ?>
  <div class="qtyrow">
    <label class="l" for="oq-<?= $id ?>">Kolik kusů nového zboží?</label>
    <?= qty_stepper('other_qty[' . $id . ']', 'oq-' . $id, $oqty, 20, ' data-oqty="' . $id . '"') ?>
  </div>
  <?php else: ?><div class="flash err" style="margin:0">Toto zboží teď není skladem ani u dodavatele. Vyberte prosím jiné.</div><?php endif; ?>
  <div class="grey" data-diff="<?= $id ?>" hidden></div>
</div>
