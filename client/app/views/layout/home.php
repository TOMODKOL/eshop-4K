<div class="col">
  <div style="display:flex;flex-direction:column;gap:10px;padding:0 4px">
    <span class="kicker">Klientská zóna</span>
    <h1 class="h1">4 KLUCI → <span class="out">PRO VÁS</span></h1>
  </div>
  <?php if (cfg('modules.vratky')): ?>
  <a class="card" href="<?= url('/vratky') ?>" style="text-decoration:none"><strong>Vrácení, výměna a reklamace</strong><span style="color:var(--text);font-size:14px">Zboží z e-shopu vrátíte nebo vyměníte na pár kliknutí.</span></a>
  <?php endif; ?>
</div>
