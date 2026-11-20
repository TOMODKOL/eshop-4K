// Hledání objednávky přes odkaz v e-mailu + průchod bez JavaScriptu
const { chromium } = require('playwright');
const fs = require('fs');
const [base, maillog, out] = process.argv.slice(2);
(async () => {
  const b = await chromium.launch();
  const ok = (c, m) => { if (!c) { console.log('CHYBA: ' + m); process.exitCode = 1; } else console.log('ok: ' + m); };
  const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto(base + '/vratky');
  await p.screenshot({ path: out + '/m1-start.png', fullPage: true });
  await p.click('text=Nepamatuji si číslo objednávky');
  await p.fill('#em2', 'jana.novakova@email.cz');
  await p.click('form.findcard button.btn');
  ok((await p.textContent('body')).includes('Zkontrolujte'.toUpperCase()) || (await p.textContent('body')).includes('ZKONTROLUJTE'), 'stránka „zkontrolujte e-mail“');
  const log = fs.readFileSync(maillog, 'utf8');
  const m = log.match(/http:\/\/localhost:8080\/vratky\/moje\/[A-Za-z0-9_-]+/);
  ok(!!m, 'odkaz je v e-mailu');
  await p.goto(m[0]);
  await p.screenshot({ path: out + '/m2-objednavky.png', fullPage: true });
  ok((await p.$$('button.ordrow')).length === 2, 'vidí obě objednávky');
  await p.click('button.ordrow >> nth=0');
  ok(p.url().endsWith('/vratky/novy'), 'vybraná objednávka → formulář');
  await p.screenshot({ path: out + '/m3-form.png', fullPage: true });

  // bez JavaScriptu: všechny kroky na jedné stránce, odeslání funguje
  const ctx = await b.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 900 } });
  const q = await ctx.newPage();
  await q.goto(base + '/vratky');
  await q.fill('#em', 'jana.novakova@email.cz'); await q.fill('#ob', '260109001');
  await q.click('form.findcard button.btn');
  const id = await q.$eval('.item-card', c => c.dataset.item);
  await q.click(`label:has(input[name="act[${id}]"][value=vymenit])`);
  await q.selectOption(`#size-${id}`, 'L');
  await q.click('label:has(input[name="ship"][value=osobne])');
  await q.check('input[name=agree]');
  await q.click('button[type=submit]');
  ok(q.url().includes('/vratky/stav/'), 'bez JS: vratka odeslána (číslo faktury místo objednávky)');
  await q.screenshot({ path: out + '/n1-hotovo.png', fullPage: true });
  await b.close();
})();
