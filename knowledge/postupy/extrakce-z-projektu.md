# Extrakce kódu z existujícího projektu

1. Zdroj zapsat do `tools/sources.json`; zdroj se jen čte.
2. Doslovná kopie: `python3 -I tools/extract.py <komponenta> <zdroj> <z>:<do> …` – zapíše sha256, commit a zda byl soubor necommitnutý.
3. Zobecněný kód (mode `adapted`): zapsat zdroje do `origin`, napsat **rozdílové testy** proti originálu (stejná data → stejné výsledky, víc časových scénářů).
4. Ověřit licenci každého převzatého souboru (knowledge/licence.md).
5. Stav DEMO, dokud nesplní demo-vs-produkce.md.
