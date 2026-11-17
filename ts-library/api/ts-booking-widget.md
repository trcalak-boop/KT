# API: ts-booking-widget 1.0.0

Závisí na `ts-booking-core` (načíst dřív). Globální `window.TSBookingWidget`.

```html
<link rel="stylesheet" href="vendor/ts/ts-booking-widget/core/booking-widget.css">
<link rel="stylesheet" href="ts-theme/ts-booking-widget/tokens.css">
<script src="vendor/ts/ts-booking-core/core/booking-core.js"></script>
<script src="vendor/ts/ts-booking-core/adapters/store-demo-local.js"></script>
<script src="vendor/ts/ts-booking-widget/core/booking-widget.js"></script>
```

## `TSBookingWidget.mount(el, options) → Promise<{ render, state, engine() }>`
| Volba | Význam |
|---|---|
| `config` | konfigurace ts-booking-core (typicky `ts-config/ts-booking-core/<obor>.json`) |
| `store` | adaptér úložiště (`TSBooking.stores.demoLocal(klíč, seed)`); když `store.demo`, zobrazí se povinné upozornění |
| `extensions` | oborová pravidla předaná do jádra |
| `onBooked(booking, duplicate)` | volá se po uložení |
| `ui.lang` | `cs` (výchozí) / `en`; `ui.locale` pro formát dat a měny |
| `ui.labels` | přepsání libovolných textů (`W.LABELS.cs` = seznam klíčů) |
| `ui.serviceLabel`, `ui.variantLabel`, `ui.resourceLabel`, `ui.unitsLabel` | názvy kroků podle oboru (Stylistka, Vůz, Počet hostů…) |
| `ui.chooseResource` (`true`), `ui.allowAny` (`true`) | výběr zdroje a volba „Kdokoli volný“ |
| `ui.services` | omezení na vybraná id služeb |
| `ui.showPrices` (`true`), `ui.currency` (`Kč`) | ceny |
| `ui.days` (14), `ui.unitsMax` (8), `ui.unitsDefault` (2) | rozsah nabídky |
| `ui.fields` | `['name','email','phone','note']` – pořadí a výběr polí (jméno, e-mail a souhlas jsou povinné) |
| `ui.consentText`, `ui.location`, `ui.eventTitle`, `ui.icsName` | souhlas a kalendářová událost |

## `TSBookingWidget.mountAdmin(el, { config, store, ui })`
Seznam rezervací s potvrzením a zrušením (v produkci jen za přihlášením).

## Vzhled
Jen proměnné `--tsb-*` (viz `module.json → themeTokens`) v `ts-theme/ts-booking-widget/tokens.css`. Třídy mají předponu `tsb-`.
