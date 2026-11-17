/* Rozdílové testy: stejná data → originální logika ukázky (jen čtení ze zdrojového projektu) vs. ts-booking-core.
   Výsledky musí být identické. Čas je zmrazený (parametr ?cas=…, výchozí úterý 3. 11. 2026 10:17). */
var CAS = window.CAS || '2026-11-03T10:17:00', GAP = window.GAP || 0;
T.freeze(CAS);
T.run(function () {
  var W = T.src('tas-web') + '/web/ukazky/';
  return T.scripts([W + 'kit/kit.js', W + 'lokna/data.js', W + 'motoria/data.js', W + 'koren/data.js',
    T.lib('components/ts-booking-core/core/booking-core.js'), T.lib('components/ts-booking-core/tests/adapters.js')]).then(function () {
    var TS = window.TS, B = window.TSBooking, AD = window.TSBookingAdapters, today = TS.today(), n = 0;
    T.info('čas ' + CAS + ', dnes ' + today);
    function days(a, b) { var out = []; for (var i = a; i <= b; i++) out.push(TS.addDays(today, i)); return out; }

    /* ======================= Lokna (salon, stylistky) ======================= */
    var L = window.LOKNA, D = L.defaults();
    /* doplníme blokace, nepřítomnost a pauzu, ať se otestují všechny větve */
    var team = D.team;
    D.rules.gap = GAP;
    D.blocks.push({ id: 'k1', st: team[0].id, date: TS.addDays(today, 1), from: 600, to: 690, reason: 'školení' });
    D.blocks.push({ id: 'k2', st: 'all', date: TS.addDays(today, 3), from: 0, to: 1440, reason: 'inventura' });
    D.blocks.push({ id: 'k3', st: 'all', date: TS.addDays(today, 5), from: 720, to: 780, reason: 'porada' });
    team[1].away = (team[1].away || []).concat([{ from: TS.addDays(today, 7), to: TS.addDays(today, 9) }]);
    T.info('Lokna: ' + D.bookings.length + ' rezervací, ' + team.length + ' stylistky, gap ' + D.rules.gap + ', leadMin ' + D.rules.leadMin);
    var X = AD.fromLokna(D, L), E = B.create(X.config, X.state);
    T.eq(B.validate(X.config), [], 'Lokna: konfigurace je platná');
    var svcs = X.config.services.map(function (s) { return s.id; }), sts = ['any'].concat(team.map(function (s) { return s.id; }));
    var range = days(-1, D.rules.maxDays + 1), bad = 0;
    svcs.forEach(function (sv) {
      [0, 1, 2].forEach(function (len) {
        T.eq(E.calc(sv, len, []), (function (c) { return { dur: c.dur, price: c.price, buffer: c.buf }; })(L.calc(D, sv, len, [])), 'Lokna calc ' + sv + '/' + len);
        sts.forEach(function (st) {
          [false, true].forEach(function (adm) {
            var a = [], b = [];
            range.forEach(function (d) {
              a.push(L.slots(D, d, sv, len, [], st, null, { admin: adm }).map(function (x) { return [x.t, x.sts]; }));
              b.push(E.slots(d, sv, { variant: len, resource: st, admin: adm }).map(function (x) { return [x.t, x.resources]; }));
            });
            n += range.length;
            if (!T.eq(b, a, 'Lokna slots ' + sv + '/' + len + '/' + st + '/admin=' + adm)) bad++;
          });
        });
      });
      L.extrasFor(D, sv).forEach(function (x) {
        var a = range.map(function (d) { return L.slots(D, d, sv, 1, [x.id], 'any').map(function (s) { return [s.t, s.sts]; }); });
        var b = range.map(function (d) { return E.slots(d, sv, { variant: 1, addons: [x.id], resource: 'any' }).map(function (s) { return [s.t, s.resources]; }); });
        n += range.length; T.eq(b, a, 'Lokna slots s příplatkem ' + sv + '+' + x.id);
        T.eq(E.calc(sv, 2, [x.id]).dur, L.calc(D, sv, 2, [x.id]).dur, 'Lokna calc s příplatkem ' + sv + '+' + x.id);
      });
      T.eq(range.map(function (d) { return E.dayState(d, sv, { variant: 0, resource: 'any' }); }), range.map(function (d) { return L.dayState(D, d, sv, 0, [], 'any'); }), 'Lokna dayState ' + sv);
      T.eq(E.nextFree(sv, { variant: 0, resource: 'any' }, 6).map(function (x) { return [x.date, x.t, x.resource]; }), L.nextFree(D, sv, 0, [], 'any', 6).map(function (x) { return [x.date, x.t, x.st]; }), 'Lokna nextFree ' + sv);
    });
    D.bookings.forEach(function (b) {
      var o = L.conflicts(D, b.date, b.start, b.dur, b.buf, b.st, null), g = E.conflicts(b.date, b.start, b.dur, b.buf, b.st, null);
      var txt = g.items.map(function (i) { return i.type === 'booking' ? 'rezervace ' + TS.hm(i.start) + ' (' + i.name + ')' : 'blokace: ' + (i.reason || 'blokováno'); });
      T.eq([txt, g.inHours, g.away], [o.items, o.inHours, o.away], 'Lokna conflicts ' + b.id);
      var ex = range.slice(0, 20).map(function (d) { return L.slots(D, d, b.svc, b.len || 0, [], b.st, b.id).map(function (x) { return x.t; }); });
      var ey = range.slice(0, 20).map(function (d) { return E.slots(d, b.svc, { variant: b.len || 0, resource: b.st, except: b.id }).map(function (x) { return x.t; }); });
      T.eq(ey, ex, 'Lokna slots bez rezervace ' + b.id);
    });
    range.forEach(function (d) { T.eq(E.pickResource(d, team.map(function (s) { return s.id; })), L.pickSt(D, d, team.map(function (s) { return s.id; })), 'Lokna pickSt ' + d); });

    /* ======================= Motoria (autosalon, vozy + pool prodejců) ======================= */
    var M = window.MOTORIA, DM = M.defaults();
    DM.blocks = (DM.blocks || []).concat([{ id: 'b1', car: DM.cars[0].id, date: TS.addDays(today, 1), from: 600, to: 720 }, { id: 'b2', date: TS.addDays(today, 2), from: 840, to: 900 }]);
    T.info('Motoria: ' + DM.drives.length + ' jízd, ' + DM.cars.length + ' vozů, ' + M.driveStaff(DM).length + ' prodejců');
    var XM = AD.fromMotoria(DM, M), EM = B.create(XM.config, XM.state), why = { car: 'busy', staff: 'pool' };
    T.eq(B.validate(XM.config), [], 'Motoria: konfigurace je platná');
    var mr = days(-1, DM.drive.ahead + 2);
    DM.cars.forEach(function (car) {
      [false, true].forEach(function (adm) {
        var a = mr.map(function (d) { return M.slots(DM, car.id, d, { admin: adm }).map(function (s) { return [s.t, s.ok, why[s.why] || s.why]; }); });
        var b = mr.map(function (d) { return EM.scan(d, car.id, 'testovaci-jizda', { admin: adm }).map(function (s) { return [s.t, s.ok, s.why]; }); });
        n += mr.length; T.eq(b, a, 'Motoria slots ' + car.id + '/admin=' + adm);
      });
    });
    DM.drives.forEach(function (d) {
      T.eq(EM.scan(d.date, d.car, 'testovaci-jizda', { except: d.id }).map(function (s) { return [s.t, s.ok]; }), M.slots(DM, d.car, d.date, { except: d.id }).map(function (s) { return [s.t, s.ok]; }), 'Motoria slots bez jízdy ' + d.id);
      T.eq(EM.pickPoolMember(d.date, d.time, d.dur, d.id), M.pickStaff(DM, d.date, d.time, d.dur, d.id), 'Motoria pickStaff ' + d.id);
      T.eq(EM.pickPoolMember(d.date, d.time, d.dur), M.pickStaff(DM, d.date, d.time, d.dur), 'Motoria pickStaff obsazeno ' + d.id);
    });

    /* ======================= Kořen (restaurace, kapacita míst) ======================= */
    var K = window.KOREN, DK = K.defaults();
    T.info('Kořen: ' + DK.bookings.length + ' rezervací, kapacita ' + DK.res.capacity);
    var XK = AD.fromKoren(DK, K), EK = B.create(XK.config, XK.state);
    T.eq(B.validate(XK.config), [], 'Kořen: konfigurace je platná');
    var kr = days(-1, 40).concat([today.slice(0, 4) + '-12-24', today.slice(0, 4) + '-12-31']);
    [['stul', 'Večeře'], ['degustace', AD.KOREN_TASTING]].forEach(function (oc) {
      for (var g = 1; g <= 10; g++) {
        var a = kr.map(function (d) { return K.times(DK, d, g, oc[1]); }), b = kr.map(function (d) { return EK.capacityTimes(d, g, oc[0]); });
        n += kr.length; T.eq(b, a, 'Kořen times ' + oc[0] + '/' + g + ' hostů');
      }
    });
    DK.bookings.forEach(function (b) {
      T.eq(EK.capacityTimes(b.date, b.guests, b.occ === AD.KOREN_TASTING ? 'degustace' : 'stul', b.id), K.times(DK, b.date, b.guests, b.occ, b.id), 'Kořen times bez rezervace ' + b.id);
    });
    var wk = days(0, 7);
    T.ok(E.nextFree(svcs[0], { resource: 'any' }, 3).length === 3, 'Lokna: nabídka termínů není prázdná');
    T.ok(wk.some(function (d) { return EM.freeTimes(d, DM.cars[1].id, 'testovaci-jizda').length > 3; }), 'Motoria: volné časy nejsou prázdné');
    T.ok(wk.some(function (d) { return EK.capacityTimes(d, 2, 'stul').some(function (x) { return x.ok; }); }), 'Kořen: volné stoly nejsou prázdné');
    T.info('porovnáno ' + n + ' dnů × kombinací');
  });
});
