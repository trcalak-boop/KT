<?php
/* ==========================================================================
   ts-site-audit – serverová konfigurace (kopii uložit MIMO webroot, např. /private/audit/site-audit.php)
   Vstupní bod: <?php require '…/vendor/ts/ts-site-audit/core/bootstrap.php'; ts_site_audit(require '…/site-audit.php')->serve();
   Klíče se čtou ze souborů vedle (vkládá provozovatel), nikdy z prohlížeče ani z URL.
   ========================================================================== */
$private = __DIR__;
return [
    'enabled' => true,
    'timezone' => 'Europe/Prague', 'defaultLang' => 'cs',
    'source' => 'admin',                                  // 'admin' (jen přihlášený tým) | 'public' (veřejně, přísné limity)
    /* měření: Google PageSpeed Insights API v5 – bezplatný klíč z Google Cloud omezený jen na toto API */
    'meter' => ['provider' => 'pagespeed', 'apiKeyFile' => $private . '/google-api-klic.txt', 'timeout' => 100, 'issuesPerCategory' => 5],
    /* vizuální hodnocení snímku (volitelné): Cloudflare Workers AI, sdílí bezplatnou denní kvótu neuronů s AI asistentem */
    'vision' => ['provider' => 'cloudflare', 'apiKeyFile' => $private . '/cloudflare-token.txt', 'accountIdFile' => $private . '/cloudflare-ucet.txt',
        'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 700],
    'visionPerDay' => 30,                                 // strop hodnocení za den (šetří neurony pro chat)
    'db' => ['dsn' => 'sqlite:' . $private . '/data/audit.sqlite', 'autoMigrate' => true],
    'shotDir' => $private . '/data/snimky',               // snímky webů – mimo webroot, vydávají se jen s tokenem úlohy
    'salt' => 'ZMENIT-na-nahodny-retezec',
    'cacheHours' => 24, 'retentionDays' => 30,
    'limits' => ['publicPerIpDay' => 2, 'publicPerDay' => 20, 'adminPerDay' => 100],
    'allowedOrigins' => ['https://www.example.cz'],      // prázdné = jen stejný původ (Host)
];
