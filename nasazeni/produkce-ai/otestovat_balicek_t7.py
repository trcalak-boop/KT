#!/usr/bin/env python3
"""Test balíčku T7 na simulovaném hostingu: knihovna 0.6.0 z git HEAD (jako produkce), na ni nahraný balíček, stejná struktura /web, /sub/nahledy, /private/studio.
Falešný model, žádné skutečné služby ani data. Projekt založený starou verzí musí dál fungovat. Brána běží se zúženým open_basedir jako na hostingu.
Poslední řádek: RESULT pass=N fail=M"""
import io, json, os, shutil, subprocess, tarfile, tempfile
HERE = os.path.dirname(os.path.abspath(__file__)); PKG = os.path.join(HERE, 'balicek-t7'); PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PRE = 'vendor/ts/ts-design-studio'
P = F = 0


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:500] if d else ''))


def overlay(src, dst):
    for dp, _, fs in os.walk(src):
        for f in fs:
            p = os.path.join(dp, f); t = os.path.join(dst, os.path.relpath(p, src)); os.makedirs(os.path.dirname(t), exist_ok=True); shutil.copy2(p, t)


root = tempfile.mkdtemp(prefix='t7-'); W = os.path.join(root, 'web'); N = os.path.join(root, 'sub', 'nahledy'); PR = os.path.join(root, 'private', 'studio')
os.makedirs(os.path.join(PR, 'data'))
tar = subprocess.run(['git', '-C', PROJ, 'archive', 'HEAD', 'web/' + PRE, 'web/vendor/ts/ts-mailer-php'], capture_output=True, check=True).stdout
with tarfile.open(fileobj=io.BytesIO(tar)) as t: t.extractall(root)
shutil.copytree(os.path.join(W, 'vendor'), os.path.join(N, 'vendor'), ignore=shutil.ignore_patterns('public'))
open(os.path.join(PR, 'studio.php'), 'w').write(r'''<?php
$plan = function (array $m) {
    $u = $m[1]['content'] ?? ''; $n = strpos($u, 'klidný a čistý') !== false ? 0 : (strpos($u, 'výrazný a sebevědomý') !== false ? 1 : 2);
    return json_encode(['site' => ['brand' => 'Testovací pekárna', 'title' => 'Testovací pekárna', 'email' => 'test@example.cz'], 'tokens' => ['accent' => ['#0d6e74', '#d9480f', '#7a3e9d'][$n], 'f_display' => ['marcellus', 'syne', 'outfit'][$n], 'f_body' => 'figtree'],
        'sections' => [['type' => 'hero', 'id' => 'hero', 'variant' => ['center', 'cover', 'split'][$n], 'content' => ['title' => 'Chléb', 'lead' => 'Z kvasu.']], ['type' => 'contact', 'id' => 'kontakt', 'content' => ['title' => 'Kontakt', 'email' => 'test@example.cz']]]], JSON_UNESCAPED_UNICODE);
};
return ['enabled' => true, 'studio' => ['db' => ['dsn' => 'sqlite:' . __DIR__ . '/data/studio.sqlite', 'autoMigrate' => true], 'timezone' => 'Europe/Prague', 'ai' => ['provider' => 'fake', 'script' => [$plan], 'neuronsPerDay' => 2500],
    'deliverySecret' => str_repeat('t', 40), 'previewBase' => 'https://nahledy.test/']];
''')
BOOT = "require %r . '/core/bootstrap.php'; $cfg = require %r; $S = ts_design_studio($cfg['studio']);\n"
web_boot = BOOT % (os.path.join(W, PRE), os.path.join(PR, 'studio.php'))


def run(code, *args, cwd=None):
    f = os.path.join(root, 'krok.php'); open(f, 'w').write('<?php\n' + web_boot + code)
    r = subprocess.run([PHP, f] + list(args), capture_output=True, text=True, cwd=cwd)
    try: return json.loads(r.stdout.strip().splitlines()[-1])
    except Exception: return {'_err': r.stdout[-600:] + r.stderr[-600:]}


try:
    old = run("$r = $S->createProject(['name' => 'Starý projekt', 'source' => 'new', 'template' => 'salon'], 1); echo json_encode($r + ['v' => json_decode(file_get_contents(%r), true)['version']]);" % os.path.join(W, PRE, 'module.json'))
    ok(old.get('v') == '0.6.0' and old.get('id') == 1, 'výchozí stav: knihovna 0.6.0 a projekt ze šablony', old)
    overlay(os.path.join(PKG, 'k-nahrani', 'web'), W); overlay(os.path.join(PKG, 'k-nahrani', 'sub-nahledy'), N)
    ok(json.load(open(os.path.join(W, PRE, 'module.json')))['version'] == '0.11.0' and json.load(open(os.path.join(N, PRE, 'module.json')))['version'] == '0.11.0', 'po nahrání obě kopie 0.11.0')
    ok(not os.path.exists(os.path.join(N, PRE, 'public', 'ts-studio.js.new')) and os.path.isfile(os.path.join(W, PRE, 'kit', '.htaccess')) and 'denied' in open(os.path.join(N, PRE, 'kit', '.htaccess')).read(), 'stavebnice je za .htaccess v obou kopiích')
    a = run("$v = $S->version(1, 1, 1); $r = $S->renderVersion(1, 1, ['brand' => 'Lokna 2'] + $v['content'], $v['tokens'], 'po aktualizaci'); echo json_encode(['num' => $r['num'], 'chyby' => TsStudioChecks::errors($r['checks']), 'zip' => strlen($S->exportZip(1, $r['num'], 1))]);")
    ok(a.get('num') == 2 and a.get('chyby') == [] and a.get('zip', 0) > 10000, 'starý projekt ze šablony funguje dál', a)
    k = run("$r = $S->kitCreate(['name' => 'Testovací pekárna', 'facts' => 'test@example.cz', 'lang' => 'cs'], 1, ['source' => 'new'], 3); echo json_encode(['id' => $r['id'], 'v' => array_column($r['versions'], 'num'), 'failed' => $r['failed'], 'chyby' => TsStudioChecks::errors($r['checks']), 'versions' => array_column($S->get($r['id'], 1)['versions'], 'status', 'num'), 'prehled' => strlen($S->preview($r['id'], $r['version'], 1)), 'schema' => count($S->kitSchema()['sections']), 'tpl' => count($S->templates())]);")
    ok(k.get('id') == 2 and len(k.get('v', [])) == 3 and k.get('failed') is None and k.get('chyby') == [] and k.get('prehled', 0) > 10000 and k.get('schema') == 21, 'návrh na míru: tři směry, náhled, popis stavebnice', k)
    ok(k.get('tpl') == 6 or k.get('tpl', 0) >= 5, 'šablony v nabídce', k.get('tpl'))
    d = run("$x = $S->setStatus(2, 'approved_internal', 1); $d = $S->prepareDelivery(2, %d, 1, 14); $l = $S->copyLink((int) $d['id'], 1); echo json_encode(['url' => $l['url']]);" % (k['v'][0] if k.get('v') else 0))
    url = d.get('url', ''); token = url.rstrip('/').split('/')[-1]
    ok(len(token) == 32, 'doručení: aktivní odkaz', d)
    gate = os.path.join(N, 'brana-test.php')
    open(gate, 'w').write("<?php\nrequire __DIR__ . '/vendor/ts/ts-design-studio/core/bootstrap.php';\n$private = %r;\n$cfg = require $private . '/studio.php'; $c = $cfg['studio']; unset($c['ai'], $c['mail']); $s = ts_design_studio($c);\nini_set('open_basedir', implode(PATH_SEPARATOR, [realpath(__DIR__) . '/', realpath($private) . '/']));\n$g = new TsStudioPreviewGate($s, ['failDir' => $private . '/data/brana', 'contact' => 'x']);\n$o = [];\nforeach (array_slice($argv, 1) as $p) { [$code, $h, $b] = $g->handle('GET', $p, '10.0.0.1'); $o[$p] = [$code, strlen($b), $h['Content-Type'] ?? '', $h['X-Robots-Tag'] ?? '', mb_scrub(substr($b, 0, 4000), 'UTF-8')]; }\necho json_encode($o);\n" % PR)
    css = run("$f = $S->allFiles(2, %d, 1); echo json_encode(array_keys($f));" % (k['v'][0] if k.get('v') else 0))
    font = [f for f in css if isinstance(f, str) and f.endswith('.woff2')] if isinstance(css, list) else []
    other = [f for f in os.listdir(os.path.join(N, PRE, 'kit', 'fonts')) if f.startswith('syne-')][0]
    paths = ['/%s/' % token, '/%s/assets/site.css' % token, '/%s/%s' % (token, font[0]) if font else '/x', '/%s/kit/kit.json' % token, '/%s/assets/fonts/fonts.json' % token, '/%s/core/bootstrap.php' % token, '/%s/assets/fonts/..%%2f..%%2fkit.json' % token, '/%s/' % ('a' * 32)]
    r = subprocess.run([PHP, gate] + paths, capture_output=True, text=True)
    g = json.loads(r.stdout) if r.stdout.startswith('{') else {}
    ok(g.get(paths[0], [0])[0] == 200 and 'Chléb' in g[paths[0]][4] and 'noindex' in g[paths[0]][3], 'brána se zúženým open_basedir: návrh na míru se otevře, bez indexace', r.stdout[-300:] + r.stderr[-300:])
    ok(g.get(paths[1], [0])[0] == 200 and 'css' in g[paths[1]][2] and '@font-face' in g[paths[1]][4], 'brána: styly', g.get(paths[1], [])[:3])
    ok(g.get(paths[2], [0])[0] == 200 and g[paths[2]][1] > 1000, 'brána: písmo návrhu', g.get(paths[2], [])[:3])
    ok(all(g.get(p, [0])[0] == 404 for p in paths[3:]), 'brána: zdroje stavebnice, kód a cizí token = 404', {p: g.get(p, [0])[0] for p in paths[3:]})
    n = os.path.join(PKG, 'navrat-pri-problemu')
    ok(os.path.isfile(os.path.join(n, 'web', PRE, 'core', 'TsStudioApi.php')) and json.load(open(os.path.join(n, 'web', PRE, 'module.json')))['version'] == '0.6.0' and not os.path.exists(os.path.join(n, 'web', PRE, 'core', 'TsStudioKit.php')), 'návrat: původní verze 0.6.0')
    overlay(os.path.join(n, 'web'), W); overlay(os.path.join(n, 'sub-nahledy'), N)
    z = run("echo json_encode(['stary' => strlen($S->preview(1, 2, 1)) > 5000, 'projekty' => count($S->projects(1))]);")
    ok(z.get('stary') is True and z.get('projekty') == 2, 'po návratu projekt ze šablony funguje a databáze je v pořádku', z)
    r = subprocess.run([PHP, gate, paths[0]], capture_output=True, text=True)
    ok(json.loads(r.stdout).get(paths[0], [0])[0] == 404 if r.stdout.startswith('{') else False, 'po návratu návrh na míru v bráně = 404, ne chyba serveru', r.stdout[:200] + r.stderr[:200])
finally:
    shutil.rmtree(root, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
