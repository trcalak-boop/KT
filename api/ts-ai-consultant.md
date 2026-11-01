# API: ts-ai-consultant 1.4.0

Textový AI konzultant: PHP 8.1+ brána (JSON) + widget. Závisí na `ts-auth-php` ^1.0.0 (HTTPS za proxy) a `ts-mailer-php` ^1.1.0 (fronta oznámení). Poskytovatel Claude přes oficiální `anthropic-ai/sdk` ^1.3 (složka `vendor/` mimo webroot, viz `sdk/README.md`).

## Struktura na hostingu
```
web/api/ai/chat.php            vstupní bod (config/api.example.php)
web/vendor/ts/ts-ai-consultant jádro (PHP složky mají .htaccess „Require all denied“), public/ widget
private/ai/ai-consultant.php   konfigurace (config/consultant.example.php)
private/ai/znalosti.json       řízené znalosti
private/ai/claude-api-klic.txt API klíč (jeden řádek, 0600) – vkládá provozovatel
private/ai/vendor/             sestavené SDK
private/ai/data/ai.sqlite      konverzace, spotřeba, leady
```

## Konfigurace (hlavní klíče)
| Klíč | Význam |
|---|---|
| `provider` | `'claude'` (SDK) / `'cloudflare'` (Workers AI zdarma, `cloudflare.accountId`, `cloudflare.model`) / `'openai'` (`openai.baseUrl`, `openai.model`) / `'fake'` / instance `TsAiProvider` |
| `contactMode` | `'tool'` (nástroj nabidnout_kontakt) / `'marker'` (značka `[[KONTAKT: …]]` v odpovědi) |
| `budget` | `['limit' => 9500, 'period' => 'day', 'unit' => 'neuron']`; bez něj `monthlyBudgetUsd` měsíčně |
| `knowledgeMaxChars` | 0 = celé znalosti; jinak přehled + sekce podle dotazu do limitu |
| `apiKeyFile`, `sdkAutoload` | cesta ke klíči a k `vendor/autoload.php` (obojí mimo webroot) |
| `claude` | `model` (výchozí `claude-opus-5-5`), `effort` (`low`), `timeout`, `connectTimeout`, `maxRetries`, `baseUrl` (jen testy) |
| `maxTokens`, `monthlyBudgetUsd`, `pricing` | strop výstupu, měsíční rozpočet, přepis ceníku USD/MTok `[vstup, výstup, cache čtení, cache zápis]` |
| `knowledgeFile` | znalosti (níže) |
| `limits` | `perIp10min`, `perIpDay`, `globalMinute`, `turnsPerConversation`, `leadsPerIpHour` |
| `maxChars`, `historyMessages`, `maxToolRounds` | délka zprávy, kolik zpráv historie jde modelu, kola nástrojů |
| `allowedOrigins`, `salt`, `retention` | původ, sůl otisku IP, retence konverzací/leadů ve dnech |
| `contactEmail`, `texts`, `defaultLang` | kontakt v náhradních odpovědích, přepis textů `TsAiTexts`, výchozí jazyk |
| `consentText`, `notify`, `mail`, `leadSink` | souhlas (záložní text), komu oznámení, TsMailer, předání do CRM `fn(array $lead): ?string` |
| `check` | `codeHash`, `until` (a `probe` pro testy) pro `TsAiCheck` |
| `enabled` | vypínač (status → widget tlačítko neukáže) |
| `persona` | `['name' => 'Tessa', 'file' => '/…/persona.md']` – identita na začátek promptu |
| `capabilities` / `capabilitiesFile` | `[{popis, dostupne}]` nebo JSON `{"funkce": [...]}` – co persona umí a co ne |
| `access` | `['cookie' => 'název', 'hash' => sha256(klíč)]` – neveřejný staging, bez cookie 403 |

## Znalosti (`znalosti.json`)
```json
{ "business": "…", "contact": { "email": "…", "phone": "…" },
  "sections": [{ "title": "…", "text": "…" }],
  "references": [{ "id": "…", "title": "…", "field": "obor", "services": [], "features": [], "summary": "…", "url": "/ukazky/x.html", "fictional": true }],
  "pages": [{ "key": "kontakt", "title": "Kontakt", "urls": { "cs": "/cs/kontakt.html", "en": "/contact.html" } }],
  "links": [{ "title": "…", "url": "…" }],
  "pricePolicy": { "noAmounts": true, "rules": ["…"] } }
```
Odkazy v odpovědích projdou jen tehdy, když jsou v `references[].url`, `pages[].urls` nebo `links`.

## HTTP API (`api/ai/chat.php?r=…`, JSON, POST jen s `Content-Type: application/json`)
| Metoda, r | Vstup → výstup |
|---|---|
| GET `status` | → `{ ok, enabled, langs }` (nevolá AI) |
| POST `message` | `{ conversation?, text (≤ maxChars), lang, page? }` → `{ ok, conversation, reply: { text, links: [{url,title}], actions: ['contact'], summary?, fallback? } }`; `fallback` = `unavailable` / `budget` / `limit` / `ended` / `refusal`-text; `422 empty|long`, `415 type`, `403 origin`, `429 limit`, `503 disabled` |
| POST `lead` | `{ conversation?, submission_id (16–64 [A-Za-z0-9_-]), name, email, phone?, company?, website?, message?, consent: true, consentText, lang, hp: '', openedMs }` → `{ ok, duplicate? }`; `422 fields|consent`, `400 robot`, `429 limit` |

## PHP
- `ts_ai_consultant(array $s): TsAiConsultant` → `serve()`, `handle($method, $route, $raw, $headers, $ip)`, `enabled()`, `priceGuard($text)`, `filterLinks($text)`, `systemPrompt($lang)`.
- `TsAiProvider::respond(['system','messages','tools','maxTokens'])` → `['stop' => end|tool_use|refusal|max_tokens, 'text', 'tools', 'usage', 'assistant']`; chyby `TsAiProviderError(kind)`.
- `TsAiStore`: `migrate, hit, hits, reserve, settle, spent, report, conversation, messages, createLead, cleanup`.
- `ts_ai_harden(['requireHttps' => true, 'paths' => [...]])`, `ts_ai_api_key($s)`.

## Widget
`window.TS_AI_CONFIG = { api, business, email, privacyUrl: 'url' | { cs: '…', '*': '…' }, lang?, maxChars?, checkStatus?, name?, mascot?, avatar?, texts?: { cs: { role, intro, … }, '*': {…} } }`; `window.TSAiWidget.open() / close() / send(text)`. Jazyk z `<html lang>`, přepínač 18 jazyků (arabština RTL). Stav konverzace v `sessionStorage` (jen v záložce).
`mascot: true` = animovaný robůtek místo portrétu (SVG + CSS, stavy z `data-state` na `.tsai`: idle, listening, processing, speaking, error; třída `tsai-beat` na hranici slova). `avatar` = URL statického portrétu, použije se jen bez maskota.

## Databáze
Migrace `migrations/001_ai.{sqlite,mysql}.sql`, rollback `001_ai.down.sql` (smaže data). Tabulky `ts_ai_conversations, ts_ai_messages, ts_ai_usage, ts_ai_reservations, ts_ai_rate, ts_ai_leads, ts_ai_schema` + `ts_mail_queue`.
