# Etapa 1 – souhrnná zpráva (30. 10. 2026)

## Umístění
- Knihovna: `/Users/krystof/TS-Studio/ts-library` (lokální git `main`, zatím bez commitu a bez vzdáleného repozitáře)
- Demonstrační projekty: `/Users/krystof/TS-Studio/demo-projekty/kadernictvi-vlna`, `/Users/krystof/TS-Studio/demo-projekty/autosalon-dynamo`
- Zálohy: `/Users/krystof/TS-Studio/zalohy` (práva 700/600) · PHP pro testy: `/Users/krystof/TS-Studio/tools/php/php`

## Komponenty (všechny DEMO)
| Komponenta | Verze | Druh | Zralost | Testy |
|---|---|---|---|---|
| ts-booking-core | 1.0.0 | JS jádro rezervací (resources, pool, capacity, rozšíření) | tested | 3 407 (rozdílové ×3 + jednotkové) |
| ts-booking-widget | 1.0.1 | JS UI rezervací + přehled | tested | 20 + integrace 27 |
| ts-kit-core | 1.0.0 | JS základ ukázek (doslovně) | extracted | 44 |
| ts-receipts-core | 1.0.0 | doklady, DPH, řady, jsPDF | tested | 71 (63 rozdílových) |
| ts-auth-php | 1.0.0 | PHP relace, CSRF, zámek, limit, role | tested | 22 |
| ts-mailer-php | 1.0.0 | PHP SMTP/mail(), fronta | tested | 16 |
| ts-pdf-php | 1.0.0 | tFPDF 1.34 (LGPL, doslovně) | extracted | 5 |
| ts-i18n-demo | 1.0.0 | překladová vrstva ukázek (doslovně) | extracted | 9 |
| ts-i18n-build | 1.0.0 | generátor 18 jazyků (reference) | extracted | – |
| ts-crm-intake | 1.0.0 | poptávka → CRM (reference) | extracted | – (OpenAPI) |
| ts-shop-core | 1.0.0 | e-shop VRSTVA (reference) | extracted | 5 |
| ts-cms-blocks | 1.0.0 | CMS Slunečnice (reference) | extracted | 4 |
| ts-ref-lokna | 1.0.0 | úplná šablona salonu (reference) | extracted | 2 |

## Výsledky testů
- `ts.py validate --origin`: 13 komponent, 0 chyb (manifesty, zamčené soubory, původ = zdroj beze změny).
- `ts.py test`: **3 605 kontrol, 0 chyb** (Chrome bez okna + PHP 8.5).
- `tools/integration_test.py`: **27 kontrol, 0 chyb** – dva projekty, stejné jádro, oddělená data, jiná konfigurace a vzhled, rozšíření, mobil, jádro beze změny.
- Aktualizace 1.0.0 → 1.0.1 přes `ts.py update`: zákaznický vzhled zachován, ruční úprava jádra aktualizaci zastavila, nová hlavní verze bez `--major` odmítnuta (ověřeno na dočasné kopii).
- Testovací sada projektu (`skripty/test/test.py`, spuštěno na kopii, port 8091): spadla v části „Poptávka“ (`#fNext` nenalezen) – stav rozpracovaného projektu, ne knihovny; poslední záznam projektu z 19:38 (před Etapou 1) má 115 chyb „Stránky“.

## Claude Code
Plugin `ts-studio` (5 skills: library, booking, kit-core, php-backend, i18n; hook SessionStart) a lokální marketplace: `claude plugin validate` ✔, `claude plugin details` – loader načte 5 skills + hook. Behaviorální ověření (že Claude knihovnu sám vybere) je připravené jako `claude plugin eval` (4 případy v `claude-plugin/ts-studio/evals/`), spustit lze jen v přihlášeném Claude Code.
