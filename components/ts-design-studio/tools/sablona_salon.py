#!/usr/bin/env python3
"""Převod ukázky Lokna (web/ukazky/lokna.html) na šablonu Studia „salon“ – jednostránkový web jako módní časopis, bez kitu a bez ukázkové lišty.
Styly z lokna/style.css (vrstva LOKNA 2026, bez dokladu a administrace), barvy a písma jako tokeny, písma Syne / Sora a DM Serif / Outfit (OFL) i s licencemi.
Kresby (obálka, portrét) jsou zachycené z vykreslené ukázky v tools/zdroje/lokna-kresby.json – bez plátna a bez obrázků z JS.
Spouštět při změně zdrojové ukázky:  python3 -I tools/sablona_salon.py /cesta/k/projektu/web"""
import json, os, re, shutil, sys

WEB = sys.argv[1] if len(sys.argv) > 1 else '/Users/krystof/Downloads/T&S webstudio_28-10/web'
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, '..', 'templates', 'salon')
F = os.path.join(OUT, 'files')

TOKENS = {
    'paper': ('--paper', 'color', '#fafafa', 'Pozadí (papír)'), 'paper2': ('--paper-2', 'color', '#f0f0f0', 'Pozadí – druhá vrstva'), 'blush': ('--blush', 'color', '#ebebeb', 'Pozadí – třetí vrstva'),
    'ink': ('--ink', 'color', '#0d0d0d', 'Text a linky'), 'ink2': ('--ink-2', 'color', '#2a2a2a', 'Text – doplňkový'), 'muted': ('--muted', 'color', '#6b6b6b', 'Text – tlumený'),
    'hair': ('--hair', 'color', '#d2d2d2', 'Vlasové linky'), 'accent': ('--ac', 'color', '#7a1e2c', 'Sezónní akcent (světlý režim)'), 'accent_dark': ('--ac-dark', 'color', '#e05a70', 'Sezónní akcent (tmavý režim)'),
    'art_bg': ('--cv-bg', 'color', '#161616', 'Pozadí kresby na obálce'),
}
FONTS = {
    'f_disp': ('--f-disp', '"Syne","Arial Black",system-ui,sans-serif', ['"Syne","Arial Black",system-ui,sans-serif', '"DM Serif Display","Didot","Bodoni 72",Georgia,serif'], 'Písmo nadpisů a logotypu'),
    'f_body': ('--f-body', '"Sora",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', ['"Sora",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', '"Outfit",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'], 'Písmo textu'),
}

T = lambda mx, label: {'type': 'text', 'max': mx, 'label': label}
SLOTS = {
    'lang': T(5, 'Jazyk stránky (cs, en…)'),
    'brand': T(40, 'Název salonu'),
    'title': T(70, 'Titulek stránky (prohlížeč, vyhledávače)'),
    'description': T(160, 'Popis pro vyhledávače'),
    'logo_pre': T(10, 'Logotyp – začátek'), 'logo_accent': T(3, 'Logotyp – zvýrazněné písmeno (volitelné)'), 'logo_post': T(10, 'Logotyp – konec'),
    'logo_text': T(120, 'Logotyp pro čtečky (celý název a popis)'),
    'issue': T(30, 'Záhlaví – vydání (např. Vydání 07)'), 'season': T(30, 'Záhlaví – období (např. Podzim 2026)'), 'where': T(70, 'Záhlaví – obor a místo'),
    'cta_label': T(30, 'Text hlavního tlačítka'), 'cta_url': {'type': 'url', 'label': 'Odkaz hlavního tlačítka (#rezervace, tel:…, https://…)'},
    'cover_quote': T(90, 'Obálka – citát'), 'cover_quote_by': T(50, 'Obálka – autor citátu'),
    'cover_price_title': T(50, 'Obálka – ceník, nadpis'), 'cover_price_note': T(50, 'Obálka – ceník, poznámka'),
    'cover_shades': T(60, 'Obálka – vzorník, nadpis'), 'cover_book_title': T(50, 'Obálka – rezervace, nadpis'), 'cover_book_note': T(60, 'Obálka – rezervace, poznámka'),
    'cover_caption': T(50, 'Obálka – popisek kresby'), 'cover_strip': T(60, 'Obálka – svislý pruh (služby)'),
    'toc_services': T(50, 'Obsah – služby, podtitulek'), 'toc_team': T(50, 'Obsah – tým, podtitulek'), 'hours_short': T(50, 'Otevírací doba stručně'),
    'ed_running': T(60, 'Úvodník – záhlaví (obor, místo, rok)'), 'ed_title': T(50, 'Úvodník – nadpis'), 'ed_em': T(40, 'Úvodník – zvýrazněný konec nadpisu'),
    'ed_lead': {'type': 'multiline', 'max': 320, 'label': 'Úvodník – odstavec'}, 'ed_pull': T(80, 'Úvodník – citát'), 'ed_pull_by': T(50, 'Úvodník – autor citátu'),
    'address_short': T(50, 'Adresa stručně'),
    'price_title': T(30, 'Ceník – nadpis'), 'price_em': T(30, 'Ceník – zvýrazněné slovo'), 'price_title_end': T(30, 'Ceník – konec nadpisu'),
    'price_text': {'type': 'multiline', 'max': 260, 'label': 'Ceník – úvodní text'},
    'col_1': T(16, 'Ceník – 1. sloupec (např. Krátké)'), 'col_2': T(16, 'Ceník – 2. sloupec'), 'col_3': T(16, 'Ceník – 3. sloupec'),
    'prices': {'type': 'list', 'items': 8, 'label': 'Ceník – řádky', 'fields': {'name': T(40, ''), 'p1': T(16, ''), 'p2': T(16, ''), 'p3': T(16, '')}},
    'book_title': T(30, 'Rezervace – nadpis'), 'book_em': T(20, 'Rezervace – zvýrazněné slovo'), 'book_title_end': T(30, 'Rezervace – konec nadpisu'),
    'book_text': {'type': 'multiline', 'max': 260, 'label': 'Rezervace – text'}, 'book_note': T(160, 'Rezervace – poznámka pod kontakty'),
    'shades_title': T(40, 'Vzorník – nadpis'), 'shades_em': T(30, 'Vzorník – zvýrazněné slovo'), 'shades_text': {'type': 'multiline', 'max': 260, 'label': 'Vzorník – text'},
    'shades': {'type': 'list', 'items': 8, 'label': 'Vzorník – odstíny', 'fields': {'code': T(8, ''), 'name': T(30, ''), 'top': {'type': 'color'}, 'bottom': {'type': 'color'}, 'light_text': T(3, 'Světlý text na tmavém odstínu (ano = cokoli, ne = prázdné)')}},
    'team_title': T(40, 'Tým – nadpis'), 'team_em': T(30, 'Tým – zvýrazněný konec'), 'team_text': {'type': 'multiline', 'max': 260, 'label': 'Tým – text'},
    'team': {'type': 'list', 'items': 4, 'label': 'Tým', 'fields': {'role': T(50, ''), 'name': T(40, ''), 'quote': T(100, ''), 'bio': T(220, ''), 'days': T(50, '')}},
    'quote_big': T(140, 'Recenze – velký citát'),
    'reviews': {'type': 'list', 'items': 3, 'label': 'Recenze', 'fields': {'text': T(220, ''), 'by': T(40, '')}},
    'area': T(30, 'Návštěva – čtvrť / místo'),
    'address': T(80, 'Adresa celá'), 'transport': T(160, 'Jak k nám (doprava, volitelné)'), 'map_url': {'type': 'url', 'label': 'Odkaz na mapu (volitelné)'},
    'hours': {'type': 'list', 'items': 7, 'label': 'Otevírací doba', 'fields': {'days': T(30, ''), 'time': T(30, '')}},
    'phone': {'type': 'phone', 'label': 'Telefon'}, 'phone_href': {'type': 'url', 'label': 'Telefon jako odkaz (tel:+420…)'},
    'email': {'type': 'email', 'label': 'E-mail'}, 'instagram': T(40, 'Instagram (volitelné)'),
    'company': T(100, 'Patička – provozovatel (© …, IČO)'),
}
SH = lambda c, n, a, b, lt='': {'code': c, 'name': n, 'top': a, 'bottom': b, 'light_text': lt}
CONTENT = {
    'lang': 'cs', 'brand': 'Lokna hair studio', 'title': 'Lokna hair studio', 'description': 'Butikové kadeřnictví na Vinohradech. Střih, barva a péče bez spěchu, vždy s konzultací. Cena podle délky vlasů.',
    'logo_pre': 'L', 'logo_accent': 'O', 'logo_post': 'KNA', 'logo_text': 'Lokna hair studio – butikové kadeřnictví na Vinohradech',
    'issue': 'Vydání 07', 'season': 'Podzim 2026', 'where': 'Hair studio · Mánesova 28 · Vinohrady',
    'cta_label': 'Rezervovat termín', 'cta_url': '#rezervace',
    'cover_quote': '„Dobrá barva odrůstá tak, že si toho nikdo nevšimne.“', 'cover_quote_by': 'Tereza Malá, zakladatelka',
    'cover_price_title': 'Cena podle délky vašich vlasů', 'cover_price_note': 'Dámský střih od 790 Kč',
    'cover_shades': 'Osm odstínů, které letos milujeme', 'cover_book_title': 'Volné termíny každý týden', 'cover_book_note': 'Telefonem, e-mailem nebo osobně',
    'cover_caption': 'Na obálce: 7.43 Karamelová', 'cover_strip': 'Balayage · Keratin · Střih · Barva',
    'toc_services': 'střih, barva, péče, účesy', 'toc_team': 'Tereza, Klára, Denisa', 'hours_short': 'Út–Pá 9–20, Sobota 9–15',
    'ed_running': 'Hair studio · Vinohrady · od roku 2019', 'ed_title': 'Vlasy, ve kterých jste', 'ed_em': 'sama sebou.',
    'ed_lead': 'Butikové kadeřnictví pro ženy, které chtějí účes podle svých vlasů, ne podle posledního trendu. Střih, barva a péče bez spěchu, vždy s konzultací.',
    'ed_pull': '„Nejdřív si povídáme, pak stříháme.“', 'ed_pull_by': 'Tereza Malá, zakladatelka', 'address_short': 'Mánesova 28, Praha 2',
    'price_title': 'Cena podle', 'price_em': 'vašich', 'price_title_end': 'vlasů',
    'price_text': 'Cena se řídí délkou vašich vlasů a vždy v ní je mytí, masáž hlavy a foukaná. Konzultace barvy je zdarma.',
    'col_1': 'Krátké', 'col_2': 'Polodlouhé', 'col_3': 'Dlouhé',
    'prices': [{'name': 'Dámský střih', 'p1': '790 Kč', 'p2': '890 Kč', 'p3': '990 Kč'}, {'name': 'Barvení odrostu', 'p1': '1 190 Kč', 'p2': '1 290 Kč', 'p3': '1 390 Kč'},
               {'name': 'Celobarva', 'p1': '1 390 Kč', 'p2': '1 690 Kč', 'p3': '1 990 Kč'}, {'name': 'Balayage / airtouch', 'p1': '2 990 Kč', 'p2': '3 590 Kč', 'p3': '4 290 Kč'},
               {'name': 'Foukaná a styling', 'p1': '490 Kč', 'p2': '590 Kč', 'p3': '690 Kč'}],
    'book_title': 'Rezervujte si', 'book_em': 'svůj', 'book_title_end': 'termín',
    'book_text': 'Zavolejte nebo napište, s jakou službou a jak dlouhými vlasy přijdete. Termín potvrdíme ještě týž den.',
    'book_note': 'Termín můžete zrušit zdarma do 24 hodin předem.',
    'shades_title': 'Odstíny, které u nás', 'shades_em': 'milujete', 'shades_text': 'Osm odstínů, které mícháme nejčastěji. Na konzultaci vám každý ukážeme na prameni vlasů.',
    'shades': [SH('10.1', 'Platinová blond', '#f2ebdd', '#cfc4b0'), SH('9.3', 'Zlatá blond', '#efd49a', '#c9a160'), SH('8.34', 'Medová měď', '#e0a86a', '#b36b35', 'ano'),
               SH('7.43', 'Karamelová', '#c98f5a', '#8a5a3c', 'ano'), SH('6.66', 'Třešňová', '#a8374a', '#5e1a28', 'ano'), SH('5.0', 'Oříšková hnědá', '#7a5238', '#4a2f1f', 'ano'),
               SH('4.15', 'Čokoládová', '#5a3a2e', '#2e1c17', 'ano'), SH('1.0', 'Černá', '#2b2426', '#0e0b0c', 'ano')],
    'team_title': 'Tři stylistky,', 'team_em': 'tři rukopisy', 'team_text': 'Každá z nás má svou specializaci. Objednat se můžete ke konkrétní stylistce, nebo nechte výběr na nás.',
    'team': [{'role': 'Zakladatelka · barvy & balayage', 'name': 'Tereza Malá', 'quote': '„Dobrá barva odrůstá tak, že si toho nikdo nevšimne.“', 'bio': '14 let u křesla, školení v Londýně a Miláně. Specialistka na přirozené přechody a opravy barev.', 'days': 'V salonu: út–so'},
             {'role': 'Střihy · krátké vlasy', 'name': 'Klára Nováková', 'quote': '„Střih je dobrý, když ho doma zvládnete bez žehličky.“', 'bio': 'Bob, pixie a mikado. Stříhá tak, aby účes držel tvar i po šesti týdnech a doma ho zvládla foukaná za pět minut.', 'days': 'V salonu: út–pá'},
             {'role': 'Účesy · péče & regenerace', 'name': 'Denisa Vlková', 'quote': '„Zkouška účesu vám vezme půlku svatební nervozity.“', 'bio': 'Svatební a společenské účesy, keratinové narovnání a obnova vlasových vazeb. Ke svatebnímu účesu vždy zkouška předem.', 'days': 'V salonu: st–so'}],
    'quote_big': 'Poprvé za deset let odcházím s barvou, která vypadá, jako bych se s ní narodila.“',
    'reviews': [{'text': 'Klára mi ostříhala bob, který drží i ráno po probuzení. Konečně nemusím každý den žehlit.', 'by': 'Markéta, Vinohrady'},
                {'text': 'Svatební účes vydržel od obřadu do tří do rána. A zkouška týden předem mi vzala všechnu nervozitu.', 'by': 'Anna, Žižkov'}],
    'area': 'Vinohrady', 'address': 'Mánesova 28, 120 00 Praha 2', 'transport': 'Tramvaj 11 a 13, zastávka Vinohradská tržnice, odtud 3 minuty pěšky.', 'map_url': '',
    'hours': [{'days': 'Pondělí', 'time': 'zavřeno'}, {'days': 'Út – Pá', 'time': '9:00 – 20:00'}, {'days': 'Sobota', 'time': '9:00 – 15:00'}, {'days': 'Neděle', 'time': 'zavřeno'}],
    'phone': '+420 603 000 888', 'phone_href': 'tel:+420603000888', 'email': 'ahoj@lokna-studio.cz', 'instagram': '@lokna.studio',
    'company': '© 2026 Lokna hair studio · Tereza Malá, IČO 09876543',
}

MOON = '<svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/></svg>'
SUN = '<svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></svg>'
ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
TOC = [('cenik', '04', 'Služby a ceník'), ('odstiny', '12', 'Odstíny'), ('tym', '18', 'Tým'), ('rezervace', '26', 'Rezervace'), ('navsteva', '32', 'Návštěva')]
MENU = ''.join('<a href="#%s"><i>%s</i><span>%s</span></a>' % t for t in TOC)
FOOTNAV = ''.join('<a href="#%s"><i>%s</i>%s</a>' % t for t in TOC)
HOURS = '{{#hours}}<dt>{{days}}</dt><dd>{{time}}</dd>{{/hours}}'

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
<body class="p-index">
__DEFS__
<a class="skip" href="#main">Přeskočit na obsah</a>
<header class="mast" id="top">
  <div class="wrap mast-line">
    <span class="mast-issue">{{issue}}{{#season}}<span class="mast-season"> · {{season}}</span>{{/season}}</span>
    <span class="mast-where">{{where}}</span>
    <span class="mast-act">
      <a href="#obsah" class="mast-toc">Obsah</a>
      <a href="{{cta_url}}" class="mast-book">{{cta_label}}</a>
      <button class="mode-btn" type="button" data-mode-btn aria-label="Přepnout režim">__MOON____SUN__</button><button class="burger" id="burger" type="button" aria-expanded="false" aria-controls="menu"><span>Obsah</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 8h16M4 16h16"/></svg></button>
    </span>
  </div>
  <div class="wrap mast-bar">
    <a href="#top" class="word" aria-label="{{brand}} – úvod">{{logo_pre}}{{logo_accent}}{{logo_post}}</a>
    <nav class="toc" id="menu" aria-label="Obsah webu">
      <span class="toc-h" aria-hidden="true">V tomto čísle</span>
      <a href="#top" class="toc-home" aria-current="page"><i>01</i><span>Titulní strana</span></a>
      __MENU__
      <p class="toc-foot">{{address_short}}<br>{{hours_short}}</p>
    </nav>
  </div>
</header>

<main id="main">
<section class="cover" aria-labelledby="coverT">
  <div class="wrap">
    <h1 class="logotype" id="coverT"><span aria-hidden="true">{{logo_pre}}{{#logo_accent}}<span class="o">{{logo_accent}}</span>{{/logo_accent}}{{logo_post}}</span><span class="k-sr">{{logo_text}}</span></h1>
    <div class="cover-grid">
      <div class="cover-lines">
        <a class="cl" href="#tym"><span class="kicker">Rozhovor · <i>str. 18</i></span><span class="cl-t cl-q">{{cover_quote}}</span><span class="cl-s">{{cover_quote_by}}</span></a>
        <a class="cl" href="#cenik"><span class="kicker">Ceník · <i>str. 04</i></span><span class="cl-t">{{cover_price_title}}</span><span class="cl-s">{{cover_price_note}}</span></a>
      </div>
      <figure class="cover-art">
        __COVER__
        {{#cover_caption}}<figcaption><a href="#odstiny">{{cover_caption}}</a></figcaption>{{/cover_caption}}
        <span class="cover-strip" aria-hidden="true">{{cover_strip}}</span>
      </figure>
      <div class="cover-lines">
        <a class="cl" href="#odstiny"><span class="kicker">Vzorník · <i>str. 12</i></span><span class="cl-t"><em>{{cover_shades}}</em></span></a>
        <a class="cl" href="#rezervace"><span class="kicker">Rezervace · <i>str. 26</i></span><span class="cl-t">{{cover_book_title}}</span><span class="cl-s">{{cover_book_note}}</span></a>
      </div>
    </div>
  </div>
</section>

<section class="toc-sec" id="obsah" aria-labelledby="tocT">
  <div class="wrap toc-grid">
    <h2 id="tocT">V tomto<br><em>čísle</em></h2>
    <nav class="toc-big" aria-label="V tomto čísle">
      <a href="#cenik"><i>04</i><b>Služby a ceník</b><span>{{toc_services}}</span></a>
      <a href="#odstiny"><i>12</i><b>Odstíny</b><span>vzorník a péče o barvu</span></a>
      <a href="#tym"><i>18</i><b>Tým</b><span>{{toc_team}}</span></a>
      <a href="#rezervace"><i>26</i><b>Rezervace</b><span>telefonem a e-mailem</span></a>
      <a href="#navsteva"><i>32</i><b>Návštěva</b><span>{{hours_short}}</span></a>
    </nav>
  </div>
</section>

<section class="sec editorial" aria-labelledby="edT">
  <div class="wrap">
    <div class="runhead"><span>Úvodník</span><span class="rh-mid">{{ed_running}}</span><i>str. 02</i></div>
    <div class="ed-grid">
      <div>
        <h2 id="edT" class="ed-h">{{ed_title}} <em>{{ed_em}}</em></h2>
        {{#ed_pull}}<div class="ed-side"><blockquote class="pull">{{ed_pull}}<cite>{{ed_pull_by}}</cite></blockquote></div>{{/ed_pull}}
      </div>
      <div class="ed-body">
        <p class="dropcap ed-lead">{{ed_lead}}</p>
        <ul class="ed-meta">
          <li><span class="kicker">Adresa</span><span>{{address_short}}</span></li>
          <li><span class="kicker">Otevřeno</span><span>{{hours_short}}</span></li>
        </ul>
        <p class="ed-cta"><a href="{{cta_url}}" class="btn">{{cta_label}} __ARROW__</a><a href="#cenik" class="tlink">Ceník</a></p>
      </div>
    </div>
  </div>
</section>

<section class="sec price-sec" id="cenik" aria-labelledby="prT">
  <div class="wrap pr-grid">
    <div class="pr-intro">
      <span class="kicker">Ceník · <i>str. 04</i></span>
      <h2 id="prT">{{price_title}} <em>{{price_em}}</em> {{price_title_end}}</h2>
      <p class="dropcap">{{price_text}}</p>
    </div>
    <div class="pr-table">
      <table class="ptab ptab--teaser"><caption class="k-sr">Ceny podle délky vlasů</caption>
        <thead><tr><th scope="col">Služba</th><th scope="col" class="lc">{{col_1}}</th><th scope="col" class="lc on">{{col_2}}</th><th scope="col" class="lc">{{col_3}}</th></tr></thead>
        <tbody>{{#prices}}<tr><th scope="row">{{name}}</th><td class="pc"><span class="pl" aria-hidden="true">{{col_1}}</span>{{p1}}</td><td class="pc on"><span class="pl" aria-hidden="true">{{col_2}}</span>{{p2}}</td><td class="pc"><span class="pl" aria-hidden="true">{{col_3}}</span>{{p3}}</td></tr>{{/prices}}</tbody>
      </table>
    </div>
  </div>
</section>

<section class="sec quick-sec" id="rezervace" aria-labelledby="quickTitle">
  <div class="wrap">
    <div class="runhead"><span>Rezervace</span><span class="rh-mid">{{brand}}</span><i>str. 26</i></div>
    <div class="quick">
      <div class="quick-copy">
        <h2 id="quickTitle">{{book_title}} <em>{{book_em}}</em> {{book_title_end}}</h2>
        <p>{{book_text}}</p>
      </div>
      <div class="quick-slots">
        <ul class="next-list">
          <li><a class="next-slot" href="{{phone_href}}"><span class="d">Telefon</span><span class="t">{{phone}}</span><span class="w">__ARROW__</span></a></li>
          <li><a class="next-slot" href="mailto:{{email}}"><span class="d">E-mail</span><span class="t t--sm">{{email}}</span><span class="w">__ARROW__</span></a></li>
        </ul>
        {{#book_note}}<p class="quick-note">{{book_note}}</p>{{/book_note}}
      </div>
    </div>
  </div>
</section>

<section class="shade-spread" id="odstiny" aria-labelledby="shT">
  <div class="wrap spread-head">
    <span class="kicker">Vzorník · <i>str. 12</i></span>
    <h2 id="shT">{{shades_title}} <em>{{shades_em}}</em></h2>
    <p>{{shades_text}}</p>
  </div>
  <div class="bands">{{#shades}}<div class="band{{#light_text}} lt{{/light_text}}" style="background:linear-gradient(180deg,{{top}},{{bottom}})"><span class="b-c">{{code}}</span><span class="b-n">{{name}}</span></div>{{/shades}}</div>
</section>

<section class="sec team-sec" id="tym" aria-labelledby="tmT">
  <div class="wrap">
    <div class="runhead"><span>Tým</span><span class="rh-mid">{{toc_team}}</span><i>str. 18</i></div>
    <div class="team-head">
      <h2 id="tmT">{{team_title}} <em>{{team_em}}</em></h2>
      <p>{{team_text}}</p>
    </div>
    <div class="team">{{#team}}<article class="member"><div class="m-port"><svg class="pt" viewBox="0 0 300 380" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false"><use href="#portret"/></svg></div><p class="role">{{role}}</p><h3>{{name}}</h3><p class="m-q">{{quote}}</p><p class="m-bio">{{bio}}</p><p class="days">{{days}}</p><a class="m-link" href="{{cta_url}}">{{cta_label}} <span aria-hidden="true">→</span></a></article>{{/team}}</div>
  </div>
</section>

<section class="sec letters" aria-label="Napsali nám">
  <div class="wrap quotes">
    <p class="q-big">{{quote_big}}</p>
    <div class="q-small">
      <span class="kicker">Napsali nám</span>
      {{#reviews}}<blockquote>{{text}}<span class="q-by">{{by}}</span></blockquote>
      {{/reviews}}
    </div>
  </div>
</section>

<section class="sec visit-sec" id="navsteva" aria-labelledby="viT">
  <div class="wrap">
    <div class="runhead"><span id="viT">Návštěva</span><span class="rh-mid">{{area}}</span><i>str. 32</i></div>
    <div class="visit">
      <div><h3>Kde nás <em>najdete</em></h3><p>{{address}}{{#transport}}<br>{{transport}}{{/transport}}</p>{{#map_url}}<p class="visit-link"><a href="{{map_url}}">Mapa a jak k nám</a></p>{{/map_url}}</div>
      <div><h3>Otevřeno</h3><dl class="hours">__HOURS__</dl></div>
      <div><h3>Kontakt</h3><p><a href="{{phone_href}}">{{phone}}</a><br><a href="mailto:{{email}}">{{email}}</a>{{#instagram}}<br>Instagram {{instagram}}{{/instagram}}</p><p class="visit-link"><a href="{{cta_url}}">{{cta_label}}</a></p></div>
    </div>
  </div>
</section>
</main>

<footer class="site">
  <div class="wrap">
    <div class="colophon">
      <div>
        <h2 class="col-h">Tiráž</h2>
        <p class="foot-pub">Vydává <a class="foot-word" href="#top">{{brand}}</a></p>
        <p class="foot-addr">{{brand}} · {{address}}</p>
        <p class="foot-addr">{{phone}} · {{email}}{{#instagram}} · {{instagram}}{{/instagram}}</p>
      </div>
      <nav class="foot-nav" aria-label="Patička">
        <h2 class="col-h">Obsah</h2>
        __FOOTNAV__
      </nav>
      <div class="foot-hours">
        <h2 class="col-h">Otevřeno</h2>
        <dl>__HOURS__</dl>
      </div>
    </div>
  </div>
  <div class="back-word" aria-hidden="true">{{logo_pre}}{{logo_accent}}{{logo_post}}</div>
  <div class="wrap foot-row"><span>{{company}}</span></div>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
'''
APP = '''/* Šablona „salon“ – obsah na mobilu, přepínač světlého/tmavého režimu a logotyp přes celou šířku (bez ukládání, bez síťových požadavků). */
(function () {
  var root = document.documentElement, btn = document.getElementById('burger'), nav = document.getElementById('menu');
  if (btn && nav) {
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); root.classList.toggle('menu-open', o); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('open'); root.classList.remove('menu-open'); btn.setAttribute('aria-expanded', 'false'); } });
  }
  function label() { var l = root.getAttribute('data-theme') === 'dark' ? 'Přepnout na světlý režim' : 'Přepnout na tmavý režim'; document.querySelectorAll('[data-mode-btn]').forEach(function (b) { b.setAttribute('aria-label', l); b.title = l; }); }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-mode-btn]')) return;
    if (root.getAttribute('data-theme') === 'dark') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', 'dark');
    label();
  });
  label();
  /* delší název salonu: logotyp se zmenší, aby se vešel na šířku */
  var logo = document.querySelector('.logotype');
  function fit() { if (!logo) return; logo.style.fontSize = ''; var w = logo.parentNode.clientWidth, s = logo.scrollWidth; if (s > w) logo.style.fontSize = Math.floor(parseFloat(getComputedStyle(logo).fontSize) * w / s * 0.98) + 'px'; }
  fit(); window.addEventListener('resize', fit);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
})();
'''
EXTRA_CSS = '''
/* šablona: přístupnost, odstíny bez textur, rezervace bez rezervačního systému, portrét ze symbolu */
.k-sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.skip{position:absolute;left:-9999px;top:0}.skip:focus{left:12px;top:12px;z-index:99;background:var(--ink);color:var(--paper);padding:.5rem .8rem}
.svg-defs{position:absolute;width:0;height:0;overflow:hidden}
.band{cursor:default}
.next-slot{grid-template-columns:7.5rem minmax(0,1fr) auto}
.next-slot .t--sm{font-size:clamp(1.2rem,2.4vw,1.8rem);overflow-wrap:anywhere}
.m-port{display:block}
.visit a{color:inherit}
'''


def tokens_css():
    return ';'.join(['%s:%s' % (t[0], t[2]) for t in TOKENS.values()] + ['%s:%s' % (f[0], f[1]) for f in FONTS.values()])


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(os.path.join(F, 'assets', 'fonts'))
    css = open(os.path.join(WEB, 'ukazky', 'lokna', 'style.css'), encoding='utf-8').read()
    css = css[:css.index('/* ==========================================================================\n   Doklad')]  # bez dokladu a administrace
    # vrstva LOKNA 2026: barvy a písma z tokenů, tmavý režim bere akcent z tokenu --ac-dark
    i = css.index('   LOKNA 2026 –'); m = re.compile(r':root\{(.*?)\}', re.S).search(css, i)
    names = [t[0] for t in TOKENS.values()] + [f[0] for f in FONTS.values()]
    keep = [d.strip() for d in m.group(1).split(';') if d.strip() and d.split(':', 1)[0].strip() not in names]
    css = css[:m.start()] + '/*@tokens*/:root{' + tokens_css() + '}/*@end*/\n:root{' + ';'.join(keep) + '}' + css[m.end():]
    d = re.compile(r':root\[data-theme="dark"\]\{(.*?)\}', re.S).search(css, css.index('/*@end*/'))
    css = css[:d.start()] + d.group(0).replace('--ac:#E05A70', '--ac:var(--ac-dark)') + css[d.end():]
    assert '--ac:var(--ac-dark)' in css
    header = '/* Šablona „salon“ (T&S Design Studio) – styly převzaté z ukázky Lokna. Barvy a písma mění Studio v bloku @tokens; tmavý režim má vlastní paletu. */\n'
    fonts = open(os.path.join(WEB, 'assets', 'fonts', 'lokna.css'), encoding='utf-8').read()
    fonts = re.sub(r'url\("([a-z0-9-]+\.woff2)(\?v=[0-9a-f]+)?"\)', r'url("fonts/\1")', '\n'.join(l for l in fonts.split('\n') if l.startswith('@font-face')))
    open(os.path.join(F, 'assets', 'styles.css'), 'w', encoding='utf-8').write(header + fonts + '\n' + css + EXTRA_CSS)
    for f in re.findall(r'url\("fonts/([^"]+)"\)', fonts): shutil.copy(os.path.join(WEB, 'assets', 'fonts', f), os.path.join(F, 'assets', 'fonts', f))
    lic = ['Syne-OFL.txt', 'Sora-OFL.txt', 'DM_Serif_Display-OFL.txt', 'Outfit-OFL.txt']
    for l in lic: shutil.copy(os.path.join(WEB, 'assets', 'fonts', 'licence', l), os.path.join(F, 'assets', 'fonts', l))
    art = json.load(open(os.path.join(HERE, 'zdroje', 'lokna-kresby.json'), encoding='utf-8'))
    cover = art['cover'].replace(' style=""', '').replace('<g id="hairArt"></g>', '')
    # vlasy bez kresby z plátna: svislý přechod a pár pramenů místo jednolité plochy
    cover = cover.replace('</defs>', '<linearGradient id="cvHair" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#6e4329"/><stop offset=".35" stop-color="#c98f5a"/><stop offset=".55" stop-color="#e0b07a"/><stop offset="1" stop-color="#7a4a2e"/></linearGradient></defs>')
    cover = cover.replace('fill="#8a5a3c"></rect>', 'style="fill:url(#cvHair)"></rect><path d="M110 60C96 160 92 280 100 404M150 46C146 160 146 280 150 404M190 60C204 160 208 280 200 404M128 52C118 170 116 290 124 404M172 52C182 170 184 290 176 404" fill="none" stroke="#5a341f" stroke-opacity=".35" stroke-width="3"></path><ellipse cx="150" cy="130" rx="60" ry="80" fill="url(#cvShine)"></ellipse>')
    assert 'url(#cvHair)' in cover
    pt = re.sub(r'<image\b[^>]*>(</image>)?', '', art['portrait']).replace('pg-tereza', 'pt-a')
    pt = re.sub(r'^<svg class="pt" viewBox="0 0 300 380"[^>]*>', '<symbol id="portret" viewBox="0 0 300 380">', pt)[:-len('</svg>')] + '</symbol>'
    defs = '<svg class="svg-defs" aria-hidden="true" focusable="false">' + pt + '</svg>'
    html = (HTML.replace('__DEFS__', defs).replace('__COVER__', cover).replace('__MENU__', MENU).replace('__FOOTNAV__', FOOTNAV).replace('__HOURS__', HOURS)
            .replace('__MOON__', MOON).replace('__SUN__', SUN).replace('__ARROW__', ARROW))
    open(os.path.join(F, 'index.html'), 'w', encoding='utf-8').write(html)
    open(os.path.join(F, 'assets', 'app.js'), 'w', encoding='utf-8').write(APP)
    t = {'id': 'salon', 'name': 'Kadeřnictví a salon krásy', 'basedOn': 'ukázka Lokna (web/ukazky/lokna.html)', 'obor': ['kadeřnictví', 'barbershop', 'kosmetika', 'salon krásy', 'nehtové studio', 'masáže'],
         'description': 'Jednostránkový web jako módní časopis: obří logotyp, obsah s čísly stran, ceník podle délky vlasů, rezervace telefonem a e-mailem, vzorník odstínů, tým, recenze, návštěva a tiráž. Tmavý režim tlačítkem.',
         'render': ['index.html', 'assets/styles.css'],
         'tokens': dict({k: {'var': v[0], 'type': v[1], 'value': v[2], 'label': v[3]} for k, v in TOKENS.items()}, **{k: {'var': v[0], 'type': 'font', 'value': v[1], 'options': v[2], 'label': v[3]} for k, v in FONTS.items()}),
         'slots': SLOTS, 'contrast': [['ink', 'paper'], ['muted', 'paper'], ['ink2', 'paper2'], ['accent', 'paper']], 'content': CONTENT,
         'licenses': ['assets/fonts/' + l for l in lic]}
    json.dump(t, open(os.path.join(OUT, 'template.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('šablona salon:', sum(len(fs) for _, _, fs in os.walk(F)), 'souborů')


if __name__ == '__main__':
    main()
