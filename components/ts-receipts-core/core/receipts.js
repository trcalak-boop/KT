/* ==========================================================================
   ts-receipts-core – výpočty dokladů a faktur, číselné řady, DPH, načtení jsPDF
   Převzato z ukázek tasstudio.eu: Lokna L.rcCalc a číslování dokladů (lokna/admin.js nextNo),
   VRSTVA V.invLines (rozpad DPH po řádcích), ts-kit-core TS.pdf.load (líné načtení jsPDF a písem).
   Bez DOM a bez vzhledu: rozvržení dokladu (HTML/PDF) patří projektu.
   ========================================================================== */
(function (root) {
  'use strict';
  var R = root.TSReceipts = { version: '1.0.0' };
  /* čísla s mezerou a čárkou („1 234,5“) – shodně s TS.num */
  var num = R.num = function (v) { var n = Number(String(v == null ? '' : v).replace(/\s/g, '').replace(',', '.')); return isFinite(n) ? n : 0; };
  var r2 = function (n) { return Math.round(n * 100) / 100; };

  /* items: [{ name, qty, price }], disc: { type: 'pct' | 'amount', v } | null
     → { lines, sub, disc, total, base, vat } (base/vat jen pro plátce DPH, cena je včetně DPH) */
  R.calc = function (items, disc, vatPayer, rate) {
    var lines = items.map(function (i) { var q = num(i.qty) || 1, p = num(i.price); return { name: i.name, qty: q, price: p, sum: r2(q * p) }; });
    var sub = r2(lines.reduce(function (a, l) { return a + l.sum; }, 0)), dAmt = 0;
    if (disc && num(disc.v) > 0) dAmt = disc.type === 'pct' ? Math.round(sub * Math.min(100, num(disc.v)) / 100) : Math.min(sub, Math.round(num(disc.v)));
    var total = r2(sub - dAmt), base = null, vat = null;
    if (vatPayer) { base = r2(total / (1 + rate / 100)); vat = r2(total - base); }
    return { lines: lines, sub: sub, disc: dAmt, total: total, base: base, vat: vat };
  };

  /* rozpad DPH po řádcích (ceny včetně DPH): lines [{ total }] → doplní base, vat; vrací součty */
  R.vatByLine = function (lines, rate) {
    var k = 1 + (rate == null ? 21 : rate) / 100, sb = 0, sv = 0;
    lines.forEach(function (l) { l.base = r2(l.total / k); l.vat = r2(l.total - l.base); sb += l.base; sv += l.vat; });
    return { base: r2(sb), vat: r2(sv), total: r2(sb + sv) };
  };

  /* další číslo v řadě: prefix + pořadí doplněné nulami. Vezme nejvyšší existující číslo se stejným prefixem
     a případné „floor“ (např. počítadlo uložené mimo seznam dokladů). Číslo se nikdy nevrací zpět. */
  R.nextNumber = function (o) {
    var prefix = o.prefix || '', pad = o.pad || 4, max = o.floor || 0;
    (o.existing || []).forEach(function (no) { if (String(no).indexOf(prefix) === 0) max = Math.max(max, parseInt(String(no).slice(prefix.length), 10) || 0); });
    return prefix + String(max + 1).padStart(pad, '0');
  };
  /* roční řada „2026-0001“ jako v ukázce Lokna */
  R.yearNumber = function (date, existing, seq, today) {
    var y = date.slice(0, 4);
    return R.nextNumber({ prefix: y + '-', pad: 4, existing: existing, floor: today && y === today.slice(0, 4) ? seq || 0 : 0 });
  };

  /* líné načtení jsPDF a písem (TTF) až při prvním PDF; fonts: [[soubor, název, styl]] → Promise<function () → jsPDF doc> */
  var cache = {};
  R.pdf = {
    jspdf: '../assets/js/jspdf.umd.min.js', fonts: '../assets/fonts/pdf/', lang: 'cs-CZ',
    load: function (fonts) {
      var key = fonts.map(function (f) { return f[0]; }).join('|'); if (cache[key]) return cache[key];
      var b64 = function (buf) { var u = new Uint8Array(buf), s = ''; for (var i = 0; i < u.length; i += 32768) s += String.fromCharCode.apply(null, u.subarray(i, i + 32768)); return btoa(s); };
      var lib = root.jspdf ? Promise.resolve() : new Promise(function (ok, no) { var s = document.createElement('script'); s.src = R.pdf.jspdf; s.onload = ok; s.onerror = function () { no(new Error('jsPDF')); }; document.head.appendChild(s); });
      var fs = Promise.all(fonts.map(function (f) { return fetch(R.pdf.fonts + f[0]).then(function (r) { if (!r.ok) throw new Error('font ' + f[0]); return r.arrayBuffer(); }).then(b64); }));
      var p = cache[key] = Promise.all([lib, fs]).then(function (x) {
        return function () {
          var doc = new root.jspdf.jsPDF({ unit: 'pt', format: 'a4', compress: true, putOnlyUsedFonts: true });
          fonts.forEach(function (f, i) { doc.addFileToVFS(f[0], x[1][i]); doc.addFont(f[0], f[1], f[2]); });
          if (doc.setLanguage) doc.setLanguage(R.pdf.lang);
          return doc;
        };
      });
      p.catch(function () { delete cache[key]; });
      return p;
    }
  };
})(typeof window !== 'undefined' ? window : globalThis);
