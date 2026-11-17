#!/usr/bin/env python3
"""Ukázkové prostředí pro manuál: kopie webu projektu se smyšlenými daty, falešnými službami a zachytáváním e-mailů.
Nic se nepřipojuje k produkci ani ke skutečným službám (AI, audit = falešní poskytovatelé, e-maily = soubory)."""
import json, os, random, shutil, sqlite3, subprocess, time, urllib.error, urllib.request

PROJ = '/Users/krystof/Downloads/T&S webstudio_28-10'
HERE = os.path.dirname(os.path.abspath(__file__))
SIM = os.path.join(HERE, 'sim'); W = os.path.join(SIM, 'web'); PRIV = os.path.join(SIM, 'private'); G = os.path.join(SIM, 'sub', 'nahledy')
DATA = os.path.join(W, 'admin', 'data'); MAIL = os.path.join(SIM, 'posta')
PHP = os.path.expanduser('~/TS-Studio/tools/php/php')
PORT, GPORT = 8271, 8272; BASE = 'http://localhost:%d' % PORT; GBASE = 'http://127.0.0.1:%d' % GPORT
SID = {1: 'k' * 26 + '01', 2: 'm' * 26 + '02'}


def setup():
    if os.path.exists(SIM): shutil.rmtree(SIM)
    shutil.copytree(os.path.join(PROJ, 'web'), W, ignore=shutil.ignore_patterns('crm.sqlite*', 'relace', '.DS_Store'))
    shutil.rmtree(DATA, ignore_errors=True)
    os.makedirs(os.path.join(DATA, 'relace')); os.makedirs(MAIL)
    for d in ('ai/data', 'ai/tessa', 'audit/data', 'stat/data', 'studio/data'): os.makedirs(os.path.join(PRIV, d))
    os.makedirs(G)
    # heslo administrace (smyšlené, jen pro snímek přihlášení) a připravené relace dvou členů týmu
    subprocess.run([PHP, '-r', "file_put_contents('%s/heslo.php', '<?php return ' . var_export(['hash' => password_hash('ukazka-manual-2026', PASSWORD_DEFAULT)], true) . ';');" % DATA], check=True)
    for uid, sid in SID.items():
        open(os.path.join(DATA, 'relace', 'sess_' + sid), 'w').write('csrf|s:9:"token-%03d";ok|b:1;t|i:%d;uid|i:%d;' % (uid, int(time.time()) + 86400 * 30, uid))
    # AI asistentka: falešný poskytovatel s připravenými odpověďmi
    bal = os.path.expanduser('~/TS-Studio/nasazeni/produkce-ai/balicek/k-nahrani/ai')
    shutil.copy2(os.path.join(bal, 'znalosti.json'), os.path.join(PRIV, 'ai', 'znalosti.json'))
    for f in ('persona.md', 'capabilities.json'):
        src = os.path.join(os.path.expanduser('~/TS-Studio/nasazeni/produkce-ai/balicek-t5/k-nahrani/private-ai/tessa'), f)
        if not os.path.exists(src): src = os.path.join(bal, 'tessa', f)
        shutil.copy2(src, os.path.join(PRIV, 'ai', 'tessa', f))
    open(os.path.join(PRIV, 'ai', 'ai-consultant.php'), 'w').write(AI_CFG % {'base': BASE})
    open(os.path.join(PRIV, 'audit', 'site-audit.php'), 'w').write(AUDIT_CFG % {'base': BASE})
    open(os.path.join(PRIV, 'stat', 'analytics.php'), 'w').write(STAT_CFG % {'base': BASE})
    open(os.path.join(PRIV, 'studio', 'studio.php'), 'w').write(STUDIO_CFG % {'g': GBASE, 'b': BASE})
    open(os.path.join(G, 'router.php'), 'w').write(GATE % (W, PRIV + '/studio', PRIV + '/studio'))
    open(os.path.join(SIM, 'sendmail.sh'), 'w').write('#!/bin/sh\nf=$(mktemp "%s/mail-XXXXXX")\ncat > "$f.eml"\nrm -f "$f"\n' % MAIL)
    os.chmod(os.path.join(SIM, 'sendmail.sh'), 0o755)


AI_CFG = r"""<?php
/* Ukázkové prostředí manuálu: falešný poskytovatel AI (nikam se nepřipojuje). */
$private = __DIR__; $web = dirname(__DIR__, 2) . '/web';
require_once $web . '/vendor/ts/ts-ai-consultant/core/bootstrap.php';
$odpoved = function (array $req) {
    $m = end($req['messages']); $c = is_array($m['content']) ? '' : mb_strtolower($m['content']);
    if (strpos($c, 'nabídk') !== false || strpos($c, 'kontakt') !== false || strpos($c, 'ozv') !== false)
        $t = "Ráda vás propojím se studiem. Vyplňte prosím krátký formulář níže – Kryštof nebo Matouš se vám ozvou s návrhem dalšího postupu.\n[[KONTAKT: Web pro rodinnou pekárnu s objednávkou dortů online]]";
    elseif (strpos($c, 'pekár') !== false || strpos($c, 'web') !== false)
        $t = "Pro pekárnu se hodí přehledný web s nabídkou, otevírací dobou a objednávkou dortů předem. Podobný přístup ukazuje naše reference **Salon Šalvěj** – jednoduchá rezervace a důraz na fotografie. Chcete, abych vám zprostředkovala nezávaznou nabídku?";
    else $t = 'Dobrý den, jsem Tessa, AI asistentka T&S Studia. Pomohu vám vybrat služby, najít podobnou referenci nebo předat poptávku studiu.';
    return ['text' => $t];
};
return [
    'enabled' => true, 'timezone' => 'Europe/Prague', 'business' => 'T&S Studio', 'contactEmail' => 'tasstudio@tasstudio.eu', 'defaultLang' => 'cs',
    'provider' => new TsAiFakeProvider(['script' => array_fill(0, 40, $odpoved)]), 'pricing' => ['fake' => [0, 0, 0, 0]],
    'contactMode' => 'marker', 'knowledgeMaxChars' => 5000, 'historyMessages' => 8, 'maxTokens' => 500,
    'knowledgeFile' => $private . '/znalosti.json',
    'persona' => ['name' => 'Tessa', 'file' => $private . '/tessa/persona.md'], 'capabilitiesFile' => $private . '/tessa/capabilities.json',
    'audit' => ['enabled' => true, 'lookup' => function (string $token) use ($private) {
        require_once dirname($private, 2) . '/web/vendor/ts/ts-site-audit/core/bootstrap.php';
        $s = require dirname($private) . '/audit/site-audit.php'; $s['source'] = 'public'; return ts_site_audit($s)->status($token);
    }],
    'db' => ['dsn' => 'sqlite:' . $private . '/data/ai.sqlite', 'autoMigrate' => true], 'salt' => 'ukazka-manual',
    'limits' => ['perIp10min' => 200, 'perIpDay' => 400, 'globalMinute' => 300, 'turnsPerConversation' => 20, 'leadsPerIpHour' => 50, 'newConvPerIpHour' => 60, 'globalDay' => 3000],
    'guard' => ['requireOrigin' => true, 'blockBots' => false, 'pow' => 8],
    'maxChars' => 1000, 'allowedOrigins' => ['%(base)s'], 'retention' => ['conversationDays' => 30, 'leadDays' => 90],
    'consentText' => 'Souhlasím se zpracováním údajů z formuláře a této konverzace, aby mi studio mohlo odpovědět.',
    'leadSink' => function (array $lead) use ($web) { require_once $web . '/admin/crm-ai.php'; return crm_ai_lead($lead); },
];
"""
AUDIT_CFG = r"""<?php
$private = __DIR__;
return ['enabled' => true, 'timezone' => 'Europe/Prague', 'defaultLang' => 'cs', 'source' => 'admin',
    'meter' => ['provider' => 'fake'], 'vision' => ['provider' => 'fake'], 'visionPerDay' => 30,
    'db' => ['dsn' => 'sqlite:' . $private . '/data/audit.sqlite', 'autoMigrate' => true], 'shotDir' => $private . '/data/snimky',
    'salt' => 'ukazka-manual', 'cacheHours' => 24, 'retentionDays' => 30,
    'limits' => ['publicPerIpDay' => 20, 'publicPerDay' => 50, 'adminPerDay' => 100], 'allowedOrigins' => ['%(base)s']];
"""
STAT_CFG = r"""<?php
$private = __DIR__;
return ['enabled' => true, 'timezone' => 'Europe/Prague', 'db' => ['dsn' => 'sqlite:' . $private . '/data/analytics.sqlite', 'autoMigrate' => true],
    'salt' => 'ukazka-manual', 'allowedOrigins' => ['%(base)s'], 'ownHosts' => ['localhost'], 'retentionMonths' => 14, 'limits' => ['perIpHour' => 6000, 'globalDay' => 50000]];
"""
STUDIO_CFG = r"""<?php
$private = __DIR__;
$studio = ['db' => ['dsn' => 'sqlite:' . $private . '/data/studio.sqlite', 'autoMigrate' => true], 'timezone' => 'Europe/Prague', 'uploadDir' => $private . '/data/navrhy',
  'deliverySecret' => str_repeat('u', 40), 'previewBase' => '%(g)s/', 'retentionMonths' => 24, 'feedbackTo' => ['tasstudio@tasstudio.eu'], 'crmUrl' => '%(b)s/admin/#crm/lead',
  'mail' => ['transport' => 'log', 'logFile' => $private . '/data/posta.log', 'from' => 'tasstudio@tasstudio.eu', 'fromName' => 'T&S Studio']];
return ['enabled' => true, 'studio' => $studio, 'previewContact' => 'tasstudio.eu', 'allowedOrigins' => ['%(b)s'], 'adminOrigins' => ['%(b)s']];
"""
GATE = """<?php
require %r . '/vendor/ts/ts-design-studio/core/bootstrap.php';
$cfg = require %r . '/studio.php';
(new TsStudioPreviewGate(ts_design_studio($cfg['studio']), ['failDir' => %r . '/data/brana', 'contact' => 'tasstudio.eu', 'adminOrigins' => $cfg['adminOrigins']]))->serve();
"""

_procs = []


def start():
    log = open(os.path.join(SIM, 'php.log'), 'a')
    _procs.append(subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-d', 'sendmail_path=' + os.path.join(SIM, 'sendmail.sh'), '-d', 'upload_max_filesize=8M', '-d', 'post_max_size=10M',
                                    '-S', 'localhost:%d' % PORT, '-t', W], stdout=log, stderr=log, start_new_session=True, cwd=W))
    _procs.append(subprocess.Popen([PHP, '-d', 'opcache.enable=0', '-S', '127.0.0.1:%d' % GPORT, os.path.join(G, 'router.php')], stdout=log, stderr=log, start_new_session=True))
    time.sleep(1.5)


def stop():
    for p in _procs:
        try: os.killpg(p.pid, 15)
        except Exception: pass


def api(a, body=None, uid=1, q=''):
    h = {'Cookie': 'tsadmin=' + SID[uid], 'Origin': BASE}
    if body is not None: h['Content-Type'] = 'application/json'; h['X-CSRF'] = 'token-%03d' % uid
    r = urllib.request.Request(BASE + '/admin/api.php?a=' + a + q, data=json.dumps(body).encode() if body is not None else None, headers=h)
    try:
        with urllib.request.urlopen(r, timeout=60) as x: return json.loads(x.read())
    except urllib.error.HTTPError as e:
        t = e.read().decode('utf-8', 'replace')
        try: return json.loads(t)
        except Exception: return {'ok': False, 'http': e.code, 'text': t[:300]}


def seed_stat(days=30):
    """Smyšlená návštěvnost za posledních 30 dní přímo do databáze měření (jen v ukázkovém prostředí)."""
    db = sqlite3.connect(os.path.join(PRIV, 'stat', 'data', 'analytics.sqlite'))
    db.executescript(open(os.path.join(W, 'vendor', 'ts', 'ts-analytics', 'migrations', '001_analytics.sqlite.sql')).read())
    db.execute('CREATE TABLE IF NOT EXISTS ts_an_schema (version INTEGER NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)')
    db.execute("INSERT OR IGNORE INTO ts_an_schema VALUES (1, '2026-11-01 00:00:00')")
    rnd = random.Random(7); now = int(time.time())
    pages = ['/cs/', '/cs/reference.html', '/cs/sluzby-web.html', '/cs/kontakt.html', '/', '/de/', '/cs/projekt.html?p=motoria', '/cs/studio.html']
    srcs = [('google', 'organic'), ('', ''), ('instagram.com', 'referral'), ('facebook.com', 'referral'), ('google', 'cpc'), ('linkedin.com', 'referral')]
    for d in range(days):
        day_ts = now - d * 86400
        for _ in range(rnd.randint(8, 30)):
            vid = '%016x' % rnd.getrandbits(64); sid = '%016x' % rnd.getrandbits(64); s, m = rnd.choice(srcs); dev = rnd.choice('mmmdt'); t = day_ts - rnd.randint(0, 80000)
            for i in range(rnd.randint(1, 4)):
                p = rnd.choice(pages)
                db.execute('INSERT INTO ts_an_hits (ts, day, vid, sid, newv, kind, path, ref, src, med, camp, lang, dev, br, os, ev) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                           (t + i * 40, time.strftime('%Y-%m-%d', time.localtime(t)), vid, sid, 1 if i == 0 else 0, 'pv', p, s if m == 'referral' else '', s, m, '', 'cs' if '/cs/' in p else ('de' if '/de/' in p else 'en'),
                            dev, rnd.choice(['Chrome', 'Safari', 'Firefox', 'Edge']), rnd.choice(['iOS', 'Android', 'Windows', 'macOS']), ''))
            if rnd.random() < .06: db.execute("INSERT INTO ts_an_hits (ts, day, vid, sid, kind, path, ev, dev) VALUES (?,?,?,?,'ev','/cs/kontakt.html','poptavka',?)", (t + 300, time.strftime('%Y-%m-%d', time.localtime(t)), vid, sid, dev))
            if rnd.random() < .1: db.execute("INSERT INTO ts_an_hits (ts, day, vid, sid, kind, path, ev, dev) VALUES (?,?,?,?,'ev','/cs/','tessa_zprava',?)", (t + 200, time.strftime('%Y-%m-%d', time.localtime(t)), vid, sid, dev))
    db.commit(); db.close()


if __name__ == '__main__':
    setup(); seed_stat(); print('ok', SIM)
