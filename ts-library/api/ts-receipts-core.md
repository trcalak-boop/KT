# API: ts-receipts-core 1.0.0 (`window.TSReceipts`)

| Funkce | Popis |
|---|---|
| `calc(items, disc, vatPayer, rate)` | `items: [{ name, qty, price }]`, `disc: { type: 'pct' \| 'amount', v }` → `{ lines, sub, disc, total, base, vat }` (ceny vč. DPH; `base`/`vat` jen u plátce) |
| `vatByLine(lines, rate = 21)` | doplní každému řádku `base`, `vat` (z `total`), vrátí součty |
| `nextNumber({ prefix, pad = 4, existing, floor })` | další číslo v řadě, nikdy nižší než existující |
| `yearNumber(date, existing, seq, today)` | roční řada `RRRR-NNNN` (jako Lokna) |
| `pdf.jspdf`, `pdf.fonts`, `pdf.lang` | cesty k jsPDF (MIT) a k písmům TTF |
| `pdf.load([[soubor, název, styl]])` | `Promise<() => jsPDF dokument>` s vloženými písmy (mezipaměť) |
| `num(v)` | „1 234,5“ → 1234.5 |
