<?php
/* ==========================================================================
   ts-ai-consultant – poskytovatelé AI (adaptér). Konzultant zná jen rozhraní TsAiProvider,
   konkrétní poskytovatel se volí v konfiguraci ('provider' => 'claude' | 'fake').

   respond(['system' => string, 'messages' => [...], 'tools' => [...], 'maxTokens' => int]) → [
     'stop'    => 'end' | 'tool_use' | 'refusal' | 'max_tokens',
     'text'    => string,                                  text odpovědi (bez myšlení modelu)
     'tools'   => [['id' => , 'name' => , 'input' => []]], požadovaná volání nástrojů
     'usage'   => ['in' => , 'out' => , 'cacheRead' => , 'cacheWrite' => ],
     'assistant' => mixed,                                 obsah tahu pro pokračování smyčky nástrojů (beze změny zpět)
   ]
   Chyby: TsAiProviderError (kind: 'auth' | 'limit' | 'overloaded' | 'connection' | 'request' | 'other').
   Zprávy v 'messages': ['role' => 'user'|'assistant', 'content' => string | bloky]; výsledek nástroje:
   ['type' => 'tool_result', 'id' => , 'content' => string] – převod na formát poskytovatele dělá adaptér.
   ========================================================================== */

final class TsAiProviderError extends RuntimeException
{
    public string $kind;
    public function __construct(string $kind, string $message = '') { parent::__construct($message); $this->kind = $kind; }
}

interface TsAiProvider
{
    public function respond(array $req): array;
    public function model(): string;
}

/* Claude přes oficiální PHP SDK (anthropic-ai/sdk). Klíč se předává jen sem, nikdy do prohlížeče ani do logu. */
final class TsAiClaudeProvider implements TsAiProvider
{
    private $client; private array $c;

    public function __construct(array $c)
    {
        $this->c = $c + ['model' => 'claude-opus-5-5', 'effort' => 'low', 'timeout' => 30, 'connectTimeout' => 6, 'maxRetries' => 1];
        if (empty($this->c['apiKey'])) throw new TsAiProviderError('auth', 'chybí API klíč');
        if (!class_exists('Anthropic\\Client')) throw new TsAiProviderError('other', 'SDK není načtené (vendor/autoload.php)');
        $http = class_exists('GuzzleHttp\\Client') ? new \GuzzleHttp\Client(['timeout' => (float) $this->c['timeout'], 'connect_timeout' => (float) $this->c['connectTimeout']]) : null;
        $this->client = new \Anthropic\Client(apiKey: $this->c['apiKey'], baseUrl: $this->c['baseUrl'] ?? null, requestOptions: ['maxRetries' => (int) $this->c['maxRetries']] + ($http ? ['transporter' => $http] : []), openTelemetry: false);
    }

    public function model(): string { return $this->c['model']; }

    public function respond(array $req): array
    {
        $msgs = [];
        foreach ($req['messages'] as $m) {
            $content = $m['content'];
            if (is_array($content)) {
                $content = array_map(function ($b) {
                    if (is_array($b) && ($b['type'] ?? '') === 'tool_result') return ['type' => 'tool_result', 'toolUseID' => $b['id'], 'content' => (string) $b['content']];
                    return $b;
                }, $content);
            }
            $msgs[] = ['role' => $m['role'], 'content' => $content];
        }
        $tools = array_map(fn ($t) => ['name' => $t['name'], 'description' => $t['description'], 'inputSchema' => $t['schema']], $req['tools'] ?? []);
        try {
            $r = $this->client->messages->create(
                model: $this->c['model'],
                maxTokens: (int) $req['maxTokens'],
                system: [['type' => 'text', 'text' => $req['system'], 'cacheControl' => ['type' => 'ephemeral']]],
                messages: $msgs,
                tools: $tools ?: null,
                outputConfig: $this->c['effort'] ? ['effort' => $this->c['effort']] : null,
            );
        } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
            $t = $e->type?->value ?? '';
            $code = (int) ($e->status ?? 0);
            $kind = in_array($t, ['authentication_error', 'permission_error'], true) || in_array($code, [401, 403], true) ? 'auth'
                : ($t === 'rate_limit_error' || $code === 429 ? 'limit' : ($t === 'overloaded_error' || $code === 529 || $code >= 500 ? 'overloaded'
                : ($t === 'billing_error' ? 'limit' : 'request')));
            throw new TsAiProviderError($kind, $t ?: ('http ' . $code));
        } catch (\Anthropic\Core\Exceptions\APIConnectionException $e) {
            throw new TsAiProviderError('connection', 'spojení');
        } catch (\Throwable $e) {
            throw new TsAiProviderError('other', get_class($e));
        }
        $text = ''; $calls = [];
        foreach ($r->content as $b) {
            if ($b->type === 'text') $text .= $b->text;
            elseif ($b->type === 'tool_use') $calls[] = ['id' => $b->id, 'name' => $b->name, 'input' => is_array($b->input) ? $b->input : (array) $b->input];
        }
        $u = $r->usage;
        $stop = match ($r->stopReason) { 'tool_use' => 'tool_use', 'refusal' => 'refusal', 'max_tokens' => 'max_tokens', default => 'end' };
        return ['stop' => $stop, 'text' => $text, 'tools' => $calls, 'assistant' => $r->content,
            'usage' => ['in' => (int) $u->inputTokens, 'out' => (int) $u->outputTokens, 'cacheRead' => (int) ($u->cacheReadInputTokens ?? 0), 'cacheWrite' => (int) ($u->cacheCreationInputTokens ?? 0)]];
    }
}

/* OpenAI-kompatibilní Chat Completions přes cURL (bez SDK): Cloudflare Workers AI, Groq, Mistral a další.
   'baseUrl' => 'https://api.cloudflare.com/client/v4/accounts/<účet>/ai/v1', 'model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast'.
   Nástroje se neposílají (podpora se u modelů liší) – kontaktní formulář řeší brána značkou v textu ('contactMode' => 'marker'). */
final class TsAiOpenAICompatProvider implements TsAiProvider
{
    private array $c;
    public function __construct(array $c)
    {
        $this->c = $c + ['timeout' => 30, 'connectTimeout' => 6, 'temperature' => 0.4];
        if (empty($this->c['apiKey'])) throw new TsAiProviderError('auth', 'chybí API klíč');
        if (empty($this->c['baseUrl']) || empty($this->c['model'])) throw new TsAiProviderError('other', 'chybí baseUrl nebo model');
        if (!function_exists('curl_init')) throw new TsAiProviderError('other', 'chybí rozšíření curl');
    }
    public function model(): string { return $this->c['model']; }
    public function respond(array $req): array
    {
        $msgs = [['role' => 'system', 'content' => $req['system']]];
        foreach ($req['messages'] as $m) if (is_string($m['content'])) $msgs[] = ['role' => $m['role'], 'content' => $m['content']];
        $body = json_encode(['model' => $this->c['model'], 'messages' => $msgs, 'max_tokens' => (int) $req['maxTokens'], 'temperature' => (float) $this->c['temperature']], JSON_UNESCAPED_UNICODE);
        $ch = curl_init(rtrim($this->c['baseUrl'], '/') . '/chat/completions');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $this->c['apiKey']],
            CURLOPT_TIMEOUT => (int) $this->c['timeout'], CURLOPT_CONNECTTIMEOUT => (int) $this->c['connectTimeout']]);
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_errno($ch);
        if ($raw === false || $err) throw new TsAiProviderError('connection', 'spojení (curl ' . $err . ')');
        $j = json_decode((string) $raw, true);
        if ($code === 401 || $code === 403) throw new TsAiProviderError('auth', 'http ' . $code);
        if ($code === 429) throw new TsAiProviderError('limit', 'http 429');
        if ($code >= 500) throw new TsAiProviderError('overloaded', 'http ' . $code);
        if ($code !== 200 || !is_array($j) || !isset($j['choices'][0]['message'])) {
            $msg = is_array($j) ? (string) ($j['errors'][0]['message'] ?? $j['error']['message'] ?? '') : '';
            throw new TsAiProviderError(stripos($msg, 'allocation') !== false || stripos($msg, 'quota') !== false || stripos($msg, 'limit') !== false ? 'limit' : 'request', 'http ' . $code . ' ' . mb_substr($msg, 0, 120));
        }
        $c = $j['choices'][0]; $text = (string) ($c['message']['content'] ?? '');
        $u = $j['usage'] ?? [];
        $in = (int) ($u['prompt_tokens'] ?? ceil(strlen($body) / 3)); $out = (int) ($u['completion_tokens'] ?? ceil(strlen($text) / 3));
        $stop = ($c['finish_reason'] ?? '') === 'length' ? 'max_tokens' : (($c['finish_reason'] ?? '') === 'content_filter' ? 'refusal' : 'end');
        return ['stop' => $stop, 'text' => $text, 'tools' => [], 'assistant' => $text, 'usage' => ['in' => $in, 'out' => $out, 'cacheRead' => 0, 'cacheWrite' => 0]];
    }
}

/* Falešný poskytovatel pro testy a pro vyzkoušení widgetu bez API klíče. Nikam se nepřipojuje.
   'script' => [fn(array $req): array, ...] – postupně vrací připravené odpovědi; bez skriptu odpoví
   ozvěnou, na „kontakt“ zavolá nástroj nabidnout_kontakt, na „chyba“ simuluje výpadek. */
final class TsAiFakeProvider implements TsAiProvider
{
    private array $script; public array $requests = [];
    public function __construct(array $c = []) { $this->script = $c['script'] ?? []; }
    public function model(): string { return 'fake'; }
    public function respond(array $req): array
    {
        $this->requests[] = $req;
        if ($this->script) { $f = array_shift($this->script); $r = $f($req); return $r + ['tools' => [], 'text' => '', 'stop' => 'end', 'assistant' => $r['text'] ?? '', 'usage' => ['in' => 100, 'out' => 20, 'cacheRead' => 0, 'cacheWrite' => 0]]; }
        $last = end($req['messages']); $c = $last['content'];
        $u = ['in' => 1200, 'out' => 60, 'cacheRead' => 0, 'cacheWrite' => 0];
        if (is_array($c)) return ['stop' => 'end', 'text' => 'Formulář pro kontakt najdete pod touto zprávou.', 'tools' => [], 'assistant' => 'Formulář pro kontakt najdete pod touto zprávou.', 'usage' => $u];
        if (stripos($c, 'chyba') !== false) throw new TsAiProviderError('overloaded', 'simulovaný výpadek');
        if (stripos($c, 'kontakt') !== false) {
            $call = ['id' => 'toolu_fake_' . count($this->requests), 'name' => 'nabidnout_kontakt', 'input' => ['shrnuti' => 'Zájemce chce kontakt: ' . mb_substr($c, 0, 200)]];
            return ['stop' => 'tool_use', 'text' => '', 'tools' => [$call], 'assistant' => [['type' => 'tool_use'] + $call], 'usage' => $u];
        }
        $t = '(Zkušební režim bez AI) Napsali jste: ' . mb_substr($c, 0, 300);
        return ['stop' => 'end', 'text' => $t, 'tools' => [], 'assistant' => $t, 'usage' => $u];
    }
}

/* ==========================================================================
   Převod textu na řeč (hlas persony). Brána zná jen rozhraní TsAiSpeech:
   synth(string $text, string $lang) → MP3 (bajty); chyby TsAiProviderError jako u modelů.
   ========================================================================== */
interface TsAiSpeech
{
    public function synth(string $text, string $lang): string;
    public function voice(): string;
}

/* ElevenLabs Text to Speech přes cURL (bez SDK). Klíč jen ze serverové konfigurace, nikdy do prohlížeče.
   'voiceId' => hlas z knihovny účtu (např. navržený ve Voice Design), 'model' => 'eleven_flash_v2_5' (rychlý, 32 jazyků vč. češtiny).
   Komerční použití vyžaduje placený plán ElevenLabs (Starter a výš) – ověřit v podmínkách. */
final class TsAiElevenLabsSpeech implements TsAiSpeech
{
    private array $c;
    public function __construct(array $c)
    {
        $this->c = $c + ['model' => 'eleven_flash_v2_5', 'baseUrl' => 'https://api.elevenlabs.io', 'format' => 'mp3_44100_64', 'timeout' => 25, 'connectTimeout' => 6,
            'settings' => ['stability' => 0.5, 'similarity_boost' => 0.75, 'style' => 0.0, 'use_speaker_boost' => true, 'speed' => 1.0]];
        if (empty($this->c['apiKey'])) throw new TsAiProviderError('auth', 'chybí API klíč ElevenLabs');
        if (!preg_match('/^[A-Za-z0-9]{8,40}$/', (string) ($this->c['voiceId'] ?? ''))) throw new TsAiProviderError('other', 'chybí nebo neplatné voiceId');
        if (!function_exists('curl_init')) throw new TsAiProviderError('other', 'chybí rozšíření curl');
    }
    public function voice(): string { return $this->c['voiceId'] . '|' . $this->c['model']; }
    public function synth(string $text, string $lang): string
    {
        $body = ['text' => $text, 'model_id' => $this->c['model'], 'voice_settings' => $this->c['settings']];
        if (preg_match('/_v2_5$/', $this->c['model'])) $body['language_code'] = substr($lang, 0, 2);      /* jazyk vynutit (Flash/Turbo v2.5) */
        $ch = curl_init(rtrim($this->c['baseUrl'], '/') . '/v1/text-to-speech/' . rawurlencode($this->c['voiceId']) . '?output_format=' . rawurlencode($this->c['format']));
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: audio/mpeg', 'xi-api-key: ' . $this->c['apiKey']],
            CURLOPT_TIMEOUT => (int) $this->c['timeout'], CURLOPT_CONNECTTIMEOUT => (int) $this->c['connectTimeout']]);
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE); $err = curl_errno($ch);
        if ($raw === false || $err) throw new TsAiProviderError('connection', 'spojení (curl ' . $err . ')');
        if ($code === 200 && stripos($type, 'audio') !== false && strlen((string) $raw) > 100) return (string) $raw;
        $j = json_decode((string) $raw, true); $st = is_array($j) ? (string) ($j['detail']['status'] ?? $j['detail']['code'] ?? '') : '';
        if ($code === 401 || $code === 403) throw new TsAiProviderError($st === 'quota_exceeded' ? 'limit' : 'auth', 'http ' . $code . ' ' . $st);
        if ($code === 429 || $code === 402 || $st === 'quota_exceeded') throw new TsAiProviderError('limit', 'http ' . $code . ' ' . $st);
        if ($code >= 500) throw new TsAiProviderError('overloaded', 'http ' . $code);
        throw new TsAiProviderError('request', 'http ' . $code . ' ' . mb_substr($st, 0, 80));
    }
}

/* Falešný hlas pro testy: krátké platné MP3 ticho, nikam se nepřipojuje; 'fail' => 'limit' simuluje chybu. */
final class TsAiFakeSpeech implements TsAiSpeech
{
    public array $calls = []; private array $c;
    public function __construct(array $c = []) { $this->c = $c; }
    public function voice(): string { return 'fake|fake'; }
    public function synth(string $text, string $lang): string
    {
        $this->calls[] = [$text, $lang];
        if (!empty($this->c['fail'])) throw new TsAiProviderError($this->c['fail'], 'simulovaná chyba hlasu');
        return base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tQwAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAACAAABhgC7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7//////////////////////////////////////////////////////////////////8AAAAATGF2YzU4LjEzAAAAAAAAAAAAAAAAJAAAAAAAAAAAAYYoRBqpAAAAAAD/+1DEAAPH3Yz0AAR8CPqyIEABp6AxjG/4x/XiInE4lfQDFwIGRZ8uf/+1DEGAPKZYJ0AAR8CL1yYMACT8AAAQBBAQBAQBAQBAQBAQBAQBAQBA');
    }
}
