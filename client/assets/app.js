/* client.4kluciodkol.cz – průvodce vratkou (funguje i bez JS: pak se zobrazí všechny kroky naráz) */
(function () {
  'use strict';
  document.documentElement.classList.add('js');

  var form = document.getElementById('vrform');
  if (!form) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('.step'));
  var progBars = form.querySelectorAll('.prog .bars i');
  var progText = form.querySelector('[data-progtext]');
  var postageCfg = form.getAttribute('data-postage');
  var postage = postageCfg === '' ? null : parseFloat(postageCfg);
  var shipChargeCfg = parseFloat(form.getAttribute('data-ship-charge')) || 0;
  var goodsLeft = parseFloat(form.getAttribute('data-goods-left')) || 0;
  var freeFrom = parseFloat(form.getAttribute('data-free-from')) || 0;
  var codFee = parseFloat(form.getAttribute('data-cod-fee')) || 0;
  var current = 1;
  var LABEL = { vratit: 'Vrácení', vymenit: 'Výměna velikosti', reklamovat: 'Reklamace' };
  var nf = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 2 });
  function money(n) { return nf.format(n).replace(/\s/g, ' ') + ' Kč'; }
  function $(sel, root) { return (root || form).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || form).querySelectorAll(sel)); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  // vybraná velikost: dlaždice (radio) nebo select / textové pole
  function sizeVal(id) {
    var r = form.querySelector('input[type="radio"][name="size_to[' + id + ']"]:checked');
    if (r) return r.value;
    var s = form.querySelector('select[name="size_to[' + id + ']"], input[type="text"][name="size_to[' + id + ']"], input:not([type])[name="size_to[' + id + ']"]');
    return s && s.value.trim() ? s.value.trim() : '';
  }

  // ---------- data z formuláře ----------
  function items() {
    return $$('.item-card').map(function (card) {
      var id = card.getAttribute('data-item');
      var act = $('input[name="act[' + id + ']"]:checked', card);
      var qtyEl = form.querySelector('[name="qty[' + id + ']"]');
      var kindEl = form.querySelector('input[name="swap_kind[' + id + ']"]:checked');
      var kind = kindEl ? kindEl.value : 'velikost';
      var pick = form.querySelector('input[name="other_pick[' + id + ']"]:checked');
      var box = form.querySelector('[data-other="' + id + '"] .otherprod strong');
      var np = pick ? parseFloat(pick.getAttribute('data-price')) : NaN;
      return {
        id: id,
        name: card.getAttribute('data-name'),
        price: parseFloat(card.getAttribute('data-price')) || 0,
        act: act ? act.value : '',
        qty: qtyEl ? parseInt(qtyEl.value, 10) || 1 : 1,
        kind: kind,
        picked: !!pick,
        newPrice: kind === 'jine' && pick && !isNaN(np) ? np : null,
        newQty: (function () { var s = form.querySelector('[name="other_qty[' + id + ']"]'); return s ? parseInt(s.value, 10) || 1 : 1; })(),
        newName: kind === 'jine' && pick && box ? (box.textContent + (pick.value ? ' ' + pick.value : '')) : ''
      };
    });
  }
  function chosen() { return items().filter(function (i) { return i.act; }); }
  function ship() { var s = $('input[name="ship"]:checked'); return s ? s.value : ''; }
  function calc() {
    var ch = chosen(), refund = 0, diff = 0;
    ch.forEach(function (i) {
      if (i.act === 'vratit') refund += i.price * i.qty;
      if (i.act === 'vymenit' && i.newPrice !== null) diff += i.price * i.qty - i.newPrice * i.newQty;
    });
    var onlyReturn = ch.length > 0 && ch.every(function (i) { return i.act === 'vratit'; });
    var after = goodsLeft - refund;
    var shipCharge = (shipChargeCfg > 0 && refund > 0 && after > 0.005 && after < freeFrom) ? shipChargeCfg : 0;
    var postageApplies = onlyReturn && (ship() === 'zbox' || ship() === 'vm');
    var balance = refund + diff - (postageApplies && postage !== null ? postage : 0) - shipCharge;
    var payEl = $('input[name="pay_method"]:checked');
    var cod = balance < -0.005 && payEl && payEl.value === 'dobirka' ? codFee : 0;
    return { ch: ch, refund: refund, diff: diff, onlyReturn: onlyReturn, postageApplies: postageApplies, shipCharge: shipCharge, balance: balance - cod, cod: cod, owes: balance < -0.005 };
  }
  function detailText(i) {
    if (i.act === 'vratit') { var r = $('input[name="reason[' + i.id + ']"]:checked'); return r ? r.value : ''; }
    if (i.act === 'vymenit') {
      if (i.kind === 'jine') return i.newName ? 'Za: ' + i.newName + (i.newQty > 1 ? ' × ' + i.newQty + ' ks' : '') : '';
      var sv = sizeVal(i.id); return sv ? 'Za velikost ' + sv : '';
    }
    var d = form.querySelector('[name="defect[' + i.id + ']"]');
    var n = (form.querySelector('[data-photos="' + i.id + '"]') || { files: [] }).files.length;
    return (d && d.value ? 'Vada: ' + d.value.slice(0, 80) : '') + (n ? ' · ' + n + ' foto' : '');
  }

  // ---------- vykreslení ----------
  function syncPills() {
    $$('label.pill, label.opt').forEach(function (l) {
      var inp = l.querySelector('input');
      if (inp) l.classList.toggle('on', inp.checked);
    });
    $$('[data-clear]').forEach(function (b) {
      var id = b.getAttribute('data-clear');
      b.style.display = $('input[name="act[' + id + ']"]:checked') ? '' : 'none';
    });
    $$('.item-card').forEach(function (c) { c.classList.toggle('sel', !!$('input[type=radio]:checked', c)); });
  }

  function syncDetails() {
    var map = {};
    items().forEach(function (i) { map[i.id] = i.act; });
    $$('.detail').forEach(function (d) {
      var id = d.getAttribute('data-detail'), act = map[id] || '';
      d.hidden = !act;
      $$('[data-for]', d).forEach(function (b) { b.hidden = b.getAttribute('data-for') !== act; });
      var k = $('input[name="swap_kind[' + id + ']"]:checked', d);
      var tag = $('[data-tag]', d); if (tag) tag.textContent = act === 'vymenit' && k && k.value === 'jine' ? 'Výměna za jiné zboží' : (LABEL[act] || '');
      $$('[data-kind]', d).forEach(function (b) { b.hidden = b.getAttribute('data-kind') !== (k ? k.value : 'velikost'); });
    });
  }

  function syncMoney() {
    var c = calc();
    // doprava
    $$('[data-shipprice]').forEach(function (el) {
      var k = el.getAttribute('data-shipprice');
      var paid = c.onlyReturn && (k === 'zbox' || k === 'vm') && c.ch.length;
      if (k === 'vlastni') { el.textContent = 'na vaše náklady'; el.className = ''; return; }
      el.textContent = paid ? (postage === null ? 'dle ceníku' : '− ' + money(postage)) : 'zdarma';
      el.className = paid ? '' : 'free';
    });
    var note = $('[data-postage-note]'); if (note) note.hidden = !c.postageApplies;
    var acc = $('[data-account]'); if (acc) acc.hidden = c.balance <= 0.005;
    var pb = $('[data-paybox]'); if (pb) pb.hidden = !c.owes;
    c.ch.forEach(function (i) {
      var el = form.querySelector('[data-diff="' + i.id + '"]');
      if (!el) return;
      if (i.newPrice === null) { el.hidden = true; return; }
      var d = i.price * i.qty - i.newPrice * i.newQty;
      el.hidden = false;
      el.innerHTML = Math.abs(d) < 0.005 ? 'Cena je stejná, nic se nedoplácí.' : (d > 0 ? 'Nové zboží je levnější – rozdíl <strong>' + money(d) + '</strong> vám vrátíme.' : 'Nové zboží je dražší – doplatíte <strong>' + money(-d) + '</strong>.');
    });

    // panel vpravo + souhrn
    var rows = c.ch.map(function (i) {
      var amount = i.act === 'vratit' ? money(i.price * i.qty) : (i.newPrice !== null ? (function (d) { return Math.abs(d) < 0.005 ? 'bez doplatku' : (d > 0 ? 'vrátíme ' + money(d) : 'doplatek ' + money(-d)); })(i.price * i.qty - i.newPrice * i.newQty) : '–');
      return { label: i.act === 'vymenit' && i.kind === 'jine' ? 'Výměna za jiné zboží' : LABEL[i.act], name: i.name + (i.qty > 1 ? ' × ' + i.qty : ''), detail: detailText(i), amount: amount };
    });
    var shipEl = $('input[name="ship"]:checked');
    var shipName = shipEl ? shipEl.closest('label').querySelector('strong').textContent : '';
    var postTxt = ship() === 'vlastni' ? 'na vaše náklady' : (c.postageApplies ? (postage === null ? '− poštovné' : '− ' + money(postage)) : 'zdarma');
    var net = c.balance;
    var totLabel = net > 0.005 ? 'Vrátíme vám' : (net < -0.005 ? 'Doplatíte' : 'Bez peněz');
    var codRow = c.cod ? '<div class="row"><span>Dobírka</span><strong style="white-space:nowrap">+ ' + money(c.cod) + '</strong></div>' : '';
    var chargeRow = c.shipCharge ? '<div class="row"><span>Doprava objednávky – zbytek je pod ' + money(freeFrom) + ', doprava už není zdarma</span><strong style="white-space:nowrap">− ' + money(c.shipCharge) + '</strong></div>' : '';

    var aside = $('[data-aside]');
    if (aside) {
      aside.innerHTML = rows.length ? rows.map(function (r) {
        return '<div class="row"><span><span class="kicker" style="font-size:10px">' + esc(r.label) + '</span><br>' + esc(r.name) + '</span><strong style="white-space:nowrap">' + r.amount + '</strong></div>';
      }).join('') + '<div class="row" style="border-top:1px solid var(--line2);padding-top:10px"><span>Doprava</span><strong class="' + (c.postageApplies || ship() === 'vlastni' ? '' : 'free') + '">' + postTxt + '</strong></div>' + chargeRow +
        codRow + '<div class="total" style="padding:14px"><strong style="font-size:14px">' + totLabel + '</strong><span class="v" style="font-size:28px">' + money(Math.abs(net)) + '</span></div>'
        : '<span style="font-size:13px;color:var(--muted)">Zatím nic nevybráno.</span>';
    }
    var sum = $('[data-summary]');
    if (sum) {
      sum.innerHTML = rows.map(function (r) {
        return '<div class="sumrow"><span><span class="kicker" style="font-size:11px">' + esc(r.label) + '</span><br><strong>' + esc(r.name) + '</strong>' + (r.detail ? '<br><span style="color:var(--muted)">' + esc(r.detail) + '</span>' : '') + '</span><strong style="white-space:nowrap">' + r.amount + '</strong></div>';
      }).join('') + (shipName ? '<div class="sumrow"><span>Doprava: ' + esc(shipName) + '</span><strong class="' + (c.postageApplies || ship() === 'vlastni' ? '' : 'free') + '" style="white-space:nowrap">' + postTxt + '</strong></div>' : '') +
        (c.shipCharge ? '<div class="sumrow"><span>Doprava objednávky – zbytek objednávky je pod ' + esc(money(freeFrom)) + ', doprava už není zdarma</span><strong style="white-space:nowrap">− ' + money(c.shipCharge) + '</strong></div>' : '') +
        (c.cod ? '<div class="sumrow"><span>Dobírka za doplatek</span><strong style="white-space:nowrap">+ ' + money(c.cod) + '</strong></div>' : '');
    }
    var tot = $('[data-total]');
    if (tot) {
      tot.hidden = !c.ch.length;
      $('[data-total-label]').textContent = net > 0.005 ? 'Vrátíme vám' : (net < -0.005 ? 'Doplatíte' : 'Vychází přesně');
      $('[data-total-value]').innerHTML = money(Math.abs(net)) + (c.postageApplies && postage === null ? ' <span style="font-family:\'Good Sans\',Arial;font-size:14px">− poštovné</span>' : '');
    }
  }

  function render() { syncPills(); syncDetails(); syncMoney(); }

  // ---------- kroky ----------
  function show(n) {
    current = n;
    steps.forEach(function (s) { s.hidden = parseInt(s.getAttribute('data-step'), 10) !== n; });
    Array.prototype.forEach.call(progBars, function (b, i) { b.classList.toggle('on', i < n); });
    var st = steps[n - 1];
    if (progText && st) progText.textContent = 'Krok ' + n + ' z ' + steps.length + ' · ' + st.getAttribute('data-title');
    clearError();
    render();
    try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); }
  }

  function clearError() { $$('.js-err').forEach(function (e) { e.remove(); }); }
  function error(msg) {
    clearError();
    var st = steps[current - 1];
    var box = document.createElement('div');
    box.className = 'flash err js-err';
    box.setAttribute('role', 'alert');
    box.textContent = msg;
    var nav = st.querySelector('.navrow') || st.querySelector('button[type=submit]');
    st.insertBefore(box, nav);
    box.scrollIntoView({ block: 'center', behavior: 'smooth' });
    return false;
  }

  function validate(n) {
    var ch = chosen();
    if (n === 1 && !ch.length) return error('Vyberte aspoň jednu položku a co s ní chcete udělat.');
    if (n === 2) {
      for (var k = 0; k < ch.length; k++) {
        var i = ch[k];
        if (i.act === 'vratit' && !$('input[name="reason[' + i.id + ']"]:checked')) return error('Vyberte důvod vrácení u „' + i.name + '“.');
        if (i.act === 'vymenit' && i.kind === 'jine') {
          if (!form.querySelector('[data-other="' + i.id + '"] .otherprod')) return error('U „' + i.name + '“ vložte odkaz na nové zboží a klikněte na Načíst.');
          if (!i.picked) return error('U „' + i.name + '“ vyberte velikost nového zboží.');
        } else if (i.act === 'vymenit') { if (!sizeVal(i.id)) return error('Vyberte velikost pro výměnu u „' + i.name + '“.'); }
        if (i.act === 'reklamovat') { var d = form.querySelector('[name="defect[' + i.id + ']"]'); if (!d || d.value.trim().length < 5) return error('Popište vadu u „' + i.name + '“.'); }
      }
    }
    if (n === 3 && !ship()) return error('Vyberte, jak nám zboží pošlete.');
    if (n === 4 && calc().owes && !$('input[name="pay_method"]:checked')) return error('Vyberte, jak doplatíte rozdíl v ceně.');
    if (n === 4 && calc().balance > 0.005) {
      var a = ($('#account') || { value: '' }).value.replace(/\s+/g, '').toUpperCase();
      if (!/^(\d{1,6}-)?\d{2,10}\/\d{4}$/.test(a) && !/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(a)) return error('Vyplňte číslo účtu ve tvaru 123456789/0800.');
    }
    return true;
  }

  form.addEventListener('click', function (e) {
    var t = e.target.closest('button, [data-clear], [data-switch]');
    if (!t) return;
    if (t.hasAttribute('data-load')) { e.preventDefault(); loadProduct(t.getAttribute('data-load')); return; }
    if (t.hasAttribute('data-shop')) { e.preventDefault(); goShop(t); return; }
    if (t.hasAttribute('data-dosearch')) { e.preventDefault(); searchShop(t.getAttribute('data-dosearch')); return; }
    if (t.hasAttribute('data-pick-url')) {
      e.preventDefault();
      var pid = t.getAttribute('data-pick-id'), u = $('#ou-' + pid);
      if (u) u.value = t.getAttribute('data-pick-url');
      $$('[data-results="' + pid + '"] .res').forEach(function (b) { b.classList.toggle('on', b === t); });
      loadProduct(pid);
      return;
    }
    if (t.hasAttribute('data-kindswitch')) {
      e.preventDefault();
      var kr = $('input[name="swap_kind[' + t.getAttribute('data-kindswitch') + ']"][value="jine"]');
      if (kr) { kr.checked = true; render(); var u = $('#ou-' + t.getAttribute('data-kindswitch')); if (u) u.focus(); }
      return;
    }
    if (t.hasAttribute('data-next')) { e.preventDefault(); if (validate(current)) show(Math.min(steps.length, current + 1)); }
    else if (t.hasAttribute('data-prev')) { e.preventDefault(); show(Math.max(1, current - 1)); }
    else if (t.hasAttribute('data-clear')) {
      e.preventDefault();
      $$('input[name="act[' + t.getAttribute('data-clear') + ']"]').forEach(function (r) { r.checked = false; });
      render();
    } else if (t.hasAttribute('data-switch')) {
      e.preventDefault();
      var r = $('input[name="act[' + t.getAttribute('data-switch') + ']"][value="vymenit"]');
      if (r && !r.disabled) { r.checked = true; render(); }
    }
  });
  form.addEventListener('change', function (e) {
    if (e.target.matches('[data-photos]')) {
      var box = form.querySelector('[data-thumbs="' + e.target.getAttribute('data-photos') + '"]');
      if (box) {
        box.innerHTML = '';
        Array.prototype.slice.call(e.target.files, 0, 6).forEach(function (f) {
          if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { var s = document.createElement('span'); s.className = 'pill'; s.textContent = f.name; box.appendChild(s); return; }
          var img = document.createElement('img'); img.alt = 'Náhled fotky'; img.src = URL.createObjectURL(f); box.appendChild(img);
        });
      }
    }
    render();
  });
  form.addEventListener('input', function () { syncMoney(); });
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('input[name^="other_url"]')) { e.preventDefault(); loadProduct(e.target.id.replace('ou-', '')); }
    if (e.key === 'Enter' && e.target.matches('[data-search]')) { e.preventDefault(); searchShop(e.target.getAttribute('data-search')); }
  });

  // výměna za jiné zboží: výběr přímo na e-shopu (uloží rozpracovaný formulář a otevře e-shop se žlutým pruhem)
  function goShop(btn) {
    var fd = new FormData(form);
    fd.delete && Array.prototype.slice.call(form.querySelectorAll('input[type=file]')).forEach(function (f) { fd.delete(f.name); });
    var info = { i: parseInt(btn.getAttribute('data-shop'), 10), n: btn.getAttribute('data-name'), c: parseFloat(btn.getAttribute('data-credit')) || 0 };
    var b64 = btoa(unescape(encodeURIComponent(JSON.stringify(info)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    btn.disabled = true; btn.textContent = 'Otevírám e-shop…';
    var go = function () { location.href = form.getAttribute('data-shop-url') + '#vratka=' + b64; };
    fetch(form.getAttribute('data-draft-url'), { method: 'POST', body: fd, credentials: 'same-origin' }).then(go, go);
  }

  // výměna za jiné zboží: hledání na e-shopu
  function searchShop(id) {
    var inp = $('#sq-' + id), box = form.querySelector('[data-results="' + id + '"]');
    if (!inp || !box) return;
    var q = inp.value.trim();
    if (q.length < 2) { box.innerHTML = '<div class="grey">Napište aspoň dvě písmena.</div>'; return; }
    box.innerHTML = '<span class="loading">Hledám na e-shopu…</span>';
    fetch(form.getAttribute('data-search-url') + '?id=' + encodeURIComponent(id) + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) { box.innerHTML = html; })
      .catch(function () { box.innerHTML = '<div class="flash err" style="margin:0">Hledání se nepovedlo. Zkuste to prosím znovu.</div>'; });
  }

  // výměna za jiné zboží: načtení zboží podle odkazu
  function loadProduct(id) {
    var inp = $('#ou-' + id), box = form.querySelector('[data-other="' + id + '"]');
    if (!inp || !box) return;
    if (!inp.value.trim()) { box.innerHTML = '<div class="flash err" style="margin:0">Vložte odkaz na zboží z 4kluciodkol.cz.</div>'; return; }
    box.innerHTML = '<span class="loading">Načítám zboží z e-shopu…</span>';
    fetch(form.getAttribute('data-product-url') + '?id=' + encodeURIComponent(id) + '&url=' + encodeURIComponent(inp.value.trim()), { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) { box.innerHTML = html; render(); try { box.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) {} })
      .catch(function () { box.innerHTML = '<div class="flash err" style="margin:0">Zboží se nepodařilo načíst. Zkuste to prosím znovu.</div>'; });
  }
  form.addEventListener('submit', function (e) {
    for (var n = 1; n <= 4; n++) { if (!validate(n)) { e.preventDefault(); if (current !== n) { show(n); validate(n); } return; } }
    if (!$('input[name="agree"]').checked) { e.preventDefault(); error('Potvrďte prosím souhlas s obchodními podmínkami.'); return; }
    var b = form.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Odesílám…';
  });

  show(parseInt(form.getAttribute('data-start'), 10) || 1);
})();
