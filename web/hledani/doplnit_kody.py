"""Kódy výrobce pro rozlišení modelů ve vyhledávání (POC, Castelli, ASSOS).
Výpisy kategorií kód výrobce nemají, je jen na detailu produktu (JSON-LD varianty "sku").
kody.json = {url: první sku varianty}; stahují se jen produkty, které v něm ještě nejsou.
python doplnit_kody.py produkty.json kody.json   (nebo index.json místo produkty.json)"""
import json, re, sys, time, urllib.request, os
from concurrent.futures import ThreadPoolExecutor

ZNACKY = ('poc', 'castelli', 'assos')
BASE = 'https://www.4kluciodkol.cz'
src, out = sys.argv[1], sys.argv[2]
d = json.load(open(src, encoding='utf-8'))
kody = json.load(open(out, encoding='utf-8')) if os.path.exists(out) else {}

urls = []
if 'products' in d:   # produkty.json z crawl.py
    for p in d['products']:
        br = p.get('brand') or ''
        br = (br.get('name') if isinstance(br, dict) else br) or ''
        if br.strip().lower() in ZNACKY:
            urls.append(re.sub(r'\?.*$', '', p.get('url', '')).replace(BASE, ''))
else:                 # index.json
    for p in d['p']:
        if d['b'][p[5]].lower() in ZNACKY:
            urls.append(p[0])
chybi = sorted({u for u in urls if u and u not in kody})
print('produktů značek', len(set(urls)), 'chybí kód', len(chybi), file=sys.stderr)

def sku(u):
    for i in range(3):
        try:
            req = urllib.request.Request(BASE + u, headers={'User-Agent': 'Mozilla/5.0 4K-hledani-index'})
            h = urllib.request.urlopen(req, timeout=40).read().decode('utf-8', 'replace')
            m = re.search(r'"sku"\s*:\s*"([^"]+)"', h)
            return u, m.group(1) if m else ''
        except Exception:
            time.sleep(3 + i * 5)
    return u, None
with ThreadPoolExecutor(3) as ex:
    for u, s in ex.map(sku, chybi):
        if s is not None:
            kody[u] = s
# produkty, které už na webu nejsou, vyhodit
jsou = set(urls)
kody = {u: s for u, s in kody.items() if u in jsou}
json.dump(kody, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=0, sort_keys=True)
print('kódů', len(kody), 'bez kódu', sum(1 for s in kody.values() if not s), file=sys.stderr)
