# Bezpečnostní kontrolní seznam – test.tasstudio.eu

## Ověřeno lokálně (otestovat_balicek.py, 39/39)
- [x] Balíček neobsahuje heslo; SMTP heslo jen v `smtp-heslo.txt` vytvořeném na serveru, mimo webroot; čte se jako text (bez spouštění), v chybách ani logu se neobjeví.
- [x] Instalační kód není v balíčku (jen otisk password_hash); instalátor `{INSTALATOR}` platí do {PLATNOST}, jen HTTPS, CSRF, zámek po 5 špatných kódech; bez kódu nezobrazí ani kontrolu a žádná akce (databáze, SMTP, e-mail, administrátor) neproběhne; administrátor jen jednou (install.lock).
- [x] Databáze, relace, zámek a mail.log jen v `/private/booking-test/data/`; do webrootu aplikace nic nezapisuje; `open_basedir` zúžený za běhu na `/sub/test/` + `/private/booking-test/` (pokus o zápis do `/web/` selže – ověřeno testem).
- [x] Žádný odkaz na produkční `/web/`, CRM databázi ani původní projekt; simulovaný produkční web beze změny.
- [x] Jádro knihovny v balíčku shodné s knihovnou (kontrolní součty, ts.py verify).
- [x] Veřejná dostupnost bez jmen, e-mailů, id a důvodů blokací; správa jen po přihlášení (role, CSRF, zámek přihlášení).
- [x] Dvojitá rezervace stejného termínu odmítnuta (server ověřuje v transakci); zablokovaný termín nejde rezervovat.
- [x] Rezervace z cizího webu (Origin) odmítnuta; ochrana proti robotům a limit 20 rezervací/h z adresy.
- [x] E-maily v testovacím režimu: log, nebo SMTP s přesměrováním jen do testovací schránky.
- [x] Chybové odpovědi bez cest a interních údajů; PHP 8.5 bez varování.
- [x] Stránky `noindex`, `robots.txt` Disallow, hlavičky X-Robots-Tag, nosniff, X-Frame-Options, HSTS.

## HTTPS
- [x] `http://test.tasstudio.eu/` → 301 na `https://` na straně hostingu (ověřeno 31. 10.); bez přesměrování v .htaccess (riziko smyčky); API a instalátor bez HTTPS odmítnou; HSTS.
- [ ] Pozn.: produkční `http://tasstudio.eu/` na HTTPS nepřesměrovává – mimo rozsah testu, doporučeno řešit samostatně.

## Ověří se po nahrání (overit_nasazeni.py – jen čtení)
- [ ] `vendor/ts/*/core|bin|config|migrations` vracejí 403 (hosting respektuje .htaccess – ověřeno diagnostikou).
- [ ] `ts-components.lock.json`, výpis složek → 403; konfigurace a databáze nejsou ve webrootu (404).
- [ ] Instalátor dostupný jen do smazání, pak 404.
- [ ] Hlavička X-Robots-Tag noindex; API dostupnost bez osobních údajů.
- [ ] SMTP spojení z hostingu (instalátor, bez přihlášení); zkušební e-mail do testovací schránky.

## Známá rizika
- Hosting sám dovolí PHP zapisovat v celém účtu (i do `/web/`); aplikace si proto `open_basedir` zužuje sama. Neplatí to pro jiné PHP soubory, než jsou v balíčku – žádné vlastní skripty do `/sub/test/` nepřidávat.
- Cron neověřen – fronta e-mailů se zpracuje při dalších požadavcích (až 2 e-maily na požadavek).
- Test je DEMO: bez revize kódu druhou osobou, bez testů Safari/Firefox, bez právních textů provozovny.
