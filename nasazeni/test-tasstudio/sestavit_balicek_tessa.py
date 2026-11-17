#!/usr/bin/env python3
"""Etapa T1 (Tessa v textu) pro NEVEŘEJNÝ staging test.tasstudio.eu.

  5-balicek-tessa/
    k-nahrani/test/        → obsah nahrát do /sub/test/   (knihovna 1.3.0 – jen změněné soubory, stránka asistent.php, portrét)
    k-nahrani/ai-test/     → obsah nahrát do /private/ai-test/   (nová konfigurace s personou a přístupovým klíčem, persona Tessy)
    lokalne-NENAHRAVAT/    → tajný odkaz na staging, kontrolní kód, výsledky testu
    NAVOD.md

Staging je neveřejný: brána odpoví jen prohlížeči s přístupovou cookie (knihovna 'access'); cookie nastaví jednorázově
tajný odkaz asistent.php?pristup=…  Bez ní stránka vrátí 404 a tlačítko se neukáže. Leady testu jen do DB testovací brány.
Použití: python3 -I sestavit_balicek_tessa.py"""
import datetime, hashlib, json, os, secrets, shutil, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
LIB = os.path.expanduser('~/TS-Studio/ts-library'); PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; PHP = os.path.join(PROJ, 'tools', 'php', 'php')
TESSA = os.path.join(PROJ, 'ai', 'tessa'); SITE = 'https://tasstudio.eu'; BASE = 'https://test.tasstudio.eu'; MAILBOX = 'rezervace@tasstudio.eu'
OUT = os.path.join(HERE, '5-balicek-tessa'); KT = os.path.join(OUT, 'k-nahrani', 'test'); KA = os.path.join(OUT, 'k-nahrani', 'ai-test'); LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
VRSTVY = [os.path.join(HERE, '2-balicek', 'nahrat-do-sub-test'), os.path.join(HERE, '3-balicek-ai', 'nahrat-do-sub-test'),
          os.path.join(HERE, '3-balicek-ai', 'k-nahrani', 'oprava-1.0.1', 'test')]           # stav /sub/test po předchozích nasazeních
DENY = '# Neveřejné – z internetu nepřístupné (Apache).\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n'


def w(p, t, mode=0o644):
    os.makedirs(os.path.dirname(p), exist_ok=True)
    with open(p, 'w', encoding='utf-8') as f: f.write(t)
    os.chmod(p, mode)


def sha(p): return hashlib.sha256(open(p, 'rb').read()).hexdigest()


def main():
    global AIV
    AIV = json.load(open(os.path.join(LIB, 'components', 'ts-ai-consultant', 'module.json')))['version']
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(KT); os.makedirs(KA); os.makedirs(LOC, mode=0o700)
    tmp = os.path.join(OUT, '.tmp'); os.makedirs(tmp)
    for v in VRSTVY: shutil.copytree(v, tmp, dirs_exist_ok=True)
    pred = {os.path.relpath(os.path.join(d, f), tmp): sha(os.path.join(d, f)) for d, _, fs in os.walk(tmp) for f in fs}
    assert json.load(open(os.path.join(tmp, 'ts-components.lock.json')))['components']['ts-ai-consultant']['version'] == '1.0.1', 'neočekávaný výchozí stav testu'
    r = subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'update', 'ts-ai-consultant', tmp], capture_output=True, text=True)
    if r.returncode: sys.exit('ts.py update: ' + r.stdout + r.stderr)
    for d, _, fs in os.walk(tmp):
        for f in fs:
            p = os.path.join(d, f); rel = os.path.relpath(p, tmp)
            if rel.split(os.sep)[0] in ('ts-config', 'ts-theme'): continue
            if pred.get(rel) != sha(p): os.makedirs(os.path.dirname(os.path.join(KT, rel)), exist_ok=True); shutil.copy2(p, os.path.join(KT, rel))
    shutil.rmtree(tmp)

    # přístupový klíč stagingu a kontrolní kód
    token = secrets.token_urlsafe(18); th = hashlib.sha256(token.encode()).hexdigest()
    code = 'AI-' + secrets.token_hex(3).upper() + '-' + secrets.token_hex(3).upper()
    ch = subprocess.run([PHP, '-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', code], capture_output=True, text=True).stdout.strip()
    until = (datetime.date.today() + datetime.timedelta(days=14)).isoformat()
    check = 'kontrola-ai-' + secrets.token_hex(5) + '.php'
    locales = json.load(open(os.path.join(TESSA, 'locales.json'), encoding='utf-8')); locales.pop('$comment', None)
    privacy = {'cs': SITE + '/cs/ochrana-osobnich-udaju.html', 'de': SITE + '/de/datenschutz.html', 'en': SITE + '/privacy.html', '*': SITE + '/privacy.html'}
    for l in ['es', 'fr', 'pt', 'it', 'pl', 'nl', 'uk', 'ar', 'hi', 'zh', 'ja', 'ko', 'tr', 'id', 'ru']: privacy[l] = SITE + '/' + l + '/privacy.html'
    cfg = {'api': '/api/ai/chat.php', 'business': 'T&S Studio', 'email': 'tasstudio@tasstudio.eu', 'privacyUrl': privacy, 'name': 'Tessa', 'mascot': True, 'texts': locales}
    w(os.path.join(KT, 'asistent.php'), (r'''<?php
/* Neveřejný staging Tessy (etapa T1). Vstup jen přes tajný odkaz asistent.php?pristup=…, který nastaví přístupovou cookie
   (stejnou ověřuje brána). Bez ní 404. Klíč je jen jako otisk v /private/ai-test/ai-consultant.php. */
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
$s = require dirname(__DIR__, 2) . '/private/ai-test/ai-consultant.php';
$a = $s['access'];
$ok = function ($v) use ($a) { return is_string($v) && $v !== '' && hash_equals($a['hash'], hash('sha256', $v)); };
if (isset($_GET['pristup']) && $ok($_GET['pristup'])) {
    setcookie($a['cookie'], $_GET['pristup'], ['expires' => time() + 30 * 86400, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    header('Location: /asistent.php', true, 303); exit;
}
if (!$ok($_COOKIE[$a['cookie']] ?? '')) { http_response_code(404); echo '<!doctype html><meta charset="utf-8"><title>Nenalezeno</title><p>Stránka neexistuje.</p>'; exit; }
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Tessa · staging T&amp;S Studio</title>
<link rel="stylesheet" href="/vendor/ts/ts-ai-consultant/public/ts-ai-widget.css?v=__AIV__">
<style>
:root{--paper:#F1F1EE;--surface:#FFFFFF;--ink:#111113;--line:#D9D9D4;--red:#E10600}
@media (prefers-color-scheme:dark){:root{--paper:#111113;--surface:#1A1A1D;--ink:#F1F0EE;--line:#2B2B30}}
body{margin:0;background:var(--paper);color:var(--ink);font:17px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}
main{max-width:760px;margin:0 auto;padding:48px 16px 140px}h1{font-size:clamp(28px,6vw,44px);line-height:1.1;margin:0 0 12px}
.tag{display:inline-block;background:var(--red);color:#fff;font:700 12px/1 system-ui;letter-spacing:.08em;text-transform:uppercase;padding:6px 8px;margin-bottom:16px}
li{margin:6px 0}
</style></head>
<body><main>
<span class="tag">Neveřejný staging · etapa T1</span>
<h1>Tessa</h1>
<p>Stejný asistent jako na webu, navíc jméno Tessa, osobnost, animovaný maskot (robůtek), hlas přes prohlížeč a seznam skutečných funkcí. Odpovídá bezplatný model Cloudflare Workers AI z ověřených znalostí webu; poptávky z formuláře jdou jen do testovací databáze, ne do CRM.</p>
<h2>Co vyzkoušet</h2>
<ol>
<li>Pozdrav a otázka „Kdo jsi?“ – představí se jako Tessa, AI (ne člověk), v ženském rodě.</li>
<li>Tykání („Ahoj, potřebuju web“) a vykání – přizpůsobí oslovení.</li>
<li>„Umíš mluvit?“, „Udělej mi audit mého webu“, „Pošli mi nabídku e-mailem“ – přizná, že to zatím neumí, a nabídne alternativu.</li>
<li>Cena e-shopu i s naléháním – žádná částka, reference a konzultace.</li>
<li>Angličtina, němčina, arabština (RTL), japonština – jméno, označení AI a úvod.</li>
<li>„Jsem naštvaný, nikdo se mi neozval“ – klidně uzná problém a nabídne předání studiu.</li>
</ol>
</main>
<script>window.TS_AI_CONFIG = %s;</script>
<script src="/vendor/ts/ts-ai-consultant/public/ts-ai-widget.js?v=__AIV__" defer></script>
</body></html>
''' % json.dumps(cfg, ensure_ascii=False).replace('</', '<\\/')).replace('__AIV__', AIV))
    w(os.path.join(KT, check), '''<?php
/* Jednorázová kontrola AI asistenta – staging Tessy. Každá akce vyžaduje kontrolní kód; jen HTTPS. Po kontrole SMAZAT. */
require __DIR__ . '/vendor/ts/ts-ai-consultant/core/bootstrap.php';
$private = dirname(__DIR__, 2) . '/private/ai-test';
$settings = require $private . '/ai-consultant.php';
if (!ts_ai_harden(['requireHttps' => true, 'paths' => [__DIR__, $private]])) exit;
(new TsAiCheck($settings))->serve();
''')

    # /private/ai-test: nová konfigurace (persona + přístup), persona Tessy
    os.makedirs(os.path.join(KA, 'tessa'), exist_ok=True); shutil.copy2(os.path.join(TESSA, 'persona.md'), os.path.join(KA, 'tessa', 'persona.md'))
    shutil.copy2(os.path.join(TESSA, 'capabilities.json'), os.path.join(KA, 'tessa', 'capabilities.json'))
    w(os.path.join(KA, 'tessa', '.htaccess'), DENY)
    w(os.path.join(KA, 'ai-consultant.php'), '''<?php
/* ==========================================================================
   Tessa – NEVEŘEJNÝ STAGING (test.tasstudio.eu), etapa T1. /private/ai-test/ai-consultant.php
   Bezplatné Cloudflare Workers AI (token a ID účtu vkládá provozovatel), žádné platby. Přístup jen s cookie z tajného odkazu.
   Leady jen do DB testovací brány, žádné CRM. E-maily o leadech jen do testovací schránky.
   ========================================================================== */
$private = __DIR__;
$smtpHeslo = dirname(__DIR__) . '/booking-test/smtp-heslo.txt';
return [
    'enabled' => true,
    'timezone' => 'Europe/Prague', 'business' => 'T&S Studio', 'contactEmail' => 'tasstudio@tasstudio.eu', 'defaultLang' => 'cs',
    /* bezplatné Cloudflare Workers AI: token v cloudflare-token.txt, ID účtu v cloudflare-ucet.txt (oba vkládá provozovatel) */
    'provider' => 'cloudflare', 'apiKeyFile' => $private . '/cloudflare-token.txt',
    'cloudflare' => ['accountId' => trim((string) @file_get_contents($private . '/cloudflare-ucet.txt')), 'model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast', 'timeout' => 30, 'connectTimeout' => 6],
    'contactMode' => 'marker', 'knowledgeMaxChars' => 5000, 'historyMessages' => 8,
    'maxTokens' => 500, 'budget' => ['limit' => 9500, 'period' => 'day', 'unit' => 'neuron'],
    'knowledgeFile' => $private . '/znalosti.json',
    'persona' => ['name' => 'Tessa', 'file' => $private . '/tessa/persona.md'],
    'capabilitiesFile' => $private . '/tessa/capabilities.json',
    'access' => ['cookie' => 'tsai_pristup', 'hash' => '%(th)s'],
    'db' => ['dsn' => 'sqlite:' . $private . '/data/ai-test.sqlite', 'autoMigrate' => true],
    'salt' => '%(salt)s',
    'limits' => ['perIp10min' => 30, 'perIpDay' => 150, 'globalMinute' => 30, 'turnsPerConversation' => 30, 'leadsPerIpHour' => 5],
    'maxChars' => 1000, 'allowedOrigins' => ['%(base)s'], 'retention' => ['conversationDays' => 30, 'leadDays' => 90],
    'consentText' => 'Souhlasím se zpracováním údajů z formuláře a této konverzace, aby mi studio mohlo odpovědět.',
    'notify' => ['to' => '%(mailbox)s', 'subject' => '[TEST Tessa] Nová poptávka z AI asistenta'],
    'mail' => [
        'transport' => 'smtp', 'logFile' => $private . '/data/mail.log', 'redirectTo' => '%(mailbox)s',
        'from' => '%(mailbox)s', 'fromName' => 'Tessa (TEST)', 'domain' => 'tasstudio.eu',
        'smtp_host' => 'smtp.websupport.cz', 'smtp_port' => 465, 'smtp_secure' => 'ssl', 'smtp_user' => '%(mailbox)s',
        'smtp_pass' => is_readable($smtpHeslo) ? rtrim((string) file_get_contents($smtpHeslo), "\\r\\n") : '',
    ],
    'leadSink' => null,
    'check' => ['codeHash' => '%(ch)s', 'until' => '%(until)s'],
];
''' % {'th': th, 'salt': secrets.token_hex(16), 'base': BASE, 'mailbox': MAILBOX, 'ch': ch, 'until': until}, 0o600)

    for d, _, fs in os.walk(OUT):
        for f in fs:
            if 'lokalne' in d: continue
            t = open(os.path.join(d, f), 'rb').read()
            assert b'sk-ant-api' not in t and token.encode() not in t, 'tajná hodnota v balíčku: ' + f
    w(os.path.join(LOC, 'pristup.txt'), 'Tajný odkaz na staging Tessy (otevřete jednou, prohlížeč si přístup pamatuje 30 dní):\n%s/asistent.php?pristup=%s\n\nKontrolní kód (do %s): %s\nKontrola: %s/%s\nNikam nenahrávat ani neposílat.\n' % (BASE, token, until, code, BASE, check), 0o600)
    files = sorted(os.path.relpath(os.path.join(d, f), KT) for d, _, fs in os.walk(KT) for f in fs)
    json.dump({'check': check, 'until': until, 'built': datetime.datetime.now().isoformat(timespec='seconds'), 'test_files': files}, open(os.path.join(LOC, 'balicek.json'), 'w'), ensure_ascii=False, indent=1)
    t = open(os.path.join(HERE, 'navod-tessa-sablona.md'), encoding='utf-8').read().replace('{KONTROLA}', check).replace('{PLATNOST}', until).replace('{SOUBORY}', '\n'.join('- `' + f + '`' for f in files))
    w(os.path.join(OUT, 'NAVOD.md'), t)
    print('Balíček: %s\n  /sub/test: %d souborů, /private/ai-test: %d\n  kontrola: %s' % (OUT, len(files), sum(len(fs) for _, _, fs in os.walk(KA)), check))


if __name__ == '__main__':
    main()
