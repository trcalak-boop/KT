/* Lokna – veřejný web: obsah a volné časy se počítají z dat, která upravuje administrace ukázky */
(function () {
  'use strict';
  var L = window.LOKNA, D = TS.data('lokna', 2, L.defaults), I = D.info, esc = TS.esc;
  TS.mode('lokna-rezim'); TS.webPage(); TS.burger('#burger', '#menu');
  var page = (document.body.className.match(/p-([a-z]+)/) || [])[1] || 'index';
  var today = TS.today(), RM = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
  function $(s) { return TS.$(s); }
  function setAll(k, v, html) { TS.$$('[data-k="' + k + '"]').forEach(function (el) { if (html) el.innerHTML = v; else el.textContent = v; }); }
  function q(name) { var m = new RegExp('[?&]' + name + '=([^&#]*)').exec(location.search); return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : null; }
  function lenPref() { var v = TS.flag('len'); return v === '0' || v === '1' || v === '2' ? +v : 1; }
  function webSvcs() { return L.allSvcs(D).filter(function (s) { return s.on !== false && L.doers(D, s.id).length; }); }
  /* portrét-ilustrace stylistky (bez fotek): účes podle specializace, barvy vlasů z dat */
  var HAIRDO = { tereza: 'long', klara: 'bob', denisa: 'bun' };
  var HD = {
    long: { back: 'M96 150C92 96 118 70 150 70C182 70 208 96 204 150C206 210 222 270 238 330C214 344 190 336 184 300L116 300C110 336 86 344 62 330C78 270 94 210 96 150Z',
      front: 'M104 168C98 112 120 84 154 84C188 86 206 112 198 168C192 132 176 114 150 112C128 120 112 140 104 168Z',
      locks: 'M106 150C96 200 96 262 108 338C118 332 124 318 126 300C122 260 118 210 116 168ZM194 150C204 200 204 262 192 338C182 332 176 318 174 300C178 260 182 210 184 168Z' },
    bob: { back: 'M92 160C88 98 116 68 150 68C184 68 212 98 208 160C210 196 212 222 220 244C196 252 186 240 182 228L118 228C114 240 104 252 80 244C88 222 90 196 92 160Z',
      front: 'M98 150C96 100 120 78 150 78C180 78 204 100 202 150C178 138 122 138 98 150Z', locks: '' },
    bun: { back: 'M102 162C98 106 122 80 150 80C178 80 202 106 198 162C194 140 188 128 180 120L120 120C112 128 106 140 102 162Z',
      front: 'M104 150C102 108 126 88 152 88C178 88 198 106 196 146C184 120 166 110 140 114C124 120 110 134 104 150Z', locks: '', bun: true }
  };
  function portrait(st, mini) {
    var i = L.stIdx(D, st.id), kind = HAIRDO[st.id] || ['long', 'bob', 'bun'][i % 3], h = HD[kind], g = 'pg-' + esc(st.id) + (mini ? '-m' : '');
    /* vlasy: barevný podklad hned, realistická kresba (lokna/vlasy.js) se doplní do <image> po dokreslení */
    var ref = 'pt-' + kind + '|' + esc(st.id);
    function hair(d, part) {
      if (!d) return '';
      return '<path d="' + d + '" fill="url(#' + g + ')"/><image class="pt-hair" data-pt="' + ref + '" x="0" y="0" width="300" height="380" preserveAspectRatio="none" clip-path="url(#' + g + part + ')"/>' +
        (part === 'b' ? '<path d="' + d + '" fill="none" stroke="rgba(30,12,20,.2)" stroke-width="1.2"/>' : '');
    }
    function clip(d, part) { return d ? '<clipPath id="' + g + part + '"><path d="' + d + '"/></clipPath>' : ''; }
    return '<svg class="pt" viewBox="' + (mini ? '82 62 136 136' : '0 0 300 380') + '" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false"><defs>' +
      '<linearGradient id="' + g + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + esc(st.b) + '"/><stop offset=".5" stop-color="' + esc(st.a) + '"/><stop offset="1" stop-color="' + esc(st.b) + '"/></linearGradient>' +
      clip(h.back, 'b') + clip(h.front, 'f') + clip(h.locks, 'l') + (h.bun ? '<clipPath id="' + g + 'u"><circle cx="150" cy="74" r="27"/></clipPath>' : '') + '</defs>' +
      '<rect class="pt-bg" width="300" height="380"/>' + (mini ? '' : '<path class="pt-sun" d="M0 0h120L0 150Z"/>') +
      (h.bun ? '<circle cx="150" cy="74" r="27" fill="url(#' + g + ')"/><image class="pt-hair" data-pt="' + ref + '" x="0" y="0" width="300" height="380" preserveAspectRatio="none" clip-path="url(#' + g + 'u)"/>' : '') + hair(h.back, 'b') +
      '<rect class="pt-skin2" x="132" y="206" width="36" height="96"/><path class="pt-garment" d="M28 384C38 318 96 292 150 292C204 292 262 318 272 384Z"/>' +
      '<path class="pt-line" d="M128 294L150 326L172 294"/><ellipse class="pt-skin" cx="150" cy="170" rx="46" ry="58"/>' + hair(h.front, 'f') + hair(h.locks, 'l') +
      '<circle class="pt-ear" cx="104" cy="198" r="4.5"/></svg>';
  }
  /* doplnění kresby vlasů do portrétů, které se objeví na stránce (i později vykreslené) */
  function hydratePortraits(root) {
    (root || document).querySelectorAll('image.pt-hair[data-pt]:not([href]):not([data-pt-kresli])').forEach(function (im) {
      var p = im.getAttribute('data-pt').split('|'), st = L.st(D, p[1]); if (!st) return;
      im.setAttribute('data-pt-kresli', '');   // značka „už se kreslí“ (prázdný obrázek data:, by prohlížeč hlásil jako chybu načtení)
      hairTex({ id: st.id, a: st.a, b: st.b }, p[0], function (url) { im.setAttribute('href', url); im.classList.add('on'); });
    });
  }
  function stAvatar(st, cls) { return '<span class="' + (cls || 'av') + '" aria-hidden="true">' + portrait(st, true) + '</span>'; }
  function dropcap(t) { t = String(t || ''); return /^[0-9]/.test(t) ? esc(t) : t ? '<span class="dc">' + esc(t.charAt(0)) + '</span>' + esc(t.slice(1)) : ''; }
  function lum(hex) { var m = /^#?([0-9a-f]{6})$/i.exec(hex || ''); if (!m) return 0; var n = parseInt(m[1], 16); return (0.299 * (n >> 16) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255; }

  /* ---------- realistické vlasy (kresba v lokna/vlasy.js) ----------
     Textury se kreslí ve Web Workeru, takže stránka zůstává plynulá. Každý odstín se kreslí
     jen jednou a výsledek se drží v paměti. Bez podpory workeru se kreslí postupně na hlavním vlákně. */
  var VLASY_V = 'c5933b42', HX = {}, HQ = [], HBUSY = false, DPR = Math.min(2, window.devicePixelRatio || 1), HW = null, HFALL = null;
  try { if (window.Worker && window.OffscreenCanvas && OffscreenCanvas.prototype.convertToBlob) { HW = new Worker('lokna/vlasy.js?v=' + VLASY_V); HW.onmessage = function (e) { var d = e.data; if (d.blob) done(d.key, URL.createObjectURL(d.blob)); else fallback(d.key); }; HW.onerror = function () { HW = null; Object.keys(HX).forEach(function (k) { if (!HX[k].url) fallback(k); }); }; } } catch (err) { HW = null; }
  function done(key, url) { var e = HX[key]; e.url = url; var cbs = e.cbs; e.cbs = []; cbs.forEach(function (f) { f(url); }); }
  function fallback(key) { HQ.push(key); pump(); }
  function pump() {
    if (HBUSY || !HQ.length) return;
    if (!window.LOKNA_VLASY) {
      if (HFALL) return; HFALL = document.createElement('script'); HFALL.src = 'lokna/vlasy.js?v=' + VLASY_V; HFALL.onload = pump; document.head.appendChild(HFALL); return;
    }
    HBUSY = true;
    setTimeout(function () {
      var key = HQ.shift(), e = HX[key], cv;
      try { cv = window.LOKNA_VLASY(e.s, e.mode, hairK(e.mode), function (w, h) { var c = document.createElement('canvas'); c.width = w; c.height = h; return c; }); }
      catch (err) { HBUSY = false; pump(); return; }
      var fin = function (url) { done(key, url); HBUSY = false; pump(); };
      if (cv.toBlob && window.URL) cv.toBlob(function (b) { fin(b ? URL.createObjectURL(b) : cv.toDataURL('image/jpeg', .9)); }, 'image/jpeg', .9); else fin(cv.toDataURL('image/jpeg', .9));
    }, 16);
  }
  function hairTex(s, mode, cb) {
    var key = mode + '|' + s.id + '|' + s.a + s.b, e = HX[key];
    if (e && e.url) { cb(e.url); return; }
    if (e) { e.cbs.push(cb); return; }
    HX[key] = { cbs: [cb], s: { id: s.id, a: s.a, b: s.b }, mode: mode };
    if (HW) HW.postMessage({ key: key, s: HX[key].s, mode: mode, k: hairK(mode) }); else fallback(key);
  }
  /* rozlišení kresby podle toho, jak velká bude na tomto zařízení (zbytečně velká kresba jen zdržuje) */
  function hairK(mode) {
    var vw = window.innerWidth || 1024;
    if (mode === 'head') return Math.min(3.4, Math.max(1.6, Math.min(660, Math.max(440, vw * .45)) / 400 * DPR * 1.05));
    if (mode.indexOf('pt-') === 0) return Math.min(2.6, 1.4 * DPR);
    return Math.min(1.3, Math.max(.5, Math.min(520, Math.max(300, vw * .36)) * DPR / 900 * 1.1));
  }
  function hairUrl(s, mode) { var e = HX[mode + '|' + s.id + '|' + s.a + s.b]; return e && e.url; }
  if ('MutationObserver' in window) { var ptTimer = 0; new MutationObserver(function () { clearTimeout(ptTimer); ptTimer = setTimeout(function () { hydratePortraits(); }, 30); }).observe(document.body, { childList: true, subtree: true }); }
  setTimeout(function () { hydratePortraits(); }, 0);
  /* políčko odstínu: hned barevný přechod, po dokreslení textura vlasů */
  function swatch(el, s, pos) {
    if (!el || !s) return;
    var u = hairUrl(s, 'lock'), base = grad(s, 170);
    el.style.background = u ? 'url(' + u + ') ' + (pos || '50% 24%') + '/cover no-repeat,' + base : base;
    if (!u) { el.dataset.hs = s.id; hairTex(s, 'lock', function (url) { if (el.dataset.hs === s.id) el.style.background = 'url(' + url + ') ' + (pos || '50% 24%') + '/cover no-repeat,' + base; }); }
    else el.dataset.hs = s.id;
  }
  function hydrateBands(root) {
    TS.$$((root || '') + ' .b-tex').forEach(function (t) {
      var s = L.shade(D, t.dataset.h); if (!s || t.classList.contains('on')) return;
      hairTex(s, 'lock', function (url) { t.style.backgroundImage = 'url(' + url + ')'; t.classList.add('on'); });
    });
  }
  function bandHTML(s, tag, attrs) {
    return '<' + tag + ' class="band' + (lum(s.a) < .74 ? ' lt' : '') + '" style="background:' + grad(s, 180) + '" ' + attrs + '><i class="b-tex" data-h="' + esc(s.id) + '"></i><span class="b-c">' + esc(s.c) + '</span><span class="b-n">' + esc(s.n) + '</span></' + tag + '>';
  }
  var NUMW = ['', 'Jeden odstín', 'Dva odstíny', 'Tři odstíny', 'Čtyři odstíny', 'Pět odstínů', 'Šest odstínů', 'Sedm odstínů', 'Osm odstínů', 'Devět odstínů', 'Deset odstínů', 'Jedenáct odstínů', 'Dvanáct odstínů'];
  /* tabulka cen ve třech sloupcích délek (úvod i ceník); zvolená délka je zvýrazněná */
  function lenHead(l, first, last) {
    return '<thead><tr><th scope="col">' + first + '</th>' + L.LEN.map(function (n, i) { return '<th scope="col" class="lc' + (i === l ? ' on' : '') + '"><button type="button" data-l="' + i + '" aria-pressed="' + (i === l) + '">' + n + '</button></th>'; }).join('') + (last ? '<th scope="col"><span class="k-sr">' + last + '</span></th>' : '') + '</tr></thead>';
  }
  function priceCells(s, l, flash) {
    if (L.free(s)) return '<td class="pc flat free" colspan="3">zdarma<small>pro všechny délky</small></td>';
    if (L.flat(s)) return '<td class="pc flat" colspan="3">' + L.priceTxt(s.p[0]) + '<small>pro všechny délky</small></td>';
    return s.p.map(function (p, i) { return '<td class="pc' + (i === l ? ' on' + (flash ? ' flash' : '') : '') + '"><span class="pl" aria-hidden="true">' + L.LEN[i] + '</span>' + L.priceTxt(p) + '</td>'; }).join('');
  }
  function relDay(d) { var dd = TS.diffDays(today, d); return dd === 0 ? 'dnes' : dd === 1 ? 'zítra' : TS.fmtShort(d); }

  /* ---------- společné části: kontakty, otevírací doba, patička ---------- */
  var wdToday = TS.wd(today);
  function hoursHTML() {
    return L.hourGroups(D).map(function (g) {
      var t = g.days.indexOf(wdToday) >= 0 ? ' class="today"' : '';
      return '<dt' + t + '>' + esc(g.label) + '</dt><dd' + t + '>' + esc(g.text) + '</dd>';
    }).join('');
  }
  setAll('addrShort', I.street + ', ' + I.city);
  setAll('addrFull', I.street + ', ' + I.zip + ' ' + I.city);
  setAll('transport', I.transport);
  setAll('mastWhere', 'Hair studio · ' + I.street + ' · ' + I.district);
  setAll('phone', I.phone); setAll('email', I.email); setAll('instagram', I.instagram);
  setAll('hoursShort', L.hoursShort(D)); setAll('hoursShortLower', L.hoursShort(D).toLowerCase());
  setAll('hoursList', hoursHTML(), true);
  setAll('storno', D.rules.storno); setAll('vouchers', I.vouchers);
  setAll('footAddr', I.full + ' · ' + I.street + ', ' + I.zip + ' ' + I.city);
  setAll('footContact', I.phone + ' · ' + I.email + ' · ' + I.instagram);
  setAll('footHours', hoursHTML(), true);
  setAll('footCo', '© 2026 ' + I.full + ' · ' + D.billing.name + ', IČO ' + D.billing.ico);

  function openState() {
    var w = L.salonWin(D, today), now = TS.nowMin();
    if (w && now >= w[0] && now < w[1]) return { open: true, text: 'Právě otevřeno · do ' + TS.hm(w[1]) };
    if (w && now < w[0]) return { open: false, text: 'Dnes otevíráme v ' + TS.hm(w[0]) };
    for (var i = 1; i < 21; i++) { var d = TS.addDays(today, i), x = L.salonWin(D, d); if (x) return { open: false, text: 'Zavřeno · otevíráme ' + (i === 1 ? 'zítra' : ['v neděli', 'v pondělí', 'v úterý', 've středu', 've čtvrtek', 'v pátek', 'v sobotu'][TS.wd(d)]) + ' v ' + TS.hm(x[0]) }; }
    return { open: false, text: 'Zavřeno' };
  }

  function shadesOn() { return D.shades.filter(function (s) { return s.on !== false; }); }
  function grad(s, deg) { return 'linear-gradient(' + (deg || 135) + 'deg,' + s.a + ',' + s.b + ')'; }
  var artTok = 0, CV = null;
  function paintShade(s) {
    if ($('#hairArt')) {
      if (CV === null) CV = coverCtl() || false;
      var tok = ++artTok;
      hairTex(s, 'head', function (url) {
        if (tok !== artTok) return;
        var im = new Image(); im.onload = function () { if (tok === artTok && CV) CV.set(im, s); }; im.src = url;
        /* další odstín v pořadí nakreslíme dopředu, ať je prolnutí plynulé */
        var all = shadesOn(), nx = all[(all.indexOf(s) + 1) % all.length]; if (nx && nx !== s) setTimeout(function () { hairTex(nx, 'head', function () { }); }, 1200);
      });
    }
    swatch($('#shadeDot'), s);
    var hb = $('.cv-hairbase'); if (hb) hb.style.fill = s.b;
    var nm = $('#shadeName'); if (nm) nm.textContent = s.c + ' ' + s.n;
  }
  /* ---------- obálka: vlasy se vlní podle polohy a pohybu myši ----------
     Vlasy z obálky se kreslí do canvasu nad ilustrací po vodorovných proužcích. Každý proužek se
     posune podle „vlny“ od temene: u hlavy se vlasy skoro nehýbou, konečky reagují víc a se zpožděním.
     Vlasy se odklánějí od kurzoru, rychlý pohyb myši je rozhoupe, při klidu jen lehce dýchají.
     Na dotykových zařízeních je rozhoupe posouvání stránky. Při omezeném pohybu zůstanou v klidu. */
  function coverCtl() {
    var fig = $('.cover-art'), svg = fig && fig.querySelector('svg'); if (!fig || !svg || !window.Path2D) return null;
    var cv = document.createElement('canvas'), g = cv.getContext && cv.getContext('2d'); if (!g) return null;
    cv.className = 'cv-live'; cv.setAttribute('aria-hidden', 'true'); svg.parentNode.insertBefore(cv, svg.nextSibling);
    var CLIP = new Path2D(svg.querySelector('#cvClip path').getAttribute('d'));
    var LX = 30, LY = 30, LW = 240, LH = 380, RS = 2;          // vrstva vlasů v souřadnicích SVG, výška proužku
    var W = 0, H = 0, sc = 1, ox = 0, oy = 0, dpr = Math.min(2, window.devicePixelRatio || 1);
    var cur = null, old = null, fadeT = 0, clipCol = '#B03F63', running = false, visible = true;
    function fit() {
      var r = fig.getBoundingClientRect(); W = r.width; H = r.height; if (!W || !H) return;
      cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
      sc = Math.max(W / 420, H / 400); ox = (W - 420 * sc) / 2 + 60 * sc; oy = H - 400 * sc;
      [cur, old].forEach(function (l) { if (l) build(l); });
      var cc = svg.querySelector('.cv-clip'); if (cc) clipCol = getComputedStyle(cc).fill || clipCol;
      if (!running) frame(performance.now());
    }
    function build(l) {
      var k = Math.min(3.2, sc * dpr), c = l.c || document.createElement('canvas'); c.width = Math.round(LW * k); c.height = Math.round(LH * k);
      var x = c.getContext('2d'); x.setTransform(k, 0, 0, k, -LX * k, -LY * k); x.save(); x.clip(CLIP);
      x.fillStyle = l.s.b; x.fillRect(LX, LY, LW, LH); x.drawImage(l.img, 40, 30, 220, 380);
      x.lineWidth = 10; x.strokeStyle = 'rgba(30,12,20,.28)'; x.stroke(CLIP); x.restore(); l.c = c; l.k = k;
    }
    /* fyzika: D = náklon (pružina k cíli), historie D pro zpoždění konečků, E = rozhoupání */
    var D = 0, V = 0, E = 0, HI = [], mx = null, my = 0, lastX = null, lastY = window.scrollY, t0 = performance.now(), prev = t0;
    function sample(t) { for (var i = HI.length - 1; i > 0; i--) if (HI[i - 1][0] <= t) { var a = HI[i - 1], b = HI[i], q = (t - a[0]) / ((b[0] - a[0]) || 1); return a[1] + (b[1] - a[1]) * q; } return HI.length ? HI[0][1] : 0; }
    function off(y, now) {
      if (RM) return 0;
      var w = Math.pow(Math.min(1, Math.max(0, (y - 96) / 300)), 1.35), ts = now / 1000;
      return sample(now - w * 420) * w + (.6 + E * 5) * Math.sin(y * .05 - ts * 3.3) * w + Math.sin(ts * .9 + y * .012) * .9 * w;
    }
    function frame(now) {
      var dt = Math.min(.05, (now - prev) / 1000); prev = now;
      if (!RM) {
        var r = fig.getBoundingClientRect(), tgt = 0;
        if (mx !== null) { var nx = (mx - (r.left + r.width / 2)) / (window.innerWidth * .5), near = 1 - Math.min(1, Math.abs(my - (r.top + r.height / 2)) / (window.innerHeight * 1.2)) * .5; tgt = -Math.max(-1, Math.min(1, nx)) * 9 * near; }
        V += ((tgt - D) * 26 - V * 5.2) * dt; D += V * dt; E *= Math.exp(-dt * 1.5);
        HI.push([now, D]); while (HI.length > 2 && HI[1][0] < now - 700) HI.shift();
      }
      g.setTransform(dpr, 0, 0, dpr, 0, 0); g.clearRect(0, 0, W, H);
      var fa = old ? Math.min(1, (now - fadeT) / 700) : 1; if (old && fa >= 1) old = null;
      [[old, 1], [cur, fa]].forEach(function (p) {
        var l = p[0]; if (!l || !l.c) return; g.globalAlpha = p[1];
        for (var y = LY; y < LY + LH; y += RS) g.drawImage(l.c, 0, (y - LY) * l.k, l.c.width, RS * l.k, ox + (LX + off(y + RS / 2, now)) * sc, oy + y * sc, LW * sc, RS * sc + .6);
      });
      g.globalAlpha = 1;                                   // sponka jde s pramenem, na kterém sedí
      g.save(); g.translate(ox + (194 + off(102, now)) * sc, oy + 102 * sc); g.rotate(-24 * Math.PI / 180); g.fillStyle = clipCol; g.fillRect(-18 * sc, -4 * sc, 36 * sc, 9 * sc); g.restore();
      running = !RM && visible && !document.hidden && !!cur;
      if (running) requestAnimationFrame(frame);
    }
    function kick() { if (!running && cur && !RM && visible) { prev = performance.now(); running = true; requestAnimationFrame(frame); } }
    window.addEventListener('pointermove', function (e) {
      if (e.pointerType && e.pointerType !== 'mouse') return;
      if (lastX !== null) { var dx = e.clientX - lastX; V -= dx * .5; E = Math.min(1.4, E + Math.abs(dx) * .004); }
      lastX = mx = e.clientX; my = e.clientY; kick();
    }, { passive: true });
    document.addEventListener('mouseleave', function () { mx = null; lastX = null; });
    window.addEventListener('scroll', function () { var dy = window.scrollY - lastY; lastY = window.scrollY; V += dy * .25; E = Math.min(1.2, E + Math.abs(dy) * .002); kick(); }, { passive: true });
    if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { visible = en[0].isIntersecting; kick(); }).observe(fig);
    document.addEventListener('visibilitychange', kick);
    if ('ResizeObserver' in window) new ResizeObserver(fit).observe(fig); else window.addEventListener('resize', fit);
    fit();
    return {
      set: function (img, s) {
        var l = { img: img, s: s }; if (!W) fit(); build(l);
        if (cur) { old = cur; fadeT = performance.now(); }
        cur = l; fig.classList.add('live');
        if (RM || !running) frame(performance.now());
        if (RM && old) setTimeout(function () { frame(performance.now() + 800); }, 720);
        kick();
      }
    };
  }
  function lengthCard(segSel, onPick) {
    var seg = $(segSel); if (!seg) return;
    seg.innerHTML = L.LEN.map(function (l, i) { return '<button type="button" data-l="' + i + '" aria-pressed="false">' + l + '</button>'; }).join('');
    seg.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) onPick(+b.dataset.l, true); });
  }
  function markLen(segSel, l) { TS.$$(segSel + ' button').forEach(function (b) { b.setAttribute('aria-pressed', String(+b.dataset.l === l)); }); }

  /* =================================================================== úvod */
  if (page === 'index') {
    var os = openState(), on = $('#openNow');
    if (on) { on.hidden = false; on.className = 'open-now' + (os.open ? '' : ' closed'); on.innerHTML = '<i></i>' + esc(os.text); }
    var sh = shadesOn(), si = Math.max(0, sh.findIndex(function (s) { return s.id === '7.43'; }));
    function showHero() { if (!sh.length) return; var s = sh[si % sh.length]; paintShade(s); var tag = $('#shadeTag'); if (tag) tag.href = 'lokna-odstiny.html?odstin=' + encodeURIComponent(s.id); }
    showHero();
    if (!RM && sh.length > 1) setInterval(function () { if (!document.hidden) { si = (si + 1) % sh.length; showHero(); } }, 6000);

    /* nejbližší volné termíny */
    var qs = $('#q-svc'), qLen = lenPref();
    qs.innerHTML = D.cats.map(function (c) {
      var its = c.items.filter(function (s) { return s.on !== false && L.doers(D, s.id).length; });
      return its.length ? '<optgroup label="' + esc(c.n) + '">' + its.map(function (s) { return '<option value="' + esc(s.id) + '">' + esc(s.n) + '</option>'; }).join('') + '</optgroup>' : '';
    }).join('');
    if (L.svc(D, 'damsky')) qs.value = 'damsky';
    lengthCard('#q-len', function (l) { qLen = l; TS.flag('len', String(l)); renderQuick(); if ($('#priceTeaser').innerHTML) renderTeaser(); });
    qs.addEventListener('change', renderQuick);
    function renderQuick() {
      markLen('#q-len', qLen);
      var s = L.svc(D, qs.value), flat = s && L.flat(s);
      $('#q-len').closest('div').style.display = flat ? 'none' : '';
      var list = s ? L.nextFree(D, s.id, qLen, [], 'any', 4, 2) : [], c = L.calc(D, qs.value, qLen, []);
      $('#nextList').innerHTML = list.length ? list.map(function (x) {
        var st = L.st(D, x.st);
        return '<li><a class="next-slot" href="lokna-rezervace.html?sluzba=' + encodeURIComponent(s.id) + '&delka=' + qLen + '&stylistka=any&prirazena=' + x.st + '&datum=' + x.date + '&cas=' + x.t + '">' +
          '<span class="d">' + esc(TS.diffDays(today, x.date) < 2 ? relDay(x.date) : TS.WDS[TS.wd(x.date)]) + '<br>' + TS.fmtDM(x.date) + '</span><span class="t">' + TS.hm(x.t) + '</span>' +
          '<span class="w">' + stAvatar(st) + esc(st.n.split(' ')[0]) + ' ' + ARROW + '</span></a></li>';
      }).join('') : '<li class="quick-note">V nejbližších týdnech je na tuto službu plno. Zavolejte nám, zkusíme něco najít.</li>';
      $('#quickNote').textContent = s ? s.n + (flat ? '' : ', ' + L.LENS[qLen] + ' vlasy') + ': ' + L.durTxt(c.dur) + ' · ' + L.priceTxt(c.price) + '. Počítáno z aktuálního volna stylistek.' : '';
    }
    renderQuick();

    /* titulky na obálce a obsah čísla */
    var founder = D.team.filter(function (x) { return x.web !== false; })[0];
    if (founder) {
      $('#clTalk').href = 'lokna-tym.html#' + founder.id;
      if (founder.quote) $('#clQuote').textContent = '„' + founder.quote + '“';
      $('#clQuoteBy').textContent = founder.n + ', ' + founder.role.split('·')[0].trim().toLowerCase();
    }
    var dsv = L.svc(D, 'damsky') || webSvcs()[0];
    if (dsv) $('#clPrice').textContent = dsv.n + ' od ' + TS.money(Math.min.apply(null, dsv.p));
    if (sh.length) $('#clShades').textContent = (NUMW[sh.length] || sh.length + ' odstínů') + ', ' + (sh.length === 1 ? 'který' : 'které') + ' letos milujeme';
    $('#tocSvc').textContent = D.cats.map(function (c) { return c.n.toLowerCase(); }).join(', ');
    $('#tocTeam').textContent = D.team.filter(function (x) { return x.web !== false; }).map(function (x) { return x.n.split(' ')[0]; }).join(', ');
    if (dsv) {
      var nx1 = L.nextFree(D, dsv.id, qLen, [], 'any', 1, 1)[0];
      if (nx1) {
        var nst = L.st(D, nx1.st);
        $('#clSlotT').textContent = TS.cap(TS.diffDays(today, nx1.date) < 2 ? relDay(nx1.date) : TS.WDS[TS.wd(nx1.date)]) + ' ' + TS.fmtDM(nx1.date) + ([12, 13, 14].indexOf(Math.floor(nx1.t / 60)) >= 0 || nx1.t >= 1200 ? ' ve ' : ' v ') + TS.hm(nx1.t) + ' u\u00a0' + nst.gen;
        $('#clSlotS').textContent = dsv.n + ', ' + L.LENS[qLen] + ' vlasy · ' + L.priceTxt(L.calc(D, dsv.id, qLen, []).price);
        $('#clSlot').href = 'lokna-rezervace.html?sluzba=' + encodeURIComponent(dsv.id) + '&delka=' + qLen + '&stylistka=any&prirazena=' + nx1.st + '&datum=' + nx1.date + '&cas=' + nx1.t;
      }
    }

    /* ceník na úvodu: vybrané služby ve třech délkách */
    var PICK = ['damsky', 'odrost', 'barva', 'balayage', 'foukana'];
    var tsv = PICK.map(function (id) { return L.svc(D, id); }).filter(function (x) { return x; });
    if (tsv.length < 3) tsv = L.allSvcs(D).filter(function (x) { return !L.free(x); }).slice(0, 5);
    function renderTeaser() {
      $('#priceTeaser').innerHTML = '<caption class="k-sr">Vybrané ceny podle délky vlasů</caption>' + lenHead(qLen, 'Služba') + '<tbody>' + tsv.map(function (s) {
        return '<tr><th scope="row">' + esc(s.n) + '</th>' + priceCells(s, qLen) + '</tr>';
      }).join('') + '</tbody>';
    }
    renderTeaser();
    $('#priceTeaser').addEventListener('click', function (e) { var b = e.target.closest('button[data-l]'); if (b) { qLen = +b.dataset.l; TS.flag('len', String(qLen)); renderTeaser(); renderQuick(); } });

    $('#miniShades').innerHTML = sh.map(function (s) {
      return bandHTML(s, 'a', 'href="lokna-odstiny.html?odstin=' + encodeURIComponent(s.id) + '"');
    }).join('');
    hydrateBands('#miniShades');
    $('#teamTeaser').innerHTML = D.team.filter(function (s) { return s.web !== false; }).map(function (st) {
      var days = L.daysTxt(D, st).map(function (g) { return g.days; }).join(', '), aw = (st.away || []).find(function (a) { return a.to >= today && a.from <= TS.addDays(today, 21); });
      return '<article class="member"><a class="m-port" href="lokna-tym.html#' + st.id + '" aria-label="Rozhovor: ' + esc(st.n) + '">' + portrait(st) + '</a><p class="role">' + esc(st.role) + '</p><h3>' + esc(st.n) + '</h3>' +
        (st.quote ? '<p class="m-q">„' + esc(st.quote) + '“</p>' : '') + '<p class="m-bio">' + esc(st.bio) + '</p>' +
        '<p class="days">V salonu: ' + esc(days) + '</p>' + (aw ? '<span class="away">' + esc(aw.note || 'Mimo salon') + ' ' + TS.fmtDM(aw.from) + '–' + TS.fmtDM(aw.to) + '</span>' : '') +
        '<a class="m-link" href="lokna-rezervace.html?stylistka=' + st.id + '">Rezervovat u ' + esc(st.gen) + ' <span aria-hidden="true">→</span></a></article>';
    }).join('');
  }

  /* =================================================================== služby a ceník */
  if (page === 'sluzby') {
    var len = lenPref();
    var konz = L.svc(D, 'konzultace');
    $('#freeConsult').innerHTML = konz ? '<span class="ic" aria-hidden="true">0&nbsp;Kč</span><div><span class="kicker">Než začneme barvit</span><h3>' + esc(konz.n) + ' <em>zdarma</em></h3><p>' + esc(TS.cap(konz.d)) + '. Trvá zhruba ' + konz.t[0] + ' minut.</p></div><a class="tlink" href="lokna-rezervace.html?sluzba=konzultace">Domluvit konzultaci <span aria-hidden="true">→</span></a>' : '';
    if (!konz) $('#freeConsult').hidden = true;
    lengthCard('#seg', setLen);
    function setLen(l, flash) {
      len = l; TS.flag('len', String(l)); markLen('#seg', l);
      var hp = $('#hairPath'); hp.setAttribute('d', L.HAIR[l]); hp.style.d = 'path("' + L.HAIR[l] + '")';
      $('#lenNote').textContent = L.LNOTE[l];
      $('#cats').innerHTML = D.cats.map(function (c) {
        return '<div class="cat" id="cat-' + c.id + '"><h3>' + esc(c.n) + ' <small>' + c.items.length + ' ' + TS.plural(c.items.length, 'služba', 'služby', 'služeb') + '</small></h3><table class="ptab ptab--full"><caption class="k-sr">Ceník: ' + esc(c.n) + '</caption>' + lenHead(l, 'Služba', 'Rezervace') + '<tbody>' + c.items.map(function (s) {
          var bookable = s.on !== false && L.doers(D, s.id).length;
          return '<tr><th scope="row"><b>' + esc(s.n) + '</b><span class="d">' + esc(s.d) + (bookable ? '' : ' · jen po telefonu') + '</span><span class="t">' + L.durTxt(s.t[l]) + (L.flat(s) ? '' : ' · ' + L.LENS[l] + ' vlasy') + '</span></th>' + priceCells(s, l, flash) +
            '<td class="go-c">' + (bookable ? '<a class="go" href="lokna-rezervace.html?sluzba=' + encodeURIComponent(s.id) + '&delka=' + l + '" aria-label="Rezervovat: ' + esc(s.n) + '">' + ARROW + '</a>' : '') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
      }).join('');
      if (flash) setTimeout(function () { TS.$$('#cats .flash').forEach(function (x) { x.classList.remove('flash'); }); }, 600);
      $('#extrasBox').innerHTML = D.extras.length ? '<h3><span aria-hidden="true">*</span> Příplatky <small>' + L.LENS[l] + ' vlasy</small></h3><ul>' + D.extras.map(function (x) {
        return '<li><div><b>' + esc(x.n) + '</b><span>' + esc(x.d) + ' · +' + x.t[l] + ' min</span></div><em>+ ' + TS.money(x.p[l]) + '</em></li>';
      }).join('') + '</ul>' : '';
      var max = Math.max.apply(null, L.allSvcs(D).map(function (s) { return s.t[l]; }));
      $('#durs').innerHTML = D.cats.map(function (c) {
        return '<div class="dur-cat">' + esc(c.n) + '</div>' + c.items.map(function (s) {
          return '<div class="dur-row"><b>' + esc(s.n) + '</b><span class="bar"><i style="width:' + Math.round(s.t[l] / max * 100) + '%"></i></span><span>' + L.durTxt(s.t[l]) + '</span></div>';
        }).join('');
      }).join('');
    }
    setLen(len, false);
    $('#cats').addEventListener('click', function (e) { var b = e.target.closest('button[data-l]'); if (b) setLen(+b.dataset.l, true); });
    if (location.hash) { var tgt = document.getElementById(location.hash.slice(1)); if (tgt) setTimeout(function () { tgt.scrollIntoView(); }, 30); }
  }

  /* =================================================================== odstíny */
  if (page === 'odstiny') {
    var SH = shadesOn(), cur = Math.max(0, SH.findIndex(function (s) { return s.id === (q('odstin') || '7.43'); }));
    var box = $('#shades');
    box.innerHTML = SH.map(function (s, i) { return bandHTML(s, 'button', 'type="button" data-i="' + i + '" aria-pressed="' + (i === cur) + '"'); }).join('');
    box.addEventListener('click', function (e) { var b = e.target.closest('.band'); if (b) setShade(+b.dataset.i); });
    function setShade(i) {
      cur = i; var s = SH[i]; if (!s) return;
      TS.$$('#shades .band').forEach(function (b) { b.setAttribute('aria-pressed', String(+b.dataset.i === i)); });
      paintShade(s);
      /* sezónní akcent webu se přebarví podle vybraného odstínu (tmavší tón, aby zůstal čitelný na světlém i tmavém papíře) */
      var acc = [s.b, s.a].filter(function (c) { var n = parseInt(String(c).replace('#', ''), 16), l = (0.299 * (n >> 16) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255; return l > .2 && l < .56; })[0];
      if (acc) document.documentElement.style.setProperty('--ac', acc); else document.documentElement.style.removeProperty('--ac');
      swatch($('#siDot'), s, '50% 26%'); $('#siName').textContent = s.c + ' ' + s.n; $('#siText').textContent = s.t;
      $('#siBtn').href = 'lokna-rezervace.html?sluzba=barva&odstin=' + encodeURIComponent(s.id);
      swatch($('#pheadShade'), s); $('#pheadShadeName').textContent = s.c + ' ' + s.n;
      if (history.replaceState) history.replaceState(null, '', '?odstin=' + encodeURIComponent(s.id));
    }
    setShade(cur);
    hydrateBands('#shades');                 // pramínky až po obálce, ať se nejdřív dokreslí velký obrázek
    var DEP = ['#1D1517', '#2B1D18', '#3B271E', '#4F3426', '#6A4630', '#86603F', '#A67E55', '#C4A070', '#DCC08F', '#EFE0BD'];
    $('#depth').innerHTML = DEP.map(function (c, i) { return '<span style="background:' + c + '">' + (i + 1) + '</span>'; }).join('');
    var TECH = {
      odrost: ['Když vám barva odrůstá a jinak jste s ní spokojená.', 'Každých 5–7 týdnů'],
      barva: ['Jednotný odstín od kořínků, pokrytí šedin, změna tónu.', 'Každých 6–8 týdnů'],
      melir: ['Rozjasnění pramínky, klasický i jemný „baby“ melír.', 'Každé 2–3 měsíce'],
      balayage: ['Přirozený ručně malovaný přechod, který odrůstá nenápadně.', '2–3× do roka'],
      tonovani: ['Oživení odstínu a lesk, korekce žlutých tónů.', 'Každých 6 týdnů']
    };
    $('#techs').innerHTML = Object.keys(TECH).map(function (id) {
      var s = L.svc(D, id); if (!s) return '';
      return '<article class="tech"><h3>' + esc(s.n) + '</h3><p>' + esc(TECH[id][0]) + '</p><dl><dt>Jak často</dt><dd>' + esc(TECH[id][1]) + '</dd><dt>Trvá</dt><dd>' + L.durTxt(s.t[0]) + '–' + L.durTxt(s.t[2]) + '</dd></dl>' +
        '<p class="price">' + L.priceRange(s) + '</p>' + (s.on !== false ? '<a href="lokna-rezervace.html?sluzba=' + id + '">Rezervovat</a>' : '') + '</article>';
    }).join('');
  }

  /* =================================================================== tým */
  if (page === 'tym') {
    var FEAT = { tereza: 'odrost', klara: 'damsky', denisa: 'foukana' };
    $('#profiles').innerHTML = D.team.filter(function (s) { return s.web !== false; }).map(function (st) {
      var feat = L.canDo(st, FEAT[st.id]) ? FEAT[st.id] : st.svcs.filter(function (x) { return x !== 'konzultace'; })[0], fs = L.svc(D, feat);
      var nx = fs ? L.nextFree(D, feat, lenPref(), [], st.id, 1, 1)[0] : null;
      var aw = (st.away || []).filter(function (a) { return a.to >= today; }).sort(function (a, b) { return a.from < b.from ? -1 : 1; })[0];
      var days = L.daysTxt(D, st);
      return '<article class="profile" id="' + st.id + '"><figure class="p-art">' + portrait(st) + '<figcaption>' + esc(st.n) + (st.since ? ' · u křesla od roku ' + st.since : '') + '</figcaption></figure>' +
        '<div class="p-text"><p class="role">Rozhovor · ' + esc(st.role) + '</p><h2>' + esc(st.n) + '</h2>' + (st.quote ? '<blockquote class="pq">„' + esc(st.quote) + '“</blockquote>' : '') + '<p class="bio">' + dropcap(st.bio + (st.more ? ' ' + st.more : '')) + '</p>' +
        (st.tags && st.tags.length ? '<div class="tags-w"><span class="kicker">Specializace</span><ul class="tags">' + st.tags.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul></div>' : '') +
        '<div class="p-meta"><div><span class="kicker">V salonu</span><dl>' + days.map(function (g) { return '<dt>' + esc(g.days) + '</dt><dd>' + esc(g.time) + '</dd>'; }).join('') + '</dl>' +
        (aw ? '<span class="p-away">' + esc(aw.note || 'Mimo salon') + ' ' + TS.fmtDM(aw.from) + (aw.to !== aw.from ? '–' + TS.fmtDM(aw.to) : '') + '</span>' : '') + '</div>' +
        '<div><span class="kicker">Nejbližší volno</span>' + (nx ? '<p class="p-next">' + esc(TS.cap(relDay(nx.date))) + (TS.diffDays(today, nx.date) < 2 ? ' ' + TS.fmtDM(nx.date) : '') + ' v ' + TS.hm(nx.t) + '<small>' + esc(fs.n.toLowerCase()) + ', ' + L.LENS[lenPref()] + ' vlasy</small></p>' : '<p class="p-next">Teď je plno<small>zkuste jinou stylistku nebo nám zavolejte</small></p>') + '</div></div>' +
        '<div class="p-cta"><a class="btn" href="lokna-rezervace.html?stylistka=' + st.id + '">Rezervovat u ' + esc(st.gen) + ' ' + ARROW + '</a>' + (nx ? '<a class="tlink" href="lokna-rezervace.html?stylistka=' + st.id + '&sluzba=' + feat + '&delka=' + lenPref() + '&datum=' + nx.date + '&cas=' + nx.t + '">Vzít nejbližší volno</a>' : '') + '</div></div></article>';
    }).join('');
    /* rozvrh na příštích 7 dní */
    var days7 = []; for (var i = 0; i < 7; i++) days7.push(TS.addDays(today, i));
    $('#roster').innerHTML = '<thead><tr><th scope="col"><span class="k-sr">Stylistka</span></th>' + days7.map(function (d) { return '<th scope="col"' + (d === today ? ' class="today"' : '') + '>' + (d === today ? 'dnes' : TS.WDS[TS.wd(d)]) + '<br>' + TS.fmtDM(d) + '</th>'; }).join('') + '</tr></thead><tbody>' +
      D.team.filter(function (s) { return s.web !== false; }).map(function (st) {
        return '<tr><th scope="row">' + esc(st.n.split(' ')[0]) + '</th>' + days7.map(function (d) {
          if (!L.salonWin(D, d)) return '<td class="off">zavřeno</td>';
          var a = L.away(st, d); if (a) return '<td class="away"><span>' + esc((a.note || 'mimo salon').toLowerCase()) + '</span></td>';
          var w = L.stWin(D, st, d); return w ? '<td><span>' + TS.hm(w[0]).replace(':00', '') + '–' + TS.hm(w[1]).replace(':00', '') + '</span></td>' : '<td class="off">volno</td>';
        }).join('') + '</tr>';
      }).join('') + '</tbody>';
  }

  /* =================================================================== kontakt */
  if (page === 'kontakt') {
    $('#contactLead').textContent = I.street + ', ' + I.zip + ' ' + I.city + ' – ' + I.district + '. ' + I.transport;
    $('#addr').innerHTML = '<b>' + esc(I.full) + '</b><span>' + esc(I.street + ', ' + I.zip + ' ' + I.city) + '</span>';
    $('#cinfo').innerHTML = [['Telefon', '<a href="tel:' + esc(I.phone.replace(/\s/g, '')) + '">' + esc(I.phone) + '</a>'], ['E-mail', '<a href="mailto:' + esc(I.email) + '">' + esc(I.email) + '</a>'], ['Instagram', esc(I.instagram)],
      ['Doprava', esc(I.transport)], ['Parkování', esc(I.parking)], ['Přístup', esc(I.access)]].filter(function (r) { return r[1]; }).map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + r[1] + '</dd>'; }).join('');
    $('#todayLine').textContent = openState().text + '.';
    var day = today, k = 0, tw = L.salonWin(D, today);
    if (tw && TS.nowMin() >= tw[1]) day = TS.addDays(today, 1);   /* po zavírací době ukážeme příští otevřený den */
    while (!L.salonWin(D, day) && k++ < 14) day = TS.addDays(day, 1);
    var who = D.team.filter(function (s) { return L.stWin(D, s, day); });
    var h3 = $('#whoToday').previousElementSibling; if (day !== today && h3) h3.innerHTML = 'Kdo je ' + esc(TS.diffDays(today, day) === 1 ? 'zítra' : ['v neděli', 'v pondělí', 'v úterý', 've středu', 've čtvrtek', 'v pátek', 'v sobotu'][TS.wd(day)]) + ' <em>v salonu</em>';
    $('#whoToday').innerHTML = who.length ? who.map(function (st) {
      var w = L.stWin(D, st, day);
      return '<li>' + stAvatar(st) + '<div><b>' + esc(st.n) + '</b><span>' + TS.hm(w[0]) + '–' + TS.hm(w[1]) + ' · ' + esc(st.role.split('·').pop().trim()) + '</span></div><a href="lokna-rezervace.html?stylistka=' + st.id + '">Rezervovat</a></li>';
    }).join('') : '<li>V nejbližších dnech je salon zavřený.</li>';
    $('#company').innerHTML = esc(D.billing.name) + ' · IČO ' + esc(D.billing.ico) + (D.billing.dic ? ' · DIČ ' + esc(D.billing.dic) : '') + '<br>' + esc(D.billing.seat) + '<br>' + esc(D.billing.register) +
      '<br><small>Ukázkový web studia T&amp;S. Salon, osoby i všechny údaje jsou smyšlené.</small>';
  }

  /* =================================================================== rezervace */
  if (page === 'rezervace') {
    var S = { step: 1, cat: null, svc: null, len: lenPref(), ex: [], shade: '', st: null, assigned: null, date: null, t: null, page: 0, form: { name: '', phone: '', email: '', note: '', first: false, gdpr: false }, errs: {}, done: null, notice: '' };
    var STEPS = ['Služba', 'Vlasy', 'Stylistka', 'Termín', 'Údaje'];
    var body = $('#bkBody');

    /* předvolby z odkazu (ceník, tým, odstíny, nejbližší termín) */
    (function () {
      var sv = q('sluzba'), dl = q('delka'), st = q('stylistka'), od = q('odstin'), dt = q('datum'), cs = q('cas'), pr = q('prirazena');
      if (dl === '0' || dl === '1' || dl === '2') S.len = +dl;
      if (sv && L.svc(D, sv) && L.svc(D, sv).on !== false) { S.svc = sv; S.cat = L.catOf(D, sv).id; }
      if (od && (od === 'poradit' || L.shade(D, od))) S.shade = od;
      if (st === 'any') S.st = 'any'; else if (st && L.st(D, st) && L.st(D, st).web !== false) S.st = st;
      if (S.st && S.svc && S.st !== 'any' && !L.canDo(L.st(D, S.st), S.svc)) S.notice = L.st(D, S.st).n.split(' ')[0] + ' tuto službu nedělá. Vyberte prosím jinou stylistku, nebo nechte výběr na nás.';
      if (S.svc && S.st && dt && cs != null) {
        var sl = L.slots(D, dt, S.svc, S.len, [], S.st).find(function (x) { return x.t === +cs; });
        if (sl) { S.date = dt; S.t = +cs; S.assigned = S.st === 'any' ? (pr && sl.sts.indexOf(pr) >= 0 ? pr : L.pickSt(D, dt, sl.sts)) : S.st; }
        else S.notice = 'Vybraný čas už mezitím někdo obsadil. Vyberte prosím jiný.';
      }
      if (S.st && !S.svc) S.cat = null;
      S.step = !S.svc ? 1 : needHair() && !(dl != null) ? 2 : !S.st ? 3 : !S.date ? 4 : 5;
      if (S.step >= 2 && !S.st && !needHair()) S.step = 3;
    })();
    function svc() { return S.svc ? L.svc(D, S.svc) : null; }
    function needHair() { var s = svc(); return !!s && (!L.flat(s) || s.color || L.extrasFor(D, s.id).length > 0); }
    function exOk() { return S.ex.filter(function (id) { return L.extrasFor(D, S.svc).some(function (x) { return x.id === id; }); }); }
    function calc() { return L.calc(D, S.svc, S.len, exOk()); }
    function maxStep() { return !S.svc ? 1 : !S.st ? 3 : !S.date || S.t == null ? 4 : 5; }

    function stepsHTML() {
      return STEPS.map(function (l, i) {
        var n = i + 1, cls = S.done ? 'is-done' : n === S.step ? 'is-cur' : n < S.step ? 'is-done' : '';
        if (n === 2 && !needHair() && S.svc && !S.done) cls = S.step > 2 ? 'is-done' : '';
        return '<li class="' + cls + '"><button type="button" ' + (cls === 'is-done' && !S.done ? 'data-a="go" data-v="' + n + '"' : 'tabindex="-1"') + (n === S.step && !S.done ? ' aria-current="step"' : '') + '><i>' + n + '</i><span>' + l + '</span></button></li>';
      }).join('');
    }
    /* krok 1: služba */
    function step1() {
      var cats = D.cats.filter(function (c) { return c.items.some(function (s) { return s.on !== false && L.doers(D, s.id).length; }); });
      if (!S.cat || !cats.some(function (c) { return c.id === S.cat; })) S.cat = (cats[0] || {}).id;
      var c = cats.find(function (x) { return x.id === S.cat; });
      var preSt = S.st && S.st !== 'any' ? L.st(D, S.st) : null;
      return '<div><h2 class="bk-title">Jakou službu si <em>přejete?</em></h2></div>' + (preSt ? '<p class="bk-sub">Rezervujete u ' + esc(preSt.gen) + '. Služby, které nedělá, jsou skryté.</p>' : '') +
        '<div class="pills bk-cats" role="group" aria-label="Druh služby">' + cats.map(function (x) { return '<button type="button" data-a="cat" data-v="' + x.id + '" aria-pressed="' + (x.id === S.cat) + '">' + esc(x.n) + '</button>'; }).join('') + '</div>' +
        '<ul class="svc-list">' + (c ? c.items.filter(function (s) { return s.on !== false && L.doers(D, s.id).length && (!preSt || L.canDo(preSt, s.id)); }).map(function (s) {
          var l = L.flat(s) ? 0 : S.len;
          return '<li><button type="button" class="svc-opt" data-a="svc" data-v="' + s.id + '" aria-pressed="' + (S.svc === s.id) + '"><b>' + esc(s.n) + '</b><span class="d">' + esc(s.d) + '</span>' +
            '<span class="p">' + L.priceTxt(s.p[l]) + '<small>' + (L.flat(s) ? '' : L.LENS[l] + ' · ') + L.durTxt(s.t[l]) + '</small></span></button></li>';
        }).join('') : '') + '</ul>' + '<p class="bk-note">Ceny jsou orientační pro ' + L.LENS[S.len] + ' vlasy, délku upřesníte v dalším kroku. <a href="lokna-sluzby.html">Celý ceník</a></p>';
    }
    /* krok 2: vlasy */
    function step2() {
      var s = svc(), exs = L.extrasFor(D, s.id), c = calc();
      var h = '<div><h2 class="bk-title">Jaké máte <em>vlasy?</em></h2></div>';
      if (!L.flat(s)) h += '<div class="bk-len"><svg viewBox="0 0 200 220" aria-hidden="true"><path d="M34 220Q40 160 100 152Q160 160 166 220Z" fill="#2E1A26" class="sil"/><rect x="88" y="120" width="24" height="40" rx="8" fill="#E8B9A2"/>' +
        '<path class="hair" id="bkHair" fill="' + (S.shade && S.shade !== 'poradit' && L.shade(D, S.shade) ? L.shade(D, S.shade).b : '#8A5A3C') + '" d="' + L.HAIR[S.len] + '"/><ellipse cx="100" cy="94" rx="32" ry="40" fill="#F0C6B0"/>' +
        '<path d="M68 84Q80 56 112 58Q130 64 134 86Q116 70 100 74Q82 72 68 84Z" fill="' + (S.shade && S.shade !== 'poradit' && L.shade(D, S.shade) ? L.shade(D, S.shade).b : '#8A5A3C') + '"/><g fill="#2E1A26"><ellipse cx="88" cy="96" rx="3" ry="2"/><ellipse cx="112" cy="96" rx="3" ry="2"/></g><path d="M92 114q8 5 16 0" stroke="#B03F63" stroke-width="2.4" fill="none" stroke-linecap="round"/></svg>' +
        '<div><span class="lbl" id="l-len">Délka vlasů</span><div class="seg" role="group" aria-labelledby="l-len">' + L.LEN.map(function (l, i) { return '<button type="button" data-a="len" data-v="' + i + '" aria-pressed="' + (S.len === i) + '">' + l + '</button>'; }).join('') + '</div><p>' + L.LNOTE[S.len] + ' · ' + esc(s.n) + ' ' + L.priceTxt(s.p[S.len]) + ', ' + L.durTxt(s.t[S.len]) + '</p></div></div>';
      if (exs.length) h += '<div><span class="lbl">Příplatky</span><div style="display:grid;gap:.6rem">' + exs.map(function (x) {
        return '<label class="opt-row"><input type="checkbox" data-a="ex" value="' + x.id + '"' + (S.ex.indexOf(x.id) >= 0 ? ' checked' : '') + '><span><b>' + esc(x.n) + '</b><span>' + esc(x.d) + ' · +' + x.t[S.len] + ' min</span></span><em>+ ' + TS.money(x.p[S.len]) + '</em></label>';
      }).join('') + '</div></div>';
      if (s.color) {
        var sh = shadesOn();
        h += '<div><span class="lbl" id="l-sh">Odstín</span><div class="bk-shades" role="group" aria-labelledby="l-sh">' +
          '<button type="button" class="bk-sw any" data-a="shade" data-v="poradit" aria-pressed="' + (S.shade === 'poradit' || !S.shade) + '"><i></i><span>Poradíme<br>na místě</span></button>' +
          sh.map(function (x) { return '<button type="button" class="bk-sw" data-a="shade" data-v="' + esc(x.id) + '" aria-pressed="' + (S.shade === x.id) + '"><i data-sw="' + esc(x.id) + '" style="background:' + grad(x) + '"></i><code>' + esc(x.c) + '</code><span>' + esc(x.n) + '</span></button>'; }).join('') +
          '</div><p class="bk-note" style="margin-top:.7rem">Odstín je jen orientační, finální tón vždy doladíme při konzultaci před barvením. <a href="lokna-odstiny.html">Prohlédnout odstíny</a></p></div>';
      }
      return h + '<div class="bk-foot"><button type="button" class="bk-back" data-a="go" data-v="1">Zpět na služby</button><button type="button" class="btn" data-a="next2">Pokračovat · ' + L.durTxt(c.dur) + ', ' + L.priceTxt(c.price) + '</button></div>';
    }
    /* krok 3: stylistka */
    function step3() {
      var s = svc(), c = calc(), ex = exOk();
      var anyNx = L.nextFree(D, s.id, S.len, ex, 'any', 1, 1)[0];
      var h = '<div><h2 class="bk-title">U koho <em>chcete</em> sedět?</h2></div><p class="bk-sub">' + esc(s.n) + ' trvá ' + L.durTxt(c.dur) + '. U každé stylistky vidíte její nejbližší volný čas.</p>' + (S.notice ? '<p class="bk-alert" role="alert">' + esc(S.notice) + '</p>' : '') + '<div class="st-opts">' +
        '<button type="button" class="st-opt" data-a="st" data-v="any" aria-pressed="' + (S.st === 'any') + '"><span class="av any" aria-hidden="true">✦</span><span><b>Je mi to jedno</b><span>přiřadíme tu, která má volno</span>' + (anyNx ? '<span class="nx">nejdřív ' + esc(relDay(anyNx.date)) + ' v ' + TS.hm(anyNx.t) + '</span>' : '') + '</span></button>';
      D.team.filter(function (x) { return x.web !== false; }).forEach(function (st) {
        var can = L.canDo(st, s.id), nx = can ? L.nextFree(D, s.id, S.len, ex, st.id, 1, 1)[0] : null, aw = (st.away || []).find(function (a) { return a.to >= today && a.from <= TS.addDays(today, 14); });
        h += '<button type="button" class="st-opt" data-a="st" data-v="' + st.id + '" aria-pressed="' + (S.st === st.id) + '"' + (can ? '' : ' disabled') + '>' + stAvatar(st) + '<span><b>' + esc(st.n) + '</b><span>' + esc(st.role) + '</span>' +
          '<span class="nx">' + (!can ? 'tuto službu nedělá' : nx ? 'nejdřív ' + esc(relDay(nx.date)) + ' v ' + TS.hm(nx.t) : 'v nejbližších týdnech plno') + (aw && can ? ' · ' + esc((aw.note || 'mimo salon').toLowerCase()) + ' ' + TS.fmtDM(aw.from) + '–' + TS.fmtDM(aw.to) : '') + '</span></span></button>';
      });
      return h + '</div><div class="bk-foot"><button type="button" class="bk-back" data-a="go" data-v="' + (needHair() ? 2 : 1) + '">Zpět</button></div>';
    }
    /* krok 4: den a čas */
    function dayList() {
      var out = [], ex = exOk(), sts = S.st === 'any' ? L.doers(D, S.svc) : [L.st(D, S.st)];
      for (var i = 0; i <= D.rules.maxDays; i++) {
        var d = TS.addDays(today, i), sw = L.salonWin(D, d); if (!sw) continue;
        if (i === 0 && TS.nowMin() + (D.rules.leadMin || 0) >= sw[1]) continue;   /* dnes už se nic nestihne */
        var works = sts.some(function (st) { return st && (L.stWin(D, st, d) || L.away(st, d)); }); if (!works) continue;
        var awayAll = sts.every(function (st) { return !L.stWin(D, st, d); });
        out.push({ d: d, n: awayAll ? 0 : L.slots(D, d, S.svc, S.len, ex, S.st).length, away: awayAll });
      }
      return out;
    }
    function step4() {
      var days = dayList(), per = window.innerWidth < 560 ? 4 : 7, ex = exOk();
      if (!S.date || !days.some(function (x) { return x.d === S.date && x.n; })) { var f = days.find(function (x) { return x.n; }); S.date = f ? f.d : null; S.t = null; }
      var di = days.findIndex(function (x) { return x.d === S.date; });
      if (S.page == null || S.page < 0) S.page = 0;
      if (di >= 0 && (di < S.page * per || di >= (S.page + 1) * per)) S.page = Math.floor(di / per);
      var pages = Math.max(1, Math.ceil(days.length / per)), vis = days.slice(S.page * per, S.page * per + per);
      var st = S.st === 'any' ? null : L.st(D, S.st);
      var h = '<div><h2 class="bk-title">Kdy se <em>uvidíme?</em></h2></div><p class="bk-sub">' + (st ? 'Volné časy ' + esc(st.gen) + '.' : 'Volné časy všech stylistek, které ' + esc(svc().n.toLowerCase()) + ' dělají.') + ' Ukazujeme jen časy, kdy je volno na celých ' + L.durTxt(calc().dur) + '.</p>' +
        (S.notice ? '<p class="bk-alert" role="alert">' + esc(S.notice) + '</p>' : '') +
        '<div><span class="lbl" id="l-day">Den</span><div class="days-nav"><button type="button" class="icon-round" data-a="page" data-v="-1" aria-label="Předchozí dny"' + (S.page <= 0 ? ' disabled' : '') + '><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg></button>' +
        '<div class="pills days" role="group" aria-labelledby="l-day">' + vis.map(function (x) {
          return '<button type="button" data-a="day" data-v="' + x.d + '" aria-pressed="' + (x.d === S.date) + '"' + (x.n ? '' : ' disabled class="full"') + ' aria-label="' + esc(TS.fmtLong(x.d)) + (x.n ? ', ' + x.n + ' volných časů' : x.away ? ', mimo salon' : ', plno') + '"><small>' + TS.WDS[TS.wd(x.d)] + '</small><b>' + TS.parse(x.d).getDate() + '.</b><small class="n">' + (x.n ? x.n + ' ' + TS.plural(x.n, 'čas', 'časy', 'časů') : x.away ? 'mimo' : 'plno') + '</small></button>';
        }).join('') + '</div><button type="button" class="icon-round" data-a="page" data-v="1" aria-label="Další dny"' + (S.page >= pages - 1 ? ' disabled' : '') + '><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg></button></div>' +
        (vis.length ? '<p class="bk-note" style="margin-top:.5rem">' + TS.cap(TS.MN[TS.parse(vis[0].d).getMonth()]) + (TS.parse(vis[vis.length - 1].d).getMonth() !== TS.parse(vis[0].d).getMonth() ? ' – ' + TS.MN[TS.parse(vis[vis.length - 1].d).getMonth()] : '') + ' ' + TS.parse(vis[0].d).getFullYear() + '</p>' : '') + '</div>';
      if (!S.date) h += '<p class="bk-alert">V nejbližších ' + D.rules.maxDays + ' dnech už není volno. Zkuste jinou stylistku, nebo nám zavolejte na ' + esc(I.phone) + '.</p>';
      else {
        var sl = L.slots(D, S.date, S.svc, S.len, ex, S.st);
        var grp = function (title, arr) {
          return arr.length ? '<div class="slot-group"><h4>' + title + '</h4><div class="pills times" role="group" aria-label="' + title + '">' + arr.map(function (x) {
            var who = S.st === 'any' ? L.st(D, L.pickSt(D, S.date, x.sts)) : null;
            return '<button type="button" data-a="time" data-v="' + x.t + '" aria-pressed="' + (S.t === x.t) + '">' + TS.hm(x.t) + (who ? '<span class="slot-st">' + esc(who.n.split(' ')[0]) + '</span>' : '') + '</button>';
          }).join('') + '</div></div>' : '';
        };
        h += '<div><span class="lbl">Čas · ' + esc(TS.cap(TS.fmtLong(S.date))) + '</span>' + grp('Dopoledne', sl.filter(function (x) { return x.t < 720; })) + grp('Odpoledne', sl.filter(function (x) { return x.t >= 720 && x.t < 1020; })) + grp('Večer', sl.filter(function (x) { return x.t >= 1020; })) + '</div>';
      }
      return h + '<div class="bk-foot"><button type="button" class="bk-back" data-a="go" data-v="3">Zpět na stylistky</button></div>';
    }
    /* krok 5: údaje a shrnutí */
    function fld(id, key, label, type, extra) {
      var e = S.errs[key];
      return '<div><label class="lbl" for="' + id + '">' + label + '</label><input id="' + id + '" type="' + type + '" value="' + esc(S.form[key]) + '" data-f="' + key + '"' + (e ? ' class="bad" aria-invalid="true" aria-describedby="' + id + '-e"' : '') + ' ' + (extra || '') + '>' + (e ? '<p class="field-err" id="' + id + '-e">' + e + '</p>' : '') + '</div>';
    }
    function recap() {
      var s = svc(), c = calc(), st = L.st(D, S.assigned || S.st), ex = L.exNames(D, { extras: exOk() });
      return '<dl class="recap"><dt>Služba</dt><dd>' + esc(s.n) + (ex.length ? ' + ' + esc(ex.join(', ').toLowerCase()) : '') + '</dd>' + (L.flat(s) ? '' : '<dt>Vlasy</dt><dd>' + TS.cap(L.LENS[S.len]) + '</dd>') +
        (s.color ? '<dt>Odstín</dt><dd>' + esc(L.shadeTxt(D, { shade: S.shade || 'poradit' })) + '</dd>' : '') + '<dt>Stylistka</dt><dd>' + esc(st ? st.n : '') + (S.st === 'any' ? ' (podle volna)' : '') + '</dd>' +
        '<dt>Termín</dt><dd>' + esc(TS.cap(TS.fmtLong(S.date))) + ' v ' + TS.hm(S.t) + '–' + TS.hm(S.t + c.dur) + '</dd><dt>Cena</dt><dd>' + (c.price ? 'orientačně ' : '') + L.priceTxt(c.price) + '</dd></dl>';
    }
    function step5() {
      return '<div><h2 class="bk-title">Kam vám pošleme <em>potvrzení?</em></h2></div>' + recap() +
        '<form id="bkForm" novalidate style="display:grid;gap:1.2rem"><div class="two">' + fld('f-name', 'name', 'Jméno a příjmení', 'text', 'autocomplete="name" placeholder="Petra Svobodová"') + fld('f-tel', 'phone', 'Telefon', 'tel', 'autocomplete="tel" inputmode="tel" placeholder="+420 …"') + '</div>' +
        fld('f-mail', 'email', 'E-mail', 'email', 'autocomplete="email" placeholder="petra@example.com"') +
        '<div><label class="lbl" for="f-note">Poznámka <span style="text-transform:none;letter-spacing:0">(nepovinné)</span></label><textarea id="f-note" data-f="note" placeholder="Například: barvím doma, mám citlivou pokožku hlavy, přinesu fotku účesu">' + esc(S.form.note) + '</textarea></div>' +
        '<label class="chk"><input type="checkbox" data-f="first"' + (S.form.first ? ' checked' : '') + '><span>Jdu k vám poprvé</span></label>' +
        '<div><label class="chk"><input type="checkbox" data-f="gdpr"' + (S.form.gdpr ? ' checked' : '') + (S.errs.gdpr ? ' aria-invalid="true"' : '') + '><span>Souhlasím se zpracováním osobních údajů pro účely rezervace.</span></label>' + (S.errs.gdpr ? '<p class="field-err">' + S.errs.gdpr + '</p>' : '') + '</div>' +
        '<p class="bk-note">' + esc(D.rules.storno) + '</p>' +
        '<div class="bk-foot"><button type="button" class="bk-back" data-a="go" data-v="4">Změnit termín</button><button type="submit" class="btn">' + (D.rules.autoConfirm ? 'Závazně rezervovat' : 'Odeslat rezervaci') + ' ' + ARROW + '</button></div>' +
        '<p class="bk-note">' + (D.rules.autoConfirm ? 'Termín se potvrdí hned po odeslání.' : 'Termín pro vás podržíme, dokud ho salon nepotvrdí. Obvykle do několika hodin.') + '</p></form>';
    }
    function stepDone() {
      var b = S.done, st = L.st(D, b.st), auto = b.status === 'confirmed';
      return '<div class="done"><span class="stamp" aria-hidden="true">' + (auto ? 'Rezervováno' : 'Přijato') + '<small>' + esc(TS.fmtDMY(b.date)) + ' · ' + TS.hm(b.start) + '</small></span>' +
        '<h2 class="bk-title">' + (auto ? 'Těšíme se na <em>vás</em>' : 'Žádost <em>máme</em>') + '</h2>' +
        '<p><strong>' + esc(b.svcName) + '</strong> · ' + esc(TS.fmtLong(b.date)) + ' v ' + TS.hm(b.start) + ' u ' + esc(st.gen) + '. ' +
        (auto ? 'Potvrzení jsme poslali na <strong>' + esc(b.email) + '</strong>.' : 'Termín pro vás držíme a potvrzení pošleme na <strong>' + esc(b.email) + '</strong>, obvykle do několika hodin.') + (b.shade && b.shade !== 'poradit' ? ' Poznamenali jsme si odstín ' + esc(L.shadeTxt(D, b)) + '.' : '') + '</p>' +
        (auto ? '<div class="row"><a class="btn btn--line btn--sm" href="' + esc(TS.cal.gcal(L.event(D, b))) + '" target="_blank" rel="noopener">Přidat do Google Kalendáře</a><button type="button" class="btn btn--line btn--sm" data-a="ics">Stáhnout do kalendáře (.ics)</button></div>' : '') +
        '<div class="demo-hint"><span><strong>Ukázka:</strong> ' + (auto ? 'salon právě dostal e-mail o nové rezervaci a termín se objevil v kalendáři administrace.' : 'salon právě dostal e-mail s tlačítkem pro potvrzení a žádost čeká v přehledu administrace.') + '</span>' +
        '<div class="row"><a class="btn btn--sm" href="lokna-sprava.html#emaily">Zobrazit e-maily</a><a class="btn btn--line btn--sm" href="lokna-sprava.html#rezervace/' + b.id + '">Otevřít v administraci</a></div></div>' +
        '<button type="button" class="bk-back" data-a="again">Rezervovat další termín</button></div>';
    }
    function summary() {
      var s = svc(), c = s ? calc() : null, st = L.st(D, S.assigned || (S.st !== 'any' ? S.st : null)), ex = s ? L.exNames(D, { extras: exOk() }) : [];
      if (S.done) { s = L.svc(D, S.done.svc) || { n: S.done.svcName }; }
      $('#rSvc').textContent = s ? s.n : 'Vyberte službu';
      var rows = [];
      rows.push(['Stylistka', S.st === 'any' ? (st ? esc(st.n) + '<small>podle volna</small>' : 'Kdokoli z týmu') : st ? '<i style="background:' + grad(st) + '"></i>' + esc(st.n) : '–']);
      rows.push(['Termín', S.date && S.t != null ? esc(TS.cap(TS.fmtShort(S.date))) + ' v ' + TS.hm(S.t) : 'vyberte den a čas']);
      if (s && !L.flat(s)) rows.push(['Vlasy', TS.cap(L.LENS[S.len])]);
      if (s && s.color) rows.push(['Odstín', esc(L.shadeTxt(D, { shade: S.shade || 'poradit' }))]);
      if (ex.length) rows.push(['Příplatky', esc(ex.join(', '))]);
      rows.push(['Trvání', c ? L.durTxt(c.dur) : '–']);
      $('#rList').innerHTML = rows.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + r[1] + '</dd>'; }).join('');
      $('#rPrice').textContent = c ? L.priceTxt(c.price) : '–';
      $('#rStorno').textContent = 'Platit můžete kartou i hotově. ' + D.rules.storno.split('. ')[0] + '.';
    }
    function render(focus) {
      $('#bkSteps').innerHTML = stepsHTML();
      body.innerHTML = S.done ? stepDone() : [null, step1, step2, step3, step4, step5][S.step]();
      summary();
      TS.$$('#bk [data-sw]').forEach(function (i) { swatch(i, L.shade(D, i.dataset.sw)); });
      if (focus) {
        var t = body.querySelector('.bk-title'); if (t) { t.setAttribute('tabindex', '-1'); try { t.focus({ preventScroll: true }); } catch (e) { } }
        var top = $('#bk').getBoundingClientRect().top; if (top < 70 || top > window.innerHeight * .6) window.scrollTo({ top: window.scrollY + top - 96, behavior: RM ? 'auto' : 'smooth' });
      }
    }
    function go(n, focus) { S.step = Math.max(1, Math.min(n, maxStep())); if (S.step === 2 && !needHair()) S.step = n > 2 ? 3 : 1; render(focus !== false); }
    $('#bk').addEventListener('click', function (e) {
      var el = e.target.closest('[data-a]'); if (!el || el.disabled) return;
      var a = el.dataset.a, v = el.dataset.v;
      if (a === 'ex') return;
      if (a === 'cat') { S.cat = v; render(); }
      else if (a === 'svc') {
        if (S.svc !== v) { S.svc = v; S.date = null; S.t = null; S.assigned = null; S.ex = []; if (!L.svc(D, v).color) S.shade = S.shade && S.shade !== 'poradit' ? S.shade : ''; }
        if (S.st && S.st !== 'any' && !L.canDo(L.st(D, S.st), v)) { S.notice = L.st(D, S.st).n.split(' ')[0] + ' tuto službu nedělá. Vyberte prosím jinou stylistku.'; S.st = null; } else S.notice = '';
        go(needHair() ? 2 : 3);
      }
      else if (a === 'len') { S.len = +v; TS.flag('len', v); S.t = null; S.assigned = null; render(); var hp = $('#bkHair'); if (hp) hp.style.d = 'path("' + L.HAIR[S.len] + '")'; }
      else if (a === 'shade') { S.shade = v; render(); }
      else if (a === 'next2') go(3);
      else if (a === 'st') { if (S.st !== v) { S.st = v; S.t = null; S.assigned = null; S.page = 0; } S.notice = ''; go(4); }
      else if (a === 'page') { S.page += +v; var days = dayList(), per = window.innerWidth < 560 ? 4 : 7, first = days.slice(S.page * per, S.page * per + per).find(function (x) { return x.n; }); if (first) { S.date = first.d; S.t = null; } render(); }
      else if (a === 'day') { S.date = v; S.t = null; S.notice = ''; render(); }
      else if (a === 'time') {
        var sl = L.slots(D, S.date, S.svc, S.len, exOk(), S.st).find(function (x) { return x.t === +v; }); if (!sl) return;
        S.t = +v; S.assigned = S.st === 'any' ? L.pickSt(D, S.date, sl.sts) : S.st; S.notice = ''; go(5);
      }
      else if (a === 'go') go(+v);
      else if (a === 'again') { S = Object.assign(S, { step: 1, svc: null, ex: [], shade: '', st: null, assigned: null, date: null, t: null, done: null, errs: {}, notice: '' }); render(true); }
      else if (a === 'ics') { var b = S.done; TS.download('termin-lokna.ics', TS.cal.ics(L.event(D, b)), 'text/calendar'); }
    });
    $('#bk').addEventListener('change', function (e) {
      var el = e.target;
      if (el.dataset.a === 'ex') { var i = S.ex.indexOf(el.value); if (el.checked && i < 0) S.ex.push(el.value); if (!el.checked && i >= 0) S.ex.splice(i, 1); S.t = null; S.assigned = null; render(); var c = $('[data-a="ex"][value="' + el.value + '"]'); if (c) c.focus(); }
      if (el.dataset.f) { S.form[el.dataset.f] = el.type === 'checkbox' ? el.checked : el.value; if (S.errs[el.dataset.f] && el.type === 'checkbox' && el.checked) { delete S.errs[el.dataset.f]; render(); } }
    });
    $('#bk').addEventListener('input', function (e) { var el = e.target; if (el.dataset.f && el.type !== 'checkbox') { S.form[el.dataset.f] = el.value; if (S.errs[el.dataset.f]) { el.classList.remove('bad'); } } });
    $('#bk').addEventListener('submit', function (e) {
      e.preventDefault();
      var f = S.form, er = {};
      if (f.name.trim().length < 3 || f.name.trim().indexOf(' ') < 0) er.name = 'Vyplňte prosím jméno i příjmení.';
      if (!TS.validPhone(f.phone)) er.phone = 'Zadejte telefon, například 777 123 456.';
      if (!TS.validEmail(f.email)) er.email = 'Zadejte platný e-mail, pošleme na něj potvrzení.';
      if (!f.gdpr) er.gdpr = 'Bez souhlasu rezervaci nemůžeme uložit.';
      S.errs = er;
      if (Object.keys(er).length) { render(); var first = body.querySelector('[aria-invalid="true"]'); if (first) first.focus(); return; }
      /* těsně před uložením ještě jednou ověříme, že čas je pořád volný */
      var ex = exOk(), sl = L.slots(D, S.date, S.svc, S.len, ex, S.st).find(function (x) { return x.t === S.t; });
      if (!sl) { S.t = null; S.notice = 'Tento čas si mezitím někdo rezervoval. Vyberte si prosím jiný.'; go(4); return; }
      if (S.st !== 'any') S.assigned = S.st; else if (sl.sts.indexOf(S.assigned) < 0) S.assigned = L.pickSt(D, S.date, sl.sts);
      var s = svc(), c = calc(), auto = !!D.rules.autoConfirm, ts = Date.now();
      var b = { id: TS.uid('lb'), svc: s.id, svcName: s.n, flat: L.flat(s), len: S.len, extras: ex, shade: s.color ? (S.shade || 'poradit') : '', st: S.assigned, stAny: S.st === 'any',
        date: S.date, start: S.t, dur: c.dur, buf: c.buf, price: c.price, status: auto ? 'confirmed' : 'pending', name: f.name.trim(), phone: f.phone.trim(), email: f.email.trim(),
        note: (f.first ? 'Poprvé v salonu. ' : '') + f.note.trim(), source: 'web', createdAt: ts, log: [{ at: ts, t: 'Rezervace z webu' + (S.st === 'any' ? ' (stylistka podle volna)' : '') }] };
      b.note = b.note.trim();
      if (auto) b.log.push({ at: ts, t: 'Potvrzeno automaticky, čas byl volný' });
      D.bookings.push(b);
      if (D.notify.newReq !== false || auto) L.mail(D, auto ? 'salon_auto' : 'salon_new', b);
      L.mail(D, auto ? 'confirmed' : 'received', b);
      TS.save();
      S.done = b; S.form = { name: '', phone: '', email: '', note: '', first: false, gdpr: false }; S.errs = {};
      render(true);
      TS.toast(auto ? 'Rezervace je potvrzená.' : 'Žádost odeslána. Salon dostal e-mail.', { href: 'lokna-sprava.html#emaily', label: 'Zobrazit e-maily' });
    });
    render(false);
  }
})();
