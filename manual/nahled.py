#!/usr/bin/env python3
"""Kontaktní arch snímků (jen pro kontrolu): nahled.py výstup.jpg vzor1 vzor2 … (vzor = začátek názvu v obr/)."""
import os, sys
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
OBR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'obr')
out, pats = sys.argv[1], sys.argv[2:]
fs = sorted(f for f in os.listdir(OBR) if f.endswith('.jpg') and any(f.startswith(p) for p in pats))
html = '<html><body style="margin:0;background:#888;font:14px sans-serif;display:grid;grid-template-columns:repeat(3,1fr);gap:6px;padding:6px">' + ''.join(
    '<figure style="margin:0;background:#fff"><img src="file://%s/%s" style="width:100%%;display:block;max-height:520px;object-fit:contain;object-position:top"><figcaption>%s</figcaption></figure>' % (OBR, f, f) for f in fs) + '</body></html>'
p = os.path.join(OBR, '..', '_arch.html'); open(p, 'w').write(html)
b = Browser(9399); b.size(1500, 900); b.go('file://' + os.path.abspath(p), 1.5); b.shot(out, full=True, quality=70); b.close()
