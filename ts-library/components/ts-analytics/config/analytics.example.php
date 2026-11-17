<?php
/* ==========================================================================
   ts-analytics – vzor serverové konfigurace. Kopii uložte MIMO webroot (např. /private/stat/analytics.php).
   Vstupní bod sběru (ve webrootu, např. /api/stat/hit.php):
     <?php require __DIR__ . '/../../vendor/ts/ts-analytics/core/bootstrap.php';
           ts_analytics(require dirname(__DIR__, 3) . '/private/stat/analytics.php')->serve();
   Měřicí skript spouštějte až po souhlasu návštěvníka (public/ts-analytics.js).
   ========================================================================== */
$private = __DIR__;
return [
    'enabled' => true,                                   // vypínač sběru (false = požadavky se přijmou a zahodí)
    'timezone' => 'Europe/Prague',
    'db' => ['dsn' => 'sqlite:' . $private . '/data/analytics.sqlite', 'autoMigrate' => true],
    'salt' => 'ZMENTE-na-nahodny-retezec',               // otisk IP jen pro limity (IP se neukládá)
    'allowedOrigins' => ['https://www.example.cz', 'https://example.cz'],   // prázdné = stejná doména jako vstupní bod
    'ownHosts' => ['example.cz'],                         // vlastní domény se jako „odkud přišel“ nepočítají
    'retentionMonths' => 14,
    'limits' => ['perIpHour' => 600, 'globalDay' => 50000],
];
