<?php
/* ==========================================================================
   ts-ai-consultant – serverová konfigurace. Kopii uložte MIMO webroot (např. private/ai-consultant.php),
   nikdy do gitu. API klíč patří do samostatného souboru (apiKeyFile), ne sem.
   ========================================================================== */
$private = __DIR__;                                   // složka s touto konfigurací (mimo webroot)
return [
    'timezone' => 'Europe/Prague',
    'business' => 'Název studia',
    'contactEmail' => 'info@example.cz',                // v náhradních odpovědích (výpadek, rozpočet, limit)
    'defaultLang' => 'cs',

    /* poskytovatel: 'claude' (oficiální SDK) | 'fake' (zkušební, nikam se nepřipojuje) */
    'provider' => 'claude',
    'apiKeyFile' => $private . '/claude-api-klic.txt',   // jeden řádek s klíčem, práva 0600
    'sdkAutoload' => $private . '/vendor/autoload.php',   // composer vendor s anthropic-ai/sdk + guzzle
    'claude' => [
        'model' => 'claude-opus-5-5',                     // změna modelu = rozhodnutí provozovatele (cena, kvalita)
        'effort' => 'low',                                // krátké odpovědi v chatu
        'timeout' => 30, 'connectTimeout' => 6, 'maxRetries' => 1,
    ],
    'maxTokens' => 1200,                                  // strop výstupu včetně přemýšlení modelu
    'monthlyBudgetUsd' => 10.0,                           // po vyčerpání asistent odpovídá jen náhradním textem s kontaktem
    // 'pricing' => ['claude-opus-5-5' => [4.0, 20.0, 0.40, 5.0]],   // přepis ceníku USD/MTok [vstup, výstup, cache čtení, cache zápis]

    'knowledgeFile' => $private . '/znalosti.json',       // řízený zdroj znalostí (služby, reference bez cen, cenová pravidla)
    'db' => ['dsn' => 'sqlite:' . $private . '/data/ai.sqlite'],
    'salt' => 'NAHRADIT-NAHODNYM-RETEZCEM',               // otisk IP adres (limity) – náhodný řetězec

    'limits' => ['perIp10min' => 20, 'perIpDay' => 80, 'globalMinute' => 30, 'turnsPerConversation' => 20, 'leadsPerIpHour' => 5],
    'maxChars' => 1000,
    'allowedOrigins' => ['https://www.example.cz'],      // prázdné = jen stejný původ (Host)
    'retention' => ['conversationDays' => 30, 'leadDays' => 90],

    /* hlas persony (volitelné, placené): ElevenLabs – komerčně až od plánu Starter. Bez klíče/hlasu čte widget hlasem prohlížeče.
       Strop monthlyChars hlídá brána (měsíc UTC), nastavte ho pod kredity plánu; namluvené odpovědi se ukládají do cacheDir (mazání jako konverzace). */
    // 'speech' => ['provider' => 'elevenlabs', 'apiKeyFile' => $private . '/elevenlabs-klic.txt', 'voiceIdFile' => $private . '/elevenlabs-hlas.txt',
    //     'model' => 'eleven_flash_v2_5', 'monthlyChars' => 25000, 'perIp10min' => 20, 'perConversation' => 30, 'maxChars' => 1200, 'cacheDir' => $private . '/data/hlas'],

    /* lead: souhlas se ukládá s textem, který návštěvník viděl */
    'consentText' => 'Souhlasím se zpracováním údajů z formuláře a této konverzace za účelem odpovědi na poptávku.',
    'notify' => ['to' => 'poptavky@example.cz', 'subject' => 'Nová poptávka z AI asistenta'],
    'mail' => [                                           // konfigurace ts-mailer-php (bez 'from' se e-maily neposílají)
        'from' => 'web@example.cz', 'fromName' => 'Web', 'domain' => 'example.cz',
        'transport' => 'log', 'logFile' => $private . '/data/mail.log',
        // 'transport' => 'smtp', 'smtp_host' => 'smtp.example.cz', 'smtp_port' => 465, 'smtp_secure' => 'ssl', 'smtp_user' => 'web@example.cz',
        // 'smtp_pass' => trim((string) @file_get_contents($private . '/smtp-heslo.txt')),
        // 'redirectTo' => 'test@example.cz',             // testovací režim: všechny e-maily jen sem
    ],
    /* předání leadu do CRM: fn(array $lead): ?string (id v CRM). Výjimka = lead zůstane v DB a přijde e-mail. */
    'leadSink' => null,
];
