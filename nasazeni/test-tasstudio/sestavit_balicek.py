#!/usr/bin/env python3
"""Sestaví nasazovací balíček rezervací pro test.tasstudio.eu (Active24/Websupport) z knihovny T&S.

    python3 -I sestavit_balicek.py

Výsledek: 2-balicek/
  nahrat-do-sub-test/          → obsah nahrát do /sub/test/            (webroot test.tasstudio.eu)
  nahrat-do-private-booking/   → obsah nahrát do /private/booking-test/ (mimo webroot)
  lokalne-NENAHRAVAT/          → instalační kód (jen pro vás, nikam se nenahrává)
  SHA256SUMS.txt
Balíček neobsahuje žádné heslo. Instalační kód je v konfiguraci jen jako otisk (password_hash).
"""
import datetime, hashlib, json, os, secrets, shutil, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
LIB = os.path.expanduser('~/TS-Studio/ts-library')
PHP = os.path.expanduser('~/TS-Studio/tools/php/php')
DEMO = os.path.expanduser('~/TS-Studio/demo-projekty/kadernictvi-vlna-server')
OUT = os.path.join(HERE, '2-balicek')
WEB = os.path.join(OUT, 'nahrat-do-sub-test')
PRIV = os.path.join(OUT, 'nahrat-do-private-booking')
LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
BASE = 'https://test.tasstudio.eu'
MAILBOX = 'rezervace@tasstudio.eu'
DENY = '''# Z internetu nepřístupné.
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order allow,deny
  Deny from all
</IfModule>
'''


def w(path, text, mode=0o644):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(text)
    os.chmod(path, mode)


def main():
    for d in (WEB, PRIV, LOC):                               # NAVOD.md a KONTROLNI-SEZNAM.md zůstávají
        if os.path.exists(d): shutil.rmtree(d)
    os.makedirs(WEB); os.makedirs(PRIV); os.makedirs(LOC, mode=0o700)
    # --- knihovní komponenty standardním postupem ---
    for c in ['ts-booking-server', 'ts-booking-widget']:
        subprocess.run([sys.executable, '-I', os.path.join(LIB, 'tools', 'ts.py'), 'add', c, WEB], check=True, stdout=subprocess.DEVNULL)
    shutil.rmtree(os.path.join(WEB, 'ts-config'))           # vzory konfigurací do webrootu nepatří (konfigurace je v private)
    # --- web ---
    shutil.copy(os.path.join(DEMO, 'web', 'ts-theme', 'ts-booking-widget', 'tokens.css'), os.path.join(WEB, 'ts-theme', 'ts-booking-widget', 'tokens.css'))
    os.makedirs(os.path.join(WEB, 'assets'))
    shutil.copy(os.path.join(DEMO, 'web', 'assets', 'web.css'), os.path.join(WEB, 'assets', 'web.css'))
    for page in ['index.html', 'sprava.html', 'rezervace-zrusit.html']:
        t = open(os.path.join(DEMO, 'web', page), encoding='utf-8').read()
        t = t.replace('<head><meta charset="utf-8">', '<head><meta charset="utf-8"><meta name="robots" content="noindex, nofollow">', 1)
        t = t.replace('(serverová ukázka)', '(TEST)').replace('E-maily se v ukázce neodesílají.', 'Testovací prostředí – rezervace jsou fiktivní, e-maily chodí jen do testovací schránky studia.')
        t = t.replace('(v ukázce se zapisuje do private/mail.log)', '(v testu jen do testovací schránky studia)')
        w(os.path.join(WEB, page), t)
    w(os.path.join(WEB, 'api', 'rezervace.php'), '''<?php
/* Rezervace – vstupní bod API (test.tasstudio.eu). Konfigurace je mimo webroot v /private/booking-test/.
   ts_booking_harden: bez zobrazení chyb, jen HTTPS, PHP smí číst a zapisovat jen ve webrootu subdomény a v /private/booking-test/. */
ini_set('display_errors', '0');
$private = dirname(__DIR__, 3) . '/private/booking-test';
if (!is_file($private . '/booking-server.php')) { http_response_code(503); header('Content-Type: application/json'); echo '{"ok":false,"error":"config"}'; exit; }
require __DIR__ . '/../vendor/ts/ts-booking-server/core/bootstrap.php';
if (!ts_booking_harden(['requireHttps' => true, 'paths' => [dirname(__DIR__), $private]])) exit;
ts_booking_api(require $private . '/booking-server.php')->serve();
''')
    inst_name = 'instalace-%s.php' % secrets.token_hex(5)
    w(os.path.join(WEB, inst_name), '''<?php
/* JEDNORÁZOVÝ INSTALÁTOR – po vytvoření administrátora tento soubor z hostingu SMAŽTE.
   Každá akce i zobrazení kontroly vyžaduje instalační kód; jen HTTPS; PHP omezené na webroot subdomény a /private/booking-test/. */
ini_set('display_errors', '0');
$private = dirname(__DIR__, 2) . '/private/booking-test';
if (!is_file($private . '/booking-server.php')) { http_response_code(503); echo 'Chybí neveřejná konfigurace.'; exit; }
require __DIR__ . '/vendor/ts/ts-booking-server/core/bootstrap.php';
require __DIR__ . '/vendor/ts/ts-booking-server/core/TsBookingInstaller.php';
if (!ts_booking_harden(['requireHttps' => true, 'paths' => [__DIR__, $private]])) exit;
(new TsBookingInstaller(require $private . '/booking-server.php'))->serve();
''')
    w(os.path.join(WEB, 'robots.txt'), 'User-agent: *\nDisallow: /\n')
    w(os.path.join(WEB, '.htaccess'), '''# test.tasstudio.eu – rezervace (TEST)
Options -Indexes
DirectoryIndex index.html
AddDefaultCharset UTF-8
# Přesměrování HTTP → HTTPS záměrně není: za proxy hostingu hrozí smyčka (stejně jako na hlavním webu). HTTPS vynucuje HSTS níže.
<IfModule mod_headers.c>
  Header always set X-Robots-Tag "noindex, nofollow"
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Strict-Transport-Security "max-age=86400"
</IfModule>
# soubory, které nemají být čitelné z webu
<FilesMatch "(\\.(sqlite|sqlite-wal|sqlite-shm|db|log|md|lock|bak|sql)|ts-components\\.lock\\.json|module\\.json|LOCKED\\.sha256)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
</FilesMatch>
''')
    # --- neveřejná část ---
    cfg = json.load(open(os.path.join(DEMO, 'private', 'rezervace-config.json'), encoding='utf-8'))
    cfg['$comment'] = 'Salon Vlna (fiktivní) – TEST na test.tasstudio.eu. Konfigurace oboru pro ts-booking-server.'
    w(os.path.join(PRIV, 'rezervace-config.json'), json.dumps(cfg, ensure_ascii=False, indent=2) + '\n', 0o600)
    code = '-'.join(secrets.token_hex(3).upper() for _ in range(4))
    h = subprocess.run([PHP, '-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', code], capture_output=True, text=True, check=True).stdout.strip()
    until = (datetime.date.today() + datetime.timedelta(days=7)).isoformat()
    w(os.path.join(PRIV, 'booking-server.php'), '''<?php
/* ==========================================================================
   Rezervace TEST (test.tasstudio.eu) – neveřejná serverová konfigurace.
   Umístění: /private/booking-test/booking-server.php (mimo webroot). Do gitu ani do balíčku nepatří heslo:
   SMTP heslo patří do samostatného textového souboru smtp-heslo.txt vedle tohoto souboru (vytvoříte ho až na serveru).
   Čte se jako prostý text (file_get_contents) – nikdy se nespouští jako PHP, takže se nemůže objevit v chybové hlášce.
   Data jsou fiktivní; e-maily jdou vždy jen do testovací schránky (redirectTo).
   ========================================================================== */
$private = __DIR__;
$hesloSoubor = $private . '/smtp-heslo.txt';
return [
    'timezone' => 'Europe/Prague',
    'db' => ['dsn' => 'sqlite:' . $private . '/data/rezervace-test.sqlite', 'autoMigrate' => true],
    'configFile' => $private . '/rezervace-config.json',
    'authDir' => $private . '/data/auth',
    'cookiePath' => '/',
    'autoConfirm' => false,
    'rateLimit' => ['bookings' => [20, 3600]],
    'minFillSeconds' => 2,
    'allowedOrigins' => ['%(base)s'],
    'retentionDays' => 90,
    'mail' => [
        /* REŽIM E-MAILŮ:  'log'  = jen zápis do data/mail.log (výchozí, nic se neodesílá)
                           'smtp' = odesílání přes SMTP; díky redirectTo jde VŠE jen do testovací schránky */
        'transport' => 'log',
        'logFile' => $private . '/data/mail.log',
        'redirectTo' => '%(mailbox)s',
        'from' => '%(mailbox)s', 'fromName' => 'Salon Vlna (TEST)', 'domain' => 'tasstudio.eu',
        'smtp_host' => 'smtp.websupport.cz', 'smtp_port' => 465, 'smtp_secure' => 'ssl',
        'smtp_user' => '%(mailbox)s',
        'smtp_pass' => is_readable($hesloSoubor) ? rtrim((string) file_get_contents($hesloSoubor), "\\r\\n") : '',
    ],
    'notify' => [
        'business' => 'Salon Vlna (TEST)', 'staffEmail' => '%(mailbox)s', 'currency' => 'Kč',
        'cancelUrl' => '%(base)s/rezervace-zrusit.html?id={id}&token={token}',
        'adminUrl' => '%(base)s/sprava.html',
    ],
    'install' => [
        'codeHash' => '%(hash)s',
        'until' => '%(until)s',
        'testRecipient' => '%(mailbox)s',
    ],
];
''' % {'base': BASE, 'mailbox': MAILBOX, 'hash': h, 'until': until}, 0o600)
    w(os.path.join(OUT, 'SMTP-heslo-navod.txt'), '''Jak zadat heslo SMTP – tento soubor se NENAHRÁVÁ.
Na serveru ve složce /private/booking-test/ vytvořte nový soubor smtp-heslo.txt, který obsahuje JEN heslo
schránky rezervace@tasstudio.eu na jednom řádku (bez uvozovek, bez PHP, bez mezer na začátku a na konci).
Soubor se čte jako prostý text, nikdy se nespouští.

Heslo nikam jinam nepište (ne do gitu, e-mailu ani chatu). Soubor smažte, až test skončí.
''')
    w(os.path.join(PRIV, '.htaccess'), DENY)
    w(os.path.join(PRIV, 'data', '.htaccess'), DENY)
    w(os.path.join(LOC, 'instalacni-kod.txt'), 'Instalační kód (platí do %s): %s\nInstalátor: %s/%s\nNikam ho nenahrávejte ani neposílejte.\n' % (until, code, BASE, inst_name), 0o600)
    # --- kontrolní součty (bez lokálních souborů) ---
    sums = []
    for top in (WEB, PRIV):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f)
                sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    w(os.path.join(OUT, 'SHA256SUMS.txt'), '\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    json.dump({'installer': inst_name, 'until': until, 'built': datetime.datetime.now().isoformat(timespec='seconds')}, open(os.path.join(LOC, 'balicek.json'), 'w'))
    # návod a kontrolní seznam ze šablon – vždy s aktuálním názvem instalátoru a platností kódu
    for src, dst in (('navod-sablona.md', 'NAVOD.md'), ('kontrolni-seznam-sablona.md', 'KONTROLNI-SEZNAM.md')):
        t = open(os.path.join(HERE, src), encoding='utf-8').read().replace('{INSTALATOR}', inst_name).replace('{PLATNOST}', until)
        w(os.path.join(OUT, dst), t)
    print('Balíček: %s\n  instalátor: %s (platí do %s)\n  souborů: %d' % (OUT, inst_name, until, len(sums)))


if __name__ == '__main__':
    main()
