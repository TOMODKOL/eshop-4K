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
         'adj', 'adjustable', 'wf', 'evo', 'neck', 'lightweight', 'unisea', 'twinpack', 'přední', 'zadní'}
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
TYP = {'glove', 'gloves', 'jersey', 'socks', 'sock', 'bibshort', 'bibshorts', 'bib', 'shorts', 'short', 'jacket', 'vest',
       'gilet', 'tee', 'tights', 'bibtights', 'knickers', 'cap', 'beanie', 'neck', 'warmer', 'warmers', 'top', 'skinsuit',
       'helmet', 'shoe', 'shoes', 'overshoe', 'overshoes', 'shell', 'pants', 'hoodie', 'bag', 'case'}
def cast_modelu(r):   # zbytek názvu až po poslední slovo typu výrobku (např. „Adj Glove“); za ním je barva/velikost
    t = [x.lower().strip('-–/,') for x in r]
    k = max((i for i, x in enumerate(t) if x in TYP), default=-1)
    return t[:k + 1]
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
# ---------- kód výrobce (kody.json = {url: [sku variant]} z detailů produktů, doplnit_kody.py) ----------
# U každé značky se z dat zjistí, jestli a jak její kódy rozlišují model (bez barvy a velikosti):
#   POC PC303341815SML1 → PC30334 (barva 1815, velikost SML1) · Castelli 4523026-424-M → 4523026 · ASSOS P13.60.677.18.I → P13.60.677
# Vyzkouší se několik způsobů (oddělovače, useknutí konce, pevná délka) a porovnají se se skupinami podle názvu.
# Značka, jejíž kódy neodpovídají (naše interní čísla 00078046, kódy bez struktury), se řídí jen názvem.
KODY = {}
_kf = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'kody.json')
if os.path.exists(_kf):
    KODY = json.load(open(_kf, encoding='utf-8'))
SEP = re.compile(r'[-._/ ]')
VEL = re.compile(r'^(XXXS|XXS|XS|S|M|L|XL|XXL|XXXL|[2-5]XL|S/M|M/L|L/XL|XS/S|ONE|UNI|OS|TU|0|I|II|III|IV|V|\d{2}(,5|\.5)?|\d{3})$', re.I)
def zaklad_kodu(skus):   # kód výrobku bez velikosti
    skus = [x.strip().upper().rstrip('*') for x in skus if x and x.strip()]
    if not skus: return None
    if re.match(r'^0\d{7}(-.*)?$', skus[0]) or re.match(r'^\d{1,7}$', skus[0]): return None   # naše interní číslo karty
    if len(skus) > 1:
        z = os.path.commonprefix(skus).rstrip('-._/ ')
    else:
        z = skus[0]
        c = SEP.split(z)
        if len(c) > 1 and VEL.match(c[-1]): z = z[:len(z) - len(c[-1])].rstrip('-._/ ')
        else: z = re.sub(r'(XSM|SML|MED|LRG|XLG|XXL|ONE)\d$', '', z)
    return z if len(z) >= 4 else None
ZK = [zaklad_kodu(KODY.get(p[0]) or []) for p in prods]
def klic(z, f):   # jeden ze způsobů, jak z kódu výrobku udělat kód modelu
    if not z: return None
    if f == 'sep':
        c = SEP.split(z); return z[:len(z) - len(c[-1])].rstrip('-._/ ') if len(c) > 1 else None
    if f == 'sep2':   # M-MM-AA-BK-1 → M-MM-AA (barva BK, provedení 1)
        c = SEP.split(z); return z[:len(z) - len(c[-1]) - len(c[-2]) - 1].rstrip('-._/ ') if len(c) > 2 else None
    if f[0] == 'd': n = int(f[1:]); return z[:-n] if len(z) > n + 2 else None
    if f[0] == 'p': n = int(f[1:]); return z[:n] if len(z) > n else None
ZPUSOBY = ['sep', 'sep2'] + ['d%d' % n for n in range(1, 6)] + ['p%d' % n for n in range(4, 11)]

def seskupit(MK, vypis=False):   # skupiny barev; MK = kód modelu u každého produktu (nebo None)
    global parent, znacky
    parent = list(range(len(prods)))
    znacky = [{ROZ[p[7]]} if p[7] in ROZ else set() for p in prods]   # (model, skupina) v každé skupině
    pary = []   # (a, b, podle názvu stejný výrobek?) pro učení kódů
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
                if l < bpos + 2: continue
                ra, rb = x[l:], y[l:]
                mx = 5 if l >= bpos + 3 else 3   # dlouhé barvy („Hydrogen White/Uranium Black Matt“) jen u delšího názvu modelu
                stejna_slova = slova_modelu(ra) == slova_modelu(rb) and cast_modelu(ra) == cast_modelu(rb)
                podle_nazvu = 0 < len(ra) <= mx and 0 < len(rb) <= mx and nodig(ra) and nodig(rb) and stejna_slova \
                    and zaklad(prods[ids[a]][1]) == zaklad(prods[ids[b]][1])
                pary.append((ids[a], ids[b], podle_nazvu))
                if not lze_spojit(ids[a], ids[b]): continue
                ka, kb = MK[ids[a]], MK[ids[b]]
                vada = 'vada' in (prods[ids[a]][1] + prods[ids[b]][1]).lower()   # kus s vadou nechat zvlášť
                if ka and kb and not vada:
                    if ka == kb and stejna_slova: spoj(ids[a], ids[b])   # stejný kód modelu → barvy téhož výrobku (pokud název
                    #   neříká jiné provedení, např. „Ventral Air WF MIPS“ × „Ventral Air MIPS“)
                    continue                                     # jiný kód modelu → jiný výrobek
                if podle_nazvu: spoj(ids[a], ids[b])
    return pary

buckets = {}
for i, p in enumerate(prods):
    if any(k in cats[p[6]][0] for k in SKUPINY_NE) or not brands[p[5]]: continue
    buckets.setdefault((p[6], p[5]), []).append(i)
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

# 1) jen podle názvu → páry „stejný výrobek“ / „jiný výrobek“ pro každou značku
pary = seskupit([None] * len(prods))
po_znacce = {}
for a, b, stejny in pary:
    if ZK[a] and ZK[b]: po_znacce.setdefault(prods[a][5], []).append((a, b, stejny))
# 2) u každé značky vybrat způsob, který s názvy souhlasí nejlépe: barvy téhož výrobku podle názvu musí mít
#    stejný kód modelu aspoň v 88 % a různé výrobky podle názvu jiný kód aspoň v 75 % (název někdy dlouhou barvu
#    neodhalí, proto ne 100 %; kód, který nerozlišuje, např. jen pořadové číslo, tím neprojde)
ZPUSOB = {}
for bi, pp in po_znacce.items():
    st = [(a, b) for a, b, s in pp if s]; ji = [(a, b) for a, b, s in pp if not s]
    if len(st) < 5 or len(ji) < 3: continue
    nej = None
    for f in ZPUSOBY:
        ks = [klic(ZK[a], f) for a, b in st]; kj = [klic(ZK[a], f) for a, b in ji]
        if any(k is None for k in ks + kj): continue
        s1 = sum(klic(ZK[a], f) == klic(ZK[b], f) for a, b in st) / len(st)
        s2 = sum(klic(ZK[a], f) != klic(ZK[b], f) for a, b in ji) / len(ji)
        if s1 >= 0.88 and s2 >= 0.75 and (nej is None or s1 + s2 > nej[0]): nej = (s1 + s2, f, s1, s2, len(st), len(ji))
    if nej: ZPUSOB[bi] = nej
for bi, n in sorted(ZPUSOB.items(), key=lambda x: brands[x[0]]):
    print('kódy: %-22s %-4s stejné %3.0f %% (%d)  různé %3.0f %% (%d)' % (brands[bi], n[1], n[2] * 100, n[4], n[3] * 100, n[5]))
# 3) výsledné skupiny: u značek s funkčním kódem rozhoduje kód, jinak název
MK = [klic(ZK[i], ZPUSOB[p[5]][1]) if p[5] in ZPUSOB else None for i, p in enumerate(prods)]
seskupit(MK)
for i, p in enumerate(prods): p.append(find(i))
print('skupin s více barvami', sum(1 for i in range(len(prods)) if sum(1 for q in prods if q[9] == i) > 1))
json.dump({'v': datetime.date.today().isoformat(), 'c': cats, 'b': brands, 'p': prods}, open(out, 'w', encoding='utf-8'),
          ensure_ascii=False, separators=(',', ':'))
print('produktů', len(prods), 'kategorií', len(cats), 'bez url kategorie', sum(1 for c in cats if not c[1]), 'značek', len(brands))
