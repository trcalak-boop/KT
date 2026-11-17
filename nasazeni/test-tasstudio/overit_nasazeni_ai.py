#!/usr/bin/env python3
"""Ověření nasazení AI asistenta na https://test.tasstudio.eu – bez dotazů na AI (nic neplatí, nic neukládá).
GET na stránky a stav brány; dva POSTy, které brána odmítne ještě před AI (špatný Content-Type, cizí Origin).
    python3 -I -B overit_nasazeni_ai.py [--po-kontrole]
--po-kontrole: kontrolní stránka už musí být smazaná (404)."""
import json, os, sys, urllib.request, urllib.error
B = 'https://test.tasstudio.eu'
CHECK = json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), '3-balicek-ai', 'lokalne-NENAHRAVAT', 'balicek.json')))['check']
po = '--po-kontrole' in sys.argv
bad = 0


def get(path, method='GET', data=None, headers=None):
    try:
        r = urllib.request.urlopen(urllib.request.Request(B + path, method=method, data=data, headers=headers or {}), timeout=20); return r.status, r.read(6000).decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read(6000).decode('utf-8', 'replace')


def rep(good, label, extra=''):
    global bad
    bad += not good; print(('OK    ' if good else 'CHYBA ') + label + ('  ' + extra if extra else ''))


for path, want, label in [
    ('/asistent.html', 200, 'testovací stránka asistenta'), ('/vendor/ts/ts-ai-consultant/public/ts-ai-widget.js', 200, 'skript widgetu'),
    ('/vendor/ts/ts-ai-consultant/public/ts-ai-widget.css', 200, 'styly widgetu'),
    ('/vendor/ts/ts-ai-consultant/core/TsAiConsultant.php', 403, 'PHP jádro nepřístupné'), ('/vendor/ts/ts-ai-consultant/config/consultant.example.php', 403, 'vzor konfigurace nepřístupný'),
    ('/vendor/ts/ts-ai-consultant/migrations/001_ai.sqlite.sql', 403, 'migrace nepřístupné'), ('/vendor/ts/ts-ai-consultant/bin/report.php', 403, 'skripty nepřístupné'),
    ('/vendor/ts/ts-ai-consultant/sdk/composer.lock', 403, 'složka sdk nepřístupná'), ('/ts-components.lock.json', 403, 'zámek komponent nepřístupný'),
    ('/' + CHECK, 404 if po else 200, 'kontrolní stránka ' + ('SMAZANÁ' if po else 'dostupná (smazat po kontrole)')),
    ('/api/rezervace.php?r=config', 200, 'rezervace fungují dál'), ('/ai-consultant.php', 404, 'konfigurace není ve webrootu'), ('/znalosti.json', 404, 'znalosti nejsou ve webrootu'),
]:
    code, body = get(path)
    leak = any(x in body for x in ('sk-ant-', 'smtp_pass', 'codeHash', '/private/'))
    rep((code == want or (want == 404 and code in (403, 404))) and not leak, '%-58s %s (čekáno %s)' % (label + ' ' + path, code, want), 'ÚNIK!' if leak else '')
code, body = get('/api/ai/chat.php?r=status')
try: st = json.loads(body)
except Exception: st = {}
rep(code == 200 and st.get('enabled') is True and len(st.get('langs', [])) == 18, 'brána: stav zapnuto (klíč, SDK, rozpočet)', '%s %s' % (code, body[:120]))
code, _ = get('/api/ai/chat.php?r=message'); rep(code == 405, 'brána: GET na zprávu odmítnut (405)', str(code))
code, _ = get('/api/ai/chat.php?r=message', 'POST', b'{"text":"x"}', {'Content-Type': 'text/plain'}); rep(code == 415, 'brána: jiný Content-Type odmítnut (415, bez AI)', str(code))
code, _ = get('/api/ai/chat.php?r=message', 'POST', b'{"text":"x"}', {'Content-Type': 'application/json', 'Origin': 'https://cizi.example'}); rep(code == 403, 'brána: cizí Origin odmítnut (403, bez AI)', str(code))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


for path in ('/asistent.html', '/api/ai/chat.php?r=status'):
    try:
        urllib.request.build_opener(NoRedirect).open('http://test.tasstudio.eu' + path, timeout=20); code, loc = 200, ''
    except urllib.error.HTTPError as e:
        code, loc = e.code, e.headers.get('Location', '')
    rep(code in (301, 308) and loc == B + path, 'HTTP → HTTPS %-36s %s %s' % (path, code, loc))
print('\n%s' % ('Vše v pořádku.' if not bad else 'Problémů: %d' % bad))
sys.exit(1 if bad else 0)
