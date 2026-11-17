#!/usr/bin/env python3
"""Ověření produkce https://www.tasstudio.eu po nasazení AI asistenta – bez dotazů na AI (nic neplatí, nic neukládá).
    python3 -I -B overit_nasazeni.py [--pred] [--po-kontrole]
--pred: stav před nahráním (jen HTTPS přesměrování a že AI ještě není).  --po-kontrole: kontrolní stránka už je smazaná."""
import json, os, sys, urllib.request, urllib.error
B = 'https://www.tasstudio.eu'; HERE = os.path.dirname(os.path.abspath(__file__))
pred = '--pred' in sys.argv; po = '--po-kontrole' in sys.argv
bad = 0


def get(url, method='GET', data=None, h=None):
    try:
        r = urllib.request.urlopen(urllib.request.Request(url, method=method, data=data, headers=h or {}), timeout=20); return r.status, r.read(400000).decode('utf-8', 'replace')
    except urllib.error.HTTPError as e: return e.code, e.read(6000).decode('utf-8', 'replace')


def rep(good, label):
    global bad
    bad += not good; print(('OK    ' if good else 'CHYBA ') + label)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


for host in ('http://tasstudio.eu', 'http://www.tasstudio.eu'):
    try: urllib.request.build_opener(NoRedirect).open(host + '/cs/', timeout=20); code, loc = 200, ''
    except urllib.error.HTTPError as e: code, loc = e.code, e.headers.get('Location', '')
    rep(code in (301, 302, 307, 308) and loc.startswith('https://'), 'HTTP → HTTPS %s/cs/  %s %s' % (host, code, loc))
if pred:
    c, _ = get(B + '/api/ai/chat.php?r=status'); rep(c == 404, 'AI brána ještě není nasazená (%s)' % c)
    print('\n' + ('Vše v pořádku.' if not bad else 'Problémů: %d' % bad)); sys.exit(1 if bad else 0)
CHECK = json.load(open(os.path.join(HERE, 'balicek', 'lokalne-NENAHRAVAT', 'balicek.json')))['check']
for path, want, label in [
    ('/vendor/ts/ts-ai-consultant/public/ts-ai-widget.js?v=1.5.1', 200, 'skript widgetu'), ('/vendor/ts/ts-ai-consultant/public/ts-ai-widget.css?v=1.5.1', 200, 'styly widgetu'),
    ('/tessa/tessa-portret.jpg?v=1', 200, 'portrét Tessy'), ('/vendor/ts/ts-ai-consultant/core/TsAiConsultant.php', 403, 'jádro brány nepřístupné'), ('/vendor/ts/ts-auth-php/core/TsAuth.php', 403, 'knihovna přihlášení nepřístupná'),
    ('/vendor/ts/ts-mailer-php/migrations/001_ts_mail_queue.sql', 403, 'SQL knihoven nepřístupné'), ('/vendor/ts/ts-ai-consultant/module.json', 403, 'manifest knihovny nepřístupný'),
    ('/vendor/ts/ts-ai-consultant/public/.htaccess', 403, '.htaccess widgetu nepřístupný'), ('/admin/crm-ai.php', 403, 'CRM napojení nepřístupné'),
    ('/admin/crm-lib.php', 403, 'CRM knihovna nepřístupná'), ('/admin/data/crm.sqlite', 403, 'databáze CRM nepřístupná'),
    ('/ts-components.lock.json', 404, 'zámek komponent na produkci není'), ('/' + CHECK, 404 if po else 200, 'kontrolní stránka ' + ('SMAZANÁ' if po else 'dostupná (smazat po kontrole)')),
    ('/poptavka.php', 405, 'poptávkový formulář odpovídá (GET odmítnut)'),
]:
    c, body = get(B + path)
    leak = any(x in body for x in ('sk-ant-', 'smtp_pass', 'codeHash', 'password_hash', '<?php'))
    rep((c == want or (want in (403, 404) and c in (403, 404, 466))) and not leak, '%-52s %s (čekáno %s)%s' % (label + ' ' + path.split('?')[0], c, want, '  ÚNIK!' if leak else ''))
c, body = get(B + '/api/ai/chat.php?r=status')
try: st = json.loads(body)
except Exception: st = {}
rep(c == 200 and st.get('enabled') is True, 'brána: zapnuto (klíč, SDK, rozpočet)  %s %s' % (c, body[:90]))
c, _ = get(B + '/api/ai/chat.php?r=message', 'POST', b'{"text":"x"}', {'Content-Type': 'text/plain'}); rep(c == 415, 'brána: jiný Content-Type odmítnut bez AI (%s)' % c)
c, _ = get(B + '/api/ai/chat.php?r=message', 'POST', b'{"text":"x"}', {'Content-Type': 'application/json', 'Origin': 'https://cizi.example'}); rep(c == 403, 'brána: cizí Origin odmítnut bez AI (%s)' % c)
for path, lang in [('/', 'en'), ('/cs/', 'cs'), ('/de/', 'de'), ('/ja/', 'ja'), ('/cs/kontakt.html', 'cs')]:
    c, body = get(B + path)
    rep(c == 200 and 'ts-ai-widget.js?v=1.5.1' in body and '"name":"Tessa"' in body, 'stránka %s načítá Tessu (widget 1.5.1)' % path)
for path in ('/privacy.html', '/cs/ochrana-osobnich-udaju.html', '/de/datenschutz.html'):
    c, body = get(B + path); rep(c == 200 and 'Cloudflare, Inc.' in body and 'Anthropic' not in body, 'zásady %s uvádějí Cloudflare (ne Anthropic)' % path)
print('\n' + ('Vše v pořádku.' if not bad else 'Problémů: %d' % bad)); sys.exit(1 if bad else 0)
