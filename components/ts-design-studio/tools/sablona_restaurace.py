#!/usr/bin/env python3
"""Převod ukázky Kořen (web/ukazky/koren.html) na šablonu Studia „restaurace“ – jednostránkový web bez kitu a bez ukázkové lišty.
Styly z koren/style.css (barvy a písma jako tokeny), písma Alegreya / Alegreya Sans (OFL) i s licencí, obsah ze slotů.
Spouštět při změně zdrojové ukázky:  python3 -I tools/sablona_restaurace.py /cesta/k/projektu/web"""
import json, os, re, shutil, sys

WEB = sys.argv[1] if len(sys.argv) > 1 else '/Users/krystof/Downloads/T&S webstudio_28-10/web'
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'templates', 'restaurace')
F = os.path.join(OUT, 'files')

TOKENS = {
    'bg': ('--bg', 'color', '#201913', 'Pozadí'), 'bg2': ('--bg-2', 'color', '#2b221b', 'Pozadí karet'), 'bg3': ('--bg-3', 'color', '#3a2e24', 'Pozadí – třetí vrstva'),
    'deep': ('--deep', 'color', '#17120e', 'Tmavá sekce'), 'deeper': ('--deeper', 'color', '#120e0b', 'Nejtmavší sekce'),
    'ink': ('--ink', 'color', '#e7e3da', 'Text'), 'ink2': ('--ink-2', 'color', '#cfc4b1', 'Text – doplňkový'), 'muted': ('--muted', 'color', '#9c8f7b', 'Text – tlumený'),
    'accent': ('--wax', 'color', '#c98a2b', 'Akcent (tlačítka, čáry)'), 'band': ('--wine', 'color', '#5b1a2a', 'Pás s citací'), 'band_ink': ('--wine-ink', 'color', '#f2e6e8', 'Text v pásu'),
}
FONTS = {
    'f_disp': ('--f-disp', '"Alegreya",Georgia,"Times New Roman",serif', ['"Alegreya",Georgia,"Times New Roman",serif', 'Georgia,"Times New Roman",serif', '"Iowan Old Style","Palatino Linotype",Palatino,serif'], 'Písmo nadpisů'),
    'f_body': ('--f-body', '"Alegreya Sans","Gill Sans","Trebuchet MS",system-ui,sans-serif', ['"Alegreya Sans","Gill Sans","Trebuchet MS",system-ui,sans-serif', 'system-ui,-apple-system,"Segoe UI",sans-serif'], 'Písmo textu'),
}

SLOTS = {
    'lang': {'type': 'text', 'max': 5, 'label': 'Jazyk stránky (cs, en…)'},
    'brand': {'type': 'text', 'max': 40, 'label': 'Název podniku'},
    'title': {'type': 'text', 'max': 70, 'label': 'Titulek stránky (prohlížeč, vyhledávače)'},
    'description': {'type': 'text', 'max': 160, 'label': 'Popis pro vyhledávače'},
    'status_text': {'type': 'text', 'max': 40, 'label': 'Štítek nad nadpisem (např. otevírací dny)'},
    'hero_title': {'type': 'text', 'max': 40, 'label': 'Hlavní nadpis – 1. řádek'},
    'hero_em': {'type': 'text', 'max': 60, 'label': 'Hlavní nadpis – zvýrazněný 2. řádek'},
    'hero_lead': {'type': 'multiline', 'max': 320, 'label': 'Úvodní odstavec'},
    'hero_caption': {'type': 'text', 'max': 60, 'label': 'Popisek ilustrace'},
    'cta_label': {'type': 'text', 'max': 30, 'label': 'Text hlavního tlačítka'},
    'cta_url': {'type': 'url', 'label': 'Odkaz hlavního tlačítka (#rezervace, tel:…, https://…)'},
    'origin_label': {'type': 'text', 'max': 30, 'label': 'Sekce původ – štítek'},
    'origin_title': {'type': 'text', 'max': 60, 'label': 'Sekce původ – nadpis'},
    'suppliers': {'type': 'list', 'items': 6, 'label': 'Dodavatelé / body původu', 'fields': {'km': {'type': 'text', 'max': 12}, 'name': {'type': 'text', 'max': 40}, 'what': {'type': 'text', 'max': 60}}},
    'table_label': {'type': 'text', 'max': 30, 'label': 'Sekce nabídka – štítek'},
    'table_title': {'type': 'text', 'max': 60, 'label': 'Sekce nabídka – nadpis'},
    'menu_title': {'type': 'text', 'max': 40, 'label': 'Karta menu – nadpis'},
    'dishes': {'type': 'list', 'items': 4, 'label': 'Ukázka jídel', 'fields': {'name': {'type': 'text', 'max': 60}, 'price': {'type': 'text', 'max': 20}}},
    'tasting_title': {'type': 'text', 'max': 40, 'label': 'Karta degustace – nadpis'},
    'tasting_price': {'type': 'text', 'max': 60, 'label': 'Karta degustace – cena'},
    'tasting_days': {'type': 'text', 'max': 90, 'label': 'Karta degustace – kdy'},
    'reserve_title': {'type': 'text', 'max': 40, 'label': 'Karta rezervace – nadpis'},
    'reserve_text': {'type': 'text', 'max': 160, 'label': 'Karta rezervace – text'},
    'quote_label': {'type': 'text', 'max': 50, 'label': 'Citace – autor'},
    'quote_1': {'type': 'text', 'max': 40, 'label': 'Citace – 1. řádek'},
    'quote_2': {'type': 'text', 'max': 40, 'label': 'Citace – 2. řádek (zvýrazněný)'},
    'quote_text': {'type': 'multiline', 'max': 300, 'label': 'Text vedle citace'},
    'hours': {'type': 'list', 'items': 7, 'label': 'Otevírací doba', 'fields': {'days': {'type': 'text', 'max': 30}, 'time': {'type': 'text', 'max': 30}}},
    'hours_note': {'type': 'text', 'max': 160, 'label': 'Poznámka k otevírací době (volitelné)'},
    'street': {'type': 'text', 'max': 60, 'label': 'Ulice a číslo'},
    'city': {'type': 'text', 'max': 60, 'label': 'PSČ a obec'},
    'phone': {'type': 'phone', 'label': 'Telefon'},
    'phone_href': {'type': 'url', 'label': 'Telefon jako odkaz (tel:+420…)'},
    'email': {'type': 'email', 'label': 'E-mail'},
    'map_url': {'type': 'url', 'label': 'Odkaz na mapu (volitelné)'},
    'company': {'type': 'text', 'max': 80, 'label': 'Patička – provozovatel (© …)'},
}
CONTENT = {
    'lang': 'cs', 'brand': 'Kořen', 'title': 'Kořen bistro & vinárna', 'description': 'Sezónní česká kuchyně z toho, co roste do sta kilometrů od Olomouce. Moravská vína, chléb z vlastního kvasu.',
    'status_text': 'Út–So od 11:30', 'hero_title': 'Dnes vaříme', 'hero_em': 'z toho, co roste kolem.',
    'hero_lead': 'Sezónní česká kuchyně z toho, co roste a zraje do sta kilometrů od Olomouce. Moravská vína, chléb z vlastního kvasu. Každého dodavatele uvádíme jménem.',
    'hero_caption': 'Červená řepa · Bystročice · 9 km', 'cta_label': 'Rezervovat stůl', 'cta_url': '#navsteva',
    'origin_label': 'Od kořene', 'origin_title': 'Kdo pěstuje vaši večeři.',
    'suppliers': [{'km': '9 km', 'name': 'Bystročice', 'what': 'zelenina, kořeny, bylinky'}, {'km': '24 km', 'name': 'Farma Doubravka', 'what': 'hovězí, telecí, vejce'},
                  {'km': '31 km', 'name': 'Štěpánov', 'what': 'kozí a ovčí sýry'}, {'km': '62 km', 'name': 'Pstruhařství Hané', 'what': 'pstruh, siven'},
                  {'km': '96 km', 'name': 'Mikulovsko a Slovácko', 'what': '40 vín od malých vinařů'}],
    'table_label': 'Na stole', 'table_title': 'Menu, degustace, stůl.', 'menu_title': 'Podzimní menu',
    'dishes': [{'name': 'Polévka z pečené dýně, kozí sýr', 'price': '145 Kč'}, {'name': 'Hovězí líčka, kořenová zelenina', 'price': '385 Kč'}, {'name': 'Švestkové knedlíky, tvaroh', 'price': '165 Kč'}],
    'tasting_title': 'Sedm chodů podzimu', 'tasting_price': '1 890 Kč · víno + 990 Kč', 'tasting_days': 'Čtvrtek až sobota od 18:00, 7 chodů.',
    'reserve_title': 'Stůl pro vás', 'reserve_text': 'Rezervace telefonem nebo e-mailem pro 1–8 hostů, větší skupinám rádi připravíme nabídku.',
    'quote_label': 'Šéfkuchař Ondřej Hanák', 'quote_1': '„Vaříme to, co nám', 'quote_2': 'ráno přivezou.“',
    'quote_text': 'Když na Hané dozrají švestky, voní jimi celý podnik. Chléb pečeme dvakrát denně z kvasu, který krmíme už šestý rok.',
    'hours': [{'days': 'Úterý až čtvrtek', 'time': '11:30–22:00'}, {'days': 'Pátek a sobota', 'time': '11:30–23:00'}, {'days': 'Neděle a pondělí', 'time': 'zavřeno'}],
    'hours_note': '', 'street': 'Ztracená 14', 'city': '779 00 Olomouc', 'phone': '+420 585 000 444', 'phone_href': 'tel:+420585000444', 'email': 'stul@koren-bistro.cz', 'map_url': '',
    'company': '© 2026 Kořen bistro s.r.o.',
}

MOON = '<svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/></svg>'
SUN = '<svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></svg>'
NAV = '<a href="#top" aria-current="page">Dnes</a><a href="#puvod">Původ</a><a href="#nabidka">Nabídka</a><a href="#navsteva">Návštěva</a>'
HTML = '''<!doctype html>
<html lang="{{lang}}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{title}}</title>
<meta name="description" content="{{description}}">
<link rel="stylesheet" href="assets/styles.css">
</head>
<body class="p-index" id="top">
<a class="skip" href="#main">Přeskočit na obsah</a>
<div class="frame">
<aside class="rail" aria-label="{{brand}}">
  <a class="brand" href="#top">{{brand}}</a>
  <nav aria-label="Hlavní menu">__NAV__</nav>
  <div class="rb"><a class="btn" href="{{cta_url}}">{{cta_label}}</a><div class="row"><small>{{lang}}</small><button class="mode-btn" type="button" data-mode-btn aria-label="Přepnout režim">__MOON____SUN__</button></div></div>
</aside>
<div class="main">
<header class="mbar"><a class="brand" href="#top">{{brand}}</a><div class="r"><button class="mode-btn" type="button" data-mode-btn aria-label="Přepnout režim">__MOON____SUN__</button><button class="burger" id="burger" aria-expanded="false" aria-controls="nav" aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 8h16M4 16h16"/></svg></button></div>
  <nav id="nav" aria-label="Menu">__NAV__</nav></header>
<main id="main">
<section class="hero strata">
  <div>
    {{#status_text}}<span class="status"><i></i><span>{{status_text}}</span></span>{{/status_text}}
    <h1>{{hero_title}}<em>{{hero_em}}</em></h1>
    <p class="hero-lead">{{hero_lead}}</p>
    <a class="ticket-mini" href="{{cta_url}}"><div><span>{{reserve_title}}</span><b>{{cta_label}}</b></div><strong>Stůl</strong></a>
  </div>
  <div class="hv"><svg class="beet" viewBox="0 0 400 470" aria-hidden="true">
  <path class="body" d="M200 170c-80 0-130 60-130 130s60 120 130 120 130-50 130-120-50-130-130-130z"/>
  <path class="wax grow" style="--l:220;--d:.5s" d="M120 270c30-20 60-20 80-4M140 330c40-10 80-6 120 10M150 380c30 6 60 6 90-4"/>
  <path class="grow" style="--l:260;--d:.1s" d="M200 170c-20-60-10-110 24-150M200 170c40-50 90-64 140-56M200 170c-50-40-100-44-140-20"/>
  <path class="wax grow" style="--l:200;--d:.8s" d="M224 20c-30 20-40 50-30 90M340 114c-36 4-70 24-100 54M60 150c34-8 70 2 110 22"/>
  <path class="grow" style="--l:60;--d:1.2s" d="M200 420c0 20-6 30-14 40"/>
</svg>{{#hero_caption}}<span class="cap">{{hero_caption}}</span>{{/hero_caption}}</div>
</section>

{{#suppliers}}{{/suppliers}}<section class="sec soil" id="puvod">
  <div class="sh"><span class="label">{{origin_label}}</span><h2>{{origin_title}}</h2></div>
  <div class="layers">
    {{#suppliers}}<div class="ly"><span class="km">{{km}}</span><b>{{name}}</b><span>{{what}}</span></div>
    {{/suppliers}}
  </div>
</section>

<section class="sec teasers-sec" id="nabidka">
  <div class="sh"><span class="label">{{table_label}}</span><h2>{{table_title}}</h2></div>
  <div class="teasers">
    <div class="teaser"><span class="label">Stálý lístek</span><h2>{{menu_title}}</h2><ul class="t-dishes">{{#dishes}}<li><span>{{name}}</span><b>{{price}}</b></li>{{/dishes}}</ul></div>
    <div class="teaser"><span class="label">Degustační menu</span><h2>{{tasting_title}}</h2><p class="tz-price">{{tasting_price}}</p><p class="t-days">{{tasting_days}}</p></div>
    <a class="teaser" href="{{cta_url}}"><span class="label">Rezervace</span><h2>{{reserve_title}}</h2><p class="t-free">{{reserve_text}}</p><span class="t-more">{{cta_label}}</span></a>
  </div>
</section>

<section class="band">
  <div><span class="label">{{quote_label}}</span><h2>{{quote_1}}<br><em>{{quote_2}}</em></h2></div>
  <div><p class="band-text">{{quote_text}}</p></div>
</section>

<section class="sec" id="navsteva">
  <div class="visit">
    <div><span class="label">Otevírací doba</span><dl class="hours">{{#hours}}<dt>{{days}}</dt><dd>{{time}}</dd>{{/hours}}</dl>{{#hours_note}}<p class="closure">{{hours_note}}</p>{{/hours_note}}</div>
    <div><span class="label">Kde nás najdete</span><div class="addr"><b>{{street}}, {{city}}</b><span><a href="{{phone_href}}">{{phone}}</a></span><span><a href="mailto:{{email}}">{{email}}</a></span></div>
      <div class="hero-cta"><a class="btn" href="{{cta_url}}">{{cta_label}}</a>{{#map_url}}<a class="btn btn--ghost" href="{{map_url}}">Jak k nám</a>{{/map_url}}</div></div>
  </div>
</section>
</main>
<footer class="site">
  <a class="brand" href="#top">{{brand}}</a>
  <p>{{street}}, {{city}} · {{phone}} · {{email}}</p>
  <nav aria-label="Patička">__NAV__</nav>
  <p>{{company}}</p>
</footer>
</div>
</div>
<a class="btn mbook" href="{{cta_url}}">{{cta_label}}</a>
<script src="assets/app.js"></script>
</body>
</html>
'''.replace('__NAV__', NAV).replace('__MOON__', MOON).replace('__SUN__', SUN).replace('{{#suppliers}}{{/suppliers}}', '')
APP = '''/* Šablona „restaurace“ – menu na mobilu a přepínač světlého/tmavého režimu (bez ukládání, bez síťových požadavků). */
(function () {
  var root = document.documentElement, btn = document.getElementById('burger'), nav = document.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); } });
  }
  function label() { var l = root.getAttribute('data-theme') === 'light' ? 'Přepnout na tmavý režim' : 'Přepnout na světlý režim'; document.querySelectorAll('[data-mode-btn]').forEach(function (b) { b.setAttribute('aria-label', l); b.title = l; }); }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-mode-btn]')) return;
    if (root.getAttribute('data-theme') === 'light') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', 'light');
    label();
  });
  label();
})();
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(os.path.join(F, 'assets', 'fonts'))
    css = open(os.path.join(WEB, 'ukazky', 'koren', 'style.css'), encoding='utf-8').read()
    m = re.search(r':root\{(.*?)\}', css, re.S); body = m.group(1)
    keep = []
    for decl in [d.strip() for d in body.split(';') if d.strip()]:
        name = decl.split(':', 1)[0].strip()
        if name in [t[0] for t in TOKENS.values()] + [f[0] for f in FONTS.values()]: continue
        keep.append(decl)
    tok = ';'.join(['%s:%s' % (t[0], t[2]) for t in TOKENS.values()] + ['%s:%s' % (f[0], f[1]) for f in FONTS.values()])
    header = '/* Šablona „restaurace“ (T&S Design Studio) – styly převzaté z ukázky Kořen. Barvy a písma mění Studio v bloku @tokens. */\n'
    fonts = open(os.path.join(WEB, 'assets', 'fonts', 'koren.css'), encoding='utf-8').read()
    fonts = re.sub(r'url\("(koren-[^"]+)"\)', r'url("fonts/\1")', '\n'.join(l for l in fonts.split('\n') if l.startswith('@font-face')))
    css = css[:m.start()] + '/*@tokens*/:root{' + tok + '}/*@end*/\n:root{' + ';'.join(keep) + '}' + css[m.end():]
    css += '\n/* šablona: přeskakovací odkaz, text v pásu */\n.skip{position:absolute;left:-9999px;top:0}.skip:focus{left:12px;top:12px;z-index:99;background:var(--wax);color:var(--deeper);padding:.5rem .8rem}\n.band-text{max-width:24em;color:var(--wine-ink)}\n'
    open(os.path.join(F, 'assets', 'styles.css'), 'w', encoding='utf-8').write(header + fonts + '\n' + css)
    for f in re.findall(r'url\("fonts/(koren-[^"]+)"\)', fonts): shutil.copy(os.path.join(WEB, 'assets', 'fonts', f), os.path.join(F, 'assets', 'fonts', f))
    for lic in ('Alegreya-OFL.txt', 'Alegreya_Sans-OFL.txt'): shutil.copy(os.path.join(WEB, 'assets', 'fonts', 'licence', lic), os.path.join(F, 'assets', 'fonts', lic))
    open(os.path.join(F, 'index.html'), 'w', encoding='utf-8').write(HTML)
    open(os.path.join(F, 'assets', 'app.js'), 'w', encoding='utf-8').write(APP)
    t = {'id': 'restaurace', 'name': 'Restaurace a bistro', 'basedOn': 'ukázka Kořen (web/ukazky/koren.html)', 'obor': ['restaurace', 'bistro', 'vinárna', 'kavárna', 'jídelna', 'pivnice'],
         'description': 'Jednostránkový web restaurace: tmavý „řez půdou“, svislé menu, dodavatelé s kilometry, ukázka menu a degustace, citace šéfkuchaře, otevírací doba a kontakt. Světlý režim tlačítkem.',
         'render': ['index.html', 'assets/styles.css'],
         'tokens': dict({k: {'var': v[0], 'type': v[1], 'value': v[2], 'label': v[3]} for k, v in TOKENS.items()}, **{k: {'var': v[0], 'type': 'font', 'value': v[1], 'options': v[2], 'label': v[3]} for k, v in FONTS.items()}),
         'slots': SLOTS, 'contrast': [['ink', 'bg'], ['muted', 'bg'], ['band_ink', 'band'], ['ink', 'deeper']], 'content': CONTENT,
         'licenses': ['assets/fonts/Alegreya-OFL.txt', 'assets/fonts/Alegreya_Sans-OFL.txt']}
    json.dump(t, open(os.path.join(OUT, 'template.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('šablona restaurace:', sum(len(fs) for _, _, fs in os.walk(F)), 'souborů')


if __name__ == '__main__':
    main()
