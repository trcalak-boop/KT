#!/usr/bin/env python3
"""Převod ukázky Šalvěj (web/ukazky/salon-salvej.html) na šablonu Studia „kosmetika“ – jednostránkový web kosmetického salonu bez kitu, ukázkové lišty a administrace.
Styly jsou v ukázce přímo ve stránce (společně s administrací) – převezmou se celé, barvy webu (proměnné --h-* na .web) a písma se napojí na tokeny.
Ilustrace lahviček v úvodu zůstává (etikety nesou značku a názvy produktů ze slotů). Rezervační průvodce a nejbližší volné termíny jsou v ukázce dynamické –
šablona má místo nich statický přehled a kontakt. Tmavý režim podle nastavení zařízení (styly ukázky). Písma Marcellus, Albert Sans, Azeret Mono
(+ Bodoni Moda a Figtree jako volba) – OFL i s licencemi.
Spouštět při změně zdrojové ukázky:  python3 -I tools/sablona_kosmetika.py /cesta/k/projektu/web"""
import json, os, re, shutil, sys

WEB = sys.argv[1] if len(sys.argv) > 1 else '/Users/krystof/Downloads/T&S webstudio_28-10/web'
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, '..', 'templates', 'kosmetika')
F = os.path.join(OUT, 'files')

TOKENS = {
    'ground': ('--t-ground', 'color', '#f6f6f3', 'Pozadí (světlý režim)'), 'paper': ('--t-paper', 'color', '#ffffff', 'Papír (etikety, karty)'),
    'ink': ('--t-ink', 'color', '#1f2421', 'Text a linky'), 'ink2': ('--t-ink2', 'color', '#4a554e', 'Text – doplňkový'),
    'leaf': ('--t-leaf', 'color', '#5f7363', 'Šalvějová (stonky, linky)'), 'bloom': ('--t-bloom', 'color', '#5e4b8b', 'Akcent – květ (tlačítka, štítky)'),
    'bloom_dark': ('--t-bloom-d', 'color', '#b9a6ea', 'Akcent v tmavém režimu'),
}
FONTS = {
    'f_display': ('--f-display', '"Marcellus","Didot",Georgia,serif', ['"Marcellus","Didot",Georgia,serif', '"Bodoni Moda","Bodoni 72","Didot",Georgia,serif'], 'Písmo nadpisů a značky'),
    'f_body': ('--f-body', '"Albert Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', ['"Albert Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', '"Figtree",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'], 'Písmo textu'),
}
T = lambda mx, label='': {'type': 'text', 'max': mx, 'label': label}
M = lambda mx, label='': {'type': 'multiline', 'max': mx, 'label': label}
SLOTS = {
    'lang': T(5, 'Jazyk stránky (cs, en…)'), 'brand': T(40, 'Název salonu'), 'mark': T(14, 'Slovní značka (velkými písmeny, krátká)'), 'mark_sub': T(40, 'Pod značkou (obor · město)'),
    'title': T(70, 'Titulek stránky (prohlížeč, vyhledávače)'), 'description': T(160, 'Popis pro vyhledávače'),
    'nav1': T(24, 'Menu – služby'), 'nav2': T(24, 'Menu – rezervace'), 'nav3': T(24, 'Menu – kontakt'),
    'hero_kicker': T(60, 'Úvod – řádek nad nadpisem'), 'hero_title': T(40, 'Úvod – nadpis'), 'hero_text': M(220, 'Úvod – text'),
    'hero_btn': T(24, 'Úvod – hlavní tlačítko'), 'hero_link': T(24, 'Úvod – odkaz vedle tlačítka'),
    'ledger_title': T(40, 'Etiketa v úvodu – nadpis'), 'ledger': {'type': 'list', 'items': 4, 'label': 'Etiketa v úvodu – řádky', 'fields': {'left': T(24), 'mid': T(14), 'right': T(20)}},
    'ledger_note': T(90, 'Etiketa v úvodu – poznámka'),
    'products': {'type': 'list', 'items': 3, 'label': 'Ilustrace – názvy produktů na lahvičkách (3)', 'fields': {'name': T(22), 'sub': T(24)}},
    'caption_left': T(60, 'Ilustrace – popisek vlevo'), 'caption_right': T(40, 'Ilustrace – popisek vpravo'),
    'svc_kicker': T(30, 'Služby – štítek'), 'svc_title': T(50, 'Služby – nadpis'), 'svc_text': M(240, 'Služby – text'), 'svc_word': T(14, 'Služby – slovo před číslem (např. Receptura)'),
    'services': {'type': 'list', 'items': 9, 'label': 'Služby a ceník', 'fields': {'num': T(4), 'minutes': T(10), 'name': T(40), 'text': T(110), 'price': T(16)}},
    'book_kicker': T(30, 'Rezervace – štítek'), 'book_title': T(40, 'Rezervace – nadpis'), 'book_text': M(220, 'Rezervace – text'),
    'book_phone_label': T(30, 'Rezervace – tlačítko telefon'), 'book_mail_label': T(30, 'Rezervace – tlačítko e-mail'),
    'visit_kicker': T(30, 'Návštěva – štítek'), 'visit_title': T(40, 'Návštěva – nadpis'),
    'address1': T(50, 'Adresa – 1. řádek'), 'address2': T(60, 'Adresa – 2. řádek'),
    'phone': {'type': 'phone', 'label': 'Telefon'}, 'phone_href': {'type': 'url', 'label': 'Telefon jako odkaz (tel:+420…)'}, 'email': {'type': 'email', 'label': 'E-mail'},
    'hours': {'type': 'list', 'items': 7, 'label': 'Otevírací doba', 'fields': {'day': T(14), 'time': T(20)}}, 'hours_note': T(80, 'Otevírací doba – poznámka (volitelné)'),
    'company': T(100, 'Patička – provozovatel (© …)'),
}
CONTENT = {
    'lang': 'cs', 'brand': 'Salon Šalvěj', 'mark': 'Šalvěj', 'mark_sub': 'kosmetický salon · Brno',
    'title': 'Salon Šalvěj – kosmetický salon Brno', 'description': 'Ošetření pleti, obočí a řas v klidném salonu v Brně. Ceník služeb a rezervace termínu.',
    'nav1': 'Receptury a ceník', 'nav2': 'Rezervace', 'nav3': 'Kde nás najdete',
    'hero_kicker': 'Kosmetický salon · Lipová 14, 602 00 Brno', 'hero_title': 'Hodina jen pro vaši pleť',
    'hero_text': 'Ošetření pleti, obočí a řas v klidném salonu. Termín domluvíte telefonem nebo e-mailem, potvrdíme ho ještě týž den.',
    'hero_btn': 'Vybrat termín', 'hero_link': 'Ceník služeb',
    'ledger_title': 'Otevřeno tento týden', 'ledger': [{'left': 'pondělí–úterý', 'mid': '9:00–18:00', 'right': 'Rezervovat'}, {'left': 'středa', 'mid': '10:00–19:00', 'right': 'Rezervovat'},
                                                        {'left': 'čtvrtek–pátek', 'mid': '9:00–18:00', 'right': 'Rezervovat'}, {'left': 'sobota', 'mid': '9:00–13:00', 'right': 'Rezervovat'}],
    'ledger_note': 'Polední pauza po–pá 12:30–13:00.',
    'products': [{'name': 'Sérum se šalvějí', 'sub': 'Receptura 03 · 30 ml'}, {'name': 'Tonikum se šalvějí', 'sub': 'Receptura 02 · 150 ml'}, {'name': 'Pleťový krém', 'sub': 'Receptura 05 · 50 ml'}],
    'caption_left': 'Salvia officinalis · zklidňuje, čistí, tonizuje', 'caption_right': 'Vlastní receptury salonu',
    'svc_kicker': 'Služby a ceník', 'svc_title': 'Ošetření sepsaná jako receptury', 'svc_word': 'Receptura',
    'svc_text': 'Každá receptura má číslo, délku a cenu. U každé služby počítáme s jejím skutečným trváním, takže vám nabídneme jen čas, který opravdu vyjde.',
    'services': [{'num': '01', 'minutes': '60 min', 'name': 'Kosmetické ošetření pleti', 'text': 'Čištění, peeling, maska a masáž podle typu pleti.', 'price': '1 290 Kč'},
                 {'num': '02', 'minutes': '90 min', 'name': 'Hloubkové čištění pleti', 'text': 'Ultrazvuková špachtle, extrakce a zklidňující maska.', 'price': '1 690 Kč'},
                 {'num': '03', 'minutes': '45 min', 'name': 'Masáž obličeje', 'text': 'Liftingová masáž, která uvolní napětí a prokrví pleť.', 'price': '790 Kč'},
                 {'num': '04', 'minutes': '45 min', 'name': 'Laminace obočí', 'text': 'Zvýraznění a fixace tvaru obočí na 6 až 8 týdnů.', 'price': '850 Kč'},
                 {'num': '05', 'minutes': '60 min', 'name': 'Lash lifting', 'text': 'Natočení a výživa vlastních řas, včetně barvení.', 'price': '990 Kč'},
                 {'num': '06', 'minutes': '30 min', 'name': 'Úprava a barvení obočí', 'text': 'Tvarování pinzetou a barvení podle typu obočí.', 'price': '390 Kč'}],
    'book_kicker': '02 · Rezervace', 'book_title': 'Rezervace termínu', 'book_text': 'Zavolejte nebo napište, o jaké ošetření máte zájem. Termín vám potvrdíme ještě týž den.',
    'book_phone_label': 'Zavolat', 'book_mail_label': 'Napsat e-mail',
    'visit_kicker': '03 · Návštěva', 'visit_title': 'Kde nás najdete', 'address1': 'Salon Šalvěj', 'address2': 'Lipová 14, 602 00 Brno',
    'phone': '+420 777 123 456', 'phone_href': 'tel:+420777123456', 'email': 'rezervace@salon-salvej.cz',
    'hours': [{'day': 'pondělí', 'time': '9:00–18:00'}, {'day': 'úterý', 'time': '9:00–18:00'}, {'day': 'středa', 'time': '10:00–19:00'}, {'day': 'čtvrtek', 'time': '9:00–18:00'},
              {'day': 'pátek', 'time': '9:00–15:00'}, {'day': 'sobota', 'time': '9:00–13:00'}, {'day': 'neděle', 'time': 'zavřeno'}],
    'hours_note': 'Polední pauza po–pá 12:30–13:00', 'company': '© 2026 Salon Šalvěj',
}

HTML = '''<!doctype html>
<html lang="{{lang}}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{title}}</title>
<meta name="description" content="{{description}}">
<meta name="color-scheme" content="light dark">
<link rel="stylesheet" href="assets/styles.css">
</head>
<body class="v-web">
<a class="skip" href="#main">Přeskočit na obsah</a>
<main id="main"><div class="web">
<header class="wrap hb-head"><div class="hb-head-in"><a class="hb-logo" href="#top"><span class="hb-mark">{{mark}}</span><span class="hb-sub">{{mark_sub}}</span></a>
<nav class="hb-nav" aria-label="Menu"><a href="#sluzby">{{nav1}}</a><a href="#rezervace">{{nav2}}</a><a href="#kontakt">{{nav3}}</a></nav></div></header>

<section class="wrap hb-intro sv-hero" id="top"><div class="hb-main"><p class="hb-kicker">{{hero_kicker}}</p><h1>{{hero_title}}</h1><p class="hb-lede">{{hero_text}}</p>
<div class="hb-actions"><a class="btn btn-primary" href="#rezervace">{{hero_btn}}</a><a class="hb-tl" href="#sluzby">{{hero_link}}</a></div>
<section class="hb-ledger" aria-label="{{ledger_title}}"><div class="hb-ledger-head"><h2>{{ledger_title}}</h2></div>
<ol class="hb-book">{{#ledger}}<li><a class="hb-row" href="#rezervace"><span class="hb-row-n">•</span><span class="hb-row-d">{{left}}</span><strong class="tnum">{{mid}}</strong><span class="hb-row-go">{{right}}<span aria-hidden="true"> →</span></span></a></li>{{/ledger}}</ol>
{{#ledger_note}}<p class="hb-note">{{ledger_note}}</p>{{/ledger_note}}</section></div>
__FIGURE__</section>

<section class="wrap hb-herbar sv-forms" id="sluzby"><div class="sv-hd2"><p class="hb-kicker">{{svc_kicker}}</p><h2>{{svc_title}}</h2><p class="hb-herbar-p">{{svc_text}}</p></div>
<ul class="hb-labels">{{#services}}<li class="hb-label"><div class="hb-label-top"><span>{{svc_word}}&nbsp;{{num}}</span><span class="tnum">{{minutes}}</span></div><h3>{{name}}</h3><p>{{text}}</p><div class="hb-price"><strong class="tnum">{{price}}</strong><i aria-hidden="true"></i><a href="#rezervace">Rezervovat</a></div></li>{{/services}}</ul></section>

<section class="wrap hb-sec" id="rezervace"><div class="hb-chapter"><p class="hb-orn"><span>{{book_kicker}}</span></p><h2>{{book_title}}</h2><p>{{book_text}}</p></div>
<div class="book-cta"><a class="btn btn-primary" href="{{phone_href}}">{{book_phone_label}} · {{phone}}</a><a class="btn btn-line" href="mailto:{{email}}">{{book_mail_label}}</a></div></section>

<section class="wrap hb-sec" id="kontakt"><div class="hb-chapter"><p class="hb-orn"><span>{{visit_kicker}}</span></p><h2>{{visit_title}}</h2></div>
<div class="contact-grid"><div><h3>Adresa</h3><p>{{address1}}<br>{{address2}}</p></div><div><h3>Kontakt</h3><p class="tnum"><a href="{{phone_href}}">{{phone}}</a><br><a href="mailto:{{email}}">{{email}}</a></p></div>
<div><h3>Otevírací doba</h3><table class="hours"><tbody>{{#hours}}<tr><td>{{day}}</td><td>{{time}}</td></tr>{{/hours}}</tbody></table>{{#hours_note}}<p class="small muted hours-note">{{hours_note}}</p>{{/hours_note}}</div></div></section>

<footer class="wrap foot"><span>{{company}}</span></footer>
</div></main>
</body>
</html>
'''
EXTRA_CSS = '''
/* šablona: odkazy místo tlačítek ukázky, rezervace bez průvodce, přístupnost */
a.hb-logo,.hb-nav a,.hb-price a,a.hb-tl,a.hb-row,.contact-grid a{text-decoration:none;color:inherit}
.hb-nav a{background:none;border:0;padding:8px 0;color:var(--h-ink);font-weight:500;border-bottom:1px solid transparent;font-family:var(--f-body);font-size:15px}
.hb-nav a:hover{color:var(--h-bloom);border-bottom-color:currentColor}
a.hb-tl{display:inline-block}
a.hb-row{display:grid;width:100%;align-items:center}
.hb-price a{font-family:var(--f-body);font-size:13px;border-bottom:1px solid currentColor;padding-bottom:2px}
.book-cta{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
.web .btn-line{border:1px solid var(--h-rule);color:var(--h-ink);background:transparent}
.web .btn-line:hover{border-color:var(--h-bloom);color:var(--h-bloom)}
.hours-note{margin-top:8px}
.skip{position:absolute;left:-9999px;top:0}.skip:focus{left:12px;top:12px;z-index:99;background:var(--h-btn);color:var(--h-btn-ink);padding:.5rem .8rem}
.sv-vis text{text-transform:uppercase}
a.hb-row{grid-template-columns:18px minmax(0,1fr) auto minmax(64px,auto);gap:0 12px}
.hb-row strong,.hb-row-go{white-space:nowrap}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) .sv-vis{background:linear-gradient(180deg,#232A26,#1A201C)}:root:not([data-theme="light"]) .sv-shelf{border-color:rgba(236,239,234,.14)}:root:not([data-theme="light"]) body.v-web{background:#151917}}
@media (max-width:640px){.hb-nav a{font-size:10.5px;letter-spacing:.13em;padding:11px 0;text-transform:uppercase;font-family:var(--f-mono)}}
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(os.path.join(F, 'assets', 'fonts'))
    page = open(os.path.join(WEB, 'ukazky', 'salon-salvej.html'), encoding='utf-8').read()
    css = re.findall(r'<style[^>]*>(.*?)</style>', page, re.S)[-1]
    # písma: tokeny místo pevného :root (vrstva webu)
    a = ':root{--f-display:"Marcellus","Didot",Georgia,serif;--f-body:"Albert Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;--f-mono:"Azeret Mono",ui-monospace,monospace}'
    assert css.count(a) == 1
    tok = ';'.join(['%s:%s' % (t[0], t[2]) for t in TOKENS.values()] + ['%s:%s' % (f[0], f[1]) for f in FONTS.values()])
    css = css.replace(a, '/*@tokens*/:root{' + tok + '}/*@end*/\n:root{--f-mono:"Azeret Mono",ui-monospace,monospace}')
    # barvy webu (.web, světlý režim) z tokenů; akcent tmavého režimu také
    i = css.index('/*@end*/'); w = re.compile(r'\.web\{(.*?)\}', re.S).search(css, i)
    blk = w.group(0)
    for old, new in [('--h-ground:#F6F6F3', '--h-ground:var(--t-ground)'), ('--h-paper:#FFFFFF', '--h-paper:var(--t-paper)'), ('--h-ink:#1F2421', '--h-ink:var(--t-ink)'),
                     ('--h-rule:#1F2421', '--h-rule:var(--t-ink)'), ('--h-ink-2:#4A554E', '--h-ink-2:var(--t-ink2)'), ('--h-leaf-d:#5F7363', '--h-leaf-d:var(--t-leaf)'), ('--h-stem:#5F7363', '--h-stem:var(--t-leaf)'),
                     ('--h-bloom:#5E4B8B', '--h-bloom:var(--t-bloom)'), ('--h-btn:#5E4B8B', '--h-btn:var(--t-bloom)')]:
        assert old in blk, old; blk = blk.replace(old, new)
    css = css[:w.start()] + blk + css[w.end():]
    css = re.sub(r'(--h-bloom:)#B9A6EA', r'\1var(--t-bloom-d)', css); css = re.sub(r'(--h-btn:)#B9A6EA', r'\1var(--t-bloom-d)', css)
    css = css.replace('body.v-web{background:#F6F6F3}', 'body.v-web{background:var(--t-ground)}')
    # tlačítka ukázky → odkazy šablony
    css = css.replace('.hb-nav button', '.hb-nav a').replace('.hb-price button', '.hb-price a')
    header = '/* Šablona „kosmetika“ (T&S Design Studio) – styly převzaté z ukázky Šalvěj (včetně společného základu s administrací). Barvy webu a písma mění Studio v bloku @tokens. */\n'
    fonts = open(os.path.join(WEB, 'assets', 'fonts', 'salon.css'), encoding='utf-8').read()
    fonts = '\n'.join(l for l in fonts.split('\n') if l.startswith('@font-face'))
    fonts = re.sub(r'url\("([a-z0-9-]+\.woff2)(\?v=[0-9a-f]+)?"\)', r'url("fonts/\1")', fonts)
    open(os.path.join(F, 'assets', 'styles.css'), 'w', encoding='utf-8').write(header + fonts + '\n' + css + EXTRA_CSS)
    for f in re.findall(r'url\("fonts/([^"]+)"\)', fonts): shutil.copy(os.path.join(WEB, 'assets', 'fonts', f), os.path.join(F, 'assets', 'fonts', f))
    lic = ['Marcellus-OFL.txt', 'Albert_Sans-OFL.txt', 'Azeret_Mono-OFL.txt', 'Bodoni_Moda-OFL.txt', 'Figtree-OFL.txt']
    for l in lic: shutil.copy(os.path.join(WEB, 'assets', 'fonts', 'licence', l), os.path.join(F, 'assets', 'fonts', l))
    # ilustrace: lahvičky z ukázky, etikety ze slotů (značka + názvy produktů)
    fig = json.load(open(os.path.join(HERE, 'zdroje', 'salvej-ilustrace.json'), encoding='utf-8'))['figure']
    texts = re.findall(r'(<text[^>]*>)([^<]*)(</text>)', fig)
    assert len(texts) == 9
    rep = ['{{mark}}', '{{name}}', '{{sub}}'] * 3
    it = iter(rep)
    fig = re.sub(r'(<text[^>]*>)([^<]*)(</text>)', lambda m: m.group(1) + next(it) + m.group(3), fig)
    # každá lahvička bere svůj produkt: rozdělit SVG a obalit položky seznamu
    parts = re.findall(r'<svg class="g [^"]+".*?</svg>', fig, re.S); assert len(parts) == 3
    sprig = re.search(r'<svg class="sv-sprig".*?</svg>', fig, re.S).group(0)
    prods = ''.join('{{#products.%d}}%s{{/products.%d}}' % (k, p, k) for k, p in enumerate(parts))
    figure = ('<figure class="sv-vis">' + sprig + prods + '<span class="sv-shelf" aria-hidden="true"></span><figcaption class="sv-cap"><span>{{caption_left}}</span><span>{{caption_right}}</span></figcaption></figure>')
    open(os.path.join(F, 'index.html'), 'w', encoding='utf-8').write(HTML.replace('__FIGURE__', figure))
    t = {'id': 'kosmetika', 'name': 'Kosmetický salon', 'basedOn': 'ukázka Šalvěj (web/ukazky/salon-salvej.html)',
         'obor': ['kosmetika', 'kosmetický', 'kosmetička', 'pleť', 'obočí', 'řasy', 'nehty', 'manikúra', 'pedikúra', 'wellness', 'masáže', 'beauty', 'spa', 'lash'],
         'description': 'Jednostránkový web kosmetického salonu v duchu herbáře: centrovaná značka, úvod s ilustrací lahviček se značkou salonu, etiketa s otevírací dobou, služby jako číslované receptury s délkou a cenou, rezervace telefonem a e-mailem, adresa a otevírací doba. Tmavý režim podle zařízení.',
         'render': ['index.html', 'assets/styles.css'],
         'tokens': dict({k: {'var': v[0], 'type': v[1], 'value': v[2], 'label': v[3]} for k, v in TOKENS.items()}, **{k: {'var': v[0], 'type': 'font', 'value': v[1], 'options': v[2], 'label': v[3]} for k, v in FONTS.items()}),
         'slots': SLOTS, 'contrast': [['ink', 'ground'], ['ink2', 'ground'], ['bloom', 'ground'], ['ink', 'paper']], 'content': CONTENT,
         'licenses': ['assets/fonts/' + l for l in lic],
         'cssHints': ['.hb-mark', '.sv-hero h1', '.hb-lede', '.sv-forms h2', '.hb-label h3', '.web .btn', '.hb-chapter h2', '.sv-vis']}
    json.dump(t, open(os.path.join(OUT, 'template.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('šablona kosmetika:', sum(len(fs) for _, _, fs in os.walk(F)), 'souborů')


if __name__ == '__main__':
    main()
