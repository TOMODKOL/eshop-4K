<?php use App\Modules\Vratky\Service; ?>
<div class="col">
  <h1 class="h2">VAŠE OBJEDNÁVKY</h1>
  <span class="hint" style="padding:0 4px"><?= e($email) ?> · vyberte objednávku, které se vratka týká. U objednávek uvidíte i stav vratek a reklamací, které už jste zadali.</span>
  <?php if (!$orders): ?><div class="card">Za posledních 24 měsíců jsme žádnou objednávku nenašli.</div><?php endif; ?>
  <?php foreach ($orders as $o): $dl = Service::deadlines($o); ?>
  <form method="post" action="<?= url('/vratky/vybrat') ?>" style="margin:0">
    <?= csrf_field() ?><input type="hidden" name="order" value="<?= (int) $o['id'] ?>">
    <button class="ordrow"><span class="t"><strong>Objednávka <?= e($o['order_no'] ?: $o['invoice_no']) ?></strong>
      Faktura <?= e(date_cz($o['invoice_date'])) ?> · <?= e(money($o['total'])) ?> ·
      <?= $dl['can_return'] ? 'vrátit lze do ' . e(date_cz($dl['return_until'])) : 'lze už jen reklamovat' ?></span><span aria-hidden="true">→</span></button>
  </form>
  <?php foreach (Service::returnsForOrder((int) $o['id']) as $p): ?>
    <a class="link" style="padding:0 4px;font-size:13px" href="<?= url('/vratky/stav/' . $p['token']) ?>"><?= e($p['action'] === 'reklamovat' ? 'Reklamace' : 'Vratka') ?> <?= e($p['no']) ?>: <?= e(Service::STATUS[$p['status']] ?? $p['status']) ?> – zobrazit stav →</a>
  <?php endforeach; ?>
  <?php endforeach; ?>
</div>
