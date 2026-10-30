# API: ts-booking-server 1.0.0

Serverová část rezervací v PHP 8 (bez composeru). Závisí na `ts-booking-core` ^1.1.0 (konfigurace oboru, JS jádro pro prohlížeč), `ts-auth-php` a `ts-mailer-php`.

## Instalace do projektu (struktura jako na hostingu)
```
python3 -I ~/TS-Studio/ts-library/tools/ts.py add ts-booking-server <projekt>/web
python3 -I ~/TS-Studio/ts-library/tools/ts.py add ts-booking-widget <projekt>/web
```
```
web/                     webroot
  api/rezervace.php      vstupní bod (kopie vendor/ts/ts-booking-server/config/api.example.php)
  vendor/ts/…            jádro knihovny (PHP složky mají .htaccess „Require all denied“)
private/                 MIMO webroot – nikdy do gitu
  booking-server.php     serverová konfigurace (kopie config/server.example.php)
  rezervace-config.json  konfigurace oboru (služby, zdroje, pracovní doba…)
  data/                  SQLite databáze, relace správy
```
Uživatel správy: `php web/vendor/ts/ts-booking-server/bin/create-user.php private/booking-server.php <jméno> <admin|staff|viewer> "Celé jméno" [e-mail]` (heslo na vstupu). Cron (např. každých 10 min): `php …/bin/cron.php private/booking-server.php` – odešle frontu e-mailů a anonymizuje staré rezervace. Migrace: `…/bin/migrate.php` (jinak automaticky při prvním požadavku).

## Serverová konfigurace (`config/server.example.php`)
| Klíč | Význam |
|---|---|
| `db.dsn` (`user`, `pass`, `autoMigrate`) | `sqlite:/cesta.sqlite` nebo `mysql:host=…;dbname=…;charset=utf8mb4` |
| `configFile` | JSON konfigurace oboru (schéma ts-booking-core) |
| `authDir`, `cookiePath`, `sessionName`, `policy` | správa: relace, zámek přihlášení, role (výchozí `TsBookingApi::POLICY`) |
| `autoConfirm` | `true` = rezervace hned potvrzená, jinak „čeká na potvrzení“ |
| `rateLimit.bookings` | `[počet, sekund]` pokusů o rezervaci z jedné adresy (otisk IP) |
| `minFillSeconds`, `allowedOrigins` | ochrana proti robotům a cizím webům |
| `retentionDays` | anonymizace osobních údajů po termínu (cron) |
| `mail` | konfigurace TsMailer (`from` povinné, SMTP volitelné); bez `from` se e-maily neposílají |
| `notify` | `business`, `staffEmail`, `cancelUrl` (s `{id}`, `{token}`), `adminUrl`, `currency`, `texts` (přepis šablon cs/en) |
| `extensions` | oborová pravidla – PHP funkce `fn(array $ctx): string` (stejná logika jako JS rozšíření) |

## HTTP API (`api/rezervace.php?r=…`, JSON)
| Metoda, r | Přístup | Vstup → výstup |
|---|---|---|
| GET `config` | veřejné | → `{ ok, config }` (veřejná konfigurace oboru) |
| GET `availability` (`from`, `to`) | veřejné | → `{ ok, from, to, today, nowMin, state: { bookings, blocks } }` – bez id, jmen, kontaktů a důvodů blokací; rozsah oříznut na dnes…maxDays |
| POST `bookings` | veřejné | `{ date, service, start, variant?, addons?, resource?, units?, name, email, phone?, note?, lang?, consent: true, hp: '', openedMs }` + hlavička `Idempotency-Key` → `201 { ok, booking, cancelToken }`, `200 { duplicate: true }`, `409 { error: taken \| capacity \| pool \| past }`, `422 { error: fields \| closed \| service }`, `400 robot`, `403 origin`, `429 limit` |
| POST `cancel` | veřejné (token) | `{ id, token }` → `200` / `403 token` / `409 transition`; token jednorázový, v DB jen otisk |
| POST `admin/login` | – | `{ username, password }` → `{ ok, user, csrf }`; zámek po 6 pokusech (`429 locked`) |
| POST `admin/logout`, GET `admin/me` | přihlášený | `me` → `{ user, csrf, can: [...] }` |
| GET `admin/bookings` (`from`, `to`, `status`) | `booking.view` | → rezervace s kontakty + blokace |
| POST `admin/status` | `booking.edit` | `{ id, status, note? }`; přechody pending→confirmed/rejected/cancelled, confirmed→done/noshow/cancelled; e-mail zákazníkovi |
| POST `admin/blocks`, `admin/blocks/delete` | `booking.blocks` | `{ date, resource ('all'), from, to, reason }` / `{ id }` |
| GET `admin/export` (`email`), POST `admin/anonymize` | `booking.gdpr` (jen admin) | GDPR export / anonymizace |

Všechny POST správy vyžadují hlavičku `X-CSRF` (token z login/me). Role: `admin` = vše, `staff` = view + edit + blocks, `viewer` = jen čtení.

## PHP třídy
- `TsBookingEngine($config, $state, ['clock' => …, 'extensions' => […]])` – stejné metody jako JS engine (`slots, scan, freeTimes, dayState, nextFree, conflicts, capacityTimes, prepareBooking, pickResource, pickPoolMember, calc, dayHours, window`), `TsBookingEngine::validate()`. Čas v pásmu `config.ics.timezone`.
- `TsBookingStore::open($dsn)` – `migrate, state, create (transakce + idempotence), setStatus, cancelByToken, listAdmin, addBlock, deleteBlock, exportByEmail, anonymize, anonymizeOlderThan, events, createUser, verifyUser`.
- `TsBookingApi(settings)->handle($method, $route, $query, $body, $headers)` / `->serve()`.
- `ts_booking_api(array $settings)` v `core/bootstrap.php`.

## Prohlížeč
`TSBooking.stores.http(apiUrl, { lang })` (veřejný formulář) a `TSBooking.stores.httpAdmin(apiUrl)` (správa) v `ts-booking-core/adapters/store-http.js`; widget: `mount`, `mountAdmin` (přihlášení), `mountCancel` (zrušení odkazem).

## Databáze
Migrace `migrations/001_booking.{sqlite,mysql}.sql`, rollback `001_booking.down.sql` (smaže data – předtím záloha). Tabulky `ts_booking_bookings, ts_booking_blocks, ts_booking_events (audit), ts_booking_users, ts_booking_schema` + `ts_mail_queue` (ts-mailer-php).
