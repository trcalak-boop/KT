# API: ts-booking-core 1.0.0

Globální objekt `window.TSBooking` (v Node/PHP-JS prostředí `module.exports`). Časy jsou **minuty od půlnoci** (540 = 9:00), data řetězce `YYYY-MM-DD`, dny v týdnu `0 = neděle`.

## Konfigurace (`config/schema.json`, výchozí hodnoty `TSBooking.DEFAULTS`)

| Klíč | Význam | Výchozí |
|---|---|---|
| `mode` | `resources` (zdroje a služby) / `capacity` (sdílená místa) | `resources` |
| `step`, `minStep` | krok nabídky začátků; krok je aspoň `minStep` | 15, 5 |
| `gap` | pauza po každé rezervaci | 0 |
| `leadMin`, `leadScope` | nejkratší předstih; `all-days` (přes půlnoc) / `today-only` | 0, `all-days` |
| `adminMinStart` | administrace: `none` (bez omezení času) / `now` | `none` |
| `maxDays` | kolik dní dopředu | 60 |
| `activeStatuses` | stavy, které zabírají čas | `['pending','confirmed']` |
| `hours[7]` | `{ open, from, to }` provozovny | – (povinné) |
| `closures` | `[{ from, to, open?, hours?: [from,to], note? }]` | `[]` |
| `fullDayBlockCloses` | blokace `all` přes celou dobu zavře den | `true` |
| `blocksIncludeBuffer` | blokace se porovnává i s přípravou po službě | `true` |
| `services` | `[{ id, name, durations[], prices[], buffer, active }]` – index = varianta | – |
| `variants` | názvy variant pro UI (např. délka vlasů) | – |
| `addons` | `[{ id, name, services[], durations[], prices[] }]` | `[]` |
| `resources` | `[{ id, name, services: '*' \| [ids], hours?[7], away?[{from,to}], bookable }]` | – |
| `pool` | `{ members: [ids] }` – souběh omezený počtem lidí | `null` |
| `spread` | `{ perDay, minGapMin }` pro `nextFree` | `{2, 90}` |
| `capacity` | `{ units, step, sampleMin, lastBeforeCloseMin, todayLeadMin, serviceStart: { svcId: min } }` | `null` |
| `ics` | `{ prodId, uidDomain, timezone, alarmMin }` | T&S výchozí |

## Stav (data)
```js
state = {
  bookings: [{ id, date, resource, service, start, dur, buffer?, status, units?, poolMember?, name?, … }],
  blocks:   [{ date, resource: id | 'all' | null, from, to, reason? }]
}
```
Stav se čte při každém volání – po přidání rezervace do `state.bookings` není nutné engine vytvářet znovu.

## Funkce
| Volání | Vrací |
|---|---|
| `TSBooking.validate(config)` | `[]` nebo seznam chyb |
| `TSBooking.create(config, state, { clock?, extensions? })` | engine `E` |
| `E.calc(svcId, variant?, addons?)` | `{ dur, price, buffer }` |
| `E.dayHours(date)` | `{ open, from, to, note? }` |
| `E.window(resource, date)` | `[from, to]` nebo `null` |
| `E.scan(date, resId, svcId, { variant, addons, admin, except })` | `[{ t, ok, why }]`, `why` = `past` \| `busy` \| `block` \| `pool` \| důvod rozšíření |
| `E.freeTimes(date, resId, svcId, o)` | `[t…]` |
| `E.slots(date, svcId, { variant, addons, resource: id \| 'any', admin, except })` | `[{ t, resources: [id…] }]` |
| `E.dayState(date, svcId, o)` | `{ s: past \| closed \| free \| full, n }` |
| `E.nextFree(svcId, o, limit)` | `[{ date, t, resource }]` |
| `E.pickResource(date, ids)` | nejméně vytížený zdroj |
| `E.pickPoolMember(date, time, dur, exceptId?)` | volný člen poolu nebo `''` |
| `E.conflicts(date, start, dur, buffer, resId, exceptId?)` | `{ items: [{ type: booking \| block, … }], inHours, away }` |
| `E.capacityTimes(date, units, svcId, exceptId?)` | `[{ t, ok, left }]` |
| `E.capacityLoad(date, t, dur, exceptId?)` | nejvyšší obsazenost v intervalu |
| `E.prepareBooking(req)` | `{ ok: true, booking }` / `{ ok: false, error: service \| closed \| taken \| capacity \| pool \| <rozšíření> }` |
| `E.ics(event)`, `E.gcal(event)`, `TSBooking.ics(event, opt)`, `TSBooking.gcal(event, opt)` | text .ics / odkaz Google Kalendáře |
| `TSBooking.date.{iso, parse, addDays, diffDays, wd, hm}` | práce s daty |

`event = { uid, title, date, start, end, location?, details? }`.

## Rozšíření (oborová pravidla bez zásahu do jádra)
```js
TSBooking.create(config, state, { extensions: [{
  name: 'bez-obeda',
  check: function (ctx) {            // ctx = { engine, date, t, dur, resource, service, opts }
    return ctx.t < 780 && ctx.t + ctx.dur > 720 ? 'obed' : '';   // '' = povoleno, jinak důvod
  }
}] });
```

## Úložiště (`adapters/`)
Rozhraní, které musí mít každý adaptér (demo dnes, serverový v další etapě):
`load() → Promise<state>`, `add(booking, idempotencyKey?) → Promise<{ ok, booking, duplicate? }>`, `update(id, changes) → Promise<{ ok, booking }>`, `reset()`, `demo: boolean`.

`TSBooking.stores.demoLocal(key, seedState)` – localStorage, **jen ukázky**.

## Kompatibilita
- Změna významu konfigurace, struktury `state` nebo návratových hodnot = **nová hlavní verze** + `MIGRATION.md`; `ts.py update` ji bez `--major` odmítne.
- Nové volitelné klíče s výchozí hodnotou zachovávající chování = vedlejší verze.
