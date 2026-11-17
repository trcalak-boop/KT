#!/usr/bin/env python3
"""Ověření produkce po nahrání balíčku T8 – jen čtení (GET/HEAD), nic neukládá, nic neposílá. Poslední řádek: RESULT pass=N fail=M"""
import hashlib, os, re, urllib.error, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek-t8', 'k-nahrani')
B = 'https://www.tasstudio.eu'; N = 'https://nahledy.tasstudio.eu'
P = F = 0


class NR(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


def get(url):
    try:
        with urllib.request.build_opener(NR).open(urllib.request.Request(url, headers={'User-Agent': 'TS-overeni/1.0'}), timeout=25) as r: return r.status, dict(r.headers), r.read()
    except urllib.error.HTTPError as e: return e.code, dict(e.headers), e.read()


def ok(c, m, d=''):
    global P, F
    if c: P += 1; print('OK    ' + m)
    else: F += 1; print('CHYBA ' + m + (' | ' + str(d)[:200] if d else ''))


s, h, b = get(B + '/admin/studio.php'); ok(s in (302, 303) and 'studio' not in h.get('Location', ''), 'stará stránka Studia přesměrovává (%s → %s)' % (s, h.get('Location')))
s, h, b = get(B + '/admin/studio-api.php?r=list'); ok(s == 410 and b'gone' in b, 'API Studia zrušené (%s)' % s)
for f in ('admin/crm.js', 'admin/crm.css'):
    s, h, b = get(B + '/' + f + '?kontrola=t8'); loc = open(os.path.join(PKG, 'web', f), 'rb').read()
    ok(s == 200 and hashlib.sha256(b).digest() == hashlib.sha256(loc).digest(), '%s shodný s balíčkem' % f, s)
s, h, b = get(B + '/admin/crm-navrhy.php'); ok(s != 500 and b'navrhy_' not in b, 'crm-navrhy.php samostatně nic neprozradí (%s)' % s)
for dp, _, fs in os.walk(os.path.join(PKG, 'web')):
    for f in fs:
        rel = os.path.relpath(os.path.join(dp, f), os.path.join(PKG, 'web'))
        if not rel.endswith('.html'): continue
        s, h, b = get(B + '/' + rel); loc = open(os.path.join(dp, f), 'rb').read()
        ok(s == 200 and b == loc, 'zásady %s shodné s balíčkem' % rel, (s, len(b), len(loc)))
s, h, b = get(B + '/cs/ochrana-osobnich-udaju.html'); t = b.decode('utf-8', 'replace')
ok('připomínky nebo návrh schválit' in t and 'jazykový model Cloudflare, jak je popsáno výše' not in t, 'cs zásady: návrhy s připomínkami, bez předání do USA')
s, h, b = get(N + '/' + 'A' * 32 + '/'); ok(s in (403, 404, 410) and 'Set-Cookie' not in h, 'brána: neznámý odkaz %s, bez cookies' % s)
s, h, b = get(N + '/_nahled/1-1-1-x/'); ok(s in (403, 404), 'brána: neplatný interní náhled %s' % s)
s, h, b = get(N + '/' + 'A' * 32 + '/pripominka'); ok(s in (403, 404, 405, 410), 'brána: připomínka k neznámému odkazu %s' % s)
s, h, b = get(N + '/index.php'); ok(s != 500, 'brána běží (%s)' % s)
s, h, b = get(B + '/cs/'); ok(s == 200, 'web běží (%s)' % s)
print('RESULT pass=%d fail=%d' % (P, F))
