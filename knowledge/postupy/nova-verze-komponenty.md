# Nová verze komponenty

1. Změna v `components/<n>/` (jádro jen s testy). Původní projekt se nemění.
2. SemVer: oprava = PATCH, nová volitelná funkce se zachovaným chováním = MINOR, změna API / konfigurace / dat / chování = MAJOR.
3. MAJOR: `MIGRATION.md` (co se mění, jak upravit konfiguraci/data, rollback) a případná migrace v `migrations/` s rollbackem.
4. Zápis do `CHANGELOG.md` (u MAJOR sekce **BREAKING**), zvýšit `version` v `module.json`, upravit závislosti (`"ts-x": "^2.0.0"`).
5. `ts.py lock <n>` → `ts.py test <n>` → `ts.py registry` → `ts.py validate --origin`.
6. Commit v gitu knihovny, tag `<n>@<verze>`.
