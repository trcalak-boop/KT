#!/usr/bin/env python3
"""Test doplňkového balíčku T4 (hlas Tessy z ElevenLabs) na simulované produkci: kořen/web = produkce T3 (git HEAD projektu),
kořen/private/ai = produkční konfigurace z balíčku T1 + funkce T3 (bez tokenů); nejdřív běží stará verze (databáze), pak se nahraje balicek-t4.
Žádná skutečná služba: Cloudflare i ElevenLabs = napodobenina na localhostu, falešné klíče, přehrávání zvuku a hlas prohlížeče = napodobeniny.
Poslední řádek: RESULT pass=N fail=M"""
import http.server, json, os, re, shutil, subprocess, sys, tarfile, io, tempfile, threading, time, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek-t4'); T3 = os.path.join(HERE, 'balicek-t3', 'k-nahrani', 'private-ai'); T1 = os.path.join(HERE, 'balicek', 'k-nahrani', 'ai')
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; PHP = os.path.join(PROJ, 'tools', 'php', 'php')
LIB = os.path.expanduser('~/TS-Studio/ts-library'); sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PORT, MOCK = 8195, 8196; BASE = 'http://localhost:%d' % PORT
P = F = 0; RES = []; REQS = []; TTS = []
ELKEY = 'el-FALESNY-produkce-0000'


def ok(c, m, d=''):
    global P, F
    RES.append([bool(c), m])
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:400] if d else ''))


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        if '/v1/text-to-speech/' in self.path:
            TTS.append({'path': self.path, 'key': self.headers.get('xi-api-key'), 'body': json.loads(self.rfile.read(int(self.headers.get('content-length', 0))))})
            o = b'ID3' + b'\x00' * 600; self.send_response(200); self.send_header('content-type', 'audio/mpeg'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o); return
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}'); REQS.append(b)
        o = json.dumps({'id': 'c', 'object': 'chat.completion', 'model': b['model'], 'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': 'Dobrý den, ráda pomohu. Podívejte se na Kořen: /ukazky/koren.html (ukázka, fiktivní značka).'}, 'finish_reason': 'stop'}],
                        'usage': {'prompt_tokens': 3000, 'completion_tokens': 80}}).encode()
        self.send_response(200); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o)


meta = json.load(open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'balicek.json')))
files = open(os.path.join(PKG, 'lokalne-NENAHRAVAT', 'soubory-web.txt')).read().split()
ok(files and not any(f.startswith(('admin/', 'data/')) or f == 'ts-components.lock.json' for f in files), 'balíček neobsahuje administraci, data ani zámek komponent')
ok(sorted(os.listdir(os.path.join(PKG, 'k-nahrani', 'private-ai'))) == ['ai-consultant.php', 'elevenlabs-hlas.txt', 'tessa'] and not any('klic' in f or 'token' in f for f in os.listdir(os.path.join(PKG, 'k-nahrani', 'private-ai'))), 'do /private/ai jde konfigurace, ID hlasu a funkce Tessy – žádný klíč ani token')
cfg = open(os.path.join(PKG, 'k-nahrani', 'private-ai', 'ai-consultant.php')).read(); old = open(os.path.join(T1, 'ai-consultant.php')).read()
ok(re.search(r"'salt' => '([0-9a-f]+)'", cfg).group(1) == re.search(r"'salt' => '([0-9a-f]+)'", old).group(1) and "'monthlyChars' => 22000" in cfg and 'elevenlabs-klic.txt' in cfg, 'konfigurace: stejná sůl jako na produkci, hlas se stropem 22 000 znaků')
ok(all(('vendor/ts/ts-ai-consultant/' + f) in files for f in ('public/ts-ai-widget.js', 'public/ts-ai-widget.css', 'core/TsAiConsultant.php', 'module.json')) and 'cs/ochrana-osobnich-udaju.html' in files, 'balíček: widget, brána a zásady')

tmp = tempfile.mkdtemp(); W = os.path.join(tmp, 'web'); PA = os.path.join(tmp, 'private', 'ai')
arch = subprocess.run(['git', '-C', PROJ, 'archive', 'HEAD', 'web'], capture_output=True, check=True).stdout
tarfile.open(fileobj=io.BytesIO(arch)).extractall(tmp)
shutil.copytree(os.path.join(PROJ, 'web', 'data'), os.path.join(W, 'data'), dirs_exist_ok=True)
ok('ts-ai-widget.js?v=1.7.1' in open(os.path.join(W, 'cs', 'index.html'), encoding='utf-8').read(), 'výchozí stav = produkce T3 (widget 1.7.1)')
os.makedirs(os.path.join(PA, 'tessa')); os.makedirs(os.path.join(PA, 'data'))
for f in ('ai-consultant.php', 'znalosti.json', 'tessa/persona.md', 'tessa/capabilities.json'): shutil.copy(os.path.join(T1, f), os.path.join(PA, f))
shutil.copy(os.path.join(T3, 'tessa', 'capabilities.json'), os.path.join(PA, 'tessa', 'capabilities.json'))
open(os.path.join(PA, 'cloudflare-token.txt'), 'w').write('CF-FALESNY-PROD\n'); open(os.path.join(PA, 'cloudflare-ucet.txt'), 'w').write('0123456789abcdef0123456789abcdef\n')
def patch():
  c = open(os.path.join(PA, 'ai-consultant.php')).read()
  c = c.replace("'timeout' => 30, 'connectTimeout' => 6]", "'timeout' => 30, 'connectTimeout' => 6, 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1']" % MOCK)
  c = c.replace("'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu']", "'allowedOrigins' => ['%s']" % BASE)
  c = c.replace("'model' => 'eleven_flash_v2_5',", "'model' => 'eleven_flash_v2_5', 'baseUrl' => 'http://127.0.0.1:%d'," % MOCK)
  open(os.path.join(PA, 'ai-consultant.php'), 'w').write(c)
patch()
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo $s["cloudflare"]["baseUrl"] ?? "";', os.path.join(PA, 'ai-consultant.php')], capture_output=True, text=True).stdout
ok(chk.startswith('http://127.0.0.1:%d/' % MOCK), 'pojistka: kopie nemíří na skutečné Cloudflare', chk)
if 'cloudflare.com' in chk: sys.exit(1)
mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()
# stará verze (T3) vytvoří databázi, jak je dnes na produkci
srv0 = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)
st0 = json.loads(urllib.request.urlopen(BASE + '/api/ai/chat.php?r=status', timeout=10).read()); srv0.terminate(); time.sleep(.5)
ok(st0.get('enabled') is True and 'speech' not in st0, 'před nahráním: běží stará brána bez hlasu')
# nahrání balíčku + klíč, který vloží provozovatel
shutil.copytree(os.path.join(PKG, 'k-nahrani', 'web'), W, dirs_exist_ok=True); shutil.copytree(os.path.join(PKG, 'k-nahrani', 'private-ai'), PA, dirs_exist_ok=True)
open(os.path.join(PA, 'elevenlabs-klic.txt'), 'w').write(ELKEY + '\n'); patch()
chk = subprocess.run([PHP, '-r', '$s = require $argv[1]; echo $s["speech"]["baseUrl"] ?? "";', os.path.join(PA, 'ai-consultant.php')], capture_output=True, text=True).stdout
ok(chk == 'http://127.0.0.1:%d' % MOCK, 'pojistka: kopie nemíří na skutečné ElevenLabs', chk)
if 'elevenlabs.io' in chk: sys.exit(1)
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-d', 'sendmail_path=cat >/dev/null', '-d', 'log_errors=1', '-d', 'error_log=' + os.path.join(HERE, 'balicek-t3', 'lokalne-NENAHRAVAT', 'php-chyby.log'), '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)
src = open(os.path.join(LIB, 'components', 'ts-ai-consultant', 'tests', 'http_test.py'), encoding='utf-8').read()
STUB = re.search(r"STUB = '''<script>\n(.*?)</script>'''", src, re.S).group(1)
br = Browser(9344)
try:
    st = json.loads(urllib.request.urlopen(BASE + '/api/ai/chat.php?r=status', timeout=10).read())
    ok(st.get('enabled') is True and st.get('speech') is True, 'brána běží po nahrání a hlásí hlas Tessy (status)', st)
    import sqlite3
    ok(3 in [r[0] for r in sqlite3.connect(os.path.join(PA, 'data', 'ai.sqlite')).execute('SELECT version FROM ts_ai_schema')], 'databáze z produkce převedena (migrace 003 hlas)')
    AUD = '''window.__played=[]; window.Audio=function(){ var a=this; a.play=function(){ var s=a.src; if(!/^data:/.test(s)) { window.__played.push(s); setTimeout(function(){ a.onplaying&&a.onplaying(); setTimeout(function(){ a.onended&&a.onended(); }, 150); }, 20); } return Promise.resolve(); }; a.pause=function(){}; }; window.AudioContext=undefined; window.webkitAudioContext=undefined;'''
    br.call('Page.addScriptToEvaluateOnNewDocument', source=AUD)
    br.call('Page.addScriptToEvaluateOnNewDocument', source=STUB)
    br.size(1280, 900); br.go(BASE + '/cs/index.html', wait=1.8)
    r = br.js("const l=document.querySelector('.tsai-launcher'); return [document.querySelector('.tsai').hidden, l.textContent, !!l.querySelector('.tsai-bot svg'), !!document.querySelector('.tsai img'), document.querySelector('script[src*=\"ts-ai-widget.js\"]').getAttribute('src'), document.querySelector('script[src*=\"reference.js\"]').getAttribute('src')]")
    ok(r[0] is False and r[1] == 'Tessa' and r[2] and not r[3] and r[4].endswith('?v=' + meta['aiv']) and r[5].endswith('?v=' + meta['verze']['data']), 'web: tlačítko „Tessa“ s robůtkem (bez fotky), nový widget, verze dat jako produkce', r)
    br.js("TSAiWidget.open()", wait=.3)
    ok(br.js("return !!document.querySelector('.tsai-head-face .tsai-bot svg') && !!document.querySelector('.tsai-mic') && !document.querySelector('.tsai-mute').hidden"), 'panel: robůtek v hlavičce, mikrofon a předčítání')
    br.js("document.querySelector('.tsai-mute').click(); TSAiWidget.send('Dobrý den, hledám web pro kavárnu')", wait=2.5)
    ok(len(REQS) >= 1 and 'Kořen' in br.js("return document.querySelector('.tsai-log').textContent"), 'textový chat: odpověď přes bránu (napodobenina Cloudflare)')
    sysmsg = json.dumps(REQS[-1].get('messages', [])[:1], ensure_ascii=False) if REQS else ''
    ok('hlasem Tessy (syntetický hlas ElevenLabs)' in sysmsg, 'brána: model ví o hlasu Tessy z ElevenLabs (capabilities z balíčku)')
    ev = br.js("return window.__events"); sp = br.js("return window.__spoken")
    pl = br.js("return window.__played.length")
    ok([e for e in ev if e in ('processing', 'speaking', 'idle')][:3] == ['processing', 'speaking', 'idle'] and pl == 1 and not sp, 'hlas: odpověď čte hlas Tessy z ElevenLabs (ne prohlížeč), stavy processing → speaking → idle', [ev, pl, sp[:1]])
    ok(len(TTS) == 1 and TTS[0]['path'].startswith('/v1/text-to-speech/fiovsrMYQB7LKXX3ytcI?') and TTS[0]['key'] == ELKEY and TTS[0]['body']['language_code'] == 'cs' and '/ukazky/' not in TTS[0]['body']['text'],
       'ElevenLabs: hlas Tessa, klíč ze souboru provozovatele, čeština, text bez adres', TTS)
    ok(len(os.listdir(os.path.join(PA, 'data', 'hlas'))) == 1, 'namluvená odpověď uložená v /private/ai/data/hlas (mezipaměť)')
    n = len(REQS); br.js("TSAiWidget.listen()", wait=.3); br.js("window.__say('Kolik trvá výroba webu?')", wait=2.5)
    ok(len(REQS) > n and 'Kolik trvá výroba webu' in json.dumps(REQS[-1], ensure_ascii=False), 'hlas: řeč z mikrofonu se odešle jako zpráva')
    for rel, word in (('cs/ochrana-osobnich-udaju.html', 'Hlas Tessy'), ('privacy.html', 'Tessa’s voice'), ('de/datenschutz.html', 'Tessas Stimme')):
        t = urllib.request.urlopen(BASE + '/' + rel, timeout=10).read().decode()
        ok(t.count('data-ai-elevenlabs') == 4 and word in t and 'Eleven Labs Inc.' in t and 'Data Privacy Framework' in t, 'zásady: hlas Tessy, ElevenLabs jako příjemce a předání do USA (%s)' % rel)
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
