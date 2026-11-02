#!/usr/bin/env python3
"""Integrační test rozhraní administrace ts-design-studio: TsStudioApi přes php -S s falešným modelem (bez sítě) a public/ts-studio.js v Chromu.
Průchod: prázdný seznam → projekt ze šablony → náhled ve 3 šířkách → obsah a barvy → přijetí → kód → rozdíl → kontroly → návrat → export →
návrh s AI z formuláře → úprava v konverzaci → mobil. Poslední řádek: RESULT pass=N fail=M"""
import json, os, shutil, subprocess, sys, tempfile, time, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); COMP = os.path.abspath(os.path.join(HERE, '..')); LIB = os.path.abspath(os.path.join(COMP, '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT = 8291; BASE = 'http://localhost:%d' % PORT
P = F = 0


def ok(c, msg, detail=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + msg + (' | ' + str(detail)[:400] if detail else ''))


tmp = tempfile.mkdtemp(prefix='tsstudio-ui-'); W = os.path.join(tmp, 'web'); PR = os.path.join(tmp, 'private')
os.makedirs(os.path.join(W, 'vendor', 'ts')); os.makedirs(os.path.join(W, 'api')); os.makedirs(PR)
shutil.copytree(COMP, os.path.join(W, 'vendor', 'ts', 'ts-design-studio'), ignore=shutil.ignore_patterns('tests', 'tools'))
open(os.path.join(PR, 'studio.php'), 'w').write(r'''<?php
/* falešný model: odpověď podle druhu pokynu (návrh / úprava) */
$fake = function (array $m) {
    if (strpos($m[0]['content'], 'upravuješ web') !== false) return json_encode(['reply' => 'Zvětšila jsem logotyp.', 'css' => '.logotype{font-size:min(18vw,280px)}']);
    return json_encode(['content' => ['brand' => 'Salon Vlna', 'title' => 'Salon Vlna', 'phone' => '+420 731 555 210', 'phone_href' => 'tel:+420731555210'], 'tokens' => ['accent' => '#3d6b57'], 'notes' => 'Doplnit ceny.'], JSON_UNESCAPED_UNICODE);
};
return ['db' => ['dsn' => 'sqlite:' . __DIR__ . '/studio.sqlite'], 'ai' => ['provider' => 'fake', 'script' => [$fake], 'neuronsPerDay' => 5000]];
''')
open(os.path.join(W, 'api', 'studio.php'), 'w').write('''<?php
require __DIR__ . '/../vendor/ts/ts-design-studio/core/bootstrap.php';
$s = ts_design_studio(require dirname(__DIR__, 2) . '/private/studio.php');
(new TsStudioApi($s, ['uid' => 1, 'jobsDir' => dirname(__DIR__, 2) . '/private/ulohy', 'allowedOrigins' => ['%s']]))->serve();
''' % BASE)
open(os.path.join(W, 'index.html'), 'w').write('''<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Studio</title>
<link rel="stylesheet" href="/vendor/ts/ts-design-studio/public/ts-studio.css"><style>body{margin:0}main{max-width:1500px;margin:0 auto;padding:16px}</style></head>
<body><main><div id="ts-studio"></div></main><script>window.TS_STUDIO_CONFIG = { api: '/api/studio.php' };</script><script src="/vendor/ts/ts-design-studio/public/ts-studio.js"></script></body></html>''')
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True); time.sleep(1.2)

b = None
try:
    b = Browser(9361)
    b.size(1400, 1000)
    errs = []
    def J(code, wait=0): return b.js(code, wait)
    def until(expr, t=15):
        for _ in range(int(t / .3)):
            if J('!!(' + expr + ')'): return True
            time.sleep(.3)
        return False
    b.go(BASE + '/index.html', 1.5)
    ok(until("document.querySelector('.tss-main h1')") and 'Zatím žádný projekt' in J("document.querySelector('.tss-main').textContent"), 'prázdný seznam projektů')
    J("document.querySelector('[data-act=new]').click()", .5)
    ok(J("document.querySelector('[data-form=ai]').hidden===false && document.querySelector('[data-mode=ai]').getAttribute('aria-selected')==='true'"), 'nový projekt: výchozí návrh s AI (AI je nastavená)')
    J("document.querySelector('[data-mode=tpl]').click(); var f=document.querySelector('[data-form=tpl]'); f.name.value='Ruční salon'; f.template.value='salon'; f.querySelector('[type=submit]').click()", 1)
    ok(until("location.hash==='#/p/1' && document.querySelector('.tss-frame iframe') && document.querySelector('.tss-frame iframe').srcdoc.length>5000"), 'projekt ze šablony → editor s náhledem')
    ok(J("document.querySelector('.tss-frame iframe').getAttribute('sandbox')==='allow-scripts' && String(document.querySelector('.tss-frame iframe').contentDocument)==='null'"), 'náhled v sandboxu (origin null)')
    J("document.querySelector('[data-w=\"390\"]').click()", 1.2)
    ok(until("document.querySelector('.tss-frame iframe') && document.querySelector('.tss-frame iframe').width==390 && document.querySelector('[data-w=\"390\"]').getAttribute('aria-pressed')==='true'"), 'přepnutí náhledu na mobil (390 px)')
    ok(J("document.querySelectorAll('.tss-vers [data-ver]').length")==1 and 'aktuální' in J("document.querySelector('.tss-vers').textContent"), 'seznam verzí: v1 aktuální')
    # obsah a barvy
    J("document.querySelector('[data-tab=content]').click()", .5)
    ok(J("document.querySelectorAll('[data-slot]').length>30 && document.querySelectorAll('[data-token]').length>=10 && document.querySelectorAll('[data-list] .tss-row').length>5"), 'formulář obsahu: sloty, seznamy a tokeny ze schématu')
    J("var i=document.querySelector('[data-slot=brand]'); i.value='Salon Mořská <b>'; document.querySelector('[data-token=accent]').value='#225588'; var l=document.querySelector('[data-list=reviews]'); l.querySelector('[data-delrow]').click(); document.querySelector('.tss-cform .tss-msg').value='Název a akcent'; document.querySelector('.tss-cform [type=submit]').click()", 1.5)
    ok(until("document.querySelectorAll('.tss-vers [data-ver]').length===2 && document.querySelector('.tss-vhead') && document.querySelector('.tss-vhead').textContent.indexOf('Verze 2')>=0"), 'uložení obsahu → verze 2 (návrh)')
    ok(until("document.querySelector('.tss-frame iframe') && document.querySelector('.tss-frame iframe').srcdoc.indexOf('Salon Mořská &lt;b&gt;')>=0"), 'náhled verze 2 s novým názvem (escapovaným)')
    J("document.querySelector('[data-act=accept]').click()", 1.2)
    ok(until("document.querySelector('.tss-main').textContent.indexOf('přijatá verze 2')>=0"), 'přijetí verze 2')
    # kód
    J("document.querySelector('[data-tab=code]').click()", 1)
    ok(until("document.querySelector('.tss-src textarea') && document.querySelector('.tss-src textarea').value.indexOf('<!doctype html>')===0"), 'kód: index.html v editoru')
    ok(J("[...document.querySelectorAll('.tss-tree span')].some(s=>s.textContent.indexOf('.woff2')>0)"), 'strom souborů: písma jako binární (needitovatelná)')
    J("var t=document.querySelector('.tss-src textarea'); t.value=t.value.replace('</main>','<section class=\"sec\"><h2>Novinka</h2></section></main>'); t.dispatchEvent(new Event('input',{bubbles:true})); document.querySelector('.tss-src .tss-msg').value='Sekce novinka'; document.querySelector('[data-act=savecode]').click()", 1.5)
    ok(until("document.querySelectorAll('.tss-vers [data-ver]').length===3 && document.querySelector('.tss-vhead').textContent.indexOf('Sekce novinka')>=0"), 'uložení kódu → verze 3')
    J("document.querySelector('[data-tab=diff]').click()", 1)
    ok(until("document.querySelector('.tss-dfile pre .add') && document.querySelector('.tss-dfile').textContent.indexOf('Novinka')>=0"), 'rozdíl proti verzi 2: přidaný řádek zvýrazněný')
    J("document.querySelector('[data-tab=checks]').click()", .5)
    ok('Kontroly' in J("document.querySelector('.tss-pane').textContent") and 'K doplnění' in J("document.querySelector('.tss-pane').textContent"), 'záložka kontroly a k doplnění')
    # cizí skript → chyba, přijetí zakázané
    J("document.querySelector('[data-tab=code]').click()", 1)
    until("document.querySelector('.tss-src textarea') && document.querySelector('.tss-src textarea').value.length>100")
    J("var t=document.querySelector('.tss-src textarea'); t.value=t.value.replace('</main>','<script src=\"https://zly.example/x.js\"></script></main>'); t.dispatchEvent(new Event('input',{bubbles:true})); document.querySelector('[data-act=savecode]').click()", 1.5)
    ok(until("document.querySelectorAll('.tss-vers [data-ver]').length===4 && document.querySelector('[data-act=accept]') && document.querySelector('[data-act=accept]').disabled && document.querySelector('.tss-vhead .tss-err')"), 'verze s cizím skriptem: chyba kontrol, tlačítko Přijmout zakázané')
    J("document.querySelector('[data-act=reject]').click()", 1.2)
    ok(until("document.querySelector('.vs-rejected')"), 'zamítnutí verze 4')
    # návrat k verzi 1
    J("document.querySelector('[data-ver=\"1\"]').click()", 1)
    until("document.querySelector('[data-act=revert]')")
    J("document.querySelector('[data-act=revert]').click()", 1.5)
    ok(until("document.querySelectorAll('.tss-vers [data-ver]').length===5 && document.querySelector('.tss-vhead').textContent.indexOf('Návrat k verzi 1')>=0"), 'návrat k verzi 1 → verze 5')
    # export
    with urllib.request.urlopen(BASE + '/api/studio.php?r=export&pid=1&num=3') as r:
        z = r.read(); ok(r.headers.get('content-type') == 'application/zip' and z[:4] == b'PK\x03\x04' and 'rucni-salon-v3.zip' in r.headers.get('content-disposition', ''), 'export ZIP ke stažení')
    # stav
    J("var s=document.querySelector('[data-act=status]'); s.value='in_review'; s.dispatchEvent(new Event('change',{bubbles:true}))", 1)
    with urllib.request.urlopen(BASE + '/api/studio.php?r=project&pid=1') as r: ok(json.load(r)['project']['status'] == 'in_review', 'změna stavu konceptu')
    # návrh s AI z formuláře
    b.go(BASE + '/index.html#/', 1.5); until("document.querySelector('[data-act=new]')")
    J("document.querySelector('[data-act=new]').click()", .5)
    J("var f=document.querySelector('[data-form=ai]'); f.querySelector('[value=crm]').click(); f.name.value='Salon Vlna'; f.obor.value='kadeřnictví'; f.lead_phone.value='+420 731 555 210'; f.facts.value='Telefon: +420 731 555 210'", .5)
    ok(J("document.querySelector('[data-only=crm]').hidden===false && document.querySelector('[data-only=redesign]').hidden===true"), 'formulář AI: pole podle zdroje (lead z CRM)')
    J("document.querySelector('[data-form=ai] [type=submit]').click()", 1)
    ok(until("location.hash==='#/p/2' && document.querySelector('.tss-msgs') && document.querySelector('.tss-msgs').textContent.indexOf('Doplnit ceny')>=0", 25), 'návrh s AI → projekt 2, konverzace s poznámkou')
    ok('z CRM' in J("document.querySelector('.tss-main .tss-bar').textContent"), 'projekt 2 se zdrojem z CRM')
    J("var f=document.querySelector('.tss-ask'); f.text.value='Zvětši logotyp.'; f.querySelector('button').click()", 1)
    ok(until("document.querySelectorAll('.tss-vers [data-ver]').length===2 && document.querySelector('.tss-msgs').textContent.indexOf('Zvětšila jsem logotyp')>=0", 25), 'úprava v konverzaci → návrh verze 2 a odpověď')
    ok(until("document.querySelector('.tss-frame iframe') && document.querySelector('.tss-frame iframe').srcdoc.indexOf('min(18vw,280px)')>=0"), 'náhled návrhu s vrstvou CSS')
    ok('AI dnes:' in J("document.querySelector('.tss-u').textContent"), 'ukazatel spotřeby AI')
    b.shot(os.path.join(tmp, 'editor.jpg'), quality=70)
    # mobil
    b.size(390, 844, 2, True); b.go(BASE + '/index.html#/p/1', 2); until("document.querySelector('.tss-frame iframe')")
    ok(J("document.documentElement.scrollWidth<=window.innerWidth+1"), 'mobil: bez vodorovného posuvníku', J("document.documentElement.scrollWidth"))
    shutil.copy(os.path.join(tmp, 'editor.jpg'), os.path.join(LIB, 'reports', 'ts-design-studio-editor.jpg')) if os.path.isdir(os.path.join(LIB, 'reports')) else None
finally:
    if b: b.close()
    os.killpg(srv.pid, 15)
    shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
