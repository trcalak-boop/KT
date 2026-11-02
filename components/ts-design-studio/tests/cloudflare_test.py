#!/usr/bin/env python3
"""Adaptér Cloudflare (TsStudioCloudflareModel) proti napodobenině API na localhostu: přetížení → opakování, záložní model, kvóta bez opakování.
Poslední řádek: RESULT pass=N fail=M"""
import http.server, json, os, subprocess, tempfile, threading
HERE = os.path.dirname(os.path.abspath(__file__)); COMP = os.path.abspath(os.path.join(HERE, '..'))
PHP = os.path.expanduser('~/TS-Studio/tools/php/php'); PORT = 8297
P = F = 0; CALLS = []; MODE = {'v': ''}


def ok(c, m, d=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL ' + m + (' | ' + str(d)[:300] if d else ''))


class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        b = json.loads(self.rfile.read(int(self.headers.get('content-length', 0)))); CALLS.append(b['model'])
        busy = {'success': False, 'errors': [{'code': 3040, 'message': 'Capacity temporarily exceeded, please try again.'}]}
        quota = {'success': False, 'errors': [{'code': 4006, 'message': 'you have used up your daily free allocation of 10,000 neurons'}]}
        okb = {'choices': [{'message': {'content': '{"ok": "%s"}' % b['model']}, 'finish_reason': 'stop'}], 'usage': {'prompt_tokens': 1000, 'completion_tokens': 500}}
        m = MODE['v']
        if m == 'busy-once' and len(CALLS) == 1: code, o = 429, busy
        elif m == 'busy-main' and b['model'] == 'main': code, o = 429, busy
        elif m == 'quota': code, o = 429, quota
        else: code, o = 200, okb
        o = json.dumps(o).encode(); self.send_response(code); self.send_header('content-type', 'application/json'); self.send_header('content-length', str(len(o))); self.end_headers(); self.wfile.write(o)


srv = http.server.ThreadingHTTPServer(('127.0.0.1', PORT), Mock); threading.Thread(target=srv.serve_forever, daemon=True).start()
script = os.path.join(tempfile.mkdtemp(), 'run.php')
open(script, 'w').write('''<?php
require %r . '/core/bootstrap.php';
$m = new TsStudioCloudflareModel(['apiKey' => 'x', 'baseUrl' => 'http://127.0.0.1:%d/ai/v1', 'model' => 'main', 'retryDelay' => 0,
    'fallbackModels' => $argv[1] === 'fb' ? [['model' => 'zaloha', 'rates' => [26668, 204805], 'extra' => []]] : []]);
try { $r = $m->complete([['role' => 'user', 'content' => 'x']]); echo json_encode(['ok' => $r['text'], 'model' => $m->model(), 'neurons' => $m->neurons($r['in'], $r['out'])]); }
catch (TsStudioModelError $e) { echo json_encode(['kind' => $e->kind, 'msg' => $e->getMessage()]); }
''' % (COMP, PORT))
run = lambda arg='': json.loads(subprocess.run([PHP, script, arg], capture_output=True, text=True).stdout)
try:
    MODE['v'] = 'busy-once'; CALLS.clear(); r = run()
    ok(r.get('model') == 'main' and CALLS == ['main', 'main'], 'přetížení jednou → jedno opakování a úspěch', (r, CALLS))
    MODE['v'] = 'busy-main'; CALLS.clear(); r = run('fb')
    ok(r.get('model') == 'zaloha' and CALLS == ['main', 'main', 'zaloha'] and r['neurons'] == 130, 'hlavní model stále přetížený → záložní model, neurony podle jeho ceníku', (r, CALLS))
    MODE['v'] = 'busy-main'; CALLS.clear(); r = run()
    ok(r.get('kind') == 'busy' and 'Capacity temporarily exceeded' in r.get('msg', ''), 'bez zálohy → chyba busy s textem Cloudflare', r)
    MODE['v'] = 'quota'; CALLS.clear(); r = run('fb')
    ok(r.get('kind') == 'limit' and CALLS == ['main'], 'vyčerpaná kvóta → limit hned, bez opakování a bez zálohy', (r, CALLS))
finally:
    srv.shutdown()
print('RESULT pass=%d fail=%d' % (P, F))
