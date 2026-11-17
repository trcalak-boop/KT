#!/usr/bin/env python3
"""Test balíčku Tessa T1 (5-balicek-tessa) na simulovaném hostingu: kořen/sub/test = stav po předchozích nasazeních + balíček,
kořen/private/ai-test = dřívější soukromá část + balíček. API = napodobenina na localhostu, e-maily jen do logu, kontrola spojení
na localhost. Ověřuje neveřejnost stagingu, personu ve widgetu i v promptu a funkčnost brány. Poslední řádek: RESULT pass=N fail=M"""
import http.cookiejar, http.server, json, os, re, shutil, subprocess, sys, tempfile, threading, time, urllib.error, urllib.parse, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, '5-balicek-tessa')
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT, MOCK = 8201, 8202; BASE = 'http://localhost:%d' % PORT
P = F = 0; RES = []; REQS = []
AIV = json.load(open(os.path.expanduser('~/TS-Studio/ts-library/components/ts-ai-consultant/module.json')))['version']


def ok(c, m, d=''):
    global P, F
    RES.append([bool(c), m])
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}'); REQS.append(b)
        REQS[-1]['_path'] = self.path; REQS[-1]['_auth'] = self.headers.get('Authorization', '')
        o = json.dumps({'id': 'c', 'object': 'chat.completion', 'model': b['model'], 'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': 'Dobrý den, jsem Tessa, AI asistentka. Hlasový režim zatím neumím.'}, 'finish_reason': 'stop'}],
                        'usage': {'prompt_tokens': 3000, 'completion_tokens': 50}}).encode()
        self.send_response(200); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o)


txt = open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'pristup.txt')).read()
token = re.search(r'pristup=([A-Za-z0-9_-]+)', txt).group(1); code = re.search(r'(AI-[0-9A-F]{6}-[0-9A-F]{6})', txt).group(1)
meta = json.load(open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'balicek.json')))
files = meta['test_files']
ok('asistent.php' in files and 'vendor/ts/ts-ai-consultant/core/TsAiConsultant.php' in files and not any(f.startswith('api/') for f in files), 'balíček: stránka stagingu, portrét, knihovna; brána beze změny')
lock = json.load(open(os.path.join(PKG, 'k-nahrani', 'test', 'ts-components.lock.json')))
ok(lock['components']['ts-ai-consultant']['version'] == AIV and 'ts-booking-server' in lock['components'], 'zámek: AI %s, rezervace zůstaly' % AIV)
cfg0 = open(os.path.join(PKG, 'k-nahrani', 'ai-test', 'ai-consultant.php')).read()
ok("'access' => ['cookie' => 'tsai_pristup'" in cfg0 and token not in cfg0 and "'leadSink' => null" in cfg0 and "'persona' => ['name' => 'Tessa'" in cfg0 and "'provider' => 'cloudflare'" in cfg0 and 'claude' not in cfg0.lower(), 'konfigurace: Cloudflare zdarma, přístup jen otiskem, persona, bez CRM a bez Claude')

tmp = tempfile.mkdtemp(prefix='tessa-t1-'); R = os.path.join(tmp, 'koren'); SUB = os.path.join(R, 'sub', 'test'); PA = os.path.join(R, 'private', 'ai-test'); PB = os.path.join(R, 'private', 'booking-test')
for v in (os.path.join(HERE, '2-balicek', 'nahrat-do-sub-test'), os.path.join(HERE, '3-balicek-ai', 'nahrat-do-sub-test'), os.path.join(HERE, '3-balicek-ai', 'k-nahrani', 'oprava-1.0.1', 'test'), os.path.join(PKG, 'k-nahrani', 'test')):
    shutil.copytree(v, SUB, dirs_exist_ok=True)
os.remove(os.path.join(SUB, 'asistent.html'))
shutil.copytree(os.path.join(HERE, '3-balicek-ai', 'nahrat-do-private-ai-test'), PA); shutil.copytree(os.path.join(PKG, 'k-nahrani', 'ai-test'), PA, dirs_exist_ok=True)
shutil.copytree(os.path.join(HERE, '2-balicek', 'nahrat-do-private-booking'), PB)
open(os.path.join(PB, 'smtp-heslo.txt'), 'w').write('falesne\n'); open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY-TOKEN\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0123456789abcdef0123456789abcdef\n')
c = cfg0.replace("'transport' => 'smtp'", "'transport' => 'log'").replace("'smtp_host' => 'smtp.websupport.cz'", "'smtp_host' => '127.0.0.1'").replace("'smtp_user' => 'rezervace@tasstudio.eu'", "'smtp_user' => 'x@example.invalid'")
c = c.replace("'timeout' => 30, 'connectTimeout' => 6]", "'timeout' => 30, 'connectTimeout' => 6, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1']" % MOCK).replace("'allowedOrigins' => ['https://test.tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
c = c.replace("'until' => '%s']" % meta['until'], "'until' => '%s', 'probe' => 'tcp://127.0.0.1:%d']" % (meta['until'], MOCK))
open(os.path.join(PA, 'ai-consultant.php'), 'w').write(c)
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo json_encode([$s["mail"]["transport"], $s["cloudflare"]["baseUrl"] ?? "", $s["check"]["probe"] ?? ""]);', os.path.join(PA, 'ai-consultant.php')], capture_output=True, text=True).stdout
ok(json.loads(chk) == ['log', 'http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1' % MOCK, 'tcp://127.0.0.1:%d' % MOCK], 'pojistka: kopie nemíří na skutečné služby', chk)
if 'cloudflare.com' in chk or 'anthropic.com' in chk or 'smtp' in json.loads(chk)[0]: sys.exit(1)
mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', SUB], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)


class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


def get(path, h=None, data=None, opener=None):
    try:
        r = (opener or urllib.request.build_opener(NoRedir)).open(urllib.request.Request(BASE + path, headers=h or {}, data=data), timeout=30); return r.status, r.read().decode('utf-8', 'replace'), r.headers
    except urllib.error.HTTPError as e: return e.code, e.read().decode(), e.headers


try:
    st, b, _ = get('/asistent.php'); ok(st == 404 and 'Tessa' not in b, 'bez klíče: stránka stagingu 404 a nic neprozradí')
    st, b, _ = get('/asistent.php?pristup=spatny'); ok(st == 404, 'špatný klíč: 404')
    st, b, _ = get('/api/ai/chat.php?r=status'); ok(st == 403, 'bez cookie: brána 403 (tlačítko se neukáže)', st)
    st, b, _ = get('/api/ai/chat.php?r=message', {'Content-Type': 'application/json'}, b'{"text":"x","lang":"cs"}'); ok(st == 403 and not REQS, 'bez cookie: zpráva odmítnuta, AI se nevolá')
    st, b, hd = get('/asistent.php?pristup=' + token)
    sc = hd.get('Set-Cookie', '')
    ok(st == 303 and hd.get('Location') == '/asistent.php' and 'tsai_pristup=' in sc and 'secure' in sc.lower() and 'httponly' in sc.lower() and 'samesite=strict' in sc.lower(), 'tajný odkaz: přesměrování a cookie (Secure, HttpOnly, SameSite=Strict)', sc)
    ck = {'Cookie': 'tsai_pristup=' + token}
    st, b, _ = get('/asistent.php', ck); ok(st == 200 and '"name": "Tessa"' in b and '"mascot": true' in b and ('ts-ai-widget.js?v=%s' % AIV) in b, 's cookie: stránka stagingu s Tessou')
    st, b, _ = get('/' + meta['check'], {'Content-Type': 'application/x-www-form-urlencoded'}, urllib.parse.urlencode({'code': code, 'a': 'check'}).encode())
    bad = [t for s, t in re.findall(r'class="(ok|no)">\w+</td><td>([^<]*)', b) if s == 'no']; ok(st == 200 and '<table' in b and not bad, 'kontrola prostředí: vše OK', bad)
    st, b, _ = get('/' + meta['check'], {'Content-Type': 'application/x-www-form-urlencoded'}, urllib.parse.urlencode({'code': code, 'a': 'live'}).encode())
    ok('OK – odpověď:' in b, 'kontrola: jeden dotaz projde i bez cookie stagingu (chráněno kódem)')
    br = Browser(port=9401)
    try:
        br.size(1280, 900); br.go(BASE + '/asistent.php?pristup=' + token, wait=2.0)
        r = br.js("return (()=>{const l=document.querySelector('.tsai-launcher'); return [location.pathname, document.querySelector('.tsai').hidden, l.textContent, l.getAttribute('aria-label'), !!l.querySelector('.tsai-bot svg')]})()")
        ok(r[0] == '/asistent.php' and r[1] is False and r[2] == 'Tessa' and r[3] == 'Tessa, AI asistentka T&S Studia' and r[4], 'prohlížeč: po tajném odkazu tlačítko „Tessa“ s animovaným robůtkem', r)
        br.js("TSAiWidget.open()", wait=.3)
        r = br.js("return [document.querySelector('.tsai-title').textContent, document.querySelector('.tsai-role').textContent, document.querySelector('.tsai-log .tsai-assistant').textContent]")
        ok(r[0] == 'Tessa' and r[1] == 'AI asistentka T&S Studia' and 'jsem Tessa, AI asistentka T&S Studia' in r[2], 'hlavička: jméno, trvalé označení AI, úvod Tessy', r)
        br.js("TSAiWidget.send('Kdo jsi a umíš mluvit?')", wait=2.5)
        last = REQS[-1] if REQS else {}
        sysp = last['messages'][0]['content'] if last.get('messages') else ''
        ok(last.get('_path', '').endswith('/accounts/0123456789abcdef0123456789abcdef/ai/v1/chat/completions') and last.get('_auth') == 'Bearer CF-FALESNY-TOKEN' and last.get('model') == '@cf/meta/llama-3.3-70b-instruct-fp8-fast' and 'tools' not in last, 'Cloudflare: adresa účtu, token, model, bez nástrojů')
        ok('[[KONTAKT:' in sysp and len(sysp) < 16000, 'úsporný prompt se značkou kontaktu (%d znaků)' % len(sysp))
        ok(sysp.startswith('# Identita\nJsi Tessa') and 'ženském rodě' in sysp and 'umělá inteligence (Tessa)' in sysp and 'hlasový režim (mluvení a poslech)' in sysp.split('Zatím neumíš')[-1], 'prompt: persona, rod, pravidlo o AI, hlas mezi nedostupnými funkcemi')
        ok('Ceny: nikdy neuváděj' in sysp and 'Salon Šalvěj' in sysp, 'prompt: pravidla brány a znalosti zůstaly')
        for lang, role in (('en', 'AI assistant at T&S Studio'), ('ar', 'مساعدة ذكاء اصطناعي لدى T&S Studio'), ('ja', 'T&S Studio の AIアシスタント')):
            br.js("{const s=document.getElementById('tsai-lang'); s.value='%s'; s.dispatchEvent(new Event('change'))}" % lang, wait=.2)
            r = br.js("return [document.querySelector('.tsai-role').textContent, document.getElementById('tsai-panel').dir]")
            ok(r[0] == role and (r[1] == 'rtl') == (lang == 'ar'), 'jazyk %s: označení AI pod jménem' % lang, r)
        br.size(390, 844, mobile=True); br.go(BASE + '/asistent.php', wait=1.5)
        ok(br.js("return document.documentElement.scrollWidth <= innerWidth && document.querySelector('.tsai').hidden === false"), 'mobil: bez vodorovného posunu, tlačítko viditelné')
        br.js("TSAiWidget.open()", wait=.4); br.shot(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'nahled-mobil.jpg'))
        br.size(1280, 900); br.go(BASE + '/asistent.php', wait=1.5); br.js("{const s=document.getElementById('tsai-lang'); s.value='cs'; s.dispatchEvent(new Event('change'))} TSAiWidget.open()", wait=.4)
        br.shot(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'nahled-desktop.jpg'))
    finally:
        br.close()
    st, b, _ = get('/api/rezervace.php?r=config'); ok(st == 200, 'rezervace na testu fungují dál')
finally:
    srv.terminate(); mock.shutdown(); shutil.rmtree(tmp, ignore_errors=True)
json.dump({'pass': P, 'fail': F, 'results': RES}, open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'vysledky-testu.json'), 'w'), ensure_ascii=False, indent=1)
print('RESULT pass=%d fail=%d' % (P, F))
