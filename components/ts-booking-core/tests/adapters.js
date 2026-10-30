/* Převod dat ukázek tasstudio.eu (Lokna, Motoria, Kořen) do neutrální konfigurace ts-booking-core.
   Slouží rozdílovým testům a jako vzor migrace z ukázkových dat. Nic z ukázek nemění (data se kopírují). */
(function () {
  'use strict';
  var A = window.TSBookingAdapters = {};
  function hours(list) { return list.map(function (h) { return h ? { open: !!(h.on != null ? h.on : h.open), from: h.from, to: h.to } : null; }); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  /* Lokna – salon: stylistky (zdroje s pracovní dobou a dovednostmi), služby s variantou délky vlasů, příplatky */
  A.fromLokna = function (D, L) {
    var svcs = [];
    D.cats.forEach(function (c) { c.items.forEach(function (s) { svcs.push({ id: s.id, name: s.n, durations: s.t.slice(), prices: s.p.slice(), buffer: s.buf || 0, active: s.on !== false }); }); });
    return {
      config: {
        mode: 'resources', step: D.rules.step, gap: D.rules.gap || 0, leadMin: D.rules.leadMin || 0, maxDays: D.rules.maxDays,
        activeStatuses: L.ACTIVE.slice(), hours: hours(D.hours), services: svcs,
        addons: D.extras.map(function (x) { return { id: x.id, name: x.n, services: x.svcs.slice(), durations: x.t.slice(), prices: x.p.slice() }; }),
        resources: D.team.map(function (s) { return { id: s.id, name: s.n || s.name, services: s.svcs.slice(), hours: hours(s.hours), away: clone(s.away || []), bookable: s.web !== false }; })
      },
      state: {
        bookings: D.bookings.map(function (b) { return { id: b.id, date: b.date, resource: b.st, service: b.svc, start: b.start, dur: b.dur, buffer: b.buf || 0, status: b.status, name: b.name }; }),
        blocks: D.blocks.map(function (k) { return { date: k.date, resource: k.st, from: k.from, to: k.to, reason: k.reason }; })
      }
    };
  };

  /* Motoria – autosalon: zdroj = vůz, sdílený pool prodejců, předstih jen pro dnešek */
  A.fromMotoria = function (D, M) {
    var dr = D.drive;
    return {
      config: {
        mode: 'resources', step: dr.step, minStep: 1, gap: 0, leadMin: dr.lead, leadScope: 'today-only', adminMinStart: 'now', maxDays: dr.ahead,
        activeStatuses: M.ACTIVE.slice(), hours: hours(D.hours), fullDayBlockCloses: false, blocksIncludeBuffer: false,
        closures: (D.closures || []).map(function (c) { return { from: c.from, to: c.to, note: c.note }; }),
        services: [{ id: 'testovaci-jizda', name: 'Testovací jízda', durations: [dr.dur], buffer: dr.buffer }],
        resources: D.cars.map(function (c) { return { id: c.id, name: M.title(c), services: '*' }; }),
        pool: { members: M.driveStaff(D).map(function (s) { return s.id; }) }
      },
      state: {
        bookings: D.drives.map(function (d) { return { id: d.id, date: d.date, resource: d.car, service: 'testovaci-jizda', start: d.time, dur: d.dur, buffer: dr.buffer, status: d.status, poolMember: d.staff }; }),
        blocks: (D.blocks || []).map(function (k) { return { date: k.date, resource: k.car || 'all', from: k.from, to: k.to, reason: k.reason || k.note }; })
      }
    };
  };

  /* Kořen – restaurace: sdílená kapacita míst, dva druhy rezervace s různou délkou */
  A.KOREN_TASTING = 'Degustační menu';
  A.fromKoren = function (D, K) {
    return {
      config: {
        mode: 'capacity', step: D.res.step, activeStatuses: K.ACTIVE.slice(), hours: hours(D.hours),
        closures: (D.closures || []).map(function (c) { return { from: c.from, to: c.to, open: !!c.open, hours: c.open ? [c.fromT, c.toT] : null, note: c.note }; }),
        services: [{ id: 'stul', name: 'Stůl', durations: [D.res.dur] }, { id: 'degustace', name: A.KOREN_TASTING, durations: [D.res.durTasting] }],
        capacity: { units: D.res.capacity, step: D.res.step, sampleMin: 15, lastBeforeCloseMin: D.res.lastBefore, todayLeadMin: 60, serviceStart: { degustace: D.tasting.from } }
      },
      state: {
        bookings: D.bookings.map(function (b) { return { id: b.id, date: b.date, service: b.occ === A.KOREN_TASTING ? 'degustace' : 'stul', start: b.time, units: b.guests, status: b.status }; }),
        blocks: []
      }
    };
  };
})();
