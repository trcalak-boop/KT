#!/usr/bin/env python3
"""Průzkum stránky v ukázkovém prostředí: zkus.py URL 'js1' 'js2' … (vypíše výsledky; volitelně TS_SHOT=cesta.jpg)."""
import os, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
import msim
from cdp import Browser
msim.start(); b = None
try:
    b = Browser(9391); b.size(int(os.environ.get('TS_W', 1280)), int(os.environ.get('TS_H', 860)), mobile=os.environ.get('TS_M') == '1'); b.scheme(False)
    b.call('Network.enable'); b.call('Network.setCookie', name='tsadmin', value=msim.SID[1], url=msim.BASE, path='/admin/')
    b.go(msim.BASE + sys.argv[1], 2.5)
    for c in sys.argv[2:]:
        if c.startswith('sleep:'): time.sleep(float(c[6:])); continue
        print('>>', str(b.js(c))[:3000])
    if os.environ.get('TS_SHOT'): b.shot(os.environ['TS_SHOT'])
finally:
    if b: b.close()
    msim.stop()
