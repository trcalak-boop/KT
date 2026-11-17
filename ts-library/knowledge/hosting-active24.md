# Hosting Active24 (tasstudio.eu) – co víme

Zdroj: audit Fáze 0 (30. 10. 2026), kód projektu, záznam nasazení. **Ověřeno** = z kódu nebo logu; **Neověřeno** = nutný test.

| Vlastnost | Stav |
|---|---|
| Apache + PHP-FPM 8.5 (`upstream php85` v error_log) | ověřeno |
| `.htaccess` (mod_alias, mod_headers, mod_expires, mod_deflate, `Require all denied`) | ověřeno nasazením 30. 10. 2026 (403 na admin/data) |
| PDO SQLite | ověřeno (CRM běží) |
| Struktura FTP: `/web` (webroot), `/sub`, `/logs` – existuje úroveň nad webrootem | ověřeno (FileZilla) |
| Přístup PHP mimo webroot (`open_basedir`) | **neověřeno** |
| cURL / odchozí HTTPS | v kódu podporováno, na produkci **neověřeno** |
| SMTP | **nenastaveno** (posílá se přes mail(), stav „předáno“) |
| cron | **neověřeno** – fronty se dnes zpracovávají „při další akci“ |
| `max_execution_time`, `memory_limit`, limity uploadu | **neověřeno** (admin akce `stav` vrací část údajů) |
| HTTPS přesměrování | v `.htaccess` **zakomentované** – před nasazením formulářů s osobními údaji zapnout |
| Staging | neexistuje; nasazení ručně přes FileZillu, rollback skripty projektu |

## Ověřeno diagnostikou na test.tasstudio.eu (31. 10. 2026)
| Vlastnost | Zjištění |
|---|---|
| Server | Apache + PHP-FPM **8.5.12**, Linux, HTTPS funkční (rozhraní hostingu je Websupport) |
| Rozšíření | pdo_sqlite, sqlite3, pdo_mysql, mbstring, openssl, json, session, filter, ctype, curl, intl – vše k dispozici; SQLite funguje |
| `.htaccess` | respektuje `Require all denied` (403); přímý přístup k `.htaccess` blokuje firewall hostingu (kód 466) |
| Struktura FTP | kořen hostingu obsahuje `logs/`, `logs-test.tasstudio.eu/`, `sub/` (subdomény, `sub/test/` = webroot test.tasstudio.eu), `web/` (webroot tasstudio.eu) |
| `open_basedir` | nastavené, ale povoluje celý hosting: PHP čte i zapisuje v `sub/`, v kořeni hostingu i ve `web/` – **testovací kód může technicky sáhnout na produkci**, proto jen ověřený kód a oddělená data |
| Složka mimo webrooty | možná – PHP zapisuje do kořene hostingu (např. `/private/booking-test/`); žádná doména na kořen nemíří |
| Limity | memory_limit 512M, max_execution_time 600, post_max_size 512M, upload 256M; `display_errors` = 0; `disable_functions` prázdné; `mail()` dostupné |
| Časové pásmo PHP | Europe/Bratislava (stejný posun jako Praha) – aplikace si má nastavit Europe/Prague sama |
| SMTP | `smtp.websupport.cz:465` (SSL, AUTH LOGIN) – ověřeno z hostingu 1. 11. 2026: spojení, přihlášení a doručení zkušebního e-mailu. (`smtp.active24.com` z hostingu nedostupný.) |
| cron | neověřeno |

## Testovací nasazení rezervací (1. 11. 2026)
ts-booking-server 1.1.0 na `test.tasstudio.eu`: web `/sub/test/`, data `/private/booking-test/`. Ověřeno: 22/22 kontrol zabezpečení, instalátor smazán (404), rezervace → e-maily (přesměrované do testovací schránky) → potvrzení → blokace → zrušení odkazem. HTTP → HTTPS dělá předřazený server hostingu (openresty). Cron neověřen. Postup a skripty: `~/TS-Studio/nasazeni/test-tasstudio/`.

## Rezervace se serverem
Postup nasazení a kontrolní seznam: `postupy/nasazeni-rezervaci.md`.

## Pravidla pro komponenty
- Bez composeru, npm a dlouho běžících procesů; čisté PHP 8.x + statické soubory.
- Data a tajemství mimo webroot nebo za `Require all denied`; `admin/data/` se na serveru nikdy nepřepisuje.
- Dlouhé úlohy (screenshoty, AI, hromadné e-maily) nepatří na sdílený hosting.
