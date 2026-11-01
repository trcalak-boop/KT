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
    if (!empty($s['timezone'])) date_default_timezone_set($s['timezone']);   /* časové razítko záznamů v pásmu provozovny */
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
        'redact' => array_values(array_filter([$s['mail']['smtp_pass'] ?? null, $s['db']['pass'] ?? null])),
    ] + (isset($s['clock']) ? ['clock' => $s['clock']] : []));
}

/* Zpevnění vstupního bodu (api.php, instalátor) na sdíleném hostingu:
   - chyby PHP se nezobrazují (jen log hostingu),
   - HTTPS povinné (kromě localhost); TsAuth::isHttps zná i proxy (X-Forwarded-Proto),
   - open_basedir se zúží jen na zadané složky (webroot aplikace + její neveřejná data): PHP pak technicky
     nemůže číst ani zapisovat jinde na hostingu (např. v produkčním webu). Zúžení za běhu PHP dovoluje vždy.
   Vrací false a ukončí požadavek, když podmínka neplatí. */
function ts_booking_harden(array $o): bool
{
    ini_set('display_errors', '0'); ini_set('log_errors', '1');
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!empty($o['requireHttps']) && !$local && !TsAuth::isHttps()) {
        http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo '{"ok":false,"error":"https"}'; return false;
    }
    if (!empty($o['paths'])) {
        $dirs = [];
        foreach ($o['paths'] as $p) { $r = realpath($p); if ($r === false) { http_response_code(503); echo '{"ok":false,"error":"paths"}'; return false; } $dirs[] = rtrim($r, '/') . '/'; }
        if (ini_set('open_basedir', implode(PATH_SEPARATOR, $dirs)) === false) error_log('ts-booking: open_basedir nelze zúžit');
    }
    return true;
}
