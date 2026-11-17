#!/usr/bin/env python3
"""Etapa 6.3 – administrace AI Design Studia na NEVEŘEJNÉM stagingu (knihovna ts-design-studio 0.3.0).

  7-balicek-studio/oprava-6.3/test/  → obsah nahrát do /sub/test/   (studio.php = rozhraní administrace, api/studio/studio.php = TsStudioApi, knihovna 0.3.0)

Konfigurace /private/studio-test se nemění (model, strop, přístupová cookie). Projekty z testu 6.2 zůstanou.
Stará stránka testu kvality (studio.js) se už nepoužívá – na serveru může zůstat, nic nenačítá.
Použití: python3 -I sestavit_balicek_studio_63.py"""
import hashlib, json, os, shutil, subprocess, sys, tempfile
import importlib.util

HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.expanduser('~/TS-Studio/ts-library')
spec = importlib.util.spec_from_file_location('bs', os.path.join(HERE, 'sestavit_balicek_studio.py')); bs = importlib.util.module_from_spec(spec); spec.loader.exec_module(bs)
VRSTVY = bs.VRSTVY + [os.path.join(HERE, '7-balicek-studio', 'k-nahrani', 'test')]
OUT = os.path.join(HERE, '7-balicek-studio', 'oprava-6.3'); KT = os.path.join(OUT, 'test')
sha = lambda p: hashlib.sha256(open(p, 'rb').read()).hexdigest()

API = r'''<?php
/* AI Design Studio – API administrace na neveřejném stagingu (etapa 6.3). Jen s přístupovou cookie stagingu, jinak 404.
   Konfigurace a data mimo webroot v /private/studio-test/. Model (token Cloudflare) se načte před zúžením open_basedir. */
require __DIR__ . '/../../vendor/ts/ts-design-studio/core/bootstrap.php';
require __DIR__ . '/../../vendor/ts/ts-ai-consultant/core/bootstrap.php';           // ts_ai_harden (HTTPS, open_basedir)
$private = dirname(__DIR__, 4) . '/private/studio-test';
$fail = function (int $code, string $e) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode(['ok' => false, 'error' => $e]); exit; };
if (!is_file($private . '/studio.php')) $fail(503, 'config');
$cfg = require $private . '/studio.php';
$a = $cfg['access'];
if (!is_string($_COOKIE[$a['cookie']] ?? null) || !hash_equals($a['hash'], hash('sha256', (string) $_COOKIE[$a['cookie']]))) $fail(404, 'not-found');
try { $studio = ts_design_studio($cfg['studio']); } catch (Throwable $e) { $fail(503, 'ai'); }
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [dirname(__DIR__, 2), $private]])) exit;
/* staging: jeden uživatel (role a přihlášení přes administraci CRM řeší krok 6.4) */
(new TsStudioApi($studio, ['uid' => 1, 'jobsDir' => $private . '/data/ulohy', 'allowedOrigins' => $cfg['allowedOrigins']]))->serve();
'''

PAGE = r'''<?php
/* Neveřejná administrace „AI Design Studio“ (etapa 6.3). Přístup jen s cookie stagingu (tajný odkaz asistent.php?pristup=…). Bez ní 404.
   CSP: náhled v <iframe srcdoc> dědí pravidla stránky – proto inline skripty/styly a písma data: (síť jen vlastní původ). */
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-src 'self' about:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
$s = require dirname(__DIR__, 2) . '/private/studio-test/studio.php';
$a = $s['access'];
if (!is_string($_COOKIE[$a['cookie']] ?? null) || !hash_equals($a['hash'], hash('sha256', (string) $_COOKIE[$a['cookie']]))) { http_response_code(404); echo '<!doctype html><meta charset="utf-8"><title>Nenalezeno</title><p>Stránka neexistuje.</p>'; exit; }
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>AI Design Studio · staging T&amp;S Studio</title>
<link rel="stylesheet" href="/vendor/ts/ts-design-studio/public/ts-studio.css?v=__V__">
<style>:root{color-scheme:light dark}body{margin:0;background:#e9e9e4;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}@media (prefers-color-scheme:dark){body{background:#0a0a0b}}
main{max-width:1560px;margin:0 auto;padding:16px}.stg{display:inline-block;background:#e10600;color:#fff;font:800 12px/1 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:5px 8px;margin:0 0 10px}</style></head>
<body><main><span class="stg">Neveřejný staging · etapa 6.3</span><div id="ts-studio"></div></main>
<script>window.TS_STUDIO_CONFIG = { api: '/api/studio/studio.php' };</script>
<script src="/vendor/ts/ts-design-studio/public/ts-studio.js?v=__V__"></script>
</body></html>
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    tmp = tempfile.mkdtemp(dir=os.environ.get('TMPDIR'))
    for v in VRSTVY: shutil.copytree(v, tmp, dirs_exist_ok=True)
    lock = json.load(open(os.path.join(tmp, 'ts-components.lock.json')))['components']
    assert lock['ts-design-studio']['version'] == '0.2.1', 'neočekávaný stav stagingu: ' + lock['ts-design-studio']['version']
    pred = {os.path.relpath(os.path.join(d, f), tmp): sha(os.path.join(d, f)) for d, _, fs in os.walk(tmp) for f in fs}
    r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'update', 'ts-design-studio', tmp], capture_output=True, text=True)
    if r.returncode: sys.exit('ts.py update: ' + r.stdout + r.stderr)
    ver = json.load(open(os.path.join(LIB, 'components', 'ts-design-studio', 'module.json')))['version']
    bs.w(os.path.join(tmp, 'api', 'studio', 'studio.php'), API)
    bs.w(os.path.join(tmp, 'studio.php'), PAGE.replace('__V__', ver))
    for d, _, fs in os.walk(tmp):
        for f in fs:
            p = os.path.join(d, f); rel = os.path.relpath(p, tmp)
            if rel.split(os.sep)[0] in ('ts-config', 'ts-theme'): continue
            if '/ts-design-studio/tools/' in '/' + rel or '/ts-design-studio/tests/' in '/' + rel: continue
            if pred.get(rel) != sha(p): os.makedirs(os.path.dirname(os.path.join(KT, rel)), exist_ok=True); shutil.copy2(p, os.path.join(KT, rel))
    shutil.rmtree(tmp)
    assert not any('/tests/' in os.path.join(d, f) or '/tools/' in os.path.join(d, f) for d, _, fs in os.walk(KT) for f in fs), 'testy a nástroje nepatří na server'
    php = os.path.expanduser('~/TS-Studio/tools/php/php')
    for f in (os.path.join(KT, 'studio.php'), os.path.join(KT, 'api', 'studio', 'studio.php')):
        r = subprocess.run([php, '-l', f], capture_output=True, text=True); assert r.returncode == 0, r.stdout
    print('Balíček: %s\n  /sub/test: %d souborů' % (OUT, sum(len(fs) for _, _, fs in os.walk(KT))))


if __name__ == '__main__':
    main()
