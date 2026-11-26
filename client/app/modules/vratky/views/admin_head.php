<?php
use App\Modules\Vratky\Service;
/** Hlavička přehledu: nadpis, hledání, záložky. @var string $tab ('' = Přehled) @var string $heading @var string $q @var array $counts */
?>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <div style="display:flex;flex-direction:column;gap:6px"><span class="kicker">Prodejna</span><h1 class="h2"><?= e($heading) ?></h1></div>
    <form method="get" action="<?= url('/admin/vratky') ?>" style="display:flex;gap:6px;margin:0">
      <label class="sr" for="q">Hledat</label>
      <input class="f" id="q" name="q" value="<?= e($q) ?>" placeholder="číslo, jméno, e-mail, objednávka" style="height:42px;width:260px;max-width:calc(100vw - 130px);font-size:14px">
      <button class="pill">Hledat</button>
    </form>
  </div>
  <nav class="tabs" aria-label="Přehled a typy">
    <a href="<?= url('/admin/vratky') ?>" class="<?= $q === '' && $tab === '' ? 'on' : '' ?>">Přehled</a>
    <?php foreach (Service::TYPY as $t => $lab): $n = (int) ($counts[$t][''] ?? 0); ?>
      <a href="<?= url('/admin/vratky?typ=' . $t) ?>" class="<?= $q === '' && $tab === $t ? 'on' : '' ?>"><?= e($lab) ?></a>
    <?php endforeach; ?>
  </nav>
