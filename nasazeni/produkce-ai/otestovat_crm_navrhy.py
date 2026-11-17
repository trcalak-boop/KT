#!/usr/bin/env python3
"""Test bloku Návrhy webu v CRM na simulovaném hostingu: kořen/web = web projektu, kořen/private/studio, brána náhledů přes php -S, smyšlené CRM (TS_ADMIN_DATA),
přihlášení = připravená relace, e-mail jen do logu (TsMailer transport log). Žádná produkční data ani služby. Poslední řádek: RESULT pass=N fail=M"""
import base64, email, io, json, os, re, shutil, sqlite3, subprocess, sys, tempfile, time, urllib.error, urllib.request, zipfile
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; LIB = os.path.expanduser('~/TS-Studio/ts-library')
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT, GPORT = 8251, 8252; BASE = 'http://localhost:%d' % PORT; GBASE = 'http://127.0.0.1:%d' % GPORT
SHOTS = os.environ.get('TS_UI_SHOTS', '')
P = F = 0


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:600] if d else ''))


tmp = tempfile.mkdtemp(prefix='crmnv-'); R = os.path.join(tmp, 'koren'); W = os.path.join(R, 'web'); PS = os.path.join(R, 'private', 'studio'); DATA = os.path.join(tmp, 'crm-data'); G = os.path.join(R, 'sub', 'nahledy')
shutil.copytree(os.path.join(PROJ, 'web'), W, ignore=shutil.ignore_patterns('reference', 'ukazky', 'zalohy', 'crm.sqlite*', 'relace'))
shutil.rmtree(os.path.join(W, 'admin', 'data'), ignore_errors=True)
os.makedirs(os.path.join(PS, 'data')); os.makedirs(os.path.join(DATA, 'relace')); os.makedirs(G)
open(os.path.join(PS, 'studio.php'), 'w').write("""<?php
$private = __DIR__;
$studio = ['db' => ['dsn' => 'sqlite:' . $private . '/data/studio.sqlite', 'autoMigrate' => true], 'timezone' => 'Europe/Prague', 'uploadDir' => $private . '/data/navrhy',
  'deliverySecret' => str_repeat('t', 40), 'previewBase' => '%s/', 'retentionMonths' => 24, 'feedbackTo' => ['tasstudio@tasstudio.example'], 'crmUrl' => '%s/admin/#crm/lead',
  'mail' => ['transport' => 'log', 'logFile' => $private . '/data/posta.log', 'from' => 'studio@tasstudio.example', 'fromName' => 'T&S Studio']];
return ['enabled' => true, 'studio' => $studio, 'previewContact' => 'tasstudio.eu', 'allowedOrigins' => ['%s'], 'adminOrigins' => ['%s']];
""" % (GBASE, BASE, BASE, BASE))
open(os.path.join(G, 'router.php'), 'w').write("""<?php
require %r . '/vendor/ts/ts-design-studio/core/bootstrap.php';
$cfg = require %r . '/studio.php';
(new TsStudioPreviewGate(ts_design_studio($cfg['studio']), ['failDir' => %r . '/data/brana', 'contact' => 'tasstudio.eu', 'adminOrigins' => $cfg['adminOrigins']]))->serve();
""" % (W, PS, PS))
SID = {1: 'a' * 26 + '01', 2: 'b' * 26 + '02'}
for uid, sid in SID.items():
    open(os.path.join(DATA, 'relace', 'sess_' + sid), 'w').write('csrf|s:9:"token-%03d";ok|b:1;t|i:%d;uid|i:%d;' % (uid, int(time.time()), uid))
open(os.path.join(DATA, 'heslo.php'), 'w').write("<?php return ['hash' => '$2y$10$abcdefghijklmnopqrstuuJ0p8CwMZt4QmF8m1wZ8pQ9oX1mYq5uK'];\n")      # jen příznak „administrace je nainstalovaná“, přihlášení je relace
env = dict(os.environ, TS_ADMIN_DATA=DATA)
srv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-d', 'upload_max_filesize=4M', '-d', 'post_max_size=5M', '-S', 'localhost:%d' % PORT, '-t', W], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=env, start_new_session=True)
gsrv = subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', '127.0.0.1:%d' % GPORT, os.path.join(G, 'router.php')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True); time.sleep(1.4)


def api(a, body=None, uid=1, q=''):
    h = {'Cookie': 'tsadmin=' + SID[uid], 'Origin': BASE}
    if body is not None: h['Content-Type'] = 'application/json'; h['X-CSRF'] = 'token-%03d' % uid
    r = urllib.request.Request(BASE + '/admin/api.php?a=' + a + q, data=json.dumps(body).encode() if body is not None else None, headers=h)
    try:
        with urllib.request.urlopen(r, timeout=30) as x: return json.loads(x.read())
    except urllib.error.HTTPError as e:
        t = e.read().decode('utf-8', 'replace')
        try: return json.loads(t)
        except Exception: return {'ok': False, 'http': e.code, 'text': t[:300]}


def zipb(files):
    b = io.BytesIO()
    with zipfile.ZipFile(b, 'w', zipfile.ZIP_DEFLATED) as z:
        for k, v in files.items(): z.writestr(k, v)
    return b.getvalue()


HTML = '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pekárna Veveří</title><link rel="stylesheet" href="styl.css"></head><body><h1>Chléb z kvasu</h1><h2>Nabídka</h2><img src="foto.bin.png" alt="" width="10"><h2>Kontakt</h2><script>document.title="běží"</script></body></html>'
CSS = ':root{--pozadi:#fff8ea;--akcent:#e4572e}body{background:var(--pozadi);color:#16324f;font-family:Georgia,serif}h1{color:var(--akcent)}@media (prefers-color-scheme:dark){:root{--pozadi:#101010}}'
GOOD = zipb({'navrh/index.html': HTML, 'navrh/styl.css': CSS, 'navrh/foto.bin.png': os.urandom(2500000)})    # víc než 1 MB: nahrává se po částech
LOG = os.path.join(PS, 'data', 'posta.log')


def mails():
    if not os.path.exists(LOG): return []
    out = []
    raw = open(LOG, newline='', encoding='utf-8', errors='replace').read()
    for m in re.split(r'(?<=--)\r\n(?:\r\n)*(?=[A-Za-z-]+: )', raw):      # zprávy končí uzavíracím oddělovačem MIME
        if m.strip(): out.append(email.message_from_string(m))
    return out


def body(m): return ''.join(p.get_payload(decode=True).decode('utf-8', 'replace') for p in m.walk() if p.get_content_type() == 'text/plain')
b = None
try:
    api('crm_init')             # vytvoří databázi CRM
    db = sqlite3.connect(os.path.join(DATA, 'crm.sqlite'))
    db.execute("UPDATE users SET name = 'Adam Admin', email = 'adam@tasstudio.example', role = 'admin', active = 1 WHERE id = 1")
    db.execute("UPDATE users SET name = 'Věra Viewer', email = 'vera@tasstudio.example', role = 'viewer', active = 1 WHERE id = 2")
    db.commit(); db.close()
    r = api('crm_lead_ulozit', {'name': 'Pekárna Veveří', 'email': 'klient@example.test', 'phone': '+420600000001', 'priority': 'standardni', 'assigned_user_id': 1, 'outreach_reason': 'Poptávka webu', 'duplicitu_potvrdit': True})
    lead = r.get('id'); ok(lead, 'smyšlený lead založený', r)
    ok(api('crm_navrhy', q='&id=%s' % lead).get('zapnuto') is True, 'návrhy jsou zapnuté')

    b = Browser(9375); b.size(1400, 1000); b.scheme(False)
    b.call('Network.enable'); b.call('Network.setCookie', name='tsadmin', value=SID[1], url=BASE, path='/admin/')
    b.go(BASE + '/admin/#crm/lead/%s' % lead, 2.5)
    def J(c, w=0): return b.js(c, w)
    def until(e, t=20):
        for _ in range(int(t / .3)):
            if J('return !!(' + e + ')'): return True
            time.sleep(.3)
        return False
    ok(until("document.querySelector('#crmNavrhy:not([hidden])')"), 'blok Návrhy webu v detailu leadu', J("return document.body.innerText.slice(0,200)"))
    ok('Zatím žádný návrh' in (J("return document.querySelector('#crmNavrhy').innerText") or ''), 'prázdný stav')

    def upload(bytes_, name):
        J("(document.querySelector('#crmNavrhy [data-nv=nahrat][data-p]')||document.querySelector('#crmNavrhy [data-nv=nahrat]')).click()", .3)
        J("var s=atob('%s'),a=new Uint8Array(s.length);for(var i=0;i<s.length;i++)a[i]=s.charCodeAt(i);var dt=new DataTransfer();dt.items.add(new File([a],'%s'));document.querySelector('#nvF').files=dt.files;document.querySelector('#crmDlg [type=submit]').click();" % (base64.b64encode(bytes_).decode(), name))
    upload(GOOD, 'navrh.zip')
    ok(until("!document.querySelector('#crmDlg').open && document.querySelector('.nv-frame iframe')", 40), 'nahrání po částech a přehled', J("return (document.querySelector('#dErr')||{}).textContent"))
    t = J("return document.querySelector('.nv-info').innerText") or ''
    ok('Pekárna Veveří' in t and '#fff8ea' in t and '#e4572e' in t and '#101010' in t and 'Georgia' in t and 'Nabídka' in t and 'Kontroly v pořádku' in t and 'MB' in t, 'přehled: titulek, barvy, tmavý režim, písmo, sekce, kontroly, velikost', t)
    fr = J("return document.querySelector('.nv-frame iframe').src")
    ok(fr.startswith(GBASE + '/_nahled/') and J("return document.querySelector('.nv-frame iframe').getAttribute('sandbox')") == 'allow-scripts', 'náhled přes bránu v sandboxu', fr)
    with urllib.request.urlopen(fr) as x: hd = dict(x.headers); bd = x.read().decode()
    ok('Chléb z kvasu' in bd and BASE in hd.get('Content-Security-Policy', '') and 'sandbox allow-scripts' in hd.get('Content-Security-Policy', '') and 'X-Frame-Options' not in hd, 'interní náhled smí jen administrace', hd.get('Content-Security-Policy'))
    bad = re.sub(r'-[A-Za-z0-9_-]{32}/', '-' + 'A' * 32 + '/', fr)
    try: urllib.request.urlopen(bad); ok(False, 'podvržený podpis náhledu')
    except urllib.error.HTTPError as e: ok(e.code == 404, 'podvržený podpis náhledu = 404')
    time.sleep(1)
    if SHOTS: os.makedirs(SHOTS, exist_ok=True); b.shot(os.path.join(SHOTS, 'crm.jpg'), quality=65)

    # odeslání
    ok(J("return !!document.querySelector('[data-nv=poslat]')"), 'tlačítko Poslat klientovi')
    J("document.querySelector('[data-nv=poslat]').click()", 1)
    ok(until("document.querySelector('#crmDlg').open && document.querySelector('#nv-to')") and J("return document.querySelector('#nv-to').value") == 'klient@example.test' and 'Pekárna' in J("return document.querySelector('#nv-su').value") and '{odkaz}' in J("return document.querySelector('#nv-bo').value"), 'předvyplněný e-mail z leadu')
    J("document.querySelector('#nv-po').value='spatny@example.test';document.querySelector('#crmDlg [type=submit]').click()", 1)
    ok(J("return document.querySelector('#dErr').textContent").find('potvrzení') >= 0 and not mails(), 'špatné potvrzení: nic se neodešle', J("return document.querySelector('#dErr').textContent"))
    J("document.querySelector('#nv-po').value='klient@example.test';document.querySelector('#crmDlg [type=submit]').click()", 1.5)
    ok(until("!document.querySelector('#crmDlg').open"), 'odesláno', J("return document.querySelector('#dErr').textContent"))
    m = mails(); ok(len(m) == 1 and 'klient@example.test' in m[0]['To'] and 'adam@tasstudio.example' in str(m[0]['Reply-To']), 'e-mail klientovi, odpověď na přiřazeného', m and dict(m[0].items()))
    link = re.search(re.escape(GBASE) + r'/[A-Za-z0-9_-]{32}/', body(m[0])).group(0)
    ok('Adam Admin' in body(m[0]), 'podpis přihlášeného')
    ok(until("document.querySelector('.nv-del')") and 'platí do' in J("return document.querySelector('.nv-del').innerText"), 'doručení v přehledu')

    # klient
    b.go(link, 2)
    ok(J("return !!document.querySelector('iframe.dsg') && !!document.querySelector('textarea[name=text]')"), 'klient vidí návrh a připomínky')
    J("var f=document.querySelector('form[action$=pripominka]');f.cast.value='Nabídka';f.text.value='Prosím větší ceny';f.querySelector('button').click()", 1.5)
    ok('stav=ok' in J("return location.href"), 'připomínka odeslána')
    m = mails(); to = [str(x['To']) for x in m[1:]]
    ok(any('tasstudio@tasstudio.example' in x for x in to) and any('adam@tasstudio.example' in x for x in to) and 'Prosím větší ceny' in body(m[1]) and '#crm/lead/%s' % lead in body(m[1]), 'upozornění nám i přiřazenému, s odkazem do CRM', to)
    J("var f=document.querySelector('form[action$=pripominka]');f.text.value='A ještě logo';f.querySelector('button').click()", 1.5)

    # zpět v CRM
    c = api('crm_odznak'); ok(c.get('pocet') == 1, 'odznak: úkol po přenosu', c)
    api('crm_odznak')
    L = api('crm_lead', q='&id=%s' % lead)
    ukoly = [u for u in L['ukoly'] if u['title'] == 'Reagovat na připomínky k návrhu']
    ok(len(ukoly) == 1 and ukoly[0]['status'] == 'ceka', 'úkol jen jednou i na dvě připomínky', L['ukoly'])
    h = [x['new'] for x in L['historie'] if x['action'] == 'navrh']
    ok(sum('Připomínka klienta' in (x or '') for x in h) == 2 and any('odeslán' in (x or '') for x in h) and any('Nahraný návrh' in (x or '') for x in h), 'historie leadu', h)
    b.go(BASE + '/admin/#crm/lead/%s' % lead, 2.5); until("document.querySelector('.nv-fb')")
    t = J("return document.querySelector('#crmNavrhy').innerText") or ''
    ok('Prosím větší ceny' in t and 'Nabídka' in t and re.search(r'Nevyřízené připomínky:\s*2', t) and 'otevřeno' in t, 'připomínky v bloku, počet nevyřízených, otevření odkazu', t[:600])
    J("document.querySelector('[data-nv=vyrizeno]').click()", 1); until("(document.querySelector('.nv-open')||{}).textContent && document.querySelector('.nv-open').textContent.indexOf('1')>=0")
    ok(re.search(r'Nevyřízené připomínky:\s*1', J("return document.querySelector('#crmNavrhy').innerText") or ''), 'označení vyřízené')
    # schválení
    b.go(link, 1.5); J("document.querySelector('.apr button').click()", 1.5)
    ok('vyřešeno' in J("return document.querySelector('.prev').textContent"), 'klient vidí vyřešenou připomínku')
    api('crm_odznak'); L = api('crm_lead', q='&id=%s' % lead)
    ok(any(u['title'].startswith('Klient schválil návrh') for u in L['ukoly']), 'úkol po schválení')

    # další verze a chyby
    b.go(BASE + '/admin/#crm/lead/%s' % lead, 2.5); until("document.querySelector('.nv-fb')")
    upload(zipb({'index.html': HTML.replace('styl.css', 'chybi.css'), 'foto.bin.png': b'x'}), 'chyba.zip')
    ok(until("!document.querySelector('#crmDlg').open && document.querySelector('.nv-chk')", 30), 'verze s chybou se nahraje', J("return (document.querySelector('#dErr')||{}).textContent"))
    t = J("return document.querySelector('#crmNavrhy').innerText") or ''
    ok('chybi.css' in t and 'nejde poslat' in t and not J("return !!document.querySelector('[data-nv=poslat]')"), 'chyba v přehledu a nejde poslat', t[:400])
    upload(zipb({'index.html': HTML, 'zly.php': '<?php echo 1;'}), 'zly.zip')
    ok(until("(document.querySelector('#dErr')||{}).textContent") and 'zly.php' in J("return document.querySelector('#dErr').textContent"), 'PHP v ZIPu se odmítne', J("return document.querySelector('#dErr').textContent"))
    J("document.querySelector('#crmDlg [data-close]').click()", .2)

    # oprávnění, odvolání, cizí lead
    r2 = api('crm_lead_ulozit', {'name': 'Jiný lead', 'email': 'jiny@example.test', 'priority': 'standardni', 'assigned_user_id': 1, 'outreach_reason': 'x'}); dv = api('crm_navrhy', q='&id=%s' % lead)
    pid = dv['navrhy'][0]['id']
    ok(api('crm_navrh_pripravit', {'project': pid, 'num': 1}, uid=2).get('ok') is False, 'viewer nemůže posílat')
    ok(api('crm_navrhy', q='&id=%s' % r2.get('id'))['navrhy'] == [], 'jiný lead návrhy nevidí')
    did = [d for d in dv['navrhy'][0]['deliveries'] if d['status'] == 'sent'][0]['id']
    ok(api('crm_navrh_odvolat', {'id': did}).get('ok') is True, 'odvolání')
    try: urllib.request.urlopen(link); ok(False, 'odvolaný odkaz')
    except urllib.error.HTTPError as e: ok(e.code == 404, 'odvolaný odkaz = 404')

    # mobil
    b.size(390, 844, 2, True); b.go(BASE + '/admin/#crm/lead/%s' % lead, 2.5); until("document.querySelector('.nv-frame iframe')")
    ok(J("return document.documentElement.scrollWidth<=innerWidth+1"), 'telefon bez vodorovného posuvníku', J("return document.documentElement.scrollWidth"))
    ok(J("return document.querySelector('.nv-frame').getBoundingClientRect().right<=innerWidth"), 'telefon: náhled se vejde')
    # CRM bez nastavení návrhů
    os.rename(os.path.join(PS, 'studio.php'), os.path.join(PS, 'x.php'))
    ok(api('crm_navrhy', q='&id=%s' % lead).get('zapnuto') is False and api('crm_odznak').get('ok') is True, 'bez konfigurace CRM funguje dál a blok se skryje')
finally:
    if b: b.close()
    os.killpg(srv.pid, 15); os.killpg(gsrv.pid, 15)
    shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
