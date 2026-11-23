<?php
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Packeta;
/** @var array $ret @var ?array $order @var array $lines @var array $events */
$claim = Service::hasAction($lines, 'reklamovat');
$docState = (string) ($ret['doc_state'] ?? '');
$plan = Service::docPlan(array_merge($ret, $docState === '' ? ['ship_refund' => Service::shippingRefund($ret)] : []), $lines);
?>
<div class="col wide">
  <a class="link" href="<?= url('/admin/vratky') ?>" style="align-self:flex-start;padding:0">← Přehled vratek</a>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <h1 class="h2"><?= e(mb_strtoupper(Service::words($lines)['n'])) ?> <?= e(Service::displayNo($ret)) ?></h1>
    <span class="st s<?= array_search($ret['status'], array_keys(Service::STATUS), true) ?>" style="font-size:13px"><?= e(Service::STATUS[$ret['status']] ?? $ret['status']) ?></span>
  </div>
  <div class="layout" style="max-width:none">
  <div class="main"><div class="col" style="max-width:none">
    <?php $imgs = Service::lineImages($lines); foreach ($lines as $l): $im = $imgs[$l['id']] ?? ['old' => '', 'new' => '']; ?>
    <div class="card">
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
      <?php if ($l['action'] === 'vratit' && $l['reason'] !== ''): ?><div>Důvod: <strong><?= e($l['reason']) ?></strong></div><?php endif; ?>
      <?php if ($l['action'] === 'reklamovat'): ?>
        <div class="grey" style="white-space:pre-line"><?= e($l['defect_text']) ?></div>
        <?php if ($l['photos']): ?><div class="photos"><?php foreach ($l['photos'] as $p): ?>
          <a href="<?= url('/admin/vratky/foto/' . $p['id']) ?>" target="_blank" rel="noopener"><?php if ($p['mime'] === 'image/heic'): ?><span class="pill" style="display:inline-flex;align-items:center">Fotka HEIC</span><?php else: ?><img src="<?= url('/admin/vratky/foto/' . $p['id']) ?>" alt="Fotka vady"><?php endif; ?></a>
        <?php endforeach; ?></div><?php else: ?><span class="hint">Bez fotek.</span><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <form class="card" method="post" action="<?= url('/admin/vratky/' . $ret['no']) ?>" data-once>
      <?= csrf_field() ?>
      <strong>Změnit stav nebo připsat poznámku</strong>
      <div class="field"><label class="l" for="status">Stav</label>
        <select class="f" id="status" name="status"><?php foreach (Service::STATUS as $k => $lab): ?><option value="<?= $k ?>" <?= $k === $ret['status'] ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="l" for="note" data-note-label>Poznámka</label><textarea class="f" id="note" name="note" rows="3" placeholder="Např. Peníze odeslány 21. 11. / Nová velikost M odeslána, sledování Z 123…"></textarea></div>
      <?php if ($ret['email'] !== ''): ?><label class="chk"><input type="checkbox" name="notify" value="1" checked><span>Poslat zákazníkovi e-mail (vidí stav i tuto poznámku)</span></label>
      <?php else: ?><span class="hint">Zákazník nemá e-mail – o změně ho informujte telefonem.</span><?php endif; ?>
      <button class="btn" style="align-self:flex-start">Uložit</button>
    </form>

    <div class="card"><span class="kicker">Historie</span>
      <?php foreach ($events as $ev): ?>
        <div style="font-size:14px;border-top:1px solid var(--line2);padding-top:8px">
          <span class="hint"><?= e(date_cz($ev['created_at'], true)) ?> · <?= e($ev['user_name'] ?? 'zákazník/systém') ?><?= $ev['public'] ? ' · viděl zákazník' : ' · interní' ?></span><br>
          <?php if ($ev['status']): ?><strong><?= e(Service::STATUS[$ev['status']] ?? $ev['status']) ?></strong><br><?php endif; ?>
          <?= nl2br(e((string) $ev['note'])) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>

  <aside style="display:flex;flex-direction:column;gap:12px">
    <?php if ($ret['pohoda_state'] === 'cekame'): ?>
    <div class="card" style="border:2px solid var(--yellow,#ffc524)"><span class="kicker">Založte v Pohodě – Reklamace</span>
      <div style="font-size:14px;line-height:1.55">Pro každou reklamovanou položku jednu reklamaci, do pole <strong>Doklad</strong> číslo dokladu nákupu.
        Pomocník ji podle něj <?= e(Service::pohodaEta()) ?> propojí a zákazník dostane potvrzení s číslem z Pohody.</div>
      <?php foreach ($lines as $l): if ($l['action'] !== 'reklamovat' || (string) ($l['pohoda_no'] ?? '') !== '') continue; ?>
        <div style="font-size:13px;line-height:1.5;border-top:1px solid var(--line2);padding-top:8px">
          <div>Doklad: <strong style="user-select:all"><?= e($order['invoice_no'] ?? '') ?></strong><?= $order ? ' z ' . e(date_cz($order['invoice_date'])) : '' ?></div>
          <div>Předmět: <span style="user-select:all"><?= e($l['item_name']) ?></span><?= $l['item_code'] ? ' (' . e($l['item_code']) . ')' : '' ?>, <?= (int) $l['qty'] ?> ks</div>
          <div>Vada: <span style="user-select:all"><?= e((string) $l['defect_text']) ?></span></div>
        </div>
      <?php endforeach; ?>
      <div style="font-size:13px">Zákazník: <span style="user-select:all"><?= e($ret['customer_name']) ?></span>, <span style="user-select:all"><?= e($ret['phone']) ?></span>, <span style="user-select:all"><?= e($ret['email']) ?></span></div>
    </div>
    <?php elseif ($ret['pohoda_state'] === 'propojeno'): ?>
    <div class="card"><span class="kicker">Pohoda – Reklamace</span>
      <?php foreach ($lines as $l): if ((string) ($l['pohoda_no'] ?? '') === '') continue; ?>
        <div style="font-size:14px"><strong><?= e($l['pohoda_no']) ?></strong> · <?= e($l['pohoda_status'] ?: '–') ?></div>
      <?php endforeach; ?>
      <span class="hint">Stav měňte v Pohodě (záložka Stav reklamace). Zákazník změnu uvidí <?= e(Service::pohodaEta()) ?>.</span>
    </div>
    <?php endif; ?>
    <div class="card"><span class="kicker">Zákazník</span>
      <div style="font-size:14px;line-height:1.6"><strong><?= e($ret['customer_name']) ?></strong><br><?= e($ret['email']) ?><br><?= e($ret['phone']) ?></div>
      <?php if ($order && $order['source'] === 'prodejna'): ?><div class="hint">Prodejka <?= e($order['invoice_no']) ?> z <?= e(date_cz($order['invoice_date'])) ?></div>
      <?php elseif ($order): ?><div class="hint"><?= $order['order_no'] ? 'Objednávka ' . e($order['order_no']) . ' · ' : '' ?>faktura <?= e($order['invoice_no']) ?> z <?= e(date_cz($order['invoice_date'])) ?></div><?php endif; ?>
      <a class="link" style="padding:0;align-self:flex-start" href="<?= e(\App\Modules\Vratky\Notify::statusUrl($ret)) ?>" target="_blank" rel="noopener">Stránka, kterou vidí zákazník</a>
    </div>
    <div class="card"><span class="kicker">Doprava</span>
      <div style="font-size:14px"><?= e(Service::SHIP[$ret['ship_method']] ?? '') ?></div>
      <?php if ($ret['packeta_password']): ?>
        <div style="font-size:14px">Podací kód: <strong><?= e($ret['packeta_password']) ?></strong><?php if ((string) ($ret['packeta_barcode'] ?? '') !== ''): ?><br>Zásilka: <?= e($ret['packeta_barcode']) ?><?php endif; ?></div>
      <?php elseif (Service::usesPacketa((string) $ret['ship_method'])): ?>
        <?php if ($ret['packeta_error']): ?><div class="warn" style="font-size:13px"><?= e($ret['packeta_error']) ?></div><?php endif; ?>
        <?php if (Packeta::enabled()): ?>
        <form data-once method="post" action="<?= url('/admin/vratky/' . $ret['no']) ?>" style="margin:0"><?= csrf_field() ?><input type="hidden" name="act" value="packeta"><button class="pill">Vytvořit podací kód a poslat zákazníkovi</button></form>
        <?php else: ?><span class="hint">Zásilkovna zatím není zapnutá – kód vytvořte v klientské sekci a pošlete zákazníkovi poznámkou.</span><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php if (!empty($ret['xorder_no'])): ?>
    <div class="card"<?= $ret['xorder_state'] === 'chyba' ? ' style="border:2px solid var(--red,#d33)"' : '' ?>><span class="kicker">Výměna v Pohodě</span>
      <div style="font-size:14px;line-height:1.6">Přijatá objednávka <strong><?= e($ret['xorder_no']) ?></strong> · <?= e(Service::XORDER_STATE[$ret['xorder_state']] ?? $ret['xorder_state']) ?>
      <?php if (Service::advanceAmount($ret) > 0.005): ?><br>Zálohová faktura na doplatek <?= e(money(Service::advanceAmount($ret))) ?>: <?= $ret['zf_no'] ? '<strong>' . e($ret['zf_no']) . '</strong> · ' . ($ret['zf_paid'] ? 'zaplaceno' : 'čeká na platbu') : 'vznikne s objednávkou' ?><?php endif; ?></div>
      <?php if ($ret['xorder_state'] === 'chyba' && $ret['xorder_error']): ?><div class="warn" style="font-size:13px"><?= e($ret['xorder_error']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($plan['type']): ?>
    <div class="card"<?= $docState === 'chyba' ? ' style="border:2px solid var(--red,#d33)"' : '' ?>><span class="kicker">Doklad v Pohodě</span>
      <?php if ($docState === 'hotovo'): ?>
        <div style="font-size:14px"><?= e(Service::DOC_TYPE[$ret['doc_type']] ?? 'Doklad') ?> <strong style="user-select:all"><?= e((string) $ret['doc_no']) ?></strong> je vystavený.</div>
        <span class="hint"><?= $ret['doc_type'] === 'odd' ? 'Peníze pošlete příkazem k úhradě. Pak ve výpisu vratek „K proplacení“ označte odeslané – zákazník dostane e-mail.' : 'Novou velikost odešlete a změňte stav na Vyřízeno.' ?></span>
      <?php elseif ($docState === 'ceka'): ?>
        <div style="font-size:14px"><?= e(Service::DOC_TYPE[$ret['doc_type']] ?? 'Doklad') ?> čeká na vystavení. Pomocník na KANCL ho vystaví <?= e(Service::pohodaEta()) ?>.</div>
      <?php elseif ($docState === 'navrh'): ?>
        <div style="font-size:14px">Návrh dokladu je připravený a čeká na schválení, než se zapíše do Pohody.</div>
      <?php else: ?>
        <div style="font-size:14px;line-height:1.55"><strong><?= e(Service::DOC_TYPE[$plan['type']]) ?></strong> na <strong><?= e(money($plan['total'])) ?></strong></div>
        <?php foreach ($plan['items'] as $i): ?>
          <div style="font-size:13px;display:flex;justify-content:space-between;gap:8px;border-top:1px solid var(--line2);padding-top:6px"><span><?= e($i['name']) ?><?= $i['code'] !== '' ? ' <span class="hint">(' . e($i['code']) . ')</span>' : '' ?></span><span style="white-space:nowrap"><?= (int) $i['qty'] ?> × <?= e(money($i['unit_price'])) ?></span></div>
        <?php endforeach; ?>
        <?php if ($docState === 'chyba'): ?><div class="warn" style="font-size:13px"><?= e((string) $ret['doc_error']) ?></div><?php endif; ?>
        <?php if ($ret['checked_at'] && !empty($ret['zf_no']) && empty($ret['zf_paid'])): ?><span class="hint">Zboží je zkontrolované. Doklad se vystaví sám, až přijde doplatek.</span>
        <?php elseif (!in_array($ret['status'], ['vyrizeno', 'zamitnuto'], true)): ?>
        <form data-once method="post" action="<?= url('/admin/vratky/' . $ret['no']) ?>" style="margin:0;display:flex;flex-direction:column;gap:8px"><?= csrf_field() ?>
          <input type="hidden" name="act" value="<?= $docState === 'chyba' ? 'doklad_znovu' : 'doklad' ?>">
          <?php foreach ($lines as $l): if ($l['action'] !== 'vymenit') continue; if ((string) ($l['new_code'] ?? '') !== ''): ?><input type="hidden" name="new_code[<?= (int) $l['id'] ?>]" value="<?= e((string) $l['new_code']) ?>"><?php continue; endif; ?>
            <div class="field" style="margin:0"><label class="l" for="nc<?= (int) $l['id'] ?>">Kód zásoby – <?= e(($l['new_name'] ?? '') !== '' ? $l['new_name'] : 'nová velikost ' . $l['size_to']) ?></label>
              <input class="f" id="nc<?= (int) $l['id'] ?>" name="new_code[<?= (int) $l['id'] ?>]" value="<?= e((string) ($l['new_code'] ?? '')) ?>" placeholder="kód z Pohody" required></div>
          <?php endforeach; ?>
          <button class="btn" style="align-self:flex-start"><?= $docState === 'chyba' ? 'Zkusit znovu' : (in_array($ret['status'], ['novy', 'na_ceste'], true) ? 'Zboží převzato a je v pořádku ✓' : 'Zboží je v pořádku ✓') ?></button>
          <span class="hint">Doklad v Pohodě i e-mail zákazníkovi pak půjdou samy.</span>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="card"><span class="kicker">Peníze</span>
      <?php $bal = Service::balance($ret); if (abs($bal) > 0.005 || $ret['refund_total'] > 0): ?>
        <div style="font-size:14px;line-height:1.6"><?php if ((float) $ret['refund_total'] > 0): ?>Vrácené zboží: <?= e(money($ret['refund_total'])) ?><br><?php endif; ?>
          <?php if ((float) ($ret['swap_diff'] ?? 0) != 0): ?><?= (float) $ret['swap_diff'] > 0 ? 'Jiné zboží levnější – vrátit rozdíl' : 'Jiné zboží dražší – doplatek zákazníka' ?>: <?= e(money(abs((float) $ret['swap_diff']))) ?><?php endif; ?>
          <?php if ((float) ($ret['cod_fee'] ?? 0) > 0): ?><br>Dobírka (platí zákazník): <?= e(money($ret['cod_fee'])) ?><?php endif; ?>
          <?php if ($ret['postage_applies'] && $ret['postage'] !== null): ?><br>Poštovné za vrácení: −<?= e(money($ret['postage'])) ?><?php endif; ?>
          <?php if ((float) ($ret['ship_charge'] ?? 0) > 0): ?><br>Doprava (pod hranicí dopravy zdarma): −<?= e(money($ret['ship_charge'])) ?><?php endif; ?>
          <?php if ((float) $ret['ship_refund'] > 0): ?><br>Vrácení dopravy: +<?= e(money($ret['ship_refund'])) ?><?php endif; ?>
          <br><?= $bal < 0 ? 'Zákazník doplatí' : 'Vrátit' ?>: <strong><?= e(money(abs($bal))) ?></strong></div>
        <?php if ($bal < 0): ?><div style="font-size:14px">Doplatek: <strong><?= e(Service::PAY[$ret['pay_method']] ?? '–') ?></strong><?php $cardPay = \App\Modules\Vratky\GpWebpay::paidPayment((int) $ret['id']); ?><?= $ret['pay_method'] === 'prevod' ? ($ret['zf_paid'] ? ($cardPay ? ' – zaplaceno kartou ' . e(money((float) $cardPay['amount'])) . ' (GP ' . e($cardPay['order_number']) . ($cardPay['test'] ? ', TEST' : '') . '), nové zboží můžete odeslat' : ' – zaplaceno, nové zboží můžete odeslat') : ' – nové zboží odeslat až po zaplacení zálohové faktury') : ' – nové zboží poslat na dobírku' ?></div>
        <?php else: ?><div style="font-size:14px">Na účet: <strong style="user-select:all"><?= e($ret['bank_account']) ?></strong></div><?php endif; ?>
      <?php else: ?><span class="hint">Nic se nevrací.</span><?php endif; ?>
    </div>
    <?php if ($claim && $ret['claim_deadline']): ?><div class="card"><span class="kicker">Reklamace</span><div style="font-size:14px">Vyřídit nejpozději do <strong><?= e(date_cz($ret['claim_deadline'])) ?></strong></div></div><?php endif; ?>
    <?php if ($ret['note']): ?><div class="card"><span class="kicker">Poznámka zákazníka</span><div style="font-size:14px;white-space:pre-line"><?= e($ret['note']) ?></div></div><?php endif; ?>
  </aside>
  </div>
</div>
