# ts-booking-core

Univerzální jádro rezervací T&S Studio (`window.TSBooking`). Stav: **DEMO** – logika je otestovaná, ale chybí serverová část (viz `module.json → missingForProduction`).

> ⚠️ V demo režimu se rezervace ukládají jen do prohlížeče (`adapters/store-demo-local.js`), ne do serverové databáze.

## Vrstvy
| Vrstva | Soubor | V projektu |
|---|---|---|
| Funkční jádro | `core/booking-core.js` | `vendor/ts/ts-booking-core/` – zamčeno |
| Datová vrstva (úložiště) | `adapters/store-demo-local.js` (později `store-http.js` pro PHP API) | zamčeno |
| Konfigurace | `config/schema.json`, `config/presets/*.json` | `ts-config/ts-booking-core/` – patří projektu |
| Vzhled a texty | komponenta `ts-booking-widget` | `ts-theme/…` |
| Oborová pravidla | `opts.extensions` | kód projektu |

## Rychlé použití
```js
var engine = TSBooking.create(config, state);              // state = { bookings: [], blocks: [] }
engine.slots('2026-11-04', 'damsky-strih', { variant: 1, resource: 'any' });   // [{ t: 540, resources: ['stylistka-1'] }, …]
var p = engine.prepareBooking({ date, service, start, variant, resource: 'any', name, email });
if (p.ok) store.add(p.booking, idempotencniKlic);
```
Režimy a předvolby: kadeřnictví / kosmetika (`resources`), autosalon (`resources` + `pool`), restaurace (`capacity`), ordinace (`resources` + `gap`).

API: `api/ts-booking-core.md`. Skill: `/ts-studio:booking`.
