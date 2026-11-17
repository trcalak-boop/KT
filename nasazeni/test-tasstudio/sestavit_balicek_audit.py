#!/usr/bin/env python3
"""Etapa 5.2 – audit webů na NEVEŘEJNÉM stagingu test.tasstudio.eu (administrace „Audity“ za tajnou cookie Tessy).

  6-balicek-audit/k-nahrani/test/        → obsah nahrát do /sub/test/   (audit.php, api/audit/audit.php, vendor/ts/ts-site-audit, zámek komponent)
  6-balicek-audit/k-nahrani/audit-test/  → obsah nahrát do /private/audit-test/   (konfigurace, .htaccess; klíč Google vkládá provozovatel)

Stav /sub/test se skládá z dosavadních balíčků (vrstvy) a ts.py add přidá komponentu – do balíčku jdou jen nové/změněné soubory.
Přístup: stejná cookie jako staging Tessy (otisk se převezme z k-nahrani/ai-test/ai-consultant.php), nic se neukazuje veřejně.
Použití: python3 -I sestavit_balicek_audit.py"""
import hashlib, json, os, re, secrets, shutil, subprocess, sys, tempfile

HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.expanduser('~/TS-Studio/ts-library')
T = os.path.join(HERE, '5-balicek-tessa', 'k-nahrani')
VRSTVY = [os.path.join(HERE, '2-balicek', 'nahrat-do-sub-test'), os.path.join(HERE, '3-balicek-ai', 'nahrat-do-sub-test'), os.path.join(HERE, '3-balicek-ai', 'k-nahrani', 'oprava-1.0.1', 'test'),
          os.path.join(T, 'test'), os.path.join(T, 'oprava-1.5.1', 'oprava-1.5.0', 'test'), os.path.join(T, 'oprava-1.5.1', 'test')] + [os.path.join(T, 'oprava-' + v, 'test') for v in ('1.6.0', '1.6.1', '1.7.0', '1.7.1', '1.8.0')]
OUT = os.path.join(HERE, '6-balicek-audit'); KT = os.path.join(OUT, 'k-nahrani', 'test'); KP = os.path.join(OUT, 'k-nahrani', 'audit-test')
DENY = '# Neveřejné – z internetu nepřístupné (Apache).\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n'
sha = lambda p: hashlib.sha256(open(p, 'rb').read()).hexdigest()


def w(p, t, mode=0o644):
    os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(t); os.chmod(p, mode)


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    tmp = tempfile.mkdtemp(dir=os.environ.get('TMPDIR'))
    for v in VRSTVY: shutil.copytree(v, tmp, dirs_exist_ok=True)
    assert json.load(open(os.path.join(tmp, 'ts-components.lock.json')))['components']['ts-ai-consultant']['version'] == '1.8.0', 'neočekávaný stav stagingu'
    pred = {os.path.relpath(os.path.join(d, f), tmp): sha(os.path.join(d, f)) for d, _, fs in os.walk(tmp) for f in fs}
    r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'add', 'ts-site-audit', tmp], capture_output=True, text=True)
    if r.returncode: sys.exit('ts.py add: ' + r.stdout + r.stderr)
    w(os.path.join(tmp, 'api', 'audit', 'audit.php'), '''<?php
/* Audit webů – vstupní bod API (test.tasstudio.eu, jen administrace za přístupovou cookie). Konfigurace a klíče mimo webroot v /private/audit-test/.
   Klíče (Google, Cloudflare) se načtou před zúžením open_basedir; potom PHP smí jen do webrootu subdomény a /private/audit-test/. */
require __DIR__ . '/../../vendor/ts/ts-site-audit/core/bootstrap.php';
require __DIR__ . '/../../vendor/ts/ts-ai-consultant/core/bootstrap.php';           // ts_ai_harden (HTTPS, open_basedir)
$private = dirname(__DIR__, 4) . '/private/audit-test';
if (!is_file($private . '/site-audit.php')) { http_response_code(503); header('Content-Type: application/json'); echo '{"ok":false,"error":"config"}'; exit; }
$audit = ts_site_audit(require $private . '/site-audit.php');
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [dirname(__DIR__, 2), $private]])) exit;
$audit->serve();
''')
    w(os.path.join(tmp, 'audit.php'), '''<?php
/* Neveřejná administrace „Audity“ (etapa 5.2). Přístup jen s cookie stagingu Tessy (nastaví ji tajný odkaz asistent.php?pristup=…). Bez ní 404. */
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
$s = require dirname(__DIR__, 2) . '/private/audit-test/site-audit.php';
$a = $s['access'];
if (!is_string($_COOKIE[$a['cookie']] ?? null) || !hash_equals($a['hash'], hash('sha256', (string) $_COOKIE[$a['cookie']]))) { http_response_code(404); echo '<!doctype html><meta charset="utf-8"><title>Nenalezeno</title><p>Stránka neexistuje.</p>'; exit; }
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Audity webů · staging T&amp;S Studio</title>
<link rel="stylesheet" href="/vendor/ts/ts-site-audit/public/ts-audit.css?v=__V__">
<style>:root{--paper:#f1f1ee;--surface:#fff;--ink:#111113;--line:#d9d9d4;--red:#e10600}
@media (prefers-color-scheme:dark){:root{--paper:#111113;--surface:#18181b;--ink:#f1f1ee;--line:#34343a}}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}main{max-width:1000px;margin:0 auto;padding:32px 16px 64px}
.tag{display:inline-block;background:var(--red);color:#fff;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:3px 8px}h1{font-size:36px;margin:12px 0 4px}</style></head>
<body><main><span class="tag">Neveřejný staging · etapa 5.2</span><h1>Audity webů</h1>
<p>Měření Lighthouse přes Google PageSpeed Insights (mobil i počítač) a vizuální hodnocení snímku modelem Cloudflare. Výsledky jen pro tým, nic se nezveřejňuje.</p>
<div id="ts-audit"></div></main>
<script>window.TS_AUDIT_CONFIG = { api: '/api/audit/audit.php', lang: 'cs', admin: true };</script>
<script src="/vendor/ts/ts-site-audit/public/ts-audit.js?v=__V__" defer></script></body></html>
'''.replace('__V__', json.load(open(os.path.join(LIB, 'components', 'ts-site-audit', 'module.json')))['version']))
    for d, _, fs in os.walk(tmp):
        for f in fs:
            p = os.path.join(d, f); rel = os.path.relpath(p, tmp)
            if rel.split(os.sep)[0] in ('ts-config', 'ts-theme'): continue
            if pred.get(rel) != sha(p): os.makedirs(os.path.dirname(os.path.join(KT, rel)), exist_ok=True); shutil.copy2(p, os.path.join(KT, rel))
    shutil.rmtree(tmp)
    assert not any('/tests/' in os.path.join(d, f) for d, _, fs in os.walk(KT) for f in fs), 'testy nepatří na server'

    acc = re.search(r"'access' => \['cookie' => '([^']+)', 'hash' => '([0-9a-f]{64})'\]", open(os.path.join(T, 'ai-test', 'ai-consultant.php'), encoding='utf-8').read())
    assert acc, 'přístupová cookie stagingu nenalezena'
    w(os.path.join(KP, 'site-audit.php'), '''<?php
/* ==========================================================================
   Audit webů – NEVEŘEJNÝ staging (test.tasstudio.eu/audit.php). Konfigurace mimo webroot: /private/audit-test/site-audit.php
   Měření: Google PageSpeed Insights – klíč vkládá provozovatel do google-api-klic.txt (vedle). Bez něj je audit vypnutý.
   Hodnocení vzhledu: Cloudflare Workers AI se stávajícím tokenem stagingu (/private/ai-test), sdílí denní kvótu neuronů s Tessou.
   ========================================================================== */
$private = __DIR__; $ai = dirname(__DIR__) . '/ai-test';
return [
    'enabled' => true, 'timezone' => 'Europe/Prague', 'defaultLang' => 'cs', 'source' => 'admin',
    'meter' => ['provider' => 'pagespeed', 'apiKeyFile' => $private . '/google-api-klic.txt', 'timeout' => 100, 'issuesPerCategory' => 5],
    'vision' => ['provider' => 'cloudflare', 'apiKeyFile' => $ai . '/cloudflare-token.txt', 'accountIdFile' => $ai . '/cloudflare-ucet.txt', 'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 700],
    'visionPerDay' => 20,
    'db' => ['dsn' => 'sqlite:' . $private . '/data/audit.sqlite', 'autoMigrate' => true],
    'shotDir' => $private . '/data/snimky',
    'salt' => '%s', 'cacheHours' => 24, 'retentionDays' => 30,
    'limits' => ['publicPerIpDay' => 2, 'publicPerDay' => 20, 'adminPerDay' => 40],
    'allowedOrigins' => ['https://test.tasstudio.eu'],
    'access' => ['cookie' => '%s', 'hash' => '%s'],
];
''' % (secrets.token_hex(16), acc.group(1), acc.group(2)), 0o600)
    w(os.path.join(KP, '.htaccess'), DENY); w(os.path.join(KP, 'data', '.htaccess'), DENY)
    r = subprocess.run([os.path.expanduser('~/TS-Studio/tools/php/php'), '-l', os.path.join(KP, 'site-audit.php')], capture_output=True, text=True)
    assert r.returncode == 0, r.stdout
    n = sum(len(fs) for _, _, fs in os.walk(KT))
    print('Balíček: %s\n  /sub/test: %d souborů, /private/audit-test: %d' % (OUT, n, sum(len(fs) for _, _, fs in os.walk(KP))))


if __name__ == '__main__':
    main()
