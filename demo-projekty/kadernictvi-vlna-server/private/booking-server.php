<?php
/* Salon Vlna (server) – serverová konfigurace rezervací. Leží MIMO webroot (private/ vedle web/), do gitu nepatří.
   Lokální ukázka: e-maily se neodesílají, zapisují se do private/mail.log (transport log). */
$private = __DIR__;
$base = getenv('TS_DEMO_BASE') ?: 'http://localhost:8103';
return [
    'db' => ['dsn' => 'sqlite:' . $private . '/data/rezervace.sqlite', 'autoMigrate' => true],
    'configFile' => $private . '/rezervace-config.json',
    'authDir' => $private . '/data/auth',
    'cookiePath' => '/',
    'autoConfirm' => false,
    'rateLimit' => ['bookings' => [30, 3600]],
    'minFillSeconds' => 2,
    'allowedOrigins' => [],
    'retentionDays' => 730,
    'mail' => ['transport' => 'log', 'logFile' => $private . '/mail.log', 'from' => 'rezervace@salon-vlna.test', 'fromName' => 'Salon Vlna', 'domain' => 'salon-vlna.test'],
    'notify' => ['business' => 'Salon Vlna', 'staffEmail' => 'recepce@salon-vlna.test', 'currency' => 'Kč',
                 'cancelUrl' => $base . '/rezervace-zrusit.html?id={id}&token={token}', 'adminUrl' => $base . '/sprava.html'],
];
