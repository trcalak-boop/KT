/* ==========================================================================
   ts-booking-core – úložiště rezervací na serveru (ts-booking-server, PHP API ?r=…)
   Stejné rozhraní jako demo úložiště: load / add / update / cancel; data jsou v databázi provozovny.
   Veřejná část dostává jen obsazenost bez osobních údajů; server termín při zápisu znovu ověří
   a vrátí chybu „taken“, když ho mezitím někdo obsadil.

     var store = TSBooking.stores.http('/api/rezervace.php');                 // veřejný formulář
     var admin = TSBooking.stores.httpAdmin('/api/rezervace.php');            // správa (přihlášení)
   ========================================================================== */
(function (root) {
  'use strict';
  var B = root.TSBooking; if (!B) throw new Error('Nejdřív načtěte core/booking-core.js');
  B.stores = B.stores || {};

  function req(url, method, body, headers) {
    var h = { Accept: 'application/json' }, k; for (k in headers || {}) h[k] = headers[k];
    if (body !== undefined) h['Content-Type'] = 'application/json';
    return fetch(url, { method: method, headers: h, credentials: 'same-origin', body: body !== undefined ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'server' }; }).then(function (j) { j.status = r.status; return j; }); })
      .catch(function () { return { ok: false, error: 'network', status: 0 }; });
  }
  function u(base, route, q) { var s = base + (base.indexOf('?') < 0 ? '?' : '&') + 'r=' + encodeURIComponent(route), k; for (k in q || {}) s += '&' + k + '=' + encodeURIComponent(q[k]); return s; }

  B.stores.http = function (apiUrl, opt) {
    opt = opt || {};
    var opened = Date.now(), st = { state: { bookings: [], blocks: [] } };
    var store = {
      demo: false, server: true, state: st.state,
      config: function () { return req(u(apiUrl, 'config'), 'GET').then(function (j) { return j.ok ? j.config : Promise.reject(new Error(j.error || 'config')); }); },
      load: function () {
        return req(u(apiUrl, 'availability'), 'GET').then(function (j) {
          if (!j.ok) return Promise.reject(new Error(j.error || 'availability'));
          st.state.bookings = j.state.bookings; st.state.blocks = j.state.blocks; store.serverToday = j.today; return st.state;
        });
      },
      /* booking z prepareBooking (obsahuje jméno, e-mail…); key = idempotenční klíč */
      add: function (b, key) {
        var body = { date: b.date, service: b.service, start: b.start, variant: b.variant || 0, addons: b.addons || [], resource: b.resource, units: b.units,
          name: b.name, email: b.email, phone: b.phone || '', note: b.note || '', lang: opt.lang || b.lang || 'cs', consent: true, hp: '', openedMs: Date.now() - opened };
        return req(u(apiUrl, 'bookings'), 'POST', body, key ? { 'Idempotency-Key': key } : {}).then(function (j) {
          if (!j.ok) return { ok: false, error: j.error || 'server', fields: j.fields, status: j.status };
          if (!j.duplicate) st.state.bookings.push(j.booking);
          return { ok: true, booking: j.booking, duplicate: !!j.duplicate, cancelToken: j.cancelToken };
        });
      },
      cancel: function (id, token) { return req(u(apiUrl, 'cancel'), 'POST', { id: id, token: token }); },
      update: function () { return Promise.resolve({ ok: false, error: 'admin-only' }); },
      reset: function () { return store.load(); }
    };
    return store;
  };

  B.stores.httpAdmin = function (apiUrl) {
    var csrf = '', st = { bookings: [], blocks: [] };
    function post(route, body) { return req(u(apiUrl, 'admin/' + route), 'POST', body, { 'X-CSRF': csrf }).then(function (j) { if (j.csrf) csrf = j.csrf; return j; }); }
    var store = {
      demo: false, server: true, auth: true, state: st, user: null,
      me: function () { return req(u(apiUrl, 'admin/me'), 'GET').then(function (j) { if (j.csrf) csrf = j.csrf; store.user = j.ok ? j.user : null; store.can = j.can || []; return j; }); },
      login: function (username, password) { return post('login', { username: username, password: password }).then(function (j) { if (j.ok) store.user = j.user; return j; }); },
      logout: function () { return post('logout', {}).then(function (j) { store.user = null; return j; }); },
      load: function (from, to) {
        return req(u(apiUrl, 'admin/bookings', from ? { from: from, to: to } : {}), 'GET').then(function (j) {
          if (!j.ok) return Promise.reject(Object.assign(new Error(j.error || 'load'), { status: j.status }));
          st.bookings = j.bookings; st.blocks = j.blocks; return st;
        });
      },
      update: function (id, changes) {
        return post('status', { id: id, status: changes.status, note: changes.note || '' }).then(function (j) {
          if (j.ok) { var i = st.bookings.findIndex(function (b) { return b.id === id; }); if (i >= 0) st.bookings[i] = j.booking; }
          return j;
        });
      },
      addBlock: function (b) { return post('blocks', b); },
      deleteBlock: function (id) { return post('blocks/delete', { id: id }); }
    };
    return store;
  };
})(typeof window !== 'undefined' ? window : globalThis);
