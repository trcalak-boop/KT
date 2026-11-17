# Tessa (etapa T1) na neveřejném stagingu test.tasstudio.eu

Nahráváte ručně ve FileZille. Produkce (`/web`) se nemění.

## 0. Bezplatný účet Cloudflare (bez platební karty)
1. Zaregistrujte se na https://dash.cloudflare.com/sign-up (e-mail a heslo). Plán **Free**; platební metodu nezadávejte. Doménu do Cloudflare přidávat **není** potřeba.
2. V levém menu otevřete **AI › Workers AI** a zvolte **Use REST API**. Na té stránce najdete **ID účtu** (Account ID, 32 znaků) a tlačítko **Create a Workers AI API Token** – token se šablonou oprávnění jen pro Workers AI. Vytvořte ho a zkopírujte (zobrazí se jen jednou).
   Kdyby stránka vypadala jinak: Profil › API Tokens › Create Token › Custom token s oprávněním Account › Workers AI (Read i Edit) pro váš účet; ID účtu je i v adrese `dash.cloudflare.com/<ID účtu>/…`.
Bezplatný plán nic neúčtuje: po vyčerpání 10 000 neuronů denně (reset v 1:00 našeho času v zimě) asistent do druhého dne odpovídá jen kontaktem.

## 1. `/private/ai-test/`
Nahrajte **obsah** složky `k-nahrani/ai-test/` do `/private/ai-test/`. Přepíše se `ai-consultant.php` (nová konfigurace s personou a přístupovým klíčem) a přibude složka `tessa/` (včetně skrytého `.htaccess`).
Pak v `/private/ai-test/` vytvořte dva textové soubory (pravé tlačítko › Vytvořit nový soubor, pak Zobrazit/Upravit): `cloudflare-token.txt` s API tokenem a `cloudflare-ucet.txt` s ID účtu, každý jen jeden řádek. Starý `claude-api-klic.txt` smažte.

## 2. `/sub/test/`
Nahrajte **obsah** složky `k-nahrani/test/` do `/sub/test/` a potvrďte přepsání. Soubory:
{SOUBORY}

Smažte z `/sub/test/` starou veřejnou stránku `asistent.html`.

## 3. Ověření
1. Otevřete jednou tajný odkaz z `lokalne-NENAHRAVAT/pristup.txt` (prohlížeč si přístup pamatuje 30 dní). Bez něj stránka hlásí 404 a brána odmítá požadavky.
2. Napište mi „nahráno“. Ověřím staging jen čtením, spustím kontrolu prostředí (`{KONTROLA}`, kód do {PLATNOST}) a projdu scénáře ze stránky (tajný odkaz mi nepošlete – pro testy si cookie nastavím z místního souboru).
3. Po kontrole smažte `{KONTROLA}`.

Podoba portrétu, jméno, označení AI a úvodní texty jsou ke schválení – na produkci jdou až po vašem souhlasu.
