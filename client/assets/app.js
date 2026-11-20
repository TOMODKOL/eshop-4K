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
  var current = 1;
  var LABEL = { vratit: 'Vrácení', vymenit: 'Výměna velikosti', reklamovat: 'Reklamace' };
  var nf = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 2 });
  function money(n) { return nf.format(n).replace(/\s/g, ' ') + ' Kč'; }
  function $(sel, root) { return (root || form).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || form).querySelectorAll(sel)); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  // ---------- data z formuláře ----------
  function items() {
    return $$('.item-card').map(function (card) {
      var id = card.getAttribute('data-item');
      var act = $('input[name="act[' + id + ']"]:checked', card);
      var qtyEl = form.querySelector('[name="qty[' + id + ']"]');
      return {
        id: id,
        name: card.getAttribute('data-name'),
        price: parseFloat(card.getAttribute('data-price')) || 0,
        act: act ? act.value : '',
        qty: qtyEl ? parseInt(qtyEl.value, 10) || 1 : 1
      };
    });
  }
  function chosen() { return items().filter(function (i) { return i.act; }); }
  function ship() { var s = $('input[name="ship"]:checked'); return s ? s.value : ''; }
  function calc() {
    var ch = chosen(), refund = 0;
    ch.forEach(function (i) { if (i.act === 'vratit') refund += i.price * i.qty; });
    var onlyReturn = ch.length > 0 && ch.every(function (i) { return i.act === 'vratit'; });
    return { ch: ch, refund: refund, onlyReturn: onlyReturn, postageApplies: onlyReturn && (ship() === 'zbox' || ship() === 'vm') };
  }
  function detailText(i) {
    if (i.act === 'vratit') { var r = $('input[name="reason[' + i.id + ']"]:checked'); return r ? r.value : ''; }
    if (i.act === 'vymenit') { var s = form.querySelector('[name="size_to[' + i.id + ']"]'); return s && s.value ? 'Za velikost ' + s.value : ''; }
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
      var tag = $('[data-tag]', d); if (tag) tag.textContent = LABEL[act] || '';
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
    var acc = $('[data-account]'); if (acc) acc.hidden = c.refund <= 0;

    // panel vpravo + souhrn
    var rows = c.ch.map(function (i) {
      var amount = i.act === 'vratit' ? money(i.price * i.qty) : '–';
      return { label: LABEL[i.act], name: i.name + (i.qty > 1 ? ' × ' + i.qty : ''), detail: detailText(i), amount: amount };
    });
    var shipEl = $('input[name="ship"]:checked');
    var shipName = shipEl ? shipEl.closest('label').querySelector('strong').textContent : '';
    var postTxt = ship() === 'vlastni' ? 'na vaše náklady' : (c.postageApplies ? (postage === null ? '− poštovné' : '− ' + money(postage)) : 'zdarma');
    var net = c.refund - (c.postageApplies && postage !== null ? postage : 0);

    var aside = $('[data-aside]');
    if (aside) {
      aside.innerHTML = rows.length ? rows.map(function (r) {
        return '<div class="row"><span><span class="kicker" style="font-size:10px">' + esc(r.label) + '</span><br>' + esc(r.name) + '</span><strong style="white-space:nowrap">' + r.amount + '</strong></div>';
      }).join('') + '<div class="row" style="border-top:1px solid var(--line2);padding-top:10px"><span>Doprava</span><strong class="' + (c.postageApplies || ship() === 'vlastni' ? '' : 'free') + '">' + postTxt + '</strong></div>' +
        '<div class="total" style="padding:14px"><strong style="font-size:14px">' + (c.refund > 0 ? 'Vrátíme vám' : 'Bez peněz') + '</strong><span class="v" style="font-size:28px">' + money(Math.max(0, net)) + '</span></div>'
        : '<span style="font-size:13px;color:var(--muted)">Zatím nic nevybráno.</span>';
    }
    var sum = $('[data-summary]');
    if (sum) {
      sum.innerHTML = rows.map(function (r) {
        return '<div class="sumrow"><span><span class="kicker" style="font-size:11px">' + esc(r.label) + '</span><br><strong>' + esc(r.name) + '</strong>' + (r.detail ? '<br><span style="color:var(--muted)">' + esc(r.detail) + '</span>' : '') + '</span><strong style="white-space:nowrap">' + r.amount + '</strong></div>';
      }).join('') + (shipName ? '<div class="sumrow"><span>Doprava: ' + esc(shipName) + '</span><strong class="' + (c.postageApplies || ship() === 'vlastni' ? '' : 'free') + '" style="white-space:nowrap">' + postTxt + '</strong></div>' : '');
    }
    var tot = $('[data-total]');
    if (tot) {
      tot.hidden = !c.ch.length;
      $('[data-total-label]').textContent = c.refund > 0 ? 'Vrátíme vám' : 'Vychází přesně';
      $('[data-total-value]').innerHTML = money(Math.max(0, net)) + (c.postageApplies && postage === null ? ' <span style="font-family:\'Good Sans\',Arial;font-size:14px">− poštovné</span>' : '');
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
        if (i.act === 'vymenit') { var s = form.querySelector('[name="size_to[' + i.id + ']"]'); if (!s || !s.value.trim()) return error('Vyberte velikost pro výměnu u „' + i.name + '“.'); }
        if (i.act === 'reklamovat') { var d = form.querySelector('[name="defect[' + i.id + ']"]'); if (!d || d.value.trim().length < 5) return error('Popište vadu u „' + i.name + '“.'); }
      }
    }
    if (n === 3 && !ship()) return error('Vyberte, jak nám zboží pošlete.');
    if (n === 4 && calc().refund > 0) {
      var a = ($('#account') || { value: '' }).value.replace(/\s+/g, '').toUpperCase();
      if (!/^(\d{1,6}-)?\d{2,10}\/\d{4}$/.test(a) && !/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(a)) return error('Vyplňte číslo účtu ve tvaru 123456789/0800.');
    }
    return true;
  }

  form.addEventListener('click', function (e) {
    var t = e.target.closest('button, [data-clear], [data-switch]');
    if (!t) return;
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
  form.addEventListener('submit', function (e) {
    for (var n = 1; n <= 4; n++) { if (!validate(n)) { e.preventDefault(); if (current !== n) { show(n); validate(n); } return; } }
    if (!$('input[name="agree"]').checked) { e.preventDefault(); error('Potvrďte prosím souhlas s obchodními podmínkami.'); return; }
    var b = form.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Odesílám…';
  });

  show(1);
})();
