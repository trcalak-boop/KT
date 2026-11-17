#!/usr/bin/env python3
"""Doslovná extrakce souborů ze zdrojového projektu do komponenty knihovny.

    python3 -I tools/extract.py <komponenta> <zdroj-klíč> <z>:<do> [<z>:<do> …]

<z> je cesta ve zdrojovém projektu (tools/sources.json), <do> cesta v komponentě.
Zdroj se jen čte. Soubor se zkopíruje beze změny a do module.json → origin.files se zapíše
cesta, sha256 a režim „verbatim“; commit zdrojového gitu a zda byl soubor proti HEAD změněný.
Existující záznamy se stejným <do> se přepíšou, ostatní zůstanou.
"""
import hashlib, json, os, shutil, subprocess, sys

LIB = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))


def sha(p):
    return hashlib.sha256(open(p, 'rb').read()).hexdigest()


def main(name, key, pairs):
    root = json.load(open(os.path.join(LIB, 'tools', 'sources.json')))[key]
    cdir = os.path.join(LIB, 'components', name)
    mp = os.path.join(cdir, 'module.json')
    m = json.load(open(mp, encoding='utf-8'))
    commit = subprocess.run(['git', '-C', root, 'rev-parse', '--short', 'HEAD'], capture_output=True, text=True).stdout.strip()
    o = m.setdefault('origin', {'project': key, 'commit': commit, 'files': []})
    o['project'], o['commit'] = key, commit
    dirty = []
    for pair in pairs:
        src, dst = pair.split(':', 1)
        sp, dp = os.path.join(root, src), os.path.join(cdir, dst)
        os.makedirs(os.path.dirname(dp), exist_ok=True)
        shutil.copy2(sp, dp)
        if subprocess.run(['git', '-C', root, 'diff', '--quiet', 'HEAD', '--', src]).returncode:
            dirty.append(src)
        o['files'] = [f for f in o['files'] if f['to'] != dst] + [{'from': src, 'to': dst, 'sha256': sha(sp), 'mode': 'verbatim'}]
        print('%s → %s' % (src, dst))
    o['workingTree'] = ('dirty: ' + ', '.join(dirty)) if dirty else 'clean'
    with open(mp, 'w', encoding='utf-8') as f:
        json.dump(m, f, ensure_ascii=False, indent=2); f.write('\n')


if __name__ == '__main__':
    if len(sys.argv) < 4:
        print(__doc__); sys.exit(2)
    main(sys.argv[1], sys.argv[2], sys.argv[3:])
