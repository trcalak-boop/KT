#!/usr/bin/env python3
"""Integrační test demonstračních projektů (~/TS-Studio/demo-projekty): kadeřnictví a autosalon nad stejným jádrem.

    python3 -I tools/integration_test.py

Spustí lokální HTTP server (jen 127.0.0.1) nad složkou projektů, v Chromu bez okna projde rezervaci v obou webech,
ověří oddělená data, rozdílnou konfiguraci a vzhled, oborové rozšíření, přehled rezervací a mobilní šířku,
uloží snímky do reports/snimky/ a zkontroluje, že jádro v projektech i v knihovně zůstalo beze změny.
"""
import functools, hashlib, http.server, json, os, subprocess, sys, threading, time

LIB = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
DEMO = os.path.expanduser('~/TS-Studio/demo-projekty')
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser

res = []


def ok(cond, msg, detail=''):
    res.append((bool(cond), msg, detail))
    print(('OK    ' if cond else 'CHYBA ') + msg + (('  [' + str(detail)[:160] + ']') if detail and not cond else ''))


class Quiet(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *a):
        pass


def serve(port):
    h = functools.partial(Quiet, directory=DEMO)
    srv = http.server.ThreadingHTTPServer(('127.0.0.1', port), h)
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    return srv


def core_hashes():
    out = {}
    for p in ['components/ts-booking-core/core/booking-core.js', 'components/ts-booking-core/adapters/store-demo-local.js', 'components/ts-booking-widget/core/booking-widget.js', 'components/ts-booking-widget/core/booking-widget.css']:
        out[p] = hashlib.sha256(open(os.path.join(LIB, p), 'rb').read()).hexdigest()
    return out


BOOK = r'''
return (async () => {
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  for (let i = 0; i < 60 && !document.querySelector('.tsb-card'); i++) await sleep(100);
  const el = document.querySelector('#widget'), q = s => el.querySelector(s), out = {};
  out.demo = !!q('.tsb-demo'); out.services = el.querySelectorAll('[data-svc]').length;
  q('[data-svc="%(svc)s"]').click(); await sleep(30);
  %(pre)s
  out.resources = [...el.querySelectorAll('[data-res]')].map(b => b.dataset.res);
  if (q('[data-res="%(res)s"]')) { q('[data-res="%(res)s"]').click(); await sleep(30); }
  const day = q('.tsb-day.tsb-free'); out.date = day && day.dataset.date; day.click(); await sleep(30);
  const t = q('.tsb-time'); out.t = +t.dataset.t; t.click(); await sleep(30);
  const f = q('.tsb-form'); f.elements.name.value = '%(name)s'; f.elements.email.value = 'test@example.com'; f.elements.consent.checked = true;
  f.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await sleep(120);
  out.done = !!q('.tsb-done'); out.doneText = q('.tsb-done') ? q('.tsb-done').textContent : '';
  out.ls = Object.keys(localStorage).filter(k => k.indexOf('ts-booking-demo:') === 0);
  out.stored = JSON.parse(localStorage.getItem('ts-booking-demo:%(key)s') || '{}').bookings || [];
  out.accent = getComputedStyle(el).getPropertyValue('--tsb-accent').trim();
  out.font = getComputedStyle(el).getPropertyValue('--tsb-font').trim();
  out.overflow = document.documentElement.scrollWidth > window.innerWidth + 1;
  return JSON.stringify(out);
})();
'''


def main():
    t0 = time.time()
    before = core_hashes()
    port = 8150
    srv = serve(port)
    br = Browser(port=9362)
    shots = os.path.join(LIB, 'reports', 'snimky'); os.makedirs(shots, exist_ok=True)
    try:
        br.size(1280, 900)
        # --- A: kadeřnictví ---
        br.go('http://127.0.0.1:%d/kadernictvi-vlna/index.html' % port, wait=1.5)
        br.js('localStorage.clear(); location.reload()'); time.sleep(1.5)
        a = json.loads(br.js(BOOK % {'svc': 'barveni', 'pre': "q('[data-variant=\"2\"]').click(); await sleep(30);", 'res': 'any', 'name': 'Jana Vlnová', 'key': 'salon-vlna'}))
        ok(a['demo'], 'A: upozornění „rezervace jen v prohlížeči“ je vidět')
        ok(a['services'] == 4, 'A: 4 služby salonu z konfigurace', a['services'])
        ok(a['resources'] == ['any', 'klara', 'tereza'], 'A: barvení umí jen Klára a Tereza (+ kdokoli)', a['resources'])
        ok(a['done'] and len(a['stored']) == 1, 'A: rezervace dokončena a uložena', a)
        b0 = a['stored'][0] if a['stored'] else {}
        ok(b0.get('dur') == 165 and b0.get('buffer') == 15 and b0.get('resource') in ('klara', 'tereza'), 'A: délka podle délky vlasů (165 min + 15 příprava), přidělený stylista', b0)
        ok(a['accent'] == '#b4536f', 'A: vlastní vzhled (akcent salonu)', a['accent'])
        br.shot(os.path.join(shots, 'A-kadernictvi-potvrzeni.jpg'), full=True)
        br.js("location.reload()"); time.sleep(1.2)
        br.js("document.querySelector('[data-svc=\"damsky-strih\"]').click()"); time.sleep(.2)
        br.shot(os.path.join(shots, 'A-kadernictvi-vyber.jpg'), full=True)
        # --- B: autosalon ---
        br.go('http://127.0.0.1:%d/autosalon-dynamo/index.html' % port, wait=1.5)
        b = json.loads(br.js(BOOK % {'svc': 'testovaci-jizda', 'pre': '', 'res': 'tucson', 'name': 'Petr Dynamo', 'key': 'autosalon-dynamo'}))
        ok(b['demo'], 'B: upozornění „rezervace jen v prohlížeči“ je vidět')
        ok(b['resources'] == ['octavia', 'tucson', 'id4', 'model3'], 'B: výběr konkrétního vozu bez „kdokoli“', b['resources'])
        ok(b['done'] and len(b['stored']) == 1 and b['stored'][0].get('resource') == 'tucson', 'B: testovací jízda uložena na vybraný vůz', b['stored'])
        ok(b['stored'] and b['stored'][0].get('poolMember') in ('prodejce-novak', 'prodejce-dvorak'), 'B: přidělen prodejce z poolu', b['stored'])
        ok('zarezervovaná' in b['doneText'], 'B: vlastní texty projektu (ui.labels)')
        ok(b['accent'] == '#ff7a1a' and 'Helvetica' in b['font'], 'B: jiný vzhled (tmavý, oranžový akcent)', b['accent'])
        ok(set(b['ls']) == {'ts-booking-demo:salon-vlna', 'ts-booking-demo:autosalon-dynamo'}, 'oddělená data: každý projekt vlastní úložiště', b['ls'])
        ok(len(json.loads(br.js("return JSON.stringify(JSON.parse(localStorage.getItem('ts-booking-demo:salon-vlna')).bookings)"))) == 1, 'rezervace autosalonu nezměnila data salonu')
        # pool a rozšíření v jádru přímo na stránce B (stejná konfigurace jako widget)
        chk = json.loads(br.js(r'''return fetch('ts-config/ts-booking-core/autosalon-dynamo.json').then(r => r.json()).then(c => {
          const d = '%s', S = { bookings: [
            { id: 'x1', date: d, resource: 'octavia', service: 'testovaci-jizda', start: 600, dur: 30, buffer: 15, status: 'confirmed', poolMember: 'prodejce-novak' },
            { id: 'x2', date: d, resource: 'tucson', service: 'testovaci-jizda', start: 600, dur: 30, buffer: 15, status: 'confirmed', poolMember: 'prodejce-dvorak' }] };
          const nab = { name: 'nabijeni', check: x => x.resource && x.resource.id === 'id4' && TSBooking.date.wd(x.date) === 6 && x.t >= 660 ? 'nabijeni' : '' };
          const E = TSBooking.create(c, S, { extensions: [nab] });
          let sat = TSBooking.date.addDays(d, 0); while (TSBooking.date.wd(sat) !== 6) sat = TSBooking.date.addDays(sat, 1);
          return JSON.stringify({ pool: (E.scan(d, 'id4', 'testovaci-jizda').find(s => s.t === 600) || {}).why,
            satWhy: E.scan(sat, 'id4', 'testovaci-jizda').filter(s => s.t >= 660).map(s => s.why),
            satOther: E.scan(sat, 'octavia', 'testovaci-jizda').some(s => s.t >= 660 && s.ok),
            away: E.scan('2026-11-10', 'model3', 'testovaci-jizda').length });
        });''' % b['date']))
        ok(chk['pool'] == 'pool', 'B: třetí souběžná jízda zakázaná (2 prodejci)', chk)
        ok(chk['satWhy'] and set(chk['satWhy']) == {'nabijeni'} and chk['satOther'], 'B: oborové rozšíření „nabíjení“ platí jen pro ID.4', chk)
        ok(chk['away'] == 0, 'B: vůz mimo provoz (away) není nabízen', chk)
        br.shot(os.path.join(shots, 'B-autosalon-potvrzeni.jpg'), full=True)
        br.js("location.reload()"); time.sleep(1.2)
        br.js("document.querySelector('[data-svc=\"testovaci-jizda\"]').click()"); time.sleep(.2)
        br.js("document.querySelector('[data-res=\"octavia\"]').click()"); time.sleep(.2)
        br.shot(os.path.join(shots, 'B-autosalon-vyber.jpg'), full=True)
        # --- přehled rezervací ---
        for proj, key, name in [('kadernictvi-vlna', 'A', 'Jana Vlnová'), ('autosalon-dynamo', 'B', 'Petr Dynamo')]:
            br.go('http://127.0.0.1:%d/%s/sprava.html' % (port, proj), wait=1.2)
            txt = br.js('document.querySelector("#admin").textContent')
            ok(name in txt and ('Jana' in txt) == (key == 'A'), '%s: přehled rezervací ukazuje jen vlastní data' % key, txt[:120])
        br.shot(os.path.join(shots, 'B-autosalon-sprava.jpg'), full=True)
        # --- mobil ---
        for proj, key in [('kadernictvi-vlna', 'A'), ('autosalon-dynamo', 'B')]:
            br.size(390, 844, scale=2, mobile=True)
            br.go('http://127.0.0.1:%d/%s/index.html' % (port, proj), wait=1.4)
            br.js("document.querySelector('[data-svc]').click()"); time.sleep(.2)
            ov = br.js('document.documentElement.scrollWidth > window.innerWidth + 1')
            ok(not ov, '%s: mobil 390 px bez vodorovného posunu' % key)
            br.shot(os.path.join(shots, '%s-mobil.jpg' % key), full=False)
        br.size(1280, 900)
        errs = br.js('JSON.stringify(window.__errs || [])')
    finally:
        br.close(); srv.shutdown()
    # --- jádro beze změny ---
    after = core_hashes()
    ok(before == after, 'knihovna: jádro rezervací se testem nezměnilo')
    for p in ['kadernictvi-vlna', 'autosalon-dynamo']:
        r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'verify', os.path.join(DEMO, p)], capture_output=True, text=True)
        ok(r.returncode == 0, '%s: ts.py verify – jádro v projektu beze změny' % p, r.stdout.strip())
    lock = [json.load(open(os.path.join(DEMO, p, 'ts-components.lock.json')))['components']['ts-booking-core']['files']['core/booking-core.js'] for p in ['kadernictvi-vlna', 'autosalon-dynamo']]
    ok(lock[0] == lock[1] == after['components/ts-booking-core/core/booking-core.js'], 'oba projekty používají totéž jádro (stejný kontrolní součet jako knihovna)')
    for p in ['kadernictvi-vlna', 'autosalon-dynamo']:
        own = [os.path.join(dp, f) for dp, _, fs in os.walk(os.path.join(DEMO, p)) if '/vendor/' not in dp + '/' for f in fs if f.endswith('.js')]
        bad = [f for f in own if 'cal.free' in open(f).read() or 'function scan' in open(f).read() or 'slots = function' in open(f).read()]
        ok(not bad, '%s: vlastní kód projektu neobsahuje kopii rezervační logiky' % p, bad)
    n_ok = sum(1 for r in res if r[0]); n_bad = len(res) - n_ok
    print('Celkem %d kontrol, %d chyb (%.0f s)' % (len(res), n_bad, time.time() - t0))
    json.dump({'at': time.strftime('%Y-%m-%dT%H:%M:%S'), 'pass': n_ok, 'fail': n_bad, 'checks': [{'ok': r[0], 'test': r[1], 'detail': str(r[2])[:300]} for r in res]},
              open(os.path.join(LIB, 'reports', 'integrace-posledni.json'), 'w'), ensure_ascii=False, indent=2)
    return 1 if n_bad else 0


if __name__ == '__main__':
    sys.exit(main())
