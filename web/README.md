# Kód webu 4kluciodkol.cz

Kopie kódu, který jde do BSADMIN. Před každou změnou na webu si ulož zálohu toho, co tam je teď.
Stav nasazení a rozhodnutí jsou v dokumentu „E-shop 4K – stav a rozhodnutí“ (odkaz v ODKAZY.md).

| Složka / soubor | Co to je | Kam patří v BSADMIN |
| --- | --- | --- |
| **Pravidlo** | Soubory v `css/` a `skripty/` jsou **přesná kopie toho, co je teď v BSADMIN** (ověřeno 26. 11. 2026 porovnáním délky a obsahu). Každou změnu na webu hned zapsat sem; staré verze jsou v historii gitu, ne jako další soubory. | |
| `css/4K-detail-produktu-specifikace.css` | Záložka Specifikace a tabulka parametrů pod popisem | Internetový obchod → Stylesheety → „4K – detail produktu“ (pořadí 0) |
| `css/4K-novy-detail-produktu.css` | Nový detail produktu (v8.2.1 z 5. 11. + dlaždice velikostí a šikmá cena 6. 11. + záložky Popis/Tabulka velikostí 26. 11.) | Stylesheety → „4K – Nový detail produktu“ (pořadí 10) |
| `css/4K-popisy-na-mobilu.css` | Popisy na mobilu: fotky v rozbalovacích sadách ve 2 sloupcích | Stylesheety → „4K - popisy na mobilu“ (pořadí 20) |
| `css/4K-black-friday-menu.css` | Black Friday v hlavním menu (po akci vypnout) | Stylesheety → „4K - Black Friday menu“ (pořadí 30) |
| `css/4K-ucet-zakaznika.css` | Účet zákazníka (objednávky, faktury) | Stylesheety → „4K – účet zákazníka“ (pořadí 40) |
| `css/4K-vypis-produktu.css` | Výpis kategorie: filtr, mřížka, řádkový výpis, stránkování | Stylesheety → „4K – výpis produktů“ (pořadí 50) |
| `skripty/` | Části Uživatelského obsahu → Zápatí → 2. obsah → Responsivní, v tomto pořadí: `zapati-zacatek-odkazy-a-bf-popup.html`, `4K-vymena-skript.txt`, `4K-ceny-velikosti-skript.txt`, `4K-ucet-skript.txt`, `zapati-hledani-loader.html`, `4K-filtr-skript.txt`, `4K-radkovy-vypis-nahledy.txt`. Editor BSSHOP při uložení mění `\u00a0` apod. na skutečné znaky – obsah je stejný. | Zápatí – jen se souhlasem Tomáše |
| `popisy/` | Vzorový popis produktu (kolo SuperSix EVO LAB71) | Popis produktu v režimu zdrojového kódu |
| `emaily/` | Master šablona, potvrzení objednávky, vratka, newsletter | Šablony e-mailů BSSHOP / Pohoda |
| `kategorie/` | Texty kategorií (HTML) | Zásoby → kategorie → text |

Testovat na šířce 1440 px (počítač) a 390 px (mobil).
