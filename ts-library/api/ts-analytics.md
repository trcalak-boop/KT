# ts-analytics – API

## Sběr (vstupní bod projektu, např. `/api/stat/hit.php`)
`POST` tělo JSON (posílá `public/ts-analytics.js`, `Content-Type: text/plain` kvůli sendBeacon):

| pole | význam |
|---|---|
| `t` | `pv` zobrazení stránky, `ev` událost |
| `p` | cesta stránky (parametry a kotva se zahodí) |
| `r` | odkazující stránka – uloží se jen doména, vlastní domény ne |
| `u` | UTM `{ s: source, m: medium, c: campaign }` |
| `l`, `w` | jazyk stránky, šířka okna (→ telefon / tablet / počítač) |
| `v`, `s` | ID návštěvníka a návštěvy z cookies `ts_v` / `ts_s` (16 znaků a–z0–9) |
| `n` | 1 = nový návštěvník |
| `e` | název události `[a-z0-9_]{1,40}` |

Odpovědi: `204` uloženo (i zahozený robot), `400` neplatná data, `403` chybí / cizí Origin, `405`, `413` velké tělo, `429` limit.

## Prohlížeč (`public/ts-analytics.js`)
- `TsAnalytics.start({ api, visitorDays?: 395, sessionMinutes?: 30, domain? })` – **jen po souhlasu**; nastaví cookies a pošle zobrazení.
- `TsAnalytics.event(name)`, `TsAnalytics.stop()` (smaže cookies), `TsAnalytics.running()`.

## PHP
- `ts_analytics($settings)->report($od, $do)` → pole souhrnů (`totals`, `previous`, `daily`, `pages`, `channels`, `referrers`, `campaigns`, `languages`, `devices`, `browsers`, `systems`, `events`).
- `TsAnalyticsReport::html($report, ['events' => [klíč => název], 'periods' => [7, 30, 90], 'url' => fn(int $dni): string])` + `public/ts-analytics-report.css`.
