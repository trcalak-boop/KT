#!/usr/bin/env python3
"""Sestaví řízenou znalostní bázi AI konzultanta T&S Studio (znalosti.json) ze souborů webu.
Zdroje: české stránky služeb, studia a kontaktu (hlavní obsah bez formulářů a skriptů), data/reference.js
(jen viditelné zakázky, BEZ cen), data/nastaveni.js (kontakt), cenova-pravidla.json. Interní ceník se
NEPOUŽÍVÁ. Skript končí chybou, když by se do znalostí dostala jakákoli částka.
Použití: python3 -I sestavit_znalosti.py <cesta/k/web> <výstup/znalosti.json> [https://zaklad-webu]
Se základem se relativní odkazy změní na absolutní (test na jiné doméně než web)."""
import datetime, html, json, os, re, sys

WEB, OUT = sys.argv[1], sys.argv[2]
ZAKLAD = sys.argv[3].rstrip('/') if len(sys.argv) > 3 else ''
HERE = os.path.dirname(os.path.abspath(__file__))
CENA = re.compile(r'\d[\d\s.,  ]*\s?(Kč|Kc|CZK|EUR|€|USD|\$|korun)|(€|\$)\s?\d', re.I)
LANGS = ['en', 'cs', 'de', 'es', 'fr', 'pt', 'it', 'pl', 'nl', 'uk', 'ar', 'hi', 'zh', 'ja', 'ko', 'tr', 'id', 'ru']


def text(f):
    s = open(os.path.join(WEB, f), encoding='utf-8').read()
    m = re.search(r'<main.*?</main>', s, flags=re.S); s = m.group(0) if m else s
    s = re.sub(r'<(script|style|nav|form|svg)\b.*?</\1>', ' ', s, flags=re.S)
    s = re.sub(r'<(h[1-4]|p|li|dt|dd|section|article|div)\b[^>]*>', '\n', s)
    s = re.sub(r'<[^>]+>', ' ', s); s = html.unescape(s)
    lines = [re.sub(r'\s+', ' ', x).strip() for x in s.split('\n')]
    out = []
    for x in lines:
        if not x or x in ('&',) or re.fullmatch(r'[0-9A-E]\s*&?', x): continue
        if x.startswith('Pojďme vytvořit něco výrazného') and len(out) > 3: break       # závěrečná výzva s formulářem
        if CENA.search(x): continue                                      # věta s částkou se vynechá
        out.append(x)
    return '\n'.join(out)


def js_data(f, var):
    s = open(os.path.join(WEB, f), encoding='utf-8').read()
    m = re.search(r'window\.' + var + r'\s*=\s*', s)
    return json.JSONDecoder().raw_decode(s[m.end():])[0]


sections = []
for f, t in [('cs/sluzby.html', 'Služby – přehled'), ('cs/sluzby-branding.html', 'Strategie a identita značky'), ('cs/sluzby-design.html', 'Grafika a kreativní obsah'),
             ('cs/sluzby-web.html', 'Design a vývoj webů'), ('cs/sluzby-ai.html', 'AI a chytrá řešení'), ('cs/sluzby-partnerstvi.html', 'Dlouhodobé digitální partnerství'),
             ('cs/studio.html', 'Studio, lidé a postup spolupráce'), ('cs/kontakt.html', 'Jak probíhá poptávka')]:
    sections.append({'title': t, 'text': text(f), 'source': f})

refs = []
for r in js_data('data/reference.js', 'REFERENCE'):
    if not r.get('zobrazit', True): continue
    u = r.get('odkaz') or ''
    if u and not re.match(r'^https?://', u): u = '/' + u.lstrip('/')
    popis = ' '.join(s for s in re.split(r'(?<=[.!?])\s+', r.get('kratkyPopis', '')) if not CENA.search(s))
    refs.append({'id': r['id'], 'title': r.get('nazev', r['id']), 'field': r.get('obor', ''), 'services': r.get('sluzby', []), 'features': r.get('funkce', []) if isinstance(r.get('funkce'), list) else [],
                 'summary': popis, 'url': u, 'fictional': bool(r.get('ukazka'))})

NS = open(os.path.join(WEB, 'data/nastaveni.js'), encoding='utf-8').read()
N = {k: (re.search(r'"' + k + r'"\s*:\s*"([^"]*)"', NS) or [None, ''])[1] for k in ('email', 'telefon')}
pol = json.load(open(os.path.join(HERE, 'cenova-pravidla.json'), encoding='utf-8'))


def urls(en, cs, de):
    d = {'en': '/' + en, 'cs': '/cs/' + cs, 'de': '/de/' + de}
    for l in LANGS:
        if l not in d: d[l] = '/' + l + '/' + en
    return d


pages = [{'key': 'kontakt', 'title': 'Kontakt a poptávkový formulář', 'urls': urls('contact.html', 'kontakt.html', 'kontakt.html')},
         {'key': 'reference', 'title': 'Reference (všechny projekty)', 'urls': urls('work.html', 'reference.html', 'arbeiten.html')},
         {'key': 'sluzby', 'title': 'Služby', 'urls': urls('services.html', 'sluzby.html', 'leistungen.html')},
         {'key': 'studio', 'title': 'Studio', 'urls': urls('studio.html', 'studio.html', 'studio.html')}]
for l, p in pages[0]['urls'].items():
    assert os.path.isfile(os.path.join(WEB, p.lstrip('/'))), p
for p in pages[1:]:
    for u in p['urls'].values(): assert os.path.isfile(os.path.join(WEB, u.lstrip('/'))), u

k = {'business': 'T&S Studio', 'updated': datetime.date.today().isoformat(),
     'contact': {'email': N.get('email') or 'tasstudio@tasstudio.eu', 'phone': N.get('telefon') or '+420 731 868 680'},
     'sections': sections, 'references': refs, 'pages': pages, 'links': [],
     'pricePolicy': {'noAmounts': bool(pol['neuvadet_castky']), 'rules': pol['postup']}}
blob = json.dumps(k, ensure_ascii=False)
bad = [m.group(0) for m in CENA.finditer(blob)]
if bad: sys.exit('CHYBA: ve znalostech je částka: %s' % bad[:5])
if 'cenaOd' in blob or 'cenaRozpis' in blob: sys.exit('CHYBA: ve znalostech je cenové pole')
if ZAKLAD:
    for r in refs:
        if r['url'].startswith('/'): r['url'] = ZAKLAD + r['url']
    for p in pages: p['urls'] = {l: ZAKLAD + u for l, u in p['urls'].items()}
os.makedirs(os.path.dirname(os.path.abspath(OUT)), exist_ok=True)
json.dump(k, open(OUT, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print('znalosti: %d sekcí, %d referencí, %d znaků, bez částek -> %s' % (len(sections), len(refs), len(blob), OUT))
