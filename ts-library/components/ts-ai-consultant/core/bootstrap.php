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
    if ($p === 'cloudflare' || $p === 'openai') {            /* bezplatné Cloudflare Workers AI nebo jiná OpenAI-kompatibilní služba (bez SDK) */
        $key = ts_ai_api_key($s);
        if ($key === '') return null;
        $o = $s[$p] ?? [];
        if ($p === 'cloudflare') $o += ['baseUrl' => 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode((string) ($o['accountId'] ?? '')) . '/ai/v1', 'model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast'];
        try { return new TsAiOpenAICompatProvider(['apiKey' => $key] + $o); }
        catch (Throwable $e) { error_log('ts-ai: poskytovatel nejde vytvořit: ' . $e->getMessage()); return null; }
    }
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

/* Hlas persony: 'speech' => ['provider' => 'elevenlabs', 'apiKeyFile' => …, 'voiceId' => … | 'voiceIdFile' => …, 'model' => 'eleven_flash_v2_5', 'monthlyChars' => 25000]
   Bez klíče nebo hlasu → null: widget dál čte hlasem prohlížeče. */
function ts_ai_speech(array $s): ?TsAiSpeech
{
    $o = $s['speech'] ?? null;
    if (!is_array($o) || empty($o['provider'])) return null;
    if ($o['provider'] instanceof TsAiSpeech) return $o['provider'];
    if ($o['provider'] === 'fake') return new TsAiFakeSpeech($o['fake'] ?? []);
    if ($o['provider'] !== 'elevenlabs') return null;
    $key = ts_ai_api_key(['apiKeyFile' => $o['apiKeyFile'] ?? '']);
    $voice = (string) ($o['voiceId'] ?? '');
    if ($voice === '' && !empty($o['voiceIdFile'])) $voice = ts_ai_api_key(['apiKeyFile' => $o['voiceIdFile']]);
    if ($key === '' || $voice === '') return null;
    try { return new TsAiElevenLabsSpeech(['apiKey' => $key, 'voiceId' => $voice] + array_intersect_key($o, array_flip(['model', 'baseUrl', 'format', 'timeout', 'connectTimeout', 'settings']))); }
    catch (Throwable $e) { error_log('ts-ai: hlas nejde vytvořit: ' . $e->getMessage()); return null; }
}

function ts_ai_consultant(array $s): TsAiConsultant
{
    $store = ts_ai_store($s);
    $queue = null;
    if (!empty($s['mail']['from'])) { TsMailQueue::migrate($store->pdo()); $queue = new TsMailQueue($store->pdo(), new TsMailer($s['mail']), $s['mailQueue'] ?? []); }
    $key = ts_ai_api_key($s); $skey = ts_ai_api_key(['apiKeyFile' => $s['speech']['apiKeyFile'] ?? '']);
    return new TsAiConsultant([
        'store' => $store, 'provider' => ts_ai_provider($s), 'queue' => $queue, 'tts' => ts_ai_speech($s),
        'redact' => array_values(array_filter([$key, $skey, $s['mail']['smtp_pass'] ?? null, $s['db']['pass'] ?? null])),
    ] + array_diff_key($s, array_flip(['db', 'mail', 'mailQueue', 'provider', 'apiKeyFile', 'sdkAutoload', 'claude', 'cloudflare', 'openai', 'fake'])));
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
