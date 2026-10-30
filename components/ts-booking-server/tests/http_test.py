#!/usr/bin/env python3
"""HTTP integrační test ts-booking-server na kopii demo projektu C (kadernictvi-vlna-server) přes php -S:
API (config, dostupnost, rezervace, idempotence, souběh, ochrany, zrušení), průchod webem v Chromu (rezervace → e-mail →
zrušení odkazem), správa (přihlášení, potvrzení, odhlášení) a kontrola, že jádro v projektu zůstalo beze změny.
Poslední řádek: RESULT pass=N fail=M"""
import datetime, json, os, re, shutil, sqlite3, subprocess, sys, tempfile, threading, time, urllib.error, urllib.request, base64, quopri
HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.abspath(os.path.join(HERE, '..', '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); SRC = os.path.expanduser('~/TS-Studio/demo-projekty/kadernictvi-vlna-server')
PORT = 8160; BASE = 'http://localhost:%d' % PORT
P = F = 0


def ok(c, msg, detail=''):
    global P, F
    if c: P += 1
    else:
        F += 1; print('FAIL ' + msg + (' | ' + str(detail)[:300] if detail else ''))


def http(method, route, body=None, headers=None, raw=False):
    url = BASE + '/api/rezervace.php?r=' + route if not raw else BASE + route
    data = json.dumps(body).encode() if body is not None else None
    h = {'Accept': 'application/json'}; h.update(headers or {})
    if data is not None: h['Content-Type'] = 'application/json'
    req = urllib.request.Request(url, data=data, headers=h, method=method)
    try:
        with urllib.request.urlopen(req, timeout=20) as r: return r.status, r.read().decode()
    except urllib.error.HTTPError as e: return e.code, e.read().decode()


def j(s):
    try: return json.loads(s)
    except Exception: return {'raw': s[:300]}


tmp = tempfile.mkdtemp(prefix='tsb-http-'); D = os.path.join(tmp, 'projekt')
shutil.copytree(SRC, D, ignore=shutil.ignore_patterns('rezervace.sqlite*', 'mail.log', 'auth'))
env = dict(os.environ, TS_DEMO_BASE=BASE)
p = subprocess.run([PHP, os.path.join(D, 'web/vendor/ts/ts-booking-server/bin/create-user.php'), os.path.join(D, 'private/booking-server.php'), 'majitel', 'admin', 'Majitelka Vlna'], input='heslo-majitelky-2026\n', capture_output=True, text=True, env=env)
ok(p.returncode == 0 and 'vytvořen' in p.stdout, 'CLI create-user', p.stdout + p.stderr)
srv = subprocess.Popen([PHP, '-S', 'localhost:%d' % PORT, '-t', os.path.join(D, 'web')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=env)
time.sleep(1.2)
db = os.path.join(D, 'private/data/rezervace.sqlite')
try:
    st, b = http('GET', 'config'); cfg = j(b).get('config', {})
    ok(st == 200 and len(cfg.get('services', [])) == 4, 'GET config', b)
    st, b = http('GET', 'availability'); av = j(b)
    ok(st == 200 and av['state']['bookings'] == [], 'GET availability (prázdná DB)', b)
    today = av['today']; d = datetime.date.fromisoformat(today)
    while True:
        d += datetime.timedelta(days=1)
        if d.weekday() in (2, 4): break          # středa/pátek – Klára pracuje 9–17
    day = d.isoformat()
    form = {'date': day, 'service': 'pansky-strih', 'start': 600, 'resource': 'klara', 'name': 'Petr Souběžný', 'email': 'petr@example.com', 'consent': True, 'openedMs': 8000, 'hp': ''}
    # souběh: 8 požadavků na stejný termín u stejné stylistky naráz
    res = []; th = []
    for i in range(8):
        t = threading.Thread(target=lambda i=i: res.append(http('POST', 'bookings', form, {'Idempotency-Key': 'soubeh-%d' % i}))); th.append(t)
    [t.start() for t in th]; [t.join() for t in th]
    codes = sorted(r[0] for r in res)
    ok(codes.count(201) == 1 and codes.count(409) == 7, 'souběh 8 požadavků na stejný termín → jen 1 rezervace, 7× 409', codes)
    n = sqlite3.connect(db).execute("SELECT COUNT(*) FROM ts_booking_bookings WHERE date=? AND start=600 AND resource='klara'", (day,)).fetchone()[0]
    ok(n == 1, 'v databázi je termín jen jednou', n)
    st, b = http('POST', 'bookings', form, {'Idempotency-Key': 'soubeh-%d' % [i for i, r in enumerate(res) if r[0] == 201][0]}) if False else (0, '')
    win = [r for r in res if r[0] == 201][0]; key = None
    st, b = http('POST', 'bookings', dict(form, hp='spam')); ok(st == 400, 'robot (skryté pole) → 400', st)
    st, b = http('POST', 'bookings', dict(form, email='x')); ok(st == 422 and 'email' in j(b).get('fields', []), 'neplatný e-mail → 422', b)
    st, b = http('POST', 'bookings', '{', raw=False) if False else http('POST', 'bookings', None); ok(st == 422, 'prázdný požadavek → 422', st)
    st, b = http('GET', 'availability'); ok(len(j(b)['state']['bookings']) == 1 and 'name' not in json.dumps(j(b)['state']), 'dostupnost ukazuje rezervaci bez osobních údajů', b)
    st, b = http('GET', 'admin/bookings'); ok(st == 401, 'správa bez přihlášení → 401', st)
    st, b = http('GET', '/vendor/ts/ts-booking-server/core/TsBookingStore.php', raw=True); ok(b.strip() == '', 'PHP soubory knihovny nic nevypíší ani při přímém volání (na Apache je zakáže .htaccess)', b[:100])
    st, b = http('GET', '/../private/booking-server.php', raw=True); ok("'db'" not in b and 'sqlite:' not in b, 'private/ mimo webroot není dostupná (konfigurace se nevydá)', b[:80])

    # --- web v Chromu ---
    br = Browser(port=9364)
    try:
        br.size(1280, 900)
        br.go(BASE + '/index.html', wait=1.8)
        out = json.loads(br.js(r'''return (async () => {
          const sleep = ms => new Promise(r => setTimeout(r, ms)), el = document.querySelector('#widget'), q = s => el.querySelector(s);
          for (let i = 0; i < 60 && !q('.tsb-card'); i++) await sleep(100);
          const o = { demo: !!q('.tsb-demo') };
          q('[data-svc="damsky-strih"]').click(); await sleep(50); q('[data-variant="1"]').click(); await sleep(50); q('[data-res="klara"]').click(); await sleep(50);
          q('[data-date="%s"]').click(); await sleep(50);
          o.times = [...el.querySelectorAll('.tsb-time')].map(b => +b.dataset.t);
          await sleep(2100);
          el.querySelector('[data-t="' + o.times[0] + '"]').click(); await sleep(50);
          const f = q('.tsb-form'); f.elements.name.value = 'Jana Webová'; f.elements.email.value = 'jana.web@example.com'; f.elements.consent.checked = true;
          f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          for (let i = 0; i < 40 && !q('.tsb-done'); i++) await sleep(100);
          o.done = !!q('.tsb-done'); return JSON.stringify(o); })();''' % day))
        ok(not out['demo'], 'serverový režim: bez demo upozornění')
        ok(600 not in out['times'] and 570 not in out['times'] and 630 in out['times'], 'obsazený čas 10:00–10:30 (pánský střih) se u Kláry nenabízí, 60min služba ani nezačne v 9:30', out['times'][:8])
        ok(out['done'], 'rezervace přes web dokončena')
        con = sqlite3.connect(db); con.row_factory = sqlite3.Row
        row = con.execute("SELECT * FROM ts_booking_bookings WHERE email='jana.web@example.com'").fetchone()
        ok(row and row['status'] == 'pending' and row['dur'] == 60 and row['resource'] == 'klara' and row['cancel_hash'], 'rezervace v databázi (60 min, Klára, čeká, otisk tokenu)', dict(row) if row else None)
        time.sleep(.3); log = open(os.path.join(D, 'private/mail.log'), encoding='utf-8', errors='replace').read()
        bodies = ''.join(base64.b64decode(x).decode('utf-8', 'replace') for x in re.findall(r'Content-Type: text/plain; charset=UTF-8\r?\nContent-Transfer-Encoding: base64\r?\n\r?\n([A-Za-z0-9+/=\r\n]+?)\r?\n--', log))
        ok('To: <jana.web@example.com>' in log and 'To: <recepce@salon-vlna.test>' in log, 'e-mail zákaznici i recepci (fronta)')
        m = re.search(r'(http://localhost:%d/rezervace-zrusit\.html\?id=\S+&token=\S+)' % PORT, bodies[bodies.find('Jana Webová'):])
        ok(bool(m), 'v e-mailu je odkaz pro zrušení')
        # správa: přihlášení a potvrzení
        br.go(BASE + '/sprava.html', wait=1.5)
        st2 = json.loads(br.js(r'''return (async () => {
          const sleep = ms => new Promise(r => setTimeout(r, ms)), el = document.querySelector('#admin');
          for (let i = 0; i < 40 && !el.querySelector('form'); i++) await sleep(100);
          const o = { login: !!el.querySelector('.tsb-login') }, f = el.querySelector('form');
          f.elements.u.value = 'majitel'; f.elements.p.value = 'spatne-heslo'; f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await sleep(600);
          o.err = (el.querySelector('.tsb-err') || {}).textContent || '';
          const f2 = el.querySelector('form'); f2.elements.u.value = 'majitel'; f2.elements.p.value = 'heslo-majitelky-2026'; f2.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          for (let i = 0; i < 40 && !el.querySelector('.tsb-table'); i++) await sleep(100);
          o.rows = el.querySelectorAll('.tsb-table tbody tr').length; o.text = el.textContent;
          const c = [...el.querySelectorAll('[data-status="confirmed"]')].find(b => b.closest('tr').textContent.indexOf('Jana Webová') >= 0);
          c.click(); await sleep(800);
          o.after = el.textContent; return JSON.stringify(o); })();'''))
        ok(st2['login'] and 'Nesprávné' in st2['err'], 'správa: přihlašovací formulář, špatné heslo odmítnuto')
        ok(st2['rows'] == 2 and 'jana.web@example.com' in st2['text'] and 'Majitelka Vlna' in st2['text'], 'správa: seznam rezervací s kontakty a přihlášenou osobou', st2['text'][:200])
        con = sqlite3.connect(db); con.row_factory = sqlite3.Row
        ok(con.execute("SELECT status FROM ts_booking_bookings WHERE email='jana.web@example.com'").fetchone()[0] == 'confirmed', 'potvrzení ve správě uložené v databázi')
        ev = con.execute("SELECT actor, new_status FROM ts_booking_events WHERE action='stav'").fetchall()
        ok(any(e['actor'] == 'majitel' and e['new_status'] == 'confirmed' for e in ev), 'auditní záznam potvrzení')
        br.shot(os.path.join(LIB, 'reports', 'snimky', 'C-server-sprava.jpg'), full=True)
        # zákazník zruší odkazem
        br.go(m.group(1), wait=1.2)
        br.js("document.querySelector('[data-act=\"cancel\"]').click()"); time.sleep(.8)
        txt = br.js("document.querySelector('#zruseni').textContent")
        ok('zrušená' in txt, 'stránka zrušení: rezervace zrušena', txt)
        ok(sqlite3.connect(db).execute("SELECT status FROM ts_booking_bookings WHERE email='jana.web@example.com'").fetchone()[0] == 'cancelled', 'zrušení uložené v databázi')
        br.go(m.group(1), wait=1.2); br.js("document.querySelector('[data-act=\"cancel\"]').click()"); time.sleep(.6)
        ok('neplatný' in br.js("document.querySelector('#zruseni').textContent"), 'odkaz pro zrušení je jednorázový')
        br.go(BASE + '/index.html', wait=1.5)
        br.js("document.querySelector('[data-svc=\"damsky-strih\"]').click()"); time.sleep(.3)
        br.shot(os.path.join(LIB, 'reports', 'snimky', 'C-server-web.jpg'), full=True)
    finally:
        br.close()
    # cron (fronta + retence)
    p = subprocess.run([PHP, os.path.join(D, 'web/vendor/ts/ts-booking-server/bin/cron.php'), os.path.join(D, 'private/booking-server.php')], capture_output=True, text=True, env=env)
    ok(p.returncode == 0 and 'fronta:' in p.stdout, 'bin/cron.php běží', p.stdout + p.stderr)
    r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'verify', os.path.join(D, 'web')], capture_output=True, text=True)
    ok(r.returncode == 0, 'jádro v projektu beze změny (ts.py verify)', r.stdout)
finally:
    srv.terminate(); srv.wait(5)
    shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
