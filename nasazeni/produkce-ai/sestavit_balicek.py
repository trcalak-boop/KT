#!/usr/bin/env python3
"""Sestaví balíček pro PRODUKCI tasstudio.eu: AI asistent na stránkách, brána, napojení na CRM a nové zásady ochrany údajů.

  balicek/
    k-nahrani/web/      → obsah nahrát do /web/   (jen změněné a nové soubory proti produkci = git HEAD projektu)
    k-nahrani/ai/       → složku nahrát do /private/   (vznikne /private/ai: konfigurace, znalosti, SDK)
    lokalne-NENAHRAVAT/ → kontrolní kód, seznam souborů, výsledky testu
    NAVOD.md, KLIC-navod.txt, SHA256SUMS.txt

Do balíčku nikdy nepatří: admin/data (živé CRM), ts-components.lock.json, token a ID účtu Cloudflare, hesla.
T1 (1. 11. 2026): Tessa + bezplatné Cloudflare Workers AI místo placeného Claude.
Použití: python3 -I sestavit_balicek.py"""
import datetime, hashlib, json, os, secrets, shutil, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web')
PHP = os.path.join(PROJ, 'tools', 'php', 'php'); SDK = os.path.expanduser('~/TS-Studio/build/anthropic-sdk/vendor')
ZNALOSTI = os.path.expanduser('~/TS-Studio/nasazeni/test-tasstudio/ai-znalosti/sestavit_znalosti.py')
OUT = os.path.join(HERE, 'balicek'); KW = os.path.join(OUT, 'k-nahrani', 'web'); KA = os.path.join(OUT, 'k-nahrani', 'ai'); LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
NIKDY = ('admin/data/', 'ts-components.lock.json')
DENY = '# Neveřejné – z internetu nepřístupné (Apache).\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n'


def w(p, t, mode=0o644):
    os.makedirs(os.path.dirname(p), exist_ok=True)
    with open(p, 'w', encoding='utf-8') as f: f.write(t)
    os.chmod(p, mode)


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(KW); os.makedirs(KA); os.makedirs(LOC, mode=0o700)

    # --- změněné a nové soubory webu proti git HEAD (produkce = HEAD, ověřeno porovnáním přes curl) ---
    soubory = []
    for line in git('status', '--porcelain', '--untracked-files=all', '--', 'web/').splitlines():
        st, path = line[:2], line[3:].strip().strip('"')
        if 'D' in st: sys.exit('Smazaný soubor ve webu (balíček umí jen přidat/přepsat): ' + path)
        rel = path[len('web/'):]
        if rel.startswith(NIKDY[0]) or rel == NIKDY[1]: continue
        soubory.append(rel)
    soubory.sort()
    for rel in soubory:
        os.makedirs(os.path.dirname(os.path.join(KW, rel)), exist_ok=True); shutil.copy2(os.path.join(WEB, rel), os.path.join(KW, rel))

    # --- jednorázová kontrola ---
    code = 'AI-' + secrets.token_hex(3).upper() + '-' + secrets.token_hex(3).upper()
    h = subprocess.run([PHP, '-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', code], capture_output=True, text=True).stdout.strip()
    until = (datetime.date.today() + datetime.timedelta(days=14)).isoformat()
    check = 'kontrola-ai-' + secrets.token_hex(5) + '.php'
    w(os.path.join(KW, check), '''<?php
/* Jednorázová kontrola AI asistenta (tasstudio.eu). Každá akce vyžaduje kontrolní kód; jen HTTPS. Po kontrole SMAZAT. */
require __DIR__ . '/vendor/ts/ts-ai-consultant/core/bootstrap.php';
$private = dirname(__DIR__) . '/private/ai';
if (!is_file($private . '/ai-consultant.php')) { http_response_code(503); echo 'Chybí /private/ai/ai-consultant.php.'; exit; }
$settings = require $private . '/ai-consultant.php';
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [__DIR__, $private]])) exit;
(new TsAiCheck($settings))->serve();
''')

    # --- /private/ai ---
    r = subprocess.run([sys.executable, '-I', ZNALOSTI, WEB, os.path.join(KA, 'znalosti.json')], capture_output=True, text=True)
    if r.returncode: sys.exit('Znalosti: ' + r.stdout + r.stderr)
    TESSA = os.path.join(PROJ, 'ai', 'tessa')
    os.makedirs(os.path.join(KA, 'tessa'), exist_ok=True)
    for f in ('persona.md', 'capabilities.json'): shutil.copy2(os.path.join(TESSA, f), os.path.join(KA, 'tessa', f))
    w(os.path.join(KA, 'tessa', '.htaccess'), DENY)
    w(os.path.join(KA, 'ai-consultant.php'), '''<?php
/* ==========================================================================
   AI asistentka Tessa – PRODUKCE (tasstudio.eu). Neveřejná konfigurace: /private/ai/ai-consultant.php (mimo webroot).
   Provoz ZDARMA: Cloudflare Workers AI (bezplatný plán, denní kvóta, nic se neúčtuje).
   Token v cloudflare-token.txt a ID účtu v cloudflare-ucet.txt vedle (vkládá provozovatel, čtou se jako prostý text).
   Poptávka z formuláře asistentky = nový lead v CRM (původ „AI asistent na webu“), oznámení posílá CRM.
   Vypnutí asistentky: 'enabled' => false (tlačítko na webu zmizí).
   ========================================================================== */
$private = __DIR__;
$web = dirname(__DIR__, 2) . '/web';
return [
    'enabled' => true,
    'timezone' => 'Europe/Prague', 'business' => 'T&S Studio', 'contactEmail' => 'tasstudio@tasstudio.eu', 'defaultLang' => 'en',
    'provider' => 'cloudflare', 'apiKeyFile' => $private . '/cloudflare-token.txt',
    'cloudflare' => ['accountId' => trim((string) @file_get_contents($private . '/cloudflare-ucet.txt')), 'model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast', 'timeout' => 30, 'connectTimeout' => 6],
    'contactMode' => 'marker', 'knowledgeMaxChars' => 5000, 'historyMessages' => 8,
    'maxTokens' => 500, 'budget' => ['limit' => 9500, 'period' => 'day', 'unit' => 'neuron'],
    'knowledgeFile' => $private . '/znalosti.json',
    'persona' => ['name' => 'Tessa', 'file' => $private . '/tessa/persona.md'],
    'capabilitiesFile' => $private . '/tessa/capabilities.json',
    'db' => ['dsn' => 'sqlite:' . $private . '/data/ai.sqlite', 'autoMigrate' => true],
    'salt' => '%(salt)s',
    'limits' => ['perIp10min' => 20, 'perIpDay' => 80, 'globalMinute' => 30, 'turnsPerConversation' => 20, 'leadsPerIpHour' => 5],
    'maxChars' => 1000,
    'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],
    'retention' => ['conversationDays' => 30, 'leadDays' => 90],
    'consentText' => 'Souhlasím se zpracováním údajů z formuláře a této konverzace, aby mi studio mohlo odpovědět.',
    /* bez 'mail' a 'notify': oznámení o poptávce posílá CRM (fronta lead_notifications) */
    'leadSink' => function (array $lead) use ($web) { require_once $web . '/admin/crm-ai.php'; return crm_ai_lead($lead); },
    'check' => ['codeHash' => '%(hash)s', 'until' => '%(until)s'],
];
''' % {'salt': secrets.token_hex(16), 'hash': h, 'until': until}, 0o600)
    w(os.path.join(KA, '.htaccess'), DENY); w(os.path.join(KA, 'data', '.htaccess'), DENY)

    # --- pojistky ---
    for top in (KW, KA):
        for d, _, fs in os.walk(top):
            if os.path.relpath(d, KA).split(os.sep)[0] == 'vendor' and top == KA: continue
            for f in fs:
                t = open(os.path.join(d, f), 'rb').read()
                assert b'sk-ant-api' not in t, 'v balíčku je něco jako API klíč: ' + f
    assert not any(n in soubory for n in ('admin/data/heslo.php', 'admin/data/posta.php', 'admin/data/crm.sqlite'))
    assert not os.path.exists(os.path.join(KW, 'admin', 'data')) and not os.path.exists(os.path.join(KW, 'ts-components.lock.json'))
    kn = open(os.path.join(KA, 'znalosti.json'), encoding='utf-8').read()
    assert 'cenaOd' not in kn and 'Kč' not in kn and 'https://tasstudio.eu' not in kn, 'znalosti: ceny nebo absolutní odkazy'
    assert 'claude' not in open(os.path.join(KA, 'ai-consultant.php')).read().lower(), 'konfigurace nesmí používat placený Claude'

    sums = []
    for top in (KW, KA):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    w(os.path.join(OUT, 'SHA256SUMS.txt'), '\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    w(os.path.join(LOC, 'kontrolni-kod.txt'), 'Kontrolní kód (platí do %s): %s\nKontrola: https://www.tasstudio.eu/%s\nNikam ho nenahrávejte ani neposílejte.\n' % (until, code, check), 0o600)
    w(os.path.join(LOC, 'soubory-web.txt'), '\n'.join(soubory + [check]) + '\n')
    json.dump({'check': check, 'until': until, 'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory) + 1},
              open(os.path.join(LOC, 'balicek.json'), 'w'))
    t = open(os.path.join(HERE, 'navod-sablona.md'), encoding='utf-8').read().replace('{KONTROLA}', check).replace('{PLATNOST}', until).replace('{POCET_WEB}', str(len(soubory) + 1))
    w(os.path.join(OUT, 'NAVOD.md'), t)
    print('Balíček: %s\n  web: %d souborů, /private/ai: %d\n  kontrola: %s (do %s)' % (OUT, len(soubory) + 1, sum(1 for s in sums if '/ai/' in s[66:]), check, until))


if __name__ == '__main__':
    main()
