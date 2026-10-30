/* Lokna – administrace ukázky: přehled, kalendář po stylistkách, rezervace, doklady, ceník, tým, provoz, oznámení, údaje */
(function () {
  'use strict';
  var L = window.LOKNA, D = TS.data('lokna', 2, L.defaults), esc = TS.esc, ic = TS.ic, f = TS.f;
  TS.mode('lokna-rezim');
  var ui, A;
  var FEED = 'https://lokna-studio.cz/kalendar/';

  /* ---------- pomocníci ---------- */
  function byId(id) { return D.bookings.find(function (b) { return b.id === id; }) || null; }
  function pending() { return D.bookings.filter(function (b) { return b.status === 'pending'; }); }
  function active(b) { return b.status === 'pending' || b.status === 'confirmed' || b.status === 'done'; }
  function isPast(b) { var t = TS.today(); return b.date < t || (b.date === t && b.start + b.dur <= TS.nowMin()); }
  function started(b) { var t = TS.today(); return b.date < t || (b.date === t && b.start <= TS.nowMin()); }
  function toIssue() { return D.bookings.filter(function (b) { var s = L.svc(D, b.svc); return (b.status === 'confirmed' || b.status === 'done') && started(b) && !b.receipt && !(b.status === 'done' && s && L.free(s)); }).sort(function (a, b) { return L.order(a) < L.order(b) ? 1 : -1; }); }
  function stOf(b) { return L.st(D, b.st); }
  function first(n) { return String(n || '').split(' ')[0]; }
  function dot(st) { return st ? '<i class="lk-dot" style="background:linear-gradient(135deg,' + st.a + ',' + st.b + ')"></i>' : ''; }
  function stTag(st) { return st ? '<span class="lk-st">' + dot(st) + esc(first(st.n)) + '</span>' : ''; }
  function pill(b) {
    if (b.status === 'confirmed' && isPast(b)) return '<span class="k-pill k-accent">K uzavření</span>';
    if (b.status === 'done') return b.receipt ? '<span class="k-pill k-ok">Zaplaceno</span>' : '<span class="k-pill k-done">Proběhlo</span>';
    if (b.status === 'noshow') return '<span class="k-pill k-bad">Nedostavila se</span>';
    return '<span class="k-pill k-' + b.status + '">' + L.STATUS[b.status] + '</span>';
  }
  function greet() { var h = new Date().getHours(); return h < 10 ? 'Dobré ráno' : h < 18 ? 'Dobré odpoledne' : 'Dobrý večer'; }
  function nextOpen(from, inclusive) { var d = inclusive ? from : TS.addDays(from, 1); for (var i = 0; i < 30; i++) { if (L.salonWin(D, d)) return d; d = TS.addDays(d, 1); } return from; }
  function tOpts(a, b, s) { var o = []; for (var m = a; m <= b; m += s) o.push([m, TS.hm(m)]); return o; }
  function dayWord(d) { var dd = TS.diffDays(TS.today(), d); return dd === 0 ? 'Dnes' : dd === 1 ? 'Zítra' : dd === -1 ? 'Včera' : TS.cap(TS.WD[TS.wd(d)]); }
  function svcOpts() { var o = []; D.cats.forEach(function (c) { c.items.forEach(function (s) { o.push([s.id, c.n + ' · ' + s.n]); }); }); return o; }
  function stOpts(any) { return (any ? [['any', 'Kdokoli (podle volna)']] : []).concat(D.team.map(function (s) { return [s.id, s.n]; })); }
  function calRange() {
    var lo = 1440, hi = 0; D.hours.forEach(function (h) { if (h.on) { lo = Math.min(lo, h.from); hi = Math.max(hi, h.to); } });
    if (lo >= hi) { lo = 540; hi = 1200; } return [Math.floor(lo / 60) * 60, Math.ceil(hi / 60) * 60];
  }
  function dayMinutes(date) { return D.team.reduce(function (a, st) { var w = L.stWin(D, st, date); return a + (w ? w[1] - w[0] : 0); }, 0); }
  function bookedMinutes(date) { return D.bookings.filter(function (b) { return b.date === date && active(b); }).reduce(function (a, b) { return a + b.dur; }, 0); }
  function headActs() { return '<button class="k-btn k-sec" type="button" data-act="newBlock">' + ic('lock') + 'Zablokovat čas</button><button class="k-btn k-pri" type="button" data-act="newRes">' + ic('plus') + 'Nová rezervace</button>'; }
  function receipts() { return D.bookings.filter(function (b) { return b.receipt; }); }

  /* ===================== přehled ===================== */
  function reqCard(b) {
    var st = stOf(b), sh = b.shade ? L.shade(D, b.shade) : null;
    return '<article class="k-req"><div class="k-req-when"><strong>' + esc(TS.fmtRel(b.date)) + (TS.diffDays(TS.today(), b.date) > 1 ? '' : ' ' + TS.fmtDM(b.date)) + '</strong><span>' + TS.hm(b.start) + '</span></div>' +
      '<div style="min-width:0"><b class="lk-req-svc">' + esc(L.svcLabel(D, b)) + '</b> <span class="k-muted">· ' + L.durTxt(b.dur) + ' · ' + L.priceTxt(b.price) + '</span>' +
      '<div>' + esc(b.name) + ' · <span class="k-tnum">' + esc(b.phone) + '</span></div>' +
      '<div class="lk-chips" style="margin-top:4px">' + stTag(st) + (b.stAny ? '<span class="lk-mini">vybrala „je mi to jedno“</span>' : '') + (b.shade ? '<span class="lk-st">' + (sh ? '<i class="lk-sw" style="background:linear-gradient(135deg,' + sh.a + ',' + sh.b + ')"></i>' : '') + esc(L.shadeTxt(D, b)) + '</span>' : '') + '</div>' +
      (b.note ? '<p class="k-muted k-small" style="font-style:italic;margin-top:4px">„' + esc(b.note) + '“</p>' : '') +
      '<p class="k-small k-muted" style="margin-top:3px">' + esc(TS.cap(TS.fmtLong(b.date))) + ' · z webu ' + TS.ago(b.createdAt) + '</p></div>' +
      '<div class="k-req-act"><button class="k-btn k-pri k-sm" type="button" data-act="confirm" data-arg="' + b.id + '">' + ic('check') + 'Potvrdit</button>' +
      '<button class="k-btn k-sec k-sm" type="button" data-act="openRes" data-arg="' + b.id + '|move">Přesunout</button>' +
      '<button class="k-btn k-ghost k-sm" type="button" data-act="openRes" data-arg="' + b.id + '|reject">Odmítnout</button>' +
      '<button class="k-btn k-ghost k-sm" type="button" data-act="openRes" data-arg="' + b.id + '">Detail</button></div></article>';
  }
  function agenda(date) {
    var list = D.bookings.filter(function (b) { return b.date === date && (active(b) || b.status === 'noshow'); }).sort(function (a, b) { return a.start - b.start; });
    var blocks = D.blocks.filter(function (k) { return k.date === date; }), away = D.team.filter(function (s) { return L.salonWin(D, date) && s.hours[TS.wd(date)].on && L.away(s, date); });
    var head = '<h3>' + dayWord(date) + ' · ' + TS.fmtDM(date) + (L.salonWin(D, date) ? ' · ' + list.filter(active).length + ' ' + TS.plural(list.filter(active).length, 'rezervace', 'rezervace', 'rezervací') : '') + '</h3>';
    if (!L.salonWin(D, date)) return '<div class="k-day">' + head + '<p class="k-empty">Salon je zavřený.</p></div>';
    var items = list.map(function (b) {
      return '<li><button type="button" class="k-ag' + (isPast(b) ? ' k-past' : '') + '" data-act="openRes" data-arg="' + b.id + '"><span class="k-t">' + TS.hm(b.start) + '–' + TS.hm(b.start + b.dur) + '</span>' +
        '<span class="k-m"><b>' + esc(b.name) + '</b><span>' + esc(L.svcLabel(D, b)) + '</span></span><span style="display:grid;gap:4px;justify-items:end">' + stTag(stOf(b)) + (b.status === 'pending' ? '<span class="k-pill k-pending">Čeká</span>' : b.status === 'confirmed' && isPast(b) ? '<span class="k-pill k-accent">K uzavření</span>' : b.receipt ? '<span class="k-pill k-ok">Zaplaceno</span>' : b.status === 'noshow' ? '<span class="k-pill k-bad">Nedorazila</span>' : '') + '</span></button></li>';
    }).join('');
    var extra = blocks.map(function (k) { var st = L.st(D, k.st); return '<li><button type="button" class="k-ag" data-act="openBlock" data-arg="' + k.id + '"><span class="k-t">' + TS.hm(k.from) + '–' + TS.hm(k.to) + '</span><span class="k-m"><b>' + esc(k.reason || 'Blokováno') + '</b><span>' + (st ? esc(st.n) : 'celý salon') + '</span></span><span class="k-pill k-done">Blokace</span></button></li>'; }).join('') +
      away.map(function (s) { var a = L.away(s, date); return '<li><button type="button" class="k-ag" data-act="openAway" data-arg="' + s.id + '"><span class="k-t">celý den</span><span class="k-m"><b>' + esc(a.note || 'Mimo salon') + '</b><span>' + esc(s.n) + '</span></span><span class="k-pill k-done">Nepřítomnost</span></button></li>'; }).join('');
    return '<div class="k-day">' + head + (items || extra ? '<ul class="k-agenda">' + items + extra + '</ul>' : '<p class="k-empty">Zatím žádné rezervace.</p>') + '</div>';
  }
  function remindDay() { return nextOpen(TS.today(), false); }
  function remindList() { var d = remindDay(); return D.bookings.filter(function (b) { return b.date === d && b.status === 'confirmed' && b.email && !b.reminded; }); }
  function mPrehled(app) {
    var t = TS.today(), focus = nextOpen(t, true), next = nextOpen(focus, false);
    var todayList = D.bookings.filter(function (b) { return b.date === focus && active(b); });
    var cap = dayMinutes(focus), occ = cap ? Math.round(bookedMinutes(focus) / cap * 100) : 0;
    var rev = receipts().filter(function (b) { return b.receipt.issueDate === t; }).reduce(function (a, b) { return a + b.receipt.total; }, 0);
    var p = pending().sort(function (a, b) { return L.order(a) < L.order(b) ? -1 : 1; }), ti = toIssue().filter(function (b) { return TS.diffDays(b.date, t) <= 2; });
    var rem = remindList();
    return app.head((focus === t ? 'Dnes · ' : 'Dnes zavřeno · nejbližší otevřený den ') + esc(TS.fmtLong(focus)), greet(), headActs()) +
      '<div class="k-stats"><div class="k-stat"><b>' + todayList.length + '</b><span>' + (focus === t ? 'rezervací dnes' : 'rezervací ' + TS.fmtShort(focus)) + '</span></div>' +
      '<div class="k-stat"><b>' + occ + ' %</b><span>obsazenost stylistek</span></div>' +
      '<div class="k-stat"><b>' + p.length + '</b><span>čeká na potvrzení</span></div>' +
      '<div class="k-stat"><b>' + TS.money(rev) + '</b><span>tržba dnes z dokladů</span></div></div>' +
      '<div class="k-cols"><div class="k-stack"><section class="k-panel"><h2>Čeká na potvrzení ' + (p.length ? '<span class="k-count">' + p.length + '</span>' : '') + '</h2>' +
      (p.length ? '<div class="k-stack">' + p.map(reqCard).join('') + '</div>' : '<p class="k-empty">Všechny žádosti jsou vyřízené. Nové z webu se objeví tady a zároveň přijdou e-mailem.</p>') + '</section>' +
      (ti.length ? '<section class="k-panel"><h2>Doklady k vystavení <span class="k-count">' + ti.length + '</span></h2><p class="k-sub">Návštěvy, které proběhly a ještě nemají doklad.</p><div class="k-rows">' + ti.slice(0, 5).map(function (b) {
        return '<div class="k-row lk-ti" style="--cols:110px minmax(0,1fr) auto"><span class="k-tnum"><b>' + esc(dayWord(b.date)) + '</b><small>' + TS.hm(b.start) + '</small></span><span><b>' + esc(b.name) + '</b><small>' + esc(L.svcLabel(D, b)) + ' · ' + L.priceTxt(b.price) + '</small></span><button class="k-btn k-pri k-sm" type="button" data-act="openReceipt" data-arg="' + b.id + '">' + ic('receipt') + 'Vystavit</button></div>';
      }).join('') + '</div></section>' : '') + '</div>' +
      '<section class="k-panel"><div class="k-panel-h"><span>Program</span><button class="k-btn k-sec k-sm" type="button" data-act="remind"' + (rem.length ? '' : ' disabled') + '>' + ic('bell') + 'Připomenout ' + esc(dayWord(remindDay()).toLowerCase()) + ' (' + rem.length + ')</button></div>' +
      agenda(focus) + agenda(next) + '<p style="margin-top:14px"><a class="k-link" href="#kalendar">Otevřít celý kalendář</a></p></section></div>';
  }

  /* ===================== kalendář ===================== */
  function evCls(b) {
    var i = L.stIdx(D, b.st), s = ' s' + i;
    if (b.status === 'pending') return 'pending' + s;
    if (b.status === 'done') return 'muted' + s;
    if (b.status === 'noshow') return 'noshow' + s;
    return 'confirmed' + s;
  }
  function evTag(b) { return b.receipt ? 'Kč' : b.status === 'noshow' ? 'nedorazila' : b.status === 'pending' ? 'čeká' : ''; }
  function shown(b) { return active(b) || b.status === 'noshow'; }
  function dayGrid(date, cls) {
    var r = calRange(), t = TS.today();
    var cols = D.team.map(function (st) {
      var w = L.stWin(D, st, date), a = L.away(st, date), n = D.bookings.filter(function (b) { return b.date === date && b.st === st.id && active(b); }).length;
      var bands = w ? [[r[0], w[0], 'mimo směnu'], [w[1], r[1], 'mimo směnu']].filter(function (x) { return x[1] > x[0]; }) : [];
      return { key: st.id, d: st.role.split('·')[0].trim(), n: first(st.n), c: w ? TS.hm(w[0]) + '–' + TS.hm(w[1]) + ' · ' + n + ' rez.' : a ? (a.note || 'nepřítomna') : L.salonWin(D, date) ? 'volno' : 'zavřeno', closed: !w, bands: bands, now: date === t ? TS.nowMin() : null };
    });
    var events = D.bookings.filter(function (b) { return b.date === date && shown(b); }).map(function (b) {
      return { col: b.st, start: b.start, end: b.start + b.dur, cls: evCls(b), s: b.name, w: L.svcLabel(D, b), tag: evTag(b), act: 'openRes', arg: b.id };
    });
    var blocks = [];
    D.blocks.filter(function (k) { return k.date === date; }).forEach(function (k) {
      (k.st === 'all' ? D.team.map(function (s) { return s.id; }) : [k.st]).forEach(function (c) { blocks.push({ col: c, from: k.from, to: k.to, label: k.reason || 'Blokováno', sub: TS.hm(k.from) + '–' + TS.hm(k.to), act: 'openBlock', arg: k.id }); });
    });
    D.team.forEach(function (s) { var a = L.away(s, date); if (a && L.salonWin(D, date)) blocks.push({ col: s.id, from: r[0], to: r[1], label: a.note || 'Nepřítomna', sub: TS.fmtDM(a.from) + '–' + TS.fmtDM(a.to), act: 'openAway', arg: s.id }); });
    return '<div class="lk-cal">' + TS.cal.grid({ cols: cols, from: r[0], to: r[1], hh: 88, events: events, blocks: blocks, emptyAct: 'calEmpty', step: D.rules.step, res: true, cls: cls }) + '</div>';
  }
  function weekGrid(ws) {
    var r = calRange(), t = TS.today(), days = [];
    for (var i = 0; i < 7; i++) days.push(TS.addDays(ws, i));
    days = days.filter(function (d) { return L.salonWin(D, d) || D.bookings.some(function (b) { return b.date === d && active(b); }); });
    var stf = ui.cst || 'all';
    var cols = days.map(function (d) {
      var w = L.salonWin(D, d), n = D.bookings.filter(function (b) { return b.date === d && active(b) && (stf === 'all' || b.st === stf); }).length;
      var st = stf === 'all' ? null : L.st(D, stf), sw = st ? L.stWin(D, st, d) : w;
      var bands = sw ? [[r[0], sw[0], ''], [sw[1], r[1], '']].filter(function (x) { return x[1] > x[0]; }) : [];
      return { key: d, d: TS.WDS[TS.wd(d)], n: TS.fmtDM(d), c: w ? n + ' ' + TS.plural(n, 'rezervace', 'rezervace', 'rezervací') : 'zavřeno', today: d === t, closed: !sw, bands: bands, now: d === t ? TS.nowMin() : null };
    });
    var events = D.bookings.filter(function (b) { return days.indexOf(b.date) >= 0 && shown(b) && (stf === 'all' || b.st === stf); }).map(function (b) {
      return { col: b.date, start: b.start, end: b.start + b.dur, cls: evCls(b), s: b.name, w: L.svcLabel(D, b), tag: evTag(b), act: 'openRes', arg: b.id };
    });
    var blocks = D.blocks.filter(function (k) { return days.indexOf(k.date) >= 0 && (stf === 'all' || k.st === stf || k.st === 'all'); }).map(function (k) {
      var st = L.st(D, k.st); return { col: k.date, from: k.from, to: k.to, label: (k.reason || 'Blokováno') + (st && stf === 'all' ? ' · ' + first(st.n) : ''), sub: TS.hm(k.from) + '–' + TS.hm(k.to), act: 'openBlock', arg: k.id };
    });
    if (stf !== 'all') days.forEach(function (d) { var s = L.st(D, stf), a = L.away(s, d); if (a) blocks.push({ col: d, from: r[0], to: r[1], label: a.note || 'Nepřítomna', sub: 'celý den', act: 'openAway', arg: s.id }); });
    return '<div class="lk-cal">' + TS.cal.grid({ cols: cols, from: r[0], to: r[1], hh: 72, events: events, blocks: blocks, emptyAct: 'calEmpty', step: D.rules.step, cls: 'week' }) + '</div>';
  }
  function mKalendar(app) {
    ui.cv = ui.cv || 'den'; ui.cd = ui.cd || nextOpen(TS.today(), true); ui.cst = ui.cst || 'all';
    var d = ui.cd, ws = TS.weekStart(d), we = TS.addDays(ws, 6);
    var title = ui.cv === 'den' ? TS.cap(TS.fmtLong(d)) : (TS.parse(ws).getMonth() === TS.parse(we).getMonth() ? TS.parse(ws).getDate() + '.–' + TS.parse(we).getDate() + '. ' + TS.MG[TS.parse(ws).getMonth()] : TS.fmtDM(ws) + ' – ' + TS.fmtDM(we)) + ' ' + TS.parse(we).getFullYear();
    var bar = '<div class="k-calbar"><button class="k-icon-btn" type="button" data-act="calNav" data-arg="-1" aria-label="Předchozí">' + ic('chevL') + '</button><button class="k-btn k-sec k-sm" type="button" data-act="calNav" data-arg="0">Dnes</button>' +
      '<button class="k-icon-btn" type="button" data-act="calNav" data-arg="1" aria-label="Další">' + ic('chevR') + '</button>' +
      '<input class="k-inp k-sm" type="date" style="width:auto" value="' + d + '" data-bind="ui.cd" data-rr aria-label="Přejít na den">' +
      '<div class="k-mtabs" style="margin-left:auto"><button type="button" data-act="cv" data-arg="den" aria-pressed="' + (ui.cv === 'den') + '">Den po stylistkách</button><button type="button" data-act="cv" data-arg="tyden" aria-pressed="' + (ui.cv === 'tyden') + '">Týden</button></div></div>';
    var legend = '<div class="lk-legend">' + D.team.map(function (s) { return stTag(s).replace(esc(first(s.n)), esc(s.n)); }).join('') + '<span class="k-pill k-pending">Čeká na potvrzení</span><span class="k-pill k-done">Proběhlo</span><span class="k-pill k-bad">Nedorazila</span><span class="k-muted">Klepnutím do volného místa přidáte rezervaci nebo blokaci.</span></div>';
    var body;
    if (ui.cv === 'den') {
      body = (L.salonWin(D, d) ? '' : '<div class="lk-day-closed">V tento den je salon zavřený. <button class="k-link" type="button" data-act="calGo" data-arg="' + nextOpen(d, false) + '">Další otevřený den: ' + esc(TS.fmtLong(nextOpen(d, false))) + '</button></div>') + dayGrid(d);
    } else {
      var all7 = []; for (var i = 0; i < 7; i++) all7.push(TS.addDays(ws, i));
      if (!L.st(D, ui.cst)) ui.cst = D.team[0].id;   /* týden vždy pro jednu stylistku, souběh všech ukazuje den po stylistkách */
      var chips = '<div class="k-filters" role="group" aria-label="Týden stylistky">' + D.team.map(function (s) { return '<button class="k-chip" type="button" data-act="cst" data-arg="' + s.id + '" aria-pressed="' + (ui.cst === s.id) + '">' + dot(s) + ' ' + esc(first(s.n)) + '</button>'; }).join('') + '</div>';
      var strip = '<div class="k-daystrip">' + all7.map(function (x) {
        var n = D.bookings.filter(function (b) { return b.date === x && active(b); }).length, open = !!L.salonWin(D, x);
        return '<button type="button" class="k-daychip' + (open ? '' : ' off') + '" data-act="calGo" data-arg="' + x + '|keep" aria-pressed="' + (x === d) + '"><span>' + TS.WDS[TS.wd(x)] + '</span><strong>' + TS.fmtDM(x) + '</strong><small>' + (open ? n + '×' : 'zavř.') + '</small></button>';
      }).join('') + '</div>';
      body = chips + weekGrid(ws) + '<div class="k-dv">' + strip + dayGrid(d) + '</div>';
    }
    return app.head('Kalendář', esc(title), headActs()) + bar + legend + body;
  }

  /* ===================== rezervace ===================== */
  function conflictOf(b) {
    if (!(b.status === 'pending' || b.status === 'confirmed') || isPast(b)) return null;
    var st = stOf(b); if (!st) return 'stylistka neexistuje';
    if (L.away(st, b.date)) return (L.away(st, b.date).note || 'nepřítomnost').toLowerCase();
    var w = L.stWin(D, st, b.date); if (!w || b.start < w[0] || b.start + b.dur > w[1]) return 'mimo směnu';
    return null;
  }
  function resRow(b) {
    var cf = conflictOf(b);
    return '<button type="button" class="k-row" style="--cols:118px minmax(0,1fr) minmax(0,1.1fr) 96px 130px" data-act="openRes" data-arg="' + b.id + '">' +
      '<span class="k-tnum"><b>' + esc(TS.fmtShort(b.date)) + '</b><small>' + TS.hm(b.start) + '–' + TS.hm(b.start + b.dur) + '</small></span>' +
      '<span><b>' + esc(b.name) + '</b><small class="k-tnum">' + esc(b.phone) + '</small></span>' +
      '<span class="k-hide-m">' + esc(L.svcLabel(D, b)) + '<small>' + L.priceTxt(b.price) + (b.shade ? ' · ' + esc(L.shadeTxt(D, b)) : '') + '</small></span>' +
      '<span class="k-hide-m">' + stTag(stOf(b)) + '</span><span class="k-end" style="display:grid;gap:4px;justify-items:end">' + pill(b) + (cf ? '<span class="k-pill k-warn">' + esc(cf) + '</span>' : '') + '</span></button>';
  }
  function mRezervace(app) {
    ui.rf = ui.rf || 'upcoming'; ui.rst = ui.rst || 'all';
    var q = TS.norm(ui.rq || ''), rows = D.bookings.slice();
    var F = {
      upcoming: function (b) { return (b.status === 'pending' || b.status === 'confirmed') && !isPast(b); },
      pending: function (b) { return b.status === 'pending'; },
      open: function (b) { return b.status === 'confirmed' && isPast(b); },
      past: function (b) { return b.status === 'done'; },
      noshow: function (b) { return b.status === 'noshow'; },
      closed: function (b) { return b.status === 'rejected' || b.status === 'cancelled'; },
      conflict: function (b) { return !!conflictOf(b); }
    };
    rows = rows.filter(F[ui.rf] || F.upcoming);
    if (ui.rst !== 'all') rows = rows.filter(function (b) { return b.st === ui.rst; });
    if (q) rows = rows.filter(function (b) { return TS.norm(b.name + ' ' + b.phone + ' ' + b.email + ' ' + b.svcName + ' ' + b.note).indexOf(q) >= 0; });
    var desc = ui.rf === 'past' || ui.rf === 'noshow' || ui.rf === 'closed' || ui.rf === 'open';
    rows.sort(function (a, b) { return (L.order(a) < L.order(b) ? -1 : 1) * (desc ? -1 : 1); });
    var cnt = function (k) { return D.bookings.filter(F[k]).length; };
    var chip = function (k, l) { var n = cnt(k); return '<button class="k-chip" type="button" data-act="rf" data-arg="' + k + '" aria-pressed="' + (ui.rf === k) + '">' + l + (n && (k === 'pending' || k === 'open' || k === 'conflict') ? ' <span class="k-muted">' + n + '</span>' : '') + '</button>'; };
    var total = rows.length; rows = rows.slice(0, ui.rmore ? 400 : 60);
    return app.head('Seznam', 'Rezervace', headActs()) +
      '<div class="k-filters">' + chip('upcoming', 'Nadcházející') + chip('pending', 'Čeká na potvrzení') + chip('open', 'K uzavření') + chip('past', 'Proběhlé') + chip('noshow', 'Nedostavily se') + chip('closed', 'Zrušené a odmítnuté') + (cnt('conflict') ? chip('conflict', 'Kolize se směnou') : '') + '</div>' +
      '<div class="k-filters"><select class="k-inp k-sm" style="width:auto" data-bind="ui.rst" data-rr aria-label="Stylistka">' + f.opts([['all', 'Všechny stylistky']].concat(stOpts()), ui.rst) + '</select>' +
      '<label class="k-sr" for="rq">Hledat</label><input class="k-inp k-search" id="rq" type="search" placeholder="Hledat jméno, telefon, službu" value="' + esc(ui.rq || '') + '" data-bind="ui.rq" data-live></div>' +
      (ui.rf === 'conflict' ? '<div class="k-alert k-warn" style="margin-bottom:12px">Tyto rezervace spadají do nepřítomnosti stylistky nebo mimo její směnu. Přesuňte je na jiný čas nebo k jiné stylistce.</div>' : '') +
      (rows.length ? '<div class="k-rows">' + rows.map(resRow).join('') + '</div>' + (total > rows.length ? '<p style="margin-top:12px"><button class="k-btn k-sec k-sm" type="button" data-act="rmore">Zobrazit dalších ' + (total - rows.length) + '</button></p>' : '') : '<p class="k-empty">' + (q ? 'Nic neodpovídá hledání „' + esc(ui.rq) + '“.' : 'V tomto seznamu nic není.') + '</p>');
  }

  /* --- detail rezervace a jeho režimy --- */
  function kvRows(rows) { return '<dl class="k-kv">' + rows.filter(Boolean).map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + r[1] + '</dd>'; }).join('') + '</dl>'; }
  function slotPicker(o, bind, exceptId) {   /* o: {svc,len,ex,st,date,t,manualOn,manual} */
    var t = TS.today(), chips = '', n = 0, i = 0;
    while (n < 12 && i < 60) {
      var d = TS.addDays(t, i++), sw = L.salonWin(D, d); if (!sw || (d === t && TS.nowMin() >= sw[1])) continue; n++;
      var c = L.slots(D, d, o.svc, o.len, o.ex, o.st, exceptId, { admin: true }).filter(function (x) { return d !== t || x.t >= TS.nowMin(); }).length;
      chips += '<button type="button" class="k-daychip" data-act="mPickDate" data-arg="' + d + '" aria-pressed="' + (o.date === d) + '"><span>' + TS.WDS[TS.wd(d)] + '</span><strong>' + TS.fmtDM(d) + '</strong><small>' + (c ? c + ' ' + TS.plural(c, 'čas', 'časy', 'časů') : 'plno') + '</small></button>';
    }
    var h = '<div><p class="k-lbl" style="margin-bottom:6px">Den</p><div class="k-daystrip">' + chips + '</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><label class="k-small k-muted" for="m-date">Jiné datum</label><input class="k-inp k-sm" id="m-date" type="date" style="width:auto" value="' + esc(o.date || '') + '" data-bind="' + bind + '.date" data-rr></div></div>';
    if (o.date) {
      var sl = L.slots(D, o.date, o.svc, o.len, o.ex, o.st, exceptId, { admin: true }).filter(function (x) { return o.date !== t || x.t >= TS.nowMin(); });
      h += '<div><p class="k-lbl" style="margin-bottom:6px">Volné časy · ' + esc(TS.fmtLong(o.date)) + '</p>' + (sl.length ? '<div class="k-slots">' + sl.map(function (x) {
        var who = o.st === 'any' ? L.st(D, L.pickSt(D, o.date, x.sts)) : null;
        return '<button type="button" class="k-slot" data-act="mPickTime" data-arg="' + x.t + '" aria-pressed="' + (!o.manualOn && o.t === x.t) + '"' + (who ? ' title="' + esc(who.n) + '"' : '') + '>' + TS.hm(x.t) + (who ? '<br><small style="font-weight:500;font-size:11px">' + esc(first(who.n)) + '</small>' : '') + '</button>';
      }).join('') + '</div>' : '<p class="k-muted">' + (L.salonWin(D, o.date) ? 'Tento den už pro tuto službu není volno' + (o.st !== 'any' ? ' u vybrané stylistky' : '') + '.' : 'Tento den je salon zavřený.') + '</p>');
      h += '<div style="margin-top:10px">' + (o.manualOn ? '<label class="k-lbl" for="m-manual">Vlastní čas začátku</label><input class="k-inp" id="m-manual" type="time" step="300" style="max-width:150px;margin-top:6px" value="' + esc(o.manual || '') + '" data-bind="' + bind + '.manual" data-rr>' + manualWarn(o, exceptId) + '<p style="margin-top:6px"><button class="k-link" type="button" data-act="mManual" data-arg="0">Zpět na volné časy</button></p>'
        : '<button class="k-link" type="button" data-act="mManual" data-arg="1">Zadat jiný čas ručně</button>') + '</div></div>';
    }
    return h;
  }
  function manualWarn(o, exceptId) {
    var t = TS.parseHM(o.manual); if (t == null || !o.date) return '';
    var st = o.st === 'any' ? D.team[0].id : o.st, c = L.calc(D, o.svc, o.len, o.ex), x = L.conflicts(D, o.date, t, c.dur, c.buf, st, exceptId), notes = [];
    if (o.st === 'any') return '<p class="k-hint">U ručního času vyberte konkrétní stylistku.</p>';
    if (x.away) notes.push('Stylistka je ten den nepřítomná (' + esc(x.away.note || '') + ').'); else if (!x.inHours) notes.push('Mimo směnu stylistky.');
    x.items.forEach(function (s) { notes.push('Překrývá se: ' + esc(s) + '.'); });
    return notes.length ? '<div class="k-alert k-warn" style="margin-top:8px">' + notes.join(' ') + ' Uložit to půjde, ale zkontrolujte to prosím.</div>' : '<p class="k-hint" style="margin-top:6px">Čas je volný.</p>';
  }
  function effStart(o) { return o.manualOn ? TS.parseHM(o.manual) : o.t; }
  function svcFields(o, bind) {
    var s = L.svc(D, o.svc), exs = L.extrasFor(D, o.svc), c = L.calc(D, o.svc, o.len, o.ex);
    return '<div class="k-grid2">' + f.select('m-svc', bind + '.svc', 'Služba', o.svc, svcOpts(), 'data-rr') +
      (s && !L.flat(s) ? f.select('m-len', bind + '.len', 'Délka vlasů', o.len, L.LEN.map(function (l, i) { return [i, l]; }), 'data-num data-rr') : '<div></div>') +
      f.select('m-st', bind + '.st', 'Stylistka', o.st, stOpts(true).filter(function (x) { return x[0] === 'any' || L.canDo(L.st(D, x[0]), o.svc); }), 'data-rr') +
      (s && s.color ? f.select('m-shade', bind + '.shade', 'Odstín', o.shade || 'poradit', [['poradit', 'Poradíme na místě']].concat(D.shades.map(function (x) { return [x.id, x.c + ' ' + x.n]; }))) : '<div></div>') + '</div>' +
      (exs.length ? '<div class="lk-chips">' + exs.map(function (x) { return '<label class="k-check"><input type="checkbox" data-act="mEx" data-arg="' + x.id + '"' + (o.ex.indexOf(x.id) >= 0 ? ' checked' : '') + '><span>' + esc(x.n) + ' (+' + TS.money(x.p[o.len]) + ', +' + x.t[o.len] + ' min)</span></label>'; }).join('') + '</div>' : '') +
      '<p class="k-muted k-small">Délka ' + L.durTxt(c.dur) + (c.buf ? ' + ' + c.buf + ' min příprava' : '') + ' · cena ' + L.priceTxt(c.price) + '</p>';
  }
  function mRes() {
    var m = ui.m, b = byId(m.id); if (!b) return TS.mShell('Rezervace nenalezena', '', '<p>Rezervace už neexistuje.</p>');
    var st = stOf(b), sub = esc(b.name) + ' · ' + esc(TS.cap(TS.fmtLong(b.date))) + ' v ' + TS.hm(b.start);
    if (m.mode === 'reject' || m.mode === 'cancel') {
      var rej = m.mode === 'reject', chips = rej ? ['Tento den máme bohužel plno, zkuste prosím příští týden.', 'Na tuto službu potřebujeme nejdřív konzultaci, zavoláme vám.', 'Stylistka bude v tento den na školení.'] : ['Omlouváme se, stylistka onemocněla.', 'Rušíme na vaše přání.', 'Z provozních důvodů musíme termín zrušit.'];
      return TS.mShell(rej ? 'Odmítnout žádost' : 'Zrušit rezervaci', sub,
        '<p>' + esc(L.svcLabel(D, b)) + (st ? ' · ' + esc(st.n) : '') + '</p>' +
        '<div class="k-field"><label for="m-msg">Zpráva pro klientku (nepovinné)</label><textarea class="k-inp" id="m-msg" data-bind="ui.m.msg">' + esc(m.msg || '') + '</textarea>' +
        '<div class="k-filters" style="margin:6px 0 0">' + chips.map(function (c) { return '<button class="k-chip" type="button" data-act="msgChip" data-arg="' + esc(c) + '">' + esc(c) + '</button>'; }).join('') + '</div></div>' +
        (b.email ? f.check('m-notify', 'ui.m.notify', 'Poslat klientce e-mail na ' + esc(b.email), m.notify !== false) : '<p class="k-muted">Klientka nezadala e-mail, dejte jí prosím vědět telefonem na ' + esc(b.phone) + '.</p>') +
        '<p class="k-hint">Čas se na webu hned znovu uvolní.</p>',
        '<button class="k-btn k-ghost" type="button" data-act="resMode" data-arg="">Zpět</button><button class="k-btn k-pri" type="button" data-act="' + (rej ? 'rejectDo' : 'cancelDo') + '">' + (rej ? 'Odmítnout žádost' : 'Zrušit rezervaci') + '</button>', '', 'Rezervace');
    }
    if (m.mode === 'move') {
      var o = m.mv, start = effStart(o);
      return TS.mShell(b.status === 'pending' ? 'Potvrdit v jiném čase' : 'Přesunout termín', esc(b.name) + ' · původně ' + esc(TS.fmtShort(b.date)) + ' v ' + TS.hm(b.start) + (st ? ' u ' + esc(st.gen) : ''),
        svcFields(o, 'ui.m.mv') + slotPicker(o, 'ui.m.mv', b.id) +
        (b.email ? f.check('m-notify', 'ui.m.mv.notify', 'Poslat klientce e-mail s novým termínem', o.notify !== false) + (o.notify !== false ? '<div class="k-field"><label for="m-msg">Zpráva pro klientku (nepovinné)</label><textarea class="k-inp" id="m-msg" data-bind="ui.m.mv.msg" placeholder="Například: v 10:00 už máme plno, nabízíme 14:00.">' + esc(o.msg || '') + '</textarea></div>' : '') : '<p class="k-muted">Klientka nemá e-mail, změnu jí prosím oznamte telefonicky.</p>') +
        (o.err ? '<div class="k-alert k-bad">' + esc(o.err) + '</div>' : ''),
        '<button class="k-btn k-ghost" type="button" data-act="resMode" data-arg="">Zpět</button><button class="k-btn k-pri" type="button" data-act="moveSave"' + (start == null || !o.date ? ' disabled' : '') + '>' + (b.status === 'pending' ? 'Potvrdit v tomto čase' : 'Uložit změnu') + '</button>', 'wide', 'Přesunout termín');
    }
    var exn = L.exNames(D, b), sh = b.shade ? L.shade(D, b.shade) : null, cf = conflictOf(b);
    var kv = kvRows([
      ['Stav', pill(b) + (cf ? ' <span class="k-pill k-warn">' + esc(cf) + '</span>' : '')],
      ['Služba', esc(L.svcLabel(D, b)) + (exn.length ? '<br><span class="k-muted">+ ' + esc(exn.join(', ')) + '</span>' : '')],
      b.shade ? ['Odstín', (sh ? '<i class="lk-sw" style="background:linear-gradient(135deg,' + sh.a + ',' + sh.b + ')"></i>' : '') + esc(L.shadeTxt(D, b))] : null,
      ['Stylistka', st ? dot(st) + ' ' + esc(st.n) + (b.stAny ? ' <span class="k-muted">(klientka nechala výběr na salonu)</span>' : '') : '–'],
      ['Termín', esc(TS.cap(TS.fmtLong(b.date))) + ', ' + TS.hm(b.start) + '–' + TS.hm(b.start + b.dur) + (b.buf ? ' <span class="k-muted">(+ ' + b.buf + ' min příprava)</span>' : '')],
      ['Cena', L.priceTxt(b.price) + ' <span class="k-muted">orientačně, skutečnou zadáte na doklad</span>'],
      ['Klientka', esc(b.name)],
      ['Telefon', '<a href="tel:' + esc(b.phone.replace(/\s/g, '')) + '">' + esc(b.phone) + '</a> <button class="k-link" type="button" data-act="copy" data-arg="' + esc(b.phone) + '">Kopírovat</button>'],
      ['E-mail', b.email ? esc(b.email) : '<span class="k-muted">nezadán</span>'],
      b.note ? ['Poznámka', '<i>„' + esc(b.note) + '“</i>'] : null,
      b.receipt ? ['Doklad', 'č. ' + esc(b.receipt.no) + ' · ' + TS.money(b.receipt.total) + ' · ' + L.PAY[b.receipt.pay].toLowerCase() + ' <button class="k-link" type="button" data-act="openReceipt" data-arg="' + b.id + '">Zobrazit</button>'] : null,
      ['Odkud', b.source === 'web' ? 'Rezervace z webu' : 'Zapsáno v salonu'],
      b.reminded ? ['Připomínka', 'odeslána'] : null
    ]);
    var foot, gc = '<a class="k-btn k-sec" href="' + esc(TS.cal.gcal(L.event(D, b, true))) + '" target="_blank" rel="noopener">Do Google Kalendáře</a>';
    if (b.status === 'pending') foot = '<button class="k-btn k-danger" type="button" data-act="resMode" data-arg="reject">Odmítnout</button><span class="k-sp"></span><button class="k-btn k-sec" type="button" data-act="resMode" data-arg="move">Přesunout</button><button class="k-btn k-pri" type="button" data-act="confirm" data-arg="' + b.id + '">' + ic('check') + 'Potvrdit</button>';
    else if (b.status === 'confirmed' && !started(b)) foot = '<button class="k-btn k-danger" type="button" data-act="resMode" data-arg="cancel">Zrušit</button><span class="k-sp"></span>' + gc + '<button class="k-btn k-pri" type="button" data-act="resMode" data-arg="move">Přesunout</button>';
    else if (b.status === 'confirmed') foot = '<button class="k-btn k-danger" type="button" data-act="noshow" data-arg="' + b.id + '">Nedostavila se</button><span class="k-sp"></span><button class="k-btn k-sec" type="button" data-act="markDone" data-arg="' + b.id + '">Proběhlo bez dokladu</button><button class="k-btn k-pri" type="button" data-act="openReceipt" data-arg="' + b.id + '">' + ic('receipt') + 'Vystavit doklad</button>';
    else if (b.status === 'done') foot = '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zavřít</button><span class="k-sp"></span><button class="k-btn k-sec" type="button" data-act="rebook" data-arg="' + b.id + '">Objednat znovu</button><button class="k-btn k-pri" type="button" data-act="openReceipt" data-arg="' + b.id + '">' + ic('receipt') + (b.receipt ? 'Zobrazit doklad' : 'Vystavit doklad') + '</button>';
    else if (b.status === 'noshow') foot = '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zavřít</button><span class="k-sp"></span><button class="k-btn k-sec" type="button" data-act="markDone" data-arg="' + b.id + '">Přece jen proběhlo</button><button class="k-btn k-pri" type="button" data-act="rebook" data-arg="' + b.id + '">Objednat znovu</button>';
    else foot = '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zavřít</button><span class="k-sp"></span><button class="k-btn k-sec" type="button" data-act="rebook" data-arg="' + b.id + '">Objednat znovu</button>';
    return TS.mShell(esc(b.name), esc(L.svcLabel(D, b)) + ' · ' + esc(TS.cap(TS.fmtShort(b.date))) + ' v ' + TS.hm(b.start), kv +
      '<div><p class="k-lbl" style="margin-bottom:6px">Historie</p><ul class="k-log">' + (b.log || []).slice().reverse().map(function (l) { return '<li><time>' + TS.fmtStamp(l.at) + '</time><span>' + esc(l.t) + '</span></li>'; }).join('') + '</ul></div>', foot, 'wide', 'Detail rezervace');
  }

  /* --- nová rezervace / blokace --- */
  function mNew() {
    var n = ui.m.n, tabs = '<div class="k-mtabs"><button type="button" data-act="nKind" data-arg="booking" aria-pressed="' + (n.kind === 'booking') + '">Rezervace</button><button type="button" data-act="nKind" data-arg="block" aria-pressed="' + (n.kind === 'block') + '">Blokace času</button></div>';
    if (n.kind === 'booking') {
      var e = n.errs || {};
      return TS.mShell('Nová rezervace', 'Například když klientka zavolá nebo se objedná u pultu. Rovnou se potvrdí a čas zmizí z webu.', tabs + svcFields(n, 'ui.m.n') + slotPicker(n, 'ui.m.n', null) +
        '<div class="k-grid2"><div class="k-full k-field' + (e.name ? ' k-err' : '') + '"><label for="n-name">Jméno a příjmení</label><input class="k-inp" id="n-name" value="' + esc(n.name) + '" data-bind="ui.m.n.name" autocomplete="off">' + (e.name ? '<p class="k-ferr">' + e.name + '</p>' : '') + '</div>' +
        '<div class="k-field' + (e.phone ? ' k-err' : '') + '"><label for="n-tel">Telefon</label><input class="k-inp" id="n-tel" type="tel" value="' + esc(n.phone) + '" data-bind="ui.m.n.phone">' + (e.phone ? '<p class="k-ferr">' + e.phone + '</p>' : '') + '</div>' +
        '<div class="k-field' + (e.email ? ' k-err' : '') + '"><label for="n-mail">E-mail (pro potvrzení)</label><input class="k-inp" id="n-mail" type="email" value="' + esc(n.email) + '" data-bind="ui.m.n.email">' + (e.email ? '<p class="k-ferr">' + e.email + '</p>' : '') + '</div>' +
        '<div class="k-full">' + f.text('n-note', 'ui.m.n.note', 'Poznámka', n.note) + '</div></div>' +
        f.check('n-notify', 'ui.m.n.notify', 'Poslat klientce potvrzení e-mailem (pokud zadala e-mail)', n.notify !== false) + (n.err ? '<div class="k-alert k-bad">' + esc(n.err) + '</div>' : ''),
        '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zrušit</button><button class="k-btn k-pri" type="button" data-act="newSave"' + (effStart(n) == null || !n.date ? ' disabled' : '') + '>Uložit rezervaci</button>', 'wide', 'Nová rezervace');
    }
    var from = n.allDay ? 0 : n.bfrom, to = n.allDay ? 1440 : n.bto;
    var hits = n.bdate ? D.bookings.filter(function (b) { return b.date === n.bdate && (b.status === 'pending' || b.status === 'confirmed') && (n.bst === 'all' || b.st === n.bst) && b.start < to && from < b.start + b.dur; }) : [];
    return TS.mShell('Zablokovat čas', 'Lékař, školení nebo soukromá věc. Web tento čas přestane nabízet. Celé dny dovolené zadáte v části Tým.', tabs +
      '<div class="k-grid2">' + f.select('b-st', 'ui.m.n.bst', 'Pro koho', n.bst, [['all', 'Celý salon']].concat(stOpts()), 'data-rr') +
      '<div class="k-field"><label for="b-date">Den</label><input class="k-inp" id="b-date" type="date" value="' + esc(n.bdate || '') + '" min="' + TS.today() + '" data-bind="ui.m.n.bdate" data-rr></div>' +
      '<div class="k-full">' + f.check('b-all', 'ui.m.n.allDay', 'Celý den', n.allDay, 'data-rr') + '</div>' +
      (n.allDay ? '' : f.select('b-from', 'ui.m.n.bfrom', 'Od', n.bfrom, tOpts(420, 1320, 15), 'data-num data-rr') + f.select('b-to', 'ui.m.n.bto', 'Do', n.bto, tOpts(435, 1380, 15), 'data-num data-rr')) +
      '<div class="k-full">' + f.text('b-reason', 'ui.m.n.reason', 'Důvod (vidí jen salon)', n.reason) + '<div class="k-filters" style="margin:6px 0 0">' + ['Lékař', 'Školení', 'Soukromé', 'Focení účesů', 'Údržba'].map(function (r) { return '<button class="k-chip" type="button" data-act="nReason" data-arg="' + r + '">' + r + '</button>'; }).join('') + '</div></div></div>' +
      (hits.length ? '<div class="k-alert k-warn">V tomto čase ' + TS.plural(hits.length, 'je', 'jsou', 'je') + ' ' + hits.length + ' ' + TS.plural(hits.length, 'rezervace', 'rezervace', 'rezervací') + ' (' + hits.map(function (b) { return TS.hm(b.start) + ' ' + esc(b.name); }).join(', ') + '). Blokace je nezruší, vyřešte je prosím zvlášť.</div>' : '<p class="k-hint">Na webu se tento čas přestane nabízet.</p>') +
      (n.err ? '<div class="k-alert k-bad">' + esc(n.err) + '</div>' : ''),
      '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zrušit</button><button class="k-btn k-pri" type="button" data-act="blockSave"' + (n.bdate ? '' : ' disabled') + '>Zablokovat</button>', 'wide', 'Zablokovat čas');
  }

  /* ===================== doklady ===================== */
  function nextNo(date) {
    var y = date.slice(0, 4), max = y === TS.today().slice(0, 4) ? D.rcSeq || 0 : 0;
    receipts().forEach(function (b) { var no = b.receipt.no; if (no.indexOf(y + '-') === 0) max = Math.max(max, parseInt(no.slice(5), 10) || 0); });
    return y + '-' + String(max + 1).padStart(4, '0');
  }
  function mDoklady(app) {
    ui.df = ui.df || 'week';
    var t = TS.today(), ws = TS.weekStart(t), ms = t.slice(0, 8) + '01', all = receipts();
    var sum = function (arr) { return arr.reduce(function (a, b) { return a + b.receipt.total; }, 0); };
    var td = all.filter(function (b) { return b.receipt.issueDate === t; }), wk = all.filter(function (b) { return b.receipt.issueDate >= ws; }), mo = all.filter(function (b) { return b.receipt.issueDate >= ms; });
    var cardSum = sum(wk.filter(function (b) { return b.receipt.pay === 'karta'; })), cashSum = sum(wk.filter(function (b) { return b.receipt.pay === 'hotove'; }));
    var ti = toIssue(), q = TS.norm(ui.dq || '');
    var list = ui.df === 'today' ? td : ui.df === 'week' ? wk : ui.df === 'month' ? mo : all;
    if (q) list = list.filter(function (b) { return TS.norm(b.receipt.no + ' ' + b.name + ' ' + b.svcName).indexOf(q) >= 0; });
    list = list.slice().sort(function (a, b) { return a.receipt.no < b.receipt.no ? 1 : -1; });
    var dTotal = list.length; list = list.slice(0, ui.dmore ? 600 : 40);
    var chip = function (k, l) { return '<button class="k-chip" type="button" data-act="df" data-arg="' + k + '" aria-pressed="' + (ui.df === k) + '">' + l + '</button>'; };
    return app.head('Pokladna', 'Doklady', '') +
      '<div class="k-stats"><div class="k-stat"><b>' + TS.money(sum(td)) + '</b><span>dnes · ' + td.length + ' ' + TS.plural(td.length, 'doklad', 'doklady', 'dokladů') + '</span></div>' +
      '<div class="k-stat"><b>' + TS.money(sum(wk)) + '</b><span>tento týden</span></div><div class="k-stat"><b>' + TS.money(cardSum) + '</b><span>týden kartou</span></div><div class="k-stat"><b>' + TS.money(cashSum) + '</b><span>týden hotově</span></div></div>' +
      '<section class="k-panel" style="margin-bottom:18px"><h2>Čeká na doklad ' + (ti.length ? '<span class="k-count">' + ti.length + '</span>' : '') + '</h2><p class="k-sub">Návštěvy, které už začaly nebo proběhly. Doklad vystavíte se skutečnou cenou, prodanou kosmetikou i slevou.</p>' +
      (ti.length ? '<div class="k-rows">' + ti.map(function (b) {
        return '<div class="k-row" style="--cols:120px minmax(0,1fr) minmax(0,1fr) auto"><span class="k-tnum"><b>' + esc(TS.fmtShort(b.date)) + '</b><small>' + TS.hm(b.start) + '–' + TS.hm(b.start + b.dur) + '</small></span><span><b>' + esc(b.name) + '</b><small>' + esc(L.svcLabel(D, b)) + '</small></span><span class="k-hide-m">' + stTag(stOf(b)) + '<small>orientačně ' + L.priceTxt(b.price) + '</small></span>' +
          '<span style="display:flex;gap:6px"><button class="k-btn k-ghost k-sm" type="button" data-act="openRes" data-arg="' + b.id + '">Detail</button><button class="k-btn k-pri k-sm" type="button" data-act="openReceipt" data-arg="' + b.id + '">' + ic('receipt') + 'Vystavit</button></span></div>';
      }).join('') + '</div>' : '<p class="k-empty">Všechny návštěvy mají doklad.</p>') + '</section>' +
      '<div class="k-filters">' + chip('today', 'Dnes') + chip('week', 'Tento týden') + chip('month', 'Tento měsíc') + chip('all', 'Všechny') +
      '<label class="k-sr" for="dq">Hledat doklad</label><input class="k-inp k-search" id="dq" type="search" placeholder="Číslo dokladu nebo jméno" value="' + esc(ui.dq || '') + '" data-bind="ui.dq" data-live></div>' +
      (list.length ? '<div class="k-rows">' + list.map(function (b) {
        var r = b.receipt;
        return '<button type="button" class="k-row" style="--cols:110px 100px minmax(0,1fr) 110px 120px" data-act="openReceipt" data-arg="' + b.id + '"><span class="k-tnum"><b>' + esc(r.no) + '</b></span><span class="k-tnum">' + TS.fmtDMY(r.issueDate) + '</span><span><b>' + esc(b.name) + '</b><small>' + esc(L.svcLabel(D, b)) + ' · ' + esc(first(r.stylist)) + '</small></span><span class="k-hide-m"><span class="k-pill k-' + (r.pay === 'karta' ? 'accent' : 'done') + '">' + L.PAY[r.pay] + '</span></span><span class="k-end lk-total">' + TS.money(r.total) + '</span></button>';
      }).join('') + '</div>' + (dTotal > list.length ? '<p style="margin-top:12px"><button class="k-btn k-sec k-sm" type="button" data-act="dmore">Zobrazit dalších ' + (dTotal - list.length) + '</button></p>' : '') : '<p class="k-empty">Za vybrané období nejsou žádné doklady.</p>');
  }
  function mReceipt() {
    var m = ui.m, b = byId(m.id); if (!b) return TS.mShell('Doklad', '', '<p>Rezervace nenalezena.</p>');
    var sub = esc(b.name) + ' · ' + esc(L.svcLabel(D, b)) + ', ' + TS.fmtDMY(b.date);
    if (b.receipt) {
      return TS.mShell('Doklad č. ' + esc(b.receipt.no), sub, '<div class="lk-doc-wrap">' + L.docHTML(b.receipt) + '</div><p class="k-hint">Vystavený doklad se už nemění a jeho číslo zůstává v číselné řadě.' + (b.receiptSent ? ' Klientce byl odeslán e-mailem.' : '') + '</p>',
        '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zavřít</button><span class="k-sp"></span>' + (b.email ? '<button class="k-btn k-sec" type="button" data-act="rcMail" data-arg="' + b.id + '">' + ic('send') + (b.receiptSent ? 'Poslat znovu' : 'Poslat e-mailem') + '</button>' : '') +
        '<button class="k-btn k-sec" type="button" data-act="rcPrint" data-arg="' + b.id + '">' + ic('print') + 'Vytisknout</button><button class="k-btn k-pri" type="button" data-act="pdfDl" data-arg="' + b.id + '">' + ic('download') + 'Stáhnout PDF</button>', 'wide', 'Doklad');
    }
    var r = m.rc, bill = D.billing, missing = !bill.name || !bill.ico || !bill.seat;
    var items = r.items.filter(function (i) { return String(i.name).trim(); });
    var preview = L.mkReceipt(D, b, nextNo(r.issueDate || TS.today()), r.issueDate || TS.today(), r.pay, items.length ? items : [{ name: '–', qty: 1, price: 0 }], { type: r.dtype, v: r.dv, label: r.dlabel });
    var rows = r.items.map(function (it, i) {
      return '<div class="lk-rc-row"><input class="k-inp k-sm" id="rc-' + i + '-n" value="' + esc(it.name) + '" placeholder="Název položky" aria-label="Položka" data-bind="ui.m.rc.items.' + i + '.name" data-live>' +
        '<input class="k-inp k-sm" id="rc-' + i + '-q" inputmode="decimal" value="' + esc(it.qty) + '" aria-label="Množství" data-bind="ui.m.rc.items.' + i + '.qty" data-live>' +
        '<input class="k-inp k-sm" id="rc-' + i + '-p" inputmode="decimal" value="' + esc(it.price) + '" aria-label="Cena za kus v Kč" data-bind="ui.m.rc.items.' + i + '.price" data-live>' +
        (r.items.length > 1 ? '<button class="k-icon-btn" type="button" data-act="rcDel" data-arg="' + i + '" aria-label="Odebrat položku">' + ic('x') + '</button>' : '<span></span>') + '</div>';
    }).join('');
    return TS.mShell('Vystavit doklad', sub,
      (missing ? '<div class="k-alert k-warn">Na doklad chybí údaje salonu (jméno, IČO nebo sídlo). Doplňte je v části Kontakty a údaje.</div>' : '') +
      '<div><div class="lk-rc-row lk-rc-head"><span>Položka</span><span>Množství</span><span>Cena za ks</span><span></span></div>' + rows +
      '<div class="k-filters" style="margin:10px 0 0"><button class="k-btn k-sec k-sm" type="button" data-act="rcAdd">' + ic('plus') + 'Položka</button><span class="k-muted k-small">Prodej kosmetiky:</span>' +
      L.PRODUCTS.map(function (p, i) { return '<button class="k-chip" type="button" data-act="rcProd" data-arg="p' + i + '">' + esc(p[0].replace(/ \d+ ml$/, '')) + '</button>'; }).join('') + '</div>' +
      '<div class="k-filters" style="margin:6px 0 0"><span class="k-muted k-small">Materiál navíc:</span>' + L.MATERIALS.map(function (p, i) { return '<button class="k-chip" type="button" data-act="rcProd" data-arg="m' + i + '">' + esc(p[0]) + ' · ' + TS.money(p[1]) + '</button>'; }).join('') + '</div></div>' +
      '<div class="k-grid3">' + f.select('rc-dt', 'ui.m.rc.dtype', 'Sleva', r.dtype, [['pct', 'v procentech'], ['kc', 'v korunách']], 'data-rr') + f.text('rc-dv', 'ui.m.rc.dv', r.dtype === 'pct' ? 'Výše slevy (%)' : 'Výše slevy (Kč)', r.dv, 'inputmode="decimal" data-live') + f.text('rc-dl', 'ui.m.rc.dlabel', 'Popis slevy', r.dlabel, 'data-live') +
      f.select('rc-pay', 'ui.m.rc.pay', 'Způsob úhrady', r.pay, [['karta', 'Kartou'], ['hotove', 'Hotově'], ['prevod', 'Převodem']], 'data-rr') +
      '<div class="k-field"><label for="rc-date">Datum vystavení</label><input class="k-inp" id="rc-date" type="date" value="' + esc(r.issueDate) + '" data-bind="ui.m.rc.issueDate" data-rr></div><div></div></div>' +
      (b.email ? f.check('rc-send', 'ui.m.rc.send', 'Po vystavení poslat doklad klientce na ' + esc(b.email), r.send !== false) : '') +
      '<div><p class="k-lbl" style="margin-bottom:6px">Náhled dokladu</p><div class="lk-doc-wrap">' + L.docHTML(preview) + '</div></div>' + (r.err ? '<div class="k-alert k-bad">' + esc(r.err) + '</div>' : ''),
      '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zrušit</button><button class="k-btn k-pri" type="button" data-act="rcIssue"' + (missing ? ' disabled' : '') + '>Vystavit doklad · ' + TS.money(preview.total) + '</button>', 'wide', 'Vystavit doklad');
  }
  /* doklad v PDF: jsPDF a písma Lokny (Outfit, DM Serif Display) se načtou až při stažení */
  function receiptPdf(make, r) {
    var doc = make(), s = r.supplier, c = L.rcCalc(r.items, r.disc, s.vatPayer, s.vatRate);
    var title = s.vatPayer ? 'Zjednodušený daňový doklad' : 'Doklad o zaplacení';
    doc.setProperties({ title: title + ' č. ' + r.no, subject: s.salon + ', ' + r.customer.name, author: s.name, creator: s.salon });
    var PW = doc.internal.pageSize.getWidth(), PH = doc.internal.pageSize.getHeight(), Lm = 56, R = PW - 56, CW = R - Lm, LH = 13.4, BOTTOM = PH - 64;
    var INK = [46, 26, 38], MUTED = [122, 94, 104], LINE = [234, 215, 206], ROSE = [176, 63, 99];
    var font = function (n, sz, col) { doc.setFont(n, 'normal'); doc.setFontSize(sz); doc.setTextColor.apply(doc, col || INK); };
    var label = function (t, x, y, right, col) { font('OutfitSemi', 7, col || MUTED); t = t.toUpperCase(); var w = doc.getTextWidth(t) + .9 * (t.length - 1); doc.text(t, right ? x - w : x, y, { charSpace: .9 }); };
    var rule = function (y, w, col, x1, x2) { doc.setDrawColor.apply(doc, col || LINE); doc.setLineWidth(w || .6); doc.line(x1 == null ? Lm : x1, y, x2 == null ? R : x2, y); };
    var para = function (t, x, y, w) { doc.splitTextToSize(String(t), w).forEach(function (ln) { doc.text(ln, x, y); y += LH; }); return y; };
    var note = function () { font('Outfit', 7.5, MUTED); doc.text('Ukázkový doklad z ukázkového webu studia T&S. Salon, osoby i všechny údaje jsou smyšlené.', PW / 2, PH - 30, { align: 'center' }); };
    var y = 60;
    font('DMSerif', 30); doc.text('LOKNA', Lm, y + 24, { charSpace: 8 });
    label(s.salon, Lm, y + 42, false, ROSE);
    font('OutfitSemi', 13); doc.text(title, R, y + 13, { align: 'right' });
    font('Outfit', 10.5, MUTED); doc.text('č. ' + r.no, R, y + 29, { align: 'right' });
    y += 56; rule(y, 1.6, INK);
    y += 22; var xR = Lm + CW * .58, wL = xR - Lm - 20, wR = R - xR;
    label('Dodavatel', Lm, y); label('Odběratel', xR, y); y += 16;
    var yl = y, yr = y;
    font('OutfitSemi', 9.5); yl = para(s.name, Lm, yl, wL);
    font('Outfit', 9.5); ['Sídlo: ' + s.seat, 'IČO: ' + s.ico, s.dic ? 'DIČ: ' + s.dic : '', 'Provozovna: ' + s.place].filter(Boolean).forEach(function (t) { yl = para(t, Lm, yl, wL); });
    yl += 6; ['Tel.: ' + s.phone, 'E-mail: ' + s.email, s.web].filter(Boolean).forEach(function (t) { yl = para(t, Lm, yl, wL); });
    font('OutfitSemi', 9.5); yr = para(r.customer.name, xR, yr, wR);
    if (r.customer.email) { font('Outfit', 9.5); yr = para(r.customer.email, xR, yr, wR); }
    if (r.stylist) { yr += 10; label('Obsluhovala', xR, yr); yr += 14; font('Outfit', 9.5); yr = para(r.stylist, xR, yr, wR); }
    y = Math.max(yl, yr) - 2; rule(y);
    y += 20; var c3 = [Lm, Lm + CW / 3, Lm + 2 * CW / 3];
    label('Datum vystavení', c3[0], y); label('Datum uskutečnění plnění', c3[1], y); label('Způsob úhrady', c3[2], y);
    y += 15; font('OutfitSemi', 10); doc.text(TS.fmtDMY(r.issueDate), c3[0], y); doc.text(TS.fmtDMY(r.taxDate), c3[1], y); doc.text(L.PAY[r.pay], c3[2], y);
    y += 12; rule(y);
    var xQ = R - 178, xP = R - 88;
    var head = function (yy) { label('Položka', Lm, yy); label('Množství', xQ, yy, true); label('Cena za ks', xP, yy, true); label('Celkem', R, yy, true); rule(yy + 8); return yy + 8; };
    y = head(y + 24);
    c.lines.forEach(function (l) {
      font('Outfit', 9.5); var lines = doc.splitTextToSize(l.name, xQ - 70 - Lm);
      if (y + lines.length * LH + 14 > BOTTOM) { note(); doc.addPage(); y = head(64); font('Outfit', 9.5); }
      var yy = y + 17; lines.forEach(function (t, i) { doc.text(t, Lm, yy + i * LH); });
      doc.text(String(l.qty).replace('.', ','), xQ, yy, { align: 'right' }); doc.text(TS.money(l.price), xP, yy, { align: 'right' }); doc.text(TS.money(l.sum), R, yy, { align: 'right' });
      y = yy + (lines.length - 1) * LH + 9; rule(y);
    });
    if (y + 170 > BOTTOM) { note(); doc.addPage(); y = 64; }
    var sx = R - 210; y += 20;
    font('Outfit', 9.5, MUTED);
    if (c.disc) { doc.text('Mezisoučet', sx, y); doc.text(TS.money(c.sub), R, y, { align: 'right' }); y += LH; doc.text(L.discLabel(r.disc), sx, y); doc.text('−' + TS.money(c.disc), R, y, { align: 'right' }); y += LH; }
    if (s.vatPayer) { doc.text('Základ daně', sx, y); doc.text(TS.money2(c.base), R, y, { align: 'right' }); y += LH; doc.text('DPH ' + s.vatRate + ' %', sx, y); doc.text(TS.money2(c.vat), R, y, { align: 'right' }); y += LH; }
    y -= 4; rule(y, 1.6, INK, sx, R);
    y += 24; font('OutfitSemi', 10.5); doc.text('Celkem' + (s.vatPayer ? ' vč. DPH' : ''), sx, y);
    font('DMSerif', 20); doc.text(TS.money(c.total), R, y + 1, { align: 'right' });
    y += 16; font('Outfit', 8.5, MUTED); doc.text('Uhrazeno ' + L.PAY[r.pay].toLowerCase() + ' dne ' + TS.fmtDMY(r.issueDate), R, y, { align: 'right' });
    y += 28; rule(y); y += 18; font('Outfit', 8.5, MUTED);
    var gx = R - 160; para((s.vatPayer ? 'Sazba DPH ' + s.vatRate + ' %. ' : 'Dodavatel není plátcem DPH. ') + (s.register || ''), Lm, y, gx - Lm - 28);
    doc.setDrawColor.apply(doc, INK); doc.setLineWidth(.6); doc.line(gx, y + 30, R, y + 30);
    font('Outfit', 8, MUTED); doc.text('Vystavil(a), podpis a razítko', (gx + R) / 2, y + 43, { align: 'center' });
    note();
    return doc;
  }

  /* ===================== služby a ceník ===================== */
  function srcSvc() { return { cats: D.cats, extras: D.extras, shades: D.shades }; }
  var DURS = []; for (var dm = 5; dm <= 300; dm += 5) DURS.push([dm, L.durTxt(dm)]);
  function mSluzby(app) {
    var X = app.draft('svc', srcSvc); if (ui.sv == null) ui.sv = 0;
    if (typeof ui.sv === 'number' && ui.sv >= X.cats.length) ui.sv = 0;
    var tabs = '<div class="k-filters">' + X.cats.map(function (c, i) { return '<button class="k-chip" type="button" data-act="sv" data-arg="' + i + '" aria-pressed="' + (ui.sv === i) + '">' + esc(c.n || 'Bez názvu') + ' <span class="k-muted">' + c.items.length + '</span></button>'; }).join('') +
      '<button class="k-chip" type="button" data-act="sv" data-arg="ex" aria-pressed="' + (ui.sv === 'ex') + '">Příplatky</button><button class="k-chip" type="button" data-act="sv" data-arg="sh" aria-pressed="' + (ui.sv === 'sh') + '">Odstíny</button>' +
      '<button class="k-btn k-ghost k-sm" type="button" data-act="catAdd">' + ic('plus') + 'Kategorie</button></div>';
    var body;
    if (ui.sv === 'ex') {
      var all = []; X.cats.forEach(function (c) { c.items.forEach(function (s) { all.push(s); }); });
      body = '<section class="k-panel"><h2>Příplatky</h2><p class="k-sub">Klientka je zaškrtne v rezervaci. Přičtou se k ceně i k délce služby, takže web nabídne jen čas, kam se vejdou.</p><div class="k-list">' + X.extras.map(function (x, i) {
        var b = 'dr.svc.extras.' + i + '.';
        return '<div class="k-item"><div class="k-item-h"><b>' + esc(x.n || 'Nový příplatek') + '</b><button class="k-icon-btn" type="button" data-act="exDel" data-arg="' + i + '" aria-label="Smazat příplatek">' + ic('trash') + '</button></div>' +
          '<div class="k-grid2">' + f.text('x-n-' + i, b + 'n', 'Název', x.n) + f.text('x-d-' + i, b + 'd', 'Popis pro klientky', x.d) + '</div>' +
          '<div class="k-grid2"><div><p class="lk-sub-h">Příplatek (Kč)</p><div class="lk-price3">' + [0, 1, 2].map(function (l) { return f.text('x-p-' + i + '-' + l, b + 'p.' + l, L.LEN[l], x.p[l], 'type="number" min="0" step="10" data-num'); }).join('') + '</div></div>' +
          '<div><p class="lk-sub-h">Čas navíc</p><div class="lk-price3">' + [0, 1, 2].map(function (l) { return f.select('x-t-' + i + '-' + l, b + 't.' + l, L.LEN[l], x.t[l], DURS, 'data-num'); }).join('') + '</div></div></div>' +
          '<div><p class="lk-sub-h">Nabízet u služeb</p><div class="lk-checks">' + all.filter(function (s) { return !L.free(s); }).map(function (s) { return '<label class="k-check"><input type="checkbox" data-act="exSvc" data-arg="' + i + '|' + s.id + '"' + (x.svcs.indexOf(s.id) >= 0 ? ' checked' : '') + '><span>' + esc(s.n) + '</span></label>'; }).join('') + '</div></div></div>';
      }).join('') + '</div><div style="margin-top:12px"><button class="k-btn k-sec k-sm" type="button" data-act="exAdd">' + ic('plus') + 'Přidat příplatek</button></div></section>';
    } else if (ui.sv === 'sh') {
      body = '<section class="k-panel"><h2>Odstíny ve vzorníku</h2><p class="k-sub">Zobrazují se na stránce Odstíny, na úvodu a při rezervaci barvení. Skryté odstíny web nenabízí.</p><div class="k-list">' + X.shades.map(function (s, i) {
        var b = 'dr.svc.shades.' + i + '.';
        return '<div class="k-item' + (s.on === false ? ' k-dim' : '') + '"><div class="k-item-h"><b><i class="lk-sw" style="background:linear-gradient(135deg,' + esc(s.a) + ',' + esc(s.b) + ')"></i>' + esc(s.c + ' ' + s.n) + '</b><div class="k-item-tools">' + TS.sortBtns('shMove', i, X.shades.length) + '<button class="k-icon-btn" type="button" data-act="shDel" data-arg="' + i + '" aria-label="Smazat odstín">' + ic('trash') + '</button></div></div>' +
          '<div class="k-grid3">' + f.text('s-c-' + i, b + 'c', 'Číslo', s.c, 'data-live') + f.text('s-n-' + i, b + 'n', 'Název', s.n, 'data-live') +
          '<div class="k-grid2"><div class="k-field"><label for="s-a-' + i + '">Světlá</label><input class="k-inp" type="color" id="s-a-' + i + '" value="' + esc(s.a) + '" data-bind="' + b + 'a" data-rr></div><div class="k-field"><label for="s-b-' + i + '">Tmavá</label><input class="k-inp" type="color" id="s-b-' + i + '" value="' + esc(s.b) + '" data-bind="' + b + 'b" data-rr></div></div>' +
          '<div class="k-full">' + f.text('s-t-' + i, b + 't', 'Popis', s.t) + '</div></div>' + f.check('s-on-' + i, b + 'on', 'Zobrazit na webu', s.on !== false, 'data-rr') + '</div>';
      }).join('') + '</div><div style="margin-top:12px"><button class="k-btn k-sec k-sm" type="button" data-act="shAdd">' + ic('plus') + 'Přidat odstín</button></div></section>';
    } else {
      var ci = ui.sv, c = X.cats[ci];
      body = '<section class="k-panel"><div class="k-panel-h"><div class="k-field" style="flex:1;max-width:340px"><label for="c-n">Název kategorie</label><input class="k-inp" id="c-n" value="' + esc(c.n) + '" data-bind="dr.svc.cats.' + ci + '.n" data-live></div>' +
        '<div class="k-item-tools">' + TS.sortBtns('catMove', ci, X.cats.length) + '<button class="k-btn k-danger k-sm" type="button" data-act="catDel">' + ic('trash') + 'Smazat kategorii</button></div></div>' +
        '<div class="k-list">' + (c.items.map(function (s, i) {
          var b = 'dr.svc.cats.' + ci + '.items.' + i + '.', doers = D.team.filter(function (t) { return L.canDo(t, s.id); }).map(function (t) { return first(t.n); });
          return '<div class="k-item' + (s.on === false ? ' k-dim' : '') + '"><div class="k-item-h"><b>' + esc(s.n || 'Nová služba') + '</b><div class="k-item-tools">' + TS.sortBtns('svcMove', i, c.items.length) + '<button class="k-icon-btn" type="button" data-act="svcDel" data-arg="' + i + '" aria-label="Smazat službu">' + ic('trash') + '</button></div></div>' +
            '<div class="k-grid2">' + f.text('i-n-' + i, b + 'n', 'Název', s.n) + f.text('i-d-' + i, b + 'd', 'Co je v ceně', s.d) + '</div>' +
            '<div class="lk-svc-grid"><div><p class="lk-sub-h">Cena (Kč) podle délky vlasů</p><div class="lk-price3">' + [0, 1, 2].map(function (l) { return f.text('i-p-' + i + '-' + l, b + 'p.' + l, L.LEN[l], s.p[l], 'type="number" min="0" step="10" data-num'); }).join('') + '</div></div>' +
            '<div><p class="lk-sub-h">Délka služby</p><div class="lk-price3">' + [0, 1, 2].map(function (l) { return f.select('i-t-' + i + '-' + l, b + 't.' + l, L.LEN[l], s.t[l], DURS, 'data-num'); }).join('') + '</div></div>' +
            '<div><p class="lk-sub-h">Po službě</p>' + f.select('i-b-' + i, b + 'buf', 'Úklid a příprava', s.buf || 0, [[0, 'bez přípravy'], [5, '5 min'], [10, '10 min'], [15, '15 min'], [20, '20 min'], [30, '30 min']], 'data-num') + '</div></div>' +
            '<div class="k-filters" style="margin:0">' + f.check('i-on-' + i, b + 'on', 'Rezervovat online', s.on !== false, 'data-rr') + f.check('i-c-' + i, b + 'color', 'Barvení (klientka vybírá odstín)', !!s.color) +
            '<span class="lk-mini">Dělá: ' + (doers.length ? esc(doers.join(', ')) : 'zatím nikdo, po uložení se přiřadí všem stylistkám') + '</span></div></div>';
        }).join('') || '<p class="k-empty">V kategorii zatím nic není.</p>') + '</div><div style="margin-top:12px"><button class="k-btn k-sec k-sm" type="button" data-act="svcAdd">' + ic('plus') + 'Přidat službu</button></div></section>';
    }
    return app.head('Obsah webu', 'Služby a ceník', '<a class="k-btn k-sec" href="lokna-sluzby.html" target="_blank" rel="noopener">' + ic('eye') + 'Ceník na webu</a>') +
      '<p class="k-muted" style="margin:-8px 0 14px;max-width:70ch">Cena i délka se zadávají zvlášť pro krátké, polodlouhé a dlouhé vlasy. Délka určuje, kolik času si služba v kalendáři zabere a jaké časy web nabídne.</p>' +
      tabs + body + app.savebar('svc', srcSvc);
  }

  /* ===================== tým ===================== */
  function srcTeam() { return D.team.map(function (s) { return Object.assign({}, s, { tagsTxt: (s.tags || []).join(', ') }); }); }
  function mTym(app) {
    var T = app.draft('team', srcTeam); ui.ti = Math.min(ui.ti || 0, T.length - 1);
    var s = T[ui.ti], b = 'dr.team.' + ui.ti + '.';
    var tabs = '<div class="k-filters">' + T.map(function (x, i) { return '<button class="k-chip" type="button" data-act="ti" data-arg="' + i + '" aria-pressed="' + (i === ui.ti) + '">' + dot(x) + ' ' + esc(x.n) + '</button>'; }).join('') + '</div>';
    var hrows = [1, 2, 3, 4, 5, 6, 0].map(function (d) {
      var h = s.hours[d], sal = D.hours[d], hb = b + 'hours.' + d + '.';
      return '<div class="k-hrow"><span class="k-t"><b>' + TS.WDN[d] + '</b></span>' + (sal.on ? '<label class="k-check"><input type="checkbox" data-bind="' + hb + 'on"' + (h.on ? ' checked' : '') + ' data-rr><span>V salonu</span></label>' +
        '<select class="k-inp k-sm" aria-label="' + TS.WDN[d] + ' od" data-bind="' + hb + 'from" data-num' + (h.on ? '' : ' disabled') + '>' + f.opts(tOpts(420, 1320, 30), h.from) + '</select><span class="k-dash">–</span>' +
        '<select class="k-inp k-sm" aria-label="' + TS.WDN[d] + ' do" data-bind="' + hb + 'to" data-num' + (h.on ? '' : ' disabled') + '>' + f.opts(tOpts(450, 1380, 30), h.to) + '</select><span class="k-muted k-small">salon ' + TS.hm(sal.from) + '–' + TS.hm(sal.to) + '</span>'
        : '<span class="k-muted">salon zavřeno</span><span></span><span></span><span></span><span></span>') + '</div>';
    }).join('');
    var svcs = D.cats.map(function (c) { return '<div><p class="lk-sub-h" style="margin-top:10px">' + esc(c.n) + '</p><div class="lk-checks">' + c.items.map(function (x) { return '<label class="k-check"><input type="checkbox" data-act="tSvc" data-arg="' + x.id + '"' + (s.svcs.indexOf(x.id) >= 0 ? ' checked' : '') + '><span>' + esc(x.n) + '</span></label>'; }).join('') + '</div></div>'; }).join('');
    var aways = s.away.map(function (a, i) {
      var ab = b + 'away.' + i + '.', hits = a.from ? D.bookings.filter(function (x) { return x.st === s.id && (x.status === 'pending' || x.status === 'confirmed') && x.date >= a.from && x.date <= (a.to || a.from) && x.date >= TS.today(); }) : [];
      return '<div class="k-item"><div class="k-item-h"><b>' + (a.from ? TS.fmtDMY(a.from) + (a.to && a.to !== a.from ? ' – ' + TS.fmtDMY(a.to) : '') : 'Nová nepřítomnost') + '</b><button class="k-icon-btn" type="button" data-act="awDel" data-arg="' + i + '" aria-label="Smazat nepřítomnost">' + ic('trash') + '</button></div>' +
        '<div class="k-grid3"><div class="k-field"><label for="aw-f-' + i + '">Od</label><input class="k-inp" type="date" id="aw-f-' + i + '" value="' + esc(a.from) + '" data-bind="' + ab + 'from" data-rr></div>' +
        '<div class="k-field"><label for="aw-t-' + i + '">Do</label><input class="k-inp" type="date" id="aw-t-' + i + '" value="' + esc(a.to) + '" data-bind="' + ab + 'to" data-rr></div>' + f.select('aw-n-' + i, ab + 'note', 'Důvod', a.note, ['Dovolená', 'Nemoc', 'Školení', 'Volno', 'Mateřská'].concat(['Dovolená', 'Nemoc', 'Školení', 'Volno', 'Mateřská'].indexOf(a.note) < 0 ? [a.note] : [])) + '</div>' +
        (hits.length ? '<div class="k-alert k-warn">V tomto období ' + TS.plural(hits.length, 'má', 'má', 'má') + ' ' + hits.length + ' ' + TS.plural(hits.length, 'rezervaci', 'rezervace', 'rezervací') + ': ' + hits.slice(0, 4).map(function (x) { return TS.fmtShort(x.date) + ' ' + TS.hm(x.start) + ' ' + esc(x.name); }).join(', ') + (hits.length > 4 ? '…' : '') + '. Po uložení je najdete v Rezervacích pod „Kolize se směnou“.</div>' : '') + '</div>';
    }).join('');
    return app.head('Provoz', 'Tým', '<a class="k-btn k-sec" href="lokna-tym.html" target="_blank" rel="noopener">' + ic('eye') + 'Tým na webu</a>') + tabs +
      '<div class="k-cols"><div class="k-stack"><section class="k-panel"><h2>Pracovní dny a hodiny</h2><p class="k-sub">Web nabízí časy jen v hodinách stylistky, a to v rámci otevírací doby salonu.</p>' + hrows + '</section>' +
      '<section class="k-panel"><h2>Nepřítomnost a dovolená</h2><p class="k-sub">V těchto dnech web u stylistky nic nenabídne a v kalendáři je den přeškrtnutý.</p><div class="k-list">' + (aways || '<p class="k-empty">Žádná plánovaná nepřítomnost.</p>') + '</div>' +
      '<div style="margin-top:12px"><button class="k-btn k-sec k-sm" type="button" data-act="awAdd">' + ic('plus') + 'Přidat nepřítomnost</button></div></section>' +
      '<section class="k-panel"><h2>Služby, které dělá</h2><p class="k-sub">Při rezervaci si klientka může vybrat jen stylistku, která službu dělá.</p>' + svcs + '</section></div>' +
      '<section class="k-panel"><h2>Profil na webu</h2><div class="k-stack">' + f.text('t-n', b + 'n', 'Jméno a příjmení', s.n) +
      '<div class="k-grid2">' + f.text('t-g', b + 'gen', 'Tvar pro „Rezervovat u …“', s.gen, '', 'Například Terezy, Kláry.') + f.text('t-since', b + 'since', 'U křesla od roku', s.since, 'inputmode="numeric" data-num', 'Na stránce Tým u profilu.') + '</div>' +
      f.text('t-r', b + 'role', 'Zaměření', s.role) + f.area('t-b', b + 'bio', 'Krátce (úvod a karta)', s.bio) + f.area('t-m', b + 'more', 'Víc o ní (stránka Tým)', s.more) + f.text('t-q', b + 'quote', 'Citát', s.quote) +
      f.text('t-tags', b + 'tagsTxt', 'Specializace (oddělte čárkou)', s.tagsTxt) +
      '<div class="k-grid2"><div class="k-field"><label for="t-a">Barva oblouku – světlá</label><input class="k-inp" type="color" id="t-a" value="' + esc(s.a) + '" data-bind="' + b + 'a" data-rr></div><div class="k-field"><label for="t-bb">tmavá</label><input class="k-inp" type="color" id="t-bb" value="' + esc(s.b) + '" data-bind="' + b + 'b" data-rr></div></div>' +
      f.toggle('t-web', b + 'web', 'Nabízet na webu', 'Vypnutím stylistku skryjete z webu i z online rezervací (např. během mateřské).', s.web !== false) + '</div></section></div>' +
      app.savebar('team', srcTeam);
  }

  /* ===================== otevírací doba a pravidla ===================== */
  function srcProvoz() { return { hours: D.hours, rules: D.rules }; }
  function mProvoz(app) {
    var X = app.draft('provoz', srcProvoz), R = X.rules;
    var rows = [1, 2, 3, 4, 5, 6, 0].map(function (d) {
      var h = X.hours[d], b = 'dr.provoz.hours.' + d + '.';
      return '<div class="k-hrow"><span class="k-t"><b>' + TS.WDN[d] + '</b></span><label class="k-check"><input type="checkbox" data-bind="' + b + 'on"' + (h.on ? ' checked' : '') + ' data-rr><span>Otevřeno</span></label>' +
        '<select class="k-inp k-sm" aria-label="' + TS.WDN[d] + ' od" data-bind="' + b + 'from" data-num' + (h.on ? '' : ' disabled') + '>' + f.opts(tOpts(420, 1320, 30), h.from) + '</select><span class="k-dash">–</span>' +
        '<select class="k-inp k-sm" aria-label="' + TS.WDN[d] + ' do" data-bind="' + b + 'to" data-num' + (h.on ? '' : ' disabled') + '>' + f.opts(tOpts(450, 1380, 30), h.to) + '</select><span></span></div>';
    }).join('');
    return app.head('Provoz', 'Otevírací doba a pravidla', '<a class="k-btn k-sec" href="lokna-rezervace.html" target="_blank" rel="noopener">' + ic('eye') + 'Rezervace na webu</a>') +
      '<section class="k-panel" style="margin-bottom:16px"><h2>Otevírací doba salonu</h2><p class="k-sub">Zobrazuje se na webu a omezuje směny stylistek. Jednorázové zavření (svátek, inventura) zadáte v kalendáři jako celodenní blokaci celého salonu.</p>' + rows + '</section>' +
      '<section class="k-panel" style="margin-bottom:16px"><h2>Pravidla online rezervací</h2><div class="k-grid2">' +
      f.select('r-max', 'dr.provoz.rules.maxDays', 'Kolik dní dopředu', R.maxDays, [[14, '2 týdny'], [30, '1 měsíc'], [60, '2 měsíce'], [90, '3 měsíce'], [180, '6 měsíců']], 'data-num') +
      f.select('r-lead', 'dr.provoz.rules.leadMin', 'Nejpozději předem', R.leadMin, [[0, 'kdykoli'], [60, '1 hodinu'], [120, '2 hodiny'], [240, '4 hodiny'], [720, '12 hodin'], [1440, '1 den']], 'data-num') +
      f.select('r-step', 'dr.provoz.rules.step', 'Časy nabízet po', R.step, [[15, '15 minutách'], [30, '30 minutách'], [60, 'hodině']], 'data-num') +
      f.select('r-gap', 'dr.provoz.rules.gap', 'Rozestup mezi klientkami', R.gap, [[0, 'bez rozestupu'], [5, '5 minut'], [10, '10 minut'], [15, '15 minut'], [30, '30 minut']], 'data-num', 'Přičte se k přípravě po každé službě.') +
      '<div class="k-full">' + f.toggle('r-auto', 'dr.provoz.rules.autoConfirm', 'Potvrzovat rezervace automaticky', 'Když je čas volný, potvrdí se hned a klientka dostane potvrzení. Salon dostane e-mail pro informaci. Jinak čeká každá rezervace na potvrzení.', R.autoConfirm) + '</div></div></section>' +
      '<section class="k-panel"><h2>Storno podmínky</h2><p class="k-sub">Zobrazují se v rezervaci, na stránce Kontakt a v potvrzovacím e-mailu.</p>' + f.area('r-storno', 'dr.provoz.rules.storno', 'Text storno podmínek', R.storno) + '</section>' +
      app.savebar('provoz', srcProvoz);
  }

  /* ===================== oznámení a kalendář ===================== */
  function srcNotify() { return { notify: D.notify }; }
  function feedEvents(st) {
    return D.bookings.filter(function (b) { return (b.status === 'confirmed' || b.status === 'done') && TS.diffDays(TS.today(), b.date) >= -14 && (st === 'all' || b.st === st); })
      .sort(function (a, b) { return L.order(a) < L.order(b) ? -1 : 1; }).map(function (b) { return L.event(D, b, true); });
  }
  function feedICS(st) {
    var evs = feedEvents(st), now = new Date(), stamp = now.toISOString().replace(/[-:]/g, '').replace(/\.\d+/, '');
    var e = function (s) { return String(s || '').replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n'); };
    var dt = function (d, m) { return d.replace(/-/g, '') + 'T' + TS.pad(Math.floor(m / 60)) + TS.pad(m % 60) + '00'; };
    var stn = st === 'all' ? 'všechny stylistky' : L.st(D, st).n;
    var out = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Lokna hair studio//Rezervace//CS', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' + e('Lokna – ' + stn), 'X-WR-TIMEZONE:Europe/Prague'];
    evs.forEach(function (v) { out.push('BEGIN:VEVENT', 'UID:' + v.uid + '@lokna-studio.cz', 'DTSTAMP:' + stamp, 'DTSTART;TZID=Europe/Prague:' + dt(v.date, v.start), 'DTEND;TZID=Europe/Prague:' + dt(v.date, v.end), 'SUMMARY:' + e(v.title), 'DESCRIPTION:' + e(v.details), 'STATUS:CONFIRMED', 'END:VEVENT'); });
    out.push('END:VCALENDAR'); return out.join('\r\n');
  }
  function mOznameni(app) {
    var X = app.draft('notify', srcNotify), N = X.notify; ui.feed = ui.feed || 'all';
    var url = FEED + (ui.feed === 'all' ? 'salon' : ui.feed) + '-k7f3q9x2.ics', evs = feedEvents(ui.feed);
    return app.head('Provoz', 'Oznámení a kalendář', '') +
      '<section class="k-panel" style="margin-bottom:16px"><h2>E-maily salonu</h2><p class="k-sub">Kam chodí oznámení o nových rezervacích. Klientky dostávají potvrzení, změny a doklady vždy.</p><div class="k-stack">' +
      f.text('n-mail', 'dr.notify.notify.email', 'E-mail pro oznámení', N.email, 'type="email"') +
      f.toggle('n-new', 'dr.notify.notify.newReq', 'E-mail o každé nové žádosti z webu', 'S tlačítky Potvrdit, Navrhnout jiný čas a Odmítnout. Automaticky potvrzené rezervace chodí vždy.', N.newReq !== false) +
      f.toggle('n-rem', 'dr.notify.notify.reminders', 'Připomínky klientkám den předem', 'V ostrém provozu odcházejí samy v 10:00 den před termínem. V ukázce je pošlete tlačítkem na Přehledu.', N.reminders !== false) + '</div></section>' +
      app.savebar('notify', srcNotify) +
      '<section class="k-panel"><h2>Propojení s kalendářem v telefonu</h2><p class="k-sub">Odkaz stačí jednou přidat do Google Kalendáře nebo iPhonu. Potvrzené rezervace se tam pak zobrazují samy. Každá stylistka si může přidat jen svůj kalendář.</p>' +
      '<div class="k-filters"><button class="k-chip" type="button" data-act="feed" data-arg="all" aria-pressed="' + (ui.feed === 'all') + '">Celý salon</button>' + D.team.map(function (s) { return '<button class="k-chip" type="button" data-act="feed" data-arg="' + s.id + '" aria-pressed="' + (ui.feed === s.id) + '">' + dot(s) + ' ' + esc(first(s.n)) + '</button>'; }).join('') + '</div>' +
      '<div class="lk-urlbox"><code>' + esc(url) + '</code><button class="k-btn k-sec k-sm" type="button" data-act="copy" data-arg="' + esc(url) + '">' + ic('copy') + 'Kopírovat</button></div>' +
      '<div class="lk-howto"><div><b>Google Kalendář</b><ol><li>Na počítači otevřete Google Kalendář.</li><li>Vlevo u „Další kalendáře“ klikněte na +.</li><li>Zvolte „Podle adresy URL“ a vložte odkaz.</li></ol></div>' +
      '<div><b>iPhone</b><ol><li>Nastavení → Kalendář → Účty.</li><li>Přidat účet → Jiný.</li><li>Přidat odebíraný kalendář a vložit odkaz.</li></ol></div></div>' +
      '<div class="k-filters" style="margin:16px 0 0"><button class="k-btn k-sec k-sm" type="button" data-act="toggleIcs">' + (ui.showIcs ? 'Skrýt obsah kalendáře' : 'Ukázat, co kalendář obsahuje') + '</button><button class="k-btn k-sec k-sm" type="button" data-act="feedDl">' + ic('download') + 'Stáhnout .ics</button><button class="k-btn k-ghost k-sm" type="button" data-act="googleInfo">Okamžité propojení s Google Kalendářem</button></div>' +
      (ui.showIcs ? '<pre class="lk-pre">' + esc(feedICS(ui.feed).replace(/\r\n/g, '\n')) + '</pre><p class="k-hint" style="margin-top:6px">' + evs.length + ' ' + TS.plural(evs.length, 'rezervace', 'rezervace', 'rezervací') + ' ve formátu iCalendar, kterému rozumí Google, Apple i Outlook.</p>' : '') + '</section>';
  }

  /* ===================== kontakty a údaje ===================== */
  function srcInfo() { return { info: D.info, billing: D.billing }; }
  function mKontakty(app) {
    var X = app.draft('info', srcInfo), I = X.info, B = X.billing, b = 'dr.info.info.', bb = 'dr.info.billing.';
    return app.head('Provoz', 'Kontakty a údaje', '<a class="k-btn k-sec" href="lokna-kontakt.html" target="_blank" rel="noopener">' + ic('eye') + 'Kontakt na webu</a>') +
      '<section class="k-panel" style="margin-bottom:16px"><h2>Kontakt</h2><div class="k-grid2">' + f.text('i-full', b + 'full', 'Název salonu', I.full) + f.text('i-st', b + 'street', 'Ulice a číslo', I.street) +
      '<div class="k-grid2">' + f.text('i-zip', b + 'zip', 'PSČ', I.zip) + f.text('i-city', b + 'city', 'Město', I.city) + '</div>' + f.text('i-dis', b + 'district', 'Čtvrť', I.district) +
      f.text('i-ph', b + 'phone', 'Telefon', I.phone, 'type="tel"') + f.text('i-em', b + 'email', 'E-mail', I.email, 'type="email"') + f.text('i-ig', b + 'instagram', 'Instagram', I.instagram) + f.text('i-web', b + 'web', 'Web', I.web) +
      '<div class="k-full">' + f.area('i-tr', b + 'transport', 'Jak se k nám dostanete', I.transport) + '</div><div class="k-full">' + f.area('i-pk', b + 'parking', 'Parkování', I.parking) + '</div>' +
      '<div class="k-full">' + f.text('i-acc', b + 'access', 'Přístup do salonu', I.access) + '</div><div class="k-full">' + f.area('i-vo', b + 'vouchers', 'Dárkové poukazy', I.vouchers) + '</div></div></section>' +
      '<section class="k-panel"><h2>Údaje na doklad</h2><p class="k-sub">Tisknou se na každý doklad a jsou v patičce webu. Telefon a e-mail se berou z kontaktu výše.</p><div class="k-grid2">' +
      f.text('b-n', bb + 'name', 'Jméno podnikatelky nebo název firmy', B.name) + f.text('b-ico', bb + 'ico', 'IČO', B.ico, 'inputmode="numeric" maxlength="8"') +
      '<div class="k-full">' + f.text('b-seat', bb + 'seat', 'Sídlo (adresa z rejstříku)', B.seat) + '</div>' + f.text('b-dic', bb + 'dic', 'DIČ (nepovinné)', B.dic) + f.text('b-reg', bb + 'register', 'Zápis v rejstříku', B.register) + '</div>' +
      '<div style="margin-top:14px">' + f.toggle('b-vat', bb + 'vatPayer', 'Jsem plátkyně DPH', 'Doklad se pak vystaví jako zjednodušený daňový doklad s rozpisem DPH. Ceny služeb zůstávají konečné, včetně DPH.', B.vatPayer, 'data-rr') + '</div>' +
      (B.vatPayer ? '<div style="margin-top:12px;max-width:240px">' + f.select('b-rate', bb + 'vatRate', 'Sazba DPH', B.vatRate, [[21, '21 %'], [12, '12 %']], 'data-num', 'Sazbu pro vaše služby si ověřte s účetní.') + '</div>' : '') + '</section>' +
      app.savebar('info', srcInfo);
  }

  /* ===================== aplikace ===================== */
  var app = TS.admin({
    D: D, brand: { name: 'LOKNA' }, user: { email: 'tereza@lokna-studio.cz' },
    mail: { title: 'Odeslané e-maily', from: 'Lokna hair studio <ahoj@lokna-studio.cz>', intro: 'Rezervační systém posílá e-maily salonu i klientkám sám. V ukázce je vidíte tady a nikam neodcházejí.',
      boxes: [{ id: 'salon', label: 'Salonu', addr: D.notify.email }, { id: 'klient', label: 'Klientkám' }], emptyHint: 'Zkuste si rezervovat termín na webu.' },
    draftSources: { svc: srcSvc, team: srcTeam, provoz: srcProvoz, notify: srcNotify, info: srcInfo },
    modules: [
      { id: 'prehled', label: 'Přehled', icon: 'home', tab: true, render: mPrehled, badge: function () { return pending().length; } },
      { id: 'kalendar', label: 'Kalendář', icon: 'cal', tab: true, render: mKalendar },
      { id: 'rezervace', label: 'Rezervace', icon: 'list', tab: true, render: mRezervace, onArg: function (app, arg) { onResArg(arg); } },
      { id: 'doklady', label: 'Doklady', icon: 'receipt', tab: true, render: mDoklady, badge: function () { return toIssue().filter(function (b) { return TS.diffDays(b.date, TS.today()) <= 2; }).length; } },
      { id: 'sluzby', label: 'Služby a ceník', icon: 'scissors', render: mSluzby, short: 'Ceník' },
      { id: 'tym', label: 'Tým', icon: 'users', render: mTym },
      { id: 'provoz', label: 'Otevírací doba a pravidla', icon: 'clock', render: mProvoz, short: 'Provoz' },
      { id: 'oznameni', label: 'Oznámení a kalendář', icon: 'bell', render: mOznameni, short: 'Oznámení' },
      { id: 'kontakty', label: 'Kontakty a údaje', icon: 'pin', render: mKontakty, short: 'Kontakty' }
    ]
  });
  ui = app.ui; A = app.actions;

  /* --- rezervace: akce --- */
  function onResArg(arg) {
    var p = String(arg).split('/'), b = byId(p[0]); if (!b) { TS.toast('Rezervace už neexistuje.', { bad: true }); return; }
    if (p[1] === 'potvrdit') { if (b.status === 'pending') A.confirm(b.id); else { openRes(b.id); TS.toast('Tahle žádost už je vyřízená: ' + (L.STATUS[b.status] || '').toLowerCase() + '.'); } return; }
    if ((p[1] === 'presunout' || p[1] === 'odmitnout') && b.status !== 'pending') { openRes(b.id); TS.toast('Tahle žádost už je vyřízená.'); return; }
    openRes(b.id, p[1] === 'presunout' ? 'move' : p[1] === 'odmitnout' ? 'reject' : '');
  }
  function mvFrom(b) { return { svc: b.svc, len: b.len, ex: (b.extras || []).slice(), shade: b.shade || '', st: b.st, date: b.date >= TS.today() ? b.date : nextOpen(TS.today(), true), t: b.date >= TS.today() ? b.start : null, manualOn: false, manual: TS.hhmm(b.start), notify: true, msg: '' }; }
  function openRes(id, mode) {
    var b = byId(id); if (!b) return;
    ui.m = { id: id, mode: mode || '', notify: true, msg: '' }; if (mode === 'move') ui.m.mv = mvFrom(b);
    app.modal(function () { return ui.m && ui.m.rc ? mReceipt() : mRes(); });
  }
  A.openRes = function (arg) { var p = String(arg).split('|'); openRes(p[0], p[1]); };
  A.resMode = function (mode) { var b = byId(ui.m.id); ui.m.mode = mode; ui.m.msg = ''; ui.m.notify = true; if (mode === 'move') ui.m.mv = mvFrom(b); app.refreshModal(); };
  A.msgChip = function (t) { ui.m.msg = t; app.refreshModal(); };
  A.confirm = function (id) {
    var b = byId(id); if (!b || b.status !== 'pending') return;
    b.status = 'confirmed'; L.log(b, 'Potvrzeno'); var m = L.mail(D, 'confirmed', b);
    app.modal(null); app.commit('Potvrzeno.' + (m ? ' Klientka dostala e-mail.' : ''), m ? { href: '#emaily', label: 'Zobrazit e-mail' } : {});
  };
  A.rejectDo = function () {
    var b = byId(ui.m.id); if (!b) return; var send = b.email && ui.m.notify !== false;
    b.status = 'rejected'; L.log(b, 'Odmítnuto' + (ui.m.msg ? ': ' + ui.m.msg : ''));
    if (send) L.mail(D, 'rejected', b, { msg: ui.m.msg });
    app.modal(null); app.commit('Žádost je odmítnutá a čas je znovu volný.' + (send ? ' Klientka dostala e-mail.' : ''), send ? { href: '#emaily', label: 'Zobrazit e-mail' } : {});
  };
  A.cancelDo = function () {
    var b = byId(ui.m.id); if (!b) return; var send = b.email && ui.m.notify !== false;
    b.status = 'cancelled'; L.log(b, 'Zrušeno salonem' + (ui.m.msg ? ': ' + ui.m.msg : ''));
    if (send) L.mail(D, 'cancelled', b, { msg: ui.m.msg });
    app.modal(null); app.commit('Rezervace je zrušená a čas se na webu znovu nabízí.' + (send ? ' Klientka dostala e-mail.' : ''), send ? { href: '#emaily', label: 'Zobrazit e-mail' } : {});
  };
  A.markDone = function (id) { var b = byId(id); if (!b) return; b.status = 'done'; L.log(b, 'Proběhlo'); app.modal(null); app.commit('Návštěva je uzavřená jako proběhlá.'); };
  A.noshow = function (id) { var b = byId(id); if (!b) return; b.status = 'noshow'; L.log(b, 'Nedostavila se'); app.modal(null); app.commit('Zapsáno: klientka se nedostavila.'); };
  A.rebook = function (id) {
    var b = byId(id); if (!b) return; var d = nextOpen(TS.today(), true);
    ui.m = { n: Object.assign(newDefaults(d), { svc: b.svc, len: b.len, ex: (b.extras || []).slice(), shade: b.shade, st: b.st, name: b.name, phone: b.phone, email: b.email, note: '' }) };
    firstSlot(ui.m.n); app.modal(mNew);
  };
  /* výběr času v okně (přesun i nová rezervace) */
  function curO() { return ui.m.mode === 'move' ? ui.m.mv : ui.m.n; }
  A.mPickDate = function (d) { var o = curO(); o.date = d; o.t = null; o.manualOn = false; app.refreshModal(); };
  A.mPickTime = function (t) { var o = curO(); o.t = +t; o.manualOn = false; o.err = ''; app.refreshModal(); };
  A.mManual = function (on) { var o = curO(); o.manualOn = on === '1'; if (o.manualOn && !o.manual) o.manual = o.t != null ? TS.hhmm(o.t) : '10:00'; if (o.manualOn && o.st === 'any') o.st = D.team[0].id; app.refreshModal(); };
  A.mEx = function (id, el) { var o = curO(), i = o.ex.indexOf(id); if (el.checked && i < 0) o.ex.push(id); if (!el.checked && i >= 0) o.ex.splice(i, 1); o.t = null; app.refreshModal(); };
  function resolveSt(o, start) {   /* stylistka pro „kdokoli“ */
    if (o.st !== 'any') return o.st;
    var sl = L.slots(D, o.date, o.svc, o.len, o.ex, 'any', null, { admin: true }).find(function (x) { return x.t === start; });
    return sl ? L.pickSt(D, o.date, sl.sts) : null;
  }
  A.moveSave = function () {
    var b = byId(ui.m.id), o = ui.m.mv; if (!b) return;
    var start = effStart(o); if (start == null || !o.date) { o.err = 'Vyberte den a čas.'; app.refreshModal(); return; }
    var st = resolveSt(o, start); if (!st) { o.err = 'V tento čas nemá volno žádná stylistka.'; app.refreshModal(); return; }
    var s = L.svc(D, o.svc), c = L.calc(D, o.svc, o.len, o.ex), wasP = b.status === 'pending';
    var same = o.date === b.date && start === b.start && st === b.st && o.svc === b.svc && o.len === b.len && JSON.stringify(o.ex) === JSON.stringify(b.extras || []);
    if (same) { if (wasP) A.confirm(b.id); else app.modal(null); return; }
    var old = L.svcLabel(D, b) + ', ' + TS.fmtShort(b.date) + ' v ' + TS.hm(b.start) + (b.st !== st ? ' u ' + (stOf(b) || {}).gen : '');
    Object.assign(b, { svc: s.id, svcName: s.n, flat: L.flat(s), len: o.len, extras: o.ex.slice(), shade: s.color ? (o.shade || b.shade || 'poradit') : '', st: st, date: o.date, start: start, dur: c.dur, buf: c.buf, price: c.price, status: 'confirmed', reminded: false });
    if (o.st === 'any') b.stAny = true;
    L.log(b, (wasP ? 'Potvrzeno v jiném čase' : 'Přesunuto') + ' (původně ' + old + ')');
    var send = b.email && o.notify !== false; if (send) L.mail(D, 'changed', b, { old: old, msg: o.msg });
    ui.cd = b.date; app.modal(null);
    app.commit((wasP ? 'Potvrzeno v novém čase.' : 'Termín je přesunutý.') + (send ? ' Klientka dostala e-mail.' : ''), send ? { href: '#emaily', label: 'Zobrazit e-mail' } : {});
  };
  function newDefaults(d) { return { kind: 'booking', svc: 'damsky', len: 1, ex: [], shade: '', st: 'any', date: d, t: null, manualOn: false, manual: '', name: '', phone: '', email: '', note: '', notify: true, bst: 'all', bdate: d, allDay: false, bfrom: 600, bto: 720, reason: '', errs: {} }; }
  function firstSlot(n) {
    for (var i = 0; i < 30; i++) { var d = TS.addDays(n.date || TS.today(), i), sl = L.slots(D, d, n.svc, n.len, n.ex, n.st, null, { admin: true }).filter(function (x) { return d !== TS.today() || x.t >= TS.nowMin(); }); if (sl.length) { n.date = d; n.t = sl[0].t; return; } }
  }
  A.newRes = function () { ui.m = { n: newDefaults(nextOpen(TS.today(), true)) }; firstSlot(ui.m.n); app.modal(mNew); };
  A.newBlock = function () { ui.m = { n: newDefaults(nextOpen(TS.today(), true)) }; ui.m.n.kind = 'block'; app.modal(mNew); };
  A.nKind = function (k) { ui.m.n.kind = k; ui.m.n.err = ''; app.refreshModal(); };
  A.nReason = function (r) { ui.m.n.reason = r; app.refreshModal(); };
  A.calEmpty = function (arg) {
    var p = arg.split('|'), m = +p[1], n;
    if (/^\d{4}-/.test(p[0])) { n = newDefaults(p[0]); if (ui.cst && ui.cst !== 'all') { n.st = ui.cst; n.bst = ui.cst; } }
    else { n = newDefaults(ui.cd); n.st = p[0]; n.bst = p[0]; if (!L.canDo(L.st(D, p[0]), n.svc)) n.svc = L.st(D, p[0]).svcs[0]; }
    var free = L.slots(D, n.date, n.svc, n.len, [], n.st, null, { admin: true }).some(function (x) { return x.t === m; });
    n.t = free ? m : null; n.manualOn = !free; n.manual = TS.hhmm(m); n.bfrom = m; n.bto = Math.min(m + 60, 1380);
    if (n.manualOn && n.st === 'any') n.st = D.team[0].id;
    ui.m = { n: n }; app.modal(mNew);
  };
  A.newSave = function () {
    var n = ui.m.n, e = n.errs = {};
    if (n.name.trim().length < 3) e.name = 'Vyplňte jméno a příjmení.';
    if (!TS.validPhone(n.phone)) e.phone = 'Zadejte telefon, ať se klientce můžete ozvat.';
    if (n.email && !TS.validEmail(n.email)) e.email = 'E-mail nemá správný tvar.';
    var start = effStart(n); n.err = start == null || !n.date ? 'Vyberte den a čas.' : '';
    if (e.name || e.phone || e.email || n.err) { app.refreshModal(); return; }
    var st = resolveSt(n, start); if (!st) { n.err = 'V tento čas nemá volno žádná stylistka.'; app.refreshModal(); return; }
    var s = L.svc(D, n.svc), c = L.calc(D, n.svc, n.len, n.ex), ts = Date.now();
    var b = { id: TS.uid('lb'), svc: s.id, svcName: s.n, flat: L.flat(s), len: n.len, extras: n.ex.slice(), shade: s.color ? (n.shade || 'poradit') : '', st: st, stAny: false, date: n.date, start: start, dur: c.dur, buf: c.buf, price: c.price,
      status: 'confirmed', name: n.name.trim(), phone: n.phone.trim(), email: n.email.trim(), note: n.note.trim(), source: 'admin', createdAt: ts, log: [{ at: ts, t: 'Zapsáno v salonu' }] };
    var cf = L.conflicts(D, b.date, b.start, b.dur, b.buf, b.st, null);
    D.bookings.push(b); var send = b.email && n.notify !== false; if (send) L.mail(D, 'confirmed', b);
    ui.cd = b.date; app.modal(null);
    app.commit('Rezervace je uložená u ' + L.st(D, st).gen + '.' + (cf.items.length || !cf.inHours ? ' Pozor, překrývá se nebo je mimo směnu.' : '') + (send ? ' Klientka dostala potvrzení.' : ''), send ? { href: '#emaily', label: 'Zobrazit e-mail' } : {});
  };
  A.blockSave = function () {
    var n = ui.m.n; if (!n.bdate) { n.err = 'Vyberte den.'; app.refreshModal(); return; }
    var from = n.allDay ? 0 : n.bfrom, to = n.allDay ? 1440 : n.bto;
    if (to <= from) { n.err = 'Konec musí být po začátku.'; app.refreshModal(); return; }
    D.blocks.push({ id: TS.uid('k'), st: n.bst, date: n.bdate, from: from, to: to, reason: n.reason.trim() || 'Blokováno', createdAt: Date.now() });
    ui.cd = n.bdate; app.modal(null); app.commit('Čas je zablokovaný, web ho přestal nabízet.');
  };
  A.openBlock = function (id) {
    var k = D.blocks.find(function (x) { return x.id === id; }); if (!k) return; var st = L.st(D, k.st);
    app.modal(function () {
      return TS.mShell(esc(k.reason || 'Blokovaný čas'), esc(TS.cap(TS.fmtLong(k.date))) + ' · ' + (k.from <= 0 && k.to >= 1440 ? 'celý den' : TS.hm(k.from) + '–' + TS.hm(k.to)) + ' · ' + (st ? esc(st.n) : 'celý salon'),
        '<p>Web v tomto čase ' + (st ? 'u ' + esc(st.gen) + ' ' : '') + 'nenabízí žádné termíny.</p>', '<button class="k-btn k-danger" type="button" data-act="blockDel" data-arg="' + k.id + '">Zrušit blokaci</button><span class="k-sp"></span><button class="k-btn k-sec" type="button" data-act="modalClose">Zavřít</button>');
    });
  };
  A.blockDel = function (id) { D.blocks = D.blocks.filter(function (k) { return k.id !== id; }); app.modal(null); app.commit('Blokace je zrušená, čas se na webu znovu nabízí.'); };
  A.openAway = function (id) {
    var s = L.st(D, id), a = L.away(s, ui.cd) || (s.away || [])[0]; if (!a) return;
    app.modal(function () {
      return TS.mShell(esc(a.note || 'Nepřítomnost'), esc(s.n) + ' · ' + TS.fmtDMY(a.from) + (a.to !== a.from ? ' – ' + TS.fmtDMY(a.to) : ''), '<p>V těchto dnech web u ' + esc(s.gen) + ' nenabízí žádné časy. Nepřítomnost upravíte v části Tým.</p>',
        '<button class="k-btn k-ghost" type="button" data-act="modalClose">Zavřít</button><a class="k-btn k-pri" href="#tym" data-act="goTym" data-arg="' + s.id + '">Upravit v části Tým</a>');
    });
  };
  A.goTym = function (id) { ui.ti = D.team.findIndex(function (s) { return s.id === id; }); app.modal(null); app.go('tym'); };
  A.remind = function () {
    var list = remindList();
    list.forEach(function (b) { b.reminded = true; L.log(b, 'Odeslána připomínka'); L.mail(D, 'reminder', b); });
    app.commit(list.length ? 'Připomínky odešly ' + list.length + ' ' + TS.plural(list.length, 'klientce', 'klientkám', 'klientkám') + '.' : 'Není co připomínat.', list.length ? { href: '#emaily', label: 'Zobrazit e-maily' } : {});
  };
  A.copy = function (t) { try { navigator.clipboard.writeText(t).then(function () { TS.toast('Zkopírováno.'); }, function () { TS.toast(t); }); } catch (e) { TS.toast(t); } };
  A.rf = function (k) { ui.rf = k; ui.rmore = false; app.render(); };
  A.rmore = function () { ui.rmore = true; app.render(); };
  A.cv = function (v) { ui.cv = v; app.render(); };
  A.cst = function (v) { ui.cst = v; app.render(); };
  A.calNav = function (n) { var step = ui.cv === 'den' ? 1 : 7; ui.cd = n === '0' ? nextOpen(TS.today(), true) : TS.addDays(ui.cd, +n * step); if (ui.cv === 'den' && n !== '0' && !L.salonWin(D, ui.cd)) { for (var i = 0; i < 7 && !L.salonWin(D, ui.cd); i++) ui.cd = TS.addDays(ui.cd, +n); } app.render(); };
  A.calGo = function (arg) { var p = arg.split('|'); ui.cd = p[0]; if (!p[1]) ui.cv = 'den'; app.render(); };

  /* --- doklady: akce --- */
  A.df = function (k) { ui.df = k; ui.dmore = false; app.render(); };
  A.dmore = function () { ui.dmore = true; app.render(); };
  A.openReceipt = function (id) {
    var b = byId(id); if (!b) return;
    var sh = b.shade && b.shade !== 'poradit' ? L.shade(D, b.shade) : null;
    var items = [{ name: L.svcLabel(D, b) + (sh ? ' – odstín ' + sh.c + ' ' + sh.n : ''), qty: '1', price: String(L.svc(D, b.svc) ? L.svc(D, b.svc).p[b.len] : b.price) }];
    L.exNames(D, b).forEach(function (nm) { var x = D.extras.find(function (e) { return e.n === nm; }); items.push({ name: nm, qty: '1', price: String(x ? x.p[b.len] : 0) }); });
    ui.m = { id: id, rc: { items: items, pay: 'karta', issueDate: TS.today() < b.date ? b.date : TS.today(), dtype: 'pct', dv: '', dlabel: 'Věrnostní sleva', send: true, err: '' } };
    app.modal(mReceipt);
  };
  A.rcAdd = function () { ui.m.rc.items.push({ name: '', qty: '1', price: '' }); app.refreshModal(); var el = TS.$('#rc-' + (ui.m.rc.items.length - 1) + '-n'); if (el) el.focus(); };
  A.rcDel = function (i) { ui.m.rc.items.splice(+i, 1); app.refreshModal(); };
  A.rcProd = function (a) { var p = (a.charAt(0) === 'm' ? L.MATERIALS : L.PRODUCTS)[+a.slice(1)]; ui.m.rc.items.push({ name: p[0], qty: '1', price: String(p[1]) }); app.refreshModal(); };
  A.rcIssue = function () {
    var b = byId(ui.m.id), r = ui.m.rc; if (!b || b.receipt) return;
    var items = r.items.filter(function (i) { return String(i.name).trim(); }).map(function (i) { return { name: String(i.name).trim(), qty: TS.num(i.qty) || 1, price: TS.num(i.price) }; });
    if (!items.length) { r.err = 'Doklad potřebuje aspoň jednu položku s názvem.'; app.refreshModal(); return; }
    if (items.some(function (i) { return i.price < 0 || i.qty <= 0; })) { r.err = 'Množství i cena musí být kladná čísla.'; app.refreshModal(); return; }
    if (r.dtype === 'pct' && TS.num(r.dv) > 100) { r.err = 'Sleva nemůže být vyšší než 100 %.'; app.refreshModal(); return; }
    var issue = r.issueDate || TS.today(), no = nextNo(issue);
    var rc = L.mkReceipt(D, b, no, issue, r.pay, items, { type: r.dtype, v: r.dv, label: r.dlabel }, Date.now());
    if (!(rc.total > 0)) { r.err = 'Celková částka musí být větší než nula.'; app.refreshModal(); return; }
    b.receipt = rc; b.status = 'done'; L.log(b, 'Proběhlo · vystaven doklad ' + no + ' (' + TS.money(rc.total) + ', ' + L.PAY[r.pay].toLowerCase() + ')');
    var send = b.email && r.send !== false; if (send) { b.receiptSent = true; L.mail(D, 'receipt', b, { subject: 'Doklad č. ' + no + ' – Lokna hair studio' }); }
    delete ui.m.rc; app.commit('Doklad ' + no + ' je vystavený.' + (send ? ' Klientka ho dostala e-mailem.' : ''), send ? { href: '#emaily', label: 'Zobrazit e-mail' } : {});
    app.modal(mReceipt);
  };
  A.rcMail = function (id) {
    var b = byId(id); if (!b || !b.receipt || !b.email) return;
    b.receiptSent = true; L.mail(D, 'receipt', b, { subject: 'Doklad č. ' + b.receipt.no + ' – Lokna hair studio' }); L.log(b, 'Doklad odeslán e-mailem');
    app.commit('Doklad odešel na ' + b.email + '.', { href: '#emaily', label: 'Zobrazit e-mail' }); app.refreshModal();
  };
  A.rcPrint = function (id) { var b = byId(id); if (b && b.receipt) TS.print('<div class="k-doc">' + L.docHTML(b.receipt) + '</div>'); };
  A.pdfDl = function (id, el) {
    var b = byId(id); if (!b || !b.receipt) { TS.toast('Doklad nenalezen.', { bad: true }); return; }
    TS.pdf.run(el, function () {
      return TS.pdf.load([['outfit-400.ttf', 'Outfit', 'normal'], ['outfit-600.ttf', 'OutfitSemi', 'normal'], ['dm-serif-400.ttf', 'DMSerif', 'normal']]).then(function (make) {
        receiptPdf(make, b.receipt).save('doklad-' + b.receipt.no + '.pdf'); TS.toast('Doklad ' + b.receipt.no + ' je stažený jako PDF.');
      });
    });
  };
  A.icsDl = function (id) { var b = byId(id); if (!b) { TS.toast('Rezervace už neexistuje.', { bad: true }); return; } TS.download('termin-lokna.ics', TS.cal.ics(L.event(D, b)), 'text/calendar'); };

  /* --- služby: akce --- */
  function SV() { return ui.drafts.svc; }
  A.sv = function (v) { ui.sv = v === 'ex' || v === 'sh' ? v : +v; app.render(); };
  A.catAdd = function () { SV().cats.push({ id: TS.uid('c'), n: 'Nová kategorie', items: [] }); ui.sv = SV().cats.length - 1; app.render(); };
  A.catDel = function () {
    var c = SV().cats[ui.sv]; if (!c) return;
    if (SV().cats.length <= 1) { TS.toast('Ceník musí mít aspoň jednu kategorii.', { bad: true }); return; }
    if (c.items.length && !confirm('Smazat kategorii „' + c.n + '“ i se všemi službami? Změna se projeví až po uložení.')) return;
    SV().cats.splice(ui.sv, 1); ui.sv = 0; app.render();
  };
  A.catMove = function (arg) { var p = arg.split('|'); TS.move(SV().cats, +p[0], +p[1]); ui.sv = Math.max(0, Math.min(SV().cats.length - 1, +p[0] + +p[1])); app.render(); };
  A.svcAdd = function () { var c = SV().cats[ui.sv]; c.items.push({ id: TS.uid('s'), n: '', d: '', p: [0, 0, 0], t: [60, 60, 60], buf: 0, color: false, on: true }); app.render(); var el = TS.$('#i-n-' + (c.items.length - 1)); if (el) el.focus(); };
  A.svcDel = function (i) { SV().cats[ui.sv].items.splice(+i, 1); app.render(); };
  A.svcMove = function (arg) { var p = arg.split('|'); TS.move(SV().cats[ui.sv].items, +p[0], +p[1]); app.render(); };
  A.exAdd = function () { SV().extras.push({ id: TS.uid('x'), n: '', d: '', p: [100, 100, 100], t: [10, 10, 10], svcs: [] }); app.render(); };
  A.exDel = function (i) { SV().extras.splice(+i, 1); app.render(); };
  A.exSvc = function (arg, el) { var p = arg.split('|'), x = SV().extras[+p[0]], i = x.svcs.indexOf(p[1]); if (el.checked && i < 0) x.svcs.push(p[1]); if (!el.checked && i >= 0) x.svcs.splice(i, 1); app.render(); };
  A.shAdd = function () { SV().shades.push({ id: TS.uid('o'), c: '', n: '', a: '#D9B48C', b: '#8A5A3C', t: '', on: true }); app.render(); };
  A.shDel = function (i) { SV().shades.splice(+i, 1); app.render(); };
  A.shMove = function (arg) { var p = arg.split('|'); TS.move(SV().shades, +p[0], +p[1]); app.render(); };
  app.saves.svc = function (d) {
    var err = null, all = [];
    d.cats.forEach(function (c) { if (!String(c.n).trim()) err = 'Každá kategorie potřebuje název.'; c.items.forEach(function (s) {
      s.n = String(s.n || '').trim(); if (!s.n) err = 'Každá služba potřebuje název.';
      s.p = s.p.map(function (x) { return Math.max(0, Math.round(TS.num(x))); }); s.t = s.t.map(function (x) { return Math.max(5, TS.num(x)); }); all.push(s);
    }); });
    d.extras.forEach(function (x) { x.n = String(x.n || '').trim(); if (!x.n) err = 'Každý příplatek potřebuje název.'; x.p = x.p.map(function (v) { return Math.max(0, Math.round(TS.num(v))); }); });
    d.shades.forEach(function (s) { if (!String(s.c).trim() || !String(s.n).trim()) err = 'Každý odstín potřebuje číslo i název.'; if (!s.id || /^o/.test(s.id) && s.c) s.id = String(s.c).trim(); });
    if (!all.length) err = 'Ceník musí obsahovat aspoň jednu službu.';
    if (err) { TS.toast(err, { bad: true }); return false; }
    var added = all.filter(function (s) { return !D.team.some(function (t) { return t.svcs.indexOf(s.id) >= 0; }); });
    added.forEach(function (s) { D.team.forEach(function (t) { t.svcs.push(s.id); }); });
    D.cats = d.cats; D.extras = d.extras; D.shades = d.shades; delete ui.drafts.team;
    return { msg: 'Ceník je uložený a na webu platí hned.' + (added.length ? ' Nové služby jsme přiřadili všem stylistkám, upravíte to v části Tým.' : ''), href: 'lokna-sluzby.html', label: 'Zobrazit ceník' };
  };

  /* --- tým: akce --- */
  function TM() { return ui.drafts.team[ui.ti]; }
  A.ti = function (i) { ui.ti = +i; app.render(); };
  A.tSvc = function (id, el) { var s = TM(), i = s.svcs.indexOf(id); if (el.checked && i < 0) s.svcs.push(id); if (!el.checked && i >= 0) s.svcs.splice(i, 1); app.render(); };
  A.awAdd = function () { var t = TS.addDays(TS.today(), 14); TM().away.push({ id: TS.uid('a'), from: t, to: TS.addDays(t, 4), note: 'Dovolená' }); app.render(); };
  A.awDel = function (i) { TM().away.splice(+i, 1); app.render(); };
  app.saves.team = function (d) {
    var err = null;
    d.forEach(function (s) {
      if (!String(s.n).trim()) err = 'Stylistka potřebuje jméno.';
      s.hours.forEach(function (h, w) { if (h.on && h.to <= h.from) err = s.n + ': ' + TS.WD[w] + ' končí směna dřív, než začíná.'; });
      s.away.forEach(function (a) { if (!a.from) err = s.n + ': u nepřítomnosti chybí datum.'; if (!a.to) a.to = a.from; if (a.to < a.from) err = s.n + ': nepřítomnost končí dřív, než začíná.'; });
      if (!s.svcs.length) err = s.n + ' musí dělat aspoň jednu službu.';
    });
    if (err) { TS.toast(err, { bad: true }); return false; }
    d.forEach(function (s) { s.tags = String(s.tagsTxt || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean); delete s.tagsTxt; s.since = TS.num(s.since) || ''; });
    D.team = d;
    var cf = D.bookings.filter(function (b) { return !!conflictOf(b); }).length;
    if (cf) { ui.rf = 'conflict'; return { msg: 'Uloženo. Pozor: ' + cf + ' ' + TS.plural(cf, 'rezervace spadá', 'rezervace spadají', 'rezervací spadá') + ' mimo směnu nebo do nepřítomnosti.', href: '#rezervace', label: 'Zobrazit' }; }
    return { msg: 'Tým je uložený. Web podle směn hned nabízí volné časy.', href: 'lokna-tym.html', label: 'Zobrazit na webu' };
  };
  app.saves.provoz = function (d) {
    if (d.hours.some(function (h) { return h.on && h.to <= h.from; })) { TS.toast('U některého dne končí otevírací doba dřív, než začíná.', { bad: true }); return false; }
    if (!d.hours.some(function (h) { return h.on; })) { TS.toast('Salon musí mít otevřeno aspoň jeden den v týdnu.', { bad: true }); return false; }
    if (!String(d.rules.storno).trim()) { TS.toast('Vyplňte prosím storno podmínky.', { bad: true }); return false; }
    D.hours = d.hours; D.rules = d.rules;
    return { msg: 'Otevírací doba a pravidla jsou uložené. Web podle nich hned nabízí časy.', href: 'lokna-rezervace.html', label: 'Zkusit rezervaci' };
  };
  app.saves.notify = function (d) {
    if (!TS.validEmail(d.notify.email)) { TS.toast('E-mail pro oznámení nemá správný tvar.', { bad: true }); return false; }
    D.notify = d.notify; return { msg: 'Nastavení oznámení je uložené.' };
  };
  app.saves.info = function (d) {
    if (!TS.validEmail(d.info.email)) { TS.toast('E-mail nemá správný tvar.', { bad: true }); return false; }
    d.billing.ico = String(d.billing.ico || '').replace(/\s/g, '');
    if (d.billing.ico && !/^\d{8}$/.test(d.billing.ico)) { TS.toast('IČO má mít 8 číslic.', { bad: true }); return false; }
    D.info = d.info; D.billing = d.billing; return { msg: 'Kontakty a údaje jsou uložené.', href: 'lokna-kontakt.html', label: 'Zobrazit kontakt' };
  };
  A.feed = function (v) { ui.feed = v; app.render(); };
  A.toggleIcs = function () { ui.showIcs = !ui.showIcs; app.render(); };
  A.feedDl = function () { TS.download('lokna-' + (ui.feed === 'all' ? 'salon' : ui.feed) + '.ics', feedICS(ui.feed), 'text/calendar'); };
  A.googleInfo = function () {
    app.modal(function () {
      return TS.mShell('Okamžité propojení s Google Kalendářem', '', '<p>V ostrém provozu tady stylistka klikne na „Propojit“ a přihlásí se svým Google účtem. Od té chvíle:</p>' +
        '<ul style="margin:0;padding-left:20px;display:grid;gap:6px"><li>se každá potvrzená rezervace zapíše do jejího Google Kalendáře během pár sekund,</li><li>přesuny a zrušení se tam promítnou také,</li><li>soukromé události, které si zapíše přímo do Google Kalendáře, web klientkám nenabídne.</li></ul>' +
        '<p>V této ukázce propojení aktivní není, potřebuje přístup ke Google účtu.</p>', '<button class="k-btn k-pri" type="button" data-act="modalClose">Rozumím</button>');
    });
  };
  app.render();
})();
