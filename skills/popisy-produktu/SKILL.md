---
name: "popisy-produktu"
description: "Šablony a postup pro popisy produktů na 4kluciodkol.cz: kola, e-kola, dětská kola, helmy, oblečení, tretry, sedla, pláště, ložiska. Použij při psaní, úpravě nebo kontrole popisu produktu."
---

# Popisy produktů 4kluciodkol.cz

Ovládání BSADMIN, importy CSV, roboti a CSS jsou ve skillu **bsadmin**. Tady je, jak má popis vypadat a jak ho vyrobit.
Stav k 19. 11. 2026: na webu je 2 962 stránek s naším popisem (audit 18. 11.: https://claude.ai/artifact/TQi5GxCkTKcofKw98JUXQT).

## Cíl a zásady

- **Cíl.** Popis má vypadat líp než u dovozce i konkurence a má chtít zákazníka koupit. Konkurence (assos-shop.cz, kolokram.cz, bikero.cz, mtbiker.cz) má většinou jen přeložený text výrobce bez fotek.
- **Co prodává.** Detailní fotky, fotky na postavě, akční fotky z jízdy a video výrobce.
- **Galerie produktu.** Popis nesmí vypadat jako druhá kopie galerie (velká fotka + náhledy).
- **Zdroj faktů.** Web výrobce (u Whyte katalog 2026 Retail Manual). Nic si nevymýšlet. Fakta „O značce“ ze stránek značky nebo tiskové zprávy.
- **Oslovení.** Zákazníkovi se vždy vyká. Tykají jen slogan „Nasedni a jeď“ a krátké výzvy.
- **Názvy produktů v textu** jsou stejné jako na kartách e-shopu.
- **Rozbalovací sady místo krácení.** Raději uvést vše důležité a schovat to do sad.
- **Do hlavního popisu nepatří:**
  - tabulka velikostí, ta jde do 3. popisu (záložka Tabulka velikostí),
  - geometrie, ta jde do 2. popisu,
  - roční záruka výrobce (uvést jen skutečné bonusy, např. doživotní záruku rámu),
  - rady s velikostí po telefonu nebo bike fitting.
- **Velikosti v textu.** Uvádět celý výrobní rozsah, ne jen to, co je skladem. Výšku modelky nebo modelu psát do textu, ne přes fotku.
- **Kontakt** (žlutý box): 604 998 800, U Vlečky 726/5c, Brno-Komárov, Po–Pá 9.00–17.00 · So 9.00–13.00.

### Terminologie
- šířka regular / wide (nikdy „střih“)
- ponožkový návlek
- horská / MTB (ne „terénní“)
- uchycení pro 3šroubové kufry
- Wahoo Speedplay
- ližiny (nikdy saně)
- výřez (průchozí) / středový kanál
- závody (ne granfondo)
- treter (2. pád množného čísla)
- nepoužívat „temeno“. Slovo „stabilní“ zakázané není – vadil jen jeden špatný překlad (nadpis sedla). Kde dává smysl (kola v bočním větru apod.), použít ho lze.
- košík = držák na láhev, bidon = láhev
- Další výrazy: memory soubor /topics/eshop-terminologie.md.

## Dvě šablony

| Šablona | Kořenový div | Pro | Dlaždice nahoře |
|---|---|---|---|
| **am4k** | `<div class="am4k">` | kola, e-kola, helmy, oblečení, tretry, sedla, doplňky | velké: štítek, hodnota 34 px, věta pod ní |
| **wo4k** | `<div class="wo4k">` | komponenty: ložiska a díly Enduro, pláště Maxxis/Michelin, dětská kola | menší: štítek, hodnota 28 px |

Obě šablony mají stejný základ:
- šířka max. 1000 px, písmo 16 px / 1,65, text #2b2b2b,
- nadpisy FK Screamer (`'FKScreamer-Upright', Impact, sans-serif`), černá #0b0b0b, šedé popisky #6a6a66, plochy #f4f4f2, linky #e6e6e3, žlutá #ffc524,
- všechny styly inline (BSSHOP editor nic jiného nedrží), jen na začátku jeden `<style>` pro rozbalovací sady,
- třída kořene určuje i třídu tlačítka „+“ (`am4k-plus` / `wo4k-plus`).

**Nejrychlejší cesta je zkopírovat blok z hotového popisu na webu** (vzory níže) a změnit obsah. Kód popisu z webu: balanced `<div class="am4k"` / `<div class="wo4k"` ze stránky (skript rawdesc.py: `raw_desc(html, 'am4k')`).

## Pořadí bloků podle typu produktu

Platí pro všechny: 1. HERO → 2. dlaždice → 3. video nebo fotky → 4. Ideální pro → 5. rozbalovací sady → 6. „O značce“ (poslední sada) → 7. žlutý box RADÍ (vždy na konci).

### Kola a e-kola (am4k) – vzor Cannondale Scalpel HT Carbon 2 (p163933), Whyte Kado RSX (p183387), Amflow PX (p187683)
1. HERO: řádek „Model · typ kola“, nadpis „MODEL – POINTA“, úvodní odstavec.
2. Dlaždice 4–5: Rám, Zdvih / Vidlice, Řazení nebo Motor + Baterie, Kola, Velikosti. Hodnota velkým písmenem („Karbon“), u řazení značka („Shimano Ultegra Di2“).
3. Video výrobce (místo úvodní fotky).
4. Ideální pro.
5. Sady: „Rám a technologie“ (karty s fotkou a textem), „Výbava“ (karty komponent), u e-kol „Motor a baterie“, „Proč <model>“, „Fotogalerie“ (posuvná galerie akčních fotek s „Posuňte do strany pro další fotky →“).
6. Černý box záruky, jen když je skutečný bonus (doživotní záruka rámu Cannondale / rámu a ložisek Whyte).
7. Specifikace z webu výrobce: `h3` „Specifikace · <název>“ + seznam řádků (štítek 150 px, hodnota). U Whyte i srovnávací tabulka modelů v sadě.
8. Box RADÍ: „Od nás neodjedete se špatnou velikostí.“ + vyzkoušení na prodejně, 14 dní na výměnu.
- Kola mají 15–17 fotek v popisu, text 5 800–8 000 znaků.
- Záložka Specifikace z feedu (Whyte, Cannondale) se nemění. Amflow se plní ručně.

### Dětská kola a odrážedla (wo4k) – vzor Woom OFF AIR 5 (p14896), Rascal 20 (p1321)
1. HERO, 2. dlaždice Věk, Výška dítěte, Hmotnost, Kola, Převody / Zdvih, 3. video (Woom OFF / OFF AIR), 4. karty „fotka + nadpis + text“ po technologiích (převodovka, řemen, rám, brzdy pro malé ruce, pláště), 5. sady VÝBAVA, JAKOU VELIKOST VYBRAT? (výška dítěte, věk jen orientačně), případně „Woom OFF, nebo OFF AIR?“, 6. box „JAKOU VELIKOST VYBRAT? Přijďte s dítětem na prodejnu…“.

### Helmy (am4k) – vzor Giro Artex MIPS (p83288)
1. HERO, 2. dlaždice Hmotnost (s velikostí a certifikací), Kšilt / Větrání, Ochrana (MIPS®, Spherical…), 3. řada 3 fotek (pohledy, `aspect-ratio: 4 / 3; object-fit: contain`) nebo video, 4. Ideální pro, 5. odstavec + blok „Technologie – CO V NÍ NAJDETE“, 6. sady „Parametry – HMOTNOST A CERTIFIKACE“, „O ZNAČCE GIRO“, 7. RADÍ „Od nás neodejdete se špatnou velikostí. Helmu si na prodejně v Brně vyzkoušíte…“.
- Rok do názvu helmy nepatří.

### Oblečení (am4k) – vzor Assos UMA GT (p177913), Castelli Espresso 2 (p178391), Ortovox Sequence (p179188)
1. HERO, 2. teplotní škála a hodnocení (Prodyšnost, Izolace… x / 5) + věta „Teplotní rozsah a hodnocení podle výrobce, stupnice 1–5.“ (blok zkopírovat z Castelli Espresso 2), 3. řada 2–3 fotek nebo krátký klip výrobce, 4. Ideální pro, 5. sady: materiál/vložka a střih (s řadou fotek a kartami detailů), Detaily, Střih a padnutí (výška a velikost modelu), Složení a péče, u Assos „Doplňte výbavu“ (dlaždice Dres, Spodní vrstva, Nohy, Vesta), „O značce“, 6. RADÍ „Od nás neodejdete se špatnou velikostí…“.
- Tabulka velikostí značky vždy do 3. popisu, platí každou sezónu stejně, bez poznámek typu „padne o půl čísla menší“.
- Fotky na postavě: hlavní úsilí patří Castelli, Isadore a Assos. Sportful a Maloja doprodáváme, nedohánět.

### Tretry (am4k) – vzor Fizik Vento Powerstrap Aeroweave (p123406), Lake MX333 (p124407)
1. HERO (typ · podrážka · šířka), 2. dlaždice Hmotnost, Tuhost podrážky, Zapínání, Podrážka, Šířka (Lake: Kopyto, Šířka, Podrážka, Zapínání, Svršek), 3. video (Lake MX333) a řada 3 fotek, 4. Ideální pro, 5. sady Svršek, Zapínání, Podrážka, u Lake Kopyto a šířka, Výroba, péče o kůži, video „tvarování paty“ (CX403 Thermaform, spouští se kliknutím), Technické údaje, „O značce“, 6. RADÍ „Tretry si na prodejně vyzkoušíte… měříme chodidlo“.

### Sedla (am4k) – vzor Fizik Tempo Argo R1 (p91690)
1. HERO, 2. dlaždice Hmotnost, Šířka, Délka, Ližiny, Polstrování, 3. řada 3 fotek, 4. Ideální pro, 5. sady Tvar, Výřez, Polstrování, Konstrukce (skelet a ližiny), Technické údaje, 6. RADÍ „Od nás neodejdete se špatným sedlem… měříme rozteč sedacích kostí“.

### Pláště (wo4k) – vzor Maxxis Dissector (p25297)
1. HERO „Značka Model · MTB plášť 27,5 × 2,40″“, 2. dlaždice Rozměr, Použití, Hmotnost, Bezdušový, 3. karty technologií (směs 3C, ochrana EXO+…), 4. sady PARAMETRY, JAKÝ RÁFEK?, BEZDUŠOVÁ MONTÁŽ, CO ZNAMENÁ TPI?, 5. box „NEVÍTE, JAKÝ PLÁŠŤ VYBRAT? Přijeďte s kolem na prodejnu… namontujeme“.
- Krátký popis = jedna věta: k čemu plášť je a jaký má rozměr (hotovo u všech 411 Maxxis a Michelin).

### Ložiska, středy a díly Enduro (wo4k) – vzor ložisko 3801 LLU MAX (p188600)
1. HERO: nad nadpisem malým písmem kód, nadpis = název („PF30 TORQTITE PRO OSU 29 MM“), nikdy kód, 2. dlaždice Rozměr (d × D × B), Typ, Materiál, Těsnění, 3. karty k čemu je a technologie (MAX, LLU, Torqtite…) s obrázkem a vysvětlením, 4. sada PARAMETRY, 5. box „NEVÍTE, JAKÉ LOŽISKO POTŘEBUJETE? Přineste nám staré ložisko…“ (středy: „Nevíte, jaký střed pasuje?“, ostatní „Poradíme vám“).

## Společné bloky (HTML)

Začátek popisu (am4k; u wo4k zaměnit `am4k` za `wo4k` i ve stylu):
```html
<div class="am4k" style="max-width: 1000px; margin: 0 auto; font-size: 16px; line-height: 1.65; color: #2b2b2b;"> <style>.am4k summary{list-style:none}.am4k summary::-webkit-details-marker{display:none}.am4k details[open] .am4k-plus{transform:rotate(45deg)}.am4k-plus{transition:transform .2s}</style>
```

HERO:
```html
<div style="max-width: 760px; margin: 24px auto 36px; text-align: center;"> <p style="margin: 0 0 8px; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #6a6a66;">Giro Artex MIPS · helma s kšiltem</p> <h2 style="margin: 0 0 16px; font-family: 'FKScreamer-Upright', Impact, sans-serif; font-weight: normal; font-size: 56px; line-height: 1; color: #0b0b0b;">ARTEX MIPS – ZÁVODNÍ TVAR, TERÉNNÍ KŠILT</h2> <p style="margin: 0; font-size: 19px; line-height: 1.6;">Úvodní odstavec. Tato varianta je v barvě …</p> </div>
```

Dlaždice (am4k, jedna z řady; řada = `<div style="display: flex; flex-wrap: wrap; gap: 12px; margin: 28px 0 0;">`):
```html
<div style="flex: 1 1 210px; min-width: 0; box-sizing: border-box; background: #f4f4f2; border-radius: 4px; padding: 20px;"><span style="display: block; font-size: 13px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #6a6a66;">Hmotnost</span><strong style="display: block; margin: 6px 0 8px; font-family: 'FKScreamer-Upright', Impact, sans-serif; font-weight: normal; font-size: 34px; line-height: 1; color: #0b0b0b;">280 g</strong><span style="display: block; font-size: 14px; line-height: 1.5;">Velikost M, certifikace CE.</span></div>
```
Dlaždice wo4k: `flex: 1 1 150px; … padding: 16px 18px;`, štítek 13 px bez kapitálek, hodnota `font-size: 28px; line-height: 1.1;`, bez věty pod ní.

Ideální pro:
```html
<div style="padding: 40px 0; border-top: 1px solid #e6e6e3;"> <p style="margin: 0 0 8px; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #6a6a66;">Ideální pro</p><p style="margin: 0; font-size: 18px; max-width: 760px;">Na XC závody, maratony a rychlé výjezdy do kopců.</p> </div>
```

Rozbalovací sada (první sada `<details open …>`, ostatní zavřené; sady jsou v obalovém divu):
```html
<details style="border-top: 1px solid #e6e6e3;"><summary style="display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 24px 0; cursor: pointer; list-style: none;"><span style="min-width: 0;"><span style="display: block; font-size: 13px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #6a6a66;">Značka</span><span style="display: block; margin: 4px 0 0; font-family: 'FKScreamer-Upright', Impact, sans-serif; font-weight: normal; font-size: 38px; line-height: 1.05; color: #0b0b0b;">O ZNAČCE GIRO</span><span style="display: block; margin: 6px 0 0; font-size: 15px; color: #6a6a66;">Kalifornská značka helem od roku 1985</span></span><span class="am4k-plus" style="flex: 0 0 auto; display: inline-block; width: 40px; height: 40px; line-height: 38px; text-align: center; border-radius: 50%; background: #ffc524; color: #0b0b0b; font-size: 28px; font-weight: bold;">+</span></summary><div style="padding: 0 0 40px;"> … obsah … </div></details>
```
- **„O značce“** = poslední sada před boxem RADÍ, štítek „Značka“, nadpis „O ZNAČCE <ZNAČKA>“, podtitul jednou větou, obsah jeden odstavec `max-width: 760px` (založení, odkud, čím je značka známá). Má ho Maloja, Sportful, Mons Royale, Ortovox, Alé, Giro, 4GOLD. Připraveno pro Isadore, Lake, Amflow, Whyte (19. 11. čeká na import). Chybí ještě Assos, Castelli, Fizik, Cannondale.

Řada fotek v sadě (každá fotka ve vlastním divu, jen `<img>` uvnitř):
```html
<div style="display: flex; flex-wrap: wrap; gap: 12px; margin: 0 0 28px;"><div style="flex: 1 1 200px; min-width: 0; max-width: 320px;"><img src="https://cdn.4kluciodkol.cz/data/user-content/Obr%C3%A1zky/Eshop/Assos/<soubor>.webp" alt="ASSOS UMA GT Bib Shorts S11 zepředu" width="1100" height="1375" loading="lazy" style="display: block; width: 100%; height: auto; border-radius: 4px;"></div> … </div>
```
Na telefonu dá stylesheet „4K - popisy na mobilu“ fotky v sadách do 2 sloupců, ale jen když je div s fotkou bez dalšího obsahu (`div > img:only-child`) a v řádku jsou aspoň 2. Fotka s textem zůstane přes celou šířku. Tuto strukturu proto dodržovat.

Video výrobce (nahoře místo úvodní fotky, samo se spustí bez zvuku, když je z poloviny vidět; pauza mimo obrazovku; ruční pauzu respektuje). Skript nesmí obsahovat `<`, `>` ani `&` (BSSHOP editor je rozbije), proto `i!=vs.length` a `p['catch']`:
```html
<div style="width: 100%; margin: 0 0 8px;"><video class="am4k-auto" src="https://cdn.4kluciodkol.cz/data/user-content/Obr%C3%A1zky/Eshop/Amflow/amflow-px-carbon-video.mp4" poster="https://cdn.4kluciodkol.cz/data/user-content/Obr%C3%A1zky/Eshop/Amflow/amflow-px-carbon-video-nahled.webp" width="1280" height="720" controls muted loop playsinline preload="none" title="Amflow PX Carbon – video výrobce" style="display: block; width: 100%; height: auto; aspect-ratio: 16 / 9; border-radius: 4px; background: #0b0b0b;"></video><script>(function(){if(!('IntersectionObserver' in window))return;var vs=document.querySelectorAll('video.am4k-auto');for(var i=0;i!=vs.length;i++){(function(v){if(v.am4k)return;v.am4k=1;v.muted=true;var vis=false,auto=false,user=false;v.addEventListener('pause',function(){if(vis){if(!auto)user=true;}auto=false;});v.addEventListener('play',function(){user=false;});new IntersectionObserver(function(es){vis=es[0].isIntersecting;if(vis){if(!user){var p=v.play();if(p)p['catch'](function(){});}}else{if(!v.paused){auto=true;v.pause();}}},{threshold:0.5}).observe(v);})(vs[i]);}})();</script></div>
```
- Video, které se má pouštět jen kliknutím (návod, např. tvarování paty Lake): stejný `<video>` bez třídy `am4k-auto` a bez skriptu.
- Krátké klipy výrobce (Assos, Ortovox, Mons Royale, 1–5 MB) jsou `autoplay muted loop playsinline`.

Posuvná galerie akčních fotek (kola):
```html
<div style="display: flex; gap: 12px; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding: 0 0 12px; margin: 0 0 4px;"><div style="flex: 0 0 82%; max-width: 560px; scroll-snap-align: start; box-sizing: border-box;"><img src="…webp" alt="Cannondale Scalpel HT v akci" width="1000" height="492" loading="lazy" style="display: block; width: 100%; height: 360px; object-fit: cover; border-radius: 4px;"></div> … </div><p style="margin: 0; font-size: 14px; color: #6a6a66; text-align: right;">Posuňte do strany pro další fotky →</p>
```

Černý box záruky:
```html
<div style="background: #0b0b0b; color: #ffffff; border-radius: 4px; padding: 28px 32px; margin: 36px 0 0; display: flex; flex-wrap: wrap; gap: 8px 32px; align-items: center;"><strong style="flex: 0 1 auto; font-family: 'FKScreamer-Upright', Impact, sans-serif; font-weight: normal; font-size: 40px; line-height: 1; color: #ffc524;">DOŽIVOTNÍ ZÁRUKA NA RÁM</strong><span style="flex: 1 1 320px; font-size: 16px;">Text…</span></div>
```

Specifikace (řádek seznamu):
```html
<li style="display: flex; flex-wrap: wrap; gap: 4px 16px; padding: 10px 0; border-top: 1px solid #e6e6e3;"><strong style="flex: 0 0 150px; color: #0b0b0b;">Rám</strong><span style="flex: 1 1 260px; min-width: 0;">…</span></li>
```
Seznam: `<ul style="margin: 0; padding: 0; list-style: none; border-bottom: 1px solid #e6e6e3;">`, nadpis `h3` 13 px kapitálky šedě.

Box 4 KLUCI → RADÍ (am4k, vždy poslední, za koncem obalu sad):
```html
<div style="background: #ffc524; color: #0b0b0b; border-radius: 4px; padding: 32px; margin: 16px 0 24px; display: flex; flex-wrap: wrap; gap: 20px 40px; align-items: center;"><div style="flex: 2 1 360px; min-width: 0;"><h2 style="margin: 0 0 10px; font-family: 'FKScreamer-Upright', Impact, sans-serif; font-weight: normal; font-size: 40px; line-height: 1; color: #0b0b0b; text-align: left;">4 KLUCI → <span style="font-family: 'FKScreamer-Slanted', 'FKScreamer-Upright', Impact, sans-serif; color: #ffc524; -webkit-text-stroke: 1.2px #0b0b0b;">RADÍ</span></h2><p style="margin: 0 0 6px; font-size: 18px; font-weight: bold; color: #0b0b0b;">Od nás neodejdete se špatnou velikostí.</p><p style="margin: 0; font-size: 16px; color: #0b0b0b;">Text podle typu produktu… A když objednáte z e-shopu a velikost nesedne, do 14 dnů ji vyměníme.</p></div><div style="flex: 1 1 240px; min-width: 0; background: #0b0b0b; color: #ffffff; border-radius: 4px; padding: 20px 22px; font-size: 15px; line-height: 1.6;"><strong style="font-size: 20px; color: #ffc524;">604 998 800</strong><br>U Vlečky 726/5c, Brno-Komárov<br>Po–Pá 9.00–17.00 · So 9.00–13.00</div></div> </div>
```
U wo4k je nadpis boxu otázka („NEVÍTE, JAKÝ PLÁŠŤ VYBRAT?“) bez „4 KLUCI → RADÍ“ a jeden odstavec.

## Barevné varianty

Barvy jsou na webu samostatné produkty se stejným textem, což Google bere jako duplicitu. Proto:
- na konec úvodního odstavce HERO přidat větu „Tato varianta je v barvě <Barva>.“ (barva z názvu karty, anglicky jako u výrobce, „ - “ → „/“),
- fotky, které se u barev liší a mají stejný alt, dostanou „ – barva <Barva>“ na konec altu,
- zbytek popisu se nemění. Skript: scratchpad audit/color2.py (seskupí stránky stejné značky se shodným textem).

## Obrázky

- Vlastní FTP: `Obrázky/Eshop/<Značka>`, na webu `https://cdn.4kluciodkol.cz/data/user-content/Obr%C3%A1zky/Eshop/<Značka>/<soubor>.webp`. Nikdy odkaz na web výrobce.
- Vždy WebP, každý obrázek s vlastním českým `alt`, s `width` + `height` a `loading="lazy"`.
- Názvy souborů: `<značka>-<model>-<co>.webp` malými písmeny s pomlčkami.
- Fotky hledat na webu výrobce a dovozce (detaily, na postavě, v akci). Šedé pozadí produktových fotek opravit na bílé.
- Soubory pro uživatele do `Pomocnik\Fotky\<Značka>` (podsložka `Video na FTP` pro videa), on je nahraje na FTP.

## Video

- Zdroj: vlastní soubor výrobce nebo dovozce. **Z YouTube nestahujeme** (podmínky YouTube). Seznam videí k vyžádání od dovozců: https://claude.ai/artifact/RaecTyMwkNzSznvzHLqLPF. Uživatel videa stáhne sám a dá do `Pomocnik\Fotky\<Značka>`.
- Úprava (ffmpeg): `-vf "scale=1920:-2,fps=30,format=yuv420p" -c:v libx264 -preset veryfast -crf 25 -maxrate 4M -bufsize 8M -an -movflags +faststart`. Dlouhé filmy zkrátit `-t 60` (Giro Eclipse Pro) nebo `-t 120` (Whyte Karve). Bez zvuku.
- Náhled: snímek z videa jako `<název>-nahled.webp` (`-quality 82`).
- Název: `<značka>-<model>-video.mp4`. Video patří nahoru místo úvodní fotky, ne doprostřed textu.
- Soubor přes 23 MB nejde zapsat na KANCL přímo: poslat přes SendUserFile a pak zapsat přes fileUuid, nebo překódovat menší.
- Hotová videa (19. 11. 2026): Amflow PR, PX, TL, UE · Whyte Kado · Fizik (Kudo, Kudo Aero, Kassis, Kyros, Adaptive) · Maxxis Minion DHX · Woom OFF, OFF AIR · Cannondale 10 modelů · Giro Fixture II MIPS, Eclipse Pro, Aries Spherical · Lake tvarování paty, MX333. Whyte Karve čeká na nahrání na FTP. Cannondale Synapse: soubor „Road Reimagined“ má v názvu skrytý znak, je potřeba ho přejmenovat (např. synapse.mp4).

## Postup výroby sady popisů

1. Zjistit kódy a stránky: z exportu BSADMIN (ne hromadným procházením webu). Export Zásob se sloupci Id, Id Virtuální, Kód, Internet, Obrázky: filtr `[Id Virtuální] In (…)` (ID masterů = číslo z URL `-p12345`) → pravé tlačítko → Export zobrazených produktů (uložit rovnou do `\\tsclient\C\Users\<uživatel>\Downloads`). Tak se najdou i varianty s vypnutým Internetem, které na webu ani v JSON-LD nejsou (24. 11. u POC oblečení chybělo 20 variant). Kód masteru je obvykle ID doplněné nulami na 7 znaků (p34512 → 0034512). Do CSV vždy kód masteru I všech jeho variant – master si popis z variant nepřevezme (viz bsadmin). Na stránce webu je `code value"><span>KÓD` někdy kód varianty, ne masteru (Topstone 00100033-54), proto kódy brát z BSADMIN a ne jen ze stránky; kódy variant jsou v JSON-LD `"sku":"…"`. Pozor na stejný kód na více kartách (SKLAD, KOMIS, Cyklospeci), viz bsadmin.
2. Vzít aktuální popis z webu (raw_desc), ne starý soubor. Měnit jen to, co je potřeba.
3. Připravit CSV pro import (bsadmin → Importy): `"Kod";"Popis"`, ASCII s HTML entitami (`&#233;`), oddělovač `;`, vše v uvozovkách, CRLF, jeden soubor do 4,5 MB. Kód masteru i všech variant dostane stejný popis. Krátký popis jen ve sloupci KratkyPopis, nikdy prázdný (prázdný by smazal stávající).
4. Náhled `NAHLED-<značka>.html` a zkontrolovat ho na šířce 390 px i 1440 px.
4a. **Povinná kontrola obsahu před předáním** (nejen vzhledu). Skriptem projít všechna CSV a pro každý model vypsat počet fotek v galerii a počet obrázků u technologií. Každý model musí mít:
   - vlastní akční fotky (z jízdy, na postavě) ze stránky výrobce, ne jen společné fotky celé značky,
   - obrázky u technologií tam, kde je výrobce má,
   - video výrobce, pokud existuje,
   - alt texty, které sedí na daný model (ne „Mavic Cosmic“ u Ksyrium).
   Výsledek porovnat s nejlepším hotovým vzorem stejné kategorie. Každý model s méně než 2 fotkami v galerii nebo bez obrázků technologií vysvětlit uživateli (výrobce nic nemá) nebo doplnit.
   Fotky hledat nejen na stránce produktu, ale i na stránkách řad a kampaní výrobce (u Zipp `sram.com/en/zipp/road/series/<řada>`, `…/mountain/series/…`; u Mavic `ambiance_paysage` a `product_benefit_image` ve stránce modelu). Stránka produktu u Zipp má jen fotky na bílém pozadí. (Chyba 22. 11. 2026: popisy Zipp měly jen 4 společné fotky a technologie bez obrázků.)
4b. **Vizuální kontrola všech fotek před předáním** (galerie i popisy): přehledový obrázek, každý řádek = kód + název a barva karty + miniatury fotek. Projít očima, jestli barva i produkt sedí. Výrobci (POC rukavice a čepice) dávají jako 2. fotku jinou barvu – fotky podle čísla barvy z webu výrobce nestačí (24. 11. 2026: 9 karet POC s fotkou v cizí barvě).
5. Úkol do Todoistu (projekt Otík): které soubory nahrát na FTP, které CSV importovat a **které sloupce mají být aktivní** (u popisů jen Kod a Popis).
6. Po importu a doběhnutí robota ověřit na webu **každou** stránku, ne vzorek: skript v prohlížeči projde všechny URL (pauza ~0,7 s) a porovná H1 s novým názvem, přítomnost `.am4k`, nadpis HERO, počet obrázků v popisu a počet fotek galerie (`#PDImage .items a`, bez nich 1 hlavní fotka). Teprve pak úkol odškrtnout. Počet „rozdílných“ v importu není potvrzení – jen říká, že se kód s něčím spároval.
7. Když soubor po předání uživateli změním, musí ho naimportovat znovu. Napsat to do úkolu, jinak na webu zůstane stará verze (stalo se 18. 11. u Giro a Lake).

## Otevřené body (stav 19. 11. 2026, aktuální stav vždy v Todoistu)

- Import čeká: názvy bez roku (Cannondale, Giro, Fizik), blok „O značce“ (Isadore, Lake, Amflow, Whyte Kado), věta o barvě (23 CSV: Castelli, Assos, Fizik, Giro, Mons Royale, Ortovox, Rascal, volitelně Maloja, Sportful, Alé), znovu Giro a Lake, Whyte Karve.
- Teplotní stupnice: Assos a Castelli mají 1–5, Isadore 1–6. Sjednotit.
- Specifikace Cannondale je z velké části anglicky (Whyte a Amflow česky). Konkurence (kolokram.cz) ji má celou česky.
- Fotky na postavě doplnit u Castelli, Isadore a Assos.
- Mobil: přes 70 % přístupů. Okraj stránky (10 px) a další úpravy pro telefon až při redesignu webu.
- Hodnocení produktů a otázky zákazníků (v tom konkurence vede): zjistit u BSSHOP, jestli to jde zapnout.