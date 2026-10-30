#!/usr/bin/env python3
"""Rozdílový test PHP jádra (TsBookingEngine) proti JS jádru (ts-booking-core) na datech ukázek – 3 časové scénáře.
Spouští ho tools/ts.py test; poslední řádek výstupu: RESULT pass=N fail=M"""
import json, os, subprocess, sys, tempfile, time, urllib.parse
HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.abspath(os.path.join(HERE, '..', '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
SRC = json.load(open(os.path.join(LIB, 'tools', 'sources.json')))['tas-web']
PHP = os.path.expanduser(os.environ.get('TS_PHP', '~/TS-Studio/tools/php/php'))
P = F = 0


def norm(x):
    if isinstance(x, dict):
        return {k: norm(v) for k, v in x.items() if v is not None}
    if isinstance(x, list):
        return [norm(v) for v in x]
    if isinstance(x, float) and x.is_integer():
        return int(x)
    return x


def check(ok, msg):
    global P, F
    if ok: P += 1
    else:
        F += 1; print('FAIL ' + msg)


br = Browser(port=9363)
try:
    for cas in ['2026-11-03T10:17:00', '2026-11-07T18:40:00', '2026-12-21T06:05:00']:
        url = 'file://' + urllib.parse.quote(os.path.join(HERE, 'diff-gen.html')) + '?' + urllib.parse.urlencode({'cas': cas, 'tas-web': 'file://' + urllib.parse.quote(SRC), 'lib': 'file://' + urllib.parse.quote(LIB)})
        br.call('Page.navigate', url=url)
        data = None
        for _ in range(600):
            time.sleep(.1)
            data = br.js('window.__DIFF ? JSON.stringify(window.__DIFF) : null')
            if data: break
        d = json.loads(data or '{"error": "časový limit"}')
        if 'error' in d:
            check(False, cas + ': generátor: ' + d['error'][:300]); continue
        with tempfile.TemporaryDirectory() as t:
            fi, fo = os.path.join(t, 'in.json'), os.path.join(t, 'out.json')
            json.dump(d, open(fi, 'w'))
            r = subprocess.run([PHP, '-d', 'memory_limit=1G', os.path.join(HERE, 'diff_run.php'), fi, fo], capture_output=True, text=True)
            if r.returncode or not os.path.exists(fo):
                check(False, cas + ': PHP selhalo: ' + (r.stderr or r.stdout)[:500]); continue
            out = json.load(open(fo))
        n = 0
        for case, act in zip(d['cases'], out['cases']):
            bad = 0
            for i, (q, e, a) in enumerate(zip(case['queries'], case['expected'], act['actual'])):
                n += 1
                if norm(e) != norm(a):
                    bad += 1
                    if bad <= 3: print('FAIL %s %s %s %s | JS %s | PHP %s' % (cas, case['name'], q[0], json.dumps(q[1], ensure_ascii=False)[:120], json.dumps(e)[:200], json.dumps(a)[:200]))
            check(bad == 0 and len(case['expected']) == len(act['actual']), '%s %s: %d rozdílů z %d dotazů' % (cas, case['name'], bad, len(case['queries'])))
        for (cfg, ev), pv in zip(d['validate'], out['validate']):
            check(ev == pv, '%s validate: JS %s PHP %s' % (cas, ev, pv))
        print('INFO %s: porovnáno %d dotazů (%s)' % (cas, n, ', '.join('%s %d' % (c['name'], len(c['queries'])) for c in d['cases'])))
finally:
    br.close()
print('RESULT pass=%d fail=%d' % (P, F))
