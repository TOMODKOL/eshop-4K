<?php
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Eshop;
/** @var array $order @var array $items @var array $dl @var array $old @var array $errors */
$o = fn(string $k, $id = null, $def = '') => $id === null ? ($old[$k] ?? $def) : ($old[$k][$id] ?? $def);
$postage = cfg('vratky.return_postage');
[$shipCharge, $goodsLeft, $freeFrom] = Service::freeShippingInfo($order);
$steps = ['Položky', 'Doprava', 'Kontakt a peníze', 'Souhrn'];
?>
<form id="vrform" class="layout" method="post" action="<?= url('/vratky/novy') ?>" enctype="multipart/form-data" novalidate
      data-postage="<?= $postage === null ? '' : e((string) $postage) ?>"
      data-ship-charge="<?= e((string) $shipCharge) ?>" data-goods-left="<?= e((string) $goodsLeft) ?>" data-free-from="<?= e((string) $freeFrom) ?>" data-cod-fee="<?= e((string) cfg('vratky.cod_fee', 69)) ?>" data-product-url="<?= url('/vratky/zbozi') ?>" data-search-url="<?= url('/vratky/hledat') ?>" data-draft-url="<?= url('/vratky/koncept') ?>" data-shop-url="<?= e(rtrim((string) cfg('shop.web'), '/')) ?>/eshop-c372/" data-start="<?= (int) ($start ?? 1) ?>">
<?= csrf_field() ?>
<div class="main"><div class="col">

<?php if ($errors): ?>
  <div class="flash err" role="alert"><strong>Zkontrolujte prosím:</strong><ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  <?php if (!empty($_FILES['photos']['name'])): ?><div style="margin-top:6px">Fotky k reklamaci prosím přidejte znovu.</div><?php endif; ?></div>
<?php endif; ?>

<div class="prog" aria-hidden="true"><div class="bars"><?php foreach ($steps as $s): ?><i></i><?php endforeach; ?></div><span class="kicker" data-progtext>Krok 1 z <?= count($steps) ?> · <?= $steps[0] ?></span></div>

<!-- 1 · položky -->
<section class="step" data-step="1" data-title="<?= $steps[0] ?>">
  <div style="padding:0 4px;display:flex;flex-direction:column;gap:6px">
    <h2 class="h2">CO CHCETE UDĚLAT?</h2>
    <span class="hint" style="font-size:13px">Objednávka <?= e($order['order_no'] ?: $order['invoice_no']) ?> · faktura z <?= e(date_cz($order['invoice_date'])) ?></span>
  </div>
  <div class="grey">
    <?php if ($dl['basis'] === 'waiting'): ?>Zásilku jste zatím nepřevzali – vrátit nebo vyměnit ji můžete do 14 dnů od převzetí.
    <?php elseif ($dl['can_return'] && $dl['basis'] === 'delivered'): ?>Zásilku jste převzali <?= e(date_cz($dl['delivered'])) ?>. Vrátit nebo vyměnit můžete do <strong style="color:var(--ink)"><?= e(date_cz($dl['return_until'])) ?></strong>.
    <?php elseif ($dl['can_return']): ?>Vrátit nebo vyměnit můžete do 14 dnů od převzetí zásilky, tedy zhruba do <strong style="color:var(--ink)"><?= e(date_cz($dl['return_until'])) ?></strong>.
    <?php else: ?>Lhůta pro vrácení a výměnu už uplynula, zboží můžete reklamovat. Pokud jste zásilku převzali později, zavolejte nám na <?= e(cfg('shop.phone')) ?>.<?php endif; ?>
    Reklamovat do <?= e(date_cz($dl['claim_until'])) ?>.
  </div>
  <?php $prev = Service::returnsForOrder((int) $order['id']); if ($prev): ?>
  <div class="card prevret"><strong>Už zadané k této objednávce</strong>
    <?php foreach ($prev as $p): ?><a class="ordrow" href="<?= url('/vratky/stav/' . $p['token']) ?>"><span class="t"><strong><?= e(Service::kind(Service::lines((int) $p['id']))) ?></strong> <?= e(Service::STATUS[$p['status']] ?? $p['status']) ?> · zadáno <?= e(date_cz(substr($p['created_at'], 0, 10))) ?></span><span aria-hidden="true">Stav →</span></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($dl['can_return']): ?><div class="freeswap big"><b>Výměna = doprava zdarma</b><span>Vyměňte za jinou velikost nebo za jiné zboží a neplatíte nic za dopravu k nám ani k vám. Při vrácení zboží se poštovné <?= e(money((float) $postage)) ?> odečte.</span></div><?php endif; ?>
  <?php if (!$items): ?><div class="card">V této objednávce není zboží, které by šlo vrátit. Zavolejte nám prosím na <?= e(cfg('shop.phone')) ?>.</div><?php endif; ?>
  <?php foreach ($items as $it): $id = (int) $it['id']; $cur = $o('act', $id); $left = $it['qty_left']; ?>
  <div class="card item-card" data-item="<?= $id ?>" data-price="<?= e((string) (float) $it['unit_price']) ?>" data-name="<?= e($it['name']) ?>">
    <div class="item"><?php if (!empty($it['image'])): ?><img class="thumb" src="<?= e($it['image']) ?>" alt="" width="64" height="64"><?php else: ?><span class="thumb ph" aria-hidden="true"></span><?php endif; ?><div class="t"><span class="n"><?= e($it['name']) ?></span>
      <span class="m"><?= e(money($it['unit_price'])) ?><?= $it['qty_int'] > 1 ? ' / ks · objednáno ' . $it['qty_int'] . ' ks' : '' ?><?= $it['code'] ? ' · kód ' . e($it['code']) : '' ?></span></div></div>
    <?php if ($left < 1): ?>
      <span class="hint">Tuto položku už jste zadali – stav najdete výše.</span>
    <?php else: ?>
    <fieldset class="seg" style="border:0;padding:0;margin:0">
      <legend class="sr">Co chcete udělat s: <?= e($it['name']) ?></legend>
      <?php foreach (['vratit' => 'Vrátit', 'vymenit' => 'Vyměnit', 'reklamovat' => 'Reklamovat'] as $a => $lab):
        $dis = ($a !== 'reklamovat' && !$dl['can_return']) || ($a === 'reklamovat' && !$dl['can_claim']); ?>
        <label class="pill <?= $cur === $a ? 'on' : '' ?> <?= $dis ? 'na' : '' ?>" style="display:flex;align-items:center;justify-content:center">
          <input class="sr" type="radio" name="act[<?= $id ?>]" value="<?= $a ?>" <?= $cur === $a ? 'checked' : '' ?> <?= $dis ? 'disabled' : '' ?>><?= $lab ?><?php if ($a === 'vymenit' && !$dis): ?><small class="fr">doprava zdarma</small><?php endif; ?></label>
      <?php endforeach; ?>
    </fieldset>
    <button type="button" class="link js-only" data-clear="<?= $id ?>" style="align-self:flex-start;padding:0;font-size:13px<?= $cur ? '' : ';display:none' ?>">Zrušit výběr</button>
    <?php if ($left > 1): ?>
      <div class="qtyrow">
        <label class="l" for="qty-<?= $id ?>">Kolik kusů? <span class="hint">objednali jste <?= $left ?> ks</span></label>
        <?= qty_stepper('qty[' . $id . ']', 'qty-' . $id, (int) $o('qty', $id, 1), $left) ?>
      </div>
    <?php else: ?><input type="hidden" name="qty[<?= $id ?>]" value="1"><?php endif; ?>
    <div class="detail inl" data-detail="<?= $id ?>">

    <div data-for="vratit">
      <div class="note">Chcete raději <?= empty($it['no_size']) ? 'jinou velikost nebo jiné zboží' : 'jiné zboží' ?>? <strong>Při výměně je doprava zdarma</strong> a poštovné <?= e(money((float) $postage)) ?> se neodečítá. <button type="button" class="link js-only" data-switch="<?= $id ?>" style="padding:0;color:var(--ink);font-weight:700">Změnit na výměnu →</button></div>
    </div>

    <div data-for="vymenit">
      <?php $kind = !empty($it['no_size']) || $o('swap_kind', $id, 'velikost') === 'jine' ? 'jine' : 'velikost'; ?>
      <?php if (!empty($it['no_size'])): ?>
      <input class="sr" type="radio" name="swap_kind[<?= $id ?>]" value="jine" checked tabindex="-1" aria-hidden="true">
      <?php else: ?>
      <fieldset class="seg" style="border:0;padding:0;margin:0">
        <legend class="sr">Za co chcete vyměnit</legend>
        <?php foreach (['velikost' => 'Jinou velikost', 'jine' => 'Jiné zboží'] as $k => $lab): ?>
          <label class="pill <?= $kind === $k ? 'on' : '' ?>" style="display:flex;align-items:center;justify-content:center"><input class="sr" type="radio" name="swap_kind[<?= $id ?>]" value="<?= $k ?>" <?= $kind === $k ? 'checked' : '' ?>><?= $lab ?></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <?php if (empty($it['no_size'])): ?>
      <div data-kind="velikost" class="kindbox">
        <?php if (!empty($it['eshop'])): ?>
          <fieldset class="sizes" style="border:0;padding:0;margin:0">
            <legend class="l" style="padding:0;margin-bottom:8px">Za jakou velikost?</legend>
            <div class="sizegrid">
            <?php foreach ($it['eshop']['sizes'] as $sz): $cur = $sz['current']; $na = !$cur && $sz['cls'] !== 'zelena'; ?>
              <label class="sz<?= $cur ? ' cur' : '' ?><?= $na ? ' na' : '' ?>">
                <input class="sr" type="radio" name="size_to[<?= $id ?>]" value="<?= e($sz['value']) ?>" <?= $cur || $na ? 'disabled' : '' ?> <?= !$cur && !$na && $o('size_to', $id) === $sz['value'] ? 'checked' : '' ?>>
                <span class="v"><?= e($sz['value']) ?></span>
                <span class="a <?= e($sz['cls']) ?>"><?= $cur ? 'Vaše velikost' : e($sz['avail']) ?></span>
              </label>
            <?php endforeach; ?>
            </div>
          </fieldset>
          <span class="hint">Vybrat můžete velikost skladem nebo skladem u dodavatele (dostupnost je stejná jako na e-shopu). Novou velikost pošleme hned, jak vaše zboží dorazí.</span>
          <?php if (!array_filter($it['eshop']['sizes'], fn($z) => !$z['current'] && $z['cls'] === 'zelena')): ?>
            <div class="note">Jiná velikost teď není skladem ani u dodavatele. Zboží můžete vrátit, nebo <button type="button" class="link js-only" data-kindswitch="<?= $id ?>" style="padding:0;color:var(--ink);font-weight:700">vyměnit za jiné zboží →</button></div>
          <?php endif; ?>
        <?php else: ?>
        <label class="l" for="size-<?= $id ?>">Za jakou velikost?</label>
        <?php if ($it['sizes']): ?>
          <div class="sizegrid" id="size-<?= $id ?>">
            <?php foreach ($it['sizes'] as $sz): ?><label class="sz"><input class="sr" type="radio" name="size_to[<?= $id ?>]" value="<?= e($sz) ?>" <?= $o('size_to', $id) === $sz ? 'checked' : '' ?>><span class="v"><?= e($sz) ?></span></label><?php endforeach; ?>
          </div>
        <?php else: ?>
          <input class="f" id="size-<?= $id ?>" name="size_to[<?= $id ?>]" maxlength="40" value="<?= e($o('size_to', $id)) ?>" placeholder="např. M nebo 43">
        <?php endif; ?>
        <span class="hint">Dostupnost velikosti ověříme a novou pošleme hned, jak vaše zboží dorazí. Kdyby nebyla skladem, ozveme se.</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div data-kind="jine" class="kindbox">
        <div class="shoppick js-only">
          <button type="button" class="btn" data-shop="<?= $id ?>" data-name="<?= e($it['name']) ?>" data-credit="<?= e((string) (float) $it['unit_price']) ?>">Vybrat na e-shopu →</button>
          <span class="hint">Otevře se náš e-shop. U zboží, které chcete, klikněte na „Vyměnit za tento produkt“ a vrátíte se sem. Uvidíte i doplatek nebo kolik vám vrátíme.</span>
        </div>
        <details class="byurl"><summary>Nebo vložte odkaz na zboží</summary>
        <div class="field"><label class="l" for="ou-<?= $id ?>">Odkaz na zboží z e-shopu</label>
          <div class="urlrow"><input class="f" id="ou-<?= $id ?>" name="other_url[<?= $id ?>]" inputmode="url" autocomplete="off" placeholder="https://www.4kluciodkol.cz/…" value="<?= e($o('other_url', $id)) ?>">
            <button type="button" class="btn dark js-only" data-load="<?= $id ?>">Načíst</button></div>
          <span class="hint">Najděte zboží na <a href="<?= e(rtrim((string) cfg('shop.web'), '/')) ?>/" target="_blank" rel="noopener">4kluciodkol.cz ↗</a>, zkopírujte adresu stránky a vložte ji sem. Vybrat můžete zboží skladem nebo skladem u dodavatele. Rozdíl v ceně vám vrátíme, nebo ho doplatíte.</span></div>
        </details>
        <div data-other="<?= $id ?>"><?php $ou = trim((string) $o('other_url', $id)); if ($ou !== ''): ?><?= \App\Core\View::render('vratky:other_product', ['p' => Eshop::product($ou), 'id' => $id, 'sel' => (string) $o('other_pick', $id), 'url' => $ou, 'oqty' => (int) $o('other_qty', $id, 1)]) ?><?php endif; ?></div>
      </div>
    </div>

    <div data-for="reklamovat">
      <div class="field"><label class="l" for="vada-<?= $id ?>">Popište vadu</label>
        <textarea class="f" rows="3" id="vada-<?= $id ?>" name="defect[<?= $id ?>]" maxlength="3000" placeholder="Např. praskla přezka na řemínku. Kdy jste vadu zjistili?"><?= e($o('defect', $id)) ?></textarea></div>
      <div class="field"><span class="l">Fotky vady (doporučujeme)</span>
        <div class="thumbs" data-thumbs="<?= $id ?>"></div>
        <label class="upl">+ Přidat fotky<input type="file" name="photos[<?= $id ?>][]" accept="image/*" multiple data-photos="<?= $id ?>"></label>
        <span class="hint">Nejvýš 6 fotek, každá do 12 MB.</span></div>
      <div class="note">Poštovné u reklamace hradíme my. Reklamaci vyřídíme nejpozději do <?= (int) cfg('vratky.claim_resolve_days', 30) ?> dnů.</div>
    </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="mtotal" data-mtotal hidden><span data-mtotal-label>Vrátíme vám</span><strong data-mtotal-value>0 Kč</strong></div>
  <div class="navrow"><button type="button" class="btn js-only" data-next>Pokračovat →</button></div>
</section>

<!-- 2 · doprava -->
<section class="step" data-step="2" data-title="<?= $steps[1] ?>">
  <h2 class="h2" style="padding:0 4px">JAK NÁM TO POŠLETE?</h2>
  <?php foreach (['zbox' => ['Z-BOX Zásilkovny', 'Kód zadáte do Z-BOXu, nic netisknete. Nonstop.'],
                  'vm' => ['Výdejní místo Zásilkovny', 'Kód nadiktujete obsluze na kterémkoli výdejním místě.'],
                  'osobne' => ['Osobně na prodejně', cfg('shop.address') . ' · ' . cfg('shop.hours')],
                  'vlastni' => ['Vlastní dopravou', 'Pošlete na naši adresu na vlastní náklady, ne na dobírku.']] as $k => [$t, $d]): $on = $o('ship', null, 'zbox') === $k; ?>
  <label class="opt <?= $on ? 'on' : '' ?>"><input class="sr" type="radio" name="ship" value="<?= $k ?>" <?= $on ? 'checked' : '' ?>><span class="dot"></span>
    <span class="b"><span class="r"><strong><?= e($t) ?></strong><strong style="font-size:13px;white-space:nowrap" data-shipprice="<?= $k ?>"></strong></span><span class="d"><?= e($d) ?></span></span></label>
  <?php endforeach; ?>
  <div class="grey" data-postage-note hidden>Poštovné za vrácení odečteme z vrácené částky. Při výměně nebo reklamaci je zdarma.</div>
  <div class="mtotal" data-mtotal hidden><span data-mtotal-label>Vrátíme vám</span><strong data-mtotal-value>0 Kč</strong></div>
  <div class="navrow"><button type="button" class="btn js-only" data-next>Pokračovat →</button><button type="button" class="link js-only" data-prev>Zpět</button></div>
</section>

<!-- 3 · kontakt a peníze -->
<section class="step" data-step="3" data-title="<?= $steps[2] ?>">
  <h2 class="h2" style="padding:0 4px">KONTAKT A PENÍZE</h2>
  <div class="card">
    <div class="field" data-account><label class="l" for="account">Číslo účtu pro vrácení peněz</label>
      <input class="f" id="account" name="account" inputmode="text" autocomplete="off" placeholder="např. 123456789/0800" value="<?= e($o('account')) ?>">
      <span class="hint">Peníze pošleme nejpozději do 14 dnů od doručení zboží k nám, obvykle mnohem dřív.</span></div>
    <div class="field" data-paybox><span class="l">Jak doplatíte rozdíl v ceně?</span>
      <?php $pm = $o('pay_method'); $gpOn = \App\Modules\Vratky\GpWebpay::enabled(); foreach (['prevod' => ['Převodem nebo QR kódem', 'Platební údaje a QR kód pošleme e-mailem – stačí ho načíst v bankovní aplikaci. Nové zboží odešleme po zaplacení.']]
                    + ($gpOn ? ['karta' => ['Kartou online', 'Odkaz na platbu kartou pošleme e-mailem. Nové zboží odešleme po zaplacení.']] : []) + [
                    'dobirka' => ['Na dobírku', 'Zaplatíte při převzetí nového zboží. Dobírka stojí ' . money((float) cfg('vratky.cod_fee', 69)) . '.']] as $k => [$t, $d]): ?>
      <label class="opt <?= $pm === $k ? 'on' : '' ?>"><input class="sr" type="radio" name="pay_method" value="<?= $k ?>" <?= $pm === $k ? 'checked' : '' ?>><span class="dot"></span>
        <span class="b"><span class="r"><strong><?= e($t) ?></strong></span><span class="d"><?= e($d) ?></span></span></label>
      <?php endforeach; ?>
      <span class="hint nojs-only">Vyplňte jen tehdy, když je nové zboží dražší.</span></div>
    <div class="field"><label class="l" for="phone">Telefon <span style="font-weight:400;color:var(--muted)">(pro Zásilkovnu a když se potřebujeme domluvit)</span></label>
      <input class="f" id="phone" name="phone" type="tel" autocomplete="tel" value="<?= e($o('phone', null, $order['phone'])) ?>"></div>
    <div class="field"><label class="l" for="note">Poznámka pro nás <span style="font-weight:400;color:var(--muted)">(nepovinné)</span></label>
      <textarea class="f" id="note" name="note" rows="3" maxlength="2000"><?= e($o('note')) ?></textarea></div>
    <span class="hint">Potvrzení a podací kód pošleme na <?= e($order['email']) ?>.</span>
  </div>
  <div class="mtotal" data-mtotal hidden><span data-mtotal-label>Vrátíme vám</span><strong data-mtotal-value>0 Kč</strong></div>
  <div class="navrow"><button type="button" class="btn js-only" data-next>Pokračovat →</button><button type="button" class="link js-only" data-prev>Zpět</button></div>
</section>

<!-- 4 · souhrn -->
<section class="step" data-step="4" data-title="<?= $steps[3] ?>">
  <h2 class="h2" style="padding:0 4px">SOUHRN</h2>
  <div class="card" style="padding:4px 18px 18px;gap:0"><div data-summary><span class="hint" style="padding:14px 0;display:block">Souhrn se zobrazí po výběru položek.</span></div>
  <div class="total" data-total hidden><strong data-total-label>Vrátíme vám</strong><span class="v" data-total-value>0 Kč</span></div></div>
  <label class="chk"><input type="checkbox" name="agree" value="1" <?= $o('agree') ? 'checked' : '' ?> required>
    <span>Souhlasím s <a href="<?= e(rtrim((string) cfg('shop.web'), '/')) ?>/obchodni-podminky/" target="_blank" rel="noopener">obchodními podmínkami</a> pro vrácení a reklamaci zboží.</span></label>
  <button class="btn dark" type="submit" data-submit>Odeslat →</button>
  <button type="button" class="link js-only" data-prev>Zpět</button>
</section>

</div></div>

<aside class="aside" aria-label="Přehled">
  <div class="card"><span class="kicker">Objednávka <?= e($order['order_no'] ?: $order['invoice_no']) ?></span>
    <span style="font-size:13px;color:var(--text)"><?= e($order['customer_name']) ?> · faktura <?= e(date_cz($order['invoice_date'])) ?><?php if ($dl['basis'] === 'delivered'): ?><br>Převzato <?= e(date_cz($dl['delivered'])) ?><?php endif; ?><?php if ($dl['can_return'] && $dl['basis'] !== 'waiting'): ?><br>Vrátit lze do <?= e(date_cz($dl['return_until'])) ?><?php endif; ?></span></div>
  <div class="card"><span class="kicker">Přehled</span><div data-aside><span style="font-size:13px;color:var(--muted)">Zatím nic nevybráno.</span></div></div>
  <div class="note">Nevíte si rady? Zavolejte na <strong><?= e(cfg('shop.phone')) ?></strong>, <?= e(cfg('shop.hours')) ?>.</div>
</aside>
</form>
