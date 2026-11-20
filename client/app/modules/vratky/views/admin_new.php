<?php
use App\Modules\Vratky\Sync;
/** @var string $q @var string $od @var string $do @var array $rows @var bool $searched */
?>
<div class="col wide">
  <div style="display:flex;flex-direction:column;gap:6px"><span class="kicker">Prodejna</span><h1 class="h2">NOVÁ REKLAMACE</h1></div>
  <div class="grey">Najděte doklad, na kterém zákazník zboží koupil: číslo prodejky (26PH…), faktury nebo objednávky, jméno, telefon nebo e-mail.
    Prodejky jméno zákazníka nemají – hledejte podle názvu nebo kódu zboží a omezte datum nákupu.</div>
  <form class="card" method="get" action="<?= url('/admin/vratky/nova') ?>" style="display:flex;flex-direction:row;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="field" style="flex:1 1 260px;min-width:0"><label class="l" for="q">Doklad, zákazník nebo zboží</label>
      <input class="f" id="q" name="q" value="<?= e($q) ?>" placeholder="26PH03231, Novák, 604…, helma POC" autofocus></div>
    <div class="field" style="flex:0 1 160px"><label class="l" for="od">Koupeno od</label><input class="f" type="date" id="od" name="od" value="<?= e($od) ?>"></div>
    <div class="field" style="flex:0 1 160px"><label class="l" for="do">do</label><input class="f" type="date" id="do" name="do" value="<?= e($do) ?>"></div>
    <button class="btn dark" style="width:auto">Hledat</button>
  </form>

  <?php if ($searched && !$rows): ?><div class="card">Nic nenalezeno. Doklady se z Pohody načítají každou hodinu – dnešní nákup tu může chybět.</div><?php endif; ?>
  <?php if (count($rows) >= 40): ?><div class="hint">Zobrazeno prvních 40 dokladů – upřesněte hledání nebo datum.</div><?php endif; ?>

  <div class="vr">
  <?php foreach ($rows as $r): ?>
    <a class="vcard" href="<?= url('/admin/vratky/nova/' . (int) $r['id']) ?>" style="text-decoration:none;color:inherit">
      <div class="hd"><span class="no"><?= e($r['invoice_no']) ?></span><span class="st"><?= e(Sync::SOURCES[$r['source']] ?? $r['source']) ?></span></div>
      <div class="meta"><span><?= e(date_cz($r['invoice_date'])) ?></span><?php if ($r['customer_name']): ?><span><?= e($r['customer_name']) ?></span><?php endif; ?><?php if ($r['order_no']): ?><span>obj. <?= e($r['order_no']) ?></span><?php endif; ?><span><?= e(money($r['total'])) ?></span></div>
      <?php foreach ($r['item_names'] as $n): ?><div class="li"><?= e($n) ?></div><?php endforeach; ?>
    </a>
  <?php endforeach; ?>
  </div>
</div>
