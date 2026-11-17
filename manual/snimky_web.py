#!/usr/bin/env python3
"""Snímky veřejného webu pro manuál (ukázkové prostředí msim). Volitelně jen vybrané: snimky_web.py w01 w05 …"""
import base64, os, sys, time, traceback
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
import msim
from cdp import Browser
OBR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'obr'); os.makedirs(OBR, exist_ok=True)
B = msim.BASE; b = None
ONLY = set(sys.argv[1:])


def shot(name, sel=None, pad=0, full=False):
    p = os.path.join(OBR, name + '.jpg')
    if sel:
        r = b.js("var e=document.querySelector(%r); if(!e) return null; var r=e.getBoundingClientRect(); return [r.left+scrollX, r.top+scrollY, r.width, r.height]" % sel)
        if not r: raise RuntimeError('není ' + sel)
        x, y, w, h = r
        clip = {'x': max(0, x - pad), 'y': max(0, y - pad), 'width': w + 2 * pad, 'height': h + 2 * pad, 'scale': 1}
        d = b.call('Page.captureScreenshot', format='jpeg', quality=86, clip=clip, captureBeyondViewport=True)['data']
        open(p, 'wb').write(base64.b64decode(d))
    else: b.shot(p, full=full, quality=84)
    print('  ', name)


def J(c, w=0): return b.js(c, w)
def click(sel, w=.6): J("document.querySelector(%r).click()" % sel, w)
def click_text(sel, text, w=.6): J("[...document.querySelectorAll(%r)].find(e=>e.textContent.trim().indexOf(%r)===0).click()" % (sel, text), w)
def desktop(): b.size(1280, 860); b.scheme(False)
def accept_cookies(): J("try{var x=[...document.querySelectorAll('button')].find(e=>e.textContent.trim()=='Jen nezbytné'); if(x&&x.offsetParent) x.click()}catch(e){}", .4)


def w01():
    desktop(); b.go(B + '/cs/', 2.2); shot('w01-uvod-cookies')
def w02():
    click('.ck-link', 1); shot('w02-cookies-nastaveni')
def w03():
    b.go(B + '/cs/', 2); accept_cookies(); J("scrollTo(0,0)", .4); shot('w03-uvod')
def w04():
    pass
    b.go(B + '/cs/', 1.5); J("document.querySelector('#langBtn').click()", .6); shot('w04-jazyky-menu')
def w05():
    b.go(B + '/cs/', 1.5); click('.ico.theme', 1); J("scrollTo(0,0)", .3); shot('w05-tmavy'); click('.ico.theme', .5)
def w06():
    b.go(B + '/cs/', 1.5); click('#svcBtn', .8); shot('w06-sluzby-menu')
def w07():
    b.go(B + '/cs/reference.html', 2.5); shot('w07-reference')
def w08():
    b.go(B + '/cs/reference.html', 2); href = J("var a=document.querySelector('a[href*=\"projekt.html\"]'); return a?a.getAttribute('href'):''")
    b.go(B + '/cs/' + href if not href.startswith('/') else B + href, 2.5); shot('w08-detail-zakazky')
    J("scrollTo(0, 900)", .8); shot('w08b-detail-zakazky-galerie')
def w09():
    b.go(B + '/cs/kontakt.html', 2); J("document.querySelector('#leadForm').scrollIntoView({block:'start'}); scrollBy(0,-90)", .6); shot('w09-formular-1')
    J("document.querySelectorAll('#leadForm [name=sluzby]')[0].click(); document.querySelectorAll('#leadForm [name=sluzby]')[2].click(); document.querySelector('#leadForm [name=msg]').value='Dobrý den, chtěli bychom nový web pro naši pekárnu s objednávkou dortů.'", .3)
    J("document.querySelector('#fNext').click()", .8); shot('w09-formular-2')
    J("document.querySelectorAll('#leadForm [name=budget]')[2].click(); document.querySelectorAll('#leadForm [name=when]')[1].click()", .3)
    J("document.querySelector('#fNext').click()", .8); shot('w09-formular-3')
    J("var f=document.querySelector('#leadForm'); f.name.value='Jana Nováková'; f.company.value='Pekárna U Zlatého klasu'; f.email.value='jana.novakova@example.test'; f.phone.value='+420 600 111 222'", .3)
    J("document.querySelector('#fNext').click()", .8); shot('w09-formular-4')
def w10():
    desktop(); b.go(B + '/cs/', 2); accept_cookies(); click('.tsai-launcher', 2.2); shot('w10-tessa-uvod', '.tsai-panel', 16)
    J("var t=document.querySelector('.tsai-input'); t.value='Potřebujeme nový web pro naši pekárnu.'; t.dispatchEvent(new Event('input',{bubbles:true})); document.querySelector('.tsai-send').click()", 6)
    shot('w11-tessa-odpoved', '.tsai-panel', 16)
    J("var t=document.querySelector('.tsai-input'); t.value='Ano, chci nabídku, ozvěte se mi.'; t.dispatchEvent(new Event('input',{bubbles:true})); document.querySelector('.tsai-send').click()", 10)
    J("var l=document.querySelector('.tsai-log'); l.scrollTop=l.scrollHeight", .8); shot('w12-tessa-formular', '.tsai-panel', 16)
    J("[...document.querySelectorAll('.tsai-log button')].find(e=>e.textContent.trim()=='Kontaktovat studio').click()", 1)
    J("var l=document.querySelector('.tsai-log'); l.scrollTop=l.scrollHeight", .5); shot('w12b-tessa-kontakt', '.tsai-panel', 16)
    J("var P=document.querySelector('.tsai-panel'); function f(n,v){var e=P.querySelector('[name='+n+']'); e.value=v; e.dispatchEvent(new Event('input',{bubbles:true}))} f('name','Petr Svoboda'); f('email','petr.svoboda@example.test'); f('phone','+420 608 333 444'); f('message','Pekárna Svoboda – web s objednávkou dortů.'); P.querySelector('[name=consent]').click()", 9)
    J("var l=document.querySelector('.tsai-log'); l.scrollTop=l.scrollHeight", .3); shot('w12c-tessa-kontakt-vyplneny', '.tsai-panel', 16)
    J("var P=document.querySelector('.tsai-panel'); var x=P.querySelector('[name=consent]').closest('form'); (x.querySelector('[type=submit]')||x.querySelector('button')).click()", 5)
    J("var l=document.querySelector('.tsai-log'); l.scrollTop=l.scrollHeight", .5); shot('w12d-tessa-odeslano', '.tsai-panel', 16)
    print(J("return document.querySelector('.tsai-log').innerText.slice(-300)"))
def w13():
    b.size(390, 844, mobile=True); b.go(B + '/cs/', 2.5); accept_cookies(); shot('w13-mobil-uvod')
    J("var m=[...document.querySelectorAll('button')].find(e=>/menu/i.test(e.getAttribute('aria-label')||'')||e.classList.contains('burger')); m&&m.click()", 1); shot('w13-mobil-menu')
    desktop()
def w14():
    desktop(); b.go(B + '/ukazky/motoria.html', 3); shot('w14-ukazka')
def w15():
    b.go(B + '/cs/ochrana-osobnich-udaju.html', 1); J("try{localStorage.clear();sessionStorage.clear()}catch(e){}"); b.go(B + '/cs/ochrana-osobnich-udaju.html', 2); accept_cookies()
    J("var x=document.querySelector('.tsai-panel'); if(x&&x.offsetParent){[...x.querySelectorAll('button')].find(e=>e.textContent.trim()=='×').click()}", .5); shot('w15-zasady')


ALL = [w01, w02, w03, w04, w05, w06, w07, w08, w09, w10, w13, w14, w15]
msim.start()
try:
    b = Browser(9392); desktop()
    for f in ALL:
        if ONLY and f.__name__ not in ONLY: continue
        try: f()
        except Exception: print('CHYBA', f.__name__); traceback.print_exc(limit=1)
finally:
    if b: b.close()
    msim.stop()
