#!/usr/bin/env python3
"""Balíček pro PRODUKCI tasstudio.eu: návrh webu na míru v AI Design Studiu (ts-design-studio 0.6.0 → 0.11.0, etapa 6.7d).

  balicek-t7/
    k-nahrani/web/          → obsah nahrát do /web/          (jen vendor/ts/ts-design-studio, změněné a nové soubory proti git HEAD)
    k-nahrani/sub-nahledy/  → obsah nahrát do /sub/nahledy/  (stejná knihovna pro bránu náhledů, bez public/)
    navrat-pri-problemu/    → původní verze změněných souborů
    lokalne-NENAHRAVAT/, NAVOD.md, SHA256SUMS.txt

Konfigurace /private/studio se nemění. Stránky webu, administrace, CRM, Tessa ani data se nemění.
Spustit PŘED commitem (bere změny proti HEAD). Použití: python3 -I sestavit_balicek_t7.py"""
import datetime, hashlib, json, os, shutil, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web')
OUT = os.path.join(HERE, 'balicek-t7'); KW = os.path.join(OUT, 'k-nahrani', 'web'); KN = os.path.join(OUT, 'k-nahrani', 'sub-nahledy'); LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
PRE = 'vendor/ts/ts-design-studio/'; VER = '0.11.0'


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(KW); os.makedirs(KN); os.makedirs(LOC, mode=0o700)
    # -z: názvy bez uvozovek a escapování
    out = subprocess.run(['git', '-C', PROJ, 'status', '--porcelain', '-z', '--untracked-files=all', '--', 'web/'], capture_output=True, text=True, check=True).stdout
    soubory = []
    for item in filter(None, out.split('\0')):
        st, path = item[:2], item[3:]
        if 'D' in st: sys.exit('Smazaný soubor (balíček umí jen přidat a přepsat): ' + path)
        rel = path[len('web/'):]
        if rel == 'ts-components.lock.json': continue
        if not rel.startswith(PRE): sys.exit('Neočekávaná změna mimo knihovnu Studia: ' + rel)
        soubory.append(rel)
    soubory.sort()
    assert json.load(open(os.path.join(WEB, PRE, 'module.json')))['version'] == VER
    for need in ('core/TsStudioKit.php', 'core/TsStudioPlanner.php', 'kit/kit.json', 'kit/.htaccess', 'kit/fonts/fonts.json', 'public/ts-studio.js', 'module.json'):
        assert PRE + need in soubory, 'chybí ' + need
    assert not any('/tests/' in r or '/tools/' in r for r in soubory), 'testy v balíčku'
    bez_public = 0
    for rel in soubory:
        dst = os.path.join(KW, rel); os.makedirs(os.path.dirname(dst), exist_ok=True); shutil.copy2(os.path.join(WEB, rel), dst)
        if rel.startswith(PRE + 'public/'): continue
        dst = os.path.join(KN, rel); os.makedirs(os.path.dirname(dst), exist_ok=True); shutil.copy2(os.path.join(WEB, rel), dst); bez_public += 1
    for d, _, fs in os.walk(OUT):
        for f in fs:
            b = open(os.path.join(d, f), 'rb').read()
            assert b'sk-ant-api' not in b and f.split('.')[0] not in ('cloudflare-token', 'cloudflare-ucet', 'elevenlabs-klic', 'google-api-klic', 'turnstile-tajny-klic', 'posta', 'studio'), 'citlivý soubor v balíčku: ' + f

    N = os.path.join(OUT, 'navrat-pri-problemu'); nove = 0
    for rel in soubory:
        r = subprocess.run(['git', '-C', PROJ, 'show', 'HEAD:web/' + rel], capture_output=True)
        if r.returncode != 0: nove += 1; continue
        p = os.path.join(N, 'web', rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'wb').write(r.stdout)
        if not rel.startswith(PRE + 'public/'):
            p = os.path.join(N, 'sub-nahledy', rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'wb').write(r.stdout)

    sums = []
    for top in (KW, KN):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    open(os.path.join(OUT, 'SHA256SUMS.txt'), 'w').write('\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    open(os.path.join(LOC, 'soubory-web.txt'), 'w').write('\n'.join(soubory) + '\n')
    json.dump({'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory), 'nahledy': bez_public, 'nove': nove, 'verze': VER}, open(os.path.join(LOC, 'balicek.json'), 'w'))
    open(os.path.join(OUT, 'NAVOD.md'), 'w', encoding='utf-8').write('''# Návrh webu na míru v AI Design Studiu – nahrání na tasstudio.eu

Knihovna Studia se zvedne z verze 0.6.0 na %s. Přibude návrh na míru se třemi směry, editor plánu a šablony ordinace a kosmetika.
Stránky webu, administrace, CRM, Tessa ani data se nemění. **Nikdy nenahrávejte nic do `/web/admin/data`.** Konfiguraci `/private/studio` neměňte.

Ve FileZille zapněte Server → Vynutit zobrazování skrytých souborů (v balíčku jsou `.htaccess`).

## 0. Záloha
Stáhněte `/web/vendor/ts/ts-design-studio/` a `/sub/nahledy/vendor/ts/ts-design-studio/` do `zalohy/produkce-%s/`. Stačí i složka `navrat-pri-problemu/` v balíčku.

## 1. Web (%d souborů)
Vlevo `k-nahrani/web/`, vpravo `/web`. Označte vlevo složku `vendor`, pravé tlačítko › „Přidat soubory do fronty“, zkontrolujte, že cíle začínají `/web/vendor/ts/ts-design-studio/…`, spusťte frontu (⌘P). Přepsání potvrďte („Vždy použít tuto akci“).

## 2. Náhledy (%d souborů)
Vlevo `k-nahrani/sub-nahledy/`, vpravo `/sub/nahledy`. Stejně složku `vendor`; cíle začínají `/sub/nahledy/vendor/ts/ts-design-studio/…`. Nahrajte hned po webu, ať obě kopie běží ve stejné verzi.

## 3. Ověření
Napište mi „nahráno“. Ověřím jen čtením, že jsou soubory shodné a `kit` není z internetu vidět. Pak v administraci › Studio založíte jeden skutečný návrh: Nový projekt › na míru, tři směry. Stojí asi 200 neuronů z denního stropu 2 500 a potrvá pár minut.

## Návrat při problému
- Okamžitě: v `/private/studio/studio.php` nastavte `'enabled' => false`. Studio i náhledy se vypnou, data zůstanou.
- Úplně: nahrajte `navrat-pri-problemu/web/` do `/web` a `navrat-pri-problemu/sub-nahledy/` do `/sub/nahledy`. Nové soubory mohou zůstat, nic je nevolá. Návrhy na míru pak v náhledech vrátí 404, projekty ze šablon fungují dál.
''' % (VER, datetime.date.today().isoformat(), len(soubory), bez_public))
    print('balíček:', OUT, '| web:', len(soubory), '| náhledy:', bez_public, '| nové:', nove)


main()
