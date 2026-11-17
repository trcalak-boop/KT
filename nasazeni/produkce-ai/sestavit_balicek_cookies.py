#!/usr/bin/env python3
"""Balíček pro PRODUKCI tasstudio.eu: vyskakovací upozornění na cookies (ts.js, ts.css, verze souborů ts42 ve všech stránkách).

  balicek-cookies/
    k-nahrani/web/            → obsah nahrát do /web/   (změněné soubory proti git HEAD; nic z admin/ ani data/)
    navrat-pri-problemu/web/  → původní soubory (git HEAD)
    NAVOD.md, SHA256SUMS.txt, lokalne-NENAHRAVAT/

Verze dat ?v= ve stránkách se ověří proti živému webu. Spustit PŘED commitem (bere změny proti HEAD).
Použití: python3 -I sestavit_balicek_cookies.py"""
import datetime, hashlib, json, os, re, shutil, subprocess, sys, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web')
OUT = os.path.join(HERE, 'balicek-cookies'); KW = os.path.join(OUT, 'k-nahrani', 'web'); LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
SITE = 'https://www.tasstudio.eu'; V = 'ts42'


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout
def live(rel): return urllib.request.urlopen(urllib.request.Request(SITE + '/' + rel, headers={'User-Agent': 'ts-overeni'}), timeout=30).read()


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(KW); os.makedirs(LOC, mode=0o700)
    soubory = []
    for line in git('status', '--porcelain', '--untracked-files=all', '--', 'web/').splitlines():
        st, path = line[:2], line[3:].strip().strip('"')
        if 'D' in st: sys.exit('Smazaný soubor ve webu: ' + path)
        rel = path[len('web/'):]
        if rel.startswith(('admin/', 'data/')) or rel == 'ts-components.lock.json': sys.exit('Neočekávaná změna: ' + rel)
        soubory.append(rel)
    soubory.sort()
    assert 'assets/js/ts.js' in soubory and 'assets/css/ts.css' in soubory
    prod = live('cs/index.html').decode()
    dv = re.search(r'/data/reference\.js\?v=([0-9A-Za-z]+)', prod).group(1)
    assert 'ts.js?v=' + V not in prod, 'produkce už má ' + V
    for rel in soubory:
        p = os.path.join(WEB, rel)
        if rel.endswith('.html'):
            t = open(p, encoding='utf-8').read()
            for m in re.findall(r'/data/(?:nastaveni|reference)\.js\?v=([0-9A-Za-z]+)', t):
                if m != dv: sys.exit('%s: verze dat %s ≠ produkce %s' % (rel, m, dv))
            assert 'ts.js?v=' + V in t and 'v=ts41' not in t, rel
            d = subprocess.run(['git', '-C', PROJ, 'diff', 'HEAD', '--', 'web/' + rel], capture_output=True, text=True).stdout
            for l in d.splitlines():
                if l[:1] in '+-' and l[:3] not in ('+++', '---'): assert 'ts4' in l, rel + ': jiná změna než verze: ' + l[:120]
        os.makedirs(os.path.dirname(os.path.join(KW, rel)), exist_ok=True); shutil.copy2(p, os.path.join(KW, rel))
    js = open(os.path.join(WEB, 'assets/js/ts.js'), encoding='utf-8').read()
    assert 'function cookies()' in js and 'jazyk(); cookies();' in js

    N = os.path.join(OUT, 'navrat-pri-problemu', 'web')
    for rel in soubory:
        r = subprocess.run(['git', '-C', PROJ, 'show', 'HEAD:web/' + rel], capture_output=True, check=True)
        os.makedirs(os.path.dirname(os.path.join(N, rel)), exist_ok=True); open(os.path.join(N, rel), 'wb').write(r.stdout)

    sums = []
    for dp, _, fs in os.walk(KW):
        for f in sorted(fs):
            p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    open(os.path.join(OUT, 'SHA256SUMS.txt'), 'w').write('\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    open(os.path.join(LOC, 'soubory-web.txt'), 'w').write('\n'.join(soubory) + '\n')
    json.dump({'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory), 'data': dv, 'assets': V},
              open(os.path.join(LOC, 'balicek.json'), 'w'))
    open(os.path.join(OUT, 'NAVOD.md'), 'w', encoding='utf-8').write('''# Upozornění na cookies – nahrání na tasstudio.eu

Balíček obsahuje jen změněné soubory: %d do `/web` (skript a styl webu a všechny stránky se novou verzí souborů `ts42`).
**Nikdy nenahrávejte nic do `/web/admin/data`** (živé CRM). V balíčku nic z `admin/` ani `data/` není.

## 0. Záloha
Stáhněte z `/web` složku `assets/` a stránky `*.html` (kořen i jazykové složky) do `zalohy/produkce-%s/`.
Stačí i složka `navrat-pri-problemu/` v tomto balíčku – jsou v ní přesně původní verze.

## 1. Nahrání
Vlevo `k-nahrani/web/`, vpravo `/web`. Označte vlevo **všechno uvnitř** (`assets` a stránky i jazykové složky), pravé tlačítko › „Přidat soubory do fronty“,
zkontrolujte, že cíle ve frontě začínají `/web/…`, a spusťte frontu (⌘P). Přepsání potvrďte („Vždy použít tuto akci“).

## 2. Ověření
Napište mi „nahráno“. Ověřím jen čtením, že stránky mají `ts42`, nové okno se zobrazí a verze dat zůstaly.

## Návrat při problému
Nahrajte stejným postupem obsah `navrat-pri-problemu/web/` do `/web`.
''' % (len(soubory), datetime.date.today().isoformat()))
    print('balíček:', OUT, '| web:', len(soubory), '| data:', dv)


main()
