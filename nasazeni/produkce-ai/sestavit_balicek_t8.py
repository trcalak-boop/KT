#!/usr/bin/env python3
"""Balíček pro PRODUKCI tasstudio.eu: návrhy webů v CRM místo AI Design Studia (etapa 7.3, plán docs/ai/12-navrhy-v-crm-plan.md).

  balicek-t8/
    k-nahrani/web/             → obsah do /web/            (CRM: blok Návrhy webu; Studio odebrané; knihovna ts-design-studio 0.13.0; zásady v 18 jazycích)
    k-nahrani/sub-nahledy/     → obsah do /sub/nahledy/    (brána: stránka klienta s připomínkami, upozornění e-mailem, interní náhled pro CRM)
    k-nahrani/private-studio/  → studio.php do /private/studio/ (bez modelu AI; úložiště návrhů, příjemci upozornění; tajný klíč doručení beze změny)
    navrat-pri-problemu/, lokalne-NENAHRAVAT/, NAVOD.md, SHA256SUMS.txt

Verze ?v= ve stránkách zásad se nastaví podle živého webu (administrace je mění). Spustit PŘED commitem (bere změny proti HEAD).
Nikdy do balíčku: admin/data, data/, ts-components.lock.json, posta.php, žádný klíč ani heslo.
Použití: python3 -I sestavit_balicek_t8.py"""
import datetime, hashlib, json, os, re, shutil, subprocess, sys, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'; WEB = os.path.join(PROJ, 'web'); PHP = os.path.expanduser('~/TS-Studio/tools/php/php')
OUT = os.path.join(HERE, 'balicek-t8'); KW = os.path.join(OUT, 'k-nahrani', 'web'); KN = os.path.join(OUT, 'k-nahrani', 'sub-nahledy'); KS = os.path.join(OUT, 'k-nahrani', 'private-studio')
LOC = os.path.join(OUT, 'lokalne-NENAHRAVAT'); KVOTA = os.path.join(HERE, 'balicek-kvota', 'private-studio', 'studio.php'); GATE_OLD = os.path.join(HERE, 'balicek-t6', 'k-nahrani', 'sub-nahledy', 'index.php')
ADMIN_OK = ('admin/index.php', 'admin/studio.php', 'admin/studio-api.php', 'admin/crm-api.php', 'admin/crm.js', 'admin/crm.css', 'admin/crm-navrhy.php')
SITE = 'https://www.tasstudio.eu'; PRE = 'vendor/ts/ts-design-studio/'; VER = '0.13.0'

GATE = r'''<?php
/* Brána neveřejných náhledů pro klienty – nahledy.tasstudio.eu (PRODUKCE, data /private/studio).
   Nahraný návrh: stránka klienta s připomínkami a schválením, upozornění nám e-mailem (posta.php), interní náhled pro CRM (podepsaná adresa).
   Návrhy ze šablon: jako dosud. Bez cookies, bez indexace, přísná CSP; kód návrhu běží jen v sandboxu bez přístupu na síť.
   Konfigurace (tajný klíč doručení, SMTP) se načte před zúžením open_basedir; potom PHP smí jen do této složky a /private/studio. */
require __DIR__ . '/vendor/ts/ts-design-studio/core/bootstrap.php';
$private = dirname(__DIR__, 2) . '/private/studio';
$deny = function (int $code) { http_response_code($code); header('Content-Type: text/plain; charset=utf-8'); header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); echo $code === 503 ? 'Náhledy jsou dočasně nedostupné.' : 'Chyba.'; exit; };
if (!is_file($private . '/studio.php')) $deny(503);
$cfg = require $private . '/studio.php';
if (empty($cfg['enabled'])) $deny(503);
$c = $cfg['studio']; unset($c['ai']);                                    // model brána nepotřebuje; e-mail ano (upozornění na připomínky)
try { $studio = ts_design_studio($c); } catch (Throwable $e) { error_log('ts-nahledy: ' . $e->getMessage()); $deny(503); }
@ini_set('display_errors', '0');
if (ini_set('open_basedir', implode(PATH_SEPARATOR, [realpath(__DIR__) . '/', realpath($private) . '/'])) === false) error_log('ts-nahledy: open_basedir nelze zúžit');
(new TsStudioPreviewGate($studio, ['failDir' => $private . '/data/brana', 'contact' => (string) ($cfg['previewContact'] ?? 'tasstudio.eu'), 'adminOrigins' => (array) ($cfg['adminOrigins'] ?? [])]))->serve();
'''


def git(*a): return subprocess.run(['git', '-C', PROJ] + list(a), capture_output=True, text=True, check=True).stdout
def live(rel): return urllib.request.urlopen(urllib.request.Request(SITE + '/' + rel, headers={'User-Agent': 'Mozilla/5.0 ts-overeni'}), timeout=30).read()
def w(p, t, mode=0o644): os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(t); os.chmod(p, mode)


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    for d in (KW, KN, KS): os.makedirs(d)
    os.makedirs(LOC, mode=0o700)
    out = subprocess.run(['git', '-C', PROJ, 'status', '--porcelain', '-z', '--untracked-files=all', '--', 'web/'], capture_output=True, text=True, check=True).stdout
    soubory = []
    for item in filter(None, out.split('\0')):
        st, path = item[:2], item[3:]
        if 'D' in st: sys.exit('Smazaný soubor: ' + path)
        rel = path[len('web/'):]
        if rel == 'ts-components.lock.json': continue
        if rel.startswith('admin/') and rel not in ADMIN_OK: sys.exit('Neočekávaná změna v administraci: ' + rel)
        if not (rel.startswith('admin/') or rel.startswith(PRE) or re.search(r'(privacy|ochrana-osobnich-udaju|datenschutz)\.html$', rel)): sys.exit('Neočekávaná změna: ' + rel)
        soubory.append(rel)
    soubory.sort()
    assert json.load(open(os.path.join(WEB, PRE, 'module.json')))['version'] == VER
    for need in ADMIN_OK + (PRE + 'core/TsStudioUpload.php', PRE + 'core/TsStudioClientPage.php', PRE + 'migrations/004_feedback.sqlite.sql'): assert need in soubory, 'chybí ' + need
    assert not any('/tests/' in r or '/tools/' in r for r in soubory)
    # verze ?v= podle živého webu
    prod = live('cs/index.html').decode()
    dv = re.search(r'/data/reference\.js\?v=([0-9A-Za-z]+)', prod).group(1); av = re.search(r'/assets/css/ts\.css\?v=([0-9A-Za-z]+)', prod).group(1)
    aiv = re.search(r'ts-ai-widget\.js\?v=([0-9.]+)', prod).group(1)
    stranky = 0
    for rel in soubory:
        src = os.path.join(WEB, rel); dst = os.path.join(KW, rel); os.makedirs(os.path.dirname(dst), exist_ok=True)
        if rel.endswith('.html') and not rel.startswith(PRE):
            t = open(src, encoding='utf-8').read()
            t = re.sub(r'(/data/(?:nastaveni|reference)\.js\?v=)[0-9A-Za-z]+', r'\g<1>' + dv, t)
            t = re.sub(r'(/assets/(?:css|js|fonts)/[\w.-]+\.(?:css|js)\?v=)[0-9A-Za-z]+', r'\g<1>' + av, t)
            assert 'ts-ai-widget.js?v=' + aiv in t, rel
            assert t.count('data-ai-studio') == 2 and 'připomínk' in t or rel != 'cs/ochrana-osobnich-udaju.html', rel
            open(dst, 'w', encoding='utf-8').write(t); stranky += 1
        else: shutil.copy2(src, dst)
        if rel.startswith(PRE) and not rel.startswith(PRE + 'public/'):
            d2 = os.path.join(KN, rel); os.makedirs(os.path.dirname(d2), exist_ok=True); shutil.copy2(src, d2)
    assert stranky == 18, stranky
    for rel in ('vendor/ts/ts-mailer-php/core/TsMailer.php', 'vendor/ts/ts-mailer-php/core/.htaccess'):
        if os.path.isfile(os.path.join(WEB, rel)): d2 = os.path.join(KN, rel); os.makedirs(os.path.dirname(d2), exist_ok=True); shutil.copy2(os.path.join(WEB, rel), d2)
    w(os.path.join(KN, 'index.php'), GATE)
    # /private/studio/studio.php: nasazená konfigurace bez modelu + nahrávání a upozornění; tajný klíč doručení beze změny
    c = open(KVOTA, encoding='utf-8').read()
    sec = re.search(r"'deliverySecret' => '([^']+)'", c).group(1); assert len(sec) >= 32
    c = re.sub(r"    'ai' => \[.*?\],\n", '', c, count=1, flags=re.S)
    assert "'ai' =>" not in c and "'previewBase' => 'https://nahledy.tasstudio.eu/'," in c
    c = c.replace("    'previewBase' => 'https://nahledy.tasstudio.eu/',\n", """    'previewBase' => 'https://nahledy.tasstudio.eu/',
    /* návrhy webů v CRM (etapa 7): nahrané soubory, upozornění na připomínky klienta (+ člověk přiřazený k leadu), odkaz do CRM v e-mailu */
    'uploadDir' => $private . '/data/navrhy',
    'feedbackTo' => ['tasstudio@tasstudio.eu'],
    'crmUrl' => 'https://www.tasstudio.eu/admin/#crm/lead',
""")
    c = c.replace("    'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],\n", "    'allowedOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],\n    'adminOrigins' => ['https://www.tasstudio.eu', 'https://tasstudio.eu'],      // interní náhled návrhu smí vložit jen administrace\n")
    c = re.sub(r"/\* =+\n   AI Design Studio – PRODUKCE.*?=+ \*/", """/* ==========================================================================
   Návrhy webů – PRODUKCE: CRM u leadu (tasstudio.eu/admin) + brána nahledy.tasstudio.eu. Neveřejná konfigurace: /private/studio/studio.php
   AI Design Studio je zrušené (7. 11. 2026), model se nepoužívá. Data: databáze a nahrané návrhy v data/.
   Doručení: tajný klíč odvozuje tokeny odkazů klientům – NEMĚNIT (jinak všechny odeslané odkazy přestanou platit).
   E-mail: posta.php (SMTP, heslo vkládá provozovatel). Bez něj se návrh klientovi neodešle a upozornění na připomínky nepřijdou.
   Vypnutí: 'enabled' => false (blok v CRM zmizí, náhledy 503, data zůstanou).
   ========================================================================== */""", c, count=1, flags=re.S)
    assert "'adminOrigins'" in c and "'uploadDir'" in c and sec in c and 'AI Design Studio je zrušené' in c
    w(os.path.join(KS, 'studio.php'), c, 0o600)
    for f in (os.path.join(KS, 'studio.php'), os.path.join(KN, 'index.php'), os.path.join(KW, 'admin', 'crm-navrhy.php'), os.path.join(KW, 'admin', 'crm-api.php')):
        r = subprocess.run([PHP, '-l', f], capture_output=True, text=True)
        if r.returncode: sys.exit(f + ': ' + r.stdout)
    for d, _, fs in os.walk(OUT):
        for f in fs:
            b = open(os.path.join(d, f), 'rb').read()
            assert b'sk-ant-api' not in b and f.split('.')[0] not in ('cloudflare-token', 'cloudflare-ucet', 'posta', 'heslo'), 'citlivý soubor: ' + f

    N = os.path.join(OUT, 'navrat-pri-problemu'); nove = []
    for rel in soubory:
        r = subprocess.run(['git', '-C', PROJ, 'show', 'HEAD:web/' + rel], capture_output=True)
        if r.returncode != 0: nove.append(rel); continue
        p = os.path.join(N, 'web', rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'wb').write(r.stdout)
        if rel.startswith(PRE) and not rel.startswith(PRE + 'public/'): p = os.path.join(N, 'sub-nahledy', rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'wb').write(r.stdout)
    shutil.copy2(GATE_OLD, os.path.join(N, 'sub-nahledy', 'index.php'))
    os.makedirs(os.path.join(N, 'private-studio')); shutil.copy2(KVOTA, os.path.join(N, 'private-studio', 'studio.php'))
    sums = []
    for top in (KW, KN, KS):
        for dp, _, fs in os.walk(top):
            for f in sorted(fs):
                p = os.path.join(dp, f); sums.append('%s  %s' % (hashlib.sha256(open(p, 'rb').read()).hexdigest(), os.path.relpath(p, OUT)))
    open(os.path.join(OUT, 'SHA256SUMS.txt'), 'w').write('\n'.join(sorted(sums, key=lambda x: x[66:])) + '\n')
    open(os.path.join(LOC, 'soubory-web.txt'), 'w').write('\n'.join(soubory) + '\n')
    json.dump({'built': datetime.datetime.now().isoformat(timespec='seconds'), 'git': git('rev-parse', '--short', 'HEAD').strip(), 'web': len(soubory), 'nove': nove, 'data': dv, 'assets': av}, open(os.path.join(LOC, 'balicek.json'), 'w'), ensure_ascii=False)
    nk = sum(len(fs) for _, _, fs in os.walk(KN))
    open(os.path.join(OUT, 'NAVOD.md'), 'w', encoding='utf-8').write('''# Návrhy webů v CRM – nahrání na tasstudio.eu (balíček T8)

Co se změní:
- **CRM:** v detailu leadu blok **Návrhy webu** (nahrání, přehled, odeslání klientovi, připomínky, úkol).
- **AI Design Studio** zmizí z administrace (stará adresa vede do CRM), model AI se už nepoužívá.
- **Náhledy:** klient u nahraného návrhu píše připomínky nebo ho schválí; upozornění chodí e-mailem.
- **Zásady ochrany údajů (18 jazyků):** část Návrhy webů.
Nic se nemaže. **Nikdy nenahrávejte nic do `/web/admin/data`.** Během nahrávání nic neukládejte v administraci.
Ve FileZille zapněte Server → Vynutit zobrazování skrytých souborů.

## 0. Záloha
Stáhněte `/private/studio/studio.php`, `/sub/nahledy/index.php` a z `/web/admin` soubory `index.php`, `studio.php`, `studio-api.php`, `crm-api.php`, `crm.js`, `crm.css`
do `zalohy/produkce-%s/`. Stačí i složka `navrat-pri-problemu/` v balíčku.

## 1. /private/studio
Nahrajte `k-nahrani/private-studio/studio.php` do `/private/studio/` (přepsat). Tajný klíč doručení je stejný, už odeslané odkazy fungují dál.

## 2. Náhledy (%d souborů)
Vlevo `k-nahrani/sub-nahledy/`, vpravo `/sub/nahledy`: označte vše uvnitř (`index.php` a `vendor`), pravé tlačítko › „Přidat soubory do fronty“, cíle začínají `/sub/nahledy/…`, ⌘P, přepsat.

## 3. Web (%d souborů)
Vlevo `k-nahrani/web/`, vpravo `/web`: označte vše uvnitř, do fronty, cíle začínají `/web/…`, ⌘P, přepsat.

## 4. Ověření
Napište mi „nahráno“. Ověřím jen čtením. Pak v CRM u smyšleného leadu s vaším e-mailem nahrajete testovací návrh, pošlete ho sami sobě a napíšete připomínku.

## Návrat při problému
- Okamžitě: v `/private/studio/studio.php` nastavte `'enabled' => false`.
- Úplně: nahrajte `navrat-pri-problemu/web/` do `/web`, `navrat-pri-problemu/sub-nahledy/` do `/sub/nahledy` a `navrat-pri-problemu/private-studio/studio.php` do `/private/studio/`.
  Nové soubory (`crm-navrhy.php` a nové soubory knihovny) mohou zůstat, nic je pak nevolá.
''' % (datetime.date.today().isoformat(), nk, len(soubory)))
    print('balíček:', OUT, '| web:', len(soubory), '| náhledy:', nk, '| data:', dv, '| assets:', av)


main()
