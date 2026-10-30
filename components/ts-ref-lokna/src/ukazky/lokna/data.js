/* Lokna hair studio – data ukázky, pravidla volných časů, e-maily a doklad (sdílí web i administrace) */
(function () {
  'use strict';
  var L = window.LOKNA = {};
  var esc = TS.esc;
  var WEB = 'lokna-rezervace.html', ADMIN = 'lokna-sprava.html';

  L.LEN = ['Krátké', 'Polodlouhé', 'Dlouhé'];
  L.LENS = ['krátké', 'polodlouhé', 'dlouhé'];
  L.LNOTE = ['Do úrovně brady, do 15 cm', 'Po ramena, 15–30 cm', 'Pod lopatky, nad 30 cm'];
  /* silueta vlasů pro kartu délky (krátké, polodlouhé, dlouhé) */
  L.HAIR = [
    'M60 124Q52 44 100 38Q148 44 140 124Q134 118 130 112Q134 76 116 64Q100 76 80 66Q66 80 70 112Q66 118 60 124Z',
    'M56 160Q46 44 100 36Q154 44 144 160Q136 150 132 140Q134 80 116 64Q100 76 80 66Q66 80 68 140Q64 150 56 160Z',
    'M50 212Q34 44 100 34Q166 44 150 212Q140 198 134 172Q134 80 116 64Q100 76 80 66Q66 80 66 172Q60 198 50 212Z'];
  L.STATUS = { pending: 'Čeká na potvrzení', confirmed: 'Potvrzeno', done: 'Proběhlo', noshow: 'Nedostavila se', rejected: 'Odmítnuto', cancelled: 'Zrušeno' };
  L.PAY = { hotove: 'Hotově', karta: 'Kartou', prevod: 'Převodem' };
  L.ACTIVE = ['pending', 'confirmed', 'done'];   /* stavy, které v kalendáři zabírají čas */
  /* materiál, který se účtuje navíc podle skutečné spotřeby */
  L.MATERIALS = [['Barva navíc (30 g)', 150], ['Zesvětlovač navíc', 250], ['Ochrana vazeb navíc', 190], ['Tonér navíc', 120]];
  L.PRODUCTS = [['Šampon pro barvené vlasy 250 ml', 420], ['Kondicionér s keratinem 200 ml', 460], ['Termoochranný sprej 150 ml', 380], ['Regenerační maska 200 ml', 540], ['Olej na konečky 50 ml', 490]];

  /* ---------- výchozí obsah ---------- */
  function cats() {
    var it = function (id, n, d, p, t, o) { return Object.assign({ id: id, n: n, d: d, p: p, t: t, buf: 0, color: false, on: true }, o || {}); };
    return [
      { id: 'cut', n: 'Střih', items: [
        it('damsky', 'Dámský střih', 'konzultace, mytí, střih, foukaná', [790, 890, 990], [45, 60, 75]),
        it('foukana', 'Foukaná a styling', 'mytí, maska, foukaná nebo vlny', [490, 590, 690], [30, 40, 50]),
        it('ofina', 'Zastřižení ofiny', 'mezi střihy', [150, 150, 150], [10, 10, 10]),
        it('pansky', 'Pánský střih', 'včetně mytí', [490, 490, 490], [30, 30, 30])] },
      { id: 'color', n: 'Barva', items: [
        it('konzultace', 'Konzultace barvy', 'zdarma a nezávazně: probereme odstín, test pramene', [0, 0, 0], [20, 20, 20]),
        it('odrost', 'Barvení odrostu', 'do 3 cm, včetně foukané', [1190, 1290, 1390], [90, 100, 110], { buf: 10, color: true }),
        it('barva', 'Celobarva', 'jednotný tón od kořínků ke konečkům', [1390, 1690, 1990], [100, 120, 140], { buf: 10, color: true }),
        it('melir', 'Melír fóliový', 'klasické pramínky, tónování', [1690, 2090, 2490], [120, 150, 180], { buf: 10, color: true }),
        it('balayage', 'Balayage / airtouch', 'ručně malovaný přechod, tónování, ochrana vazeb', [2990, 3590, 4290], [180, 210, 240], { buf: 15, color: true }),
        it('tonovani', 'Tónování lesku', 'oživení barvy a lesk', [690, 790, 890], [45, 50, 60], { color: true })] },
      { id: 'care', n: 'Péče', items: [
        it('vazby', 'Obnova vlasových vazeb', 'bond ošetření pro barvené a zesvětlené vlasy', [590, 690, 790], [20, 25, 30]),
        it('keratin', 'Keratinové narovnání', 'uhlazení až na 4 měsíce', [2490, 3290, 3990], [120, 150, 180], { buf: 15 }),
        it('regenerace', 'Hloubková regenerace', 'maska a masáž hlavy 15 minut', [390, 490, 590], [30, 30, 35])] },
      { id: 'style', n: 'Účesy', items: [
        it('spolecensky', 'Společenský účes', 'ples, promoce, večírek', [990, 1190, 1390], [60, 75, 90]),
        it('svatebni', 'Svatební účes', 'včetně zkoušky předem', [2900, 3200, 3500], [90, 105, 120])] }
    ];
  }
  function extras() {
    return [
      { id: 'huste', n: 'Velmi husté vlasy', d: 'víc času u křesla a vyšší spotřeba barvy', p: [150, 250, 350], t: [10, 15, 20], svcs: ['damsky', 'foukana', 'odrost', 'barva', 'melir', 'balayage', 'keratin', 'spolecensky', 'svatebni'] },
      { id: 'ochrana', n: 'Ochrana vazeb k barvě', d: 'ochrana vlasových vazeb přímo během barvení', p: [390, 490, 590], t: [15, 20, 20], svcs: ['odrost', 'barva', 'melir', 'tonovani'] }];
  }
  function shades() {
    return [
      { id: '10.1', c: '10.1', n: 'Platinová blond', a: '#F2EBDD', b: '#CFC4B0', t: 'Chladná, téměř bílá. Vyžaduje zesvětlení a pravidelné tónování každých 6 týdnů.', on: true },
      { id: '9.3', c: '9.3', n: 'Zlatá blond', a: '#EFD49A', b: '#C9A160', t: 'Teplá sluneční blond, krásná na letní balayage a k pleti s broskvovým podtónem.', on: true },
      { id: '8.34', c: '8.34', n: 'Medová měď', a: '#E0A86A', b: '#B36B35', t: 'Náš podzimní bestseller. Zlatý a měděný reflex, který svítí i v šedém dni.', on: true },
      { id: '7.43', c: '7.43', n: 'Karamelová', a: '#C98F5A', b: '#8A5A3C', t: 'Nejoblíbenější přechod z tmavé. Jemně rozjasní tvář, odrůstá nenápadně.', on: true },
      { id: '6.66', c: '6.66', n: 'Třešňová', a: '#A8374A', b: '#5E1A28', t: 'Intenzivní červená pro odvážné. Doporučujeme šampon pro barvené vlasy a chladnou vodu.', on: true },
      { id: '5.0', c: '5.0', n: 'Oříšková hnědá', a: '#7A5238', b: '#4A2F1F', t: 'Přirozená střední hnědá. Skvělá na zakrytí prvních šedin bez ostrého odrostu.', on: true },
      { id: '4.15', c: '4.15', n: 'Čokoládová', a: '#5A3A2E', b: '#2E1C17', t: 'Hluboká chladná hnědá s popelavým reflexem. Elegantní s rovným střihem.', on: true },
      { id: '1.0', c: '1.0', n: 'Černá', a: '#2B2426', b: '#0E0B0C', t: 'Sytá černá s modrým leskem. Pro zesvětlení později počítejte s více sezeními.', on: true }];
  }
  function hrs(spec) {   /* {2:[540,1020], …} → pole 0–6 */
    var out = []; for (var d = 0; d < 7; d++) out.push(spec[d] ? { on: true, from: spec[d][0], to: spec[d][1] } : { on: false, from: 540, to: 1020 });
    return out;
  }
  function team() {
    return [
      { id: 'tereza', n: 'Tereza Malá', gen: 'Terezy', role: 'Zakladatelka · barvy & balayage', a: '#C98F5A', b: '#7A3E2C', since: 2012,
        bio: '14 let u křesla, školení v Londýně a Miláně. Specialistka na přirozené přechody a opravy barev.',
        more: 'Lokna vznikla v roce 2019 z jednoduché myšlenky: barva má sedět k pleti, střihu i k tomu, kolik času chcete ráno trávit u zrcadla. Tereza vám vždycky nejdřív ukáže pramen, teprve pak míchá barvu.',
        tags: ['Balayage a airtouch', 'Opravy barev', 'Přirozené přechody', 'Melír'],
        quote: 'Dobrá barva odrůstá tak, že si toho nikdo nevšimne.',
        hours: hrs({ 2: [540, 1020], 3: [540, 1020], 4: [720, 1200], 5: [540, 1020], 6: [540, 900] }),
        svcs: ['damsky', 'foukana', 'ofina', 'konzultace', 'odrost', 'barva', 'melir', 'balayage', 'tonovani', 'vazby', 'regenerace'], away: [], web: true },
      { id: 'klara', n: 'Klára Nováková', gen: 'Kláry', role: 'Střihy · krátké vlasy', a: '#B03F63', b: '#4A1F33', since: 2016,
        bio: 'Bob, pixie a mikado. Stříhá tak, aby účes držel tvar i po šesti týdnech a doma ho zvládla foukaná za pět minut.',
        more: 'Klára začínala v pánském holičství a přesnost střihu je pro ni víc než trend. Na každou novou klientku si nechává pár minut navíc, aby viděla, jak vlasy přirozeně padají.',
        tags: ['Bob a pixie', 'Mikado', 'Pánské střihy', 'Střih na suché vlasy'],
        quote: 'Střih je dobrý, když ho doma zvládnete bez žehličky.',
        hours: hrs({ 2: [720, 1200], 3: [720, 1200], 4: [540, 1020], 5: [720, 1200] }),
        svcs: ['damsky', 'foukana', 'ofina', 'pansky', 'konzultace', 'odrost', 'barva', 'tonovani', 'regenerace'], away: [], web: true },
      { id: 'denisa', n: 'Denisa Vlková', gen: 'Denisy', role: 'Účesy · péče & regenerace', a: '#E4B8A6', b: '#9C6B6E', since: 2018,
        bio: 'Svatební a společenské účesy, keratinové narovnání a obnova vlasových vazeb. Ke svatebnímu účesu vždy zkouška předem.',
        more: 'Denisa učesala desítky nevěst a ví, že účes musí vydržet obřad, focení i první tanec. Kromě účesů má na starosti péči: keratin, obnovu vazeb a hloubkovou regeneraci po létě.',
        tags: ['Svatební účesy', 'Keratin', 'Obnova vazeb', 'Regenerace'],
        quote: 'Zkouška účesu vám vezme půlku svatební nervozity.',
        hours: hrs({ 3: [720, 1200], 4: [540, 1020], 5: [720, 1200], 6: [540, 900] }),
        svcs: ['damsky', 'foukana', 'konzultace', 'tonovani', 'vazby', 'keratin', 'regenerace', 'spolecensky', 'svatebni'], away: [], web: true }];
  }

  /* ---------- pomocníci nad daty ---------- */
  L.allSvcs = function (D) { var out = []; D.cats.forEach(function (c) { c.items.forEach(function (i) { out.push(i); }); }); return out; };
  L.svc = function (D, id) { for (var i = 0; i < D.cats.length; i++) for (var j = 0; j < D.cats[i].items.length; j++) if (D.cats[i].items[j].id === id) return D.cats[i].items[j]; return null; };
  L.catOf = function (D, id) { return D.cats.find(function (c) { return c.items.some(function (i) { return i.id === id; }); }) || null; };
  L.st = function (D, id) { return D.team.find(function (s) { return s.id === id; }) || null; };
  L.stIdx = function (D, id) { var i = D.team.findIndex(function (s) { return s.id === id; }); return i < 0 ? 0 : i; };
  L.shade = function (D, id) { return D.shades.find(function (s) { return s.id === id; }) || null; };
  L.flat = function (s) { return s.p[0] === s.p[2] && s.p[0] === s.p[1] && s.t[0] === s.t[2] && s.t[0] === s.t[1]; };
  L.free = function (s) { return !s.p[0] && !s.p[1] && !s.p[2]; };
  L.extrasFor = function (D, svcId) { var s = L.svc(D, svcId); if (!s || L.free(s)) return []; return D.extras.filter(function (x) { return x.svcs.indexOf(svcId) >= 0; }); };
  L.canDo = function (st, svcId) { return !!st && st.svcs.indexOf(svcId) >= 0; };
  L.doers = function (D, svcId) { return D.team.filter(function (s) { return s.web !== false && L.canDo(s, svcId); }); };
  /* délka a cena služby podle délky vlasů a příplatků */
  L.calc = function (D, svcId, len, ex) {
    var s = L.svc(D, svcId); if (!s) return { dur: 0, price: 0, buf: 0 };
    len = Math.max(0, Math.min(2, +len || 0));
    var dur = s.t[len], price = s.p[len];
    (ex || []).forEach(function (id) { var x = D.extras.find(function (e) { return e.id === id; }); if (x && x.svcs.indexOf(svcId) >= 0) { dur += x.t[len]; price += x.p[len]; } });
    return { dur: dur, price: price, buf: s.buf || 0 };
  };
  L.priceRange = function (s) { var a = Math.min.apply(null, s.p), b = Math.max.apply(null, s.p); return L.free(s) ? 'zdarma' : a === b ? TS.money(a) : 'od ' + TS.money(a); };
  L.priceTxt = function (n) { return n ? TS.money(n) : 'zdarma'; };
  L.durTxt = function (m) { var h = Math.floor(m / 60), r = m % 60; return h ? h + ' h' + (r ? ' ' + r + ' min' : '') : m + ' min'; };
  L.svcLabel = function (D, b) {
    var s = L.svc(D, b.svc), flat = s ? L.flat(s) : b.flat;
    return b.svcName + (flat ? '' : ', ' + L.LENS[b.len] + ' vlasy');
  };
  L.exNames = function (D, b) { return (b.extras || []).map(function (id) { var x = D.extras.find(function (e) { return e.id === id; }); return x ? x.n : null; }).filter(Boolean); };
  L.shadeTxt = function (D, b) {
    if (!b.shade) return '';
    if (b.shade === 'poradit') return 'Poradíme na místě';
    var s = L.shade(D, b.shade); return s ? s.c + ' ' + s.n : b.shade;
  };

  /* ---------- otevírací doba, směny a volné časy ---------- */
  L.salonWin = function (D, date) {
    var h = D.hours[TS.wd(date)];
    if (!h || !h.on || h.to <= h.from) return null;
    if (D.blocks.some(function (k) { return k.st === 'all' && k.date === date && k.from <= h.from && k.to >= h.to; })) return null;
    return [h.from, h.to];
  };
  L.away = function (st, date) { return (st.away || []).find(function (a) { return date >= a.from && date <= a.to; }) || null; };
  L.stWin = function (D, st, date) {
    if (!st || st.web === false) return null;
    var s = L.salonWin(D, date); if (!s) return null;
    var h = st.hours[TS.wd(date)]; if (!h || !h.on) return null;
    if (L.away(st, date)) return null;
    var a = Math.max(s[0], h.from), b = Math.min(s[1], h.to);
    return b > a ? [a, b] : null;
  };
  L.busy = function (D, stId, date, exceptId) {
    var out = [], gap = D.rules.gap || 0;
    D.bookings.forEach(function (b) {
      if (b.date !== date || b.st !== stId || L.ACTIVE.indexOf(b.status) < 0 || b.id === exceptId) return;
      out.push([b.start, b.start + b.dur + (b.buf || 0) + gap]);
    });
    D.blocks.forEach(function (k) { if (k.date === date && (k.st === stId || k.st === 'all')) out.push([k.from, k.to]); });
    return out;
  };
  L.minStart = function (D, date) {
    var dd = TS.diffDays(TS.today(), date);
    return TS.nowMin() + (D.rules.leadMin || 0) - dd * 1440;
  };
  L.inRange = function (D, date) { var dd = TS.diffDays(TS.today(), date); return dd >= 0 && dd <= D.rules.maxDays; };
  /* volné začátky: [{t, sts:[id…]}] – stId je id stylistky, nebo 'any' */
  L.slots = function (D, date, svcId, len, ex, stId, exceptId, opt) {
    opt = opt || {};
    if (!opt.admin && !L.inRange(D, date)) return [];
    var c = L.calc(D, svcId, len, ex); if (!c.dur) return [];
    var sts = stId === 'any' || !stId ? L.doers(D, svcId) : [L.st(D, stId)].filter(function (s) { return s && (opt.admin || s.web !== false) && L.canDo(s, svcId); });
    var map = {}, ms = opt.admin ? null : L.minStart(D, date);
    sts.forEach(function (st) {
      var w = L.stWin(D, st, date); if (!w) return;
      TS.cal.free({ wins: [w], busy: L.busy(D, st.id, date, exceptId), dur: c.dur, after: c.buf + (D.rules.gap || 0), step: D.rules.step, minStart: ms })
        .forEach(function (t) { (map[t] = map[t] || []).push(st.id); });
    });
    return Object.keys(map).map(Number).sort(function (a, b) { return a - b; }).map(function (t) { return { t: t, sts: map[t] }; });
  };
  /* „je mi to jedno“: přiřadí stylistku s nejmenším počtem rezervací ten den */
  L.pickSt = function (D, date, ids) {
    var load = function (id) { return D.bookings.filter(function (b) { return b.date === date && b.st === id && L.ACTIVE.indexOf(b.status) >= 0; }).reduce(function (a, b) { return a + b.dur; }, 0); };
    return ids.slice().sort(function (a, b) { return load(a) - load(b) || L.stIdx(D, a) - L.stIdx(D, b); })[0];
  };
  L.dayState = function (D, date, svcId, len, ex, stId) {
    if (date < TS.today()) return { s: 'past', n: 0 };
    if (!L.inRange(D, date) || !L.salonWin(D, date)) return { s: 'closed', n: 0 };
    var n = L.slots(D, date, svcId, len, ex, stId).length;
    return { s: n ? 'free' : 'full', n: n };
  };
  L.nextFree = function (D, svcId, len, ex, stId, limit, perDay) {
    var out = [], t0 = TS.today();
    for (var i = 0; i <= D.rules.maxDays && out.length < limit; i++) {
      var d = TS.addDays(t0, i), sl = L.slots(D, d, svcId, len, ex, stId);
      var taken = 0, last = -999;
      sl.forEach(function (x) {   /* časy v rámci dne rozložíme, ať nabídka není 12:00, 12:15 */
        if (out.length >= limit || taken >= (perDay || 2) || x.t - last < 90) return;
        out.push({ date: d, t: x.t, st: x.sts.length > 1 ? L.pickSt(D, d, x.sts) : x.sts[0] }); taken++; last = x.t;
      });
    }
    return out;
  };
  L.conflicts = function (D, date, start, dur, buf, stId, exceptId) {
    var end = start + dur + (buf || 0), out = [];
    D.bookings.forEach(function (b) { if (b.date === date && b.st === stId && L.ACTIVE.indexOf(b.status) >= 0 && b.id !== exceptId && start < b.start + b.dur + (b.buf || 0) && b.start < end) out.push('rezervace ' + TS.hm(b.start) + ' (' + b.name + ')'); });
    D.blocks.forEach(function (k) { if (k.date === date && (k.st === stId || k.st === 'all') && start < k.to && k.from < end) out.push('blokace: ' + (k.reason || 'blokováno')); });
    var st = L.st(D, stId), w = L.stWin(D, st, date), inH = !!w && start >= w[0] && start + dur <= w[1];
    return { items: out, inHours: inH, away: st ? L.away(st, date) : null };
  };
  /* dny v týdnu, kdy stylistka pracuje: „út, st 9–17 · čt 12–20 …“ */
  L.daysTxt = function (D, st) {
    var groups = [];
    [1, 2, 3, 4, 5, 6, 0].forEach(function (d) {
      var h = st.hours[d], s = D.hours[d]; if (!h.on || !s.on) return;
      var a = Math.max(h.from, s.from), b = Math.min(h.to, s.to); if (b <= a) return;
      var key = TS.hm(a) + '–' + TS.hm(b), g = groups.find(function (x) { return x.key === key; });
      if (g) g.days.push(TS.WDS[d]); else groups.push({ key: key, days: [TS.WDS[d]] });
    });
    return groups.map(function (g) { return { days: g.days.join(', '), time: g.key.replace(/:00/g, '') }; });
  };
  L.hourGroups = function (D) {
    var out = [];
    [1, 2, 3, 4, 5, 6, 0].forEach(function (d) {
      var h = D.hours[d], key = h.on ? h.from + '-' + h.to : 'x', last = out[out.length - 1];
      if (last && last.key === key) last.days.push(d); else out.push({ key: key, days: [d], h: h });
    });
    return out.map(function (g) {
      var a = TS.WDN[g.days[0]], b = TS.WDN[g.days[g.days.length - 1]];
      return { label: g.days.length > 1 ? (g.days.length > 2 ? TS.WDS[g.days[0]].replace(/^./, function (c) { return c.toUpperCase(); }) + ' – ' + TS.WDS[g.days[g.days.length - 1]].replace(/^./, function (c) { return c.toUpperCase(); }) : a + ', ' + b.toLowerCase()) : a,
        text: g.h.on ? TS.hm(g.h.from) + ' – ' + TS.hm(g.h.to) : 'zavřeno', days: g.days };
    });
  };
  L.hoursShort = function (D) {
    return L.hourGroups(D).filter(function (g) { return g.text !== 'zavřeno'; }).map(function (g) { return g.label.replace(' – ', '–') + ' ' + g.text.replace(/:00/g, '').replace(' – ', '–'); }).join(', ');
  };

  /* ---------- ukázková data relativně k dnešku ---------- */
  var CUST = [
    ['Petra Svobodová', '+420 731 482 615', 'petra.svobodova@example.com'], ['Kristýna Dvořáková', '+420 604 219 873', 'kristyna.dvorakova@example.com'],
    ['Lucie Marková', '+420 776 340 192', 'lucie.markova@example.com'], ['Jana Procházková', '+420 728 615 044', 'jana.prochazkova@example.com'],
    ['Eva Černá', '+420 603 771 289', 'eva.cerna@example.com'], ['Markéta Kučerová', '+420 739 208 517', 'marketa.kucerova@example.com'],
    ['Anna Veselá', '+420 777 902 136', 'anna.vesela@example.com'], ['Veronika Horáková', '+420 608 455 970', 'veronika.horakova@example.com'],
    ['Barbora Němcová', '+420 724 386 251', 'barbora.nemcova@example.com'], ['Alena Pokorná', '+420 605 117 482', 'alena.pokorna@example.com'],
    ['Hana Benešová', '+420 732 590 618', 'hana.benesova@example.com'], ['Simona Fialová', '+420 721 064 339', 'simona.fialova@example.com'],
    ['Michaela Šťastná', '+420 774 813 520', 'michaela.stastna@example.com'], ['Monika Růžičková', '+420 607 342 891', 'monika.ruzickova@example.com'],
    ['Tereza Kolářová', '+420 736 925 407', 'tereza.kolarova@example.com'], ['Zuzana Marešová', '+420 775 260 913', 'zuzana.maresova@example.com'],
    ['Karolína Bartošová', '+420 731 908 446', 'karolina.bartosova@example.com'], ['Nikola Pospíšilová', '+420 602 557 318', 'nikola.pospisilova@example.com'],
    ['Lenka Hájková', '+420 739 114 820', 'lenka.hajkova@example.com'], ['Adéla Krejčí', '+420 776 482 059', 'adela.krejci@example.com'],
    ['Gabriela Urbanová', '+420 608 731 264', 'gabriela.urbanova@example.com'], ['Ivana Doležalová', '+420 724 059 377', 'ivana.dolezalova@example.com'],
    ['Klára Sedláčková', '+420 605 893 140', 'klara.sedlackova@example.com'], ['Dominika Vacková', '+420 737 266 905', 'dominika.vackova@example.com']];
  var MEN = [['Jan Dvořák', '+420 731 205 774', 'jan.dvorak@example.com'], ['Tomáš Malý', '+420 608 913 452', 'tomas.maly@example.com'], ['Ondřej Beneš', '+420 777 340 618', '']];
  /* poznámky klientek podle druhu služby, ať k pánskému střihu nepřijde dotaz na barvu */
  var NOTES = {
    color: ['Barvím doma, poslední barva asi před dvěma měsíci.', 'Chtěla bych přechod z tmavé do teplejší hnědé.', 'Mám šediny hlavně na spáncích.', 'Mám citlivou pokožku hlavy.'],
    cut: ['Prosím o radu s ofinou, zvažuji ji poprvé.', 'Jdu k vám poprvé, přinesu fotku účesu.', 'Chci zachovat délku, jen zpevnit konečky.'],
    men: ['Po stranách kratší, nahoře nechat délku.'],
    care: ['Vlasy mám po létě dost suché.', 'Mám citlivou pokožku hlavy.'],
    style: ['Svatba je za tři týdny, tohle je zkouška účesu.', 'Jdu na ples, přinesu fotku šatů.']
  };
  function noteFor(D, sid, r) {
    var c = L.catOf(D, sid), pool = sid === 'pansky' ? NOTES.men : NOTES[c ? c.id : 'cut'] || NOTES.cut;
    return pool[Math.floor(r * pool.length) % pool.length];
  }
  var MIX = {
    tereza: ['balayage', 'odrost', 'barva', 'damsky', 'melir', 'odrost', 'tonovani', 'konzultace', 'balayage', 'damsky'],
    klara: ['damsky', 'damsky', 'pansky', 'foukana', 'ofina', 'damsky', 'tonovani', 'odrost', 'damsky', 'regenerace'],
    denisa: ['foukana', 'spolecensky', 'keratin', 'vazby', 'regenerace', 'damsky', 'svatebni', 'foukana', 'spolecensky', 'tonovani']
  };
  function rnd(n) { var x = Math.sin(n * 12.9898 + 78.233) * 43758.5453; return x - Math.floor(x); }

  L.defaults = function () {
    var today = TS.today(), now = TS.nowMin(), ts = Date.now();
    var D = {
      info: { name: 'Lokna', full: 'Lokna hair studio', street: 'Mánesova 28', zip: '120 00', city: 'Praha 2', district: 'Vinohrady', phone: '+420 603 000 888', email: 'ahoj@lokna-studio.cz', instagram: '@lokna.studio', web: 'www.lokna-studio.cz',
        transport: 'Tramvaj 11 a 13, zastávka Vinohradská tržnice, odtud 3 minuty pěšky.', parking: 'Okolní ulice jsou v modré zóně Prahy 2, parkovné zaplatíte v automatu nebo aplikaci. Pohodlnější je tramvaj.',
        access: 'Salon je v přízemí, bez schodů. Kočárek můžete nechat u nás.', vouchers: 'Dárkové poukazy prodáváme na recepci v libovolné hodnotě. Platí 12 měsíců a dají se použít na jakoukoli službu.' },
      billing: { name: 'Tereza Malá', ico: '09876543', dic: '', seat: 'Mánesova 28, 120 00 Praha 2', register: 'Fyzická osoba zapsaná v živnostenském rejstříku.', vatPayer: false, vatRate: 21 },
      hours: hrs({ 2: [540, 1200], 3: [540, 1200], 4: [540, 1200], 5: [540, 1200], 6: [540, 900] }),
      rules: { step: 15, leadMin: 120, maxDays: 60, autoConfirm: false, gap: 0,
        storno: 'Termín zrušíte nebo přesunete zdarma nejpozději 24 hodin předem, stačí zavolat nebo odpovědět na potvrzovací e-mail. Když se zpozdíte o víc než 15 minut, možná budeme muset službu zkrátit, aby další klientka nečekala.' },
      notify: { email: 'ahoj@lokna-studio.cz', newReq: true, reminders: true, summary: false },
      cats: cats(), extras: extras(), shades: shades(), team: team(), blocks: [], bookings: [], emails: [], rcSeq: 0
    };
    /* dovolená a školení, ať je v kalendáři vidět, jak se projeví */
    var den = L.st(D, 'denisa'); den.away.push({ id: 'a1', from: TS.addDays(today, 9), to: TS.addDays(today, 13), note: 'Dovolená' });
    var kl = L.st(D, 'klara'), kd = 0;
    for (var i = 2; i < 14; i++) { var d0 = TS.addDays(today, i); if (L.stWin(D, kl, d0)) { kd++; if (kd === 2) { var w0 = L.stWin(D, kl, d0); D.blocks.push({ id: 'k1', st: 'klara', date: d0, from: w0[1] - 180, to: w0[1], reason: 'Školení střihů', createdAt: ts - 6 * 864e5 }); break; } } }

    /* rezervace na posledních deset a příštích pětadvacet dní */
    var list = [], n = 0, ci = 0, mi = 0;
    for (var dd = -10; dd <= 25; dd++) {
      var date = TS.addDays(today, dd); if (!L.salonWin(D, date)) continue;
      D.team.forEach(function (st, si) {
        var w = L.stWin(D, st, date); if (!w) return;
        var seed = +date.replace(/-/g, '') % 100000 + si * 977, dens = dd < 0 ? 0.86 : dd <= 0 ? 0.9 : dd <= 2 ? 0.78 : dd <= 6 ? 0.5 : dd <= 13 ? 0.34 : 0.18;
        var t = w[0] + (rnd(seed) < 0.25 ? 30 : 0), k = 0, mix = MIX[st.id] || st.svcs;
        while (t < w[1] - 20 && k < 30) {
          k++;
          var r = rnd(seed + k * 7.31), sid = mix[Math.floor(rnd(seed + k * 3.7) * mix.length)], s = L.svc(D, sid);
          if (!s || !L.canDo(st, sid)) { t += 15; continue; }
          var len = L.flat(s) ? 1 : (r < 0.28 ? 0 : r < 0.72 ? 1 : 2), ex = [];
          if (L.extrasFor(D, sid).some(function (x) { return x.id === 'ochrana'; }) && rnd(seed + k) < 0.3) ex.push('ochrana');
          if (L.extrasFor(D, sid).some(function (x) { return x.id === 'huste'; }) && rnd(seed + k * 1.9) < 0.14) ex.push('huste');
          var c = L.calc(D, sid, len, ex);
          if (t + c.dur > w[1]) { t += 15; continue; }
          if (L.busy(D, st.id, date).some(function (b) { return t < b[1] && b[0] < t + c.dur + c.buf; })) { t += 15; continue; }
          if (rnd(seed + k * 5.13) < dens) {
            var men = sid === 'pansky', cu = men ? MEN[(mi++) % MEN.length] : CUST[(ci++ * 7) % CUST.length];
            var web = rnd(seed + k * 2.2) < 0.62;
            var b = { id: 'lb' + (++n), svc: sid, svcName: s.n, flat: L.flat(s), len: len, extras: ex, shade: '', st: st.id, stAny: web && rnd(seed + k * 4.4) < 0.4,
              date: date, start: t, dur: c.dur, buf: c.buf, price: c.price, status: 'confirmed', name: cu[0], phone: cu[1], email: web ? (cu[2] || '') : (rnd(seed + k * 6.1) < 0.7 ? cu[2] : ''),
              note: rnd(seed + k * 8.8) < 0.18 ? noteFor(D, sid, rnd(seed + k)) : '', source: web ? 'web' : 'admin', log: [] };
            if (s.color) b.shade = rnd(seed + k * 9.1) < 0.35 ? 'poradit' : D.shades[Math.floor(rnd(seed + k * 9.7) * D.shades.length)].id;
            list.push(b);
            D.bookings.push(b);
            t += c.dur + c.buf; t = Math.ceil(t / 15) * 15;
          } else t += rnd(seed + k * 1.3) < 0.5 ? 30 : 45;
        }
      });
    }
    /* stav, historie a doklady */
    var dayStart = function (date, m) { return TS.parse(date).setHours(Math.floor(m / 60), m % 60, 0, 0); };
    list.sort(function (a, b) { return (a.date + TS.pad(Math.floor(a.start / 60)) + TS.pad(a.start % 60)).localeCompare(b.date + TS.pad(Math.floor(b.start / 60)) + TS.pad(b.start % 60)); });
    var seq = 286, pendingLeft = 4, todayPast = list.filter(function (b) { return b.date === today && b.start + b.dur <= now; });
    list.forEach(function (b, i) {
      var startTs = dayStart(b.date, b.start), past = b.date < today || (b.date === today && b.start + b.dur <= now);
      var created = past ? startTs - (2 + (i * 5) % 11) * 864e5 - (i * 37 % 300) * 60e3 : Math.min(ts - 3 * 3600e3, startTs - (1 + (i * 3) % 9) * 864e5) - (i * 53 % 400) * 60e3;
      b.createdAt = created;
      b.log.push({ at: created, t: b.source === 'web' ? 'Rezervace z webu' + (b.stAny ? ' (stylistka podle volna)' : '') : 'Zapsáno v salonu (telefonicky)' });
      if (b.source === 'web') b.log.push({ at: created + (25 + i % 90) * 60e3, t: 'Potvrzeno' });
      if (past) {
        if (i % 29 === 7) { b.status = 'noshow'; b.log.push({ at: startTs + 30 * 60e3, t: 'Nedostavila se' }); return; }
        if (i % 37 === 11 && b.date < today) { b.status = 'cancelled'; b.log.push({ at: startTs - 2 * 864e5, t: 'Zrušeno na přání klientky (telefonicky)' }); return; }
        var lastToday = b.date === today && todayPast.indexOf(b) >= todayPast.length - 2;
        if (lastToday) return;   /* dnešní poslední návštěvy čekají na vystavení dokladu */
        b.status = 'done';
        var shd = b.shade && b.shade !== 'poradit' ? L.shade(D, b.shade) : null;
        var items = [{ name: L.svcLabel(D, b) + (shd ? ' – odstín ' + shd.c + ' ' + shd.n : ''), qty: 1, price: L.svc(D, b.svc).p[b.len] }];
        L.exNames(D, b).forEach(function (nm, j) { var x = D.extras.find(function (e) { return e.n === nm; }); items.push({ name: nm, qty: 1, price: x.p[b.len] }); });
        if (i % 9 === 4) items.push({ name: L.PRODUCTS[i % L.PRODUCTS.length][0], qty: 1, price: L.PRODUCTS[i % L.PRODUCTS.length][1] });
        if (i % 13 === 6 && L.svc(D, b.svc).color) items.push({ name: L.MATERIALS[0][0], qty: 2, price: L.MATERIALS[0][1] });
        if (L.free(L.svc(D, b.svc)) && items.length === 1) { b.log.push({ at: startTs + b.dur * 60e3, t: 'Proběhlo' }); return; }
        var issued = startTs + (b.dur + 4) * 60e3;
        b.receipt = L.mkReceipt(D, b, b.date.slice(0, 4) + '-' + TS.pad(++seq).padStart(4, '0'), b.date, i % 5 < 3 ? 'karta' : 'hotove', items, i % 17 === 3 ? { type: 'pct', v: 10, label: 'Věrnostní sleva' } : null, issued);
        b.log.push({ at: issued, t: 'Proběhlo · vystaven doklad ' + b.receipt.no });
      } else if (b.source === 'web' && pendingLeft > 0 && TS.diffDays(today, b.date) >= 1 && TS.diffDays(today, b.date) <= 8 && i % 3 === 0) {
        pendingLeft--; b.status = 'pending';
        b.createdAt = ts - [42, 95, 180, 310][3 - pendingLeft] * 60e3;
        b.log = [{ at: b.createdAt, t: 'Rezervace z webu' + (b.stAny ? ' (stylistka podle volna)' : '') }];
        if (!b.note) b.note = noteFor(D, b.svc, (3 - pendingLeft) / 4 + 0.1);
      } else if (i % 31 === 5) {
        b.status = 'cancelled'; b.log.push({ at: ts - (2 + i % 20) * 3600e3, t: 'Zrušeno na přání klientky' });
      }
    });
    D.rcSeq = seq;
    /* e-maily, které by systém k ukázkovým rezervacím už odeslal */
    var pend = list.filter(function (b) { return b.status === 'pending'; });
    pend.forEach(function (b) { L.mail(D, 'salon_new', b, { at: b.createdAt }); L.mail(D, 'received', b, { at: b.createdAt + 2000 }); });
    list.filter(function (b) { return b.status === 'confirmed' && b.source === 'web' && b.email && b.date >= today; }).sort(function (a, b) { return b.createdAt - a.createdAt; }).slice(0, 3)
      .forEach(function (b) { L.mail(D, 'confirmed', b, { at: Math.min(ts - 20 * 60e3, b.createdAt + 40 * 60e3) }); });
    list.filter(function (b) { return b.receipt && b.email && b.date === TS.addDays(today, -1) || (b.receipt && b.email && b.date === today); }).slice(-2)
      .forEach(function (b) { b.receiptSent = true; L.mail(D, 'receipt', b, { at: b.receipt.issuedAt + 60e3 }); });
    D.emails.sort(function (a, b) { return b.at - a.at; });
    D.emails.forEach(function (e, i) { e.read = i > 2; });
    return D;
  };

  /* ---------- doklad ---------- */
  L.supplier = function (D) {
    var b = D.billing, I = D.info;
    return { name: b.name, salon: I.full, ico: b.ico, dic: b.dic, seat: b.seat, place: I.street + ', ' + I.zip + ' ' + I.city, phone: I.phone, email: I.email, web: I.web, register: b.register, vatPayer: !!b.vatPayer, vatRate: Number(b.vatRate) || 21 };
  };
  L.rcCalc = function (items, disc, vatPayer, rate) {
    var lines = items.map(function (i) { var q = TS.num(i.qty) || 1, p = TS.num(i.price); return { name: i.name, qty: q, price: p, sum: Math.round(q * p * 100) / 100 }; });
    var sub = Math.round(lines.reduce(function (a, l) { return a + l.sum; }, 0) * 100) / 100, dAmt = 0;
    if (disc && TS.num(disc.v) > 0) dAmt = disc.type === 'pct' ? Math.round(sub * Math.min(100, TS.num(disc.v)) / 100) : Math.min(sub, Math.round(TS.num(disc.v)));
    var total = Math.round((sub - dAmt) * 100) / 100, base = null, vat = null;
    if (vatPayer) { base = Math.round(total / (1 + rate / 100) * 100) / 100; vat = Math.round((total - base) * 100) / 100; }
    return { lines: lines, sub: sub, disc: dAmt, total: total, base: base, vat: vat };
  };
  L.mkReceipt = function (D, b, no, issueDate, pay, items, disc, at) {
    var s = L.supplier(D), c = L.rcCalc(items, disc, s.vatPayer, s.vatRate), st = L.st(D, b.st);
    return { no: no, issueDate: issueDate, taxDate: b.date, pay: pay, items: c.lines.map(function (l) { return { name: l.name, qty: l.qty, price: l.price }; }),
      disc: disc && TS.num(disc.v) > 0 ? { type: disc.type, v: TS.num(disc.v), label: disc.label || 'Sleva' } : null, total: c.total, supplier: s, customer: { name: b.name, email: b.email || '' }, stylist: st ? st.n : '', issuedAt: at || Date.now() };
  };
  L.discLabel = function (d) { return (d.label || 'Sleva') + (d.type === 'pct' ? ' ' + String(d.v).replace('.', ',') + ' %' : ''); };
  L.docHTML = function (r) {
    var s = r.supplier, c = L.rcCalc(r.items, r.disc, s.vatPayer, s.vatRate), q = function (n) { return String(n).replace('.', ','); };
    var rows = c.lines.map(function (l) { return '<tr><td>' + esc(l.name) + '</td><td class="r">' + q(l.qty) + '</td><td class="r">' + TS.money(l.price) + '</td><td class="r">' + TS.money(l.sum) + '</td></tr>'; }).join('');
    return '<article class="lk-doc">' +
      '<header class="lk-doc-head"><div><div class="lk-doc-brand">LOKNA</div><div class="lk-doc-sub">' + esc(s.salon) + '</div></div>' +
      '<div class="lk-doc-title"><strong>' + (s.vatPayer ? 'Zjednodušený daňový doklad' : 'Doklad o zaplacení') + '</strong><span>č. ' + esc(r.no) + '</span></div></header>' +
      '<div class="lk-doc-parties"><section><h4>Dodavatel</h4><p><strong>' + esc(s.name) + '</strong><br>Sídlo: ' + esc(s.seat) + '<br>IČO: ' + esc(s.ico) + (s.dic ? '<br>DIČ: ' + esc(s.dic) : '') + '<br>Provozovna: ' + esc(s.place) + '</p>' +
      '<p>Tel.: ' + esc(s.phone) + '<br>E-mail: ' + esc(s.email) + (s.web ? '<br>' + esc(s.web) : '') + '</p></section>' +
      '<section><h4>Odběratel</h4><p><strong>' + esc(r.customer.name) + '</strong>' + (r.customer.email ? '<br>' + esc(r.customer.email) : '') + '</p>' + (r.stylist ? '<h4>Obsluhovala</h4><p>' + esc(r.stylist) + '</p>' : '') + '</section></div>' +
      '<dl class="lk-doc-dates"><div><dt>Datum vystavení</dt><dd>' + TS.fmtDMY(r.issueDate) + '</dd></div><div><dt>Datum uskutečnění plnění</dt><dd>' + TS.fmtDMY(r.taxDate) + '</dd></div><div><dt>Způsob úhrady</dt><dd>' + L.PAY[r.pay] + '</dd></div></dl>' +
      '<div class="lk-doc-tw"><table class="lk-doc-items"><thead><tr><th>Položka</th><th class="r">Množství</th><th class="r">Cena za ks</th><th class="r">Celkem</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
      '<div class="lk-doc-sum">' + (c.disc ? '<div class="row"><span>Mezisoučet</span><span>' + TS.money(c.sub) + '</span></div><div class="row"><span>' + esc(L.discLabel(r.disc)) + '</span><span>−' + TS.money(c.disc) + '</span></div>' : '') +
      (s.vatPayer ? '<div class="row"><span>Základ daně</span><span>' + TS.money2(c.base) + '</span></div><div class="row"><span>DPH ' + s.vatRate + ' %</span><span>' + TS.money2(c.vat) + '</span></div>' : '') +
      '<div class="lk-doc-total"><span>Celkem' + (s.vatPayer ? ' vč. DPH' : '') + '</span><strong>' + TS.money(c.total) + '</strong></div><p class="lk-doc-paid">Uhrazeno ' + L.PAY[r.pay].toLowerCase() + ' dne ' + TS.fmtDMY(r.issueDate) + '</p></div>' +
      '<footer class="lk-doc-foot"><p>' + (s.vatPayer ? 'Sazba DPH ' + s.vatRate + ' %. ' : 'Dodavatel není plátcem DPH. ') + esc(s.register) + '</p><div class="lk-sign"><span class="line"></span><span>Vystavil(a), podpis a razítko</span></div></footer>' +
      '<p class="lk-doc-demo">Ukázkový doklad z ukázkového webu studia T&amp;S. Salon, osoby i všechny údaje jsou smyšlené.</p></article>';
  };

  /* ---------- e-maily ---------- */
  L.brand = function () { return { name: 'Lokna', sub: 'hair studio' }; };
  L.foot = function (D) { var I = D.info; return esc(I.full) + ' · ' + esc(I.street) + ', ' + esc(I.zip + ' ' + I.city) + ' · ' + esc(I.phone) + ' · ' + esc(I.email); };
  L.when = function (b) { return TS.cap(TS.fmtLong(b.date)) + ' v ' + TS.hm(b.start); };
  L.event = function (D, b, staff) {
    var st = L.st(D, b.st), I = D.info;
    if (staff) return { uid: b.id + '-salon', title: L.svcLabel(D, b) + ' – ' + b.name, date: b.date, start: b.start, end: b.start + b.dur,
      details: 'Stylistka: ' + (st ? st.n : '') + '\nTelefon: ' + b.phone + (b.email ? '\nE-mail: ' + b.email : '') + (b.shade ? '\nOdstín: ' + L.shadeTxt(D, b) : '') + (b.note ? '\nPoznámka: ' + b.note : '') };
    return { uid: b.id, title: 'Lokna: ' + b.svcName + (st ? ' u ' + st.gen : ''), date: b.date, start: b.start, end: b.start + b.dur, location: I.full + ', ' + I.street + ', ' + I.zip + ' ' + I.city,
      details: L.svcLabel(D, b) + ', ' + L.durTxt(b.dur) + ', orientačně ' + L.priceTxt(b.price) + '. Změna nebo zrušení: ' + I.phone + '.' };
  };
  L.table = function (D, b, staff) {
    var st = L.st(D, b.st), ex = L.exNames(D, b);
    return TS.mail.table([
      ['Služba', esc(b.svcName) + (ex.length ? ' + ' + esc(ex.join(', ').toLowerCase()) : '')],
      b.flat ? null : ['Vlasy', esc(TS.cap(L.LENS[b.len])) + (b.extras && b.extras.indexOf('huste') >= 0 ? ', velmi husté' : '')],
      b.shade ? ['Odstín', esc(L.shadeTxt(D, b))] : null,
      ['Stylistka', st ? esc(st.n) + (b.stAny ? ' <span style="font-weight:400;color:#6E6E76">(podle volna)</span>' : '') : '–'],
      ['Termín', esc(L.when(b))], ['Délka', L.durTxt(b.dur)], ['Cena', (b.price ? 'orientačně ' : '') + L.priceTxt(b.price)]
    ].concat(staff ? [['Klientka', esc(b.name)], ['Telefon', esc(b.phone)], ['E-mail', b.email ? esc(b.email) : '–'], b.note ? ['Poznámka', esc(b.note)] : null] : (b.note ? [['Vaše poznámka', esc(b.note)]] : [])));
  };
  L.subject = function (kind, b) {
    var when = TS.fmtShort(b.date) + ' v ' + TS.hm(b.start);
    return {
      salon_new: 'Nová žádost: ' + b.svcName + ', ' + when, salon_auto: 'Nová rezervace: ' + b.svcName + ', ' + when,
      received: 'Vaši žádost o termín ' + when + ' máme', confirmed: 'Termín potvrzen: ' + TS.fmtLong(b.date) + ' v ' + TS.hm(b.start),
      changed: 'Změna termínu: nově ' + TS.fmtLong(b.date) + ' v ' + TS.hm(b.start), rejected: 'Termín ' + when + ' vám nemůžeme potvrdit',
      cancelled: 'Rezervace na ' + when + ' je zrušená', reminder: (TS.diffDays(TS.today(), b.date) === 1 ? 'Zítra se uvidíme' : 'Připomínáme termín ' + TS.fmtShort(b.date)) + ' – ' + TS.hm(b.start) + ', Lokna', receipt: 'Doklad k návštěvě ' + TS.fmtDM(b.date) + ' – Lokna'
    }[kind] || 'Rezervace';
  };
  /* oslovení: u ženských příjmení na -á je 5. pád stejný („paní Zelená“), jinak bez jména */
  L.greet = function (b) {
    var p = String(b.name || '').trim().split(/\s+/), last = p[p.length - 1] || '';
    return p.length > 1 && /á$/.test(last) ? 'Dobrý den, paní ' + esc(last) + ',' : 'Dobrý den,';
  };
  function tipList(title, t) { return '<div class="k-em-note" style="margin:14px 0"><strong>' + title + '</strong><ul style="margin:6px 0 0;padding-left:18px">' + t.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul></div>'; }
  /* jak se připravit na návštěvu (potvrzení, přesun, připomínka) */
  L.prep = function (D, b) {
    var s = L.svc(D, b.svc), c = L.catOf(D, b.svc), t = [];
    if (s && s.color) t.push('Vlasy si den předem nemyjte, kožní maz chrání pokožku a barva lépe drží.', 'Pokud jste si v posledních měsících barvila doma nebo hennou, řekněte to prosím stylistce.');
    if (b.svc === 'keratin') t.push('Přijďte s čistými vlasy bez stylingových přípravků. Po ošetření je 72 hodin nemyjte a nesvazujte.');
    if (b.svc === 'svatebni' || b.svc === 'spolecensky') t.push('Přineste fotky účesů, které se vám líbí, a případně ozdoby nebo závoj. Vlasy si umyjte večer předem.');
    if (c && c.id === 'cut' && b.svc !== 'pansky') t.push('Přijďte učesaná tak, jak se nosíte každý den, stylistka uvidí, jak vlasy přirozeně padají.');
    return t.length ? tipList('Jak se připravit', t) : '';
  };
  /* péče po návštěvě (poděkování s dokladem) */
  L.care = function (D, b) {
    var s = L.svc(D, b.svc), c = L.catOf(D, b.svc), t = [];
    if (s && s.color) t.push('První dva dny vlasy nemyjte, barva se teprve usazuje.', 'Myjte vlažnou vodou a šamponem pro barvené vlasy bez sulfátů.', 'Na odrost nebo tónování se uvidíme zhruba za 6 týdnů.');
    else if (b.svc === 'keratin') t.push('72 hodin vlasy nemyjte, nesvazujte ani nezastrkávejte za uši.', 'Potom používejte šampon bez sulfátů a soli, uhlazení vydrží déle.');
    else if (c && c.id === 'cut') t.push('Tvar střihu vydrží 6–8 týdnů, pak je ideální čas na zastřižení.', 'Před foukáním použijte termoochranu, konečky vám poděkují.');
    else t.push('Jednou týdně dopřejte vlasům masku a před žehlením či foukáním vždy použijte termoochranu.');
    return tipList('Tipy na péči', t);
  };
  /* druh: salon_new, salon_auto, received, confirmed, changed, rejected, cancelled, reminder, receipt */
  L.mail = function (D, kind, b, extra) {
    extra = extra || {};
    var I = D.info, st = L.st(D, b.st), salon = kind.indexOf('salon') === 0, to = salon ? D.notify.email : b.email, html;
    if (!to) return null;
    var msg = extra.msg ? '<p class="k-em-quote">' + esc(extra.msg) + '</p>' : '';
    var contact = '<p>Potřebujete termín změnit? Zavolejte nám na ' + esc(I.phone) + ' nebo odpovězte na tento e-mail.</p>';
    var cal = '<div class="k-em-btns">' + TS.mail.btn('Přidat do Google Kalendáře', TS.cal.gcal(L.event(D, b)), true) + '</div>' +
      TS.mail.attach('termin-lokna.ics', 'Příloha pro Apple Kalendář a Outlook', '<button class="k-em-btn" type="button" data-act="icsDl" data-arg="' + esc(b.id) + '">Stáhnout</button>');
    var hello = L.greet(b);
    if (kind === 'salon_new') {
      html = '<h2>Nová žádost o termín</h2><p>Přes web přišla nová žádost. Do vyřízení je čas ' + (st ? 'u ' + esc(st.gen) : '') + ' zablokovaný, takže si ho nikdo jiný nevezme.</p>' + L.table(D, b, true) +
        '<div class="k-em-btns">' + TS.mail.btn('Potvrdit termín', ADMIN + '#rezervace/' + b.id + '/potvrdit', true) + TS.mail.btn('Navrhnout jiný čas', ADMIN + '#rezervace/' + b.id + '/presunout') + TS.mail.btn('Odmítnout', ADMIN + '#rezervace/' + b.id + '/odmitnout') + '</div>' +
        '<p style="font-size:13px;color:#6E6E76">Tlačítka otevřou rezervaci v administraci. Fungují i z telefonu.</p>';
    } else if (kind === 'salon_auto') {
      html = '<h2>Nová rezervace z webu</h2><p>Termín byl volný, a tak se potvrdil automaticky. Klientka už dostala potvrzení.</p>' + L.table(D, b, true) +
        '<div class="k-em-btns">' + TS.mail.btn('Otevřít v administraci', ADMIN + '#rezervace/' + b.id, true) + '</div>';
    } else if (kind === 'received') {
      html = '<h2>Děkujeme, žádost máme</h2><p>' + hello + ' vaši žádost o termín jsme přijali a čas pro vás držíme. Potvrzení pošleme dalším e-mailem, obvykle do několika hodin.</p>' + L.table(D, b) + contact;
    } else if (kind === 'confirmed') {
      html = '<h2>Těšíme se na vás</h2><p>' + hello + ' termín potvrzujeme. ' + (st ? esc(st.n.split(' ')[0]) + ' si na vás vyhradila ' + L.durTxt(b.dur) + '.' : '') + '</p>' + msg + L.table(D, b) + cal +
        L.prep(D, b) + '<p>Najdete nás na adrese ' + esc(I.street) + ', ' + esc(I.city) + '. ' + esc(I.transport) + '</p><p style="font-size:13px;color:#6E6E76">' + esc(D.rules.storno) + '</p>';
    } else if (kind === 'changed') {
      html = '<h2>Posunuli jsme váš termín</h2><p>' + hello + ' váš termín jsme upravili.' + (extra.old ? ' Původně: <span class="k-em-old">' + esc(extra.old) + '</span>' : '') + '</p>' + msg + L.table(D, b) + cal + L.prep(D, b) + contact;
    } else if (kind === 'rejected') {
      html = '<h2>Tento termín vám nemůžeme potvrdit</h2><p>' + hello + ' omlouváme se, ale ' + esc(TS.fmtLong(b.date)) + ' v ' + TS.hm(b.start) + ' vám termín na ' + esc(b.svcName.toLowerCase()) + ' potvrdit nemůžeme.</p>' + msg +
        '<p>Vyberte si prosím jiný volný čas na webu, nebo nám zavolejte na ' + esc(I.phone) + ', rádi poradíme.</p><div class="k-em-btns">' + TS.mail.btn('Vybrat jiný termín', WEB + '?sluzba=' + b.svc + '&delka=' + b.len, true) + '</div>';
    } else if (kind === 'cancelled') {
      html = '<h2>Rezervace je zrušená</h2><p>' + hello + ' vaše rezervace na ' + esc(TS.fmtLong(b.date)) + ' v ' + TS.hm(b.start) + ' (' + esc(b.svcName.toLowerCase()) + ') je zrušená.</p>' + msg +
        '<div class="k-em-btns">' + TS.mail.btn('Rezervovat nový termín', WEB + '?sluzba=' + b.svc + '&delka=' + b.len, true) + '</div>';
    } else if (kind === 'reminder') {
      var zit = TS.diffDays(TS.today(), b.date) === 1;
      html = '<h2>' + (zit ? 'Zítra se uvidíme' : 'Brzy se uvidíme') + '</h2><p>' + hello + ' připomínáme ' + (zit ? 'zítřejší termín' : 'váš termín ' + esc(TS.fmtLong(b.date))) + '. Kdybyste nemohla přijít, dejte nám prosím vědět co nejdřív, ať čas nabídneme další klientce.</p>' + L.table(D, b) + cal +
        L.prep(D, b);
    } else if (kind === 'receipt') {
      var r = b.receipt;
      html = '<h2>Děkujeme za návštěvu</h2><p>' + hello + ' posíláme doklad o zaplacení k vaší návštěvě ' + TS.fmtDMY(b.date) + '.</p>' +
        (r ? '<div class="lk-doc-wrap">' + L.docHTML(r) + '</div>' + TS.mail.attach('doklad-' + r.no + '.pdf', 'Doklad v PDF', '<button class="k-em-btn" type="button" data-act="pdfDl" data-arg="' + esc(b.id) + '">Stáhnout PDF</button>') : '') +
        L.care(D, b) + '<p>Další termín si můžete kdykoli rezervovat online.</p><div class="k-em-btns">' + TS.mail.btn('Rezervovat další termín', WEB, true) + '</div>';
    }
    return TS.mail.add(D, { box: salon ? 'salon' : 'klient', to: to, from: salon ? 'Rezervační systém Lokna <noreply@lokna-studio.cz>' : I.full + ' <' + I.email + '>',
      subject: extra.subject || L.subject(kind, b), html: TS.mail.wrap(L.brand(), html, L.foot(D)), ref: b.id, kind: kind, at: extra.at || Date.now() });
  };
  L.log = function (b, t) { b.log = (b.log || []).slice(-14); b.log.push({ at: Date.now(), t: t }); };
  L.order = function (b) { return b.date + TS.pad(Math.floor(b.start / 60)) + TS.pad(b.start % 60); };
})();
