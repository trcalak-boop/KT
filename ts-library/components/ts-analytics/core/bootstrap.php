<?php
/* ==========================================================================
   ts-analytics – sestavení z konfigurace (pole z config/analytics.example.php)
   ts_analytics(require '/cesta/mimo/webroot/analytics.php')->serve();          // vstupní bod sběru
   ts_analytics($s)->report('2026-11-01', '2026-11-30');                         // přehled pro administraci
   ========================================================================== */
require_once __DIR__ . '/TsAnalyticsStore.php';
require_once __DIR__ . '/TsAnalytics.php';
require_once __DIR__ . '/TsAnalyticsReport.php';

function ts_analytics(array $s): TsAnalytics
{
    if (!empty($s['timezone'])) date_default_timezone_set($s['timezone']);
    static $stores = [];
    $k = $s['db']['dsn'];
    if (!isset($stores[$k])) { $stores[$k] = TsAnalyticsStore::open($k, $s['db']['user'] ?? null, $s['db']['pass'] ?? null); if ($s['db']['autoMigrate'] ?? true) $stores[$k]->migrate(); }
    return new TsAnalytics(['store' => $stores[$k]] + array_diff_key($s, array_flip(['db'])));
}
