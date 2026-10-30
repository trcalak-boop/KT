# T&S Studio Components & Skills

Lokální, verzovaná knihovna opakovaně použitelných webových komponent T&S Studio.
Umístění: `~/TS-Studio/ts-library` · Verze knihovny: viz `VERSION` · Stav všech komponent: **DEMO** (viz `knowledge/demo-vs-produkce.md`).

## Části
| Část | Kde |
|---|---|
| Component Library – zdrojový kód | `components/<název>/` (`core/` jádro, `theme/` vzhled, `config/` konfigurace, `adapters/` datová vrstva, `tests/`) |
| Skills Library – Claude Code | `claude-plugin/ts-studio/skills/` (plugin `ts-studio`, lokální marketplace `.claude-plugin/marketplace.json`) |
| API Registry | `api/*.md`, `api/openapi/*.yaml` |
| Knowledge Base | `knowledge/` |
| Component Registry | `registry/components.json` (generovaný), `registry/module.schema.json` |
| Nástroje | `tools/ts.py` (validate, registry, lock, test, add, verify, update, origin), `tools/extract.py` |

## Rychlý start
```bash
python3 -I tools/ts.py list
python3 -I tools/ts.py test                      # všechny testy (Chrome bez okna + PHP 8.5 z ~/TS-Studio/tools/php)
python3 -I tools/ts.py add ts-booking-widget ~/projekty/novy-web
```

## Claude Code
Jednou nainstalovat plugin (v aplikaci Claude Code nebo terminálu):
```
/plugin marketplace add ~/TS-Studio/ts-library
/plugin install ts-studio@ts-library
```
Pak jsou v každém projektu dostupné skills `/ts-studio:library`, `/ts-studio:booking`, `/ts-studio:kit-core`, `/ts-studio:php-backend`, `/ts-studio:i18n` a hook při startu relace připomene knihovnu. Lokální plugin se načítá přímo ze složky – změny platí od další relace.

## Zdroj
Komponenty jsou extrahované z projektu tasstudio.eu (`tools/sources.json`), který se jen čte. Původ a kontrolní součty: `module.json → origin`.
