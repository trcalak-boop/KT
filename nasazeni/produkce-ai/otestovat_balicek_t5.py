#!/usr/bin/env python3
"""Test doplňkového balíčku T5 (audit webu v chatu a v administraci) na simulované produkci: kořen/web = produkce T4 (git HEAD projektu),
kořen/private/ai = produkční konfigurace z balíčku T4 (bez klíčů); nejdřív běží stará verze (databáze), pak se nahraje balicek-t5.
Žádná skutečná služba: Google PageSpeed, Cloudflare (chat i hodnocení snímku) a ElevenLabs = napodobenina na localhostu, falešné klíče,
DNS podstrčené. Skutečné adaptéry (PageSpeed, Cloudflare) i zpevnění open_basedir běží. Poslední řádek: RESULT pass=N fail=M"""
import http.server, io, json, os, re, shutil, subprocess, sys, tarfile, tempfile, threading, time, urllib.error, urllib.parse, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek-t5'); T4 = os.path.join(HERE, 'balicek-t4', 'k-nahrani', 'private-ai'); T1 = os.path.join(HERE, 'balicek', 'k-nahrani', 'ai')
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; PHP = os.path.join(PROJ, 'tools', 'php', 'php')
LIB = os.path.expanduser('~/TS-Studio/ts-library'); sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PORT, MOCK = 8197, 8198; BASE = 'http://localhost:%d' % PORT
P = F = 0; RES = []; CHAT = []; PSI = []; VIS = []
GKEY = 'google-FALESNY-0000'


def ok(c, m, d=''):
    global P, F
    RES.append([bool(c), m])
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


sys.path.insert(0, os.path.join(LIB, 'components', 'ts-site-audit'))
SAMPLE = {s: json.loads(subprocess.run([PHP, '-r', 'require %r; echo json_encode(TsAuditFakeMeter::sample(%r));' % (os.path.join(LIB, 'components', 'ts-site-audit', 'core', 'bootstrap.php'), s)], capture_output=True, text=True).stdout) for s in ('mobile', 'desktop')}


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def send(self, code, body, ctype='application/json'):
        b = body if isinstance(body, bytes) else json.dumps(body).encode()
        self.send_response(code); self.send_header('content-type', ctype); self.send_header('content-length', str(len(b))); self.end_headers(); self.wfile.write(b)

    def do_GET(self):
        q = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query); PSI.append(q)
        if q.get('key', [''])[0] != GKEY: return self.send(400, {'error': {'code': 400, 'message': 'API key not valid.'}})
        self.send(200, SAMPLE['desktop' if q.get('strategy', [''])[0] == 'desktop' else 'mobile'])

    def do_POST(self):
        if '/v1/text-to-speech/' in self.path: return self.send(200, b'ID3' + b'\x00' * 600, 'audio/mpeg')
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}')
        img = any(isinstance(m.get('content'), list) and any(p.get('type') == 'image_url' for p in m['content']) for m in b.get('messages', []))
        if img:
            VIS.append(b); txt = '{"items":[{"area":"Výzva k akci","text":"Tlačítko rezervace by mělo být výraznější a nad ohybem stránky.","priority":1},{"area":"Kontrast","text":"Šedý text na béžovém pozadí je hůř čitelný, ztmavte ho.","priority":2}]}'
        else:
            CHAT.append(b); txt = 'Dobrý den, pod touto odpovědí je tlačítko pro spuštění auditu.'
        self.send(200, {'id': 'c', 'object': 'chat.completion', 'model': b['model'], 'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': txt}, 'finish_reason': 'stop'}], 'usage': {'prompt_tokens': 600, 'completion_tokens': 120}})


meta = json.load(open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'balicek.json')))
files = open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'soubory-web.txt')).read().split()
ok([f for f in files if f.startswith('admin/')] == ['admin/audity-api.php', 'admin/audity.php'] and not any(f.startswith('data/') or f == 'ts-components.lock.json' for f in files), 'z administrace jen stránky Audity, žádná data ani zámek komponent')
ok(sorted(os.listdir(os.path.join(PKG, 'k-nahrani', 'private-audit'))) == ['.htaccess', 'data', 'site-audit.php'] and not any('klic' in f or 'token' in f for d, _, fs in os.walk(os.path.join(PKG, 'k-nahrani')) for f in fs), '/private/audit: konfigurace a ochrana složek – žádný klíč ani token nikde v balíčku')
cfg = open(os.path.join(PKG, 'k-nahrani', 'private-ai', 'ai-consultant.php')).read(); old = open(os.path.join(T4, 'ai-consultant.php')).read()
ok(re.search(r"'salt' => '([0-9a-f]+)'", cfg).group(1) == re.search(r"'salt' => '([0-9a-f]+)'", old).group(1) and "'audit' => ['enabled' => true" in cfg and cfg.replace(cfg[cfg.index("    /* audit webu"):cfg.index("    }],\n") + 8], '') == old, 'konfigurace asistentky: stejná jako T4, přibyl jen oddíl audit')

tmp = tempfile.mkdtemp(); W = os.path.join(tmp, 'web'); PA = os.path.join(tmp, 'private', 'ai'); PU = os.path.join(tmp, 'private', 'audit')
tarfile.open(fileobj=io.BytesIO(subprocess.run(['git', '-C', PROJ, 'archive', 'HEAD', 'web'], capture_output=True, check=True).stdout)).extractall(tmp)
shutil.copytree(os.path.join(PROJ, 'web', 'data'), os.path.join(W, 'data'), dirs_exist_ok=True)
ok('ts-ai-widget.js?v=1.8.0' in open(os.path.join(W, 'cs', 'index.html'), encoding='utf-8').read(), 'výchozí stav = produkce T4 (widget 1.8.0)')
os.makedirs(os.path.join(PA, 'tessa')); os.makedirs(os.path.join(PA, 'data'))
shutil.copy(os.path.join(T4, 'ai-consultant.php'), PA); shutil.copy(os.path.join(T4, 'tessa', 'capabilities.json'), os.path.join(PA, 'tessa'))
for f in ('znalosti.json', 'tessa/persona.md'): shutil.copy(os.path.join(T1, f), os.path.join(PA, f))
for f, v in (('cloudflare-token.txt', 'CF-FALESNY'), ('cloudflare-ucet.txt', '0' * 32), ('elevenlabs-klic.txt', 'el-FALESNY'), ('elevenlabs-hlas.txt', 'fiovsrMYQB7LKXX3ytcI')): open(os.path.join(PA, f), 'w').write(v + '\n')


def patch_ai():
    c = open(os.path.join(PA, 'ai-consultant.php')).read()
    c = c.replace("'timeout' => 30, 'connectTimeout' => 6]", "'timeout' => 30, 'connectTimeout' => 6, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0/ai/v1']" % MOCK)
    c = c.replace("'model' => 'eleven_flash_v2_5',", "'model' => 'eleven_flash_v2_5', 'baseUrl' => 'http://127.0.0.1:%d'," % MOCK)
    c = c.replace("'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
    open(os.path.join(PA, 'ai-consultant.php'), 'w').write(c)


patch_ai()
mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()
srv0 = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)
ok(json.loads(urllib.request.urlopen(BASE + '/api/ai/chat.php?r=status', timeout=10).read()).get('enabled') is True, 'před nahráním: běží stará brána (T4)'); srv0.terminate(); time.sleep(.5)
# nahrání balíčku + klíč Google od provozovatele
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'web'), W, dirs_exist_ok=True); shutil.copytree(os.path.join(PKG, 'k-nahrani', 'private-ai'), PA, dirs_exist_ok=True)
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'private-audit'), PU)
open(os.path.join(PU, 'google-api-klic.txt'), 'w').write(GKEY + '\n'); patch_ai()
u = open(os.path.join(PU, 'site-audit.php')).read()
u = u.replace("'timeout' => 100, 'issuesPerCategory' => 5]", "'timeout' => 100, 'issuesPerCategory' => 5, 'baseUrl' => 'http://127.0.0.1:%d/pagespeedonline/v5/runPagespeed']" % MOCK)
u = u.replace("'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 700]", "'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 700, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0/ai/v1']" % MOCK)
u = u.replace("'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],", "'allowedOrigins' => ['%s'], 'resolve' => fn ($h) => ['93.184.216.34'], 'processAfterResponse' => true," % BASE)
open(os.path.join(PU, 'site-audit.php'), 'w').write(u)
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo $s["meter"]["baseUrl"] ?? "", "|", $s["vision"]["baseUrl"] ?? "";', os.path.join(PU, 'site-audit.php')], capture_output=True, text=True).stdout
ok(chk.count('127.0.0.1:%d' % MOCK) == 2, 'pojistka: kopie nemíří na skutečné Google ani Cloudflare', chk)
if 'googleapis' in chk or 'cloudflare.com' in chk: sys.exit(1)

srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)
STUB = re.search(r"STUB = '''<script>\n(.*?)</script>'''", open(os.path.join(LIB, 'components', 'ts-ai-consultant', 'tests', 'http_test.py'), encoding='utf-8').read(), re.S).group(1)
br = Browser(9365)


def api(path, body=None, ip_hdr=None):
    h = {'Content-Type': 'application/json', 'Accept': 'application/json', 'Origin': BASE}
    try:
        r = urllib.request.urlopen(urllib.request.Request(BASE + path, data=json.dumps(body).encode() if body is not None else None, headers=h, method='POST' if body is not None else 'GET'), timeout=60)
        return r.status, json.loads(r.read() or b'{}')
    except urllib.error.HTTPError as e: return e.code, json.loads(e.read() or b'{}')


try:
    ok(api('/api/ai/chat.php?r=status')[1].get('enabled') is True, 'brána asistentky po nahrání běží')
    br.call('Page.addScriptToEvaluateOnNewDocument', source=STUB)
    br.size(1280, 900); br.go(BASE + '/cs/index.html', wait=1.8)
    ok(br.js("return document.querySelector('script[src*=\"ts-ai-widget.js\"]').getAttribute('src').endsWith('?v=" + meta['aiv'] + "')"), 'web: nový widget ' + meta['aiv'])
    br.js("TSAiWidget.open(); TSAiWidget.send('Dobrý den, náš web je www.kavarna-u-mostu.cz – jak na tom je?')", wait=2.5)
    for _ in range(10):
        if br.js("return !!document.querySelector('.tsai-audit-btn')"): break
        time.sleep(.5)
    lbl = br.js("const b=document.querySelector('.tsai-audit-btn'); return b ? b.textContent : ''") or ''
    ok('kavarna-u-mostu.cz' in lbl.replace('\u2060', ''), 'chat: u adresy webu tlačítko „Spustit audit webu …“', lbl)
    ok('nikdy netvrď, že ses na něj podívala' in json.dumps(CHAT[-1]['messages'][0], ensure_ascii=False), 'model dostal pokyn, že web neviděla')
    br.js("document.querySelector('.tsai-audit-btn').click()", wait=1)
    for _ in range(15):
        time.sleep(2.5)
        if br.js("return !!document.querySelector('.tsai-ascores') || /nepodařil|limit|nelze/.test((document.querySelector('.tsai-audit')||{}).textContent||'')"): break
    r = br.js("return [[...document.querySelectorAll('.tsai-ascores td')].map(e=>e.textContent), document.querySelectorAll('.tsai-audit li').length, (document.querySelector('.tsai-audit')||{}).textContent]")
    ok(r[0] == ['54', '81', '96', '90', '88', '81', '96', '90'] and r[1] == 5, 'chat: audit doběhl a souhrn se ukázal (skóre, 3 zjištění, 2 postřehy AI)', r)
    ok(len(PSI) == 2 and {q['strategy'][0] for q in PSI} == {'mobile', 'desktop'} and PSI[0]['url'][0] == 'https://www.kavarna-u-mostu.cz/' and PSI[0]['locale'][0] == 'cs' and len(PSI[0]['category']) == 4,
       'Google PageSpeed (napodobenina): mobil i desktop, adresa, jazyk, 4 kategorie, klíč ze souboru provozovatele', PSI[:1])
    ok(len(VIS) == 1 and VIS[0]['model'] == '@cf/google/gemma-4-26b-a4b-it' and VIS[0].get('chat_template_kwargs') == {'enable_thinking': False}, 'hodnocení snímku: Gemma 4 bez „přemýšlení“ s obrázkem')
    shots = os.listdir(os.path.join(PU, 'data', 'snimky')) if os.path.isdir(os.path.join(PU, 'data', 'snimky')) else []
    ok(len(shots) >= 2 and os.path.isfile(os.path.join(PU, 'data', 'audit.sqlite')), 'snímky a databáze auditu v /private/audit/data (mimo webroot)', shots)
    br.js("TSAiWidget.send('Co bych měl opravit jako první?')", wait=2.5)
    ok('[Výsledek auditu webu https://www.kavarna-u-mostu.cz/' in json.dumps(CHAT[-1]['messages'], ensure_ascii=False), 'asistentka zná výsledek auditu (brána čte /private/audit i se zúženým open_basedir)')
    # veřejné API: ochrana a limity
    c, j = api('/api/audit/verejny.php?r=audit', {'url': 'http://127.0.0.1/admin'}); ok(c == 422 and j.get('reason') == 'ip', 'veřejné API: interní adresa odmítnuta')
    c1, _ = api('/api/audit/verejny.php?r=audit', {'url': 'jiny-web.cz'}); c2, j2 = api('/api/audit/verejny.php?r=audit', {'url': 'treti-web.cz'})
    ok(c1 == 202 and c2 == 429 and j2.get('error') == 'limit', 'veřejné API: limit 2 audity na adresu IP za den', [c1, c2])
    ok(api('/api/audit/verejny.php?r=audit-list')[0] == 404, 'veřejné API: seznam auditů není dostupný')
    # administrace jen po přihlášení
    try: urllib.request.urlopen(urllib.request.Request(BASE + '/admin/audity.php'), timeout=10); r303 = None
    except urllib.error.HTTPError as e: r303 = e.code
    rr = urllib.request.build_opener(type('N', (urllib.request.HTTPRedirectHandler,), {'redirect_request': lambda *a, **k: None})()).open if False else None
    st = subprocess.run(['curl', '-s', '-o', '/dev/null', '-w', '%{http_code} %{redirect_url}', BASE + '/admin/audity.php'], capture_output=True, text=True).stdout
    ok(st.startswith('303'), 'administrace Audity: bez přihlášení přesměruje na přihlášení', st)
    ok(api('/admin/audity-api.php?r=audit-list')[0] == 401, 'API administrace: bez přihlášení 401')
    for rel, word in (('cs/ochrana-osobnich-udaju.html', 'Audit webu'), ('privacy.html', 'Website audit'), ('de/datenschutz.html', 'Website-Audit')):
        t = urllib.request.urlopen(BASE + '/' + rel, timeout=10).read().decode()
        ok(t.count('data-ai-audit') == 4 and word in t and 'Google LLC' in t and t.count('data-ai-elevenlabs') == 4, 'zásady: audit webu, Google LLC jako příjemce, předání do USA (%s)' % rel)
    br.size(375, 812, mobile=True); br.go(BASE + '/cs/index.html', wait=1.5)
    ok(br.js("return document.documentElement.scrollWidth <= innerWidth"), 'mobil: bez vodorovného posunu')
finally:
    br.close(); srv.terminate(); mock.shutdown(); shutil.rmtree(tmp, ignore_errors=True)
json.dump(RES, open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'vysledky-testu.json'), 'w'), ensure_ascii=False, indent=1)
print('RESULT pass=%d fail=%d' % (P, F))
