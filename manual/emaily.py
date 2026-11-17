#!/usr/bin/env python3
"""Ukázky e-mailů pro manuál: zachycené zprávy z ukázkového prostředí vykreslí jako obrázky (obr/e-*.jpg) s hlavičkou Od/Komu/Předmět."""
import email, email.header, html, os, re, subprocess, sys
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
HERE = os.path.dirname(os.path.abspath(__file__)); OBR = os.path.join(HERE, 'obr'); SIM = os.path.join(HERE, 'sim')


def dec(s): return str(email.header.make_header(email.header.decode_header(s or '')))


msgs = []
for f in sorted(os.listdir(os.path.join(SIM, 'posta')), key=lambda f: os.path.getmtime(os.path.join(SIM, 'posta', f))):
    msgs.append(email.message_from_bytes(open(os.path.join(SIM, 'posta', f), 'rb').read()))
raw = open(os.path.join(SIM, 'private', 'studio', 'data', 'posta.log'), newline='', encoding='utf-8', errors='replace').read()
for m in re.split(r'(?<=--)\r\n(?:\r\n)*(?=[A-Za-z-]+: )', raw):
    if m.strip() and 'Subject:' in m: msgs.append(email.message_from_string(m))

b = Browser(9396); b.size(820, 900, scale=1.5)
for i, m in enumerate(msgs, 1):
    body = ''; plain = ''
    for p in m.walk():
        if p.get_content_type() == 'text/html': body = p.get_payload(decode=True).decode('utf-8', 'replace')
        if p.get_content_type() == 'text/plain': plain = p.get_payload(decode=True).decode('utf-8', 'replace')
    if body:
        mb = re.search(r'<body[^>]*>(.*)</body>', body, re.S)
        if mb: body = '<div style="background:#F1F1EE">' + mb.group(1) + '</div>'
    if not body: body = '<pre style="white-space:pre-wrap;font:15px/1.5 Arial">' + html.escape(plain) + '</pre>'
    hdr = ''.join('<tr><th>%s</th><td>%s</td></tr>' % (k, html.escape(dec(m.get(h, '')))) for k, h in (('Od', 'From'), ('Komu', 'To'), ('Odpověď na', 'Reply-To'), ('Předmět', 'Subject')) if m.get(h))
    page = ('<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;background:#e9e9e6;font:14px/1.45 -apple-system,Arial,sans-serif;padding:18px}'
            '.win{background:#fff;border:1px solid #cfcfca;border-radius:10px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.08)}'
            'table.h{width:100%;border-collapse:collapse;background:#f6f6f3;border-bottom:1px solid #ddd}table.h th{width:110px;text-align:left;color:#666;font-weight:500;padding:6px 14px;vertical-align:top}table.h td{padding:6px 14px 6px 0}'
            '.b{padding:6px 18px 18px}</style></head><body><div class="win"><table class="h">' + hdr + '</table><div class="b">' + body + '</div></div></body></html>')
    p = os.path.join(SIM, '_mail.html'); open(p, 'w', encoding='utf-8').write(page)
    b.go('file://' + p, 1.2)
    name = 'e%02d' % i
    r = b.js("var w=document.querySelector('.win').getBoundingClientRect(); var mt='', m=0, it=document.createTreeWalker(document.querySelector('.win'), NodeFilter.SHOW_TEXT), n; while(n=it.nextNode()){ if(!n.textContent.trim()) continue; var g=document.createRange(); g.selectNodeContents(n); var rs=g.getClientRects(); for(var k=0;k<rs.length;k++) if(rs[k].height && rs[k].bottom>m){ m=rs[k].bottom; mt=n.textContent.trim().slice(0,40)+'|'+n.parentNode.tagName; } } return [w.left, w.top+scrollY, w.width, Math.min(w.height, m - w.top + 30), m, w.height, mt]")

    import base64
    d = b.call('Page.captureScreenshot', format='jpeg', quality=88, captureBeyondViewport=True, clip={'x': r[0] - 8, 'y': r[1] - 8, 'width': r[2] + 16, 'height': r[3] + 16, 'scale': 1})['data']
    open(os.path.join(OBR, name + '.jpg'), 'wb').write(base64.b64decode(d))
    print(name, '|', dec(m.get('To')), '|', dec(m.get('Subject')))
b.close()
