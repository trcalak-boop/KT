---
name: eshop
description: E-shop v knihovně T&S Studio (ts-shop-core, reference z ukázky VRSTVA) – katalog s variantami barva/velikost, sklad, košík, doprava a platba, slevové kódy, objednávky a jejich stavy, faktura s DPH, vrácení a reklamace. Použij, když se má stavět e-shop, košík, objednávkový proces nebo fakturace objednávek – zjistíš, co lze z knihovny převzít, co je jen DEMO a co vyžaduje samostatné schválení (platby, doprava, sklad na serveru).
when_to_use: „Udělej e-shop / internetový obchod“, „přidej košík a objednávky“, „slevové kódy“, „faktura k objednávce“, „vrácení zboží“, ukázka nebo prototyp obchodu pro klienta.
---

# E-shop: ts-shop-core (REFERENCE, DEMO)

**Stav:** `ts-shop-core` je **referenční** komponenta – doslovná kopie logiky ukázky VRSTVA (`components/ts-shop-core/src/vrstva-data.js`, `window.VRSTVA`, závisí na `ts-kit-core`). Není to konfigurovatelný modul: data jedné značky, české texty a DPH 21 % jsou v kódu natvrdo, vše běží v prohlížeči (sessionStorage), bez serveru. Obecný e-shopový modul (`ts-shop-*` s konfigurací a backendem) je samostatná budoucí etapa.

Vždy to uživateli řekni na začátku: co je hotové, co je jen ukázka a co bude potřebovat schválení.

## Kdy co použít
| Situace | Postup |
|---|---|
| Ukázka / prototyp obchodu pro prezentaci | Lze stavět nad `ts-shop-core` + `ts-kit-core` (vložit `ts.py add ts-shop-core <projekt>`), s viditelným upozorněním „ukázka, objednávky se neodesílají“. |
| Výpočty dokladu, DPH, číselné řady faktur | `ts-receipts-core` (`calc`, `vatByLine`, `nextNumber`) – testovaná komponenta, ne kopie z VRSTVA. |
| PDF faktury v prohlížeči / na serveru | `ts-receipts-core.pdf` (jsPDF, MIT) / `ts-pdf-php` (tFPDF, LGPL). |
| E-maily objednávek, přihlášení správy | `ts-mailer-php`, `ts-auth-php` (`/ts-studio:php-backend`). |
| Skutečný obchod pro klienta (produkce) | **Zastav se a vyžádej schválení** samostatné etapy: serverový katalog, sklad a objednávky v DB, platební brána, doprava, právní náležitosti. Do té doby jen návrh. |

## Co reference umí (API `window.VRSTVA`, podrobně `api/ts-shop-core.md`)
- Katalog a varianty: `prod`, `visible`, `badges`, `priceInfo(p, date)`, `saleOn`.
- Sklad: `colorStock`, `totalStock`, `lastFew`, `moveStock(D, items, dir, why)`, `moveLog`.
- Košík a součty: `lines(D, cart)`, `cartCount`, `calc(D, lines, shipId, payId, code)` → `{ sub, disc, ship, fee, total, base, vat, left … }` (doprava zdarma od limitu, dobírka).
- Slevové kódy: `findCode`, `checkCode(D, raw, sub, email)`, `codeState`, `genCode` (procento, částka, doprava zdarma, platnost).
- Objednávky: `placeOrder(D, f)`, stavy `STATUS` (přijatá → zaplacená → připravujeme → odeslaná / k vyzvednutí → doručená, storno), `next`, `advance`, `needsAction`.
- Faktura: `issueInvoice` (řada + splatnost), `invLines` (rozpad DPH po řádcích), `invoiceHTML`, `invoicePdf`.
- Vrácení a reklamace: `addReturn`, `retItems`; hlídání naskladnění `addWatch`, `checkWatch`.

## Přizpůsobení (u prototypu)
- Vzhled: proměnné `--k-*` (ts-kit-core) a vlastní CSS stránky; obsah katalogu přes `VRSTVA.defaults()` → vlastní data v kódu projektu.
- Logiku v `src/vrstva-data.js` **neupravuj** – když je potřeba jiné chování, navrhni nový konfigurovatelný modul v knihovně (nová komponenta, testy), ne úpravu kopie v projektu.

## Bezpečnost a zakázané věci
- **Nikdy neukládej platební údaje** (čísla karet, CVV) – ani v ukázce. Platby jen přes certifikovanou platební bránu (GoPay, Comgate, Stripe…) po samostatném schválení; v ukázce jen simulace výslovně označená.
- Cena, sleva, sklad a součet objednávky se v produkci počítají **na serveru**; klientský výpočet je jen náhled.
- Nekopíruj `vrstva-data.js` do projektu ručně a nevydávej ukázku za funkční obchod.
- Doprava (Zásilkovna, PPL…), DPH a daňové doklady, obchodní podmínky, reklamační řád, GDPR a cookies: jen po ověření a schválení – v referenci jsou texty a sazby ukázkové.

## Co chybí do produkce (výběr z `module.json → missingForProduction`)
Server a databáze (katalog, sklad, objednávky), platební brána a párování plateb, napojení dopravců, konfigurace DPH a měn, e-maily objednávek přes frontu, administrace s rolemi, právní texty, testy mimo Chromium.

## Testování
- Reference: `python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py test ts-shop-core` (kontrola načtení a základních funkcí).
- Prototyp: projdi katalog → košík → slevový kód → doprava a platba → objednávka → faktura; ověř `ts.py verify <projekt>`.
