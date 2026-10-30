# Známá omezení (Etapa 1)

- Žádná komponenta není production-ready; rezervace se v demo režimu ukládají jen v prohlížeči.
- Testy běží jen v Chromiu (headless Chrome přes DevTools Protocol); Safari a Firefox netestovány.
- ts-booking-core počítá v místním čase zařízení; server bude muset počítat v časovém pásmu provozovny.
- Předvolba ordinace není rozdílově ověřená proti ukázce Ordinace; Šalvěj (monolit) není extrahovaná.
- ts-i18n-demo má jazyky en/cs/de natvrdo; ts-i18n-build je vázaný na strukturu tasstudio.eu.
- ts-shop-core, ts-cms-blocks, ts-crm-intake, ts-ref-lokna jsou referenční (doslovné kopie), ne konfigurovatelné moduly.
- tFPDF: chybí plný text LGPL; mezipaměť písma vyžaduje zapisovatelnou složku.
- Knihovna je jen na tomto Macu (bez vzdáleného repozitáře) – záloha je na uživateli (viz postupy/zaloha-knihovny.md).
