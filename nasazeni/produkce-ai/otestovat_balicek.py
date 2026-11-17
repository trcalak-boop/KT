#!/usr/bin/env python3
"""Test produkčního balíčku AI asistenta na simulované produkci: kořen/web = produkce (git HEAD projektu) + kopie
vývojového CRM (dev/admin-data, migrace 5), přes ni nahraný balíček, kořen/private/ai z balíčku.
Žádná skutečná služba: API Claude = napodobenina na localhostu, falešný klíč, e-maily jen do souboru (sendmail_path),
kontrola spojení na localhost. Poslední řádek: RESULT pass=N fail=M"""
import http.server, json, os, re, shutil, sqlite3, subprocess, sys, tempfile, threading, time, urllib.error, urllib.parse, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek')
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; PHP = os.path.join(PROJ, 'tools', 'php', 'php')
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
PORT, MOCK = 8191, 8192; BASE = 'http://localhost:%d' % PORT; KEY = 'sk-ant-api03-FALESNY-produkce-0000'
P = F = 0; RES = []


def ok(c, m, d=''):
    global P, F
    RES.append([bool(c), m])
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


REQS = []


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}'); b['_path'] = self.path; b['_auth'] = self.headers.get('Authorization', ''); REQS.append(b)
        u = str(b['messages'][-1]['content']).lower()
        txt = 'Rádi se ozveme.\n[[KONTAKT: Web pro kavárnu]]' if 'kontakt' in u else 'Podívejte se na Kořen: /ukazky/koren.html (ukázka, fiktivní značka).'
        o = json.dumps({'id': 'c', 'object': 'chat.completion', 'model': b['model'], 'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': txt}, 'finish_reason': 'stop'}], 'usage': {'prompt_tokens': 4000, 'completion_tokens': 100}}).encode()
        self.send_response(200); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o)


meta = json.load(open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'balicek.json')))
code = re.search(r': (AI-[0-9A-F]{6}-[0-9A-F]{6})', open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'kontrolni-kod.txt')).read()).group(1)
files = open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'soubory-web.txt')).read().split()
ok(not any(f.startswith('admin/data') for f in files) and 'ts-components.lock.json' not in files, 'balíček neobsahuje admin/data ani zámek komponent')
ok(all(f in files for f in ('index.html', 'cs/index.html', 'cs/ochrana-osobnich-udaju.html', 'vendor/ts/ts-ai-consultant/core/TsAiConsultant.php', 'vendor/ts/ts-ai-consultant/core/TsAiProviders.php')) and not any(f.startswith('admin/') for f in files), 'balíček: stránky, zásady, knihovna; CRM beze změny (už nasazené)')
cfg0 = open(os.path.join(PKG, 'k-nahrani', 'ai', 'ai-consultant.php')).read()
ok("'enabled' => true" in cfg0 and "crm_ai_lead($lead)" in cfg0 and "'mail'" not in cfg0.replace("'mail' a 'notify'", '') and "'provider' => 'cloudflare'" in cfg0 and 'claude' not in cfg0.lower() and "'persona' => ['name' => 'Tessa'" in cfg0, 'konfigurace: Cloudflare zdarma, Tessa, lead do CRM, bez vlastních e-mailů, bez Claude')
ok('tessa/tessa-portret.jpg' in files, 'balíček: portrét Tessy')
K = json.load(open(os.path.join(PKG, 'k-nahrani', 'ai', 'znalosti.json')))
ok(all(r['url'].startswith('/') or r['url'].startswith('https://www.krystof') for r in K['references']) and not re.search(r'\d\s?Kč', json.dumps(K, ensure_ascii=False)), 'znalosti: relativní odkazy, bez částek')

tmp = tempfile.mkdtemp(prefix='tsai-prod-'); R = os.path.join(tmp, 'koren'); W = os.path.join(R, 'web'); PA = os.path.join(R, 'private', 'ai')
os.makedirs(R)
subprocess.run('git -C "%s" archive HEAD web | tar -x -C "%s"' % (PROJ, R), shell=True, check=True)          # produkce = git HEAD
os.makedirs(os.path.join(W, 'admin', 'data'), exist_ok=True)
for f in ('crm.sqlite', 'crm.sqlite-wal', 'crm.sqlite-shm', 'heslo.php'):
    if os.path.isfile(os.path.join(PROJ, 'dev', 'admin-data', f)): shutil.copy(os.path.join(PROJ, 'dev', 'admin-data', f), os.path.join(W, 'admin', 'data', f))
assert not os.path.exists(os.path.join(W, 'admin', 'data', 'posta.php'))
leadu0 = sqlite3.connect(os.path.join(W, 'admin', 'data', 'crm.sqlite')).execute('SELECT COUNT(*) FROM leads').fetchone()[0]
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'web'), W, dirs_exist_ok=True)
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'ai'), PA)
open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY-PROD\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0123456789abcdef0123456789abcdef\n')
c = cfg0.replace("'timeout' => 30, 'connectTimeout' => 6]", "'timeout' => 30, 'connectTimeout' => 6, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1']" % MOCK).replace("'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
c = c.replace("'until' => '%s']" % meta['until'], "'until' => '%s', 'probe' => 'tcp://127.0.0.1:%d']" % (meta['until'], MOCK))
open(os.path.join(PA, 'ai-consultant.php'), 'w').write(c)
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo json_encode([$s["cloudflare"]["baseUrl"] ?? "", $s["check"]["probe"] ?? "", isset($s["mail"])]);', os.path.join(PA, 'ai-consultant.php')], capture_output=True, text=True).stdout
ok(json.loads(chk) == ['http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1' % MOCK, 'tcp://127.0.0.1:%d' % MOCK, False], 'pojistka: kopie nemíří na skutečné API ani poštu', chk)
if 'cloudflare.com' in chk or 'anthropic.com' in chk: sys.exit(1)
MAILLOG = os.path.join(tmp, 'sendmail.log')

mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-d', 'sendmail_path=cat >> %s' % MAILLOG, '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)


def http(method, path, body=None, form=None, h=None):
    hd = {'Accept': 'application/json'}; hd.update(h or {}); data = None
    if body is not None: data = json.dumps(body).encode(); hd['Content-Type'] = 'application/json'
    if form is not None: data = urllib.parse.urlencode(form, doseq=True).encode(); hd['Content-Type'] = 'application/x-www-form-urlencoded'
    try:
        with urllib.request.urlopen(urllib.request.Request(BASE + path, data=data, headers=hd, method=method), timeout=30) as r: return r.status, r.read().decode()
    except urllib.error.HTTPError as e: return e.code, e.read().decode()


try:
    st, b = http('GET', '/api/ai/chat.php?r=status'); ok(st == 200 and json.loads(b)['enabled'], 'brána: zapnuto', b)
    st, b = http('POST', '/' + meta['check'], form={'code': code, 'a': 'check'})
    bad = [t for s, t in re.findall(r'class="(ok|no)">\w+</td><td>([^<]*)', b) if s == 'no']
    ok(st == 200 and '<table' in b and not bad, 'kontrola prostředí: vše OK', bad)
    st, b = http('GET', '/cs/index.html'); ok('ts-ai-widget.js?v=1.5.1' in b and '"name":"Tessa"' in b and 'tessa-portret.jpg' in b, 'stránka webu načítá widget s Tessou')
    st, b = http('GET', '/cs/ochrana-osobnich-udaju.html'); ok('Cloudflare, Inc.' in b and 'Anthropic' not in b, 'zásady: Cloudflare místo Anthropic')

    br = Browser(port=9391)
    try:
        br.size(1280, 900); br.go(BASE + '/cs/index.html', wait=2.0)
        ok(br.js("return !!document.querySelector('.tsai') && document.querySelector('.tsai').hidden === false"), 'tlačítko asistenta se ukázalo (brána potvrdila zapnutí)')
        errs = br.js("return window.__errs || []")
        br.js("TSAiWidget.open()", wait=.3)
        ok(br.js("const a=document.querySelector('.tsai-note a'); return !!a && a.getAttribute('href') === '/cs/ochrana-osobnich-udaju.html'"), 'úvodní poznámka odkazuje na české zásady')
        br.js("TSAiWidget.send('Máte podobný projekt pro restauraci?')", wait=2.5)
        ok(br.js("return [...document.querySelectorAll('.tsai-assistant a')].some(a => a.getAttribute('href') === '/ukazky/koren.html')"), 'odpověď s odkazem na ukázku Kořen (relativní, na produkci)')
        r = br.js("return [document.querySelector('.tsai-title').textContent, document.querySelector('.tsai-role').textContent, !!document.querySelector('.tsai-head-face img')]")
        ok(r[0] == 'Tessa' and r[1] == 'AI asistentka T&S Studia' and r[2], 'hlavička: Tessa, označení AI, portrét', r)
        sysp = REQS[-1]['messages'][0]['content']
        ok(REQS[-1]['_path'].endswith('/ai/v1/chat/completions') and REQS[-1]['_auth'] == 'Bearer CF-FALESNY-PROD' and sysp.startswith('# Identita\nJsi Tessa') and '[[KONTAKT:' in sysp, 'Cloudflare: token, persona, značka kontaktu v promptu')
        br.js("TSAiWidget.send('Chci kontakt')", wait=2.5)
        ok(br.js("return !!document.querySelector('.tsai-offer') && !document.querySelector('.tsai-form')"), 'nabídka kontaktu jako tlačítko (formulář se sám neotevřel)')
        br.js("document.querySelector('.tsai-offer').click()", wait=.3)
        time.sleep(3.6)
        br.js("document.getElementById('tsai-f-name').value='TEST Produkce'; document.getElementById('tsai-f-email').value='test@example.invalid'; document.getElementById('tsai-f-consent').click(); document.querySelector('.tsai-form [type=submit]').click()", wait=2.5)
        ok(br.js("return document.querySelector('.tsai-log').textContent.indexOf('poptávka je odeslaná') >= 0"), 'formulář odeslán')
        br.size(390, 844, mobile=True); br.go(BASE + '/cs/index.html', wait=2.0)
        ok(br.js("return document.documentElement.scrollWidth <= innerWidth && document.querySelector('.tsai').hidden === false"), 'mobil: tlačítko viditelné, stránka bez vodorovného posunu')
        br.shot(os.path.join(HERE, 'balicek', 'lokalne-NENAHRAVAT', 'nahled-mobil.jpg'))
        br.size(1280, 900); br.go(BASE + '/de/index.html', wait=2.0); br.js("TSAiWidget.open()", wait=.3)
        r = br.js("return [document.querySelector('.tsai-launcher').textContent, (document.querySelector('.tsai-note a')||{}).getAttribute && document.querySelector('.tsai-note a').getAttribute('href'), document.documentElement.lang]"); ok(r[0].replace('\u2060', '').find('Tessa') >= 0 and r[1] == '/de/datenschutz.html', 'němčina: popisek a zásady', r)
        br.shot(os.path.join(HERE, 'balicek', 'lokalne-NENAHRAVAT', 'nahled-desktop.jpg'))
    finally:
        br.close()
    time.sleep(1.5)
    db = sqlite3.connect(os.path.join(W, 'admin', 'data', 'crm.sqlite'))
    ok(db.execute('SELECT MAX(version) FROM schema_migrations').fetchone()[0] == 6 and db.execute('SELECT COUNT(*) FROM leads').fetchone()[0] == leadu0 + 1, 'CRM: migrace 6 proběhla, přibyl právě jeden lead')
    row = db.execute("SELECT l.source, l.status, i.form, i.message FROM leads l JOIN lead_inquiries i ON i.lead_id = l.id WHERE l.name = 'TEST Produkce'").fetchone()
    ok(row and row[0] == 'ai' and row[1] == 'novy' and row[2] == 'ai-asistent' and 'Návštěvník: Chci kontakt' in row[3], 'lead z asistenta: původ ai, stav Nový, přepis konverzace', row)
    ok(os.path.isdir(os.path.join(W, 'admin', 'data', 'crm-zalohy')) and any(f.startswith('pred-migraci-6') for f in os.listdir(os.path.join(W, 'admin', 'data', 'crm-zalohy'))), 'záloha CRM před migrací')
    n = db.execute("SELECT n.status FROM lead_notifications n JOIN leads l ON l.id = n.lead_id WHERE l.name = 'TEST Produkce'").fetchone()
    mail = open(MAILLOG).read() if os.path.isfile(MAILLOG) else ''
    ok(n and n[0] in ('predano', 'odeslano') and 'test@example.invalid' in mail, 'oznámení o poptávce odešlo přes CRM (lokálně jen do souboru)', (n, mail[:200]))
    ok(not os.path.isfile(os.path.join(PA, 'data', 'mail.log')), 'brána neposílá vlastní e-mail (žádné zdvojení)')
    # stávající poptávkový formulář funguje dál
    st, b = http('POST', '/poptavka.php', form={'name': 'TEST Formulář', 'email': 'form@example.invalid', 'msg': 'test', 'consent': '1', 'sluzby[]': 'Web', 'submission_id': 'prodtest-0123456789ab', 't': '9000'})
    r2 = db.execute("SELECT source FROM leads WHERE name = 'TEST Formulář'").fetchone()
    ok(st == 200 and r2 and r2[0] == 'web', 'kontaktní formulář webu dál zakládá lead s původem web', (st, b[:200]))
    st, b = http('GET', '/api/rezervace.php?r=config'); ok(st == 404, 'na produkci není nic z testovacích rezervací')
finally:
    srv.terminate(); mock.shutdown(); shutil.rmtree(tmp, ignore_errors=True)
json.dump({'pass': P, 'fail': F, 'results': RES, 'at': time.strftime('%Y-%m-%d %H:%M')}, open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'vysledky-testu.json'), 'w'), ensure_ascii=False, indent=1)
print('RESULT pass=%d fail=%d' % (P, F))
