<?php /** @var string $content @var string $title @var array $flash */
$u = \App\Core\Auth::user(); $active = $active ?? ''; ?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Administrace') ?> · Client 4K</title>
<link rel="stylesheet" href="<?= asset('app.css') ?>">
<link rel="icon" href="<?= asset('favicon.ico') ?>" sizes="any">
<link rel="shortcut icon" href="<?= asset('favicon.ico') ?>">
</head>
<body>
<header class="top"><div class="in">
  <a class="logo" href="<?= url('/admin') ?>" aria-label="Administrace"><img src="<?= asset('logo-white.png') ?>" alt="4 KLUCI OD KOL" width="187" height="28"></a>
  <?php if ($u): ?>
  <nav class="adminnav" aria-label="Administrace">
    <?php if (cfg('modules.vratky')): ?><a href="<?= url('/admin/vratky') ?>" class="<?= $active === 'vratky' ? 'on' : '' ?>">Vratky</a><?php endif; ?>
    <?php if ($u['role'] === 'admin'): ?><a href="<?= url('/admin/uzivatele') ?>" class="<?= $active === 'users' ? 'on' : '' ?>">Kolegové</a><?php endif; ?>
    <span><?= e($u['name']) ?></span>
    <form method="post" action="<?= url('/admin/odhlaseni') ?>" style="margin:0"><?= csrf_field() ?><button class="link" style="color:#fff;padding:0;font-size:13px">Odhlásit</button></form>
  </nav>
  <?php else: ?><span class="staff">Administrace</span><?php endif; ?>
</div></header>
<?php if (trim((string) cfg('mail.redirect_to', '')) !== ''): ?><div style="background:#ffc524;color:#231F20;font-weight:700;font-size:13px;text-align:center;padding:8px max(16px, env(safe-area-inset-left, 0px))">Testovací režim: všechny e-maily (i zákazníkům) chodí jen na <?= e((string) cfg('mail.redirect_to')) ?></div><?php endif; ?>
<div class="wrap">
<?php foreach ($flash ?? [] as [$t, $m]): ?><div class="col wide" style="margin-bottom:14px"><div class="flash <?= e($t) ?>"><?= e($m) ?></div></div><?php endforeach; ?>
<?= $content ?>
</div>
<script src="<?= asset('app.js') ?>" defer></script>
</body>
</html>
