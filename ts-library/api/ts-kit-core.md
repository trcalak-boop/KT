# API: ts-kit-core 1.0.0 (`window.TS`)

| Oblast | Funkce |
|---|---|
| Utility | `esc, uid, pad, clone, num, plural, money, money2, int, get, set, validEmail, validPhone, norm, slug, $, $$` |
| Datum (místní čas) | `iso, today, nowMin, parse, addDays, diffDays, wd, weekStart, cap, fmtLong, fmtShort, fmtDM, fmtDMY, fmtDayMonth, fmtRel, hm, hhmm, parseHM, fmtStamp, ago`, konstanty `WD, WDS, WDN, MG, MN` |
| Ikony | `I`, `ic(name, cls)`, `addIcons({ name: '<path…/>' })` |
| Úložiště ukázky | `data(key, version, defaults)`, `save()`, `reset()`, `flag(name, value)`, `badge()`, `webPage()`, `webLink()` – **sessionStorage, jen demo** |
| Vzhled | `mode(storeKey, onChange)` (světlý/tmavý), `burger(btn, nav)` |
| UI | `toast(text, { href, label, act, arg, bad })`, `mShell(title, sub, body, foot, cls, label)`, `openModal(html, onClose)`, `closeModal()`, `modalOpen()`, `download(name, data, mime)`, `print(html)` |
| E-maily ukázky | `mail.add(D, m)`, `mail.wrap(brand, inner, foot)`, `mail.table(rows)`, `mail.btn(label, href, pri)`, `mail.attach(name, note, btn)` |
| Kalendář | `cal.free({ wins, busy, dur, after, step, minStart })`, `cal.overlaps(a0, a1, list)`, `cal.grid(o)`, `cal.gcal(ev)`, `cal.ics(ev)` |
| PDF | `pdf.base`, `pdf.load(fonts)`, `pdf.run(button, job)` |
| Administrace | `admin({ D, root, brand, user, modules: [{ id, label, icon, render(app), badge, tab, short, onArg }], mail: { boxes, intro, from }, afterRender })` → `app` (`go, head, draft, dirty, savebar, render, modal, refreshModal, commit, actions`) |
| Formuláře | `f.text, f.area, f.select, f.opts, f.check, f.toggle, f.times` (vazba `data-bind="cesta.v.D"`) |
| Řazení, přesun | `sortBtns`, `move` |

Vzhled: proměnné `--k-*` (seznam v `module.json`).
