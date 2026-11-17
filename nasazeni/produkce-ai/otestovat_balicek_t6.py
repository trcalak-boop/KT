#!/usr/bin/env python3
"""Test balíčku T6 na simulovaném hostingu: kořen/web = web projektu (bez admin/data), kořen/private/studio + kořen/sub/nahledy z balíčku.
Smyšlené CRM (TS_ADMIN_DATA → dočasná složka, 2 uživatelé admin + 1 viewer, 1 lead), přihlášení = připravená relace, e-mail jen do logu (TsMailer transport log),
model nikam. Žádná produkční data ani služby. Poslední řádek: RESULT pass=N fail=M"""
import json, os, re, shutil, sqlite3, subprocess, sys, tempfile, time, urllib.error, urllib.request, email
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek-t6'); PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT, GPORT = 8241, 8242; BASE = 'http://localhost:%d' % PORT; GBASE = 'http://127.0.0.1:%d' % GPORT
P = F = 0


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


KW = os.path.join(PKG, 'k-nahrani', 'web'); KS = os.path.join(PKG, 'k-nahrani', 'private-studio'); KN = os.path.join(PKG, 'k-nahrani', 'sub-nahledy')
files = open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'soubory-web.txt')).read().split()
ok(sorted(f for f in files if f.startswith('admin/')) == ['admin/index.php', 'admin/studio-api.php', 'admin/studio.php'] and not any(f.startswith('admin/data') for f in files), 'balíček: z administrace jen index.php a studio*.php, nic z admin/data')
ok(len([f for f in files if re.search(r'(privacy|ochrana-osobnich-udaju|datenschutz)\.html$', f)]) == 18 and not os.path.exists(os.path.join(KW, 'ts-components.lock.json')), 'balíček: 18 stránek zásad, bez zámku komponent')
cfg0 = open(os.path.join(KS, 'studio.php')).read()
ok("'neuronsPerDay' => 2000" in cfg0 and "'enabled' => true" in cfg0 and 'redirectTo' not in cfg0 and not os.path.exists(os.path.join(KS, 'posta.php')) and "'/ai'" in cfg0, 'konfigurace: strop 2 000, vypínač, produkční token asistentky, bez posta.php v balíčku')
ok("/private/studio'" in open(os.path.join(KN, 'index.php')).read() and 'studio-test' not in open(os.path.join(KN, 'index.php')).read(), 'brána: produkční data /private/studio')
ok(open(os.path.join(KW, 'admin', 'index.php')).read().count('href="studio.php"') == 2, 'hlavička administrace: odkaz na Studio (počítač i mobil)')

tmp = tempfile.mkdtemp(prefix='t6-'); R = os.path.join(tmp, 'koren'); W = os.path.join(R, 'web'); PS = os.path.join(R, 'private', 'studio'); PA = os.path.join(R, 'private', 'ai'); NAH = os.path.join(R, 'sub', 'nahledy'); DATA = os.path.join(tmp, 'crm-data')
shutil.copytree(os.path.join(PROJ, 'web'), W, ignore=shutil.ignore_patterns('data', 'node_modules'))
shutil.copytree(os.path.join(PROJ, 'web', 'data'), os.path.join(W, 'data'))
shutil.copytree(KW, W, dirs_exist_ok=True); shutil.copytree(KS, PS); shutil.copytree(KN, NAH); os.makedirs(PA); os.makedirs(os.path.join(DATA, 'relace'))
open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0' * 32 + '\n')
c = cfg0.replace("'previewBase' => 'https://nahledy.tasstudio.eu/'", "'previewBase' => '%s/'" % GBASE).replace("'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
c = c.replace("'neuronsPerDay' => 2000]", "'neuronsPerDay' => 2000, 'baseUrl' => 'http://127.0.0.1:9/nikam']")
open(os.path.join(PS, 'studio.php'), 'w').write(c)
open(os.path.join(PS, 'posta.php'), 'w').write("<?php return ['transport' => 'log', 'logFile' => __DIR__ . '/data/posta.log', 'from' => 'studio@tasstudio.example', 'fromName' => 'T&S Studio'];\n")
db = sqlite3.connect(os.path.join(DATA, 'crm.sqlite'))
db.executescript('''CREATE TABLE users (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, email TEXT, role TEXT, active INTEGER, password_hash TEXT, created_at TEXT, last_login_at TEXT);
INSERT INTO users VALUES (1,'test-a','Testovací Admin','admin@tasstudio.example','admin',1,NULL,'2026-01-01',NULL),(2,'test-v','Testovací Viewer','viewer@tasstudio.example','viewer',1,NULL,'2026-01-01',NULL);
CREATE TABLE leads (id INTEGER PRIMARY KEY, name TEXT, phone TEXT, email TEXT, website TEXT, outreach_reason TEXT, archived_at TEXT);
INSERT INTO leads VALUES (5,'Pekárna Test','+420 600 000 001','pekarna@example.test','','Poptávka webu',NULL);
CREATE TABLE lead_notes (id INTEGER PRIMARY KEY, lead_id INTEGER, user_id INTEGER, content TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT);
INSERT INTO lead_notes VALUES (1,5,1,'Chtějí ukázat sortiment.','2026-01-01',NULL,NULL);''')
db.commit(); db.close()
SID = {1: 'a' * 26 + '01', 2: 'b' * 26 + '02'}
for uid, sid in SID.items():
    open(os.path.join(DATA, 'relace', 'sess_' + sid), 'w').write('csrf|s:9:"token-%03d";ok|b:1;t|i:%d;uid|i:%d;' % (uid, int(time.time()), uid))
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo json_encode([$s["studio"]["mail"]["transport"] ?? "", $s["studio"]["ai"]["baseUrl"]]);', os.path.join(PS, 'studio.php')], capture_output=True, text=True).stdout
ok(json.loads(chk)[0] == 'log' and 'cloudflare' not in chk, 'pojistka: e-mail jen do logu, model nikam', chk)
for port in (PORT, GPORT):
    if subprocess.run(['lsof', '-nP', '-iTCP:%d' % port, '-sTCP:LISTEN'], capture_output=True).stdout: sys.exit('port %d je obsazený' % port)
env = dict(os.environ, TS_ADMIN_DATA=DATA)
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=env, start_new_session=True)
gsrv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', '127.0.0.1:%d' % GPORT, '-t', NAH, os.path.join(NAH, 'index.php')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True); time.sleep(1.3)


def req(path, body=None, uid=1, csrf=True, base=BASE, cookie=True):
    h = {'Content-Type': 'application/json', 'Origin': BASE}
    if cookie: h['Cookie'] = 'tsadmin=' + SID[uid]
    if csrf and body is not None: h['X-CSRF'] = 'token-%03d' % uid
    r = urllib.request.Request(base + path, data=json.dumps(body).encode() if body is not None else None, headers=h)
    try:
        with urllib.request.urlopen(r, timeout=30) as x: return x.status, x.read().decode('utf-8', 'replace'), dict(x.headers)
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8', 'replace'), dict(e.headers)
A = lambda r, b=None, uid=1, csrf=True: (lambda s, t, h: (s, json.loads(t) if t.startswith('{') else t))(*req('/admin/studio-api.php?r=' + r, b, uid, csrf))
LOG = os.path.join(PS, 'data', 'posta.log')
try:
    class NoRedir(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k): return None
    try: urllib.request.build_opener(NoRedir).open(BASE + '/admin/studio.php', timeout=20); ok(False, 'bez přihlášení musí přesměrovat')
    except urllib.error.HTTPError as e: ok(e.code == 303 and e.headers.get('Location') == './', 'stránka Studia bez přihlášení → 303 na přihlášení', e.code)
    s, t, h = req('/admin/studio-api.php?r=list', cookie=False); ok(s == 401, 'API bez přihlášení → 401')
    s, t, h = req('/admin/studio.php'); ok(s == 200 and '"token-001"' in t and 'studio-api.php' in t, 'stránka Studia s přihlášením a tokenem CSRF')
    s, o = A('templates')
    if not isinstance(o, dict): print('templates:', s, str(o)[:800])
    ok(o['role'] == 'admin' and 'deliver' in o['actions'] and o['delivery'] and o['ai'], 'role z CRM: admin (vše)')
    s, o = A('templates', uid=2); ok(o['role'] == 'viewer' and o['actions'] == ['view'], 'role z CRM: viewer (jen prohlížení)')
    ok(A('create', {'name': 'X', 'template': 'salon'}, csrf=False)[1].get('error') == 'csrf', 'POST bez tokenu CSRF → 403')
    ok(A('create', {'name': 'X', 'template': 'salon'}, uid=2)[0] == 403, 'viewer nemůže zakládat (403)')
    s, o = A('create', {'name': 'Smyšlený klient', 'template': 'restaurace'}); pid = o['id']; ok(s == 201, 'admin založí projekt')
    ok(A('ai-create', {'source': 'crm', 'lead_ref': '999', 'brief': {'obor': 'pekárna'}})[0] == 400, 'lead mimo CRM → 400 (čte se jen CRM)')
    ok(A('status', {'pid': pid, 'status': 'approved_internal'})[0] == 200 and not os.path.exists(LOG), 'interní schválení, žádný e-mail')
    s, o = A('delivery-prepare', {'pid': pid, 'num': 1}); did = o['delivery']['id']
    A('delivery-draft', {'id': did, 'to_email': 'klient@example.test', 'subject': 'Návrh', 'body': 'Dobrý den,\n{odkaz}\n\n{jmeno}'})
    ok(A('delivery-send', {'id': did, 'confirm': 'jiny@example.test', 'idem': 'prod-test-1'})[0] == 400 and not os.path.exists(LOG), 'bez správného potvrzení se neodešle')
    s, o = A('delivery-send', {'id': did, 'confirm': 'klient@example.test', 'idem': 'prod-test-2'})
    log = open(LOG).read() if os.path.exists(LOG) else ''
    ok(s == 200 and 'To: <klient@example.test>' in log and 'Reply-To:' in log and 'admin@tasstudio.example' in log, 'odesláno: příjemce, Reply-To přihlášeného z CRM', log[:300])
    msg = email.message_from_string(log.split('\r\n\r\n\r\n')[0]); body = ''.join(p.get_payload(decode=True).decode() for p in msg.walk() if p.get_content_type() == 'text/plain')
    link = re.search(re.escape(GBASE) + r'/[A-Za-z0-9_-]{32}/', body).group(0)
    ok('Testovací Admin' in body, 'podpis jménem přihlášeného z CRM')
    s, t, h = req(link[len(GBASE):], cookie=False, base=GBASE); ok(s == 200 and 'neveřejný odkaz platí do' in t and 'Set-Cookie' not in h, 'odkaz klientovi přes bránu (produkční data)')
    ok(A('delivery-revoke', {'id': did})[0] == 200 and req(link[len(GBASE):], cookie=False, base=GBASE)[0] == 404, 'odvolání → 404')
    # vypínač
    open(os.path.join(PS, 'studio.php'), 'w').write(c.replace("'enabled' => true", "'enabled' => false"))
    ok(A('list')[0] == 503 and 'vypnuté' in req('/admin/studio.php')[1] and req('/' + 'A' * 32 + '/', cookie=False, base=GBASE)[0] == 503, 'vypínač: API 503, stránka „vypnuté“, náhledy 503')
    open(os.path.join(PS, 'studio.php'), 'w').write(c)
    # CRM zůstalo beze změny (jen čtení)
    db = sqlite3.connect(os.path.join(DATA, 'crm.sqlite')); n = db.execute("SELECT count(*) FROM sqlite_master WHERE type='table'").fetchone()[0]; db.close()
    names = [r[0] for r in sqlite3.connect(os.path.join(DATA, "crm.sqlite")).execute("SELECT name FROM sqlite_master WHERE type='table'")]; ok(n == 3, "databáze CRM jen čtená (žádné nové tabulky)", names)
finally:
    os.killpg(srv.pid, 15); os.killpg(gsrv.pid, 15)
print('RESULT pass=%d fail=%d' % (P, F))
