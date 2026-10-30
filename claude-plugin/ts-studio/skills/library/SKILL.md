---
name: library
description: Hlavní pravidla knihovny T&S Studio Components & Skills (~/TS-Studio/ts-library). Použij VŽDY, když se staví nový web, ukázka, prototyp nebo funkce pro T&S Studio či jeho klienta – rezervace a objednávání termínů (kadeřnictví, salon, autosalon, restaurace, ordinace, služby), administrace, kalendář, formuláře, modální okna, e-maily, PDF, doklady a faktury, přihlášení a role, CRM a poptávky, e-shop, aktuality a CMS, vícejazyčný web. Nejdřív najdi hotovou komponentu v registru, teprve potom piš nový kód.
when_to_use: Na začátku jakékoli práce na webu nebo webové funkci; když uživatel řekne „použij náš rezervační systém / naši knihovnu / naše komponenty“; před psaním vlastní logiky termínů, košíku, e-mailů, PDF nebo přihlášení.
---

# T&S Studio Library – jak s ní pracovat

Knihovna: `/Users/krystof/TS-Studio/ts-library` (lokální git, bez vzdáleného repozitáře).
Nástroj: `python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py <příkaz>` (jen standardní Python, nic neinstaluje).

## 1. Nejdřív hledej, potom programuj
1. Přečti registr: `python3 -I …/tools/ts.py list` nebo `registry/components.json` (název, verze, stav, co funguje, co chybí).
2. Pro vybranou komponentu přečti `components/<název>/README.md`, `api/<název>.md` a příslušný skill (`/ts-studio:booking`, `/ts-studio:kit-core`, `/ts-studio:php-backend`, `/ts-studio:i18n`).
3. Řekni uživateli, které komponenty použiješ a v jakém stavu jsou. Funkce, kterou knihovna má, se **znovu neprogramuje** – ani „zjednodušeně“, ani „jen pro tento projekt“.
4. Když komponenta chybí nebo nestačí, navrhni rozšíření knihovny (nová verze komponenty) místo kopie kódu do projektu.

| Potřeba | Komponenta |
|---|---|
| Rezervace, volné termíny, kapacita, směny, testovací jízdy, objednání k lékaři | `ts-booking-core` + `ts-booking-widget` |
| Bohaté UI salonu (kalendář po pracovnících, doklady, ICS feed) jako vzor | `ts-ref-lokna` (jen reference, logika přes ts-booking-core) |
| Modální okna, toast, formuláře, shell administrace, ikony, datum, kalendářová mřížka | `ts-kit-core` |
| Doklady, faktury, DPH, číselné řady, PDF v prohlížeči | `ts-receipts-core` (+ jsPDF) |
| PDF na serveru (PHP) | `ts-pdf-php` |
| Přihlášení, CSRF, role, limit požadavků (PHP) | `ts-auth-php` |
| E-maily, SMTP, fronta (PHP) | `ts-mailer-php` |
| Poptávkový formulář → CRM | `ts-crm-intake` (reference) |
| E-shop (košík, objednávky, sklad, slevy) | `ts-shop-core` (reference, DEMO) |
| Aktuality, jídelníček, akce, kroužky | `ts-cms-blocks` (reference, DEMO) |
| Jazykové verze | `ts-i18n-demo`, `ts-i18n-build` |

## 2. Vložení do projektu – vždy standardním postupem
```bash
python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py add <komponenta> <cesta-k-projektu>   # i se závislostmi
python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py verify <cesta-k-projektu>             # jádro beze změny?
python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py update <komponenta> <cesta-k-projektu>
```
Výsledek v projektu:
- `vendor/ts/<komponenta>/` – **jádro, nikdy needitovat** (hlídá `ts-components.lock.json`).
- `ts-theme/<komponenta>/` – vzhled projektu (CSS proměnné), upravovat smíš.
- `ts-config/<komponenta>/` – konfigurace projektu (JSON), upravovat smíš.
Nikdy nekopíruj soubory komponent ručně a nepřepisuj je – `update` odmítne aktualizaci, když jádro někdo změnil.

## 3. Stavy – mluv pravdu
- `demo` = funguje v ukázkách a prototypech. **Neříkej, že je to produkční řešení.** U rezervací v demo režimu musí být vidět upozornění, že se data neukládají na server.
- `production-ready` = splněný checklist v `knowledge/demo-vs-produkce.md`. Zatím **žádná** komponenta tento stav nemá.
- Co chybí pro produkci, je v `module.json → missingForProduction` – uveď to uživateli, když se ptá na nasazení.

## 4. Co je zakázané
- Upravovat `vendor/ts/**` v projektu nebo `components/*/core|adapters|src` v knihovně bez nové verze, testů a schválení.
- Psát vlastní výpočet volných termínů, kolizí, kapacity, DPH nebo číslování dokladů vedle knihovny.
- Ukládat platební údaje; nasazovat cokoli na produkci (tasstudio.eu, Active24) bez výslovného souhlasu.
- Měnit původní projekt `/Users/krystof/Downloads/T&S webstudio_28-10` – knihovna z něj jen čte (`tools/sources.json`).

## 5. Změny v knihovně samotné
Nová verze = úprava v `components/<n>/`, zápis do `CHANGELOG.md`, zvýšení `version` (SemVer: změna API/dat = hlavní verze + `MIGRATION.md`), `ts.py lock <n>`, `ts.py test <n>`, `ts.py registry`, `ts.py validate --origin`. Postup: `knowledge/postupy/nova-verze-komponenty.md`.

Další znalosti: `knowledge/` (hosting Active24, bezpečnost, GDPR, licence, známá omezení).
