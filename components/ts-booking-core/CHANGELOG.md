# ts-booking-core – změny

## 1.2.0 – 2026-10-31
- Demo úložiště: `addBlock`, `deleteBlock` (správa blokovaných termínů i bez serveru). Jádro beze změny.


## 1.1.0 – 2026-10-30
- Nový adaptér `adapters/store-http.js` (`TSBooking.stores.http`, `TSBooking.stores.httpAdmin`) pro serverové úložiště ts-booking-server.
- Oprava jádra: rezervace bez `id` (veřejná dostupnost ze serveru) se při výpočtu nevynechávaly správně – `except` se teď uplatní jen, když je zadané. Výsledky s id beze změny (rozdílové testy).

## 1.0.0 – 2026-10-30
- První verze. Algoritmy převzaté z ukázek Lokna, Motoria, Kořen a z `TS.cal` (ts-kit-core), zobecněné do neutrální konfigurace.
- Režimy `resources` (včetně `pool`) a `capacity`, rozšíření `opts.extensions`, `prepareBooking`, ICS a Google Kalendář.
- Demo úložiště `adapters/store-demo-local.js` (localStorage, idempotence).
- Předvolby: kadeřnictví, autosalon, restaurace, ordinace.
- Rozdílové testy proti originálům (3 časové scénáře) a jednotkové testy.
