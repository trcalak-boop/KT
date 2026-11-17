import os, sys
sys.path.insert(0, os.path.expanduser('~/TS-Studio/ts-library/tools/lib'))
from cdp import Browser
D = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'strany'); out = sys.argv[1]; a, z = int(sys.argv[2]), int(sys.argv[3])
fs = ['s%03d.jpg' % i for i in range(a, z + 1) if os.path.exists(os.path.join(D, 's%03d.jpg' % i))]
html = '<body style="margin:0;background:#777;display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:8px">' + ''.join('<div style="background:#fff"><img src="file://%s/%s" style="width:100%%;display:block"><small>%s</small></div>' % (D, f, f) for f in fs) + '</body>'
p = os.path.join(D, '..', '_s.html'); open(p, 'w').write(html)
b = Browser(9398); b.size(1600, 900); b.go('file://' + os.path.abspath(p), 1.5); b.shot(out, full=True, quality=72); b.close()
