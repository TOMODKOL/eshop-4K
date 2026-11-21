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
$last = Service::payout($ret) > 0 ? 'Peníze odeslány' : ($hasSwap ? 'Nové zboží odesláno' : ($hasClaim ? 'Reklamace vyřízena' : 'Vyřízeno'));
?>
<div class="donegrid">
<div class="col">
  <div class="prog" aria-hidden="true"><div class="bars"><?php for ($i = 0; $i < 4; $i++): ?><i class="on"></i><?php endfor; ?></div><span class="kicker" style="color:var(--green)">✓ <?= e($w['N']) ?> <?= e(Service::displayNo($ret)) ?><?= $isNew ? ' vytvořena' : '' ?></span></div>
  <h2 class="h2" style="padding:0 4px"><?= $isNew ? 'HOTOVO, DÍKY!' : e(mb_strtoupper(Service::STATUS[$ret['status']] ?? '')) ?></h2>
  <?php if ($ret['status'] === 'novy'): ?>
    <?php if ($ret['ship_method'] === 'osobne'): ?>
      <div class="card"><span class="kicker">Co teď</span><p style="margin:0">Zboží přineste na prodejnu <strong><?= e(cfg('shop.address')) ?></strong> (<?= e(cfg('shop.hours')) ?>) a řekněte číslo <?= e($w['g']) ?> <strong><?= e(Service::displayNo($ret)) ?></strong>.</p></div>
    <?php elseif ($ret['ship_method'] === 'vlastni'): ?>
      <div class="card"><span class="kicker">Co teď</span><p style="margin:0">Zboží zabalte, přiložte papír s číslem <?= e($w['g']) ?> <strong><?= e(Service::displayNo($ret)) ?></strong> a pošlete ho na vlastní náklady (ne na dobírku) na adresu <strong><?= e(cfg('shop.name')) ?>, <?= e(cfg('shop.address')) ?></strong>.</p></div>
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
  <?php
    // stejná „účtenka“ jako v panelu formuláře
    $ln = fn(string $k, string $n, string $v = '', bool $wait = false) => '<div class="ai-l"><span class="ai-lk">' . e($k) . '</span><span class="ai-ln' . ($wait ? ' ai-wait' : '') . '">' . e($n) . '</span>' . ($v !== '' ? '<span class="ai-lv">' . e($v) . '</span>' : '') . '</div>';
    $bal = Service::balance($ret);
  ?>
  <div class="card"><span class="kicker">Vaše <?= e($w['n']) ?></span>
    <div class="ai-list">
    <?php foreach ($lines as $l): $nm = $l['item_name'] . ($l['qty'] > 1 ? ' × ' . (int) $l['qty'] . ' ks' : ''); $pr = money((float) $l['unit_price'] * (int) $l['qty']); ?>
      <div class="ai"><span class="ai-k"><?= e($l['action'] === 'vymenit' && ($l['new_name'] ?? '') === '' ? 'Výměna velikosti' : Service::actionLabel($l)) ?></span>
        <?php if ($l['action'] === 'vratit'): ?><?= $ln('Vracíte', $nm, $pr) ?>
        <?php elseif ($l['action'] === 'vymenit' && ($l['new_name'] ?? '') !== ''): $nq = max(1, (int) ($l['new_qty'] ?? 1)); ?><?= $ln('Posíláte', $nm, $pr) ?><?= $ln('Dostanete', $l['new_name'] . ($nq > 1 ? ' × ' . $nq . ' ks' : ''), $l['new_price'] !== null ? money((float) $l['new_price'] * $nq) : '') ?>
        <?php elseif ($l['action'] === 'vymenit'): ?><?= $ln('Posíláte', $nm) ?><?= $ln('Dostanete', 'velikost ' . $l['size_to']) ?>
        <?php else: ?><?= $ln('Posíláte', $nm) ?><?= trim((string) $l['defect_text']) !== '' ? $ln('Vada', mb_strimwidth((string) $l['defect_text'], 0, 160, '…'), '', true) : '' ?><?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?php if ($ret['refund_total'] > 0 || abs($bal) > 0.005 || (float) ($ret['swap_diff'] ?? 0) != 0): ?>
    <div class="ai-sum">
      <?php if ((float) $ret['refund_total'] > 0): ?><div class="row"><span>Vrátíme za zboží</span><strong><?= e(money((float) $ret['refund_total'])) ?></strong></div><?php endif; ?>
      <?php if ((float) ($ret['swap_diff'] ?? 0) != 0): ?><div class="row"><span><?= (float) $ret['swap_diff'] > 0 ? 'Vrátíme rozdíl – nové zboží je levnější' : 'Doplatek – nové zboží je dražší' ?></span><strong><?= e(money(abs((float) $ret['swap_diff']))) ?></strong></div><?php endif; ?>
      <?php if ($ret['postage_applies'] && $ret['postage'] !== null): ?><div class="row"><span>Odečteme poštovné</span><strong><?= e(money($ret['postage'])) ?></strong></div><?php else: ?><div class="row"><span>Doprava</span><strong class="<?= $ret['ship_method'] === 'vlastni' ? '' : 'free' ?>"><?= $ret['ship_method'] === 'vlastni' ? 'na vaše náklady' : 'zdarma' ?></strong></div><?php endif; ?>
      <?php if ((float) ($ret['ship_charge'] ?? 0) > 0): ?><div class="row"><span>Odečteme dopravu objednávky – zbytek je pod hranicí dopravy zdarma</span><strong><?= e(money($ret['ship_charge'])) ?></strong></div><?php endif; ?>
      <?php if ((float) ($ret['ship_refund'] ?? 0) > 0): ?><div class="row"><span>Vrátíme i dopravu</span><strong><?= e(money($ret['ship_refund'])) ?></strong></div><?php endif; ?>
      <?php if ((float) ($ret['cod_fee'] ?? 0) > 0): ?><div class="row"><span>Poplatek za dobírku</span><strong><?= e(money($ret['cod_fee'])) ?></strong></div><?php endif; ?>
    </div>
    <div class="ai-total"><span><?= $bal < -0.005 ? 'Doplatíte' : ($bal > 0.005 ? 'Vrátíme vám' : 'Vychází nastejno') ?></span><span class="v"><?= e(money(abs($bal))) ?></span></div>
    <span class="hint"><?= $bal < -0.005 ? ($ret['pay_method'] === 'dobirka' ? 'Doplatek zaplatíte na dobírku při převzetí nového zboží.' : 'Doplatek zaplatíte převodem – fakturu pošleme, až vaše zboží dorazí.') : ($bal > 0.005 ? 'Peníze pošleme na účet ' . e($ret['bank_account']) . ', jakmile zboží zkontrolujeme.' : '') ?></span>
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
