# DEMO vs. PRODUCTION READY

**DEMO** – komponenta je určená pro ukázkové projekty a prototypy. Může fungovat bezchybně v ukázce, ale nesplňuje produkční checklist.
**PRODUCTION READY** – smí se nasadit pro reálného klienta. Fungování v ukázkovém webu k tomu **nestačí**.

## Checklist pro production-ready (vše musí platit)
1. Data se ukládají na server (DB) se zálohou; žádná produkční data v localStorage/sessionStorage.
2. Serverová validace všech vstupů; u rezervací opakované ověření termínu v transakci.
3. Autentizace a oprávnění na serveru (ts-auth-php), CSRF, limit požadavků, ochrana proti robotům.
4. Bezpečnostní revize kódu (XSS, injekce hlaviček, SQL, IDOR), bez tajemství v kódu a gitu.
5. GDPR: informace o zpracování, souhlasy, retenční lhůta, export a smazání.
6. Automatické testy (jednotkové, integrační, u převzaté logiky rozdílové) + test na cílovém hostingu.
7. Prohlížeče: Chrome, Safari, Firefox; mobil; klávesnice a čtečka (WCAG 2.1 AA základní kontrola).
8. Dokumentace: README, API, CHANGELOG, MIGRATION u hlavní verze, rollback.
9. Licence všech převzatých souborů ověřené a přiložené.
10. Schválení odpovědné osoby T&S Studio (zapsat do CHANGELOG).

Pole `missingForProduction` v `module.json` musí být prázdné; `ts.py validate` hlídá, aby production-ready nemělo nesplněné body.

## Stav k 30. 10. 2026
Všech 13 komponent je **DEMO**. Nejblíž produkci jsou PHP komponenty (kód běží v produkci tasstudio.eu, chybí revize zobecněných tříd a test na hostingu) a ts-booking-core (logika otestovaná, chybí server).
