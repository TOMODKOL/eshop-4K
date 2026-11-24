/* 4K – vlastní vyhledávání na 4kluciodkol.cz (zkušební verze 23. 11. 2026).
   Našeptávač pod polem „Zadejte hledaný výraz…“ a vlastní stránka výsledků /hledat/?q=…
   Index produktů (index.json vedle tohoto souboru) vzniká z výpisů kategorií e-shopu.
   Zapíná se jen pro toho, kdo otevře web s #hledani=1 (vypnutí #hledani=0; web otazník v adrese zahodí) – zákazníci zatím vidí původní hledání. */
(function () {
  'use strict';
  if (window.K4H) return;
  var ME = document.currentScript && document.currentScript.src || '';
  var BASE = ME.replace(/[^\/]*$/, '');
  // index přímo z GitHubu: obnoví se do 5 minut po nočním sestavení (jsDelivr si větev @main drží i půl dne)
  var INDEX = /cdn\.jsdelivr\.net\/gh\/TOMODKOL\/eshop-4K@main\//.test(BASE) ? 'https://raw.githubusercontent.com/TOMODKOL/eshop-4K/main/web/hledani/index.json' : BASE + 'index.json';
  var CDN = 'https://cdn.4kluciodkol.cz/images/0/';
  var K4H = window.K4H = { ready: null };

  /* ---------- text ---------- */
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim(); }
  function stem(t) {
    if (t.length < 5 || /\d/.test(t)) return t;
    return t.replace(/(ami|ach|ech|ich|ych|emi|ovy|ove|ovi|ou|em|y|a|e|i|o|u)$/, '');
  }
  var STOP = { a: 1, na: 1, do: 1, pro: 1, s: 1, se: 1, v: 1, ve: 1, z: 1, k: 1, i: 1, the: 1, and: 1 };
  // slova, která lidé píšou jinak než my (klíč i hodnota už normalizované, hodnoty se hledají místo klíče)
  var SYN = {
    fyzik: 'fizik', fizyk: 'fizik', casteli: 'castelli', kastelli: 'castelli', kasteli: 'castelli', canondale: 'cannondale', kanondale: 'cannondale',
    asos: 'assos', sportfull: 'sportful', maxis: 'maxxis', schimano: 'shimano', simano: 'shimano', sram: 'sram', poc: 'poc', vait: 'whyte', wajt: 'whyte',
    prilba: 'helma', prilby: 'helma', boty: 'tretry', obuv: 'tretry', tretra: 'tretry', jersey: 'dres', brejle: 'bryle', plast: 'plast', pneumatika: 'plast', pneu: 'plast', galuska: 'plast',
    bidon: 'lahev', flasa: 'lahev', flaska: 'lahev', kosik: 'kosik', drzak: 'kosik', ekolo: 'elektrokolo', ebike: 'elektrokolo', elektrokola: 'elektrokolo',
    kalhotky: 'kalhoty', bibshort: 'kratasy', bibshorts: 'kratasy', vlozka: 'kratasy', navlek: 'navleky', retez: 'retez', kazeta: 'kazeta', svetlo: 'svetlo', svetla: 'svetlo',
    hustilka: 'pumpa', pumpicka: 'pumpa', zamek: 'zamek', satky: 'satek', nakrcnik: 'satek', cepice: 'cepice', kulich: 'cepice',
    // ze statistik BSADMIN „bez výsledků“ (1. 1.–24. 11. 2026)
    majola: 'maloja', maloya: 'maloja', sedacka: 'sedlo', sedacky: 'sedlo', gabba: 'perfetto', gabra: 'perfetto', lahve: 'lahev', lahvicka: 'lahev', lahvi: 'lahev',
    triko: 'tricko', tricko: 'triko', kompresor: 'pumpa', manometr: 'pumpa', plastenka: 'nepromokava', plastenky: 'nepromokava', lady: 'w', zenske: 'damske', zensky: 'damsky',
    // české barvy → názvy barev výrobců (anglicky)
    zluta: 'yellow', zluty: 'yellow', zlute: 'yellow', ruzova: 'pink', ruzovy: 'pink', ruzove: 'pink', cerna: 'black', cerny: 'black', cerne: 'black',
    bila: 'white', bily: 'white', bile: 'white', modra: 'blue', modry: 'blue', modre: 'blue', cervena: 'red', cerveny: 'red', cervene: 'red',
    zelena: 'green', zeleny: 'green', zelene: 'green', seda: 'gray', sedy: 'gray', sede: 'gray', oranzova: 'orange', oranzovy: 'orange', oranzove: 'orange',
    fialova: 'purple', fialovy: 'purple', fialove: 'purple', hneda: 'brown', hnedy: 'brown', hnede: 'brown'
  };
  // dotazy, které nejsou zboží: místo „nic jsme nenašli“ nabídnout stránku
  var STRANKY = [
    { k: /reklam|vrac|vrat|vymen|odstoup|formular/, t: 'Vrácení, výměna a reklamace', u: 'https://client.4kluciodkol.cz/vratky' },
    { k: /reklam/, t: 'Reklamační podmínky', u: '/reklamacni-podminky/' },
    { k: /servis|cenik|oprav/, t: 'Servis a ceník', u: '/servis/' },
    { k: /kontakt|telefon|adresa|oteviraci|prodejn|kde jste/, t: 'Kontakt a otevírací doba', u: '/kontakt/' },
    { k: /obchodni podminky|doprav|postovn|platb/, t: 'Obchodní podmínky, doprava a platba', u: '/obchodni-podminky/' }
  ];
  function stranky(q) { var n = norm(q); return STRANKY.filter(function (x) { return x.k.test(n); }); }
  K4H.stranky = stranky;

  /* ---------- index ---------- */
  var D = null, VOC = null, VKEYS = null, KOLA = {}, GRP = {}, LAB = {};
  function load() {
    if (K4H.ready) return K4H.ready;
    return (K4H.ready = fetch(INDEX, { cache: 'no-cache' }).then(function (r) { return r.json(); }).then(function (d) {
      D = d; VOC = Object.create(null);
      function add(t, i, w) {
        if (!t || STOP[t]) return;
        var a = VOC[t] || (VOC[t] = {});
        if (!(a[i] >= w)) a[i] = w;
      }
      d.p.forEach(function (p, i) {
        var nm = norm(p[1]).split(' '), cat = d.c[p[6]] || ['', ''], br = norm(d.b[p[5]] || '');
        nm.forEach(function (t, k) {
          add(stem(t), i, k === 0 ? 3.4 : 3);   // první slovo = druh zboží (helma, dres, kolo…)
          if (k && nm[k - 1]) add(nm[k - 1] + t, i, 3);   // „super six“ i „supersix“
        });
        br.split(' ').forEach(function (t) { add(stem(t), i, 3); });
        if (br) add(br.replace(/ /g, ''), i, 3);
        var segs = cat[0].split(' > ').slice(1);
        segs.forEach(function (s, k) { norm(s).split(' ').forEach(function (t) { add(stem(t), i, k === segs.length - 1 ? 3 : 1.5); }); });
        if (p[7]) add(norm(p[7]).replace(/ /g, ''), i, 4);
        if (p[8]) add(String(p[8]), i, 4);
      });
      VKEYS = Object.keys(VOC);
      if (!d.p || d.p.length < 1000) throw new Error('málo produktů');
      // barevné varianty téhož modelu (p[9] = skupina) a jejich popisek = to, čím se názvy liší
      d.p.forEach(function (p, i) { if (p[9] != null) (GRP[p[9]] || (GRP[p[9]] = [])).push(i); });
      Object.keys(GRP).forEach(function (g) {
        var ids = GRP[g]; if (ids.length < 2) return;
        var t = ids.map(function (i) { return d.p[i][1].split(' '); }), n = 0;
        while (t.every(function (x) { return x[n] !== undefined && x[n].toLowerCase() === t[0][n].toLowerCase(); })) n++;
        // „iPhone 18 Pro - Ocean“ × „iPhone 18 Pro Max - Ocean“: když se některé liší modelem a jiné jen barvou, ukázat i model
        var dash = t.map(function (x) { return /^[-–]$/.test(x[n] || ''); });
        if (n > 0 && dash.some(Boolean) && !dash.every(Boolean)) n--;
        ids.forEach(function (i, k) { LAB[i] = t[k].slice(n).join(' ').replace(/^[-–\s]+/, '').replace(/\s+[-–]\s+/g, ' · ') || d.p[i][1]; });
      });
      d.c.forEach(function (c, ci) { if (/^Eshop > (Jízdní kola|Elektrokola)/.test(c[0])) KOLA[ci] = 1; });
      return d;
    })['catch'](function (e) { K4H.off = true; throw e; }));   // data nejdou načíst → vrátí se původní hledání BSSHOP
  }

  function lev(a, b, max) {   // Damerau–Levenshtein s ořezem
    if (Math.abs(a.length - b.length) > max) return max + 1;
    var p2 = null, p = [], c, i, j;
    for (j = 0; j <= b.length; j++) p[j] = j;
    for (i = 1; i <= a.length; i++) {
      c = [i]; var lo = i;
      for (j = 1; j <= b.length; j++) {
        var x = Math.min(p[j] + 1, c[j - 1] + 1, p[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        if (p2 && i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) x = Math.min(x, p2[j - 2] + 1);
        c[j] = x; if (x < lo) lo = x;
      }
      if (lo > max) return max + 1;
      p2 = p; p = c;
    }
    return p[b.length];
  }

  // pro jedno slovo dotazu: { index produktu: skóre }
  function matchTerm(q, last) {
    var out = {}, s = stem(q);
    function take(t, qual) {
      var a = VOC[t]; if (!a) return;
      for (var i in a) { var v = a[i] * qual; if (!(out[i] >= v)) out[i] = v; }
    }
    take(s, 1); if (s !== q) take(q, 1);
    if (SYN[q]) take(stem(SYN[q]), 0.95);
    var minPref = last ? 1 : 3;
    if (s.length >= minPref) {
      for (var k = 0; k < VKEYS.length; k++) {
        var t = VKEYS[k];
        if (t.length > s.length && t.lastIndexOf(s, 0) === 0) take(t, s.length >= 3 ? 0.9 : 0.6);
      }
    }
    if (s.length >= 5 && Object.keys(out).length < 3) {
      var max = s.length >= 7 ? 2 : 1;
      for (var m = 0; m < VKEYS.length; m++) {
        var u = VKEYS[m];
        if (u[0] !== s[0] && max === 1) continue;
        var dd = lev(s, u.length > s.length + 2 ? u.slice(0, s.length + 1) : u, max);
        if (dd <= max) take(u, dd === 1 ? 0.7 : 0.5);
      }
    }
    return out;
  }

  function search(query) {
    var raw = norm(query).split(' ').filter(function (t) { return t && !STOP[t]; });
    if (!raw.length) return { items: [], ignored: [] };
    // spojit sousední slova, když spojení existuje („super six“ → „supersix“)
    var terms = [];
    for (var i = 0; i < raw.length; i++) {
      if (i + 1 < raw.length && VOC[stem(raw[i] + raw[i + 1])]) { terms.push(raw[i] + raw[i + 1]); i++; }
      else terms.push(raw[i]);
    }
    var sets = [], ignored = [];
    terms.forEach(function (t, k) {
      var m = matchTerm(t, k === terms.length - 1);
      if (Object.keys(m).length) sets.push(m); else ignored.push(t);
    });
    if (!sets.length) return { items: [], ignored: ignored };
    var res = [], all = {}, relaxed = false;
    sets.forEach(function (m) { for (var x in m) all[x] = 1; });
    // všechna slova musí sedět; když nesedí nic, stačí všechna kromě jednoho („dámský dres krátký rukáv“)
    for (var pass = 0; pass < 2 && !res.length; pass++) {
    if (pass === 1) { if (sets.length < 2) break; relaxed = true; }
    for (var id in all) {
      var sc = 0, miss = 0;
      for (var j = 0; j < sets.length; j++) { var v = sets[j][id]; if (v == null) miss++; else sc += v; }
      if (miss > pass) continue;
      var p = D.p[id];
      sc *= p[4] === 1 ? 1.15 : p[4] === 2 ? 1.05 : 0.8;
      if (KOLA[p[6]]) sc *= 1.25;   // kola jsou hlavní sortiment – při shodě dopředu
      res.push([+id, sc]);
    }
    }
    res.sort(function (a, b) { return b[1] - a[1] || (D.p[b[0]][4] === 1) - (D.p[a[0]][4] === 1) || D.p[b[0]][3] - D.p[a[0]][3]; });
    var seen = {}, items = [];
    res.forEach(function (r) { var g = D.p[r[0]][9]; if (g == null || GRP[g].length < 2) items.push(r[0]); else if (!seen[g]) { seen[g] = 1; items.push(r[0]); } });
    return { items: items, ignored: ignored, terms: terms, relaxed: relaxed };
  }
  K4H.search = function (q) { return load().then(function () { return search(q); }); };

  /* ---------- pomocné pro zobrazení ---------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function cena(n) { return n ? String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč' : ''; }
  function img(p, size) { return p[2] ? CDN + p[2].replace('|', '/' + size + '/') : ''; }
  var DOST = ['Na objednávku', 'Skladem', 'Skladem u dodavatele'];   // web v datech nerozliší „u dodavatele“ od „není“
  function skupina(i) { var g = D.p[i][9]; return g != null && GRP[g] && GRP[g].length > 1 ? GRP[g].slice().sort(function (a, b) { return (LAB[a] || '').localeCompare(LAB[b] || '', 'cs'); }) : []; }
  function varianty(i) { return skupina(i).filter(function (j) { return j !== i; }); }
  function nVar(n) { return n + ' ' + (n === 1 ? 'další varianta' : n < 5 ? 'další varianty' : 'dalších variant'); }
  // dlaždice variant: malá fotka + to, čím se varianta liší (barva, model); aktuální zvýrazněná
  function dlazdice(ids, cur) {
    return '<div class="k4h-vt">' + ids.map(function (j) {
      var p = D.p[j];
      return '<a href="' + esc(p[0]) + '" title="' + esc(p[1]) + '"' + (j === cur ? ' class="k4h-cur"' : '') + '><span class="k4h-vi" style="background-image:url(\'' + esc(img(p, 0)) + '\')"></span><span class="k4h-vl">' + esc(LAB[j] || p[1]) + '</span>' +
        (p[4] === 1 ? '' : '<span class="k4h-vn">' + DOST[p[4]] + '</span>') + '</a>';
    }).join('') + '</div>';
  }
  function vKat(i, path) { var c = D.c[D.p[i][6]]; return !!c && (c[0] === path || c[0].lastIndexOf(path + ' > ', 0) === 0); }
  function catName(ci) { var c = D.c[ci]; return c ? c[0].split(' > ').slice(1).join(' › ') : ''; }

  function categories(items, q) {
    var cnt = {}, nq = norm(q).split(' ').map(stem);
    items.slice(0, 400).forEach(function (i) { var c = D.p[i][6]; cnt[c] = (cnt[c] || 0) + 1; });
    // kategorie, jejichž název odpovídá dotazu (např. „gravel“ → Jízdní kola › Gravel)
    D.c.forEach(function (c, ci) {
      if (!c[1]) return;
      var last = norm(c[0].split(' > ').pop()).split(' ').map(stem);
      if (nq.every(function (t) { return last.some(function (x) { return x.lastIndexOf(t, 0) === 0; }); })) cnt[ci] = (cnt[ci] || 0) + 1000;
    });
    return Object.keys(cnt).filter(function (c) { return D.c[c] && D.c[c][1]; })
      .sort(function (a, b) { return cnt[b] - cnt[a] || D.c[a][0].length - D.c[b][0].length; }).slice(0, 5);
  }
  function brands(items) {
    var cnt = {};
    items.slice(0, 300).forEach(function (i) { var b = D.p[i][5]; if (D.b[b]) cnt[b] = (cnt[b] || 0) + 1; });
    return Object.keys(cnt).sort(function (a, b) { return cnt[b] - cnt[a]; });
  }

  /* ---------- styl ---------- */
  var css = [
    '.k4h-drop{position:absolute;z-index:10000;background:#fff;color:#231F20;border:1px solid #e6e6e3;border-top:3px solid #ffc524;box-shadow:0 18px 40px rgba(0,0,0,.18);font-family:"GoodSans-Regular",Arial,sans-serif;font-size:15px;line-height:1.4;display:grid;grid-template-columns:240px 1fr;max-height:calc(100vh - 160px);overflow:auto;box-sizing:border-box}',
    '.k4h-drop[hidden]{display:none}',
    '.k4h-side{background:#f4f4f2;padding:18px 18px 20px}',
    '.k4h-main{padding:18px 20px 20px;min-width:0}',
    '.k4h-h{font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:#6a6a66;margin:0 0 8px}',
    '.k4h-side .k4h-h+*{margin-bottom:18px}',
    '.k4h-cats a{display:block;padding:5px 0;color:#231F20;text-decoration:none}',
    '.k4h-cats a:hover,.k4h-cats a.k4h-on{text-decoration:underline}',
    '.k4h-cats small{display:block;color:#6a6a66;font-size:12px}',
    '.k4h-brands{display:flex;flex-wrap:wrap;gap:6px}',
    '.k4h-brands a{border:1px solid #d6d6d2;border-radius:3px;padding:3px 9px;font-size:13px;color:#231F20;text-decoration:none;background:#fff}',
    '.k4h-brands a:hover{border-color:#231F20}',
    '.k4h-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 16px}',
    '.k4h-it{display:grid;grid-template-columns:64px 1fr;gap:12px;align-items:center;padding:6px;border-radius:3px;color:#231F20;text-decoration:none}',
    '.k4h-it:hover,.k4h-it.k4h-on{background:#f4f4f2}',
    '.k4h-img{width:64px;height:64px;background:#fff center/contain no-repeat;border-radius:3px}',
    '.k4h-n{display:block;font-size:14px;line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}',
    '.k4h-p{display:block;margin-top:3px;font-family:"FKScreamer-Upright",Impact,sans-serif;font-size:19px;letter-spacing:.3px}',
    '.k4h-s{font-family:"GoodSans-Regular",Arial,sans-serif;font-size:12px;margin-left:8px;letter-spacing:0}',
    '.k4h-s1{color:#1f8a3b}.k4h-s2{color:#6a6a66}.k4h-s0{color:#6a6a66}',
    '.k4h-all{display:inline-block;margin-top:14px;background:#231F20;color:#fff!important;text-decoration:none;padding:10px 18px;border-radius:3px;font-size:14px}',
    '.k4h-all:hover{background:#000}',
    '.k4h-none{padding:6px 0;color:#6a6a66}',
    '@media (max-width:767px){.k4h-drop{grid-template-columns:1fr;max-height:calc(100vh - 120px)}.k4h-side{order:2}.k4h-list{grid-template-columns:1fr}}',
    /* stránka výsledků */
    '.k4h-page{font-family:"GoodSans-Regular",Arial,sans-serif;color:#231F20;margin:8px 0 40px}',
    '.k4h-bar{display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center;margin:0 0 18px}',
    '.k4h-count{font-size:15px;color:#6a6a66;margin-right:auto}',
    '.k4h-count b{color:#231F20}',
    '.k4h-bar select,.k4h-bar label{font:inherit;font-size:14px}',
    '.k4h-bar select{padding:7px 10px;border:1px solid #d6d6d2;border-radius:3px;background:#fff}',
    '.k4h-facets{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 22px}',
    '.k4h-chip{border:1px solid #d6d6d2;border-radius:20px;padding:6px 14px;font-size:14px;background:#fff;color:#231F20;cursor:pointer}',
    '.k4h-chip.k4h-on{background:#231F20;border-color:#231F20;color:#fff}',
    '.k4h-chip small{color:inherit;opacity:.6;margin-left:4px}',
    '.k4h-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:24px 20px}',
    '.k4h-card{display:flex;flex-direction:column;color:#231F20}',
    '.k4h-card>a{display:flex;flex-direction:column;color:#231F20;text-decoration:none}',
    '.k4h-vars{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px}',
    '.k4h-vars a,.k4h-vars span{font-size:12px;line-height:1.3;border:1px solid #d6d6d2;border-radius:3px;padding:2px 7px;color:#444;text-decoration:none;background:#fff}',
    '.k4h-vars a:hover{border-color:#231F20;color:#231F20}',
    '.k4h-var{display:block;font-size:12px;color:#6a6a66;margin-top:2px}',
    '.k4h-card .k4h-img{width:100%;height:auto;aspect-ratio:1/1;margin-bottom:10px}',
    '.k4h-card:hover .k4h-n{text-decoration:underline}',
    '.k4h-card .k4h-p{font-size:24px;margin-top:6px}',
    '.k4h-card .k4h-s{display:block;margin:2px 0 0}',
    '.k4h-more{display:block;margin:28px auto 0;background:#231F20;color:#fff;border:0;padding:12px 26px;border-radius:3px;font:inherit;cursor:pointer}',
    '.k4h-kat{display:flex;flex-wrap:wrap;gap:6px 18px;align-items:baseline;margin:0 0 20px;font-size:15px}',
    '.k4h-kat a{color:#231F20}',
    '.k4h-dalsi{text-align:center;margin-top:28px}',
    '.k4h-vt{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px}',
    '.k4h-vt a{display:flex;flex-direction:column;align-items:center;text-align:center;gap:4px;padding:6px 4px;border:1px solid #e6e6e3;border-radius:3px;color:#231F20;text-decoration:none;background:#fff;font-size:12px;line-height:1.25}',
    '.k4h-vt a:hover{border-color:#231F20}',
    '.k4h-vt a.k4h-cur{border:2px solid #231F20;padding:5px 3px}',
    '.k4h-vi{width:64px;height:64px;background:#fff center/contain no-repeat}',
    '.k4h-vn{color:#6a6a66;font-size:11px}',
    '.k4h-vbtn{display:inline-block;margin:0 0 4px 82px;padding:2px 0;border:0;background:none;font:inherit;font-size:12px;color:#231F20;text-decoration:underline;cursor:pointer}',
    '.k4h-itw .k4h-vt{margin:4px 6px 10px 82px;grid-template-columns:repeat(auto-fill,minmax(84px,1fr))}',
    '.k4h-card .k4h-vbtn{margin:8px 0 0;text-align:left}',
    '.k4h-card .k4h-vt{margin-top:8px;grid-template-columns:repeat(auto-fill,minmax(80px,1fr))}',
    '.k4h-detail{margin:10px 0 18px}',
    '.k4h-detail .k4h-vt{grid-template-columns:repeat(auto-fill,minmax(92px,1fr))}',
    '.k4h-tip{background:#f4f4f2;padding:14px 18px;margin:0 0 20px;font-size:14px}',
    '#SearchMaster.k4h-vlastni>:not(h1):not(.k4h-page){display:none!important}'
  ].join('\n');
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  /* ---------- našeptávač ---------- */
  var inp = document.querySelector('#SimpleSearch input.query');
  var drop = document.createElement('div'); drop.className = 'k4h-drop'; drop.hidden = true; drop.setAttribute('role', 'listbox');
  document.body.appendChild(drop);
  var seq = 0, sel = -1, timer = null;

  function place() {
    var bar = inp.closest('.dcHeader') || inp.parentNode, r = inp.getBoundingClientRect(), b = bar.getBoundingClientRect();
    var w = Math.min(980, window.innerWidth - 24);
    var left = Math.max(12, Math.min(r.right - w, window.innerWidth - w - 12));
    if (window.innerWidth < 768) left = 12;
    drop.style.width = w + 'px';
    drop.style.left = (left + window.scrollX) + 'px';
    drop.style.top = (Math.max(r.bottom, b.bottom) + window.scrollY) + 'px';
  }
  function close() { drop.hidden = true; sel = -1; }
  function goSearch(q) { location.href = '/hledat/?q=' + encodeURIComponent(q); }

  function renderDrop(q, r) {
    var it = r.items, html = '';
    var cs = categories(it, q), bs = brands(it).slice(0, 8);
    html += '<div class="k4h-side">';
    var st = stranky(q);
    if (st.length) html += '<div class="k4h-h">Stránky</div><div class="k4h-cats">' + st.map(function (x) { return '<a href="' + esc(x.u) + '">' + esc(x.t) + ' →</a>'; }).join('') + '</div>';
    if (cs.length) html += '<div class="k4h-h">Kategorie</div><div class="k4h-cats">' + cs.map(function (c) {
      var parts = D.c[c][0].split(' > ').slice(1);
      // kategorie s nalezeným zbožím → výsledky hledání jen v ní; kategorie nalezená jen podle názvu → rovnou kategorie
      var n = it.filter(function (i) { return vKat(i, D.c[c][0]); }).length;
      var href = n ? '/hledat/?q=' + encodeURIComponent(q) + '#kat=' + encodeURIComponent(D.c[c][1]) : D.c[c][1];
      return '<a href="' + esc(href) + '">' + esc(parts[parts.length - 1]) + (parts.length > 1 ? '<small>' + esc(parts.slice(0, -1).join(' › ')) + '</small>' : '') + '</a>';
    }).join('') + '</div>';
    if (bs.length) html += '<div class="k4h-h">Značky</div><div class="k4h-brands">' + bs.map(function (b) {
      return '<a href="/hledat/?q=' + encodeURIComponent(q) + '#znacka=' + encodeURIComponent(D.b[b]) + '">' + esc(D.b[b]) + '</a>';
    }).join('') + '</div>';
    if (!cs.length && !bs.length && !st.length) html += '<div class="k4h-none">Nic dalšího k dotazu.</div>';
    html += '</div><div class="k4h-main"><div class="k4h-h">Produkty' + (it.length ? ' (' + it.length + ')' : '') + '</div>';
    if (it.length) {
      html += '<div class="k4h-list">' + it.slice(0, 8).map(function (i) {
        var p = D.p[i], v = varianty(i);
        return '<div class="k4h-itw"><a class="k4h-it" href="' + esc(p[0]) + '"><span class="k4h-img" style="background-image:url(\'' + esc(img(p, 0)) + '\')"></span><span><span class="k4h-n">' + esc(p[1]) + '</span><span class="k4h-p">' + cena(p[3]) +
          '<span class="k4h-s k4h-s' + p[4] + '">' + DOST[p[4]] + '</span></span></span></a>' +
          (v.length ? '<button type="button" class="k4h-vbtn" data-i="' + i + '">+ ' + nVar(v.length) + '</button>' : '') + '</div>';
      }).join('') + '</div><a class="k4h-all" href="/hledat/?q=' + encodeURIComponent(q) + '">Zobrazit všech ' + it.length + ' výsledků</a>';
    } else html += '<div class="k4h-none">K „' + esc(q) + '“ jsme nic nenašli. Zkuste jiné slovo, nebo nám zavolejte na 604 998 800.</div>';
    html += '</div>';
    drop.innerHTML = html; sel = -1; place(); drop.hidden = false;
    // „+ N dalších variant“ rozbalí všechny varianty přímo v nabídce
    [].forEach.call(drop.querySelectorAll('.k4h-vbtn'), function (b) {
      b.onmousedown = function (e) { e.preventDefault(); };   // pole hledání nesmí ztratit fokus
      b.onclick = function (e) {
        e.preventDefault(); e.stopPropagation();
        var w = b.parentNode, open = w.querySelector('.k4h-vt'), i = +b.getAttribute('data-i');
        if (open) { open.remove(); w.style.gridColumn = ''; b.textContent = '+ ' + nVar(varianty(i).length); return; }
        w.style.gridColumn = '1 / -1';
        w.insertAdjacentHTML('beforeend', dlazdice(skupina(i), i));
        b.textContent = 'Skrýt varianty';
      };
    });
  }

  function onInput() {
    var q = inp.value.trim(), my = ++seq;
    clearTimeout(timer);
    if (q.length < 2) { close(); return; }
    timer = setTimeout(function () {
      K4H.search(q).then(function (r) { if (my === seq && inp.value.trim() === q) renderDrop(q, r); });
    }, 90);
  }
  function move(d) {
    var a = drop.querySelectorAll('.k4h-it'); if (!a.length) return;
    if (sel >= 0 && a[sel]) a[sel].classList.remove('k4h-on');
    sel = (sel + d + a.length + 1) % (a.length + 1) - 1;
    if (sel >= 0) { a[sel].classList.add('k4h-on'); a[sel].scrollIntoView({ block: 'nearest' }); }
  }
  if (inp) {
    // BSSHOP má na poli vlastní obsluhu – naši spustíme dřív a jeho vypneme
    inp.addEventListener('input', function (e) { if (K4H.off) return; e.stopImmediatePropagation(); onInput(); }, true);
    inp.addEventListener('keyup', function (e) { if (K4H.off) return; e.stopImmediatePropagation(); }, true);
    inp.addEventListener('keydown', function (e) {
      if (K4H.off) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); e.stopImmediatePropagation(); if (drop.hidden) onInput(); else move(e.key === 'ArrowDown' ? 1 : -1); return; }
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'Enter') {
        e.preventDefault(); e.stopImmediatePropagation();
        var a = drop.querySelectorAll('.k4h-it');
        if (sel >= 0 && a[sel]) location.href = a[sel].href; else if (inp.value.trim()) goSearch(inp.value.trim());
      }
    }, true);
    inp.addEventListener('focus', function () { load(); if (inp.value.trim().length >= 2) onInput(); });
    var btn = inp.parentNode.querySelector('a.search');
    if (btn) btn.addEventListener('click', function (e) { if (!K4H.off && inp.value.trim()) { e.preventDefault(); e.stopImmediatePropagation(); goSearch(inp.value.trim()); } }, true);
    document.addEventListener('mousedown', function (e) { if (!drop.hidden && !drop.contains(e.target) && e.target !== inp) close(); });
    window.addEventListener('resize', function () { if (!drop.hidden) place(); });
  }

  /* ---------- stránka výsledků /hledat/ ---------- */
  var SM = document.getElementById('SearchMaster');
  if (SM && /^\/hledat\//.test(location.pathname)) {
    // web zahodí neznámé parametry za otazníkem → filtry jdou za mřížkou (#kat=…, #znacka=…)
    var P = new URLSearchParams(location.search), H = new URLSearchParams(location.hash.replace(/^#/, '')), Q = (P.get('q') || '').trim();
    H.forEach(function (v, k) { if (!P.has(k)) P.set(k, v); });
    if (inp && Q) inp.value = Q;
    if (Q) {
      var page = document.createElement('div'); page.className = 'k4h-page';
      page.innerHTML = '<div class="k4h-tip">Zkušební verze nového vyhledávání. Původní výsledky: <a href="#" id="k4h-puvodni">zobrazit</a>.</div><div class="k4h-bar"><span class="k4h-count">Hledám…</span></div>';
      var h1 = SM.querySelector('h1'); h1.textContent = 'Hledáte „' + Q + '“';
      h1.parentNode.insertBefore(page, h1.nextSibling);
      SM.classList.add('k4h-vlastni');
      page.querySelector('#k4h-puvodni').addEventListener('click', function (e) { e.preventDefault(); SM.classList.remove('k4h-vlastni'); page.remove(); h1.textContent = 'Hledat'; });
      var KAT = null, kurl = P.get('kat') || '';
      var state = { kat: kurl, cat: P.get('kategorie') || '', brand: P.get('znacka') || '', stock: P.get('skladem') === '1', sort: 'rel', shown: 48 };
      K4H.search(Q).then(function (r) {
        function top(ci) { var s = D.c[ci] ? D.c[ci][0].split(' > ') : []; return s[1] || ''; }
        if (state.kat) D.c.forEach(function (c) { if (c[1] === state.kat) KAT = c; });
        if (!KAT) state.kat = '';
        function draw() {
          var all = r.items;
          if (state.kat) all = all.filter(function (i) { return vKat(i, KAT[0]); });
          var base = all.filter(function (i) { var p = D.p[i]; return (!state.brand || D.b[p[5]] === state.brand) && (!state.stock || p[4] === 1); });
          var list = base.filter(function (i) { return !state.cat || top(D.p[i][6]) === state.cat; });
          if (state.sort === 'asc') list = list.slice().sort(function (a, b) { return D.p[a][3] - D.p[b][3]; });
          if (state.sort === 'desc') list = list.slice().sort(function (a, b) { return D.p[b][3] - D.p[a][3]; });
          var cc = {}; base.forEach(function (i) { var t = top(D.p[i][6]); if (t) cc[t] = (cc[t] || 0) + 1; });
          var bc = {}; all.forEach(function (i) { var b = D.b[D.p[i][5]]; if (b) bc[b] = (bc[b] || 0) + 1; });
          var h = '<div class="k4h-bar"><span class="k4h-count"><b>' + list.length + '</b> ' + (list.length === 1 ? 'produkt' : list.length >= 2 && list.length <= 4 ? 'produkty' : 'produktů') +
            (r.ignored.length ? ' · slovo „' + esc(r.ignored.join(', ')) + '“ jsme v názvech nenašli, hledáme bez něj' : '') + '</span>' +
            '<label><input type="checkbox" id="k4h-sk"' + (state.stock ? ' checked' : '') + '> Jen skladem</label>' +
            '<select id="k4h-sort" aria-label="Řazení"><option value="rel">Nejvíc odpovídá</option><option value="asc">Od nejlevnějšího</option><option value="desc">Od nejdražšího</option></select></div>';
          var stp = stranky(Q);
          if (stp.length) h += '<div class="k4h-kat">Hledáte stránku? ' + stp.map(function (x) { return '<a href="' + esc(x.u) + '">' + esc(x.t) + ' →</a>'; }).join(' ') + '</div>';
          if (r.relaxed && list.length) h += '<div class="k4h-kat">Přesnou shodu se všemi slovy jsme nenašli, ukazujeme nejbližší.</div>';
          if (state.kat) {
            var kn = KAT[0].split(' > ').pop();
            h += '<div class="k4h-kat">Jen v kategorii <b>' + esc(kn) + '</b> <a href="#" id="k4h-kat-x">hledat všude (' + r.items.length + ')</a> <a href="' + esc(KAT[1]) + '">celá kategorie ' + esc(kn) + ' →</a></div>';
          }
          var ck = Object.keys(cc).sort(function (a, b) { return cc[b] - cc[a]; });
          if (!state.kat && (ck.length > 1 || state.cat)) h += '<div class="k4h-facets"><button class="k4h-chip' + (state.cat ? '' : ' k4h-on') + '" data-cat="">Vše<small>' + base.length + '</small></button>' +
            ck.map(function (c) { return '<button class="k4h-chip' + (state.cat === c ? ' k4h-on' : '') + '" data-cat="' + esc(c) + '">' + esc(c) + '<small>' + cc[c] + '</small></button>'; }).join('') + '</div>';
          var bk = Object.keys(bc).sort(function (a, b) { return bc[b] - bc[a]; });
          if (bk.length > 1 || state.brand) h += '<div class="k4h-facets">' + bk.slice(0, 14).map(function (b) { return '<button class="k4h-chip' + (state.brand === b ? ' k4h-on' : '') + '" data-brand="' + esc(b) + '">' + esc(b) + '<small>' + bc[b] + '</small></button>'; }).join('') + '</div>';
          if (!list.length) h += '<p class="k4h-none">Nic jsme nenašli. Zkuste jiné slovo, nebo nám zavolejte na 604 998 800 – poradíme.</p>';
          h += '<div class="k4h-grid">' + list.slice(0, state.shown).map(function (i) {
            var p = D.p[i], v = varianty(i);
            return '<div class="k4h-card"><a href="' + esc(p[0]) + '"><span class="k4h-img" style="background-image:url(\'' + esc(img(p, 1)) + '\')"></span><span class="k4h-n">' + esc(p[1]) + '</span><span class="k4h-p">' + cena(p[3]) + '</span><span class="k4h-s k4h-s' + p[4] + '">' + DOST[p[4]] + '</span></a>' +
              (v.length ? '<button type="button" class="k4h-vbtn" data-i="' + i + '">+ ' + nVar(v.length) + '</button>' : '') + '</div>';
          }).join('') + '</div>';
          if (list.length > state.shown) h += '<button class="k4h-more" id="k4h-more">Zobrazit další (' + (list.length - state.shown) + ')</button>';
          if (state.kat) h += '<div class="k4h-dalsi"><a class="k4h-all" href="' + esc(KAT[1]) + '">Další zboží z kategorie ' + esc(KAT[0].split(' > ').pop()) + ' →</a></div>';
          var keep = page.querySelector('.k4h-tip').outerHTML;
          page.innerHTML = keep + h;
          page.querySelector('#k4h-puvodni').addEventListener('click', function (e) { e.preventDefault(); SM.classList.remove('k4h-vlastni'); page.remove(); h1.textContent = 'Hledat'; });
          page.querySelector('#k4h-sort').value = state.sort;
          page.querySelector('#k4h-sort').onchange = function () { state.sort = this.value; draw(); };
          page.querySelector('#k4h-sk').onchange = function () { state.stock = this.checked; draw(); };
          [].forEach.call(page.querySelectorAll('.k4h-vbtn'), function (b) {
            b.onclick = function () {
              var c = b.parentNode, open = c.querySelector('.k4h-vt'), i = +b.getAttribute('data-i');
              if (open) { open.remove(); b.textContent = '+ ' + nVar(varianty(i).length); return; }
              b.insertAdjacentHTML('afterend', dlazdice(skupina(i), i)); b.textContent = 'Skrýt varianty';
            };
          });
          var kx = page.querySelector('#k4h-kat-x');
          if (kx) kx.onclick = function (e) { e.preventDefault(); state.kat = ''; state.shown = 48; try { history.replaceState(null, '', location.pathname + location.search); } catch (er) {} draw(); };
          var more = page.querySelector('#k4h-more'); if (more) more.onclick = function () { state.shown += 48; draw(); };
          [].forEach.call(page.querySelectorAll('[data-cat]'), function (b) { b.onclick = function () { state.cat = b.getAttribute('data-cat'); state.shown = 48; draw(); }; });
          [].forEach.call(page.querySelectorAll('[data-brand]'), function (b) { b.onclick = function () { var v = b.getAttribute('data-brand'); state.brand = state.brand === v ? '' : v; state.cat = ''; state.shown = 48; draw(); }; });
        }
        draw();
      })['catch'](function () { SM.classList.remove('k4h-vlastni'); page.remove(); h1.textContent = 'Hledat'; });
    }
  }
  /* ---------- detail produktu: ostatní varianty téhož modelu (samostatné karty) ---------- */
  if (/-p\d+\/?$/.test(location.pathname)) {
    load().then(function () {
      var path = location.pathname.replace(/\/?$/, '/'), me = -1;
      for (var i = 0; i < D.p.length; i++) if (D.p[i][0] === path) { me = i; break; }
      var ids = me >= 0 ? skupina(me) : [];
      var h1 = document.querySelector('h1');
      if (ids.length < 2 || !h1 || document.querySelector('.k4h-detail')) return;
      var box = document.createElement('div'); box.className = 'k4h-detail';
      box.innerHTML = '<div class="k4h-h">Varianty (' + ids.length + ')</div>' + dlazdice(ids, me);
      // detail BSSHOP je mřížka s pevnými řádky → blok dáme do panelu s cenou (pravý sloupec pod názvem)
      var t = [].filter.call(document.querySelectorAll('.ProductDetailTableView'), function (e) { return e.querySelector('.price') && getComputedStyle(e).display === 'flex'; })[0];
      if (t) { box.style.cssText = 'flex:0 0 100%;width:100%;margin:14px 0 4px'; t.style.flexWrap = 'wrap'; t.appendChild(box); }
      else h1.insertAdjacentElement('afterend', box);
    })['catch'](function () {});
  }
})();
