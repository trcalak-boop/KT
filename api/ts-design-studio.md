# ts-design-studio – API

AI Design Studio pro administraci T&S. Plán a rozhodnutí: projekt T&S `docs/ai/08-design-studio-plan.md`. Nic nenasazuje ani neodesílá – výstupem je náhled a ZIP.

## Sestavení
```php
require __DIR__ . '/../../vendor/ts/ts-design-studio/core/bootstrap.php';
$studio = ts_design_studio([
  'db' => ['dsn' => 'sqlite:' . dirname(__DIR__, 3) . '/private/studio/data/studio.sqlite'],
  'can' => fn (int $uid, string $akce, ?array $projekt): bool => …,   // akce view|edit|accept|export|status; BEZ can() je povoleno vše
  'timezone' => 'Europe/Prague',
]);
```

## Projekty a verze
| Metoda | Výsledek |
|---|---|
| `templates()` | seznam šablon `[id, name, obor, description]` |
| `createProject(['name','source'=>'new'\|'redesign'\|'crm','template'], $uid, $content = [], $tokens = [])` | projekt + verze 1 (přijatá, pokud nemá chyby kontrol) |
| `projects($uid)`, `get($pid, $uid)` | projekt, verze, záznam akcí |
| `setStatus($pid, 'draft'\|'in_review'\|'approved_internal'\|'archived', $uid)` | stav konceptu (akce `status`) |
| `renderVersion($pid, $uid, $content, $tokens, $zprava, $autor = 'human'\|'ai')` | nová verze-kandidát vykreslená ze šablony + `checks` |
| `patchVersion($pid, $uid, ['cesta' => 'obsah', …], $zprava, $autor, $zaklad = null)` | nová verze-kandidát s upravenými textovými soubory |
| `accept($pid, $num, $uid)` | přijetí; `TsStudioChecksFailed` (pole `errors`) při chybách kontrol |
| `reject($pid, $num, $uid)`, `revert($pid, $num, $uid)` | zamítnutí; návrat = nová verze se soubory staré |
| `version()`, `diff($pid, $a, $b, $uid)` | soubory verze; rozdíly `[cesta => {status, unified}]` |
| `exportZip($pid, $num, $uid)` | ZIP (bajty) včetně písem a licencí šablony |
| `preview($pid, $num, $uid, $stranka = 'index.html')` | samostatné HTML pro `<iframe sandbox="allow-scripts" srcdoc>`; CSP bez sítě |

Oprávnění: `TsStudioDenied`. Každá akce se zapisuje do `studio_log`.

## Šablona
`templates/<id>/template.json`: `tokens` (`{var, type: color|font, value, options?, label}`), `slots` (`text|multiline|url|email|phone|color|list` s `max`, `items`, `fields`), `contrast` (dvojice tokenů), `content` (výchozí obsah), `render` (soubory, které se vykreslují), `licenses`. Soubory v `files/`; HTML používá `{{slot}}`, `{{#seznam}}…{{/seznam}}`, `{{^x}}…{{/x}}` – vše escapované. CSS: blok `/*@tokens*/:root{…}/*@end*/`.
Nová šablona = převodní skript v `tools/` (vzor `sablona_restaurace.py`, `sablona_salon.py`); test v `tests/studio_test.php` ji projde automaticky.

## Kontroly (`TsStudioChecks::run($soubory, $sablona?, $tokeny?)`)
`[{level: error|warn, code, file, line, msg}]`. Chyba brání přijetí: placeholder, tag, link, external, js-url, tag-forbidden, form-external, css-braces, css-import, css-external, css-script, js-risk (eval, Function, cookie, parent/top/opener, import), js-brackets, contrast < 3, size, path. Varování: doctype, lang, viewport, title, inline-handler, img-alt, js-risk (síť, úložiště), contrast < 4,5.

## Databáze
`migrations/001_studio.{sqlite,mysql}.sql` (rollback `001_studio.down.sql`): `studio_projects`, `studio_versions` (soubory verze jako JSON, jen textové), `studio_log`, `studio_schema`.

## AI (0.2.0)
Konfigurace: `'ai' => ['provider' => 'cloudflare', 'apiKeyFile', 'accountIdFile', 'model'?, 'maxTokens'?, 'neuronsPerDay' => 3000]` (nebo `'model' => TsStudioModel`).
| Metoda | Výsledek |
|---|---|
| `aiCreate($brief, $uid, ['source', 'lead_ref', 'audit_ref'])` | `{id, version, checks, template, notes, todo[], errors[], tokensFixed[], rights}` |
| `aiEdit($pid, $uid, $pokyn)` | `{num \| null (beze změny), reply, checks, changed[], todo[]}` – verze-kandidát, přijímá člověk |
| `messages($pid, $uid)`, `usage()` | konverzace projektu; spotřeba dne UTC `{calls, neurons, tokens_in, tokens_out, limit, model}` |

Zadání (`TsStudioAi::cleanBrief`): `name, obor, audience, style, services, facts, texts, notes, lang, template?, audit?, rights?`; z CRM `TsStudioAi::briefFromLead($lead, $poznamky)`, z auditu `briefFromAudit($report, $texty, $dalsi)`.
Chyby modelu `TsStudioModelError->kind`: `disabled, auth, limit, timeout, connection, request, format`. Projekt při chybě nevznikne.
Vrstva úprav CSS: blok `/*@ai*/…/*@ai-end*/` na konci `assets/styles.css` (TsStudioAi::aiCss / withAiCss).

## HTTP API a rozhraní (0.3.0)
```php
$studio = ts_design_studio(require $private . '/studio.php');           // klíče se načtou před zúžením open_basedir
(new TsStudioApi($studio, ['uid' => $prihlasenyUzivatel, 'jobsDir' => $private . '/data/ulohy', 'allowedOrigins' => ['https://admin.example']]))->serve();
```
| GET `?r=` | | POST `?r=` (JSON) | |
|---|---|---|---|
| `templates` | šablony, `ai` | `create {name, template, source}` | ruční projekt |
| `list` | projekty, spotřeba | `ai-create {source, brief, lead?, audit?, texts?}` | 202 + `job` |
| `project&pid` | projekt, verze, záznam, konverzace | `ai-edit {pid, text}` | 202 + `job` |
| `version&pid&num` | soubory, obsah, tokeny, kontroly, todo, schéma | `render {pid, base, content, tokens, message}` | nová verze |
| `file&pid&num&path` | zdroj textového souboru | `patch {pid, base, files, message}` | nová verze |
| `diff&pid&a&b` | rozdíl | `accept / reject / revert {pid, num}` | |
| `preview&pid&num` | HTML pro iframe | `status {pid, status}` | |
| `export&pid&num` | ZIP | | |
| `job&id` | stav úlohy (jen stejný uživatel) | | |
Chyby: 400 vstup, 403 původ/oprávnění, 413 velikost, 415 typ, 422 `checks` (seznam chyb), 429 limit AI, 503 AI vypnutá.
Rozhraní: `<div id="ts-studio"></div>`, `window.TS_STUDIO_CONFIG = { api }`, `public/ts-studio.css` + `ts-studio.js`. CSP stránky musí povolit inline skripty a `data:` písma (náhled v `srcdoc` dědí CSP stránky).
