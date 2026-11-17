# ts-site-audit – API

Audit existujícího webu pro AI konzultanta a administraci. Plán a rozhodnutí: projekt T&S `docs/ai/07-audit-webu-plan.md`.

## Sestavení
```php
require __DIR__ . '/../../vendor/ts/ts-site-audit/core/bootstrap.php';
ts_site_audit(require dirname(__DIR__, 3) . '/private/audit/site-audit.php')->serve();
```
Konfigurace: `config/audit.example.php` (kopie mimo webroot). Klíč Google (`meter.apiKeyFile`) povinný, hodnocení AI (`vision`) volitelné.
U `source = 'admin'` musí vstupní bod chránit přihlášení administrace.

## HTTP
| Trasa | Tělo / parametry | Odpověď |
|---|---|---|
| `POST ?r=audit` | `{ url, lang?, ref? }` | `202 { ok, id, status, cached?, shared? }`; `422 { error: 'url', reason }`; `429 limit`; `503 disabled` |
| `GET ?r=audit-status&id=` | token | `{ ok, status: queued\|desktop\|vision\|done\|error, progress, url, report?, cached?, error? }` |
| `GET ?r=audit-shot&id=&d=mobile\|desktop[&full=1]` | token | obrázek (JPEG/WEBP), jen hotový audit |

Každý dotaz na stav po odeslání odpovědi posune úlohu o jeden krok (mobil → desktop → hodnocení → report). Klient se ptá zhruba každé 3 s.
`reason` u adresy: `empty, long, scheme, host, ip, port, userinfo, private, dns`. Chyba úlohy: `limit, auth, timeout, connection, site, request, lost`.

## Zobrazení
`<div id="ts-audit"></div>` + `window.TS_AUDIT_CONFIG = { api, lang: 'cs'|'en', admin: true }` + `public/ts-audit.js`, `public/ts-audit.css`. `TSAudit.run(url)` spustí audit z kódu.
Administrace: `GET ?r=audit-list`, `audit-status`/`audit-shot` s `job=<číslo>`; `access` = přístupová cookie (staging).

## Report
```json
{ "url": "https://example.cz/", "finalUrl": "…", "lang": "cs", "measuredAt": "2026-11-01T20:00:00.000Z",
  "source": { "name": "Google PageSpeed Insights (Lighthouse)", "version": "12.0.0", "kind": "lab" },
  "devices": { "mobile": { "scores": { "performance": 54, "accessibility": 81, "best-practices": 96, "seo": 90 },
                           "metrics": { "lcp": { "value": 4823.4, "display": "4,8 s", "score": 0.31 }, "fcp": {}, "tbt": {}, "cls": {}, "si": {} },
                           "issues": [{ "category": "performance", "id": "…", "title": "…", "display": "…", "score": 0.2 }], "screenshot": true, "fullpage": true },
               "desktop": { … } },
  "recommendations": [{ "basis": "measurement", "category": "…", "text": "…", "detail": "…", "devices": ["mobile", "desktop"] },
                      { "basis": "ai", "area": "…", "text": "…", "priority": 1, "devices": ["desktop"] }],
  "ai": { "ok": true, "model": "…", "reason": "", "device": "desktop" }, "warnings": [], "notes": ["lab", "single", "aiSubjective"] }
```
`basis: measurement` = fakt z měření (titulky Lighthouse v jazyce auditu), `basis: ai` = subjektivní doporučení modelu, nikdy s čísly.
`notes` zobrazit vždy: laboratorní měření (ne reální uživatelé), jednorázové měření s datem, doporučení AI jsou subjektivní.

## Databáze
`migrations/001_audit.{sqlite,mysql}.sql` (rollback `001_audit.down.sql`): `ts_audit_jobs`, `ts_audit_rate`, `ts_audit_schema`. Snímky jako soubory v `shotDir`.
