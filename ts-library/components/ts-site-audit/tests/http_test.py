#!/usr/bin/env python3
"""Integrační test ts-site-audit: API přes php -S s falešným měřením a hodnocením (bez sítě, DNS podstrčené), přístupová cookie,
zobrazení v Chromu (formulář, průběh po krocích, report s oddělenými fakty a AI, snímky, záložky, seznam pro administraci), mobil.
Poslední řádek: RESULT pass=N fail=M"""
import json, os, shutil, subprocess, sys, tempfile, time, urllib.error, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); COMP = os.path.abspath(os.path.join(HERE, '..')); LIB = os.path.abspath(os.path.join(COMP, '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT = 8273; BASE = 'http://localhost:%d' % PORT
KEY = 'pristup-test-0123456789'
P = F = 0


def ok(c, msg, detail=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + msg + (' | ' + str(detail)[:400] if detail else ''))


tmp = tempfile.mkdtemp(prefix='tsaudit-http-'); W = os.path.join(tmp, 'web'); PR = os.path.join(tmp, 'private')
os.makedirs(os.path.join(W, 'vendor', 'ts')); os.makedirs(os.path.join(W, 'api', 'audit')); os.makedirs(os.path.join(PR, 'data'))
shutil.copytree(COMP, os.path.join(W, 'vendor', 'ts', 'ts-site-audit'), ignore=shutil.ignore_patterns('tests'))
import hashlib
open(os.path.join(PR, 'site-audit.php'), 'w').write('''<?php
$dns = ['example.cz' => ['93.184.216.34']];
return ['timezone' => 'Europe/Prague', 'source' => 'admin', 'meter' => ['provider' => 'fake'], 'vision' => ['provider' => 'fake'],
  'db' => ['dsn' => 'sqlite:' . __DIR__ . '/data/audit.sqlite'], 'shotDir' => __DIR__ . '/data/snimky', 'salt' => 'x',
  'resolve' => fn ($h) => $dns[$h] ?? [], 'access' => ['cookie' => 'tsai_pristup', 'hash' => '%s']];
''' % hashlib.sha256(KEY.encode()).hexdigest())
open(os.path.join(W, 'api', 'audit', 'audit.php'), 'w').write('''<?php
require __DIR__ . '/../../vendor/ts/ts-site-audit/core/bootstrap.php';
ts_site_audit(require dirname(__DIR__, 3) . '/private/site-audit.php')->serve();
''')
open(os.path.join(W, 'audit.html'), 'w').write('''<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Audit</title>
<link rel="stylesheet" href="/vendor/ts/ts-site-audit/public/ts-audit.css"></head><body><main style="padding:16px"><h1>Audity</h1><div id="ts-audit"></div></main>
<script>window.TS_AUDIT_CONFIG = { api: '/api/audit/audit.php', lang: 'cs', admin: true };</script>
<script src="/vendor/ts/ts-site-audit/public/ts-audit.js" defer></script></body></html>''')
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)


def http(method, route, body=None, cookie=True, q=''):
    h = {'Accept': 'application/json', 'Content-Type': 'application/json'}
    if cookie: h['Cookie'] = 'tsai_pristup=' + KEY
    req = urllib.request.Request(BASE + '/api/audit/audit.php?r=' + route + q, data=json.dumps(body).encode() if body is not None else None, headers=h, method=method)
    try:
        with urllib.request.urlopen(req, timeout=20) as r: return r.status, r.headers.get('content-type'), r.read()
    except urllib.error.HTTPError as e: return e.code, e.headers.get('content-type'), e.read()


br = None
try:
    c, _, b = http('POST', 'audit', {'url': 'example.cz'}, cookie=False)
    ok(c == 403 and json.loads(b)['error'] == 'access', 'bez přístupové cookie: 403 (neveřejný staging)')
    c, _, b = http('POST', 'audit', {'url': 'http://169.254.169.254/latest/meta-data'})
    ok(c == 422 and json.loads(b)['reason'] == 'ip', 'adresa metadat cloudu odmítnuta')
    c, _, b = http('POST', 'audit', {'url': 'example.cz', 'lang': 'cs'}); j = json.loads(b)
    ok(c == 202 and len(j['id']) == 48, 'API: audit založen (202)', j)
    for _ in range(5):
        c, _, b = http('GET', 'audit-status', q='&id=' + j['id']); s = json.loads(b)
        if s['status'] == 'done': break
    ok(s['status'] == 'done' and s['report']['devices']['mobile']['scores']['performance'] == 54, 'API: dotazy na stav posunou audit až do reportu', s.get('status'))
    c, ct, b = http('GET', 'audit-shot', q='&id=%s&d=mobile' % j['id'])
    ok(c == 200 and ct == 'image/jpeg' and b[:2] == b'\xff\xd8', 'API: snímek jako JPEG')
    c, _, b = http('GET', 'audit-list'); L = json.loads(b)
    ok(c == 200 and L['items'][0]['url'] == 'https://example.cz/' and 'token_hash' not in L['items'][0], 'API: seznam auditů pro administraci (bez otisků tokenů)')
    c, _, b = http('GET', 'audit-status', q='&job=%d' % L['items'][0]['id']); ok(c == 200 and json.loads(b)['status'] == 'done', 'administrace: otevření auditu podle čísla úlohy')

    br = Browser(9361)
    br.size(1280, 900); br.go(BASE + '/audit.html', wait=.3)
    br.js("document.cookie = 'tsai_pristup=%s; path=/'" % KEY); br.go(BASE + '/audit.html', wait=1.5)
    ok(br.js("return document.querySelectorAll('.tsa-table tbody tr').length") == 1, 'stránka: seznam posledních auditů')
    br.js("document.getElementById('tsa-url').value = 'http://127.0.0.1'; document.querySelector('.tsa-btn').click()", wait=1)
    ok('nelze auditovat' in br.js("return document.querySelector('.tsa-status').textContent"), 'stránka: interní adresa → srozumitelná chyba')
    shutil.rmtree(os.path.join(PR, 'data', 'snimky'), ignore_errors=True)
    br.js("TSAudit.run('https://example.cz/kontakt')", wait=8)
    r = br.js("return [document.querySelector('.tsa-head h2') && document.querySelector('.tsa-head h2').textContent, [...document.querySelectorAll('#tsa-p-mobile .tsa-score strong')].map(e=>e.textContent), document.querySelectorAll('.tsa-sec:not(.tsa-ai) li').length, document.querySelectorAll('.tsa-ai li').length, document.querySelectorAll('.tsa-notes li').length]")
    ok(r[1] == ['54', '81', '96', '90'] and r[2] == 4 and r[3] == 2 and r[4] == 3, 'report: skóre mobilu, 4 fakta z měření, 2 doporučení AI, 3 poznámky o omezeních', r)
    ok(br.js("return [...document.querySelectorAll('.tsa-sec h3')].map(e=>e.textContent).join('|')") == 'Zjištěno měřením|Doporučení AI k vzhledu (subjektivní)', 'report: fakta a AI v oddělených částech')
    ok(br.js("return !/\\d/.test(document.querySelector('.tsa-ai').textContent.replace(/\\(.*?\\)/g,''))"), 'doporučení AI bez čísel')
    ok(br.js("const i=document.querySelector('#tsa-p-mobile .tsa-shot img'); return i && i.complete && i.naturalWidth === 1"), 'snímek mobilu se načetl (jen s tokenem)')
    br.js("document.getElementById('tsa-t-desktop').click()", wait=.2)
    ok(br.js("return document.getElementById('tsa-p-mobile').hidden && !document.getElementById('tsa-p-desktop').hidden && document.querySelector('#tsa-p-desktop .tsa-score strong').textContent === '88'"), 'záložka Počítač: desktopová skóre')
    ok('Měřeno' in br.js("return document.querySelector('.tsa-meta').textContent") and 'Lighthouse' in br.js("return document.querySelector('.tsa-meta').textContent"), 'report: datum měření a zdroj')
    ok(br.js("return document.querySelectorAll('.tsa-table tbody tr').length") == 2, 'seznam se po auditu obnoví')
    br.js("document.querySelector('.tsa-out').innerHTML=''; document.querySelector('.tsa-link').click()", wait=1.2)
    ok(br.js("return document.querySelectorAll('.tsa-score').length") >= 4, 'otevření staršího auditu ze seznamu')
    br.size(375, 812, mobile=True); br.go(BASE + '/audit.html', wait=1.2); br.js("document.querySelectorAll('.tsa-link')[0].click()", wait=1.2)
    ok(br.js("return document.documentElement.scrollWidth <= innerWidth"), 'mobil: bez vodorovného posunu')
finally:
    if br: br.close()
    srv.terminate(); shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
