---
name: ai-consultant
description: Textový AI konzultant / chatbot / AI recepční na web T&S Studio (ts-ai-consultant) – PHP brána s Claude přes oficiální SDK, znalosti jen z řízeného souboru, žádné částky (cenová pojistka), limity a měsíční rozpočet, přístupný widget v 18 jazycích, lead se souhlasem do CRM. Použij, když web potřebuje AI asistenta, chat s návštěvníky, kvalifikaci poptávek nebo „AI recepční“. Integruj komponentu přes ts.py add, bránu ani widget nepiš znovu.
when_to_use: „přidej na web AI asistenta“, „chatbot“, „AI recepční“, „AI konzultant“, „ať AI sbírá poptávky“, „chat s návštěvníky“, „napoj Claude na web“.
---

# AI konzultant: ts-ai-consultant

Stav: **DEMO** – otestováno lokálně (falešný poskytovatel + skutečné SDK proti napodobenině API), ještě ne na hostingu se skutečným klíčem.

## Pravidla (neporušovat)
- **API klíč** jen v souboru mimo webroot (`apiKeyFile`), nikdy v kódu, gitu, prohlížeči, chatu ani v logu. Klíč zakládá provozovatel sám.
- **Testy nikdy nevolají skutečné API.** Automatické testy: `provider: 'fake'` nebo napodobenina na localhostu (`claude.baseUrl`). Skutečný dotaz jen ručně přes kontrolní stránku a se souhlasem provozovatele.
- **Znalosti** jen z ověřených zdrojů klienta (JSON, viz `api/ts-ai-consultant.md`). Ceník do znalostí nepatří, když klient ceny uvádět nechce (`pricePolicy.noAmounts: true` + pravidla).
- **Model a rozpočet** rozhoduje provozovatel (výchozí `claude-opus-5-5`, effort `low`, `monthlyBudgetUsd`). Model kvůli ceně sám neměň.
- **Osobní údaje** jen přes formulář widgetu do brány (`?r=lead`), ne k poskytovateli AI. Souhlas povinný, ukládá se jeho text.
- Historii konverzace drží server (token → otisk); prohlížeč nemůže podstrčit odpovědi asistenta.

## Postup integrace
1. `python3 -I ~/TS-Studio/ts-library/tools/ts.py add ts-ai-consultant <projekt>/web` (přidá i ts-auth-php, ts-mailer-php).
2. Neveřejná složka (např. `private/ai/`): kopie `config/consultant.example.php` → `ai-consultant.php`, `znalosti.json`, `data/`, sestavené SDK `vendor/` (viz `sdk/README.md`), klíč doplní provozovatel do `claude-api-klic.txt`.
3. Vstupní bod: `config/api.example.php` → `web/api/ai/chat.php` (`ts_ai_harden` + `ts_ai_consultant(...)->serve()`), cesty upravit.
4. Do stránek: CSS + `window.TS_AI_CONFIG = { api, business, email, privacyUrl }` + `ts-ai-widget.js` s `defer`.
5. Lead do CRM: `leadSink` = funkce `fn(array $lead): ?string`; výjimka lead neztratí (zůstane v DB a přijde e-mail).
6. Na hostingu: jednorázová kontrolní stránka (`TsAiCheck`, kód jako `password_hash`, `until`), pak smazat. Cron: `bin/cron.php` (fronta e-mailů, retence). Útrata: `bin/report.php`.
7. Testy: `python3 -I tools/ts.py test ts-ai-consultant`.

## Co hlídá brána
Cenová pojistka (částka s měnou → nahradí pravidlem), filtr odkazů (jen URL ze znalostí), odmítnutí modelu, výpadek / 429 / 529 / 401 → náhradní text s kontaktem (200, web běží), limity na IP / konverzaci / web, rezervace rozpočtu před každým voláním (odhad shora), Origin + `application/json`, retence.
