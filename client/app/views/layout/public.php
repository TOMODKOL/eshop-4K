<?php /** @var string $content @var string $title @var array $flash */ ?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Klientská zóna') ?> · 4 KLUCI OD KOL</title>
<link rel="stylesheet" href="<?= asset('app.css') ?>">
<link rel="icon" href="<?= asset('logo-black.png') ?>">
</head>
<body>
<header class="top"><div class="in">
  <a class="logo" href="<?= url($homeUrl ?? '/') ?>" aria-label="Na začátek"><img src="<?= asset('logo-white.png') ?>" alt="4 KLUCI OD KOL" width="187" height="28"></a>
  <div class="r">
    <span class="help">Poradíme: <strong><?= e(cfg('shop.phone')) ?></strong></span>
    <a class="back" href="<?= e(cfg('shop.web')) ?>"><span class="arr">←</span> Zpět na e-shop</a>
  </div>
</div></header>
<div class="wrap">
<?php foreach ($flash ?? [] as [$t, $m]): ?><div class="col" style="margin-bottom:14px"><div class="flash <?= e($t) ?>"><?= e($m) ?></div></div><?php endforeach; ?>
<?= $content ?>
</div>
<footer class="foot"><strong>Poradíme na <?= e(cfg('shop.phone')) ?></strong> · <?= e(cfg('shop.hours')) ?><br><?= e(cfg('shop.name')) ?> s.r.o. · <?= e(cfg('shop.address')) ?></footer>
<script src="<?= asset('app.js') ?>" defer></script>
</body>
</html>
