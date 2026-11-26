<?php
use App\Core\Auth;
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Packeta;
/** @var array $ret @var ?array $order @var array $lines @var array $events */
$claim = Service::hasAction($lines, 'reklamovat');
$swap = Service::hasAction($lines, 'vymenit');
$onlyRet = !$swap && !$claim;
$docState = (string) ($ret['doc_state'] ?? '');
$plan = Service::docPlan(array_merge($ret, $docState === '' ? ['ship_refund' => Service::shippingRefund($ret)] : []), $lines);
$st = (string) $ret['status'];
$open = !in_array($st, ['vyrizeno', 'zamitnuto'], true);
$byPacketa = Service::usesPacketa((string) $ret['ship_method']);
$arrived = in_array($st, ['dorazilo', 'v_reseni'], true) || !$byPacketa;   // u Zásilkovny až po doručení k nám
$todo = Service::todo($ret + ['lines' => $lines]);
$isAdmin = (Auth::user()['role'] ?? '') === 'admin';
$bal = Service::balance($ret);
$waitPay = !empty($ret['checked_at']) && !empty($ret['zf_no']) && empty($ret['zf_paid']);
$canCheck = $open && $plan['type'] && $arrived && !in_array($docState, ['ceka', 'navrh', 'hotovo'], true) && !$waitPay && ($docState !== 'chyba' || $isAdmin);
$canEarly = $open && $plan['type'] && !$arrived && $docState === '';   // Zásilkovna ještě nedoručila, ale zákazník může zboží přinést osobně
$canSend = $open && $st === 'dorazilo' && $docState === 'hotovo' && $ret['doc_type'] !== 'odd';
$form = fn() => '<form data-once method="post" action="' . e(url('/admin/vratky/' . $ret['no'])) . '" style="margin:0;display:flex;flex-direction:column;gap:8px">' . csrf_field();
?>
<div class="col wide">
  <a class="link" href="<?= url('/admin/vratky') ?>" style="align-self:flex-start;padding:0">← Přehled</a>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <h1 class="h2"><?= e(mb_strtoupper(Service::words($lines)['n'])) ?> <?= e(Service::displayNo($ret)) ?></h1>
    <span class="st s<?= array_search($st, array_keys(Service::STATUS), true) ?>" style="font-size:13px"><?= e(Service::STATUS[$st] ?? $st) ?></span>
  </div>
  <div class="layout" style="max-width:none">
  <div class="main"><div class="col" style="max-width:none">

    <?php /* ---------- CO TEĎ ---------- */ ['steps' => $pSteps, 'cur' => $pCur, 'rejected' => $pRej] = Service::progress($ret, $lines); ?>
    <div class="card nowcard<?= $todo && in_array($todo['who'], ['vy', 'tomas'], true) ? ' mine' : '' ?>">
      <div class="prog"><div class="bars"><?php foreach ($pSteps as $i => $lab): ?><i class="<?= $i <= $pCur ? ($pRej && $i === $pCur ? 'rej' : 'ok') : '' ?>"></i><?php endforeach; ?></div>
        <div class="steplabs"><?php foreach ($pSteps as $i => $lab): ?><span<?= $i === $pCur ? ' class="cur"' : '' ?>><?= e($lab) ?></span><?php endforeach; ?></div></div>

      <?php if ($todo): ?>
        <div><span class="kicker"><?= e(Service::WHO[$todo['who']] ?? '') ?></span><div class="todo-l" style="margin-top:4px"><?= e($todo['label']) ?></div></div>
        <span class="hint" style="font-size:14px"><?= e($todo['hint']) ?></span>
      <?php else: ?>
        <div class="todo-l"><?= $st === 'zamitnuto' ? 'Zamítnuto' : 'Hotovo' ?></div>
        <span class="hint" style="font-size:14px"><?= $st === 'zamitnuto' ? 'Zákazník dostal e-mail s důvodem.' : 'Vyřízeno, nic dalšího se nedělá.' ?></span>
      <?php endif; ?>

      <?php $lastProb = null; foreach ($events as $ev) if (str_starts_with((string) $ev['note'], 'Problém se zbožím: ')) $lastProb = $ev;
        if ($lastProb && $open && empty($ret['checked_at'])): ?><div class="warn" style="font-size:14px"><?= e($lastProb['note']) ?> <span class="hint">(<?= e(date_cz($lastProb['created_at'], true)) ?>)</span></div><?php endif; ?>

      <?php /* reklamace z webu → založit v Pohodě */ if ($ret['pohoda_state'] === 'cekame'): ?>
        <?php foreach ($lines as $l): if ($l['action'] !== 'reklamovat' || (string) ($l['pohoda_no'] ?? '') !== '') continue; ?>
          <div class="copybox">
            <div>Doklad: <strong class="sel"><?= e($order['invoice_no'] ?? '') ?></strong><?= $order ? ' <span class="hint">z ' . e(date_cz($order['invoice_date'])) . '</span>' : '' ?></div>
            <div>Předmět: <span class="sel"><?= e($l['item_name']) ?></span><?= $l['item_code'] ? ' <span class="hint">(' . e($l['item_code']) . ')</span>' : '' ?>, <?= (int) $l['qty'] ?> ks</div>
            <div>Vada: <span class="sel"><?= e((string) $l['defect_text']) ?></span></div>
          </div>
        <?php endforeach; ?>
        <div class="copybox">Zákazník: <span class="sel"><?= e($ret['customer_name']) ?></span>, <span class="sel"><?= e($ret['phone']) ?></span>, <span class="sel"><?= e($ret['email']) ?></span></div>
        <details class="howto" open><summary>Jak na to</summary><ol>
          <li>V Pohodě otevřete agendu <strong>Reklamace</strong> a založte nový záznam – za každou reklamovanou položku jeden.</li>
          <li>Do pole <strong>Doklad</strong> vložte číslo dokladu nákupu z rámečku výše (kliknutím se celé označí, pak Ctrl+C).</li>
          <li>Předmět, vadu a zákazníka zkopírujte stejně.</li>
          <li>Uložte. Client se s Pohodou propojí sám <?= e(Service::pohodaEta()) ?> a zákazník dostane potvrzení s číslem reklamace.</li>
        </ol></details>
      <?php elseif ($claim && $ret['pohoda_state'] === 'propojeno' && $open): ?>
        <div class="copybox"><?php foreach ($lines as $l): if ((string) ($l['pohoda_no'] ?? '') === '') continue; ?><div>V Pohodě: <strong><?= e($l['pohoda_no']) ?></strong> · <?= e($l['pohoda_status'] ?: 'přijato') ?></div><?php endforeach; ?></div>
        <details class="howto"><summary>Jak na to</summary><ul>
          <li>Reklamaci vyřizujete v Pohodě – stav měňte na záložce <strong>Stav reklamace</strong>.</li>
          <li>Client si změnu načte sám <?= e(Service::pohodaEta()) ?> a zákazník dostane e-mail. V Clientu nic neklikejte.</li>
          <?php if ($ret['claim_deadline']): ?><li>Vyřídit nejpozději do <strong><?= e(date_cz($ret['claim_deadline'])) ?></strong>.</li><?php endif; ?>
        </ul></details>
      <?php endif; ?>

      <?php /* kontrola zboží */ if ($canCheck || $canEarly): ?>
        <?php if ($canEarly): ?><details class="howto more"><summary>Zákazník zboží přinesl na prodejnu?</summary><div style="margin-top:8px"><?php endif; ?>
        <?= $form() ?>
          <input type="hidden" name="act" value="<?= $docState === 'chyba' ? 'doklad_znovu' : 'doklad' ?>">
          <?php foreach ($lines as $l): if ($l['action'] !== 'vymenit') continue; if ((string) ($l['new_code'] ?? '') !== ''): ?><input type="hidden" name="new_code[<?= (int) $l['id'] ?>]" value="<?= e((string) $l['new_code']) ?>"><?php continue; endif; ?>
            <div class="field" style="margin:0"><label class="l" for="nc<?= (int) $l['id'] ?>">Kód zásoby – <?= e(($l['new_name'] ?? '') !== '' ? $l['new_name'] : 'nová velikost ' . $l['size_to']) ?></label>
              <input class="f" id="nc<?= (int) $l['id'] ?>" name="new_code[<?= (int) $l['id'] ?>]" value="<?= e((string) ($l['new_code'] ?? '')) ?>" placeholder="kód z Pohody" required></div>
          <?php endforeach; ?>
          <button class="btn" style="align-self:flex-start"><?= $docState === 'chyba' ? 'Zkusit znovu' : (in_array($st, ['novy', 'na_ceste'], true) ? 'Zboží převzato a je v pořádku ✓' : 'Zboží je v pořádku ✓') ?></button>
        </form>
        <?php if ($canEarly): ?></div></details><?php endif; ?>
        <?php if ($docState !== 'chyba' && $canCheck): ?>
        <details class="howto"><summary>Jak zkontrolovat zboží</summary><ol>
          <li>Porovnejte zboží s položkami níže: název, velikost a počet kusů.</li>
          <li>Je nenošené a čisté – bez stop použití, zápachu, chlupů a skvrn?</li>
          <li>Má visačky a původní obal? U bot i krabici, u helem a doplňků všechno příslušenství.</li>
          <li>Všechno sedí → klikněte <strong>Zboží je v pořádku</strong>. Doklad v Pohodě i e-mail zákazníkovi půjdou samy, zboží vraťte do regálu.</li>
          <?php if ($swap): ?><li>Kód nové velikosti je většinou vyplněný sám. Když chybí, najdete ho v Pohodě v Zásobách podle názvu.</li><?php endif; ?>
          <li>Něco nesedí → otevřete <strong>Zboží není v pořádku</strong> a napište, co přesně.</li>
        </ol></details>
        <details class="howto more"><summary>Zboží není v pořádku</summary>
          <?= $form() ?>
            <input type="hidden" name="act" value="problem">
            <div class="field" style="margin:8px 0 0"><label class="l" for="why">Co je špatně?</label><textarea class="f" id="why" name="why" rows="3" required placeholder="Např. Zboží je nošené / chybí krabice / jiné zboží, než jsme prodali…"></textarea></div>
            <label class="chk"><input type="radio" name="what" value="vyresit" checked><span>Jen poznamenat – vratka zůstane otevřená, zákazník nic nedostane. Domluvte se s Tomášem.</span></label>
            <label class="chk"><input type="radio" name="what" value="zamitnout"><span>Zamítnout – jen po domluvě s Tomášem. Zákazník dostane e-mail s tímto důvodem a zboží mu pošleme zpět.</span></label>
            <button class="btn ghost" style="align-self:flex-start">Uložit</button>
          </form>
        </details>
        <?php endif; ?>
      <?php endif; ?>

      <?php /* výměna: odeslat nové zboží */ if ($canSend): ?>
        <?= $form() ?>
          <input type="hidden" name="status" value="vyrizeno"><input type="hidden" name="notify" value="1">
          <div class="field" style="margin:0"><label class="l" for="trk">Číslo zásilky (nepovinné, zákazník ho dostane e-mailem)</label><input class="f" id="trk" name="note" placeholder="Např. Z 123 4567 890"></div>
          <button class="btn" style="align-self:flex-start">Nové zboží odesláno ✓</button>
        </form>
        <details class="howto"><summary>Jak na to</summary><ol>
          <li>Nové zboží je v Pohodě na faktuře <strong><?= e((string) $ret['doc_no']) ?></strong> – zabalte ho a odešlete jako běžnou zásilku z e-shopu<?= ($ret['pay_method'] ?? '') === 'dobirka' && $bal < 0 ? ', <strong>na dobírku ' . e(money(abs($bal) + (float) ($ret['cod_fee'] ?? 0))) . '</strong>' : '' ?>.</li>
          <li>Číslo zásilky napište do políčka a klikněte <strong>Nové zboží odesláno</strong>. Vratka se uzavře a zákazník dostane e-mail.</li>
        </ol></details>
      <?php endif; ?>

      <?php /* vrácení peněz (Tomáš) */ if ($open && $docState === 'hotovo' && $ret['doc_type'] === 'odd'): ?>
        <div class="copybox">Poslat <strong><?= e(money(Service::payout($ret))) ?></strong> na účet <strong class="sel"><?= e($ret['bank_account']) ?></strong>, VS <strong class="sel"><?= e((string) $ret['doc_no']) ?></strong></div>
      <?php endif; ?>

      <?php /* chyby lidsky */ if ($docState === 'chyba' || ($ret['xorder_state'] ?? '') === 'chyba'): ?>
        <div class="warn" style="font-size:14px">Doklad v Pohodě se nepodařilo vystavit. Dejte vědět Tomášovi – technická zpráva je dole v Podrobnostech.</div>
      <?php endif; ?>
    </div>

    <?php /* ---------- ZBOŽÍ ---------- */ $imgs = Service::lineImages($lines); ?>
    <div class="card"><span class="kicker">Zboží</span>
    <?php foreach ($lines as $l): $im = $imgs[$l['id']] ?? ['old' => '', 'new' => '']; ?>
      <div style="display:flex;flex-direction:column;gap:10px;border-top:1px solid var(--line2);padding-top:12px">
      <span class="tag"><?= e(Service::actionLabel($l)) ?></span>
      <?php $th = fn(string $u) => $u !== '' ? '<img class="pthumb" src="' . e($u) . '" alt="" loading="lazy">' : '<span class="pthumb ph"></span>'; ?>
      <div class="swap<?= $l['action'] === 'vymenit' ? ' two' : '' ?>">
        <div class="pit"><?= $th($im['old']) ?>
          <div class="pt"><?php if ($l['action'] === 'vymenit'): ?><span class="pk">Posílá</span><?php endif; ?><strong><?= e($l['item_name']) ?></strong>
            <span class="hint"><?= $l['item_code'] ? 'Kód ' . e($l['item_code']) . ' · ' : '' ?><?= (int) $l['qty'] ?> ks × <?= e(money($l['unit_price'])) ?></span></div></div>
        <?php if ($l['action'] === 'vymenit'): $other = ($l['new_name'] ?? '') !== ''; ?>
        <span class="parr" aria-hidden="true">→</span>
        <div class="pit"><?= $th($im['new']) ?>
          <div class="pt"><span class="pk">Dostane</span><strong><?= e($other ? $l['new_name'] : 'Velikost ' . $l['size_to']) ?></strong>
            <span class="hint"><?php if ($other): ?><?= (int) ($l['new_qty'] ?? 1) ?> ks × <?= e(money($l['new_price'])) ?><?php if ($l['new_url']): ?> · <a href="<?= e($l['new_url']) ?>" target="_blank" rel="noopener">e-shop ↗</a><?php endif; ?><?php else: ?>stejné zboží, jiná velikost<?php endif; ?><?= ($l['new_code'] ?? '') !== '' ? ' · kód ' . e($l['new_code']) : '' ?></span></div></div>
        <?php endif; ?>
      </div>
      <?php if ($l['action'] === 'vratit' && $l['reason'] !== ''): ?><div style="font-size:14px">Důvod: <strong><?= e($l['reason']) ?></strong></div><?php endif; ?>
      <?php if ($l['action'] === 'reklamovat'): ?>
        <div class="grey" style="white-space:pre-line"><?= e($l['defect_text']) ?></div>
        <?php if ($l['photos']): ?><div class="photos"><?php foreach ($l['photos'] as $p): ?>
          <a href="<?= url('/admin/vratky/foto/' . $p['id']) ?>" target="_blank" rel="noopener"><?php if ($p['mime'] === 'image/heic'): ?><span class="pill" style="display:inline-flex;align-items:center">Fotka HEIC</span><?php else: ?><img src="<?= url('/admin/vratky/foto/' . $p['id']) ?>" alt="Fotka vady"><?php endif; ?></a>
        <?php endforeach; ?></div><?php else: ?><span class="hint">Bez fotek.</span><?php endif; ?>
      <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($ret['note']): ?><div class="custnote"><span class="kicker">Poznámka zákazníka</span><div style="white-space:pre-line"><?= e($ret['note']) ?></div></div><?php endif; ?>
    </div>

    <?php /* ---------- HISTORIE + POZNÁMKA ---------- */ ?>
    <div class="card"><span class="kicker">Historie</span>
      <?php foreach ($events as $ev): ?>
        <div style="font-size:14px;border-top:1px solid var(--line2);padding-top:8px">
          <span class="hint"><?= e(date_cz($ev['created_at'], true)) ?> · <?= e($ev['user_name'] ?? 'systém') ?><?= $ev['public'] ? ' · vidí zákazník' : '' ?></span><br>
          <?php if ($ev['status']): ?><strong><?= e(Service::STATUS[$ev['status']] ?? $ev['status']) ?></strong><br><?php endif; ?>
          <?= nl2br(e((string) $ev['note'])) ?>
        </div>
      <?php endforeach; ?>
      <details class="howto more"><summary>Přidat poznámku</summary>
        <?= $form() ?>
          <div class="field" style="margin:8px 0 0"><label class="l" for="note">Poznámka</label><textarea class="f" id="note" name="note" rows="3" placeholder="Např. Volali jsme zákazníkovi, posílá zbytek balení…"></textarea></div>
          <?php if ($ret['email'] !== ''): ?><label class="chk"><input type="checkbox" name="notify" value="1"><span>Poslat ji zákazníkovi e-mailem</span></label><?php endif; ?>
          <button class="btn ghost" style="align-self:flex-start">Uložit poznámku</button>
        </form>
      </details>
    </div>
  </div></div>

  <aside style="display:flex;flex-direction:column;gap:12px">
    <div class="card"><span class="kicker">Zákazník</span>
      <div style="font-size:14px;line-height:1.6"><strong><?= e($ret['customer_name']) ?></strong><br><?= e($ret['email']) ?><br><?= e($ret['phone']) ?></div>
      <?php if ($order && $order['source'] === 'prodejna'): ?><div class="hint">Prodejka <?= e($order['invoice_no']) ?> z <?= e(date_cz($order['invoice_date'])) ?></div>
      <?php elseif ($order): ?><div class="hint"><?= $order['order_no'] ? 'Objednávka ' . e($order['order_no']) . ' · ' : '' ?>faktura <?= e($order['invoice_no']) ?> z <?= e(date_cz($order['invoice_date'])) ?></div><?php endif; ?>
      <div class="hint"><?= e(Service::SHIP[$ret['ship_method']] ?? '') ?><?= $ret['packeta_barcode'] ?? '' ? ' · zásilka ' . e($ret['packeta_barcode']) : '' ?></div>
      <a class="link" style="padding:0;align-self:flex-start" href="<?= e(\App\Modules\Vratky\Notify::statusUrl($ret)) ?>" target="_blank" rel="noopener">Co vidí zákazník ↗</a>
    </div>

    <?php if (abs($bal) > 0.005 || $ret['refund_total'] > 0): ?>
    <div class="card"><span class="kicker">Peníze</span>
      <?php if (abs($bal) > 0.005 || $ret['refund_total'] > 0): ?>
        <div style="font-size:15px"><?= $bal < 0 ? 'Zákazník doplatí' : 'Vrátit' ?> <strong><?= e(money(abs($bal))) ?></strong></div>
        <?php if ($bal < 0): $cardPay = \App\Modules\Vratky\GpWebpay::paidPayment((int) $ret['id']); ?>
          <div style="font-size:14px"><?= e(Service::PAY[$ret['pay_method']] ?? '–') ?> · <?= in_array($ret['pay_method'], ['prevod', 'karta'], true) ? ($ret['zf_paid'] ? '<strong>zaplaceno</strong>' . ($cardPay && $cardPay['test'] ? ' (TEST)' : '') : 'zatím nezaplaceno') : 'posílá se na dobírku' ?></div>
        <?php else: ?><div style="font-size:14px">Na účet <strong class="sel"><?= e($ret['bank_account']) ?></strong></div><?php endif; ?>
        <details class="howto more"><summary>Rozpis</summary><div style="font-size:13px;line-height:1.7;margin-top:6px">
          <?php if ((float) $ret['refund_total'] > 0): ?>Vrácené zboží: <?= e(money($ret['refund_total'])) ?><br><?php endif; ?>
          <?php if ((float) ($ret['swap_diff'] ?? 0) != 0): ?><?= (float) $ret['swap_diff'] > 0 ? 'Jiné zboží levnější – rozdíl' : 'Jiné zboží dražší – doplatek' ?>: <?= e(money(abs((float) $ret['swap_diff']))) ?><br><?php endif; ?>
          <?php if ((float) ($ret['cod_fee'] ?? 0) > 0): ?>Dobírka (platí zákazník): <?= e(money($ret['cod_fee'])) ?><br><?php endif; ?>
          <?php if ($ret['postage_applies'] && $ret['postage'] !== null): ?>Poštovné za vrácení: −<?= e(money($ret['postage'])) ?><br><?php endif; ?>
          <?php if ($onlyRet && $byPacketa && $order): [$kLeft, $kLimit] = Service::keptInfo($order, (int) $ret['id']); ?><span class="hint">Z objednávky si nechává zboží za <?= e(money(max(0, $kLeft - (float) $ret['refund_total']))) ?> (doprava zdarma od <?= e(money($kLimit)) ?>)<?= $ret['postage_applies'] ? '' : ' – poštovné se neodečítá' ?>.</span><br><?php endif; ?>
          <?php if ((float) ($ret['ship_charge'] ?? 0) > 0): ?>Doprava (zbytek pod hranicí dopravy zdarma): −<?= e(money($ret['ship_charge'])) ?><br><?php endif; ?>
          <?php if ((float) $ret['ship_refund'] > 0): ?>Vrácení dopravy: +<?= e(money($ret['ship_refund'])) ?><br><?php endif; ?>
        </div></details>
      <?php else: ?><span class="hint">Nic se nevrací ani nedoplácí.</span><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($claim && $ret['claim_deadline'] && $open): ?><div class="card"><span class="kicker">Reklamace</span><div style="font-size:14px">Vyřídit do <strong><?= e(date_cz($ret['claim_deadline'])) ?></strong></div></div><?php endif; ?>

    <details class="card"><summary class="kicker" style="cursor:pointer">Podrobnosti</summary>
      <div class="details-body">
        <div><strong>Doprava</strong><br><?= e(Service::SHIP[$ret['ship_method']] ?? '') ?>
          <?php if ($ret['packeta_password']): ?><br>Podací kód <?= e($ret['packeta_password']) ?><?php if ((string) ($ret['packeta_barcode'] ?? '') !== ''): ?> · zásilka <?= e($ret['packeta_barcode']) ?><?php endif; ?>
          <?php elseif ($byPacketa): ?>
            <?php if ($ret['packeta_error']): ?><br><span class="warn"><?= e($ret['packeta_error']) ?></span><?php endif; ?>
            <?php if (Packeta::enabled() && $open): ?><?= $form() ?><input type="hidden" name="act" value="packeta"><button class="pill" style="align-self:flex-start;margin-top:6px">Vytvořit podací kód znovu</button></form><?php endif; ?>
          <?php endif; ?></div>
        <?php if (!empty($ret['xorder_no'])): ?>
        <div><strong>Výměna v Pohodě</strong><br>Přijatá objednávka <?= e($ret['xorder_no']) ?> · <?= e(Service::XORDER_STATE[$ret['xorder_state']] ?? $ret['xorder_state']) ?>
          <?php if (Service::advanceAmount($ret) > 0.005): ?><br>Zálohová faktura <?= e(money(Service::advanceAmount($ret))) ?>: <?= $ret['zf_no'] ? e($ret['zf_no']) . ' · ' . ($ret['zf_paid'] ? 'zaplaceno' : 'čeká na platbu') : 'vznikne s objednávkou' ?><?php endif; ?>
          <?php if ($ret['xorder_state'] === 'chyba' && $ret['xorder_error']): ?><br><span class="warn"><?= e($ret['xorder_error']) ?></span><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($plan['type']): ?>
        <div><strong>Doklad v Pohodě</strong><br>
          <?php if ($docState === 'hotovo'): ?><?= e(Service::DOC_TYPE[$ret['doc_type']] ?? 'Doklad') ?> <?= e((string) $ret['doc_no']) ?> – vystaven
          <?php elseif ($docState === 'ceka'): ?><?= e(Service::DOC_TYPE[$ret['doc_type']] ?? 'Doklad') ?> – vystaví se <?= e(Service::pohodaEta()) ?>
          <?php elseif ($docState === 'navrh'): ?>Návrh čeká na schválení
          <?php else: ?><?= e(Service::DOC_TYPE[$plan['type']]) ?> na <?= e(money($plan['total'])) ?> – vystaví se po kontrole zboží
            <?php foreach ($plan['items'] as $i): ?><br><span class="hint"><?= (int) $i['qty'] ?> × <?= e($i['name']) ?><?= $i['code'] !== '' ? ' (' . e($i['code']) . ')' : '' ?> · <?= e(money($i['unit_price'])) ?></span><?php endforeach; ?>
          <?php endif; ?>
          <?php if ($docState === 'chyba'): ?><br><span class="warn"><?= e((string) $ret['doc_error']) ?></span><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($isAdmin && empty($ret['zf_paid']) && empty($ret['doc_no'])): ?>
        <details><summary class="hint" style="cursor:pointer">Smazat zkušební záznam (jen správce)</summary>
          <form method="post" action="<?= url('/admin/vratky/' . $ret['no']) ?>" style="margin:8px 0 0;display:flex;flex-direction:column;gap:8px"><?= csrf_field() ?>
            <input type="hidden" name="act" value="smazat_test">
            <span class="hint">Smaže vratku i s historií a fotkami a zruší zpětnou zásilku u Zásilkovny. Zákazník nic nedostane.</span>
            <div class="field" style="margin:0"><label class="l" for="potvrzeni">Pro potvrzení opište <?= e($ret['no']) ?></label><input class="f" id="potvrzeni" name="potvrzeni" autocomplete="off" required></div>
            <button class="btn" style="align-self:flex-start;background:#b3261e;border-color:#b3261e;color:#fff">Smazat natrvalo</button>
          </form>
        </details>
        <?php endif; ?>
      </div>
    </details>
  </aside>
  </div>
</div>
