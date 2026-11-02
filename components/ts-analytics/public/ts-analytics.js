/* ==========================================================================
   ts-analytics – měřicí skript webu (bez služeb třetích stran)
   Spouštět AŽ PO SOUHLASU návštěvníka s měřením (lišta cookies):
     TsAnalytics.start({ api: '/api/stat/hit.php' });      // nastaví cookies ts_v (návštěvník, 13 měsíců) a ts_s (návštěva, 30 min), pošle zobrazení stránky
     TsAnalytics.event('poptavka');                         // událost (a–z, 0–9, _), jen když měření běží
     TsAnalytics.stop();                                     // odvolaný souhlas: smaže cookies, dál nic neposílá
   Posílá jen cestu stránky (bez parametrů), doménu odkazující stránky, UTM z adresy, jazyk stránky a šířku okna.
   ========================================================================== */
(function () {
  'use strict';
  var on = false, opt = {}, vid = '', sid = '', fresh = false;
  function rid() {
    var a = new Uint8Array(10), s = '';
    if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(a); else for (var i = 0; i < a.length; i++) a[i] = Math.random() * 256;
    for (var j = 0; j < a.length; j++) s += (a[j] % 36).toString(36);
    return (s + Date.now().toString(36)).slice(0, 16);
  }
  function getC(n) { var m = document.cookie.match(new RegExp('(?:^|; )' + n + '=([a-z0-9]{16})(?:;|$)')); return m ? m[1] : ''; }
  function setC(n, v, sec) { document.cookie = n + '=' + v + '; Max-Age=' + sec + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '') + (opt.domain ? '; Domain=' + opt.domain : ''); }
  function delC(n) { setC(n, '', 0); document.cookie = n + '=; Max-Age=0; Path=/'; }
  function ids() {
    vid = getC('ts_v'); fresh = !vid; if (fresh) vid = rid();
    setC('ts_v', vid, (opt.visitorDays || 395) * 86400);                  /* 13 měsíců, obnovuje se při návštěvě */
    sid = getC('ts_s') || rid(); setC('ts_s', sid, (opt.sessionMinutes || 30) * 60);
  }
  function send(o) {
    if (!on) return;
    ids();
    o.v = vid; o.s = sid; o.p = location.pathname; o.l = (document.documentElement.lang || '').slice(0, 8); o.w = window.innerWidth || 0;
    if (fresh) { o.n = 1; fresh = false; }
    var body = JSON.stringify(o);
    try { if (navigator.sendBeacon && navigator.sendBeacon(opt.api, new Blob([body], { type: 'text/plain' }))) return; } catch (e) { }
    try { fetch(opt.api, { method: 'POST', body: body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } }); } catch (e) { }
  }
  function pageview() {
    var o = { t: 'pv' }, q = new URLSearchParams(location.search), u = {};
    if (q.get('utm_source')) u.s = q.get('utm_source').slice(0, 60);
    if (q.get('utm_medium')) u.m = q.get('utm_medium').slice(0, 60);
    if (q.get('utm_campaign')) u.c = q.get('utm_campaign').slice(0, 80);
    if (u.s || u.c) o.u = u;
    try { var r = document.referrer && new URL(document.referrer); if (r && r.host !== location.host) o.r = r.protocol + '//' + r.host + '/'; } catch (e) { }
    send(o);
  }
  window.TsAnalytics = {
    start: function (o) { if (on || !o || !o.api) return; opt = o; on = true; pageview(); },
    event: function (name) { if (on && /^[a-z0-9_]{1,40}$/.test(name)) send({ t: 'ev', e: name }); },
    stop: function () { on = false; delC('ts_v'); delC('ts_s'); },
    running: function () { return on; }
  };
})();
