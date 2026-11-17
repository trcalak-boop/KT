#!/usr/bin/env python3
"""Balíček pro PRODUKCI tasstudio.eu: souhlas s cookies a měření návštěvnosti (ts-analytics 1.0.0 + Google Analytics po souhlasu),
ochrana AI asistentky proti robotům (ts-ai-consultant 1.10.0: výzva v prohlížeči, Turnstile, Origin, User-Agent, limity), přehled Návštěvnost
v administraci, pole „ga4“ v Kontaktech a údajích, zásady ochrany osobních údajů v 18 jazycích.

  balicek-mereni/
    k-nahrani/web/            → obsah nahrát do /web/   (změněné a nové soubory proti git HEAD; z admin/ jen admin.js, index.php, lib.php, navstevnost.php)
    k-nahrani/private-ai/     → obsah nahrát do /private/ai/   (ai-consultant.php: konfigurace z balíčku „kvóta“ + ochrana proti robotům a nové limity)
    k-nahrani/private-stat/   → složku nahrát jako /private/stat/   (konfigurace měření, databázi vytvoří samo)
    navrat-pri-problemu/, lokalne-NENAHRAVAT/, NAVOD.md, SHA256SUMS.txt

Verze dat ?v= ve stránkách se ověří proti živému webu. Spustit PŘED commitem (bere změny proti HEAD).
Nikdy do balíčku: admin/data, data/, ts-components.lock.json, žádný klíč ani token.
Použití: python3 -I sestavit_balicek_mereni.py"""
import datetime, hashlib, json, os, re, secrets, shutil, subprocess, sys, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web'); PHP = os.path.expanduser('~/TS-Studio/tools/php/php')
OUT = os.path.join(HERE, 'balicek-mereni'); KW = os.path.join(OUT, 'k-nahrani', 'web'); KA = os.path.join(OUT, 'k-nahrani', 'private-ai'); KS = os.path.join(OUT, 'k-nahrani', 'private-stat')
LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT'); KVOTA = os.path.join(HERE, 'balicek-kvota', 'private-ai', 'ai-consultant.php')
ADMIN_OK = ('admin/admin.js', 'admin/index.php', 'admin/lib.php', 'admin/navstevnost.php')
SITE = 'https://www.tasstudio.eu'; V = 'ts43'; AIV = '1.10.0'
DENY = '# Neveřejné – z internetu nepřístupné (Apache).\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n'


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout
def live(rel): return urllib.request.urlopen(urllib.request.Request(SITE + '/' + rel, headers={'User-Agent': 'ts-overeni'}), timeout=30).read()


def w(p, t, mode=0o644):
    os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(t); os.chmod(p, mode)


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    for d in (KW, KA, KS): os.makedirs(d)
    os.makedirs(LOC, mode=0o700)
    soubory = []
    for line in git('status', '--porcelain', '--untracked-files=all', '--', 'web/').splitlines():
        st, path = line[:2], line[3:].strip().strip('"')
        if 'D' in st: sys.exit('Smazaný soubor ve webu: ' + path)
        rel = path[len('web/'):]
        if rel.startswith('data/') or rel == 'ts-components.lock.json': continue
        if rel.startswith('admin/') and rel not in ADMIN_OK: sys.exit('Neočekávaná změna v administraci: ' + rel)
        soubory.append(rel)
    soubory.sort()
    for need in ('assets/js/ts.js', 'assets/css/ts.css', 'api/stat/hit.php', 'admin/navstevnost.php', 'vendor/ts/ts-analytics/core/TsAnalytics.php',
                 'vendor/ts/ts-analytics/core/.htaccess', 'vendor/ts/ts-analytics/public/.htaccess', 'vendor/ts/ts-ai-consultant/core/TsAiConsultant.php', 'cs/ochrana-osobnich-udaju.html'):
        assert need in soubory, 'chybí ' + need
    prod = live('cs/index.html').decode()
    dv = re.search(r'/data/reference\.js\?v=([0-9A-Za-z]+)', prod).group(1)
    assert 'ts.js?v=' + V not in prod, 'produkce už má ' + V
    for rel in soubory:
        p = os.path.join(WEB, rel)
        if rel.endswith('.html'):
            t = open(p, encoding='utf-8').read()
            for m in re.findall(r'/data/(?:nastaveni|reference)\.js\?v=([0-9A-Za-z]+)', t):
                if m != dv: sys.exit('%s: verze dat %s ≠ produkce %s' % (rel, m, dv))
            assert 'ts.js?v=' + V in t and 'ts-ai-widget.js?v=' + AIV in t, rel
            if re.search(r'(privacy|ochrana|datenschutz)', rel): assert t.count('data-mereni') == 7 and t.count('data-turnstile') == 3, rel
        os.makedirs(os.path.dirname(os.path.join(KW, rel)), exist_ok=True); shutil.copy2(p, os.path.join(KW, rel))
    js = open(os.path.join(WEB, 'assets/js/ts.js'), encoding='utf-8').read()
    assert "CONSENT = { v: 1, key: 'ts-consent'" in js and "/api/stat/hit.php" in js and "zmer('poptavka'" in js

    # /private/ai: konfigurace z balíčku „kvóta“ + ochrana proti robotům
    c = open(KVOTA, encoding='utf-8').read()
    old = "    'limits' => ['perIp10min' => 20, 'perIpDay' => 80, 'globalMinute' => 30, 'turnsPerConversation' => 20, 'leadsPerIpHour' => 5],\n"
    assert c.count(old) == 1 and "'guard'" not in c
    c = c.replace(old, """    'limits' => ['perIp10min' => 12, 'perIpDay' => 40, 'globalMinute' => 30, 'turnsPerConversation' => 20, 'leadsPerIpHour' => 5, 'newConvPerIpHour' => 6, 'globalDay' => 300],
    /* ochrana proti robotům (ts-ai-consultant 1.10.0): nová konverzace jen s vyřešenou výzvou v prohlížeči; skripty bez hlavičky Origin
       a zjevní roboti se odmítnou. Cloudflare Turnstile se zapne sám, až v této složce budou turnstile-klic-webu.txt (Site Key)
       a turnstile-tajny-klic.txt (Secret Key) – oba vytváří provozovatel v Cloudflare (Turnstile › Add widget, doména tasstudio.eu). */
    'guard' => ['requireOrigin' => true, 'blockBots' => true, 'pow' => 15,
        'turnstile' => is_file($private . '/turnstile-klic-webu.txt') && is_file($private . '/turnstile-tajny-klic.txt')
            ? ['siteKey' => trim((string) file_get_contents($private . '/turnstile-klic-webu.txt')), 'secretFile' => $private . '/turnstile-tajny-klic.txt'] : null],
""")
    w(os.path.join(KA, 'ai-consultant.php'), c, 0o600)
    # /private/stat: měření návštěvnosti
    w(os.path.join(KS, 'analytics.php'), '''<?php
/* ==========================================================================
   Měření návštěvnosti – PRODUKCE (tasstudio.eu). Neveřejná konfigurace: /private/stat/analytics.php (mimo webroot).
   Sběr: /web/api/stat/hit.php (volá ho jen prohlížeč návštěvníka, který v liště cookies povolil měření).
   Přehled: administrace › Návštěvnost. Databáze vznikne sama v data/analytics.sqlite. IP adresy se neukládají.
   Vypnutí sběru: 'enabled' => false.
   ========================================================================== */
$private = __DIR__;
return [
    'enabled' => true,
    'timezone' => 'Europe/Prague',
    'db' => ['dsn' => 'sqlite:' . $private . '/data/analytics.sqlite', 'autoMigrate' => true],
    'salt' => '%s',
    'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],
    'ownHosts' => ['tasstudio.eu'],
    'retentionMonths' => 14,
    'limits' => ['perIpHour' => 600, 'globalDay' => 50000],
];
''' % secrets.token_hex(16), 0o600)
    w(os.path.join(KS, '.htaccess'), DENY); w(os.path.join(KS, 'data', '.htaccess'), DENY)
    for f in (os.path.join(KA, 'ai-consultant.php'), os.path.join(KS, 'analytics.php'), os.path.join(KW, 'admin/navstevnost.php'), os.path.join(KW, 'admin/lib.php'), os.path.join(KW, 'api/stat/hit.php')):
        r = subprocess.run([PHP, '-l', f], capture_output=True, text=True)
        if r.returncode: sys.exit(f + ': ' + r.stdout)
    for d, _, fs in os.walk(OUT):
        for f in fs:
            b = open(os.path.join(d, f), 'rb').read()
            assert b'sk-ant-api' not in b and f.split('.')[0] not in ('cloudflare-token', 'cloudflare-ucet', 'elevenlabs-klic', 'google-api-klic', 'turnstile-tajny-klic', 'posta'), 'citlivý soubor v balíčku: ' + f

    # návrat: původní soubory z HEAD (nové soubory při návratu nevadí, nikdo je nevolá) + konfigurace „kvóta“
    N = os.path.join(OUT, 'navrat-pri-problemu')
    for rel in soubory:
        r = subprocess.run(['git', '-C', PROJ, 'show', 'HEAD:web/' + rel], capture_output=True)
        if r.returncode == 0: os.makedirs(os.path.dirname(os.path.join(N, 'web', rel)), exist_ok=True); open(os.path.join(N, 'web', rel), 'wb').write(r.stdout)
    os.makedirs(os.path.join(N, 'private-ai')); shutil.copy2(KVOTA, os.path.join(N, 'private-ai', 'ai-consultant.php'))

    sums = []
    for top in (KW, KA, KS):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    open(os.path.join(OUT, 'SHA256SUMS.txt'), 'w').write('\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    open(os.path.join(LOC, 'soubory-web.txt'), 'w').write('\n'.join(soubory) + '\n')
    json.dump({'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory), 'data': dv, 'assets': V, 'aiv': AIV},
              open(os.path.join(LOC, 'balicek.json'), 'w'))
    open(os.path.join(OUT, 'NAVOD.md'), 'w', encoding='utf-8').write('''# Souhlas s cookies, měření návštěvnosti a ochrana Tessy – nahrání na tasstudio.eu

Balíček obsahuje jen změněné a nové soubory: %d do `/web`, konfiguraci Tessy do `/private/ai` a novou složku `/private/stat`.
**Nikdy nenahrávejte nic do `/web/admin/data`** (živé CRM). V balíčku nic z `admin/data` ani `data/` není.

## 0. Záloha
Stáhněte z `/web` složky `assets/`, `admin/` (bez `data`) a stránky `*.html` (kořen i jazykové složky) a z `/private/ai/` soubor `ai-consultant.php`
do `zalohy/produkce-%s/`. Stačí i složka `navrat-pri-problemu/` v tomto balíčku.

## 1. `/private/stat/`
Složku `k-nahrani/private-stat/` nahrajte jako `/private/stat/` (vedle `/private/ai/`), **včetně skrytých `.htaccess`** (i v `data/`).

## 2. `/private/ai/`
Nahrajte `k-nahrani/private-ai/ai-consultant.php` do `/private/ai/` a potvrďte přepsání.

## 3. `/web/`
Vlevo `k-nahrani/web/`, vpravo `/web`. Označte vlevo **všechno uvnitř**, pravé tlačítko › „Přidat soubory do fronty“, zkontrolujte, že cíle ve frontě
začínají `/web/…`, a spusťte frontu (⌘P). Přepsání potvrďte („Vždy použít tuto akci“). Přibude `vendor/ts/ts-analytics/` (i skryté `.htaccess`),
`api/stat/hit.php` a `admin/navstevnost.php`.

## 4. Ověření
Napište mi „nahráno“. Ověřím jen čtením: stránky mají `%s`, lišta se zobrazí, skript bez výzvy u Tessy neprojde a `/private/stat` není z internetu vidět.

## 5. Volitelně kdykoli později (klíče vytváříte vy)
- **Google Analytics 4:** v analytics.google.com založte službu a webový stream pro `https://www.tasstudio.eu`, ID měření (`G-…`) vložte v administraci
  do záložky **Kontakty a údaje › Google Analytics 4** a uložte. Spustí se jen u návštěvníků, kteří povolí měření.
- **Cloudflare Turnstile:** v Cloudflare › Turnstile › Add widget (doména `tasstudio.eu`, režim Managed). Site Key uložte jako
  `/private/ai/turnstile-klic-webu.txt`, Secret Key jako `/private/ai/turnstile-tajny-klic.txt` (každý jeden řádek). Bez nich chrání Tessu
  výzva v prohlížeči, limity a filtr robotů.

## Návrat při problému
Nahrajte stejným postupem obsah `navrat-pri-problemu/web/` do `/web` a `navrat-pri-problemu/private-ai/ai-consultant.php` do `/private/ai/`.
Složka `/private/stat` může zůstat (bez stránek nic nedělá).
''' % (len(soubory), datetime.date.today().isoformat(), V))
    print('balíček:', OUT, '| web:', len(soubory), '| data:', dv)


main()
