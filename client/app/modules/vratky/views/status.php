<?php
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Notify;
/** @var array $ret @var array $lines @var array $events */
$hasClaimLine = Service::hasAction($lines, 'reklamovat');
$order = $hasClaimLine ? ['novy', 'na_ceste', 'dorazilo', 'v_reseni', 'vyrizeno'] : ['novy', 'na_ceste', 'dorazilo', 'vyrizeno'];
$curIdx = array_search($ret['status'], $order, true);
$isNew = $ret['status'] === 'novy' && (time() - strtotime($ret['created_at'])) < 3600;
$hasSwap = Service::hasAction($lines, 'vymenit');
$hasClaim = Service::hasAction($lines, 'reklamovat');
$w = Service::words($lines);
$last = $ret['refund_total'] > 0 ? 'Peníze odeslány' : ($hasSwap ? 'Nová velikost odeslána' : ($hasClaim ? 'Reklamace vyřízena' : 'Vyřízeno'));
?>
<div class="donegrid">
<div class="col">
  <div style="padding:0 4px;display:flex;flex-direction:column;gap:8px">
    <span class="kicker" style="color:var(--green)">✓ <?= e($w['N']) ?> <?= e(Service::displayNo($ret)) ?><?= $isNew ? ' vytvořena' : '' ?></span>
    <h1 class="h1" style="font-size:46px"><?= $isNew ? 'HOTOVO, <span class="out">DÍKY!</span>' : e(mb_strtoupper(Service::STATUS[$ret['status']] ?? '')) ?></h1>
  </div>
  <?php if ($ret['status'] === 'novy'): ?>
    <?php if ($ret['ship_method'] === 'osobne'): ?>
      <div class="card">Zboží přineste na prodejnu <?= e(cfg('shop.address')) ?> (<?= e(cfg('shop.hours')) ?>) a řekněte číslo <?= e($w['g']) ?> <strong><?= e(Service::displayNo($ret)) ?></strong>.</div>
    <?php elseif ($ret['ship_method'] === 'vlastni'): ?>
      <div class="card">Zboží zabalte, přiložte papír s číslem <?= e($w['g']) ?> <strong><?= e(Service::displayNo($ret)) ?></strong> a pošlete ho na vlastní náklady (ne na dobírku) na adresu <strong><?= e(cfg('shop.name')) ?>, <?= e(cfg('shop.address')) ?></strong>.</div>
    <?php elseif ($ret['packeta_password']): ?>
      <div class="code"><span class="kicker" style="color:var(--ink)">Podací kód Zásilkovny</span><span class="v"><?= e($ret['packeta_password']) ?></span><span style="font-size:13px">Kód jsme vám poslali i e-mailem.</span></div>
      <div class="card"><div class="steps dark">
        <div><b>1</b><span>Zboží zabalte, ideálně do původní krabice.</span></div>
        <div><b>2</b><span>Na balík napište kód a „4 KLUCI OD KOL“.</span></div>
        <div><b>3</b><span><?= $ret['ship_method'] === 'zbox' ? 'V Z-BOXu kód zadejte a balík vložte.' : 'Na výdejním místě kód nadiktujte obsluze.' ?></span></div></div></div>
    <?php else: ?>
      <div class="code"><span class="kicker" style="color:var(--ink)">Podací kód Zásilkovny</span><span style="font-size:15px;font-weight:700">Pošleme vám ho e-mailem během pracovního dne.</span></div>
    <?php endif; ?>
  <?php endif; ?>
  <div class="card"><span class="kicker">V <?= e($w['l']) ?></span>
    <?php foreach ($lines as $l): ?>
      <div style="font-size:14px;line-height:1.45"><span class="kicker" style="font-size:10px"><?= e(Service::ACTIONS[$l['action']] ?? $l['action']) ?></span><br>
        <strong><?= e($l['item_name']) ?><?= $l['qty'] > 1 ? ' × ' . (int) $l['qty'] : '' ?></strong><br>
        <span style="color:var(--muted)"><?= e($l['action'] === 'vratit' ? $l['reason'] : ($l['action'] === 'vymenit' ? 'Za velikost ' . $l['size_to'] : $l['defect_text'])) ?></span></div>
    <?php endforeach; ?>
    <?php if ($ret['refund_total'] > 0): ?>
      <?php if ($ret['postage_applies'] && $ret['postage'] !== null): ?><div class="sumrow" style="border-top:1px solid var(--line2);border-bottom:0;padding-bottom:0"><span>Poštovné za vrácení</span><span>−<?= e(money($ret['postage'])) ?></span></div><?php endif; ?>
      <?php if ((float) ($ret['ship_refund'] ?? 0) > 0): ?><div class="sumrow" style="border-top:1px solid var(--line2);border-bottom:0;padding-bottom:0"><span>Vrácení dopravy</span><span>+<?= e(money($ret['ship_refund'])) ?></span></div><?php endif; ?>
      <div class="sumrow" style="border-top:1px solid var(--line2);border-bottom:0;padding-bottom:0"><span>Vrátíme na účet <?= e($ret['bank_account']) ?></span><strong><?= e(money(Service::payout($ret))) ?></strong></div>
    <?php endif; ?>
  </div>
</div>
<div class="col">
  <div class="card"><span class="kicker">Stav <?= e($w['g']) ?> <?= e(Service::displayNo($ret)) ?></span>
    <?php if ($ret['status'] === 'zamitnuto'): ?>
      <div class="tl"><div class="done"><i></i><?= e($w['N']) ?> vytvořena</div><div class="now"><i style="background:var(--red);border-color:var(--red)"></i>Zamítnuto</div></div>
    <?php else: ?>
    <div class="tl">
      <?php $tl = [['novy', $w['N'] . ' vytvořena'], ['na_ceste', ($ret['ship_method'] === 'osobne' ? 'Předáno na prodejně' : ($ret['ship_method'] === 'vlastni' ? 'Odesláno' : 'Podáno u Zásilkovny'))], ['dorazilo', 'Dorazilo k nám, kontrolujeme']];
        if ($hasClaimLine) $tl[] = ['v_reseni', 'Posuzuje dodavatel'];
        $tl[] = ['vyrizeno', $last];
        foreach ($tl as $k => [$s, $lab]):
        $cls = $curIdx === false ? 'todo' : ($k <= $curIdx ? 'done' : ($k === $curIdx + 1 ? 'now' : 'todo')); ?>
        <div class="<?= $cls ?>"><i></i><?= e($lab) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php $notes = array_filter($events, fn($ev) => trim((string) $ev['note']) !== '' && $ev['note'] !== 'Vratku vytvořil zákazník.'); ?>
    <?php foreach ($notes as $ev): ?><div class="grey"><span class="hint"><?= e(date_cz($ev['created_at'], true)) ?></span><br><?= nl2br(e($ev['note'])) ?></div><?php endforeach; ?>
    <span class="hint">O každé změně vám pošleme e-mail. Tuto stránku si můžete uložit, najdete ji i v e-mailu.</span>
  </div>
  <a class="btn ghost" href="<?= url('/vratky') ?>" style="text-align:center;text-decoration:none;line-height:46px">Další vratka</a>
</div>
</div>
