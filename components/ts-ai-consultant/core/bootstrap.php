<?php
/* ==========================================================================
   ts-ai-consultant – sestavení brány ze serverové konfigurace (pole z config/consultant.example.php)
   ts_ai_consultant(require '/cesta/mimo/webroot/ai-consultant.php')->serve();
   Závislosti ts-auth-php (HTTPS za proxy) a ts-mailer-php (fronta oznámení) se hledají vedle komponenty.
   Oficiální SDK Anthropic (composer vendor/) leží MIMO webroot, cesta 'sdkAutoload'.
   ========================================================================== */
require_once __DIR__ . '/TsAiProviders.php';
require_once __DIR__ . '/TsAiStore.php';
require_once __DIR__ . '/TsAiTexts.php';
require_once __DIR__ . '/TsAiConsultant.php';
require_once __DIR__ . '/TsAiCheck.php';
require_once __DIR__ . '/../../ts-auth-php/core/TsAuth.php';
require_once __DIR__ . '/../../ts-mailer-php/core/TsMailer.php';

function ts_ai_store(array $s): TsAiStore
{
    static $cache = [];
    if (!empty($s['timezone'])) date_default_timezone_set($s['timezone']);
    $k = $s['db']['dsn'];
    if (!isset($cache[$k])) { $cache[$k] = TsAiStore::open($k, $s['db']['user'] ?? null, $s['db']['pass'] ?? null); if ($s['db']['autoMigrate'] ?? true) $cache[$k]->migrate(); }
    return $cache[$k];
}

/* Klíč se čte ze souboru mimo webroot (jen první řádek, bez mezer); nikdy z URL ani z prohlížeče. */
function ts_ai_api_key(array $s): string
{
    $f = $s['apiKeyFile'] ?? '';
    if ($f === '' || !is_file($f) || !is_readable($f)) return '';
    return trim((string) strtok((string) file_get_contents($f, false, null, 0, 400), "\r\n"));
}

function ts_ai_provider(array $s): ?TsAiProvider
{
    $p = $s['provider'] ?? 'claude';
    if ($p instanceof TsAiProvider) return $p;
    if ($p === 'fake') return new TsAiFakeProvider($s['fake'] ?? []);
    if ($p !== 'claude') return null;
    $key = ts_ai_api_key($s);
    if ($key === '') return null;
    if (!class_exists('Anthropic\\Client')) {
        $a = $s['sdkAutoload'] ?? '';
        if ($a === '' || !is_file($a)) { error_log('ts-ai: chybí SDK (sdkAutoload)'); return null; }
        require_once $a;
    }
    try { return new TsAiClaudeProvider(['apiKey' => $key] + ($s['claude'] ?? [])); }
    catch (Throwable $e) { error_log('ts-ai: poskytovatel nejde vytvořit: ' . get_class($e)); return null; }
}

function ts_ai_consultant(array $s): TsAiConsultant
{
    $store = ts_ai_store($s);
    $queue = null;
    if (!empty($s['mail']['from'])) { TsMailQueue::migrate($store->pdo()); $queue = new TsMailQueue($store->pdo(), new TsMailer($s['mail']), $s['mailQueue'] ?? []); }
    $key = ts_ai_api_key($s);
    return new TsAiConsultant([
        'store' => $store, 'provider' => ts_ai_provider($s), 'queue' => $queue,
        'redact' => array_values(array_filter([$key, $s['mail']['smtp_pass'] ?? null, $s['db']['pass'] ?? null])),
    ] + array_diff_key($s, array_flip(['db', 'mail', 'mailQueue', 'provider', 'apiKeyFile', 'sdkAutoload', 'claude', 'fake'])));
}

/* Zpevnění vstupního bodu na sdíleném hostingu (stejné chování jako ts_booking_harden):
   skryté chyby, HTTPS povinné (kromě localhost), open_basedir zúžený na zadané složky. */
function ts_ai_harden(array $o): bool
{
    ini_set('display_errors', '0'); ini_set('log_errors', '1');
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!empty($o['requireHttps']) && !$local && !TsAuth::isHttps()) {
        http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo '{"ok":false,"error":"https"}'; return false;
    }
    if (!empty($o['paths'])) {
        $dirs = [];
        foreach ($o['paths'] as $p) { $r = realpath($p); if ($r === false) { http_response_code(503); echo '{"ok":false,"error":"paths"}'; return false; } $dirs[] = rtrim($r, '/') . '/'; }
        if (ini_set('open_basedir', implode(PATH_SEPARATOR, $dirs)) === false) error_log('ts-ai: open_basedir nelze zúžit');
    }
    return true;
}
