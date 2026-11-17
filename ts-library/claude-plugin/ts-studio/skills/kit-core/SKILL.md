---
name: kit-core
description: Společný frontendový základ T&S (ts-kit-core, window.TS) – modální okna, toast, formuláře s vazbou na data, shell administrace s moduly, ikony, české formáty data a peněz, kalendářová mřížka, ICS, líné PDF (jsPDF). Použij pro ukázkové weby, prototypy a administrace v prohlížeči místo psaní vlastních UI prvků.
when_to_use: Ukázka nebo prototyp s administrací; potřeba modálního okna, upozornění, formuláře, týdenního kalendáře, exportu .ics nebo PDF v prohlížeči.
---

# ts-kit-core (DEMO)

**Účel:** jednotné UI a utility všech ukázek T&S. **Stav DEMO:** úložiště je sessionStorage, přihlášení v `TS.admin` je jen příznak, e-maily se jen simulují.

**Integrace:** `python3 -I /Users/krystof/TS-Studio/ts-library/tools/ts.py add ts-kit-core <projekt>`, pak
```html
<link rel="stylesheet" href="vendor/ts/ts-kit-core/core/kit.css">
<link rel="stylesheet" href="ts-theme/ts-kit-core/tokens.css">
<script src="vendor/ts/ts-kit-core/core/kit.js"></script>
```
**Parametry a API:** `/Users/krystof/TS-Studio/ts-library/api/ts-kit-core.md`. Administrace: `TS.admin({ D, brand, user, modules: [{ id, label, icon, render(app) }], mail })`; data ukázky `TS.data(klíč, verze, výchozí)`.
**Závislosti:** žádné; PDF potřebuje jsPDF a písma v `TS.pdf.base`.
**Vzhled:** jen proměnné `--k-*` v `ts-theme/ts-kit-core/tokens.css`; třídy mají předponu `k-`.
**Bezpečnost:** do HTML vždy přes `TS.esc()`; nepoužívat pro produkční data (sessionStorage).
**Zakázáno:** upravovat `vendor/ts/ts-kit-core/core/*`; kopírovat části kitu do projektu.
**Příklad:** `TS.openModal(TS.mShell('Nový termín', '', '<form>…</form>', '<button class="k-btn k-pri">Uložit</button>'))`; `TS.toast('Uloženo')`; `TS.cal.free({ wins: [[540, 1020]], busy: [[600, 660]], dur: 45, step: 15 })`.
**Testy:** `python3 -I …/tools/ts.py test ts-kit-core` (44 kontrol API v Chromu).
