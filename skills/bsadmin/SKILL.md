---
name: "bsadmin"
description: "E-shop 4kluciodkol.cz (BSSHOP/BSADMIN): karty a mastery, CSV importy, názvy, parametry, popisy produktů, obrázky, CSS a design webu, SEO, e-maily, vyhledávání, feedy, koš. Použij při čemkoli kolem webu a BSADMIN."
---

# BSADMIN a e-shop 4kluciodkol.cz

E-shop běží na BSSHOP; BSADMIN je program na vzdáleném serveru iPodnik (ne webová administrace). Data karet pocházejí z Pohody (viz skill **pohoda**). Je jeden e-shop s jednou jazykovou mutací. Podpora BSSHOP je pomalá, takže co jde, řešíme sami.

### Co patří sem a co do skillu pohoda
- **Sem (bsadmin):** vše, co vidí zákazník na 4kluciodkol.cz a jak se tam dostane – názvy karet, kategorie (členění SKLAD/Eshop/… z pohledu webu), hodnoty parametrů a filtry, mastery, popisy, fotky a videa, CSV importy, ovládání BSADMIN, CSS a design, SEO, e-maily, vyhledávání, koš, cenovky (vycházejí z designu webu).
- **Do pohoda:** doklady, banka, dodavatelé a jejich faktury/proformy, mServer, XML a SQL. I když jde o kartu pro web, technika zápisu do Pohody (XML pro kartu, členění, parametr, ID polí) je ve skillu pohoda.
- **Překryv:** pravidlo je jen v jednom skillu, druhý na něj odkazuje jednou větou. Nové pravidlo zapsat podle toho, KDE se provádí (v BSADMIN / na webu vs. v Pohodě).

**Než začneš pracovat na webu, přečti dokument „E-shop 4K – stav a rozhodnutí“** (https://claude.ai/artifact/7DcPoZeGC2Z7UcDWVkGNCu, Claude Docs). Je v něm aktuální stav, rozhodnutí a otevřené úkoly. Když se něco rozhodne, dopiš to tam.

Podklady jsou ve firemním OneDrive `Pomocnik\Podklady pro Claude`, na počítači KANCL v `D:\User\OneDrive - 4 KLUCI OD KOL s.r.o\Pomocnik\Podklady pro Claude` (stará složka `4 KLUCI OD KOL\AI` od 19. 11. 2026 neexistuje):
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
- **Čekání.** Čas, kdy běží synchronizace, využít na jinou práci a výsledek zkontrolovat později. Robot (synchronizace) se spouští každých 30 minut (interval mezi běhy, ne délka běhu) – změny jsou na webu až po dalším běhu.
- **Kontroly webu.** Kontrolovat bez umělého zpomalování. Seznamy karet ale zjišťovat z exportu v BSADMIN, ne hromadným procházením webu (to už způsobilo chyby 503/504).
- **Zásada.** „Méně je více“: web má být přehledný a jednoduchý.
- **Vyprodané karty.** Nikdy je nemazat. Karty i master se přesunou do **koše** (viz Postup: vyřazení do koše). Internet se nevypíná. Adresa produktu dál funguje a web nehlásí 404.
- **Nulový sklad** (náš ani dodavatele) neznamená, že výrobek skončil.
- **Karty TEST…** jsou testovací sedla, která půjčujeme zákazníkům.
- **Stejný kód na více kartách.** Kód bývá ve skladě SKLAD, KOMIS, SERVIS, Cyklospeci i Import. Přejmenování platí pro všechny karty. Kopie v „Koš-sklad“ se neřeší.
- **Text do vzdálené plochy** se vkládá přes schránku: na Macu Cmd+C, ve Windows Ctrl+C, na serveru vždy Ctrl+V. Při ovládání počítače: zapsat text do schránky Macu a ve Windows App vložit Cmd+V (funguje, ověřeno 16. 11. 2026). Psaní po znacích je pomalé a dlouhá pole se zasekávají.
- **Ovládání z KANCL (Připojení ke vzdálené ploše, 19. 11. 2026).** Text s diakritikou se při psaní posílá přes schránku a vzdálená plocha může vložit starý obsah schránky serveru (stalo se: do názvu se vložil celý popis). Do polí psát jen ASCII (bez háčků, „-“ místo „–“) a po vložení vždy zkontrolovat. Obsah z jedné karty do druhé kopírovat uvnitř serveru (Ctrl+A, Ctrl+C v editoru kódu, Ctrl+V v cílové kartě).
- **Licence BSADMIN.** Najednou běží jen omezený počet instancí. Hláška „Je spuštěno příliš mnoho instancí aplikace BSADMIN“ (uvádí uživatele, např. 4KPavlO, 4KKrcalS) → požádat uživatele, ať jednu zavře. Po práci BSADMIN vždy zavřít. Přihlášení Ota Hliník je bez hesla (jen OK).
- **Spuštění BSADMIN na serveru:** vyhledávání v liště vzdálené plochy → „BSADMIN SQL“ → Enter.
- **Složka Pomocnik na serveru.** Ve Windows App (připojení 4K – Šárka, Folders) je přesměrovaná firemní OneDrive složka Pomocnik, čtení i zápis. Na serveru je jako `\\tsclient\Pomocnik` (Tento počítač → „Pomocnik na Tomas-MacBook“). CSV k importu a exporty z BSADMIN se ukládají přímo tam. Funguje jen v relaci připojené z jeho Macu.
- **Export z BSADMIN.** V Zásobách pravé tlačítko → „Export zobrazených produktů“. Soubor se uloží do Stažených na serveru. Odtud se přenese přes sdílenou schránku nebo složku vzdálené plochy.
- **Návody.** Příkazy a postupy psát pro Mac i Windows (kolega používá Windows).

## Zavedení nové značky nebo kategorie – povinný kontrolní seznam

Platí pro každé větší zavedení (nová značka, přestavba kategorií, stovky karet – např. zapletená kola Mavic/Zipp 11/2026). Na začátku si seznam vypsat jako úkoly. Hotovo je až tehdy, když je každý bod splněný a ověřený na webu, nebo výslovně odložený s důvodem. Uživateli vždy předat celý stav seznamu, ne jen hotovou část. Rozhodnutí o struktuře a o Internetu dělá uživatel.

1. **Struktura kategorií:** ukázat strom a počet kategorií a počkat na OK.
2. **Nahrávat na eshop** u každé nové kategorie (BSADMIN → Zásoby) – bez toho kategorie na webu není. Úkol se seznamem kategorií do Todoistu.
3. **Názvy karet** podle pravidel (typ – značka – model – …), bez roku. V Pohodě i v BSADMIN (Název krátký CZ, Název CZ) – CSV názvů patří do importního úkolu.
4. **Internet:** které karty mají být na webu – rozhodne uživatel.
5. **Mastery a varianty:** co se seskupí (přední/zadní/sada, velikosti, barvy). Filtr pro uživatele připravit.
6. **Parametry:** vyplnit všechny hodnoty, které známe – i ty, které už jsou v popisu (hmotnost…). Jednotné hodnoty.
7. **Parametrický filtr:** zapnout v každé nové kategorii (Editor kategorií → Obsah na stránce) a vybrat parametry.
8. **„Zobrazit produkty“** u kategorií, které mají karty i podkategorie.
9. **Popisy produktů:** podle skillu popisy-produktu, včetně kontroly obsahu (fotky po modelech, všechny obrázky dostupné na CDN, kontakt, O značce). Kód masteru i variant.
10. **Krátký popis a Klíčová slova pro hledání** v CSV.
11. **Text kategorie a SEO** (titulek, meta popis) – hlavní kategorie, značky a řady, u ostatních aspoň titulek a meta popis.
12. **Video výrobce**, pokud ho má k většině produktů (jinak vynechat).
13. **Kontrola na webu po synchronizaci:** drobečková navigace (chyba Archiv), sitemap, filtr, mobil.

## Karty, mastery, seskupení

- **Tvorba masteru.** Master vytváří uživatel sám: označí karty → **Seskupit položky**. Já pomáhám hlavně se **správným filtrem** karet.
- **Přidání karet k existujícímu masteru.** Označit existující master (i se skupinou) a nové karty → Seskupit. Jinak vznikne nový master.
- **Checkbox Internet** je v detailu karty (záložka Hlavní, dole), ne v gridu.
- **Importy textu jdou do masteru I do všech variant.** Popis, krátký popis, názvy i tabulka velikostí: CSV obsahuje řádek pro kód masteru a řádek pro každou variantu, všechny se stejným textem (názvy variant s velikostí). Master si text z variant NEPřevezme a stránka webu často zobrazuje právě master (18. 11. 2026: Topstone Carbon 1 Lefty AXS zůstal bez videa, protože v CSV byla jen varianta 00100033-54). Platí i obráceně: jen master nestačí, varianty mají vlastní stránky.
- **Chyba s kategorií Archiv.** Po seskupení někdy master spadne do „Sklad pro vyřazené produkty\Archiv“. Na webu pak chybí drobečková navigace a master není v sitemapě. Oprava: v Zásobách přetáhnout master do stejné kategorie jako varianty a zkontrolovat, že Umístění ukazuje [hlavní karta].
- **Výběr velikosti.** Když varianta nemá parametr Velikost (nebo ho má jinak než ostatní), web místo výběru velikosti ukáže seznam celých názvů.
- **„Související produkty“** nepoužíváme.
- **Nové karty z feedů** padají do Import / !Importy. Popis připravit dřív, než je kolega přesune na e-shop.
- **Fotky galerie (pole Obrázky)** se dávají na varianty, master je převezme. To platí JEN pro fotky galerie, ne pro texty (viz výše).

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
- **Kontrola při zpracování zboží.** Názvy vždy zkontrolovat a opravit, i u karet z feedů (koš, !Importy mají název bez typu).
- **Vzor:** „helma POC Kortal Race MIPS Hydrogen White/Uranium Black Matt M/L“, „vesta POC M's Enthral Gilet Uranium Black S“, „duše Mitas AV35 14x1,50-2,10“. Typ malými, ZNAČKA velkými.
- **Typy:** helma, dětská helma, lyžařská helma, brýle, lyžařské brýle, vesta, chránič páteře, čepice…
- **Velikosti z feedu:** MLG → M/L, XSS → XS/S, XLX → XL/XXL, SML → S, LXL → L/XL, ONE → vynechat.
- **Model a barva** anglicky, jak je používá výrobce nebo dovozce. Názvy LAKE:
  - pánské: `tretry LAKE <model> <barva> <NARROW|REGULAR|WIDE> <velikost>`
  - dámské: `dámské tretry LAKE CX333 Women <barva> <velikost>`
  - master bez velikosti
- **AMFLOW.** „AM…“ u KROSS je značka **AMFLOW**, nikdy ne KROSS. Název `kolo AMFLOW <model> <kolo> <barva> <velikost>`, např. „kolo AMFLOW TL Carbon 27,5 béžová S/M“. Kategorie: Elektrokola/Horská celoodpružená/Amflow/PX, PR, TL; Elektrokola/Městská/Amflow/UE (od 17. 11. 2026).
- **POC VPD Air Vest** je „chránič páteře“.
- **Ložiska a díly (Enduro):** typ výrobku česky, zbytek názvu stejnými hodnotami jako ve filtrech (anglicky). Název technologie výrobce (Maxhit, TorqTite, XD15, Directline…) do názvu patří, lidé podle ní hledají. Technologie se musí vysvětlit i v popisu (vlastní odstavec s obrázkem). Ložiska se jmenují jen „ložisko ENDURO 6903 LLB 17x30x7 mm“, ne „ložisko do náboje“. Jedinou výjimkou je „ložisko do hlavového složení“.
- **Členění ložisek** (od 16. 11. 2026) je podle typu jako u Enduro:
  - Ložiska/: Ložiska do nábojů, Ložiska do odpružení, Jehlová ložiska do tlumičů, Ložiska do hlavového složení, Ložiska do středového složení, Miniaturní ložiska, Sady ložisek do středů, Kuličky, Díly ložisek
  - Středy/: Díly středového složení, Redukce středového složení
  - Nářadí/: Nářadí na ložiska a středy
- **URL** je název + `-p{ID}`. Přejmenování URL změní, ale stará adresa se přesměruje podle ID.
- **Hledací slova** patří do **Klíčová slova pro hledání CZ**. Pole „SEO Klíčová slova CZ“ je jen meta tag.

## Internetové parametry (hodnoty pro web)

Zápis a změna parametrů přes XML a fixace cen: viz skill pohoda. Tady jsou jen pravidla, jak mají hodnoty vypadat kvůli webu.

- **barva, ID 3:** česky, jedna hlavní barva (kvůli filtru).
- **Velikost, ID 5:** u obuvi jen číslo (42,5), jinak velikost jako na kartě (M, S/M, L/XL, Regular).
- **Šířka, ID 52:** seznam Narrow / Regular / Wide.
- **Seznamový parametr:** každá položka musí mít vyplněný Popis. Prázdný popis shodí celou Synchronizaci zásob. Kontrolovat log v Ovládání robotů (řádky Chyba).
- **Nové parametry raději jako textValue.** Stávající parametry (Velikost, Sezóna…) jsou textové a filtry na webu fungují. Textový parametr nemá problém s prázdným Popisem položek seznamu.
- **Popis parametru = popisek na webu.** BSSHOP v tabulce parametrů i ve filtru ukazuje Popis z Pohody, ne název. Do Popisu jen čistý název parametru.
- **Filtr v kategorii se nezapne sám.** Editor kategorií → kategorie → záložka Obsah na stránce → zaškrtnout „Zobrazit parametrický filtr“ → Nastavení parametrů → zaškrtnout „Zobrazit v parametrickém filtru“ u vybraných parametrů → OK. Každá kategorie zvlášť (dělalo se u plášťů i u Enduro). Po nových parametrech v Pohodě: BSADMIN → Internetové parametry → Načíst internetové parametry.
- **Jednotné hodnoty.** Stejná hodnota musí být napsaná stejně (bez překlepů a předpon jako „Material:“), jinak je ve filtru dvakrát.
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
- **POZOR – Název POHODA CZ.** Když je pole aktivní a CSV nemá sloupec Nazev, import zapíše prázdný název a BSADMIN ho pošle do Pohody. 16. 11. 2026 tak zmizel název 1 108 kartám Enduro (opraveno XML). Proto: buď CSV vždy obsahuje sloupec Nazev, nebo je pole vypnuté. U každého CSV výslovně uvést, která pole mají být aktivní.
- **Import Obrázků nahradí stávající obrázky.** Při doplňování musí CSV obsahovat i ty, které už na kartě jsou.
- **Zdvojené karty.** Import podle Kódu zapíše jen do jedné z nich (první nalezené, např. do KOMIS) a web může ukazovat jinou (Cyklospeci). Před importem zjistit, jestli kód není na více kartách. Po importu ověřit web; kartu, kterou web ukazuje, opravit ručně: v Zásobách filtr Kód → u každé karty Umístění ukáže sklad → otevřít kartu s novým popisem, 1. popis → Zobrazit → Zdrojový kód → Ctrl+A, Ctrl+C → zavřít křížkem → otevřít druhou kartu → Zdrojový kód → Ctrl+A, Ctrl+V → Uložit → zavřít → pravé tlačítko v mřížce → **Uložit všechny změny** (ověřeno 19. 11. 2026 na 00100033-54). Tlačítko OK v okně karty okno nezavře, zavírá se křížkem. Řádek se změnou je kurzívou s červenou ikonou.
- **Velikost CSV.** Soubor 21 MB se nenaimportoval, soubory do 5 MB prošly. Dělit na soubory do 4,5 MB (hranice mezi kartami jednoho masteru).
- **Změna souboru po předání.** Když CSV po předání uživateli upravím, musí ho naimportovat znovu. Vždy to napsat do úkolu v Todoistu.
- **Po importu** se změna na web dostane až robotem (viz Roboti). Ověřit na webu, ne jen v BSADMIN.
- **Kontrola před importem.** (1) Kódy s mezerou nebo tabulátorem: SQL filtr `SELECT ID FROM SKz WHERE CHARINDEX(CHAR(9),IDS)>0` – import kód ořízne a zapíše do duplicitní karty v jiném skladu (24. 11. 2026: 6 chráničů POC; 29 tabulátorů v kódech odstraněno, uživatel to schválil, Pohodu to neovlivní). Oprava: v detailu karty pole Kód → End → Backspace → v mřížce pravé tlačítko → Uložit všechny změny. (2) Duplicitní kódy (Zdvojené karty výše). (3) Podklad z exportu Zásob, ne z webu: master + všechny varianty včetně těch s vypnutým Internetem.
- **Kontrola po importu.** Nový export Zásob (stejný filtr `[Id Virtuální] In (…)`) a porovnat s CSV – hlavně Obrázky u všech variant a že se zapsalo do správného Id (ne do kopie ve skladu Import/KOMIS). V náhledu importu (Rozšířený mód) zkontrolovat Id spárovaných karet. Řádky „Obnovené“ = karta v koši/archivu – neoznačovat, import by ji vrátil.
- **Ovládání importu.** Soubor se vybírá až v Import → Načíst data → Zdroj dat ze souboru; cesta k počítači uživatele `\\tsclient\C\Users\<uživatel>\Downloads\…`. Název souboru psát po 9 znacích (vkládání přes schránku ho rozbije). Dotazy BSADMIN „není výrobce“ / „cena nulová“ jsou jen varování – nemapovaná pole se nepřepíší. Aktivní pole se mění ve Správci importů (filtr sloupce Aktivní: 2 kliky = jen zaškrtnutá), pak Uložit.
- **Pohoda obrázky** jsou v BSADMIN jen pro čtení. Mění se v Pohodě.

### Loopback import (vlastní karty → vlastní karty)
- Přidat další… → Loopback. Párovat podle **Pomocný párovací kód <-> id** a v Základním mapování aktivovat **Pomocný párovací kód (import) = sloupec `id`**. Bez toho jsou všechny položky „Nevalidní“.
- Vstupní pole: `skupina_cesta` (Sklad|Eshop|Komponenty|…), `vetev1–7`, `priznak0–9`, `neaktualizovatCeny` aj. Matematický filtr bere pole holým názvem, např. `Obsahuje(skupina_cesta, "|Velké pumpy", …)` (Obsahuje rozlišuje velikost písmen).
- Před ostrým během: Import → Druh ceníku = import → Načíst data → přehled (počty rozdílných) → filtr jen Rozdílné → Označit vše → Aktualizovat. Hlášky „není výrobce“ / „nulová cena“ jsou obecné, nemapovaná pole se nemění.
- „Smazané skladem“ = karty v koši (loopback koš nenačítá); Aktualizovat se jich netýká.
- Import **4K - nadrozměrné (GLS)** (24. 11. 2026) nastavuje jen příznak „Pouze GLS na adresu“ pro Zapletená kola (Silniční, MTB, Gravel, E-bike, Ráfky), Velké pumpy, Trenažery. Zatím se pouští ručně.
- Nové volitelné parametry z Pohody (VPr…) BSADMIN uvidí až po synchronizaci parametrů (Nastavení aplikace, expertní → Synchronizace internetových parametrů a E1 parametrů → Synchronizovat parametry) a restartu.

## Licence a automatické úlohy (z e-mailů BSSHOP 2019–2026)
- Licence **BSSHOP ENTERPRISE + BSADMIN** koupena 06/2021 (faktura 215500878, na dobu neomezenou). Moduly: responzivní šablona, Zásilkovna, GLS parcel, Smartform, platby (ČSOB → GP webpay), Essox, robot Nepoužívané obrázky, Facebook Conversion API, Server Side Tracking (do 2025). Podpora a aktualizace placené ročně, nyní do **14. 5. 2027**. Přídavná licence BSADMIN 4 200 Kč + 1 200 Kč/rok (úkol 164603).
- **Roboty a automatické úlohy si od 25. 11. 2026 upravujeme a zakládáme sami:** BSSHOP zpřístupnil agendy Nastavení robotů a celé Nastavení automatických úloh (na vlastní riziko). Podporu BSSHOP nežádat, oprava trvá aspoň měsíc.
- **Univerzální import „4K - univerzální“** (25. 11. 2026): jedna definice CSV importu pro všechny textové a datové importy. Robot pro něj běží jen na vyžádání jedním klikem (Ovládání robotů → Spustit), ne v intervalu.
- **Automatické úlohy jsou součástí ENTERPRISE.** Dříve je u každého nového importu připravil technik BSSHOP („roboti jsou připravení ve frontě, stačí nastavit interval a zaktivnit“, úkoly 81696 Cyklomax, 81858 LevelSport, 81859 SportImport, 98745 Nassa). Interval a Aktivní pak nastavujeme sami v Roboti → Nastavení automatických úloh. Požadavek do Zákaznického centra formulovat: „připravte robota pro import X ve frontě B (importy)“.
- Roboti běží ve službě BSSHOP na serveru iPodniku (ne v klientu BSADMIN); služba je vypnutá 2:00–4:00 kvůli zálohám. Synchronizace zásob každých 30 min, Eshop objednávky každých 10 min, importy dodavatelů ve frontě B.
- E1 parametr „Neaktualizovat ceny“ (VPrBsNeaktCeny) se edituje jen v Pohodě; skript ho přenáší do BSADMIN každých 30 min a před importy (úkol 99119).
- Koš: do koše posílá jen import Aspire – Produkty u nevalidních (sklad 0 a dodavatel 0 / mimo feed); 4/2024 omylem přesunul všechny karty Aspire (úkol 122121).
- Import Nassa: jednou vyplněný parametr už nemění a seskupení nemění; nové varianty seskupovat ručně (úkol 102124).

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
- **Feedy a popisy:** Aspire nepřepíše popis, který byl jakkoli upraven. Při našem importu dostane master i každá varianta stejný popis (v CSV zvlášť řádek pro každý kód).
- **Tabulka parametrů BSSHOP** pod popisem je skrytá přes CSS.
- **Konec popisu:** žlutý box s poradenstvím (604 998 800, adresa, otevírací doba). Nadpisy FK Screamer.

### Postup vložení popisu

1. Uložit zálohu původního kódu.
2. Popis přepnout do zdrojového kódu a vložit nový.
3. Uložit, zavřít, znovu otevřít a zkontrolovat konec kódu.
4. Zkontrolovat na počítači i v telefonu.

Větší množství popisů najízdí importem CSV.

### Obrázky a videa
- **Obrázky v popisech** jsou na vlastním FTP ve složce `Obrázky/Eshop/<Značka>`, ne odkazem na web výrobce. Ručně se nahrávají přes BSADMIN → Internetový obchod → FTP klient (automatické nahrávání viz skill pohoda, úloha Fotky-FTP).
- **Další fotky značky**, jejíž složka už byla nahraná na FTP, dávat do nové podsložky, ne do té už nahrané.
- **Formát:** vždy **WebP**, každý obrázek s vlastním českým **alt** textem a s **width/height** v kódu.
- **Videa** výrobce zkomprimovat a dát na FTP. Produktové video výrobce dát na začátek popisu místo první (hlavní) fotky, aby nebylo schované níž.
- **Fotky v popisu** vždy (ne jen text): dohledat na webu výrobce detailní záběry, fotky na lidech a v akci. Popis nesmí vypadat jako druhá galerie (velká fotka + náhledy). Cíl: hezčí než konkurence a chuť koupit.
- **Výstupy pro web** (CSV k importu, náhledy, ZIPy obrázků) ukládat do podsložek značek v Pomocnik\Fotky (např. Fotky\Sportful).
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
- nepoužívat „temeno“ („stabilní“ zakázané není, viz popisy-produktu)
- košík = držák na láhev; bidon = láhev

### Oslovení
- Slogan „Nasedni a jeď“ a krátké výzvy tykají.
- Vše, co mluví k zákazníkovi, vyká. Text dodavatele s tykáním přepsat na vykání.
- Kontakt: telefon 604 998 800, U Vlčky 726/5c, Brno, Po–Pá 9–17, So 9–13.

## Vzhled webu (CSS)

Kde se co mění:
- **Hlavní CSS:** Nastavení → Nastavení aplikace → expertní nastavení → Vlastní HTML → Všechny stránky → Vlastní CSS styly. Nic nemazat. Naše bloky označovat `/* 4K – … */`.
- **Stylesheety:** Internetový obchod → Stylesheety. Šest našich: „4K – detail produktu“ (0, Specifikace), „4K – Nový detail produktu“ (10), „4K - popisy na mobilu“ (20), „4K - Black Friday menu“ (30), „4K – účet zákazníka“ (40), „4K – výpis produktů“ (50). Vypínají se odškrtnutím Aktivní. V editoru stylesheetu jsou dole tři tlačítka: vlevo „Uložit a zavřít“, uprostřed „Uložit“, vpravo „Zavřít a zahodit změny“ – pozor, ne kliknout vpravo. Po uložení zkontrolovat, že se ve sloupci Délka změnilo číslo.
- **Minifikátor BSSHOP:** vlastní proměnná nesmí začínat číslicí (`--4k-…` rozbije, používat `--k4-…`); znaky mimo Latin-2 (✕, „ “ apod.) v CSS mění na „?“ – ikonky kreslit CSS, v komentářích se jim vyhnout.
- **„4K - popisy na mobilu“** (19. 11. 2026): do šířky 600 px dá v rozbalovacích sadách popisů (.am4k/.wo4k details) samotné fotky (`div > img:only-child`, aspoň 2 vedle sebe) do 2 sloupců, lichou doprostřed. Kód: `web/css/4K-popisy-na-mobilu.css` v repozitáři eshop-4K.
- **Mobil** dělá přes 70 % přístupů. Boční okraj stránky na telefonu (10 px) zatím zůstává, mobilní zobrazení se bude řešit při redesignu webu (rozhodnuto 19. 11. 2026).
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
- **Pravidlo:** `web/css/` a `web/skripty/` jsou přesná kopie toho, co je teď v BSADMIN (srovnáno 26. 11. 2026). Jeden soubor na jeden stylesheet, žádné souběžné verze – staré verze jsou v historii gitu. Každou změnu v BSADMIN hned zapsat do repozitáře a commitnout. Přehled souborů a kam patří je ve `web/README.md`.
- `web/css/4K-novy-detail-produktu.css`: detail produktu (v8.2.1 + dlaždice velikostí 6. 11. + záložky 26. 11.).
- `web/css/4K-vypis-produktu.css`: výpis kategorie (filtr, mřížka, řádkový výpis, stránkování). Počet sloupců se nastavuje číslem `--k4-sloupce`.
- `web/skripty/`: části Zápatí → 2. obsah → Responsivní v pořadí podle `web/README.md`.
- Ověření shody: v editoru stylesheetu Ctrl+A, Ctrl+C, přečíst schránku a porovnat; délka v BSADMIN = počet znaků s konci řádků CRLF.
- `web/popisy/`: vzorový popis kola (SuperSix EVO LAB71).
- `web/emaily/`: master šablona, potvrzení objednávky, vratka a newsletter.
- `web/kategorie/`: texty kategorií.
- `web/ODKAZY.md`: odkazy na návrhy a dokumenty.

U Tomáše na Macu leží pracovní kopie ve Stažených (`~/Downloads`, soubory `4K-…`, `popis-…`, `NAHLED-…`). Nápovědu BSSHOP (BSSHOP-KB-5 až 48.pdf) najdeš ve firemním OneDrive v `Pomocnik\Podklady pro Claude\BSshop - návod` (na KANCL `D:\User\OneDrive - 4 KLUCI OD KOL s.r.o\Pomocnik\Podklady pro Claude\BSshop - návod`, dostupné přes nástroje vzdálené plochy). Kopie je i v Tomášově osobním OneDrive, `Work/4 KLUCI OD KOL/Dokumenty/BSshop - návod`. Než začneš v BSADMIN hledat nastavení naslepo, podívej se nejdřív do návodu.

## Design webu

- **Schválený design webu** se záměrně trochu odchyluje od manuálu 2019. Z hotového webu vznikne nový manuál. Cenovky kol vycházejí z designu webu.
- **Design system „4 KLUCI OD KOL“** (https://claude.ai/artifact/6ruuEdMCdEJPMGs4H4khUQ):
  - písma FK Screamer a FK Screamer Slanted na nadpisy, Good Sans na texty
  - originální loga
  - manuál 2019
- **Žádné abstrakce**, pokud o ně někdo výslovně nepožádá. Logo vždy z originálního souboru.
- **Grafické prvky webu:**
  - šipka → z loga v drobečkové navigaci
  - přeškrtnutá cena šikmo – čára vede zleva shora doprava dolů (ne zleva zdola nahoru)
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

- **Nová kategorie = text a SEO.** Každá nová kategorie potřebuje aspoň titulek a meta popis. Hlavní kategorie, značky a řady potřebují i text kategorie. Je to součást zavedení (viz kontrolní seznam), ne dodatečný úkol.
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
- **SEO úvodní stránky** (title, meta description, keywords): Internetový obchod → Uživatelský obsah. Popis a Klíčová slova jsou vlevo nahoře v sekci SEO, Titulek a Nadpis (H1) hlavní strany vpravo v sekci Titulek stránky. Ukládá se samo po opuštění pole (ověřeno 23. 11. 2026, návod BSSHOP-KB-5). Popis obsahuje hranici dopravy zdarma – při její změně ho upravit.
- **SEO a text kategorie v BSADMIN:** Editor kategorií → kategorie (filtr nahoře vlevo, Enter) →
  - záložka **SEO**: Titulek a Popis (meta description, do ~158 znaků);
  - záložka **Obsah na stránce**: zaškrtnout „Povolit vlastní obsah“ → Upravit obsah → Zobrazit → Zdrojový kód → vložit HTML (formát kat-obsah) → OK.
  - Ukládá se samo při přepnutí. Hláška „Ověření přepsání novějšího záznamu“ → Ne, pak Aktualizovat a zadat znovu.
- **Kategorie se zboží i podkategoriemi:** když jsou karty přímo v nadřazené kategorii (např. Středy, Ložiska, Hlavové složení), musí mít v Obsahu na stránce zaškrtnuté „Zobrazit produkty“, jinak web ukáže jen podkategorie a 0 produktů.

## Sortiment a dodavatelé

- **Nové značky:** WHYTE, AMFLOW (modely PR, PX), ISADORE, GOBIK.
- **Klíčové:** Whyte Karve / Karve EVO a Cannondale.
- **Mavic** jen komponenty, **Rapha** jen vzorky.
- **Zapletená kola (Mavic/Zipp):** hluboká struktura kategorií až na model (použití → značka → řada → model), aby zákazník mohl zůstat u řady nebo jít na konkrétní model. Internet u těchto karet zapíná uživatel sám. Na webu jen aktuální modely podle webů výrobců a modely, které má Aspire skladem (ty dostanou Internet).
- **Feedy:**
  - Aspire: B2B API
  - Levelsport: POC RESULT15_POC, LEZYNE RESULT11_LEZYNE; ceny přes mywac.cloud; lyžařské POC ne na web
  - Cyklomax: LAKE
  - Schindler: feed na B2B
  - Progress Cycle (plán 25. 11. 2026): feed přes robota BSADMIN, jen Giro, Vittoria a Crankbrothers, denně zakládá jen karty, které ještě nemáme. Kódy s předponou PS; stávající karty Progressu překódovat tak, aby se nerozbily feedy Facebook/Google/Heureka.
  - Peak Design (plán 25. 11. 2026): feed OneFive jen pro data (EAN, kódy, názvy, popisy, ceny), dostupnost z něj nebrat; zakládá jen chybějící karty; párovat podle kódu (ne EAN), kód výrobce bez předpony. Nakupuje se od Agory.
- **Bez feedu:** Paul Lange.
- **Dovozci:** Cyklospeciality (Woom, Rascal, FÍHA), Bottico (DT Swiss).
- **Data pro nové karty** se berou z B2B portálů. Přihlášení zadává uživatel.

## Roboti a synchronizace na web

- Změny z BSADMIN (popisy, názvy) přenáší na web robot **Eshop synchronizátor produktů** v úloze „Synchronizace zásob“ (fronta 2, zhruba každých 30 minut). Ceny a sklady mají vlastní roboty.
- **Kontrola:** Roboti → Ovládání robotů → záložka 2. fronta. Odškrtnout Informace, zaškrtnout kompletní log → Filtrovat. Detail chyby je ve spodním panelu (Ctrl+End na konec). Vpravo je čas do dalšího běhu.
- **OutOfMemoryException** (18. 11. 2026 ve 23:19 a 23:37, po velkých importech popisů): robot spadne a na web nejde nic z popisů ani názvů. V 0:08 prošel sám. Když se opakuje, je to pro podporu BSSHOP (paměť služby). Prevence: velké importy po dávkách a mezi nimi nechat robota doběhnout.
- Chyba „Error reading item … Popis2/techspec“ v detailu není chyba konkrétní karty, ale důsledek nedostatku paměti.

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

- **Vlastní vyhledávání:** objednávka Elasticsearch u BSSHOP je zrušená (24. 11. 2026). Vyhledávání (našeptávač + stránka výsledků) dělá Claude vlastním skriptem. Placené AI vyhledávání (Luigi's Box apod.) nechceme.
- **Seskupování variant ve výsledcích jen podle barvy** v celém sortimentu. Jiný model, velikost nebo verze (iPhone Pro × Pro Max, Cytal × Cytal Lite, SuperSix EVO 1 × EVO 1 SL) je samostatný produkt.
- **Ladění:** na skutečných dotazech ze Statistik vyhledávání.
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
- **Doprava podle příznaků karty** (BSADMIN, Příznaky produktu): kola mají „Jízdní kola (A)“ (jen PPL, platí i pro rámy). Nadrozměrné zboží – zapletená kola, ráfky (Zapletená kola → Ráfky), velké pumpy, trenažéry – má „Pouze GLS na adresu (D)“: nejde přes Zásilkovnu a vratky jen vlastní dopravou zákazníka. Příznak se zatím nastavuje ručně u celých kategorií, u nových karet těchto kategorií ho zkontrolovat (24. 11. 2026).
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