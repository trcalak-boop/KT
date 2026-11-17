#!/usr/bin/env python3
"""Etapa 6.2 – AI Design Studio na NEVEŘEJNÉM stagingu test.tasstudio.eu: test kvality modelu Gemma 4 na 3 zadáních (A nový, B redesign z auditu, C lead z CRM).

  7-balicek-studio/k-nahrani/test/          → obsah nahrát do /sub/test/   (studio.php, api/studio/studio.php, vendor/ts/ts-design-studio, zámek komponent)
  7-balicek-studio/k-nahrani/studio-test/   → obsah nahrát do /private/studio-test/   (konfigurace, .htaccess, data/)

Model: Cloudflare Workers AI se stávajícím tokenem stagingu (/private/ai-test) – žádný nový klíč. Denní strop Studia 2 500 neuronů (Tessa a audit mají zbytek kvóty).
Přístup: stejná cookie jako staging Tessy a auditu; bez ní 404. Všechna zadání jsou smyšlená (žádná skutečná data).
Použití: python3 -I sestavit_balicek_studio.py"""
import hashlib, json, os, re, shutil, subprocess, sys, tempfile
import importlib.util

HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.expanduser('~/TS-Studio/ts-library')
spec = importlib.util.spec_from_file_location('b52', os.path.join(HERE, 'sestavit_balicek_audit.py')); b52 = importlib.util.module_from_spec(spec); spec.loader.exec_module(b52)
VRSTVY = b52.VRSTVY + [os.path.join(HERE, '6-balicek-audit', 'k-nahrani', 'test'), os.path.join(HERE, '6-balicek-audit', 'oprava-5.3', 'test')]
OUT = os.path.join(HERE, '7-balicek-studio'); KT = os.path.join(OUT, 'k-nahrani', 'test'); KP = os.path.join(OUT, 'k-nahrani', 'studio-test')
sha = lambda p: hashlib.sha256(open(p, 'rb').read()).hexdigest()
w = b52.w

API = r'''<?php
/* AI Design Studio – API neveřejného stagingu (etapa 6.2). Jen s přístupovou cookie stagingu, jinak 404. Konfigurace a data mimo webroot v /private/studio-test/.
   Model (token Cloudflare) se načte před zúžením open_basedir; potom PHP smí jen do webrootu subdomény a /private/studio-test/.
   Dlouhé dotazy na model (návrh, úprava): odpověď 202 s číslem úlohy hned, práce pokračuje po fastcgi_finish_request, stránka se ptá na stav. */
require __DIR__ . '/../../vendor/ts/ts-design-studio/core/bootstrap.php';
require __DIR__ . '/../../vendor/ts/ts-ai-consultant/core/bootstrap.php';           // ts_ai_harden (HTTPS, open_basedir)
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow'); header('Referrer-Policy: no-referrer');
$private = dirname(__DIR__, 4) . '/private/studio-test';
function out(int $code, array $j): void { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
if (!is_file($private . '/studio.php')) out(503, ['ok' => false, 'error' => 'config']);
$cfg = require $private . '/studio.php';
$a = $cfg['access'];
if (!is_string($_COOKIE[$a['cookie']] ?? null) || !hash_equals($a['hash'], hash('sha256', (string) $_COOKIE[$a['cookie']]))) out(404, ['ok' => false]);
try { $studio = ts_design_studio($cfg['studio']); } catch (Throwable $e) { out(503, ['ok' => false, 'error' => 'ai', 'detail' => $e->getMessage()]); }
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [dirname(__DIR__, 2), $private]])) exit;
$uid = 1;   /* staging: jeden uživatel (role řeší krok 6.4) */
$r = (string) ($_GET['r'] ?? '');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post) {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if (!in_array($origin, $cfg['allowedOrigins'], true) || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') !== 'same-origin') out(403, ['ok' => false, 'error' => 'origin']);
    $in = json_decode((string) file_get_contents('php://input'), true); if (!is_array($in)) out(400, ['ok' => false, 'error' => 'json']);
}
$jobs = $private . '/data/ulohy';
$job = function (callable $work) use ($jobs): void {
    if (!is_dir($jobs)) mkdir($jobs, 0700, true);
    foreach (glob($jobs . '/*.json') as $f) if (filemtime($f) < time() - 86400) @unlink($f);
    $id = bin2hex(random_bytes(8)); $f = "$jobs/$id.json";
    file_put_contents($f, json_encode(['status' => 'running', 'started' => time()]));
    http_response_code(202); echo json_encode(['ok' => true, 'job' => $id]);
    ignore_user_abort(true); @set_time_limit(300);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    $t0 = microtime(true);
    try { $res = $work(); file_put_contents($f, json_encode(['status' => 'done', 'result' => $res, 'seconds' => round(microtime(true) - $t0, 1)], JSON_UNESCAPED_UNICODE)); }
    catch (TsStudioModelError $e) { file_put_contents($f, json_encode(['status' => 'error', 'error' => $e->kind, 'detail' => mb_substr($e->getMessage(), 0, 300), 'seconds' => round(microtime(true) - $t0, 1)], JSON_UNESCAPED_UNICODE)); }
    catch (Throwable $e) { file_put_contents($f, json_encode(['status' => 'error', 'error' => 'internal', 'detail' => mb_substr($e->getMessage(), 0, 300)], JSON_UNESCAPED_UNICODE)); }
    exit;
};
try {
    switch ($r) {
        case 'list':
            $list = [];
            foreach ($studio->projects($uid) as $p) {
                $g = $studio->get((int) $p['id'], $uid);
                $list[] = ['project' => $p, 'versions' => $g['versions'], 'messages' => $studio->messages((int) $p['id'], $uid)];
            }
            out(200, ['ok' => true, 'projects' => $list, 'usage' => $studio->usage()]);
        case 'create':
            if (!$post) out(405, ['ok' => false]);
            $src = in_array($in['source'] ?? '', TsDesignStudio::SOURCES, true) ? $in['source'] : 'new';
            $brief = $src === 'crm' ? TsStudioAi::briefFromLead((array) ($in['lead'] ?? []), (array) ($in['leadNotes'] ?? [])) + (array) ($in['brief'] ?? [])
                   : ($src === 'redesign' ? TsStudioAi::briefFromAudit((array) ($in['audit'] ?? []), (string) ($in['texts'] ?? ''), (array) ($in['brief'] ?? [])) : (array) ($in['brief'] ?? []));
            $job(fn () => $studio->aiCreate($brief, $uid, ['source' => $src, 'lead_ref' => $src === 'crm' ? 'test-lead' : '', 'audit_ref' => $src === 'redesign' ? 'test-audit' : '']));
        case 'edit':
            if (!$post) out(405, ['ok' => false]);
            $job(fn () => $studio->aiEdit((int) ($in['pid'] ?? 0), $uid, (string) ($in['text'] ?? '')));
        case 'accept':
            if (!$post) out(405, ['ok' => false]);
            $studio->accept((int) ($in['pid'] ?? 0), (int) ($in['num'] ?? 0), $uid); out(200, ['ok' => true]);
        case 'reject':
            if (!$post) out(405, ['ok' => false]);
            $studio->reject((int) ($in['pid'] ?? 0), (int) ($in['num'] ?? 0), $uid); out(200, ['ok' => true]);
        case 'job':
            $id = (string) ($_GET['id'] ?? ''); if (!preg_match('/^[0-9a-f]{16}$/', $id) || !is_file("$jobs/$id.json")) out(404, ['ok' => false]);
            out(200, ['ok' => true] + json_decode((string) file_get_contents("$jobs/$id.json"), true));
        case 'version':
            $v = $studio->version((int) ($_GET['pid'] ?? 0), (int) ($_GET['num'] ?? 0), $uid);
            out(200, ['ok' => true, 'content' => $v['content'], 'tokens' => $v['tokens'], 'checks' => $v['checks'], 'status' => $v['status'], 'message' => $v['message'], 'todo' => TsStudioAi::todo($v['content'])]);
        case 'preview':
            out(200, ['ok' => true, 'html' => $studio->preview((int) ($_GET['pid'] ?? 0), (int) ($_GET['num'] ?? 0), $uid)]);
        default:
            out(404, ['ok' => false, 'error' => 'route']);
    }
} catch (TsStudioChecksFailed $e) { out(422, ['ok' => false, 'error' => 'checks', 'errors' => $e->errors]); }
catch (TsStudioDenied $e) { out(403, ['ok' => false, 'error' => 'denied']); }
catch (InvalidArgumentException $e) { out(400, ['ok' => false, 'error' => 'input', 'detail' => $e->getMessage()]); }
'''

PAGE = r'''<?php
/* Neveřejná stránka „AI Design Studio – test kvality“ (etapa 6.2). Přístup jen s cookie stagingu (tajný odkaz asistent.php?pristup=…). Bez ní 404. */
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; frame-src 'self' about:; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
$s = require dirname(__DIR__, 2) . '/private/studio-test/studio.php';
$a = $s['access'];
if (!is_string($_COOKIE[$a['cookie']] ?? null) || !hash_equals($a['hash'], hash('sha256', (string) $_COOKIE[$a['cookie']]))) { http_response_code(404); echo '<!doctype html><meta charset="utf-8"><title>Nenalezeno</title><p>Stránka neexistuje.</p>'; exit; }
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>AI Design Studio · staging T&amp;S Studio</title>
<style>
:root{--paper:#f1f1ee;--surface:#fff;--ink:#111113;--muted:#5d5d63;--line:#d9d9d4;--red:#e10600;--ok:#137a3e;--warn:#9a6300}
@media (prefers-color-scheme:dark){:root{--paper:#111113;--surface:#18181b;--ink:#f1f1ee;--muted:#a8a8ae;--line:#34343a;--ok:#5fd08b;--warn:#f0c26e}}
*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
main{max-width:1240px;margin:0 auto;padding:28px 16px 80px}h1{font-size:34px;margin:10px 0 4px;letter-spacing:-.02em}h2{font-size:22px;margin:34px 0 12px}h3{margin:0 0 6px;font-size:18px}
.tag{display:inline-block;background:var(--red);color:#fff;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:3px 8px}
.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.card{background:var(--surface);border:1px solid var(--line);padding:16px}.card p{margin:.3rem 0}
button{font:inherit;font-weight:650;border:1px solid var(--ink);background:var(--ink);color:var(--paper);padding:8px 14px;cursor:pointer}button.ghost{background:transparent;color:var(--ink)}
button:disabled{opacity:.45;cursor:wait}input,textarea{font:inherit;width:100%;padding:8px;border:1px solid var(--line);background:var(--paper);color:var(--ink)}
.usage{font-variant-numeric:tabular-nums}.bar{height:6px;background:var(--line);margin-top:4px}.bar i{display:block;height:100%;background:var(--red)}
.proj{background:var(--surface);border:1px solid var(--line);margin:18px 0;padding:16px}.proj-h{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:baseline}
.pv{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:14px;margin-top:12px}@media (max-width:900px){.pv{grid-template-columns:1fr}}
.frame{position:relative;border:1px solid var(--line);overflow:hidden;background:#fff}.frame.desk{height:560px}.frame.mob{height:560px}
.frame iframe{border:0;transform-origin:0 0;position:absolute;left:0;top:0}
.vers{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0}.vers button{padding:4px 9px;font-size:13px}.vers button.on{outline:3px solid var(--red)}
.st-candidate{color:var(--warn)}.st-accepted{color:var(--ok)}.st-rejected{text-decoration:line-through}
.chat{margin-top:10px;max-height:260px;overflow:auto;border-top:1px solid var(--line);padding-top:8px}.chat p{margin:.25rem 0;white-space:pre-wrap}.chat .u{color:var(--muted)}
.row{display:flex;gap:8px;margin-top:8px}.row input{flex:1}.checks{font-size:13px}.err{color:var(--red)}
details{margin-top:8px}pre{white-space:pre-wrap;font-size:12px;max-height:320px;overflow:auto;background:var(--paper);padding:8px}
.status{position:fixed;right:16px;bottom:16px;background:var(--ink);color:var(--paper);padding:10px 14px;max-width:420px;display:none}.status.on{display:block}
</style></head>
<body><main>
<span class="tag">Neveřejný staging · etapa 6.2</span>
<h1>AI Design Studio – test kvality</h1>
<p class="muted">Návrh webu ze zadání modelem Cloudflare Workers AI (Gemma 4) do ověřených šablon. Model píše jen obsah a barvy jako JSON, Studio z nich vykreslí soubory a zkontroluje je. Všechna zadání jsou smyšlená.</p>
<div class="card usage" id="usage">Spotřeba…</div>

<h2>Testovací zadání</h2>
<div class="grid" id="briefs"></div>

<h2>Projekty</h2>
<div id="projects"><p class="muted">Načítám…</p></div>
</main>
<div class="status" id="status"></div>
<script src="/studio.js?v=__V__"></script>
</body></html>
'''

JS = r'''/* AI Design Studio – stránka testu kvality na stagingu (etapa 6.2). Bez knihoven; náhled v <iframe sandbox="allow-scripts" srcdoc> (origin null, bez cookies). */
(function () {
  var API = '/api/studio/studio.php';
  var BRIEFS = [
    { key: 'A', title: 'A · nový projekt', desc: 'Kadeřnictví v Brně – zadání z formuláře.', body: { source: 'new', brief: {
      name: 'Salon Vlna', obor: 'kadeřnictví a barvení vlasů', audience: 'ženy 25–55 let z Brna, které chtějí přirozenou barvu a klidnou atmosféru',
      style: 'světlý, vzdušný, moderní, šalvějově zelená a teplá béžová, žádná černá', services: 'dámský střih, foukaná, barvení, balayage, péče o vlasy, svatební účesy',
      facts: 'Adresa: Údolní 18, 602 00 Brno\nTelefon: +420 731 555 210\nE-mail: ahoj@salonvlna.example\nOtevřeno: Po–Pá 8–19, So 9–13\nMajitelka: Petra Horká, 12 let praxe\nDámský střih od 650 Kč, barvení od 1 200 Kč',
      notes: 'Chce působit lidsky, žádné luxusní fráze.' } } },
    { key: 'B', title: 'B · redesign z auditu', desc: 'Bistro se zastaralým webem – zjištění z auditu a texty ze současného webu.', body: { source: 'redesign',
      audit: { url: 'https://bistro-u-mlyna.example/', devices: { mobile: { scores: { performance: 38, accessibility: 71, 'best-practices': 79, seo: 82 } } },
        recommendations: [{ basis: 'measurement', text: 'Obrázky jsou příliš velké – zpomalují načtení na mobilu.' }, { basis: 'measurement', text: 'Text má nízký kontrast vůči pozadí.' },
          { basis: 'ai', text: 'Chybí výrazné tlačítko pro rezervaci stolu.' }, { basis: 'ai', text: 'Menu je jen v PDF, na mobilu se špatně čte.' }] },
      texts: 'Bistro U Mlýna vaří od roku 2011 v bývalém mlýně u řeky Svratky. Polední menu každý všední den, večer à la carte. Pečeme vlastní chléb. Zahrádka u vody od května do září.\nHlavní menu: svíčková 245 Kč, pečená kachna 295 Kč, smažený sýr 189 Kč.\nOtevřeno Út–So 11–22, Ne 11–16. Mlýnská 4, 664 34 Kuřim. Tel. +420 541 000 777.',
      brief: { name: 'Bistro U Mlýna', obor: 'bistro, restaurace', style: 'útulný, rustikální, tmavé dřevo a krémová', notes: 'Klient chce rezervace stolu telefonem.' } } },
    { key: 'C', title: 'C · lead z CRM', desc: 'Poptávka z webu – málo informací, šablonu vybírá model.', body: { source: 'crm',
      lead: { name: 'Pekárna Kvásek', phone: '+420 606 222 999', email: 'info@kvasek.example', website: '', outreach_reason: 'Poptávka z webu: chceme jednoduchý web pro naši pekárnu s kavárnou, ukázat sortiment a otevírací dobu.' },
      leadNotes: ['Telefonát: pekárna a malá kavárna v Olomouci, 2 provozovny, chléb z kvásku, objednávky dortů.'], brief: { obor: 'pekárna s kavárnou' } } }
  ];
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var selected = {};
  function status(t, on) { var s = $('#status'); s.textContent = t; s.classList.toggle('on', !!on); }
  function api(r, body, q) {
    return fetch(API + '?r=' + r + (q || ''), body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' } : { credentials: 'same-origin' })
      .then(function (res) { return res.json().then(function (j) { j._code = res.status; return j; }); });
  }
  function wait(job, label) {
    var t0 = Date.now();
    return new Promise(function (ok) {
      (function tick() {
        api('job', null, '&id=' + job).then(function (j) {
          if (j.status === 'running') { status(label + ' … ' + Math.round((Date.now() - t0) / 1000) + ' s', true); setTimeout(tick, 2500); return; }
          ok(j);
        }).catch(function () { setTimeout(tick, 4000); });
      })();
    });
  }
  function run(r, body, label, btn) {
    if (btn) btn.disabled = true; status(label + ' …', true);
    return api(r, body).then(function (j) {
      if (!j.job) throw j;
      return wait(j.job, label);
    }).then(function (j) {
      if (j.status === 'error') status(label + ': chyba ' + j.error + (j.detail ? ' – ' + j.detail : ''), true);
      else { status(label + ': hotovo za ' + j.seconds + ' s', true); setTimeout(function () { status('', false); }, 6000); }
      return load();
    }).catch(function (e) { status(label + ': ' + (e && e.error ? e.error : 'chyba spojení'), true); })
      .then(function () { if (btn) btn.disabled = false; });
  }
  function briefs() {
    $('#briefs').innerHTML = BRIEFS.map(function (b, i) {
      return '<div class="card"><h3>' + esc(b.title) + '</h3><p class="muted">' + esc(b.desc) + '</p><details><summary>Zadání</summary><pre>' + esc(JSON.stringify(b.body, null, 2)) + '</pre></details><p><button data-brief="' + i + '">Navrhnout web</button></p></div>';
    }).join('');
  }
  function usage(u) {
    var pct = u.limit ? Math.min(100, Math.round(u.neurons / u.limit * 100)) : 0;
    $('#usage').innerHTML = 'Dnes (UTC): <b>' + u.neurons + '</b> neuronů z denního stropu Studia ' + u.limit + ' · dotazů ' + u.calls + ' · tokeny ' + u.tokens_in + ' / ' + u.tokens_out + ' · model <code>' + esc(u.model) + '</code><div class="bar"><i style="width:' + pct + '%"></i></div>';
  }
  function project(p) {
    var pr = p.project, id = pr.id, sel = selected[id] || pr.current_num || (p.versions[0] && p.versions[0].num);
    var vers = p.versions.slice().reverse().map(function (v) {
      return '<button class="ghost' + (v.num == sel ? ' on' : '') + '" data-ver="' + id + ':' + v.num + '" title="' + esc(v.message) + '">v' + v.num + ' <span class="st-' + v.status + '">' + { candidate: 'návrh', accepted: 'přijatá', rejected: 'zamítnutá' }[v.status] + '</span>' + (v.author_kind === 'ai' ? ' · AI' : '') + '</button>';
    }).join('');
    var chat = p.messages.map(function (m) { return '<p class="' + (m.role === 'user' ? 'u' : '') + '"><b>' + (m.role === 'user' ? 'Zadání / pokyn' : 'Studio') + (m.num ? ' (v' + m.num + ')' : '') + ':</b> ' + esc(m.text) + '</p>'; }).join('');
    return '<section class="proj" data-pid="' + id + '"><div class="proj-h"><h3>' + esc(pr.name) + '</h3><span class="muted">šablona ' + esc(pr.template) + ' · zdroj ' + esc(pr.source) + ' · přijatá verze ' + pr.current_num + '</span></div>' +
      '<div class="vers">' + vers + '</div><div class="vinfo muted">…</div>' +
      '<div class="pv"><div class="frame desk"><iframe sandbox="allow-scripts" title="Náhled – počítač" width="1280" height="900"></iframe></div><div class="frame mob"><iframe sandbox="allow-scripts" title="Náhled – mobil" width="390" height="844"></iframe></div></div>' +
      '<div class="row"><input placeholder="Pokyn pro AI, např. „Barvy teplejší, větší logotyp, v úvodníku zmínit svatební účesy“" maxlength="1500"><button data-edit="' + id + '">Upravit</button></div>' +
      '<div class="chat">' + chat + '</div></section>';
  }
  function fit(fr) { var f = fr.querySelector('iframe'), sc = fr.clientWidth / f.width; f.style.transform = 'scale(' + sc + ')'; fr.style.height = Math.round(f.height * sc) + 'px'; }
  function show(id, num) {
    selected[id] = num;
    var sec = $('[data-pid="' + id + '"]'); if (!sec) return;
    sec.querySelectorAll('[data-ver]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-ver') === id + ':' + num); });
    api('preview', null, '&pid=' + id + '&num=' + num).then(function (j) { sec.querySelectorAll('iframe').forEach(function (f) { f.srcdoc = j.html || '<p>Náhled nelze zobrazit.</p>'; }); sec.querySelectorAll('.frame').forEach(fit); });
    api('version', null, '&pid=' + id + '&num=' + num).then(function (v) {
      var errs = v.checks.filter(function (c) { return c.level === 'error'; }), warns = v.checks.filter(function (c) { return c.level === 'warn'; });
      sec.querySelector('.vinfo').innerHTML = '<span class="checks">' + esc(v.message) + ' · kontroly: ' + (errs.length ? '<b class="err">' + errs.length + ' chyb</b> (' + esc(errs.map(function (c) { return c.msg; }).join('; ')) + ')' : 'bez chyb') + (warns.length ? ', ' + warns.length + ' varování' : '') +
        (v.todo.length ? ' · k doplnění: ' + esc(v.todo.join(', ')) : '') + '</span>' + (v.status === 'candidate' ? ' <button data-accept="' + id + ':' + num + '"' + (errs.length ? ' disabled' : '') + '>Přijmout</button> <button class="ghost" data-reject="' + id + ':' + num + '">Zamítnout</button>' : '') +
        '<details><summary>Obsah a tokeny (JSON)</summary><pre>' + esc(JSON.stringify({ tokens: v.tokens, content: v.content }, null, 2)) + '</pre></details>';
    });
  }
  function load() {
    return api('list').then(function (j) {
      if (!j.ok) { $('#projects').innerHTML = '<p class="err">Chyba: ' + esc(j.error || j._code) + '</p>'; return; }
      usage(j.usage);
      $('#projects').innerHTML = j.projects.length ? j.projects.map(project).join('') : '<p class="muted">Zatím žádný projekt – spusťte některé zadání.</p>';
      j.projects.forEach(function (p) { show(p.project.id, selected[p.project.id] || (p.versions[0] && p.versions[0].num)); });
    });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    if (b.hasAttribute('data-brief')) { var br = BRIEFS[+b.getAttribute('data-brief')]; run('create', br.body, 'Návrh ' + br.key, b); }
    else if (b.hasAttribute('data-ver')) { var v = b.getAttribute('data-ver').split(':'); show(+v[0], +v[1]); }
    else if (b.hasAttribute('data-edit')) { var id = +b.getAttribute('data-edit'), inp = b.parentNode.querySelector('input'); if (inp.value.trim()) { delete selected[id]; run('edit', { pid: id, text: inp.value.trim() }, 'Úprava', b); } }
    else if (b.hasAttribute('data-accept') || b.hasAttribute('data-reject')) {
      var acc = b.hasAttribute('data-accept'), x = b.getAttribute(acc ? 'data-accept' : 'data-reject').split(':');
      api(acc ? 'accept' : 'reject', { pid: +x[0], num: +x[1] }).then(function (j) { if (!j.ok) status('Nelze: ' + (j.error || ''), true); load(); });
    }
  });
  window.addEventListener('resize', function () { document.querySelectorAll('.frame').forEach(fit); });
  briefs(); load();
})();
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    tmp = tempfile.mkdtemp(dir=os.environ.get('TMPDIR'))
    for v in VRSTVY: shutil.copytree(v, tmp, dirs_exist_ok=True)
    lock = json.load(open(os.path.join(tmp, 'ts-components.lock.json')))['components']
    assert lock['ts-ai-consultant']['version'] == '1.9.2' and lock['ts-site-audit']['version'] == '1.1.3' and 'ts-design-studio' not in lock, 'neočekávaný stav stagingu'
    pred = {os.path.relpath(os.path.join(d, f), tmp): sha(os.path.join(d, f)) for d, _, fs in os.walk(tmp) for f in fs}
    r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'add', 'ts-design-studio', tmp], capture_output=True, text=True)
    if r.returncode: sys.exit('ts.py add: ' + r.stdout + r.stderr)
    ver = json.load(open(os.path.join(LIB, 'components', 'ts-design-studio', 'module.json')))['version']
    w(os.path.join(tmp, 'api', 'studio', 'studio.php'), API)
    w(os.path.join(tmp, 'studio.php'), PAGE.replace('__V__', ver))
    w(os.path.join(tmp, 'studio.js'), JS)
    # do balíčku jen nové a změněné soubory; převodní skripty a testy knihovny na server nepatří
    for d, _, fs in os.walk(tmp):
        for f in fs:
            p = os.path.join(d, f); rel = os.path.relpath(p, tmp)
            if rel.split(os.sep)[0] in ('ts-config', 'ts-theme'): continue
            if '/ts-design-studio/tools/' in '/' + rel or '/ts-design-studio/tests/' in '/' + rel: continue
            if pred.get(rel) != sha(p): os.makedirs(os.path.dirname(os.path.join(KT, rel)), exist_ok=True); shutil.copy2(p, os.path.join(KT, rel))
    shutil.rmtree(tmp)
    assert not any('/tests/' in os.path.join(d, f) or '/tools/' in os.path.join(d, f) for d, _, fs in os.walk(KT) for f in fs), 'testy a nástroje nepatří na server'

    acc = re.search(r"'access' => \['cookie' => '([^']+)', 'hash' => '([0-9a-f]{64})'\]", open(os.path.join(b52.T, 'ai-test', 'ai-consultant.php'), encoding='utf-8').read())
    assert acc, 'přístupová cookie stagingu nenalezena'
    w(os.path.join(KP, 'studio.php'), '''<?php
/* ==========================================================================
   AI Design Studio – NEVEŘEJNÝ staging (test.tasstudio.eu/studio.php). Konfigurace mimo webroot: /private/studio-test/studio.php
   Model: Cloudflare Workers AI se stávajícím tokenem stagingu (/private/ai-test), sdílí denní kvótu 10 000 neuronů s Tessou a auditem.
   ========================================================================== */
$private = __DIR__; $ai = dirname(__DIR__) . '/ai-test';
return [
    'studio' => [
        'db' => ['dsn' => 'sqlite:' . $private . '/data/studio.sqlite', 'autoMigrate' => true],
        'timezone' => 'Europe/Prague',
        'ai' => ['provider' => 'cloudflare', 'apiKeyFile' => $ai . '/cloudflare-token.txt', 'accountIdFile' => $ai . '/cloudflare-ucet.txt',
                 'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 4500, 'timeout' => 150, 'neuronsPerDay' => 2500],
    ],
    'allowedOrigins' => ['https://test.tasstudio.eu'],
    'access' => ['cookie' => '%s', 'hash' => '%s'],
];
''' % (acc.group(1), acc.group(2)), 0o600)
    w(os.path.join(KP, '.htaccess'), b52.DENY); w(os.path.join(KP, 'data', '.htaccess'), b52.DENY)
    php = os.path.expanduser('~/TS-Studio/tools/php/php')
    for f in (os.path.join(KP, 'studio.php'), os.path.join(KT, 'studio.php'), os.path.join(KT, 'api', 'studio', 'studio.php')):
        r = subprocess.run([php, '-l', f], capture_output=True, text=True); assert r.returncode == 0, r.stdout
    n = sum(len(fs) for _, _, fs in os.walk(KT))
    print('Balíček: %s\n  /sub/test: %d souborů, /private/studio-test: %d' % (OUT, n, sum(len(fs) for _, _, fs in os.walk(KP))))


if __name__ == '__main__':
    main()
