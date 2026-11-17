/* T&S Studio – minimální testovací knihovna pro stránky tests/*.test.html (spouští je tools/ts.py test v Chromu).
   Výsledek zapisuje do window.__TSTEST, odkud ho čte spouštěč. */
(function () {
  'use strict';
  var R = window.__TSTEST = { done: false, pass: 0, fail: 0, log: [] };
  var q = new URLSearchParams(location.search);
  function same(a, b) { return JSON.stringify(a) === JSON.stringify(b); }
  var T = window.T = {
    ok: function (c, msg) { if (c) R.pass++; else { R.fail++; R.log.push('FAIL ' + msg); } return !!c; },
    eq: function (a, b, msg) { var s = same(a, b); if (s) R.pass++; else { R.fail++; R.log.push('FAIL ' + msg + ' | dostal ' + JSON.stringify(a).slice(0, 300) + ' | čekal ' + JSON.stringify(b).slice(0, 300)); } return s; },
    info: function (msg) { R.log.push('INFO ' + msg); },
    /* adresa zdrojového projektu (tools/sources.json), např. T.src('tas-web') + '/web/ukazky/kit/kit.js' */
    src: function (key) { var v = q.get(key); if (!v) throw new Error('chybí zdroj ' + key + ' (tools/sources.json)'); return v; },
    lib: function (rel) { return new URL('../../../' + rel, location.href).href; },     /* cesta od kořene knihovny */
    script: function (url) { return new Promise(function (ok, no) { var s = document.createElement('script'); s.src = url; s.onload = ok; s.onerror = function () { no(new Error('nelze načíst ' + url)); }; document.head.appendChild(s); }); },
    scripts: function (urls) { return urls.reduce(function (p, u) { return p.then(function () { return T.script(u); }); }, Promise.resolve()); },
    /* zmrazí čas prohlížeče (Date, Date.now) – volat před načtením testovaného kódu */
    freeze: function (iso) {
      var Real = window.Date, fixed = new Real(iso).getTime();
      function D() { if (!(this instanceof D)) return new Real(fixed).toString(); var a = Array.prototype.slice.call(arguments); return a.length ? new (Function.prototype.bind.apply(Real, [null].concat(a)))() : new Real(fixed); }
      D.now = function () { return fixed; }; D.UTC = Real.UTC; D.parse = Real.parse; D.prototype = Real.prototype;
      window.Date = D; T.realDate = Real;
    },
    run: function (fn) {
      Promise.resolve().then(fn).catch(function (e) { R.fail++; R.log.push('FAIL výjimka: ' + (e && e.stack || e)); }).then(function () { R.done = true; });
    }
  };
})();
