<?php
/* ==========================================================================
   ts-design-studio – jazykové modely pro AI vrstvu: Cloudflare Workers AI (OpenAI-kompatibilní rozhraní) a falešný model pro testy.
   complete($messages, $opt) → ['text', 'in', 'out', 'finish']; neurons($in, $out) = spotřeba z bezplatné denní kvóty (u placených modelů 0).
   Klíč se čte ze souboru mimo webroot (apiKeyFile) – nikdy z konfigurace v repozitáři.
   ========================================================================== */
interface TsStudioModel
{
    public function model(): string;
    public function complete(array $messages, array $opt = []): array;
    public function neurons(int $in, int $out): int;
}

final class TsStudioModelError extends RuntimeException
{
    public string $kind;   /* auth | limit | timeout | connection | request | format */
    public function __construct(string $kind, string $message = '') { parent::__construct($message ?: $kind); $this->kind = $kind; }
}

final class TsStudioCloudflareModel implements TsStudioModel
{
    private array $c;
    public function __construct(array $c)
    {
        /* Gemma 4: vypnuté „přemýšlení“ (jinak spotřebuje limit tokenů – ověřeno v auditu); neurony za milion tokenů [vstup, výstup] podle ceníku 10/2026 */
        $this->c = $c + ['model' => '@cf/google/gemma-4-26b-a4b-it', 'timeout' => 90, 'maxTokens' => 4000, 'temperature' => 0.4,
            'extra' => ['chat_template_kwargs' => ['enable_thinking' => false]], 'rates' => [9091, 27273]];
        if (empty($this->c['apiKey']) && !empty($this->c['apiKeyFile']) && is_readable($this->c['apiKeyFile'])) $this->c['apiKey'] = trim((string) file_get_contents($this->c['apiKeyFile']));
        if (empty($this->c['accountId']) && !empty($this->c['accountIdFile']) && is_readable($this->c['accountIdFile'])) $this->c['accountId'] = trim((string) file_get_contents($this->c['accountIdFile']));
        if (empty($this->c['apiKey'])) throw new TsStudioModelError('auth', 'chybí token Cloudflare');
        if (empty($this->c['baseUrl'])) $this->c['baseUrl'] = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode((string) ($this->c['accountId'] ?? '')) . '/ai/v1';
    }
    public function model(): string { return $this->c['model']; }
    public function neurons(int $in, int $out): int { return (int) ceil(($in * $this->c['rates'][0] + $out * $this->c['rates'][1]) / 1e6); }
    public function complete(array $messages, array $opt = []): array
    {
        $body = json_encode((array) $this->c['extra'] + ['model' => $this->c['model'], 'max_tokens' => (int) ($opt['maxTokens'] ?? $this->c['maxTokens']),
            'temperature' => (float) ($opt['temperature'] ?? $this->c['temperature']), 'messages' => $messages], JSON_UNESCAPED_UNICODE);
        [$code, $raw, $err] = self::http(rtrim($this->c['baseUrl'], '/') . '/chat/completions', ['Content-Type: application/json', 'Authorization: Bearer ' . $this->c['apiKey']], $body, (int) $this->c['timeout']);
        if ($err !== '') throw new TsStudioModelError(stripos($err, 'timed out') !== false ? 'timeout' : 'connection', $err);
        $j = json_decode($raw, true);
        if ($code === 401 || $code === 403) throw new TsStudioModelError('auth', 'http ' . $code);
        if ($code === 429 || ($code !== 200 && is_array($j) && preg_match('/allocation|quota|neurons/i', (string) json_encode($j)))) throw new TsStudioModelError('limit', 'http ' . $code);
        if ($code !== 200 || !isset($j['choices'][0]['message'])) throw new TsStudioModelError('request', 'http ' . $code . ' ' . mb_substr(preg_replace('/\s+/', ' ', $raw), 0, 160));
        $c = $j['choices'][0]['message']['content'] ?? '';
        if (is_array($c)) $c = implode("\n", array_map(fn ($p) => is_array($p) ? (string) ($p['text'] ?? '') : (string) $p, $c));
        $u = $j['usage'] ?? [];
        return ['text' => (string) $c, 'in' => (int) ($u['prompt_tokens'] ?? 0), 'out' => (int) ($u['completion_tokens'] ?? 0), 'finish' => (string) ($j['choices'][0]['finish_reason'] ?? '')];
    }
    private static function http(string $url, array $headers, string $body, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP]);
        $raw = curl_exec($ch); $err = $raw === false ? curl_error($ch) : ''; $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return [$code, (string) $raw, $err];
    }
}

/* Falešný model pro testy: nikam se nepřipojuje. 'script' => [odpověď | callable($messages) | ['fail' => 'limit'], …] – odpovědi postupně, poslední se opakuje. */
final class TsStudioFakeModel implements TsStudioModel
{
    public array $calls = []; private array $script;
    public function __construct(array $c = []) { $this->script = $c['script'] ?? ['{}']; }
    public function model(): string { return 'fake'; }
    public function neurons(int $in, int $out): int { return (int) ceil(($in * 9091 + $out * 27273) / 1e6); }
    public function complete(array $messages, array $opt = []): array
    {
        $this->calls[] = $messages;
        $r = $this->script[min(count($this->calls) - 1, count($this->script) - 1)];
        if (is_array($r) && isset($r['fail'])) throw new TsStudioModelError($r['fail'], 'simulovaná chyba');
        $text = is_callable($r) ? (string) $r($messages) : (is_array($r) ? json_encode($r, JSON_UNESCAPED_UNICODE) : (string) $r);
        $in = (int) ceil(mb_strlen(implode(' ', array_column($messages, 'content'))) / 4);
        return ['text' => $text, 'in' => $in, 'out' => (int) ceil(mb_strlen($text) / 4), 'finish' => 'stop'];
    }
}
