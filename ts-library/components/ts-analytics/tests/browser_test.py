#!/usr/bin/env python3
"""Integrační test ts-analytics: měřicí skript v Chromu proti vstupnímu bodu přes php -S (bez sítě).
Bez start() nic neposílá ani neukládá cookies; start() nastaví ts_v a ts_s a pošle zobrazení (sendBeacon), událost, UTM, odkaz;
druhá stránka = stejný návštěvník i návštěva; stop() smaže cookies a dál nic neposílá; přehled v HTML.
Poslední řádek: RESULT pass=N fail=M"""
import json, os, shutil, subprocess, sys, tempfile, time
HERE = os.path.dirname(os.path.abspath(__file__)); COMP = os.path.abspath(os.path.join(HERE, '..')); LIB = os.path.abspath(os.path.join(COMP, '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT = 8291; BASE = 'http://localhost:%d' % PORT
P = F = 0


def ok(c, msg, detail=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + msg + (' | ' + str(detail)[:400] if detail else ''))


tmp = tempfile.mkdtemp(prefix='tsan-'); W = os.path.join(tmp, 'web'); PR = os.path.join(tmp, 'private')
os.makedirs(os.path.join(W, 'vendor', 'ts')); os.makedirs(os.path.join(W, 'api', 'stat')); os.makedirs(os.path.join(PR, 'data'))
shutil.copytree(COMP, os.path.join(W, 'vendor', 'ts', 'ts-analytics'), ignore=shutil.ignore_patterns('tests'))
open(os.path.join(PR, 'analytics.php'), 'w').write("""<?php
return ['timezone' => 'Europe/Prague', 'db' => ['dsn' => 'sqlite:' . __DIR__ . '/data/an.sqlite'], 'salt' => 'x', 'blockBots' => false];   /* test běží v bezhlavém Chromu */
""")
open(os.path.join(W, 'api', 'stat', 'hit.php'), 'w').write("""<?php
require __DIR__ . '/../../vendor/ts/ts-analytics/core/bootstrap.php';
ts_analytics(require dirname(__DIR__, 3) . '/private/analytics.php')->serve();
""")
open(os.path.join(W, 'prehled.php'), 'w').write("""<?php
require __DIR__ . '/vendor/ts/ts-analytics/core/bootstrap.php';
$a = ts_analytics(require dirname(__DIR__) . '/private/analytics.php');
echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Přehled</title><link rel="stylesheet" href="/vendor/ts/ts-analytics/public/ts-analytics-report.css"></head><body style="margin:24px">';
echo TsAnalyticsReport::html($a->report(date('Y-m-d', strtotime('-6 days')), date('Y-m-d')), ['events' => ['poptavka' => 'Odeslaná poptávka']]) . '</body></html>';
""")
PAGE = '''<!doctype html><html lang="%s"><head><meta charset="utf-8"><title>T</title><script src="/vendor/ts/ts-analytics/public/ts-analytics.js"></script></head>
<body><h1>%s</h1><a id="dal" href="/b.html">dál</a></body></html>'''
open(os.path.join(W, 'a.html'), 'w').write(PAGE % ('cs', 'A'))
open(os.path.join(W, 'b.html'), 'w').write(PAGE % ('cs', 'B'))
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)
db = os.path.join(PR, 'data', 'an.sqlite')


def rows():
    out = subprocess.run([PHP, '-r', '$d=new PDO("sqlite:' + db + '"); echo json_encode($d->query("SELECT * FROM ts_an_hits ORDER BY id")->fetchAll(PDO::FETCH_ASSOC));'], capture_output=True, text=True).stdout
    try: return json.loads(out)
    except Exception: return []


try:
    br = Browser(9367)
    try:
        br.size(390, 844)
        br.go(BASE + '/a.html?utm_source=Newsletter&utm_medium=email&utm_campaign=Podzim', wait=1)
        ok(br.js("return document.cookie") == '' and not os.path.exists(db), 'bez start(): žádné cookies, nic se neposlalo')
        br.js("TsAnalytics.start({ api: '/api/stat/hit.php' })", wait=1)
        ck = br.js("return document.cookie") or ''
        r = rows()
        ok('ts_v=' in ck and 'ts_s=' in ck, 'start(): cookies ts_v a ts_s', ck)
        ok(len(r) == 1 and r[0]['kind'] == 'pv' and r[0]['path'] == '/a.html' and r[0]['src'] == 'newsletter' and r[0]['camp'] == 'Podzim' and r[0]['newv'] == 1 and r[0]['dev'] == 'm' and r[0]['lang'] == 'cs', 'start(): zobrazení s UTM, nový návštěvník, telefon, jazyk', r)
        br.js("TsAnalytics.event('poptavka'); TsAnalytics.event('Zlá událost')", wait=1)
        r = rows()
        ok(len(r) == 2 and r[1]['ev'] == 'poptavka', 'událost se pošle, neplatný název ne', r[-1:])
        vid, sid = r[0]['vid'], r[0]['sid']
        br.js("document.getElementById('dal').click()", wait=1.2)
        br.js("TsAnalytics.start({ api: '/api/stat/hit.php' })", wait=1)
        r = rows()
        ok(len(r) == 3 and r[2]['path'] == '/b.html' and r[2]['vid'] == vid and r[2]['sid'] == sid and r[2]['newv'] == 0 and r[2]['ref'] == '', 'další stránka: stejný návštěvník i návštěva, ne nový, vlastní web není zdroj', r[-1:])
        br.js("TsAnalytics.stop(); TsAnalytics.event('poptavka')", wait=1)
        ok(br.js("return document.cookie") == '' and len(rows()) == 3 and br.js("return TsAnalytics.running()") is False, 'stop(): cookies smazané, dál se nic neposílá')
        br.size(1280, 900); br.go(BASE + '/prehled.php', wait=1)
        ok(br.js("return document.querySelectorAll('.tsan-card').length === 6 && !!document.querySelector('.tsan-chart svg polyline') && document.body.textContent.indexOf('Odeslaná poptávka') > 0"), 'přehled: karty, graf, událost s názvem')
        ok(br.js("return document.documentElement.scrollWidth <= innerWidth"), 'přehled bez vodorovného posuvníku (počítač)')
        br.size(390, 844); br.go(BASE + '/prehled.php', wait=1)
        ok(br.js("return document.documentElement.scrollWidth <= innerWidth"), 'přehled bez vodorovného posuvníku (telefon)')
    finally:
        br.close()
finally:
    srv.terminate(); shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
