# Aktualizace komponenty v projektu a kontrola kompatibility

- `ts.py update <komponenta> <projekt>`:
  1. ověří, že jádro v projektu nikdo neupravil (jinak zastaví – úpravy přesunout do ts-theme/ts-config),
  2. u nové hlavní verze zastaví a odkáže na `MIGRATION.md`; pokračovat jde jen s `--major`,
  3. zkontroluje, že ostatní komponenty v projektu novou verzi snesou (pole `dependencies`),
  4. nahradí jen `vendor/ts/<komponenta>/`; `ts-theme/` a `ts-config/` zůstanou beze změny,
  5. zapíše nové kontrolní součty do `ts-components.lock.json`.
- Konfigurace projektu se po aktualizaci ověří `TSBooking.validate(config)` (u rezervací) a testy projektu.
- Databázové změny jen migracemi komponenty (`migrations/NNN_*.sql`) se záznamem rollbacku; před migrací záloha DB.
