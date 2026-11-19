# Kód webu 4kluciodkol.cz

Kopie kódu, který jde do BSADMIN. Před každou změnou na webu si ulož zálohu toho, co tam je teď.
Stav nasazení a rozhodnutí jsou v dokumentu „E-shop 4K – stav a rozhodnutí“ (odkaz v ODKAZY.md).

| Složka / soubor | Co to je | Kam patří v BSADMIN |
| --- | --- | --- |
| `css/4K-novy-detail-produktu-v8.2.css` | Detail produktu – verze nasazená na webu (4. 11. 2026) | Internetový obchod → Stylesheety → „4K – detail produktu“ |
| `css/4K-novy-detail-produktu-v8.2.1.css` | Detail produktu – novější úprava v8.2.1 (ověř v dokumentu, zda je nasazená) | tamtéž |
| `css/4K-popisy-na-mobilu-v1.css` | Popisy na mobilu: fotky v rozbalovacích sadách ve 2 sloupcích (nasazeno 19. 11. 2026) | Stylesheety → „4K - popisy na mobilu“ (pořadí 20) |
| `css/4K-black-friday-menu.css` | Black Friday v hlavním menu na počítači: černý panel, 7 dlaždic s fotkou (ikony /Ikony/bf-*.jpg), žluté názvy, bez podkategorií (v2, 19. 11. 2026, po akci vypnout) | Stylesheety → „4K - Black Friday menu“ |
| `css/4K-vypis-produktu.css` | Výpis kategorie (filtr, 3/4 sloupce – číslo `--4k-sloupce` na začátku) | Stylesheety → „4K – výpis produktů“ |
| `skripty/4K-filtr-skript.txt`, `skripty/4K-vypis-skript.txt` | Skript filtru ve výpisu | Konec Uživatelského obsahu Zápatí (HTML režim) – jen se souhlasem Tomáše |
| `popisy/` | Vzorový popis produktu (kolo SuperSix EVO LAB71) | Popis produktu v režimu zdrojového kódu |
| `emaily/` | Master šablona, potvrzení objednávky, vratka, newsletter | Šablony e-mailů BSSHOP / Pohoda |
| `kategorie/` | Texty kategorií (HTML) | Zásoby → kategorie → text |

Testovat na šířce 1440 px (počítač) a 390 px (mobil).
