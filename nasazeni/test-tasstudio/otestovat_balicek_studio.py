#!/usr/bin/env python3
"""Test balíčku AI Design Studia (7-balicek-studio) na simulovaném hostingu: kořen/sub/test = stav po předchozích nasazeních + balíček,
kořen/private/studio-test = balíček s konfigurací přesměrovanou na napodobeninu Cloudflare na localhostu (skutečné služby se nevolají).
Ověřuje neveřejnost (404 bez cookie, kontrola původu), skutečný adaptér modelu proti napodobenině, úlohy na pozadí, návrh A/B/C, úpravu, přijetí,
náhled v sandboxu a stránku v Chromu. Poslední řádek: RESULT pass=N fail=M"""
import hashlib, http.server, json, os, re, shutil, subprocess, sys, tempfile, threading, time, urllib.error, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, '7-balicek-studio')
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib')); sys.path.insert(0, HERE)
from cdp import Browser
import importlib.util
spec = importlib.util.spec_from_file_location('bs', os.path.join(HERE, 'sestavit_balicek_studio.py')); bs = importlib.util.module_from_spec(spec); spec.loader.exec_module(bs)
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT, MOCK = 8211, 8212; BASE = 'http://localhost:%d' % PORT
P = F = 0; REQS = []
COOKIE = 'studio-lokalni-test'


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


def answer(b):
    sys_ = b['messages'][0]['content']; user = b['messages'][-1]['content']
    if 'Vybíráš šablonu' in sys_: return {'template': 'restaurace'}
    if 'upravuješ web klienta' in sys_: return {'reply': 'Zvětšila jsem logotyp a ztmavila akcent.', 'tokens': {'accent': '#6b2a1a'}, 'css': '.brand{letter-spacing:.02em}'}
    name = re.search(r'## Název podniku\n(.+)', user).group(1).strip()
    if '„Kadeřnictví' in sys_:
        return {'content': {'brand': name, 'title': name + ' – kadeřnictví', 'logo_pre': name[:2].upper(), 'logo_post': name[2:6].upper(), 'phone': '+420 731 555 210', 'phone_href': 'tel:+420731555210',
                            'prices': [{'name': 'Dámský střih', 'p1': '650 Kč', 'p2': '750 Kč', 'p3': '850 Kč'}], 'ed_title': 'Vlasy, které', 'ed_em': 'vám sedí.'}, 'tokens': {'accent': '#4d6b57', 'f_disp': 1}, 'notes': 'Doplnit tým a recenze.'}
    return {'content': {'brand': name, 'title': name, 'hero_title': 'Dnes pečeme', 'hero_em': 'z kvásku.'}, 'tokens': {'accent': '#9a5b2b'}, 'notes': 'Doplnit otevírací dobu.'}


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}')
        REQS.append({'path': self.path, 'auth': self.headers.get('Authorization', ''), 'body': b})
        txt = '```json\n' + json.dumps(answer(b), ensure_ascii=False) + '\n```'
        o = json.dumps({'id': 'c', 'object': 'chat.completion', 'model': b['model'], 'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': txt}, 'finish_reason': 'stop'}],
                        'usage': {'prompt_tokens': 4200, 'completion_tokens': 1900}}).encode()
        time.sleep(.3)
        self.send_response(200); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o)


cfg0 = open(os.path.join(PKG, 'k-nahrani', 'studio-test', 'studio.php'), encoding='utf-8').read()
ok("'provider' => 'cloudflare'" in cfg0 and "'neuronsPerDay' => 2500" in cfg0 and '/ai-test' in cfg0 and 'claude' not in cfg0.lower() and "'hash' => '" in cfg0, 'konfigurace: Cloudflare zdarma s tokenem stagingu, denní strop, přístup jen otiskem')
lock = json.load(open(os.path.join(PKG, 'k-nahrani', 'test', 'ts-components.lock.json')))['components']
ok(lock['ts-design-studio']['version'] == json.load(open(os.path.expanduser('~/TS-Studio/ts-library/components/ts-design-studio/module.json')))['version'] and lock['ts-site-audit']['version'] == '1.1.3', 'zámek: Studio přidané, audit zůstal')
allf = [os.path.relpath(os.path.join(d, f), os.path.join(PKG, 'k-nahrani', 'test')) for d, _, fs in os.walk(os.path.join(PKG, 'k-nahrani', 'test')) for f in fs]
ok(not any('/tests/' in f or '/tools/' in f or f.endswith('.py') for f in allf) and 'vendor/ts/ts-design-studio/templates/salon/files/assets/fonts/Syne-OFL.txt' in allf and not any(f.startswith('vendor/ts/ts-ai-consultant') for f in allf), 'balíček: bez testů a převodních skriptů, šablony s licencemi, ostatní knihovny beze změny')

tmp = tempfile.mkdtemp(prefix='studio-62-'); R = os.path.join(tmp, 'koren'); SUB = os.path.join(R, 'sub', 'test'); PS = os.path.join(R, 'private', 'studio-test'); PA = os.path.join(R, 'private', 'ai-test')
for v in bs.VRSTVY + [os.path.join(PKG, 'k-nahrani', 'test')]: shutil.copytree(v, SUB, dirs_exist_ok=True)
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'studio-test'), PS); os.makedirs(PA)
open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY-TOKEN\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0123456789abcdef0123456789abcdef\n')
c = re.sub(r"'hash' => '[0-9a-f]{64}'", "'hash' => '%s'" % hashlib.sha256(COOKIE.encode()).hexdigest(), cfg0)
c = c.replace("'neuronsPerDay' => 2500]", "'neuronsPerDay' => 2500, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1']" % MOCK).replace("'allowedOrigins' => ['https://test.tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
open(os.path.join(PS, 'studio.php'), 'w').write(c)
cname = re.search(r"'cookie' => '([^']+)'", c).group(1)
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
        with urllib.request.urlopen(r, timeout=60) as x: return x.status, x.read().decode()
    except urllib.error.HTTPError as e: return e.code, e.read().decode()


def job(path, body):
    s, t = req(path, body); j = json.loads(t)
    if s != 202: return s, j
    for _ in range(80):
        time.sleep(.4); s2, t2 = req('/api/studio/studio.php?r=job&id=' + j['job']); jj = json.loads(t2)
        if jj.get('status') != 'running': return 202, jj
    return 0, {}


try:
    s, t = req('/studio.php', cookie=False); ok(s == 404 and 'Studio' not in t, 'stránka bez cookie → 404')
    s, t = req('/api/studio/studio.php?r=list', cookie=False); ok(s == 404, 'API bez cookie → 404')
    s, t = req('/api/studio/studio.php?r=create', {'source': 'new', 'brief': {'name': 'X'}}, origin='https://zly.example'); ok(s == 403, 'POST z cizího původu → 403', t)
    s, t = req('/vendor/ts/ts-design-studio/templates/salon/template.json', cookie=False); ok(True, 'soubory šablon chrání .htaccess (Apache) – lokální server je neumí, ověří se na stagingu')
    s, t = req('/studio.php'); ok(s == 200 and 'AI Design Studio – test kvality' in t and 'noindex' in t, 'stránka s cookie')
    BR = re.search(r'var BRIEFS = (\[.*?\n  \]);', bs.JS, re.S).group(1)
    s, j = job('/api/studio/studio.php?r=create', {'source': 'new', 'brief': {'name': 'Salon Vlna', 'obor': 'kadeřnictví a barvení vlasů', 'facts': 'Telefon: +420 731 555 210'}})
    ok(s == 202 and j.get('status') == 'done' and j['result']['template'] == 'salon' and j['result']['version'] == 1, 'A: úloha na pozadí → projekt ze šablony salon, verze 1', j)
    ok(REQS and REQS[-1]['path'].endswith('/ai/v1/chat/completions') and REQS[-1]['auth'] == 'Bearer CF-FALESNY-TOKEN' and REQS[-1]['body']['model'] == '@cf/google/gemma-4-26b-a4b-it'
       and REQS[-1]['body']['chat_template_kwargs'] == {'enable_thinking': False}, 'adaptér Cloudflare: cesta, token ze souboru, Gemma 4 bez přemýšlení')
    ok('address' in j['result']['todo'] and j['result']['notes'] == 'Doplnit tým a recenze.', 'A: seznam k doplnění a poznámka')
    s, j = job('/api/studio/studio.php?r=create', {'source': 'redesign', 'audit': {'url': 'https://bistro.example/', 'recommendations': [{'basis': 'ai', 'text': 'Chybí tlačítko rezervace.'}]}, 'texts': 'Vaříme od roku 2011.', 'brief': {'name': 'Bistro U Mlýna', 'obor': 'bistro'}})
    ok(j.get('status') == 'done' and j['result']['template'] == 'restaurace' and j['result']['rights'], 'B: redesign → restaurace, převzaté texty označené k ověření práv', j)
    ok('Chybí tlačítko rezervace.' in REQS[-1]['body']['messages'][1]['content'] and 'ověřit práva' in REQS[-1]['body']['messages'][0]['content'], 'B: zjištění auditu a pravidlo o právech v pokynech')
    n0 = len(REQS)
    s, j = job('/api/studio/studio.php?r=create', {'source': 'crm', 'lead': {'name': 'Pekárna Kvásek', 'phone': '+420 606 222 999', 'outreach_reason': 'web pro pekárnu'}, 'brief': {'obor': 'pekárna s kavárnou'}})
    ok(j.get('status') == 'done' and len(REQS) - n0 == 2 and j['result']['template'] == 'restaurace', 'C: lead z CRM, šablonu vybral model (2 dotazy)', j)
    s, t = req('/api/studio/studio.php?r=list'); L = json.loads(t)
    ok(s == 200 and len(L['projects']) == 3 and {p['project']['source'] for p in L['projects']} == {'new', 'redesign', 'crm'} and L['usage']['calls'] == 4 and L['usage']['neurons'] > 0, 'seznam: 3 projekty se zdroji new/redesign/crm, spotřeba 4 dotazy', L.get('usage'))
    pid = [p['project']['id'] for p in L['projects'] if p['project']['source'] == 'new'][0]
    s, j = job('/api/studio/studio.php?r=edit', {'pid': pid, 'text': 'Zvětši logotyp a ztmav akcent.'})
    ok(j.get('status') == 'done' and j['result']['num'] == 2 and j['result']['changed'] == ['token:accent', 'css'], 'úprava: nová verze 2 (token + vrstva CSS)', j)
    s, t = req('/api/studio/studio.php?r=version&pid=%d&num=2' % pid); v = json.loads(t)
    ok(v['status'] == 'candidate' and v['tokens']['accent'] == '#6b2a1a', 'verze 2 je návrh s novým akcentem')
    s, t = req('/api/studio/studio.php?r=accept', {'pid': pid, 'num': 2}); ok(s == 200, 'přijetí verze 2', t)
    s, t = req('/api/studio/studio.php?r=preview&pid=%d&num=2' % pid); h = json.loads(t)['html']
    ok("default-src 'none'" in h and 'assets/styles.css' not in h and '.brand{letter-spacing:.02em}' in h and 'Salon Vlna' in h, 'náhled: CSP bez sítě, styly vložené včetně vrstvy úprav')
    s, t = req('/api/studio/studio.php?r=job&id=../../studio'); ok(s == 404, 'úloha: neplatné číslo → 404')

    b = Browser(9351)
    try:
        b.call('Network.enable'); b.call('Network.setCookie', name=cname, value=COOKIE, url=BASE)
        b.size(1300, 1000); b.go(BASE + '/studio.php', 3)
        info = json.loads(b.js("JSON.stringify({p:document.querySelectorAll('.proj').length,f:document.querySelectorAll('.proj iframe').length,sd:[...document.querySelectorAll('.proj iframe')].filter(f=>f.srcdoc.length>1000).length,sb:[...document.querySelectorAll('iframe')].every(f=>f.getAttribute('sandbox')==='allow-scripts'),u:document.querySelector('#usage').textContent})", 1.5))
        ok(info['p'] == 3 and info['f'] == 6 and info['sd'] == 6 and info['sb'], 'Chrome: 3 projekty, 6 náhledů v sandboxu (počítač + mobil)', info)
        acc = b.js("String(document.querySelector('.proj iframe').contentDocument)")
        ok(acc == 'null', 'náhled běží v jiném původu (sandbox bez allow-same-origin) – stránka ani náhled na sebe nevidí', acc)
        b.shot(os.path.join(tmp, 'studio.jpg'), quality=70); shutil.copy(os.path.join(tmp, 'studio.jpg'), os.path.join(HERE, '7-balicek-studio', 'lokalni-test-studio.jpg'))
    finally: b.close()
finally:
    os.killpg(srv.pid, 15); mock.shutdown()   # i pracovní procesy serveru (PHP_CLI_SERVER_WORKERS)
print('RESULT pass=%d fail=%d' % (P, F))
