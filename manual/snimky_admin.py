#!/usr/bin/env python3
"""Snímky administrace pro manuál (ukázkové prostředí msim, smyšlená data ze seed.py). Volitelně jen vybrané kroky: snimky_admin.py a01 a07 …"""
import base64, io, json, os, re, sqlite3, sys, time, traceback, urllib.request, zipfile
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
import msim
from cdp import Browser
OBR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'obr'); os.makedirs(OBR, exist_ok=True)
B = msim.BASE; b = None; ONLY = set(sys.argv[1:])
S = json.load(open(os.path.join(msim.SIM, 'stav.json')))


def shot(name, sel=None, pad=0, full=False):
    p = os.path.join(OBR, name + '.jpg')
    if sel:
        r = b.js("var e=document.querySelector(%r); if(!e) return null; var r=e.getBoundingClientRect(); return [r.left+scrollX, r.top+scrollY, r.width, r.height]" % sel)
        if not r: raise RuntimeError('není ' + sel)
        x, y, w, h = r
        d = b.call('Page.captureScreenshot', format='jpeg', quality=86, clip={'x': max(0, x - pad), 'y': max(0, y - pad), 'width': w + 2 * pad, 'height': h + 2 * pad, 'scale': 1}, captureBeyondViewport=True)['data']
        open(p, 'wb').write(base64.b64decode(d))
    else: b.shot(p, full=full, quality=84)
    print('  ', name)


def J(c, w=0): return b.js(c, w)
def until(e, t=20):
    for _ in range(int(t / .3)):
        if J('return !!(' + e + ')'): return True
        time.sleep(.3)
    return False
def clean(): J("[...document.querySelectorAll('.banner')].forEach(e=>{if(/šifrování|vlastní heslo/.test(e.textContent)) e.remove()})", .2)
def go(h, w=2.2):
    if h.startswith('/admin/#'): h = '/admin/?r=%d#' % (time.time() * 1000) + h.split('#', 1)[1]
    b.go(B + h, w); clean()
def btn(text, scope='body', w=.8): J("[...document.querySelector(%r).querySelectorAll('button')].filter(e=>e.offsetParent).find(e=>e.textContent.trim().indexOf(%r)===0).click()" % (scope, text), w)
def desktop(h=860): b.size(1280, h); b.scheme(False)
def dlg(name): until("document.querySelector('#crmDlg') && document.querySelector('#crmDlg').open", 8); time.sleep(.5); shot(name, '#crmDlg .dlg, #crmDlg form, #crmDlg > div', 0) if J("return !!document.querySelector('#crmDlg .dlg, #crmDlg form, #crmDlg > div')") else shot(name)
def close(): J("var d=document.querySelector('#crmDlg'); if(d&&d.open){var x=d.querySelector('[data-close]'); x?x.click():d.close()}", .4)


def a01():
    b.call('Network.deleteCookies', name='tsadmin', url=B); desktop(); b.go(B + '/admin/', 2); shot('a01-prihlaseni')
    b.call('Network.setCookie', name='tsadmin', value=msim.SID[1], url=B, path='/admin/')
def a02():
    desktop(900); go('/admin/', 2.5); J("document.querySelector('[data-tab=ref]').click()", 1); shot('a02-reference')
    J("document.querySelector('#plist li, #plist button, #plist a').click()", 1.2); shot('a02b-reference-editor')
    for i, y in enumerate([900, 1800, 2700, 3600]):
        J("var e=document.querySelector('#editor'); (e.scrollHeight>e.clientHeight+10 ? e : document.scrollingElement).scrollTop=%d" % y, .6); shot('a02c-reference-editor-%d' % i)
def a03():
    go('/admin/', 2); J("document.querySelector('[data-tab=ref]').click()", .8); J("document.querySelector('#plist li, #plist button, #plist a').click()", 1)
    J("var t=document.querySelector('#editor textarea'); t.value=t.value+' '; t.dispatchEvent(new Event('input',{bubbles:true}))", .6); shot('a03-neulozeno', 'header', 0)
    J("document.querySelector('#btnSave').click()", 4); until("document.querySelector('#doneDlg').open", 30); shot('a03b-ulozeno', '#doneDlg .dlg', 0)
    J("document.querySelector('#doneOk').click()", .4)
def a04():
    go('/admin/', 2); J("document.querySelector('[data-tab=kontakty]').click()", 1); shot('a04-kontakty', full=True)
def a05():
    go('/admin/', 2); J("document.querySelector('[data-tab=zalohy]').click()", 1.5); shot('a05-zalohy')
def a06():
    go('/admin/', 2); J("document.querySelector('[data-tab=ucet]').click()", 1); shot('a06-ucet', full=True)
def a07():
    desktop(); go('/admin/#crm', 3); shot('a07-crm-prehled', full=True)
def a08():
    go('/admin/#crm/leady', 3); shot('a08-leady', full=True)
    print(J("return [...document.querySelectorAll('#tab-crm button')].filter(e=>e.offsetParent).map(e=>e.textContent.trim().slice(0,25)).join(' | ')"))
def a09():
    go('/admin/#crm/ukoly', 3); shot('a09-ukoly', full=True)
def a10():
    go('/admin/#crm/lead/%d' % S['autoservis'], 3); shot('a10-lead-detail', full=True)
    btn('+ Zaznamenat kontakt', '#tab-crm', 1); dlg('a11-dlg-kontakt'); close()
    btn('Změnit stav', '#tab-crm', 1); dlg('a12-dlg-stav'); close()
    btn('Přidat úkol', '#tab-crm', 1); dlg('a13-dlg-ukol'); close()
    btn('Upravit lead', '#tab-crm', 1); dlg('a14-dlg-upravit'); close()
    J("document.querySelector('#tab-crm [data-act=menu]').click()", .8); J("scrollTo(0,0)",.3); shot('a15-dalsi-akce')
    print(J("return [...document.querySelectorAll('#tab-crm button')].filter(e=>e.offsetParent&&e.closest('[role=menu],.menu,.pop,.dd')).map(e=>e.textContent.trim()).join(' | ')"))
def a16():
    go('/admin/#crm/lead/%d' % S['pekarna'], 3); shot('a16-lead-z-webu', full=True)
    ai = sqlite3.connect(os.path.join(msim.DATA, 'crm.sqlite')).execute("SELECT max(id) FROM leads").fetchone()[0]
    go('/admin/#crm/lead/%d' % ai, 3); shot('a17-lead-z-tessy', full=True)
def a18():
    go('/admin/#crm/leady', 2.5); btn('+ Nový lead', '#tab-crm', 1); dlg('a18-dlg-novy-lead'); close()
    J("[...document.querySelectorAll('#tab-crm button')].find(e=>e.offsetParent&&/Načíst ze souboru/.test(e.textContent)).click()", 1); dlg('a19-dlg-import'); close()
def a20():
    go('/admin/#crm/lead/%d' % S['truhlar'], 3)
    btn('Připravit dokument k podpisu', '#tab-crm', 1); dlg('a20-dlg-souhlas')
    J("function v(i,x){var e=document.getElementById(i); e.value=x; e.dispatchEvent(new Event('input',{bubbles:true}))} v('sgJm','Tomáš Dub'); v('sgFn','jednatel'); v('sgIco','12345678'); v('sgAd','Dřevařská 12, 760 01 Zlín'); [...document.querySelectorAll('#crmDlg [name=services]')].slice(0,2).forEach(e=>{if(!e.checked)e.click()}); [...document.querySelectorAll('#crmDlg [name=purposes]')].forEach(e=>{if(!e.checked)e.click()})", .5)
    dlg('a20b-dlg-souhlas-vyplneny')
    J("document.querySelector('#crmDlg [type=submit]').click()", 3)
    shot('a21-souhlas-vytvoren', full=True)
    c = sqlite3.connect(os.path.join(msim.DATA, 'crm.sqlite')).execute('SELECT id FROM lead_consents ORDER BY id DESC').fetchone()[0]
    r = urllib.request.Request(B + '/admin/api.php?a=crm_souhlas_pdf&id=%d' % c, headers={'Cookie': 'tsadmin=' + msim.SID[1]})
    open(os.path.join(OBR, '..', 'souhlas.pdf'), 'wb').write(urllib.request.urlopen(r).read())
    go('/admin/#crm/lead/%d' % S['truhlar'], 3); btn('Zaznamenat podpis', '#tab-crm', 1); dlg('a22-dlg-podpis'); close()


def zip_kavarna():
    z = io.BytesIO()
    html = open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'navrh-kavarna.html'), encoding='utf-8').read()
    with zipfile.ZipFile(z, 'w', zipfile.ZIP_DEFLATED) as f: f.writestr('index.html', html)
    return z.getvalue()


def a30():
    desktop(); go('/admin/#crm/lead/%d' % S['kavarna'], 3); until("document.querySelector('#crmNavrhy:not([hidden])')")
    pass
    J("(document.querySelector('#crmNavrhy [data-nv=nahrat][data-p]')||document.querySelector('#crmNavrhy [data-nv=nahrat]')).click()", .6); dlg('a31-dlg-nahrat')
    data = base64.b64encode(zip_kavarna()).decode()
    J("var s=atob('%s'),a=new Uint8Array(s.length);for(var i=0;i<s.length;i++)a[i]=s.charCodeAt(i);var dt=new DataTransfer();dt.items.add(new File([a],'modry-hrnek.zip'));document.querySelector('#nvF').files=dt.files;document.querySelector('#crmDlg [type=submit]').click()" % data, 1)
    until("!document.querySelector('#crmDlg').open && document.querySelector('.nv-frame iframe')", 40); time.sleep(2.5)
    shot('a32-navrh-prehled', '#crmNavrhy', 0)
    J("document.querySelector('[data-nv=poslat]').click()", 1.2); dlg('a33-dlg-poslat')
    J("document.querySelector('#nv-po').value=document.querySelector('#nv-to').value; document.querySelector('#crmDlg [type=submit]').click()", 2)
    until("!document.querySelector('#crmDlg').open"); time.sleep(1); shot('a34-navrh-odeslany', '#crmNavrhy', 0)


def link():
    raw = open(os.path.join(msim.PRIV, 'studio', 'data', 'posta.log'), errors='replace').read(); txt = raw
    for blk in re.findall(r'base64\r?\n\r?\n([A-Za-z0-9+/=\r\n]+)', raw):
        try: txt += base64.b64decode(re.sub(r'\s', '', blk)).decode('utf-8', 'replace')
        except Exception: pass
    return re.findall(re.escape(msim.GBASE) + r'/[A-Za-z0-9_-]{32}/', txt)[-1]


def a35():
    desktop(); L = link(); b.go(L, 3); shot('a35-klient-stranka')
    J("document.querySelector('form[action$=pripominka]').scrollIntoView()", .6); shot('a36-klient-pripominky')
    J("var f=document.querySelector('form[action$=pripominka]'); if(f.cast) f.cast.value=f.cast.options[1]?f.cast.options[1].value:''; f.text.value='Prosím větší fotky dortů a výraznější tlačítko Rezervace.'; f.jmeno&&(f.jmeno.value='Eva Modrá')", 6)
    J("document.querySelector('form[action$=pripominka] button').click()", 2); shot('a37-klient-dekujeme')
    desktop(); go('/admin/#crm', 3); go('/admin/#crm/lead/%d' % S['kavarna'], 3); until("document.querySelector('.nv-fb')"); time.sleep(1)
    shot('a38-navrh-pripominka', '#crmNavrhy', 0); shot('a38b-lead-po-pripomince', full=True)
    b.go(L, 2); J("document.querySelector('.apr button').click()", 2); shot('a39-klient-schvaleno')
    go('/admin/#crm', 3)


def a40():
    desktop(); go('/admin/audity.php', 2.5); shot('a40-audity')
    print(J("return [...document.querySelectorAll('#ts-audit input,#ts-audit button')].map(e=>e.name+'/'+e.type+'='+e.textContent.trim()).join(' | ')"))
    J("var i=document.querySelector('#ts-audit input[type=url],#ts-audit input'); i.value='https://www.example.com'; i.dispatchEvent(new Event('input',{bubbles:true})); (i.form.querySelector('[type=submit]')||i.form.querySelector('button')).click()", 12)
    shot('a41-audit-vysledek', full=True)
def a42():
    desktop(); go('/admin/navstevnost.php', 2.5); shot('a42-navstevnost', full=True)
def a50():
    b.size(390, 844, mobile=True); go('/admin/#crm', 3); shot('a50-mobil-prehled')
    go('/admin/#crm/lead/%d' % S['autoservis'], 3); shot('a51-mobil-lead')
    J("document.querySelector('[data-m=vice]').click()", 1); shot('a52-mobil-vice'); desktop()


ALL = [a01, a02, a03, a04, a05, a06, a07, a08, a09, a10, a16, a18, a20, a30, a35, a40, a42, a50]
db = sqlite3.connect(os.path.join(msim.DATA, 'crm.sqlite'))
db.execute("UPDATE users SET password_hash = '$2y$10$ukazkaukazkaukazkaukazuJ0p8CwMZt4QmF8m1wZ8pQ9oX1mYq5uK' WHERE password_hash IS NULL"); db.commit(); db.close()
msim.start()
try:
    b = Browser(9393); desktop()
    b.call('Network.enable'); b.call('Network.setCookie', name='tsadmin', value=msim.SID[1], url=B, path='/admin/')
    for f in ALL:
        if ONLY and f.__name__ not in ONLY: continue
        try: f()
        except Exception: print('CHYBA', f.__name__); traceback.print_exc(limit=2)
finally:
    if b: b.close()
    msim.stop()
