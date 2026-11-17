#!/usr/bin/env python3
"""Test nasazovacího balíčku v kopii se strukturou hostingu (kořen/sub/test + kořen/private/booking-test), PHP 8.5 lokálně.

    python3 -I -B otestovat_balicek.py

Projde: instalátor (kód, databáze, SMTP bez přihlášení, zkušební e-mail bez hesla, administrátor, zámek), veřejné API,
rezervaci přes web, správu (přihlášení, blokace, potvrzení), zrušení odkazem, testovací režim e-mailů, úniky informací,
izolaci od produkce a integritu jádra. Balíček ani hosting nemění (pracuje na dočasné kopii).
"""
import base64, http.cookiejar, json, os, re, shutil, sqlite3, subprocess, sys, tempfile, time, urllib.parse, urllib.request, urllib.error
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, '2-balicek')
LIB = os.path.expanduser('~/TS-Studio/ts-library'); PHP = os.path.expanduser('~/TS-Studio/tools/php/php')
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PORT = 8181; BASE = 'http://localhost:%d' % PORT
res = []


def ok(c, msg, d=''):
    res.append((bool(c), msg)); print(('OK    ' if c else 'CHYBA ') + msg + ('' if c or not d else '  [' + str(d)[:200] + ']'))


loc = open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'instalacni-kod.txt')).read()
CODE = re.search(r': ([0-9A-F]{6}(?:-[0-9A-F]{6}){3})', loc).group(1)
INST = json.load(open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'balicek.json')))['installer']
tmp = tempfile.mkdtemp(prefix='tsb-balicek-'); ROOT = os.path.join(tmp, 'hosting')
WEB = os.path.join(ROOT, 'sub', 'test'); PRIV = os.path.join(ROOT, 'private', 'booking-test')
os.makedirs(os.path.join(ROOT, 'web')); open(os.path.join(ROOT, 'web', 'PRODUKCE.txt'), 'w').write('nesahat')
shutil.copytree(os.path.join(PKG, 'nahrat-do-sub-test'), WEB); shutil.copytree(os.path.join(PKG, 'nahrat-do-private-booking'), PRIV)
# lokální test: stránka běží na localhost – v kopii konfigurace povolíme i tento původ (balíček se nemění)
_cfg = os.path.join(PRIV, 'booking-server.php'); _txt = open(_cfg).read()
_txt = _txt.replace("'smtp_user' => 'rezervace@tasstudio.eu'", "'smtp_user' => 'test-uzivatel@example.invalid'")   # pojistka: nikdy skutečná schránka
assert "test-uzivatel@example.invalid" in _txt
open(_cfg, 'w').write(_txt.replace("'allowedOrigins' => ['https://test.tasstudio.eu']", "'allowedOrigins' => ['https://test.tasstudio.eu', '%s']" % BASE))
os.chmod(os.path.join(ROOT, 'web'), 0o555)                 # simulovaná produkce jen pro čtení
SONDA = '_sonda-%s.php' % os.getpid()
open(os.path.join(WEB, SONDA), 'w').write('''<?php
$private = dirname(__DIR__, 2) . '/private/booking-test';
require __DIR__ . '/vendor/ts/ts-booking-server/core/bootstrap.php';
if (!ts_booking_harden(['requireHttps' => true, 'paths' => [__DIR__, $private]])) exit;
$web = dirname(__DIR__, 2) . '/web';
echo json_encode(['web_zapis' => @file_put_contents($web . '/HACK.txt', 'x') !== false, 'web_cteni' => @file_get_contents($web . '/PRODUKCE.txt') !== false,
  'koren_zapis' => @file_put_contents(dirname(__DIR__, 2) . '/HACK.txt', 'x') !== false, 'private_zapis' => @file_put_contents($private . '/data/sonda.txt', 'x') !== false && @unlink($private . '/data/sonda.txt'),
  'ob' => ini_get('open_basedir')]);
''')
def snapshot():
    out = {}
    for d, _, fs in os.walk(ROOT):
        for f in fs:
            p = os.path.join(d, f); out[os.path.relpath(p, ROOT)] = open(p, 'rb').read()
    return out
snap_before = snapshot()
web_before = sorted(os.path.relpath(os.path.join(d, f), WEB) for d, _, fs in os.walk(WEB) for f in fs)
prod_before = os.listdir(os.path.join(ROOT, 'web'))

# --- statická kontrola balíčku ---
allf = [os.path.join(d, f) for top in (os.path.join(PKG, 'nahrat-do-sub-test'), os.path.join(PKG, 'nahrat-do-private-booking')) for d, _, fs in os.walk(top) for f in fs]
for f in allf:
    if f.endswith('.php'):
        r = subprocess.run([PHP, '-l', f], capture_output=True, text=True);
        if r.returncode: ok(False, 'PHP 8.5 syntaxe ' + f, r.stdout)
ok(True, 'PHP 8.5: syntaxe všech %d PHP souborů balíčku' % len([f for f in allf if f.endswith('.php')]))
own = [f for f in allf if '/vendor/' not in f]
text = ''.join(open(f, encoding='utf-8', errors='replace').read() for f in own)
ok(not re.search(r"smtp_pass'\s*=>\s*'[^']+'", text) and 'smtp-heslo.txt' in text, 'v balíčku není žádné heslo (SMTP heslo jen v souboru vytvořeném na serveru)')
ok(not re.search(r'/web/|admin/data|crm\.sqlite|crm-lib|poptavka\.php|Downloads/T&S', text), 'žádný odkaz na produkční web, CRM ani původní projekt')
ok(re.findall(r"'dsn' => 'sqlite:' \. \$private \. '([^']+)'", text) == ['/data/rezervace-test.sqlite'], 'databáze jen v /private/booking-test/data/rezervace-test.sqlite')
ok(CODE not in text and CODE not in open(os.path.join(PKG, 'SHA256SUMS.txt')).read(), 'instalační kód není v balíčku (jen jeho otisk)')
ok(all('/vendor/' in f or os.path.basename(f).startswith('_sonda') or os.path.basename(f) in ('.htaccess', 'index.html', 'sprava.html', 'rezervace-zrusit.html', 'robots.txt', 'rezervace.php', INST, 'web.css', 'tokens.css', 'ts-components.lock.json') for f in allf if 'nahrat-do-sub-test' in f), 'webroot obsahuje jen veřejné soubory (bez konfigurace a dat)')
r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'verify', os.path.join(PKG, 'nahrat-do-sub-test')], capture_output=True, text=True)
ok(r.returncode == 0, 'jádro knihovny v balíčku beze změny (ts.py verify)', r.stdout)

srv = subprocess.Popen([PHP, '-d', 'error_reporting=-1', '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', '-S', 'localhost:%d' % PORT, '-t', WEB], stdout=subprocess.DEVNULL, stderr=open(os.path.join(tmp, 'php.log'), 'w'))
time.sleep(1.2)
cj = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))


def get(path, opener=op):
    try:
        with opener.open(BASE + path, timeout=30) as r: return r.status, r.read().decode()
    except urllib.error.HTTPError as e: return e.code, e.read().decode()


def post(path, data, opener=op, js=False, headers=None):
    body = json.dumps(data).encode() if js else urllib.parse.urlencode(data).encode()
    h = {'Content-Type': 'application/json' if js else 'application/x-www-form-urlencoded'}; h.update(headers or {})
    try:
        with opener.open(urllib.request.Request(BASE + path, data=body, headers=h), timeout=30) as r: return r.status, r.read().decode()
    except urllib.error.HTTPError as e: return e.code, e.read().decode()


try:
    # --- instalátor ---
    st, b = get('/' + INST); csrf = re.search(r'name="csrf" value="([0-9a-f]+)"', b).group(1)
    ok(st == 200 and '✔' not in b and '✘' not in b and 'act" value="status"' in b and 'name="password"' not in b, 'instalátor bez kódu: nezobrazí kontrolu ani formuláře akcí, jen pole pro kód')
    att = {'username': 'utocnik', 'name': 'X', 'password': 'utocnik-heslo-2026', 'password2': 'utocnik-heslo-2026'}
    wrong = []
    for act, code in (('admin', ''), ('migrate', 'SPATNY-KOD'), ('testmail', CODE.lower()), ('admin', CODE + 'X')):      # 4 špatné kódy se správným CSRF (5. by zamkl)
        st, b = post('/' + INST, dict(att, csrf=csrf, act=act, code=code)); wrong.append('Nesprávný instalační kód' in b and 'name="password"' not in b)
    ok(all(wrong), 'instalátor: akce se špatným/prázdným kódem odmítnuty (i se správným CSRF) a formuláře se neukážou')
    for act in ('status', 'migrate', 'probe', 'testmail', 'admin'):
        st, b = post('/' + INST, dict(att, act=act, code=CODE)); wrong.append('vypršela' in b)                       # bez CSRF tokenu
        st, b = post('/' + INST, dict(att, csrf='0' * 48, act=act, code=CODE)); wrong.append('vypršela' in b)       # podvržený token
    ok(all(wrong), 'instalátor: akce s platným kódem, ale bez/s podvrženým CSRF tokenem odmítnuty')
    dataf = sorted(os.listdir(os.path.join(PRIV, 'data')))
    ok(not any(f.endswith('.sqlite') for f in dataf) and 'install.lock' not in dataf and 'mail.log' not in dataf, 'instalátor: žádná akce bez platného kódu a CSRF neproběhla (databáze, zámek ani e-mail nevznikly)', dataf)
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'status', 'code': CODE}); csrf = re.search(r'name="csrf" value="([0-9a-f]+)"', b).group(1)
    ok(st == 200 and '✘ SMTP heslo je vyplněné' in b and '✔ databáze SQLite je mimo webroot' in b and '✔ konfigurace rezervací je mimo webroot' in b, 'instalátor: kontrola prostředí (data mimo webroot, heslo zatím chybí)')
    ok('BEZ přesměrování' not in b, 'instalátor: e-maily v testovacím režimu (přesměrování do testovací schránky)')
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'migrate', 'code': 'SPATNY-KOD'}); ok('Nesprávný instalační kód' in b, 'instalátor: špatný kód odmítnut')
    st, b = post('/' + INST, {'act': 'migrate', 'code': CODE}); ok('vypršela' in b, 'instalátor: bez CSRF tokenu odmítnut')
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'migrate', 'code': CODE}); ok('Databáze vytvořena' in b, 'instalátor: databáze vytvořena')
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'probe', 'code': CODE}); ok('SMTP server odpovídá' in b and 'Přihlášení se nezkoušelo' in b, 'instalátor: smtp.websupport.cz:465 odpovídá (z tohoto Macu, bez přihlášení)', re.sub('<[^>]+>', ' ', b)[-300:])
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'testmail', 'code': CODE}); ok('doplňte SMTP heslo' in b, 'instalátor: zkušební e-mail bez hesla se nepošle')
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'admin', 'code': CODE, 'username': 'majitel', 'name': 'Majitelka (test)', 'password': 'kratke', 'password2': 'kratke'}); ok('12 znaků' in b, 'instalátor: krátké heslo odmítnuto')
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'admin', 'code': CODE, 'username': 'majitel', 'name': 'Majitelka (test)', 'password': 'testovaci-heslo-2026', 'password2': 'testovaci-heslo-2026'}); ok('Administrátor vytvořen' in b, 'instalátor: administrátor vytvořen')
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'admin', 'code': CODE, 'username': 'druhy', 'name': 'X', 'password': 'testovaci-heslo-2026', 'password2': 'testovaci-heslo-2026'}); ok('už existuje' in b or 'zamčený' in b, 'instalátor: druhý administrátor nejde (zamčeno)')
    ok(os.path.isfile(os.path.join(PRIV, 'data', 'install.lock')), 'instalátor: zámek uložen v neveřejné složce')
    op2 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    st, b = get('/' + INST, op2); csrf2 = re.search(r'name="csrf" value="([0-9a-f]+)"', b).group(1)
    for i in range(5): post('/' + INST, {'csrf': csrf2, 'act': 'migrate', 'code': 'X%d' % i}, op2)
    st, b = post('/' + INST, {'csrf': csrf2, 'act': 'migrate', 'code': CODE}, op2); ok('Příliš mnoho' in b, 'instalátor: zámek po 5 špatných kódech')
    os.remove(os.path.join(PRIV, 'data', 'pokusy.json'))      # jen v testovací kopii: zrušit zámek pro další kroky testu
    # --- heslo SMTP: textový soubor, nikdy v odpovědi, chybě ani logu ---
    SECRET = 'Tajne"Heslo\'$x\\9<?php echo 1;'
    cfgp = os.path.join(PRIV, 'booking-server.php'); orig = open(cfgp).read()
    import socket; _s = socket.socket(); _s.bind(('127.0.0.1', 0)); fport = _s.getsockname()[1]; _s.close()   # volný port
    fsmtp = subprocess.Popen([PHP, os.path.join(LIB, 'components', 'ts-mailer-php', 'tests', 'fake_smtp.php'), str(fport), os.path.join(tmp, 'smtp.txt')])
    for _ in range(60):
        if os.path.exists(os.path.join(tmp, 'smtp.txt.ready')): break
        time.sleep(.05)
    new = orig.replace("'smtp_host' => 'smtp.websupport.cz', 'smtp_port' => 465, 'smtp_secure' => 'ssl'", "'smtp_host' => '127.0.0.1', 'smtp_port' => %d, 'smtp_secure' => 'none'" % fport)
    assert "'smtp_host' => '127.0.0.1'" in new and 'smtp.websupport.cz' not in new, 'test musí mířit jen na lokální SMTP'
    open(cfgp, 'w').write(new); time.sleep(.2)
    open(os.path.join(PRIV, 'smtp-heslo.txt'), 'w').write(SECRET + '\n')        # heslo až po přepnutí na lokální server
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'status', 'code': CODE}); ok('✔ SMTP heslo je vyplněné' in b and SECRET not in b, 'heslo ze smtp-heslo.txt načteno (hodnota se nezobrazí)', re.sub('<[^>]+>', ' ', b.split('</style>')[-1])[:700])
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'testmail', 'code': CODE})
    try: fsmtp.wait(10)
    except subprocess.TimeoutExpired: fsmtp.kill()
    sent = open(os.path.join(tmp, 'smtp.txt'), encoding='utf-8', errors='replace').read() if os.path.exists(os.path.join(tmp, 'smtp.txt')) else ''
    ok('Zkušební e-mail odeslán' in b and base64.b64encode(SECRET.encode()).decode() in sent and 'RCPT TO:<rezervace@tasstudio.eu>' in sent and 'test-uzivatel@example.invalid' in base64.b64decode(re.search(r'AUTH LOGIN\r?\n(\S+)', sent).group(1)).decode() if 'AUTH LOGIN' in sent else False, 'zkušební e-mail přes SMTP s heslem ze souboru, jen do testovací schránky', 'SMTP: ' + sent[:400].replace('\r', ''))
    new2 = orig.replace("'smtp_host' => 'smtp.websupport.cz', 'smtp_port' => 465, 'smtp_secure' => 'ssl'", "'smtp_host' => '127.0.0.1', 'smtp_port' => 1, 'smtp_secure' => 'none'").replace("'transport' => 'log'", "'transport' => 'smtp'")
    assert 'smtp.websupport.cz' not in new2; open(cfgp, 'w').write(new2)
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'testmail', 'code': CODE}); ok('selhalo' in b and SECRET not in b, 'chyba SMTP bez hesla v odpovědi')
    open(os.path.join(PRIV, 'smtp-heslo.txt'), 'w').write('<?php syntax error ' + SECRET)
    st, b = post('/' + INST, {'csrf': csrf, 'act': 'status', 'code': CODE}); ok(st == 200 and SECRET not in b, 'poškozený soubor s heslem nezpůsobí chybu ani únik (čte se jako text)')
    os.remove(os.path.join(PRIV, 'smtp-heslo.txt')); open(cfgp, 'w').write(orig)      # nejdřív pryč heslo, pak zpět skutečný server
    # --- veřejné API a úniky ---
    st, b = get('/api/rezervace.php?r=config'); ok(st == 200 and json.loads(b)['ok'], 'API: konfigurace')
    st, b = post('/api/rezervace.php?r=bookings', {'nesmysl': 1}, js=True); ok(st == 422 and '/' not in json.dumps(json.loads(b).get('error')), 'API: neplatný požadavek → 422 bez interních údajů')
    st, b = get('/api/rezervace.php?r=../../etc'); ok(st == 404 and 'private' not in b, 'API: neznámá cesta → 404')
    st, b = post('/api/rezervace.php?r=bookings', {'date': '2026-11-09'}, js=True, headers={'Origin': 'https://zly-web.example'}); ok(st == 403, 'API: rezervace z cizího webu (Origin) odmítnuta', st)
    st, b = get('/api/rezervace.php?r=admin/bookings'); ok(st == 401, 'API: správa bez přihlášení → 401')
    log = open(os.path.join(tmp, 'php.log')).read()
    ok(not re.search(r'(Warning|Deprecated|Notice|Fatal)', log), 'PHP 8.5: žádná varování ani zastaralé funkce za běhu', log[-300:])
    # --- web v prohlížeči ---
    br = Browser(port=9365)
    try:
        br.size(1280, 900); br.go(BASE + '/index.html', wait=1.8)
        out = json.loads(br.js(r'''return (async () => {
          const sleep = ms => new Promise(r => setTimeout(r, ms)), el = document.querySelector('#widget'), q = s => el.querySelector(s);
          for (let i = 0; i < 60 && !q('.tsb-card'); i++) await sleep(100);
          q('[data-svc="pansky-strih"]').click(); await sleep(60); q('[data-res="marek"]').click(); await sleep(60);
          const day = q('.tsb-day.tsb-free'); const d = day.dataset.date; day.click(); await sleep(60);
          const t = q('.tsb-time'); const tt = +t.dataset.t; await sleep(2100); t.click(); await sleep(60);
          const f = q('.tsb-form'); f.elements.name.value = 'Testovací Zákazník'; f.elements.email.value = 'zakaznik@example.com'; f.elements.consent.checked = true;
          f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          for (let i = 0; i < 40 && !q('.tsb-done'); i++) await sleep(100);
          return JSON.stringify({ d, tt, done: !!q('.tsb-done'), noindex: !!document.querySelector('meta[name=robots][content*=noindex]') }); })();'''))
        ok(out['done'] and out['noindex'], 'web: rezervace odeslána (stránka má noindex)')
        db = os.path.join(PRIV, 'data', 'rezervace-test.sqlite')
        n = sqlite3.connect(db).execute("SELECT COUNT(*) FROM ts_booking_bookings WHERE email='zakaznik@example.com' AND status='pending'").fetchone()[0]
        ok(n == 1, 'rezervace uložena v SQLite v /private/booking-test/data/')
        st, b = post('/api/rezervace.php?r=bookings', {'date': out['d'], 'service': 'pansky-strih', 'start': out['tt'], 'resource': 'marek', 'name': 'Druhý', 'email': 'druhy@example.com', 'consent': True, 'openedMs': 9000}, js=True, headers={'Idempotency-Key': 'dvojita-1'})
        ok(st == 409 and json.loads(b)['error'] == 'taken', 'ochrana proti dvojité rezervaci (stejný termín u Marka → 409)')
        mlog = open(os.path.join(PRIV, 'data', 'mail.log'), encoding='utf-8', errors='replace').read()
        ok('To: <rezervace@tasstudio.eu>' in mlog and not re.search(r'^To: <zakaznik@example.com>', mlog, re.M) and 'X-TS-Original-To: <zakaznik@example.com>' in mlog, 'e-maily (režim log): jen do testovací schránky, původní příjemce v hlavičce')
        bodies = ''.join(base64.b64decode(x).decode('utf-8', 'replace') for x in re.findall(r'Content-Transfer-Encoding: base64\r?\n\r?\n([A-Za-z0-9+/=\r\n]+?)\r?\n--', mlog))
        m = re.search(r'(https://test\.tasstudio\.eu/rezervace-zrusit\.html\?id=(\S+)&token=(\S+))', bodies)
        ok(bool(m), 'e-mail obsahuje odkaz na zrušení na test.tasstudio.eu')
        # správa
        br.go(BASE + '/sprava.html', wait=1.5)
        adm = json.loads(br.js(r'''return (async () => {
          const sleep = ms => new Promise(r => setTimeout(r, ms)), el = document.querySelector('#admin');
          for (let i = 0; i < 40 && !el.querySelector('form'); i++) await sleep(100);
          const f = el.querySelector('form'); f.elements.u.value = 'majitel'; f.elements.p.value = 'testovaci-heslo-2026'; f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          for (let i = 0; i < 40 && !el.querySelector('.tsb-block-form'); i++) await sleep(100);
          const o = { rows: el.querySelectorAll('.tsb-table tbody tr').length, blocks: !!el.querySelector('.tsb-block-form') };
          const bf = el.querySelector('.tsb-block-form'); bf.elements.date.value = '%s'; bf.elements.from.value = '08:00'; bf.elements.to.value = '20:00'; bf.elements.resource.value = 'klara'; bf.elements.reason.value = 'školení (test)';
          bf.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await sleep(800);
          o.blockShown = (el.querySelector('.tsb-blocks') || {}).textContent || '';
          const c = el.querySelector('[data-status="confirmed"]'); c.click(); await sleep(800);
          o.text = el.textContent; return JSON.stringify(o); })();''' % out['d']))
        ok(adm['blocks'] and 'školení' in adm['blockShown'], 'správa: blokace termínu přes rozhraní')
        bl = sqlite3.connect(db).execute("SELECT resource, from_min, to_min FROM ts_booking_blocks").fetchall()
        ok(bl == [('klara', 480, 1200)], 'blokace uložena v databázi', bl)
        st, b = post('/api/rezervace.php?r=bookings', {'date': out['d'], 'service': 'damsky-strih', 'start': 600, 'resource': 'klara', 'name': 'Třetí', 'email': 'treti@example.com', 'consent': True, 'openedMs': 9000}, js=True, headers={'Idempotency-Key': 'blok-1'})
        ok(st in (409, 422), 'zablokovaný termín nejde rezervovat', (st, b))
        ok(sqlite3.connect(db).execute("SELECT status FROM ts_booking_bookings WHERE email='zakaznik@example.com'").fetchone()[0] == 'confirmed', 'správa: potvrzení rezervace uloženo')
        br.shot(os.path.join(HERE, '2-balicek', 'lokalne-NENAHRAVAT', 'nahled-spravy.jpg'), full=True)
        # zrušení odkazem (adresa test.tasstudio.eu → lokální server)
        br.go(BASE + '/rezervace-zrusit.html?id=%s&token=%s' % (m.group(2), m.group(3)), wait=1.2)
        br.js("document.querySelector('[data-act=\"cancel\"]').click()"); time.sleep(.8)
        ok('zrušená' in br.js("document.querySelector('#zruseni').textContent") and sqlite3.connect(db).execute("SELECT status FROM ts_booking_bookings WHERE email='zakaznik@example.com'").fetchone()[0] == 'cancelled', 'zrušení rezervace odkazem z e-mailu')
    finally:
        br.close()
    st, b = get('/' + SONDA); so = json.loads(b)
    ok(not so['web_zapis'] and not so['web_cteni'] and not so['koren_zapis'] and so['private_zapis'] and so['ob'], 'open_basedir za běhu: /web ani kořen hostingu nejde číst/zapsat, /private/booking-test ano', so)
    log = open(os.path.join(tmp, 'php.log')).read()
    ok(not re.search(r'(Warning|Deprecated|Notice|Fatal)', log), 'PHP 8.5: celý průchod bez varování', log[-300:])
    alltext = log + ''.join(open(os.path.join(dp, f), 'rb').read().decode('utf-8', 'replace') for dp, _, fs in os.walk(os.path.join(PRIV, 'data')) for f in fs if not f.endswith(('.sqlite', '-wal', '-shm')))
    ok(SECRET not in alltext and base64.b64encode(SECRET.encode()).decode() not in alltext, 'heslo SMTP není v logu PHP, mail.log ani jiných souborech dat')
finally:
    srv.terminate(); srv.wait(5)
os.remove(os.path.join(WEB, SONDA))
snap_after = snapshot(); changed = sorted(k for k in set(snap_before) | set(snap_after) if snap_before.get(k) != snap_after.get(k) and SONDA not in k)
ok(changed and all(k.startswith('private/booking-test/data/') or k == 'private/booking-test/booking-server.php' and snap_before.get(k) == snap_after.get(k) for k in changed), 'celý hosting: změnily se jen soubory v /private/booking-test/data/', [k for k in changed if not k.startswith('private/booking-test/data/')])
web_after = sorted(os.path.relpath(os.path.join(d, f), WEB) for d, _, fs in os.walk(WEB) for f in fs)
web_before = [f for f in web_before if SONDA not in f]
ok(web_before == web_after, 'do webrootu /sub/test/ se za běhu nic nezapsalo (data jen v private)', set(web_after) ^ set(web_before))
ok(os.listdir(os.path.join(ROOT, 'web')) == prod_before and open(os.path.join(ROOT, 'web', 'PRODUKCE.txt')).read() == 'nesahat', 'simulovaný produkční /web/ beze změny')
priv_files = sorted(os.path.relpath(os.path.join(d, f), PRIV) for d, _, fs in os.walk(PRIV) for f in fs)
ok(all(p.startswith('data/') or p in ('.htaccess', 'booking-server.php', 'rezervace-config.json') for p in priv_files), 'aplikace zapisuje jen do /private/booking-test/data/', priv_files)
os.chmod(os.path.join(ROOT, 'web'), 0o755); shutil.rmtree(tmp, ignore_errors=True)
bad = [r for r in res if not r[0]]
print('Celkem %d kontrol, %d chyb' % (len(res), len(bad)))
json.dump({'pass': len(res) - len(bad), 'fail': len(bad), 'checks': [{'ok': r[0], 'test': r[1]} for r in res]}, open(os.path.join(HERE, '2-balicek', 'lokalne-NENAHRAVAT', 'vysledky-testu.json'), 'w'), ensure_ascii=False, indent=1)
sys.exit(1 if bad else 0)
