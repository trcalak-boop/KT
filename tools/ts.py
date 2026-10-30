#!/usr/bin/env python3
"""T&S Studio Components – správa knihovny a jejího použití v projektech.

Jen standardní knihovna Pythonu (3.9+). Spouštějte s -I, ať se nenačítají moduly z aktuální složky:

    python3 -I tools/ts.py validate                 # manifesty, zamčené soubory, registr
    python3 -I tools/ts.py validate --origin        # navíc shoda doslovných kopií se zdrojovým projektem
    python3 -I tools/ts.py registry                 # vygeneruje registry/components.json z manifestů
    python3 -I tools/ts.py lock <komponenta>        # přepočítá LOCKED.sha256 (jen při vývoji knihovny)
    python3 -I tools/ts.py test [komponenta…]       # testy v Chromu bez okna, v lokálním PHP a Python testy (tests/*_test.py)
    python3 -I tools/ts.py list                     # přehled komponent a jejich stavu

    python3 -I tools/ts.py add <komponenta> <projekt>      # vloží komponentu (i závislosti) do projektu
    python3 -I tools/ts.py verify <projekt>                # ověří, že jádro v projektu nikdo neupravil
    python3 -I tools/ts.py update <komponenta> <projekt>   # aktualizuje jádro, vzhled a konfiguraci nechá

V projektu: vendor/ts/<komponenta>/ = jádro (neupravovat), ts-theme/<komponenta>/ a ts-config/<komponenta>/
= soubory zákazníka (aktualizace je nikdy nepřepíše), ts-components.lock.json = verze a kontrolní součty.
"""
import datetime, glob, hashlib, json, os, re, shutil, subprocess, sys, time, urllib.parse

LIB = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
COMP = os.path.join(LIB, 'components')
SCHEMA = os.path.join(LIB, 'registry', 'module.schema.json')
SOURCES = os.path.join(LIB, 'tools', 'sources.json')
PHP = os.path.expanduser(os.environ.get('TS_PHP', '~/TS-Studio/tools/php/php'))
LOCKFILE = 'ts-components.lock.json'


def sha(path):
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for b in iter(lambda: f.read(1 << 20), b''):
            h.update(b)
    return h.hexdigest()


def load(path):
    with open(path, encoding='utf-8') as f:
        return json.load(f)


def save(path, data):
    tmp = path + '.tmp'
    with open(tmp, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
        f.write('\n')
    os.replace(tmp, path)


def sources():
    return load(SOURCES) if os.path.exists(SOURCES) else {}


def manifests():
    out = {}
    for p in sorted(glob.glob(os.path.join(COMP, '*', 'module.json'))):
        m = load(p)
        out[m['name']] = (os.path.dirname(p), m)
    return out


# ---------- malý validátor podmnožiny JSON Schema (type, required, enum, pattern, properties, items, maxLength) ----------
TYPES = {'object': dict, 'array': list, 'string': str, 'boolean': bool}


def check(schema, v, path, errs):
    t = schema.get('type')
    if t and not isinstance(v, TYPES[t]):
        errs.append('%s: má být %s' % (path, t)); return
    if 'enum' in schema and v not in schema['enum']:
        errs.append('%s: %r není z %s' % (path, v, schema['enum']))
    if isinstance(v, str):
        if 'pattern' in schema and not re.search(schema['pattern'], v):
            errs.append('%s: %r neodpovídá %s' % (path, v, schema['pattern']))
        if 'maxLength' in schema and len(v) > schema['maxLength']:
            errs.append('%s: delší než %d znaků' % (path, schema['maxLength']))
    if isinstance(v, dict):
        for k in schema.get('required', []):
            if k not in v:
                errs.append('%s: chybí %s' % (path, k))
        props = schema.get('properties', {})
        for k, x in v.items():
            if k in props:
                check(props[k], x, path + '.' + k, errs)
            elif schema.get('additionalProperties') is False:
                errs.append('%s: neznámý klíč %s' % (path, k))
            elif isinstance(schema.get('additionalProperties'), dict):
                check(schema['additionalProperties'], x, path + '.' + k, errs)
    if isinstance(v, list) and 'items' in schema:
        for i, x in enumerate(v):
            check(schema['items'], x, '%s[%d]' % (path, i), errs)


def semver(v):
    return tuple(int(x) for x in v.split('-')[0].split('.'))


def satisfies(have, want):
    """'^1.2.0' = stejná hlavní verze a aspoň 1.2.0; '1.2.0' = přesně; '*' = cokoli."""
    if want == '*':
        return True
    if want.startswith('^'):
        w = semver(want[1:]); h = semver(have)
        return h[0] == w[0] and h >= w
    return have == want


def locked_list(cdir, m):
    files = []
    for pat in m.get('lockedFiles', []):
        hit = sorted(p for p in glob.glob(os.path.join(cdir, pat), recursive=True) if os.path.isfile(p))
        files += [os.path.relpath(p, cdir) for p in hit]
    return sorted(set(files))


def read_locked(cdir):
    p = os.path.join(cdir, 'LOCKED.sha256')
    out = {}
    if os.path.exists(p):
        for line in open(p, encoding='utf-8'):
            if line.strip():
                h, f = line.rstrip('\n').split('  ', 1)
                out[f] = h
    return out


# ---------- příkazy knihovny ----------
def cmd_validate(args):
    schema = load(SCHEMA)
    ms = manifests(); bad = 0
    src = sources()
    for name, (cdir, m) in ms.items():
        errs = []
        check(schema, m, name, errs)
        if os.path.basename(cdir) != name:
            errs.append('složka %s neodpovídá názvu %s' % (os.path.basename(cdir), name))
        for dep, want in m.get('dependencies', {}).items():
            if dep not in ms:
                errs.append('závislost %s neexistuje' % dep)
            elif not satisfies(ms[dep][1]['version'], want):
                errs.append('závislost %s %s nesplňuje %s' % (dep, ms[dep][1]['version'], want))
        if m.get('status') == 'production-ready' and m.get('missingForProduction'):
            errs.append('production-ready, ale missingForProduction není prázdné')
        want = locked_list(cdir, m); have = read_locked(cdir)
        if set(want) != set(have):
            errs.append('LOCKED.sha256 nesedí se seznamem lockedFiles (spusťte ts.py lock %s)' % name)
        for f, h in have.items():
            p = os.path.join(cdir, f)
            if not os.path.exists(p) or sha(p) != h:
                errs.append('zamčený soubor %s se změnil' % f)
        for o in m['origin']['files']:
            to = os.path.join(cdir, o['to'])
            if not os.path.exists(to):
                errs.append('origin: chybí %s' % o['to'])
            elif o['mode'] == 'verbatim' and sha(to) != o['sha256']:
                errs.append('origin: %s není shodný se zdrojem' % o['to'])
            if '--origin' in args and o['mode'] in ('verbatim', 'adapted'):
                root = src.get(m['origin']['project'])
                fp = os.path.join(root, o['from']) if root else None
                if not fp or not os.path.exists(fp):
                    errs.append('origin: zdroj %s není dostupný' % o['from'])
                elif sha(fp) != o['sha256']:
                    errs.append('origin: zdroj %s se od extrakce změnil' % o['from'])
        for k in ('skill', 'api', 'configSchema'):
            if m.get(k) and not os.path.exists(os.path.join(LIB, m[k]) if k != 'configSchema' else os.path.join(cdir, m[k])):
                errs.append('%s: soubor %s neexistuje' % (k, m[k]))
        print(('OK    ' if not errs else 'CHYBA ') + name + ' ' + m.get('version', '?'))
        for e in errs:
            print('      - ' + e)
        bad += bool(errs)
    reg = os.path.join(LIB, 'registry', 'components.json')
    if os.path.exists(reg) and load(reg).get('components') != build_registry(ms)['components']:
        print('CHYBA registry/components.json je zastaralý (spusťte ts.py registry)'); bad += 1
    print('%d komponent, %d s chybou' % (len(ms), bad))
    return 1 if bad else 0


def build_registry(ms):
    comps = []
    for name, (cdir, m) in ms.items():
        comps.append({k: m.get(k) for k in ('name', 'version', 'kind', 'status', 'maturity', 'summary', 'runtime', 'dependencies', 'skill', 'api', 'works', 'missingForProduction')})
        comps[-1]['path'] = os.path.relpath(cdir, LIB)
    return {'library': 'ts-library', 'version': open(os.path.join(LIB, 'VERSION')).read().strip(), 'components': comps}


def cmd_registry(args):
    reg = build_registry(manifests())
    reg['generated'] = datetime.datetime.now().isoformat(timespec='seconds')
    save(os.path.join(LIB, 'registry', 'components.json'), reg)
    print('registry/components.json: %d komponent' % len(reg['components']))
    return 0


def cmd_lock(args):
    ms = manifests()
    for name in args:
        cdir, m = ms[name]
        with open(os.path.join(cdir, 'LOCKED.sha256'), 'w', encoding='utf-8') as f:
            for rel in locked_list(cdir, m):
                f.write('%s  %s\n' % (sha(os.path.join(cdir, rel)), rel))
        print('%s: %d zamčených souborů' % (name, len(locked_list(cdir, m))))
    return 0


def cmd_origin(args):
    """doplní chybějící sha256 v origin: adapted = otisk zdroje v době extrakce, new = otisk vlastního souboru"""
    src = sources()
    for name, (cdir, m) in manifests().items():
        ch = False
        for o in m['origin']['files']:
            if o.get('sha256') and o['sha256'] != '0' * 64:
                continue
            if o['mode'] == 'new':
                o['sha256'] = sha(os.path.join(cdir, o['to']))
            else:
                o['sha256'] = sha(os.path.join(src[m['origin']['project']], o['from']))
            ch = True
        if ch:
            save(os.path.join(cdir, 'module.json'), m); print('origin doplněn: ' + name)
    return 0


def cmd_list(args):
    for name, (cdir, m) in manifests().items():
        print('%-26s %-8s %-17s %-9s %s' % (name, m['version'], m['status'], m['maturity'], m['summary'][:70]))
    return 0


# ---------- testy ----------
def file_url(p):
    return 'file://' + urllib.parse.quote(os.path.abspath(p))


def run_browser_tests(pages, log):
    sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
    from cdp import Browser
    src = sources(); q = {}
    for k, v in src.items():
        q[k] = file_url(v)
    br = Browser(port=9361)
    res = []
    try:
        for page in pages:
            url = file_url(page) + '?' + urllib.parse.urlencode(q)
            br.call('Page.navigate', url=url)
            st = None
            for _ in range(600):
                time.sleep(.1)
                try:
                    st = br.js('window.__TSTEST && window.__TSTEST.done ? JSON.stringify(window.__TSTEST) : null')
                except Exception:
                    st = None
                if st:
                    break
            r = json.loads(st) if st else {'pass': 0, 'fail': 1, 'log': ['časový limit: test nedoběhl']}
            r['file'] = os.path.relpath(page, LIB)
            res.append(r)
            log(r)
    finally:
        br.close()
    return res


def run_php_tests(files, log):
    res = []
    for f in files:
        p = subprocess.run([PHP, '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1', f], capture_output=True, text=True, timeout=300)
        out = p.stdout.strip().splitlines()
        last = out[-1] if out else ''
        m = re.match(r'^RESULT pass=(\d+) fail=(\d+)$', last)
        r = {'file': os.path.relpath(f, LIB), 'pass': int(m.group(1)) if m else 0, 'fail': int(m.group(2)) if m else 1,
             'log': [l for l in out[:-1] if l.startswith('FAIL')] + ([p.stderr.strip()[:2000]] if p.stderr.strip() else []) + ([] if m else ['bez výsledku (kód %d)' % p.returncode])}
        res.append(r)
        log(r)
    return res


def run_py_tests(files, log):
    res = []
    for f in files:
        p = subprocess.run([sys.executable, '-I', '-B', f], capture_output=True, text=True, timeout=1200)
        out = p.stdout.strip().splitlines()
        m = re.match(r'^RESULT pass=(\d+) fail=(\d+)$', out[-1] if out else '')
        r = {'file': os.path.relpath(f, LIB), 'pass': int(m.group(1)) if m else 0, 'fail': int(m.group(2)) if m else 1,
             'log': [l for l in out[:-1] if l.startswith(('FAIL', 'INFO'))] + ([p.stderr.strip()[:2000]] if p.returncode and p.stderr.strip() else [])}
        res.append(r)
        log(r)
    return res


def cmd_test(args):
    names = [a for a in args if not a.startswith('-')] or list(manifests().keys())
    pages, php, py = [], [], []
    for n in names:
        cdir = os.path.join(COMP, n)
        pages += sorted(glob.glob(os.path.join(cdir, 'tests', '*.test.html')))
        php += sorted(glob.glob(os.path.join(cdir, 'tests', '*_test.php')))
        py += sorted(glob.glob(os.path.join(cdir, 'tests', '*_test.py')))

    def log(r):
        print(('OK    ' if not r['fail'] else 'CHYBA ') + '%-62s %4d ok %3d chyb' % (r['file'], r['pass'], r['fail']))
        for l in r.get('log', [])[:25]:
            print('      ' + str(l)[:300])
    res = (run_browser_tests(pages, log) if pages else []) + (run_php_tests(php, log) if php else []) + (run_py_tests(py, log) if py else [])
    tp = sum(r['pass'] for r in res); tf = sum(r['fail'] for r in res)
    print('Celkem %d kontrol, %d chyb' % (tp + tf, tf))
    os.makedirs(os.path.join(LIB, 'reports'), exist_ok=True)
    save(os.path.join(LIB, 'reports', 'testy-posledni.json'), {'at': datetime.datetime.now().isoformat(timespec='seconds'), 'pass': tp, 'fail': tf, 'files': res})
    return 1 if tf or not res else 0


# ---------- použití v projektu ----------
def project_lock(proj):
    p = os.path.join(proj, LOCKFILE)
    return load(p) if os.path.exists(p) else {'lockVersion': 1, 'library': LIB, 'components': {}}


def install(name, proj, lock, ms, seen, update=False):
    if name in seen:
        return
    seen.add(name)
    cdir, m = ms[name]
    for dep, want in m.get('dependencies', {}).items():
        if dep in lock['components'] and satisfies(lock['components'][dep]['version'], want) and not update:
            continue
        install(dep, proj, lock, ms, seen, update)
    dst = os.path.join(proj, 'vendor', 'ts', name)
    if os.path.isdir(dst):
        shutil.rmtree(dst)
    files = locked_list(cdir, m)
    for rel in files + ['module.json', 'README.md', 'CHANGELOG.md']:
        s = os.path.join(cdir, rel)
        if os.path.exists(s):
            os.makedirs(os.path.dirname(os.path.join(dst, rel)), exist_ok=True)
            shutil.copy2(s, os.path.join(dst, rel))
    kept = []
    for pat in m.get('customerFiles', []):
        for s in sorted(glob.glob(os.path.join(cdir, pat), recursive=True)):
            if not os.path.isfile(s):
                continue
            rel = os.path.relpath(s, cdir)
            area = 'ts-config' if rel.startswith('config/') else 'ts-theme'
            d = os.path.join(proj, area, name, rel.split('/', 1)[1] if '/' in rel else rel)
            if os.path.exists(d):
                kept.append(os.path.relpath(d, proj)); continue
            os.makedirs(os.path.dirname(d), exist_ok=True)
            shutil.copy2(s, d)
    lock['components'][name] = {'version': m['version'], 'status': m['status'], 'installedAt': datetime.datetime.now().isoformat(timespec='seconds'),
                                'files': {rel: sha(os.path.join(dst, rel)) for rel in files}}
    print('%s %s → vendor/ts/%s (%d zamčených souborů)%s' % ('aktualizováno' if update else 'vloženo', name + ' ' + m['version'], name, len(files),
                                                           ('; zachováno: ' + ', '.join(kept)) if kept else ''))


def verify(proj, only=None):
    lock = project_lock(proj); bad = []
    for name, c in lock['components'].items():
        if only and name != only:
            continue
        for rel, h in c['files'].items():
            p = os.path.join(proj, 'vendor', 'ts', name, rel)
            if not os.path.exists(p):
                bad.append('%s: chybí %s' % (name, rel))
            elif sha(p) != h:
                bad.append('%s: upravený zamčený soubor %s' % (name, rel))
    return lock, bad


def cmd_add(args):
    name, proj = args[0], os.path.abspath(args[1])
    ms = manifests()
    if name not in ms:
        print('Neznámá komponenta %s' % name); return 1
    if ms[name][1]['status'] != 'production-ready':
        print('Pozor: %s má stav %s – jen pro ukázky a prototypy.' % (name, ms[name][1]['status']))
    os.makedirs(proj, exist_ok=True)
    lock = project_lock(proj)
    install(name, proj, lock, ms, set())
    save(os.path.join(proj, LOCKFILE), lock)
    return 0


def cmd_verify(args):
    proj = os.path.abspath(args[0])
    lock, bad = verify(proj)
    for b in bad:
        print('CHYBA ' + b)
    print('%d komponent, %s' % (len(lock['components']), 'jádro beze změny' if not bad else '%d problémů' % len(bad)))
    return 1 if bad else 0


def cmd_update(args):
    name, proj = args[0], os.path.abspath(args[1])
    ms = manifests()
    lock, bad = verify(proj, name)
    if name not in lock['components']:
        print('%s v projektu není, použijte add' % name); return 1
    if bad:
        for b in bad:
            print('CHYBA ' + b)
        print('Aktualizace zastavena: jádro bylo v projektu upraveno. Úpravy přesuňte do ts-theme/ nebo ts-config/.'); return 1
    old = lock['components'][name]['version']; new = ms[name][1]['version']
    if semver(new)[0] != semver(old)[0] and '--major' not in args:
        mig = os.path.join(ms[name][0], 'MIGRATION.md')
        print('Aktualizace zastavena: %s %s → %s je nová hlavní verze (změna API nebo dat).' % (name, old, new))
        print('Postup: %s, pak znovu s --major.' % (mig if os.path.exists(mig) else 'CHANGELOG.md komponenty (sekce BREAKING)'))
        return 1
    for other, c in lock['components'].items():
        dep = ms.get(other, (None, {}))[1].get('dependencies', {}).get(name)
        if dep and not satisfies(new, dep):
            print('Aktualizace zastavena: %s %s vyžaduje %s %s.' % (other, c['version'], name, dep)); return 1
    install(name, proj, lock, ms, set(), update=True)
    save(os.path.join(proj, LOCKFILE), lock)
    print('%s: %s → %s, změny viz vendor/ts/%s/CHANGELOG.md' % (name, old, ms[name][1]['version'], name))
    return 0


CMDS = {'origin': cmd_origin, 'validate': cmd_validate, 'registry': cmd_registry, 'lock': cmd_lock, 'list': cmd_list, 'test': cmd_test,
        'add': cmd_add, 'verify': cmd_verify, 'update': cmd_update}

if __name__ == '__main__':
    if len(sys.argv) < 2 or sys.argv[1] not in CMDS:
        print(__doc__); sys.exit(2)
    sys.exit(CMDS[sys.argv[1]](sys.argv[2:]))
