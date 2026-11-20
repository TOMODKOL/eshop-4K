<?php
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Sync;
/** @var array $order @var array $items @var array $dl @var array $old @var array $errors */
$o = fn(string $k, $id = null, $def = '') => $id === null ? ($old[$k] ?? $def) : ($old[$k][$id] ?? $def);
$posted = (bool) $old;
?>
<form class="col wide" method="post" action="<?= url('/admin/vratky/nova/' . (int) $order['id']) ?>" enctype="multipart/form-data">
<?= csrf_field() ?>
  <div style="display:flex;flex-direction:column;gap:6px">
    <span class="kicker"><?= e(Sync::SOURCES[$order['source']] ?? $order['source']) ?> · doklad <?= e($order['invoice_no']) ?> z <?= e(date_cz($order['invoice_date'])) ?></span>
    <h1 class="h2">NOVÁ REKLAMACE</h1>
    <a class="link" href="<?= url('/admin/vratky/nova') ?>" style="padding:0;align-self:flex-start">← Jiný doklad</a>
  </div>

  <?php if ($errors): ?>
    <div class="flash err" role="alert"><strong>Zkontrolujte prosím:</strong><ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    <?php if (!empty($_FILES['photos']['name'])): ?><div style="margin-top:6px">Fotky prosím přidejte znovu.</div><?php endif; ?></div>
  <?php endif; ?>
  <?php if (!$dl['can_claim']): ?><div class="flash err">Od nákupu uplynulo víc než <?= (int) cfg('vratky.claim_months', 24) ?> měsíců (lhůta do <?= e(date_cz($dl['claim_until'])) ?>). Reklamaci systém nepřijme.</div><?php endif; ?>

  <div class="card"><span class="kicker">Reklamované zboží</span>
    <?php if (!$items): ?><span class="hint">Na dokladu není zboží, které by šlo reklamovat.</span><?php endif; ?>
    <?php foreach ($items as $it): $id = (int) $it['id']; $left = $it['qty_left']; $on = $o('act', $id) === 'reklamovat'; ?>
      <div style="border-top:1px solid var(--line2);padding-top:12px;display:flex;flex-direction:column;gap:10px">
        <?php if ($left < 1): ?>
          <div class="item"><div class="t"><span class="n"><?= e($it['name']) ?></span><span class="m">Už je v jiné reklamaci nebo vratce.</span></div></div>
        <?php else: ?>
        <label class="chk"><input type="checkbox" name="act[<?= $id ?>]" value="reklamovat" <?= $on ? 'checked' : '' ?>>
          <span><strong><?= e($it['name']) ?></strong><br><span class="hint"><?= e(money($it['unit_price'])) ?><?= $it['qty_int'] > 1 ? ' / ks · koupeno ' . $it['qty_int'] . ' ks' : '' ?><?= $it['code'] ? ' · kód ' . e($it['code']) : '' ?></span></span></label>
        <?php if ($left > 1): ?>
          <div class="field"><label class="l" for="qty-<?= $id ?>">Kolik kusů</label>
            <select class="f" id="qty-<?= $id ?>" name="qty[<?= $id ?>]" style="width:auto;min-width:110px">
              <?php for ($q = 1; $q <= $left; $q++): ?><option value="<?= $q ?>" <?= (int) $o('qty', $id, 1) === $q ? 'selected' : '' ?>><?= $q ?> z <?= $left ?> ks</option><?php endfor; ?>
            </select></div>
        <?php else: ?><input type="hidden" name="qty[<?= $id ?>]" value="1"><?php endif; ?>
        <div class="field"><label class="l" for="vada-<?= $id ?>">Popis vady</label>
          <textarea class="f" rows="3" id="vada-<?= $id ?>" name="defect[<?= $id ?>]" maxlength="3000" placeholder="Co zákazník reklamuje, kdy vadu zjistil, co jsme viděli při převzetí."><?= e($o('defect', $id)) ?></textarea></div>
        <div class="field"><label class="l" for="foto-<?= $id ?>">Fotky (nepovinné, nejvýš 6)</label>
          <input id="foto-<?= $id ?>" type="file" name="photos[<?= $id ?>][]" accept="image/*" multiple></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card"><span class="kicker">Zákazník</span>
    <div class="field"><label class="l" for="customer_name">Jméno</label>
      <input class="f" id="customer_name" name="customer_name" value="<?= e($o('customer_name', null, $order['customer_name'])) ?>" required></div>
    <div class="field"><label class="l" for="phone">Telefon</label>
      <input class="f" id="phone" name="phone" type="tel" value="<?= e($o('phone', null, $order['phone'])) ?>"></div>
    <div class="field"><label class="l" for="email">E-mail <span style="font-weight:400;color:var(--muted)">(když ho zákazník dá, dostane potvrzení a změny stavu)</span></label>
      <input class="f" id="email" name="email" type="email" value="<?= e($o('email', null, $order['email'])) ?>"></div>
    <span class="hint">Vyplňte aspoň telefon nebo e-mail.</span>
  </div>

  <div class="card"><span class="kicker">Převzetí</span>
    <?php foreach (Service::SHIP as $k => $lab): ?>
      <label class="chk"><input type="radio" name="ship" value="<?= $k ?>" <?= $o('ship', null, 'osobne') === $k ? 'checked' : '' ?>>
        <span><?= $k === 'osobne' ? 'Zákazník zboží přinesl na prodejnu' : 'Zákazník pošle: ' . e($lab) ?></span></label>
    <?php endforeach; ?>
    <div class="field"><label class="l" for="note">Poznámka <span style="font-weight:400;color:var(--muted)">(např. příslušenství, stav při převzetí)</span></label>
      <textarea class="f" id="note" name="note" rows="3" maxlength="2000"><?= e($o('note')) ?></textarea></div>
    <label class="chk"><input type="checkbox" name="notify" value="1" <?= !$posted || $o('notify') ? 'checked' : '' ?>><span>Poslat zákazníkovi potvrzení e-mailem (jen když je vyplněný e-mail)</span></label>
  </div>

  <button class="btn dark" type="submit">Založit reklamaci</button>
</form>
