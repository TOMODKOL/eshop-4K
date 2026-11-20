// Průchod celou vratkou v prohlížeči:  node tests/e2e.js http://localhost:8080 <obrázek> <složka pro snímky>
const { chromium } = require('playwright');
const [base, img, out] = process.argv.slice(2);
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1366, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  const shot = n => p.screenshot({ path: `${out}/${n}.png`, fullPage: true });
  const ok = (c, m) => { if (!c) { console.log('CHYBA: ' + m); process.exitCode = 1; } else console.log('ok: ' + m); };

  await p.goto(base + '/vratky');
  await p.fill('#em', 'jana.novakova@email.cz'); await p.fill('#ob', '0000');
  await p.click('form.findcard button.btn');
  ok((await p.textContent('.flash.err')).includes('nenašli'), 'špatné číslo → chyba');
  await p.fill('#ob', '8810 000 123');
  await p.click('form.findcard button.btn');
  ok(p.url().endsWith('/vratky/novy'), 'objednávka nalezena');
  await shot('e1-polozky');
  // krok 1
  const cards = await p.$$('.item-card');
  ok(cards.length === 3, 'doprava se nenabízí, 3 položky');
  await p.click('[data-next]:visible');
  ok(await p.isVisible('.js-err'), 'bez výběru nejde dál');
  const ids = await p.$$eval('.item-card', cs => cs.map(c => c.dataset.item));
  await p.click(`label:has(input[name="act[${ids[0]}]"][value=vymenit])`);
  await p.click(`label:has(input[name="act[${ids[1]}]"][value=reklamovat])`);
  await p.click(`label:has(input[name="act[${ids[2]}]"][value=vratit])`);
  await p.selectOption(`#qty-${ids[2]}`, '2');
  await shot('e2-vybrano');
  await p.click('section[data-step="1"] [data-next]');
  // krok 2
  await p.click('section[data-step="2"] [data-next]');
  ok(await p.isVisible('.js-err'), 'bez upřesnění nejde dál');
  await p.selectOption(`#size-${ids[0]}`, 'S');
  await p.fill(`#vada-${ids[1]}`, 'Praskla přezka na řemínku, zjištěno po týdnu.');
  await p.setInputFiles(`[data-photos="${ids[1]}"]`, [img]);
  await p.click(`label:has(input[name="reason[${ids[2]}]"][value="Nesedí velikost"])`);
  await shot('e3-upresneni');
  await p.click('section[data-step="2"] [data-next]');
  // krok 3
  await shot('e4-doprava');
  await p.click('section[data-step="3"] [data-next]');
  // krok 4
  await p.click('section[data-step="4"] [data-next]');
  ok(await p.isVisible('.js-err'), 'bez účtu nejde dál');
  await p.fill('#account', '123456789/0800');
  await p.click('section[data-step="4"] [data-next]');
  await shot('e5-souhrn');
  ok((await p.textContent('[data-total-value]')).includes('3'), 'souhrn ukazuje částku');
  await p.check('input[name=agree]');
  await p.click('button[type=submit]');
  await p.waitForURL(/\/vratky\/stav\//);
  await shot('e6-hotovo');
  ok((await p.textContent('body')).includes('V-20'), 'vratka vytvořena');
  const statusUrl = p.url();

  // mobil
  await p.setViewportSize({ width: 390, height: 844 });
  await p.goto(statusUrl); await shot('m-hotovo');

  // administrace – instalace prvního správce
  await p.setViewportSize({ width: 1366, height: 900 });
  await p.goto(base + '/admin');
  ok(p.url().includes('/admin/instalace'), 'bez uživatelů → instalace');
  await p.fill('#key', 'dev-sync-token'); await p.fill('#name', 'Tomáš (test)'); await p.fill('#email', 'test@4kluciodkol.cz'); await p.fill('#pass', 'heslo-pro-test-123');
  await p.click('button.btn');
  await p.fill('#email', 'test@4kluciodkol.cz'); await p.fill('#pass', 'heslo-pro-test-123');
  await p.click('button.btn');
  ok(p.url().endsWith('/admin/vratky'), 'přihlášení → přehled');
  await shot('a1-prehled');
  await p.click('a.vcard');
  await shot('a2-detail');
  ok(await p.isVisible('.photos img'), 'fotka vady v detailu');
  await p.selectOption('#status', 'na_ceste');
  await p.fill('#note', 'Vidíme, že balík je na cestě.');
  await p.click('form.card button.btn');
  ok((await p.textContent('.flash.ok')).includes('e-mail'), 'stav uložen a e-mail odeslán');
  await p.goto(statusUrl); await shot('e7-stav-zakaznik');
  ok((await p.textContent('body')).includes('balík je na cestě'), 'zákazník vidí poznámku');
  console.log('chyby JS: ' + JSON.stringify(errs));
  await b.close();
})();
