/* ==========================================================================
   ts-site-audit – zobrazení auditu webu (formulář, průběh, report, seznam pro administraci). Bez závislostí.
   <div id="ts-audit"></div>
   <script>window.TS_AUDIT_CONFIG = { api: '/api/audit/audit.php', lang: 'cs', admin: true };</script>
   <script src="/vendor/ts/ts-site-audit/public/ts-audit.js" defer></script>
   Fakta z měření a doporučení AI jsou vždy oddělená; report uvádí zdroj, datum a omezení měření.
   ========================================================================== */
(function () {
  'use strict';
  var C = window.TS_AUDIT_CONFIG || {}, API = C.api || '/api/audit/audit.php';
  var T = {
    cs: { title: 'Audit webu', url: 'Adresa webu', run: 'Spustit audit', running: 'Audit běží…', s_queued: 'Měřím web na mobilu…', s_desktop: 'Měřím web na počítači…', s_vision: 'Hodnotím vzhled…',
      e_url: 'Tuto adresu nelze auditovat (jen veřejné weby přes http/https).', e_limit: 'Limit auditů je pro dnešek vyčerpaný, zkuste to zítra.', e_disabled: 'Audit je teď vypnutý.', e_server: 'Audit se nepodařil, zkuste to později.',
      ee_site: 'Web se nepodařilo načíst (nedostupný, blokuje měření nebo vrací chybu).', ee_limit: 'Měřicí služba je teď vytížená (kvóta), zkuste to později.', ee_auth: 'Měřicí služba odmítla přístup (klíč).', ee_timeout: 'Měření trvalo příliš dlouho.',
      mobile: 'Mobil', desktop: 'Počítač', performance: 'Výkon', accessibility: 'Přístupnost', 'best-practices': 'Osvědčené postupy', seo: 'SEO',
      fcp: 'První vykreslení obsahu', lcp: 'Vykreslení největšího obsahu', tbt: 'Celková doba blokování', cls: 'Kumulativní posun rozvržení', si: 'Index rychlosti',
      measured: 'Měřeno', source: 'Zdroj', facts: 'Zjištěno měřením', ai: 'Doporučení AI k vzhledu (subjektivní)', noai: 'Hodnocení vzhledu AI není k dispozici', fullpage: 'Celá stránka', shot: 'Snímek obrazovky',
      n_lab: 'Laboratorní měření (emulované zařízení a síť), ne data reálných návštěvníků.', n_single: 'Jednorázové měření – výsledky se mohou mezi měřeními lišit.', n_aiSubjective: 'Doporučení AI vycházejí jen ze snímku obrazovky a jsou subjektivní.',
      cached: 'Výsledek z mezipaměti (měřeno dříve).', force: 'Změřit znovu (bez mezipaměti)', raw: 'Diagnostika: odpověď modelu', recent: 'Poslední audity', open: 'Otevřít', st_done: 'hotovo', st_error: 'chyba', st_running: 'běží', none: 'Zatím žádné audity.', metrics: 'Metriky', both: 'mobil i počítač',
      r_off: 'hodnocení není zapnuté', r_quota: 'denní strop hodnocení je vyčerpaný', r_noshot: 'chybí snímek', r_empty: 'model nevrátil použitelná doporučení', r_error: 'služba hodnocení nebyla dostupná' },
    en: { title: 'Website audit', url: 'Website address', run: 'Run audit', running: 'Audit running…', s_queued: 'Measuring on mobile…', s_desktop: 'Measuring on desktop…', s_vision: 'Reviewing the design…',
      e_url: 'This address cannot be audited (public http/https websites only).', e_limit: 'The audit limit for today has been reached.', e_disabled: 'The audit is switched off.', e_server: 'The audit failed, please try later.',
      ee_site: 'The website could not be loaded.', ee_limit: 'The measuring service is busy (quota).', ee_auth: 'The measuring service refused access.', ee_timeout: 'The measurement took too long.',
      mobile: 'Mobile', desktop: 'Desktop', performance: 'Performance', accessibility: 'Accessibility', 'best-practices': 'Best practices', seo: 'SEO',
      fcp: 'First Contentful Paint', lcp: 'Largest Contentful Paint', tbt: 'Total Blocking Time', cls: 'Cumulative Layout Shift', si: 'Speed Index',
      measured: 'Measured', source: 'Source', facts: 'Found by measurement', ai: 'AI design recommendations (subjective)', noai: 'AI design review not available', fullpage: 'Full page', shot: 'Screenshot',
      n_lab: 'Lab measurement (emulated device and network), not real-visitor data.', n_single: 'Single measurement – results may vary between runs.', n_aiSubjective: 'AI recommendations are based on the screenshot only and are subjective.',
      cached: 'Cached result (measured earlier).', force: 'Measure again (skip cache)', raw: 'Diagnostics: model output', recent: 'Recent audits', open: 'Open', st_done: 'done', st_error: 'error', st_running: 'running', none: 'No audits yet.', metrics: 'Metrics', both: 'mobile and desktop',
      r_off: 'review is off', r_quota: 'daily review limit reached', r_noshot: 'no screenshot', r_empty: 'no usable recommendations', r_error: 'review service unavailable' }
  };
  var lang = T[C.lang] ? C.lang : 'en', t = function (k) { return (T[lang][k] != null ? T[lang][k] : T.en[k]) || k; };
  function el(tag, a, kids) { var e = document.createElement(tag); for (var k in a || {}) { if (k === 'text') e.textContent = a[k]; else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), a[k]); else e.setAttribute(k, a[k]); } (kids || []).forEach(function (c) { if (c) e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); }); return e; }
  function q(route, params) { var u = API + (API.indexOf('?') < 0 ? '?' : '&') + 'r=' + route; for (var k in params || {}) u += '&' + k + '=' + encodeURIComponent(params[k]); return u; }
  function getJ(u) { return fetch(u, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'server' }; }); }); }

  var root = document.querySelector(C.mount || '#ts-audit'); if (!root) return;
  root.classList.add('tsa');
  var input = el('input', { type: 'url', class: 'tsa-input', id: 'tsa-url', placeholder: 'https://', required: '', autocomplete: 'url', inputmode: 'url' });
  var btn = el('button', { type: 'submit', class: 'tsa-btn', text: t('run') });
  var force = C.admin ? el('input', { type: 'checkbox', id: 'tsa-force' }) : null;
  var form = el('form', { class: 'tsa-form', novalidate: '' }, [el('label', { for: 'tsa-url', class: 'tsa-label', text: t('url') }), el('div', { class: 'tsa-row' }, [input, btn]),
    force ? el('label', { class: 'tsa-check', for: 'tsa-force' }, [force, ' ' + t('force')]) : null]);
  var status = el('div', { class: 'tsa-status', role: 'status', 'aria-live': 'polite' });
  var out = el('div', { class: 'tsa-out' }), list = el('div', { class: 'tsa-list' });
  root.appendChild(form); root.appendChild(status); root.appendChild(out); if (C.admin) root.appendChild(list);
  var poll = 0, ref = null;    /* ref = { id } nebo { job } */

  function progress(p, text) { status.innerHTML = ''; status.appendChild(el('div', { class: 'tsa-bar', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': String(p) }, [el('span', { style: 'width:' + p + '%' })])); status.appendChild(el('p', { text: text })); }
  function fail(text) { clearTimeout(poll); btn.disabled = false; status.innerHTML = ''; status.appendChild(el('p', { class: 'tsa-err', text: text })); }
  form.addEventListener('submit', function (e) {
    e.preventDefault(); var u = input.value.trim(); if (!u) { input.focus(); return; }
    btn.disabled = true; out.innerHTML = ''; progress(3, t('running'));
    fetch(q('audit'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ url: u, lang: lang, force: !!(force && force.checked) }) })
      .then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) return fail(t('e_' + (j.error || 'server')));
        ref = { id: j.id }; check(0);
      }).catch(function () { fail(t('e_server')); });
  });
  function check(n) {
    getJ(q('audit-status', ref)).then(function (j) {
      if (!j.ok) return fail(t('e_server'));
      if (j.status === 'done') { btn.disabled = false; status.innerHTML = ''; render(j.report, !!j.cached); loadList(); return; }
      if (j.status === 'error') { loadList(); return fail(t('ee_' + j.error) !== 'ee_' + j.error ? t('ee_' + j.error) : t('e_server')); }
      progress(j.progress || 5, t('s_' + j.status)); if (n > 150) return fail(t('ee_timeout'));
      poll = setTimeout(function () { check(n + 1); }, 3000);
    }, function () { poll = setTimeout(function () { check(n + 1); }, 5000); });
  }

  function cls(s) { return s == null ? 'na' : s >= 90 ? 'good' : s >= 50 ? 'mid' : 'bad'; }
  function render(r, cached) {
    out.innerHTML = ''; if (!r) return;
    var d = r.measuredAt ? new Date(r.measuredAt) : null;
    out.appendChild(el('header', { class: 'tsa-head' }, [el('h2', { text: r.finalUrl || r.url }),
      el('p', { class: 'tsa-meta', text: t('measured') + ': ' + (d ? d.toLocaleString(lang) : '–') + ' · ' + t('source') + ': ' + r.source.name + (r.source.version ? ' ' + r.source.version : '') }),
      cached ? el('p', { class: 'tsa-meta', text: t('cached') }) : null]));
    var devs = ['mobile', 'desktop'].filter(function (k) { return r.devices[k]; }), tabs = el('div', { class: 'tsa-tabs', role: 'tablist' }), panes = el('div');
    devs.forEach(function (k, i) {
      var D = r.devices[k], b = el('button', { type: 'button', role: 'tab', class: 'tsa-tab', 'aria-selected': i === 0 ? 'true' : 'false', 'aria-controls': 'tsa-p-' + k, id: 'tsa-t-' + k, text: t(k) });
      var pane = el('section', { class: 'tsa-pane', role: 'tabpanel', id: 'tsa-p-' + k, 'aria-labelledby': 'tsa-t-' + k });
      if (i > 0) pane.hidden = true;
      var sc = el('div', { class: 'tsa-scores' });
      ['performance', 'accessibility', 'best-practices', 'seo'].forEach(function (c) { var v = D.scores[c]; sc.appendChild(el('div', { class: 'tsa-score tsa-' + cls(v) }, [el('strong', { text: v == null ? '–' : String(v) }), el('span', { text: t(c) })])); });
      pane.appendChild(sc);
      var mt = el('dl', { class: 'tsa-metrics' });
      ['fcp', 'lcp', 'tbt', 'cls', 'si'].forEach(function (m) { var x = D.metrics[m]; if (!x) return; mt.appendChild(el('div', { class: 'tsa-m tsa-' + cls(x.score == null ? null : Math.round(x.score * 100)) }, [el('dt', { text: t(m) }), el('dd', { text: x.display })])); });
      pane.appendChild(el('h3', { text: t('metrics') })); pane.appendChild(mt);
      if (D.screenshot) {
        var p = Object.assign({ d: k }, ref), fig = el('figure', { class: 'tsa-shot' }, [el('img', { src: q('audit-shot', p), alt: t('shot') + ' – ' + t(k), loading: 'lazy' })]);
        if (D.fullpage) fig.appendChild(el('figcaption', {}, [el('a', { href: q('audit-shot', Object.assign({ full: 1 }, p)), target: '_blank', rel: 'noopener', text: t('fullpage') })]));
        pane.appendChild(fig);
      }
      b.addEventListener('click', function () { [].forEach.call(tabs.children, function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); [].forEach.call(panes.children, function (x) { x.hidden = x !== pane; }); });
      tabs.appendChild(b); panes.appendChild(pane);
    });
    out.appendChild(tabs); out.appendChild(panes);
    var facts = r.recommendations.filter(function (x) { return x.basis === 'measurement'; }), ai = r.recommendations.filter(function (x) { return x.basis === 'ai'; });
    var fl = el('ul', { class: 'tsa-recs' });
    facts.forEach(function (x) { fl.appendChild(el('li', {}, [el('span', { class: 'tsa-tag', text: t(x.category) }), ' ' + x.text + (x.detail ? ' – ' + x.detail : '') + ' (' + (x.devices.length > 1 ? t('both') : t(x.devices[0])) + ')'])); });
    out.appendChild(el('section', { class: 'tsa-sec' }, [el('h3', { text: t('facts') }), fl]));
    var as = el('section', { class: 'tsa-sec tsa-ai' }, [el('h3', { text: t('ai') })]);
    if (ai.length) { var al = el('ol', { class: 'tsa-recs' }); ai.forEach(function (x) { al.appendChild(el('li', {}, [x.area ? el('strong', { text: x.area + ': ' }) : null, x.text])); }); as.appendChild(al); }
    else as.appendChild(el('p', { class: 'tsa-meta', text: t('noai') + ' (' + t('r_' + String(r.ai.reason || 'error').replace(/^error_.*/, 'error')) + ').' }));
    if (r.ai.raw) as.appendChild(el('details', { class: 'tsa-raw' }, [el('summary', { text: t('raw') + (r.ai.finish ? ' (' + r.ai.finish + ')' : '') }), el('pre', { text: r.ai.raw })]));
    out.appendChild(as);
    var nl = el('ul', { class: 'tsa-notes' }); (r.notes || []).forEach(function (n) { nl.appendChild(el('li', { text: t('n_' + n) })); });
    out.appendChild(nl);
  }

  function loadList() {
    if (!C.admin) return;
    getJ(q('audit-list')).then(function (j) {
      list.innerHTML = ''; list.appendChild(el('h3', { text: t('recent') }));
      if (!j.ok || !j.items.length) { list.appendChild(el('p', { class: 'tsa-meta', text: t('none') })); return; }
      var tb = el('table', { class: 'tsa-table' }), body = el('tbody');
      j.items.forEach(function (it) {
        var st = it.status === 'done' ? t('st_done') : it.status === 'error' ? t('st_error') + ' (' + it.error + ')' : t('st_running');
        var b = it.status === 'done' ? el('button', { type: 'button', class: 'tsa-link', text: t('open'), onclick: function () { ref = { job: it.id }; status.innerHTML = ''; getJ(q('audit-status', ref)).then(function (s) { if (s.ok && s.report) { render(s.report, false); out.scrollIntoView({ behavior: 'smooth' }); } }); } }) : null;
        body.appendChild(el('tr', {}, [el('td', { text: it.created_at }), el('td', { text: it.url }), el('td', { text: st }), el('td', {}, [b])]));
      });
      tb.appendChild(body); list.appendChild(tb);
    });
  }
  loadList();
  window.TSAudit = { run: function (u) { input.value = u; form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); } };
})();
