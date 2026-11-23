"""Index pro vyhledávání 4K: z produkty.json (crawl výpisů kategorií) udělá kompaktní index.json.
python build_index.py produkty.json index.json
p = [url, název, obrázek "hash|soubor", cena, dostupnost 0/1/2, značka, kategorie, kód, EAN]
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

json.dump({'v': datetime.date.today().isoformat(), 'c': cats, 'b': brands, 'p': prods}, open(out, 'w', encoding='utf-8'),
          ensure_ascii=False, separators=(',', ':'))
print('produktů', len(prods), 'kategorií', len(cats), 'bez url kategorie', sum(1 for c in cats if not c[1]), 'značek', len(brands))
