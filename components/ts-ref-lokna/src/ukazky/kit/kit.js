/* ==========================================================================
   T&S – společný základ ukázkových webů
   Úložiště změn v okně (sessionStorage), lišta ukázky, administrace, kalendář,
   e-maily, modální okna, upozornění a PDF. Používají ho všechny ukázky.
   ========================================================================== */
(function () {
  'use strict';
  var TS = window.TS = {};

  /* ---------- drobnosti ---------- */
  var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  TS.esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ESC[c]; }); };
  TS.uid = function (p) { return (p || '') + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); };
  var pad = TS.pad = function (n) { return String(n).padStart(2, '0'); };
  TS.clone = function (o) { return JSON.parse(JSON.stringify(o)); };
  TS.num = function (v) { var n = Number(String(v == null ? '' : v).replace(/\s/g, '').replace(',', '.')); return isFinite(n) ? n : 0; };
  TS.plural = function (n, a, b, c) { n = Math.abs(n); return n === 1 ? a : (n >= 2 && n <= 4 ? b : c); };
  TS.money = function (n, cur) {
    n = Number(n) || 0; var d = Math.round(Math.abs(n) * 100) % 100 ? 2 : 0;
    return new Intl.NumberFormat('cs-CZ', { minimumFractionDigits: d, maximumFractionDigits: 2 }).format(n) + '\u00a0' + (cur || 'Kč');
  };
  TS.money2 = function (n) { return new Intl.NumberFormat('cs-CZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0) + '\u00a0Kč'; };
  TS.int = function (n) { return new Intl.NumberFormat('cs-CZ').format(Math.round(Number(n) || 0)); };
  TS.get = function (o, path) { return path.split('.').reduce(function (a, k) { return a == null ? a : a[k]; }, o); };
  TS.set = function (o, path, v) { var ks = path.split('.'), x = o; for (var i = 0; i < ks.length - 1; i++) { if (x[ks[i]] == null) return false; x = x[ks[i]]; } x[ks[ks.length - 1]] = v; return true; };
  TS.validEmail = function (s) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(s || '').trim()); };
  TS.validPhone = function (s) { return String(s || '').replace(/[^\d]/g, '').length >= 9; };
  TS.norm = function (s) { return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); };
  TS.slug = function (s) { return TS.norm(s).replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48); };
  TS.$ = function (s, r) { return (r || document).querySelector(s); };
  TS.$$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- datum a čas (místní čas prohlížeče) ---------- */
  var WD = TS.WD = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'];
  var WDS = TS.WDS = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];
  var WDN = TS.WDN = ['Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'];
  var MG = TS.MG = ['ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];
  var MN = TS.MN = ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'];
  TS.iso = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  TS.today = function () { return TS.iso(new Date()); };
  TS.nowMin = function () { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); };
  TS.parse = function (s) { var a = String(s).split('-').map(Number); return new Date(a[0], a[1] - 1, a[2], 12); };
  TS.addDays = function (s, n) { var d = TS.parse(s); d.setDate(d.getDate() + n); return TS.iso(d); };
  TS.diffDays = function (a, b) { return Math.round((TS.parse(b) - TS.parse(a)) / 864e5); };
  TS.wd = function (s) { return TS.parse(s).getDay(); };
  TS.weekStart = function (s) { var w = TS.wd(s); return TS.addDays(s, w === 0 ? -6 : 1 - w); };
  TS.cap = function (s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); };
  TS.fmtLong = function (s) { var d = TS.parse(s); return WD[d.getDay()] + ' ' + d.getDate() + '. ' + MG[d.getMonth()]; };
  TS.fmtShort = function (s) { var d = TS.parse(s); return WDS[d.getDay()] + ' ' + d.getDate() + '. ' + (d.getMonth() + 1) + '.'; };
  TS.fmtDM = function (s) { var d = TS.parse(s); return d.getDate() + '. ' + (d.getMonth() + 1) + '.'; };
  TS.fmtDMY = function (s) { var d = TS.parse(s); return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear(); };
  TS.fmtDayMonth = function (s) { var d = TS.parse(s); return d.getDate() + '. ' + MG[d.getMonth()]; };
  TS.fmtRel = function (s) { var dd = TS.diffDays(TS.today(), s); return dd === 0 ? 'dnes' : dd === 1 ? 'zítra' : dd === -1 ? 'včera' : TS.fmtShort(s); };
  TS.hm = function (m) { m = Math.round(m); return Math.floor(m / 60) + ':' + pad(m % 60); };
  TS.hhmm = function (m) { m = Math.round(m); return pad(Math.floor(m / 60)) + ':' + pad(m % 60); };
  TS.parseHM = function (s) { if (s == null || s === '') return null; var a = String(s).split(':').map(Number); if (a.length < 2 || a.some(isNaN)) return null; return a[0] * 60 + a[1]; };
  TS.fmtStamp = function (ts) { var d = new Date(ts); return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getHours() + ':' + pad(d.getMinutes()); };
  TS.ago = function (ts) {
    var m = Math.round((Date.now() - ts) / 60000);
    if (m < 1) return 'právě teď'; if (m < 60) return 'před ' + m + ' min';
    var h = Math.round(m / 60); if (h < 24) return 'před ' + h + ' h';
    var d = Math.round(h / 24); return 'před ' + d + ' ' + TS.plural(d, 'dnem', 'dny', 'dny');
  };

  /* ---------- ikony (čárové, 24 × 24) ---------- */
  var I = TS.I = {
    home: '<path d="M4 11 12 4l8 7v9H4Z"/><path d="M10 20v-6h4v6"/>',
    cal: '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    list: '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
    menu: '<path d="M4 4v6a3 3 0 0 0 6 0V4M7 4v16M17 4c-2 0-3 3-3 6s1 4 3 4v6"/>',
    wine: '<path d="M8 3h8l-1 7a3 3 0 0 1-6 0Z"/><path d="M12 13v7M8 21h8"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    phone: '<path d="M5 4h4l2 5-3 2a11 11 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18.5 20a6.5 6.5 0 0 0-3-5.5"/>',
    sliders: '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
    plus: '<path d="M12 5v14M5 12h14"/>', x: '<path d="M6 6l12 12M18 6 6 18"/>', check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    chevL: '<path d="M15 6l-6 6 6 6"/>', chevR: '<path d="M9 6l6 6-6 6"/>', up: '<path d="M6 15l6-6 6 6"/>', down: '<path d="M6 9l6 6 6-6"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
    edit: '<path d="M4 20h4L19 9l-4-4L4 16Z"/><path d="M13 7l4 4"/>',
    eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    file: '<path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
    print: '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
    download: '<path d="M12 3v12m-5-5 5 5 5-5M5 21h14"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
    car: '<path d="M3 13l2-5a2 2 0 0 1 2-1h10a2 2 0 0 1 2 1l2 5v5h-3M6 18H3v-5h18"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/>',
    key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M15 8l2 2"/>',
    tag: '<path d="M3 12V4h8l10 10-8 8Z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
    box: '<path d="M3 7l9-4 9 4v10l-9 4-9-4Z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
    cart: '<path d="M5 8h14l-1 12H6Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    percent: '<path d="M19 5 5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/>',
    star: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9Z"/>',
    bell: '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    news: '<path d="M4 5h13v14H6a2 2 0 0 1-2-2Z"/><path d="M17 9h3v8a2 2 0 0 1-2 2M8 9h5M8 13h5M8 17h3"/>',
    food: '<path d="M4 3v7a3 3 0 0 0 6 0V3M7 3v18M17 3c-2 0-3 3-3 6s1 4 3 4v8"/>',
    pin: '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
    back: '<path d="M15 6l-6 6 6 6"/>', out: '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
    refresh: '<path d="M4 12a8 8 0 1 0 2.3-5.7M4 4v5h5"/>',
    grid: '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
    more: '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
    lock: '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    rx: '<path d="M6 4h6a4 4 0 0 1 0 8H6ZM6 12v8M11 12l7 8M18 12l-7 8"/>',
    heart: '<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11Z"/>',
    truck: '<path d="M3 7h11v9H3ZM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
    send: '<path d="M21 3 3 10l7 3 3 7Z"/><path d="m10 13 11-10"/>',
    scissors: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.5 7.5 20 18M8.5 16.5 20 6"/>',
    palette: '<path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 2-2s-1-2 0-3 2-1 3-1a4 4 0 0 0 4-4c0-4.4-4-8-9-8Z"/><circle cx="7.5" cy="11" r="1.2"/><circle cx="10" cy="7" r="1.2"/><circle cx="15" cy="7" r="1.2"/>',
    school: '<path d="M2 9l10-5 10 5-10 5Z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
    receipt: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2Z"/><path d="M9 8h6M9 12h6"/>',
    shield: '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6Z"/>',
    hourglass: '<path d="M6 3h12M6 21h12M7 3c0 5 10 5 10 9s-10 4-10 9M17 3c0 5-10 5-10 9"/>'
  };
  /* třídy společného základu mají předponu k-, ať se nepletou se styly webů */
  TS.kc = function (cls) { return cls ? ' ' + String(cls).split(/\s+/).filter(Boolean).map(function (c) { return c.indexOf('k-') === 0 ? c : 'k-' + c; }).join(' ') : ''; };
  /* web si může doplnit vlastní ikony: TS.addIcons({ jmeno: '<path d="…"/>' }) */
  TS.addIcons = function (o) { for (var k in o) I[k] = o[k]; };
  TS.ic = function (n, cls) { return '<svg class="k-ic' + (cls ? ' ' + cls : '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (I[n] || '') + '</svg>'; };

  /* ---------- úložiště ukázky: změny platí v tomto okně (karta prohlížeče) ---------- */
  TS.data = function (key, version, defaults) {
    TS.key = key; TS._K = 'ts-demo:' + key + ':v' + version; TS._defaults = defaults;
    var S = null;
    try { var raw = sessionStorage.getItem(TS._K); if (raw) S = JSON.parse(raw); } catch (e) { S = null; }
    if (!S || typeof S !== 'object') { S = defaults(); try { sessionStorage.setItem(TS._K, JSON.stringify(S)); } catch (e) { } }
    if (!S.emails) S.emails = [];
    TS.D = S;
    return S;
  };
  TS.save = function () { try { sessionStorage.setItem(TS._K, JSON.stringify(TS.D)); } catch (e) { } TS.badge(); };
  TS.reset = function () { var S = TS._defaults(); for (var k in TS.D) delete TS.D[k]; Object.assign(TS.D, S); if (!TS.D.emails) TS.D.emails = []; TS.save(); return TS.D; };
  TS.flag = function (name, v) {
    var k = 'ts-demo:' + TS.key + ':' + name;
    try { if (v === undefined) return sessionStorage.getItem(k); if (v === null) sessionStorage.removeItem(k); else sessionStorage.setItem(k, v); } catch (e) { }
    return null;
  };

  /* počet nepřečtených e-mailů v liště ukázky */
  TS.badge = function () {
    if (!TS.D) return;
    var n = (TS.D.emails || []).filter(function (e) { return !e.read; }).length;
    TS.$$('[data-mail-badge]').forEach(function (b) { b.textContent = n; b.hidden = !n; });
  };

  /* veřejné stránky: zapamatují si poslední navštívenou stránku pro odkaz „Web“ z administrace */
  TS.webPage = function () {
    var f = location.pathname.split('/').pop() || '';
    TS.flag('web', f + location.search);
    TS.badge();
  };
  /* náhled v referencích (?nahled): odkazy mezi stránkami ukázky si ho ponechají, lišta T&S zůstane skrytá */
  if (/[?&]nahled\b/.test(location.search)) document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]'); if (!a) return;
    var h = a.getAttribute('href'); if (!/^[a-z0-9-]+\.html/i.test(h) || /[?&]nahled\b/.test(h)) return;
    var p = h.split('#'); a.setAttribute('href', p[0] + (p[0].indexOf('?') < 0 ? '?' : '&') + 'nahled=1' + (p[1] ? '#' + p[1] : ''));
  }, true);
  TS.webLink = function () {
    var f = TS.flag('web');
    TS.$$('[data-web-link]').forEach(function (a) { if (f) a.setAttribute('href', f); });
  };

  /* ---------- světlý / tmavý režim (všechna tlačítka [data-mode-btn]) ---------- */
  TS.mode = function (storeKey, onChange) {
    var root = document.documentElement;
    function stored() { try { return localStorage.getItem(storeKey); } catch (e) { return null; } }
    function set(t) {
      root.setAttribute('data-theme', t);
      var l = t === 'dark' ? 'Přepnout na světlý režim' : 'Přepnout na tmavý režim';
      TS.$$('[data-mode-btn]').forEach(function (b) { b.setAttribute('aria-label', l); b.title = l; });
      if (onChange) onChange(t);
    }
    set(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-mode-btn]'); if (!b) return;
      var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem(storeKey, t); } catch (x) { }
      set(t);
    });
    if (window.matchMedia) {
      var mq = matchMedia('(prefers-color-scheme: dark)'), f = function (e) { if (!stored()) set(e.matches ? 'dark' : 'light'); };
      if (mq.addEventListener) mq.addEventListener('change', f); else if (mq.addListener) mq.addListener(f);
    }
  };

  /* ---------- mobilní menu webu ---------- */
  TS.burger = function (btn, nav) {
    btn = typeof btn === 'string' ? TS.$(btn) : btn; nav = typeof nav === 'string' ? TS.$(nav) : nav;
    if (!btn || !nav) return;
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); });
    TS.$$('a', nav).forEach(function (a) { a.addEventListener('click', function () { nav.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }); });
  };

  /* ---------- upozornění (toast) ---------- */
  var toastT = null;
  TS.toast = function (text, opt) {
    opt = opt || {};
    var root = TS.$('#k-toast-root');
    if (!root) { root = document.createElement('div'); root.id = 'k-toast-root'; root.setAttribute('aria-live', 'polite'); document.body.appendChild(root); }
    var act = '';
    if (opt.href) act = '<a href="' + TS.esc(opt.href) + '">' + TS.esc(opt.label || 'Zobrazit') + '</a>';
    else if (opt.act) act = '<button type="button" data-act="' + TS.esc(opt.act) + '" data-arg="' + TS.esc(opt.arg || '') + '">' + TS.esc(opt.label) + '</button>';
    root.innerHTML = '<div class="k-toast' + (opt.bad ? ' k-bad' : '') + '" role="status"><span>' + TS.esc(text) + '</span>' + act + '</div>';
    clearTimeout(toastT); toastT = setTimeout(function () { root.innerHTML = ''; }, act ? 7000 : 4200);
  };

  /* ---------- modální okno ---------- */
  var modalClose = null, modalFocus = null;
  TS.mShell = function (title, sub, body, foot, cls, label) {
    return '<div class="k-modal' + TS.kc(cls) + '" role="dialog" aria-modal="true" aria-label="' + TS.esc(label || String(title).replace(/<[^>]+>/g, '')) + '">' +
      '<div class="k-m-head"><div style="min-width:0"><h2>' + title + '</h2>' + (sub ? '<p>' + sub + '</p>' : '') + '</div>' +
      '<button class="k-icon-btn" type="button" data-act="modalClose" aria-label="Zavřít">' + TS.ic('x') + '</button></div>' +
      '<div class="k-m-body">' + body + '</div>' + (foot ? '<div class="k-m-foot">' + foot + '</div>' : '') + '</div>';
  };
  TS.openModal = function (html, onClose) {
    var root = TS.$('#k-modal-root');
    if (!root) { root = document.createElement('div'); root.id = 'k-modal-root'; document.body.appendChild(root); }
    if (!root.innerHTML) modalFocus = document.activeElement;
    root.innerHTML = '<div class="k-modal-back">' + html + '</div>';
    modalClose = onClose || null;
    document.body.style.overflow = 'hidden';
    var f = root.querySelector('[autofocus]') || root.querySelector('.k-m-body input,.k-m-body select,.k-m-body textarea') || root.querySelector('.k-m-head .k-icon-btn');
    if (f && !root._noFocus) try { f.focus({ preventScroll: true }); } catch (e) { }
  };
  TS.closeModal = function () {
    var root = TS.$('#k-modal-root'); if (!root || !root.innerHTML) return;
    root.innerHTML = ''; document.body.style.overflow = '';
    var cb = modalClose; modalClose = null; if (cb) cb();
    if (modalFocus && document.body.contains(modalFocus)) try { modalFocus.focus({ preventScroll: true }); } catch (e) { }
  };
  TS.modalOpen = function () { var r = TS.$('#k-modal-root'); return !!(r && r.innerHTML); };
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && TS.modalOpen()) TS.closeModal(); });
  document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('k-modal-back')) { TS.closeModal(); return; }
    var c = e.target.closest('[data-act="modalClose"]'); if (c && !TS._app) { e.preventDefault(); TS.closeModal(); }
  });

  /* ---------- stažení souboru ---------- */
  TS.download = function (name, data, mime) {
    var blob = data instanceof Blob ? data : new Blob([data], { type: mime || 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob), a = document.createElement('a');
    a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
  };

  /* ---------- e-maily ukázky ---------- */
  TS.mail = {
    add: function (D, m) {
      D.emails = D.emails || [];
      var e = Object.assign({ id: TS.uid('m'), at: Date.now(), read: false }, m);
      D.emails.unshift(e); if (D.emails.length > 150) D.emails.length = 150;
      return e;
    },
    /* obálka e-mailu: hlavička se značkou, obsah, patička s údaji firmy */
    wrap: function (brand, inner, foot) {
      return '<div class="k-em"><div class="k-em-brand"><b>' + brand.name + '</b>' + (brand.sub ? '<small>' + brand.sub + '</small>' : '') + '</div>' +
        '<div class="k-em-body">' + inner + '</div>' + (foot ? '<div class="k-em-foot">' + foot + '</div>' : '') + '</div>';
    },
    table: function (rows) { return '<table class="k-em-table">' + rows.filter(Boolean).map(function (r) { return '<tr><th>' + r[0] + '</th><td>' + r[1] + '</td></tr>'; }).join('') + '</table>'; },
    btn: function (label, href, pri) { return '<a class="k-em-btn' + (pri ? ' k-pri' : '') + '" href="' + TS.esc(href) + '">' + label + '</a>'; },
    attach: function (name, note, btnHTML) { return '<div class="k-attach">' + TS.ic('file') + '<div><strong>' + TS.esc(name) + '</strong><span>' + note + '</span></div>' + (btnHTML || '') + '</div>'; }
  };

  /* ---------- kalendář a volné časy ---------- */
  TS.cal = {
    /* časy začátku, kdy se služba (a příprava po ní) vejde do otevírací doby a nekoliduje */
    free: function (o) {
      var out = [], step = Math.max(5, o.step || 15), dur = o.dur || 30, after = o.after || 0;
      (o.wins || []).forEach(function (w) {
        for (var t = w[0]; t + dur <= w[1]; t += step) {
          if (o.minStart != null && t < o.minStart) continue;
          var end = t + dur + after, ok = true;
          for (var i = 0; i < (o.busy || []).length; i++) { var b = o.busy[i]; if (t < b[1] && b[0] < end) { ok = false; break; } }
          if (ok) out.push(t);
        }
      });
      return out;
    },
    overlaps: function (a0, a1, list) { return (list || []).filter(function (b) { return a0 < b[1] && b[0] < a1; }); },
    /* mřížka kalendáře: sloupce = dny (týden) nebo zdroje (stylistky, vozy) */
    grid: function (o) {
      var from = o.from, to = o.to, hh = o.hh || 64, px = function (m) { return (m - from) / 60 * hh; };
      var H = px(to), cols = o.cols;
      var h = '<div class="k-wk-wrap' + TS.kc(o.cls) + '"><div class="k-wk' + (o.res ? ' k-res' : '') + '" style="--cols:' + cols.length + ';--hh:' + hh + 'px">';
      h += '<div class="k-wk-h k-corner"></div>' + cols.map(function (c) {
        return '<div class="k-wk-h' + (c.today ? ' k-today' : '') + '"><div class="k-d">' + c.d + '</div><div class="k-n">' + c.n + '</div>' + (c.c ? '<div class="k-c">' + c.c + '</div>' : '') + '</div>';
      }).join('');
      var ax = '<div class="k-axis" style="height:' + H + 'px">';
      for (var m = from; m < to; m += 60) ax += '<span style="top:' + px(m) + 'px">' + TS.hm(m) + '</span>';
      h += ax + '</div>';
      cols.forEach(function (c) {
        h += '<div class="k-col' + (c.closed ? ' k-closed' : '') + '" style="height:' + H + 'px"' + (!c.closed && o.emptyAct ? ' data-empty="' + TS.esc(o.emptyAct) + '" data-col="' + TS.esc(c.key) + '" data-from="' + from + '" data-hh="' + hh + '" data-step="' + (o.step || 15) + '"' : '') + '>';
        (c.bands || []).forEach(function (b) { h += '<div class="k-band" style="top:' + px(b[0]) + 'px;height:' + (px(b[1]) - px(b[0])) + 'px">' + (b[2] ? '<span>' + TS.esc(b[2]) + '</span>' : '') + '</div>'; });
        (o.blocks || []).filter(function (k) { return k.col === c.key; }).forEach(function (k) {
          var a = Math.max(k.from, from), z = Math.min(k.to, to); if (z <= a) return;
          h += '<button type="button" class="k-blk" style="top:' + px(a) + 'px;height:' + (px(z) - px(a) - 2) + 'px"' + (k.act ? ' data-act="' + k.act + '" data-arg="' + TS.esc(k.arg) + '"' : '') + '><span>' + TS.esc(k.label) + '</span>' + (k.sub ? '<span class="k-t">' + TS.esc(k.sub) + '</span>' : '') + '</button>';
        });
        var evs = (o.events || []).filter(function (e) { return e.col === c.key; }).sort(function (a, b) { return a.start - b.start; });
        /* souběžné události vedle sebe */
        var lanes = []; evs.forEach(function (e) { var l = 0; while (lanes[l] && lanes[l] > e.start) l++; lanes[l] = e.end; e._l = l; });
        evs.forEach(function (e) {
          var n = 1; evs.forEach(function (x) { if (x !== e && x.start < e.end && e.start < x.end) n = Math.max(n, Math.max(x._l, e._l) + 1); });
          var w = 100 / n, top = px(Math.max(e.start, from)), ht = Math.max(18, px(Math.min(e.end, to)) - top - 2), mini = ht < 34;
          h += '<button type="button" class="k-ev' + TS.kc(e.cls) + '" style="top:' + top + 'px;height:' + ht + 'px;left:calc(' + (e._l * w) + '% + 3px);width:calc(' + w + '% - 6px)"' +
            (e.act ? ' data-act="' + e.act + '" data-arg="' + TS.esc(e.arg) + '"' : '') + ' title="' + TS.esc(TS.hm(e.start) + '–' + TS.hm(e.end) + ' ' + (e.s || '') + ' ' + (e.w || '')) + '">' +
            (e.tag ? '<span class="k-tag">' + TS.esc(e.tag) + '</span>' : '') +
            '<span class="k-t">' + TS.hm(e.start) + (mini ? '' : '–' + TS.hm(e.end)) + (mini && e.s ? ' ' + TS.esc(e.s) : '') + '</span>' + (mini ? '' : '<span class="k-s">' + TS.esc(e.s || '') + '</span>' + (ht >= 60 && e.w ? '<span class="k-w">' + TS.esc(e.w) + '</span>' : '')) + '</button>';
        });
        if (c.now != null && c.now >= from && c.now <= to) h += '<div class="k-now" style="top:' + px(c.now) + 'px"></div>';
        h += '</div>';
      });
      return h + '</div></div>';
    },
    gcal: function (o) {
      var d = o.date.replace(/-/g, ''), f = function (m) { return d + 'T' + pad(Math.floor(m / 60)) + pad(m % 60) + '00'; };
      return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(o.title) + '&dates=' + f(o.start) + '/' + f(o.end) +
        '&ctz=Europe%2FPrague' + (o.details ? '&details=' + encodeURIComponent(o.details) : '') + (o.location ? '&location=' + encodeURIComponent(o.location) : '');
    },
    ics: function (o) {
      var d = o.date.replace(/-/g, ''), f = function (m) { return d + 'T' + pad(Math.floor(m / 60)) + pad(m % 60) + '00'; };
      var e = function (s) { return String(s || '').replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n'); };
      var now = new Date(), st = now.getUTCFullYear() + pad(now.getUTCMonth() + 1) + pad(now.getUTCDate()) + 'T' + pad(now.getUTCHours()) + pad(now.getUTCMinutes()) + '00Z';
      return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//T&S ukazka//CS', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT', 'UID:' + (o.uid || TS.uid('ev')) + '@ukazka.tasstudio.eu', 'DTSTAMP:' + st,
        'DTSTART;TZID=Europe/Prague:' + f(o.start), 'DTEND;TZID=Europe/Prague:' + f(o.end), 'SUMMARY:' + e(o.title), o.location ? 'LOCATION:' + e(o.location) : '', o.details ? 'DESCRIPTION:' + e(o.details) : '',
        'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:' + e(o.title), 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR'].filter(Boolean).join('\r\n');
    }
  };

  /* ---------- PDF (jsPDF a písma ze složky assets, načtou se až při prvním stažení) ---------- */
  var pdfCache = {};
  TS.pdf = {
    base: '../assets/',
    load: function (fonts) {
      var key = fonts.map(function (f) { return f[0]; }).join('|');
      if (pdfCache[key]) return pdfCache[key];
      var b64 = function (buf) { var u = new Uint8Array(buf), s = ''; for (var i = 0; i < u.length; i += 32768) s += String.fromCharCode.apply(null, u.subarray(i, i + 32768)); return btoa(s); };
      var lib = window.jspdf ? Promise.resolve() : new Promise(function (ok, no) {
        var s = document.createElement('script'); s.src = TS.pdf.base + 'js/jspdf.umd.min.js?v=4.2.1'; s.onload = ok; s.onerror = function () { no(new Error('jsPDF')); }; document.head.appendChild(s);
      });
      var fs = Promise.all(fonts.map(function (f) { return fetch(TS.pdf.base + 'fonts/pdf/' + f[0] + '?v=1').then(function (r) { if (!r.ok) throw new Error('font'); return r.arrayBuffer(); }).then(b64); }));
      var p = pdfCache[key] = Promise.all([lib, fs]).then(function (x) {
        return function () {
          var doc = new window.jspdf.jsPDF({ unit: 'pt', format: 'a4', compress: true, putOnlyUsedFonts: true });
          fonts.forEach(function (f, i) { doc.addFileToVFS(f[0], x[1][i]); doc.addFont(f[0], f[1], f[2]); });
          if (doc.setLanguage) doc.setLanguage('cs-CZ');
          return doc;
        };
      });
      p.catch(function () { delete pdfCache[key]; });
      return p;
    },
    /* tlačítko během přípravy PDF ukazuje „Připravuji PDF…“ */
    run: function (el, job) {
      var old = el ? el.innerHTML : '';
      if (el) { el.disabled = true; el.textContent = 'Připravuji PDF…'; }
      return Promise.resolve().then(job).catch(function () {
        TS.toast('PDF se nepodařilo připravit. Zkontrolujte připojení a zkuste to znovu.', { bad: true });
      }).then(function () { if (el && el.isConnected) { el.disabled = false; el.innerHTML = old; } });
    }
  };

  /* ---------- tisk dokumentu (faktura, doklad) ---------- */
  TS.print = function (html) {
    var r = TS.$('#k-print'); if (!r) { r = document.createElement('div'); r.id = 'k-print'; document.body.appendChild(r); }
    r.innerHTML = html; try { window.print(); } catch (e) { }
  };

  /* ==========================================================================
     Administrace: přihlášení, menu modulů, překreslení, vazby formulářů, koncepty
     ========================================================================== */
  TS.admin = function (cfg) {
    var D = cfg.D, root = TS.$(cfg.root || '#app');
    var app = TS._app = { cfg: cfg, D: D, ui: { drafts: {}, mail: { box: null, open: null }, modOpen: null }, actions: {}, saves: {} };
    var mods = cfg.modules;
    function modById(id) { return mods.find(function (m) { return m.id === id; }) || null; }
    function route() {
      var h = decodeURIComponent((location.hash || '').slice(1)), p = h.split('/');
      return { mod: p[0] || '', arg: p.slice(1).join('/') };
    }
    app.loggedIn = function () { return TS.flag('login') === '1'; };
    app.go = function (id, arg) {
      var h = '#' + id + (arg ? '/' + arg : '');
      if (location.hash !== h) history.replaceState(null, '', h);
      app.render(); window.scrollTo(0, 0);
    };

    /* --- části obrazovky --- */
    app.head = function (eyebrow, title, actions) {
      return '<header class="k-head"><div><p class="k-eyebrow">' + eyebrow + '</p><h1>' + title + '</h1></div>' + (actions ? '<div class="k-head-act">' + actions + '</div>' : '') + '</header>';
    };
    app.draft = function (key, src) { if (!app.ui.drafts[key]) app.ui.drafts[key] = TS.clone(src()); return app.ui.drafts[key]; };
    app.dirty = function (key, src) { var d = app.ui.drafts[key]; return !!d && JSON.stringify(d) !== JSON.stringify(src()); };
    app.savebar = function (key, src, text) {
      if (!app.dirty(key, src)) return '';
      return '<div class="k-savebar" role="region" aria-label="Neuložené změny"><span>' + (text || 'Máte neuložené změny.') + '</span><span style="display:flex;gap:8px">' +
        '<button class="k-btn k-ghost k-sm" type="button" data-act="draftDiscard" data-arg="' + key + '">Zahodit</button>' +
        '<button class="k-btn k-pri k-sm" type="button" data-act="draftSave" data-arg="' + key + '">Uložit změny</button></span></div>';
    };

    function railHTML(cur) {
      var badge = function (m) { var n = m.badge ? m.badge(app) : 0; return n ? '<span class="k-count">' + n + '</span>' : ''; };
      var navs = mods.map(function (m) {
        return '<a class="k-nav" href="#' + m.id + '"' + (m.id === cur ? ' aria-current="page"' : '') + '>' + TS.ic(m.icon) + '<span>' + m.label + '</span>' + badge(m) + '</a>';
      }).join('');
      var tabs = mods.filter(function (m) { return m.tab; }).slice(0, 4);
      var tabHTML = tabs.map(function (m) {
        return '<a class="k-tab" href="#' + m.id + '"' + (m.id === cur ? ' aria-current="page"' : '') + '>' + TS.ic(m.icon) + '<span>' + (m.short || m.label) + '</span>' + badge(m) + '</a>';
      }).join('') + (mods.length > tabs.length ? '<button class="k-tab" type="button" data-act="moreMods"' + (tabs.some(function (m) { return m.id === cur; }) ? '' : ' aria-current="page"') + '>' + TS.ic('more') + '<span>Další</span></button>' : '');
      return {
        rail: '<nav class="k-rail" aria-label="Administrace"><div class="k-rail-brand"><b>' + cfg.brand.name + '</b><small>Administrace</small></div>' + navs +
          '<div class="k-rail-foot"><span>Účet: ' + TS.esc(cfg.user.email) + '</span><button class="k-link" type="button" data-act="logout">Odhlásit se</button><button class="k-link" type="button" data-act="resetAsk">Obnovit ukázková data</button></div></nav>',
        tabbar: '<nav class="k-tabbar" style="--n:' + (tabs.length + (mods.length > tabs.length ? 1 : 0)) + '" aria-label="Administrace">' + tabHTML + '</nav>'
      };
    }
    function loginHTML() {
      return '<div class="k-login"><form class="k-login-card" data-form="login" novalidate><p class="k-eyebrow">' + cfg.brand.name + '</p><h1>Přihlášení do administrace</h1>' +
        '<p class="k-muted">Ukázkový účet je předvyplněný. V ostrém provozu má každý člen týmu vlastní přístup.</p>' +
        '<div class="k-field"><label for="k-l-mail">E-mail</label><input class="k-inp" id="k-l-mail" type="email" value="' + TS.esc(cfg.user.email) + '" autocomplete="username"></div>' +
        '<div class="k-field"><label for="k-l-pass">Heslo</label><input class="k-inp" id="k-l-pass" type="password" value="ukazka-2026" autocomplete="current-password"></div>' +
        '<button class="k-btn k-pri" type="submit">Přihlásit se</button></form></div>';
    }
    function mailHTML() {
      var boxes = cfg.mail.boxes, ui = app.ui.mail;
      if (!ui.box || !boxes.some(function (b) { return b.id === ui.box; })) ui.box = boxes[0].id;
      var list = (D.emails || []).filter(function (e) { return e.box === ui.box; }).sort(function (a, b) { return b.at - a.at; });
      var open = ui.open ? (D.emails || []).find(function (e) { return e.id === ui.open; }) : null;
      if (open && open.box !== ui.box) open = null;
      if (open && !open.read) { open.read = true; TS.save(); }
      var tabs = boxes.map(function (b) {
        var n = (D.emails || []).filter(function (e) { return e.box === b.id; }).length;
        return '<button type="button" role="tab" data-act="mailBox" data-arg="' + b.id + '" aria-selected="' + (b.id === ui.box) + '"><span>' + b.label + '</span><span class="k-addr">' + TS.esc(b.addr || '') + '</span><span class="k-count" style="background:var(--k-surface-2);color:var(--k-ink-2)">' + n + '</span></button>';
      }).join('');
      var items = list.length ? list.map(function (e) {
        return '<li><button type="button" class="k-mail-item" data-act="mailOpen" data-arg="' + e.id + '" aria-current="' + (open && open.id === e.id) + '"><div class="k-mi-top"><span>' + (e.read ? '' : '<i class="k-mi-new"></i>') + 'Komu: ' + TS.esc(e.to) + '</span><span>' + TS.fmtStamp(e.at) + '</span></div><div class="k-mi-subj">' + TS.esc(e.subject) + '</div></button></li>';
      }).join('') : '<li class="k-empty" style="margin:14px">Zatím tu nic není. ' + (cfg.mail.emptyHint || '') + '</li>';
      var reader = open ? '<button class="k-btn k-sec k-sm k-mail-back" type="button" data-act="mailBack">' + TS.ic('back') + 'Zpět na seznam</button>' +
        '<div class="k-em-meta"><div class="k-em-subject">' + TS.esc(open.subject) + '</div><div><b>Od:</b> ' + TS.esc(open.from || cfg.mail.from) + '</div><div><b>Komu:</b> ' + TS.esc(open.to) + '</div><div>' + TS.fmtStamp(open.at) + '</div></div>' + open.html
        : '<div class="k-empty">Vyberte e-mail ze seznamu.</div>';
      return '<div class="k-mail"><div class="k-mail-head"><div><h1>' + (cfg.mail.title || 'Odeslané e-maily') + '</h1><p>' + cfg.mail.intro + '</p></div></div>' +
        '<div class="k-mail-tabs" role="tablist">' + tabs + '</div>' +
        '<div class="k-mail-grid' + (open ? ' k-open' : '') + '"><ul class="k-mail-list">' + items + '</ul><div class="k-mail-read">' + reader + '</div></div></div>';
    }

    /* --- překreslení se zachováním fokusu --- */
    app.render = function () {
      var r = route(), act = document.activeElement, fid = act && act.id, sel = null;
      try { if (act && act.selectionStart != null) sel = [act.selectionStart, act.selectionEnd]; } catch (e) { }
      document.body.classList.toggle('k-admin-page', true);
      TS.$$('[data-seg-mail]').forEach(function (a) { if (r.mod === 'emaily') a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
      TS.$$('[data-seg-admin]').forEach(function (a) { if (r.mod !== 'emaily') a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
      if (r.mod === 'emaily') { root.innerHTML = mailHTML(); }
      else if (!app.loggedIn()) { root.innerHTML = loginHTML(); }
      else {
        var m = modById(r.mod) || mods[0];
        if (m.onArg && r.arg && app.ui._argDone !== location.hash) { app.ui._argDone = location.hash; setTimeout(function () { m.onArg(app, r.arg); }, 0); }
        var parts = railHTML(m.id), body;
        try { body = m.render(app); } catch (e) { body = '<div class="k-alert k-bad">Tuto část se nepodařilo zobrazit.</div>'; if (window.console) console.error(e); }
        root.innerHTML = '<div class="k-admin">' + parts.rail + '<main class="k-work" id="k-work">' + body + '</main></div>' + parts.tabbar;
      }
      if (app.ui.modal) TS.openModal(app.ui.modal());
      TS.badge();
      if (fid) { var el = document.getElementById(fid); if (el) { try { el.focus({ preventScroll: true }); if (sel && el.setSelectionRange) el.setSelectionRange(sel[0], sel[1]); } catch (e) { } } }
      if (cfg.afterRender) cfg.afterRender(app);
    };
    /* modální okno řízené stavem: app.modal(fn) – fn vrací HTML, při překreslení se obnoví */
    app.modal = function (fn) { app.ui.modal = fn; if (fn) { TS.openModal(fn(), function () { app.ui.modal = null; }); } else TS.closeModal(); };
    app.refreshModal = function () { if (app.ui.modal && TS.modalOpen()) { var r = TS.$('#k-modal-root'); r._noFocus = true; var act = document.activeElement, fid = act && act.id; TS.openModal(app.ui.modal(), function () { app.ui.modal = null; }); r._noFocus = false; if (fid) { var el = document.getElementById(fid); if (el) try { el.focus({ preventScroll: true }); } catch (e) { } } } };
    app.commit = function (msg, opt) { TS.save(); app.render(); if (msg) TS.toast(msg, opt); };

    /* --- vestavěné akce --- */
    var A = app.actions;
    A.modalClose = function () { app.ui.modal = null; TS.closeModal(); };
    A.logout = function () { TS.flag('login', null); app.render(); };
    A.resetAsk = function () {
      app.modal(function () {
        return TS.mShell('Obnovit ukázková data?', '', '<p>Všechny změny, které jste v ukázce udělali (rezervace, úpravy obsahu, e-maily), se zahodí a ukázka začne znovu s výchozími daty.</p>',
          '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zrušit</button><button class="k-btn k-pri" type="button" data-act="resetDo">Obnovit</button>');
      });
    };
    A.resetDo = function () { TS.reset(); app.ui.drafts = {}; app.modal(null); app.render(); TS.toast('Ukázková data jsou zpátky.'); };
    A.draftDiscard = function (key) { delete app.ui.drafts[key]; app.render(); };
    A.draftSave = function (key) {
      var s = app.saves[key]; if (!s) return;
      var d = app.ui.drafts[key]; var res = s(d);
      if (res === false) { app.render(); return; }
      delete app.ui.drafts[key]; TS.save(); app.render();
      TS.toast(res && res.msg || 'Uloženo. Na webu se změna ukáže hned.', res && res.href ? { href: res.href, label: res.label || 'Zobrazit na webu' } : {});
    };
    A.moreMods = function () {
      app.modal(function () {
        return TS.mShell('Administrace', '', '<div class="k-list">' + mods.map(function (m) { return '<a class="k-nav" href="#' + m.id + '" data-act="modalClose">' + TS.ic(m.icon) + '<span>' + m.label + '</span></a>'; }).join('') +
          '<button class="k-nav" type="button" data-act="resetAsk">' + TS.ic('refresh') + '<span>Obnovit ukázková data</span></button><button class="k-nav" type="button" data-act="logout">' + TS.ic('out') + '<span>Odhlásit se</span></button></div>');
      });
    };
    A.mailBox = function (b) { app.ui.mail.box = b; app.ui.mail.open = null; app.render(); };
    A.mailOpen = function (id) { app.ui.mail.open = id; app.render(); var r = TS.$('.k-mail-read'); if (r) r.scrollTop = 0; if (window.innerWidth < 780) window.scrollTo(0, TS.$('.k-mail-grid').offsetTop - 60); };
    A.mailBack = function () { app.ui.mail.open = null; app.render(); };

    /* --- události --- */
    document.addEventListener('click', function (e) {
      var col = e.target.closest('.k-col[data-empty]');
      if (col && !e.target.closest('.k-ev,.k-blk')) {
        var r = col.getBoundingClientRect(), y = e.clientY - r.top, hh = +col.dataset.hh, step = +col.dataset.step;
        var min = +col.dataset.from + Math.floor(y / hh * 60 / step) * step;
        var fnE = A[col.dataset.empty]; if (fnE) fnE(col.dataset.col + '|' + min, col, e); return;
      }
      var el = e.target.closest('[data-act]'); if (!el || el.disabled) return;
      var fn = A[el.dataset.act]; if (!fn) return;
      if (el.tagName === 'A' && el.getAttribute('href') && el.getAttribute('href').charAt(0) === '#' && el.dataset.act === 'modalClose') { app.ui.modal = null; TS.closeModal(); return; }
      e.preventDefault(); fn(el.dataset.arg, el, e);
    });
    document.addEventListener('submit', function (e) {
      var f = e.target.dataset.form; if (!f) return; e.preventDefault();
      if (f === 'login') { TS.flag('login', '1'); app.render(); TS.toast('Přihlášeno ukázkovým účtem.'); return; }
      var fn = A['submit_' + f]; if (fn) fn(e.target, e);
    });
    function onBind(e) {
      var el = e.target, path = el.dataset && el.dataset.bind; if (!path) return;
      var v = el.type === 'checkbox' ? el.checked : el.value;
      if (el.dataset.num !== undefined) v = TS.num(v);
      var base = { ui: app.ui, dr: app.ui.drafts, D: D };
      TS.set(base, path, v);
      if (path.indexOf('D.') === 0) TS.save();
      var inModal = TS.modalOpen() && el.closest('#k-modal-root');
      var need = (e.type === 'input' && el.dataset.live !== undefined) || (e.type === 'change' && el.dataset.rr !== undefined) ||
        (path.indexOf('dr.') === 0 && !inModal && !TS.$('.k-savebar'));   /* první změna konceptu ukáže lištu Uložit */
      if (need) later(inModal ? 'modal' : 'page');
    }
    /* překreslení až po dokončení události (změna při ztrátě fokusu nesmí mazat právě opouštěný prvek) */
    var pend = null;
    function later(what) {
      if (pend === 'page') return; pend = what === 'page' ? 'page' : (pend || 'modal');
      setTimeout(function () { var w = pend; pend = null; if (w === 'page') app.render(); else app.refreshModal(); }, 0);
    }
    document.addEventListener('input', onBind);
    document.addEventListener('change', onBind);
    window.addEventListener('hashchange', function () { app.ui.mail.open = null; app.render(); window.scrollTo(0, 0); });
    window.addEventListener('beforeunload', function (e) {
      var dirty = Object.keys(app.ui.drafts).some(function (k) { var m = cfg.draftSources && cfg.draftSources[k]; return m && app.dirty(k, m); });
      if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
    TS.webLink();
    app.render();
    return app;
  };

  /* ---------- pomocníci pro HTML formulářů v administraci ---------- */
  TS.f = {
    text: function (id, bind, label, val, extra, hint) {
      return '<div class="k-field"><label for="' + id + '">' + label + '</label><input class="k-inp" id="' + id + '" value="' + TS.esc(val == null ? '' : val) + '" data-bind="' + bind + '" ' + (extra || '') + '>' + (hint ? '<p class="k-hint">' + hint + '</p>' : '') + '</div>';
    },
    area: function (id, bind, label, val, extra, hint) {
      return '<div class="k-field"><label for="' + id + '">' + label + '</label><textarea class="k-inp" id="' + id + '" data-bind="' + bind + '" ' + (extra || '') + '>' + TS.esc(val == null ? '' : val) + '</textarea>' + (hint ? '<p class="k-hint">' + hint + '</p>' : '') + '</div>';
    },
    select: function (id, bind, label, val, opts, extra, hint) {
      return '<div class="k-field"><label for="' + id + '">' + label + '</label><select class="k-inp" id="' + id + '" data-bind="' + bind + '" ' + (extra || '') + '>' + TS.f.opts(opts, val) + '</select>' + (hint ? '<p class="k-hint">' + hint + '</p>' : '') + '</div>';
    },
    opts: function (opts, val) { return opts.map(function (o) { var v = Array.isArray(o) ? o[0] : o, l = Array.isArray(o) ? o[1] : o; return '<option value="' + TS.esc(v) + '"' + (String(v) === String(val) ? ' selected' : '') + '>' + TS.esc(l) + '</option>'; }).join(''); },
    check: function (id, bind, label, val, extra) { return '<label class="k-check"><input type="checkbox" id="' + id + '" data-bind="' + bind + '"' + (val ? ' checked' : '') + ' ' + (extra || '') + '><span>' + label + '</span></label>'; },
    toggle: function (id, bind, title, text, val, extra) { return '<label class="k-toggle"><input type="checkbox" id="' + id + '" data-bind="' + bind + '"' + (val ? ' checked' : '') + ' ' + (extra || '') + '><span><strong>' + title + '</strong>' + (text ? '<br><span class="k-muted k-small">' + text + '</span>' : '') + '</span></label>'; },
    times: function (from, to, step) { var o = []; for (var m = from; m <= to; m += step) o.push([TS.hhmm(m), TS.hm(m)]); return o; }
  };
  /* posun položky v seznamu nahoru/dolů */
  TS.move = function (arr, i, dir) { var j = i + dir; if (j < 0 || j >= arr.length) return; var x = arr[i]; arr[i] = arr[j]; arr[j] = x; };
  TS.sortBtns = function (act, i, n) {
    return '<button class="k-sortbtn" type="button" data-act="' + act + '" data-arg="' + i + '|-1" aria-label="Posunout výš"' + (i === 0 ? ' disabled' : '') + '>' + TS.ic('up') + '</button>' +
      '<button class="k-sortbtn" type="button" data-act="' + act + '" data-arg="' + i + '|1" aria-label="Posunout níž"' + (i === n - 1 ? ' disabled' : '') + '>' + TS.ic('down') + '</button>';
  };
})();
