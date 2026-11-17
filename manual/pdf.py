#!/usr/bin/env python3
"""Sestaví PDF manuálu z manual.html (Chrome headless, číslování stran)."""
import os, sys, time
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
HERE = os.path.dirname(os.path.abspath(__file__)); out = sys.argv[1]
b = Browser(9395); b.go('file://' + os.path.join(HERE, 'manual.html'), 2.5)
b.js("return Promise.all([...document.images].map(i=>i.complete?1:new Promise(r=>{i.onload=i.onerror=r})))")
foot = '<div style="width:100%;font:8px Helvetica,Arial;color:#888;padding:0 15mm;display:flex;justify-content:space-between"><span>T&amp;S Studio · Manuál webu a administrace</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>'
b.pdf(out, displayHeaderFooter=True, headerTemplate='<div></div>', footerTemplate=foot)
b.close()
