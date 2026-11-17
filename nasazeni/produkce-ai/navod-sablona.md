# Tessa na tasstudio.eu – přechod na bezplatný provoz (T1)

Nahráváte ručně ve FileZille. Balíček obsahuje jen změněné a nové soubory ({POCET_WEB} do `/web`) a změny v `/private/ai`.
**Nikdy nenahrávejte nic do `/web/admin/data`** (živé CRM).

## 0. Před nahráním
1. **Záloha:** stáhněte `/web` (stačí stránky `*.html` všech jazyků a složku `vendor/`) a `/private/ai/ai-consultant.php` do `zalohy/produkce-2026-11-01b/`.
2. **Cloudflare token pro produkci:** v Cloudflare (stejný bezplatný účet jako staging) vytvořte druhý token stejným postupem (AI › Workers AI › Use REST API › Create a Workers AI API Token), název např. `tessa-produkce`. ID účtu je stejné.
   Denní kvóta 10 000 neuronů je **společná pro celý účet** – staging a produkce si ji dělí. Po ověření produkce doporučuji staging vypnout (krok 5).

## 1. `/private/ai/`
Nahrajte **obsah** složky `k-nahrani/ai/` do `/private/ai/` a potvrďte přepsání: `ai-consultant.php` (nová konfigurace), `znalosti.json`, složka `tessa/` (včetně skrytého `.htaccess`).
Pak v `/private/ai/` vytvořte `cloudflare-token.txt` (produkční token) a `cloudflare-ucet.txt` (ID účtu), každý jen jeden řádek.

## 2. `/web/`
Vlevo `k-nahrani/web/`, vpravo `/web`. Označte vlevo **všechno uvnitř** a přetáhněte na prázdné místo vpravo; přepsání potvrďte („Vždy použít tuto akci“). Přepíšou se stránky webu (nová verze widgetu a Tessa), zásady ochrany osobních údajů (Cloudflare místo Anthropic) a knihovna ve `vendor/ts/ts-ai-consultant/`; přibude `tessa/tessa-portret.jpg` a `{KONTROLA}`.

## 3. Ověření
Napište mi „nahráno“. Spustím kontrolu prostředí (`{KONTROLA}`, kód platí do {PLATNOST}), ověření jen čtením a vyzkouším Tessu na webu včetně jedné poptávky TEST do CRM (pak ji smažete).

## 4. Konec placeného provozu
Po ověření: v Claude Console zneplatněte produkční klíč (`tasstudio-produkce`), na serveru smažte `/private/ai/claude-api-klic.txt` a složku `/private/ai/vendor/` (SDK už není potřeba). Smažte `{KONTROLA}`.

## 5. Staging
Ve `/private/ai-test/ai-consultant.php` změňte `'enabled' => true` na `false` (šetří společnou kvótu), nebo smažte `/private/ai-test/cloudflare-token.txt`.

## Vypnutí asistentky (kdykoli)
V `/private/ai/ai-consultant.php` změňte `'enabled' => true` na `false`.
