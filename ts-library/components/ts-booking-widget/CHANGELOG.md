# ts-booking-widget – změny

## 1.2.0 – 2026-10-31
- `mountAdmin`: správa blokovaných termínů (den, od–do, celá provozovna nebo konkrétní zdroj, interní důvod; zrušení blokace). Zobrazí se jen s oprávněním `booking.blocks` nebo u demo úložiště.


## 1.1.0 – 2026-10-30
- Serverový režim se store-http: hlášky „obsazeno / limit / chyba“ podle odpovědi serveru a znovunačtení obsazenosti.
- `mountAdmin`: přihlášení a odhlášení pro serverovou správu, akce podle oprávnění, stavy Potvrdit / Odmítnout / Proběhlo / Zrušit.
- Nové `mountCancel` pro zrušení rezervace odkazem z e-mailu.

## 1.0.1 – 2026-10-30
- Oprava: datum v dlaždici dne se nezalamuje ani u širšího písma (zjištěno v demonstračním projektu Salon Vlna).

## 1.0.0 – 2026-10-30
- První verze: veřejný formulář (resources i capacity), přehled rezervací, texty cs/en, vzhled přes --tsb-*.
