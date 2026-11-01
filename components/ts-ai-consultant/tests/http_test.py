#!/usr/bin/env python3
"""HTTP integrační test ts-ai-consultant: brána přes php -S s oficiálním Anthropic PHP SDK proti LOKÁLNÍ napodobenině
Messages API (žádné volání skutečné služby, falešný klíč), smyčka nástroje, výpadky 529/401, widget v Chromu
(odeslání, odkazy, formulář se souhlasem, lead, klávesnice, mobil, výpadek brány) a kontrola, že klíč není v prohlížeči.
Poslední řádek: RESULT pass=N fail=M"""
import base64, http.server, json, os, shutil, sqlite3, subprocess, sys, tempfile, threading, time, urllib.error, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); COMP = os.path.abspath(os.path.join(HERE, '..')); LIB = os.path.abspath(os.path.join(COMP, '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); SDK = os.path.expanduser('~/TS-Studio/build/anthropic-sdk/vendor/autoload.php')
PORT, MOCK = 8171, 8172; BASE = 'http://localhost:%d' % PORT
KEY = 'sk-ant-api03-FALESNY-KLIC-pro-lokalni-test-0000'
P = F = 0


def ok(c, msg, detail=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + msg + (' | ' + str(detail)[:400] if detail else ''))


if not os.path.isfile(SDK):
    print('SKIP chybí sestavené SDK: ' + SDK); print('RESULT pass=0 fail=1'); sys.exit(1)

# ---------- napodobenina Messages API (jen localhost) ----------
REQS = []; MODE = {'v': 'ok'}


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        body = json.loads(self.rfile.read(int(self.headers.get('content-length', 0))) or b'{}')
        REQS.append({'path': self.path, 'headers': {k.lower(): v for k, v in self.headers.items()}, 'body': body})
        if self.path.endswith('/ai/v1/chat/completions'):
            if MODE['v'] == '429': return self.reply(429, {'success': False, 'errors': [{'code': 4006, 'message': 'you have used up your daily free allocation of 10,000 neurons'}]})
            txt = 'Dobrý den, podobný je Salon Šalvěj: /ukazky/salon.html' if 'kontakt' not in str(body['messages'][-1]['content']).lower() else 'Rádi se ozveme.\n[[KONTAKT: Web pro kavárnu]]'
            return self.reply(200, {'id': 'chatcmpl-1', 'object': 'chat.completion', 'model': body['model'], 'choices': [{'index': 0, 'message': {'role': 'assistant', 'content': txt}, 'finish_reason': 'stop'}],
                                    'usage': {'prompt_tokens': 2000, 'completion_tokens': 60, 'total_tokens': 2060}})
        if MODE['v'] == '529': return self.reply(529, {'type': 'error', 'error': {'type': 'overloaded_error', 'message': 'Overloaded'}})
        if MODE['v'] == '401': return self.reply(401, {'type': 'error', 'error': {'type': 'authentication_error', 'message': 'invalid x-api-key'}})
        last = body['messages'][-1]; c = last['content']
        usage = {'input_tokens': 900, 'output_tokens': 80, 'cache_read_input_tokens': 2000, 'cache_creation_input_tokens': 0}
        if isinstance(c, list) and c and c[0].get('type') == 'tool_result':
            content = [{'type': 'text', 'text': 'Formulář najdete pod touto zprávou.'}]; stop = 'end_turn'
        elif 'kontakt' in str(c).lower():
            content = [{'type': 'thinking', 'thinking': '', 'signature': 'sig'}, {'type': 'tool_use', 'id': 'toolu_01', 'name': 'nabidnout_kontakt', 'input': {'shrnuti': 'Web pro kavárnu s rezervacemi.'}}]; stop = 'tool_use'
        elif 'cena' in str(c).lower():
            content = [{'type': 'text', 'text': 'Takový web vyjde zhruba na 40 000 Kč.'}]; stop = 'end_turn'
        else:
            content = [{'type': 'text', 'text': 'Podobný je Salon Šalvěj: /ukazky/salon.html (ukázka s fiktivní značkou). Jinde: https://zly.example.com'}]; stop = 'end_turn'
        self.reply(200, {'id': 'msg_test', 'type': 'message', 'role': 'assistant', 'model': body['model'], 'content': content, 'stop_reason': stop, 'stop_sequence': None, 'usage': usage})

    def reply(self, code, obj):
        b = json.dumps(obj).encode(); self.send_response(code); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(b))); self.end_headers(); self.wfile.write(b)


mock = http.server.ThreadingHTTPServer(('127.0.0.1', MOCK), Mock); threading.Thread(target=mock.serve_forever, daemon=True).start()

# ---------- projekt: webroot + neveřejná data ----------
tmp = tempfile.mkdtemp(prefix='tsai-http-'); W = os.path.join(tmp, 'web'); PR = os.path.join(tmp, 'private')
os.makedirs(os.path.join(W, 'vendor', 'ts')); os.makedirs(os.path.join(W, 'api', 'ai')); os.makedirs(os.path.join(PR, 'data'))
for c in ('ts-ai-consultant', 'ts-auth-php', 'ts-mailer-php'): shutil.copytree(os.path.join(LIB, 'components', c), os.path.join(W, 'vendor', 'ts', c), ignore=shutil.ignore_patterns('tests', 'sdk'))
shutil.copy(os.path.join(HERE, 'fixtures', 'znalosti.json'), os.path.join(PR, 'znalosti.json'))
open(os.path.join(PR, 'klic.txt'), 'w').write(KEY + '\n')
open(os.path.join(PR, 'ai-consultant.php'), 'w').write('''<?php
return ['timezone' => 'Europe/Prague', 'business' => 'Studio Test', 'contactEmail' => 'info@studio.test', 'defaultLang' => 'cs',
  'provider' => 'claude', 'apiKeyFile' => __DIR__ . '/klic.txt', 'sdkAutoload' => %r,
  'claude' => ['model' => 'claude-opus-5-5', 'effort' => 'low', 'baseUrl' => 'http://127.0.0.1:%d', 'maxRetries' => 0, 'timeout' => 10],
  'knowledgeFile' => __DIR__ . '/znalosti.json', 'db' => ['dsn' => 'sqlite:' . __DIR__ . '/data/ai.sqlite'], 'salt' => 'x', 'monthlyBudgetUsd' => 10.0,
  'notify' => ['to' => 'poptavky@studio.test'], 'mail' => ['transport' => 'log', 'logFile' => __DIR__ . '/data/mail.log', 'from' => 'web@studio.test', 'domain' => 'studio.test', 'redirectTo' => 'test@studio.test'],
  'consentText' => 'Souhlas.'];
''' % (SDK, MOCK))
open(os.path.join(PR, 'ai-cf.php'), 'w').write('''<?php
return ['timezone' => 'Europe/Prague', 'business' => 'Studio Test', 'contactEmail' => 'info@studio.test', 'defaultLang' => 'cs',
  'provider' => 'cloudflare', 'apiKeyFile' => __DIR__ . '/cf-token.txt',
  'cloudflare' => ['accountId' => '0123456789abcdef0123456789abcdef', 'baseUrl' => 'http://127.0.0.1:%d/client/v4/accounts/0123456789abcdef0123456789abcdef/ai/v1', 'timeout' => 10],
  'contactMode' => 'marker', 'knowledgeMaxChars' => 4000, 'maxTokens' => 500, 'budget' => ['limit' => 9500, 'period' => 'day', 'unit' => 'neuron'],
  'knowledgeFile' => __DIR__ . '/znalosti.json', 'db' => ['dsn' => 'sqlite:' . __DIR__ . '/data/cf.sqlite'], 'salt' => 'x'];
''' % MOCK)
open(os.path.join(PR, 'cf-token.txt'), 'w').write('CF-TOKEN-FALESNY-0000\n')
api = open(os.path.join(COMP, 'config', 'api.example.php')).read()
api = api.replace("'paths' => [__DIR__ . '/../..', $private]", "'paths' => [__DIR__ . '/../..', $private, %r]" % os.path.dirname(os.path.dirname(SDK)))
open(os.path.join(W, 'api', 'ai', 'chat.php'), 'w').write(api)
open(os.path.join(W, 'api', 'ai', 'chat-cf.php'), 'w').write(api.replace('/ai-consultant.php', '/ai-cf.php'))
open(os.path.join(W, 'index.html'), 'w').write('''<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Test</title>
<link rel="stylesheet" href="/vendor/ts/ts-ai-consultant/public/ts-ai-widget.css"></head><body><main><h1>Web studia</h1><a id="odkaz" href="#">Odkaz webu</a></main>
<script>window.TS_AI_CONFIG = { api: '/api/ai/chat.php', business: 'Studio Test', email: 'info@studio.test', privacyUrl: '/privacy.html' };</script>
<script src="/vendor/ts/ts-ai-consultant/public/ts-ai-widget.js" defer></script></body></html>''')
open(os.path.join(W, 'tessa.png'), 'wb').write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))
open(os.path.join(W, 'tessa.html'), 'w').write(open(os.path.join(W, 'index.html')).read().replace("privacyUrl: '/privacy.html' };", "privacyUrl: '/privacy.html', name: 'Tessa', avatar: '/tessa.png', texts: { cs: { role: 'AI asistentka T&S Studia', intro: 'Dobrý den, jsem Tessa, AI asistentka T&S Studia.' } } };"))
open(os.path.join(W, 'down.html'), 'w').write(open(os.path.join(W, 'index.html')).read().replace("/api/ai/chat.php", "/api/neexistuje.php"))
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(1.2)


def http(method, route, body=None, headers=None):
    h = {'Accept': 'application/json', 'Content-Type': 'application/json'}; h.update(headers or {})
    req = urllib.request.Request(BASE + '/api/ai/chat.php?r=' + route, data=json.dumps(body).encode() if body is not None else None, headers=h, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as r: return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e: return e.code, json.loads(e.read() or b'{}')


try:
    st, b = http('GET', 'status'); ok(st == 200 and b.get('enabled') is True, 'status přes SDK konfiguraci', b)
    st, b = http('POST', 'message', {'text': 'Máte podobný projekt?', 'lang': 'cs'})
    ok(st == 200 and '/ukazky/salon.html' in b['reply']['text'] and 'zly.example.com' not in b['reply']['text'], 'SDK → napodobenina → odpověď s povoleným odkazem', b)
    r = REQS[-1]; body = r['body']
    ok(r['path'] == '/v1/messages' and (r['headers'].get('x-api-key') == KEY or r['headers'].get('authorization') == 'Bearer ' + KEY) and r['headers'].get('anthropic-version'), 'požadavek: /v1/messages, klíč v hlavičce, verze API', r['path'])
    ok(body['model'] == 'claude-opus-5-5' and body['max_tokens'] == 1200 and body.get('output_config', {}).get('effort') == 'low', 'požadavek: model, max_tokens, effort low', {k: body.get(k) for k in ('model', 'max_tokens', 'output_config')})
    ok(isinstance(body['system'], list) and body['system'][0].get('cache_control') == {'type': 'ephemeral'}, 'systémový prompt s prompt cache', body['system'][0].keys())
    ok(body['tools'][0]['name'] == 'nabidnout_kontakt' and 'input_schema' in body['tools'][0] and 'thinking' not in body, 'nástroj ve formátu API (input_schema), thinking neposíláme (Opus 5.5 ho má vždy)', body['tools'][0].keys())
    conv = b['conversation']
    st, b = http('POST', 'message', {'text': 'Chci kontakt, web pro kavárnu', 'lang': 'cs', 'conversation': conv})
    ok(st == 200 and 'contact' in b['reply']['actions'] and b['reply'].get('summary') == 'Web pro kavárnu s rezervacemi.' and 'Formulář' in b['reply']['text'], 'smyčka nástroje přes SDK: formulář + shrnutí + závěrečný text', b)
    tr = REQS[-1]['body']['messages'][-1]['content'][0]
    ok(tr.get('type') == 'tool_result' and tr.get('tool_use_id') == 'toolu_01', 'tool_result s tool_use_id v druhém požadavku', tr)
    ok(any(x.get('type') == 'thinking' for x in REQS[-1]['body']['messages'][-2]['content']), 'tah asistenta se vrací beze změny (včetně bloku myšlení)')
    st, b = http('POST', 'message', {'text': 'Jaká je cena?', 'lang': 'cs', 'conversation': conv})
    ok('Kč' not in b['reply']['text'] and 'rozsahu' in b['reply']['text'], 'cenová pojistka i přes skutečné SDK', b)
    MODE['v'] = '529'; st, b = http('POST', 'message', {'text': 'Ahoj', 'lang': 'en'})
    ok(st == 200 and b['reply'].get('fallback') == 'unavailable' and 'info@studio.test' in b['reply']['text'], 'přetížení 529 → náhradní text', b)
    MODE['v'] = '401'; st, b = http('POST', 'message', {'text': 'Ahoj', 'lang': 'en'})
    ok(st == 200 and b['reply'].get('fallback') == 'unavailable', 'neplatný klíč 401 → náhradní text', b)
    MODE['v'] = 'ok'
    db = sqlite3.connect(os.path.join(PR, 'data', 'ai.sqlite'))
    outs = [r[0] for r in db.execute('SELECT outcome FROM ts_ai_usage ORDER BY id')]
    ok('error_overloaded' in outs and 'error_auth' in outs and 'tool' in outs and 'price_guard' in outs, 'spotřeba a výsledky zapsány', outs)
    cost = db.execute('SELECT cost_micro FROM ts_ai_usage WHERE outcome = "ok" ORDER BY id LIMIT 1').fetchone()[0]
    ok(cost == 900 * 4 + 80 * 20 + 2000 * 0.4, 'cena z usage: vstup + výstup + čtení cache (%s µUSD)' % cost)
    # Cloudflare Workers AI (OpenAI-kompatibilní, cURL bez SDK)
    def cf(route, body):
        req = urllib.request.Request(BASE + '/api/ai/chat-cf.php?r=' + route, data=json.dumps(body).encode(), headers={'Content-Type': 'application/json'}, method='POST')
        try:
            with urllib.request.urlopen(req, timeout=30) as r: return r.status, json.loads(r.read())
        except urllib.error.HTTPError as e: return e.code, json.loads(e.read() or b'{}')
    n0 = len(REQS); st, b = cf('message', {'text': 'Máte podobný projekt?', 'lang': 'cs'})
    r = REQS[-1] if len(REQS) > n0 else {}
    ok(st == 200 and '/ukazky/salon.html' in b['reply']['text'], 'Cloudflare: odpověď přes OpenAI-kompatibilní rozhraní', b)
    ok(r.get('path', '').endswith('/accounts/0123456789abcdef0123456789abcdef/ai/v1/chat/completions') and r['headers'].get('authorization') == 'Bearer CF-TOKEN-FALESNY-0000', 'Cloudflare: správná adresa účtu a token v hlavičce', r.get('path'))
    bb = r.get('body', {})
    ok(bb.get('model') == '@cf/meta/llama-3.3-70b-instruct-fp8-fast' and bb['messages'][0]['role'] == 'system' and 'tools' not in bb and bb.get('max_tokens') == 500, 'Cloudflare: model, systémová zpráva, bez nástrojů, max_tokens', {k: bb.get(k) for k in ('model', 'max_tokens')})
    st, b2 = cf('message', {'text': 'Chci kontakt', 'lang': 'cs', 'conversation': b['conversation']})
    ok('offer' in b2['reply']['actions'] and b2['reply'].get('summary') == 'Web pro kavárnu' and '[[' not in b2['reply']['text'], 'Cloudflare: značka kontaktu → nabídka formuláře', b2)
    db = sqlite3.connect(os.path.join(PR, 'data', 'cf.sqlite'))
    m, c = db.execute('SELECT month, cost_micro FROM ts_ai_usage ORDER BY id LIMIT 1').fetchone()
    ok(m == time.strftime('%Y-%m-%d', time.gmtime()) and c == 2000 * 26668 + 60 * 204805, 'Cloudflare: spotřeba v neuronech za dnešní den (%s)' % c)
    MODE['v'] = '429'; st, b3 = cf('message', {'text': 'Ahoj', 'lang': 'cs'}); MODE['v'] = 'ok'
    ok(st == 200 and b3['reply'].get('fallback') == 'unavailable' and 'info@studio.test' in b3['reply']['text'], 'Cloudflare: vyčerpaná kvóta (429) → náhradní text s kontaktem', b3)
    with urllib.request.urlopen(BASE + '/vendor/ts/ts-ai-consultant/core/TsAiConsultant.php') as r: out = r.read().decode()
    ok(out.strip() == '', 'PHP jádro nic nevypíše při přímém volání (na Apache zakáže .htaccess)')
    for f in ('core/.htaccess', 'config/.htaccess', 'bin/.htaccess', 'migrations/.htaccess'): ok(os.path.isfile(os.path.join(COMP, f)), 'ochrana složky ' + f)

    # ---------- widget v Chromu ----------
    br = Browser(port=9371)
    try:
        br.size(1280, 900); br.go(BASE + '/index.html', wait=1.5)
        ok(br.js("return !!document.querySelector('.tsai-launcher') && document.querySelector('.tsai-launcher').textContent.indexOf('AI asistent') >= 0"), 'widget: tlačítko v jazyce stránky')
        html = br.js("return document.documentElement.outerHTML + [...document.scripts].map(s => s.src).join()")
        js = urllib.request.urlopen(BASE + '/vendor/ts/ts-ai-consultant/public/ts-ai-widget.js').read().decode()
        ok(KEY not in html and KEY not in js and 'sk-ant' not in js, 'klíč není ve stránce ani ve skriptu widgetu')
        br.js("document.querySelector('.tsai-launcher').click()", wait=.4)
        ok(br.js("return document.activeElement && document.activeElement.id === 'tsai-input' && document.querySelector('.tsai-launcher').getAttribute('aria-expanded') === 'true'"), 'otevření: fokus v poli zprávy, aria-expanded')
        ok(br.js("const a=document.querySelector('.tsai-note a'); return !!a && a.getAttribute('href') === '/privacy.html'"), 'úvodní poznámka odkazuje na zásady ochrany údajů')
        ok(br.js("return document.querySelectorAll('.tsai-chip').length === 5 && document.querySelector('.tsai-log').getAttribute('role') === 'log'"), '5 rychlých akcí, oblast zpráv role=log')
        br.js("document.querySelector('.tsai-chip').click()", wait=2.5)
        ok(br.js("return [...document.querySelectorAll('.tsai-assistant a')].some(a => a.getAttribute('href') === '/ukazky/salon.html' && a.textContent === 'Salon Šalvěj')"), 'odpověď: povolený odkaz klikací s názvem reference')
        ok(br.js("return ![...document.querySelectorAll('.tsai-log a')].some(a => a.href.indexOf('zly.example') >= 0)"), 'cizí odkaz se neudělá klikací')
        br.js("const i=document.getElementById('tsai-input'); i.value='Chci kontakt'; i.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))", wait=2.5)
        SH = os.environ.get('TSAI_SHOTS')
        if SH: br.shot(os.path.join(SH, 'desktop-formular.jpg'))
        ok(br.js("return !!document.querySelector('.tsai-form') && document.getElementById('tsai-f-message').value === 'Web pro kavárnu s rezervacemi.'"), 'formulář se zobrazil se shrnutím od asistenta')
        ok(br.js("return document.activeElement && document.activeElement.id === 'tsai-f-name'"), 'fokus na první pole formuláře')
        br.js("document.querySelector('.tsai-form [type=submit]').click()", wait=.3)
        ok(br.js("return document.getElementById('tsai-ferr').textContent.length > 5 && document.getElementById('tsai-f-name').getAttribute('aria-invalid') === 'true'"), 'prázdný formulář: chybová hláška a aria-invalid')
        br.js("document.getElementById('tsai-f-name').value='Jana Webová'; document.getElementById('tsai-f-email').value='jana@example.com'; document.querySelector('.tsai-form [type=submit]').click()", wait=.3)
        ok(br.js("return !!document.querySelector('.tsai-form')") and br.js("return document.activeElement.id === 'tsai-f-consent'"), 'bez souhlasu neodešle, fokus na souhlas')
        time.sleep(2.6)
        br.js("document.getElementById('tsai-f-consent').click(); document.querySelector('.tsai-form [type=submit]').click()", wait=1.5)
        ok(br.js("return !document.querySelector('.tsai-form') && document.querySelector('.tsai-log').textContent.indexOf('poptávka je odeslaná') >= 0"), 'lead odeslán, poděkování v chatu')
        row = sqlite3.connect(os.path.join(PR, 'data', 'ai.sqlite')).execute('SELECT name, email, consent_text, transcript FROM ts_ai_leads').fetchone()
        ok(row and row[0] == 'Jana Webová' and 'Souhlasím' in row[2] and 'Návštěvník: Chci kontakt' in row[3], 'lead v DB se souhlasem a přepisem', row)
        ok(not any('jana@example.com' in json.dumps(r['body']) for r in REQS), 'e-mail z formuláře nešel do API')
        br.js("document.getElementById('tsai-input').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))", wait=.3)
        ok(br.js("return document.getElementById('tsai-panel').hidden && document.activeElement.classList.contains('tsai-launcher')"), 'Esc zavře panel a vrátí fokus na tlačítko')
        br.go(BASE + '/index.html', wait=1.2); br.js("document.querySelector('.tsai-launcher').click()", wait=.3)
        ok(br.js("return document.querySelectorAll('.tsai-user').length >= 2"), 'konverzace přežije přechod na jinou stránku (sessionStorage)')
        br.js("const s=document.getElementById('tsai-lang'); s.value='ar'; s.dispatchEvent(new Event('change'))", wait=.2)
        ok(br.js("return document.getElementById('tsai-panel').getAttribute('dir') === 'rtl' && document.querySelector('.tsai-send').textContent === 'إرسال'"), 'přepnutí jazyka: arabština zprava doleva')
        br.js("document.querySelector('.tsai-new').click()", wait=.2)
        ok(br.js("return document.querySelectorAll('.tsai-user').length === 0 && !sessionStorage.getItem('tsai:conv')"), 'nová konverzace smaže historii')
        br.size(390, 844, mobile=True); br.go(BASE + '/index.html', wait=1.2); br.js("document.querySelector('.tsai-launcher').click()", wait=.4)
        r = br.js("const r=document.getElementById('tsai-panel').getBoundingClientRect(); return [r.width, r.height, innerWidth, innerHeight, document.documentElement.scrollWidth]")
        ok(r[0] == r[2] and r[1] == r[3] and r[4] <= r[2], 'mobil: panel přes celou obrazovku, bez vodorovného posunu', r)
        mins = br.js("return [...document.querySelectorAll('.tsai-chip,.tsai-send,.tsai-icon')].filter(e=>e.offsetParent).map(e=>Math.min(e.offsetWidth,e.offsetHeight))")
        ok(min(mins) >= 40, 'mobil: dotykové cíle aspoň 40 px', mins)
        if os.environ.get('TSAI_SHOTS'): br.shot(os.path.join(os.environ['TSAI_SHOTS'], 'mobil.jpg'))
        br.size(1280, 900); br.go(BASE + '/tessa.html', wait=.5); br.js("sessionStorage.clear()"); br.go(BASE + '/tessa.html', wait=1.5)
        r = br.js("const l=document.querySelector('.tsai-launcher'); return [l.textContent, l.getAttribute('aria-label'), !!l.querySelector('img.tsai-face')]")
        ok(r[0] == 'Tessa' and r[1] == 'Tessa, AI asistentka T&S Studia' and r[2], 'persona: tlačítko „Tessa“ s portrétem a popisem pro čtečky', r)
        br.js("document.querySelector('.tsai-new') && sessionStorage.clear(); TSAiWidget.open()", wait=.3)
        r = br.js("return [document.querySelector('.tsai-title').textContent, document.querySelector('.tsai-role').textContent, document.querySelector('.tsai-log .tsai-assistant').textContent, !!document.querySelector('.tsai-head-face img')]")
        ok(r[0] == 'Tessa' and r[1] == 'AI asistentka T&S Studia' and 'jsem Tessa' in r[2] and r[2].startswith('Tessa:') and r[3], 'persona: hlavička se jménem a označením AI, vlastní úvod', r)
        br.js("const s=document.getElementById('tsai-lang'); s.value='de'; s.dispatchEvent(new Event('change'))", wait=.2)
        r = br.js("return [document.querySelector('.tsai-title').textContent, document.querySelector('.tsai-role').textContent]")
        ok(r[0] == 'Tessa' and r[1] == 'KI-Assistent', 'persona: jazyk bez vlastního textu použije vestavěný (fallback)', r)
        br.js("sessionStorage.clear()")
        br.size(1280, 900); br.go(BASE + '/down.html', wait=1.2)
        ok(br.js("return document.querySelector('.tsai').hidden === true"), 'nedostupná brána: tlačítko asistenta se vůbec neukáže')
        br.js("document.querySelector('.tsai').hidden = false; TSAiWidget.open()", wait=.3); br.js("document.querySelector('.tsai-chip').click()", wait=1.5)
        ok(br.js("return document.querySelector('.tsai-log').textContent.indexOf('info@studio.test') >= 0 && !!document.querySelector('.tsai-form')"), 'výpadek během konverzace: text s e-mailem + kontaktní formulář')
        ok(br.js("document.getElementById('odkaz').click(); return document.querySelector('main h1').textContent === 'Web studia'"), 'web dál funguje')
    finally:
        br.close()
finally:
    srv.terminate(); mock.shutdown()
    shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
