<?php
use App\Modules\Vratky\Service;
/** Přehled: co čeká na prodejnu a co probíhá jinde. @var array $mine @var array $other @var ?array $lastSync */
$q = ''; $tab = ''; $heading = 'PŘEHLED'; $counts = [];
$typLabel = ['reklamace' => 'Reklamace', 'vymena' => 'Výměna', 'vratka' => 'Vrácení'];
?>
<div class="col wide">
  <?php require __DIR__ . '/admin_head.php'; ?>

  <section class="col" style="gap:10px;max-width:none;width:100%;align-items:stretch">
    <h2 class="kicker" style="margin:6px 0 0">Na vás čeká <?= count($mine) ? '· ' . count($mine) : '' ?></h2>
    <?php if (!$mine): ?><div class="card" style="flex-direction:row;align-items:center;gap:10px"><span class="okdot">✓</span><span>Nic na vás nečeká. Nové úkoly se tu objeví samy.</span></div><?php endif; ?>
    <div class="vr">
    <?php foreach ($mine as $r): $imgs = Service::lineImages($r['lines']); ?>
      <a class="vcard todo" href="<?= url('/admin/vratky/' . $r['no']) ?>">
        <div class="hd"><strong class="todo-l"><?= e($r['todo']['label']) ?></strong><?php if ($r['todo']['who'] === 'tomas'): ?><span class="st">Tomáš</span><?php endif; ?></div>
        <div class="meta"><span class="no-s"><?= e(Service::displayNo($r)) ?></span><span><?= e($r['customer_name'] ?: $r['email']) ?></span><span><?= e(date_cz($r['created_at'])) ?></span></div>
        <?php foreach ($r['lines'] as $l): $u = $imgs[$l['id']]['old'] ?? ''; ?>
          <div class="li pline"><?php if ($u !== ''): ?><img class="pthumb sm" src="<?= e($u) ?>" alt="" loading="lazy"><?php endif; ?><div style="min-width:0"><strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong><br><span class="hint"><?= e(Service::actionLabel($l)) ?></span></div></div>
        <?php endforeach; ?>
        <span class="hint" style="font-size:13px"><?= e($r['todo']['hint']) ?></span>
      </a>
    <?php endforeach; ?>
    </div>
  </section>

  <?php if ($other): ?>
  <section class="col" style="gap:8px;max-width:none;width:100%;align-items:stretch">
    <h2 class="kicker" style="margin:10px 0 0">Probíhá – nic nedělejte · <?= count($other) ?></h2>
    <div class="card" style="padding:4px 16px;gap:0">
    <?php foreach ($other as $r): ?>
      <a class="orow" href="<?= url('/admin/vratky/' . $r['no']) ?>">
        <span class="who"><?= e(Service::WHO[$r['todo']['who']] ?? '') ?></span>
        <span class="txt"><strong><?= e(Service::displayNo($r)) ?></strong> · <?= e($r['customer_name'] ?: $r['email']) ?> · <?= e($typLabel[$r['typ']] ?? '') ?><br><span class="hint"><?= e($r['todo']['label']) ?></span></span>
      </a>
    <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <div class="hint">Vyřízené a zamítnuté najdete v záložkách nahoře nebo hledáním. Poslední data z Pohody: <?= $lastSync ? e(date_cz($lastSync['received_at'], true)) : 'zatím nepřišla' ?> · <a href="<?= url('/admin/napoveda') ?>">Nápověda</a></div>
</div>
