<?php
// Vzor nastavení. Na serveru zkopírujte jako config/config.php a vyplňte.
// config.php se NIKDY neukládá na GitHub (hesla).
return [
    // Veřejná adresa aplikace (bez lomítka na konci)
    'app_url' => 'https://client.4kluciodkol.cz',

    // Vývoj: true = podrobné chyby. Na serveru vždy false.
    'debug' => false,

    // Databáze. Forpsi: MySQL. Pro zkoušení na počítači jde i SQLite:
    //   'dsn' => 'sqlite:' . __DIR__ . '/../storage/dev.sqlite'
    'db' => [
        'dsn'  => 'mysql:host=DOPLNIT;dbname=DOPLNIT;charset=utf8mb4',
        'user' => 'DOPLNIT',
        'pass' => 'DOPLNIT',
    ],

    // Které části aplikace jsou zapnuté. Nedokončené moduly nechte false.
    'modules' => [
        'vratky' => true,
        'servis' => false,
    ],

    // Tajný klíč aplikace (podpis odkazů v e-mailech). Vygenerujte: php bin/token.php
    'app_key' => '',

    // Tajný klíč, kterým se prokazuje pomocník na KANCL při posílání faktur z Pohody.
    // Vygenerujte: php bin/token.php
    'sync_token' => '',

    'shop' => [
        'name'    => '4 KLUCI OD KOL',
        'web'     => 'https://www.4kluciodkol.cz/',
        'phone'   => '604 998 800',
        'address' => 'U Vlečky 726/5c, 617 00 Brno',
        'hours'   => 'Po–Pá 9–17, So 9–13',
        'bank_account' => '2071163455/8040',   // Oberbank – sem platí zákazníci doplatky (zálohové faktury)
    ],

    'vratky' => [
        // Vrátit (odstoupit od smlouvy) lze 14 dní od převzetí. Datum převzetí neznáme,
        // proto počítáme od data faktury + rezerva na doručení.
        'xorder_prefix' => '991',          // přijaté objednávky v Pohodě pro výměny: 991 + 7 číslic (číslo dává Client)
        'return_days' => 14,               // od převzetí zásilky (datum z Zásilkovny)
        'return_days_from_invoice' => 21,  // odhad, když datum převzetí neznáme (GLS, PPL, osobní odběr)
        // Reklamace do 24 měsíců od data faktury
        'claim_months' => 24,
        // Poštovné za vrácení (Kč s DPH), které se strhne z vrácené částky – jen u čistého vrácení přes Zásilkovnu.
        // Neodečítá se, když si zákazník z objednávky nechá zboží aspoň za hranici dopravy zdarma (free_from níže, rozhodnuto 26. 11. 2026).
        // 79 Kč = zpětná zásilka Zásilkovny stojí 75,02 Kč s DPH (62 Kč bez paliva a mýta), rozhodnuto 20. 11. 2026.
        'return_postage' => 79,
        'cod_fee' => 69,                // dobírka za doplatek při výměně za dražší zboží
        // Při vrácení celé objednávky vracíme i dopravu, ale nejvýš cenu nejlevnějšího způsobu doručení z obchodních podmínek (Kč s DPH).
        // Osobní odběr se nepočítá (není to doručení), doprava zdarma nad hranicí také ne. 79 Kč = GLS výdejní místo od 23. 11. 2026 (do 22. 11. 69 Kč).
        // Dobírka se nevrací. Po změně ceníku dopravy upravit.
        'refund_shipping_max' => 79,
        // Ceník dopravy podle obchodních podmínek (Kč s DPH) a hranice dopravy zdarma, platné od data objednávky.
        // refund_max = nejlevnější způsob doručení v tom ceníku (strop vrácené dopravy při odstoupení od celé smlouvy).
        // Klíč = začátek názvu dopravy na faktuře (malými písmeny); delší klíče mají přednost.
        // Když po částečném vrácení klesne zbytek objednávky pod hranici, odečte se doprava podle ceníku (minus to, co zákazník za dopravu zaplatil).
        'shipping_rules' => [
            ['from' => '2014-01-01', 'free_from' => 2500, 'prices' => [
                'zásilkovna na adresu' => 99, 'zásilkovna sk' => 99, 'zásilkovna' => 69, 'gls čr - výdejní' => 59, 'gls čr - na adresu' => 99,
                'gls sk - výdejní' => 119, 'gls sk - na adresu' => 129, 'ppl' => 119]],
            ['from' => '2026-11-21', 'free_from' => 3000, 'refund_max' => 69, 'prices' => [
                'zásilkovna na adresu' => 129, 'zásilkovna sk' => 99, 'zásilkovna' => 89, 'gls čr - výdejní' => 69, 'gls čr - na adresu' => 109,
                'gls sk - výdejní' => 129, 'gls sk - na adresu' => 149, 'ppl' => 149]],
            ['from' => '2026-11-23', 'free_from' => 3000, 'refund_max' => 79, 'prices' => [
                'zásilkovna na adresu' => 129, 'zásilkovna sk' => 99, 'zásilkovna' => 89, 'gls čr - výdejní' => 79, 'gls čr - na adresu' => 119,
                'gls sk - výdejní' => 129, 'gls sk - na adresu' => 149, 'ppl' => 149]],
        ],
        // Lhůta na vyřízení reklamace (dny)
        'claim_resolve_days' => 30,
        // Kam poslat upozornění na novou vratku (prázdné = nikam)
        'notify_staff' => 'eshop@4kluciodkol.cz',
    ],

    // GP webpay – doplatek výměny kartou. Obchodní místo je stejné jako e-shop (jedno obchodní místo = jeden klíč).
    // Klíče doplní nasad-client.ps1 ze zašifrovaných údajů na KANCL (nastav-udaj.ps1 gpwebpay). Hesla vyplňuje jen Tomáš.
    'gpwebpay' => [
        'enabled'       => false,
        'test'          => true,                     // true = testovací brána test.3dsecure.gpwebpay.com
        'merchant'      => '684240002',              // www.4kluciodkol.cz
        'private_key'   => 'config/gp_private.pem',  // náš soukromý klíč (PEM) – cesta od kořene aplikace
        'key_password'  => '',
        'gp_public_key' => 'config/gp_public.pem',   // veřejný klíč GP (gpe.signing_test.pem / gpe.signing_prod.pem)
    ],

    // Zásilkovna – zpětné zásilky (createPacketClaimWithPassword)
    'packeta' => [
        'enabled'      => false,
        'api_password' => '',
        'eshop'        => '4 KLUCI OD KOL',  // označení odesílatele v klientské sekci Zásilkovny (doplní nasad-client.ps1)
        'endpoint'     => 'https://www.zasilkovna.cz/api/rest',
    ],

    // E-maily zákazníkům. enabled=false: e-maily se jen zapisují do storage/log/mail.log
    'mail' => [
        'enabled'     => false,
        'from'        => 'client@4kluciodkol.cz',
        'from_name'   => '4 KLUCI OD KOL',
        'reply_to'    => 'eshop@4kluciodkol.cz',
        // Kopie každého e-mailu zákazníkovi (bez příloh) – Tomáš chce ze začátku vidět vše (26. 11. 2026). Prázdné = bez kopií.
        'copy_to'     => 'tomas@4kluciodkol.cz',
        'smtp_host'   => 'smtp.forpsi.com',
        'smtp_port'   => 465,
        'smtp_secure' => 'ssl',   // ssl (465) nebo tls (587)
        'smtp_user'   => 'client@4kluciodkol.cz',
        'smtp_pass'   => '',
        // Testovací režim: všechny e-maily (i zákazníkům) jen na tuto adresu. Prázdné = ostrý provoz (od 23. 11. 2026).
        'redirect_to' => '',
        // E-maily zákazníkům jen u vratek a reklamací založených od tohoto okamžiku (starší – i reklamace načtené z Pohody – nic nedostanou).
        'customer_from' => '2026-11-23 23:45:00',
    ],
];
