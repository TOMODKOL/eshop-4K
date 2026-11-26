<?php
use App\Modules\Vratky\Service;
/** @var array $rows @var array $counts @var string $typ @var string $stav @var string $q @var ?array $lastSync */
$colors = ['novy' => '#ffc524', 'na_ceste' => '#7fb0e6', 'dorazilo' => '#231F20', 'v_reseni' => '#9b59b6', 'vyrizeno' => '#2e9d4f', 'zamitnuto' => '#c0392b'];
$typLabel = ['reklamace' => 'Reklamace', 'vymena' => 'Výměna', 'vratka' => 'Vrácení'];
?>
<div class="col wide">
  <?php $tab = $typ; $heading = $q !== '' ? 'HLEDÁNÍ' : mb_strtoupper(Service::TYPY[$typ]); require __DIR__ . '/admin_head.php'; ?>
  <?php if ($q === ''): ?>
  <nav class="stat" aria-label="Filtr podle stavu">
    <?php foreach (Service::DLAZDICE[$typ] as $k => $lab): $n = (int) ($counts[$typ][$k] ?? 0);
      $href = url('/admin/vratky?typ=' . $typ . ($k === '' ? '' : '&stav=' . $k)); ?>
      <a href="<?= $href ?>" class="<?= $stav === $k ? 'on' : '' ?><?= in_array($k, ['proplatit', 'doplatek'], true) && $n ? ' hot' : '' ?>" aria-current="<?= $stav === $k ? 'true' : 'false' ?>"><b><?= $n ?></b><span><?= e($lab ?? Service::STATUS[$k]) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="hint legend"><?php foreach (['novy' => 'čeká na odeslání', 'na_ceste' => 'na cestě', 'dorazilo' => 'dorazilo', 'v_reseni' => 'u dodavatele', 'vyrizeno' => 'vyřízeno', 'zamitnuto' => 'zamítnuto'] as $k => $lab): ?><span><i style="background:<?= $colors[$k] ?>"></i><?= $lab ?></span><?php endforeach; ?></div>
  <?php else: ?>
  <div class="hint">Výsledky hledání „<?= e($q) ?>“ ve všech reklamacích, vratkách a výměnách, i vyřízených · <a href="<?= url('/admin/vratky') ?>">zpět na přehled</a></div>
  <?php endif; ?>
  <?php \App\Modules\Vratky\Eshop::$budget = 6; // výpis nečeká na desítky stránek e-shopu ?>
  <?php if ($stav === 'proplatit'): ?>
  <div class="card">
    <strong>Peníze k odeslání</strong>
    <span class="hint">Pošlete příkazem z banky, VS = číslo dokladu. Jakmile platba projde výpisem, vratka se uzavře sama.</span>
    <?php if (!$rows): ?><span class="hint">Nic nečeká.</span><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <div style="font-size:14px;line-height:1.5;border-top:1px solid var(--line2);padding-top:8px"><strong><?= e(money(Service::payout($r))) ?></strong> · účet <span style="user-select:all"><?= e($r['bank_account']) ?></span> · VS <span style="user-select:all"><?= e((string) $r['doc_no']) ?></span><br>
        <span class="hint"><?= e($r['customer_name'] ?: $r['email']) ?> · obj. <?= e($r['order_no'] ?: $r['invoice_no']) ?> · <?= e(Service::displayNo($r)) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="vr">
    <?php foreach ($rows as $r):
      $claim = Service::hasAction($r['lines'], 'reklamovat');
      $late = $claim && $r['claim_deadline'] && !in_array($r['status'], ['vyrizeno', 'zamitnuto'], true) && strtotime($r['claim_deadline']) - time() < 5 * 86400; ?>
    <a class="vcard" href="<?= url('/admin/vratky/' . $r['no']) ?>" style="border-left-color:<?= $colors[$r['status']] ?? '#dcdcd8' ?>;text-decoration:none;color:inherit">
      <div class="hd"><span class="no"><?= e(Service::displayNo($r)) ?><?php if ($q !== ''): ?> <small class="typ"><?= e($typLabel[$r['typ']] ?? '') ?></small><?php endif; ?></span><span class="st"><?= e(Service::DLAZDICE[$r['typ']][$r['status']] ?? Service::STATUS[$r['status']] ?? $r['status']) ?></span></div>
      <div class="meta"><span><?= e(date_cz($r['created_at'], true)) ?></span><span><?= e($r['customer_name'] ?: $r['email']) ?></span><span><?= e(Service::SHIP[$r['ship_method']] ?? '') ?></span><?php if ($r['order_no'] || $r['invoice_no']): ?><span>obj. <?= e($r['order_no'] ?: $r['invoice_no']) ?></span><?php endif; ?></div>
      <?php $imgs = Service::lineImages($r['lines']); foreach ($r['lines'] as $l): ?>
        <div class="li pline"><?php $u = $imgs[$l['id']]['old'] ?? ''; if ($u !== ''): ?><img class="pthumb sm" src="<?= e($u) ?>" alt="" loading="lazy"><?php endif; ?><div style="min-width:0"><em><?= e(Service::actionLabel($l)) ?></em><br><strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong></div></div>
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
