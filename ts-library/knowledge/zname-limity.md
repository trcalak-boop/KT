# Známá omezení (Etapa 1)

- Žádná komponenta není production-ready; rezervace se v demo režimu ukládají jen v prohlížeči, serverový režim (ts-booking-server) je ověřený jen lokálně (php -S), ne na hostingu.
- Testy běží jen v Chromiu (headless Chrome přes DevTools Protocol); Safari a Firefox netestovány.
- ts-booking-core v prohlížeči počítá v místním čase zařízení; ts-booking-server počítá v pásmu provozovny (config.ics.timezone) a je pro zápis rozhodující.
- Oborová rozšíření se musí napsat dvakrát (JS pro formulář, PHP pro server).
- Předvolba ordinace není rozdílově ověřená proti ukázce Ordinace; Šalvěj (monolit) není extrahovaná.
- ts-i18n-demo má jazyky en/cs/de natvrdo; ts-i18n-build je vázaný na strukturu tasstudio.eu.
- ts-shop-core, ts-cms-blocks, ts-crm-intake, ts-ref-lokna jsou referenční (doslovné kopie), ne konfigurovatelné moduly.
- tFPDF: chybí plný text LGPL; mezipaměť písma vyžaduje zapisovatelnou složku.
- Knihovna je jen na tomto Macu (bez vzdáleného repozitáře) – záloha je na uživateli (viz postupy/zaloha-knihovny.md).
