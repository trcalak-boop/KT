/* ==========================================================================
   T&S – jazykové verze ukázkových webů (Etapa 9)
   Ukázky jsou napsané česky (HTML i texty, které skládají skripty). Tato vrstva je
   před vykreslením přeloží do zvoleného jazyka podle slovníků (kit/i18n-kit.js
   pro společné texty, <značka>/i18n-data.js pro texty značky) a hlídá i obsah,
   který skripty doplní později (rezervace, administrace, e-maily).
   Logika ukázek se nemění: pracují dál česky, překládá se jen to, co se zobrazí.

   Jazyk: ?lang=en|cs|de, jinak volba z hlavního webu (localStorage ts-lang), jinak angličtina.
   Klíč slovníku = český text se sjednocenými mezerami; data a čísla jsou zástupné
   značky {0}, {1}… (data se vypíšou ve formátu zvoleného jazyka, čísla zůstávají).
   Co ve slovníku chybí, zůstane česky; s ?i18n=sber se chybějící klíče ukládají do
   sessionStorage (ts-i18n-chybi) pro doplnění slovníků (skripty/ukazky/sber.py).
   ========================================================================== */
(function () {
  'use strict';
  var JAZYKY = ['en', 'cs', 'de'], ZDROJ = 'cs';
  var q = /[?&]lang=([a-z]{2})\b/.exec(location.search), L = q && JAZYKY.indexOf(q[1]) >= 0 ? q[1] : null;
  // volba se sdílí s hlavním webem; ve vloženém náhledu se neukládá a nepřepíše jazyk webu, který ukázky nemají (např. es),
  // jinak by web návštěvníka po zhlédnutí ukázky přesměroval do angličtiny
  try {
    var ulozeno = localStorage.getItem('ts-lang');
    if (!L) L = ulozeno;
    else if (window.top === window && (!ulozeno || JAZYKY.indexOf(ulozeno) >= 0)) localStorage.setItem('ts-lang', L);
  } catch (e) { }
  if (JAZYKY.indexOf(L) < 0) L = 'en';
  var SBER = /[?&]i18n=sber\b/.test(location.search);
  var D = {};                 // klíč → překlad pro jazyk L
  var WJ = '\u2060', CHYBI = {}, HOTOVO = new Set();   // HOTOVO: už přeložené texty (vlastní změna uzlu je pro MutationObserver zase změna)
  var api = window.TSI18N = { lang: L, source: ZDROJ, langs: JAZYKY, add: add, key: key, t: t, run: run, missing: function () { return Object.keys(CHYBI); } };
  document.documentElement.setAttribute('lang', L);

  /* ---------- data: rozpoznání a výpis podle jazyka ---------- */
  var WD = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'], WDS = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];
  var MG = ['ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];
  var MN = ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'];
  var OUT = {
    en: { wd: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], wds: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
      m: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
      long: function (w, d, m) { return this.wd[w] + ' ' + d + ' ' + this.m[m]; }, short: function (w, d, m) { return this.wds[w] + ' ' + d + '/' + (m + 1); },
      dm: function (d, m, y) { return d + ' ' + this.m[m] + (y ? ' ' + y : ''); }, dmy: function (d, m, y) { return d + '/' + (m + 1) + '/' + y; }, dd: function (d, m) { return d + '/' + (m + 1); },
      my: function (m, y) { return this.m[m] + (y ? ' ' + y : ''); },
      num: function (x) { return x.replace(/,(?=\d+$)/, '.').replace(/[ \u00a0](?=\d{3})/g, ','); } },
    de: { wd: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'], wds: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
      m: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
      long: function (w, d, m) { return this.wd[w] + ', ' + d + '. ' + this.m[m]; }, short: function (w, d, m) { return this.wds[w] + ' ' + d + '.' + (m + 1) + '.'; },
      dm: function (d, m, y) { return d + '. ' + this.m[m] + (y ? ' ' + y : ''); }, dmy: function (d, m, y) { return d + '.' + (m + 1) + '.' + y; }, dd: function (d, m) { return d + '.' + (m + 1) + '.'; },
      my: function (m, y) { return this.m[m] + (y ? ' ' + y : ''); },
      num: function (x) { return x.replace(/[ \u00a0](?=\d{3})/g, '.'); } }
  };
  var A = function (a) { return '(' + a.join('|') + ')'; };
  var cap = function (s) { return s.charAt(0).toUpperCase() + s.slice(1); };
  var WDRX = A(WD.concat(WD.map(cap))), WDSRX = A(WDS.concat(WDS.map(cap))), MGRX = A(MG), MNRX = A(MN.concat(MN.map(cap)));
  // pořadí je důležité: delší vzory dřív
  var PAT = [
    { re: new RegExp(WDRX + '\\s+(\\d{1,2})\\.\\s*' + MGRX + '(?:\\s+(\\d{4}))?', 'g'), f: function (m, o) { var w = WD.indexOf(m[1].toLowerCase()), mm = MG.indexOf(m[3]); return o.long(w, +m[2], mm) + (m[4] ? ' ' + m[4] : ''); } },
    { re: new RegExp(WDSRX + '\\s+(\\d{1,2})\\.\\s*(\\d{1,2})\\.(?!\\s*\\d{4})', 'g'), f: function (m, o) { return o.short(WDS.indexOf(m[1].toLowerCase()), +m[2], +m[3] - 1); } },
    { re: new RegExp('(?<![{\\d])(\\d{1,2})\\.\\s*' + MGRX + '(?:\\s+(\\d{4}))?', 'g'), f: function (m, o) { return o.dm(+m[1], MG.indexOf(m[2]), m[3]); } },
    { re: /(?<![{\d])(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/g, f: function (m, o) { return o.dmy(+m[1], +m[2] - 1, m[3]); } },
    { re: /(?<![{\d])(\d{1,2})\.\s*(\d{1,2})\.(?![\d])/g, f: function (m, o) { return +m[2] <= 12 ? o.dd(+m[1], +m[2] - 1) : m[0]; } },
    { re: new RegExp('(^|[^\\p{L}])' + MNRX + '(?:\\s+(\\d{4}))?(?![\\p{L}])', 'gu'), lead: true, f: function (m, o) { return o.my(MN.indexOf(m[2].toLowerCase()), m[3]); } },
    { re: new RegExp('(^|[^\\p{L}])' + WDRX + '(?![\\p{L}])', 'gu'), lead: true, f: function (m, o) { return o.wd[WD.indexOf(m[2].toLowerCase())]; } },
    { re: /[\w.+-]+@[\w-]+(?:\.[\w-]+)+/g, f: function (m) { return m[0]; } },
    { re: /(?<![{\d\u0001])\d+(?:[ \u00a0]\d{3})*(?:[.,:]\d+)*(?![\d}])/g, f: function (m, o) { var g = m[0].split(/[ \u00a0]/); return /[.:]/.test(m[0]) || (g.length > 2 && g[0].length === 3) ? m[0] : o.num(m[0]); } }   // čas, verze a telefon beze změny
  ];
  function key(s) {
    var parts = [], txt = String(s).replace(/\s+/g, ' ').trim().replace(/\{(\d+)\}/g, '\u0001$1\u0002');   // značky vnějšího textu (vnořený {*}) zůstanou, jak jsou
    PAT.forEach(function (p, pi) {
      txt = txt.replace(p.re, function () {
        var m = Array.prototype.slice.call(arguments, 0, -2);
        if (typeof m[m.length - 1] === 'object') m.pop();
        var lead = p.lead ? m[1] : '';
        parts.push({ p: pi, m: m });
        return lead + '{' + (parts.length - 1) + '}';
      });
    });
    // znovu očíslovat podle pořadí v textu
    var order = [], k = txt.replace(/\{(\d+)\}/g, function (_, i) { order.push(+i); return '{' + (order.length - 1) + '}'; });
    return { k: k.replace(/\u0001(\d+)\u0002/g, '{$1}'), parts: order.map(function (i) { return parts[i]; }) };
  }
  function render(part) {
    var o = OUT[L], p = PAT[part.p];
    if (!o || !p.f) return part.m[0];
    try { return (p.lead ? '' : '') + p.f(part.m, o); } catch (e) { return part.m[0]; }
  }
  function t(s) {
    var raw0 = String(s), raw = raw0.indexOf(WJ) > -1 ? raw0.split(WJ).join('') : raw0;   // spojovače slov (viz nedelit) se do slovníku nepočítají
    if (L === ZDROJ || !/[A-Za-zÁ-ž\d]/.test(raw) || HOTOVO.has(raw0) || HOTOVO.has(raw)) return raw0;
    var lead = raw.match(/^\s*/)[0], trail = raw.match(/\s*$/)[0];
    var x = D[raw.replace(/\s+/g, ' ').trim()];
    if (x != null) return lead + x + trail;
    var r = key(raw), v = D[r.k];
    if (v == null && /\{\d+\}/.test(raw)) {   // vnořený text se značkami vnějšího textu: hledat s čísly od nuly a vrátit původní
      var map = [], k0 = r.k.replace(/\{(\d+)\}/g, function (_, n) { map.push(n); return '{' + (map.length - 1) + '}'; });
      if (D[k0] != null) v = D[k0].replace(/\{(\d+)\}/g, function (_, n) { return '{' + (map[+n] != null ? map[+n] : n) + '}'; });
    }
    if (v == null && W.length) v = wild(r.k);
    if (v == null && P.length) v = phrases(r.k);
    if (v == null && r.parts.length && !/[\p{L}]/u.test(r.k.replace(/\{\d+\}/g, ''))) v = r.k;      // jen data a čísla: stačí přeložit data
    if (v == null) {
      if (SBER && !QUIET && /[\p{L}]{2}/u.test(r.k)) { CHYBI[r.k] = 1; }
      return raw0;
    }
    return lead + v.replace(/\{(\d+)\}/g, function (_, i) { var pt = r.parts[+i]; return pt ? render(pt) : '{' + i + '}'; }) + trail;   // značku z vnějšího textu (vnořený {*}) doplní vnější překlad
  }
  var QUIET = 0;
  /* ---------- slovo se nikdy nerozdělí na dva řádky: za spojovník (e-mail, E-Commerce), do rozsahu čísel (12:00–18:00)
     a mezi závorky se vloží neviditelný spojovač slov U+2060, i v zobrazených adresách (stejně jako /assets/js/ts.js) ---------- */
  function nedelitText(x) {
    return x.replace(/[^\s\u00a0]+/g, function (w) {
      if (w.indexOf(WJ) > -1) return w;   // i v adrese e-mailu a webu (zobrazený text; odkaz mailto/href zůstává beze změny)
      return w.replace(/([\p{L}\p{N}])([-‐\/])(?=[\p{L}\p{N}])/gu, '$1$2' + WJ)
              .replace(/(\p{N}\.?)([–—])(?=\p{N})/gu, '$1' + WJ + '$2' + WJ)
              .replace(/\)\(/g, ')' + WJ + '(');
    }).replace(/(\p{N}) ([–—]) (?=\p{N})/gu, '$1\u00a0$2\u00a0')
            .replace(/(\p{N}) (?=\p{N}{3}(?!\p{N}))/gu, '$1\u00a0')   // 15 000: tisíce se nerozdělí
            .replace(/(\p{N}) (?=(?:Kč|CZK|EUR|€|%)(?![\p{L}]))/gu, '$1\u00a0');   // 15 000 Kč, 30 %
  }
  function nedelitUzel(n) {
    if (!n.parentNode || !/[-‐\/–—)]|\d \d{3}|\d (?:Kč|CZK|EUR|€|%)/.test(n.nodeValue) || (n.parentNode.closest && n.parentNode.closest('script, style, textarea, code, pre, [contenteditable]'))) return;
    var v = nedelitText(n.nodeValue); if (v !== n.nodeValue) { HOTOVO.add(v); n.nodeValue = v; }
  }
  function nedelit(root) {
    if (!root) return; if (root.nodeType === 3) return nedelitUzel(root); if (root.nodeType !== 1) return;
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT), n, l = [];
    while ((n = w.nextNode())) l.push(n);
    l.forEach(nedelitUzel);
  }
  var W = [];                 // klíče s {*}: [regulární výraz nad klíčem, překlad, pořadí skupin]
  function wild(k) {
    for (var i = 0; i < W.length; i++) {
      var m = W[i][0].exec(k); if (!m) continue;
      var g = W[i][2], num = {}, txt = [];
      g.forEach(function (x, j) { if (x === '*') txt.push(m[j + 1]); else num[x] = m[j + 1]; });
      var j = 0; QUIET++;
      try {   // vložený text (jméno, název vozu ap.) se přeloží zvlášť a nesbírá se; číslované značky dostanou čísla celého textu
        return W[i][1].replace(/\{(\*|\d+)\}/g, function (_, x) { return x === '*' ? t(txt[j++] || '') : '{' + (num[x] != null ? num[x] : x) + '}'; });
      } finally { QUIET--; }
    }
    return null;
  }
  var P = [];                 // fráze (názvy služeb ap.), které se přeloží i uvnitř jinak neznámého textu
  function phrases(k) {
    var out = k, hit = false;
    for (var i = 0; i < P.length; i++) out = out.replace(P[i][0], function (_, a) { hit = true; return a + P[i][1]; });
    return hit ? out : null;
  }
  function addPhrases(lang, list) {
    if (lang !== L) return;
    for (var k in list) P.push([new RegExp('(^|[^\\p{L}])' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![\\p{L}])', 'gu'), list[k], k.length]);
    P.sort(function (a, b) { return b[2] - a[2]; });
    if (started) run(document.body);
  }
  api.addPhrases = addPhrases;
  function add(lang, dict) {
    if (lang !== L) return;
    for (var k in dict) {
      if (!dict[k]) continue;
      if (k.indexOf('{*}') >= 0) {      // {*} = libovolný text (smí obsahovat i čísla), {n} = číslo na svém místě
        var g = [], rx = k.split(/(\{\*\}|\{\d+\})/).map(function (x) {
          if (x === '{*}') { g.push('*'); return '(.+?)'; }
          var n = /^\{(\d+)\}$/.exec(x); if (n) { g.push(n[1]); return '\\{(\\d+)\\}'; }
          return x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }).join('');
        W.push([new RegExp('^' + rx + '$'), dict[k], g, k.replace(/\{\*\}/g, '').length]);
        W.sort(function (a, b) { return b[3] - a[3]; });   // přednost má konkrétnější klíč (víc pevného textu)
      }
      else D[k] = dict[k];
    }
    if (started) run(document.body);
  }

  /* ---------- překlad stránky ---------- */
  var ATTR = ['placeholder', 'title', 'aria-label', 'alt', 'data-cursor'];
  var SKIP = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, CODE: 1, PRE: 1, TEXTAREA: 1 };
  function skip(el) { for (var e = el; e && e.nodeType === 1; e = e.parentNode) { if (SKIP[e.tagName] || e.getAttribute('translate') === 'no' || e.isContentEditable) return true; } return false; }
  function txNode(n) {
    if (!n.parentNode || skip(n.parentNode)) return;
    var v = n.nodeValue; if (!v || !/[\p{L}\d]/u.test(v)) return;
    var nv = t(v); if (nv !== v) { HOTOVO.add(nv); n.nodeValue = nv; }
  }
  function txEl(el) {
    if (skip(el)) return;
    for (var i = 0; i < ATTR.length; i++) { var a = el.getAttribute(ATTR[i]); if (a && /[\p{L}]/u.test(a)) { var na = t(a); if (na !== a) { HOTOVO.add(na); el.setAttribute(ATTR[i], na); } } }
    if ((el.tagName === 'INPUT' && /^(button|submit|reset)$/.test(el.type)) && el.value) { var nv = t(el.value); if (nv !== el.value) { HOTOVO.add(nv); el.value = nv; } }
  }
  function run(root) {
    if (L === ZDROJ || !root) return;
    if (root.nodeType === 3) { txNode(root); return; }
    if (root.nodeType !== 1 && root.nodeType !== 9) return;
    if (root.nodeType === 1) txEl(root);
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT), n;
    while ((n = w.nextNode())) { if (n.nodeType === 3) txNode(n); else txEl(n); }
  }
  /* odkaz zpět na portfolio vede na stránku projektu ve stejném jazyce */
  var PROJEKT = { en: 'case-study.html', cs: 'cs/projekt.html', de: 'de/projekt.html' };
  function zpet() {
    Array.prototype.forEach.call(document.querySelectorAll('a[href^="../projekt.html"]'), function (a) { a.setAttribute('href', a.getAttribute('href').replace('projekt.html', PROJEKT[L])); });
  }
  var started = false;
  function start() {
    started = true;
    zpet();
    if (L !== ZDROJ) {
      document.title = t(document.title);
      ['description', 'og:title', 'og:description'].forEach(function (n) { var m = document.querySelector('meta[name="' + n + '"],meta[property="' + n + '"]'); if (m && m.content) m.content = t(m.content); });
      run(document.body);
      new MutationObserver(function (list) {
        list.forEach(function (m) {
          if (m.type === 'characterData') txNode(m.target);
          else if (m.type === 'attributes') txEl(m.target);
          else for (var i = 0; i < m.addedNodes.length; i++) run(m.addedNodes[i]);
        });
        if (!document.querySelector('.ts-lang')) switcher();   // aplikace lištu překreslila (jednostránková ukázka)
      }).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTR });
    }
    nedelit(document.body);
    // zkopírovaný text (adresa, telefon) se vloží bez neviditelných spojovačů
    document.addEventListener('copy', function (e) {
      var s = String(window.getSelection ? getSelection() : ''); if (s.indexOf(WJ) < 0 || !e.clipboardData) return;
      e.clipboardData.setData('text/plain', s.split(WJ).join('')); e.preventDefault();
    });
    new MutationObserver(function (list) {      // až po překladu (pozorovatel překladu je zaregistrovaný dřív, dostává změny první)
      list.forEach(function (m) { if (m.type === 'characterData') nedelitUzel(m.target); else for (var i = 0; i < m.addedNodes.length; i++) nedelit(m.addedNodes[i]); });
    }).observe(document.body, { childList: true, subtree: true, characterData: true });
    document.documentElement.classList.remove('i18n-wait');
    switcher();
    fitStart();
    if (L === ZDROJ) new MutationObserver(function () { if (!document.querySelector('.ts-lang')) switcher(); }).observe(document.body, { childList: true, subtree: true });
    if (SBER) setInterval(function () {        // sběr chybějících textů napříč stránkami jedné relace
      try { var o = JSON.parse(sessionStorage.getItem('ts-i18n-chybi') || '[]'); Object.keys(CHYBI).forEach(function (k) { if (o.indexOf(k) < 0) o.push(k); }); sessionStorage.setItem('ts-i18n-chybi', JSON.stringify(o)); } catch (e) { }
    }, 250);
  }

  /* ---------- dlouhé slovo (typicky němčina) se nevejde do sloupce: písmo toho prvku se zmenší, slovo se nikdy nerozdělí ---------- */
  var FIT = 'h1, h2, h3, h4, h5, h6, p, li, dt, dd, th, td, b, strong, em, span, a, button, label, figcaption, blockquote, small';
  function rodic(el) { for (var b = el; b && /^inline/.test(getComputedStyle(b).display) && b.parentElement; b = b.parentElement); return b; }
  function preteka(el) {   // měří se rozměry rozvržení (scrollWidth), ne polohy na obrazovce – ty zkresluje natočení (transform)
    var b = rodic(el); if (!b.offsetWidth) return false;
    if (b.scrollWidth > b.clientWidth + 2 && getComputedStyle(b).overflowX === 'visible') return true;
    var p = b.parentElement; if (!p || p === document.body || getComputedStyle(p).display === 'contents') return false;
    // položka flexu/mřížky roztažená na délku slova je širší než obsah rodiče (přetečení sourozence se nepočítá)
    var ps = getComputedStyle(p); return b.offsetWidth > p.clientWidth - parseFloat(ps.paddingLeft) - parseFloat(ps.paddingRight) + 2;
  }
  function jenText(el) {   // jen text a řádkové značky – u bloků s obrázky, poli apod. velikost písma nepomůže
    for (var c = el.firstElementChild; c; c = c.nextElementSibling) if (!/^(B|STRONG|EM|I|SPAN|A|BR|SMALL|SUP|SUB|ABBR|TIME|MARK|U|S|Q|CODE|WBR)$/.test(c.tagName) || c.querySelector('img, svg:not([aria-hidden=true]), input, select, textarea, video, canvas')) return false;
    return /[\p{L}\d]{2}/u.test(el.textContent);
  }
  function posuvne(el) { for (var e = el.parentElement; e && e !== document.body; e = e.parentElement) { var o = getComputedStyle(e).overflowX; if (o === 'auto' || o === 'scroll') return true; } return false; }
  // styly ukázek místy dovolují rozdělit slovo kdekoli (overflow-wrap:anywhere) – slovo se raději zmenší (viz prizpusobit)
  function bezDeleni() {
    var st = document.createElement('style'); st.setAttribute('data-ts', 'slova');
    st.textContent = 'body :not(code):not(pre):not(textarea):not(.lk-pre){overflow-wrap:normal!important;hyphens:manual!important}';
    document.head.appendChild(st);
  }
  function prizpusobit() {
    [].forEach.call(document.querySelectorAll('[data-ts-fit]'), function (el) { el.style.fontSize = el.getAttribute('data-ts-fit'); el.style.removeProperty('overflow-wrap'); el.removeAttribute('data-ts-fit'); });
    var l = [].filter.call(document.querySelectorAll(FIT), function (el) {
      if (!el.offsetParent || el.closest('.ts-demobar, .ts-lang, svg, [hidden]') || !jenText(el)) return false;
      if (getComputedStyle(el).textOverflow === 'ellipsis') return false;
      return preteka(el) && !posuvne(el);
    });
    l.forEach(function (el) {
      if (el.parentElement && el.parentElement.closest('[data-ts-fit]')) return;   // zmenšil se už rodič
      var puv = el.style.fontSize, base = parseFloat(getComputedStyle(el).fontSize), fs = base;
      var jedno = !/\s/.test(el.textContent.trim());   // jediné slovo, e-mail, datum: smí se zmenšit víc
      var min = base >= 20 || jedno ? .55 : .8;   // titulky se smějí zmenšit víc, běžný text jen trochu (jinak je chyba v rozvržení, ne v délce slova)
      el.setAttribute('data-ts-fit', puv);
      while (preteka(el) && fs > base * min) { fs *= .94; el.style.fontSize = fs.toFixed(1) + 'px'; }
      if (preteka(el)) {   // ani zmenšení nestačí: poslední možnost je zalomit dlouhý řetězec (adresa, kód), aby nevyjel z obrazovky
        el.style.fontSize = puv; el.style.setProperty('overflow-wrap', 'anywhere', 'important');
        if (preteka(el)) { el.style.removeProperty('overflow-wrap'); el.removeAttribute('data-ts-fit'); }   // nepomohlo – příčina není v délce slova
      }
    });
  }
  // měří se rozvržení bez natočení (nálepky, lístečky); změna se nestihne vykreslit
  function bezTransformaci(fn) {
    var t = [].filter.call(document.querySelectorAll('body *'), function (e) { var m = getComputedStyle(e).transform.match(/^matrix\(([^,]+), ([^,]+), ([^,]+)/); return !!m && (Math.abs(+m[2]) > 1e-3 || Math.abs(+m[3]) > 1e-3); });
    var puv = t.map(function (e) { return ['transform', 'transition'].map(function (k) { return [e.style.getPropertyValue(k), e.style.getPropertyPriority(k)]; }); });
    var vrat = function (e, i, j, k) { e.style.removeProperty(k); if (puv[i][j][0]) e.style.setProperty(k, puv[i][j][0], puv[i][j][1]); };
    t.forEach(function (e) { e.style.setProperty('transition', 'none', 'important'); e.style.setProperty('transform', 'none', 'important'); });
    try { fn(); } finally {
      t.forEach(function (e, i) { vrat(e, i, 0, 'transform'); });
      if (t.length) getComputedStyle(t[0]).transform;   // přepočet stylu ještě bez přechodu, ať se natočení nevrací animací
      t.forEach(function (e, i) { vrat(e, i, 1, 'transition'); });
    }
  }
  // řádkování titulků podle skutečné výšky písmen: háček, čárka ani kroužek nesmí narazit do řádku nad sebou
  var cv;
  function rozestup() { bezTransformaci(rozestup0); }
  function rozestup0() {
    cv = cv || document.createElement('canvas').getContext('2d');
    var VL = ['lineHeight', 'paddingTop', 'paddingBottom', 'marginTop', 'marginBottom'];
    [].slice.call(document.querySelectorAll('[data-lh]')).forEach(function (el) { var o = JSON.parse(el.getAttribute('data-lh')); VL.forEach(function (k, i) { el.style.removeProperty(k.replace(/[A-Z]/g, function (c) { return '-' + c.toLowerCase(); })); if (o[i]) el.style[k] = o[i]; }); el.removeAttribute('data-lh'); });
    var znac = function (el) { if (!el.hasAttribute('data-lh')) el.setAttribute('data-lh', JSON.stringify(VL.map(function (k) { return el.style[k]; }))); };
    var kand = [].slice.call(document.querySelectorAll('body *')).filter(function (el) {
      if (!el.offsetParent || el.closest('svg, script, style, .ts-demobar, .ts-lang')) return false;
      var s = getComputedStyle(el), fs = parseFloat(s.fontSize), lh = parseFloat(s.lineHeight);
      return /\S/.test(el.textContent) && s.display !== 'contents' && fs >= 8 && lh / fs < 1.45;   // ukázky mají i drobné popisky a citáty s těsným řádkováním
    });
    // řádky prvku se skutečným inkoustem písmen (háčky, čárky, ocásky) měřeným písmem každého úseku textu
    function radky(el, lh) {
        var fs0 = parseFloat(getComputedStyle(el).fontSize);
      var out = [], r = document.createRange(), tw = document.createTreeWalker(el, NodeFilter.SHOW_TEXT), n, re = /\S+/g, m;
      while ((n = tw.nextNode())) {
        var ps = getComputedStyle(n.parentElement); if (parseFloat(ps.fontSize) < fs0 * .75) continue;   // drobný odznak uvnitř titulku (počet) netvoří vlastní řádek
        cv.font = ps.fontStyle + ' ' + ps.fontWeight + ' ' + ps.fontSize + ' ' + ps.fontFamily;
        var asc = cv.measureText('H').fontBoundingBoxAscent;
        for (re.lastIndex = 0; (m = re.exec(n.nodeValue));) {
          var mt = cv.measureText(ps.textTransform === 'uppercase' ? m[0].toUpperCase() : m[0]);
          r.setStart(n, m.index); r.setEnd(n, m.index + m[0].length);
          [].forEach.call(r.getClientRects(), function (x) { if (x.width < .5) return;
            var base = x.top + asc, L = out.filter(function (l) { return Math.abs(l.base - base) < lh * .4; })[0];
            if (!L) out.push(L = { base: base, t: Infinity, b: -Infinity });
            L.t = Math.min(L.t, base - mt.actualBoundingBoxAscent); L.b = Math.max(L.b, base + mt.actualBoundingBoxDescent); });
        }
      }
      return out.sort(function (a, b) { return a.base - b.base; });
    }
    // 1) řádky uvnitř prvku: nejvyšší písmeno řádku nesmí narazit do nejnižšího písmene řádku nad ním
    kand.forEach(function (el) {
      var s = getComputedStyle(el), fs = parseFloat(s.fontSize), lh = parseFloat(s.lineHeight);
      var R = radky(el, lh), need = 0; if (R.length < 2) return;   // jen víc řádků (i u řádkového prvku, jehož výška řádku určuje rozestup)
      for (var i = 1; i < R.length; i++) need = Math.max(need, (R[i - 1].b - R[i - 1].base + R[i].base - R[i].t + Math.max(fs * .06, 1.5)) / fs);
      if (need > lh / fs) { znac(el); el.style.setProperty('line-height', need.toFixed(3), 'important'); }
    });
    // 2) sousední prvek nad a pod: popisek nad titulkem nesmí zasahovat do háčku, text pod ním do ocásku
    kand.forEach(function (el) {
      var s = getComputedStyle(el), fs = parseFloat(s.fontSize); if (fs < 16) return;   // odstup od sousedů jen u titulků
      var R = radky(el, parseFloat(s.lineHeight)); if (!R.length) return;
      var q = el.getBoundingClientRect(), mez = Math.max(4, fs * .06);
      if (getComputedStyle(el).transform !== 'none') return;
      var sous = function (x, nad) { if (!x || !x.offsetParent || getComputedStyle(x).position === 'absolute' || getComputedStyle(x).transform !== 'none') return false;   // jen prvek přímo nad/pod, ne vedle v mřížce
        var w = x.getBoundingClientRect(); return w.height > 0 && Math.min(w.right, q.right) - Math.max(w.left, q.left) > 2 && (nad ? w.top < q.top : w.bottom > q.bottom); };
      var pr = el.previousElementSibling, nx = el.nextElementSibling;
      var horni = sous(pr, 1) ? pr.getBoundingClientRect().bottom + mez - R[0].t : 0, dolni = sous(nx, 0) ? R[R.length - 1].b + mez - nx.getBoundingClientRect().top : 0;
      // blok dostane vnitřní odsazení; u řádkového prvku (b, span) se odsune sousední blok
      var radk = /^inline/.test(s.display) && !/block/.test(s.display);
      if (horni > .5) { if (radk) { znac(pr); pr.style.setProperty('margin-bottom', (parseFloat(getComputedStyle(pr).marginBottom) + horni).toFixed(1) + 'px', 'important'); }
        else { znac(el); el.style.setProperty('padding-top', (parseFloat(s.paddingTop) + horni).toFixed(1) + 'px', 'important'); } }
      if (dolni > .5) { if (radk) { znac(nx); nx.style.setProperty('margin-top', (parseFloat(getComputedStyle(nx).marginTop) + dolni).toFixed(1) + 'px', 'important'); }
        else { znac(el); el.style.setProperty('padding-bottom', (parseFloat(s.paddingBottom) + dolni).toFixed(1) + 'px', 'important'); } }
    });
  }
  var fitT; function fitPozdeji() { clearTimeout(fitT); fitT = setTimeout(function () { obs && obs.disconnect(); try { prizpusobit(); rozestup(); } finally { obs && obs.observe(document.body, { childList: true, subtree: true, characterData: true }); } }, 180); }
  var obs = window.MutationObserver ? new MutationObserver(fitPozdeji) : null;
  function fitStart() {
    bezDeleni(); fitPozdeji(); addEventListener('resize', fitPozdeji); addEventListener('load', fitPozdeji);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitPozdeji);
  }

  /* ---------- nativní dialogy (potvrzení ap.) ---------- */
  ['confirm', 'alert', 'prompt'].forEach(function (n) {
    var f = window[n]; if (L === ZDROJ || typeof f !== 'function') return;
    window[n] = function (msg) { var a = Array.prototype.slice.call(arguments); if (msg != null) a[0] = t(String(msg)); return f.apply(window, a); };
  });

  /* ---------- přepínač jazyka v liště ukázky ---------- */
  function switcher() {
    var bar = document.querySelector('.ts-db-in, .bar-inner'); if (!bar || bar.querySelector('.ts-lang')) return;
    var s = document.createElement('span'); s.className = 'ts-lang'; s.setAttribute('translate', 'no');
    s.innerHTML = JAZYKY.map(function (k) { return '<a href="#" data-l="' + k + '"' + (k === L ? ' aria-current="true"' : '') + (k === 'de' ? ' title="Deutsch – Übersetzung noch nicht muttersprachlich geprüft"' : '') + ' lang="' + k + '">' + k.toUpperCase() + '</a>'; }).join('');
    s.addEventListener('click', function (e) {
      var a = e.target.closest('[data-l]'); if (!a) return; e.preventDefault();
      if (a.getAttribute('data-l') === L) { s.classList.toggle('open'); return; }   // na mobilu je vidět jen zvolený jazyk, klepnutím se ukážou ostatní
      try { localStorage.setItem('ts-lang', a.getAttribute('data-l')); } catch (x) { }
      var u = location.href.replace(/([?&])lang=[a-z]{2}&?/, '$1').replace(/[?&]$/, '');
      location.href = u;
    });
    bar.appendChild(s);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
  setTimeout(function () { document.documentElement.classList.remove('i18n-wait'); }, 1500);   // pojistka: stránka se ukáže vždy
})();
