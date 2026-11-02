/* ==========================================================================
   ts-design-studio – rozhraní administrace (bez knihoven). <div id="ts-studio"></div> + window.TS_STUDIO_CONFIG = { api: '/api/studio/studio.php' }.
   Projekty, nový projekt (ze šablony / s AI: nový, redesign, lead), editor: náhled ve 3 šířkách, obsah a barvy, kód, rozdíl, kontroly, verze, konverzace s AI.
   Náhled běží v <iframe sandbox="allow-scripts" srcdoc> (origin null – bez přístupu ke stránce a cookies). Veškerý text z dat se escapuje.
   ========================================================================== */
(function () {
  'use strict';
  var C = window.TS_STUDIO_CONFIG || {}, API = C.api || '/api/studio/studio.php';
  var root = document.getElementById('ts-studio'); if (!root) return;
  var S = { templates: [], ai: false, usage: null, p: null, sel: 0, ver: null, tab: 'preview', width: 1280, file: null, dirty: false };
  var STATUS = { draft: 'Koncept', in_review: 'Ke kontrole', approved_internal: 'Schváleno interně', archived: 'Archiv' };
  var VST = { candidate: 'návrh', accepted: 'přijatá', rejected: 'zamítnutá' };
  var SRC = { 'new': 'nový', redesign: 'redesign', crm: 'z CRM' };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var $ = function (s, r) { return (r || root).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || root).querySelectorAll(s)); };

  function api(r, body, q) {
    var o = { credentials: 'same-origin', headers: {} };
    if (body) { o.method = 'POST'; o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
    return fetch(API + '?r=' + r + (q || ''), o).then(function (res) { return res.json().catch(function () { return { ok: false, error: 'http' }; }).then(function (j) { j._code = res.status; return j; }); });
  }
  function errText(j) {
    if (!j) return 'chyba spojení';
    if (j.error === 'checks') return 'verze má chyby kontrol: ' + j.errors.map(function (e) { return e.msg; }).join('; ');
    if (j.error === 'ai') return { limit: 'denní limit AI je vyčerpaný', disabled: 'AI není nastavená', auth: 'chybí oprávnění k modelu', timeout: 'model neodpověděl včas', format: 'model nevrátil platnou odpověď' }[j.kind] || ('AI: ' + (j.detail || j.kind));
    if (j.error === 'denied') return 'nemáte oprávnění';
    return j.detail || j.error || 'chyba';
  }
  var toastT;
  function toast(t, bad) { var el = $('.tss-toast'); el.textContent = t; el.className = 'tss-toast on' + (bad ? ' bad' : ''); clearTimeout(toastT); toastT = setTimeout(function () { el.className = 'tss-toast'; }, bad ? 9000 : 4500); }
  function busy(t) { var el = $('.tss-busy'); el.textContent = t || ''; el.classList.toggle('on', !!t); }
  function waitJob(id, label) {
    var t0 = Date.now();
    return new Promise(function (ok) {
      (function tick() {
        api('job', null, '&id=' + id).then(function (j) {
          if (j.status === 'running') { busy(label + ' … ' + Math.round((Date.now() - t0) / 1000) + ' s'); setTimeout(tick, 2500); return; }
          busy(''); ok(j);
        }).catch(function () { setTimeout(tick, 4000); });
      })();
    });
  }
  function usageHtml(u) {
    if (!u || !u.limit) return '';
    var pct = Math.min(100, Math.round(u.neurons / u.limit * 100));
    return '<span class="tss-usage" title="Bezplatná denní kvóta Cloudflare (UTC), strop Studia">AI dnes: ' + u.neurons + ' / ' + u.limit + ' neuronů<i style="width:' + pct + '%"></i></span>';
  }

  /* ---------- kostra ---------- */
  root.innerHTML = '<div class="tss"><header class="tss-head"><a href="#/" class="tss-logo">AI Design Studio</a><span class="tss-u"></span></header><div class="tss-main"></div><div class="tss-busy" role="status" aria-live="polite"></div><div class="tss-toast" role="status" aria-live="polite"></div></div>';
  function setUsage(u) { if (u) S.usage = u; $('.tss-u').innerHTML = usageHtml(S.usage); }

  /* ---------- seznam projektů ---------- */
  function viewList() {
    S.p = null;
    api('list').then(function (j) {
      if (!j.ok) { $('.tss-main').innerHTML = '<p class="tss-err">' + esc(errText(j)) + '</p>'; return; }
      setUsage(j.usage);
      var rows = j.projects.map(function (p) {
        return '<tr><td><a href="#/p/' + p.id + '">' + esc(p.name) + '</a></td><td>' + esc(p.template) + '</td><td>' + esc(SRC[p.source] || p.source) + '</td><td><span class="tss-st st-' + esc(p.status) + '">' + esc(STATUS[p.status] || p.status) + '</span></td><td>v' + esc(p.current_num) + '</td><td>' + esc(String(p.updated_at).slice(0, 16)) + '</td></tr>';
      }).join('');
      $('.tss-main').innerHTML = '<div class="tss-bar"><h1>Projekty</h1><button type="button" class="tss-btn" data-act="new">Nový projekt</button></div>' +
        (rows ? '<div class="tss-scroll"><table class="tss-table"><thead><tr><th>Název</th><th>Šablona</th><th>Zdroj</th><th>Stav</th><th>Přijatá verze</th><th>Změna</th></tr></thead><tbody>' + rows + '</tbody></table></div>' : '<p class="tss-muted">Zatím žádný projekt.</p>') +
        '<div class="tss-new" hidden></div>';
    });
  }
  function field(name, label, opt) {
    opt = opt || {};
    var a = ' name="' + name + '" id="tsf-' + name + '"' + (opt.max ? ' maxlength="' + opt.max + '"' : '') + (opt.req ? ' required' : '');
    return '<label class="tss-f" for="tsf-' + name + '"><span>' + esc(label) + '</span>' + (opt.area ? '<textarea' + a + ' rows="' + (opt.rows || 3) + '"></textarea>' : '<input' + a + '>') + (opt.hint ? '<small>' + esc(opt.hint) + '</small>' : '') + '</label>';
  }
  function viewNew() {
    var box = $('.tss-new'); box.hidden = false;
    var tpl = S.templates.map(function (t) { return '<option value="' + esc(t.id) + '">' + esc(t.name) + '</option>'; }).join('');
    box.innerHTML = '<h2>Nový projekt</h2><div class="tss-tabs" role="tablist"><button type="button" role="tab" data-mode="ai" aria-selected="' + S.ai + '"' + (S.ai ? '' : ' disabled') + '>Návrh s AI</button><button type="button" role="tab" data-mode="tpl" aria-selected="' + !S.ai + '">Ze šablony</button></div>' +
      '<form class="tss-form" data-form="tpl"' + (S.ai ? ' hidden' : '') + '>' + field('name', 'Název projektu', { max: 160, req: true }) +
      '<label class="tss-f"><span>Šablona</span><select name="template">' + tpl + '</select></label><label class="tss-f"><span>Zdroj</span><select name="source"><option value="new">nový projekt</option><option value="redesign">redesign</option><option value="crm">z CRM</option></select></label>' +
      '<p><button class="tss-btn" type="submit">Založit projekt</button></p></form>' +
      '<form class="tss-form" data-form="ai"' + (S.ai ? '' : ' hidden') + '><fieldset class="tss-srcset"><legend>Zdroj zadání</legend><label><input type="radio" name="source" value="new" checked> nový projekt</label><label><input type="radio" name="source" value="redesign"> redesign stávajícího webu</label><label><input type="radio" name="source" value="crm"> lead z CRM</label></fieldset>' +
      '<div class="tss-grid2">' + field('name', 'Název podniku', { max: 160, req: true }) + field('obor', 'Obor', { max: 80, hint: 'Podle oboru se vybere šablona (jinak ji vybere AI).' }) + '</div>' +
      '<div data-only="crm" hidden class="tss-grid2">' + field('lead_phone', 'Telefon z leadu', { max: 30 }) + field('lead_email', 'E-mail z leadu', { max: 120 }) + field('lead_web', 'Současný web', { max: 200 }) + field('lead_reason', 'Důvod poptávky', { max: 400 }) + '</div>' +
      field('audience', 'Cílová skupina', { max: 300 }) + field('style', 'Požadovaný styl', { max: 300, hint: 'Barvy, nálada, co nechtějí.' }) + field('services', 'Služby a nabídka', { area: true, max: 1200 }) +
      field('facts', 'Fakta', { area: true, rows: 4, max: 2000, hint: 'Kontakty, adresa, otevírací doba, ceny, jména lidí. Co tu nebude, AI označí [doplnit] – nic si nevymýšlí.' }) +
      '<div data-only="redesign" hidden>' + field('texts', 'Texty ze současného webu', { area: true, rows: 5, max: 6000, hint: 'Vložte ručně. Převzatý obsah se označí k ověření práv.' }) + field('findings', 'Zjištění z auditu', { area: true, max: 1200 }) + '</div>' +
      field('notes', 'Poznámky obchodníka', { area: true, max: 1000 }) +
      '<p><button class="tss-btn" type="submit">Navrhnout web</button> <span class="tss-muted">Návrh trvá zhruba minutu a stojí asi 100 neuronů z denní kvóty.</span></p></form>';
    box.scrollIntoView({ behavior: 'smooth' });
  }
  function submitNew(form) {
    var d = {}; new FormData(form).forEach(function (v, k) { d[k] = String(v).trim(); });
    var btn = form.querySelector('[type=submit]'); btn.disabled = true;
    if (form.getAttribute('data-form') === 'tpl') {
      api('create', { name: d.name, template: d.template, source: d.source }).then(function (j) { btn.disabled = false; if (!j.ok) return toast(errText(j), true); location.hash = '#/p/' + j.id; });
      return;
    }
    var brief = { name: d.name, obor: d.obor, audience: d.audience, style: d.style, services: d.services, facts: d.facts, notes: d.notes }, body = { source: d.source, brief: brief };
    if (d.source === 'redesign') { brief.texts = d.texts; brief.rights = !!d.texts; if (d.findings) brief.notes = ('Zjištění z auditu:\n' + d.findings + '\n' + (d.notes || '')).trim(); }
    if (d.source === 'crm') body.lead = { name: d.name, phone: d.lead_phone, email: d.lead_email, website: d.lead_web, outreach_reason: d.lead_reason };
    api('ai-create', body).then(function (j) {
      if (!j.ok) { btn.disabled = false; return toast(errText(j), true); }
      return waitJob(j.job, 'AI navrhuje web').then(function (r) {
        btn.disabled = false;
        if (r.status !== 'done') return toast('Návrh se nepovedl: ' + errText({ error: 'ai', kind: r.error, detail: r.detail }), true);
        toast('Návrh je hotový za ' + r.seconds + ' s.'); location.hash = '#/p/' + r.result.id;
      });
    });
  }

  /* ---------- editor projektu ---------- */
  function loadProject(id, keepSel) {
    return api('project', null, '&pid=' + id).then(function (j) {
      if (!j.ok) { $('.tss-main').innerHTML = '<p class="tss-err">' + esc(errText(j)) + '</p>'; return; }
      setUsage(j.usage); S.p = j;
      if (!keepSel || !j.versions.some(function (v) { return v.num === S.sel; })) S.sel = j.versions.length ? j.versions[0].num : 0;
      renderProject(); return selectVersion(S.sel);
    });
  }
  function renderProject() {
    var p = S.p.project;
    var st = Object.keys(STATUS).map(function (k) { return '<option value="' + k + '"' + (k === p.status ? ' selected' : '') + '>' + STATUS[k] + '</option>'; }).join('');
    $('.tss-main').innerHTML = '<div class="tss-bar"><div><a href="#/" class="tss-back">← Projekty</a><h1>' + esc(p.name) + '</h1><span class="tss-muted">šablona ' + esc(p.template) + ' · ' + esc(SRC[p.source] || p.source) + ' · přijatá verze ' + esc(p.current_num) + '</span></div>' +
      '<div class="tss-bar-r"><label class="tss-inline">Stav <select data-act="status">' + st + '</select></label><a class="tss-btn ghost" data-act="export" href="#">Stáhnout ZIP</a></div></div>' +
      '<div class="tss-ed"><aside class="tss-vers" aria-label="Verze"><h2>Verze</h2><ol></ol></aside><section class="tss-work"><div class="tss-vhead"></div><div class="tss-tabs" role="tablist">' +
      [['preview', 'Náhled'], ['content', 'Obsah a barvy'], ['code', 'Kód'], ['diff', 'Rozdíl'], ['checks', 'Kontroly']].map(function (t) { return '<button type="button" role="tab" data-tab="' + t[0] + '" aria-selected="' + (S.tab === t[0]) + '">' + t[1] + '</button>'; }).join('') +
      '</div><div class="tss-pane"></div></section>' + (S.ai ? '<aside class="tss-chat" aria-label="Konverzace s AI"><h2>AI úpravy</h2><div class="tss-msgs"></div><form class="tss-ask"><textarea name="text" rows="3" maxlength="1500" placeholder="Např. „Barvy teplejší, menší logotyp, v úvodníku zmínit svatební účesy“" aria-label="Pokyn pro AI"></textarea><button class="tss-btn" type="submit">Poslat</button><small class="tss-muted">Úprava vznikne jako návrh verze – přijímáte ji vy.</small></form></aside>' : '') + '</div>';
    renderVersions(); renderMsgs();
  }
  function renderVersions() {
    $('.tss-vers ol').innerHTML = S.p.versions.map(function (v) {
      return '<li><button type="button" data-ver="' + v.num + '" class="' + (v.num === S.sel ? 'on' : '') + '"><b>v' + v.num + '</b> <span class="vs-' + v.status + '">' + VST[v.status] + '</span>' + (v.author_kind === 'ai' ? ' <em>AI</em>' : '') + (v.num === S.p.project.current_num ? ' <em>aktuální</em>' : '') + '<small>' + esc(v.message) + '</small><small>' + esc(String(v.created_at).slice(5, 16)) + '</small></button></li>';
    }).join('');
  }
  function renderMsgs() {
    var m = $('.tss-msgs'); if (!m) return;
    m.innerHTML = S.p.messages.map(function (x) { return '<p class="' + (x.role === 'user' ? 'me' : 'ai') + '"><b>' + (x.role === 'user' ? 'Vy' : 'Studio') + (x.num ? ' · v' + x.num : '') + '</b>' + esc(x.text) + '</p>'; }).join('') || '<p class="tss-muted">Zatím bez konverzace.</p>';
    m.scrollTop = m.scrollHeight;
  }
  function selectVersion(num) {
    if (S.dirty && !confirm('Neuložené změny v kódu se zahodí. Pokračovat?')) return Promise.resolve();
    S.dirty = false; S.sel = num; renderVersions();
    return api('version', null, '&pid=' + S.p.project.id + '&num=' + num).then(function (v) {
      if (!v.ok) return toast(errText(v), true);
      S.ver = v; if (!S.file || !v.files.some(function (f) { return f.path === S.file; })) S.file = 'index.html';
      var errs = v.checks.filter(function (c) { return c.level === 'error'; }).length, cur = num === S.p.project.current_num;
      $('.tss-vhead').innerHTML = '<div><b>Verze ' + v.num + '</b> · <span class="vs-' + v.status + '">' + VST[v.status] + '</span>' + (v.author === 'ai' ? ' · AI' : '') + ' · ' + esc(v.message) + (v.parent ? ' <span class="tss-muted">(z v' + v.parent + ')</span>' : '') + '</div>' +
        '<div class="tss-vact"><span class="' + (errs ? 'tss-err' : 'tss-ok') + '">' + (errs ? errs + ' chyb kontrol' : 'kontroly v pořádku') + '</span>' + (v.todo.length ? ' · <span class="tss-warn">' + v.todo.length + ' k doplnění</span>' : '') +
        (v.status === 'candidate' ? ' <button type="button" class="tss-btn" data-act="accept"' + (errs ? ' disabled title="Nejdřív opravte chyby kontrol"' : '') + '>Přijmout</button> <button type="button" class="tss-btn ghost" data-act="reject">Zamítnout</button>' : '') +
        (!cur && v.status !== 'candidate' ? ' <button type="button" class="tss-btn ghost" data-act="revert">Vrátit se k této verzi</button>' : '') + '</div>';
      renderTab();
    });
  }
  function renderTab() {
    $$('.tss-work [data-tab]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-tab') === S.tab)); });
    ({ preview: tabPreview, content: tabContent, code: tabCode, diff: tabDiff, checks: tabChecks })[S.tab]();
  }
  function tabPreview() {
    var W = [[1280, 'Počítač'], [820, 'Tablet'], [390, 'Mobil']];
    $('.tss-pane').innerHTML = '<div class="tss-widths" role="group" aria-label="Šířka náhledu">' + W.map(function (w) { return '<button type="button" data-w="' + w[0] + '" aria-pressed="' + (S.width === w[0]) + '">' + w[1] + ' <small>' + w[0] + ' px</small></button>'; }).join('') +
      '</div><div class="tss-frame"><iframe sandbox="allow-scripts" title="Náhled verze ' + S.sel + '" width="' + S.width + '" height="' + (S.width < 500 ? 844 : 900) + '"></iframe></div>';
    api('preview', null, '&pid=' + S.p.project.id + '&num=' + S.sel).then(function (j) { var f = $('.tss-frame iframe'); if (f) { f.srcdoc = j.html || '<p>Náhled nelze zobrazit.</p>'; fitFrame(); } });
  }
  function fitFrame() {
    var fr = $('.tss-frame'), f = fr && fr.querySelector('iframe'); if (!f) return;
    fr.style.maxWidth = ''; var sc = Math.min(1, fr.clientWidth / f.width); f.style.transform = 'scale(' + sc + ')'; fr.style.height = Math.round(f.height * sc) + 'px'; fr.style.maxWidth = Math.round(f.width * sc) + 2 + 'px';
  }
  function tabContent() {
    var v = S.ver, sl = v.schema.slots, tk = v.schema.tokens, html = '<form class="tss-cform"><fieldset><legend>Barvy a písma</legend><div class="tss-tokens">';
    Object.keys(tk).forEach(function (k) {
      var d = tk[k], val = v.tokens[k] || d.value;
      if (d.type === 'font') html += '<label class="tss-f"><span>' + esc(d.label) + '</span><select data-token="' + esc(k) + '">' + (d.options || []).map(function (o) { return '<option' + (o === val ? ' selected' : '') + ' value="' + esc(o) + '">' + esc(o.split(',')[0].replace(/"/g, '')) + '</option>'; }).join('') + '</select></label>';
      else html += '<label class="tss-color"><input type="color" data-token="' + esc(k) + '" value="' + esc(val) + '"><span>' + esc(d.label) + '</span></label>';
    });
    html += '</div></fieldset><fieldset><legend>Texty</legend>';
    Object.keys(sl).forEach(function (k) {
      var s = sl[k], val = v.content[k];
      if (s.type === 'list') {
        html += '<div class="tss-list" data-list="' + esc(k) + '"><span class="tss-ll">' + esc(s.label || k) + ' <small>(nejvýš ' + (s.items || 12) + ')</small></span>' + (val || []).map(function (row) { return listRow(s, row); }).join('') +
          '<button type="button" class="tss-btn ghost sm" data-addrow="' + esc(k) + '">+ položka</button></div>';
      } else {
        var todo = String(val || '').indexOf('[doplnit]') >= 0, a = ' data-slot="' + esc(k) + '"' + (s.max ? ' maxlength="' + s.max + '"' : '');
        html += '<label class="tss-f' + (todo ? ' todo' : '') + '"><span>' + esc(s.label || k) + (s.grounded ? ' <small>(jen ze zadání)</small>' : '') + '</span>' + (s.type === 'multiline' ? '<textarea rows="3"' + a + '>' + esc(val) + '</textarea>' : '<input' + a + ' value="' + esc(val) + '">') + '</label>';
      }
    });
    html += '</fieldset><p class="tss-sticky"><input class="tss-msg" name="message" maxlength="300" placeholder="Popis změny (nepovinné)" aria-label="Popis změny"><button class="tss-btn" type="submit">Uložit jako novou verzi</button></p></form>';
    $('.tss-pane').innerHTML = html;
  }
  function listRow(s, row) {
    row = row || {};
    return '<div class="tss-row">' + Object.keys(s.fields || {}).map(function (f) {
      var fs = s.fields[f], val = row[f] == null ? '' : row[f];
      return '<label class="' + (String(val).indexOf('[doplnit]') >= 0 ? 'todo' : '') + '"><span>' + esc(fs.label || f) + '</span><input data-field="' + esc(f) + '"' + (fs.type === 'color' ? ' type="color"' : '') + (fs.max ? ' maxlength="' + fs.max + '"' : '') + ' value="' + esc(val || (fs.type === 'color' ? '#cccccc' : '')) + '"></label>';
    }).join('') + '<button type="button" class="tss-x" data-delrow aria-label="Odebrat položku">×</button></div>';
  }
  function saveContent(form) {
    var content = {}, tokens = {};
    $$('[data-slot]', form).forEach(function (el) { content[el.getAttribute('data-slot')] = el.value; });
    $$('[data-list]', form).forEach(function (el) { content[el.getAttribute('data-list')] = $$('.tss-row', el).map(function (r) { var o = {}; $$('[data-field]', r).forEach(function (i) { o[i.getAttribute('data-field')] = i.value; }); return o; }); });
    $$('[data-token]', form).forEach(function (el) { tokens[el.getAttribute('data-token')] = el.value; });
    api('render', { pid: S.p.project.id, base: S.sel, content: content, tokens: tokens, message: form.querySelector('.tss-msg').value }).then(function (j) {
      if (!j.ok) return toast(errText(j), true);
      toast('Uloženo jako verze ' + j.num + (j.errors && j.errors.length ? ' – opraveno: ' + j.errors.join('; ') : '') + '.'); S.tab = 'preview'; S.sel = j.num; loadProject(S.p.project.id, true);
    });
  }
  function tabCode() {
    var files = S.ver.files;
    $('.tss-pane').innerHTML = '<div class="tss-code"><nav class="tss-tree" aria-label="Soubory">' + files.map(function (f) {
      return f.text ? '<button type="button" data-file="' + esc(f.path) + '" class="' + (f.path === S.file ? 'on' : '') + '">' + esc(f.path) + '</button>' : '<span title="Binární soubor ze šablony (písmo, obrázek)">' + esc(f.path) + ' <small>' + Math.round(f.size / 1024) + ' kB</small></span>';
    }).join('') + '</nav><div class="tss-src"><div class="tss-srch"><b>' + esc(S.file) + '</b> <span class="tss-muted">verze ' + S.sel + '</span></div><textarea spellcheck="false" wrap="off" aria-label="Zdrojový kód ' + esc(S.file) + '"></textarea>' +
      '<p class="tss-sticky"><input class="tss-msg" maxlength="300" placeholder="Popis změny" aria-label="Popis změny"><button type="button" class="tss-btn" data-act="savecode">Uložit jako novou verzi</button></p></div></div>';
    api('file', null, '&pid=' + S.p.project.id + '&num=' + S.sel + '&path=' + encodeURIComponent(S.file)).then(function (j) { var t = $('.tss-src textarea'); if (t) { t.value = j.source || ''; S.dirty = false; } });
  }
  function tabDiff() {
    var v = S.ver;
    if (!v.parent) { $('.tss-pane').innerHTML = '<p class="tss-muted">První verze – není s čím srovnat.</p>'; return; }
    api('diff', null, '&pid=' + S.p.project.id + '&a=' + v.parent + '&b=' + v.num).then(function (j) {
      var names = Object.keys(j.files || {});
      if (!names.length) { $('.tss-pane').innerHTML = '<p class="tss-muted">Proti verzi ' + v.parent + ' beze změny souborů.</p>'; return; }
      $('.tss-pane').innerHTML = '<p class="tss-muted">Změny proti verzi ' + v.parent + '</p>' + names.map(function (n) {
        var f = j.files[n], lines = String(j.diff[n] || '').split('\n').map(function (l) { var c = l[0] === '+' && l.slice(0, 3) !== '+++' ? 'add' : l[0] === '-' && l.slice(0, 3) !== '---' ? 'del' : l.slice(0, 2) === '@@' ? 'hunk' : ''; return '<span class="' + c + '">' + esc(l) + '</span>'; }).join('\n');
        return '<details class="tss-dfile" open><summary><b>' + esc(n) + '</b> <span class="add">+' + f.plus + '</span> <span class="del">−' + f.minus + '</span></summary><pre>' + lines + '</pre></details>';
      }).join('');
    });
  }
  function tabChecks() {
    var v = S.ver;
    var rows = v.checks.map(function (c) { return '<li class="' + c.level + '"><b>' + (c.level === 'error' ? 'Chyba' : 'Varování') + '</b> ' + esc(c.msg) + ' <small>' + esc(c.file) + (c.line ? ':' + c.line : '') + '</small></li>'; }).join('');
    $('.tss-pane').innerHTML = '<h3>Kontroly</h3>' + (rows ? '<ul class="tss-checks">' + rows + '</ul>' : '<p class="tss-ok">Bez chyb a varování.</p>') +
      '<h3>K doplnění</h3>' + (v.todo.length ? '<p>Tyto sloty obsahují „[doplnit]“ – před předáním klientovi je vyplňte v záložce Obsah a barvy:</p><ul class="tss-todo">' + v.todo.map(function (t) { return '<li><code>' + esc(t) + '</code></li>'; }).join('') + '</ul>' : '<p class="tss-ok">Nic.</p>');
  }

  /* ---------- akce ---------- */
  function act(name, el) {
    var pid = S.p && S.p.project.id;
    if (name === 'new') return viewNew();
    if (name === 'accept' || name === 'reject') return api(name, { pid: pid, num: S.sel }).then(function (j) { if (!j.ok) return toast(errText(j), true); toast(name === 'accept' ? 'Verze ' + S.sel + ' přijata.' : 'Verze zamítnuta.'); loadProject(pid, true); });
    if (name === 'revert') return api('revert', { pid: pid, num: S.sel }).then(function (j) { if (!j.ok) return toast(errText(j), true); toast('Vznikla verze ' + j.num + ' se soubory verze ' + S.sel + '.'); S.sel = j.num; loadProject(pid, true); });
    if (name === 'export') { location.href = API + '?r=export&pid=' + pid + '&num=' + S.sel; return; }
    if (name === 'savecode') {
      var files = {}; files[S.file] = $('.tss-src textarea').value;
      return api('patch', { pid: pid, base: S.sel, files: files, message: $('.tss-src .tss-msg').value }).then(function (j) {
        if (!j.ok) return toast(errText(j), true);
        S.dirty = false; var e = j.checks.filter(function (c) { return c.level === 'error'; }).length;
        toast('Uloženo jako verze ' + j.num + (e ? ' – ' + e + ' chyb kontrol, verzi nejde přijmout' : '') + '.', !!e); S.sel = j.num; loadProject(pid, true);
      });
    }
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('button, a[data-act]'); if (!b || !root.contains(b)) return;
    if (b.hasAttribute('data-act')) { e.preventDefault(); act(b.getAttribute('data-act'), b); }
    else if (b.hasAttribute('data-ver')) selectVersion(+b.getAttribute('data-ver'));
    else if (b.hasAttribute('data-tab')) { if (S.tab === 'code' && S.dirty && !confirm('Neuložené změny v kódu se zahodí. Pokračovat?')) return; S.dirty = false; S.tab = b.getAttribute('data-tab'); renderTab(); }
    else if (b.hasAttribute('data-w')) { S.width = +b.getAttribute('data-w'); tabPreview(); }
    else if (b.hasAttribute('data-file')) { if (S.dirty && !confirm('Neuložené změny se zahodí. Pokračovat?')) return; S.file = b.getAttribute('data-file'); tabCode(); }
    else if (b.hasAttribute('data-mode')) { var m = b.getAttribute('data-mode'); $$('[data-mode]').forEach(function (x) { x.setAttribute('aria-selected', String(x === b)); }); $('[data-form="tpl"]').hidden = m !== 'tpl'; $('[data-form="ai"]').hidden = m !== 'ai'; }
    else if (b.hasAttribute('data-addrow')) { var k = b.getAttribute('data-addrow'), s = S.ver.schema.slots[k], box = b.parentNode; if ($$('.tss-row', box).length >= (s.items || 12)) return toast('Nejvýš ' + (s.items || 12) + ' položek.', true); b.insertAdjacentHTML('beforebegin', listRow(s, {})); }
    else if (b.hasAttribute('data-delrow')) b.parentNode.remove();
  });
  root.addEventListener('change', function (e) {
    if (e.target.matches('[data-act="status"]')) api('status', { pid: S.p.project.id, status: e.target.value }).then(function (j) { if (!j.ok) return toast(errText(j), true); toast('Stav: ' + STATUS[e.target.value]); });
    if (e.target.matches('[data-form="ai"] input[name="source"]')) { var v = e.target.value; $$('[data-only]').forEach(function (x) { x.hidden = x.getAttribute('data-only') !== v; }); }
  });
  root.addEventListener('input', function (e) { if (e.target.matches('.tss-src textarea')) S.dirty = true; });
  root.addEventListener('keydown', function (e) {
    if (e.key === 'Tab' && e.target.matches('.tss-src textarea') && !e.shiftKey) { e.preventDefault(); var t = e.target, s = t.selectionStart; t.setRangeText('  ', s, t.selectionEnd, 'end'); S.dirty = true; }
  });
  root.addEventListener('submit', function (e) {
    e.preventDefault(); var f = e.target;
    if (f.matches('[data-form]')) submitNew(f);
    else if (f.matches('.tss-cform')) saveContent(f);
    else if (f.matches('.tss-ask')) {
      var t = f.text.value.trim(); if (!t) return; var btn = f.querySelector('button'); btn.disabled = true; var pid = S.p.project.id;
      api('ai-edit', { pid: pid, text: t }).then(function (j) {
        if (!j.ok) { btn.disabled = false; return toast(errText(j), true); }
        f.text.value = '';
        return waitJob(j.job, 'AI upravuje').then(function (r) {
          btn.disabled = false;
          if (r.status !== 'done') return toast('Úprava se nepovedla: ' + errText({ error: 'ai', kind: r.error, detail: r.detail }), true);
          toast(r.result.num ? 'Návrh verze ' + r.result.num + ' je připravený (' + r.seconds + ' s).' : 'Beze změny: ' + r.result.reply);
          if (r.result.num) { S.sel = r.result.num; S.tab = 'preview'; } loadProject(pid, true);
        });
      });
    }
  });
  window.addEventListener('resize', fitFrame);
  window.addEventListener('beforeunload', function (e) { if (S.dirty) { e.preventDefault(); e.returnValue = ''; } });
  function route() {
    var m = /^#\/p\/(\d+)/.exec(location.hash);
    if (m) loadProject(+m[1]); else viewList();
  }
  window.addEventListener('hashchange', route);
  api('templates').then(function (j) { S.templates = j.templates || []; S.ai = !!j.ai; route(); });
})();
