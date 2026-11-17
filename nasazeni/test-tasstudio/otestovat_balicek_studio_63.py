#!/usr/bin/env python3
"""Test balíčku 6.3 (7-balicek-studio/oprava-6.3) na simulovaném hostingu: kořen/sub/test = stav stagingu po 6.2 + balíček,
kořen/private/studio-test = konfigurace z 6.2 přesměrovaná na napodobeninu Cloudflare (skutečné služby se nevolají).
Ověřuje neveřejnost, ochranu POST, skutečný adaptér proti napodobenině a rozhraní administrace v Chromu. Poslední řádek: RESULT pass=N fail=M"""
import hashlib, http.server, json, os, re, shutil, subprocess, sys, tempfile, threading, time, urllib.error, urllib.request
import importlib.util
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, '7-balicek-studio')
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
spec = importlib.util.spec_from_file_location('b63', os.path.join(HERE, 'sestavit_balicek_studio_63.py')); b63 = importlib.util.module_from_spec(spec); spec.loader.exec_module(b63)
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT, MOCK = 8221, 8222; BASE = 'http://localhost:%d' % PORT
P = F = 0; REQS = []; COOKIE = 'studio-lokalni-test-63'


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}'); REQS.append({'path': self.path, 'auth': self.headers.get('Authorization', ''), 'body': b})
        sys_ = b['messages'][0]['content']
        a = {'reply': 'Akcent jsem ztmavila.', 'tokens': {'accent': '#2f4f3f'}} if 'upravuješ web' in sys_ else \
            {'content': {'brand': 'Salon Vlna', 'title': 'Salon Vlna', 'phone': '+420 731 555 210', 'phone_href': 'tel:+420731555210'}, 'tokens': {'accent': '#4d6b57'}, 'notes': 'Doplnit ceny.'}
        o = json.dumps({'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': json.dumps(a, ensure_ascii=False)}, 'finish_reason': 'stop'}], 'usage': {'prompt_tokens': 4000, 'completion_tokens': 1500}}).encode()
        self.send_response(200); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o)


files = [os.path.relpath(os.path.join(d, f), os.path.join(PKG, 'oprava-6.3', 'test')) for d, _, fs in os.walk(os.path.join(PKG, 'oprava-6.3', 'test')) for f in fs]
ok('studio.php' in files and 'api/studio/studio.php' in files and 'vendor/ts/ts-design-studio/public/ts-studio.js' in files and 'vendor/ts/ts-design-studio/core/TsStudioApi.php' in files, 'balíček: stránka, API, rozhraní a třída API')
ok(not any('/tests/' in f or '/tools/' in f or f.startswith('vendor/ts/ts-ai-consultant') or f.startswith('vendor/ts/ts-site-audit') for f in files), 'balíček: bez testů a nástrojů, ostatní knihovny beze změny')
ok(json.load(open(os.path.join(PKG, 'oprava-6.3', 'test', 'ts-components.lock.json')))['components']['ts-design-studio']['version'] == '0.3.0', 'zámek: ts-design-studio 0.3.0')

tmp = tempfile.mkdtemp(prefix='studio-63-'); R = os.path.join(tmp, 'koren'); SUB = os.path.join(R, 'sub', 'test'); PS = os.path.join(R, 'private', 'studio-test'); PA = os.path.join(R, 'private', 'ai-test')
for v in b63.VRSTVY + [os.path.join(PKG, 'oprava-6.3', 'test')]: shutil.copytree(v, SUB, dirs_exist_ok=True)
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'studio-test'), PS); os.makedirs(PA)
open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY-TOKEN\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0123456789abcdef0123456789abcdef\n')
cfg = open(os.path.join(PS, 'studio.php'), encoding='utf-8').read()
c = re.sub(r"'hash' => '[0-9a-f]{64}'", "'hash' => '%s'" % hashlib.sha256(COOKIE.encode()).hexdigest(), cfg)
c = c.replace("'neuronsPerDay' => 2500]", "'neuronsPerDay' => 2500, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/x/ai/v1']" % MOCK).replace("'allowedOrigins' => ['https://test.tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
open(os.path.join(PS, 'studio.php'), 'w').write(c); cname = re.search(r"'cookie' => '([^']+)'", c).group(1)
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo $s["studio"]["ai"]["baseUrl"];', os.path.join(PS, 'studio.php')], capture_output=True, text=True).stdout
ok(chk.startswith('http://127.0.0.1:%d/' % MOCK), 'pojistka: kopie nemíří na skutečný Cloudflare', chk)
if 'cloudflare.com' in chk: sys.exit(1)
if subprocess.run(['lsof', '-nP', '-iTCP:%d' % PORT, '-sTCP:LISTEN'], capture_output=True).stdout: sys.exit('port %d je obsazený' % PORT)
mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', SUB], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=dict(os.environ, PHP_CLI_SERVER_WORKERS='4'), start_new_session=True); time.sleep(1.2)


def req(path, body=None, cookie=True, origin=BASE):
    h = {'Content-Type': 'application/json'}
    if cookie: h['Cookie'] = '%s=%s' % (cname, COOKIE)
    if origin and body is not None: h['Origin'] = origin
    r = urllib.request.Request(BASE + path, data=json.dumps(body).encode() if body is not None else None, headers=h)
    try:
        with urllib.request.urlopen(r, timeout=60) as x: return x.status, x.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode()


b = None
try:
    s, t = req('/studio.php', cookie=False); ok(s == 404 and 'ts-studio' not in t, 'stránka bez cookie → 404')
    s, t = req('/api/studio/studio.php?r=list', cookie=False); ok(s == 404, 'API bez cookie → 404')
    s, t = req('/api/studio/studio.php?r=create', {'name': 'X', 'template': 'salon'}, origin='https://zly.example'); ok(s == 403, 'POST z cizího původu → 403', t)
    s, t = req('/studio.php'); ok(s == 200 and 'id="ts-studio"' in t and 'ts-studio.js?v=0.3.0' in t, 'stránka administrace s cookie')
    b = Browser(9371)
    b.call('Network.enable'); b.call('Network.setCookie', name=cname, value=COOKIE, url=BASE)
    b.size(1400, 1000)
    def until(expr, t=20):
        for _ in range(int(t / .3)):
            if b.js('!!(' + expr + ')'): return True
            time.sleep(.3)
        return False
    b.go(BASE + '/studio.php', 2)
    ok(until("document.querySelector('[data-act=new]')"), 'rozhraní se načetlo')
    b.js("document.querySelector('[data-act=new]').click()", .5)
    b.js("var f=document.querySelector('[data-form=ai]'); f.name.value='Salon Vlna'; f.obor.value='kadeřnictví'; f.querySelector('[type=submit]').click()", 1)
    ok(until("/^#\\/p\\/\\d+$/.test(location.hash) && document.querySelector('.tss-frame iframe') && document.querySelector('.tss-frame iframe').srcdoc.indexOf('Salon Vlna')>0", 30), 'návrh s AI přes úlohu → editor s náhledem')
    ok(REQS and REQS[-1]['auth'] == 'Bearer CF-FALESNY-TOKEN' and REQS[-1]['body']['chat_template_kwargs'] == {'enable_thinking': False}, 'adaptér Cloudflare: token ze souboru, Gemma bez přemýšlení')
    b.js("var f=document.querySelector('.tss-ask'); f.text.value='Akcent tmavší.'; f.querySelector('button').click()", 1)
    ok(until("document.querySelectorAll('.tss-vers [data-ver]').length===2 && document.querySelector('.tss-msgs').textContent.indexOf('Akcent jsem ztmavila')>=0", 30), 'úprava v konverzaci → návrh verze 2')
    b.js("document.querySelector('[data-act=accept]').click()", 1.5)
    ok(until("document.querySelector('.tss-main').textContent.indexOf('přijatá verze 2')>=0"), 'přijetí verze 2')
    b.js("document.querySelector('[data-tab=diff]').click()", 1.2)
    ok(until("document.querySelector('.tss-dfile pre .add')"), 'rozdíl verze 2 proti 1')
    pid = int(b.js("location.hash.split('/')[2]"))
    s, t = req('/api/studio/studio.php?r=export&pid=%d&num=2' % pid); ok(s == 200 and t[:2] == 'PK', 'export ZIP')
    s, t = req('/api/studio/studio.php?r=list'); L = json.loads(t); ok(L['usage']['calls'] == 2 and L['usage']['limit'] == 2500, 'spotřeba: 2 dotazy, strop 2 500', L['usage'])
    b.shot(os.path.join(PKG, 'lokalni-test-studio-63.jpg'), quality=70)
finally:
    if b: b.close()
    os.killpg(srv.pid, 15); mock.shutdown()
print('RESULT pass=%d fail=%d' % (P, F))
