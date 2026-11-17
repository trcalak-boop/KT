#!/usr/bin/env python3
"""Test balíčku AI konzultanta (3-balicek-ai) na simulovaném hostingu (kořen/sub/test + kořen/private/…) přes php -S.
Žádná skutečná služba: API Claude nahrazuje napodobenina na localhostu (falešný klíč), e-maily jen do logu
(SMTP host 127.0.0.1, falešný uživatel), kontrola spojení míří na localhost. Před spuštěním serveru se to ověří.
Poslední řádek: RESULT pass=N fail=M"""
import http.server, json, os, re, shutil, sqlite3, subprocess, sys, tempfile, threading, time, urllib.error, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.expanduser('~/TS-Studio/ts-library')
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); WEBSRC = '/Users/krystof/Downloads/T&S webstudio_28-10/web'
PKG = os.path.join(HERE, '3-balicek-ai'); BOOK = os.path.join(HERE, '2-balicek', 'nahrat-do-sub-test')
PORT, MOCK = 8181, 8182; BASE = 'http://localhost:%d' % PORT
KEY = 'sk-ant-api03-FALESNY-balicek-0000'
P = F = 0; RESULTS = []


def ok(c, msg, detail=''):
    global P, F
    RESULTS.append([bool(c), msg])
    if c: P += 1
    else: F += 1; print('FAIL ' + msg + (' | ' + str(detail)[:400] if detail else ''))


REQS = []


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        body = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}'); REQS.append(body)
        c = body['messages'][-1]['content']
        if isinstance(c, list):
            content, stop = [{'type': 'text', 'text': 'Formulář najdete níže.'}], 'end_turn'
        elif 'kontakt' in str(c).lower():
            content, stop = [{'type': 'tool_use', 'id': 'toolu_1', 'name': 'nabidnout_kontakt', 'input': {'shrnuti': 'Web pro kadeřnictví.'}}], 'tool_use'
        else:
            content, stop = [{'type': 'text', 'text': 'Podívejte se na Salon Šalvěj: https://tasstudio.eu/ukazky/salon-salvej.html'}], 'end_turn'
        b = json.dumps({'id': 'msg_1', 'type': 'message', 'role': 'assistant', 'model': body['model'], 'content': content, 'stop_reason': stop, 'stop_sequence': None,
                        'usage': {'input_tokens': 5000, 'output_tokens': 120, 'cache_read_input_tokens': 0, 'cache_creation_input_tokens': 0}}).encode()
        self.send_response(200); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(b))); self.end_headers(); self.wfile.write(b)


# ---------- obsah balíčku ----------
meta = json.load(open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'balicek.json')))
code = re.search(r': (AI-[0-9A-F]{6}-[0-9A-F]{6})', open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'kontrolni-kod.txt')).read()).group(1)
web_files = sorted(os.path.relpath(os.path.join(d, f), os.path.join(PKG, 'nahrat-do-sub-test')) for d, _, fs in os.walk(os.path.join(PKG, 'nahrat-do-sub-test')) for f in fs)
book_files = set(os.path.relpath(os.path.join(d, f), BOOK) for d, _, fs in os.walk(BOOK) for f in fs)
over = [f for f in web_files if f in book_files]
ok(over == ['ts-components.lock.json'], 'webroot: přepíše se jen ts-components.lock.json', over)
ok(all(f.startswith(('vendor/ts/ts-ai-consultant/', 'api/ai/')) or f in ('asistent.html', meta['check'], 'ts-components.lock.json') for f in web_files), 'webroot: jen AI komponenta, brána, testovací stránka, kontrola')
lock = json.load(open(os.path.join(PKG, 'nahrat-do-sub-test', 'ts-components.lock.json')))
ok({'ts-booking-server', 'ts-booking-widget', 'ts-ai-consultant', 'ts-mailer-php', 'ts-auth-php'} <= set(lock['components']), 'zámek komponent: rezervace zůstaly, přibyl AI konzultant', list(lock['components']))
for d in ('core', 'bin', 'config', 'migrations', 'sdk'): ok(os.path.isfile(os.path.join(PKG, 'nahrat-do-sub-test', 'vendor/ts/ts-ai-consultant', d, '.htaccess')), 'ochrana vendor/ts/ts-ai-consultant/%s/.htaccess' % d)
for f in ('.htaccess', 'data/.htaccess', 'vendor/.htaccess', 'vendor/autoload.php', 'znalosti.json', 'ai-consultant.php'): ok(os.path.isfile(os.path.join(PKG, 'nahrat-do-private-ai-test', f)), 'private: ' + f)
ok(not os.path.exists(os.path.join(PKG, 'nahrat-do-private-ai-test', 'claude-api-klic.txt')), 'klíč v balíčku není (vloží provozovatel)')
cfg0 = open(os.path.join(PKG, 'nahrat-do-private-ai-test', 'ai-consultant.php')).read()
ok("'redirectTo' => 'rezervace@tasstudio.eu'" in cfg0 and "'monthlyBudgetUsd' => 10.0" in cfg0 and "'model' => 'claude-opus-5-5'" in cfg0 and "'leadSink' => null" in cfg0, 'konfigurace: testovací schránka, strop 10 USD, model, bez produkčního CRM')
ok('sk-ant' not in cfg0 and 'smtp_pass\' => \'' not in cfg0, 'konfigurace bez klíče a hesla')
K = json.load(open(os.path.join(PKG, 'nahrat-do-private-ai-test', 'znalosti.json')))
blob = json.dumps(K, ensure_ascii=False)
ok(not re.search(r'\d[\d\s.,]*\s?(Kč|CZK|EUR|€)', blob) and 'cenaOd' not in blob, 'znalosti bez částek a cenových polí')
ok(K['pricePolicy']['noAmounts'] and len(K['references']) >= 8 and all(r['url'].startswith('https://') for r in K['references']), 'znalosti: pravidla bez částek, reference s absolutními odkazy', [r['url'] for r in K['references']])
miss = [r['url'] for r in K['references'] if r['url'].startswith('https://tasstudio.eu/') and not os.path.isfile(os.path.join(WEBSRC, r['url'][len('https://tasstudio.eu/'):]))]
ok(not miss, 'odkazy referencí existují na webu', miss)
miss = [u for p in K['pages'] for u in p['urls'].values() if not os.path.isfile(os.path.join(WEBSRC, u[len('https://tasstudio.eu/'):]))]
ok(not miss and len(K['pages'][0]['urls']) == 18, 'stránky webu v 18 jazycích existují', miss)

# ---------- simulovaný hosting ----------
tmp = tempfile.mkdtemp(prefix='tsai-balicek-'); R = os.path.join(tmp, 'koren')
SUB = os.path.join(R, 'sub', 'test'); PA = os.path.join(R, 'private', 'ai-test'); PB = os.path.join(R, 'private', 'booking-test')
shutil.copytree(BOOK, SUB); shutil.copytree(os.path.join(PKG, 'nahrat-do-sub-test'), SUB, dirs_exist_ok=True)
shutil.copytree(os.path.join(HERE, '2-balicek', 'nahrat-do-private-booking'), PB)
shutil.copytree(os.path.join(PKG, 'nahrat-do-private-ai-test'), PA)
open(os.path.join(PB, 'smtp-heslo.txt'), 'w').write('falesne-heslo-jen-test\n')
open(os.path.join(PA, 'claude-api-klic.txt'), 'w').write(KEY + '\n')
# testovací úpravy konfigurace (jen v kopii): napodobenina API, e-maily do logu, kontrola spojení na localhost, místní původ
c = cfg0.replace("'transport' => 'smtp'", "'transport' => 'log'").replace("'smtp_host' => 'smtp.websupport.cz'", "'smtp_host' => '127.0.0.1'").replace("'smtp_user' => 'rezervace@tasstudio.eu'", "'smtp_user' => 'test-uzivatel@example.invalid'")
c = c.replace("'maxRetries' => 1]", "'maxRetries' => 0, 'baseUrl' => 'http://127.0.0.1:%d']" % MOCK).replace("'allowedOrigins' => ['https://test.tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
c = c.replace("'until' => '%s']" % meta['until'], "'until' => '%s', 'probe' => 'tcp://127.0.0.1:%d']" % (meta['until'], MOCK))
open(os.path.join(PA, 'ai-consultant.php'), 'w').write(c)
bc = open(os.path.join(PB, 'booking-server.php')).read().replace("'transport' => 'smtp'", "'transport' => 'log'").replace("'smtp_host' => 'smtp.websupport.cz'", "'smtp_host' => '127.0.0.1'").replace("'allowedOrigins' => ['https://test.tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
open(os.path.join(PB, 'booking-server.php'), 'w').write(bc)
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo json_encode([$s["mail"]["transport"], $s["mail"]["smtp_host"], $s["claude"]["baseUrl"] ?? "", $s["check"]["probe"] ?? ""]);', os.path.join(PA, 'ai-consultant.php')], capture_output=True, text=True).stdout
ok(json.loads(chk) == ['log', '127.0.0.1', 'http://127.0.0.1:%d' % MOCK, 'tcp://127.0.0.1:%d' % MOCK], 'pojistka: testovací kopie nemíří na žádnou skutečnou službu', chk)
if json.loads(chk)[0] != 'log' or 'anthropic.com' in json.loads(chk)[2]:
    print('RESULT pass=%d fail=%d' % (P, F + 1)); sys.exit(1)

mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', SUB], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)


def http(method, path, body=None, headers=None, form=None):
    h = {'Accept': 'application/json'}; h.update(headers or {}); data = None
    if body is not None: data = json.dumps(body).encode(); h['Content-Type'] = 'application/json'
    if form is not None: data = urllib.parse.urlencode(form).encode(); h['Content-Type'] = 'application/x-www-form-urlencoded'
    req = urllib.request.Request(BASE + path, data=data, headers=h, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as r: return r.status, r.read().decode()
    except urllib.error.HTTPError as e: return e.code, e.read().decode()


import urllib.parse
try:
    st, b = http('GET', '/api/ai/chat.php?r=status'); ok(st == 200 and json.loads(b)['enabled'] is True, 'brána: status (klíč a SDK načtené z /private/ai-test)', b)
    st, b = http('POST', '/api/ai/chat.php?r=message', {'text': 'Chci web pro kadeřnictví', 'lang': 'cs'}); j = json.loads(b)
    ok(st == 200 and j['reply']['links'] and j['reply']['links'][0]['url'] == 'https://tasstudio.eu/ukazky/salon-salvej.html', 'zpráva přes SDK, odkaz na referenci povolen', b)
    sysp = REQS[-1]['system'][0]['text']
    ok('Salon Šalvěj' in sysp and 'UKÁZKOVÝ PROJEKT' in sysp and 'https://tasstudio.eu/cs/kontakt.html' in sysp and not re.search(r'\d\s?Kč', sysp), 'systémový prompt ze znalostí T&S, bez částek')
    st, b = http('POST', '/api/ai/chat.php?r=message', {'text': 'Chci kontakt', 'lang': 'de', 'conversation': j['conversation']}); j2 = json.loads(b)
    ok('contact' in j2['reply']['actions'], 'nástroj kontaktu', b)
    st, b = http('POST', '/api/ai/chat.php?r=lead', {'conversation': j['conversation'], 'submission_id': 'balicek-test-0000000001', 'name': 'Test Balíček', 'email': 'test@example.invalid', 'message': 'x', 'consent': True, 'consentText': 'Souhlas', 'lang': 'cs', 'hp': '', 'openedMs': 5000})
    ok(st == 200 and json.loads(b)['ok'], 'lead uložen', b)
    time.sleep(.5)
    log = open(os.path.join(PA, 'data', 'mail.log')).read() if os.path.isfile(os.path.join(PA, 'data', 'mail.log')) else ''
    ok('rezervace@tasstudio.eu' in log and 'Nov' in log, 'oznámení o leadu (testovací režim: log, adresát testovací schránka)', log[:300])
    ok(os.path.isfile(os.path.join(PA, 'data', 'ai-test.sqlite')) and not any(f.endswith('.sqlite') for d, _, fs in os.walk(SUB) for f in fs), 'databáze v /private/ai-test/data, ve webrootu žádná')
    st, b = http('GET', '/' + meta['check']); ok(st == 200 and 'Kontrolní kód' in b and '<table' not in b, 'kontrola bez kódu nic neukáže')
    st, b = http('POST', '/' + meta['check'], form={'code': code, 'a': 'check'})
    rows = re.findall(r'class="(ok|no)">\w+</td><td>([^<]*)', b)
    bad = [t for s, t in rows if s == 'no']
    ok(len(rows) >= 15 and not bad, 'kontrola prostředí: všechny řádky OK (%d)' % len(rows), bad)
    ok(KEY not in b and 'falesne-heslo' not in b, 'kontrola nezobrazí klíč ani heslo')
    st, b = http('POST', '/' + meta['check'], form={'code': code, 'a': 'live'}); ok('OK – odpověď:' in b, 'kontrola: jeden dotaz přes bránu (napodobenina)', re.sub(r'<[^>]+>', ' ', b)[:300])
    st, b = http('GET', '/api/rezervace.php?r=config'); ok(st == 200 and json.loads(b)['ok'], 'rezervace po přidání AI fungují dál')
    st, b = http('GET', '/vendor/ts/ts-ai-consultant/core/TsAiConsultant.php'); ok(b.strip() == '', 'PHP jádro nic nevypíše (Apache ho navíc zakáže .htaccess)')
    br = Browser(port=9381)
    try:
        br.size(1280, 900); br.go(BASE + '/asistent.html', wait=1.5)
        br.js("document.querySelector('.tsai-launcher').click()", wait=.4); br.js("document.querySelector('.tsai-chip').click()", wait=2.5)
        ok(br.js("return [...document.querySelectorAll('.tsai-assistant a')].some(a => a.href === 'https://tasstudio.eu/ukazky/salon-salvej.html' && a.target === '_blank')"), 'testovací stránka: widget odpoví s odkazem na referenci (nové okno)')
        br.js("document.querySelectorAll('.tsai-chip')[4] ? document.querySelectorAll('.tsai-chip')[4].click() : TSAiWidget.send('kontakt')", wait=.3)
        br.js("TSAiWidget.send('Chci kontakt')", wait=2.5)
        ok(br.js("const a=document.querySelector('.tsai-consent a'); return !!a && a.href === 'https://tasstudio.eu/cs/ochrana-osobnich-udaju.html'"), 'formulář: odkaz na zásady ochrany údajů tasstudio.eu')
        br.size(390, 844, mobile=True); br.go(BASE + '/asistent.html', wait=1.2)
        ok(br.js("return document.documentElement.scrollWidth <= innerWidth"), 'testovací stránka na mobilu bez vodorovného posunu')
    finally:
        br.close()
finally:
    srv.terminate(); mock.shutdown(); shutil.rmtree(tmp, ignore_errors=True)
json.dump({'pass': P, 'fail': F, 'results': RESULTS, 'at': time.strftime('%Y-%m-%d %H:%M')}, open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'vysledky-testu.json'), 'w'), ensure_ascii=False, indent=1)
print('RESULT pass=%d fail=%d' % (P, F))
