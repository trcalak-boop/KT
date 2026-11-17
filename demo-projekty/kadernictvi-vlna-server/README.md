# Salon Vlna (server) – demonstrační projekt C

Stejný salon jako projekt A, ale **rezervace se ukládají na server** (ts-booking-server 1.0.0: PHP + SQLite) – e-maily přes frontu, správa s přihlášením, zrušení odkazem. Komponenty vložené `ts.py add ts-booking-server web` a `ts.py add ts-booking-widget web`.

Struktura jako na hostingu Active24:
```
web/        webroot: index.html, sprava.html, rezervace-zrusit.html, api/rezervace.php, vendor/ts/…, ts-config/, ts-theme/
private/    MIMO webroot: booking-server.php (serverová konfigurace), rezervace-config.json, data/ (SQLite, relace), mail.log
```
Lokální spuštění (PHP z ~/TS-Studio/tools/php):
```
~/TS-Studio/tools/php/php web/vendor/ts/ts-booking-server/bin/create-user.php private/booking-server.php majitel admin "Majitelka"
~/TS-Studio/tools/php/php -S localhost:8103 -t web
```
→ http://localhost:8103 (rezervace), http://localhost:8103/sprava.html (správa). E-maily se v ukázce zapisují do `private/mail.log`.

> ⚠️ Stav **DEMO**: serverová část je otestovaná lokálně, ne na hostingu. Před nasazením viz `vendor/ts/ts-booking-server/module.json → missingForProduction`.
