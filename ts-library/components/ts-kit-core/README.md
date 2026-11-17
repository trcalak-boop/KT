# ts-kit-core

Společný frontendový základ ukázkových webů T&S Studio (`window.TS`). Stav: **DEMO** – viz `module.json → missingForProduction`.

## Obsah
| Soubor | Co to je | Upravovat? |
|---|---|---|
| `core/kit.js` | utility, datum, ikony, úložiště ukázky, tmavý režim, modální okna, toast, e-maily ukázky, kalendář (`TS.cal`), PDF (`TS.pdf`), shell administrace (`TS.admin`), formuláře (`TS.f`) | **ne** (zamčeno) |
| `core/kit.css` | styly prvků s předponou `k-`, barvy a písma jen z proměnných `--k-*` | **ne** (zamčeno) |
| `theme/tokens.css` | vzhled konkrétního webu | ano – patří projektu |

## Vložení do projektu
```bash
python3 -I ~/TS-Studio/ts-library/tools/ts.py add ts-kit-core <projekt>
```
```html
<link rel="stylesheet" href="vendor/ts/ts-kit-core/core/kit.css">
<link rel="stylesheet" href="ts-theme/ts-kit-core/tokens.css">
<script src="vendor/ts/ts-kit-core/core/kit.js"></script>
```
`TS.pdf.base` ukazuje na složku s `js/jspdf.umd.min.js` a `fonts/pdf/` (výchozí `../assets/`), nastavte ho před prvním PDF.

API: `api/ts-kit-core.md`. Skill: `/ts-studio:kit-core`.
