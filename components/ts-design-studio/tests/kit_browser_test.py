#!/usr/bin/env python3
"""Stavebnice sekcí v Chromu (bez sítě): každá varianta každé sekce ve všech tónech a čtyřech vizuálních stylech, světlý i tmavý režim, počítač i telefon.
Kontroluje vodorovný posuvník, přetékající prvky, skutečný kontrast textu na výsledném pozadí, načtení písem, přepínač režimu a menu na telefonu.
Poslední řádek: RESULT pass=N fail=M"""
import json, os, shutil, subprocess, sys, tempfile
HERE = os.path.dirname(os.path.abspath(__file__)); COMP = os.path.abspath(os.path.join(HERE, '..')); LIB = os.path.abspath(os.path.join(COMP, '..', '..'))
sys.path.insert(0, os.path.join(LIB, 'tools', 'lib'))
from cdp import Browser
PHP = os.path.expanduser('~/TS-Studio/tools/php/php')
SHOTS = os.environ.get('TS_KIT_SHOTS', '')
P = F = 0


def ok(c, msg, detail=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + msg + (' | ' + str(detail)[:700] if detail else ''))


RENDER = r'''<?php
require %r . '/core/bootstrap.php';
$k = new TsStudioKit(); $out = $argv[1]; $preset = $argv[2]; $shift = (int) $argv[3];
$P = ['zaklad' => [],
 'tmavy' => ['bg'=>'#1a1612','surface'=>'#26201a','ink'=>'#efe8dc','muted'=>'#b8ab98','accent'=>'#d49a3a','on_accent'=>'#1a1612','line'=>'#40362b','band'=>'#5b1a2a','on_band'=>'#f5e9ea','f_display'=>'alegreya','f_body'=>'alegreya-sans','radius'=>'0','h_scale'=>'l','deco'=>'rules','eyebrow'=>'plain','h_weight'=>'800','space'=>'airy'],
 'hrave' => ['bg'=>'#fff8ea','surface'=>'#ffe9b8','ink'=>'#16324f','muted'=>'#46607a','accent'=>'#e4572e','on_accent'=>'#ffffff','line'=>'#f0d79a','band'=>'#16324f','on_band'=>'#fff8ea','f_display'=>'syne','f_body'=>'outfit','radius'=>'20','btn'=>'pill','h_case'=>'upper','h_scale'=>'xl','h_weight'=>'800','deco'=>'dots','eyebrow'=>'bar','width'=>'wide'],
 'cisty' => ['bg'=>'#ffffff','surface'=>'#eef4f6','ink'=>'#0e2a35','muted'=>'#46606b','accent'=>'#0d6e74','on_accent'=>'#ffffff','line'=>'#cfdde2','band'=>'#0e2a35','on_band'=>'#f1f8fa','f_display'=>'atkinson-hyperlegible-next','f_body'=>'atkinson-hyperlegible-next','radius'=>'4','btn'=>'outline','h_scale'=>'s','space'=>'compact','width'=>'narrow','deco'=>'rules','header'=>'center']];
$tones = ['base', 'surface', 'band', 'accent']; $secs = []; $i = $shift;
foreach ($k->sections() as $t => $d) foreach (array_keys($d['variants']) as $v) { $s = $k->sampleSection($t, $v, $tones[$i++ %% 4]); $s['nav'] = $v === $d['default'] && $i %% 5 === 0 ? mb_substr($d['label'], 0, 20) : ''; $secs[] = $s; }
$rc = new ReflectionProperty($k, 'kit'); $kit = $rc->getValue($k); $kit['maxSections'] = 200; $rc->setValue($k, $kit);
$r = $k->render(['site' => ['lang'=>'cs','brand'=>'Pekárna Veveří','tagline'=>'Chléb z kvasu','title'=>'Pekárna Veveří – chléb z kvasu','description'=>'Ukázka stavebnice','phone'=>'+420 600 000 000','email'=>'pekarna@example.cz','address'=>'Veveří 12, Brno','legal'=>'IČO 00000000','cta_label'=>'Objednat','cta_url'=>'#kontakt','header'=>$preset === 'cisty' ? 'center' : 'bar','footer'=>$preset === 'hrave' ? 'simple' : 'columns'], 'tokens' => $P[$preset], 'sections' => $secs]);
mkdir("$out/assets/fonts", 0777, true);
foreach ($r['files'] as $f => $c) file_put_contents("$out/$f", $c);
foreach ($r['binary'] as $f => $src) copy($src, "$out/$f");
echo json_encode(['errors' => $r['errors'], 'fixed' => $r['fixed'], 'n' => count($r['plan']['sections'])]);
''' % COMP

MEASURE = r'''
const parse = s => { let m = s.match(/rgba?\(([^)]+)\)/); if (m) { const a = m[1].split(/[ ,\/]+/).map(Number); return [a[0], a[1], a[2], a[3] === undefined ? 1 : a[3]]; }
  m = s.match(/color\(srgb ([^)]+)\)/); if (m) { const a = m[1].split(/[ \/]+/).map(Number); return [a[0]*255, a[1]*255, a[2]*255, a[3] === undefined ? 1 : a[3]]; } return null; };
const over = (f, b) => { const a = f[3]; return [f[0]*a+b[0]*(1-a), f[1]*a+b[1]*(1-a), f[2]*a+b[2]*(1-a), 1]; };
const lum = c => { const x = c.slice(0,3).map(v => { v /= 255; return v <= .03928 ? v/12.92 : Math.pow((v+.055)/1.055, 2.4); }); return .2126*x[0]+.7152*x[1]+.0722*x[2]; };
const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x,y)+.05)/(Math.min(x,y)+.05); };
const bgOf = el => { const st = []; for (let e = el; e; e = e.parentElement) { const c = parse(getComputedStyle(e).backgroundColor); if (c && c[3] > 0) { st.push(c); if (c[3] === 1) break; } } let b = [255,255,255,1]; for (const c of st.reverse()) b = over(c, b); return b; };
const out = { w: document.documentElement.scrollWidth, iw: innerWidth, over: [], low: [], fonts: 0, n: 0 };
const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
const seen = new Set();
for (let n; (n = walker.nextNode());) {
  if (!n.nodeValue.trim()) continue; const el = n.parentElement; if (!el || seen.has(el) || el.closest('.skip,script,style')) continue; seen.add(el);
  const r = el.getBoundingClientRect(); const st = getComputedStyle(el); if (!r.width || st.visibility === 'hidden' || st.display === 'none') continue;
  out.n++;
  let col = parse(st.color); if (!col) continue; const bg = bgOf(el); col = over(col, bg);
  const px = parseFloat(st.fontSize), large = px >= 24 || (px >= 18.66 && parseInt(st.fontWeight) >= 700), c = ratio(col, bg);
  if (c < (large ? 3 : 4.5)) out.low.push([(el.closest('section')||{}).id || el.tagName, el.className || el.tagName, Math.round(c*100)/100, n.nodeValue.trim().slice(0,24)]);
}
for (const el of document.querySelectorAll('main *, header *, footer *')) { if (el.closest('svg')) continue; const r = el.getBoundingClientRect(); if (r.width && (r.right > innerWidth + 1 || r.left < -1)) { out.over.push([(el.closest('section')||{}).id, el.tagName + '.' + (el.className||''), Math.round(r.left), Math.round(r.right)]); } }
out.broken = [];
for (const el of document.querySelectorAll('h1,h2,h3,.disp')) { const t = el.firstChild; if (!el.offsetParent) continue;
  const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
  for (let n; (n = w.nextNode());) { const re = /[^\s]+/g; let m; while ((m = re.exec(n.nodeValue))) { const r = document.createRange(); r.setStart(n, m.index); r.setEnd(n, m.index + m[0].length); const ys = new Set([...r.getClientRects()].filter(x => x.width > 1).map(x => Math.round(x.top))); if (ys.size > 1) out.broken.push([(el.closest('section')||{}).id, m[0]]); } } }
out.broken = out.broken.slice(0, 10);
out.over = out.over.slice(0, 12); out.low = out.low.slice(0, 16);
for (const f of document.fonts) if (f.status === 'loaded') out.fonts++;
return out;
'''

tmp = tempfile.mkdtemp(prefix='tskit-')
try:
    br = Browser(9371)
    try:
        for idx, preset in enumerate(['zaklad', 'tmavy', 'hrave', 'cisty']):
            res = {}
            for shift in (0, 1, 2, 3):            # každá varianta postupně ve všech čtyřech tónech
                d = os.path.join(tmp, '%s-%d' % (preset, shift)); script = os.path.join(tmp, 'r.php'); open(script, 'w').write(RENDER)
                o = subprocess.run([PHP, script, d, preset, str(shift)], capture_output=True, text=True).stdout
                j = json.loads(o); ok(j['errors'] == [] and j['n'] >= 40, '%s: plán bez chyb' % preset, j)
                for dark in (False, True):
                    for width in (1280, 390):
                        name = '%s t%d %s %d' % (preset, shift, 'tmavý' if dark else 'světlý', width)
                        br.size(width, 900, 2 if width < 500 else 1, width < 500); br.scheme(dark); br.go('file://' + d + '/index.html', wait=.5)
                        m = br.js(MEASURE)
                        ok(m['w'] <= m['iw'], name + ': bez vodorovného posuvníku', m['over'] or m['w'])
                        ok(not m['over'], name + ': nic nepřetéká', m['over'])
                        ok(not m['low'], name + ': kontrast textu', m['low'])
                        ok(not m['broken'], name + ': nadpisy bez rozděleného slova', m['broken'])
                        ok(m['fonts'] >= 1 and m['n'] > 150, name + ': písma a text', (m['fonts'], m['n']))
                        if SHOTS and shift == 0: br.shot(os.path.join(SHOTS, '%s-%s-%d.jpg' % (preset, 'd' if dark else 'l', width)), full=True, quality=45)
        # celé weby z plánu (škola, e-shop)
        PLAN = """<?php
require %r . '/core/bootstrap.php';
$r = (new TsStudioKit())->render(json_decode(file_get_contents($argv[1]), true));
mkdir("$argv[2]/assets/fonts", 0777, true);
foreach ($r['files'] as $f => $c) file_put_contents("$argv[2]/$f", $c);
foreach ($r['binary'] as $f => $src) copy($src, "$argv[2]/$f");
""" % COMP
        open(os.path.join(tmp, 'p.php'), 'w').write(PLAN)
        for n in ('skola', 'eshop'):
            d = os.path.join(tmp, 'plan-' + n); subprocess.run([PHP, os.path.join(tmp, 'p.php'), os.path.join(HERE, 'fixtures', 'plan-%s.json' % n), d], check=True)
            for dark in (False, True):
                for width in (1280, 768, 390):
                    name = 'plán %s %s %d' % (n, 'tmavý' if dark else 'světlý', width)
                    br.size(width, 900, 1, width < 500); br.scheme(dark); br.go('file://' + d + '/index.html', wait=.5)
                    m = br.js(MEASURE)
                    ok(m['w'] <= m['iw'] and not m['over'], name + ': nic nepřetéká', m['over'] or m['w'])
                    ok(not m['low'], name + ': kontrast textu', m['low'])
                    ok(not m['broken'], name + ': nadpisy bez rozděleného slova', m['broken'])
        # přepínač režimu a menu na telefonu
        d = os.path.join(tmp, 'zaklad-0')
        br.size(390, 844, 2, True); br.scheme(False); br.go('file://' + d + '/index.html', wait=.4)
        ok(br.js("return !document.querySelector('.hd-nav').classList.contains('open') && getComputedStyle(document.querySelector('.hd-nav')).display === 'none'"), 'telefon: menu zavřené')
        br.js("document.querySelector('.hd-menu').click()", wait=.1)
        ok(br.js("return getComputedStyle(document.querySelector('.hd-nav')).display !== 'none' && document.querySelector('.hd-menu').getAttribute('aria-expanded') === 'true'"), 'telefon: menu se otevře')
        before = br.js("return getComputedStyle(document.body).backgroundColor")
        br.js("document.querySelector('.hd-theme').click()", wait=.1)
        ok(br.js("return document.documentElement.getAttribute('data-theme')") == 'dark' and br.js("return getComputedStyle(document.body).backgroundColor") != before, 'přepínač režimu změní barvy')
        ok(br.js("return [...document.querySelectorAll('button,a.btn,summary,.hd-nav.open a')].filter(e => e.offsetParent && e.getBoundingClientRect().height < 40).length") == 0, 'telefon: ovládací prvky alespoň 40 px')
    finally:
        br.close()
finally:
    shutil.rmtree(tmp, ignore_errors=True)
print('RESULT pass=%d fail=%d' % (P, F))
