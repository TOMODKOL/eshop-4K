# client.4kluciodkol.cz

Jedna aplikace pro zákazníky i pro nás:

- **pro zákazníky:** vrácení, výměna velikosti a reklamace zboží z e-shopu. Později stav zakázky ze servisu a rezervace.
- **pro prodejnu a servis:** přehled vratek a reklamací. Později servisní zakázky s kalendářem.

Moduly se zapínají v `config/config.php` (`modules`). Nedokončený modul nikdo neuvidí.

Stav 20. 11. 2026: hotový základ a modul **Vratky** (fáze 1). Zatím běží jen na zkoušku, na serveru ještě není.

## Co umí modul Vratky

**Zákazník:**
- Najde objednávku podle e-mailu a čísla objednávky (8810…) nebo faktury.
- Když číslo nezná, pošleme mu odkaz e-mailem. Odkaz platí 24 hodin.
- U každé položky zvolí vrátit, vyměnit velikost nebo reklamovat, včetně počtu kusů.
  - Doprava a platba se nenabízí.
  - Kusy, které už jsou ve vratce, nejde vybrat znovu.
- Vrácení: zvolí důvod. Výměna: zvolí velikost (podle velikosti v názvu). Reklamace: popis vady a až 6 fotek.
- Doprava: Z-BOX, výdejní místo nebo osobně na prodejně. Poštovné se strhává jen u čistého vrácení zaslaného Zásilkovnou.
- Číslo účtu (jen když se vrací peníze), telefon, poznámka, souhlas s obchodními podmínkami.
- Dostane číslo vratky, podací kód Zásilkovny a stránku se stavem. E-mailem dostane potvrzení a pak e-mail při každé změně.
- Lhůty se počítají z data faktury:
  - vrátit nebo vyměnit lze 21 dní (14 dní + rezerva na doručení),
  - reklamovat lze 24 měsíců.

  Obojí jde změnit v nastavení.

**Prodejna (`/admin`):**
- Každý kolega má vlastní přihlášení. Správce zakládá kolegy v části Kolegové.
- Přehled vratek s počty podle stavu, filtrem a hledáním.
- Detail vratky: položky, fotky vady, zákazník, peníze a lhůta reklamace (30 dní).
- Změna stavu s poznámkou a e-mailem zákazníkovi.
- Ruční vytvoření podacího kódu, když Zásilkovna napoprvé selže.

**Napojení:**
- **Pohoda:** pomocník na KANCL posílá e-shopové faktury na `POST /api/sync/objednavky` (popis formátu je v `app/modules/vratky/Sync.php`).
- **Zásilkovna:** zpětná zásilka s podacím kódem (`createPacketClaimWithPassword`). Zapíná se v nastavení, až bude API heslo.
- **E-maily:** přes SMTP Forpsi ze schránky client@. Dokud není zapnuto, zapisují se jen do `storage/log/mail.log`.

## Nasazení na Forpsi (Webhosting Advanced)

1. Ve Forpsi zapnout PHP 8.x, založit subdoménu `client`, databázi MySQL a SSL. Viz úkol v Todoistu.
2. Nahrát obsah této složky do složky subdomény přes FTP nebo SSH. Složky `app`, `config`, `sql`, `storage` a `bin` jsou zamčené přes `.htaccess`.
3. Zkopírovat `config/config.example.php` jako `config/config.php` a vyplnit:
   - databázi,
   - `app_key` a `sync_token` (`php bin/token.php`, nebo libovolný dlouhý náhodný text),
   - heslo k e-mailu,
   - později API heslo Zásilkovny.

   Hesla vyplňuje jen Tomáš.
4. Otevřít `https://client.4kluciodkol.cz/admin/instalace`. Instalační klíč je `sync_token`. Tím vzniknou tabulky a první správce.
5. Do `storage/` musí mít PHP právo zápisu (fotky, logy).
6. Zapnout `mail.enabled` a zkusit vratku na vlastní e-mail.
7. Zapnout `packeta.enabled`, až bude API heslo.

## Zkoušení na počítači

```
cd client
cp config/config.example.php config/config.php   # nastavit SQLite, viz komentář v souboru
php bin/dev-seed.php                              # tabulky + ukázkové objednávky
php -S localhost:8080 bin/dev-router.php
php tests/unit.php                                # testy logiky
node tests/e2e.js http://localhost:8080 obrazek.jpg vystup/   # celý průchod v prohlížeči
```

## Další fáze

- **Fáze 2:**
  - výměna za jiné zboží: režim výměny na webu a vložení odkazu (`-p{ID}`), data z produktového feedu;
  - zápis reklamací a dobropisů do Pohody (agenda Reklamace, claim.xsd);
  - stav „na cestě“ a „dorazilo“ automaticky podle sledování Zásilkovny.
- **Fáze 3:** platba kartou (GP webpay, čeká se na odpověď GP), případně kredit jako poukaz BSSHOP.
- **Modul Servis:**
  - zakázky a kalendář pro tři servisáky s přetahováním mezi dny a kolegy;
  - později rezervace pro zákazníky.
