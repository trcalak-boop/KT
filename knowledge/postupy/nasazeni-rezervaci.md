# Nasazení serverových rezervací (ts-booking-server) na sdílený hosting

> Nasazení na produkci jen s výslovným souhlasem uživatele. Na tasstudio.eu se zatím nic nenasazuje.

## Před nasazením (kontrolní seznam)
1. **HTTPS** na doméně a přesměrování HTTP → HTTPS (cookies správy jsou `Secure` jen pod HTTPS).
2. **PHP 8.1+** s PDO SQLite (nebo MySQL), mbstring, openssl; ověřit `open_basedir` – PHP musí číst `private/` vedle webrootu. Když to hosting nedovolí: `private/` dát do webrootu a zakázat `.htaccess` „Require all denied“.
3. Hosting **Apache s .htaccess** – složky `vendor/ts/ts-booking-server/{core,bin,config,migrations}` mají `Require all denied`; ověřit, že vrací 403.
4. **SMTP** přístupy a SPF/DKIM domény odesílatele (jinak `mail()` – doručení nepotvrzeno). Test: rezervace → e-mail zákazníkovi i provozovně.
5. **cron** `php …/bin/cron.php …/private/booking-server.php` každých 10 min (fronta e-mailů, retence). Bez cronu se fronta zpracuje při dalších požadavcích.
6. Právní texty provozovny: informace o zpracování osobních údajů u formuláře, storno podmínky, retenční lhůta (`retentionDays`) schválená provozovatelem.
7. `allowedOrigins` = doména webu; `rateLimit` podle provozu.
8. Uživatelé správy přes `bin/create-user.php`, na hostingu bez SSH přes jednorázový `TsBookingInstaller` (soubor po instalaci smazat).
9. E-maily nejdřív v testovacím režimu (`transport: log`, pak `smtp` + `redirectTo`). Vzorové nasazení: `~/TS-Studio/nasazeni/test-tasstudio/` (sestavit_balicek.py, otestovat_balicek.py, overit_nasazeni.py).

## Nasazení
1. Záloha hostingu (soubory + databáze).
2. Nahrát `web/` (bez `private/`) a zvlášť `private/` mimo webroot; `private/data/` zapisovatelná pro PHP, práva co nejužší.
3. Otevřít `/api/rezervace.php?r=config` → `{"ok":true…}`; migrace proběhne automaticky.
4. Zkušební rezervace, potvrzení ve správě, zrušení odkazem, kontrola e-mailů; zkušební data smazat (anonymizace) nebo celou DB před spuštěním nahradit prázdnou.

## Rollback
Kód: nahrát předchozí verzi `web/`. Databáze: obnovit zálohu `private/data/rezervace.sqlite` (migrace 001 má `001_booking.down.sql`, ale smaže data). Aktualizace knihovny: `ts.py update` + `ts.py verify` lokálně, otestovat, teprve pak nahrát.
