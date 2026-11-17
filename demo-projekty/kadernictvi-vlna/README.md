# Salon Vlna – demonstrační projekt A (kadeřnictví)

Fiktivní web kadeřnictví postavený na knihovně T&S (`ts-booking-widget` 1.0.0 + `ts-booking-core` 1.0.0), vložené příkazem
`python3 -I ~/TS-Studio/ts-library/tools/ts.py add ts-booking-widget .`

> ⚠️ **DEMO:** rezervace se ukládají jen v prohlížeči (localStorage), ne do serverové databáze. Žádné e-maily se neodesílají.

| Soubor | Co |
|---|---|
| `vendor/ts/` | jádro knihovny – needitovat (`ts.py verify .`) |
| `ts-config/ts-booking-core/salon-vlna.json` | služby, varianty podle délky vlasů, 3 stylisté s vlastní pracovní dobou, svátky |
| `ts-theme/ts-booking-widget/tokens.css` | vzhled rezervací |
| `index.html`, `sprava.html`, `assets/` | vlastní web a přehled rezervací |

Spuštění: `python3 -m http.server 8101` v této složce → http://localhost:8101 (konfigurace se načítá přes fetch).
