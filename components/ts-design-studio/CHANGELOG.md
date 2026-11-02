# ts-design-studio – změny

## 0.4.1 – 2026-11-02
- Potvrzovací okno odeslání se vkládá dovnitř rozhraní (`.tss`) – na stagingu bylo bez barev rozhraní (průhledné). Test v Chromu nově kontroluje pozadí okna.

## 0.4.0 – 2026-11-02
- Schválení a doručení klientovi (etapa 6.4a, `docs/ai/09-schvalovani-dorucovani-plan.md`): doručení `prepared → ready_to_send → sending → sent | failed`, odvolání `revoked` (migrace 003). Sdílet jde jen přijatou verzi interně schváleného projektu; interní schválení samo nic nevytvoří ani neodešle.
- Neveřejný odkaz `previewBase/<token>/`: v databázi jen otisk, token z HMAC tajného klíče (`deliverySecret`); aktivuje se až odesláním e-mailu nebo zkopírováním (ruční cesta, zaznamená se); platnost od aktivace (1–90 dní), počítadlo otevření bez IP.
- E-mail přes `ts-mailer-php` (`mail` v konfiguraci) nebo vlastní `mailer`; odeslání jen se samostatným potvrzením (opsaná adresa příjemce) a klíčem idempotence; podpis a Reply-To přihlášeného pracovníka; výchozí text s `{odkaz}`, `{datum}`, `{jmeno}`; příjemce předvyplněný z CRM (`leadLookup`).
- `TsStudioPreviewGate` – brána náhledu na samostatném originu: jen aktivní doručení, přísná CSP, `noindex`, `robots.txt`, bez cookies, pruh s platností, neutrální 404, blokace hádání tokenu (otisk IP po hodinách).
- `TsStudioRoles` – admin / designer / sales / viewer → akce `view, edit, accept, status, deliver, export, settings`. API vrací roli a povolené akce, rozhraní podle nich skrývá tlačítka (rozhoduje server).
- Rozhraní: tlačítko „Schválit interně“, záložka Doručení (příprava, e-mail, potvrzovací okno, zkopírování odkazu, balíček, odvolání, historie otevření).

## 0.3.0 – 2026-11-02
- Administrace (etapa 6.3): `TsStudioApi` – JSON API pro rozhraní (šablony, projekty, verze, soubory, rozdíl, náhled, export ZIP, ruční úprava obsahu a kódu, přijetí/zamítnutí/návrat, stav, návrh a úprava AI jako úlohy na pozadí). POST jen s JSON a povoleným původem; přihlášení a číslo uživatele dodává vstupní bod.
- Rozhraní `public/ts-studio.js` + `ts-studio.css` (bez knihoven): seznam projektů, nový projekt ze šablony nebo s AI (nový / redesign / lead), editor – verze, náhled v sandboxu ve 3 šířkách, formulář obsahu a barev ze schématu šablony (zvýraznění „[doplnit]“), editor kódu se stromem souborů, barevný rozdíl, kontroly a seznam k doplnění, konverzace s AI, ukazatel spotřeby; světlý i tmavý režim, mobil.
- `TsDesignStudio::checkAi()` a `hasAi()` – strop a vypnutá AI se ohlásí hned, ne až v úloze.

## 0.2.1 – 2026-11-02
- Po testu s Gemma 4 na stagingu (3 zadání): model vymýšlel recenze a citáty majitelky → sloty šablon označené `grounded` (citáty, recenze, jména, autoři) smí obsahovat jen text ze zadání (≥ 80 % slov), jinak „[doplnit]“; zahozené texty Studio vypíše (`dropped`).
- Dnešní datum a roční období v pokynech (model psal „Jaro 2024“ ze vzoru).
- Barvy s kontrastem pod 4,5:1 se místo vrácení na výchozí ztmaví/zesvětlí (`TsStudioTemplate::ensureContrast`) – i při úpravě, takže verze projde kontrolami.
- Úpravy CSS: v pokynech současná pravidla klíčových prvků šablony (`cssHints`) a zákaz em/% u velikostí (model zmenšoval logotyp na `.8em`); jako změněné se počítají jen sloty a tokeny, které se opravdu liší.

## 0.2.0 – 2026-11-01
- AI vrstva (etapa 6.2): `aiCreate()` – zadání → výběr šablony (podle oboru, jinak modelem) → obsah slotů a tokeny jako JSON → projekt s verzí 1 od AI; zdroje A nový, B redesign z auditu (`TsStudioAi::briefFromAudit`, převzaté texty označené „ověřit práva“), C lead z CRM (`briefFromLead`).
- Fakta se nevymýšlejí: chybějící sloty = „[doplnit]“ (kontakty a odkazy prázdné), nikdy obsah ze vzorové ukázky; seznam k doplnění (`todo`). Barvy pod 4,5:1 se vrátí na výchozí šablony.
- `aiEdit()` – konverzační úprava aktuální verze: změny obsahu, tokenů a vrstvy CSS `/*@ai*/…/*@ai-end*/` (osnova stránky pro selektory) jako jedna nová verze-kandidát; vrstva CSS přežije nové vykreslení ze šablony.
- Modely: Cloudflare Workers AI (výchozí Gemma 4 26B bez „přemýšlení“, neurony podle ceníku) a falešný model pro testy; jedno opakování při nečitelném JSON, denní strop neuronů Studia, záznam spotřeby a konverzace (migrace 002).

## 0.1.0 – 2026-11-01
- První verze (etapa 6.1, `docs/ai/08-design-studio-plan.md` v projektu T&S): jádro AI Design Studia jen pro administraci – projekty (zdroj new/redesign/crm, stav draft/in_review/approved_internal/archived), verze jako nové záznamy (kandidát → přijatá/zamítnutá, návrat = nová verze), úprava textových souborů, záznam akcí, oprávnění přes `can(uid, akce, projekt)`.
- Šablony: `template.json` (tokeny, sloty, kontrast, výchozí obsah) + `files/`; podmnožina Mustache, která vždy escapuje; barvy a písma v bloku CSS `/*@tokens*/…/*@end*/`. Typy slotů text, multiline, url, email, phone, color, list.
- Kontroly před přijetím: HTML (párování značek, odkazy, cizí zdroje, zakázané prvky, javascript:, formuláře), CSS (závorky, importy, url(), skript), JS (eval, cookie, nadřazené okno – chyby; síť a úložiště – varování), kontrast WCAG, velikost.
- Řádkový diff a sjednocený výpis, export ZIP (čisté PHP), náhled pro iframe se sandboxem (vložené CSS/JS/písma/obrázky, CSP bez sítě, odkazy mezi stránkami jako `data-page`).
- Šablony restaurace (z ukázky Kořen) a salon (z ukázky Lokna) s převodními skripty v `tools/`.
- 74 testů bez sítě včetně kontroly obou skutečných šablon.
