# Nasazení rezervací na test.tasstudio.eu – návod (FileZilla)

Balíček je otestovaný lokálně (PHP 8.5, struktura jako na hostingu – výsledek v `lokalne-NENAHRAVAT/vysledky-testu.json`). Neobsahuje žádné heslo.
Instalační kód je jen v `lokalne-NENAHRAVAT/instalacni-kod.txt` na tomto Macu. Instalátor: `{INSTALATOR}`, platí do **{PLATNOST}**.
Ve FileZille zapněte **Server → Zobrazovat skryté soubory** (kvůli `.htaccess`).

## 1. Které soubory nahrát
- Obsah složky `nahrat-do-sub-test/` (včetně skrytého `.htaccess`).
- Obsah složky `nahrat-do-private-booking/` (včetně `.htaccess` a `data/.htaccess`).
- **Nenahrávat:** `lokalne-NENAHRAVAT/`, `SMTP-heslo-navod.txt`, `SHA256SUMS.txt`, `NAVOD.md`, `KONTROLNI-SEZNAM.md`.

## 2. Do jakých adresářů
| Z balíčku | Na hosting |
|---|---|
| `nahrat-do-sub-test/*` | `/sub/test/` (webroot test.tasstudio.eu) |
| `nahrat-do-private-booking/*` | `/private/booking-test/` (mimo webroot) |

Výsledek na hostingu: `/sub/test/index.html, sprava.html, rezervace-zrusit.html, {INSTALATOR}, robots.txt, .htaccess, api/, assets/, ts-theme/, vendor/, ts-components.lock.json`
a `/private/booking-test/booking-server.php, rezervace-config.json, .htaccess, data/.htaccess`. Nic jiného (zejména nic v `/web/`) se nemění.

## 3. Neveřejná konfigurace a heslo SMTP
- `booking-server.php` je hotový (SMTP `smtp.websupport.cz:465` SSL, uživatel `rezervace@tasstudio.eu`, e-maily v testovacím režimu). **Heslo v něm není.**
- Heslo zadejte až v kroku e-mailů (bod 7): ve FileZille ve složce `/private/booking-test/` → pravé tlačítko → **Vytvořit nový soubor** → název **`smtp-heslo.txt`** → pravé tlačítko → **Zobrazit/Upravit** → vložte **jen heslo** na jeden řádek (bez uvozovek, bez mezer na okrajích) → uložte, FileZilla nabídne nahrání zpět → potvrďte. Dočasnou lokální kopii FileZilla po zavření editoru smaže.
- Soubor se čte jako prostý text (nikdy se nespouští jako PHP), takže se jeho obsah nemůže objevit v chybové hlášce; v logu aplikace se heslo nahrazuje `***`. Je mimo web (+ `.htaccess` zákaz), není v gitu ani v balíčku. Heslo nikam jinam nepište.

## 4. Bezpečné vytvoření administrátora
1. Otevřete `https://test.tasstudio.eu/{INSTALATOR}` (jen HTTPS).
2. Instalační kód zkopírujte z `lokalne-NENAHRAVAT/instalacni-kod.txt` (nikam ho neposílejte). Bez platného kódu instalátor neukáže ani kontrolu prostředí; každá akce kód vyžaduje znovu. Po 5 špatných kódech se na 15 minut zamkne.
3. V části **5. Administrátor**: uživatelské jméno (a–z, 0–9, . _ -), celé jméno, heslo **aspoň 12 znaků** (z bezpečného správce hesel). Po vytvoření se další účet přes instalátor vytvořit nedá.

## 5. Inicializace testovací databáze
V instalátoru **1. Kontrola prostředí** musí být vše ✔ kromě „SMTP heslo je vyplněné“ (to doplníte později). Pak **2. Databáze → Vytvořit / zkontrolovat databázi**.
Databáze vznikne jako `/private/booking-test/data/rezervace-test.sqlite` (prázdná, jen fiktivní data, které vytvoříte testem).

## 6. První testovací rezervace
1. `https://test.tasstudio.eu/` → služba → délka vlasů → stylista → den → čas → jméno např. „Testovací Zákazník“, e-mail `test@example.com`, souhlas → Rezervovat.
2. `https://test.tasstudio.eu/sprava.html` → přihlášení administrátorem → rezervace je ve stavu „Čeká na potvrzení“ → **Potvrdit**.
3. Dole **Blokované termíny** → zablokujte jeden den / stylistu → na webu ověřte, že ten termín už nejde vybrat → blokaci zrušte.
4. Zkuste stejný termín rezervovat podruhé – web ho nenabídne a server by ho odmítl.

## 7. Ověření e-mailů (postupně, bez rizika)
1. **Režim log (výchozí):** po rezervaci se e-maily jen zapíšou do `/private/booking-test/data/mail.log` (stáhněte ve FileZille a prohlédněte – adresát je vždy `rezervace@tasstudio.eu`, původní adresa je v hlavičce X-TS-Original-To).
2. **SMTP bez přihlášení:** instalátor → **3. SMTP server → Ověřit spojení** (ověří server z hostingu, heslo se nepoužije).
3. **Heslo:** vytvořte `smtp-heslo.txt` podle bodu 3. Instalátor → kontrola ukáže ✔ „SMTP heslo je vyplněné“.
4. **Zkušební e-mail:** instalátor → **4. Odeslat zkušební e-mail** → zkontrolujte schránku `rezervace@tasstudio.eu`.
5. **Ostré odesílání v testu:** v `booking-server.php` změňte `'transport' => 'log'` na `'transport' => 'smtp'` (FileZilla → Zobrazit/Upravit). **`redirectTo` nechte** – všechny e-maily půjdou jen do testovací schránky s předmětem „[TEST → původní adresa]“. Nová rezervace → e-mail zákazníkovi i provozovně dorazí do testovací schránky; v něm odkaz pro zrušení → ověřte zrušení.

## 8. Ověření zabezpečení
- **HTTPS:** hosting přesměrovává `http://test.tasstudio.eu/…` na `https://…` (301) už na předřazeném serveru – ověřeno 31. 10. Do `.htaccess` se proto další přesměrování nepřidává (hrozila by smyčka); navíc API i instalátor bez HTTPS odmítnou požadavek a prohlížeč díky HSTS zůstane na HTTPS.
- **Zápis jen do neveřejné složky:** API i instalátor si za běhu zúží `open_basedir` na `/sub/test/` a `/private/booking-test/` – PHP pak technicky nemůže číst ani zapisovat v `/web/` ani jinde.

Napište mi „nahráno“ – spustím `overit_nasazeni.py` (jen čtení přes HTTPS): PHP jádro, skripty, migrace a vzory konfigurace vrací 403, databáze ani konfigurace nejsou dostupné, výpis složek je vypnutý, správa bez přihlášení vrací 401, dostupnost neobsahuje osobní údaje, hlavička noindex. Podrobně: `KONTROLNI-SEZNAM.md`.

## 9. Odstranění instalačních souborů
Po vytvoření administrátora a ověření e-mailů smažte z `/sub/test/` soubor **`{INSTALATOR}`**. Ověřím (`overit_nasazeni.py --po-instalaci`), že vrací 404. Instalátor by stejně po {PLATNOST} přestal fungovat.
`smtp-heslo.txt` smažte, až test skončí (odesílání pak přestane fungovat).

## 10. Návrat do výchozího stavu
1. Smažte obsah `/sub/test/`, který přišel z balíčku (seznam v bodě 2) – subdoména bude jako před nasazením.
2. Smažte celou složku `/private/booking-test/` (konfigurace, databáze s fiktivními daty, `smtp-heslo.txt`, `mail.log`).
3. Produkce (`/web/`), CRM ani hlavní doména se nasazením nemění – není co vracet.
