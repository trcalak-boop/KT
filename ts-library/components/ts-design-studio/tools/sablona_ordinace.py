#!/usr/bin/env python3
"""Převod ukázky Ordinace (web/ukazky/ordinace.html) na šablonu Studia „ordinace“ – jednostránkový web lékaře / ambulance bez kitu a bez ukázkové lišty.
Styly z ordinace/style.css (bez administrace a e-mailů, vrstva Redesign 2026 zůstává), barvy jako tokeny (i pro tmavý režim), písma Atkinson Hyperlegible Next
a JetBrains Mono (OFL) i s licencemi. „Tabule dneška“ a rozpis týdne ukázky jsou dynamické (JS) – šablona má místo nich statické sloty.
Režim podle nastavení zařízení, přepínač a tlačítko A+ (větší písmo) bez ukládání.
Spouštět při změně zdrojové ukázky:  python3 -I tools/sablona_ordinace.py /cesta/k/projektu/web"""
import json, os, re, shutil, sys

WEB = sys.argv[1] if len(sys.argv) > 1 else '/Users/krystof/Downloads/T&S webstudio_28-10/web'
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'templates', 'ordinace')
F = os.path.join(OUT, 'files')

TOKENS = {
    'page': ('--page', 'color', '#ffffff', 'Pozadí (světlý režim)'), 'text': ('--text', 'color', '#0c3a47', 'Text a linky (světlý režim)'), 'muted': ('--muted', 'color', '#4b676c', 'Text tlumený (světlý režim)'),
    'tint': ('--tint', 'color', '#f1f7f6', 'Jemný podklad (světlý režim)'), 'inv': ('--inv', 'color', '#0c3a47', 'Tmavé bloky (tabule, patička)'),
    'teal': ('--teal', 'color', '#34c49a', 'Signální barva (štítky, čísla)'), 'mint': ('--mint', 'color', '#ddf3ef', 'Mátový podklad (zvýraznění)'), 'amber': ('--amber', 'color', '#f2b705', 'Výstražná žlutá (upozornění)'),
    'sos': ('--sos', 'color', '#c8372f', 'Tísňové volání (pozadí)'),
    'page_dark': ('--t-page-d', 'color', '#071a1d', 'Pozadí (tmavý režim)'), 'acc_dark': ('--t-acc-d', 'color', '#5ad3c8', 'Akcent (tmavý režim)'),
}
FONTS = {
    'f': ('--f', '"Atkinson Hyperlegible Next","Atkinson Hyperlegible",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', ['"Atkinson Hyperlegible Next","Atkinson Hyperlegible",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'], 'Písmo (navržené pro lepší čitelnost)'),
}
T = lambda mx, label='': {'type': 'text', 'max': mx, 'label': label}
G = lambda mx, label='': {'type': 'text', 'max': mx, 'label': label, 'grounded': True}
M = lambda mx, label='': {'type': 'multiline', 'max': mx, 'label': label}
SLOTS = {
    'lang': T(5, 'Jazyk stránky (cs, en…)'), 'brand': G(60, 'Jméno lékaře / název ordinace'), 'subtitle': T(70, 'Podtitul (obor · město)'), 'logo_letter': T(2, 'Písmeno ve značce'),
    'title': T(70, 'Titulek stránky (prohlížeč, vyhledávače)'), 'description': T(160, 'Popis pro vyhledávače'),
    'alerts': {'type': 'list', 'items': 2, 'label': 'Upozornění nahoře (dovolená, očkování…)', 'fields': {'tag': T(24), 'title': T(60), 'text': T(160), 'link': T(30)}},
    'cta_label': T(24, 'Hlavní tlačítko (např. Objednat se)'), 'cta_url': {'type': 'url', 'label': 'Odkaz hlavního tlačítka'},
    'board_kicker': T(60, 'Tabule – řádek nad nadpisem'), 'board_title1': T(30, 'Tabule – nadpis, 1. řádek'), 'board_title2': T(30, 'Tabule – nadpis, 2. řádek'), 'board_text': M(220, 'Tabule – text'),
    'nurse_label': T(20, 'Tabule – popis telefonu (např. Sestra)'), 'phone': {'type': 'phone', 'label': 'Telefon'}, 'phone_href': {'type': 'url', 'label': 'Telefon jako odkaz (tel:+420…)'},
    'side1_label': T(50, 'Pravý blok 1 – štítek'), 'side1_big': T(20, 'Pravý blok 1 – velký údaj'), 'side1_sub': T(50, 'Pravý blok 1 – poznámka'),
    'side2_label': T(50, 'Pravý blok 2 – štítek'), 'side2_big': T(30, 'Pravý blok 2 – velký údaj'), 'side2_link': T(40, 'Pravý blok 2 – odkaz'),
    'intro_text': M(300, 'Úvod – text'), 'intro_btn1': T(30, 'Úvod – tlačítko 1'), 'intro_btn2': T(30, 'Úvod – tlačítko 2'),
    'checks': {'type': 'list', 'items': 4, 'label': 'Úvod – odškrtnuté výhody', 'fields': {'text': T(50)}},
    'hours_title': T(40, 'Hodiny – nadpis'), 'hours_text': T(120, 'Hodiny – poznámka vpravo'),
    'col_day': T(14, 'Tabulka – sloupec den'), 'col_hours': T(20, 'Tabulka – sloupec ordinace'), 'col_extra': T(20, 'Tabulka – sloupec 3 (např. Odběry krve)'), 'col_note': T(20, 'Tabulka – sloupec poznámka'),
    'hours': {'type': 'list', 'items': 7, 'label': 'Ordinační hodiny', 'fields': {'day': T(14), 'hours': T(20), 'hours2': T(40, 'druhý blok (volitelné)'), 'extra': T(20), 'note': T(60)}},
    'notice': M(240, 'Oznámení pod hodinami (volitelné, např. dovolená a zástup)'),
    'paths_title': T(40, 'Rychlé cesty – nadpis'), 'paths_text': T(80, 'Rychlé cesty – poznámka'),
    'paths': {'type': 'list', 'items': 5, 'label': 'Rychlé cesty', 'fields': {'title': T(30), 'text': T(140)}},
    'team_kicker': T(30, 'Tým – štítek'), 'team_title': T(60, 'Tým – nadpis'),
    'team': {'type': 'list', 'items': 4, 'label': 'Tým', 'fields': {'initials': T(3), 'name': G(50), 'role': T(40)}},
    'np_kicker': T(30, 'Noví pacienti – štítek'), 'np_title': T(50, 'Noví pacienti – nadpis'), 'np_text': M(240, 'Noví pacienti – text'),
    'np_steps': {'type': 'list', 'items': 3, 'label': 'Noví pacienti – kroky', 'fields': {'bold': T(20), 'text': T(80)}}, 'np_btn': T(30, 'Noví pacienti – tlačítko'),
    'visit_kicker': T(30, 'Kde nás najdete – štítek'), 'address': T(70, 'Adresa'), 'floor': T(70, 'Upřesnění (patro, vchod)'), 'map_url': {'type': 'url', 'label': 'Odkaz na mapu (volitelné)'},
    'sos_number': T(6, 'Tísňové číslo'), 'sos_title': T(60, 'Tísňové volání – nadpis'), 'sos_text': M(260, 'Tísňové volání – pohotovost mimo hodiny'),
    'email': {'type': 'email', 'label': 'E-mail'}, 'foot_address': T(100, 'Patička – adresa'), 'legal': T(140, 'Patička – provozovatel (IČO, IČP…)'),
}
CONTENT = {
    'lang': 'cs', 'brand': 'MUDr. Alžběta Kroupová', 'subtitle': 'Praktická lékařka pro dospělé · Kolín', 'logo_letter': 'K',
    'title': 'MUDr. Alžběta Kroupová – praktická lékařka Kolín', 'description': 'Praktická lékařka pro dospělé v centru Kolína. Objednání online, e-recept z domova, přijímáme nové pacienty.',
    'alerts': [{'tag': 'Dovolená 16.–20. 11.', 'title': 'Neordinujeme.', 'text': 'Zastupuje MUDr. Petr Malý, Politických vězňů 20, Kolín, tel. +420 321 000 777.', 'link': 'Kdo zastupuje'},
               {'tag': 'Aktuálně', 'title': 'Začalo očkování proti chřipce a covid-19.', 'text': 'Vakcína je pro pojištěnce nad 60 let a chronicky nemocné zdarma.', 'link': 'Objednat se na očkování'}],
    'cta_label': 'Objednat se', 'cta_url': '#hodiny',
    'board_kicker': 'Karlovo náměstí 45, Kolín', 'board_title1': 'Objednejte se', 'board_title2': 'bez čekání.',
    'board_text': 'Akutní potíže ošetříme každý ordinační den bez objednání. V naléhavých případech volejte 155.',
    'nurse_label': 'Sestra', 'phone': '+420 321 000 555', 'phone_href': 'tel:+420321000555',
    'side1_label': 'Ordinujeme', 'side1_big': 'Po–Pá', 'side1_sub': 'odběry krve Po, St a Pá 7:00–8:30',
    'side2_label': 'Objednání', 'side2_big': 'Telefonem nebo e-mailem', 'side2_link': 'Ordinační hodiny',
    'intro_text': 'Jsem praktická lékařka pro dospělé v centru Kolína. Objednáte se během minuty, o e-recept požádáte z domova a na návštěvu nebudete čekat v plné čekárně.',
    'intro_btn1': 'Objednat se', 'intro_btn2': 'Požádat o e-recept',
    'checks': [{'text': 'Přijímáme nové pacienty'}, {'text': 'Bezbariérový přístup s výtahem'}, {'text': 'Všechny zdravotní pojišťovny'}],
    'hours_title': 'Ordinační hodiny', 'hours_text': 'Akutní potíže ošetříme každý ordinační den bez objednání.',
    'col_day': 'Den', 'col_hours': 'Ordinace', 'col_extra': 'Odběry krve', 'col_note': 'Poznámka',
    'hours': [{'day': 'Pondělí', 'hours': '8:30–13:00', 'hours2': '', 'extra': '7:00–8:30', 'note': 'Akutní bez objednání do 10:00'},
              {'day': 'Úterý', 'hours': '12:00–16:00', 'hours2': '16:00–18:00 jen objednaní', 'extra': '–', 'note': 'Odpolední ordinace'},
              {'day': 'Středa', 'hours': '8:30–13:00', 'hours2': '13:00–15:00 jen objednaní', 'extra': '7:00–8:30', 'note': 'Odpoledne preventivní prohlídky a návštěvy doma'},
              {'day': 'Čtvrtek', 'hours': '12:00–16:00', 'hours2': '16:00–18:00 jen objednaní', 'extra': '–', 'note': 'Odpolední ordinace'},
              {'day': 'Pátek', 'hours': '8:30–11:00', 'hours2': '11:00–12:00 jen objednaní', 'extra': '7:00–8:30', 'note': 'Po 11:00 jen objednaní'}],
    'notice': 'Dovolená 16.–20. 11. Zastupuje MUDr. Petr Malý, Politických vězňů 20, Kolín, tel. +420 321 000 777. Ordinuje Po–Pá 7:00–12:00.',
    'paths_title': 'Co potřebujete vyřídit?', 'paths_text': 'Většinu věcí zvládnete z domova.',
    'paths': [{'title': 'Ordinační hodiny', 'text': 'Kdy ordinujeme a kdo zastupuje během dovolené. Odběry krve Po, St a Pá 7:00–8:30.'},
              {'title': 'Objednat se na termín', 'text': 'Prohlídka, kontrola, očkování nebo odběr krve – zavolejte nebo napište.'},
              {'title': 'Požádat o e-recept', 'text': 'Na léky, které užíváte dlouhodobě. Vyřídíme do 2 pracovních dnů, kód pošleme SMS nebo e-mailem.'},
              {'title': 'Služby a ceník', 'text': 'Prevence, očkování, posudky a návštěvy doma. Ceny výkonů, které pojišťovna nehradí.'},
              {'title': 'Kudy k nám', 'text': 'Karlovo náměstí 45, Kolín, 2. patro, výtah ze dvora.'}],
    'team_kicker': 'Kdo se o vás postará', 'team_title': 'Malá ordinace, kde vás znají jménem',
    'team': [{'initials': 'AK', 'name': 'MUDr. Alžběta Kroupová', 'role': 'praktická lékařka'}, {'initials': 'LT', 'name': 'Bc. Lenka Tichá', 'role': 'všeobecná sestra'}],
    'np_kicker': 'Noví pacienti', 'np_title': 'Registrujeme nové pacienty', 'np_text': 'Přijímáme dospělé pacienty od 19 let bez ohledu na pojišťovnu. Převedení dokumentace od předchozího lékaře zařídíme za vás.',
    'np_steps': [{'bold': 'Objednejte se', 'text': 'na „Registrace nového pacienta“.'}, {'bold': 'Přineste', 'text': 'kartičku pojištěnce a seznam léků.'}, {'bold': 'Podepíšete', 'text': 'registraci, zbytek zařídíme my.'}],
    'np_btn': 'Objednat se k registraci',
    'visit_kicker': 'Kde nás najdete', 'address': 'Karlovo náměstí 45, Kolín', 'floor': '2. patro, výtah ze dvora', 'map_url': '',
    'sos_number': '155', 'sos_title': 'V ohrožení života volejte záchrannou službu.',
    'sos_text': 'Mimo ordinační hodiny se obraťte na lékařskou pohotovostní službu v Nemocnici Kolín, Žižkova 146 (všední dny 17:00–22:00, víkendy a svátky 8:00–20:00).',
    'email': 'ordinace@kroupova-kolin.cz', 'foot_address': 'Karlovo náměstí 45, 280 02 Kolín · 2. patro, výtah ze dvora',
    'legal': 'MUDr. Alžběta Kroupová, praktický lékař pro dospělé · IČO 76543210 · IČP 01234567',
}

ARR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="square" aria-hidden="true"><path d="M4 12h15M13 6l6 6-6 6"/></svg>'
CHK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="square" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>'
CAL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>'
BURGER = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="square" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>'
MOON = '<svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/></svg>'
SUN = '<svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></svg>'
MK = '<span class="mk"><svg viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" rx="6" fill="#0C3A47"/><text x="29" y="48" text-anchor="middle" font-family="Atkinson Hyperlegible Next,Atkinson Hyperlegible,sans-serif" font-weight="800" font-size="44" fill="#FFFFFF">{{logo_letter}}</text><circle cx="50" cy="15" r="6.5" fill="#34C49A"/></svg></span>'
LOGO = MK + '<span class="lt"><b>{{brand}}</b><span>{{subtitle}}</span></span>'
MENU = ''.join('<a href="#%s"><span class="n" aria-hidden="true">%02d</span><span class="t">%s</span></a>' % (h, i + 1, t) for i, (h, t) in enumerate([('hodiny', 'Ordinační hodiny'), ('vyridit', 'Co vyřídit'), ('tym', 'Tým'), ('pacienti', 'Noví pacienti'), ('kontakt', 'Kontakt')]))

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
<a class="skip" href="#main">Přeskočit na obsah</a>
{{#alerts}}{{/alerts}}<div class="alerts" role="region" aria-label="Aktuální upozornění">{{#alerts}}<div class="alertbar"><div class="wrap"><strong class="ab-tag">{{tag}}</strong><span><b>{{title}}</b> <span class="ab-more">{{text}}</span></span><a href="#hodiny">{{link}}</a></div></div>{{/alerts}}</div>

<header class="nav">
  <div class="wrap nav-in">
    <a class="logo" href="#top" aria-current="page">__LOGO__</a>
    <nav class="menu" id="menu" aria-label="Hlavní menu">__MENU__</nav>
    <div class="tools">
      <button class="mode-btn" type="button" data-mode-btn aria-label="Přepnout režim">__MOON____SUN__</button><button class="aplus" id="aplus" type="button" aria-pressed="false" title="Zvětšit písmo" aria-label="Zvětšit písmo">A+</button>
      <a href="{{cta_url}}" class="btn">{{cta_label}}</a>
      <button class="burger" id="burger" type="button" aria-expanded="false" aria-controls="menu" aria-label="Menu">__BURGER__</button>
    </div>
  </div>
</header>

<main id="main">
<section class="board" id="top" aria-label="Ordinace">
  <div class="wrap board-grid">
    <div class="board-main">
      <p class="mono-l">{{board_kicker}}</p>
      <h1>{{board_title1}}<br>{{board_title2}}</h1>
      <p class="board-note">{{board_text}}</p>
      <p class="board-nurse"><span class="mono-l">{{nurse_label}}</span><a href="{{phone_href}}">{{phone}}</a></p>
    </div>
    <div class="board-side">
      <div class="nextday"><span class="mono-l">{{side1_label}}</span><span class="big">{{side1_big}}</span><span class="sub">{{side1_sub}}</span></div>
      <a class="freeslot" href="#hodiny"><span class="mono-l">{{side2_label}}</span><span class="big">{{side2_big}}</span><span class="go">{{side2_link}} __ARR__</span></a>
    </div>
  </div>
</section>

<section class="intro" aria-label="O ordinaci">
  <div class="wrap intro-grid">
    <div class="intro-text">
      <p class="intro-lead">{{intro_text}}</p>
      <div class="intro-cta"><a href="{{phone_href}}" class="btn">{{intro_btn1}} __ARR__</a><a href="mailto:{{email}}" class="btn btn--out">{{intro_btn2}}</a></div>
    </div>
    <ul class="checks">{{#checks}}<li>__CHK__{{text}}</li>{{/checks}}</ul>
  </div>
</section>

<section class="sec" id="hodiny" aria-labelledby="hTitle">
  <div class="wrap">
    <div class="shead"><h2 id="hTitle"><span class="no" aria-hidden="true">01</span>{{hours_title}}</h2><p>{{hours_text}}</p></div>
    <div class="tbl-wrap">
      <table>
        <caption class="sr">{{hours_title}}</caption>
        <thead><tr><th scope="col">{{col_day}}</th><th scope="col">{{col_hours}}</th><th scope="col">{{col_extra}}</th><th scope="col">{{col_note}}</th></tr></thead>
        <tbody>{{#hours}}<tr><td>{{day}}</td><td><span class="hp">{{hours}}</span>{{#hours2}}<span class="hp">{{hours2}}</span>{{/hours2}}</td><td>{{extra}}</td><td><span class="tag">{{note}}</span></td></tr>{{/hours}}</tbody>
      </table>
    </div>
    {{#notice}}<p class="week-note">__CAL__<span>{{notice}}</span></p>{{/notice}}
  </div>
</section>

<section class="sec sec--cont" id="vyridit" aria-labelledby="qTitle">
  <div class="wrap">
    <div class="shead shead--flush"><h2 id="qTitle">{{paths_title}}</h2><p>{{paths_text}}</p></div>
    <nav class="paths" aria-label="Rychlé cesty">{{#paths}}<a class="path" href="#kontakt"><span class="no">•</span><span class="pt">{{title}}</span><span class="pd">{{text}}</span><span class="pa" aria-hidden="true">__ARR__</span></a>{{/paths}}</nav>
  </div>
</section>

<section class="sec sec--cont">
  <div class="wrap duo">
    <div id="tym">
      <span class="over">{{team_kicker}}</span>
      <h2>{{team_title}}</h2>
      <div class="mini-team">{{#team}}<div class="mt"><span class="avatar avatar--sm av0">{{initials}}</span><div><b>{{name}}</b><span>{{role}}</span></div></div>{{/team}}</div>
    </div>
    <div id="pacienti">
      <span class="over">{{np_kicker}}</span>
      <h2>{{np_title}}</h2>
      <p>{{np_text}}</p>
      <ol class="mini-steps">{{#np_steps}}<li><span><b>{{bold}}</b> {{text}}</span></li>{{/np_steps}}</ol>
      <a class="btn" href="{{phone_href}}">{{np_btn}}</a>
    </div>
  </div>
</section>

<section class="sec sec--cont sec--visit">
  <div class="wrap visit">
    <div class="visit-addr">
      <span class="over">{{visit_kicker}}</span>
      <h2>{{address}}</h2>
      <p>{{floor}}</p>
      {{#map_url}}<a class="lnk" href="{{map_url}}">Mapa a doprava</a>{{/map_url}}
    </div>
    <div class="emergency"><span class="num">{{sos_number}}</span><p><b>{{sos_title}}</b> {{sos_text}}</p></div>
  </div>
</section>
</main>

<footer class="site" id="kontakt">
  <div class="wrap foot-grid">
    <div class="foot-brand">
      <a class="logo" href="#top">__LOGO__</a>
      <p>{{foot_address}}</p>
      <p class="foot-sos"><b>{{sos_number}}</b> {{sos_title}}</p>
    </div>
    <div>
      <h2>Na stránce</h2>
      <ul><li><a href="#hodiny">{{hours_title}}</a></li><li><a href="#vyridit">{{paths_title}}</a></li><li><a href="#pacienti">{{np_title}}</a></li></ul>
    </div>
    <div>
      <h2>Kontakt</h2>
      <ul><li><a href="{{phone_href}}">{{phone}}</a></li><li><a href="mailto:{{email}}">{{email}}</a></li></ul>
    </div>
  </div>
  <div class="wrap"><div class="foot-legal"><span>{{legal}}</span></div></div>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
'''
APP = '''/* Šablona „ordinace“ – režim podle zařízení s přepínačem, větší písmo (A+) a menu na mobilu. Bez ukládání a bez síťových požadavků. */
(function () {
  var root = document.documentElement, btn = document.getElementById('burger'), nav = document.getElementById('menu'), aplus = document.getElementById('aplus');
  if (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) root.setAttribute('data-theme', 'dark');
  if (btn && nav) {
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); root.classList.toggle('menu-open', o); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('open'); root.classList.remove('menu-open'); btn.setAttribute('aria-expanded', 'false'); } });
  }
  if (aplus) aplus.addEventListener('click', function () { var on = !root.classList.contains('large'); root.classList.toggle('large', on); aplus.setAttribute('aria-pressed', String(on)); });
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
/* šablona: přístupnost */
.sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.skip{position:absolute;left:-9999px;top:0}.skip:focus{left:12px;top:12px;z-index:99;background:var(--teal);color:var(--inv);padding:.5rem .8rem}
'''


def main():
    if os.path.isdir(OUT): shutil.rmtree(OUT)
    os.makedirs(os.path.join(F, 'assets', 'fonts'))
    css = open(os.path.join(WEB, 'ukazky', 'ordinace', 'style.css'), encoding='utf-8').read()
    a = css.index('/* ==========================================================================\n   Administrace a e-maily'); z = css.index('/* ==========================================================================\n   Redesign 2026')
    css = css[:a] + css[z:]                                  # bez administrace a e-mailů; vrstva Redesign 2026 zůstává
    m = re.search(r':root\{(.*?)\n\}', css, re.S)
    names = [t[0] for t in TOKENS.values()] + [f[0] for f in FONTS.values()]
    keep = [d.strip() for d in re.split(r';\n', m.group(1)) if d.strip() and d.split(':', 1)[0].strip() not in names]
    tok = ';'.join(['%s:%s' % (t[0], t[2]) for t in TOKENS.values()] + ['%s:%s' % (f[0], f[1]) for f in FONTS.values()])
    css = css[:m.start()] + '/*@tokens*/:root{' + tok + '}/*@end*/\n:root{' + ';\n'.join(k.rstrip(';') for k in keep) + '\n}' + css[m.end():]
    d = re.search(r':root\[data-theme="dark"\]\{(.*?)\}', css, re.S)
    blk = d.group(0).replace('--page:#071A1D', '--page:var(--t-page-d)').replace('--acc:#5AD3C8', '--acc:var(--t-acc-d)').replace('--btn-hover:#5AD3C8', '--btn-hover:var(--t-acc-d)')
    assert blk.count('var(--t-') == 3, blk
    css = css[:d.start()] + blk + css[d.end():]
    header = '/* Šablona „ordinace“ (T&S Design Studio) – styly převzaté z ukázky Ordinace. Barvy a písmo mění Studio v bloku @tokens (i pozadí a akcent tmavého režimu). */\n'
    fonts = open(os.path.join(WEB, 'assets', 'fonts', 'ordinace.css'), encoding='utf-8').read()
    fonts = '\n'.join(l for l in fonts.split('\n') if l.startswith('@font-face'))
    fonts = re.sub(r'url\("([a-z0-9-]+\.woff2)(\?v=[0-9a-f]+)?"\)', r'url("fonts/\1")', fonts)
    open(os.path.join(F, 'assets', 'styles.css'), 'w', encoding='utf-8').write(header + fonts + '\n' + css + EXTRA_CSS)
    for f in re.findall(r'url\("fonts/([^"]+)"\)', fonts): shutil.copy(os.path.join(WEB, 'assets', 'fonts', f), os.path.join(F, 'assets', 'fonts', f))
    lic = ['Atkinson_Hyperlegible_Next-OFL.txt', 'JetBrains_Mono-OFL.txt']
    for l in lic: shutil.copy(os.path.join(WEB, 'assets', 'fonts', 'licence', l), os.path.join(F, 'assets', 'fonts', l))
    html = (HTML.replace('{{#alerts}}{{/alerts}}', '').replace('__LOGO__', LOGO).replace('__MENU__', MENU).replace('__MOON__', MOON).replace('__SUN__', SUN)
            .replace('__BURGER__', BURGER).replace('__ARR__', ARR).replace('__CHK__', CHK).replace('__CAL__', CAL))
    open(os.path.join(F, 'index.html'), 'w', encoding='utf-8').write(html)
    open(os.path.join(F, 'assets', 'app.js'), 'w', encoding='utf-8').write(APP)
    t = {'id': 'ordinace', 'name': 'Ordinace a zdravotní služby', 'basedOn': 'ukázka Ordinace (web/ukazky/ordinace.html)',
         'obor': ['ordinace', 'lékař', 'lékařka', 'praktický lékař', 'zubař', 'stomatolog', 'ambulance', 'klinika', 'fyzioterapie', 'veterina', 'zdravotní', 'psycholog'],
         'description': 'Jednostránkový web ordinace ve stylu orientačního systému nemocnice: upozornění nahoře (dovolená, očkování), tabule s kontaktem, výhody, tabulka ordinačních hodin, rychlé cesty, tým, registrace nových pacientů, adresa a tísňové volání. Velmi čitelné písmo, tlačítko A+, režim podle zařízení.',
         'render': ['index.html', 'assets/styles.css'],
         'tokens': dict({k: {'var': v[0], 'type': v[1], 'value': v[2], 'label': v[3]} for k, v in TOKENS.items()}, **{k: {'var': v[0], 'type': 'font', 'value': v[1], 'options': v[2], 'label': v[3]} for k, v in FONTS.items()}),
         'slots': SLOTS, 'contrast': [['text', 'page'], ['muted', 'page'], ['text', 'tint']], 'content': CONTENT,
         'licenses': ['assets/fonts/' + l for l in lic],
         'cssHints': ['.logo', '.board h1', '.intro-lead', '.shead h2', '.btn', '.path .pt', '.emergency .num', '.menu a']}
    json.dump(t, open(os.path.join(OUT, 'template.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print('šablona ordinace:', sum(len(fs) for _, _, fs in os.walk(F)), 'souborů')


if __name__ == '__main__':
    main()
