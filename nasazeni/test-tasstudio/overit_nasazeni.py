#!/usr/bin/env python3
"""Ověření nasazení na https://test.tasstudio.eu – JEN ČTENÍ (GET). Nic nezapisuje, nepřihlašuje se, nerezervuje.
    python3 -I -B overit_nasazeni.py [--po-instalaci]
--po-instalaci: instalátor už musí být smazaný (404)."""
import json, os, sys, urllib.request, urllib.error
B = 'https://test.tasstudio.eu'
INST = json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), '2-balicek', 'lokalne-NENAHRAVAT', 'balicek.json')))['installer']
po = '--po-instalaci' in sys.argv
checks = [
    ('/', 200, 'úvodní stránka'), ('/sprava.html', 200, 'stránka správy'), ('/rezervace-zrusit.html', 200, 'stránka zrušení'),
    ('/api/rezervace.php?r=config', 200, 'API konfigurace'), ('/api/rezervace.php?r=admin/bookings', 401, 'správa bez přihlášení'),
    ('/vendor/ts/ts-booking-server/core/TsBookingStore.php', 403, 'PHP jádro nepřístupné'), ('/vendor/ts/ts-booking-server/config/server.example.php', 403, 'vzory konfigurace nepřístupné'),
    ('/vendor/ts/ts-booking-server/migrations/001_booking.sqlite.sql', 403, 'migrace nepřístupné'), ('/vendor/ts/ts-booking-server/bin/cron.php', 403, 'skripty nepřístupné'),
    ('/vendor/ts/ts-mailer-php/migrations/001_ts_mail_queue.sql', 403, 'SQL maileru nepřístupné'), ('/ts-components.lock.json', 403, 'zámek komponent nepřístupný'),
    ('/vendor/', 403, 'výpis složek vypnutý'), ('/robots.txt', 200, 'robots.txt'),
    ('/' + INST, 404 if po else 200, 'instalátor ' + ('SMAZANÝ' if po else 'dostupný (smazat po instalaci)')),
    ('/booking-server.php', 404, 'konfigurace není ve webrootu'), ('/data/rezervace-test.sqlite', 404, 'databáze není ve webrootu'),
]
bad = 0
for path, want, label in checks:
    try:
        r = urllib.request.urlopen(urllib.request.Request(B + path, method='GET'), timeout=20); code, hdr, body = r.status, r.headers, r.read(4000).decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        code, hdr, body = e.code, e.headers, e.read(4000).decode('utf-8', 'replace')
    good = code == want or (want == 404 and code in (403, 404))
    leak = any(x in body for x in ('smtp_pass', 'codeHash', 'sqlite:', '/private/', 'password_hash'))
    if leak: good = False
    bad += not good
    print(('OK    ' if good else 'CHYBA ') + '%-62s %s (čekáno %s)%s' % (label + ' ' + path, code, want, '  ÚNIK!' if leak else ''))
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
for path in ('/', '/api/rezervace.php?r=config', '/sprava.html'):
    try:
        urllib.request.build_opener(NoRedirect).open('http://test.tasstudio.eu' + path, timeout=20); code, loc = 200, ''
    except urllib.error.HTTPError as e:
        code, loc = e.code, e.headers.get('Location', '')
    good = code in (301, 308) and loc == B + path; bad += not good
    print(('OK    ' if good else 'CHYBA ') + 'HTTP → HTTPS %-40s %s %s' % (path, code, loc))
r = urllib.request.urlopen(B + '/', timeout=20); hsts = r.headers.get('Strict-Transport-Security', '')
print(('OK    ' if 'max-age' in hsts else 'CHYBA ') + 'HSTS: ' + hsts); bad += 'max-age' not in hsts
xr = r.headers.get('X-Robots-Tag', '')
print(('OK    ' if 'noindex' in xr else 'CHYBA ') + 'hlavička X-Robots-Tag: ' + xr); bad += 'noindex' not in xr
j = json.loads(urllib.request.urlopen(B + '/api/rezervace.php?r=availability', timeout=20).read())
print(('OK    ' if j.get('ok') and all('name' not in b and 'email' not in b for b in j['state']['bookings']) else 'CHYBA ') + 'dostupnost bez osobních údajů (%d rezervací)' % len(j['state']['bookings']))
print('Celkem %d kontrol, %d chyb' % (len(checks) + 6, bad)); sys.exit(1 if bad else 0)
