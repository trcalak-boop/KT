#!/usr/bin/env python3
"""Test balíčku T8 na simulovaném hostingu: /web a brána /sub/nahledy jako na produkci (git HEAD + brána z balíčku T6), na ně nahraný balíček,
/private/studio s konfigurací z balíčku (adresy přepsané na localhost, e-mail jen do logu). Smyšlené CRM, falešná pošta, žádné skutečné služby.
Brána běží přes svůj index.php se zúženým open_basedir jako na hostingu. Poslední řádek: RESULT pass=N fail=M"""
import email, io, json, os, re, shutil, sqlite3, subprocess, sys, tarfile, tempfile, time, urllib.error, urllib.parse, urllib.request, uuid, zipfile
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek-t8'); PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT, GPORT = 8261, 8262; BASE = 'http://localhost:%d' % PORT; GBASE = 'http://127.0.0.1:%d' % GPORT
P = F = 0


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:500] if d else ''))


def overlay(src, dst):
    for dp, _, fs in os.walk(src):
        for f in fs:
            p = os.path.join(dp, f); t = os.path.join(dst, os.path.relpath(p, src)); os.makedirs(os.path.dirname(t), exist_ok=True); shutil.copy2(p, t)


root = tempfile.mkdtemp(prefix='t8-'); W = os.path.join(root, 'web'); G = os.path.join(root, 'sub', 'nahledy'); PS = os.path.join(root, 'private', 'studio'); DATA = os.path.join(root, 'crm-data')
tar = subprocess.run(['git', '-C', PROJ, 'archive', 'HEAD', 'web/admin', 'web/vendor', 'web/data', 'web/cs/ochrana-osobnich-udaju.html', 'web/privacy.html'], capture_output=True, check=True).stdout
with tarfile.open(fileobj=io.BytesIO(tar)) as t: t.extractall(root)
shutil.rmtree(os.path.join(W, 'admin', 'data'), ignore_errors=True)
os.makedirs(G); shutil.copytree(os.path.join(W, 'vendor', 'ts', 'ts-design-studio'), os.path.join(G, 'vendor', 'ts', 'ts-design-studio'), ignore=shutil.ignore_patterns('public'))
shutil.copy2(os.path.join(HERE, 'balicek-t6', 'k-nahrani', 'sub-nahledy', 'index.php'), os.path.join(G, 'index.php'))
os.makedirs(os.path.join(PS, 'data')); os.makedirs(os.path.join(DATA, 'relace'))
ok(json.load(open(os.path.join(W, 'vendor', 'ts', 'ts-design-studio', 'module.json')))['version'] == '0.11.0', 'výchozí stav jako produkce: knihovna 0.11.0')
overlay(os.path.join(PKG, 'k-nahrani', 'web'), W); overlay(os.path.join(PKG, 'k-nahrani', 'sub-nahledy'), G)
cfg = open(os.path.join(PKG, 'k-nahrani', 'private-studio', 'studio.php')).read()
ok("'ai' =>" not in cfg and "'uploadDir' => $private . '/data/navrhy'" in cfg and "'feedbackTo' => ['tasstudio@tasstudio.eu']" in cfg and 'neuronsPerDay' not in cfg, 'konfigurace: bez modelu, úložiště a příjemci', cfg[:200])
cfg = cfg.replace('https://nahledy.tasstudio.eu/', GBASE + '/').replace("'https://www.tasstudio.eu', 'https://tasstudio.eu'", "'%s'" % BASE).replace('https://www.tasstudio.eu/admin/#crm/lead', BASE + '/admin/#crm/lead')
open(os.path.join(PS, 'studio.php'), 'w').write(cfg)
open(os.path.join(PS, 'posta.php'), 'w').write("<?php return ['transport' => 'log', 'logFile' => __DIR__ . '/data/posta.log', 'from' => 'studio@tasstudio.example', 'fromName' => 'T&S Studio'];\n")
open(os.path.join(DATA, 'heslo.php'), 'w').write("<?php return ['hash' => 'x'];\n")
SID = 'a' * 26 + '01'
open(os.path.join(DATA, 'relace', 'sess_' + SID), 'w').write('csrf|s:9:"token-001";ok|b:1;t|i:%d;uid|i:1;' % int(time.time()))
env = dict(os.environ, TS_ADMIN_DATA=DATA)
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=env, start_new_session=True)
gsrv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', '127.0.0.1:%d' % GPORT, os.path.join(G, 'index.php')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True, cwd=G); time.sleep(1.4)


def http(url, data=None, headers=None, method=None):
    r = urllib.request.Request(url, data=data, headers=headers or {}, method=method)
    class NR(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k): return None
    try:
        with urllib.request.build_opener(NR).open(r, timeout=40) as x: return x.status, dict(x.headers), x.read()
    except urllib.error.HTTPError as e: return e.code, dict(e.headers), e.read()


def api(a, body=None, files=None, q=''):
    h = {'Cookie': 'tsadmin=' + SID, 'Origin': BASE}
    data = None
    if body is not None: h['Content-Type'] = 'application/json'; h['X-CSRF'] = 'token-001'; data = json.dumps(body).encode()
    if files is not None:
        bd = uuid.uuid4().hex; parts = []
        for k, v in files.items():
            if isinstance(v, bytes): parts.append(('--%s\r\nContent-Disposition: form-data; name="%s"; filename="kus"\r\nContent-Type: application/octet-stream\r\n\r\n' % (bd, k)).encode() + v + b'\r\n')
            else: parts.append(('--%s\r\nContent-Disposition: form-data; name="%s"\r\n\r\n%s\r\n' % (bd, k, v)).encode())
        data = b''.join(parts) + ('--%s--\r\n' % bd).encode(); h['Content-Type'] = 'multipart/form-data; boundary=' + bd; h['X-CSRF'] = 'token-001'
    s, hd, b = http(BASE + '/admin/api.php?a=' + a + q, data, h)
    try: return json.loads(b)
    except Exception: return {'ok': False, 'http': s, 'text': b[:300]}


def postalog():
    raw = open(os.path.join(PS, 'data', 'posta.log'), errors='replace').read(); out = [raw]
    for blk in re.findall(r'base64\r?\n\r?\n([A-Za-z0-9+/=\r\n]+)', raw):
        try: out.append(__import__('base64').b64decode(re.sub(r'\s', '', blk)).decode('utf-8', 'replace'))
        except Exception: pass
    return '\n'.join(out)


def zipb(files):
    b = io.BytesIO()
    with zipfile.ZipFile(b, 'w', zipfile.ZIP_DEFLATED) as z:
        for k, v in files.items(): z.writestr(k, v)
    return b.getvalue()


try:
    s, h, b = http(BASE + '/admin/studio.php', headers={'Cookie': 'tsadmin=' + SID}); ok(s == 303 and h.get('Location') == './#crm/leady', 'stará stránka Studia vede do CRM', (s, h.get('Location')))
    s, h, b = http(BASE + '/admin/studio-api.php?r=list'); ok(s == 410 and b'gone' in b, 'API Studia je zrušené (410)')
    s, h, b = http(BASE + '/admin/', headers={'Cookie': 'tsadmin=' + SID}); ok(s == 200 and b'studio.php' not in b and b'crm.js' in b, 'v administraci už odkaz na Studio není')
    api('crm_init')
    db = sqlite3.connect(os.path.join(DATA, 'crm.sqlite')); db.execute("UPDATE users SET name = 'Adam Admin', email = 'adam@tasstudio.example', role = 'admin', active = 1 WHERE id = 1"); db.commit(); db.close()
    lead = api('crm_lead_ulozit', {'name': 'Testovací klient', 'email': 'test@example.test', 'priority': 'standardni', 'assigned_user_id': 1, 'outreach_reason': 'Test'})['id']
    z = zipb({'index.html': '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Test</title><link rel="stylesheet" href="a.css"></head><body><h1>Ahoj</h1><h2>Služby</h2></body></html>', 'a.css': 'body{color:#123456}'})
    r = api('crm_navrh_nahrat', files={'lead_id': str(lead), 'project': '0', 'i': '0', 'n': '1', 'nazev': 'test.zip', 'kus': z})
    ok(r.get('ok') and r.get('prijato'), 'nahrání návrhu na produkčním rozložení', r)
    nv = api('crm_navrhy', q='&id=%s' % lead)['navrhy'][0]; pid = nv['id']; prev = nv['versions'][0]['preview']
    s, h, b = http(prev); ok(s == 200 and b'Ahoj' in b and BASE in h.get('Content-Security-Policy', ''), 'interní náhled přes bránu (index.php, open_basedir)', (s, h.get('Content-Security-Policy')))
    d = api('crm_navrh_pripravit', {'project': pid, 'num': 1})['dorucky']
    r = api('crm_navrh_odeslat', {'id': d['id'], 'to_email': 'test@example.test', 'to_name': 'Test', 'subject': d['subject'], 'body': d['body'], 'days': 14, 'potvrzeni': 'test@example.test', 'idem': 'abcdefgh123'})
    ok(r.get('ok'), 'odeslání klientovi', r)
    log = postalog(); link = re.search(re.escape(GBASE) + r'/[A-Za-z0-9_-]{32}/', log).group(0)
    s, h, b = http(link); ok(s == 200 and b'pripominka' in b and 'Set-Cookie' not in h, 'stránka klienta')
    s, h, b = http(link + 'pripominka', urllib.parse.urlencode({'text': 'Větší nadpis', 'jmeno': 'Test'}).encode(), {'Origin': GBASE, 'Content-Type': 'application/x-www-form-urlencoded'})
    ok(s == 303 and 'stav=ok' in h.get('Location', ''), 'připomínka přes bránu', (s, h.get('Location')))
    log = postalog()
    ok('tasstudio@tasstudio.eu' in log and 'adam@tasstudio.example' in log and 'Větší nadpis' in log, 'upozornění e-mailem z brány (posta.php) nám i přiřazenému')
    api('crm_odznak'); L = api('crm_lead', q='&id=%s' % lead)
    ok(any(u['title'] == 'Reagovat na připomínky k návrhu' for u in L['ukoly']), 'úkol v CRM')
    pr = open(os.path.join(W, 'cs', 'ochrana-osobnich-udaju.html'), encoding='utf-8').read()
    ok('připomínky nebo návrh schválit' in pr and 'jazykový model Cloudflare, jak je popsáno výše' not in pr and pr.count('data-ai-studio') == 2, 'zásady: návrhy s připomínkami, bez předání do USA')
    pk = open(os.path.join(PKG, 'k-nahrani', 'web', 'cs', 'ochrana-osobnich-udaju.html'), encoding='utf-8').read()
    ok('reference.js?v=954f587444' in pk and 'ts.js?v=tnvqn4baf' in pk, 'verze dat v balíčku podle produkce (simulace si je po zápisu dat přeznačí sama)')
    # návrat
    overlay(os.path.join(PKG, 'navrat-pri-problemu', 'web'), W); overlay(os.path.join(PKG, 'navrat-pri-problemu', 'sub-nahledy'), G)
    shutil.copy2(os.path.join(PKG, 'navrat-pri-problemu', 'private-studio', 'studio.php'), os.path.join(root, 'kvota.php'))
    s, h, b = http(BASE + '/admin/', headers={'Cookie': 'tsadmin=' + SID}); ok(s == 200 and b'studio.php' in b, 'návrat: administrace jako dřív')
    s, h, b = http(link); ok(s in (200, 404) and s != 500, 'návrat: brána nespadne', s)
finally:
    os.killpg(srv.pid, 15); os.killpg(gsrv.pid, 15)
    shutil.rmtree(root, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
