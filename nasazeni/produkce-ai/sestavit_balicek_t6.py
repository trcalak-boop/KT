#!/usr/bin/env python3
"""Doplňkový balíček pro PRODUKCI tasstudio.eu (po T5): AI Design Studio v administraci, náhledy klientům na nahledy.tasstudio.eu, zásady.

  balicek-t6/
    k-nahrani/web/             → obsah nahrát do /web/   (změněné a nové soubory proti produkci = git HEAD: admin/index.php, admin/studio*.php, vendor/ts/ts-design-studio, stránky zásad)
    k-nahrani/private-studio/  → složku nahrát jako /private/studio/   (konfigurace Studia; SMTP posta.php vytváří provozovatel podle posta.example.php)
    k-nahrani/sub-nahledy/     → obsah nahrát do /sub/nahledy/   (brána náhledů přepnutá na produkční data /private/studio; přepíše staging verzi)
    navrat-pri-problemu/, lokalne-NENAHRAVAT/, NAVOD.md, SHA256SUMS.txt

Nikdy do balíčku: admin/data, data/, ts-components.lock.json, posta.php, žádný klíč, token ani heslo (tajný klíč doručení se generuje sem – je to klíč Studia, ne přístup ke službě).
Použití: python3 -I sestavit_balicek_t6.py"""
import datetime, hashlib, json, os, re, secrets, shutil, subprocess, sys, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web'); PHP = os.path.expanduser('~/TS-Studio/tools/php/php')
STG = os.path.expanduser('~/TS-Studio/nasazeni/test-tasstudio/7-balicek-studio/oprava-6.4/nahledy')
OUT = os.path.join(HERE, 'balicek-t6')
KW = os.path.join(OUT, 'k-nahrani', 'web'); KS = os.path.join(OUT, 'k-nahrani', 'private-studio'); KN = os.path.join(OUT, 'k-nahrani', 'sub-nahledy'); LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT')
ADMIN_OK = ('admin/index.php', 'admin/studio.php', 'admin/studio-api.php')
SITE = 'https://www.tasstudio.eu'
DENY = '# Neveřejné – z internetu nepřístupné (Apache).\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n'


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout
def live(rel): return urllib.request.urlopen(urllib.request.Request(SITE + '/' + rel, headers={'User-Agent': 'ts-overeni'}), timeout=30).read()


def w(p, t, mode=0o644):
    os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(t); os.chmod(p, mode)


GATE = r'''<?php
/* Brána neveřejných náhledů pro klienty – nahledy.tasstudio.eu (PRODUKCE, data /private/studio).
   Vydá jen soubory aktivního doručení (odeslaného nebo ručně předaného, neprošlého, neodvolaného). Bez cookies, bez indexace, přísná CSP.
   Konfigurace (tajný klíč doručení) se načte před zúžením open_basedir; potom PHP smí jen do této složky a /private/studio. */
require __DIR__ . '/vendor/ts/ts-design-studio/core/bootstrap.php';
$private = dirname(__DIR__, 2) . '/private/studio';
$deny = function (int $code) { http_response_code($code); header('Content-Type: text/plain; charset=utf-8'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); echo $code === 503 ? 'Náhledy jsou dočasně nedostupné.' : 'Nenalezeno.'; exit; };
if (!is_file($private . '/studio.php')) $deny(503);
$cfg = require $private . '/studio.php';
if (empty($cfg['enabled'])) $deny(503);
$c = $cfg['studio']; unset($c['ai'], $c['mail']);                      // brána nepotřebuje model ani e-mail
try { $studio = ts_design_studio($c); } catch (Throwable $e) { error_log('ts-nahledy: ' . $e->getMessage()); $deny(503); }
@ini_set('display_errors', '0');
if (ini_set('open_basedir', implode(PATH_SEPARATOR, [realpath(__DIR__) . '/', realpath($private) . '/'])) === false) error_log('ts-nahledy: open_basedir nelze zúžit');
(new TsStudioPreviewGate($studio, ['failDir' => $private . '/data/brana', 'contact' => (string) ($cfg['previewContact'] ?? 'tasstudio.eu')]))->serve();
'''

POSTA = '''<?php
/* ŠABLONA – e-mail s návrhem webu klientovi (PRODUKCE). Zkopírujte jako posta.php (ve stejné složce /private/studio) a doplňte heslo SMTP.
   Stejná schránka jako na stagingu, ale BEZ redirectTo – e-maily odcházejí skutečným příjemcům (vždy až po samostatném potvrzení v administraci).
   Dokud posta.php neexistuje, Studio odeslání odmítne („odesílání e-mailů není nastavené“); ruční předání odkazu funguje. Do gitu ani balíčků nepatří. */
return ['transport' => 'smtp', 'smtp_host' => 'smtp.websupport.cz', 'smtp_port' => 587, 'smtp_secure' => 'tls',
    'smtp_user' => '…SCHRÁNKA…', 'smtp_pass' => '…HESLO…',
    'from' => '…SCHRÁNKA…', 'fromName' => 'T&S Studio', 'domain' => 'tasstudio.eu'];
'''


NAVOD = '''# Produkce tasstudio.eu – AI Design Studio v administraci, náhledy klientům (balíček T6)

Co se změní:
- **Administrace:** nová stránka **AI Design Studio** (`/admin/studio.php`, odkaz „Studio“ v hlavičce a v mobilním menu) – jen po přihlášení, role z CRM (oba admin).
  Návrhy webů ze šablon, AI úpravy (Cloudflare, strop 2 000 neuronů/den), verze, schválení a doručení klientovi s potvrzením.
- **Náhledy klientům:** `nahledy.tasstudio.eu` se přepne na produkční data. Testovací odkazy ze stagingu přestanou platit.
- **Zásady ochrany údajů (18 jazyků):** návrhy webů, neveřejné odkazy, uchování 24 měsíců, texty zadání u modelu Cloudflare.
Web, CRM, rezervace, Tessa ani audit se nemění. Do `admin/data` se nic nenahrává.

**Během nahrávání nic neukládejte v administraci webu.** Ve FileZille zapněte Server → Vynutit zobrazování skrytých souborů (`.htaccess`).

## 1. Web (__N__ souborů)
`~/TS-Studio/nasazeni/produkce-ai/balicek-t6/k-nahrani/web/` → obsah do `/web/` (Přepsat, vždy). Z `admin/` jen `index.php`, `studio.php`, `studio-api.php`.

## 2. /private/studio (nová složka)
`…/balicek-t6/k-nahrani/private-studio/` → nahrát jako **`/private/studio/`** (`studio.php`, `posta.example.php`, `.htaccess`, `data/.htaccess`).

## 3. Náhledy
`…/balicek-t6/k-nahrani/sub-nahledy/` → obsah do **`/sub/nahledy/`** (Přepsat; `index.php` teď čte `/private/studio`).

## 4. E-mail (provozovatel)
Na serveru zkopírujte `/private/studio/posta.example.php` jako **`posta.php`** a vyplňte stejnou schránku a heslo jako na stagingu – **bez řádku `redirectTo`**.
Bez `posta.php` Studio e-mail neodešle (ruční předání odkazu funguje).

## Návrat při problému
- Okamžitě: v `/private/studio/studio.php` nastavte `'enabled' => false` (Studio i náhledy se vypnou, data zůstanou).
- Úplně: `navrat-pri-problemu/web/` → `/web/` (původní `admin/index.php` a zásady), `navrat-pri-problemu/sub-nahledy/index.php` → `/sub/nahledy/` (staging verze).
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    for d in (KW, KS, KN): os.makedirs(d)
    os.makedirs(LOC, mode=0o700)
    soubory = []
    for line in git('status', '--porcelain', '--untracked-files=all', '--', 'web/').splitlines():
        st, path = line[:2], line[3:].strip().strip('"')
        if 'D' in st: sys.exit('Smazaný soubor ve webu (balíček umí jen přidat/přepsat): ' + path)
        rel = path[len('web/'):]
        if rel.startswith('data/') or rel == 'ts-components.lock.json': continue
        if rel.startswith('admin/') and rel not in ADMIN_OK: sys.exit('Neočekávaná změna v administraci: ' + rel)
        if not (rel.startswith('admin/') or rel.startswith('vendor/ts/ts-design-studio/') or re.search(r'(privacy|ochrana-osobnich-udaju|datenschutz)\.html$', rel)): sys.exit('Neočekávaná změna: ' + rel)
        soubory.append(rel)
    soubory.sort()
    v = {k: re.search(pat, live('cs/index.html').decode()).group(1) for k, pat in (('data', r'/data/reference\.js\?v=([0-9A-Za-z]+)'), ('assets', r'/assets/css/ts\.css\?v=([0-9A-Za-z]+)'))}
    sv = json.load(open(os.path.join(WEB, 'vendor', 'ts', 'ts-design-studio', 'module.json')))['version']
    privacy = 0
    for rel in soubory:
        if rel.endswith('.html') and not rel.startswith('vendor/'):
            t = open(os.path.join(WEB, rel), encoding='utf-8').read()
            for k, pat in (('data', r'/data/(?:nastaveni|reference)\.js\?v=([0-9A-Za-z]+)'), ('assets', r'/assets/css/ts\.css\?v=([0-9A-Za-z]+)')):
                for m in re.findall(pat, t):
                    if m != v[k]: sys.exit('%s: verze %s %s ≠ produkce %s (spusťte build a srovnejte verze)' % (rel, k, m, v[k]))
            assert t.count('data-ai-studio') == 3 and t.count('data-ai-audit') == 4, rel
            privacy += 1
        os.makedirs(os.path.dirname(os.path.join(KW, rel)), exist_ok=True); shutil.copy2(os.path.join(WEB, rel), os.path.join(KW, rel))
    assert privacy == 18, 'stránek zásad: %d' % privacy
    for need in ADMIN_OK + ('vendor/ts/ts-design-studio/core/TsStudioApi.php', 'vendor/ts/ts-design-studio/public/ts-studio.js', 'vendor/ts/ts-design-studio/core/.htaccess', 'vendor/ts/ts-design-studio/templates/.htaccess'):
        assert need in soubory, 'chybí ' + need
    assert not any('/tests/' in r or '/tools/' in r for r in soubory)

    # /private/studio: konfigurace
    w(os.path.join(KS, 'studio.php'), '''<?php
/* ==========================================================================
   AI Design Studio – PRODUKCE (tasstudio.eu/admin/studio.php + brána nahledy.tasstudio.eu). Neveřejná konfigurace: /private/studio/studio.php
   Model: Cloudflare Workers AI s tokenem asistentky (/private/ai) – sdílí bezplatnou denní kvótu 10 000 neuronů s Tessou a auditem (Studio max. 2 000).
   Doručení: tajný klíč odvozuje tokeny odkazů klientům – NEMĚNIT (jinak všechny odeslané odkazy přestanou platit).
   E-mail: posta.php (SMTP, heslo vkládá provozovatel; šablona posta.example.php). Bez něj Studio e-mail neodešle, ruční předání odkazu funguje.
   Vypnutí: 'enabled' => false (administrace ukáže „vypnuto“, náhledy 503, data zůstanou).
   ========================================================================== */
$private = __DIR__; $ai = dirname(__DIR__) . '/ai';
$studio = [
    'db' => ['dsn' => 'sqlite:' . $private . '/data/studio.sqlite', 'autoMigrate' => true],
    'timezone' => 'Europe/Prague',
    'ai' => ['provider' => 'cloudflare', 'apiKeyFile' => $ai . '/cloudflare-token.txt', 'accountIdFile' => $ai . '/cloudflare-ucet.txt',
             'model' => '@cf/google/gemma-4-26b-a4b-it', 'maxTokens' => 4500, 'timeout' => 150, 'neuronsPerDay' => 2000],
    'deliverySecret' => '%s',
    'previewBase' => 'https://nahledy.tasstudio.eu/',
    'retentionMonths' => 24,
];
if (is_file($private . '/posta.php')) $studio['mail'] = require $private . '/posta.php';
return [
    'enabled' => true,
    'studio' => $studio,
    'previewContact' => 'tasstudio.eu',
    'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],
];
''' % secrets.token_urlsafe(36), 0o600)
    w(os.path.join(KS, 'posta.example.php'), POSTA, 0o600)
    w(os.path.join(KS, '.htaccess'), DENY); w(os.path.join(KS, 'data', '.htaccess'), DENY)
    # /sub/nahledy: brána na produkčních datech + vlastní kopie knihovny (bez rozhraní)
    shutil.copytree(os.path.join(WEB, 'vendor', 'ts', 'ts-design-studio'), os.path.join(KN, 'vendor', 'ts', 'ts-design-studio'), ignore=shutil.ignore_patterns('public'))
    w(os.path.join(KN, 'index.php'), GATE); shutil.copy2(os.path.join(STG, '.htaccess'), os.path.join(KN, '.htaccess')); w(os.path.join(KN, 'vendor', '.htaccess'), DENY)
    for f in (os.path.join(KS, 'studio.php'), os.path.join(KS, 'posta.example.php'), os.path.join(KN, 'index.php'), os.path.join(KW, 'admin', 'studio.php'), os.path.join(KW, 'admin', 'studio-api.php'), os.path.join(KW, 'admin', 'index.php')):
        r = subprocess.run([PHP, '-l', f], capture_output=True, text=True)
        if r.returncode: sys.exit(f + ': ' + r.stdout)
    for d, _, fs in os.walk(OUT):
        for f in fs:
            b = open(os.path.join(d, f), 'rb').read()
            assert b'sk-ant-api' not in b and f not in ('posta.php', 'cloudflare-token.txt', 'cloudflare-ucet.txt', 'elevenlabs-klic.txt', 'google-api-klic.txt'), 'citlivý soubor v balíčku: ' + f

    # návrat: původní soubory z HEAD (nové soubory při návratu nevadí, nikdo je nevolá) + brána stagingu
    N = os.path.join(OUT, 'navrat-pri-problemu')
    for rel in soubory:
        r = subprocess.run(['git', '-C', PROJ, 'show', 'HEAD:web/' + rel], capture_output=True)
        if r.returncode == 0: os.makedirs(os.path.dirname(os.path.join(N, 'web', rel)), exist_ok=True); open(os.path.join(N, 'web', rel), 'wb').write(r.stdout)
    os.makedirs(os.path.join(N, 'sub-nahledy')); shutil.copy2(os.path.join(STG, 'index.php'), os.path.join(N, 'sub-nahledy', 'index.php'))

    sums = []
    for top in (KW, KS, KN):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    open(os.path.join(OUT, 'SHA256SUMS.txt'), 'w').write('\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    open(os.path.join(LOC, 'soubory-web.txt'), 'w').write('\n'.join(soubory) + '\n')
    json.dump({'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory), 'studio': sv, 'verze': v}, open(os.path.join(LOC, 'balicek.json'), 'w'))
    w(os.path.join(OUT, 'NAVOD.md'), NAVOD.replace('__N__', str(len(soubory))))
    n = lambda root: sum(len(fs) for _, _, fs in os.walk(root))
    print('Balíček: %s\n  web: %d souborů (Studio %s, zásady 18), /private/studio: %d, /sub/nahledy: %d' % (OUT, len(soubory), sv, n(KS), n(KN)))


if __name__ == '__main__':
    main()
