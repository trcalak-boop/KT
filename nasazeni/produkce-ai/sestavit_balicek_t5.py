#!/usr/bin/env python3
"""Doplňkový balíček pro PRODUKCI tasstudio.eu (po T4): audit webu – v chatu Tessy (veřejně, s limity) a v administraci (Audity), zásady s Google.

  balicek-t5/
    k-nahrani/web/            → obsah nahrát do /web/   (změněné a nové soubory proti produkci = git HEAD; z admin/ jen audity.php a audity-api.php)
    k-nahrani/private-ai/     → obsah nahrát do /private/ai/   (ai-consultant.php s 'audit', tessa/capabilities.json s dostupným auditem)
    k-nahrani/private-audit/  → složku nahrát jako /private/audit/   (konfigurace auditu; klíč Google vkládá provozovatel)
    navrat-pri-problemu/, lokalne-NENAHRAVAT/, NAVOD.md, SHA256SUMS.txt

Konfigurace /private/ai/ai-consultant.php = nasazená verze z balíčku T4 + oddíl 'audit' (sůl, hlas, Cloudflare beze změny).
Nikdy do balíčku: admin/data, data/, ts-components.lock.json, žádný klíč ani token.
Použití: python3 -I sestavit_balicek_t5.py"""
import datetime, hashlib, json, os, re, secrets, shutil, subprocess, sys, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web'); PHP = os.path.join(PROJ, 'tools', 'php', 'php')
OUT = os.path.join(HERE, 'balicek-t5'); T4 = os.path.join(HERE, 'balicek-t4', 'k-nahrani', 'private-ai', 'ai-consultant.php')
KW = os.path.join(OUT, 'k-nahrani', 'web'); KA = os.path.join(OUT, 'k-nahrani', 'private-ai'); KU = os.path.join(OUT, 'k-nahrani', 'private-audit'); LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
ADMIN_OK = ('admin/audity.php', 'admin/audity-api.php')
SITE = 'https://www.tasstudio.eu'
DENY = '# Neveřejné – z internetu nepřístupné (Apache).\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n'


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout
def live(rel): return urllib.request.urlopen(urllib.request.Request(SITE + '/' + rel, headers={'User-Agent': 'ts-overeni'}), timeout=30).read()


def w(p, t, mode=0o644):
    os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(t); os.chmod(p, mode)


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(KW); os.makedirs(os.path.join(KA, 'tessa')); os.makedirs(KU); os.makedirs(LOC, mode=0o700)
    soubory = []
    for line in git('status', '--porcelain', '--untracked-files=all', '--', 'web/').splitlines():
        st, path = line[:2], line[3:].strip().strip('"')
        if 'D' in st: sys.exit('Smazaný soubor ve webu (balíček umí jen přidat/přepsat): ' + path)
        rel = path[len('web/'):]
        if rel.startswith('data/') or rel == 'ts-components.lock.json': continue
        if rel.startswith('admin/') and rel not in ADMIN_OK: sys.exit('Neočekávaná změna v administraci: ' + rel)
        soubory.append(rel)
    soubory.sort()
    v = {}
    for k, pat in (('data', r'/data/reference\.js\?v=([0-9A-Za-z]+)'), ('assets', r'/assets/css/ts\.css\?v=([0-9A-Za-z]+)')):
        v[k] = re.search(pat, live('cs/index.html').decode()).group(1)
    aiv = json.load(open(os.path.join(WEB, 'vendor', 'ts', 'ts-ai-consultant', 'module.json')))['version']
    for rel in soubory:
        if rel.endswith('.html'):
            t = open(os.path.join(WEB, rel), encoding='utf-8').read()
            for k, pat in (('data', r'/data/(?:nastaveni|reference)\.js\?v=([0-9A-Za-z]+)'), ('assets', r'/assets/css/ts\.css\?v=([0-9A-Za-z]+)')):
                for m in re.findall(pat, t):
                    if m != v[k]: sys.exit('%s: verze %s %s ≠ produkce %s (spusťte build a srovnejte verze)' % (rel, k, m, v[k]))
            assert ('ts-ai-widget.js?v=' + aiv) in t and '"audit":"/api/audit/verejny.php"' in t, rel
            if re.search(r'(privacy|ochrana|datenschutz)', rel): assert t.count('data-ai-audit') == 4 and 'Google LLC' in t, rel
        os.makedirs(os.path.dirname(os.path.join(KW, rel)), exist_ok=True); shutil.copy2(os.path.join(WEB, rel), os.path.join(KW, rel))
    for need in ('api/audit/verejny.php', 'api/ai/chat.php', 'admin/audity.php', 'admin/audity-api.php', 'vendor/ts/ts-site-audit/core/TsSiteAudit.php', 'vendor/ts/ts-site-audit/public/.htaccess', 'vendor/ts/ts-site-audit/core/.htaccess'):
        assert need in soubory, 'chybí ' + need

    # /private/ai: konfigurace T4 + audit, funkce Tessy
    cap = os.path.join(PROJ, 'ai', 'tessa', 'capabilities.json')
    assert [c for c in json.load(open(cap, encoding='utf-8'))['funkce'] if c['id'] == 'audit'][0]['dostupne'] is True
    shutil.copy2(cap, os.path.join(KA, 'tessa', 'capabilities.json'))
    c = open(T4, encoding='utf-8').read(); a = "    'capabilitiesFile' => $private . '/tessa/capabilities.json',\n"
    assert c.count(a) == 1 and "'audit'" not in c and "'speech'" in c
    c = c.replace(a, a + """    /* audit webu v chatu (etapa 5.4): u adresy webu tlačítko, hotový výsledek se uloží do konverzace (čte /private/audit) */
    'audit' => ['enabled' => true, 'lookup' => function (string $token) use ($private) {
        require_once dirname($private, 2) . '/web/vendor/ts/ts-site-audit/core/bootstrap.php';
        $s = require dirname($private) . '/audit/site-audit.php'; $s['source'] = 'public';
        return ts_site_audit($s)->status($token);
    }],
""")
    w(os.path.join(KA, 'ai-consultant.php'), c, 0o600)
    # /private/audit: konfigurace auditu
    w(os.path.join(KU, 'site-audit.php'), '''<?php
/* ==========================================================================
   Audit webů – PRODUKCE (tasstudio.eu): chat AI asistentky (/api/audit/verejny.php, zdroj public) a administrace (/admin/audity.php, zdroj admin).
   Neveřejná konfigurace: /private/audit/site-audit.php. Klíč Google PageSpeed Insights vkládá provozovatel do google-api-klic.txt (vedle).
   Hodnocení vzhledu: Cloudflare Workers AI se stávajícím tokenem asistentky (/private/ai), sdílí bezplatnou denní kvótu neuronů.
   Vypnutí auditu: 'enabled' => false (tlačítko v chatu pak skončí srozumitelnou hláškou).
   ========================================================================== */
$private = __DIR__; $ai = dirname(__DIR__) . '/ai';
return [
    'enabled' => true, 'timezone' => 'Europe/Prague', 'defaultLang' => 'cs', 'source' => 'public',
    'meter' => ['provider' => 'pagespeed', 'apiKeyFile' => $private . '/google-api-klic.txt', 'timeout' => 100, 'issuesPerCategory' => 5],
    'vision' => ['provider' => 'cloudflare', 'apiKeyFile' => $ai . '/cloudflare-token.txt', 'accountIdFile' => $ai . '/cloudflare-ucet.txt', 'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 700],
    'visionPerDay' => 30,
    'db' => ['dsn' => 'sqlite:' . $private . '/data/audit.sqlite', 'autoMigrate' => true],
    'shotDir' => $private . '/data/snimky',
    'salt' => '%s', 'cacheHours' => 24, 'retentionDays' => 30,
    'limits' => ['publicPerIpDay' => 2, 'publicPerDay' => 20, 'adminPerDay' => 40],
    'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],
];
''' % secrets.token_hex(16), 0o600)
    w(os.path.join(KU, '.htaccess'), DENY); w(os.path.join(KU, 'data', '.htaccess'), DENY)
    for f in (os.path.join(KA, 'ai-consultant.php'), os.path.join(KU, 'site-audit.php')):
        r = subprocess.run([PHP, '-l', f], capture_output=True, text=True)
        if r.returncode: sys.exit(f + ': ' + r.stdout)
    for d, _, fs in os.walk(OUT):
        for f in fs:
            b = open(os.path.join(d, f), 'rb').read()
            assert b'sk-ant-api' not in b and f.split('.')[0] not in ('cloudflare-token', 'elevenlabs-klic', 'google-api-klic'), 'citlivý soubor v balíčku: ' + f

    # návrat: původní soubory z HEAD (nové soubory při návratu nevadí, nikdo je nevolá) + konfigurace T4
    N = os.path.join(OUT, 'navrat-pri-problemu')
    for rel in soubory:
        r = subprocess.run(['git', '-C', PROJ, 'show', 'HEAD:web/' + rel], capture_output=True)
        if r.returncode == 0: os.makedirs(os.path.dirname(os.path.join(N, 'web', rel)), exist_ok=True); open(os.path.join(N, 'web', rel), 'wb').write(r.stdout)
    os.makedirs(os.path.join(N, 'private-ai', 'tessa'))
    shutil.copy2(T4, os.path.join(N, 'private-ai', 'ai-consultant.php')); shutil.copy2(os.path.join(HERE, 'balicek-t4', 'k-nahrani', 'private-ai', 'tessa', 'capabilities.json'), os.path.join(N, 'private-ai', 'tessa', 'capabilities.json'))

    sums = []
    for top in (KW, KA, KU):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    open(os.path.join(OUT, 'SHA256SUMS.txt'), 'w').write('\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    open(os.path.join(LOC, 'soubory-web.txt'), 'w').write('\n'.join(soubory) + '\n')
    json.dump({'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory), 'aiv': aiv, 'verze': v},
              open(os.path.join(LOC, 'balicek.json'), 'w'))
    print('Balíček: %s\n  web: %d souborů (asistent %s, verze dat %s), /private/ai: 2, /private/audit: 3' % (OUT, len(soubory), aiv, v['data']))


if __name__ == '__main__':
    main()
