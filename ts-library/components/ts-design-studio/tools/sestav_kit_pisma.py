#!/usr/bin/env python3
"""Stavebnice Studia (etapa 6.7a): katalog písem kit/fonts/fonts.json a soubory písem + licence OFL z hotových šablon.
Z deklarací @font-face v CSS šablon vezme rodinu, řez, váhu, unicode-range a soubor. Opakované spuštění dá stejný výsledek.
    python3 -I tools/sestav_kit_pisma.py"""
import json, os, re, shutil

C = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
OUT = os.path.join(C, 'kit', 'fonts')
# rodina → (kategorie, záložní písma, charakter pro model)
META = {
    'Alegreya': ('serif', 'Georgia,"Times New Roman",serif', 'teplá knižní patka'),
    'Alegreya Sans': ('sans', '"Gill Sans","Trebuchet MS",system-ui,sans-serif', 'humanistické bezpatkové'),
    'DM Serif Display': ('serif', 'Georgia,"Times New Roman",serif', 'kontrastní výrazná patka na nadpisy'),
    'Bodoni Moda': ('serif', 'Didot,Georgia,serif', 'elegantní módní patka'),
    'Marcellus': ('serif', 'Georgia,"Times New Roman",serif', 'klidná antikvová patka na nadpisy'),
    'Sora': ('sans', 'system-ui,-apple-system,"Segoe UI",sans-serif', 'široké geometrické, moderní'),
    'Syne': ('display', 'system-ui,-apple-system,"Segoe UI",sans-serif', 'výrazné experimentální na nadpisy'),
    'Outfit': ('sans', 'system-ui,-apple-system,"Segoe UI",sans-serif', 'přátelské geometrické'),
    'Saira': ('sans', '"Arial Narrow",system-ui,sans-serif', 'technické, sportovní, úzké'),
    'Albert Sans': ('sans', 'system-ui,-apple-system,"Segoe UI",sans-serif', 'neutrální čisté'),
    'Figtree': ('sans', 'system-ui,-apple-system,"Segoe UI",sans-serif', 'přívětivé, dobře čitelné'),
    'Atkinson Hyperlegible Next': ('sans', 'system-ui,-apple-system,"Segoe UI",sans-serif', 'nejlepší čitelnost, přístupnost'),
    'Red Hat Mono': ('mono', 'ui-monospace,Menlo,Consolas,monospace', 'neproporcionální technické'),
    'Azeret Mono': ('mono', 'ui-monospace,Menlo,Consolas,monospace', 'neproporcionální široké'),
    'JetBrains Mono': ('mono', 'ui-monospace,Menlo,Consolas,monospace', 'neproporcionální čitelné'),
}
LIC = {'Alegreya': 'Alegreya-OFL.txt', 'Alegreya Sans': 'Alegreya_Sans-OFL.txt', 'DM Serif Display': 'DM_Serif_Display-OFL.txt', 'Bodoni Moda': 'Bodoni_Moda-OFL.txt',
       'Marcellus': 'Marcellus-OFL.txt', 'Sora': 'Sora-OFL.txt', 'Syne': 'Syne-OFL.txt', 'Outfit': 'Outfit-OFL.txt', 'Saira': 'Saira-OFL.txt', 'Albert Sans': 'Albert_Sans-OFL.txt',
       'Figtree': 'Figtree-OFL.txt', 'Atkinson Hyperlegible Next': 'Atkinson_Hyperlegible_Next-OFL.txt', 'Red Hat Mono': 'Red_Hat_Mono-OFL.txt', 'Azeret Mono': 'Azeret_Mono-OFL.txt', 'JetBrains Mono': 'JetBrains_Mono-OFL.txt'}


def slug(s): return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(OUT)
    fams = {}
    for t in sorted(os.listdir(os.path.join(C, 'templates'))):
        fd = os.path.join(C, 'templates', t, 'files')
        if not os.path.isdir(fd): continue
        for dp, _, fs in os.walk(fd):
            for f in fs:
                if not f.endswith('.css'): continue
                css = open(os.path.join(dp, f), encoding='utf-8').read()
                for m in re.findall(r'@font-face\s*\{(.*?)\}', css, re.S):
                    fam = re.search(r'font-family:\s*["\']?([^"\';]+)', m).group(1).strip()
                    if fam not in META: raise SystemExit('rodina bez popisu: ' + fam)
                    src = re.search(r'url\(["\']?([^"\')]+\.woff2)', m).group(1)
                    path = os.path.normpath(os.path.join(dp, src))
                    style = (re.search(r'font-style:\s*(\w+)', m) or [None, 'normal'])[1]
                    weight = (re.search(r'font-weight:\s*([0-9 ]+)', m) or [None, '400'])[1].strip()
                    ur = re.search(r'unicode-range:\s*([^;]+)', m)
                    name = slug(fam) + '-' + hashlib_short(path) + '.woff2'
                    fams.setdefault(fam, {})[(style, weight, ur.group(1).strip() if ur else '')] = (name, path)
                    lic = os.path.join(os.path.dirname(path), LIC[fam])
                    if os.path.isfile(lic): shutil.copyfile(lic, os.path.join(OUT, LIC[fam]))
    cat = {}
    for fam in sorted(fams):
        kind, stack, note = META[fam]
        faces = []
        for (style, weight, ur), (name, path) in sorted(fams[fam].items()):
            shutil.copyfile(path, os.path.join(OUT, name))
            faces.append({'file': name, 'style': style, 'weight': weight, 'unicodeRange': ur})
        if not os.path.isfile(os.path.join(OUT, LIC[fam])): raise SystemExit('chybí licence: ' + fam)
        cat[slug(fam)] = {'family': fam, 'kind': kind, 'stack': '"%s",%s' % (fam, stack), 'note': note, 'license': LIC[fam], 'faces': faces}
    json.dump(cat, open(os.path.join(OUT, 'fonts.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    size = sum(os.path.getsize(os.path.join(OUT, f)) for f in os.listdir(OUT))
    print('písma: %d rodin, %d řezů, %d kB' % (len(cat), sum(len(x['faces']) for x in cat.values()), size // 1000))
    for k, v in cat.items(): print(' ', k, [(f['style'], f['weight']) for f in v['faces']])


def hashlib_short(p):
    import hashlib
    return hashlib.sha256(open(p, 'rb').read()).hexdigest()[:8]


main()
