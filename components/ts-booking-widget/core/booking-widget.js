/* ==========================================================================
   ts-booking-widget – veřejný rezervační formulář a přehled rezervací nad ts-booking-core
   Obecné UI pro libovolný obor: obor určuje konfigurace (config) a texty/vzhled (ui, CSS tokeny --tsb-*).
   Žádná rezervační logika tu není – volné časy, kontrola a doplnění rezervace jsou v TSBooking (ts-booking-core).

   TSBookingWidget.mount(el, { config, store, ui, extensions, onBooked })
   TSBookingWidget.mountAdmin(el, { config, store, ui })
   ========================================================================== */
(function (root) {
  'use strict';
  var B = root.TSBooking; if (!B) throw new Error('ts-booking-widget potřebuje ts-booking-core (TSBooking)');
  var W = root.TSBookingWidget = { version: '1.0.1' };

  var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ESC[c]; }); }
  function hm(m) { return Math.floor(m / 60) + ':' + B.date.pad(m % 60); }

  W.LABELS = {
    cs: {
      demo: 'Ukázka: rezervace se ukládá jen v tomto prohlížeči, ne do databáze provozovny. Nikdo ji neuvidí a nikdo vás nebude kontaktovat.',
      service: 'Služba', variant: 'Varianta', resource: 'Kdo', any: 'Kdokoli volný', date: 'Den', time: 'Čas', units: 'Počet osob',
      contact: 'Kontakt', name: 'Jméno a příjmení', email: 'E-mail', phone: 'Telefon', note: 'Poznámka',
      consent: 'Souhlasím se zpracováním údajů pro vyřízení rezervace.', submit: 'Rezervovat', back: 'Zpět',
      noTimes: 'V tento den už není volný čas. Zkuste jiný den.', closed: 'zavřeno', full: 'plno', free: 'volno',
      done: 'Rezervace je přijatá', doneHint: 'Potvrzení by vám v ostrém provozu přišlo e-mailem.', ics: 'Stáhnout do kalendáře (.ics)', gcal: 'Přidat do Google Kalendáře', again: 'Nová rezervace',
      required: 'Vyplňte prosím jméno, platný e-mail a souhlas.', taken: 'Tento čas mezitím někdo obsadil. Vyberte prosím jiný.', from: 'od', min: 'min',
      adminTitle: 'Rezervace', adminEmpty: 'Zatím žádné rezervace.', confirm: 'Potvrdit', cancel: 'Zrušit', reset: 'Obnovit ukázková data',
      status: { pending: 'Čeká na potvrzení', confirmed: 'Potvrzeno', cancelled: 'Zrušeno', done: 'Proběhlo' }
    },
    en: {
      demo: 'Demo: the booking is stored only in this browser, not in the business database. Nobody will see it or contact you.',
      service: 'Service', variant: 'Option', resource: 'With', any: 'Anyone available', date: 'Day', time: 'Time', units: 'People',
      contact: 'Contact', name: 'Full name', email: 'E-mail', phone: 'Phone', note: 'Note',
      consent: 'I agree to the processing of my data to handle the booking.', submit: 'Book', back: 'Back',
      noTimes: 'No free time left on this day. Please try another day.', closed: 'closed', full: 'full', free: 'free',
      done: 'Booking received', doneHint: 'In live operation you would receive a confirmation e-mail.', ics: 'Download calendar file (.ics)', gcal: 'Add to Google Calendar', again: 'New booking',
      required: 'Please enter your name, a valid e-mail and consent.', taken: 'This time was just taken. Please choose another one.', from: 'from', min: 'min',
      adminTitle: 'Bookings', adminEmpty: 'No bookings yet.', confirm: 'Confirm', cancel: 'Cancel', reset: 'Reset demo data',
      status: { pending: 'Pending', confirmed: 'Confirmed', cancelled: 'Cancelled', done: 'Done' }
    }
  };

  function labels(ui) {
    var base = W.LABELS[ui.lang] || W.LABELS.cs, out = {}, k;
    for (k in base) out[k] = base[k];
    for (k in ui.labels || {}) out[k] = ui.labels[k];
    return out;
  }
  function money(n, ui) { return new Intl.NumberFormat(ui.locale || (ui.lang === 'en' ? 'en-GB' : 'cs-CZ')).format(n) + ' ' + (ui.currency || 'Kč'); }
  function dayLabel(date, ui) {
    var d = B.date.parse(date), loc = ui.locale || (ui.lang === 'en' ? 'en-GB' : 'cs-CZ');
    return { wd: new Intl.DateTimeFormat(loc, { weekday: 'short' }).format(d), d: new Intl.DateTimeFormat(loc, { day: 'numeric', month: 'numeric' }).format(d),
      long: new Intl.DateTimeFormat(loc, { weekday: 'long', day: 'numeric', month: 'long' }).format(d) };
  }
  function validEmail(s) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(s || '').trim()); }
  function download(name, text) {
    var a = document.createElement('a'), url = URL.createObjectURL(new Blob([text], { type: 'text/calendar;charset=utf-8' }));
    a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
  }

  /* ---------- veřejný formulář ---------- */
  W.mount = function (el, o) {
    if (typeof el === 'string') el = document.querySelector(el);
    var ui = o.ui || {}, T = labels(ui), cfg = o.config, store = o.store, cap = cfg.mode === 'capacity';
    var services = (cfg.services || []).filter(function (s) { return s.active !== false && (!ui.services || ui.services.indexOf(s.id) >= 0); });
    var st = { svc: services.length === 1 ? services[0].id : null, variant: 0, resource: ui.allowAny === false ? null : 'any', units: ui.unitsDefault || 2, date: null, t: null, done: null, err: '', form: {} };
    var E = null, state = null, idem = null;
    el.classList.add('tsb');

    function engine() { E = B.create(cfg, state, { extensions: o.extensions || [] }); return E; }
    function today() { return B.systemClock.today(); }
    function days() { var out = [], n = ui.days || 14; for (var i = 0; i < n; i++) out.push(B.date.addDays(today(), i)); return out; }
    function svc() { return E.service(st.svc); }
    function chooseResource() { return !cap && ui.chooseResource !== false && E.resourcesFor(st.svc).length > 1; }
    function opts() { return { variant: st.variant, resource: st.resource || 'any' }; }
    function times() {
      if (!st.date || !st.svc) return [];
      if (cap) return E.capacityTimes(st.date, st.units, st.svc).filter(function (x) { return x.ok; }).map(function (x) { return x.t; });
      return E.slots(st.date, st.svc, opts()).map(function (x) { return x.t; });
    }
    function dayState(d) {
      if (cap) { var h = E.dayHours(d); if (!h.open) return 'closed'; return E.capacityTimes(d, st.units, st.svc).some(function (x) { return x.ok; }) ? 'free' : 'full'; }
      return E.dayState(d, st.svc, opts()).s;
    }

    function svcHTML() {
      return '<fieldset class="tsb-step"><legend>' + esc(ui.serviceLabel || T.service) + '</legend><div class="tsb-cards">' + services.map(function (s) {
        var c = E.calc(s.id, 0), dur = s.durations[0] === s.durations[s.durations.length - 1] ? c.dur + ' ' + T.min : T.from + ' ' + c.dur + ' ' + T.min;
        var price = ui.showPrices !== false && s.prices && s.prices.length ? ' · ' + (s.prices.length > 1 && s.prices[0] !== s.prices[s.prices.length - 1] ? T.from + ' ' : '') + money(Math.min.apply(null, s.prices), ui) : '';
        return '<button type="button" class="tsb-card" data-svc="' + esc(s.id) + '" aria-pressed="' + (st.svc === s.id) + '"><b>' + esc(s.name) + '</b><span>' + dur + price + '</span></button>';
      }).join('') + '</div></fieldset>';
    }
    function variantHTML() {
      var s = svc(); if (!s || s.durations.length < 2 || !cfg.variants) return '';
      return '<fieldset class="tsb-step"><legend>' + esc(ui.variantLabel || T.variant) + '</legend><div class="tsb-chips">' + cfg.variants.slice(0, s.durations.length).map(function (v, i) {
        return '<button type="button" class="tsb-chip" data-variant="' + i + '" aria-pressed="' + (st.variant === i) + '">' + esc(v) + '</button>';
      }).join('') + '</div></fieldset>';
    }
    function resourceHTML() {
      if (cap) return '<fieldset class="tsb-step"><legend>' + esc(ui.unitsLabel || T.units) + '</legend><div class="tsb-chips">' + [1, 2, 3, 4, 5, 6, 7, 8].slice(0, ui.unitsMax || 8).map(function (n) {
        return '<button type="button" class="tsb-chip" data-units="' + n + '" aria-pressed="' + (st.units === n) + '">' + n + '</button>';
      }).join('') + '</div></fieldset>';
      if (!chooseResource()) return '';
      var rs = E.resourcesFor(st.svc), items = (ui.allowAny === false ? [] : [['any', T.any]]).concat(rs.map(function (r) { return [r.id, r.name || r.id]; }));
      return '<fieldset class="tsb-step"><legend>' + esc(ui.resourceLabel || T.resource) + '</legend><div class="tsb-chips">' + items.map(function (x) {
        return '<button type="button" class="tsb-chip" data-res="' + esc(x[0]) + '" aria-pressed="' + (st.resource === x[0]) + '">' + esc(x[1]) + '</button>';
      }).join('') + '</div></fieldset>';
    }
    function dateHTML() {
      if (!st.svc || (!cap && chooseResource() && !st.resource)) return '';
      return '<fieldset class="tsb-step"><legend>' + esc(T.date) + '</legend><div class="tsb-days">' + days().map(function (d) {
        var s = dayState(d), L = dayLabel(d, ui), dis = s !== 'free';
        return '<button type="button" class="tsb-day tsb-' + s + '" data-date="' + d + '" aria-pressed="' + (st.date === d) + '"' + (dis ? ' disabled' : '') + ' aria-label="' + esc(L.long + ', ' + (T[s] || s)) + '"><span>' + esc(L.wd) + '</span><b>' + esc(L.d) + '</b><small>' + esc(T[s] || '') + '</small></button>';
      }).join('') + '</div></fieldset>';
    }
    function timeHTML() {
      if (!st.date) return '';
      var ts = times();
      return '<fieldset class="tsb-step"><legend>' + esc(T.time) + ' – ' + esc(dayLabel(st.date, ui).long) + '</legend>' + (ts.length ? '<div class="tsb-times">' + ts.map(function (t) {
        return '<button type="button" class="tsb-time" data-t="' + t + '" aria-pressed="' + (st.t === t) + '">' + hm(t) + '</button>';
      }).join('') + '</div>' : '<p class="tsb-muted">' + esc(T.noTimes) + '</p>') + '</fieldset>';
    }
    function formHTML() {
      if (st.t == null) return '';
      var f = st.form, fields = ui.fields || ['name', 'email', 'phone', 'note'];
      return '<form class="tsb-step tsb-form" novalidate><h3>' + esc(T.contact) + '</h3>' + fields.map(function (k) {
        var type = k === 'email' ? 'email' : k === 'phone' ? 'tel' : 'text', id = 'tsb-' + k + '-' + uid;
        return '<label for="' + id + '">' + esc(T[k] || k) + '</label>' + (k === 'note' ? '<textarea id="' + id + '" name="' + k + '" rows="2">' + esc(f[k] || '') + '</textarea>' : '<input id="' + id + '" name="' + k + '" type="' + type + '" value="' + esc(f[k] || '') + '"' + (k === 'name' ? ' autocomplete="name"' : k === 'email' ? ' autocomplete="email"' : '') + '>');
      }).join('') + '<label class="tsb-check"><input type="checkbox" name="consent"' + (f.consent ? ' checked' : '') + '> <span>' + esc(ui.consentText || T.consent) + '</span></label>' +
        (st.err ? '<p class="tsb-err" role="alert">' + esc(st.err) + '</p>' : '') + '<button type="submit" class="tsb-btn">' + esc(T.submit) + '</button></form>';
    }
    function doneHTML() {
      var b = st.done, s = E.service(b.service), r = b.resource ? E.resource(b.resource) : null;
      return '<div class="tsb-done" role="status"><h3>' + esc(T.done) + '</h3><p><b>' + esc(s ? s.name : b.service) + '</b>' + (r ? ' · ' + esc(r.name || r.id) : '') + (b.units ? ' · ' + b.units + '×' : '') + '<br>' + esc(dayLabel(b.date, ui).long) + ' ' + hm(b.start) + '</p>' +
        '<p class="tsb-muted">' + esc(T.doneHint) + '</p><div class="tsb-row"><button type="button" class="tsb-btn tsb-sec" data-act="ics">' + esc(T.ics) + '</button><a class="tsb-btn tsb-sec" target="_blank" rel="noopener" href="' + esc(E.gcal(event(b))) + '">' + esc(T.gcal) + '</a>' +
        '<button type="button" class="tsb-btn tsb-ghost" data-act="again">' + esc(T.again) + '</button></div></div>';
    }
    function event(b) {
      var s = E.service(b.service);
      return { uid: b.id, title: (ui.eventTitle || '') + (s ? s.name : b.service), date: b.date, start: b.start, end: b.start + (b.dur || 0), location: ui.location || '' };
    }
    var uid = Math.random().toString(36).slice(2, 7);
    function render() {
      engine();
      var demo = store && store.demo ? '<p class="tsb-demo" role="note">' + esc(T.demo) + '</p>' : '';
      el.innerHTML = demo + (st.done ? doneHTML() : svcHTML() + variantHTML() + resourceHTML() + dateHTML() + timeHTML() + formHTML()) + '<div class="tsb-live" aria-live="polite"></div>';
    }
    function readForm() { var f = el.querySelector('.tsb-form'); if (!f) return; (ui.fields || ['name', 'email', 'phone', 'note']).forEach(function (k) { var i = f.elements[k]; if (i) st.form[k] = i.value; }); st.form.consent = f.elements.consent.checked; }
    function focusSel(sel) { var x = el.querySelector(sel); if (x) try { x.focus({ preventScroll: false }); } catch (e) { } }

    el.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b || !el.contains(b)) return;
      readForm();
      if (b.dataset.svc) { st.svc = b.dataset.svc; st.variant = 0; st.date = st.t = null; render(); focusSel('[data-svc="' + st.svc + '"]'); }
      else if (b.dataset.variant != null) { st.variant = +b.dataset.variant; st.t = null; render(); focusSel('[data-variant="' + st.variant + '"]'); }
      else if (b.dataset.res) { st.resource = b.dataset.res; st.t = null; render(); focusSel('[data-res="' + st.resource + '"]'); }
      else if (b.dataset.units) { st.units = +b.dataset.units; st.t = null; render(); focusSel('[data-units="' + st.units + '"]'); }
      else if (b.dataset.date) { st.date = b.dataset.date; st.t = null; render(); focusSel('.tsb-time, .tsb-times + *, [data-date="' + st.date + '"]'); }
      else if (b.dataset.t) { st.t = +b.dataset.t; idem = 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); render(); focusSel('.tsb-form input'); }
      else if (b.dataset.act === 'ics') download((ui.icsName || 'rezervace') + '.ics', E.ics(event(st.done)));
      else if (b.dataset.act === 'again') { st.done = null; st.t = null; st.date = null; render(); }
    });
    el.addEventListener('submit', function (e) {
      e.preventDefault(); if (st.busy) return; readForm();
      var f = st.form;
      if (!String(f.name || '').trim() || !validEmail(f.email) || !f.consent) { st.err = T.required; render(); focusSel('.tsb-err'); return; }
      var req = { date: st.date, service: st.svc, start: st.t, variant: st.variant, resource: st.resource || 'any', units: cap ? st.units : undefined, name: f.name.trim(), email: f.email.trim(), phone: f.phone || '', note: f.note || '', source: 'web' };
      var p = engine().prepareBooking(req);
      if (!p.ok) { st.err = T.taken; st.t = null; render(); return; }
      st.busy = true;
      store.add(p.booking, idem).then(function (r) {
        st.busy = false; st.err = ''; st.done = r.booking; st.form = {}; render(); focusSel('.tsb-done h3');
        if (o.onBooked) o.onBooked(r.booking, r.duplicate);
      });
    });
    return store.load().then(function (s) { state = s; render(); return { render: render, state: st, engine: function () { return E; } }; });
  };

  /* ---------- přehled rezervací (jednoduchá administrace ukázky) ---------- */
  W.mountAdmin = function (el, o) {
    if (typeof el === 'string') el = document.querySelector(el);
    var ui = o.ui || {}, T = labels(ui), cfg = o.config, store = o.store;
    el.classList.add('tsb', 'tsb-admin');
    function render(state) {
      var E = B.create(cfg, state), list = state.bookings.slice().sort(function (a, b) { return a.date < b.date ? -1 : a.date > b.date ? 1 : a.start - b.start; });
      var demo = store.demo ? '<p class="tsb-demo" role="note">' + esc(T.demo) + '</p>' : '';
      el.innerHTML = demo + '<div class="tsb-row tsb-between"><h3>' + esc(ui.adminTitle || T.adminTitle) + ' <span class="tsb-count">' + list.length + '</span></h3>' + (store.demo ? '<button type="button" class="tsb-btn tsb-ghost" data-act="reset">' + esc(T.reset) + '</button>' : '') + '</div>' +
        (list.length ? '<table class="tsb-table"><thead><tr><th>' + esc(T.date) + '</th><th>' + esc(T.time) + '</th><th>' + esc(ui.serviceLabel || T.service) + '</th><th>' + esc(cfg.mode === 'capacity' ? (ui.unitsLabel || T.units) : (ui.resourceLabel || T.resource)) + '</th><th>' + esc(T.name) + '</th><th>Stav</th><th></th></tr></thead><tbody>' + list.map(function (b) {
          var s = E.service(b.service), r = b.resource ? E.resource(b.resource) : null;
          return '<tr><td>' + esc(dayLabel(b.date, ui).d) + '</td><td>' + hm(b.start) + '</td><td>' + esc(s ? s.name : b.service) + '</td><td>' + esc(cfg.mode === 'capacity' ? (b.units || '') : (r ? r.name || r.id : '')) + (b.poolMember ? ' · ' + esc(b.poolMember) : '') + '</td><td>' + esc(b.name || '') + '</td><td>' + esc(T.status[b.status] || b.status) + '</td><td class="tsb-act">' +
            (b.status === 'pending' ? '<button type="button" class="tsb-btn tsb-sm" data-confirm="' + esc(b.id) + '">' + esc(T.confirm) + '</button>' : '') + (b.status !== 'cancelled' ? '<button type="button" class="tsb-btn tsb-sm tsb-ghost" data-cancel="' + esc(b.id) + '">' + esc(T.cancel) + '</button>' : '') + '</td></tr>';
        }).join('') + '</tbody></table>' : '<p class="tsb-muted">' + esc(T.adminEmpty) + '</p>');
    }
    el.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var p = b.dataset.confirm ? store.update(b.dataset.confirm, { status: 'confirmed' }) : b.dataset.cancel ? store.update(b.dataset.cancel, { status: 'cancelled' }) : b.dataset.act === 'reset' ? store.reset() : null;
      if (p) p.then(function () { return store.load(); }).then(render);
    });
    return store.load().then(function (s) { render(s); return { render: function () { return store.load().then(render); } }; });
  };
})(typeof window !== 'undefined' ? window : globalThis);
