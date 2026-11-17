# Etapa 2 – serverový rezervační systém (30. 10. 2026)

## Výsledek
Nová komponenta **ts-booking-server 1.0.0** (PHP 8, bez composeru, SQLite/MySQL) + rozšíření **ts-booking-core 1.1.0** (adaptér `store-http.js`, oprava jádra) a **ts-booking-widget 1.1.0** (serverový režim, přihlášení do správy, zrušení odkazem). Stav všech: **DEMO** – otestováno lokálně, ne na hostingu. Nic nebylo nasazeno.

| Část | Co umí |
|---|---|
| `TsBookingEngine` | PHP port jádra, shodný s JS (rozdílově ověřeno), čas v pásmu provozovny |
| `TsBookingStore` | migrace SQLite/MySQL, transakční založení se zámkem zápisu, idempotence, stavy s povolenými přechody, blokace, audit, GDPR export/anonymizace, retence, uživatelé správy |
| `TsBookingApi` | veřejné API (konfigurace, dostupnost bez osobních údajů, rezervace, zrušení tokenem) + správa (přihlášení, CSRF, role admin/staff/viewer, stavy, blokace, GDPR) |
| `TsBookingNotifier` | e-maily cs/en přes frontu ts-mailer-php (přijato, potvrzeno, odmítnuto, zrušeno, oznámení provozovně) |
| CLI | `create-user`, `cron` (fronta + retence), `migrate` |
| Prohlížeč | `TSBooking.stores.http` / `httpAdmin`, widget `mount` / `mountAdmin` / `mountCancel` |

## Testy
- Knihovna celkem: **3 715 kontrol, 0 chyb** (`ts.py test`).
  - PHP ↔ JS jádro: 27 souborů porovnání, **14 107 dotazů** (Lokna, Motoria, Kořen, rozšíření) ve 3 časových scénářích bez rozdílu.
  - Server (API + DB, bez webového serveru): 53.
  - HTTP + prohlížeč (php -S, kopie demo projektu C): 27 – včetně **8 souběžných požadavků na stejný termín → zapíše se jen jeden**, rezervace přes web, e-mail s odkazem, přihlášení a potvrzení ve správě, jednorázové zrušení odkazem, cron.
- Demo projekty A a B aktualizovány přes `ts.py update` na 1.1.0, integrační test 27/27.
- Nalezená a opravená chyba: JS jádro vynechávalo rezervace bez `id` (veřejná dostupnost ze serveru) – obsazené časy by se ve formuláři dál nabízely; server by je odmítl. Oprava + testy, rozdílové testy beze změny výsledků.

## Demonstrační projekt C
`/Users/krystof/TS-Studio/demo-projekty/kadernictvi-vlna-server` – struktura jako hosting (`web/` + `private/`), README s lokálním spuštěním.

## Co chybí do produkce
Test na Active24 (PHP, open_basedir, .htaccess, cookies pod HTTPS, cron), zapnuté HTTPS, SMTP + SPF/DKIM, právní texty a retenční lhůta provozovatele, bezpečnostní revize druhou osobou, testy Safari/Firefox, zátěžový test SQLite, správa uživatelů v UI (zatím CLI). Postup: `knowledge/postupy/nasazeni-rezervaci.md`.
