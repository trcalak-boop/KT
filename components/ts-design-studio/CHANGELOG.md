# ts-design-studio – změny

## 0.1.0 – 2026-11-01
- První verze (etapa 6.1, `docs/ai/08-design-studio-plan.md` v projektu T&S): jádro AI Design Studia jen pro administraci – projekty (zdroj new/redesign/crm, stav draft/in_review/approved_internal/archived), verze jako nové záznamy (kandidát → přijatá/zamítnutá, návrat = nová verze), úprava textových souborů, záznam akcí, oprávnění přes `can(uid, akce, projekt)`.
- Šablony: `template.json` (tokeny, sloty, kontrast, výchozí obsah) + `files/`; podmnožina Mustache, která vždy escapuje; barvy a písma v bloku CSS `/*@tokens*/…/*@end*/`. Typy slotů text, multiline, url, email, phone, color, list.
- Kontroly před přijetím: HTML (párování značek, odkazy, cizí zdroje, zakázané prvky, javascript:, formuláře), CSS (závorky, importy, url(), skript), JS (eval, cookie, nadřazené okno – chyby; síť a úložiště – varování), kontrast WCAG, velikost.
- Řádkový diff a sjednocený výpis, export ZIP (čisté PHP), náhled pro iframe se sandboxem (vložené CSS/JS/písma/obrázky, CSP bez sítě, odkazy mezi stránkami jako `data-page`).
- Šablony restaurace (z ukázky Kořen) a salon (z ukázky Lokna) s převodními skripty v `tools/`.
- 74 testů bez sítě včetně kontroly obou skutečných šablon.
