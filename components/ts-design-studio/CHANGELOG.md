# ts-design-studio – změny

## 0.11.0 – 2026-11-07
- Administrace návrhu na míru (etapa 6.7c): ve formuláři volba tři směry / jeden návrh / podle šablony a nová pole cíle webu, co se líbí a nelíbí. Záložka Směry ukáže návrhy vedle sebe a jedním tlačítkem vybere směr.
- Záložka Obsah a barvy u návrhu na míru: barvy pro oba režimy, písma a tvar, údaje webu, sekce (rozvržení, tón, menu, sloty včetně vnořených seznamů, přidat, přesunout, odebrat), vlastní CSS. Uložení je nová verze.
- API: `ai-create` s `mode: 'kit'` a `directions`; `version` vrací u návrhu na míru popis stavebnice. Úloha má limit 7 minut, rozhraní čeká nejvýš 8 minut a pak nahlásí chybu.

## 0.10.0 – 2026-11-07
- Návrh na míru (etapa 6.7b, `TsStudioPlanner`, `TsDesignStudio::kitCreate`): model ze zadání navrhne strukturu, varianty, paletu, písma a texty jako JSON plán; nikdy HTML. Jeden až tři směry: první je přijatý, ostatní k výběru; když model cestou selže, hotové směry zůstanou.
- Fakta jen ze zadání včetně čísel: vymyšlené kontakty, ceny, časy a jména se zahodí, recenze, citáty a čísla bez podkladu zmizí se sekcí; čísla ve volném textu se hlásí k ověření.
- Kostra webu: vždy úvod a kontakt, kotvy jen na existující sekce, prázdné sekce pryč, menu se doplní.
- Vlastní vrstva CSS jen vyčištěná: selektor uvnitř sekce návrhu, povolené vlastnosti, žádné barvy, url(), proměnné ani at-pravidla kromě @media.
- Odlišnost: podobnost s dřívějšími návrhy a ostatními směry; příliš podobný návrh dostane jiné rozvržení a tvary bez dalšího dotazu na model.
- Připomínky (`aiEdit`): operace nad plánem (změnit, přidat, odebrat, přesunout sekci, tokeny, údaje webu), vždy nová verze ke schválení.
- Zadání má nově cíle webu, co se klientovi líbí a nelíbí. Export ze stavebnice už nemá noindex (náhledy chrání brána). Šablony fungují beze změny.

## 0.9.0 – 2026-11-06
- Stavebnice sekcí pro návrhy na míru (etapa 6.7a, `kit/`, `TsStudioKit`): 21 typů sekcí ve 46 variantách včetně dílů pro školu (aktuality, jídelníček, dokumenty) a e-shop (katalog, vzorník), 4 tóny sekcí, jednotné tokeny (světlá i tmavá paleta, 15 rodin písem s licencí OFL, zaoblení, vzdušnost, velikost a styl nadpisů, tlačítka, dekor), 8 abstraktních ilustrací.
- Z návrhového plánu vznikne statický web: neznámé typy, varianty a klíče se zahodí, obsah prochází schématem slotů (nově i typ `enum` a vnořené seznamy), kontrast se opraví automaticky, do projektu jdou jen použité sekce a písma, nadpisy se nedělí uprostřed slova.
- Šablony beze změny. Napojení na model a administraci přijde v 6.7b a 6.7c.

## 0.8.0 – 2026-11-02
- Nová šablona **kosmetika** (z ukázky Šalvěj): hero s ilustrací lahviček (štítky z obsahu), týdenní přehled otevírací doby s odkazy na rezervaci, ceník procedur, přípravky, kontakt a adresa. Písma Marcellus, Albert Sans a Azeret Mono (OFL), na výběr Bodoni Moda a Figtree; tmavý režim s vlastním akcentem v tokenech.

## 0.7.0 – 2026-11-02
- Nová šablona **ordinace** (z ukázky Ordinace): upozornění nahoře, tabule s kontaktem, výhody, tabulka ordinačních hodin, rychlé cesty, tým, registrace nových pacientů, adresa a tísňové volání. Písma Atkinson Hyperlegible Next a JetBrains Mono (OFL), tlačítko A+, režim podle zařízení; pozadí a akcent tmavého režimu v tokenech.

## 0.6.0 – 2026-11-02
- Chyby Cloudflare se rozlišují podle kódu a textu odpovědi: vyčerpaná denní kvóta (4006 / „daily free allocation“) = `limit`, přetížení modelu nebo omezení rychlosti (3040, ostatní 429, 5xx) = `busy`. Dříve každá odpověď 429 hlásila „denní limit AI je vyčerpaný“ (na produkci i při 0 spotřebovaných neuronech).
- Při přetížení jedno opakování po krátké pauze, volitelně záložní modely (`fallbackModels` s vlastním ceníkem neuronů); kvóta se neopakuje. Text chyby od Cloudflare se ukládá a rozhraní ho ukáže („model AI je teď přetížený – zkuste to za chvíli (…)“).
- Brána náhledů: projekt se šablonou, kterou kopie knihovny v bráně nemá, vrátí neutrální 404 místo chyby serveru.
- Nová šablona **autosalon** (z ukázky Motoria): hero s vozem týdne, přednosti, vozy skladem s parametry, kategorie, financování, služby, recenze, testovací jízda; výchozí tmavý režim, barvy i pro tmavý režim v tokenech; písma Saira a Red Hat Mono (OFL).

## 0.5.1 – 2026-11-02
- `public/.htaccess` povolí jen JS a CSS rozhraní, když je nadřazená složka `vendor/` na hostingu zakázaná (na produkci tasstudio.eu vracely 403; staging tuto zábranu nemá).

## 0.5.0 – 2026-11-02
- Produkční napojení (etapa 6.5a, `docs/ai/10-studio-produkce-plan.md`): `TsStudioApi` kontroluje token CSRF relace (`'csrf' => fn($token)`, hlavička `X-CSRF`, rozhraní ho posílá z `TS_STUDIO_CONFIG.csrf`) a má vypínač (`'enabled' => false` → 503 `disabled`, rozhraní ukáže „Studio je vypnuté“).
- Návrh z leadu CRM podle čísla: `'leadBrief' => fn($ref) => ['lead' => řádek, 'notes' => [...]]` (jen čtení CRM); neexistující lead → 400 bez dotazu na model; formulář má pole „Číslo leadu v CRM“.
- Uchování: doručení starší než `'retentionMonths'` (výchozí 24) – příjemce, předmět a text se anonymizují (nejvýš jednou denně, při načtení seznamu projektů).

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
