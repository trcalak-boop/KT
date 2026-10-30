---
name: i18n
description: Jazykové verze webů T&S – registr 18 jazyků (en, cs, de, es, fr, pt, it, pl, nl, uk, ar RTL, hi, zh-CN, ja, ko, tr, id, ru), generátor statického vícejazyčného webu s hreflang a sitemap (ts-i18n-build) a překladová vrstva ukázek nad DOM (ts-i18n-demo). Použij při tvorbě vícejazyčného webu nebo překladu ukázky.
when_to_use: Vícejazyčný web, přepínač jazyka, hreflang, překlad ukázky do en/de, RTL arabština.
---

# Lokalizace (ts-i18n-demo, ts-i18n-build)

**ts-i18n-demo (DEMO):** překládá zobrazený český text ukázky podle slovníku (`TSI18N.add('en', { 'český text': 'překlad' })`); jazyky natvrdo en/cs/de. Integrace: `ts.py add ts-i18n-demo <projekt>`, načíst `core/i18n.js` hned v `<head>` (před vykreslením), pak slovníky.
**ts-i18n-build (reference):** `components/ts-i18n-build/src/build.py` generuje web tasstudio.eu v 18 jazycích; registr `src/jazyky.json` (kód, nativní název, `dir`, `locale`, `stav`, `overeno`). Pro nový projekt vzor, ne hotový nástroj.
**Pravidla:** nepřeložené jazyky zapínat jen po kontrole (`stav: priprava` → `aktivni`); překlady bez kontroly rodilým mluvčím označit (`overeno: false`); RTL přes `dir="rtl"`; právní texty překládat samostatně.
**Zakázáno:** upravovat `vendor/ts/ts-i18n-demo/core/*`; měnit klíče slovníku (klíč = zdrojový český text).
**API:** `/Users/krystof/TS-Studio/ts-library/api/ts-i18n.md`. **Testy:** `ts.py test ts-i18n-demo`.
