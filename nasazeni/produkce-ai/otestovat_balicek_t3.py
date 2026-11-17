#!/usr/bin/env python3
"""Test doplňkového balíčku T3 na simulované produkci: kořen/web = produkce (git HEAD projektu, data/ z projektu = produkce),
kořen/private/ai = produkční konfigurace z balíčku T1 (bez tokenu), přes to nahraný balicek-t3.
Žádná skutečná služba: Cloudflare = napodobenina na localhostu, falešný token, hlas = napodobenina rozhraní prohlížeče.
Poslední řádek: RESULT pass=N fail=M"""
import http.server, json, os, re, shutil, subprocess, sys, tarfile, io, tempfile, threading, time, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek-t3'); T1 = os.path.join(HERE, 'balicek', 'k-nahrani', 'ai')
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; PHP = os.path.join(PROJ, 'tools', 'php', 'php')
LIB = os.path.expanduser('~/TS-Studio/ts-library'); sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PORT, MOCK = 8193, 8194; BASE = 'http://localhost:%d' % PORT
P = F = 0; RES = []; REQS = []


def ok(c, m, d=''):
    global P, F
    RES.append([bool(c), m])
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}'); REQS.append(b)
        o = json.dumps({'id': 'c', 'object': 'chat.completion', 'model': b['model'], 'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': 'Dobrý den, ráda pomohu. Podívejte se na Kořen: /ukazky/koren.html (ukázka, fiktivní značka).'}, 'finish_reason': 'stop'}],
                        'usage': {'prompt_tokens': 3000, 'completion_tokens': 80}}).encode()
        self.send_response(200); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o)


meta = json.load(open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'balicek.json')))
files = open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'soubory-web.txt')).read().split()
ok(files and not any(f.startswith(('admin/', 'data/')) or f == 'ts-components.lock.json' for f in files), 'balíček neobsahuje administraci, data ani zámek komponent')
ok(sorted(os.listdir(os.path.join(PKG, 'k-nahrani', 'private-ai'))) == ['tessa'] and os.listdir(os.path.join(PKG, 'k-nahrani', 'private-ai', 'tessa')) == ['capabilities.json'], 'do /private/ai jde jen tessa/capabilities.json (konfigurace a token beze změny)')
ok(all(('vendor/ts/ts-ai-consultant/' + f) in files for f in ('public/ts-ai-widget.js', 'public/ts-ai-widget.css', 'core/TsAiConsultant.php', 'module.json')) and 'cs/ochrana-osobnich-udaju.html' in files, 'balíček: widget, brána a zásady')

tmp = tempfile.mkdtemp(); W = os.path.join(tmp, 'web'); PA = os.path.join(tmp, 'private', 'ai')
arch = subprocess.run(['git', '-C', PROJ, 'archive', 'HEAD', 'web'], capture_output=True, check=True).stdout
tarfile.open(fileobj=io.BytesIO(arch)).extractall(tmp)
shutil.copytree(os.path.join(PROJ, 'web', 'data'), os.path.join(W, 'data'), dirs_exist_ok=True)
ok('ts-ai-widget.js?v=1.5.1' in open(os.path.join(W, 'cs', 'index.html'), encoding='utf-8').read(), 'výchozí stav = produkce T1 (widget 1.5.1)')
os.makedirs(os.path.join(PA, 'tessa')); os.makedirs(os.path.join(PA, 'data'))
for f in ('ai-consultant.php', 'znalosti.json', 'tessa/persona.md', 'tessa/capabilities.json'): shutil.copy(os.path.join(T1, f), os.path.join(PA, f))
open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY-PROD\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0123456789abcdef0123456789abcdef\n')
c = open(os.path.join(PA, 'ai-consultant.php')).read()
c = c.replace("'timeout' => 30, 'connectTimeout' => 6]", "'timeout' => 30, 'connectTimeout' => 6, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1']" % MOCK)
c = c.replace("'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
open(os.path.join(PA, 'ai-consultant.php'), 'w').write(c)
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo $s["cloudflare"]["baseUrl"] ?? "";', os.path.join(PA, 'ai-consultant.php')], capture_output=True, text=True).stdout
ok(chk.startswith('http://127.0.0.1:%d/' % MOCK), 'pojistka: kopie nemíří na skutečné Cloudflare', chk)
if 'cloudflare.com' in chk: sys.exit(1)
# nahrání balíčku
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'web'), W, dirs_exist_ok=True); shutil.copytree(os.path.join(PKG, 'k-nahrani', 'private-ai'), PA, dirs_exist_ok=True)

mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-d', 'sendmail_path=cat >/dev/null', '-d', 'log_errors=1', '-d', 'error_log=' + os.path.join(HERE, 'balicek-t3', 'lokalne-NENAHRAVAT', 'php-chyby.log'), '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)
src = open(os.path.join(LIB, 'components', 'ts-ai-consultant', 'tests', 'http_test.py'), encoding='utf-8').read()
STUB = re.search(r"STUB = '''<script>\n(.*?)</script>'''", src, re.S).group(1)
br = Browser(9344)
try:
    st = json.loads(urllib.request.urlopen(BASE + '/api/ai/chat.php?r=status', timeout=10).read())
    ok(st.get('enabled') is True, 'brána běží po nahrání (status)', st)
    br.call('Page.addScriptToEvaluateOnNewDocument', source=STUB)
    br.size(1280, 900); br.go(BASE + '/cs/index.html', wait=1.8)
    r = br.js("const l=document.querySelector('.tsai-launcher'); return [document.querySelector('.tsai').hidden, l.textContent, !!l.querySelector('.tsai-bot svg'), !!document.querySelector('.tsai img'), document.querySelector('script[src*=\"ts-ai-widget.js\"]').getAttribute('src'), document.querySelector('script[src*=\"reference.js\"]').getAttribute('src')]")
    ok(r[0] is False and r[1] == 'Tessa' and r[2] and not r[3] and r[4].endswith('?v=' + meta['aiv']) and r[5].endswith('?v=' + meta['verze']['data']), 'web: tlačítko „Tessa“ s robůtkem (bez fotky), nový widget, verze dat jako produkce', r)
    br.js("TSAiWidget.open()", wait=.3)
    ok(br.js("return !!document.querySelector('.tsai-head-face .tsai-bot svg') && !!document.querySelector('.tsai-mic') && !document.querySelector('.tsai-mute').hidden"), 'panel: robůtek v hlavičce, mikrofon a předčítání')
    br.js("document.querySelector('.tsai-mute').click(); TSAiWidget.send('Dobrý den, hledám web pro kavárnu')", wait=2.5)
    ok(len(REQS) >= 1 and 'Kořen' in br.js("return document.querySelector('.tsai-log').textContent"), 'textový chat: odpověď přes bránu (napodobenina Cloudflare)')
    sysmsg = json.dumps(REQS[-1].get('messages', [])[:1], ensure_ascii=False) if REQS else ''
    ok('hlasový režim přes prohlížeč' in sysmsg, 'brána: model ví, že hlas je dostupný (capabilities z balíčku)')
    ev = br.js("return window.__events"); sp = br.js("return window.__spoken")
    ok([e for e in ev if e in ('processing', 'speaking', 'idle')][:3] == ['processing', 'speaking', 'idle'] and sp and sp[0]['lang'].startswith('cs'), 'hlas: odpověď se předčítá česky, stavy processing → speaking → idle', [ev, sp[:1]])
    n = len(REQS); br.js("TSAiWidget.listen()", wait=.3); br.js("window.__say('Kolik trvá výroba webu?')", wait=2.5)
    ok(len(REQS) > n and 'Kolik trvá výroba webu' in json.dumps(REQS[-1], ensure_ascii=False), 'hlas: řeč z mikrofonu se odešle jako zpráva')
    for rel, word in (('cs/ochrana-osobnich-udaju.html', 'Hlasový režim'), ('privacy.html', 'Voice mode'), ('de/datenschutz.html', 'Sprachmodus')):
        t = urllib.request.urlopen(BASE + '/' + rel, timeout=10).read().decode()
        ok('data-ai-hlas' in t and word in t and 'Cloudflare' in t, 'zásady: odstavec o hlasu (%s)' % rel)
    br.go(BASE + '/ar/index.html', wait=1.5)
    ok(br.js("return !!document.querySelector('.tsai-launcher .tsai-bot svg') && !document.querySelector('.tsai').hidden"), 'arabská verze (RTL): robůtek se zobrazí')
    br.size(375, 812, mobile=True); br.go(BASE + '/cs/index.html', wait=1.5)
    r = br.js("const b=document.querySelector('.tsai-launcher').getBoundingClientRect(); return [b.right <= innerWidth, b.bottom <= innerHeight, document.documentElement.scrollWidth <= innerWidth]")
    ok(all(r), 'mobil: tlačítko s robůtkem v obrazovce, bez vodorovného posunu', r)
    br.shot(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'nahled-mobil.jpg'))
    br.size(1280, 900); br.go(BASE + '/cs/index.html', wait=1.5); br.js("TSAiWidget.open()", wait=.5); br.shot(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'nahled-desktop.jpg'))
finally:
    br.close(); srv.terminate(); mock.shutdown(); shutil.rmtree(tmp, ignore_errors=True)
json.dump(RES, open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'vysledky-testu.json'), 'w'), ensure_ascii=False, indent=1)
print('RESULT pass=%d fail=%d' % (P, F))
