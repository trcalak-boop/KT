<?php
/* ts-booking-server – serverová konfigurace projektu. ULOŽTE MIMO WEBROOT (nebo do složky s „Require all denied“)
   a NEDÁVEJTE do gitu (obsahuje heslo SMTP). Vzor zkopírovaný knihovnou; aktualizace ho nepřepíše. */
$private = __DIR__;                                   // např. /<hosting>/private  (vedle /web)
return [
    'timezone' => 'Europe/Prague',                           // časové pásmo provozovny (záznamy, „dnes“)
    'db' => ['dsn' => 'sqlite:' . $private . '/data/rezervace.sqlite', 'autoMigrate' => true],   // MySQL: 'mysql:host=…;dbname=…;charset=utf8mb4', 'user' => …, 'pass' => …
    'configFile' => $private . '/rezervace-config.json',     // konfigurace oboru (z ts-config/ts-booking-core/…)
    'authDir' => $private . '/data/auth',                    // relace a zámek přihlášení administrace
    'cookiePath' => '/',                                     // cesta cookie relace (stejná pro api.php i stránku správy)
    'autoConfirm' => false,                                  // true = rezervace se rovnou potvrdí
    'rateLimit' => ['bookings' => [10, 3600]],               // nejvýš 10 rezervací za hodinu z jedné adresy (otisk IP)
    'minFillSeconds' => 3,                                   // rychlejší odeslání formuláře = robot
    'allowedOrigins' => ['https://www.example.cz'],          // odkud smí přijít rezervace (prázdné = nekontrolovat)
    'retentionDays' => 730,                                  // anonymizace osobních údajů po termínu (bin/cron.php)
    'mail' => [                                              // ts-mailer-php; bez 'from' se e-maily neposílají
        'transport' => 'log', 'logFile' => $private . '/data/mail.log',   // TESTOVACÍ REŽIM 1: e-maily se jen zapisují do souboru
        'redirectTo' => 'testovaci-schranka@example.cz',      // TESTOVACÍ REŽIM 2: s 'transport' => 'smtp' dostane vše jen tato adresa
        'from' => 'rezervace@example.cz', 'fromName' => 'Rezervace', 'domain' => 'example.cz',
        // 'smtp_host' => 'smtp.…', 'smtp_port' => 587, 'smtp_secure' => 'tls', 'smtp_user' => '…', 'smtp_pass' => '…',
    ],
    'notify' => [
        'business' => 'Název provozovny', 'staffEmail' => 'provoz@example.cz', 'currency' => 'Kč',
        'cancelUrl' => 'https://www.example.cz/rezervace-zrusit.html?id={id}&token={token}',
        'adminUrl' => 'https://www.example.cz/sprava-rezervaci.html',
    ],
    'install' => [                                           // jednorázový webový instalátor (TsBookingInstaller); po instalaci může zůstat, soubor ve webrootu se smaže
        'codeHash' => '',                                    // password_hash instalačního kódu – nikdy ne kód samotný
        'until' => '2026-01-01',                             // po tomto dni instalátor nefunguje
        'testRecipient' => 'testovaci-schranka@example.cz',  // kam jde zkušební e-mail
    ],
];
