/* ==========================================================================
   ts-booking-core – DEMO úložiště rezervací v prohlížeči (localStorage)
   !!! Rezervace se NEUKLÁDAJÍ na server. Vidí je jen tento prohlížeč, kdokoli je může smazat
   nebo změnit, nejsou chráněné ani zálohované. Jen pro ukázky a prototypy. !!!

   Rozhraní úložiště (stejné bude mít budoucí serverový adaptér, viz api/ts-booking-core.md):
     store.load()              → Promise<state>          state = { bookings: [], blocks: [] }
     store.add(booking, key?)  → Promise<{ ok, booking, duplicate? }>   key = idempotenční klíč (dvojklik)
     store.update(id, změny)   → Promise<{ ok, booking }>
     store.reset()             → Promise<state>          vrátí výchozí (seed) data
     store.demo === true       → UI musí zobrazit upozornění, že jde o ukázku
   ========================================================================== */
(function (root) {
  'use strict';
  var B = root.TSBooking; if (!B) throw new Error('Nejdřív načtěte core/booking-core.js');
  B.stores = B.stores || {};
  B.stores.demoLocal = function (key, seed) {
    var K = 'ts-booking-demo:' + key;
    function clone(o) { return JSON.parse(JSON.stringify(o || { bookings: [], blocks: [] })); }
    function read() { try { var r = localStorage.getItem(K); if (r) { var s = JSON.parse(r); if (s && Array.isArray(s.bookings)) return s; } } catch (e) { } return clone(seed); }
    function write(s) { try { localStorage.setItem(K, JSON.stringify(s)); } catch (e) { } return s; }
    var state = read(); if (!state.blocks) state.blocks = []; if (!state.keys) state.keys = {};
    function uid() { return 'r' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
    return {
      demo: true,
      state: state,
      load: function () { return Promise.resolve(state); },
      add: function (b, idemKey) {
        if (idemKey && state.keys[idemKey]) {
          var prev = state.bookings.find(function (x) { return x.id === state.keys[idemKey]; });
          if (prev) return Promise.resolve({ ok: true, booking: prev, duplicate: true });
        }
        var n = Object.assign({ id: uid(), createdAt: Date.now() }, b);
        state.bookings.push(n); if (idemKey) state.keys[idemKey] = n.id; write(state);
        return Promise.resolve({ ok: true, booking: n });
      },
      update: function (id, ch) {
        var b = state.bookings.find(function (x) { return x.id === id; });
        if (!b) return Promise.resolve({ ok: false, error: 'not-found' });
        Object.assign(b, ch, { id: id, updatedAt: Date.now() }); write(state);
        return Promise.resolve({ ok: true, booking: b });
      },
      reset: function () { var s = clone(seed); s.keys = {}; state.bookings = s.bookings; state.blocks = s.blocks || []; state.keys = {}; try { localStorage.removeItem(K); } catch (e) { } return Promise.resolve(state); }
    };
  };
})(typeof window !== 'undefined' ? window : globalThis);
