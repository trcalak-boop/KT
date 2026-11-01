<?php
/* ==========================================================================
   ts-site-audit – sestavení auditu ze serverové konfigurace (pole z config/audit.example.php)
   ts_site_audit(require '/cesta/mimo/webroot/site-audit.php')->serve();
   Klíče (Google API, Cloudflare) se čtou ze souborů mimo webroot; bez klíče Google je audit vypnutý.
   ========================================================================== */
require_once __DIR__ . '/TsAuditUrl.php';
require_once __DIR__ . '/TsAuditProviders.php';
require_once __DIR__ . '/TsAuditStore.php';
require_once __DIR__ . '/TsSiteAudit.php';

function ts_audit_secret(string $file): string
{
    if ($file === '' || !is_file($file) || !is_readable($file)) return '';
    return trim((string) strtok((string) file_get_contents($file, false, null, 0, 400), "\r\n"));
}

function ts_site_audit(array $s): TsSiteAudit
{
    if (!empty($s['timezone'])) date_default_timezone_set($s['timezone']);
    static $stores = [];
    $k = $s['db']['dsn'];
    if (!isset($stores[$k])) { $stores[$k] = TsAuditStore::open($k, $s['db']['user'] ?? null, $s['db']['pass'] ?? null); if ($s['db']['autoMigrate'] ?? true) $stores[$k]->migrate(); }
    $meter = null; $vision = null;
    $m = $s['meter'] ?? [];
    if ($m instanceof TsAuditMeter) $meter = $m;
    elseif (($m['provider'] ?? 'pagespeed') === 'fake') $meter = new TsAuditFakeMeter($m['fake'] ?? []);
    elseif (($key = ts_audit_secret((string) ($m['apiKeyFile'] ?? ''))) !== '') {
        try { $meter = new TsAuditPageSpeed(['apiKey' => $key] + array_intersect_key($m, array_flip(['baseUrl', 'timeout', 'issuesPerCategory']))); }
        catch (Throwable $e) { error_log('ts-audit: měření nejde vytvořit: ' . $e->getMessage()); }
    }
    $v = $s['vision'] ?? null;
    if ($v instanceof TsAuditVision) $vision = $v;
    elseif (is_array($v) && ($v['provider'] ?? '') === 'fake') $vision = new TsAuditFakeVision($v['fake'] ?? []);
    elseif (is_array($v) && ($v['provider'] ?? '') === 'cloudflare' && ($tok = ts_audit_secret((string) ($v['apiKeyFile'] ?? ''))) !== '') {
        $acc = (string) ($v['accountId'] ?? ts_audit_secret((string) ($v['accountIdFile'] ?? '')));
        try { $vision = new TsAuditCloudflareVision(['apiKey' => $tok, 'accountId' => $acc] + array_intersect_key($v, array_flip(['model', 'baseUrl', 'timeout', 'maxTokens']))); }
        catch (Throwable $e) { error_log('ts-audit: hodnocení nejde vytvořit: ' . $e->getMessage()); }
    }
    return new TsSiteAudit(['store' => $stores[$k], 'meter' => $meter, 'vision' => $vision] + array_diff_key($s, array_flip(['db', 'meter', 'vision', 'timezone'])));
}
