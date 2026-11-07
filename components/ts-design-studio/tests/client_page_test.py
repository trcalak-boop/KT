#!/usr/bin/env python3
"""Stránka klienta u nahraného návrhu ve skutečném Chromu přes php -S (bez sítě, e-mail do souboru): návrh v sandboxu běží včetně skriptů, písem, obrázků a podstránek,
nedostane se k panelu, cookies ani na síť; připomínka a schválení formulářem bez JavaScriptu; telefon a tmavý režim. Poslední řádek: RESULT pass=N fail=M"""
import json, os, shutil, subprocess, sys, tempfile, time
HERE = os.path.dirname(os.path.abspath(__file__)); COMP = os.path.abspath(os.path.join(HERE, '..')); LIB = os.path.abspath(os.path.join(COMP, '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT = 8296; BASE = 'http://127.0.0.1:%d' % PORT
SHOTS = os.environ.get('TS_UI_SHOTS', '')
P = F = 0


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:500] if d else ''))


tmp = tempfile.mkdtemp(prefix='tsclient-'); D = os.path.join(tmp, 'navrh'); os.makedirs(os.path.join(D, 'img')); os.makedirs(os.path.join(D, 'fonts'))
FONT = next(f for f in os.listdir(os.path.join(COMP, 'kit', 'fonts')) if f.startswith('figtree') and f.endswith('.woff2'))
shutil.copy(os.path.join(COMP, 'kit', 'fonts', FONT), os.path.join(D, 'fonts', 'moje.woff2'))
PNG = bytes.fromhex('89504e470d0a1a0a0000000d4948445200000001000000010806000000' + '1f15c4890000000d49444154789c636060606000000005000160b5e2a10000000049454e44ae426082')
open(os.path.join(D, 'img', 'a.png'), 'wb').write(PNG)
open(os.path.join(D, 'styl.css'), 'w').write('@font-face{font-family:"Moje";src:url("fonts/moje.woff2") format("woff2")}body{font-family:"Moje",sans-serif;background:#fff8ea;color:#16324f}h1{font-size:64px}')
open(os.path.join(D, 'index.html'), 'w').write('''<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Návrh</title><link rel="stylesheet" href="styl.css"><style>section{padding:20px}</style></head>
<body><h1 id="n">Pekárna</h1><section><h2>Nabídka</h2><img id="i" src="img/a.png" alt="a" width="50" height="50"></section><section aria-label="Galerie"><h2>Kontakt</h2><a id="dal" href="dalsi.html">Další stránka</a></section>
<script>
var R = {};
R.skript = true;
try { R.parent = !!parent.document.body; } catch (e) { R.parent = false; }
try { document.cookie = 'x=1'; R.cookie = document.cookie; } catch (e) { R.cookie = 'blok'; }
try { localStorage.setItem('x', '1'); R.ls = true; } catch (e) { R.ls = false; }
R.origin = String(self.origin);
addEventListener('hashchange', function () { document.getElementById('dal').click(); });
fetch('/').then(function () { R.fetch = true; }, function () { R.fetch = false; }).then(function () { return document.fonts.load('16px Moje'); }).then(function (f) { R.font = f.length; R.img = document.getElementById('i').naturalWidth; parent.postMessage(JSON.stringify(R), '*'); });
</script></body></html>''')
open(os.path.join(D, 'dalsi.html'), 'w').write('<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Další</title></head><body><h1>Podstránka</h1><script>parent.postMessage("podstranka","*")</script></body></html>')
ROUTER = os.path.join(tmp, 'router.php')
open(os.path.join(tmp, 'cfg.php'), 'w').write(r'''<?php
$mail = new class { public function send($to, $s, $t, $h, $r = null, $n = '') { file_put_contents(__DIR__ . '/posta.log', json_encode([$to, $s, $t]) . "\n", FILE_APPEND); return ['ok' => true]; } };
return ['db' => ['dsn' => 'sqlite:' . __DIR__ . '/s.sqlite'], 'uploadDir' => __DIR__ . '/blob', 'deliverySecret' => str_repeat('s', 40), 'previewBase' => 'http://127.0.0.1:%d/', 'mailer' => $mail, 'feedbackTo' => ['nam@test.test']];
''' % PORT)
open(os.path.join(tmp, 'init.php'), 'w').write('''<?php
require %r . '/core/bootstrap.php'; $s = ts_design_studio(require %r . '/cfg.php');
$f = []; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(%r, FilesystemIterator::SKIP_DOTS)); foreach ($it as $x) if ($x->isFile()) $f[substr($x->getPathname(), strlen(%r) + 1)] = file_get_contents($x->getPathname());
$r = $s->uploadDesign(TsStudioZip::build($f), 'n.zip', 1, ['name' => 'Pekárna Veveří', 'lead_ref' => '7']);
$d = $s->prepareDelivery($r['id'], $r['num'], 1); $s->draftDelivery($d['id'], 1, ['to_email' => 'jana@example.test', 'to_name' => 'Jana Testová', 'subject' => 'Návrh', 'body' => 'Viz {odkaz}', 'days' => 14]);
$l = $s->copyLink($d['id'], 1); echo json_encode(['url' => $l['url'], 'checks' => $r['checks'], 'accepted' => $r['accepted']]);
''' % (COMP, tmp, D, D))
open(ROUTER, 'w').write('''<?php
require %r . '/core/bootstrap.php';
(new TsStudioPreviewGate(ts_design_studio(require %r . '/cfg.php'), ['failDir' => %r . '/brana', 'contact' => 'tasstudio.eu']))->serve();
''' % (COMP, tmp, tmp))
j = json.loads(subprocess.run([PHP, os.path.join(tmp, 'init.php')], capture_output=True, text=True).stdout)
ok(j['accepted'], 'návrh je přijatý', j['checks'])
URL = j['url']
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', '127.0.0.1:%d' % PORT, ROUTER], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True); time.sleep(1.2)
b = None
try:
    b = Browser(9373)
    b.size(1280, 860); b.scheme(False)
    b.call('Page.addScriptToEvaluateOnNewDocument', source="window.__m=[];addEventListener('message',function(e){window.__m.push(e.data)});")
    b.go(URL, 2.5)
    ok(b.js("return document.querySelector('iframe.dsg').getAttribute('sandbox')") == 'allow-scripts', 'návrh v sandboxu')
    m = b.js("return window.__m")
    R = json.loads(m[0]) if m and m[0].startswith('{') else {}
    ok(R.get('skript') is True, 'skript návrhu běží', m)
    ok(R.get('parent') is False, 'návrh se nedostane k rodičovské stránce', R)
    ok(R.get('origin') == 'null', 'návrh má neprůhledný původ', R)
    ok(R.get('cookie') in ('blok', '') and R.get('ls') is False, 'návrh nemá cookies ani úložiště', R)
    ok(R.get('fetch') is False, 'návrh nesmí na síť', R)
    ok(R.get('font', 0) >= 1, 'písmo návrhu se načte', R)
    ok(R.get('img') == 1, 'obrázek návrhu se načte', R)
    ok(b.js("return document.querySelector('select[name=cast]').textContent.indexOf('Nabídka')>=0 && document.querySelector('select[name=cast]').textContent.indexOf('Galerie')>=0"), 'výběr části z návrhu')
    ok(b.js("return document.querySelector('input[name=jmeno]').value") == 'Jana Testová', 'jméno předvyplněné')
    ok(b.js("return document.cookie") == '', 'stránka bez cookies')
    ok(b.js("return document.documentElement.scrollWidth<=innerWidth && document.querySelector('iframe.dsg').getBoundingClientRect().height>500 && document.querySelector('.panel').getBoundingClientRect().width>300"), 'rozložení na počítači')
    if SHOTS: b.shot(os.path.join(SHOTS, 'klient.jpg'), quality=70)
    # odeslání bez JavaScriptu stránky
    b.js("var f=document.querySelector('form[action$=pripominka]'); f.cast.value='Nabídka'; f.text.value='Prosím větší nadpis <b>tučně</b>'; f.querySelector('button').click()", wait=1.5)
    ok('stav=ok' in b.js("return location.href") and b.js("return document.querySelector('.msg.ok') && document.querySelector('.prev').textContent.indexOf('Prosím větší nadpis <b>tučně</b>')>=0"), 'připomínka se uloží a klient ji vidí', b.js("return location.href"))
    ok(b.js("return !document.querySelector('.prev b')"), 'text připomínky je escapovaný')
    mail = open(os.path.join(tmp, 'posta.log')).read().splitlines()
    ok(len(mail) == 1 and 'Pekárna Veveří' in json.loads(mail[0])[1], 'upozornění nám odešlo', mail)
    b.js("document.querySelector('.apr button').click()", wait=1.5)
    ok('stav=schvaleno' in b.js("return location.href") and b.js("return !document.querySelector('.apr') && document.querySelector('.panel').textContent.indexOf('Návrh jste schválili')>=0"), 'schválení')
    ok(len(open(os.path.join(tmp, 'posta.log')).read().splitlines()) == 2, 'upozornění na schválení')
    # podstránka v rámu
    b.go(URL, 2)
    b.js("window.__m=[]")
    b.js("var f=document.querySelector('iframe.dsg'); f.src=f.getAttribute('src')+'#go'", wait=1.5)
    ok('podstranka' in (b.js("return window.__m") or []), 'odkaz na podstránku funguje v rámu', b.js("return window.__m"))
    # telefon, tmavý režim
    b.size(390, 844, 2, True); b.scheme(True); b.go(URL, 2)
    ok(b.js("return document.documentElement.scrollWidth<=innerWidth"), 'telefon bez vodorovného posuvníku', b.js("return document.documentElement.scrollWidth"))
    ok(b.js("return !!document.querySelector('.jump') && getComputedStyle(document.querySelector('.jump')).display!=='none' && document.querySelector('iframe.dsg').getBoundingClientRect().width>=380"), 'telefon: návrh na šířku a odkaz na připomínky')
    ok(b.js("return [...document.querySelectorAll('.panel button,.panel input:not([name=web]),.panel textarea,.panel select')].every(e=>e.getBoundingClientRect().height>=44)"), 'telefon: ovládací prvky alespoň 44 px')
    if SHOTS: b.shot(os.path.join(SHOTS, 'klient-mobil.jpg'), full=True, quality=60)
finally:
    if b: b.close()
    os.killpg(srv.pid, 15); shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
