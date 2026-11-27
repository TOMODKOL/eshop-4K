# 4K - univerzální (BSADMIN, Správce importů)

CSV, oddělovač `;`, uvozovky `"`, UTF-8, 1. řádek = názvy sloupců. Párování podle Kódu.
Všechna pole mají Mazání = Nikdy: prázdná buňka ani chybějící sloupec nic nesmaže. Stačí poslat jen sloupce, které se mění.
Definice se vkládá v BSADMIN přes „Přidat ze schránky“ (obsah souboru 4K-univerzalni.xml).

| Sloupec CSV | Pole BSADMIN |
|---|---|
| Kod | Kód (párování) |
| Nazev | Název POHODA CZ |
| KratkyNazev | Název krátký CZ |
| DlouhyNazev | Název CZ |
| KratkyPopis | Krátký popis CZ |
| Popis | 1. Eshop popis CZ |
| Geometrie | 2. Eshop popis CZ |
| Velikosti | 3. Eshop popis CZ |
| Popis4 | 4. Eshop popis CZ |
| Popis5 | 5. Eshop popis CZ |
| Obrazky | Obrázky (nahradí všechny) |
| PopisyObrazku | Popisy BS obrázků CZ |
| KlicovaSlova | Klíčová slova pro hledání CZ |
| SeoTitulek | SEO Titulek CZ |
| SeoPopis | SEO Popis CZ |
| SeoKlicovaSlova | SEO Klíčová slova CZ |
| SeoOdkaz | SEO Odkaz CZ |
| Vyrobce | Výrobce |
| EAN | EAN |
| Hmotnost | Hmotnost |

## Spouštění (od 25. 11. 2026)
- Robot **„Automatický import - 4K univerzální“** (Nastavení robotů, Id 72): importér 4K - univerzální, jen Aktualizovat změněné, zdroj Z webu = `https://raw.githubusercontent.com/TOMODKOL/eshop-4K/main/web/importy/4K-univerzalni.csv`, sledování změn → C:\IPODNIK\BSSHOP\Importy\4K-univerzalni-zmeny.csv a e-mail tomas@4kluciodkol.cz.
- Úloha **„Automatický import 4K univerzální“** (Nastavení automatických úloh, fronta 2, Ruční spouštěč).
- Spuštění: Roboti → Ovládání robotů → 2. fronta → Automatická úloha „Automatický import 4K univerzální“ → Spustit (běh ~1 min).
- Postup: do `4K-univerzalni.csv` dát řádky (Kod + jen měněné sloupce) → push → Spustit → kontrola. Soubor je veřejný: nedávat ceny ani interní data.

## Složka Importy (od 27. 11. 2026)
- Robot **„Automatický import - 4K import (FTP Importy)“** (Id 77, úloha #19, každých 15 min): importér 4K - univerzální, zdroj `https://cdn.4kluciodkol.cz/data/user-content/Obr%C3%A1zky/Eshop/Importy/import.csv`, změny → C:\IPODNIK\BSSHOP\Importy\4K-import-ftp-zmeny.csv.
- Soubor `import.csv` do OneDrive `Pomocnik\Fotky\Importy` (Kod + libovolné sloupce z tabulky); KANCL (nahraj-fotky.ps1) ho nahraje na FTP, po 45 min vyprázdní.
- Definici nikdy neměnit (20 aktivních polí, Mazání Nikdy).

## GLS
- Robot „Automatický import - 4K nadrozměrné (GLS)“ (Id 73, loopback, jen Aktualizovat změněné), úloha „Automatický import 4K nadrozměrné (GLS)“ fronta 2, denně 1:15.
