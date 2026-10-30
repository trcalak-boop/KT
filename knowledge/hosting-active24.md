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

## Rezervace se serverem
Postup nasazení a kontrolní seznam: `postupy/nasazeni-rezervaci.md`.

## Pravidla pro komponenty
- Bez composeru, npm a dlouho běžících procesů; čisté PHP 8.x + statické soubory.
- Data a tajemství mimo webroot nebo za `Require all denied`; `admin/data/` se na serveru nikdy nepřepisuje.
- Dlouhé úlohy (screenshoty, AI, hromadné e-maily) nepatří na sdílený hosting.
