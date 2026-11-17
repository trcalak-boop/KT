#!/usr/bin/env python3
"""Doplňkový balíček pro PRODUKCI tasstudio.eu (po T1): hlas přes prohlížeč + animovaný maskot Tessy + zásady s hlasem.

  balicek-t3/
    k-nahrani/web/          → obsah nahrát do /web/   (jen změněné soubory proti produkci = git HEAD projektu)
    k-nahrani/private-ai/   → obsah nahrát do /private/ai/   (jen tessa/capabilities.json: hlas je dostupný)
    lokalne-NENAHRAVAT/     → seznam souborů, výsledky testu
    NAVOD.md, SHA256SUMS.txt

Konfigurace /private/ai/ai-consultant.php (sůl, kontrolní kód, token Cloudflare) se NEMĚNÍ.
Nikdy do balíčku: admin/ (CRM a jeho data), data/ (spravuje administrace), ts-components.lock.json.
Verze dat ?v= ve stránkách musí odpovídat produkci (administrace ji mění) – ověří se proti živému webu.
Použití: python3 -I sestavit_balicek_t3.py"""
import datetime, hashlib, json, os, re, shutil, subprocess, sys, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web')
OUT = os.path.join(HERE, 'balicek-t3'); KW = os.path.join(OUT, 'k-nahrani', 'web'); KA = os.path.join(OUT, 'k-nahrani', 'private-ai'); LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
NIKDY = ('admin/', 'data/', 'ts-components.lock.json')
SITE = 'https://www.tasstudio.eu'


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout


def live(rel): return urllib.request.urlopen(urllib.request.Request(SITE + '/' + rel, headers={'User-Agent': 'ts-overeni'}), timeout=30).read()


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(KW); os.makedirs(os.path.join(KA, 'tessa')); os.makedirs(LOC, mode=0o700)

    soubory = []
    for line in git('status', '--porcelain', '--untracked-files=all', '--', 'web/').splitlines():
        st, path = line[:2], line[3:].strip().strip('"')
        if 'D' in st: sys.exit('Smazaný soubor ve webu (balíček umí jen přidat/přepsat): ' + path)
        rel = path[len('web/'):]
        if rel.startswith(NIKDY[:2]) or rel == NIKDY[2]: continue
        soubory.append(rel)
    soubory.sort()

    # verze dat a mezipaměti ve stránkách = produkce (jinak by nahrání vrátilo zpět „Obnovit mezipaměť“ z administrace)
    v = {}
    for k, pat in (('data', r'/data/reference\.js\?v=([0-9A-Za-z]+)'), ('assets', r'/assets/css/ts\.css\?v=([0-9A-Za-z]+)')):
        v[k] = re.search(pat, live('cs/index.html').decode()).group(1)
    for rel in soubory:
        if rel.endswith('.html'):
            t = open(os.path.join(WEB, rel), encoding='utf-8').read()
            for k, pat in (('data', r'/data/(?:nastaveni|reference)\.js\?v=([0-9A-Za-z]+)'), ('assets', r'/assets/css/ts\.css\?v=([0-9A-Za-z]+)')):
                for m in re.findall(pat, t):
                    if m != v[k]: sys.exit('%s: verze %s %s ≠ produkce %s (spusťte build a srovnejte verze)' % (rel, k, m, v[k]))
        os.makedirs(os.path.dirname(os.path.join(KW, rel)), exist_ok=True); shutil.copy2(os.path.join(WEB, rel), os.path.join(KW, rel))

    cap = os.path.join(PROJ, 'ai', 'tessa', 'capabilities.json')
    assert [c for c in json.load(open(cap, encoding='utf-8'))['funkce'] if c['id'] == 'hlas'][0]['dostupne'] is True
    shutil.copy2(cap, os.path.join(KA, 'tessa', 'capabilities.json'))

    # pojistky
    assert soubory and not any(f.startswith(NIKDY[:2]) or f == NIKDY[2] for f in soubory)
    aiv = json.load(open(os.path.join(KW, 'vendor', 'ts', 'ts-ai-consultant', 'module.json')))['version']
    for rel in soubory:
        if rel.endswith('.html'):
            t = open(os.path.join(KW, rel), encoding='utf-8').read()
            assert ('ts-ai-widget.js?v=' + aiv) in t and '"mascot":true' in t and 'tessa-portret' not in t, rel
    for d, _, fs in os.walk(OUT):
        for f in fs:
            b = open(os.path.join(d, f), 'rb').read()
            assert b'sk-ant-api' not in b and 'cloudflare-token' != f.split('.')[0], 'citlivý soubor v balíčku: ' + f

    sums = []
    for top in (KW, KA):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    open(os.path.join(OUT, 'SHA256SUMS.txt'), 'w').write('\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    open(os.path.join(LOC, 'soubory-web.txt'), 'w').write('\n'.join(soubory) + '\n')
    json.dump({'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory), 'aiv': aiv, 'verze': v},
              open(os.path.join(LOC, 'balicek.json'), 'w'))
    print('Balíček: %s\n  web: %d souborů (knihovna %s, verze dat %s, mezipaměť %s), /private/ai: 1' % (OUT, len(soubory), aiv, v['data'], v['assets']))


if __name__ == '__main__':
    main()
