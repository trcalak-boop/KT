#!/usr/bin/env python3
"""Etapa 5.3 – audit webu přes Tessu na NEVEŘEJNÉM stagingu (knihovna ts-ai-consultant 1.9.0).

  6-balicek-audit/oprava-5.3/test/     → obsah do /sub/test/   (knihovna 1.9.0, asistent.php s auditem, api/audit/verejny.php, api/ai/chat.php)
  6-balicek-audit/oprava-5.3/ai-test/  → obsah do /private/ai-test/   (konfigurace s 'audit', funkce Tessy s dostupným auditem – jen staging)

Konfigurace /private/audit-test se nemění (veřejný vstup jen přepne zdroj na 'public').
Použití: python3 -I sestavit_balicek_audit_53.py"""
import hashlib, json, os, shutil, subprocess, sys, tempfile

HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.expanduser('~/TS-Studio/ts-library'); PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'
sys.path.insert(0, HERE)
import importlib.util
spec = importlib.util.spec_from_file_location('b52', os.path.join(HERE, 'sestavit_balicek_audit.py')); b52 = importlib.util.module_from_spec(spec); spec.loader.exec_module(b52)
T = b52.T; VRSTVY = b52.VRSTVY + [os.path.join(HERE, '6-balicek-audit', 'k-nahrani', 'test')]
OUT = os.path.join(HERE, '6-balicek-audit', 'oprava-5.3'); KT = os.path.join(OUT, 'test'); KA = os.path.join(OUT, 'ai-test')
sha = lambda p: hashlib.sha256(open(p, 'rb').read()).hexdigest()


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    tmp = tempfile.mkdtemp(dir=os.environ.get('TMPDIR'))
    for v in VRSTVY: shutil.copytree(v, tmp, dirs_exist_ok=True)
    lock = json.load(open(os.path.join(tmp, 'ts-components.lock.json')))['components']
    assert lock['ts-ai-consultant']['version'] == '1.8.0' and 'ts-site-audit' in lock, 'neočekávaný stav stagingu'
    pred = {os.path.relpath(os.path.join(d, f), tmp): sha(os.path.join(d, f)) for d, _, fs in os.walk(tmp) for f in fs}
    for c in ('ts-ai-consultant', 'ts-site-audit'):
        r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'update', c, tmp], capture_output=True, text=True)
        if r.returncode: sys.exit('ts.py update %s: %s%s' % (c, r.stdout, r.stderr))
    aiv = json.load(open(os.path.join(LIB, 'components', 'ts-ai-consultant', 'module.json')))['version']
    # stránka stagingu: audit v konfiguraci widgetu, nová verze, popis
    p = os.path.join(tmp, 'asistent.php'); s = open(p, encoding='utf-8').read()
    for a, b in [('?v=1.8.0', '?v=' + aiv), ('etapa T4', 'etapa 5.3'), ('<script>window.TS_AI_CONFIG = {"api": "/api/ai/chat.php",', '<script>window.TS_AI_CONFIG = {"api": "/api/ai/chat.php", "audit": "/api/audit/verejny.php",'),
                 ('hlas Tessy z ElevenLabs (záloha: hlas prohlížeče)', 'hlas Tessy z ElevenLabs (záloha: hlas prohlížeče), audit webu v chatu (napište adresu webu)')]:
        assert a in s, a; s = s.replace(a, b)
    open(p, 'w', encoding='utf-8').write(s)
    # veřejný vstup auditu pro chat (na stagingu dál za přístupovou cookie z konfigurace)
    b52.w(os.path.join(tmp, 'api', 'audit', 'verejny.php'), '''<?php
/* Audit webu z chatu Tessy – veřejný vstup ('source' => 'public': limity na adresu IP a den, bez seznamu a vynucení).
   Na stagingu stejná konfigurace jako administrace (/private/audit-test), tedy i přístupová cookie. */
require __DIR__ . '/../../vendor/ts/ts-site-audit/core/bootstrap.php';
require __DIR__ . '/../../vendor/ts/ts-ai-consultant/core/bootstrap.php';
$private = dirname(__DIR__, 4) . '/private/audit-test';
if (!is_file($private . '/site-audit.php')) { http_response_code(503); header('Content-Type: application/json'); echo '{"ok":false,"error":"config"}'; exit; }
$s = require $private . '/site-audit.php';
$s['source'] = 'public'; $s['limits']['publicPerIpDay'] = 10;          // staging: víc pokusů pro testování
$audit = ts_site_audit($s);
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [dirname(__DIR__, 2), $private]])) exit;
$audit->serve();
''')
    # brána Tessy smí číst výsledky auditu (open_basedir + /private/audit-test)
    b52.w(os.path.join(tmp, 'api', 'ai', 'chat.php'), '''<?php
/* AI konzultant – vstupní bod brány (test.tasstudio.eu). Konfigurace, znalosti a klíče jsou mimo webroot v /private/ai-test/.
   Konfigurace se načte před zúžením open_basedir; potom PHP smí jen do webrootu subdomény, /private/ai-test/ a /private/audit-test/
   (výsledek auditu webu pro konverzaci, etapa 5.3). */
require __DIR__ . '/../../vendor/ts/ts-ai-consultant/core/bootstrap.php';
$private = dirname(__DIR__, 4) . '/private/ai-test';
if (!is_file($private . '/ai-consultant.php')) { http_response_code(503); header('Content-Type: application/json'); echo '{"ok":false,"error":"config"}'; exit; }
$settings = require $private . '/ai-consultant.php';
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [dirname(__DIR__, 2), $private, dirname($private) . '/audit-test']])) exit;
ts_ai_consultant($settings)->serve();
''')
    for d, _, fs in os.walk(tmp):
        for f in fs:
            q = os.path.join(d, f); rel = os.path.relpath(q, tmp)
            if rel.split(os.sep)[0] in ('ts-config', 'ts-theme'): continue
            if pred.get(rel) != sha(q): os.makedirs(os.path.dirname(os.path.join(KT, rel)), exist_ok=True); shutil.copy2(q, os.path.join(KT, rel))
    shutil.rmtree(tmp)
    # /private/ai-test: konfigurace s auditem (z nasazené verze 1.8.0) + funkce Tessy (audit dostupný jen na stagingu)
    c = open(os.path.join(T, 'oprava-1.8.0', 'ai-test', 'ai-consultant.php'), encoding='utf-8').read()
    a = "    'capabilitiesFile' => $private . '/tessa/capabilities.json',\n"
    assert c.count(a) == 1 and "'audit'" not in c
    c = c.replace(a, a + """    /* audit webu v chatu (etapa 5.3): u adresy webu tlačítko, výsledek se uloží do konverzace (čte /private/audit-test) */
    'audit' => ['enabled' => true, 'lookup' => function (string $token) use ($private) {
        require_once dirname($private, 2) . '/sub/test/vendor/ts/ts-site-audit/core/bootstrap.php';
        $s = require dirname($private) . '/audit-test/site-audit.php'; $s['source'] = 'public';
        return ts_site_audit($s)->status($token);
    }],
""")
    os.makedirs(os.path.join(KA, 'tessa'))
    open(os.path.join(KA, 'ai-consultant.php'), 'w', encoding='utf-8').write(c); os.chmod(os.path.join(KA, 'ai-consultant.php'), 0o600)
    cap = json.load(open(os.path.join(PROJ, 'ai', 'tessa', 'capabilities.json'), encoding='utf-8'))
    for x in cap['funkce']:
        if x['id'] == 'audit': x['dostupne'] = True; x['popis'] = 'audit webu podle zadané adresy: když návštěvník napíše adresu webu, pod odpovědí se mu zobrazí tlačítko „Spustit audit“ (měření Google Lighthouse na mobilu i počítači a postřehy AI ke vzhledu); výsledek přijde do chatu za 1–3 minuty, nikdy ho neodhaduj předem'
    cap['$comment'] = cap['$comment'].replace('(T4 hlas Tessy', '(STAGING 5.3 audit + T4 hlas Tessy')
    json.dump(cap, open(os.path.join(KA, 'tessa', 'capabilities.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    for f in ('ai-consultant.php',):
        r = subprocess.run([os.path.expanduser('~/TS-Studio/tools/php/php'), '-l', os.path.join(KA, f)], capture_output=True, text=True); assert r.returncode == 0, r.stdout
    print('Balíček: %s\n  /sub/test: %d souborů, /private/ai-test: 2' % (OUT, sum(len(fs) for _, _, fs in os.walk(KT))))


if __name__ == '__main__':
    main()
