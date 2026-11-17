# AI asistent na test.tasstudio.eu – postup nahrání

Nahráváte ručně ve FileZille. **Do /web (produkce) nic nenahrávejte.** Na produkci se nic nemění.

Než začnete: API klíč z Claude Console (pracovní prostor `tasstudio-test`, měsíční limit 10 USD, automatické dobíjení vypnuté).

## 1. Neveřejná část → `/private/ai-test/`
1. V kořeni hostingu ve složce `/private/` vytvořte složku `ai-test` (vedle `booking-test`).
2. Nahrajte do ní **obsah** složky `nahrat-do-private-ai-test/` (ne složku samotnou). Jde o zhruba 3 900 souborů (SDK ve `vendor/`), nahrávání trvá několik minut.
3. Zkontrolujte, že na serveru jsou `/private/ai-test/.htaccess`, `ai-consultant.php`, `znalosti.json`, `data/.htaccess` a `vendor/autoload.php`. FileZilla skryté soubory (`.htaccess`) někdy vynechá: Server › Vynutit zobrazení skrytých souborů.

## 2. API klíč
Podle `KLIC-navod.txt` vytvořte na serveru `/private/ai-test/claude-api-klic.txt` s klíčem. Klíč nikam neposílejte, ani mně.

## 3. Webroot testu → `/sub/test/`
Nahrajte **obsah** složky `nahrat-do-sub-test/` do `/sub/test/`. Přibudou `api/ai/chat.php`, `asistent.html`, `{KONTROLA}` a `vendor/ts/ts-ai-consultant/`.
Přepsat se smí **jen** `ts-components.lock.json` (přibude v něm AI komponenta, rezervace zůstanou). Kdyby se FileZilla ptala na přepsání jiného souboru, zastavte a napište mi.

## 4. Kontrola prostředí (zdarma)
1. Otevřete `https://test.tasstudio.eu/{KONTROLA}` a zadejte kód z `lokalne-NENAHRAVAT/kontrolni-kod.txt` (platí do {PLATNOST}).
2. Tlačítko **Zkontrolovat prostředí**. Všechny řádky mají být OK. Pokud je někde CHYBA, pošlete mi text toho řádku (hodnoty klíče ani hesla se nezobrazují).

## 5. Jeden skutečný dotaz (tisíciny USD)
Na stejné stránce tlačítko **Jeden skutečný dotaz na Claude**. Má se ukázat „OK – odpověď: …“. Pokud ne, napište mi, co se zobrazilo.

## 6. Vyzkoušení asistenta
Otevřete `https://test.tasstudio.eu/asistent.html` a vyzkoušejte scénáře ze stránky. Kontaktní formulář pošle e-mail jen do schránky `rezervace@tasstudio.eu`.

## 7. Úklid
Smažte z `/sub/test/` soubor `{KONTROLA}`. Pak mi napište „hotovo“. Ověřím nasazení jen čtením (bez dotazů na AI).
