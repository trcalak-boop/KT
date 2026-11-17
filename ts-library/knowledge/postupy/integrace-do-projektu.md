# Integrace komponenty do projektu

1. Najdi komponentu: `python3 -I ~/TS-Studio/ts-library/tools/ts.py list`, přečti README a `api/`.
2. `ts.py add <komponenta> <projekt>` – zkopíruje jádro do `vendor/ts/`, šablony vzhledu do `ts-theme/`, konfiguraci do `ts-config/`, zapíše `ts-components.lock.json`.
3. Uprav jen `ts-theme/` a `ts-config/` (a vlastní kód projektu).
4. `ts.py verify <projekt>` – musí hlásit „jádro beze změny“.
5. Otestuj v prohlížeči; u DEMO komponent ponech upozornění pro návštěvníky.
6. `ts-components.lock.json` patří do gitu projektu (verze a kontrolní součty).
