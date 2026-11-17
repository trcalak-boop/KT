# Autosalon Dynamo – demonstrační projekt B (autosalon)

Fiktivní web autosalonu se **stejným rezervačním jádrem** jako projekt A (`ts-booking-core` 1.0.0 + `ts-booking-widget` 1.0.0, vloženo `ts.py add`), ale s jinou konfigurací a vzhledem:
zdroj = konkrétní vůz (bez volby „kdokoli“), souběh jízd omezený dvěma prodejci (`pool`), předstih jen pro dnešek, 21 dní dopředu, tmavý vzhled, oborové pravidlo „nabíjení elektromobilu“ jako rozšíření v `assets/app.js`.

> ⚠️ **DEMO:** rezervace se ukládají jen v prohlížeči (localStorage), ne do serverové databáze. Žádné e-maily se neodesílají.

Spuštění: `python3 -m http.server 8102` v této složce → http://localhost:8102
