# Postup: nasazení AI konzultanta (ts-ai-consultant) na sdílený hosting

1. Znalosti klienta sestavit skriptem z ověřených zdrojů (u T&S: `~/TS-Studio/nasazeni/test-tasstudio/ai-znalosti/sestavit_znalosti.py`); skript končí chybou, když by se do znalostí dostala částka. Interní ceník se asistentovi nedává.
2. SDK sestavit podle `components/ts-ai-consultant/sdk/` (composer.lock), složka `vendor/` jde MIMO webroot (~3 900 souborů, 23 MB).
3. Balíček: webroot (brána `api/ai/chat.php`, komponenta, stránka s widgetem, jednorázová kontrola) + neveřejná složka (konfigurace, znalosti, SDK, `data/`). Klíč v balíčku nikdy není – provozovatel ho vloží na serveru do `claude-api-klic.txt`.
4. Test balíčku na simulovaném hostingu jen proti napodobenině API, e-maily do logu, kontrola spojení na localhost (u T&S `otestovat_balicek_ai.py`). Skutečné API ani SMTP se v automatických testech nevolají.
5. Na hostingu: kontrolní stránka (prostředí zdarma, pak jeden skutečný dotaz se souhlasem provozovatele), vyzkoušení scénářů, smazání kontroly, ověření jen čtením (`overit_nasazeni_ai.py`).
6. Útrata: `php bin/report.php <konfigurace>`; strop hlídá brána (rezervace před voláním) a navíc měsíční limit pracovního prostoru v Claude Console.

Zjištění: oficiální PHP SDK 1.3.0 posílá klíč v hlavičce `Authorization: Bearer`; časový limit požadavku vynucuje jen HTTP klient (Guzzle `timeout`), SDK ho samo nehlídá.
