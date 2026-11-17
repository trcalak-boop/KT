#!/usr/bin/env python3
"""Etapa 6.4b – schválení a doručení návrhů na NEVEŘEJNÉM stagingu (knihovna ts-design-studio 0.4.0).

  7-balicek-studio/oprava-6.4/test/          → obsah do /sub/test/            (knihovna 0.4.0, studio.php s přepínačem testovacích rolí, API s rolemi)
  7-balicek-studio/oprava-6.4/nahledy/       → obsah do /sub/nahledy/         (brána náhledu pro klienta: .htaccess, index.php, vlastní kopie knihovny)
  7-balicek-studio/oprava-6.4/studio-test/   → obsah do /private/studio-test/ (konfigurace: + tajný klíč doručení, adresa náhledů, e-mail; přepíše studio.php)

E-mail: dokud neexistuje /private/studio-test/posta.php (SMTP – heslo vkládá provozovatel, šablona posta.example.php), e-maily se jen zapisují
do /private/studio-test/data/posta.log. I se SMTP jdou na stagingu VŠECHNY e-maily jen na testovací adresu (redirectTo v posta.php).
Role: na stagingu smyšlení testovací uživatelé (admin, designer, sales, viewer) přepínaní na stránce; skutečné role z CRM až v produkci (6.5).
Použití: python3 -I sestavit_balicek_studio_64.py"""
import hashlib, json, os, re, secrets, shutil, subprocess, sys, tempfile
import importlib.util

HERE = os.path.dirname(os.path.abspath(__file__)); LIB = os.path.expanduser('~/TS-Studio/ts-library'); COMP = os.path.join(LIB, 'components', 'ts-design-studio')
spec = importlib.util.spec_from_file_location('bs', os.path.join(HERE, 'sestavit_balicek_studio.py')); bs = importlib.util.module_from_spec(spec); spec.loader.exec_module(bs)
VRSTVY = bs.VRSTVY + [os.path.join(HERE, '7-balicek-studio', 'k-nahrani', 'test'), os.path.join(HERE, '7-balicek-studio', 'oprava-6.3', 'test')]
OUT = os.path.join(HERE, '7-balicek-studio', 'oprava-6.4'); KT = os.path.join(OUT, 'test'); KN = os.path.join(OUT, 'nahledy'); KP = os.path.join(OUT, 'studio-test')
sha = lambda p: hashlib.sha256(open(p, 'rb').read()).hexdigest()
DENY = bs.b52.DENY

ROLES = r'''/* staging: smyšlení testovací uživatelé místo účtů CRM (role přepíná stránka cookie studio_role) */
$roles = ['admin' => 1, 'designer' => 2, 'sales' => 3, 'viewer' => 4];
$role = is_string($_COOKIE['studio_role'] ?? null) && isset($roles[$_COOKIE['studio_role']]) ? $_COOKIE['studio_role'] : 'admin';
$byUid = array_flip($roles);
$cfg['studio']['can'] = fn (int $uid, string $act) => TsStudioRoles::allows($byUid[$uid] ?? 'viewer', $act);
'''

API = r'''<?php
/* AI Design Studio – API administrace na neveřejném stagingu (etapa 6.4b). Jen s přístupovou cookie stagingu, jinak 404.
   Konfigurace a data mimo webroot v /private/studio-test/. Model (token Cloudflare) a e-mail se sestaví před zúžením open_basedir. */
require __DIR__ . '/../../vendor/ts/ts-design-studio/core/bootstrap.php';
require __DIR__ . '/../../vendor/ts/ts-ai-consultant/core/bootstrap.php';           // ts_ai_harden (HTTPS, open_basedir)
$private = dirname(__DIR__, 4) . '/private/studio-test';
$fail = function (int $code, string $e) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode(['ok' => false, 'error' => $e]); exit; };
if (!is_file($private . '/studio.php')) $fail(503, 'config');
$cfg = require $private . '/studio.php';
$a = $cfg['access'];
if (!is_string($_COOKIE[$a['cookie']] ?? null) || !hash_equals($a['hash'], hash('sha256', (string) $_COOKIE[$a['cookie']]))) $fail(404, 'not-found');
__ROLES__
try { $studio = ts_design_studio($cfg['studio']); } catch (Throwable $e) { error_log('ts-studio: ' . $e->getMessage()); $fail(503, 'ai'); }
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [dirname(__DIR__, 2), $private]])) exit;
(new TsStudioApi($studio, ['uid' => $roles[$role], 'role' => $role, 'sender' => ['name' => 'Testovací ' . $role . ' (staging)', 'email' => (string) ($cfg['testSender'] ?? '')],
    'jobsDir' => $private . '/data/ulohy', 'allowedOrigins' => $cfg['allowedOrigins']]))->serve();
'''

PAGE = r'''<?php
/* Neveřejná administrace „AI Design Studio“ (etapa 6.4b). Přístup jen s cookie stagingu (tajný odkaz asistent.php?pristup=…). Bez ní 404.
   CSP: náhled v <iframe srcdoc> dědí pravidla stránky – proto inline skripty/styly a písma data: (síť jen vlastní původ). */
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-src 'self' about:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
$s = require dirname(__DIR__, 2) . '/private/studio-test/studio.php';
$a = $s['access'];
if (!is_string($_COOKIE[$a['cookie']] ?? null) || !hash_equals($a['hash'], hash('sha256', (string) $_COOKIE[$a['cookie']]))) { http_response_code(404); echo '<!doctype html><meta charset="utf-8"><title>Nenalezeno</title><p>Stránka neexistuje.</p>'; exit; }
$roles = ['admin' => 'Admin (vše)', 'designer' => 'Designer (úpravy, schválení)', 'sales' => 'Sales (doručení klientovi)', 'viewer' => 'Viewer (jen prohlížení)'];
$role = is_string($_COOKIE['studio_role'] ?? null) && isset($roles[$_COOKIE['studio_role']]) ? $_COOKIE['studio_role'] : 'admin';
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>AI Design Studio · staging T&amp;S Studio</title>
<link rel="stylesheet" href="/vendor/ts/ts-design-studio/public/ts-studio.css?v=__V__">
<style>:root{color-scheme:light dark}body{margin:0;background:#e9e9e4;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}@media (prefers-color-scheme:dark){body{background:#0a0a0b;color:#f0f0ec}}
main{max-width:1560px;margin:0 auto;padding:16px}.stg{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin:0 0 10px;font-size:14px}
.stg b{background:#e10600;color:#fff;font:800 12px/1 system-ui,sans-serif;letter-spacing:.06em;text-transform:uppercase;padding:5px 8px}.stg select{font:inherit;padding:4px 6px}</style></head>
<body><main><div class="stg"><b>Neveřejný staging · etapa 6.4</b><label>Testovací uživatel: <select id="stg-role"><?php foreach ($roles as $k => $l) echo '<option value="' . $k . '"' . ($k === $role ? ' selected' : '') . '>' . htmlspecialchars($l) . '</option>'; ?></select></label>
<span>E-maily: <?= is_file(dirname(__DIR__, 2) . '/private/studio-test/posta.php') ? 'SMTP, jen na testovací adresu' : 'jen do souboru posta.log (bez odeslání)' ?></span></div><div id="ts-studio"></div></main>
<script>document.getElementById('stg-role').addEventListener('change', function () { document.cookie = 'studio_role=' + this.value + '; path=/; secure; samesite=strict'; location.reload(); });
window.TS_STUDIO_CONFIG = { api: '/api/studio/studio.php' };</script>
<script src="/vendor/ts/ts-design-studio/public/ts-studio.js?v=__V__"></script>
</body></html>
'''

GATE = r'''<?php
/* Brána neveřejných náhledů pro klienty – nahledy.tasstudio.eu (etapa 6.4b, zatím data NEVEŘEJNÉHO stagingu /private/studio-test).
   Vydá jen soubory aktivního doručení (odeslaného nebo ručně předaného, neprošlého, neodvolaného). Bez cookies, bez indexace, přísná CSP.
   Konfigurace (tajný klíč doručení) se načte před zúžením open_basedir; potom PHP smí jen do této složky a /private/studio-test. */
require __DIR__ . '/vendor/ts/ts-design-studio/core/bootstrap.php';
$private = dirname(__DIR__, 2) . '/private/studio-test';
$deny = function (int $code) { http_response_code($code); header('Content-Type: text/plain; charset=utf-8'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); echo $code === 503 ? 'Náhledy jsou dočasně nedostupné.' : 'Nenalezeno.'; exit; };
if (!is_file($private . '/studio.php')) $deny(503);
$cfg = require $private . '/studio.php';
$c = $cfg['studio']; unset($c['ai'], $c['mail']);                      // brána nepotřebuje model ani e-mail
try { $studio = ts_design_studio($c); } catch (Throwable $e) { error_log('ts-nahledy: ' . $e->getMessage()); $deny(503); }
@ini_set('display_errors', '0');
if (ini_set('open_basedir', implode(PATH_SEPARATOR, [realpath(__DIR__) . '/', realpath($private) . '/'])) === false) error_log('ts-nahledy: open_basedir nelze zúžit');
(new TsStudioPreviewGate($studio, ['failDir' => $private . '/data/brana', 'contact' => (string) ($cfg['previewContact'] ?? 'tasstudio.eu')]))->serve();
'''
GATE_HT = '''# Brána náhledů: všechny požadavky (i neexistující soubory) zpracuje index.php; přímý přístup do vendor/ zakázán.
Options -Indexes
DirectoryIndex index.php
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^ index.php [L,QSA]
</IfModule>
<IfModule mod_headers.c>
  Header always set X-Robots-Tag "noindex, nofollow, noarchive"
</IfModule>
'''

POSTA = '''<?php
/* ŠABLONA – e-mail klientovi ze stagingu Studia. Zkopírujte jako posta.php (ve stejné složce /private/studio-test), doplňte heslo SMTP.
   NA STAGINGU MUSÍ ZŮSTAT redirectTo: každý e-mail dostane jen tato testovací adresa (předmět „[TEST → původní příjemce]“). Do gitu ani balíčků nepatří. */
return ['transport' => 'smtp', 'smtp_host' => 'smtp.websupport.cz', 'smtp_port' => 587, 'smtp_secure' => 'tls',
    'smtp_user' => 'studio@tasstudio.eu', 'smtp_pass' => '…HESLO…',
    'from' => 'studio@tasstudio.eu', 'fromName' => 'T&S Studio', 'domain' => 'tasstudio.eu',
    'redirectTo' => '…VAŠE TESTOVACÍ ADRESA…'];
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    tmp = tempfile.mkdtemp(dir=os.environ.get('TMPDIR'))
    for v in VRSTVY: shutil.copytree(v, tmp, dirs_exist_ok=True)
    lock = json.load(open(os.path.join(tmp, 'ts-components.lock.json')))['components']
    assert lock['ts-design-studio']['version'] == '0.3.0' and lock['ts-mailer-php']['version'] == '1.1.0', 'neočekávaný stav stagingu'
    pred = {os.path.relpath(os.path.join(d, f), tmp): sha(os.path.join(d, f)) for d, _, fs in os.walk(tmp) for f in fs}
    r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'update', 'ts-design-studio', tmp], capture_output=True, text=True)
    if r.returncode: sys.exit('ts.py update: ' + r.stdout + r.stderr)
    ver = json.load(open(os.path.join(COMP, 'module.json')))['version']
    bs.w(os.path.join(tmp, 'api', 'studio', 'studio.php'), API.replace('__ROLES__', ROLES))
    bs.w(os.path.join(tmp, 'studio.php'), PAGE.replace('__V__', ver))
    for d, _, fs in os.walk(tmp):
        for f in fs:
            p = os.path.join(d, f); rel = os.path.relpath(p, tmp)
            if rel.split(os.sep)[0] in ('ts-config', 'ts-theme'): continue
            if '/ts-design-studio/tools/' in '/' + rel or '/ts-design-studio/tests/' in '/' + rel: continue
            if pred.get(rel) != sha(p): os.makedirs(os.path.dirname(os.path.join(KT, rel)), exist_ok=True); shutil.copy2(p, os.path.join(KT, rel))
    # brána: vlastní kopie knihovny (jádro + šablony + migrace; bez rozhraní, testů a nástrojů) – subdoména nemusí smět do /sub/test
    shutil.copytree(os.path.join(tmp, 'vendor', 'ts', 'ts-design-studio'), os.path.join(KN, 'vendor', 'ts', 'ts-design-studio'), ignore=shutil.ignore_patterns('tests', 'tools', 'public'))
    shutil.rmtree(tmp)
    bs.w(os.path.join(KN, 'index.php'), GATE); bs.w(os.path.join(KN, '.htaccess'), GATE_HT); bs.w(os.path.join(KN, 'vendor', '.htaccess'), DENY)
    for f in ('/tests/', '/tools/'): assert not any(f in os.path.join(d, x) for root in (KT, KN) for d, _, fs in os.walk(root) for x in fs), 'testy a nástroje nepatří na server'

    acc = re.search(r"'access' => \['cookie' => '([^']+)', 'hash' => '([0-9a-f]{64})'\]", open(os.path.join(bs.b52.T, 'ai-test', 'ai-consultant.php'), encoding='utf-8').read())
    assert acc, 'přístupová cookie stagingu nenalezena'
    bs.w(os.path.join(KP, 'studio.php'), '''<?php
/* ==========================================================================
   AI Design Studio – NEVEŘEJNÝ staging (test.tasstudio.eu/studio.php + brána nahledy.tasstudio.eu). Konfigurace mimo webroot: /private/studio-test/studio.php
   Model: Cloudflare Workers AI se stávajícím tokenem stagingu (/private/ai-test), sdílí denní kvótu 10 000 neuronů s Tessou a auditem.
   Doručení: tajný klíč (odvozuje tokeny odkazů – neměnit, jinak přestanou platit), náhledy na nahledy.tasstudio.eu.
   E-mail: posta.php (SMTP, heslo vkládá provozovatel, redirectTo = testovací adresa); bez něj jen zápis do data/posta.log.
   ========================================================================== */
$private = __DIR__; $ai = dirname(__DIR__) . '/ai-test';
$posta = is_file($private . '/posta.php') ? (require $private . '/posta.php') : ['transport' => 'log', 'logFile' => $private . '/data/posta.log', 'from' => 'studio@tasstudio.eu', 'fromName' => 'T&S Studio', 'domain' => 'tasstudio.eu'];
if (($posta['transport'] ?? '') !== 'log' && empty($posta['redirectTo'])) $posta = ['transport' => 'log', 'logFile' => $private . '/data/posta.log', 'from' => 'studio@tasstudio.eu'];   // staging bez testovací adresy nikdy neposílá ven
return [
    'studio' => [
        'db' => ['dsn' => 'sqlite:' . $private . '/data/studio.sqlite', 'autoMigrate' => true],
        'timezone' => 'Europe/Prague',
        'ai' => ['provider' => 'cloudflare', 'apiKeyFile' => $ai . '/cloudflare-token.txt', 'accountIdFile' => $ai . '/cloudflare-ucet.txt',
                 'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 4500, 'timeout' => 150, 'neuronsPerDay' => 2500],
        'deliverySecret' => '%s',
        'previewBase' => 'https://nahledy.tasstudio.eu/',
        'mail' => $posta,
    ],
    'previewContact' => 'tasstudio.eu',
    'testSender' => '',
    'allowedOrigins' => ['https://test.tasstudio.eu'],
    'access' => ['cookie' => '%s', 'hash' => '%s'],
];
''' % (secrets.token_urlsafe(36), acc.group(1), acc.group(2)), 0o600)
    bs.w(os.path.join(KP, 'posta.example.php'), POSTA, 0o600)
    bs.w(os.path.join(KP, '.htaccess'), DENY); bs.w(os.path.join(KP, 'data', '.htaccess'), DENY)
    php = os.path.expanduser('~/TS-Studio/tools/php/php')
    for f in (os.path.join(KP, 'studio.php'), os.path.join(KP, 'posta.example.php'), os.path.join(KT, 'studio.php'), os.path.join(KT, 'api', 'studio', 'studio.php'), os.path.join(KN, 'index.php')):
        r = subprocess.run([php, '-l', f], capture_output=True, text=True); assert r.returncode == 0, r.stdout
    n = lambda root: sum(len(fs) for _, _, fs in os.walk(root))
    print('Balíček: %s\n  /sub/test: %d souborů, /sub/nahledy: %d, /private/studio-test: %d' % (OUT, n(KT), n(KN), n(KP)))


if __name__ == '__main__':
    main()
