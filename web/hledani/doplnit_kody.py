"""Kódy výrobce pro rozlišení modelů ve vyhledávání (všechny značky).
Výpisy kategorií kód výrobce nemají, je jen na detailu produktu (JSON-LD varianty "sku").
kody.json = {url: [sku všech variant]}; stahují se jen produkty, které v něm ještě nejsou.
python doplnit_kody.py produkty.json kody.json   (nebo index.json místo produkty.json)"""
import json, re, sys, time, urllib.request, os
from concurrent.futures import ThreadPoolExecutor

BASE = 'https://www.4kluciodkol.cz'
src, out = sys.argv[1], sys.argv[2]
d = json.load(open(src, encoding='utf-8'))
kody = json.load(open(out, encoding='utf-8')) if os.path.exists(out) else {}

urls = []
if 'products' in d:   # produkty.json z crawl.py
    for p in d['products']:
        br = p.get('brand') or ''
        br = (br.get('name') if isinstance(br, dict) else br) or ''
        if True:
            urls.append(re.sub(r'\?.*$', '', p.get('url', '')).replace(BASE, ''))
else:                 # index.json
    for p in d['p']:
        urls.append(p[0])
kody = {u: (v if isinstance(v, list) else [v]) for u, v in kody.items()}
chybi = sorted({u for u in urls if u and u not in kody})
print('produktů značek', len(set(urls)), 'chybí kód', len(chybi), file=sys.stderr)

def sku(u):
    for i in range(3):
        try:
            req = urllib.request.Request(BASE + u, headers={'User-Agent': 'Mozilla/5.0 4K-hledani-index'})
            h = urllib.request.urlopen(req, timeout=40).read().decode('utf-8', 'replace')
            s = []
            for m in re.finditer(r"<script type=['\"]application/ld\+json['\"]>(.*?)</script>", h, re.S):
                try: j = json.loads(m.group(1))
                except Exception: continue
                for x in (j if isinstance(j, list) else [j]):
                    if not isinstance(x, dict): continue
                    if x.get('hasVariant'):
                        s += [v.get('sku') for v in x['hasVariant'] if isinstance(v, dict) and v.get('sku')]
                    elif x.get('@type') == 'Product' and x.get('sku') and not s:
                        s.append(x['sku'])
                if s: break
            return u, list(dict.fromkeys(s))
        except Exception:
            time.sleep(3 + i * 5)
    return u, None
with ThreadPoolExecutor(4) as ex:
    for u, s in ex.map(sku, chybi):
        if s is not None:
            kody[u] = s
# produkty, které už na webu nejsou, vyhodit
jsou = set(urls)
kody = {u: s for u, s in kody.items() if u in jsou}
json.dump(kody, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=0, sort_keys=True)
print('kódů', len(kody), 'bez kódu', sum(1 for s in kody.values() if not s), file=sys.stderr)
