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
