<?php
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Packeta;
/** @var array $ret @var ?array $order @var array $lines @var array $events */
$claim = Service::hasAction($lines, 'reklamovat');
?>
<div class="col wide">
  <a class="link" href="<?= url('/admin/vratky') ?>" style="align-self:flex-start;padding:0">← Přehled vratek</a>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
    <h1 class="h2"><?= e(mb_strtoupper(Service::words($lines)['n'])) ?> <?= e(Service::displayNo($ret)) ?></h1>
    <span class="st s<?= array_search($ret['status'], array_keys(Service::STATUS), true) ?>" style="font-size:13px"><?= e(Service::STATUS[$ret['status']] ?? $ret['status']) ?></span>
  </div>
  <div class="layout" style="max-width:none">
  <div class="main"><div class="col" style="max-width:none">
    <?php foreach ($lines as $l): ?>
    <div class="card">
      <span class="tag"><?= e(Service::ACTIONS[$l['action']] ?? $l['action']) ?></span>
      <div><strong style="font-size:16px"><?= e($l['item_name']) ?></strong><br>
        <span class="hint"><?= $l['item_code'] ? 'Kód ' . e($l['item_code']) . ' · ' : '' ?><?= (int) $l['qty'] ?> ks × <?= e(money($l['unit_price'])) ?></span></div>
      <?php if ($l['action'] === 'vratit'): ?><div>Důvod: <strong><?= e($l['reason']) ?></strong></div><?php endif; ?>
      <?php if ($l['action'] === 'vymenit'): ?><div>Vyměnit za velikost: <strong><?= e($l['size_to']) ?></strong></div><?php endif; ?>
      <?php if ($l['action'] === 'reklamovat'): ?>
        <div class="grey" style="white-space:pre-line"><?= e($l['defect_text']) ?></div>
        <?php if ($l['photos']): ?><div class="photos"><?php foreach ($l['photos'] as $p): ?>
          <a href="<?= url('/admin/vratky/foto/' . $p['id']) ?>" target="_blank" rel="noopener"><?php if ($p['mime'] === 'image/heic'): ?><span class="pill" style="display:inline-flex;align-items:center">Fotka HEIC</span><?php else: ?><img src="<?= url('/admin/vratky/foto/' . $p['id']) ?>" alt="Fotka vady"><?php endif; ?></a>
        <?php endforeach; ?></div><?php else: ?><span class="hint">Bez fotek.</span><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <form class="card" method="post" action="<?= url('/admin/vratky/' . $ret['no']) ?>">
      <?= csrf_field() ?>
      <strong>Změnit stav nebo připsat poznámku</strong>
      <div class="field"><label class="l" for="status">Stav</label>
        <select class="f" id="status" name="status"><?php foreach (Service::STATUS as $k => $lab): ?><option value="<?= $k ?>" <?= $k === $ret['status'] ? 'selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="l" for="note">Poznámka</label><textarea class="f" id="note" name="note" rows="3" placeholder="Např. Peníze odeslány 21. 11. / Nová velikost M odeslána, sledování Z 123…"></textarea></div>
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
        Pomocník ji podle něj do pár minut propojí a zákazník dostane potvrzení s číslem z Pohody.</div>
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
      <span class="hint">Stav měňte v Pohodě (záložka Stav reklamace). Zákazník změnu uvidí do pár minut.</span>
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
        <div style="font-size:14px">Podací kód: <strong><?= e($ret['packeta_password']) ?></strong><br>Zásilka: <?= e($ret['packeta_barcode']) ?></div>
      <?php elseif ($ret['ship_method'] !== 'osobne'): ?>
        <?php if ($ret['packeta_error']): ?><div class="warn" style="font-size:13px"><?= e($ret['packeta_error']) ?></div><?php endif; ?>
        <?php if (Packeta::enabled()): ?>
        <form method="post" action="<?= url('/admin/vratky/' . $ret['no']) ?>" style="margin:0"><?= csrf_field() ?><input type="hidden" name="act" value="packeta"><button class="pill">Vytvořit podací kód a poslat zákazníkovi</button></form>
        <?php else: ?><span class="hint">Zásilkovna zatím není zapnutá – kód vytvořte v klientské sekci a pošlete zákazníkovi poznámkou.</span><?php endif; ?>
      <?php endif; ?>
    </div>
    <div class="card"><span class="kicker">Peníze</span>
      <?php if ($ret['refund_total'] > 0): ?>
        <div style="font-size:14px">Vrátit: <strong><?= e(money($ret['refund_total'])) ?></strong><?php if ($ret['postage_applies']): ?> − poštovné<?= $ret['postage'] !== null ? ' ' . e(money($ret['postage'])) : '' ?><?php endif; ?></div>
        <div style="font-size:14px">Na účet: <strong><?= e($ret['bank_account']) ?></strong></div>
      <?php else: ?><span class="hint">Nic se nevrací.</span><?php endif; ?>
    </div>
    <?php if ($claim && $ret['claim_deadline']): ?><div class="card"><span class="kicker">Reklamace</span><div style="font-size:14px">Vyřídit nejpozději do <strong><?= e(date_cz($ret['claim_deadline'])) ?></strong></div></div><?php endif; ?>
    <?php if ($ret['note']): ?><div class="card"><span class="kicker">Poznámka zákazníka</span><div style="font-size:14px;white-space:pre-line"><?= e($ret['note']) ?></div></div><?php endif; ?>
  </aside>
  </div>
</div>
