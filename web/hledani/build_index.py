"""Index pro vyhledávání 4K: z produkty.json (crawl výpisů kategorií) udělá kompaktní index.json.
python build_index.py produkty.json index.json
p = [url, název, obrázek "hash|soubor", cena, dostupnost 0/1/2, značka, kategorie, kód, EAN, skupina barev]
c = [cesta "Eshop > …", url kategorie], b = [značky]"""
import json, re, sys, unicodedata, datetime

src, out = sys.argv[1], sys.argv[2]
d = json.load(open(src, encoding='utf-8'))

def slug(s):
    s = unicodedata.normalize('NFD', s.lower())
    s = ''.join(ch for ch in s if unicodedata.category(ch) != 'Mn')
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')

def uslug(u):
    m = re.search(r'/([^/]+)-c\d+/?$', u)
    return m.group(1) if m else ''

# řetěz slugů kategorie -> url (bez BLACK FRIDAY a podobných akčních větví, když existuje normální)
chain2url = {}
for u, v in d['cats'].items():
    ch = tuple(uslug(x) for x in v['crumbs'])
    if ch and ch[-1]:
        if ch not in chain2url or 'black-friday' in chain2url[ch]:
            chain2url[ch] = u.replace('https://www.4kluciodkol.cz', '')

AV = {'InStock': 1, 'BackOrder': 2, 'PreOrder': 2, 'LimitedAvailability': 2, 'InStoreOnly': 1, 'OutOfStock': 0, 'Discontinued': 0, 'SoldOut': 0}
cats, cidx, brands, bidx, prods, seen = [], {}, [], {}, [], set()
for p in d['products']:
    url = re.sub(r'\?.*$', '', p.get('url', '')).replace('https://www.4kluciodkol.cz', '')
    if not url or url in seen:
        continue
    seen.add(url)
    path = p.get('category', '') or ''
    if path not in cidx:
        segs = path.split(' > ')
        cu = chain2url.get(tuple(slug(s) for s in segs), '')
        cidx[path] = len(cats); cats.append([path, cu])
    br = ((p.get('brand') or {}).get('name') or '').strip()
    if br and br.upper() == br and len(br) > 3:
        br = br.title() if br not in ('POC', 'SRAM', 'DT SWISS', 'KMC', 'CST') else br
    if br not in bidx:
        bidx[br] = len(brands); brands.append(br)
    off = p.get('offers') or {}
    if isinstance(off, list):
        off = off[0] if off else {}
    av = AV.get(str(off.get('availability', '')).rsplit('/', 1)[-1], 2)
    im = p.get('image') or ''
    if isinstance(im, list):
        im = im[0] if im else ''
    m = re.match(r'https://cdn\.4kluciodkol\.cz/images/0/([0-9a-f]+)/\d+/(.+)$', im)
    img = m.group(1) + '|' + m.group(2) if m else ''
    try:
        price = round(float(off.get('price') or 0))
    except Exception:
        price = 0
    prods.append([url, p.get('name', ''), img, price, av, bidx[br], cidx[path], p.get('sku') or '', p.get('gtin13') or ''])

# barevné varianty téhož modelu (samostatné karty na webu) → jedna skupina; ve výsledcích se ukáže jen jedna karta
def toks(n): return re.sub(r'\s+', ' ', n.strip()).split(' ')
def nodig(ts): return not any(re.search(r'\d', t) for t in ts)
SKUPINY_NE = ('Komponenty', 'Výživa', 'Dárkové')
# slova, která patří k názvu modelu (ne k barvě): „Cytal Lite“, „Cytal Carbon“, „SuperSix EVO 1 SL“ jsou jiné modely
MODEL = {'sl', 'lite', 'race', 'aero', 'ultra', 'mips', 'team', 'comp', 'hi-mod', 'himod', 'lab71', 'spherical',
         'women', "women's", 'wmn', 'w', "w's", 'lady', 'man', 'men', "men's", 'jr', 'junior', 'kids', 'xc', 'gt',
         'rs', 'rsx', 'se', 'cv', 'elite', 'expert', 'thermal', 'wind', 'rain', 'jacket', 'vest', 'tights', 'bibtight', 'bib',
         'shorts', 'jersey', 'tee', 'gloves', 'armwarmers', 'soks', 'socks', 'anorak', 'supercross',
         # skupina = jen barvy téhož výrobku: jiná velikost / provedení / model telefonu je jiný výrobek
         'pro', 'max', 'plus', 'mini', 'standard', 'air', 'medium', 'small', 'large', 'micro', 'long', 'short',
         'wide', 'narrow', 'regular', 'xs', 's', 'm', 'l', 'xl', 'xxl', 'uni', 'slim', 'stand', 'vent', 'vhb',
         'ultra', 'fz', 'hz', 'ls', 'ss', 'half', 'zip', 'light',
         # kola: jiné provedení = jiná výbava („Synapse Carbon 2“ × „… SmartSense“, „Topstone Carbon 1 AXS“ × „… Lefty AXS“,
         # „Topstone CAAD 3 Cues 1x“ × „… Flat Bar“, „Esecta S“ × „Esecta S EQ“)
         'lefty', 'eq', 'smartsense', 'bar',
         # jiný výrobek téže řady (26. 11.): „Resistance Enduro Glove“ × „… Adj Glove“, „Premio Glove“ × „Premio Evo Glove“,
         # „Flanders Warm“ × „… Neck“, „Core Jersey“ × „Core Lightweight Jersey“, „Winter Socks“ × „… EVO“, „Blinder Mini“ přední × zadní × Twinpack
         'adj', 'adjustable', 'evo', 'neck', 'lightweight', 'unisea', 'twinpack', 'přední', 'zadní'}
BARVA_I_MODEL = {'light', 'air', 'carbon'}   # „Light Black“, „Air Blue“: když za nimi jde další slovo, může to být barva
def slova_modelu(r):   # slova modelu/velikosti ve zbytku názvu (kromě „Light Black“ apod., kde jde o barvu)
    out = []
    for k, t in enumerate(r):
        t = t.lower().strip('-–/,')
        if t in BARVA_I_MODEL:
            nxt = r[k + 1].lower() if k + 1 < len(r) else ''
            if t == 'carbon' and len(r) < 3: continue          # samotné „Carbon“ = barva
            if t in ('light', 'air') and nxt and nxt not in MODEL and nxt not in ('-', '–'): continue
        if t in MODEL or t in BARVA_I_MODEL: out.append(t)
    return sorted(out)
def zaklad(n):   # „Peak Design Everyday Case - iPhone 14 Pro - Charcoal“ → vše před posledním „ - “
    return n.rsplit(' - ', 1)[0].lower() if ' - ' in n else None
# stejný název, jiná specifikace (jiný ročník kola) → nikdy do jedné skupiny; seznam v rozdelit.json
import os
ROZ = {}
_rf = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'rozdelit.json')
if os.path.exists(_rf):
    for model, skupiny in json.load(open(_rf, encoding='utf-8')).items():
        if model.startswith('_'): continue
        for si, kody in enumerate(skupiny):
            for k in kody: ROZ[k] = (model, si)
parent = list(range(len(prods)))
znacky = [{ROZ[p[7]]} if p[7] in ROZ else set() for p in prods]   # (model, skupina) v každé skupině
def find(i):
    while parent[i] != i:
        parent[i] = parent[parent[i]]; i = parent[i]
    return i
def lze_spojit(a, b):
    za, zb = znacky[find(a)], znacky[find(b)]
    return not any(m1 == m2 and s1 != s2 for m1, s1 in za for m2, s2 in zb)
def spoj(a, b):
    ra, rb = find(a), find(b)
    if ra == rb: return
    parent[ra] = rb; znacky[rb] |= znacky[ra]
buckets = {}
for i, p in enumerate(prods):
    if any(k in cats[p[6]][0] for k in SKUPINY_NE) or not brands[p[5]]: continue
    buckets.setdefault((p[6], p[5]), []).append(i)
for (ci, bi), ids in buckets.items():
    bl = brands[bi].lower()
    T = {i: toks(prods[i][1]) for i in ids}
    for a in range(len(ids)):
        for b in range(a + 1, len(ids)):
            x, y = T[ids[a]], T[ids[b]]
            bpos = next((k for k, t in enumerate(x) if t.lower() == bl or bl.startswith(t.lower())), None)
            if bpos is None: continue
            l = 0
            while l < min(len(x), len(y)) and x[l].lower() == y[l].lower(): l += 1
            ra, rb = x[l:], y[l:]
            mx = 5 if l >= bpos + 3 else 3   # dlouhé barvy („Hydrogen White/Uranium Black Matt“) jen u delšího názvu modelu
            if l >= bpos + 2 and 0 < len(ra) <= mx and 0 < len(rb) <= mx and nodig(ra) and nodig(rb) \
                    and slova_modelu(ra) == slova_modelu(rb) \
                    and zaklad(prods[ids[a]][1]) == zaklad(prods[ids[b]][1]) \
                    and lze_spojit(ids[a], ids[b]):
                spoj(ids[a], ids[b])
for i, p in enumerate(prods): p.append(find(i))
print('skupin s více barvami', sum(1 for i in range(len(prods)) if sum(1 for q in prods if q[9] == i) > 1))
json.dump({'v': datetime.date.today().isoformat(), 'c': cats, 'b': brands, 'p': prods}, open(out, 'w', encoding='utf-8'),
          ensure_ascii=False, separators=(',', ':'))
print('produktů', len(prods), 'kategorií', len(cats), 'bez url kategorie', sum(1 for c in cats if not c[1]), 'značek', len(brands))
