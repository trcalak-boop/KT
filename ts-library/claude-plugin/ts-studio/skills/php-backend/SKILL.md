---
name: php-backend
description: Serverové PHP komponenty T&S kompatibilní se sdíleným hostingem (Active24, PHP 8.x, bez composeru) – přihlášení a relace, CSRF, role a oprávnění, limit požadavků (ts-auth-php), e-maily přes SMTP s frontou (ts-mailer-php), PDF s češtinou (ts-pdf-php, tFPDF), napojení formuláře na CRM (ts-crm-intake). Použij při psaní PHP endpointů, administrace, odesílání e-mailů nebo PDF.
when_to_use: PHP backend, administrace s přihlášením, kontaktní nebo poptávkový formulář, potvrzovací e-maily, generování PDF na serveru, oprávnění rolí.
---

# PHP backend (ts-auth-php, ts-mailer-php, ts-pdf-php, ts-crm-intake)

Všechny komponenty jsou ve stavu **DEMO** (kód je převzatý z produkční administrace tasstudio.eu, ale knihovní třídy ještě neprošly bezpečnostní revizí a testem na hostingu).

## Integrace
```bash
python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py add ts-auth-php <projekt>
python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py add ts-mailer-php <projekt>
python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py add ts-pdf-php <projekt>
```
API a příklady: `/Users/krystof/TS-Studio/ts-library/api/ts-auth-php.md`, `ts-mailer-php.md`, `ts-pdf-php.md`, `ts-crm-intake.md`, `api/openapi/ts-crm-intake.yaml`.

## Povinná pravidla
- Každý stavový POST: `$auth->csrfOk()`; každá akce administrace: `$auth->loggedIn() && $auth->can('<oprávnění>')` – kontrola na serveru, ne jen v UI.
- Data (`dataDir`, SQLite, relace, SMTP heslo) mimo webroot, nebo ve složce s `.htaccess` „Require all denied“; nikdy v gitu.
- E-mail: odesílatel na doméně webu (SPF/DKIM), adresa návštěvníka jen v Reply-To; posílat přes `TsMailQueue` s `dedupeKey` (nic se nepošle dvakrát a nic se neztratí).
- Formuláře: `rateLimit()`, skryté pole a časová kontrola proti robotům, idempotenční klíč.
- tFPDF je LGPL: neupravovat `core/tfpdf.php`; písma s licencí OFL.
- Produkční databázi a hosting neměnit bez výslovného souhlasu.

## Zakázané úpravy
`vendor/ts/**` (jádro), oslabení CSRF/zámku/limitů, logování hesel nebo plných IP adres.

## Testování
`python3 -I …/tools/ts.py test ts-auth-php ts-mailer-php ts-pdf-php` – PHP 8.5 z `~/TS-Studio/tools/php/php`; e-maily proti falešnému SMTP serveru (nic se neodesílá).
