<?php
/* ==========================================================================
   ts-booking-server – sestavení API ze serverové konfigurace (pole z config/server.example.php)
   $api = ts_booking_api(require '/cesta/mimo/webroot/booking-server.php'); $api->serve();
   Závislosti ts-auth-php a ts-mailer-php se hledají vedle této komponenty (vendor/ts/<komponenta>/).
   ========================================================================== */
require_once __DIR__ . '/TsBookingEngine.php';
require_once __DIR__ . '/TsBookingStore.php';
require_once __DIR__ . '/TsBookingNotifier.php';
require_once __DIR__ . '/TsBookingApi.php';
require_once __DIR__ . '/../../ts-auth-php/core/TsAuth.php';
require_once __DIR__ . '/../../ts-mailer-php/core/TsMailer.php';

function ts_booking_store(array $s): TsBookingStore
{
    static $cache = [];
    $k = $s['db']['dsn'];
    if (!isset($cache[$k])) { $cache[$k] = TsBookingStore::open($k, $s['db']['user'] ?? null, $s['db']['pass'] ?? null); if ($s['db']['autoMigrate'] ?? true) $cache[$k]->migrate(); }
    return $cache[$k];
}
function ts_booking_queue(array $s, TsBookingStore $store): ?TsMailQueue
{
    if (empty($s['mail']['from'])) return null;
    $q = new TsMailQueue($store->pdo(), new TsMailer($s['mail']), $s['mailQueue'] ?? []);
    TsMailQueue::migrate($store->pdo());
    return $q;
}
function ts_booking_api(array $s): TsBookingApi
{
    $store = ts_booking_store($s);
    $auth = isset($s['authDir']) ? new TsAuth(['dataDir' => $s['authDir'], 'sessionName' => $s['sessionName'] ?? 'tsbooking', 'policy' => $s['policy'] ?? TsBookingApi::POLICY, 'cookiePath' => $s['cookiePath'] ?? null]) : null;
    if (isset($s['authDir']) && !is_dir($s['authDir'])) @mkdir($s['authDir'], 0700, true);
    return new TsBookingApi([
        'configFile' => $s['configFile'], 'store' => $store, 'auth' => $auth, 'queue' => ts_booking_queue($s, $store), 'notify' => $s['notify'] ?? [],
        'autoConfirm' => $s['autoConfirm'] ?? false, 'rateLimit' => $s['rateLimit'] ?? ['bookings' => [10, 3600]], 'minFillSeconds' => $s['minFillSeconds'] ?? 3,
        'allowedOrigins' => $s['allowedOrigins'] ?? [], 'extensions' => $s['extensions'] ?? [], 'processQueue' => $s['processQueue'] ?? 2,
    ] + (isset($s['clock']) ? ['clock' => $s['clock']] : []));
}
