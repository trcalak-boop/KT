#!/usr/bin/env python3
"""Převod ukázky Motoria (web/ukazky/motoria.html) na šablonu Studia „autosalon“ – jednostránkový web prodejce vozů bez kitu a bez ukázkové lišty.
Styly z motoria/style.css (bez administrace a e-mailů), barvy a písmo jako tokeny – i pro výchozí tmavý režim (pozadí a karty), písma Saira a Red Hat Mono (OFL) i s licencemi.
Vozy, kategorie a hero kreslí jedno SVG auta, barvu karoserie dodává obsah (slot typu color). Vyhledávací formulář a výpočty splátek (JS ukázky) šablona nemá.
Spouštět při změně zdrojové ukázky:  python3 -I tools/sablona_autosalon.py /cesta/k/projektu/web"""
import json, os, re, shutil, sys

WEB = sys.argv[1] if len(sys.argv) > 1 else '/Users/krystof/Downloads/T&S webstudio_28-10/web'
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'templates', 'autosalon')
F = os.path.join(OUT, 'files')

TOKENS = {
    'night': ('--night', 'color', '#15171a', 'Noční pás (hlavička, hero, patička)'), 'night2': ('--night-2', 'color', '#1d2024', 'Noční pás – druhý odstín'), 'steel': ('--steel', 'color', '#262a30', 'Ocel (rámečky, hover)'),
    'accent': ('--orange', 'color', '#ffb000', 'Akcent (tlačítka, štítky)'), 'accent2': ('--orange-2', 'color', '#ffc233', 'Akcent – světlejší (hover)'),
    'page_dark': ('--t-page', 'color', '#15171a', 'Pozadí stránky (tmavý režim – výchozí)'), 'card_dark': ('--t-card', 'color', '#1d2024', 'Karty (tmavý režim)'),
    'chrome': ('--chrome', 'color', '#eef0f2', 'Pozadí stránky (světlý režim)'), 'ink': ('--ink', 'color', '#15171a', 'Text (světlý režim)'), 'muted': ('--muted', 'color', '#5e646c', 'Text tlumený (světlý režim)'),
}
FONTS = {
    'f_disp': ('--f-disp', '"Saira","Arial Narrow","Helvetica Neue",Arial,sans-serif', ['"Saira","Arial Narrow","Helvetica Neue",Arial,sans-serif', '"Arial Narrow","Helvetica Neue",Arial,sans-serif'], 'Písmo nadpisů'),
    'f_body': ('--f-body', '"Saira",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', ['"Saira",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'], 'Písmo textu'),
}
T = lambda mx, label='': {'type': 'text', 'max': mx, 'label': label}
G = lambda mx, label='': {'type': 'text', 'max': mx, 'label': label, 'grounded': True}
SLOTS = {
    'lang': T(5, 'Jazyk stránky (cs, en…)'), 'brand': T(40, 'Název prodejce'), 'title': T(70, 'Titulek stránky (prohlížeč, vyhledávače)'), 'description': T(160, 'Popis pro vyhledávače'),
    'logo_pre': T(8, 'Logotyp – před kolem'), 'logo_post': T(10, 'Logotyp – za kolem'), 'logo_tag1': T(14, 'Logotyp – drobný text, 1. řádek'), 'logo_tag2': T(14, 'Logotyp – drobný text, 2. řádek'),
    'open_text': T(70, 'Lišta nahoře – otevírací doba'), 'address': T(70, 'Adresa'), 'phone': {'type': 'phone', 'label': 'Telefon'}, 'phone_href': {'type': 'url', 'label': 'Telefon jako odkaz (tel:+420…)'},
    'email': {'type': 'email', 'label': 'E-mail'}, 'cta_label': T(24, 'Hlavní tlačítko (např. Testovací jízda)'), 'cta_url': {'type': 'url', 'label': 'Odkaz hlavního tlačítka'},
    'hero_tag': T(50, 'Hero – štítek'), 'hero_title': T(24, 'Hero – nadpis, 1. část'), 'hero_em': T(24, 'Hero – nadpis, zvýrazněná část'), 'hero_lead': {'type': 'multiline', 'max': 280, 'label': 'Hero – úvodní text'},
    'hero_btn1': T(26, 'Hero – tlačítko 1'), 'hero_btn2': T(26, 'Hero – tlačítko 2'),
    'gauges': {'type': 'list', 'items': 3, 'label': 'Hero – čísla (fakta)', 'fields': {'value': T(10), 'label': T(30)}},
    'hero_car_label': T(50, 'Hero – štítek vozu (např. Vůz týdne · …)'), 'hero_car_price': T(24, 'Hero – cena vozu'), 'hero_car_note': T(40, 'Hero – poznámka k ceně'), 'hero_car_color': {'type': 'color', 'label': 'Hero – barva vozu'},
    'usp': {'type': 'list', 'items': 4, 'label': 'Přednosti (4)', 'fields': {'title': T(30), 'text': T(60)}},
    'cars_tag': T(40, 'Vozy – štítek'), 'cars_title': T(40, 'Vozy – nadpis'), 'cars_text': {'type': 'multiline', 'max': 220, 'label': 'Vozy – text'},
    'cars': {'type': 'list', 'items': 6, 'label': 'Vozy skladem (ukázka)', 'fields': {'badge': T(18), 'name': T(40), 'sub': T(50), 'year': T(6), 'km': T(16), 'power': T(12), 'fuel': T(18), 'price': T(20), 'note': T(60), 'color': {'type': 'color'}}},
    'cars_more': T(40, 'Vozy – tlačítko pod nabídkou'),
    'cats_tag': T(40, 'Kategorie – štítek'), 'cats_title': T(40, 'Kategorie – nadpis'), 'cats_text': {'type': 'multiline', 'max': 200, 'label': 'Kategorie – text'},
    'cats': {'type': 'list', 'items': 8, 'label': 'Kategorie vozů', 'fields': {'label': T(26), 'count': T(16)}},
    'fin_tag': T(30, 'Financování – štítek'), 'fin_title': T(30, 'Financování – nadpis'), 'fin_em': T(20, 'Financování – zvýrazněná část (např. částka)'), 'fin_title_end': T(20, 'Financování – konec nadpisu'),
    'fin_text': {'type': 'multiline', 'max': 260, 'label': 'Financování – text'}, 'fin_btn1': T(26, 'Financování – tlačítko 1'), 'fin_btn2': T(26, 'Financování – tlačítko 2'),
    'fin_steps': {'type': 'list', 'items': 3, 'label': 'Financování – kroky', 'fields': {'title': T(30), 'text': T(70)}},
    'svc_tag': T(30, 'Služby – štítek'), 'svc_title': T(40, 'Služby – nadpis'), 'svc_text': {'type': 'multiline', 'max': 200, 'label': 'Služby – text'},
    'services': {'type': 'list', 'items': 3, 'label': 'Služby', 'fields': {'title': T(30), 'text': T(200), 'link': T(36)}},
    'rating': G(4, 'Hodnocení (např. 4,9)'), 'rating_note': G(80, 'Hodnocení – odkud a kolik'),
    'reviews': {'type': 'list', 'items': 3, 'label': 'Recenze zákazníků', 'fields': {'text': G(220), 'name': G(40), 'initials': T(3), 'car': G(40)}},
    'drive_tag': T(30, 'Závěr – štítek'), 'drive_title': T(30, 'Závěr – nadpis'), 'drive_text': {'type': 'multiline', 'max': 200, 'label': 'Závěr – text'}, 'drive_note': T(60, 'Závěr – poznámka pod tlačítkem (volitelné)'),
    'foot_text': {'type': 'multiline', 'max': 200, 'label': 'Patička – krátký popis'}, 'hours_line': T(60, 'Patička – otevírací doba'), 'company': T(100, 'Patička – provozovatel (© …, IČO)'),
}
CAR = lambda name, sub, c, badge='', year='2022', km='', power='', fuel='', price='', note='': {'badge': badge, 'name': name, 'sub': sub, 'year': year, 'km': km, 'power': power, 'fuel': fuel, 'price': price, 'note': note, 'color': c}
CONTENT = {
    'lang': 'cs', 'brand': 'Motoria', 'title': 'Motoria – prověřené vozy Brno', 'description': 'Prověřené ojeté vozy s garancí původu, zárukou 24 měsíců a financováním na počkání. Showroom Vídeňská 88, Brno.',
    'logo_pre': 'M', 'logo_post': 'TORIA', 'logo_tag1': 'Prověřené', 'logo_tag2': 'vozy',
    'open_text': 'Po–Pá 8:00–18:00 · So 9:00–13:00', 'address': 'Vídeňská 88, 619 00 Brno', 'phone': '+420 541 000 222', 'phone_href': 'tel:+420541000222', 'email': 'prodej@motoria-brno.cz',
    'cta_label': 'Testovací jízda', 'cta_url': '#jizda',
    'hero_tag': 'Prověřené vozy s garancí původu', 'hero_title': 'Jezděte', 'hero_em': 's jistotou',
    'hero_lead': 'Každý vůz u nás prochází důkladnou technickou kontrolou a ověřením historie. Garantujeme najeté kilometry, dáváme záruku 24 měsíců a financování vyřídíme na počkání.',
    'hero_btn1': 'Prohlédnout vozy', 'hero_btn2': 'Spočítat splátky',
    'gauges': [{'value': '20', 'label': 'vozů skladem'}, {'value': '100 %', 'label': 'vozů prověřeno'}, {'value': '24', 'label': 'měsíců záruka'}],
    'hero_car_label': 'Vůz týdne · BMW 330e M Sport', 'hero_car_price': '899 900 Kč', 'hero_car_note': 'od 14 563 Kč/měs.', 'hero_car_color': '#1f5fb8',
    'usp': [{'title': 'Ověřená historie', 'text': 'Garance najetých km a původu vozu'}, {'title': 'Záruka 24 měsíců', 'text': 'Na motor, převodovku i elektroniku'},
            {'title': 'Výkup protiúčtem', 'text': 'Starý vůz započteme do ceny'}, {'title': 'Financování ihned', 'text': 'Schválení úvěru do 30 minut'}],
    'cars_tag': 'Vybrali jsme pro vás', 'cars_title': 'Vozy skladem', 'cars_text': 'Všechny vozy si můžete prohlédnout a vyzkoušet v našem showroomu v Brně. Cena je konečná včetně DPH a přepisu.',
    'cars': [CAR('BMW 330e M Sport', 'Plug-in hybrid, Portimao modrá', '#1f5fb8', 'Vůz týdne', '2022', '41 900 km', '215 kW', 'Plug-in hybrid', '899 900 Kč', 'bez DPH 743 719 Kč'),
             CAR('Škoda Superb Combi L&K', '2.0 TDI 4×4, stříbrná Brilliant', '#c9ced6', 'Top stav', '2022', '68 400 km', '147 kW', 'Diesel', '689 900 Kč', 'bez DPH 570 165 Kč'),
             CAR('Hyundai Ioniq 5 AWD', 'Long Range 77 kWh, Gravity Gold', '#b8a580', 'Elektro', '2023', '22 100 km', '239 kW', 'Elektro', '979 900 Kč', ''),
             CAR('Toyota RAV4 Hybrid AWD', '2.5 Hybrid Executive, Emotional Red', '#b81f2a', 'Záruka výrobce', '2022', '49 800 km', '163 kW', 'Hybrid', '829 900 Kč', ''),
             CAR('Škoda Octavia RS', '2.0 TSI, zelená Mamba', '#3f9a3a', 'Zlevněno', '2020', '76 300 km', '180 kW', 'Benzín', '519 900 Kč', 'původně 539 900 Kč'),
             CAR('Kia Ceed GT-Line', '1.5 T-GDi, Orange Fusion', '#e8701e', 'Novinka', '2023', '18 900 km', '118 kW', 'Benzín', '449 900 Kč', '')],
    'cars_more': 'Celá nabídka vozů',
    'cats_tag': 'Rychlé hledání', 'cats_title': 'Co hledáte?', 'cats_text': 'Vyberte typ vozu, rádi vám ukážeme vše, co máme skladem.',
    'cats': [{'label': 'SUV', 'count': '8 vozů'}, {'label': 'Kombi', 'count': '5 vozů'}, {'label': 'Elektro a hybridy', 'count': '8 vozů'}, {'label': 'Do 500 000 Kč', 'count': '6 vozů'},
             {'label': 'Pohon 4×4', 'count': '10 vozů'}, {'label': 'S automatem', 'count': '17 vozů'}, {'label': 'Pro firmy s DPH', 'count': '6 vozů'}, {'label': '7 míst', 'count': '1 vůz'}],
    'fin_tag': 'Financování', 'fin_title': 'Splátky od', 'fin_em': '5 800 Kč', 'fin_title_end': 'měsíčně',
    'fin_text': 'Úvěr i leasing vyřídíme přímo v showroomu, schválení obvykle do 30 minut. Orientační sazba 7,9 % p. a., bez poplatku za předčasné splacení.',
    'fin_btn1': 'Spočítat splátky', 'fin_btn2': 'Ocenit můj vůz',
    'fin_steps': [{'title': 'Vyberete vůz', 'text': 'Online nebo v showroomu, rádi poradíme.'}, {'title': 'Spočítáme splátky', 'text': 'Úvěr, leasing, s akontací i bez ní.'}, {'title': 'Odjedete', 'text': 'Smlouva, pojištění a přepis na počkání.'}],
    'svc_tag': 'Služby', 'svc_title': 'Víc než prodej', 'svc_text': 'Postaráme se o auto před koupí i po ní. Všechno pod jednou střechou ve Vídeňské ulici.',
    'services': [{'title': 'Výkup vozů', 'text': 'Přijeďte s autem a do 30 minut máte cenovou nabídku. Peníze posíláme ještě týž den, nebo je započteme do nového vozu.', 'link': 'Nabídka do 30 minut →'},
                 {'title': 'Servis', 'text': 'Servis všech značek, pneuservis a příprava na STK. Náhradní vůz zdarma po dobu opravy.', 'link': 'Náhradní vůz zdarma →'},
                 {'title': 'Přepis a pojištění', 'text': 'Přepis na registru, povinné ručení i havarijní pojištění vyřídíme za vás. Odjíždíte s hotovými papíry.', 'link': 'Vše na počkání →'}],
    'rating': '4,9', 'rating_note': 'Průměr z 312 hodnocení zákazníků za rok 2026',
    'reviews': [{'text': 'Superb jsme si vybrali doma na webu, spočítali splátky a v sobotu jen podepsali. Celé to zabralo hodinu i s přepisem.', 'name': 'Martin Kučera', 'initials': 'MK', 'car': 'Škoda Superb Combi'},
                {'text': 'Starou Octavii vykoupili za férovou cenu a rovnou ji započítali. Oceňuji, že mi ukázali kompletní servisní historii.', 'name': 'Lucie Dvořáková', 'initials': 'LD', 'car': 'Toyota RAV4 Hybrid'},
                {'text': 'Testovací jízdu jsem si objednal přes web na úterý v 17:00, auto bylo připravené a natankované. Takhle to má vypadat.', 'name': 'Petr Svoboda', 'initials': 'PS', 'car': 'BMW 330e'}],
    'drive_tag': 'Testovací jízda', 'drive_title': 'Vyzkoušejte si ho', 'drive_text': 'Zavolejte nebo napište, který vůz vás zajímá. Připravíme ho natankovaný a čas vám potvrdíme.', 'drive_note': '',
    'foot_text': 'Prověřené ojeté vozy s garancí původu, zárukou 24 měsíců a financováním na počkání.', 'hours_line': 'Po–Pá 8:00–18:00 · So 9:00–13:00',
    'company': '© 2026 Motoria s.r.o. · Vídeňská 88, 619 00 Brno · IČO 87654321',
}

WHEEL = lambda cx: ('<circle cx="%d" cy="116" r="27" fill="#15181d"/><circle cx="%d" cy="116" r="14.85" fill="#B8C2CE"/><circle cx="%d" cy="116" r="5.4" fill="#6B7684"/>' % (cx, cx, cx)
                    + ''.join('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#6B7684" stroke-width="3"/>' % p for p in
                              [(cx + 5.9, 116, cx + 14, 116), (cx + 1.8, 121.6, cx + 4.3, 129.4), (cx - 4.8, 119.5, cx - 11.4, 124.3), (cx - 4.8, 112.5, cx - 11.4, 107.7), (cx + 1.8, 110.4, cx + 4.3, 102.6)]))
BODY = 'M18 116V92q2-14 26-18l68-8q34-28 84-32h66q30 2 60 28l44 8q24 4 26 22v24h-46a34 34 0 0 0-68 0H140a34 34 0 0 0-68 0Z'
def car(color, label=''):
    a = ' role="img" aria-label="%s"' % label if label else ' aria-hidden="true"'
    return ('<svg viewBox="0 0 410 150"%s><ellipse cx="206" cy="138" rx="186" ry="7" fill="rgba(10,22,40,.28)"/><path d="%s" fill="%s"/><path d="%s" fill="url(#car-sh)"/>'
            '<path d="M128 66q24-20 66-24h30v24Zm108-24h24q24 2 46 22l-70 2Z" fill="#1d2a3c" opacity=".92"/><path d="M40 88H380" stroke="rgba(255,255,255,.35)" stroke-width="2"/>'
            '<rect x="376" y="84" width="16" height="7" rx="3" fill="#FFE2B8"/><rect x="18" y="86" width="12" height="6" rx="2" fill="#E0301E"/>%s%s</svg>') % (a, BODY, color, BODY, WHEEL(106), WHEEL(312))
DEFS = ('<svg class="svg-defs" aria-hidden="true" focusable="false"><defs><linearGradient id="car-sh" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".45"/>'
        '<stop offset=".42" stop-color="#fff" stop-opacity="0"/><stop offset=".62" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".3"/></linearGradient></defs></svg>')
WHEEL_LOGO = '<svg class="lg-o" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="25" fill="none" stroke="currentColor" stroke-width="9"/><path d="M14.3 49.7A25 25 0 1 1 52.5 17.7" fill="none" stroke="var(--orange)" stroke-width="9"/><path d="M32 32 46 22" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><circle cx="32" cy="32" r="4.5" fill="currentColor"/></svg>'
CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6Z"/><path d="m9 12 2 2 4-4"/></svg>'
ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
STAR = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.8 2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8Z" fill="currentColor"/></svg>'
STARS = '<div class="stars" aria-label="5 hvězdiček z 5">' + STAR * 5 + '</div>'
SVC_IC = ['<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg>',
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4Z"/></svg>',
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 3h7l5 5v13H7Z"/><path d="M14 3v5h5M10 13h6M10 17h6"/></svg>']
MOON = '<svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/></svg>'
SUN = '<svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></svg>'
MENU = '<a href="#vozy">Nabídka vozů</a><a href="#financovani">Financování</a><a href="#sluzby">Výkup a servis</a><a href="#kontakt">Kontakt</a>'
LOGO = '{{logo_pre}}' + WHEEL_LOGO + '{{logo_post}}'

HTML = '''<!doctype html>
<html lang="{{lang}}" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{title}}</title>
<meta name="description" content="{{description}}">
<meta name="color-scheme" content="dark light">
<link rel="stylesheet" href="assets/styles.css">
</head>
<body class="p-index">
__DEFS__
<a class="skip" href="#main">Přeskočit na obsah</a>
<div class="util">
  <div class="wrap">
    <span><i class="live"></i><span>{{open_text}}</span></span>
    <span>{{address}} · <a href="{{phone_href}}"><b>{{phone}}</b></a></span>
  </div>
</div>
<header class="nav">
  <div class="wrap">
    <a href="#top" class="logo" aria-label="{{brand}} – úvod">__LOGO__ <small>{{logo_tag1}}<br>{{logo_tag2}}</small></a>
    <nav class="menu" id="menu" aria-label="Hlavní menu">__MENU__<a href="{{cta_url}}" class="menu-cta">{{cta_label}}</a></nav>
    <a href="{{cta_url}}" class="btn nav-cta">{{cta_label}}</a>
    <button class="mode-btn" type="button" data-mode-btn aria-label="Přepnout režim">__MOON____SUN__</button><button class="burger" id="burger" type="button" aria-expanded="false" aria-controls="menu" aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
  </div>
</header>

<main id="main">
<section class="hero" id="top">
  <div class="wrap">
    <div class="hero-grid">
      <div>
        <span class="tag">{{hero_tag}}</span>
        <h1>{{hero_title}} <span class="o">{{hero_em}}<span class="dot">.</span></span></h1>
        <p class="hero-lead">{{hero_lead}}</p>
        <div class="hero-cta">
          <a href="#vozy" class="btn">{{hero_btn1}} __ARROW__</a>
          <a href="#financovani" class="btn btn--line">{{hero_btn2}}</a>
        </div>
        <div class="gauges">{{#gauges}}<div><b>{{value}}</b><span>{{label}}</span></div>{{/gauges}}</div>
      </div>
      <a class="stage" href="#vozy" aria-label="{{hero_car_label}}">
        <div class="streaks" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="glow" aria-hidden="true"></div>
        <div class="floor" aria-hidden="true"></div>
        <div class="hero-car">__HEROCAR__</div>
        <div class="price-tag"><small>{{hero_car_label}}</small><b>{{hero_car_price}}</b><span>{{hero_car_note}}</span></div>
      </a>
    </div>
  </div>
</section>
<div class="after-search"></div>

<div class="wrap">
  <div class="usp">{{#usp}}<div>__CHECK__<p><b>{{title}}</b><span>{{text}}</span></p></div>{{/usp}}</div>
</div>

<section class="sec" id="vozy" style="padding-top:clamp(1rem,3vw,2rem)">
  <div class="wrap">
    <div class="head">
      <div><span class="tag">{{cars_tag}}</span><h2>{{cars_title}}</h2></div>
      <p>{{cars_text}}</p>
    </div>
    <div class="cars">{{#cars}}<article class="car-card"><div class="car-pic"><div class="plate">{{#badge}}<span class="badge o">{{badge}}</span>{{/badge}}</div>__CAR__</div><div class="car-body"><h3><span class="car-link">{{name}}</span><small>{{sub}}</small></h3><dl class="specs"><div><dt>Rok</dt><dd>{{year}}</dd></div><div><dt>Najeto</dt><dd>{{km}}</dd></div><div><dt>Výkon</dt><dd>{{power}}</dd></div><div><dt>Palivo</dt><dd>{{fuel}}</dd></div></dl><div class="price-row"><p class="price">{{price}}<small>{{note}}</small></p><a class="btn btn--dark" href="{{cta_url}}">Detail</a></div></div></article>{{/cars}}</div>
    <div class="more-row"><a class="btn btn--dark" href="{{phone_href}}">{{cars_more}}</a></div>
  </div>
</section>

<section class="sec cats-sec">
  <div class="wrap">
    <div class="head">
      <div><span class="tag">{{cats_tag}}</span><h2>{{cats_title}}</h2></div>
      <p>{{cats_text}}</p>
    </div>
    <div class="cats">{{#cats}}<a class="cat" href="#vozy"><span class="cat-pic">__CATCAR__</span><b>{{label}}</b><span>{{count}}</span></a>{{/cats}}</div>
  </div>
</section>

<section class="sec finance fin-teaser" id="financovani">
  <div class="wrap">
    <div class="fin-t">
      <div>
        <span class="tag">{{fin_tag}}</span>
        <h2 class="big-h">{{fin_title}} <span>{{fin_em}}</span> {{fin_title_end}}</h2>
        <p class="fin-t-lead">{{fin_text}}</p>
        <div class="hero-cta"><a class="btn" href="{{phone_href}}">{{fin_btn1}}</a><a class="btn btn--line" href="#sluzby">{{fin_btn2}}</a></div>
      </div>
      <ol class="fin-steps">{{#fin_steps}}<li><b>{{title}}</b><span>{{text}}</span></li>{{/fin_steps}}</ol>
    </div>
  </div>
</section>

<section class="sec" id="sluzby">
  <div class="wrap">
    <div class="head">
      <div><span class="tag">{{svc_tag}}</span><h2>{{svc_title}}</h2></div>
      <p>{{svc_text}}</p>
    </div>
    <div class="svc">{{#services}}<article><div class="ic">__SVCIC__</div><h3>{{title}}</h3><p>{{text}}</p><a class="big" href="#kontakt">{{link}}</a></article>{{/services}}</div>
  </div>
</section>

{{#reviews}}{{/reviews}}<section class="sec reviews">
  <div class="wrap">
    <div class="rev-top">
      <span class="score">{{rating}}</span>
      <div>__STARS__<p>{{rating_note}}</p></div>
    </div>
    <div class="rev-grid">{{#reviews}}<article class="rev">__STARS__<p>{{text}}</p><footer><i>{{initials}}</i><div><b>{{name}}</b><span>{{car}}</span></div></footer></article>{{/reviews}}</div>
  </div>
</section>

<section class="sec drive drive-band" id="jizda">
  <div class="wrap">
    <div class="band">
      <div><span class="tag">{{drive_tag}}</span><h2 class="big-h">{{drive_title}}</h2><p>{{drive_text}}</p></div>
      <div class="band-cta"><a class="btn" href="{{phone_href}}">{{cta_label}}</a>{{#drive_note}}<span class="band-note">{{drive_note}}</span>{{/drive_note}}</div>
    </div>
  </div>
</section>
</main>

<footer class="site" id="kontakt">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand">
        <a class="logo" href="#top">__LOGO__</a>
        <p>{{foot_text}}</p>
        {{#rating}}<p class="foot-rate"><b>{{rating}}</b> z 5</p>{{/rating}}
      </div>
      <nav class="foot-col" aria-label="Patička – stránka"><b>Na stránce</b>__MENU__</nav>
      <div class="foot-col"><b>Showroom</b><span>{{address}}</span><a href="{{phone_href}}">{{phone}}</a><a href="mailto:{{email}}">{{email}}</a><span class="foot-hours">{{hours_line}}</span></div>
    </div>
    <div class="foot-bottom"><span>{{company}}</span></div>
  </div>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
'''
APP = '''/* Šablona „autosalon“ – menu na mobilu a přepínač tmavého/světlého režimu (výchozí tmavý; bez ukládání, bez síťových požadavků). */
(function () {
  var root = document.documentElement, btn = document.getElementById('burger'), nav = document.getElementById('menu');
  if (btn && nav) {
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); root.classList.toggle('menu-open', o); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('open'); root.classList.remove('menu-open'); btn.setAttribute('aria-expanded', 'false'); } });
  }
  function label() { var l = root.getAttribute('data-theme') === 'dark' ? 'Přepnout na světlý režim' : 'Přepnout na tmavý režim'; document.querySelectorAll('[data-mode-btn]').forEach(function (b) { b.setAttribute('aria-label', l); b.title = l; }); }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-mode-btn]')) return;
    root.setAttribute('data-theme', root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    label();
  });
  label();
})();
'''
EXTRA_CSS = '''
/* šablona: přístupnost, sdílené SVG, karty bez odkazu na detail */
.skip{position:absolute;left:-9999px;top:0}.skip:focus{left:12px;top:12px;z-index:99;background:var(--orange);color:var(--night);padding:.5rem .8rem}
.svg-defs{position:absolute;width:0;height:0;overflow:hidden}
.car-link{color:inherit;text-decoration:none}
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(os.path.join(F, 'assets', 'fonts'))
    css = open(os.path.join(WEB, 'ukazky', 'motoria', 'style.css'), encoding='utf-8').read()
    a = css.index('/* ==========================================================================\n   Administrace a e-maily'); z = css.index('/* ==========================================================================\n   Redesign 2026')
    css = css[:a] + css[z:]                                  # bez administrace a e-mailů; vrstva Redesign 2026 (logo, úzká Saira, mono čísla) zůstává
    m = re.search(r':root\{(.*?)\}', css, re.S)
    names = [t[0] for t in TOKENS.values()] + [f[0] for f in FONTS.values()]
    keep = [d.strip() for d in m.group(1).split(';') if d.strip() and d.split(':', 1)[0].strip() not in names]
    tok = ';'.join(['%s:%s' % (t[0], t[2]) for t in TOKENS.values()] + ['%s:%s' % (f[0], f[1]) for f in FONTS.values()])
    css = css[:m.start()] + '/*@tokens*/:root{' + tok + '}/*@end*/\n:root{' + ';'.join(keep) + '}' + css[m.end():]
    # tmavý režim (výchozí) bere pozadí a karty z tokenů
    d = re.search(r':root\[data-theme="dark"\]\{(.*?)\}', css, re.S)
    blk = d.group(0).replace('--chrome:#15171A', '--chrome:var(--t-page)').replace('--card:#1D2024', '--card:var(--t-card)').replace('--chrome-2:#1D2024', '--chrome-2:var(--t-card)')
    assert blk.count('var(--t-') == 3, blk
    css = css[:d.start()] + blk + css[d.end():]
    header = '/* Šablona „autosalon“ (T&S Design Studio) – styly převzaté z ukázky Motoria. Barvy a písmo mění Studio v bloku @tokens (i pro výchozí tmavý režim). */\n'
    fonts = open(os.path.join(WEB, 'assets', 'fonts', 'motoria.css'), encoding='utf-8').read()
    fonts = '\n'.join(l for l in fonts.split('\n') if l.startswith('@font-face{font-family:"Saira"') or l.startswith('@font-face{font-family:"Red Hat Mono"'))
    fonts = re.sub(r'url\("([a-z0-9-]+\.woff2)(\?v=[0-9a-f]+)?"\)', r'url("fonts/\1")', fonts)
    open(os.path.join(F, 'assets', 'styles.css'), 'w', encoding='utf-8').write(header + fonts + '\n' + css + EXTRA_CSS)
    for f in re.findall(r'url\("fonts/([^"]+)"\)', fonts): shutil.copy(os.path.join(WEB, 'assets', 'fonts', f), os.path.join(F, 'assets', 'fonts', f))
    for l in ('Saira-OFL.txt', 'Red_Hat_Mono-OFL.txt'): shutil.copy(os.path.join(WEB, 'assets', 'fonts', 'licence', l), os.path.join(F, 'assets', 'fonts', l))
    html = (HTML.replace('{{#reviews}}{{/reviews}}', '').replace('__DEFS__', DEFS).replace('__LOGO__', LOGO).replace('__MENU__', MENU).replace('__MOON__', MOON).replace('__SUN__', SUN)
            .replace('__ARROW__', ARROW).replace('__CHECK__', CHECK).replace('__STARS__', STARS).replace('__HEROCAR__', car('{{hero_car_color}}', '{{hero_car_label}}'))
            .replace('__CAR__', car('{{color}}')).replace('__CATCAR__', car('#9aa0a8')).replace('__SVCIC__', SVC_IC[0]))
    open(os.path.join(F, 'index.html'), 'w', encoding='utf-8').write(html)
    open(os.path.join(F, 'assets', 'app.js'), 'w', encoding='utf-8').write(APP)
    t = {'id': 'autosalon', 'name': 'Autosalon a prodej vozů', 'basedOn': 'ukázka Motoria (web/ukazky/motoria.html)',
         'obor': ['autosalon', 'autobazar', 'prodej vozů', 'prodej aut', 'ojeté vozy', 'auto', 'autoservis', 'pneuservis', 'motocykl'],
         'description': 'Jednostránkový web prodejce vozů v „nočním“ stylu: hero s vozem týdne, přednosti, vozy skladem s parametry a cenou, kategorie, financování po krocích, služby, recenze, testovací jízda a kontakt. Výchozí tmavý režim, světlý tlačítkem.',
         'render': ['index.html', 'assets/styles.css'],
         'tokens': dict({k: {'var': v[0], 'type': v[1], 'value': v[2], 'label': v[3]} for k, v in TOKENS.items()}, **{k: {'var': v[0], 'type': 'font', 'value': v[1], 'options': v[2], 'label': v[3]} for k, v in FONTS.items()}),
         'slots': SLOTS, 'contrast': [['ink', 'chrome'], ['muted', 'chrome'], ['accent', 'night'], ['accent', 'page_dark']], 'content': CONTENT,
         'licenses': ['assets/fonts/Saira-OFL.txt', 'assets/fonts/Red_Hat_Mono-OFL.txt'],
         'cssHints': ['.logo', '.hero h1', '.hero-lead', '.head h2', '.btn', '.car-card', '.price', '.big-h', '.tag', '.stage']}
    json.dump(t, open(os.path.join(OUT, 'template.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('šablona autosalon:', sum(len(fs) for _, _, fs in os.walk(F)), 'souborů')


if __name__ == '__main__':
    main()
