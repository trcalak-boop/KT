#!/usr/bin/env python3
"""Test balíčku 6.4b (7-balicek-studio/oprava-6.4) na simulovaném hostingu: kořen/sub/test (stav po 6.3 + balíček), kořen/sub/nahledy (brána),
kořen/private/studio-test (konfigurace z balíčku; přístupová cookie a původ upravené pro localhost, e-mail jen do posta.log).
Ověřuje: role na stagingu, schválení nic neodesílá, doručení s potvrzením, odkaz přes bránu na druhém originu, odvolání, ruční cesta, ochranu brány.
Poslední řádek: RESULT pass=N fail=M"""
import hashlib, json, os, re, shutil, subprocess, sys, tempfile, time, urllib.error, urllib.request
import importlib.util
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, '7-balicek-studio', 'oprava-6.4')
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
spec = importlib.util.spec_from_file_location('b64', os.path.join(HERE, 'sestavit_balicek_studio_64.py')); b64 = importlib.util.module_from_spec(spec); spec.loader.exec_module(b64)
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT, GPORT = 8231, 8232; BASE = 'http://localhost:%d' % PORT; GBASE = 'http://127.0.0.1:%d' % GPORT
P = F = 0; COOKIE = 'studio-lokalni-test-64'


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


cfg0 = open(os.path.join(PKG, 'studio-test', 'studio.php'), encoding='utf-8').read()
ok(re.search(r"'deliverySecret' => '[A-Za-z0-9_-]{40,}'", cfg0) and "'previewBase' => 'https://nahledy.tasstudio.eu/'" in cfg0 and "empty($posta['redirectTo'])" in cfg0 and 'smtp_pass' not in cfg0, 'konfigurace: tajný klíč doručení, náhledy na subdoméně, bez testovací adresy nikdy ven, žádné heslo')
ok("'redirectTo'" in open(os.path.join(PKG, 'studio-test', 'posta.example.php')).read(), 'šablona posta.example.php s povinným redirectTo')
ok(os.path.exists(os.path.join(PKG, 'nahledy', 'vendor', '.htaccess')) and 'RewriteRule ^ index.php' in open(os.path.join(PKG, 'nahledy', '.htaccess')).read(), 'brána: přesměrování na index.php, vendor zakázaný')

tmp = tempfile.mkdtemp(prefix='studio-64-'); R = os.path.join(tmp, 'koren'); SUB = os.path.join(R, 'sub', 'test'); NAH = os.path.join(R, 'sub', 'nahledy'); PS = os.path.join(R, 'private', 'studio-test'); PA = os.path.join(R, 'private', 'ai-test')
for v in b64.VRSTVY + [os.path.join(PKG, 'test')]: shutil.copytree(v, SUB, dirs_exist_ok=True)
shutil.copytree(os.path.join(PKG, 'nahledy'), NAH); shutil.copytree(os.path.join(PKG, 'studio-test'), PS); os.makedirs(PA)
open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY-TOKEN\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0123456789abcdef0123456789abcdef\n')
c = re.sub(r"'hash' => '[0-9a-f]{64}'", "'hash' => '%s'" % hashlib.sha256(COOKIE.encode()).hexdigest(), cfg0)
c = c.replace("'previewBase' => 'https://nahledy.tasstudio.eu/'", "'previewBase' => '%s/'" % GBASE).replace("'allowedOrigins' => ['https://test.tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
c = c.replace("'neuronsPerDay' => 2500]", "'neuronsPerDay' => 2500, 'baseUrl' => 'http://127.0.0.1:9/nikam']")
open(os.path.join(PS, 'studio.php'), 'w').write(c); cname = re.search(r"'cookie' => '([^']+)'", c).group(1)
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo json_encode([$s["studio"]["mail"]["transport"], $s["studio"]["ai"]["baseUrl"]]);', os.path.join(PS, 'studio.php')], capture_output=True, text=True).stdout
ok(json.loads(chk)[0] == 'log' and 'cloudflare' not in chk, 'pojistka: e-mail jen do logu, model nikam', chk)
for port in (PORT, GPORT):
    if subprocess.run(['lsof', '-nP', '-iTCP:%d' % port, '-sTCP:LISTEN'], capture_output=True).stdout: sys.exit('port %d je obsazený' % port)
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', SUB], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
gsrv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', '127.0.0.1:%d' % GPORT, '-t', NAH, os.path.join(NAH, 'index.php')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True); time.sleep(1.2)


def req(path, body=None, role='admin', cookie=True, base=BASE):
    h = {'Content-Type': 'application/json', 'Origin': BASE}
    if cookie: h['Cookie'] = '%s=%s; studio_role=%s' % (cname, COOKIE, role)
    r = urllib.request.Request(base + path, data=json.dumps(body).encode() if body is not None else None, headers=h)
    try:
        with urllib.request.urlopen(r, timeout=30) as x: return x.status, x.read().decode('utf-8', 'replace'), dict(x.headers)
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8', 'replace'), dict(e.headers)
A = lambda r, b=None, role='admin': (lambda s, t, h: (s, json.loads(t)))(*req('/api/studio/studio.php?r=' + r, b, role))
LOG = os.path.join(PS, 'data', 'posta.log')

b = None
try:
    s, t, _ = req('/studio.php'); ok(s == 200 and 'Testovací uživatel' in t and 'jen do souboru posta.log' in t, 'stránka: přepínač testovacích rolí, režim e-mailu')
    s, o = A('templates', role='sales'); ok(o['role'] == 'sales' and 'deliver' in o['actions'] and 'edit' not in o['actions'] and o['delivery'], 'role sales: doručení ano, úpravy ne')
    s, o = A('create', {'name': 'Salon Vlna (staging)', 'template': 'salon'}, role='designer'); pid = o['id']; ok(s == 201, 'designer založí projekt')
    ok(A('create', {'name': 'X', 'template': 'salon'}, role='sales')[0] == 403 and A('create', {'name': 'X', 'template': 'salon'}, role='viewer')[0] == 403, 'sales a viewer nemohou zakládat projekty (403)')
    ok(A('status', {'pid': pid, 'status': 'approved_internal'}, role='sales')[0] == 403, 'sales nemůže schválit (403)')
    ok(A('status', {'pid': pid, 'status': 'approved_internal'}, role='designer')[0] == 200, 'designer schválí interně')
    ok(not os.path.exists(LOG) and A('deliveries&pid=%d' % pid, role='sales')[1]['deliveries'] == [], 'scénář 9: po schválení žádný e-mail ani doručení')
    ok(A('delivery-prepare', {'pid': pid, 'num': 1}, role='designer')[0] == 403, 'designer nemůže připravit sdílení (403)')
    s, o = A('delivery-prepare', {'pid': pid, 'num': 1, 'days': 14}, role='sales'); did = o['delivery']['id']; ok(s == 201 and o['delivery']['status'] == 'prepared', 'sales připraví sdílení')
    s, o = A('delivery-draft', {'id': did, 'to_email': 'klient@example.cz', 'subject': 'Návrh webu', 'body': 'Dobrý den,\n\nnávrh: {odkaz}\nPlatí do {datum}.\n\n{jmeno}'}, role='sales')
    ok(s == 200 and not os.path.exists(LOG), 'scénář 10: připravený e-mail se sám neodeslal')
    ok(A('delivery-send', {'id': did, 'confirm': 'spatne@example.cz', 'idem': 'stg-0000001'}, role='sales')[0] == 400 and not os.path.exists(LOG), 'bez správného potvrzení se neodešle')
    s, o = A('delivery-send', {'id': did, 'confirm': 'klient@example.cz', 'idem': 'stg-0000002'}, role='sales')
    log = open(LOG).read() if os.path.exists(LOG) else ''
    ok(s == 200 and o['delivery']['status'] == 'sent' and 'To: <klient@example.cz>' in log, 'odesláno (TsMailer, transport log)', o)
    import base64, email
    msg = email.message_from_string(log.split('\r\n\r\n\r\n')[0]); body = ''.join(p.get_payload(decode=True).decode() for p in msg.walk() if p.get_content_type() == 'text/plain')
    link = re.search(re.escape(GBASE) + r'/[A-Za-z0-9_-]{32}/', body).group(0)
    ok('Testovací sales (staging)' in body, 'podpis testovacího pracovníka v e-mailu')
    s, t, h = req(link[len(GBASE):], cookie=False, base=GBASE)
    ok(s == 200 and 'neveřejný odkaz platí do' in t and h.get('X-Robots-Tag', '').startswith('noindex') and 'Set-Cookie' not in h, 'odkaz z e-mailu: náhled přes bránu na druhém originu, noindex, bez cookies', (s, h))
    s, t, h = req(link[len(GBASE):] + 'assets/styles.css', cookie=False, base=GBASE); ok(s == 200 and '/*@tokens*/' in t, 'brána: styly verze')
    s, t, h = req('/vendor/ts/ts-design-studio/core/bootstrap.php', cookie=False, base=GBASE); ok(s == 404 and '<?php' not in t, 'brána: zdrojové kódy knihovny nevydá')
    s, t, h = req('/robots.txt', cookie=False, base=GBASE); ok(s == 200 and 'Disallow: /' in t, 'brána: robots.txt')
    s, t, h = req('/' + 'A' * 32 + '/', cookie=False, base=GBASE); ok(s == 404 and 'Salon' not in t, 'brána: neznámý token → neutrální 404')
    ok(A('delivery-revoke', {'id': did}, role='designer')[0] == 403, 'designer nemůže odvolat (403)')
    ok(A('delivery-revoke', {'id': did}, role='sales')[0] == 200 and req(link[len(GBASE):], cookie=False, base=GBASE)[0] == 404, 'scénář 11: odvolaný odkaz vrací 404')
    s, o = A('delivery-prepare', {'pid': pid, 'num': 1, 'days': 7}, role='admin'); s, o = A('delivery-link', {'id': o['delivery']['id']}, role='admin')
    ok(s == 200 and req(o['delivery']['url'][len(GBASE):], cookie=False, base=GBASE)[0] == 200 and log.count('To: <') == 1, 'ruční cesta: zkopírovaný odkaz funguje, e-mail se neposílá')
    s, o = A('project&pid=%d' % pid, role='viewer'); acts = [x['action'] for x in o['log']]
    ok(all(a in acts for a in ('status', 'delivery.prepare', 'delivery.draft', 'delivery.send', 'delivery.revoke', 'delivery.copy_link')), 'záznam všech kroků doručení', acts)
    # rozhraní v Chromu: přepínač role mění dostupné akce
    b = Browser(9381); b.call('Network.enable'); b.call('Network.setCookie', name=cname, value=COOKIE, url=BASE); b.call('Network.setCookie', name='studio_role', value='viewer', url=BASE)
    b.size(1400, 1000); b.go(BASE + '/studio.php#/p/%d' % pid, 3)
    for _ in range(30):
        if b.js("!!document.querySelector('[data-tab=deliver]')"): break
        time.sleep(.3)
    b.js("document.querySelector('[data-tab=deliver]').click()", 1.5)
    ok(b.js("document.querySelector('#stg-role').value==='viewer' && !document.querySelector('[data-act=new],[data-dact=send],[data-dact=revoke],[data-act=prepare]') && document.querySelectorAll('.tss-dcard').length===2"), 'viewer v rozhraní: přehled doručení bez akcí')
    b.shot(os.path.join(HERE, '7-balicek-studio', 'lokalni-test-studio-64.jpg'), quality=70)
finally:
    if b: b.close()
    os.killpg(srv.pid, 15); os.killpg(gsrv.pid, 15)
print('RESULT pass=%d fail=%d' % (P, F))
