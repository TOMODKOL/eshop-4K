---
name: "bsadmin"
description: "E-shop 4kluciodkol.cz (BSSHOP/BSADMIN): karty a mastery, CSV importy, názvy, parametry, popisy produktů, obrázky, CSS a design webu, SEO, e-maily, vyhledávání, feedy, koš. Použij při čemkoli kolem webu a BSADMIN."
---

# BSADMIN a e-shop 4kluciodkol.cz

E-shop běží na BSSHOP; BSADMIN je program na vzdáleném serveru iPodnik (ne webová administrace). Data karet pocházejí z Pohody (viz skill **pohoda**). Je jeden e-shop s jednou jazykovou mutací. Podpora BSSHOP je pomalá, takže co jde, řešíme sami.

**Než začneš pracovat na webu, přečti dokument „E-shop 4K – stav a rozhodnutí“** (https://claude.ai/artifact/7DcPoZeGC2Z7UcDWVkGNCu, Claude Docs). Je v něm aktuální stav, rozhodnutí a otevřené úkoly. Když se něco rozhodne, dopiš to tam.

Podklady jsou ve firemním OneDrive `4 KLUCI OD KOL\AI\Podklady pro Claude`, na počítači KANCL v `D:\User\OneDrive - 4 KLUCI OD KOL s.r.o\4 KLUCI OD KOL\AI\Podklady pro Claude`:
- 01 Logo
- 02 Font
- 03 Barvy a styl
- 04 Fotky
- 08 Katalogy dodavatelů (Whyte 2026 Retail Manual)
- 09 Castelli

## Obecná pravidla

- **Komunikace.** Uživateli vykat a mluvit česky. Říká mi „Otík“.
- **Schvalování.** Na web se nic nezapisuje bez jeho OK. Nastavení BSADMIN a obsah Záhlaví/Zápatí se mění jen s jeho souhlasem. Import Aspire neměnit.
- **Zadání CSV.** Ke každému CSV pro BSADMIN vždy napsat, které sloupce se importují a na která pole.
- **Čekání.** Čas, kdy běží synchronizace, využít na jinou práci a výsledek zkontrolovat později.
- **Kontroly webu.** Kontrolovat bez umělého zpomalování. Seznamy karet ale zjišťovat z exportu v BSADMIN, ne hromadným procházením webu (to už způsobilo chyby 503/504).
- **Zásada.** „Méně je více“: web má být přehledný a jednoduchý.
- **Vyprodané karty.** Nikdy je nemazat. Karty i master se přesunou do **koše** (viz Postup: vyřazení do koše). Internet se nevypíná. Adresa produktu dál funguje a web nehlásí 404.
- **Nulový sklad** (náš ani dodavatele) neznamená, že výrobek skončil.
- **Karty TEST…** jsou testovací sedla, která půjčujeme zákazníkům.
- **Stejný kód na více kartách.** Kód bývá ve skladě SKLAD, KOMIS, SERVIS, Cyklospeci i Import. Přejmenování platí pro všechny karty. Kopie v „Koš-sklad“ se neřeší.
- **Text do vzdálené plochy** se vkládá přes schránku: na Macu Cmd+C, ve Windows Ctrl+C, na serveru vždy Ctrl+V.
- **Export z BSADMIN.** V Zásobách pravé tlačítko → „Export zobrazených produktů“. Soubor se uloží do Stažených na serveru. Odtud se přenese přes sdílenou schránku nebo složku vzdálené plochy.
- **Návody.** Příkazy a postupy psát pro Mac i Windows (kolega používá Windows).

## Karty, mastery, seskupení

- **Tvorba masteru.** Master vytváří uživatel sám: označí karty → **Seskupit položky**. Já pomáhám hlavně se **správným filtrem** karet.
- **Přidání karet k existujícímu masteru.** Označit existující master (i se skupinou) a nové karty → Seskupit. Jinak vznikne nový master.
- **Checkbox Internet** je v detailu karty (záložka Hlavní, dole), ne v gridu.
- **Popis masteru.** Seskupení popis do masteru nezkopíruje. Master potřebuje vlastní import popisu (Kod = kód masteru).
- **Chyba s kategorií Archiv.** Po seskupení někdy master spadne do „Sklad pro vyřazené produkty\Archiv“. Na webu pak chybí drobečková navigace a master není v sitemapě. Oprava: v Zásobách přetáhnout master do stejné kategorie jako varianty a zkontrolovat, že Umístění ukazuje [hlavní karta].
- **Výběr velikosti.** Když varianta nemá parametr Velikost (nebo ho má jinak než ostatní), web místo výběru velikosti ukáže seznam celých názvů.
- **„Související produkty“** nepoužíváme.
- **Nové karty z feedů** padají do Import / !Importy. Popis připravit dřív, než je kolega přesune na e-shop.
- **Fotky galerie** se dávají na varianty, master je převezme.

### Filtry

SQL filtr v BSADMIN vyžaduje celý dotaz, který vrací ID:

```
SELECT ID FROM SKz WHERE IDS IN ('kód1','kód2')
```

- `AND IObchod=1` vyfiltruje jen internetové karty.
- V Pohodě (Vlastnosti dotazu) se píše `SKz.IDS IN ('…')`.
- Textový filtr v Zásobách: Editovat filtr → záložka Text (tlačítko často potřebuje dvě kliknutí). Příklady:
  - `[Id] In (…)`
  - `[Výrobce] Like '%Castelli%'`
  - `[Stav zásob] = 0`
  - `[Rezervováno] = 0`

### Postup: vyřazení do koše

1. Vyfiltrovat karty, které jsou na e-shopu a nemají skladem žádnou velikost. Vynechat karty s rezervací a karty z předobjednávky. Ověřit, že kód není skladem v jiném skladě.
2. Označit první řádek, pak Ctrl+Shift+End → modrý koš „Přesunout do koše“ → Ano.
3. **Uložit disketou** úplně nahoře. Bez uložení se nic nepřesune.
4. Kontrola: ve stromu vyhledat „Koš“ a porovnat počet řádků.

Na co dát pozor:
- Nikdy nepoužívat „Trvale odstranit“ ani červený křížek.
- V mřížce nemačkat Esc. Zruší filtr i neuložené změny.
- Obnovení: v Koši pravé tlačítko → „Obnovit do původního umístění“ a pak uložit.
- Varianty v !Importy nechat být.

## Názvy karet

- **Tři pole.** Web bere **Název krátký CZ** ve výpisu a **Název CZ** na detailu. Název z Pohody použije jen tehdy, když jsou obě prázdná. Při přejmenování proto změnit všechna tři: Název POHODA CZ, Název krátký CZ a Název CZ. CSV import názvů je v UTF-8.
- **Pořadí slov.** **typ – značka – model – barva – velikost**.
- **Bez roku.** Roky byly v názvech jen kvůli Heurece. U helem a kol je odstranit, nové karty rok nemají.
- **Kontrola při zpracování zboží.** Názvy vždy zkontrolovat a opravit, i u karet z feedů.
- **Model a barva** anglicky, jak je používá výrobce nebo dovozce. Názvy LAKE:
  - pánské: `tretry LAKE <model> <barva> <NARROW|REGULAR|WIDE> <velikost>`
  - dámské: `dámské tretry LAKE CX333 Women <barva> <velikost>`
  - master bez velikosti
- **AMFLOW.** „AM…“ u KROSS je značka **AMFLOW**, nikdy ne KROSS.
- **POC VPD Air Vest** je „chránič páteře“.
- **Ložiska a díly (Enduro):** typ výrobku česky, zbytek názvu stejnými hodnotami jako ve filtrech (anglicky). Ložiska se jmenují jen „ložisko ENDURO 6903 LLB 17x30x7 mm“, ne „ložisko do náboje“. Jedinou výjimkou je „ložisko do hlavového složení“.
- **Členění ložisek** (od 16. 11. 2026) je podle typu jako u Enduro:
  - Ložiska/: Ložiska do nábojů, Ložiska do odpružení, Jehlová ložiska do tlumičů, Ložiska do hlavového složení, Ložiska do středového složení, Miniaturní ložiska, Sady ložisek do středů, Kuličky, Díly ložisek
  - Středy/: Díly středového složení, Redukce středového složení
  - Nářadí/: Nářadí na ložiska a středy
- **URL** je název + `-p{ID}`. Přejmenování URL změní, ale stará adresa se přesměruje podle ID.
- **Hledací slova** patří do **Klíčová slova pro hledání CZ**. Pole „SEO Klíčová slova CZ“ je jen meta tag.

## Internetové parametry (Pohoda)

- **barva, ID 3:** česky, jedna hlavní barva (kvůli filtru).
- **Velikost, ID 5:** jen číslo, např. 42,5.
- **Šířka, ID 52:** seznam Narrow / Regular / Wide.
- **Změna existujícího parametru:** `intParameter actionType update` s `ftr:id` přiřazení. Prosté add jen varuje „lze přiřadit pouze jednou“ a nic nezmění.
- **Seznamový parametr:** každá položka musí mít vyplněný Popis. Prázdný popis shodí celou Synchronizaci zásob. Kontrolovat log v Ovládání robotů (řádky Chyba).
- **Fixace ceny:** `stk:fixation` jde jen exportovat. Fixaci Běžné ceny nastavuje uživatel ručně.
- **Nové parametry raději jako textValue.** Stávající parametry (Velikost, Sezóna…) jsou textové a filtry na webu fungují. Textový parametr nemá problém s prázdným Popisem položek seznamu.
- **Parametry pro ložiska a díly (Enduro, ID 56–76):** názvy česky, hodnoty anglicky přesně jako ve filtrech na endurobearings.com. Jsou to: Typ ložiska, Vnitřní průměr, Vnější průměr, Šířka ložiska, Materiál, Třída přesnosti (ABEC), Vůle ložiska, Kompatibilní označení, Použití, Osa kliky, Standard středového pouzdra, Typ středového složení, Typ nářadí, Typ dílu, Značka odpružení, Průměr nohou vidlice, Kompatibilita pohonu, Průměr kuliček, Třída kuliček (Grade), Počet zubů, Typ hlavového složení.

## Importy (Správce importů)

- **Excel import na serveru padá, vždy použít CSV.**
- Definice **„4K - popisy produktů“**: CSV, oddělovač `;`, uvozovky `"`, kódování UTF-8, v 1. řádku názvy sloupců.

| CSV | Pole BSADMIN |
|---|---|
| Kod | Kód |
| Nazev | Název POHODA CZ |
| KratkyNazev | Název krátký CZ |
| DlouhyNazev | Název CZ |
| KratkyPopis | Krátký popis CZ (text pod názvem, nikdy „anotace“) |
| Popis | 1. Eshop popis CZ |
| Geometrie | 2. Eshop popis CZ |
| Velikosti | 3. Eshop popis CZ (záložka Tabulka velikostí) |
| KlicovaSlova | Klíčová slova pro hledání CZ |
| Obrazky | Obrázky |

- **Aktivní pole.** Zaškrtnout Aktivní jen u polí, která CSV obsahuje.
- **Import Obrázků nahradí stávající obrázky.** Při doplňování musí CSV obsahovat i ty, které už na kartě jsou.
- **Zdvojené karty.** Import podle Kódu zapíše jen do jedné z nich.
- **Pohoda obrázky** jsou v BSADMIN jen pro čtení. Mění se v Pohodě.

## Popisy produktů

### Obsah
- **Cíl:** popisy lepší než u dovozce a konkurence (ASSOS má vlastní assos-shop.cz). Terminologii lze ověřit na kola-radotin.cz, ale nekopírovat.
- **Zdroj spec:** specifikace v popisu se bere z webu výrobce.
- **Záložka Specifikace z feedu** se nemění. Whyte a Cannondale jdou z feedu, Amflow se plní ručně. U Whyte má katalog 2026 přednost před webem výrobce.
- **Uvést vše důležité.** Raději rozbalovací části než krátit. Jedno téma otevře celou sadu 4–6 dlaždic.
- **Dlaždice parametrů u kol:** hodnota začíná velkým písmenem („Karbon“). U řazení se uvádí značka („Shimano Ultegra Di2“).
- **Tabulka velikostí** patří na vlastní záložku (3. popis), **geometrie** do 2. popisu, ani jedno ne do hlavního popisu. U oblečení vždy připravit i tabulku velikostí.
- **Velikostní tabulka značky** platí každou sezónu stejně. Žádné poznámky typu „padne o půl čísla menší“.
- **Geometrie:** rozměry anglicky (Reach, Stack…). Na mobilu úzký sloupec popisků.
- **Velikosti v textu:** uvádět výrobní rozsah, ne jen to, co máme skladem.
- **Záruka:** nezmiňovat roční záruku výrobce. Uvést jen skutečné bonusy, např. doživotní záruku rámu.
- **Velikost a posed:** žádné rady s velikostí po telefonu ani bike fitting. Velikost se vybírá na prodejně, e-shop dává 14 dní na výměnu.
- **Velikost modelky či modelu** patří do popisu, ne jako popisek přes fotku.
- **Názvy produktů v textu:** jak jsou na kartách e-shopu.
- **Feedy a popisy:** Aspire nepřepíše popis, který byl jakkoli upraven. Varianty dostanou stejný popis jako master.
- **Tabulka parametrů BSSHOP** pod popisem je skrytá přes CSS.
- **Konec popisu:** žlutý box s poradenstvím (604 998 800, adresa, otevírací doba). Nadpisy FK Screamer.

### Postup vložení popisu

1. Uložit zálohu původního kódu.
2. Popis přepnout do zdrojového kódu a vložit nový.
3. Uložit, zavřít, znovu otevřít a zkontrolovat konec kódu.
4. Zkontrolovat na počítači i v telefonu.

Větší množství popisů najízdí importem CSV.

### Obrázky a videa
- **Obrázky v popisech** jsou na vlastním FTP ve složce `Obrázky/Eshop/<Značka>`, ne odkazem na web výrobce.
- **Formát:** vždy **WebP**, každý obrázek s vlastním českým **alt** textem a s **width/height** v kódu.
- **Videa** výrobce zkomprimovat a dát na FTP.
- **Galerie produktu:** fotky leží na serveru v `C:\IPODNIK\BSSHOP\BSADMIN\Obrazky\<Značka>\<Sezóna>`. První fotka je pohled zepředu. Šedé pozadí z fotek výrobce opravit.
- **Velikosti obrázků na CDN:**
  - `/3/` = 50 px
  - `/0/` = 220 px (výpis)
  - `/1/` = 500 px (detail)
  - `/2/` ≈ 1 200 px

### Terminologie
- **šířka regular / wide** (nikdy „střih“)
- **ponožkový návlek**
- **horská / MTB** (ne „terénní“)
- **uchycení pro 3šroubové kufry**
- **Wahoo Speedplay**
- **ližiny** (nikdy saně)
- **výřez** (průchozí) / **středový kanál**
- **závody** (ne granfondo)
- **treter** (2. pád množného čísla)
- nepoužívat „stabilní“ ani „temeno“
- košík = držák na láhev; bidon = láhev

### Oslovení
- Slogan „Nasedni a jeď“ a krátké výzvy tykají.
- Vše, co mluví k zákazníkovi, vyká. Text dodavatele s tykáním přepsat na vykání.
- Kontakt: telefon 604 998 800, U Vlčky 726/5c, Brno, Po–Pá 9–17, So 9–13.

## Vzhled webu (CSS)

Kde se co mění:
- **Hlavní CSS:** Nastavení → Nastavení aplikace → expertní nastavení → Vlastní HTML → Všechny stránky → Vlastní CSS styly. Nic nemazat. Naše bloky označovat `/* 4K – … */`.
- **Stylesheety:** Internetový obchod → Stylesheety („4K – detail produktu“ v8.2, „4K – výpis produktů“). Vypínají se odškrtnutím Aktivní.
- **Akční ikony:** Nastavení → Akční ikony.
- **Typy plateb:** Nastavení typů plateb.
- **Dostupnost:** Nastavení dostupnosti.
- **Kategorie v liště:** v Zásobách zaškrtnout Nahrávat na eshop.
- **Skript filtru:** na konec Uživatelského obsahu Zápatí (HTML režim), jen se souhlasem. Ověřit, že ho BSSHOP při uložení nesmazal.
- **Text BLACK FRIDAY** je v hlavním CSS. Po akci vrátit Výprodej.

Postup a testování:
- Před každou změnou záloha. Nový kód dodat jako celý soubor do Stažených.
- Testovat na šířce 1440 px a 390 px.
- Stránky kol se poznají podle `/jizdni-kola-c276/`.

Podpora BSSHOP je potřeba jen na šablonu detailu a galerii.

### Kde je kód webu

Aktuální kód je v GitHub repozitáři **TOMODKOL/eshop-4K**, složka `web/`. Před úpravou vždy vycházet z něj a novou verzi tam zase uložit.
- `web/css/4K-novy-detail-produktu-v8.2.css`: detail produktu nasazený 4. 11. 2026. Vedle něj je novější `v8.2.1`; zda je nasazená, ověřit ve stavovém dokumentu.
- `web/css/4K-vypis-produktu.css`: výpis kategorie. Počet sloupců se nastavuje číslem `--4k-sloupce`.
- `web/skripty/`: skript filtru do Zápatí.
- `web/popisy/`: vzorový popis kola (SuperSix EVO LAB71).
- `web/emaily/`: master šablona, potvrzení objednávky, vratka a newsletter.
- `web/kategorie/`: texty kategorií.
- `web/ODKAZY.md`: odkazy na návrhy a dokumenty.

U Tomáše na Macu leží pracovní kopie ve Stažených (`~/Downloads`, soubory `4K-…`, `popis-…`, `NAHLED-…`). Nápovědu BSSHOP (BSSHOP-KB-5 až 48.pdf) najdeš v OneDrive, `Work/4 KLUCI OD KOL/Dokumenty/BSshop - návod`.

## Design webu

- **Schválený design webu** se záměrně trochu odchyluje od manuálu 2019. Z hotového webu vznikne nový manuál. Cenovky kol vycházejí z designu webu.
- **Design system „4 KLUCI OD KOL“** (https://claude.ai/artifact/6ruuEdMCdEJPMGs4H4khUQ):
  - písma FK Screamer a FK Screamer Slanted na nadpisy, Good Sans na texty
  - originální loga
  - manuál 2019
- **Žádné abstrakce**, pokud o ně někdo výslovně nepožádá. Logo vždy z originálního souboru.
- **Grafické prvky webu:**
  - šipka → z loga v drobečkové navigaci
  - přeškrtnutá cena šikmo
  - žlutý štítek slevy #ffc524 a zelená „skladem“, jen na webu
  - „4 KLUCI → RADÍ“: slovo za šipkou písmem Slanted, obrysem
- **Responzivita:** web musí fungovat na iPhonu i na desktopu.
- **Detail produktu:**
  - bez štítků vlastností
  - Essox jen jako odkaz „Nákup na splátky“
  - „Doporučit známému“ pryč
- **Širší layout** jen spolu s většími náhledy. Čeká se na BSSHOP, bez vlastního obcházení.
- **Detail v8.3** (větší fotka): nasadit až s velkými obrázky od BSSHOP.
- **Manuál 2019:** než z něj něco přenesu na web, nejdřív říct co a proč a počkat na OK.
- **Úvodní stránka:** návrh existuje, čeká se na tým.
- **Návrhy pro tým:** sdílet tlačítkem Share. Připomínky psát jako komentáře, zapracuje je Claude.

## SEO

- **Vstupní analýza:** leden 2026, seo-analyza.vercel.app.
- **Požadavek BSSHOP 164701:**
  1. Odkaz ?print=1 se z detailu odstraní.
  2. Disallow pro tiskové stránky se v robots.txt dočasně zruší.
  3. Po vyřazení tiskových adres z indexu Google (kontrola v Search Console) se Disallow vrátí.
  4. Canonical stránkování kategorií dělá BSSHOP.

  Stav se hlídá jednou týdně.
- **Galerie `/2/` jako WebP:** tlačíme na BSSHOP.
- **EANy 2220… (ASSOS) a 2345… (Cannondale lifestyle):** Google je hlásí, ale nechávají se.
- **Texty kategorií:** bez značek Maloja, 7Mesh, Alé, Sportful, Rapha, GT, Sidi a Veloforte.

## Sortiment a dodavatelé

- **Nové značky:** WHYTE, AMFLOW (modely PR, PX), ISADORE, GOBIK.
- **Klíčové:** Whyte Karve / Karve EVO a Cannondale.
- **Mavic** jen komponenty, **Rapha** jen vzorky.
- **Feedy:**
  - Aspire: B2B API
  - Levelsport: POC RESULT15_POC, LEZYNE RESULT11_LEZYNE; ceny přes mywac.cloud; lyžařské POC ne na web
  - Cyklomax: LAKE
  - Schindler: feed na B2B
- **Bez feedu:** Paul Lange.
- **Dovozci:** Cyklospeciality (Woom, Rascal, FÍHA), Bottico (DT Swiss).
- **Data pro nové karty** se berou z B2B portálů. Přihlášení zadává uživatel.

## Úklid a automatizace

- **Úklid Aspire (!Importy):** karta není ve feedu, není skladem a 12 měsíců neměla pohyb → koš. Karta s pohybem má 12 měsíců ochranu.
- **Úkol „Automatizace“:** pravidelná kontrola, výstupem je přehled ke schválení. Hlídá:
  - texty kategorií proti skladu
  - prázdné kategorie
  - rozbité výpisy
  - zakázané značky
  - karty bez fotky nebo popisu a zdvojené karty
- **Budoucnost:** popisy hromadně z odkazu nebo kódu produktu u výrobce.
- **Odložené karty** (Galfer, Hiplok, Maxxis, Apidura, Sea to Summit, Zipp): připomenout, až se bude dělat daná značka.

## Vyhledávání

- **Plán:** test Elasticsearch za 1 500 Kč měsíčně.
- **Porovnání:** na skutečných dotazech ze Statistik vyhledávání.
- **Synonyma:** navrhnout, nasadit až po OK.

## E-maily

- **Styl:** jednotný (logo_mail_v2.png, zelené ukazatele průběhu).
- **Hotové e-maily:** potvrzení, zpracování, odběr, expedice, storno, odstoupení, vratka.
- **Čeká na BSSHOP:** interní potvrzení.
- **Komu:** pokročilé stavy jen pro objednávky 8810…
- **Editor BSSHOP:**
  - `and` místo `&&`
  - po uložení zavřít a zkontrolovat konec kódu
  - testovat jen v Testovacím režimu, nikdy na skutečných objednávkách
- **Storno** přes Vyřízeno.
- **Zpracování:** jen při vyplněné Poznámce pro zákazníka.
- **Kopie na eshop@** stejná jako zákazníkovi. Pohoda posílá z info@.
- **Číslo zásilky** patří do Poznámky vydané faktury, a to co nejdřív. Jinak robot pošle „Odesláno“ bez čísla.
- **Plán:** Zásilkovna/GLS přes API.
- **Dárkové poukazy:** zatím tištěné, uplatňují se číslem v poznámce.

## Cenovky kol

- **Formát:** A6, čtvrtina A4, kraftový papír, tenké ořezové linky. Tisk jako PDF A4.
- **Design:** „Parametry“, vychází z webu, přeškrtnutí šikmo.
- **Dlaždice podle kategorie:**
  - Silniční a Gravel: Rám, Sada, Převody, Ráfky
  - Horská celoodpružená: Rám se zdvihem, Vidlice se zdvihem, Přehazovačka, Ráfky
  - Horská s pevným rámem: Rám, Vidlice se zdvihem, Přehazovačka, Ráfky
  - Elektro celoodpružená: Rám se zdvihem, Vidlice se zdvihem, Motor, Baterie
  - Elektro s pevným rámem: Rám, Vidlice se zdvihem, Motor, Baterie
  - Elektro gravel: Rám, Motor, Baterie, Sada
  - Dětská: Velikost, Věk, Výška, Počet převodů