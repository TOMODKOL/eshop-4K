"""Stáhne produkty z výpisů kategorií e-shopu (JSON-LD) → produkty.json. Spouští GitHub Action každou noc."""
import re, json, sys, time, urllib.request, urllib.parse
from concurrent.futures import ThreadPoolExecutor

BASE = 'https://www.4kluciodkol.cz'
UA = {'User-Agent': 'Mozilla/5.0 4K-hledani-index'}

import hashlib, os
os.makedirs('cache', exist_ok=True)
CACHE = '--cache' in sys.argv
def get(url, tries=3):
    fn = 'cache/' + hashlib.md5(url.encode()).hexdigest()
    if CACHE and os.path.exists(fn):
        return open(fn, encoding='utf-8').read()
    h = _get(url, tries)
    if h:
        open(fn, 'w', encoding='utf-8').write(h)
    return h
def _get(url, tries=3):
    for i in range(tries):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=40) as r:
                return r.read().decode('utf-8', 'replace')
        except Exception as e:
            time.sleep(2 + i * 3)
    return ''

def parse(h):
    items, crumbs = [], []
    for m in re.finditer(r"<script type=['\"]application/ld\+json['\"]>(.*?)</script>", h, re.S):
        try:
            j = json.loads(m.group(1))
        except Exception:
            continue
        for x in (j if isinstance(j, list) else [j]):
            try:
                if x.get('@type') == 'ItemList':
                    items += [e['item'] for e in x.get('itemListElement', []) if isinstance(e, dict) and isinstance(e.get('item'), dict) and e['item'].get('@type') == 'Product']
                if x.get('@type') == 'BreadcrumbList':
                    crumbs = [e['item']['@id'] if isinstance(e.get('item'), dict) else e.get('item') for e in x['itemListElement']]
            except Exception:
                pass
    c = re.search(r'(\d[\d ]*)\s*produkt', re.sub(r'<[^>]+>', ' ', h.split('ProductsHost')[0] if 'ProductsHost' in h else h).replace('&nbsp;', ' '))
    return items, crumbs, int(re.sub(r'\s', '', c.group(1))) if c else 0

cats = re.findall(r'<loc>([^<]+)</loc>', get(BASE + '/sitemap-1029_categories.xml'))
info = {}
def first(u):
    h = get(u)
    it, cr, n = parse(h)
    return u, it, cr, n
with ThreadPoolExecutor(6) as ex:
    for u, it, cr, n in ex.map(first, cats):
        info[u] = {'n': n, 'crumbs': cr, 'items': it}
print('kategorie', len(info), file=sys.stderr)

prod = {}
def add(items):
    for p in items:
        prod[p.get('identifier') or p['url']] = p
for u, v in info.items():
    add(v['items'])

# kategorie k prolezení celé: má < 1000 a žádný předek s < 1000 (ten ji pokryje)
todo = []
for u, v in info.items():
    if v['n'] <= 24 or v['n'] >= 1000:
        continue
    anc = [a for a in v['crumbs'][:-1] if a in info]
    if any(0 < info[a]['n'] < 1000 for a in anc):
        continue
    todo += [f'{u}?f={f}' for f in range(24, v['n'], 24)]
# kategorie s >= 1000: aspoň 1000 od nejlevnějšího a 1000 od nejdražšího
for u, v in info.items():
    if v['n'] >= 1000:
        for s in ('Od nejlevnějšího', 'Od nejdražšího'):
            todo += [f'{u}?f={f}&sortOrder=' + urllib.parse.quote(s) for f in range(0, 1000, 24)] if False else []
print('stránek', len(todo), file=sys.stderr)
with ThreadPoolExecutor(6) as ex:
    for h in ex.map(get, todo):
        add(parse(h)[0])
print('produktů', len(prod), file=sys.stderr)
json.dump({'cats': {u: {'n': v['n'], 'crumbs': v['crumbs']} for u, v in info.items()}, 'products': list(prod.values())},
          open('produkty.json', 'w', encoding='utf-8'), ensure_ascii=False)
