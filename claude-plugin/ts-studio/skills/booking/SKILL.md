---
name: booking
description: Rezervační systém T&S Studio (ts-booking-core + ts-booking-widget) – univerzální jádro pro objednávání termínů v libovolném oboru (kadeřnictví, kosmetika, autosalon a testovací jízdy, restaurace a stoly, ordinace, služby, kurzy). Použij, když web potřebuje rezervace, volné termíny, výběr pracovníka/zdroje, kapacitu míst nebo administraci rezervací. Integruj knihovní komponentu přes ts.py add, logiku termínů nikdy nepiš znovu.
when_to_use: „Vytvoř web pro kadeřnictví/autosalon/restauraci/ordinaci a použij náš rezervační systém“, „přidej online objednávání“, „rezervace stolu“, „testovací jízda“, „volné termíny“, „kalendář rezervací“.
---

# Rezervace: ts-booking-core + ts-booking-widget

Stav: **DEMO** (logika ověřená rozdílovými testy proti ukázkám Lokna, Motoria, Kořen; chybí serverová část). V demo režimu se rezervace ukládají jen v prohlížeči – widget to sám oznámí, upozornění neodstraňuj.

## Vrstvy (nemíchat)
| Vrstva | Kde | Smíš měnit? |
|---|---|---|
| Jádro – volné časy, kolize, kapacita, `prepareBooking` | `vendor/ts/ts-booking-core/core/booking-core.js` | ne |
| Úložiště – rozhraní `load/add/update/reset` | `vendor/ts/ts-booking-core/adapters/` | ne (nový adaptér = nová verze knihovny) |
| Konfigurace oboru | `ts-config/ts-booking-core/*.json` | ano |
| UI | `vendor/ts/ts-booking-widget/core/` | ne |
| Vzhled | `ts-theme/ts-booking-widget/tokens.css` (`--tsb-*`) | ano |
| Oborová pravidla | `extensions: [{ name, check(ctx) }]` v kódu projektu | ano |

## Postup integrace
1. `python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py add ts-booking-widget <projekt>` (přidá i ts-booking-core).
2. Vyber předvolbu z `ts-config/ts-booking-core/`: `kadernictvi.json` (zdroje = pracovníci, varianty = délka vlasů), `autosalon.json` (zdroje = vozy, `pool` = prodejci, `leadScope: today-only`), `restaurace.json` (`mode: capacity`, místa), `ordinace.json` (lékař, `gap`). Zkopíruj ji pod názvem projektu a uprav služby, ceny, délky, pracovníky, pracovní dobu, svátky (`closures`), předstih (`leadMin`), rozsah (`maxDays`).
3. Ověř konfiguraci: `TSBooking.validate(config)` musí vrátit `[]`.
4. Stránka:
```html
<link rel="stylesheet" href="vendor/ts/ts-booking-widget/core/booking-widget.css">
<link rel="stylesheet" href="ts-theme/ts-booking-widget/tokens.css">
<div id="rezervace"></div>
<script src="vendor/ts/ts-booking-core/core/booking-core.js"></script>
<script src="vendor/ts/ts-booking-core/adapters/store-demo-local.js"></script>
<script src="vendor/ts/ts-booking-widget/core/booking-widget.js"></script>
<script>
fetch('ts-config/ts-booking-core/salon-vlna.json').then(r => r.json()).then(function (config) {
  var store = TSBooking.stores.demoLocal('salon-vlna', { bookings: [], blocks: [] });
  TSBookingWidget.mount('#rezervace', { config, store, ui: { lang: 'cs', resourceLabel: 'Stylistka', variantLabel: 'Délka vlasů', location: 'Salon Vlna, Brno' } });
});
</script>
```
5. Vzhled přizpůsob jen v `tokens.css` (barvy, písma, zaoblení) a okolním HTML.
6. Přehled rezervací: `TSBookingWidget.mountAdmin(el, { config, store })` – v produkci jen za přihlášením.

## Parametry
Úplný seznam: `/Users/krystof/TS-Studio/ts-library/api/ts-booking-core.md` (konfigurace, stav, funkce) a `api/ts-booking-widget.md` (volby `ui`). Schéma: `components/ts-booking-core/config/schema.json`.

## Oborová pravidla bez zásahu do jádra
Pravidla, která konfigurace neumí (oběd, jen dospělí, servis jen v sudé týdny…), přidej rozšířením:
```js
var obed = { name: 'obed', check: function (c) { return c.t < 780 && c.t + c.dur > 720 ? 'obed' : ''; } };
TSBookingWidget.mount(el, { config, store, extensions: [obed] });
```

## Bezpečnost a zakázané úpravy
- Nepiš vlastní výpočet volných časů ani kolizí; nekopíruj kód jádra do projektu.
- Neupravuj `vendor/ts/**`; `ts.py verify <projekt>` musí hlásit „jádro beze změny“.
- Demo úložiště není databáze: neslibuj klientovi ukládání, e-maily ani administraci s přihlášením. Produkční backend (PHP API, DB, e-maily přes ts-mailer-php, role přes ts-auth-php) je samostatná etapa se schválením – návrh kontraktu: `api/openapi/ts-booking-api.draft.yaml`.
- Na serveru se termín musí ověřit znovu (`prepareBooking`) v transakci.

## Testování
- Knihovna: `python3 -I …/tools/ts.py test ts-booking-core ts-booking-widget` (rozdílové + jednotkové + UI testy, Chrome bez okna).
- Projekt: otevři stránku, projdi službu → den → čas → kontakt → potvrzení; ověř, že obsazený čas zmizí; `ts.py verify <projekt>`.
- Vzory hotových integrací: `/Users/krystof/TS-Studio/demo-projekty/kadernictvi-vlna` a `/Users/krystof/TS-Studio/demo-projekty/autosalon-dynamo`.
