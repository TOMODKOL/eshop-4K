<?php
use App\Modules\Vratky\Service;
/** @var array $rows @var array $counts @var string $stav @var string $q @var ?array $lastSync */
$colors = ['novy' => '#ffc524', 'na_ceste' => '#7fb0e6', 'dorazilo' => '#231F20', 'v_reseni' => '#9b59b6', 'vyrizeno' => '#2e9d4f', 'zamitnuto' => '#c0392b'];
$open = ($counts['novy'] ?? 0) + ($counts['na_ceste'] ?? 0) + ($counts['dorazilo'] ?? 0) + ($counts['v_reseni'] ?? 0);
?>
<div class="col wide">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <div style="display:flex;flex-direction:column;gap:6px"><span class="kicker">Prodejna</span><h1 class="h2">PŘEHLED VRATEK</h1></div>
    <form method="get" action="<?= url('/admin/vratky') ?>" style="display:flex;gap:6px;margin:0">
      <?php if ($stav !== ''): ?><input type="hidden" name="stav" value="<?= e($stav) ?>"><?php endif; ?>
      <label class="sr" for="q">Hledat</label>
      <input class="f" id="q" name="q" value="<?= e($q) ?>" placeholder="číslo, jméno, e-mail, objednávka" style="height:42px;width:260px;font-size:14px">
      <button class="pill">Hledat</button>
    </form>
  </div>
  <div class="stat">
    <?php foreach (['novy', 'na_ceste', 'dorazilo', 'v_reseni', 'vyrizeno'] as $s): ?>
      <div><b><?= (int) ($counts[$s] ?? 0) ?></b><span><?= e(Service::STATUS[$s]) ?></span></div>
    <?php endforeach; ?>
  </div>
  <nav class="chips" aria-label="Filtr podle stavu">
    <a class="pill <?= $stav === '' ? 'on' : '' ?>" href="<?= url('/admin/vratky') ?>" style="display:inline-flex;align-items:center;text-decoration:none">Otevřené (<?= $open ?>)</a>
    <?php foreach (Service::STATUS as $k => $lab): ?>
      <a class="pill <?= $stav === $k ? 'on' : '' ?>" href="<?= url('/admin/vratky?stav=' . $k) ?>" style="display:inline-flex;align-items:center;text-decoration:none"><?= e($lab) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="vr">
    <?php foreach ($rows as $r):
      $claim = Service::hasAction($r['lines'], 'reklamovat');
      $late = $claim && $r['claim_deadline'] && !in_array($r['status'], ['vyrizeno', 'zamitnuto'], true) && strtotime($r['claim_deadline']) - time() < 5 * 86400; ?>
    <a class="vcard" href="<?= url('/admin/vratky/' . $r['no']) ?>" style="border-left-color:<?= $colors[$r['status']] ?? '#dcdcd8' ?>;text-decoration:none;color:inherit">
      <div class="hd"><span class="no"><?= e(Service::displayNo($r)) ?></span><span class="st"><?= e(Service::STATUS[$r['status']] ?? $r['status']) ?></span></div>
      <div class="meta"><span><?= e(date_cz($r['created_at'], true)) ?></span><span><?= e($r['customer_name'] ?: $r['email']) ?></span><span><?= e(Service::SHIP[$r['ship_method']] ?? '') ?></span><?php if ($r['order_no'] || $r['invoice_no']): ?><span>obj. <?= e($r['order_no'] ?: $r['invoice_no']) ?></span><?php endif; ?></div>
      <?php foreach ($r['lines'] as $l): ?>
        <div class="li"><em><?= e(Service::ACTIONS[$l['action']] ?? '') ?></em><br><strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong></div>
      <?php endforeach; ?>
      <?php if ($claim && $r['claim_deadline']): ?><div style="font-size:13px">Vyřídit do <span class="<?= $late ? 'warn' : '' ?>"><?= e(date_cz($r['claim_deadline'])) ?></span></div><?php endif; ?>
      <?php if ($r['refund_total'] > 0): ?><div style="font-size:14px">Vrátit: <strong><?= e(money($r['refund_total'])) ?></strong><?= $r['postage_applies'] ? ' − poštovné' : '' ?></div><?php endif; ?>
      <?php if ($r['pohoda_state'] === 'cekame'): ?><div class="warn" style="font-size:13px">Reklamace od zákazníka – založte ji v Pohodě</div><?php endif; ?>
      <?php if ($r['packeta_error']): ?><div class="warn" style="font-size:13px">Chyba Zásilkovny – otevřete detail</div><?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="card">Nic tu není.</div><?php endif; ?>
  </div>
  <div class="hint">Poslední data z Pohody: <?= $lastSync ? e(date_cz($lastSync['received_at'], true)) . ' (' . (int) $lastSync['orders'] . ' dokladů)' : 'zatím nepřišla' ?></div>
</div>
