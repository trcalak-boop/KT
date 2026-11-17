# ts-booking-server – změny

## 1.1.0 – 2026-10-31
- `TsBookingInstaller`: jednorázový webový instalátor pro hosting bez SSH (instalační kód jen jako otisk, platnost do data, jen HTTPS, zámek po 5 špatných kódech, po vytvoření administrátora zamčeno).
- `ts_booking_harden()`: zpevnění vstupních bodů (HTTPS, skrytí chyb, zúžení open_basedir); instalátor bez platného kódu nezobrazí ani kontrolu prostředí; tajné hodnoty se v logu chyb API nahrazují `***`.
- Nastavení `timezone` (záznamy a „dnes“ v pásmu provozovny); vzor konfigurace s testovacím režimem e-mailů (`transport: log`, `redirectTo`).


## 1.0.0 – 2026-10-30
- První verze (Etapa 2): TsBookingEngine (PHP port jádra 1.0.0, rozdílově ověřený), TsBookingStore (SQLite/MySQL, migrace 001, transakce, idempotence, audit, GDPR), TsBookingApi (veřejné API + správa), TsBookingNotifier (e-maily přes TsMailQueue), CLI create-user, cron, migrate.
