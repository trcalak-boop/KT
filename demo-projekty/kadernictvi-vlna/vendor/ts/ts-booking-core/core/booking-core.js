/* ==========================================================================
   ts-booking-core – univerzální jádro rezervací T&S Studio
   Volné časy, kolize, kapacita, výběr pracovníka a kalendářové výstupy pro libovolný obor
   (salon, autosalon, restaurace, ordinace…). Bez DOM, bez úložiště, bez textů rozhraní.

   Algoritmy jsou převzaté z ukázek tasstudio.eu a zobecněné do konfigurace:
     • zdroje s vlastní pracovní dobou a dovednostmi  ← Lokna (L.slots, L.busy, L.stWin, L.nextFree …)
     • konkrétní zdroj + sdílený pool lidí            ← Motoria (M.slots, M.pickStaff)
     • sdílená kapacita míst                           ← Kořen (K.times, K.load)
     • volné začátky v okně, ICS, Google Kalendář      ← ts-kit-core TS.cal
   Shodu chování s originály ověřují tests/differential.test.html.

   Oborová pravidla se NEPÍŠOU do tohoto souboru: nastavují se v konfiguraci (viz config/schema.json)
   nebo se přidávají jako rozšíření: TSBooking.create(config, state, { extensions: [{ name, check }] }).
   ========================================================================== */
(function (root) {
  'use strict';
  var B = root.TSBooking = { version: '1.1.0' };

  /* ---------- datum: řetězce YYYY-MM-DD, místní čas, poledne kvůli změně času (shodně s ts-kit-core) ---------- */
  function pad(n) { return String(n).padStart(2, '0'); }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var a = String(s).split('-').map(Number); return new Date(a[0], a[1] - 1, a[2], 12); }
  function addDays(s, n) { var d = parse(s); d.setDate(d.getDate() + n); return iso(d); }
  function diffDays(a, b) { return Math.round((parse(b) - parse(a)) / 864e5); }
  function wd(s) { return parse(s).getDay(); }
  B.date = { iso: iso, parse: parse, addDays: addDays, diffDays: diffDays, wd: wd, pad: pad,
    hm: function (m) { m = Math.round(m); return Math.floor(m / 60) + ':' + pad(m % 60); } };

  /* hodiny prohlížeče/serveru; v testech a na serveru se dá předat vlastní { today(), nowMin() } */
  B.systemClock = {
    today: function () { return iso(new Date()); },
    nowMin: function () { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
  };

  /* výchozí hodnoty konfigurace (odpovídají chování ukázky Lokna) */
  B.DEFAULTS = {
    mode: 'resources',            // 'resources' = zdroje a služby, 'capacity' = sdílená kapacita míst
    step: 15, minStep: 5,         // krok nabídky začátků (min.)
    gap: 0,                       // pauza po každé rezervaci (min.)
    leadMin: 0,                   // nejkratší předstih rezervace (min.)
    leadScope: 'all-days',        // 'all-days' = předstih platí přes půlnoc, 'today-only' = jen dnes
    adminMinStart: 'none',        // administrace: 'none' = bez omezení časem, 'now' = od teď
    maxDays: 60,                  // jak daleko dopředu lze rezervovat (dny)
    activeStatuses: ['pending', 'confirmed'],
    hours: null,                  // [7] podle dne v týdnu (0 = neděle): { open, from, to } v minutách
    closures: [],                 // [{ from, to, open?, hours?: [from, to], note? }] – svátky, dovolená, jiná doba
    fullDayBlockCloses: true,     // blokace „all“ přes celou otevírací dobu zavře den
    blocksIncludeBuffer: true,    // blokace se porovnává i s přípravou po službě
    services: [],                 // [{ id, name, durations: [], prices: [], buffer, active }]
    addons: [],                   // [{ id, name, services: [ids], durations: [], prices: [] }]
    resources: [],                // [{ id, name, services: '*' | [ids], hours?: [7], away?: [{ from, to }], bookable }]
    pool: null,                   // { members: [ids] } – souběh rezervací omezený počtem lidí (prodejci, sestry)
    spread: { perDay: 2, minGapMin: 90 },   // rozložení nabídky „nejbližší volné termíny“
    capacity: null,               // režim capacity: { units, step, sampleMin, lastBeforeCloseMin, todayLeadMin, serviceStart: { id: min } }
    ics: { prodId: '-//T&S Studio//ts-booking-core//CS', uidDomain: 'booking.local', timezone: 'Europe/Prague', alarmMin: 120 }
  };

  function merge(cfg) {
    var c = {}, k;
    for (k in B.DEFAULTS) c[k] = B.DEFAULTS[k];
    for (k in cfg || {}) if (cfg[k] !== undefined) c[k] = cfg[k];
    c.ics = Object.assign({}, B.DEFAULTS.ics, (cfg || {}).ics || {});
    c.spread = Object.assign({}, B.DEFAULTS.spread, (cfg || {}).spread || {});
    return c;
  }

  /* kontrola konfigurace: vrací seznam chyb (prázdný = v pořádku) */
  B.validate = function (cfg) {
    var e = [], c = merge(cfg);
    if (['resources', 'capacity'].indexOf(c.mode) < 0) e.push('mode: resources | capacity');
    if (!Array.isArray(c.hours) || c.hours.length !== 7) e.push('hours: pole 7 dnů (0 = neděle)');
    else c.hours.forEach(function (h, i) { if (h && h.open && !(h.to > h.from)) e.push('hours[' + i + ']: to musí být větší než from'); });
    if (!(c.step > 0)) e.push('step: kladné číslo');
    var ids = {};
    (c.services || []).forEach(function (s, i) {
      if (!s.id) e.push('services[' + i + ']: chybí id');
      if (ids['s:' + s.id]) e.push('services: duplicitní id ' + s.id); ids['s:' + s.id] = 1;
      if (!Array.isArray(s.durations) || !s.durations.length) e.push('services[' + i + ']: durations');
    });
    (c.resources || []).forEach(function (r, i) {
      if (!r.id) e.push('resources[' + i + ']: chybí id');
      if (ids['r:' + r.id]) e.push('resources: duplicitní id ' + r.id); ids['r:' + r.id] = 1;
      if (r.services !== '*' && !Array.isArray(r.services)) e.push('resources[' + i + ']: services = "*" nebo pole id');
    });
    if (c.mode === 'resources' && !(c.resources || []).length) e.push('resources: alespoň jeden zdroj');
    if (c.mode === 'capacity' && !(c.capacity && c.capacity.units > 0)) e.push('capacity.units: kladné číslo');
    return e;
  };

  /* ==========================================================================
     Engine: create(config, state, opts) – state = { bookings: [], blocks: [] } se čte při každém volání,
     takže ho lze průběžně měnit (nová rezervace, zrušení) bez nového vytvoření.
       booking: { id, date, resource, service, start, dur, buffer?, status, units?, poolMember?, name? }
       block:   { date, resource: id | 'all' | null, from, to, reason? }
     ========================================================================== */
  B.create = function (config, state, opts) {
    opts = opts || {};
    var c = merge(config), S = state || {}, clock = opts.clock || B.systemClock, ext = opts.extensions || [];
    var E = { config: c, state: S };
    function bookings() { return S.bookings || []; }
    function blocks() { return S.blocks || []; }
    function active(b) { return c.activeStatuses.indexOf(b.status) >= 0; }
    function blockAll(k) { return k.resource == null || k.resource === 'all'; }
    /* rezervace vynechaná při úpravě (except); bez except se nevynechává nic – ani rezervace bez id (veřejná dostupnost ze serveru) */
    function skip(b, id) { return id != null && b.id === id; }
    E.isActive = active;

    E.service = function (id) { return (c.services || []).find(function (s) { return s.id === id; }) || null; };
    E.resource = function (id) { return (c.resources || []).find(function (r) { return r.id === id; }) || null; };
    E.resourceIndex = function (id) { var i = (c.resources || []).findIndex(function (r) { return r.id === id; }); return i < 0 ? 0 : i; };
    E.canDo = function (r, svcId) { return !!r && (r.services === '*' || (r.services || []).indexOf(svcId) >= 0); };
    E.resourcesFor = function (svcId) { return (c.resources || []).filter(function (r) { return r.bookable !== false && E.canDo(r, svcId); }); };
    E.addonsFor = function (svcId) { return (c.addons || []).filter(function (x) { return (x.services || []).indexOf(svcId) >= 0; }); };

    /* délka, cena a příprava služby pro variantu (např. délka vlasů) a doplňky */
    E.calc = function (svcId, variant, addons) {
      var s = E.service(svcId); if (!s) return { dur: 0, price: 0, buffer: 0 };
      var n = s.durations.length - 1;
      variant = Math.max(0, Math.min(n, +variant || 0));
      var dur = s.durations[variant], price = (s.prices || [])[variant] || 0;
      (addons || []).forEach(function (id) {
        var x = (c.addons || []).find(function (a) { return a.id === id; });
        if (x && (x.services || []).indexOf(svcId) >= 0) { dur += x.durations[Math.min(variant, x.durations.length - 1)]; price += (x.prices || [])[Math.min(variant, (x.prices || [0]).length - 1)] || 0; }
      });
      return { dur: dur, price: price, buffer: s.buffer || 0 };
    };

    /* ---------- otevírací doba ---------- */
    E.closureFor = function (date) { return (c.closures || []).find(function (k) { return date >= k.from && date <= k.to; }) || null; };
    /* { open, from, to, note } pro místo (provozovnu) */
    E.dayHours = function (date) {
      var k = E.closureFor(date);
      if (k) return k.open && k.hours ? { open: true, from: k.hours[0], to: k.hours[1], note: k.note } : { open: false, note: k.note };
      var h = c.hours[wd(date)];
      if (!h || !h.open || h.to <= h.from) return { open: false };
      if (c.fullDayBlockCloses && blocks().some(function (k) { return blockAll(k) && k.date === date && k.from <= h.from && k.to >= h.to; })) return { open: false };
      return { open: true, from: h.from, to: h.to };
    };
    E.away = function (r, date) { return (r && r.away || []).find(function (a) { return date >= a.from && date <= a.to; }) || null; };
    /* pracovní okno zdroje v den: [from, to] nebo null */
    E.window = function (r, date) {
      if (!r || r.bookable === false) return null;
      var d = E.dayHours(date); if (!d.open) return null;
      var a = d.from, b = d.to;
      if (r.hours) { var h = r.hours[wd(date)]; if (!h || !h.open) return null; a = Math.max(a, h.from); b = Math.min(b, h.to); }
      if (E.away(r, date)) return null;
      return b > a ? [a, b] : null;
    };

    /* ---------- obsazenost ---------- */
    E.busy = function (resId, date, exceptId) {
      var out = [];
      bookings().forEach(function (b) {
        if (b.date !== date || b.resource !== resId || !active(b) || skip(b, exceptId)) return;
        out.push([b.start, b.start + b.dur + (b.buffer || 0) + c.gap]);
      });
      return out;
    };
    E.blocksFor = function (resId, date) {
      return blocks().filter(function (k) { return k.date === date && (k.resource === resId || blockAll(k)); }).map(function (k) { return [k.from, k.to]; });
    };
    E.inRange = function (date) { var dd = diffDays(clock.today(), date); return dd >= 0 && dd <= c.maxDays; };
    E.minStart = function (date, admin) {
      if (admin && c.adminMinStart !== 'now') return null;
      var lead = admin ? 0 : (c.leadMin || 0);
      if (c.leadScope === 'today-only') return date === clock.today() ? clock.nowMin() + lead : null;
      return clock.nowMin() + lead - diffDays(clock.today(), date) * 1440;
    };

    /* všechny možné začátky pro zdroj a službu: [{ t, ok, why }]; why = past | busy | block | pool | <rozšíření> */
    E.scan = function (date, resId, svcId, o) {
      o = o || {};
      var r = E.resource(resId), w = E.window(r, date); if (!w) return [];
      var k = E.calc(svcId, o.variant, o.addons); if (!k.dur) return [];
      var step = Math.max(c.minStep, c.step), dur = k.dur, after = k.buffer + c.gap;
      var ms = E.minStart(date, o.admin), busy = E.busy(resId, date, o.except), blk = E.blocksFor(resId, date);
      var pool = c.pool && c.pool.members ? c.pool.members.length || 1 : 0;
      var all = pool ? bookings().filter(function (b) { return b.date === date && active(b) && !skip(b, o.except); }) : [];
      var out = [];
      for (var t = w[0]; t + dur <= w[1]; t += step) {
        var why = '', end = t + dur + after;
        if (ms != null && t < ms) why = 'past';
        else if (busy.some(function (b) { return t < b[1] && b[0] < end; })) why = 'busy';
        else if (blk.some(function (b) { return t < b[1] && b[0] < (c.blocksIncludeBuffer ? end : t + dur); })) why = 'block';
        else if (pool && all.filter(function (b) { return t < b.start + b.dur && b.start < t + dur; }).length >= pool) why = 'pool';
        else for (var i = 0; i < ext.length && !why; i++) why = ext[i].check({ engine: E, date: date, t: t, dur: dur, resource: r, service: svcId, opts: o }) || '';
        out.push({ t: t, ok: !why, why: why });
      }
      return out;
    };
    E.freeTimes = function (date, resId, svcId, o) { return E.scan(date, resId, svcId, o).filter(function (s) { return s.ok; }).map(function (s) { return s.t; }); };

    /* volné začátky pro službu přes zdroje: [{ t, resources: [id…] }]; resId = id | 'any' */
    E.slots = function (date, svcId, o) {
      o = o || {};
      if (!o.admin && !E.inRange(date)) return [];
      if (!E.calc(svcId, o.variant, o.addons).dur) return [];
      var rid = o.resource, rs = rid === 'any' || !rid ? E.resourcesFor(svcId) : [E.resource(rid)].filter(function (r) { return r && (o.admin || r.bookable !== false) && E.canDo(r, svcId); });
      var map = {};
      rs.forEach(function (r) { E.freeTimes(date, r.id, svcId, o).forEach(function (t) { (map[t] = map[t] || []).push(r.id); }); });
      return Object.keys(map).map(Number).sort(function (a, b) { return a - b; }).map(function (t) { return { t: t, resources: map[t] }; });
    };
    /* „je mi to jedno“: zdroj s nejmenším vytížením ten den, při shodě dřív uvedený */
    E.pickResource = function (date, ids) {
      var load = function (id) { return bookings().filter(function (b) { return b.date === date && b.resource === id && active(b); }).reduce(function (a, b) { return a + b.dur; }, 0); };
      return ids.slice().sort(function (a, b) { return load(a) - load(b) || E.resourceIndex(a) - E.resourceIndex(b); })[0];
    };
    /* člen poolu (prodejce, sestra) volný v daném čase, nejméně vytížený ten den; '' = nikdo */
    E.pickPoolMember = function (date, time, dur, exceptId) {
      var ds = bookings().filter(function (b) { return b.date === date && active(b) && !skip(b, exceptId); });
      var free = ((c.pool && c.pool.members) || []).filter(function (m) { return !ds.some(function (b) { return b.poolMember === m && time < b.start + b.dur && b.start < time + dur; }); });
      free.sort(function (a, b) { return ds.filter(function (d) { return d.poolMember === a; }).length - ds.filter(function (d) { return d.poolMember === b; }).length; });
      return free[0] || '';
    };
    /* stav dne pro kalendář: past | closed | free | full */
    E.dayState = function (date, svcId, o) {
      if (date < clock.today()) return { s: 'past', n: 0 };
      if (!E.inRange(date) || !E.dayHours(date).open) return { s: 'closed', n: 0 };
      var n = E.slots(date, svcId, o).length;
      return { s: n ? 'free' : 'full', n: n };
    };
    /* nejbližší volné termíny, rozložené v rámci dne: [{ date, t, resource }] */
    E.nextFree = function (svcId, o, limit) {
      o = o || {};
      var out = [], t0 = clock.today(), per = c.spread.perDay, gapMin = c.spread.minGapMin;
      for (var i = 0; i <= c.maxDays && out.length < limit; i++) {
        var d = addDays(t0, i), sl = E.slots(d, svcId, o), taken = 0, last = -999;
        sl.forEach(function (x) {
          if (out.length >= limit || taken >= per || x.t - last < gapMin) return;
          out.push({ date: d, t: x.t, resource: x.resources.length > 1 ? E.pickResource(d, x.resources) : x.resources[0] }); taken++; last = x.t;
        });
      }
      return out;
    };
    /* kontrola ručně zadaného termínu (administrace): kolize, mimo pracovní dobu, nepřítomnost */
    E.conflicts = function (date, start, dur, buffer, resId, exceptId) {
      var end = start + dur + (buffer || 0), items = [];
      bookings().forEach(function (b) { if (b.date === date && b.resource === resId && active(b) && !skip(b, exceptId) && start < b.start + b.dur + (b.buffer || 0) && b.start < end) items.push({ type: 'booking', id: b.id, start: b.start, name: b.name || '' }); });
      blocks().forEach(function (k) { if (k.date === date && (k.resource === resId || blockAll(k)) && start < k.to && k.from < end) items.push({ type: 'block', reason: k.reason || '' }); });
      var r = E.resource(resId), w = E.window(r, date);
      return { items: items, inHours: !!w && start >= w[0] && start + dur <= w[1], away: r ? E.away(r, date) : null };
    };

    /* ---------- režim capacity (stoly, místa, kurzy): sdílená kapacita, rezervace má počet míst units ---------- */
    function capDur(svcId) { var s = E.service(svcId); return s ? s.durations[0] : 0; }
    E.capacityLoad = function (date, t, dur, exceptId) {
      var cap = c.capacity || {}, sample = cap.sampleMin || 15, max = 0;
      var bs = bookings().filter(function (b) { return b.date === date && active(b) && !skip(b, exceptId); });
      for (var m = t; m < t + dur; m += sample) {
        var s = 0; bs.forEach(function (b) { var e = b.start + capDur(b.service); if (b.start <= m && m < e) s += b.units || 1; });
        if (s > max) max = s;
      }
      return max;
    };
    /* časy pro formulář: [{ t, ok, left }] */
    E.capacityTimes = function (date, units, svcId, exceptId) {
      var cap = c.capacity || {}, h = E.dayHours(date); if (!h.open) return [];
      var dur = capDur(svcId), st = cap.serviceStart && cap.serviceStart[svcId] != null ? Math.max(h.from, cap.serviceStart[svcId]) : h.from;
      var last = h.to - (cap.lastBeforeCloseMin || 0), now = clock.nowMin(), today = date === clock.today(), out = [];
      for (var t = st; t <= last; t += (cap.step || c.step)) {
        var left = cap.units - E.capacityLoad(date, t, Math.min(dur, h.to - t), exceptId);
        out.push({ t: t, ok: left >= units && !(today && t < now + (cap.todayLeadMin || 0)), left: left });
      }
      return out;
    };

    /* ---------- založení rezervace: ověří, že termín je pořád volný, a doplní zdroj, délku a člena poolu ----------
       req: { date, service, start, variant?, addons?, resource? ('any'), units?, status?, admin?, except? } + libovolná další pole (jméno, kontakt…)
       → { ok: true, booking } | { ok: false, error: 'service' | 'closed' | 'taken' | 'capacity' | <why> }
       Nic neukládá – uložení řeší adaptér úložiště (adapters/), na serveru se kontrola musí zopakovat. */
    E.prepareBooking = function (req) {
      var o = { variant: req.variant, addons: req.addons, admin: req.admin, except: req.except };
      var svc = E.service(req.service); if (!svc) return { ok: false, error: 'service' };
      var b = {}, k; for (k in req) if (['admin', 'except'].indexOf(k) < 0) b[k] = req[k];
      b.status = req.status || c.activeStatuses[0];
      if (c.mode === 'capacity') {
        var units = req.units || 1, slot = E.capacityTimes(req.date, units, req.service, req.except).find(function (x) { return x.t === req.start; });
        if (!slot) return { ok: false, error: 'closed' };
        if (!slot.ok) return { ok: false, error: 'capacity' };
        b.units = units; b.dur = capDur(req.service); delete b.resource; delete b.variant; delete b.addons;
        return { ok: true, booking: b };
      }
      if (!o.admin && !E.inRange(req.date)) return { ok: false, error: 'closed' };
      var cand = req.resource && req.resource !== 'any' ? [req.resource] : E.resourcesFor(req.service).map(function (r) { return r.id; });
      var why = 'closed', free = cand.filter(function (id) {
        var s = E.scan(req.date, id, req.service, o).find(function (x) { return x.t === req.start; });
        if (s && !s.ok && why === 'closed') why = s.why === 'busy' || s.why === 'block' ? 'taken' : s.why;
        return s && s.ok;
      });
      if (!free.length) return { ok: false, error: why };
      var calc = E.calc(req.service, req.variant, req.addons);
      b.resource = free.length > 1 ? E.pickResource(req.date, free) : free[0];
      b.dur = calc.dur; b.buffer = calc.buffer; b.price = calc.price;
      if (c.pool && c.pool.members && c.pool.members.length) b.poolMember = E.pickPoolMember(req.date, req.start, calc.dur, req.except);
      return { ok: true, booking: b };
    };

    /* ---------- kalendářové výstupy ---------- */
    E.ics = function (ev) { return B.ics(ev, c.ics); };
    E.gcal = function (ev) { return B.gcal(ev, c.ics); };
    return E;
  };

  /* událost: { uid, title, date, start, end, location?, details? } (start/end v minutách) */
  B.ics = function (o, opt) {
    opt = Object.assign({}, B.DEFAULTS.ics, opt || {});
    var d = o.date.replace(/-/g, ''), f = function (m) { return d + 'T' + pad(Math.floor(m / 60)) + pad(m % 60) + '00'; };
    var e = function (s) { return String(s || '').replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n'); };
    var now = new Date(), st = now.getUTCFullYear() + pad(now.getUTCMonth() + 1) + pad(now.getUTCDate()) + 'T' + pad(now.getUTCHours()) + pad(now.getUTCMinutes()) + '00Z';
    return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:' + opt.prodId, 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT', 'UID:' + (o.uid || Date.now().toString(36)) + '@' + opt.uidDomain, 'DTSTAMP:' + st,
      'DTSTART;TZID=' + opt.timezone + ':' + f(o.start), 'DTEND;TZID=' + opt.timezone + ':' + f(o.end), 'SUMMARY:' + e(o.title), o.location ? 'LOCATION:' + e(o.location) : '', o.details ? 'DESCRIPTION:' + e(o.details) : '',
      opt.alarmMin ? 'BEGIN:VALARM' : '', opt.alarmMin ? 'TRIGGER:-PT' + (opt.alarmMin % 60 ? opt.alarmMin + 'M' : opt.alarmMin / 60 + 'H') : '', opt.alarmMin ? 'ACTION:DISPLAY' : '', opt.alarmMin ? 'DESCRIPTION:' + e(o.title) : '', opt.alarmMin ? 'END:VALARM' : '',
      'END:VEVENT', 'END:VCALENDAR'].filter(Boolean).join('\r\n');
  };
  B.gcal = function (o, opt) {
    opt = Object.assign({}, B.DEFAULTS.ics, opt || {});
    var d = o.date.replace(/-/g, ''), f = function (m) { return d + 'T' + pad(Math.floor(m / 60)) + pad(m % 60) + '00'; };
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(o.title) + '&dates=' + f(o.start) + '/' + f(o.end) +
      '&ctz=' + encodeURIComponent(opt.timezone) + (o.details ? '&details=' + encodeURIComponent(o.details) : '') + (o.location ? '&location=' + encodeURIComponent(o.location) : '');
  };

  if (typeof module === 'object' && module.exports) module.exports = B;
})(typeof window !== 'undefined' ? window : globalThis);
