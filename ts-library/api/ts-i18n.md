# API: lokalizace (ts-i18n-demo, ts-i18n-build)

## ts-i18n-demo (`window.TSI18N`) – překlad zobrazeného textu ukázek
| | |
|---|---|
| jazyk | `?lang=en\|cs\|de`, jinak `localStorage ts-lang`, jinak `en`; zdroj je čeština |
| `TSI18N.add(lang, { 'český text': 'překlad', 'Volno od {0}': 'Free from {0}' })` | slovník (klíč = český text, `{0}` = data a čísla) |
| `TSI18N.t(text)` | překlad textu |
| `TSI18N.run(root)` | přeloží DOM, další změny hlídá MutationObserver |
| `?i18n=sber` | chybějící klíče do `sessionStorage ts-i18n-chybi` |

## ts-i18n-build – generátor statického webu (Python)
`src/build.py` + `src/jazyky.json` (registr: `kod, nazev, nativni, dir (ltr/rtl), locale, intl, cesta, stav (aktivni/priprava), overeno`).
Zatím vázané na strukturu tasstudio.eu – referenční zdroj pro budoucí obecný generátor.
