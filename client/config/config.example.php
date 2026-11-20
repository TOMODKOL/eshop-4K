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
    ],

    'vratky' => [
        // Vrátit (odstoupit od smlouvy) lze 14 dní od převzetí. Datum převzetí neznáme,
        // proto počítáme od data faktury + rezerva na doručení.
        'return_days_from_invoice' => 21,
        // Reklamace do 24 měsíců od data faktury
        'claim_months' => 24,
        // Poštovné za vrácení (Kč), které se strhne z vrácené částky.
        // null = zatím neznámé, zákazníkovi se ukáže „dle ceníku Zásilkovny“.
        'return_postage' => null,
        // Lhůta na vyřízení reklamace (dny)
        'claim_resolve_days' => 30,
        // Kam poslat upozornění na novou vratku (prázdné = nikam)
        'notify_staff' => 'info@4kluciodkol.cz',
    ],

    // Zásilkovna – zpětné zásilky (createPacketClaimWithPassword)
    'packeta' => [
        'enabled'      => false,
        'api_password' => '',
        'eshop'        => '',      // označení odesílatele v klientské sekci Zásilkovny
        'endpoint'     => 'https://www.zasilkovna.cz/api/rest',
    ],

    // E-maily zákazníkům. enabled=false: e-maily se jen zapisují do storage/log/mail.log
    'mail' => [
        'enabled'     => false,
        'from'        => 'client@4kluciodkol.cz',
        'from_name'   => '4 KLUCI OD KOL',
        'reply_to'    => 'info@4kluciodkol.cz',
        'smtp_host'   => 'smtp.forpsi.com',
        'smtp_port'   => 465,
        'smtp_secure' => 'ssl',   // ssl (465) nebo tls (587)
        'smtp_user'   => 'client@4kluciodkol.cz',
        'smtp_pass'   => '',
    ],
];
