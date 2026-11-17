"""Generátor hlavního webu T&S ve všech aktivních jazycích (Etapa 9).

    python3 skripty/web/build.py            # stránky všech aktivních jazyků do web/, slovníky a přehled i18n/STAV.md

Jak to funguje:
- texty jsou v kódu anglicky a procházejí funkcí _() → zdrojový (anglický) text je klíčem ve slovníku
  i18n/web/<jazyk>.json („anglický text“: „překlad“). Chybějící nebo prázdný překlad = použije se angličtina
  a jazyk se v i18n/STAV.md ukáže jako neúplný.
- jazyky, jejich adresy a stav (aktivni / priprava) jsou v i18n/jazyky.json. Generují se jen aktivní jazyky,
  pro jazyky v přípravě se udržuje slovník se všemi klíči (prázdné hodnoty = chybí překlad).
- texty z ts.js (t('…')) se do slovníků sbírají taky a do každé stránky se vloží jen ty, které jsou potřeba.
- portfolio (data/reference.js) se nepřekládá tady, ale v administraci (pole „preklady“ u každé zakázky).
Výstup jsou statické HTML soubory; administrace v nich dál udržuje verze ?v= (seznam stránek: web/admin/lib.php).
"""
import json, os, re, sys, html as H

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
W = os.path.join(ROOT, 'web')
I18N = os.path.join(ROOT, 'i18n')
REG = json.load(open(os.path.join(I18N, 'jazyky.json'), encoding='utf-8'))
LANGS = REG['jazyky']; AKTIVNI = [l for l in LANGS if l['stav'] == 'aktivni']; BYK = {l['kod']: l for l in LANGS}
DEF = REG['vychozi']
V = 'ts37'
# verze dat stejně jako administrace (admin/lib.php oznac_verzi_dat): md5 obsahu nastaveni.js|reference.js
import hashlib
DV = hashlib.md5(open(os.path.join(W, 'data', 'nastaveni.js'), 'rb').read() + b'|' + open(os.path.join(W, 'data', 'reference.js'), 'rb').read()).hexdigest()[:10]
POJMY = json.load(open(os.path.join(I18N, 'web', 'portfolio-pojmy.json'), encoding='utf-8'))
DOMENA = 'https://www.tasstudio.eu'
m = re.search(r'"domena"\s*:\s*"([^"]+)"', open(os.path.join(W, 'data', 'nastaveni.js'), encoding='utf-8').read())
if m: DOMENA = m.group(1).rstrip('/')

LOGO_SVG = open(os.path.join(ROOT, 'navrhy/etapa3-hlavni-web/src/img/ts-logo.svg.txt'), encoding='utf-8').read().strip()
LOGO_SVG = LOGO_SVG.replace('<svg viewBox="0 0 2685 712" role="img" aria-label="T&amp;S">', '<svg viewBox="0 0 2685 712" aria-hidden="true">')
ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
PLUS = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>'

# ---------------------------------------------------------------- překlady
MSGS = {}          # zdrojový text → kde se použil (pro přehled)
CAT = {}           # aktuální slovník
LANG = DEF
def load_cat(k):
    p = os.path.join(I18N, 'web', k + '.json')
    return json.load(open(p, encoding='utf-8')) if os.path.exists(p) else {}
def _(s, ctx='web'):
    MSGS.setdefault(s, ctx)
    if LANG == 'en': return s
    v = CAT.get(s)
    return v if v else s
def JS_MSGS():
    src = open(os.path.join(W, 'assets/js/ts.js'), encoding='utf-8').read()
    return [bytes(x, 'utf-8').decode('unicode_escape') if '\\u' in x else x.replace("\\'", "'") for x in re.findall(r"\b(?:t|tf|tr)\('((?:[^'\\]|\\.)*)'", src)]

def slug(key, k=None):
    k = k or LANG
    S = REG['stranky'].get(k, REG['stranky']['en'])
    return S.get(key, REG['stranky']['en'][key])
def U(key, k=None, q=''):
    k = k or LANG
    return '/' + BYK[k]['cesta'] + slug(key, k) + q
def out_path(key, k):
    s = slug(key, k)
    return os.path.join(W, BYK[k]['cesta'], s or 'index.html')

# ---------------------------------------------------------------- data stránek
SERVICES = [  # klíč stránky, písmeno, plný název, krátký název, věta
    ('svc-a', 'A', 'Brand Strategy &amp; Identity', 'Brand &amp; Identity', 'More than a logo. A character people recognise.'),
    ('svc-b', 'B', 'Graphic Design &amp; Creative Content', 'Design &amp; Content', 'One visual language, on every channel.'),
    ('svc-c', 'C', 'Web Design &amp; Development', 'Web &amp; Digital', 'Distinctive design. Real functionality. Meaningful results.'),
    ('svc-d', 'D', 'AI &amp; Intelligent Solutions', 'AI &amp; Automation', 'Help that keeps working after hours.'),
    ('svc-e', 'E', 'Long-term Digital Partnership', 'Partnership', 'After launch, we stay.'),
]
SVC_OPTS = ['Brand Identity', 'Rebranding', 'Graphic Design', 'Social Media Content', 'Advertising Creatives', 'Website Design &amp; Development', 'E-commerce', 'AI &amp; Automation', 'Ongoing Creative Partnership', 'Other']
NAMES = {'koren': 'Kořen', 'vrstva': 'VRSTVA', 'lokna': 'LOKNA', 'slunecnice': 'Slunečnice', 'motoria': 'Motoria', 'salon-salvej': 'Šalvěj', 'ordinace': 'MUDr. Kroupová'}

def chip_opts(name, cls_span):
    return ''.join(f'<label><input type="checkbox" name="{name}" value="{v}"><span{cls_span}>{_(v)}</span></label>' for v in SVC_OPTS)

# ---------------------------------------------------------------- kostra stránky
def head(key, title, desc, extra=''):
    L = BYK[LANG]
    alts = ''.join(f'<link rel="alternate" hreflang="{l["kod"]}" href="{DOMENA}{U(key, l["kod"])}">\n' for l in AKTIVNI) + f'<link rel="alternate" hreflang="x-default" href="{DOMENA}{U(key, DEF)}">\n'
    ogalt = ''.join(f'<meta property="og:locale:alternate" content="{l["locale"]}">\n' for l in AKTIVNI if l['kod'] != LANG)
    canon = f'<link rel="canonical" href="{DOMENA}{U(key)}">\n' if key not in ('404', 'case') else ''
    return f'''<!doctype html>
<html lang="{L['kod']}" dir="{L['dir']}" class="lang-{L['kod']}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{title}</title>
<meta name="description" content="{desc}">
<meta name="theme-color" content="#F1F1EE">
<meta name="color-scheme" content="light dark">
<script>/* světlý / tmavý režim: uložená volba, jinak podle zařízení */(function(){{var t;try{{t=localStorage.getItem('ts-rezim')}}catch(e){{}}if(t!=='light'&&t!=='dark')t=window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.setAttribute('data-rezim',t)}})()</script>
<link rel="icon" href="/favicon.ico?v=2" sizes="32x32">
<link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">
<link rel="manifest" href="/site.webmanifest">
{canon}{alts}<meta property="og:type" content="website">
<meta property="og:locale" content="{L['locale']}">
{ogalt}<meta property="og:site_name" content="T&amp;S Studio">
<meta property="og:title" content="{title}">
<meta property="og:description" content="{desc}">
<meta property="og:url" content="{DOMENA}{U(key)}">
<meta property="og:image" content="https://www.tasstudio.eu/og-image.jpg">
<meta name="twitter:card" content="summary_large_image">
<link rel="stylesheet" href="/assets/fonts/hlavni.css?v={V}">
<link rel="stylesheet" href="/assets/fonts/ts.css?v={V}">
<link rel="stylesheet" href="/assets/css/ts.css?v={V}">
{extra}</head>
<body>
<a class="skip" href="#obsah">{_('Skip to content')}</a>
'''

def lang_switch(key, mobile=False):
    L = BYK[LANG]
    items = ''.join(f'<a href="{U(key, l["kod"])}" hreflang="{l["kod"]}" lang="{l["kod"]}" data-lang="{l["kod"]}"' + (' aria-current="true"' if l['kod'] == LANG else '') +
                    f'><b>{l["zkratka"]}</b><span>{l["nativni"]}</span></a>' for l in AKTIVNI)
    if mobile:
        return f'<div class="langs" role="group" aria-label="{_("Language")}">{items}</div>'
    return (f'<div class="lsw"><button type="button" class="lsw-btn" id="langBtn" aria-expanded="false" aria-controls="langMenu" aria-label="{_("Language")}: {L["nativni"]}">'
            f'<span>{L["zkratka"]}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>'
            f'<div class="lsw-menu" id="langMenu" hidden>{items}</div></div>')

def header(cur, key):
    def a(k, label, ck):
        return f'<a href="{U(k)}"' + (' aria-current="page"' if cur == ck else '') + f'>{label}</a>'
    panel = ''.join(f'<a href="{U(f)}"><b>{_(t)}</b><span>{_(d)}</span></a>' for f, L, t, s, d in SERVICES)
    sub = ''.join(f'<a href="{U(f)}">{_(t)}</a>' for f, L, t, s, d in SERVICES)
    return f'''<header class="hdr"><div class="w">
  <a class="brand" href="{U('home')}" aria-label="{_('T&amp;S Studio – home')}"><span data-logo>{LOGO_SVG}</span><span class="mono">Studio</span></a>
  <nav class="nav" aria-label="{_('Main navigation')}">
    {a('work', _('Work'), 'work')}
    <div class="svc"><button type="button" class="svc-btn" id="svcBtn" aria-expanded="false" aria-controls="svcPanel"{' aria-current="page"' if cur == 'services' else ''}>{_('Services')}</button>
      <div class="svc-panel" id="svcPanel" hidden>{panel}<a class="all" href="{U('services')}">{_('All services')} <span>→</span></a></div></div>
    {a('studio', _('Studio'), 'studio')}
    {a('contact', _('Contact'), 'contact')}
  </nav>
  <div class="tools">
    {lang_switch(key)}
    <button type="button" class="ico theme" data-theme-btn aria-label="{_('Switch theme')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path class="i-moon" d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/><g class="i-sun"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></g></svg></button>
    <a class="btn btn--red btn--sm" href="{U('contact')}">{_('Start a project')}</a>
    <button type="button" class="ico burger" id="burger" aria-expanded="false" aria-controls="mmenu" aria-label="{_('Open menu')}"><span></span></button>
  </div>
</div></header>
<div class="mmenu" id="mmenu" hidden>
  <a class="big" href="{U('work')}">{_('Work')}<span class="mono muted" data-count></span></a>
  <a class="big" href="{U('services')}">{_('Services')}</a>
  <div class="sub">{sub}</div>
  <a class="big" href="{U('studio')}">{_('Studio')}</a>
  <a class="big" href="{U('contact')}">{_('Contact')}</a>
  <div class="bottom">
    <span class="mono muted">{_('Language')}</span>
    {lang_switch(key, True)}
    <div class="row"><span>{_('Theme')}</span><span class="seg"><button type="button" data-mode="light">{_('Light')}</button><button type="button" data-mode="auto">{_('Auto')}</button><button type="button" data-mode="dark">{_('Dark')}</button></span></div>
    <a class="btn btn--red" href="{U('contact')}" style="justify-content:space-between">{_('Start a project')} {ARROW}</a>
  </div>
</div>
'''

def footer():
    return f'''<footer class="ftr"><div class="w">
  <div class="wordmark" data-logo aria-hidden="true">{LOGO_SVG}</div>
  <div class="cols">
    <div><span class="mono">{_('Work')}</span><a href="{U('work')}">{_('All projects')}</a><a href="{U('svc-a')}">{_('Identity')}</a><a href="{U('svc-c')}">{_('Websites &amp; e-commerce')}</a><a href="{U('svc-d')}">{_('AI &amp; automation')}</a></div>
    <div><span class="mono">{_('Services')}</span>{''.join(f'<a href="{U(f)}">{_(s)}</a>' for f, L, t, s, d in SERVICES)}</div>
    <div><span class="mono">{_('Studio')}</span><a href="{U('studio')}">{_('About')}</a><a href="{U('contact')}">{_('Contact')}</a><a data-n-if="instagram" hidden data-n="instagram" href="#" target="_blank" rel="noopener">Instagram ↗</a><a data-n-if="facebook" hidden data-n="facebook" href="#" target="_blank" rel="noopener">Facebook ↗</a><a data-n-if="linkedin" hidden data-n="linkedin" href="#" target="_blank" rel="noopener">LinkedIn ↗</a><a href="{U('privacy')}">{_('Privacy')}</a></div>
    <div><span class="mono">{_('Contact')}</span><span data-n="email">tasstudio@tasstudio.eu</span><span data-n="telefon">+420 731 868 680</span><span class="muted">Kryštof Trčala &amp; Matouš Slováček</span></div>
  </div>
  <div class="legal"><span>© <span class="js-year">2026</span> T&amp;S Studio<span data-n-if="provozovatel" hidden> · <span data-n="provozovatel"></span></span><span data-n-if="ico" hidden> · {_('Company ID')} <span data-n="ico"></span></span><span data-n-if="sidlo" hidden> · <span data-n="sidlo"></span></span></span><span>{_('Concept projects are self-initiated and do not represent real companies.')}</span></div>
</div></footer>
'''

def tail(key):
    L = BYK[LANG]
    # údaje pro ts.js: jazyk, adresy stránek v tomto jazyce, odkazy na stejnou stránku v ostatních jazycích a překlady textů ze skriptu
    data = {'lang': LANG, 'intl': L['intl'], 'def': DEF, 'r': {k: U(k) for k in ('home', 'work', 'case', 'contact', 'privacy', 'services')},
            'alts': {l['kod']: U(key, l['kod']) for l in AKTIVNI}, 'names': {l['kod']: l['nativni'] for l in AKTIVNI},
            't': {s: _(s, 'js') for s in JS_MSGS() if LANG != 'en' and _(s, 'js') != s},
            's': {k: v[LANG] for k, v in POJMY.items() if isinstance(v, dict) and v.get(LANG)}}
    return f'''
<script>window.TS_LANG={json.dumps(data, ensure_ascii=False, separators=(',', ':'))};</script>
<!-- Data webu: kontakty (nastaveni.js) a zakázky (reference.js). Upravujte je v administraci, ne tento soubor. -->
<script src="/data/nastaveni.js?v={DV}"></script>
<script src="/data/reference.js?v={DV}"></script>
<script src="/assets/js/ts.js?v={V}"></script>
</body>
</html>
'''

def page(key, title, desc, cur, body, extra=''):
    html = head(key, title, desc, extra) + header(cur, key) + '<main id="obsah">\n' + body + '\n</main>\n' + footer() + tail(key)
    p = out_path(key, LANG); os.makedirs(os.path.dirname(p), exist_ok=True)
    old = open(p, encoding='utf-8').read() if os.path.exists(p) else ''
    # verzi dat (?v= u data/*.js) nastavuje administrace podle obsahu dat: zachovat, ať se nemění zbytečně
    mo = re.search(r'/data/reference\.js\?v=([0-9A-Za-z]+)', old)
    if mo: html = re.sub(r'(/data/(?:nastaveni|reference)\.js\?v=)[0-9A-Za-z]*', lambda x: x.group(1) + mo.group(1), html)
    open(p, 'w', encoding='utf-8').write(html)

def CTA():
    return f'''<section class="cta"><div class="w">
  <h2 class="d">{_('Let’s build something distinctive')}<b>.</b></h2>
  <form class="steps" id="quickForm" action="{U('contact')}">
    <span class="mono" style="opacity:.7">{_('Step 1 of 3 · What do you need?')}</span>
    <div class="chips">{''.join(f'<label><input type="checkbox" name="sluzby" value="{v}"><span class="chip">{_(v)}</span></label>' for v in SVC_OPTS)}</div>
    <button class="btn btn--red" type="submit">{_('Continue')} {ARROW}</button>
  </form>
  <div class="contacts">
    <span class="mono" style="opacity:.7">{_('Or write to us directly')}</span>
    <span style="font-size:24px;font-weight:600" data-n="email">tasstudio@tasstudio.eu</span>
    <span style="font-size:24px;font-weight:600" data-n="telefon">+420 731 868 680</span>
    <span style="font-size:15px;opacity:.75">{_('You’ll hear back from one of us, not a sales team.')}</span>
  </div>
</div></section>'''

def steps4(short=False):
    S = [('Discovery', 'Strategy', 'A free first call. We learn who your customers are and what the brand needs to do, then send a clear proposal.', 'A free first call, then a written proposal with scope and price.'),
         ('Identity', 'Design', 'Logo, visual system and the first screens. We refine them with you until they feel right.', 'Visual system and key screens, refined together with you.'),
         ('Build', 'Integrate', 'We write the code ourselves, connect bookings, payments or CRM, and test on real phones.', 'Hand-written code, integrations and testing on real phones.'),
         ('Launch', 'Care', 'We launch, show you how to edit content, and stay on for hosting, updates and new campaigns.', 'Launch, training and long-term care if you want it.')]
    return ''.join(f'<li class="step"><div class="n">{i+1}</div><h3 class="h3">{_(a)} <b>&amp;</b> {_(b)}</h3><p>{_(s if short else l)}</p></li>' for i, (a, b, l, s) in enumerate(S))

def person(name, role):
    return (f'<div class="person"><div class="ph" role="img" aria-label="{_("Portrait of")} {name} ({_("coming soon")})"><span class="mono">{_("Portrait coming soon")}</span></div>'
            f'<span class="nm">{name}</span><span class="mono muted">{role}</span></div>')

# ---------------------------------------------------------------- stránky
def build_home():
    TICK = ''.join(f'{_(x)}<em>&amp;</em>' for x in ['Brand identity', 'Graphic design', 'Social content', 'Advertising', 'Websites', 'E-commerce', 'AI &amp; automation', 'Hosting &amp; care'])
    IDX = [('Brand', 'Brand Strategy &amp; Identity', 'More than a logo. A character people recognise.', 'svc-a', ['Brand strategy', 'Logo &amp; variations', 'Typography', 'Colour systems', 'Brand guidelines', 'Rebranding']),
           ('Content', 'Graphic Design &amp; Content', 'One visual language, on every channel.', 'svc-b', ['Social media', 'Templates', 'Ad creatives', 'Campaigns', 'Print', 'Packaging']),
           ('Web', 'Web Design &amp; Development', 'Distinctive design. Real functionality. Meaningful results.', 'svc-c', ['Custom websites', 'UI/UX design', 'E-commerce', 'Booking systems', 'Admin systems', 'CRM &amp; API']),
           ('AI', 'AI &amp; Automation · Custom build', 'Help that keeps working after hours.', 'svc-d', ['AI receptionist', 'Chat assistants', 'Lead qualification', 'Booking help', 'Automation', 'CRM integrations'])]
    rows = ''
    for i, (w, sub, line, href, items) in enumerate(IDX):
        rows += f'''<details class="idx-row"{' open' if i == 2 else ''}><summary><span class="big">{_(w)} <b>&amp;</b></span><span class="what"><span class="mono">{_(sub)}</span>{_(line)}</span><span class="arr">{PLUS}</span></summary>
      <div class="idx-open"><div class="copy"><ul>{''.join(f'<li>{_(x)}</li>' for x in items)}</ul><a class="link" href="{U(href)}">{_('Read more')} {ARROW}</a></div></div></details>
'''
    body = f'''<section class="hero"><div class="w">
  <div class="hero-copy">
    <span class="mono muted">{_('Brand · Content · Web · AI')}</span>
    <h1 class="d"><span class="l1">{_('We build')}</span><span class="l2">{_('brands that')}</span><span class="l3">{_('stand out')}<span class="amp-word">.</span></span></h1>
  </div>
  <div class="hero-sub">
    <p class="lead">{_('From bold identities and creative content to powerful websites and intelligent digital experiences. We bring brands to life.')}</p>
    <div class="hero-cta"><a class="btn btn--red" href="{U('contact')}">{_('Start a project')} {ARROW}</a><a class="btn" href="{U('work')}">{_('Explore our work')}</a></div>
  </div>
  <div class="hero-work" id="heroWork" aria-label="{_('Selected work')}"></div>
  <div class="hero-foot"><span class="mono muted">Kryštof Trčala &amp; Matouš Slováček</span><span class="mono muted"><span data-count></span> {_('projects · identity, content, web')}</span><a class="mono" href="#co-delame">{_('Scroll ↓')}</a></div>
</div></section>
<div class="ticker" aria-hidden="true"><div class="ticker-track"><span>{TICK}</span><span>{TICK}</span></div></div>

<section class="sec" id="co-delame" style="padding-top:120px"><div class="w">
  <div class="sec-head"><span class="mono">{_('What we do')}</span><h2 class="h2">{_('One team for everything a brand needs to exist.')}</h2></div>
  <div class="idx">
{rows}  </div>
  <p class="mono muted" style="margin-top:18px">&amp; {_('Long-term partnership: hosting, care and ongoing design after launch.')} <a class="link" href="{U('svc-e')}" style="font-family:var(--f-body);text-transform:none;letter-spacing:0">{_('Partnership')}</a></p>
</div></section>

<section class="sec"><div class="w">
  <div class="sec-head"><span class="mono">{_('Selected work')}</span><h2 class="h2">{_('Complete brands, not just websites.')}</h2><a class="more link" href="{U('work')}">{_('All work')} (<span id="workCount"></span>) {ARROW}</a></div>
  <div id="spreads"></div>
</div></section>

<section class="sec"><div class="w">
  <div class="sec-head"><span class="mono">{_('How we work')}</span><h2 class="h2">{_('Strategy, design and technology. In one team.')}</h2></div>
  <ol class="approach" style="list-style:none;padding:0">{steps4()}</ol>
</div></section>

<section class="sec"><div class="w">
  <div class="sec-head"><span class="mono">{_('Studio')}</span><h2 class="h2">{_('Two founders. No account managers.')}</h2><a class="more link" href="{U('studio')}">{_('About the studio')} {ARROW}</a></div>
  <div class="studio">
    {person('Kryštof Trčala', _('Co-founder · Photographer &amp; designer'))}
    <div class="mid" aria-hidden="true"><span>&amp;</span></div>
    {person('Matouš Slováček', _('Co-founder'))}
  </div>
  <div class="studio-note">
    <p class="q">{_('You work directly with the people who design and build your project.')}</p>
    <ul><li><b>{_('No templates')}</b>{_('Every brand and website starts from a blank page.')}</li><li><b>{_('Clear scope and price')}</b>{_('Agreed before we start, in writing.')}</li><li><b>{_('You stay in control')}</b>{_('We show you how to edit your website yourself.')}</li></ul>
  </div>
</div></section>

{CTA()}'''
    page('home', _('T&amp;S Studio — We build brands that stand out'), _('T&amp;S Studio creates distinctive brands, visual identities, creative content, modern websites and intelligent digital solutions. Kryštof Trčala &amp; Matouš Slováček.'), 'home', body)

def build_work():
    body = f'''<section class="ph-hero"><div class="w">
  <span class="crumb mono muted">{_('Work')} · <span data-count></span> {_('projects')}</span>
  <h1 class="d">{_('Brands we built')}<span class="red">.</span></h1>
  <div class="side"><p class="lead" style="font-size:18px">{_('Self-initiated concepts and client projects. Each concept is a complete brand: identity, content, website and the systems behind it.')}</p></div>
</div></section>
<div class="w">
  <div class="wk-bar">
    <div class="filters" id="filters" role="group" aria-label="{_('Filter by discipline')}"></div>
    <div class="view" id="view" role="group" aria-label="{_('View')}"><button type="button" data-v="list" aria-pressed="true">{_('List')}</button><button type="button" data-v="grid" aria-pressed="false">{_('Grid')}</button></div>
  </div>
  <div class="rows" id="rows"></div>
  <div class="wk-note"><span class="mono muted">{_('About concepts')}</span><p>{_('Concept projects are brands we invented to show how we work across industries. They are not real companies and we don’t present them as clients. Their demo websites work: you can book a table, order a T-shirt or schedule a test drive. Data stays in your browser.')}</p></div>
</div>
<div style="height:120px"></div>
{CTA()}'''
    page('work', _('Work — T&amp;S Studio'), _('Brands, identities, websites and digital systems by T&amp;S Studio. Every project with identity, applications, live demo and brand guidelines.'), 'work', body)

def build_case():
    body = f'''<article id="projekt">
<section class="cs-hero"><div class="w">
  <dl class="rail" id="pjRail"></dl>
  <div class="cs-title"><h1 class="bf" id="pjTitle">{_('Project')}</h1><p id="pjLead"></p></div>
  <div class="bot" id="pjActions"></div>
</div></section>

<section class="sec" id="pjIntro"><div class="w">
  <div class="intro"><h2 class="mono" style="grid-column:1/4">{_('The project')}</h2><div class="txt" id="pjText"></div><aside id="pjSide"></aside></div>
</div></section>

<section class="sec"><div class="w">
  <div class="chap"><span class="mono no">01 · {_('Brand identity')}</span><h2 class="h2">{_('Name, colour and type.')}</h2></div>
  <div class="ident" id="pjIdent"></div>
</div></section>

<section class="sec" id="galerie"><div class="w">
  <div class="chap"><span class="mono no">02 · {_('Applications, social &amp; advertising')}</span><h2 class="h2">{_('The brand in use.')}</h2></div>
  <div class="gal" id="pjGallery"></div>
</div></section>

<section class="sec" id="digital"><div class="w">
  <div class="chap"><span class="mono no">03 · {_('Digital experience')}</span><h2 class="h2">{_('Website, desktop and mobile.')}</h2></div>
  <div class="dx" id="pjDx"></div>
</div></section>

<section class="sec" id="functions"><div class="w">
  <div class="chap"><span class="mono no">04 · {_('Functional solutions')}</span><h2 class="h2">{_('What the demo actually does.')}</h2></div>
  <div class="fn"><ul id="pjFn"></ul><p class="disc2">{_('The demo runs entirely in your browser. Bookings and changes are not sent anywhere and reset when you close the tab.')}</p></div>
</div></section>

<section class="sec" id="pjDemo"><div class="w">
  <div class="demo"><div class="lv" id="pjLive"></div>
    <div class="gd" id="soubory"><span class="mono muted">{_('Brand guidelines')}</span><span class="h3">{_('Logo, colour, type and applications in one manual.')}</span><p style="font-size:15.5px;color:var(--ink-2)">{_('Clear space, minimum sizes, colour values for print and screen, examples of correct use.')}</p><ul class="files" id="pjFiles"></ul></div></div>
</div></section>

<a class="next" id="pjNext" href="{U('work')}"></a>
</article>
<section class="missing w" id="pjMissing" hidden><h1 class="d" style="font-size:80px">{_('Project not found')}<span class="red">.</span></h1><p class="lead" style="margin:20px 0 28px">{_('This project may have been removed or renamed.')}</p><a class="btn btn--red" href="{U('work')}">{_('All work')} {ARROW}</a></section>'''
    page('case', _('Case study — T&amp;S Studio'), _('Case study by T&amp;S Studio: identity, applications, website and live demo.'), 'work', body)

SV = {
 'A': ('We work out what your brand stands for and who it speaks to. Then we build the system that carries it: logo and its variations, typography, colour, graphic language and guidelines your team can actually use.',
       ['Brand identity', 'Visual identity systems', 'Logo design', 'Brand guidelines', 'Typography systems', 'Colour systems', 'Brand applications', 'Rebranding', 'Brand communication', 'Visual consistency'], ['koren', 'vrstva', 'lokna']),
 'B': ('Social posts, campaigns, print and ads that clearly come from one brand. We design reusable templates for everyday content and create campaigns for the moments that matter.',
       ['Graphic design', 'Social media content', 'Social media templates', 'Advertising creatives', 'Digital campaigns', 'Banners', 'Marketing materials', 'Posters &amp; brochures', 'Print', 'Content design systems'], ['slunecnice', 'motoria']),
 'C': ('Websites and web applications designed around how your customers decide, and built to work: bookings, e-commerce, admin systems, customer portals and integrations, fast on every phone.',
       ['Custom website design', 'UI/UX design', 'Corporate websites', 'Landing pages', 'E-commerce', 'Web applications', 'Admin systems', 'Booking systems', 'Customer portals', 'CRM &amp; API integrations', 'Automation', 'SEO &amp; performance', 'Hosting &amp; maintenance'], ['vrstva', 'salon-salvej', 'ordinace']),
 'D': ('We design and integrate AI assistants and automations around your processes. An AI receptionist can answer common questions, collect enquiries, help with bookings and hand anything complex to a person.',
       ['AI receptionist', 'AI chat assistants', 'AI customer support', 'Lead qualification', 'Automated communication', 'Workflow automation', 'AI-assisted search', 'Smart booking assistants', 'AI &amp; CRM integrations'], []),
 'E': ('Hosting, maintenance, content updates, ongoing design and campaign production. One team that already knows your brand and your code.',
       ['Website management', 'Hosting', 'Technical support', 'Content updates', 'Brand development', 'Ongoing graphic design', 'Campaign production', 'Website improvements', 'Automation upkeep', 'Performance optimisation'], []),
}
def ai_note():
    return '<span class="note">' + _('Every assistant is built for one client and their data. We tell visitors when they are talking to AI, and we don’t send personal details to an AI provider unless it is needed.') + '</span>'

def build_services():
    discs = ''
    for f, L, t, s, d in SERVICES:
        p, items, seen = SV[L]
        inv = L == 'D'
        seen_html = ('<div class="seen"><span class="mono muted">' + _('Seen in') + '</span>' + ''.join(f'<a class="bf bt-{i}" href="{U("case", q="?id=" + i)}">{NAMES[i]}</a>' for i in seen) + '</div>') if seen else ''
        discs += f'''<section class="disc{' inv' if inv else ''}">
    <div class="L">{L}<b>&amp;</b></div>
    <div class="C"><span class="mono{'' if inv else ' muted'}"{' style="opacity:.7"' if inv else ''}>{_(t)}</span><h2 class="h2">{_(d)}</h2><p>{_(p)}</p>{ai_note() if inv else ''}<a class="link" href="{U(f)}">{_(s)} {ARROW}</a></div>
    <div class="S">{'<span class="tag" style="align-self:flex-start">' + _('Custom build · tailored per project') + '</span>' if inv else ''}<ul>{''.join(f'<li>{_(x)}</li>' for x in items)}</ul>{seen_html}</div>
  </section>
'''
    body = f'''<section class="ph-hero"><div class="w">
  <span class="crumb mono muted">{_('Services')}</span>
  <h1 class="d">{_('Five disciplines')}<span class="red">.</span><br>{_('One team')}<span class="red">.</span></h1>
  <div class="side"><p class="lead" style="font-size:18px">{_('Most brands need more than one of these. Because the same two people work on all of them, nothing gets lost between the logo, the campaign and the code.')}</p></div>
</div></section>
<div class="w">
  {discs}
  <section class="start"><h2 class="h2">{_('Not sure which you need?')}</h2><p>{_('Tell us what you are planning. We’ll suggest a scope in the first call. It’s free.')}</p><a class="btn btn--red" href="{U('contact')}">{_('Start a project')} {ARROW}</a></section>
</div>
<div style="height:40px"></div>
{CTA()}'''
    page('services', _('Services — T&amp;S Studio'), _('Brand strategy &amp; identity, graphic design &amp; content, web design &amp; development, AI &amp; automation and long-term partnership.'), 'services', body)

DETAIL = {
 'A': {'hero': ('More than a logo', 'A character people <b>recognise</b>.', 'We work out what your brand stands for and who it speaks to. Then we build the system that carries it, from the business card to the checkout button, and write down the rules so it stays consistent after we hand it over.'),
       'incl': [('Brand strategy', 'Positioning, audience, personality and the one idea the brand stands on.'), ('Logo &amp; variations', 'Primary logo, compact mark and versions for small sizes, dark and light grounds.'), ('Typography system', 'Typefaces, sizes and hierarchy for print and screen, with licences sorted.'), ('Colour system', 'Primary and supporting colours with print, screen and accessibility values.'), ('Graphic language', 'Patterns, icons, illustration and image treatment that make it recognisable without the logo.'), ('Brand applications', 'Business cards, stationery, signage, packaging, whatever your industry needs.'), ('Brand guidelines', 'A clear PDF manual: clear space, minimum sizes, do’s and don’ts, examples.'), ('Rebranding', 'Keep what people already recognise, fix what holds you back.')],
       'flow': [('Brief &amp; research', 'A conversation about your business, customers and competitors. We agree on scope and price.', 'You get: written brief, scope, quote'), ('Directions', 'Two or three distinct creative directions, shown on real applications, not just on a white page.', 'You get: concept presentation'), ('Identity system', 'We develop the chosen direction into the full system and test it on everything you’ll use.', 'You get: logo files, colours, fonts, templates'), ('Guidelines &amp; rollout', 'The brand manual, print-ready files, and if you want, the website and social templates.', 'You get: brand manual PDF, source files')],
       'faq': [('Do we need a website as well?', 'No. Many clients start with the identity and add the website later. If you plan both, doing them together saves time, because the website is designed in the same system from day one.'), ('Can you refresh our existing logo instead of replacing it?', 'Yes. We look at what people already recognise and keep it where it works.'), ('What do we receive at the end?', 'Logo files for print and screen, colour and font specifications, templates you asked for, and a brand manual in PDF.')],
       'proof': ['koren', 'vrstva', 'lokna'], 'cta': 'Start a brand project'},
 'B': {'hero': ('One language', 'Every <b>channel</b>.', 'Social posts, campaigns, print and ads that clearly come from one brand. We build content systems your team can use every week, and campaigns for the moments that matter.'),
       'incl': [('Social media content', 'Posts, carousels and stories designed for your brand, ready to publish.'), ('Social media templates', 'Editable templates so your team can create on-brand posts without a designer.'), ('Advertising creatives', 'Static and animated ads for Meta, Google and display networks in every size you need.'), ('Campaign concepts', 'One idea carried through social, print, outdoor and the website.'), ('Print &amp; marketing materials', 'Posters, brochures, menus, flyers and price lists, print-ready.'), ('Packaging &amp; labels', 'Labels, boxes and bags that work on the shelf and in photos.'), ('Banners &amp; digital assets', 'Web banners, email headers, presentation templates.'), ('Content design systems', 'Rules for photo, type and layout so everything looks like one brand.')],
       'flow': [('Brief', 'What you want to say, to whom, where and how often.', 'You get: content plan, scope, quote'), ('Concept', 'Visual direction and key messages tested on real formats.', 'You get: concept with sample posts'), ('Production', 'We design the posts, ads or print pieces and prepare all sizes.', 'You get: final files for every channel'), ('Templates &amp; care', 'Templates for your team, or ongoing production every month.', 'You get: editable templates, schedule')],
       'faq': [('Do you also post on our social media?', 'We design the content and templates. Publishing and community management stay with you, or we can agree on a regular monthly package.'), ('Can you work with our existing brand?', 'Yes. We follow your guidelines, and if they are missing, we write down the basic rules first.'), ('Do you prepare ads for Meta and Google?', 'Yes, creatives in all required formats. Campaign targeting and budget management stay with you or your agency.')],
       'proof': ['slunecnice', 'motoria', 'koren'], 'cta': 'Start a content project'},
 'C': {'hero': ('Websites that work', 'Distinctive design. <b>Real</b> functionality.', 'Websites and web applications designed around how your customers decide, and built to work: bookings, e-commerce, admin systems, customer portals and integrations, fast on every phone.'),
       'incl': [('Custom website design', 'UI/UX designed for your customers and your brand, not a template.'), ('Development', 'Hand-written, fast and accessible code that runs on standard hosting.'), ('E-commerce', 'Product catalogue, cart, checkout, discount codes, invoices and order tracking.'), ('Booking systems', 'Online booking with calendars, confirmations and reminders.'), ('Admin systems', 'Edit content, products, bookings and opening hours yourself.'), ('Customer portals', 'Login areas for clients, documents and order history.'), ('CRM &amp; API integrations', 'Connect forms, payments, calendars and your CRM.'), ('SEO, performance &amp; hosting', 'Technical SEO, fast loading, hosting and maintenance.')],
       'flow': [('Discovery', 'Goals, customers, content and the functions you need.', 'You get: sitemap, scope, quote'), ('Design', 'Key pages on desktop and mobile in your brand.', 'You get: clickable design'), ('Build &amp; integrate', 'Development, admin, integrations and testing on real devices.', 'You get: working website on a test address'), ('Launch &amp; care', 'Launch, training and optional hosting and maintenance.', 'You get: live website, admin training')],
       'faq': [('Can we edit the website ourselves?', 'Yes. Every website comes with an admin for the content you need to change, and we show you how to use it.'), ('Do you work with WordPress or Shopify?', 'We build custom websites by default, because they are faster and fit your processes better. If you need a specific platform, we discuss it in the first call.'), ('Who takes care of hosting?', 'You can host the website yourself, or we take care of hosting, backups and updates for a monthly fee.')],
       'proof': ['vrstva', 'salon-salvej', 'ordinace'], 'cta': 'Start a web project'},
 'D': {'hero': ('Help after hours', 'AI built <b>around you</b>.', 'We design and integrate AI assistants and automations around your processes. An AI receptionist can answer common questions, collect enquiries, help with bookings and hand anything complex to a person.'),
       'incl': [('AI receptionist', 'Answers questions about your services, opening hours and prices, day and night.'), ('Lead qualification', 'Asks the right questions and sends complete inquiries to your CRM.'), ('Booking assistance', 'Helps visitors find a free time in your booking system.'), ('Multilingual support', 'Answers in the visitor’s language.'), ('Hand-over to people', 'Complex requests go to your team with the full conversation.'), ('Workflow automation', 'Confirmations, reminders and follow-ups without manual work.'), ('AI-assisted search', 'Finds the right product or answer on larger websites.'), ('CRM integrations', 'Conversations and leads saved where your team already works.')],
       'flow': [('Process review', 'Which questions and tasks repeat, and which must stay with people.', 'You get: scope and data-protection plan'), ('Knowledge &amp; rules', 'We prepare the assistant’s knowledge base, tone and limits.', 'You get: tested answers'), ('Integration', 'Connection to the website, booking system and CRM.', 'You get: assistant on a test address'), ('Monitoring', 'We check conversations and improve the answers.', 'You get: monthly review')],
       'faq': [('Is the assistant already in your demos?', 'No. AI assistants are built per project. The demos show bookings, shops and admin systems; the assistant is a custom addition.'), ('What happens with visitors’ personal data?', 'We send to the AI provider only what the answer needs, tell visitors they are talking to AI, and agree data processing in a contract.'), ('Can the assistant make mistakes?', 'Yes, like any system. That is why it works only with your verified information and hands anything unclear to a person.')],
       'proof': [], 'cta': 'Discuss an AI project'},
 'E': {'hero': ('After launch, we stay', 'One team that <b>knows</b> your brand.', 'Hosting, maintenance, content updates, ongoing design and campaign production. You get people who already know your brand and your code, without explaining everything again.'),
       'incl': [('Website management', 'Updates, backups and monitoring so the website keeps running.'), ('Hosting', 'Fast hosting with SSL, e-mail and backups, arranged by us.'), ('Technical support', 'A direct contact when something needs fixing.'), ('Content updates', 'New pages, products, prices or photos when you need them.'), ('Brand development', 'New applications and guidelines as the brand grows.'), ('Ongoing graphic design', 'Posts, print and ads every month.'), ('Website improvements', 'New features based on how customers use the website.'), ('Performance optimisation', 'Speed, SEO and accessibility checks.')],
       'flow': [('Agree the scope', 'What we take care of and how fast we respond.', 'You get: monthly plan and price'), ('Hand-over', 'Access, backups and documentation in one place.', 'You get: overview of your systems'), ('Regular care', 'Updates, backups and design production each month.', 'You get: monthly report'), ('Improve', 'Proposals for what to improve next.', 'You get: roadmap')],
       'faq': [('Do you take care of websites you did not build?', 'Sometimes. We check the code first and tell you honestly whether it makes sense.'), ('Is there a minimum contract?', 'We agree on the scope together. Typically monthly, with a notice period of one month.'), ('What if we need something urgently?', 'You write or call us directly. Response times are part of the agreed plan.')],
       'proof': [], 'cta': 'Discuss a partnership'},
}

def build_service_details():
    for f, L, t, s, d in SERVICES:
        D = DETAIL[L]
        h1, sub, lead = D['hero']
        proof = ''.join(f'<a href="{U("case", q="?id=" + i)}" data-cursor="{_("View case")}"><span class="tv bt-{i}" style="background:var(--b-bg);color:var(--b-fg);display:grid;place-items:center"><b class="bf" style="font-size:clamp(28px,3.4vw,54px)">{NAMES[i]}</b></span><span class="mono muted">{NAMES[i]}</span></a>' for i in D['proof'])
        if not proof:
            note = _('Custom build · tailored per project. We do not claim that any AI feature already runs in our demos.') if L == 'D' else _('We agree on a monthly plan that fits your website, brand and campaigns.')
            proof = f'<div style="grid-column:1/3;border:1.5px solid var(--ink);padding:32px;display:flex;flex-direction:column;gap:14px"><span class="mono muted">{_(t)}</span><span class="h3">{_(d)}</span><p style="color:var(--ink-2);font-size:16px">{note}</p></div>'
        incl = ''.join(f'<div class="it"><b>{_(a)}</b><span>{_(b)}</span></div>' for a, b in D['incl'])
        flow = ''.join(f'<div><span class="n">{i+1}<b>&amp;</b></span><span class="h3">{_(a)}</span><p>{_(b)}</p><span class="out">{_(c)}</span></div>' for i, (a, b, c) in enumerate(D['flow']))
        faq = ''.join(f'<details{" open" if i == 0 else ""}><summary>{_(q)}</summary><p>{_(a)}</p></details>' for i, (q, a) in enumerate(D['faq']))
        body = f'''<section class="sv-hero"><div class="w">
  <span class="crumb mono muted"><a href="{U('services')}">{_('Services')}</a> / {_(t)}</span>
  <div class="tx"><h1 class="d">{_(h1)}<span class="red">.</span></h1><p class="sub">{_(sub)}</p><p class="lead" style="font-size:19px">{_(lead)}</p>
    <div class="hero-cta"><a class="btn btn--red" href="{U('contact')}">{_(D['cta'])} {ARROW}</a><a class="btn" href="{U('work')}">{_('See our work')}</a></div></div>
  <div class="proof">{proof}</div>
</div></section>
<section class="sec" style="padding-top:40px"><div class="w">
  <div class="sec-head"><span class="mono">{_('What’s included')}</span><h2 class="h2">{_(t)}</h2></div>
  <div class="incl"><div class="lst">{incl}</div></div>
</div></section>
<section class="sec"><div class="w">
  <div class="sec-head"><span class="mono">{_('How it runs')}</span><h2 class="h2">{_('Four steps, clear results.')}</h2></div>
  <div class="flow4">{flow}</div>
</div></section>
<section class="sec"><div class="w">
  <div class="faq"><span class="mono" style="grid-column:1/4">{_('Questions')}</span><div class="q">{faq}</div></div>
</div></section>
{CTA()}'''
        page(f, f'{_(t)} — T&amp;S Studio', f'{_(t)}: {_(d)} T&amp;S Studio.', 'services', body)

def build_studio():
    body = f'''<section class="ph-hero"><div class="w">
  <span class="crumb mono muted">{_('Studio')}</span>
  <h1 class="d">Trčala <span class="red">&amp;</span><br>Slováček</h1>
  <div class="side"><p class="lead" style="font-size:18px">{_('T&amp;S is two people. We design and build every project ourselves, so you always talk to the people doing the work.')}</p></div>
</div></section>
<div class="w">
  <div class="studio">
    {person('Kryštof Trčala', _('Co-founder · Photographer &amp; designer'))}
    <div class="mid" aria-hidden="true"><span>&amp;</span></div>
    {person('Matouš Slováček', _('Co-founder'))}
  </div>
  <div class="studio-note">
    <p class="q">{_('We don’t want to make websites that look like all the others. We want people to remember yours.')}</p>
    <ul><li><b>{_('You talk to us directly')}</b>{_('No middlemen. The call, the design and the changes are with the two of us.')}</li><li><b>{_('No templates')}</b>{_('Every brand and website starts from a blank page, based on your industry and customers.')}</li><li><b>{_('Clear and fair')}</b>{_('Scope and price are agreed in advance. At the end we show you how to edit the website yourself.')}</li><li><b>{_('Built to work')}</b>{_('Bookings, orders or a custom admin: functions that save you time.')}</li></ul>
  </div>
</div>
<section class="sec"><div class="w">
  <div class="sec-head"><span class="mono">{_('How we work')}</span><h2 class="h2">{_('Every phase is a pair.')}</h2></div>
  <ol class="approach" style="list-style:none;padding:0">{steps4(True)}</ol>
</div></section>
{CTA()}'''
    page('studio', _('Studio — T&amp;S Studio'), _('T&amp;S Studio is Kryštof Trčala and Matouš Slováček. You work directly with the people who design and build your project.'), 'studio', body)

def build_contact():
    whens = ['As soon as possible', 'Within 1–3 months', 'In 3–6 months', 'Just exploring']
    prefs = ['E-mail', 'Phone', 'Video call']
    body = f'''<section class="ct" id="poptavka"><div class="w">
  <div class="l">
    <span class="mono muted">{_('Contact')}</span>
    <h1 class="d">{_('Let’s build something distinctive')}<span class="red">.</span></h1>
    <div class="direct"><span class="mono muted">{_('Direct')}</span><b data-n="email">tasstudio@tasstudio.eu</b><b data-n="telefon">+420 731 868 680</b><span class="mono muted" style="margin-top:8px">Kryštof Trčala &amp; Matouš Slováček</span></div>
    <div class="next3">
      <div><b>1<i>&amp;</i></b><span>{_('You send the form. It takes about three minutes.')}</span></div>
      <div><b>2<i>&amp;</i></b><span>{_('One of us reads it and replies personally, with questions or a time for a call.')}</span></div>
      <div><b>3<i>&amp;</i></b><span>{_('After the call you get a written proposal with scope and price. Free and non-binding.')}</span></div>
    </div>
  </div>
  <div class="form">
    <div id="formWrap">
      <div class="prog" aria-hidden="true"><span class="on"><i>1</i>{_('Services')}</span><span><i>2</i>{_('Project')}</span><span><i>3</i>{_('About you')}</span></div>
      <form id="leadForm" action="/poptavka.php" method="post" novalidate>
        <div class="fstep" data-step="1">
          <h2>{_('What do you need?')}</h2>
          <fieldset class="fld"><legend>{_('Services')} <small>{_('Select all that apply')}</small></legend>
            <div class="svcs" id="svcs">{chip_opts('sluzby', '')}</div>
            <span class="svcs-err" id="svcsErr" hidden>{_('Please choose at least one service.')}</span>
          </fieldset>
        </div>
        <div class="fstep" data-step="2" hidden>
          <h2>{_('Tell us about the project')}</h2>
          <div class="fld" data-req><label for="f-msg">{_('Project description')}</label><textarea id="f-msg" name="msg" maxlength="5000" placeholder="{_('For example: we are opening a coffee roastery and need a logo, packaging and a small online shop.')}"></textarea><span class="cnt" id="msgCount">0 / 5000</span><span class="err-msg">{_('Please tell us a few words about the project.')}</span></div>
          <fieldset class="fld"><legend>{_('Estimated budget')} <span class="cur" id="cur" role="group" aria-label="{_('Currency')}"><button type="button" data-c="EUR" aria-pressed="{'false' if LANG == 'cs' else 'true'}">EUR</button><button type="button" data-c="CZK" aria-pressed="{'true' if LANG == 'cs' else 'false'}">CZK</button></span></legend><div class="opts" id="budgets"></div></fieldset>
          <fieldset class="fld"><legend>{_('Preferred start')}</legend><div class="opts four">{''.join(f'<label class="opt"><input type="radio" name="when" value="{v}"><span>{_(v)}</span></label>' for v in whens)}</div></fieldset>
          <div class="fld" style="margin-bottom:0"><label for="f-website">{_('Existing website')} <small>{_('Optional')}</small></label><input id="f-website" name="website" type="url" inputmode="url" autocomplete="url" placeholder="https://"></div>
        </div>
        <div class="fstep" data-step="3" hidden>
          <h2>{_('About you')}</h2>
          <div class="two">
            <div class="fld" data-req><label for="f-name">{_('Name')}</label><input id="f-name" name="name" type="text" autocomplete="name"><span class="err-msg">{_('Please enter your name.')}</span></div>
            <div class="fld"><label for="f-company">{_('Company')} <small>{_('Optional')}</small></label><input id="f-company" name="company" type="text" autocomplete="organization"></div>
            <div class="fld" data-req data-type="email"><label for="f-email">{_('E-mail')}</label><input id="f-email" name="email" type="email" autocomplete="email"><span class="err-msg">{_('Please enter a valid e-mail so we can reply.')}</span></div>
            <div class="fld"><label for="f-phone">{_('Phone')} <small>{_('Optional')}</small></label><input id="f-phone" name="phone" type="tel" autocomplete="tel"></div>
          </div>
          <fieldset class="fld"><legend>{_('Preferred contact')}</legend><div class="opts">{''.join(f'<label class="opt"><input type="radio" name="pref" value="{v}"{" checked" if v == "E-mail" else ""}><span>{_(v)}</span></label>' for v in prefs)}</div></fieldset>
          <div class="hp" aria-hidden="true"><label for="f-web">{_('Leave this field empty')}</label><input id="f-web" name="web" type="text" tabindex="-1" autocomplete="off"></div>
          <label class="consent" id="consentWrap"><input type="checkbox" id="f-consent" name="consent" value="ano"><span>{_('I understand that T&amp;S will process my personal data to reply to this inquiry. Details are in the <a href="{privacy}" target="_blank">privacy policy</a>.').replace('{privacy}', U('privacy'))}</span></label>
        </div>
        <div class="ferr" id="formError" role="alert" hidden></div>
        <div class="acts"><button type="button" class="back" id="fBack" hidden>{_('← Back')}</button><span></span>
          <button type="button" class="btn btn--red" id="fNext">{_('Continue')} {ARROW}</button>
          <button type="submit" class="btn btn--red" id="fSend" hidden>{_('Send inquiry')} {ARROW}</button></div>
      </form>
      <p class="priv">{_('We use your details only to reply to this inquiry. No newsletters, no third-party trackers.')}</p>
    </div>
    <div class="done-box" id="done" hidden aria-live="polite"><h2 id="doneTitle" tabindex="-1">{_('Thank you')}<b>.</b></h2><p class="lead" id="doneText"></p><button type="button" class="link" id="again" style="align-self:flex-start">{_('Send another inquiry')}</button></div>
  </div>
</div></section>'''
    page('contact', _('Contact — T&amp;S Studio'), _('Start a project with T&amp;S Studio. Tell us what you need: brand, content, website or AI. You will hear back from one of us.'), 'contact', body)

def build_privacy():
    pr = os.path.join(I18N, 'web', 'pravni', f'ochrana-{LANG}.html')   # chybí-li právní text v jazyce, použije se anglický
    prose = open(pr if os.path.exists(pr) else os.path.join(I18N, 'web', 'pravni', 'ochrana-en.html'), encoding='utf-8').read()
    heads = re.findall(r'<h2>(.*?)</h2>', prose)
    c = [0]
    def num(mm):
        c[0] += 1; return f'<h2 id="s{c[0]-1}">{mm.group(1)}</h2>'
    body_html = re.sub(r'<h2>(.*?)</h2>', num, prose)
    body = f'''<section class="ph-hero"><div class="w">
  <span class="crumb mono muted">{_('Privacy')}</span>
  <h1 class="d">{_('Privacy policy')}<span class="red">.</span></h1>
  <div class="side"><p class="lead" style="font-size:17px">{_('How we handle personal data from inquiries, projects and visits to this website.')}</p></div>
</div></section>
<section class="prose"><div class="w">
  <aside aria-label="{_('Sections')}"><span class="mono muted" style="margin-bottom:6px">{_('Privacy policy')}</span>{''.join(f'<a href="#s{i}">{t}</a>' for i, t in enumerate(heads))}</aside>
  <div class="body">{body_html}</div>
</div></section>'''
    page('privacy', _('Privacy policy — T&amp;S Studio'), _('How T&amp;S Studio processes personal data from inquiries, projects and website visits.'), 'privacy', body)

def build_404():
    body = f'''<section class="e404"><div class="w">
  <div class="big">4<b>&amp;</b>4</div>
  <h1 class="h2">{_('This page doesn’t exist.')}</h1>
  <p class="lead">{_('It may have moved when we redesigned the website.')}</p>
  <div class="hero-cta"><a class="btn btn--red" href="{U('home')}">{_('Back to home')} {ARROW}</a><a class="btn" href="{U('work')}">{_('See our work')}</a></div>
</div></section>'''
    page('404', _('Page not found — T&amp;S Studio'), _('This page doesn’t exist.'), '404', body)

BUILD = [build_home, build_work, build_case, build_services, build_service_details, build_studio, build_contact, build_privacy, build_404]

# ---------------------------------------------------------------- slovníky a přehled
def write_catalogs():
    keys = list(MSGS.keys())
    for s in JS_MSGS(): MSGS.setdefault(s, 'js')
    keys = list(MSGS.keys())
    rows = []
    for l in LANGS:
        if l['kod'] == 'en': continue
        p = os.path.join(I18N, 'web', l['kod'] + '.json')
        cur = load_cat(l['kod'])
        new = {k: cur.get(k, '') for k in keys}
        extra = {k: v for k, v in cur.items() if k not in new and v}       # nepoužívané překlady se nezahodí, jen přesunou na konec
        if extra: new['_nepouzivane'] = extra if not isinstance(cur.get('_nepouzivane'), dict) else {**cur['_nepouzivane'], **extra}
        elif isinstance(cur.get('_nepouzivane'), dict): new['_nepouzivane'] = cur['_nepouzivane']
        json.dump(new, open(p, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        hotovo = sum(1 for k in keys if new.get(k))
        rows.append((l, hotovo, len(keys)))
    t = ['# Stav překladů hlavního webu', '', 'Vytváří `skripty/web/build.py`. Klíčem je anglický text, prázdná hodnota = chybí překlad (na webu se použije angličtina).', '',
         '| Jazyk | Stav | Přeloženo | Chybí | Ověřeno rodilým mluvčím |', '|---|---|---|---|---|', f'| English (en) | aktivní, výchozí | {len(keys)} / {len(keys)} | 0 | zdroj |']
    for l, h, n in rows:
        t.append(f'| {l["nativni"]} ({l["kod"]}) | {"aktivní" if l["stav"] == "aktivni" else "v přípravě (negeneruje se)"} | {h} / {n} | {n - h} | {"ano" if l["overeno"] else "ne"} |')
    t += ['', 'Jazyk v přípravě se zapne v `i18n/jazyky.json` (`"stav": "aktivni"`) až po doplnění a kontrole všech textů, právních textů (`i18n/web/pravni/`) a překladů portfolia v administraci.']
    open(os.path.join(I18N, 'STAV.md'), 'w', encoding='utf-8').write('\n'.join(t) + '\n')
    return rows

def write_admin_list():
    # seznam stránek pro administraci (verze ?v=, obrázek pro sdílení, mapa webu s jazykovými verzemi)
    keys = ['home', 'work', 'case', 'services', 'svc-a', 'svc-b', 'svc-c', 'svc-d', 'svc-e', 'studio', 'contact', 'privacy', '404']
    files = [os.path.relpath(out_path(k, l['kod']), W) for l in AKTIVNI for k in keys]
    sitemap = [{l['kod']: U(k, l['kod']) for l in AKTIVNI} for k in keys if k not in ('404', 'case')]
    case = {l['kod']: U('case', l['kod']) for l in AKTIVNI}
    def php(v, ind=''):
        if isinstance(v, dict): return '[' + ', '.join(f"'{k}' => {php(x)}" for k, x in v.items()) + ']'
        if isinstance(v, list): return '[\n' + ''.join(f'{ind}    {php(x, ind + "    ")},\n' for x in v) + ind + ']'
        return "'" + str(v).replace("\\", "\\\\").replace("'", "\\'") + "'"
    out = ("<?php\n// Vytváří skripty/web/build.py – stránky hlavního webu ve všech aktivních jazycích. Neupravujte ručně.\n"
           "if (!defined('ADM')) { http_response_code(403); exit; }\n"
           f"return ['vychozi' => '{DEF}', 'html' => {php(files)}, 'mapa' => {php(sitemap)}, 'detail' => {php(case)}];\n")
    open(os.path.join(W, 'admin', 'stranky-webu.php'), 'w', encoding='utf-8').write(out)
    # chybová stránka 404 v jazyce složky (Apache: ErrorDocument v <jazyk>/.htaccess)
    for l in AKTIVNI:
        if l['cesta']:
            open(os.path.join(W, l['cesta'], '.htaccess'), 'w', encoding='utf-8').write(
                f"# Chybová stránka v jazyce této složky (vytváří skripty/web/build.py)\nErrorDocument 404 /{l['cesta']}404.html\n")

def main():
    global LANG, CAT
    for l in AKTIVNI:
        LANG = l['kod']; CAT = load_cat(LANG) if LANG != 'en' else {}
        for b in BUILD: b()
    rows = write_catalogs()
    write_admin_list()
    for l, h, n in rows:
        if l['stav'] == 'aktivni' and h < n: print('CHYBÍ překlady:', l['kod'], n - h)
    print('hotovo:', ', '.join(l['kod'] for l in AKTIVNI))

if __name__ == '__main__':
    main()
