/* Lokna – realistická kresba vlasů pro vzorník odstínů.
   Běží ve Web Workeru (OffscreenCanvas), aby kresba nebrzdila stránku. Kde worker s OffscreenCanvas
   není, načte se stejný soubor jako běžný skript a kreslí na hlavním vlákně. */
(function (G) {
  'use strict';
  function lum(hex) { var m = /^#?([0-9a-f]{6})$/i.exec(hex || ''); if (!m) return 0; var n = parseInt(m[1], 16); return (0.299 * (n >> 16) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255; }
  /* ---------- realistické vlasy: procedurální kresba na canvasu ----------
     Každý vlas je tenká křivka. Barvy se míchají ze dvou tónů odstínu (a = světlejší, b = tmavší),
     vlasy tvoří prameny se stínem mezi sebou, kořínky a strany jsou tmavší a přes prameny běží
     úzký pruh lesku jako u skutečných vlasů. Kresba se dělá postupně (jedna textura na snímek)
     a výsledek se drží v paměti, takže každý odstín se kreslí jen jednou. */
  function rgb(h) { var n = parseInt(String(h).replace('#', ''), 16) || 0; return [n >> 16, n >> 8 & 255, n & 255]; }
  function mixc(c1, c2, t) { return [Math.round(c1[0] + (c2[0] - c1[0]) * t), Math.round(c1[1] + (c2[1] - c1[1]) * t), Math.round(c1[2] + (c2[2] - c1[2]) * t)]; }
  function rgba(c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + (a == null ? 1 : a) + ')'; }
  function prng(seed) { seed = seed % 2147483647 || 1; return function () { seed = seed * 16807 % 2147483647; return (seed - 1) / 2147483646; }; }
  function seedOf(t) { var h = 17; for (var i = 0; i < t.length; i++) h = (h * 31 + t.charCodeAt(i)) % 2147483647; return h; }
  function hairPal(s) {
    var A = rgb(s.a), B = rgb(s.b), l = lum(s.a), red = A[0] > A[1] * 1.6 && A[0] > 120;
    var spec = l < .25 ? mixc(A, [168, 184, 212], .5) : red ? mixc(A, [255, 210, 220], .45) : mixc(A, [255, 248, 234], l > .8 ? .55 : .5);
    return { deep: mixc(B, [10, 6, 8], l > .8 ? .3 : .5), dark: B, mid: mixc(A, B, .5), base: A, light: mixc(A, [255, 250, 242], l > .8 ? .3 : .2), spec: spec, l: l };
  }
  /* tvary: „lock“ = rovný pramen (vzorník, políčka), „head“ = dlouhé vlasy zezadu (obálka), „pt-…“ = účesy stylistek */
  var HMODE = {
    lock: { W: 720, H: 900, x0: 0, y0: 0, n: 2200, seg: 20, clumps: 46, 
      flow: function (u, t, ph, j) { var y = -12 + t * 936; return [u * 760 - 20 + Math.sin(t * 3.4 + ph) * 9 + Math.sin(t * 6.3 + 1.1) * 7 + Math.sin(t * 17 + j * 9) * .9, y]; },
      shade: function (u, cu) { return (.72 + .28 * Math.sin(cu * Math.PI)) * (.82 + .18 * Math.sin(u * Math.PI)); },
      conv: function (t) { return .5 * Math.pow(t, 1.6); }, so: 26, t: function (y) { return (y + 12) / 936; },
      band: function (u) { return 236 + Math.sin(u * 13 + .4) * 14 + Math.sin(u * 4.1) * 10; },
      spec: function (x, y, u, o) {
        var c1 = 236 + Math.sin(u * 13 + .4) * 14 + Math.sin(u * 4.1) * 10 + o, c2 = 560 + Math.sin(u * 9 + 2) * 22 + o * 1.5;
        return Math.exp(-Math.pow((y - c1) / 26, 2)) * .95 + Math.exp(-Math.pow((y - c2) / 40, 2)) * .2;
      },
      overlay: function (g, P) {
        var gr = g.createLinearGradient(0, 0, 0, 900);
        gr.addColorStop(0, rgba(mixc([255, 255, 255], P.deep, .55))); gr.addColorStop(.16, '#fff'); gr.addColorStop(.85, '#fff'); gr.addColorStop(1, rgba(mixc([255, 255, 255], P.deep, .25)));
        g.globalCompositeOperation = 'multiply'; g.fillStyle = gr; g.fillRect(0, 0, 720, 900); g.globalCompositeOperation = 'source-over';
      } },
    head: { W: 220, H: 380, x0: 40, y0: 30, n: 1700, seg: 26, clumps: 30, 
      flow: function (u, t, ph, j) {
        var s = u * 2 - 1, y = 38 + t * 380, w;
        if (y < 128) w = 4 + 76 * Math.sqrt(Math.max(0, (y - 38) / 90)); else w = 80 + (y - 128) * .11;
        var wave = y > 128 ? Math.sin((y - 128) / 56 + ph) * 3.4 * Math.min(1, (y - 128) / 60) : 0;
        return [150 + s * w + wave + Math.sin(y / 21 + j * 9) * .7, y];
      },
      shade: function (u, cu) { var s = u * 2 - 1; return (.42 + .58 * Math.pow(Math.max(0, Math.cos(s * Math.PI / 2)), .55)) * (.74 + .26 * Math.sin(cu * Math.PI)); },
      conv: function (t) { return t < .35 ? 0 : .32 * Math.pow((t - .35) / .65, 1.4); }, so: 9, t: function (y) { return (y - 38) / 380; },
      band: function (u) { var s = u * 2 - 1; return 106 + 26 * s * s; },
      spec: function (x, y, u, o) {
        var s = u * 2 - 1, c1 = 106 + 26 * s * s + o, c2 = 246 + Math.sin(s * 4 + 1) * 10 + o * 1.6;
        return Math.exp(-Math.pow((y - c1) / 10, 2)) * .85 * (1 - .55 * s * s) + Math.exp(-Math.pow((y - c2) / 22, 2)) * .16 * (1 - .6 * s * s);
      },
      overlay: function (g, P) {
        var gr = g.createRadialGradient(150, 150, 30, 150, 190, 190);
        gr.addColorStop(0, '#fff'); gr.addColorStop(.7, rgba(mixc([255, 255, 255], P.deep, .12))); gr.addColorStop(1, rgba(mixc([255, 255, 255], P.deep, .5)));
        g.globalCompositeOperation = 'multiply'; g.fillStyle = gr; g.fillRect(40, 30, 220, 380);
        var top = g.createLinearGradient(0, 36, 0, 70); top.addColorStop(0, rgba(mixc([255, 255, 255], P.deep, .5))); top.addColorStop(1, '#fff');
        g.fillStyle = top; g.fillRect(40, 30, 220, 50); g.globalCompositeOperation = 'source-over';
      } }
  };
  /* portréty stylistek (výřez 300 × 380, stejné souřadnice jako SVG portrétu na webu) */
  function fall(o) {   // vlasy rozčesané od temene: do výšky yS kopírují tvar hlavy, pak padají dolů
    return { W: 300, H: 380, x0: 0, y0: 0, n: o.n || 1300, seg: 24, clumps: o.clumps || 24,
      flow: function (u, t, ph, j) {
        var s = (u * 2 - 1) * 1.1, y = o.top + t * (o.len - o.top), w = y < o.yS ? 3 + (o.wS - 3) * Math.pow(Math.max(0, (y - o.top) / (o.yS - o.top)), .38) : o.wS + (y - o.yS) * o.slope;
        var wave = y > o.yS ? Math.sin((y - o.yS) / 34 + ph) * o.wave * Math.min(1, (y - o.yS) / 40) : 0;
        return [o.cx + s * w + wave + Math.sin(y / 17 + j * 9) * .5, y];
      },
      shade: function (u, cu) { var s = u * 2 - 1; return (.45 + .55 * Math.pow(Math.max(0, Math.cos(s * Math.PI / 2)), .5)) * (.74 + .26 * Math.sin(cu * Math.PI)); },
      conv: function (t) { return t < .45 ? 0 : o.conv * Math.pow((t - .45) / .55, 1.4); }, so: 7, t: function (y) { return (y - o.top) / (o.len - o.top); },
      band: function (u) { var s = u * 2 - 1; return o.band + o.bend * s * s; },
      spec: function (x, y, u, of) { var s = u * 2 - 1, c1 = o.band + o.bend * s * s + of; return Math.exp(-Math.pow((y - c1) / 8, 2)) * .85 * (1 - .5 * s * s) + Math.exp(-Math.pow((y - o.band2 - of * 1.5) / 18, 2)) * .15; },
      overlay: function (g, P) {
        var gr = g.createRadialGradient(150, o.yS, 20, 150, o.yS + 30, 170);
        gr.addColorStop(0, '#fff'); gr.addColorStop(.65, rgba(mixc([255, 255, 255], P.deep, .1))); gr.addColorStop(1, rgba(mixc([255, 255, 255], P.deep, .45)));
        g.globalCompositeOperation = 'multiply'; g.fillStyle = gr; g.fillRect(0, 0, 300, 380); g.globalCompositeOperation = 'source-over';
      } };
  }
  HMODE['pt-long'] = fall({ cx: 150, top: 68, yS: 150, wS: 58, slope: .2, len: 346, wave: 2.6, conv: .3, band: 103, bend: 22, band2: 236, n: 1400 });
  HMODE['pt-bob'] = fall({ cx: 150, top: 64, yS: 140, wS: 64, slope: .12, len: 254, wave: 1.2, conv: .22, band: 99, bend: 24, band2: 200, n: 1300, clumps: 28 });
  /* drdol: vlasy od čela a spánků vyčesané nahoru k drdolu, drdol z pramenů stočených do kruhu */
  HMODE['pt-bun'] = { W: 300, H: 380, x0: 0, y0: 0, n: 1200, seg: 22, clumps: 26, sheen: false,
    flow: function (u, t, ph, j) {
      var s = (u * 2 - 1) * 1.08, x0 = 150 + s * 9, y0 = 98, cx = 150 + s * 58, cy = 90 + 8 * s * s, x1 = 150 + s * 74, y1 = 130 + 62 * s * s, m = 1 - t;
      return [m * m * x0 + 2 * m * t * cx + t * t * x1 + Math.sin(t * 9 + ph) * 1.2 + Math.sin(t * 23 + j * 9) * .4, m * m * y0 + 2 * m * t * cy + t * t * y1];
    },
    shade: function (u, cu) { var s = u * 2 - 1; return (.5 + .5 * Math.pow(Math.max(0, Math.cos(s * Math.PI / 2)), .5)) * (.76 + .24 * Math.sin(cu * Math.PI)); },
    conv: function () { return 0; }, so: 7,
    spec: function (x, y, u, of) { var s = u * 2 - 1, c1 = 110 + 16 * s * s + of; return Math.exp(-Math.pow((y - c1) / 8, 2)) * .8 * (1 - .4 * s * s); },
    overlay: function (g, P) {
      var gr = g.createRadialGradient(150, 120, 10, 150, 130, 110);
      gr.addColorStop(0, '#fff'); gr.addColorStop(1, rgba(mixc([255, 255, 255], P.deep, .4)));
      g.globalCompositeOperation = 'multiply'; g.fillStyle = gr; g.fillRect(0, 0, 300, 380); g.globalCompositeOperation = 'source-over';
    },
    extra: function (g, P, R) {
      var cx = 150, cy = 74, r0 = 28;
      g.save(); g.beginPath(); g.arc(cx, cy, r0, 0, 7); g.clip();
      var bg = g.createRadialGradient(cx - 8, cy - 10, 2, cx, cy, r0); bg.addColorStop(0, rgba(P.mid)); bg.addColorStop(1, rgba(P.deep)); g.fillStyle = bg; g.fillRect(cx - r0, cy - r0, r0 * 2, r0 * 2);
      /* prameny stočené do spirály (poloměr se po obvodu mění), mezi závity tmavší rýhy */
      function spiral(r1, r2, a0, span, ox, oy) { g.beginPath(); for (var q = 0; q <= 18; q++) { var t = q / 18, an = a0 + span * t, rr = r1 + (r2 - r1) * t; var X = cx + ox + Math.cos(an) * rr, Y = cy + oy + Math.sin(an) * rr * .96; if (q) g.lineTo(X, Y); else g.moveTo(X, Y); } g.stroke(); }
      for (var i = 0; i < 760; i++) {
        var r = 2 + Math.pow(R(), .75) * r0, a0 = R() * 6.28, span = 1.2 + R() * 2.4, dr = (R() - .25) * 14, ox = (R() - .5) * 5, oy = (R() - .5) * 5;
        var t = R(), col = i < 300 ? mixc(P.deep, P.dark, t) : i < 580 ? mixc(P.dark, P.base, t) : mixc(P.mid, P.light, t);
        g.strokeStyle = rgba(col, .45 + R() * .45); g.lineWidth = .5 + R() * (i < 300 ? 1.8 : 1);
        spiral(r, Math.max(1, r + dr), a0, span, ox, oy);
        if (i >= 300 && R() < .75) {                 // lesk: kratší úsek pramene na horní levé straně
          var ah = -2.25 + (R() - .5) * .5, h = (1 - Math.abs(r / r0 - .6) * 1.2) * (.6 + R() * .4);
          if (h > .25) { g.strokeStyle = rgba(P.spec, h * .6); g.lineWidth = .6; spiral(r, r + dr * .25, ah - .35, .7, ox, oy); }
        }
      }
      var sh = g.createRadialGradient(cx - 9, cy - 11, 4, cx, cy, r0 + 2); sh.addColorStop(0, 'rgba(255,255,255,0)'); sh.addColorStop(.7, 'rgba(0,0,0,0)'); sh.addColorStop(1, rgba(P.deep, .55));
      g.fillStyle = sh; g.fillRect(cx - r0, cy - r0, r0 * 2, r0 * 2); g.restore();
    } };
  function drawHair(s, mode, k, mk) {
    var M = HMODE[mode], P = hairPal(s), R = prng(seedOf(mode + s.id));
    var cv = mk(Math.round(M.W * k), Math.round(M.H * k));
    var g = cv.getContext('2d'); g.setTransform(k, 0, 0, k, -M.x0 * k, -M.y0 * k); g.lineCap = 'round'; g.lineJoin = 'round';
    var bg = g.createLinearGradient(0, M.y0, 0, M.y0 + M.H); bg.addColorStop(0, rgba(P.deep)); bg.addColorStop(.5, rgba(P.dark)); bg.addColorStop(1, rgba(mixc(P.dark, P.mid, .5)));
    g.fillStyle = bg; g.fillRect(M.x0, M.y0, M.W, M.H);
    var C = M.clumps, cph = []; for (var c = 0; c <= C; c++) cph.push(R() * 6.28);
    function pts(u, j, t1) {
      var ci = Math.min(C - 1, Math.floor(u * C)), cc = (ci + .5) / C, ph = cph[ci] + (u * C - ci) * .5, out = [];
      for (var q = 0; q <= M.seg; q++) { var t = q / M.seg * t1; out.push(M.flow(u + (cc - u) * M.conv(t), t, ph, j)); }
      return out;
    }
    function line(p) { g.beginPath(); g.moveTo(p[0][0], p[0][1]); for (var q = 1; q < p.length; q++) g.lineTo(p[q][0], p[q][1]); g.stroke(); }
    var NB = 10, buckets = []; for (var bb = 0; bb < NB; bb++) buckets.push([]);
    /* tři vrstvy: zadní tmavé, střední, přední světlejší a tenčí */
    [[.5, P.deep, P.dark, .55, .9, 1.2, 2.2], [.32, P.dark, P.base, .45, .85, .7, 1.6], [.22, P.mid, P.light, .35, .8, .45, 1.1]].forEach(function (L, li) {
      var n = Math.round(M.n * L[0]);
      for (var i = 0; i < n; i++) {
        var u = R(), j = R(), cu = u * C % 1, sh = M.shade(u, cu), col = mixc(mixc(L[1], L[2], R()), P.deep, 1 - sh);
        var p = pts(u, j, .9 + R() * .12), w = L[5] + R() * (L[6] - L[5]);
        g.strokeStyle = rgba(col, L[3] + R() * (L[4] - L[3])); g.lineWidth = w; line(p);
        if (li > 0 && R() < .8) {            // lesk: jen úsek vlasu, který leží v pruhu odlesku (každý vlas trochu jinde)
          var ci = Math.min(C - 1, Math.floor(u * C)), o = Math.sin(cph[ci] * 3) * M.so * .45 + (R() - .5) * M.so * .6, m = .7 + R() * .6;
          for (var q = 1; q < p.length; q++) for (var sb = 0; sb < 4; sb++) {   // úsek dělíme na čtvrtiny, ať lesk plynule nabíhá
            var a0 = sb / 4, a1 = (sb + 1) / 4, P0 = [p[q - 1][0] + (p[q][0] - p[q - 1][0]) * a0, p[q - 1][1] + (p[q][1] - p[q - 1][1]) * a0], P1 = [p[q - 1][0] + (p[q][0] - p[q - 1][0]) * a1, p[q - 1][1] + (p[q][1] - p[q - 1][1]) * a1];
            var h = M.spec((P0[0] + P1[0]) / 2, (P0[1] + P1[1]) / 2, u, o) * sh * m; if (h > .04) buckets[Math.min(NB - 1, Math.floor(h * NB))].push(P0, P1);
          }
        }
      }
      if (li === 0) {                        // stín mezi prameny
        g.strokeStyle = rgba(P.deep, .45); g.lineWidth = 1.6;
        for (var b = 1; b < C; b++) for (var r = 0; r < 3; r++) line(pts(b / C + (R() - .5) * .006, R(), 1));
      }
    });
    g.strokeStyle = rgba(P.spec); g.lineWidth = .7;
    /* měkký odlesk pod vlasy, ať pruh lesku není ostrý: po sloupcích svislý přechod kolem pruhu */
    g.globalCompositeOperation = 'screen';
    if (M.sheen !== false) for (var uu = -.02; uu <= 1.02; uu += 1 / 110) {
      var yc = M.band(uu), x1 = M.flow(uu, M.t(yc), 0, 0)[0], x2 = M.flow(uu + 1 / 110, M.t(M.band(uu + 1 / 110)), 0, 0)[0], sp = M.so * 1.6;
      var sg = g.createLinearGradient(0, yc - sp, 0, yc + sp), al = (P.l > .8 ? .14 : .26) * M.shade(Math.min(1, Math.max(0, uu)), .5);
      sg.addColorStop(0, rgba(P.spec, 0)); sg.addColorStop(.5, rgba(P.spec, al)); sg.addColorStop(1, rgba(P.spec, 0));
      g.fillStyle = sg; g.fillRect(Math.min(x1, x2) - .6, yc - sp, Math.abs(x2 - x1) + 1.2, sp * 2);
    }
    g.globalCompositeOperation = 'source-over';
    buckets.forEach(function (seg, bi) { if (!seg.length) return; g.globalAlpha = (bi + 1) / NB * .9; g.beginPath(); for (var q = 0; q < seg.length; q += 2) { g.moveTo(seg[q][0], seg[q][1]); g.lineTo(seg[q + 1][0], seg[q + 1][1]); } g.stroke(); });
    g.globalAlpha = 1;
    M.overlay(g, P);
    if (M.extra) M.extra(g, P, R);
    g.strokeStyle = rgba(P.light, .28); g.lineWidth = .4;      // pár odstávajících vlásků
    for (var f = 0; f < 14; f++) { var fu = R(), fp = pts(fu, R(), 1); for (var q = 0; q < fp.length; q++) fp[q][0] += Math.sin(q * .5 + f) * 4 * q / fp.length; line(fp); }
    return cv;
  }
  G.LOKNA_VLASY = drawHair;
  if (typeof document === 'undefined' && typeof G.postMessage === 'function') {
    G.onmessage = function (e) {
      var d = e.data, cv;
      try { cv = drawHair(d.s, d.mode, d.k, function (w, h) { return new OffscreenCanvas(w, h); }); }
      catch (err) { G.postMessage({ key: d.key, err: String(err) }); return; }
      cv.convertToBlob({ type: 'image/jpeg', quality: .9 }).then(function (b) { G.postMessage({ key: d.key, blob: b }); }, function (err) { G.postMessage({ key: d.key, err: String(err) }); });
    };
  }
})(typeof self !== 'undefined' ? self : window);
