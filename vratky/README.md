# Vratky a reklamace 4K

Vlastní zákaznický portál pro vrácení, výměnu a reklamaci zboží z e-shopu 4kluciodkol.cz. Vzorem je Retino (uživatelsky příjemný průvodce), zpětná doprava jde přes zpětnou logistiku Zásilkovny.

Stav: návrh obrazovek (19. 11. 2026), zatím se neprogramuje.

## Klikací návrh

Claude Design „Vratky a reklamace 4K“: https://claude.ai/artifact/XKAo9vJMXYc7yLpesTmtSF (soukromý, sdílí Tomáš).
Mobilní verze, styl podle e-mailových šablon 4K (světle šedé pozadí, bílé karty, žlutá #ffc524 na hlavní akce, nadpisy FK Screamer, text Good Sans).

## Průběh pro zákazníka

1. Vyhledání objednávky: e-mail + číslo objednávky (8810…) nebo faktury.
2. U každé položky volba: vrátit / vyměnit / reklamovat.
3. Upřesnění:
   - vrácení: důvod (nesedí velikost, nelíbí se…),
   - výměna: jiná velikost (jen skladem), nebo jiné zboží z nabídky s doplatkem / vrácením rozdílu,
   - reklamace: popis vady, datum zjištění, fotky.
4. Doprava: Z-BOX nebo výdejní místo Zásilkovny (podací kód, nic se netiskne), nebo osobně na prodejně zdarma.
5. Číslo účtu pro vrácení peněz (jen když se něco vrací).
6. Souhrn a odeslání.
7. Podací kód, postup zabalení a stav vratky.

## Rozhodnutí

- Poštovné u vrácení do 14 dnů platí zákazník (strhne se z vrácené částky), u reklamace firma.
- Reklamace jdou přes stejný formulář (do 2 let od nákupu).
- Výměna i za jiné zboží; dražší = doplatek QR platbou (Oberbank, páruje se v Pohodě), levnější = rozdíl se vrátí. Při výměně poštovné zdarma.
- Výběr náhrady (návrh 19. 11. 2026): hlavně tlačítko „Vybrat na e-shopu“ → náš web v režimu výměny (žlutý pruh nahoře + na detailu „Vyměnit za tento produkt“, jen pro zákazníky z portálu; skript v Zápatí BSSHOP jen se souhlasem). Záloha: vložení odkazu na produkt – portál podle `-p{ID}` v adrese najde kartu ve feedu, cenu a sklad bere z feedu. V portálu jen pár podobných kousků ve velikosti zákazníka.
- Kredit jako poukaz na e-shop (BSSHOP KB-50/51): „Peněžní poukaz“ jde založit ručně v BSADMIN; automatické generování jen z objednávky na e-shopu (ENTERPRISE + E1, placené nastavení). Otázky na BSSHOP: založení kupónu importem/API, zbytek u částečně použitého poukazu.
- Hosting: Forpsi Webhosting Advanced pro 4kluciodkol.cz (zaplaceno do 8. 8. 2027), subdoména např. vratky.4kluciodkol.cz.
- Kód aplikace v tomto repozitáři, složka `vratky/`.
- Reklamace se v Pohodě už vedou v agendě Reklamace → portál je tam bude zapisovat přes mServer (schéma claim.xsd), stav se propisuje zpět do portálu (varianta B, později C – automatické spárování platby).
- Přes portál jdou i reklamace zboží koupeného na prodejně (kolega vyplní formulář v přehledu vratek).
- Reklamační protokol v PDF (potvrzení o uplatnění a o vyřízení), hlídání lhůty 30 dní.
- Vrácení: po schválení se v Pohodě připraví dobropis k původní faktuře (zboží zpět na sklad, stržení poštovného).
- Notifikace: stránka se stavem vratky + e-maily při každé změně, přehled vratek pro prodejnu (chráněný heslem).

## Technické poznámky

- Forpsi (ověřeno 19. 11. 2026): Webhosting Advanced, Linux, 100 GB, PHP 7.4 (před nasazením přepnout na 8.x), MySQL v ceně 2 databáze (využito 0), subdomény v ceně 5 (využito 0), SSH lze aktivovat. DNS domény spravuje Forpsi (www → hosting.bsshop.cz), subdoménu vratky.4kluciodkol.cz tedy zřídíme přímo ve Forpsi.

- Zásilkovna API: `createPacketClaimWithPassword` vrátí podací kód (password) a číslo zásilky; zpětná adresa podle fakturační adresy v klientské sekci. https://docs.packeta.com/docs/packet-creation/returns
- Heslo k API jen na serveru (Forpsi), nikdy ve stránce.
- Údaje o objednávkách jsou v Pohodě (mServer jen z kanceláře) – pomocník na KANCL bude aplikaci pravidelně posílat nutné minimum: e-shopové faktury s položkami.

## Ukázka pro kolegy

Klikací ukázka (19. 11. 2026): https://claude.ai/artifact/FBNQFKmE2XDRg9yTAHqb9p. Zdroj je `vratky/ukazka/app.html`, písma a loga do něj vkládá `build.py`. Data jsou vymyšlená, nic se neodesílá a stav vratek se ukládá jen v prohlížeči.

## Otevřené

- Platba doplatku kartou: GP smlouva má e-commerce obchodní místo 684240002 pro www.4kluciodkol.cz (karty, Apple/Google Pay, platební tlačítka). Dotaz na GP, zda ho smí použít i portál na vratky.4kluciodkol.cz s vlastním klíčem GP webpay, odeslal Tomáš 20. 11. 2026, čeká se na odpověď. Do té doby doplatek QR platbou.

- Cena zpětné zásilky podle ceníku Zásilkovny.
- Verze pro počítač.
- Zjistit, jak se dnes vyplňuje agenda Reklamace v Pohodě (pole, číselné řady, stavy) – přes mServer na KANCL.
- Ukázky e-mailů a obrazovka Přehled vratek do návrhu.
