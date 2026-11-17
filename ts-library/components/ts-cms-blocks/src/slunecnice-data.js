/* ZŠ Slunečnice – data ukázky a sdílená pravidla (používá web i administrace)
   Výchozí data se počítají relativně k dnešku: kalendář pro aktuální školní rok,
   jídelníček na minulý, tento a příští školní týden, aktuality z posledních týdnů. */
(function () {
  'use strict';
  var S = window.SLUN = {};
  var esc = TS.esc;

  S.PAGES = { index: 'slunecnice.html', aktuality: 'slunecnice-aktuality.html', jidelnicek: 'slunecnice-jidelnicek.html', akce: 'slunecnice-akce.html',
    zapis: 'slunecnice-zapis.html', krouzky: 'slunecnice-krouzky.html', kontakty: 'slunecnice-kontakty.html', sprava: 'slunecnice-sprava.html' };
  S.href = function (link) {
    if (!link) return S.PAGES.index;
    if (/^(https?:|mailto:|tel:)/.test(link)) return link;
    var p = String(link).split('#');
    return (S.PAGES[p[0]] || p[0]) + (p[1] ? '#' + p[1] : '');
  };

  /* ---------- číselníky ---------- */
  S.ALLERGENS = [[1, 'obiloviny s lepkem'], [2, 'korýši'], [3, 'vejce'], [4, 'ryby'], [5, 'arašídy'], [6, 'sója'], [7, 'mléko'], [8, 'skořápkové plody'],
    [9, 'celer'], [10, 'hořčice'], [11, 'sezam'], [12, 'oxid siřičitý'], [13, 'vlčí bob'], [14, 'měkkýši']];
  S.CATS = [
    { id: 'skola', label: 'Škola', cls: 'c-1' }, { id: 'souteze', label: 'Soutěže a sport', cls: 'c-free' }, { id: 'vylety', label: 'Výlety', cls: 'c-2' },
    { id: 'jidelna', label: 'Jídelna', cls: 'c-all' }, { id: 'druzina', label: 'Družina', cls: 'c-vio' }, { id: 'rodice', label: 'Pro rodiče', cls: 'c-navy' }];
  S.cat = function (id) { return S.CATS.find(function (c) { return c.id === id; }) || S.CATS[0]; };
  S.EVTYPES = [
    { id: 'skola', label: 'Školní akce', cls: 'c-1' }, { id: 'rodice', label: 'Pro rodiče', cls: 'c-all' },
    { id: 'vylet', label: 'Výlety a exkurze', cls: 'c-2' }, { id: 'volno', label: 'Volno a prázdniny', cls: 'c-free' }];
  S.evType = function (id) { return S.EVTYPES.find(function (c) { return c.id === id; }) || S.EVTYPES[0]; };
  S.GROUPS = [
    { id: 'vedeni', label: 'Vedení školy', short: 'Vedení', bg: 'var(--sun)' },
    { id: 'tridni1', label: 'Třídní učitelé 1. stupně', short: '1. stupeň', bg: 'var(--sky-soft)' },
    { id: 'tridni2', label: 'Třídní učitelé 2. stupně', short: '2. stupeň', bg: 'var(--coral-soft)' },
    { id: 'ucitele', label: 'Další vyučující', short: 'Další vyučující', bg: 'var(--leaf-soft)' },
    { id: 'poradna', label: 'Školní poradenské pracoviště', short: 'Poradenství', bg: 'var(--vio-soft)' },
    { id: 'druzina', label: 'Školní družina', short: 'Družina', bg: 'var(--sun-soft)' },
    { id: 'jidelna', label: 'Školní jídelna', short: 'Jídelna', bg: 'var(--coral-soft)' },
    { id: 'provoz', label: 'Kancelář a provoz', short: 'Kancelář a provoz', bg: 'var(--sky-soft)' }];
  S.group = function (id) { return S.GROUPS.find(function (g) { return g.id === id; }) || S.GROUPS[0]; };
  S.CLASSES = ['1.A', '1.B', '1.C', '2.A', '2.B', '2.C', '3.A', '3.B', '4.A', '4.B', '5.A', '5.B', '6.A', '6.B', '6.C', '7.A', '7.B', '8.A', '8.B', '9.A', '9.B'];
  S.gradeOf = function (cls) { return parseInt(cls, 10) || 0; };
  S.CLUB_COLORS = [['var(--sky)', 'Modrá'], ['var(--coral)', 'Korálová'], ['var(--sun)', 'Žlutá'], ['var(--leaf)', 'Zelená'], ['var(--navy)', 'Tmavě modrá'], ['var(--vio)', 'Fialová']];
  S.CLUB_ICONS = {
    robot: ['Robot', '<rect x="5" y="8" width="14" height="11" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01M9 16h6"/>'],
    clay: ['Keramika', '<path d="M7 4h10l-1 3a6 6 0 0 1 2 5c0 4-3 8-6 8s-6-4-6-8a6 6 0 0 1 2-5Z"/>'],
    note: ['Hudba', '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>'],
    ball: ['Míč', '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4a12 12 0 0 1 0 16M12 4a12 12 0 0 0 0 16"/>'],
    abc: ['Jazyky', '<path d="M4 18 8 6l4 12M5.5 14h5M14 6h4a2 2 0 0 1 0 6h-4Zm0 6h4.5a2.5 2.5 0 0 1 0 5H14Z"/>'],
    chess: ['Šachy', '<path d="M8 20h8M9 20l1-6h4l1 6M10 14 8 8h8l-2 6M12 3v3M10 4.5h4"/>'],
    bee: ['Včela', '<ellipse cx="12" cy="14" rx="5" ry="6"/><path d="M7.5 12h9M7.5 16h9M10 8.5C8 5 5 5 4 7s1 4 4 4M14 8.5C16 5 19 5 20 7s-1 4-4 4"/>'],
    chef: ['Vaření', '<path d="M7 14a4 4 0 0 1-1-7.9A5 5 0 0 1 16 5a4 4 0 0 1 1 9Z"/><path d="M7 14v6h10v-6M7 17h10"/>'],
    mask: ['Divadlo', '<path d="M4 5c5 2 11 2 16 0v6a8 8 0 0 1-16 0Z"/><path d="M8 10h2M14 10h2M9 15c2 1.5 4 1.5 6 0"/>'],
    code: ['Programování', '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/>'],
    run: ['Běh', '<circle cx="14" cy="4.5" r="2"/><path d="m6 20 4-6 3 2 1 5M9 9l3-2 3 3 3 1M12 7l-2 7"/>'],
    brush: ['Výtvarka', '<path d="M18 3 9 12l3 3 9-9a2.1 2.1 0 0 0-3-3Z"/><path d="M9 12c-3 0-4 2-4 4s-1 3-2 3c2 2 8 2 9-4"/>']
  };
  S.clubIcon = function (k) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (S.CLUB_ICONS[k] || S.CLUB_ICONS.ball)[1] + '</svg>'; };

  /* ilustrace k aktualitám (kreslené SVG v barvách školy) */
  var FL = function (x, y, s) { return '<use href="#flower" x="' + x + '" y="' + y + '" width="' + s + '" height="' + s + '"/>'; };
  S.ARTS = {
    papir: ['Sběr papíru', '#DDF4E7', '<g transform="translate(150 50)"><rect x="0" y="40" width="90" height="70" rx="6" fill="#00964F"/><rect x="10" y="20" width="70" height="30" rx="4" fill="#fff"/><rect x="18" y="10" width="54" height="24" rx="4" fill="#F4F6FC"/><rect x="26" y="0" width="38" height="20" rx="3" fill="#fff"/><path d="M30 70l10 10 22-22" stroke="#fff" stroke-width="7" fill="none" stroke-linecap="round"/></g><circle cx="90" cy="110" r="26" fill="#FFE800"/><text x="90" y="118" font-family="Baloo 2, sans-serif" font-weight="800" font-size="22" fill="#1B2A57" text-anchor="middle">1.</text><path d="M78 134l-6 26 18-10 18 10-6-26" fill="#F2665A"/>'],
    hory: ['Hory a výlet', '#DDF1FB', '<path d="M0 130 70 60l50 44 40-34 70 60 90-70v120H0Z" fill="#43B2E4" opacity=".55"/><path d="M0 150 90 90l60 40 60-30 110 50v30H0Z" fill="#00964F"/><g transform="translate(200 104)"><path d="M0 40 24 0l24 40Z" fill="#FFE800"/><path d="M24 0v40" stroke="#1B2A57" stroke-width="3"/></g><circle cx="60" cy="40" r="18" fill="#FFE800"/><g fill="#007F43"><path d="M40 150l10-30 10 30Z"/><path d="M60 150l10-36 10 36Z"/><path d="M270 150l8-26 8 26Z"/></g>'],
    robot: ['Robot', '#FFF4D1', '<g transform="translate(110 40)"><rect x="0" y="20" width="100" height="76" rx="18" fill="#1B2A57"/><rect x="18" y="38" width="64" height="30" rx="10" fill="#43B2E4"/><circle cx="36" cy="53" r="7" fill="#fff"/><circle cx="64" cy="53" r="7" fill="#fff"/><rect x="46" y="0" width="8" height="22" fill="#1B2A57"/><circle cx="50" cy="0" r="8" fill="#F2665A"/><rect x="-18" y="40" width="18" height="10" rx="5" fill="#1B2A57"/><rect x="100" y="40" width="18" height="10" rx="5" fill="#1B2A57"/><rect x="30" y="78" width="40" height="8" rx="4" fill="#FFE800"/></g><g stroke="#FFE800" stroke-width="4" stroke-linecap="round"><path d="M60 50l14 8M58 90h16M250 50l-14 8M252 90h-16"/></g>'],
    vcely: ['Včelín', '#FFF4D1', '<path d="M0 148h320v32H0Z" fill="#00964F"/><g transform="translate(124 46)"><rect x="-4" y="0" width="80" height="16" rx="4" fill="#F2665A"/><rect x="2" y="16" width="68" height="30" rx="3" fill="#FFE800"/><rect x="2" y="48" width="68" height="30" rx="3" fill="#FFB020"/><rect x="28" y="66" width="16" height="6" rx="3" fill="#7A4B1E"/><rect x="8" y="78" width="7" height="24" fill="#7A4B1E"/><rect x="57" y="78" width="7" height="24" fill="#7A4B1E"/></g><path d="M44 112c14-46 54-58 70-34" stroke="#1B2A57" stroke-width="2.4" stroke-dasharray="3 7" fill="none" stroke-linecap="round"/><path d="M276 64c-10 30-40 40-58 22" stroke="#1B2A57" stroke-width="2.4" stroke-dasharray="3 7" fill="none" stroke-linecap="round"/><g transform="translate(42 118)"><ellipse rx="13" ry="9" fill="#FFE800"/><path d="M-4-9v18M4-9v18" stroke="#1B2A57" stroke-width="3.4"/><ellipse cx="-3" cy="-12" rx="8" ry="5" fill="#fff"/></g><g transform="translate(278 58)"><ellipse rx="11" ry="8" fill="#FFE800"/><path d="M-3-8v16M4-8v16" stroke="#1B2A57" stroke-width="3"/><ellipse cx="2" cy="-11" rx="7" ry="4.5" fill="#fff"/></g>' + FL(232, 120, 34) + FL(268, 132, 26) + FL(56, 136, 28)],
    florbal: ['Florbal', '#DDF4E7', '<path d="M0 140h320v40H0Z" fill="#00964F"/><path d="M0 140h320" stroke="#fff" stroke-width="3"/><g transform="translate(196 62)"><path d="M0 78V12h86v66" fill="none" stroke="#F2665A" stroke-width="7" stroke-linejoin="round"/><path d="M8 20h70M8 36h70M8 52h70M8 68h70M22 14v62M40 14v62M58 14v62" stroke="#fff" stroke-width="2"/></g><path d="M58 30l52 96" stroke="#1B2A57" stroke-width="7" stroke-linecap="round"/><path d="M104 116c4 12 20 16 34 10" stroke="#1B2A57" stroke-width="11" stroke-linecap="round" fill="none"/><g stroke="#43B2E4" stroke-width="4" stroke-linecap="round"><path d="M128 100h22M134 112h26M126 88h16"/></g><circle cx="176" cy="104" r="13" fill="#FFE800"/><g fill="#1B2A57"><circle cx="171" cy="100" r="2.2"/><circle cx="180" cy="102" r="2.2"/><circle cx="175" cy="109" r="2.2"/></g>'],
    zelenina: ['Zelenina', '#FFE3DF', '<rect x="70" y="104" width="180" height="56" rx="8" fill="#B07A3E"/><path d="M70 122h180M70 140h180" stroke="#8C5E2C" stroke-width="3"/><ellipse cx="120" cy="98" rx="34" ry="24" fill="#F28B30"/><path d="M104 80c6 20 6 30 0 40M136 80c-6 20-6 30 0 40M120 76v44" stroke="#D96D18" stroke-width="3" fill="none"/><rect x="116" y="66" width="8" height="12" rx="3" fill="#00964F"/><g transform="translate(176 54) rotate(18)"><path d="M0 0 12 64 24 0Z" fill="#F2665A"/><path d="M6 0c-6-14 0-22 6-10 6-12 12-4 6 10" fill="#00964F"/></g><g transform="translate(204 58) rotate(-10)"><path d="M0 0 11 58 22 0Z" fill="#F28B30"/><path d="M5 0c-6-12 0-20 6-9 6-11 12-3 6 9" fill="#00964F"/></g><circle cx="238" cy="92" r="20" fill="#00A95C"/><path d="M226 86c8 4 16 4 24 0M228 98c6 3 14 3 20 0" stroke="#007F43" stroke-width="3" fill="none"/><circle cx="56" cy="40" r="16" fill="#FFE800"/>'],
    hriste: ['Hřiště', '#DDF1FB', '<path d="M0 146h320v34H0Z" fill="#00964F"/><g stroke="#F2665A" stroke-width="5" stroke-linecap="round"><path d="M70 146 120 46l50 100"/></g><g stroke="#1B2A57" stroke-width="2.4"><path d="M86 114h68M98 90h44M110 66h20M95 146l25-100 25 100"/></g><g stroke="#1B2A57" stroke-width="5" stroke-linecap="round"><path d="M200 146V70h80v76M196 70h88"/></g><path d="M226 70v44M254 70v44" stroke="#1B2A57" stroke-width="2"/><rect x="218" y="112" width="44" height="10" rx="5" fill="#FFE800"/><circle cx="276" cy="34" r="18" fill="#FFE800"/><g fill="#fff"><ellipse cx="44" cy="40" rx="26" ry="11"/><ellipse cx="62" cy="34" rx="17" ry="11"/></g>'],
    knihy: ['Knihy', '#FFE3DF', '<rect x="56" y="140" width="208" height="10" rx="3" fill="#7A4B1E"/><g><rect x="66" y="66" width="22" height="74" rx="3" fill="#1B2A57"/><rect x="90" y="54" width="26" height="86" rx="3" fill="#43B2E4"/><rect x="118" y="74" width="18" height="66" rx="3" fill="#FFE800"/><rect x="138" y="60" width="24" height="80" rx="3" fill="#00964F"/><rect x="164" y="80" width="18" height="60" rx="3" fill="#F2665A"/><rect x="186" y="70" width="18" height="70" rx="3" fill="#8A5CD0" transform="rotate(14 195 140)"/></g><g fill="#fff" opacity=".8"><rect x="94" y="66" width="18" height="4" rx="2"/><rect x="142" y="72" width="16" height="4" rx="2"/><rect x="70" y="80" width="14" height="4" rx="2"/></g><g transform="translate(222 104)"><path d="M0 36V6c12-6 24-6 32 2 8-8 20-8 32-2v30c-12-6-24-6-32 2-8-8-20-8-32-2Z" fill="#fff"/><path d="M32 8v30" stroke="#E1E6F2" stroke-width="2"/></g>'],
    zakovska: ['Žákovská knížka', '#DDF1FB', '<g transform="translate(92 26)"><rect x="0" y="0" width="136" height="128" rx="16" fill="#1B2A57"/><rect x="10" y="12" width="116" height="104" rx="8" fill="#fff"/><g fill="#E1E6F2"><rect x="22" y="26" width="58" height="8" rx="4"/><rect x="22" y="50" width="58" height="8" rx="4"/><rect x="22" y="74" width="58" height="8" rx="4"/><rect x="22" y="98" width="40" height="8" rx="4"/></g><g font-family="Baloo 2, sans-serif" font-weight="800" font-size="17" fill="#1B2A57"><text x="96" y="36">1</text><text x="96" y="60">2</text><text x="96" y="84">1</text></g><circle cx="102" cy="100" r="10" fill="#00964F"/><path d="M97 100l4 4 7-8" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/></g><g transform="translate(244 58) rotate(28)"><rect x="0" y="0" width="14" height="78" rx="3" fill="#FFE800"/><path d="M0 78l7 16 7-16Z" fill="#F2665A"/><rect x="0" y="0" width="14" height="10" rx="3" fill="#F2665A"/></g>'],
    foto: ['Fotografování', '#F4F6FC', '<g transform="translate(96 48)"><rect x="0" y="16" width="128" height="88" rx="16" fill="#1B2A57"/><rect x="38" y="4" width="46" height="18" rx="6" fill="#1B2A57"/><circle cx="64" cy="60" r="30" fill="#43B2E4"/><circle cx="64" cy="60" r="18" fill="#DDF1FB"/><circle cx="56" cy="52" r="6" fill="#fff"/><rect x="100" y="28" width="16" height="10" rx="3" fill="#FFE800"/></g><g fill="#FFE800"><path d="M60 40l5 12 12 5-12 5-5 12-5-12-12-5 12-5Z"/><path d="M262 120l4 9 9 4-9 4-4 9-4-9-9-4 9-4Z"/></g><rect x="244" y="40" width="44" height="52" rx="4" fill="#fff" transform="rotate(10 266 66)"/><rect x="250" y="46" width="32" height="30" fill="#00964F" transform="rotate(10 266 66)"/>'],
    krouzky: ['Kroužky', '#EDE6FB', '<g transform="translate(70 42)"><circle cx="36" cy="36" r="36" fill="#43B2E4"/><path d="M14 36h44M36 14v44" stroke="#fff" stroke-width="4"/><circle cx="36" cy="36" r="22" fill="none" stroke="#fff" stroke-width="4"/></g><g transform="translate(160 36)"><path d="M30 0l9 19 21 3-15 15 4 21-19-10-19 10 4-21L0 22l21-3Z" fill="#FFE800"/></g><g transform="translate(212 92)"><path d="M18 50V8l40-8v42" stroke="#1B2A57" stroke-width="6" fill="none"/><circle cx="10" cy="50" r="10" fill="#1B2A57"/><circle cx="50" cy="44" r="10" fill="#1B2A57"/></g><g transform="translate(118 112)"><path d="M0 40c0-22 18-40 40-40 18 0 30 10 30 22 0 8-8 10-14 10-8 0-10 8-4 14-20 8-52 4-52-6Z" fill="#F2665A"/><circle cx="22" cy="20" r="5" fill="#fff"/><circle cx="40" cy="14" r="5" fill="#FFE800"/><circle cx="54" cy="22" r="5" fill="#00964F"/></g>'],
    slunce: ['Léto', '#FFF4D1', '<circle cx="160" cy="84" r="42" fill="#FFE800"/><g stroke="#FFE800" stroke-width="7" stroke-linecap="round"><path d="M160 20v12M160 136v12M96 84h12M212 84h12M115 39l8 8M197 121l8 8M205 39l-8 8M123 121l-8 8"/></g><path d="M0 150c40-14 80-14 120 0s80 14 120 0 60-10 80-6v36H0Z" fill="#43B2E4"/><path d="M0 164c40-10 80-10 120 0s80 10 120 0 60-8 80-4v20H0Z" fill="#DDF1FB" opacity=".6"/>'],
    skola: ['Škola', '#DDF4E7', '<path d="M0 150h320v30H0Z" fill="#00964F"/><rect x="100" y="78" width="120" height="72" rx="4" fill="#F2665A"/><path d="M88 82 160 40l72 42Z" fill="#1B2A57"/><circle cx="160" cy="66" r="10" fill="#fff"/><g fill="#DDF1FB" stroke="#fff" stroke-width="3"><rect x="112" y="94" width="24" height="20" rx="3"/><rect x="184" y="94" width="24" height="20" rx="3"/></g><path d="M146 150v-26a14 14 0 0 1 28 0v26Z" fill="#1B2A57"/><g><rect x="44" y="110" width="8" height="40" rx="3" fill="#7A4B1E"/><circle cx="48" cy="100" r="22" fill="#00A95C"/></g>' + FL(250, 124, 30) + FL(276, 132, 22)]
  };
  S.FLOWER = '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><symbol id="flower" viewBox="0 0 200 200"><g fill="none" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="155.9" cy="96.8" r="3.4" fill="#FF48B0" stroke="none"/><circle cx="168.3" cy="96.1" r="2.9" fill="#FF48B0" stroke="none"/><circle cx="180.7" cy="95.4" r="2.4" fill="#FF48B0" stroke="none"/><circle cx="193.1" cy="94.7" r="1.9" fill="#FF48B0" stroke="none"/><path d="M144.1,127.6L163.0,139.4" stroke="#3255A4"/><circle cx="178.4" cy="149.0" r="6" fill="none" stroke="#3255A4"/><circle cx="119.7" cy="152.4" r="3.4" fill="#00A95C" stroke="none"/><circle cx="124.4" cy="164.9" r="2.9" fill="#00A95C" stroke="none"/><circle cx="129.0" cy="177.4" r="2.4" fill="#00A95C" stroke="none"/><circle cx="133.7" cy="189.8" r="1.9" fill="#00A95C" stroke="none"/><path d="M82.6,149.0L75.7,168.5" stroke="#FF48B0"/><circle cx="70.0" cy="184.4" r="6" fill="none" stroke="#FF48B0"/><path d="M65.1,139.2L27.8,162.1L56.0,128.6Z" fill="#3255A4" stroke="#3255A4"/><path d="M47.7,101.6L40.8,88.9L31.8,100.2L24.9,87.6L16.0,98.8L9.1,86.2L0.2,97.5" stroke="#00A95C"/><path d="M59.6,67.3L42.4,53.4" stroke="#FF48B0"/><circle cx="28.4" cy="42.0" r="6" fill="none" stroke="#FF48B0"/><path d="M84.7,50.3L78.8,31.1" stroke="#3255A4"/><circle cx="74.0" cy="15.4" r="6" fill="none" stroke="#3255A4"/><path d="M115.1,50.2L123.5,25.5" stroke="#00A95C"/><path d="M137.3,63.2L149.0,69.3L146.0,56.4L157.7,62.5L154.7,49.6L166.4,55.7L163.4,42.8" stroke="#FF48B0"/></g><circle cx="100" cy="100" r="46" fill="#FFE800"/><circle cx="103" cy="102" r="46" fill="none" stroke="#FF48B0" stroke-width="2" stroke-dasharray="1 7" opacity=".7"/></symbol></defs></svg>';
  /* karta aktuality (web i náhled v administraci) */
  S.postCard = function (p, href, preview) {
    var c = S.cat(p.cat), d = TS.parse(p.date || TS.today()), tx = function (x) { return esc(S.ty(x)); };
    var more = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    return '<article class="post' + (p.pinned ? ' pinned' : '') + '"><div class="post-art">' + S.art(p.art) +
      '<div class="date"><b>' + d.getDate() + '</b><small>' + TS.MG[d.getMonth()] + '</small></div>' + (p.pinned ? '<span class="pin-badge">Připnuto</span>' : '') + '</div>' +
      '<div class="post-body"><div class="post-tags"><span class="chip ' + c.cls + '">' + esc(c.label) + '</span>' + (p.cls ? '<span class="post-cls">' + esc(p.cls) + '</span>' : '') + '</div>' +
      '<h3>' + (tx(p.title) || '<span class="sa-ph">Nadpis aktuality</span>') + '</h3><p>' + (tx(p.perex) || '<span class="sa-ph">Krátký text, který se ukáže na kartě.</span>') + '</p>' +
      (preview ? '<span class="more">Číst dál ' + more + '</span>' : '<a class="more" href="' + href + '" aria-label="Číst dál: ' + esc(p.title) + '">Číst dál ' + more + '</a>') + '</div></article>';
  };
  S.art = function (k) {
    var a = S.ARTS[k] || S.ARTS.skola;
    return '<svg viewBox="0 0 320 180" aria-hidden="true" preserveAspectRatio="xMidYMid slice"><rect width="320" height="180" fill="' + a[1] + '"/>' + a[2] + '</svg>';
  };

  /* ---------- text a datum ---------- */
  /* nedělitelné mezery: tisíce, číslo + jednotka, jednopísmenné předložky */
  S.ty = function (s) {
    return String(s == null ? '' : s)
      .replace(/(\d) (?=\d{3}(?!\d))/g, '$1\u00a0')
      .replace(/(\d) (?=(Kč|kg|g|km|m|min|h|%|tun|dní|dnů|let|minut|hodin|míst|lekcí|dětí|žáků)(?![a-zá-ž]))/gi, '$1\u00a0')
      .replace(/(?<=^|[\s(„\u00a0])([vVkKsSzZoOuUaAiI]) (?=\S)/g, '$1\u00a0');
  };
  S.paras = function (s) { return String(s || '').split(/\n\s*\n|\r\n\s*\r\n/).map(function (p) { return p.trim(); }).filter(Boolean); };
  var WDA = ['v neděli', 'v pondělí', 'v úterý', 've středu', 've čtvrtek', 'v pátek', 'v sobotu'];
  S.WDA = WDA;
  S.onDay = function (d) { return WDA[TS.wd(d)] + ' ' + TS.fmtDayMonth(d); };
  S.lc = function (s) { s = String(s || ''); return s.charAt(0).toLowerCase() + s.slice(1); };
  S.dayMonthRange = function (a, b) {
    if (!b || a === b) return TS.fmtDayMonth(a);
    var A = TS.parse(a), B = TS.parse(b);
    if (A.getMonth() === B.getMonth() && A.getFullYear() === B.getFullYear()) return A.getDate() + '.–' + TS.fmtDayMonth(b);
    return TS.fmtDayMonth(a) + ' – ' + TS.fmtDayMonth(b);
  };
  S.weekLabel = function (mon) { return S.dayMonthRange(mon, TS.addDays(mon, 4)); };
  S.monthName = function (ym) { var y = +ym.slice(0, 4), m = +ym.slice(5, 7); return TS.cap(TS.MN[m - 1]) + ' ' + y; };
  function ymd(y, m, d) { return TS.iso(new Date(y, m - 1, d, 12)); }
  S.ymd = ymd;
  function nth(y, m, wd, n) { var f = new Date(y, m - 1, 1, 12).getDay(); return ymd(y, m, 1 + (wd - f + 7) % 7 + 7 * (n - 1)); }
  function lastOf(y, m, wd) { var d = new Date(y, m, 0, 12); return ymd(y, m, d.getDate() - (d.getDay() - wd + 7) % 7); }
  function isWknd(s) { var w = TS.wd(s); return w === 0 || w === 6; }
  function wkBefore(s) { while (isWknd(s)) s = TS.addDays(s, -1); return s; }
  function wkAfter(s) { while (isWknd(s)) s = TS.addDays(s, 1); return s; }
  S.isWknd = isWknd;
  S.easter = function (y) {
    var a = y % 19, b = Math.floor(y / 100), c = y % 100, d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25), g = Math.floor((b - f + 1) / 3),
      h = (19 * a + b - d - g + 15) % 30, i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7, m = Math.floor((a + 11 * h + 22 * l) / 451);
    return ymd(y, Math.floor((h + l - 7 * m + 114) / 31), ((h + l - 7 * m + 114) % 31) + 1);
  };
  /* školní rok, který právě běží (od července už ten nadcházející) */
  S.schoolYear = function (today) { var d = TS.parse(today); return d.getMonth() >= 6 ? d.getFullYear() : d.getFullYear() - 1; };

  /* ---------- kalendář školního roku (svátky, prázdniny a akce podle pravidel) ---------- */
  function calendar(Y) {
    var E = [], n = 0, N = Y + 1;
    function add(title, type, from, to, o) {
      o = o || {};
      var e = { id: 'e' + (++n), title: title, type: type, from: from, to: to || from, allDay: o.start == null, start: o.start == null ? 480 : o.start, end: o.end == null ? 960 : o.end,
        place: o.place || '', grades: o.grades || [], desc: o.desc || '', key: o.key || '' };
      E.push(e); return e;
    }
    function off(d) { return isWknd(d) || E.some(function (e) { return e.type === 'volno' && d >= e.from && d <= e.to; }); }
    function sd(d) { while (off(d)) d = TS.addDays(d, 1); return d; }
    function sdBack(d) { while (off(d)) d = TS.addDays(d, -1); return d; }
    var G1 = [1, 2, 3, 4, 5], SV = 'Státní svátek. Škola, družina i jídelna jsou zavřené.';
    var lastDay = wkBefore(ymd(N, 6, 30)), d, w;
    /* volno a prázdniny */
    add('Letní prázdniny', 'volno', ymd(Y, 7, 1), ymd(Y, 8, 31), { desc: 'Škola je zavřená. Kancelář má o prázdninách úřední hodiny v úterý 9:00–12:00.' });
    d = ymd(Y, 9, 28); if (!isWknd(d)) add('Den české státnosti', 'volno', d, d, { desc: SV });
    d = ymd(Y, 10, 28); w = TS.wd(d);
    if (!isWknd(d)) add('Den vzniku samostatného československého státu', 'volno', d, d, { desc: SV });
    var po = { 1: [1, 2], 2: [-1, 1], 3: [1, 2], 4: [-1, 1], 5: [-2, -1], 6: [-2, -1], 0: [1, 2] }[w];
    add('Podzimní prázdniny', 'volno', TS.addDays(d, po[0]), TS.addDays(d, po[1]), { desc: 'Žáci nemají vyučování. Družina je otevřená pro předem přihlášené děti, jídelna nevaří – oběd a svačinu dejte dětem s sebou.' });
    d = ymd(Y, 11, 17); if (!isWknd(d)) add('Den boje za svobodu a demokracii', 'volno', d, d, { desc: SV });
    var back = wkAfter(ymd(N, 1, 3));
    add('Vánoční prázdniny', 'volno', ymd(Y, 12, 23), TS.addDays(back, -1), { desc: 'Družina ani jídelna nejsou v provozu. Vyučování začíná ' + S.onDay(back) + '.' });
    var pol = lastOf(N, 1, 4);
    add('Pololetní prázdniny', 'volno', TS.addDays(pol, 1), TS.addDays(pol, 1), { desc: 'Jednodenní volno po pololetním vysvědčení. Družina je otevřená pro předem přihlášené děti, jídelna nevaří.' });
    var jp = nth(N, 3, 1, 1);
    add('Jarní prázdniny', 'volno', jp, TS.addDays(jp, 4), { desc: 'Jarní prázdniny pro Prahu 4. Škola, družina i jídelna jsou zavřené.' });
    var E0 = S.easter(N);
    add('Velikonoční prázdniny', 'volno', TS.addDays(E0, -3), TS.addDays(E0, 1), { desc: 'Ve čtvrtek jsou velikonoční prázdniny, Velký pátek a Velikonoční pondělí jsou státní svátky.' });
    [[1, 'Svátek práce'], [8, 'Den vítězství']].forEach(function (x) { var q = ymd(N, 5, x[0]); if (!isWknd(q)) add(x[1], 'volno', q, q, { desc: SV }); });
    var m8 = ymd(N, 5, 8), rv = { 2: -1, 4: 1, 6: -1, 0: 1 }[TS.wd(m8)], rvd = rv != null ? TS.addDays(m8, rv) : nth(N, 5, 5, 3);
    add('Ředitelské volno', 'volno', rvd, rvd, { desc: 'Z organizačních důvodů (revize elektroinstalace v celé budově). Družina ani jídelna nejsou v provozu.' });
    add('Letní prázdniny', 'volno', TS.addDays(lastDay, 1), ymd(N, 8, 31), { desc: 'Krásné léto! Nový školní rok začíná ' + S.onDay(wkAfter(ymd(N, 9, 1))) + '. Kancelář má o prázdninách úřední hodiny v úterý 9:00–12:00.' });

    /* akce školy */
    add('Zahájení školního roku', 'skola', wkAfter(ymd(Y, 9, 1)), null, { start: 480, end: 540, place: 'Školní zahrada a kmenové třídy', desc: 'Prvňáčky slavnostně přivítáme v 8:00 na školní zahradě, ostatní třídy jdou rovnou do svých tříd. Vyučování končí v 8:45, družina je otevřená od 6:30 do 17:30.' });
    var ak = TS.weekStart(ymd(Y, 9, 16));
    add('Adaptační kurz 6. tříd', 'vylet', ak, TS.addDays(ak, 2), { grades: [6], place: 'Kvilda, Šumava', desc: 'Tři dny s třídními učiteli a školní psycholožkou. Odjezd v pondělí v 8:00 od školy, návrat ve středu kolem 16:00.' });
    var sb = TS.weekStart(ymd(Y, 9, 23));
    add('Sběr papíru', 'skola', sd(sb), TS.addDays(sd(sb), 2), { place: 'Kontejner u hlavní brány', desc: 'Kontejner je otevřený každé ráno 7:00–8:00. Papír svažte provázkem, výtěžek jde na vybavení školní zahrady.' });
    add('Třídní schůzky', 'rodice', sd(nth(Y, 10, 2, 1)), null, { start: 1020, end: 1140, place: 'Kmenové třídy', desc: 'Od 17:00 ve třídách s třídními učiteli, potom konzultace u jednotlivých vyučujících do 19:00.' });
    add('Fotografování tříd', 'skola', sd(nth(Y, 10, 3, 2)), null, { start: 480, end: 720, place: 'Aula', desc: 'Společné fotky tříd a portréty dětí, které mají souhlas rodičů. Objednávku fotek vyplníte v žákovské knížce, platí se až při převzetí.' });
    add('Divadlo ve škole: Broučci', 'skola', sd(nth(Y, 10, 4, 3)), null, { start: 540, end: 600, grades: [1, 2, 3], place: 'Tělocvična', desc: 'Loutkové představení pro 1.–3. třídy. Vstupné 80 Kč vybírají třídní učitelky.' });
    add('Den stromů: sázíme na zahradě', 'skola', sd(ymd(Y, 10, 20)), null, { place: 'Školní zahrada', desc: 'Každá třída zasadí na zahradě ovocný stromek nebo keř. Pracovní oblečení a rukavice s sebou.' });
    add('Exkurze do Národního technického muzea', 'vylet', sd(nth(Y, 10, 4, 4)), null, { start: 495, end: 780, grades: [8, 9], place: 'Národní technické muzeum, Praha 7', desc: 'Odjezd v 8:15 od školy, návrat kolem 13:00 na oběd. Jízdenku na MHD a svačinu s sebou.' });
    add('Dýňování v družině', 'skola', sdBack(TS.addDays(ymd(Y, 10, 28), -1)), null, { start: 900, end: 990, grades: G1, place: 'Školní družina a zahrada', desc: 'Dlabeme dýně a za šera je rozsvítíme na zahradě. Pro děti z družiny, rodiče jsou vítáni od 16:00.' });
    add('Den otevřených dveří', 'rodice', sd(nth(Y, 11, 4, 2)), null, { start: 480, end: 960, key: 'dod', place: 'Celá škola', desc: 'Prohlídka tříd, družiny a zahrady. Ukázkové hodiny v 1. třídách začínají v 9:00 a v 10:00, učitelky budoucích prvňáčků jsou tu celý den.' });
    add('Konzultace s vyučujícími', 'rodice', sd(nth(Y, 11, 2, 4)), null, { start: 960, end: 1110, place: 'Kabinety vyučujících', desc: 'Hodnocení za 1. čtvrtletí. Čas si s vyučujícím domluvte přes žákovskou knížku.' });
    add('Mikulášská nadílka', 'skola', sdBack(wkBefore(ymd(Y, 12, 5))), null, { start: 540, end: 660, grades: G1, place: 'Třídy 1. stupně', desc: 'Deváťáci chystají pro malé spolužáky program s Mikulášem, čerty a anděly.' });
    var d22 = ymd(Y, 12, 22);
    add('Vánoční jarmark a koncert sboru', 'rodice', sdBack(TS.addDays(d22, -((TS.wd(d22) - 4 + 7) % 7))), null, { start: 960, end: 1110, place: 'Školní zahrada', desc: 'Od 16:00 stánky tříd s výrobky dětí, v 17:00 koncert sboru Slunečnice. Výtěžek půjde na školní výlety.' });
    var lvk = sd(nth(N, 1, 1, 3));
    add('Lyžařský kurz 7. tříd', 'vylet', lvk, TS.addDays(lvk, 4), { grades: [7], place: 'Pec pod Sněžkou, Krkonoše', desc: 'Pět dní na sjezdovkách s instruktory. Odjezd v pondělí v 7:30 od školy, cena 6 900 Kč zahrnuje ubytování, plnou penzi a skipas.' });
    add('Pololetní vysvědčení', 'skola', sdBack(pol), null, { desc: 'Vyučování končí po 4. vyučovací hodině, jídelna vydává obědy od 11:00. Družina je otevřená jako obvykle.' });
    add('Škola nanečisto', 'rodice', sd(nth(N, 2, 3, 1)), null, { start: 900, end: 960, key: 'nanecisto', place: 'Třídy 1. stupně, přízemí', desc: 'Hravé odpoledne pro předškoláky a jejich rodiče: kreslíme, počítáme a poznáváme školu. Přihlášení není potřeba, další setkání jsou každou středu v únoru.' });
    add('Masopustní průvod', 'skola', sd(TS.addDays(E0, -47)), null, { start: 600, end: 690, grades: G1, place: 'Školní zahrada a ulice Na Slunečnici', desc: 'Masky si děti vyrábějí ve výtvarné výchově. Rodiče jsou vítáni na zahradě, koblihy peče školní jídelna.' });
    add('Recitační soutěž – školní kolo', 'skola', sd(nth(N, 2, 4, 2)), null, { start: 540, end: 720, place: 'Aula', desc: 'Nejlepší recitátoři z každé třídy postupují do obvodního kola Prahy 4.' });
    add('Registrace k zápisu do 1. třídy', 'rodice', ymd(N, 3, 1), ymd(N, 3, 31), { key: 'registrace', desc: 'Vyplníte elektronický formulář na webu školy a vyberete si čas zápisu. Registrovat se můžete i v kanceláři školy.' });
    add('Matematický klokan', 'skola', sd(nth(N, 3, 5, 3)), null, { start: 480, end: 600, grades: [2, 3, 4, 5, 6, 7, 8, 9], place: 'Kmenové třídy', desc: 'Mezinárodní matematická soutěž v kategoriích Cvrček, Klokánek, Benjamín a Kadet.' });
    var zap = sd(nth(N, 4, 2, 2));
    add('Zápis do 1. třídy', 'rodice', zap, TS.addDays(zap, 1), { start: 840, end: 1080, key: 'zapis', place: 'Třídy 1. stupně, přízemí', desc: 'Zápis trvá zhruba 30 minut a probíhá hravou formou v doprovodu rodiče. Čas si vyberete při registraci.' });
    add('Třídní schůzky', 'rodice', sd(nth(N, 4, 2, 3)), null, { start: 1020, end: 1140, place: 'Kmenové třídy', desc: 'Hodnocení za 3. čtvrtletí. Od 17:00 ve třídách, konzultace u vyučujících do 19:00.' });
    add('Den Země na školní zahradě', 'skola', sd(ymd(N, 4, 22)), null, { place: 'Školní zahrada', desc: 'Celý den venku: sázení, úklid okolí školy, včelí stezka a ochutnávka medu ze školního včelína.' });
    add('Besídka ke Dni matek', 'rodice', sd(nth(N, 5, 4, 2)), null, { start: 960, end: 1020, grades: G1, place: 'Aula', desc: 'Děti z družiny zpívají, tančí a hrají divadlo. Srdečně zveme maminky, babičky i tatínky.' });
    add('Výlet do zoologické zahrady', 'vylet', sd(lastOf(N, 5, 2)), null, { start: 480, end: 780, grades: [1, 2], place: 'Zoologická zahrada Praha', desc: 'Odjezd v 8:00 objednaným autobusem, návrat ve 13:00 na oběd. Vstupné 150 Kč vybírají třídní učitelky.' });
    add('Informační schůzka pro rodiče budoucích prvňáčků', 'rodice', sd(nth(N, 6, 2, 2)), null, { start: 1020, end: 1080, place: 'Aula', desc: 'Seznámíte se s třídními učitelkami, dozvíte se, co děti potřebují na 1. září, a vyřídíte přihlášku do družiny a jídelny.' });
    add('Sportovní den', 'skola', sd(nth(N, 6, 5, 2)), null, { start: 480, end: 720, place: 'Školní hřiště', desc: 'Štafety, přetahovaná a turnaj ve vybíjené. Sportovní oblečení a pití s sebou.' });
    var vy = sd(nth(N, 6, 2, 3));
    add('Školní výlety', 'vylet', vy, TS.addDays(vy, 2), { desc: 'Každá třída jede na svůj výlet. Program, cenu a čas odjezdu posílají třídní učitelé v žákovské knížce.' });
    add('Zahradní slavnost a rozloučení s deváťáky', 'rodice', sdBack(TS.addDays(lastDay, -((TS.wd(lastDay) - 4 + 7) % 7 || 7))), null, { start: 960, end: 1140, place: 'Školní zahrada', desc: 'Vystoupení tříd, opékání buřtů a slavnostní šerpování deváťáků. Rodiče jsou srdečně zváni.' });
    add('Konec školního roku', 'skola', lastDay, null, { desc: 'Vysvědčení předáme ve 2. vyučovací hodině, vyučování končí v 9:40. Jídelna vydává obědy od 10:30.' });
    E.forEach(function (e) { e.desc = S.ty(e.desc); });
    return E;
  }
  S.calendar = calendar;

  /* ---------- práce s kalendářem ---------- */
  S.sortEv = function (a, b) { return a.from < b.from ? -1 : a.from > b.from ? 1 : (a.allDay ? 0 : a.start) - (b.allDay ? 0 : b.start); };
  S.events = function (D) { return D.events.slice().sort(S.sortEv); };
  S.evOn = function (D, d) { return S.events(D).filter(function (e) { return d >= e.from && d <= e.to; }); };
  S.evByKey = function (D, k) { return D.events.find(function (e) { return e.key === k; }) || null; };
  S.evById = function (D, id) { return D.events.find(function (e) { return e.id === id; }) || null; };
  S.volnoOn = function (D, d) {
    var l = D.events.filter(function (e) { return e.type === 'volno' && d >= e.from && d <= e.to; });
    l.sort(function (a, b) { return TS.diffDays(a.from, a.to) - TS.diffDays(b.from, b.to); });
    return l[0] || null;
  };
  S.isSchoolDay = function (D, d) { return !isWknd(d) && !S.volnoOn(D, d); };
  S.nextSchoolDay = function (D, d, incl) {
    if (!incl) d = TS.addDays(d, 1);
    for (var i = 0; i < 140; i++) { if (S.isSchoolDay(D, d)) return d; d = TS.addDays(d, 1); }
    return d;
  };
  S.upcoming = function (D, from, n, filter) {
    return S.events(D).filter(function (e) { return e.to >= from && (!filter || filter(e)); }).slice(0, n || 999);
  };
  S.gradesTxt = function (g) {
    g = (g || []).slice().sort(function (a, b) { return a - b; });
    if (!g.length || g.length === 9) return 'celá škola';
    if (g.join() === '1,2,3,4,5') return '1. stupeň';
    if (g.join() === '6,7,8,9') return '2. stupeň';
    if (g.length === 1) return g[0] + '. třídy';
    if (g.length === 2) return g[0] + '. a ' + g[1] + '. třídy';
    if (g.every(function (x, i) { return !i || x === g[i - 1] + 1; })) return g[0] + '.–' + g[g.length - 1] + '. třídy';
    return g.slice(0, -1).join('., ') + '. a ' + g[g.length - 1] + '. třídy';
  };
  S.audience = function (g) { var t = S.gradesTxt(g); return t === 'celá škola' ? 'Všichni' : t; };
  S.forGrade = function (e, gr) { return !gr || !e.grades || !e.grades.length || e.grades.indexOf(+gr) >= 0; };
  S.evTime = function (e) { return e.allDay ? '' : TS.hm(e.start) + '–' + TS.hm(e.end); };
  S.evWhen = function (e) {
    var t = e.from === e.to ? TS.cap(TS.fmtLong(e.from)) : S.dayMonthRange(e.from, e.to);
    return t + (e.allDay ? '' : ', ' + S.evTime(e));
  };
  S.evDays = function (e) { return TS.diffDays(e.from, e.to) + 1; };
  /* text do lišty oznámení vytvořený z akce */
  S.noticeFrom = function (e) {
    if (e.type === 'volno') return e.title + ' ' + (e.from === e.to ? S.onDay(e.from) : S.dayMonthRange(e.from, e.to)) + '. ' + (e.desc ? e.desc.split('. ')[0].replace(/\.$/, '') + '.' : '');
    var who = S.gradesTxt(e.grades);
    return TS.cap(e.from === e.to ? TS.fmtLong(e.from) : S.dayMonthRange(e.from, e.to)) + (e.allDay ? '' : ' od ' + TS.hm(e.start)) + ' ' + S.lc(e.title) +
      (who === 'celá škola' ? (/schůzk|konzultac/i.test(e.title) ? ' pro všechny ročníky' : '') : ' pro ' + who);
  };
  /* soubor .ics: celodenní, časové i vícedenní akce */
  function icsEsc(s) { return String(s || '').replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n'); }
  function icsD(d) { return d.replace(/-/g, ''); }
  function icsT(d, m) { return icsD(d) + 'T' + TS.pad(Math.floor(m / 60)) + TS.pad(m % 60) + '00'; }
  S.icsEvent = function (D, e) {
    var loc = e.place ? e.place + ', ' + D.info.short + ', ' + D.info.street + ', ' + D.info.city : D.info.short + ', ' + D.info.street + ', ' + D.info.city;
    var L = ['BEGIN:VEVENT', 'UID:' + e.id + '@zs-slunecnice.ukazka', 'DTSTAMP:' + new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d+/, ''), 'SUMMARY:' + icsEsc(e.title)];
    if (e.allDay) L.push('DTSTART;VALUE=DATE:' + icsD(e.from), 'DTEND;VALUE=DATE:' + icsD(TS.addDays(e.to, 1)));
    else { L.push('DTSTART;TZID=Europe/Prague:' + icsT(e.from, e.start), 'DTEND;TZID=Europe/Prague:' + icsT(e.from, e.end)); if (e.to > e.from) L.push('RRULE:FREQ=DAILY;COUNT=' + S.evDays(e)); }
    L.push('LOCATION:' + icsEsc(loc));
    if (e.desc) L.push('DESCRIPTION:' + icsEsc(e.desc));
    L.push('END:VEVENT');
    return L.join('\r\n');
  };
  S.ics = function (D, list) {
    return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ZS Slunecnice ukazka//CS', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' + icsEsc(D.info.short + ' – akce')]
      .concat(list.map(function (e) { return S.icsEvent(D, e); })).concat(['END:VCALENDAR']).join('\r\n');
  };
  /* měsíční mřížka (web i administrace): o.filter(e), o.sel (vybraný den), o.dayAttr(d), o.evAttr(e) */
  S.monthHTML = function (D, ym, o) {
    o = o || {};
    var y = +ym.slice(0, 4), m = +ym.slice(5, 7), first = ymd(y, m, 1), last = ymd(y, m + 1, 0);
    var start = TS.weekStart(first), end = TS.addDays(TS.weekStart(last), 6), t = TS.today();
    var evs = S.events(D).filter(function (e) { return e.to >= start && e.from <= end && (!o.filter || o.filter(e)); });
    var h = '<div class="mcal"><div class="mc-head" aria-hidden="true">' + ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'].map(function (x) { return '<span>' + x + '</span>'; }).join('') + '</div><div class="mc-body">';
    for (var d = start; d <= end; d = TS.addDays(d, 1)) {
      var day = evs.filter(function (e) { return d >= e.from && d <= e.to; }), out = d.slice(0, 7) !== ym, vol = day.some(function (e) { return e.type === 'volno'; });
      var cls = 'mc-day' + (out ? ' out' : '') + (isWknd(d) ? ' wknd' : '') + (d === t ? ' today' : '') + (vol ? ' volno' : '') + (o.sel === d ? ' is-sel' : '');
      var lbl = TS.cap(TS.fmtLong(d)) + (d === t ? ', dnes' : '') + (day.length ? ', ' + day.length + ' ' + TS.plural(day.length, 'akce', 'akce', 'akcí') : '');
      h += '<div class="' + cls + '" ' + (o.dayAttr ? o.dayAttr(d) : '') + '><button type="button" class="mc-n" ' + (o.dayAttr ? o.dayAttr(d) : '') + ' aria-label="' + esc(lbl) + '" aria-pressed="' + (o.sel === d) + '">' + TS.parse(d).getDate() + '</button>';
      if (day.length) {
        h += '<div class="mc-evs">' + day.slice(0, 3).map(function (e) {
          var cont = d !== e.from;
          return '<button type="button" class="mc-ev t-' + e.type + (cont ? ' cont' : '') + '" ' + (o.evAttr ? o.evAttr(e) : '') + ' title="' + esc(e.title + (e.allDay ? '' : ', ' + S.evTime(e))) + '">' + (!e.allDay && !cont ? '<b>' + TS.hm(e.start) + '</b> ' : '') + esc(e.title) + '</button>';
        }).join('') + (day.length > 3 ? '<span class="mc-more">+ ' + (day.length - 3) + ' další</span>' : '') + '</div>';
        h += '<span class="mc-dots" aria-hidden="true">' + day.slice(0, 4).map(function (e) { return '<i class="t-' + e.type + '"></i>'; }).join('') + '</span>';
      }
      h += '</div>';
    }
    return h + '</div></div>';
  };
  S.gcal = function (D, e) {
    var dates = e.allDay ? icsD(e.from) + '/' + icsD(TS.addDays(e.to, 1)) : icsT(e.from, e.start) + '/' + icsT(e.from, e.end);
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(e.title) + '&dates=' + dates + '&ctz=Europe%2FPrague' +
      (!e.allDay && e.to > e.from ? '&recur=' + encodeURIComponent('RRULE:FREQ=DAILY;COUNT=' + S.evDays(e)) : '') +
      (e.desc ? '&details=' + encodeURIComponent(e.desc) : '') + '&location=' + encodeURIComponent((e.place ? e.place + ', ' : '') + D.info.street + ', ' + D.info.city);
  };

  /* ---------- jídelníček ---------- */
  var DISH = [
    ['Hovězí vývar s nudlemi', '1, 3, 9', 'Kuřecí plátek na paprice, těstoviny', '1, 3, 7', 'Zeleninové rizoto se sýrem', '7, 9', 'Čaj s citronem, jablko', 'Chléb s pomazánkovým máslem a pažitkou, mléko', '1, 7'],
    ['Dýňový krém se semínky', '7', 'Vepřová pečeně, dušené zelí, bramborový knedlík', '1, 3, 7', 'Čočka na kyselo, vejce, okurka', '1, 3, 10', 'Ovocný čaj, mrkvový salát', 'Jogurt s müsli, jablko', '1, 7, 8'],
    ['Bramborová polévka s houbami', '1, 9', 'Filé z tresky, bramborová kaše', '4, 7', 'Boloňské špagety se sýrem', '1, 7, 9', 'Mléčný koktejl, hruška', 'Rohlík s vaječnou pomazánkou, čaj', '1, 3, 7, 10'],
    ['Rajčatová polévka s bulgurem', '1, 9', 'Švestkové knedlíky s tvarohem a máslem', '1, 3, 7', 'Kuskus se zeleninou a cizrnou', '1, 6', 'Šípkový čaj, kefír', 'Chléb se sýrovou pomazánkou, paprika', '1, 7'],
    ['Kuřecí polévka s kapáním', '1, 3, 9', 'Svíčková na smetaně, houskový knedlík', '1, 3, 7, 9, 10', 'Zapečené těstoviny se špenátem', '1, 3, 7', 'Ovocný čaj, mandarinka', 'Tvarohový koláč, mléko', '1, 3, 7'],
    ['Čočková polévka', '1, 9', 'Pečené kuřecí stehno, rýže, kompot', '', 'Smažený sýr, vařené brambory, tatarská omáčka', '1, 3, 7, 10', 'Čaj, okurkový salát', 'Chléb s lučinou a rajčetem', '1, 7'],
    ['Brokolicový krém', '7, 9', 'Hovězí guláš, těstoviny', '1, 3, 9', 'Rizoto s krůtím masem a zeleninou', '9', 'Bylinkový čaj, banán', 'Ovesná kaše s ovocem', '1, 7'],
    ['Frankfurtská polévka', '1, 7, 9', 'Rybí prsty, bramborová kaše, zeleninová obloha', '1, 3, 4, 7', 'Žemlovka s jablky a tvarohem', '1, 3, 7', 'Citronový čaj, kedlubnový salát', 'Celozrnný rohlík s pomazánkou z červené řepy', '1, 7'],
    ['Česneková polévka s krutony', '1, 7', 'Vepřový řízek, bramborový salát', '1, 3, 7, 10', 'Pečené rybí filé se zeleninou, brambory', '4, 7', 'Ovocná šťáva, pomeranč', 'Pudink s piškoty', '1, 3, 7'],
    ['Gulášová polévka', '1, 9', 'Kuřecí rizoto se sýrem, okurka', '7, 9', 'Palačinky s tvarohem a ovocem', '1, 3, 7', 'Mátový čaj, jablko', 'Chléb s domácí paštikou, mrkev', '1, 3, 9'],
    ['Zeleninová polévka s krupicí', '1, 3, 9', 'Plněná paprika v rajčatové omáčce, houskový knedlík', '1, 3, 7', 'Bulgurový salát s fetou a zeleninou', '1, 7', 'Čaj, jablečný kompot', 'Rohlík s máslem a medem, kakao', '1, 7'],
    ['Fazolová polévka', '1, 9', 'Kuřecí nudličky po čínsku, rýže', '1, 6, 11', 'Bramboráky s kysaným zelím', '1, 3, 7', 'Ovocný čaj, hruška', 'Jogurt s ovocem', '7'],
    ['Kulajda', '1, 3, 7', 'Sekaná pečeně, bramborová kaše, okurka', '1, 3, 7', 'Těstoviny s bazalkovým pestem a sýrem', '1, 7, 8', 'Čaj, mandarinka', 'Chléb s vajíčkovou pomazánkou', '1, 3, 7'],
    ['Hrachová polévka s krutony', '1, 9', 'Kuře na smetaně, těstoviny', '1, 3, 7', 'Kynuté buchty s povidly, kakao', '1, 3, 7', 'Ovocný čaj, ředkvičky', 'Rohlík s tvarohem a pažitkou', '1, 7'],
    ['Polévka z červené čočky', '9', 'Pečená krkovice, dušený špenát, bramborové knedlíky', '1, 3, 7', 'Zeleninové lasagne', '1, 3, 7, 9', 'Čaj s citronem, kiwi', 'Ovocný salát s jogurtem', '7']];
  S.emptyDay = function () { return { off: false, reason: '', soup: { n: '', a: '' }, m1: { n: '', a: '' }, m2: { n: '', a: '' }, side: '', snack: { n: '', a: '' } }; };
  function dishDay(x) { return { off: false, reason: '', soup: { n: x[0], a: x[1] }, m1: { n: x[2], a: x[3] }, m2: { n: x[4], a: x[5] }, side: x[6], snack: { n: x[7], a: x[8] } }; }
  S.blankWeek = function () { var d = []; for (var i = 0; i < 5; i++) d.push(S.emptyDay()); return { days: d, updatedAt: 0 }; };
  S.week = function (D, mon) { return D.menus[mon] || null; };
  S.dayFilled = function (day) { return !!(day && !day.off && day.soup.n.trim() && day.m1.n.trim()); };
  /* stav jednoho dne pro web: menu / bez vaření / zatím nevyplněno / víkend */
  S.dayInfo = function (D, d) {
    var wd = TS.wd(d); if (wd === 0 || wd === 6) return { state: 'weekend' };
    var v = S.volnoOn(D, d);
    if (v) return { state: 'off', title: v.title, text: v.desc || 'Jídelna tento den nevaří.', ev: v };
    var wk = D.menus[TS.weekStart(d)], day = wk && wk.days[wd - 1];
    if (day && day.off) return { state: 'off', title: day.reason || 'Jídelna nevaří', text: 'Obědy jsou na tento den automaticky odhlášené.', day: day };
    if (S.dayFilled(day)) return { state: 'menu', day: day };
    return { state: 'empty' };
  };
  /* kolik školních dnů v týdnu má vyplněný jídelníček */
  S.weekStatus = function (D, mon) {
    var need = 0, filled = 0, off = 0;
    for (var i = 0; i < 5; i++) { var s = S.dayInfo(D, TS.addDays(mon, i)); if (s.state === 'off') off++; else { need++; if (s.state === 'menu') filled++; } }
    return { need: need, filled: filled, off: off, ok: filled === need };
  };
  S.allergens = function (s) { return String(s || '').split(/[\s,;]+/).filter(Boolean); };
  S.cleanAllergens = function (s) {
    var a = S.allergens(s), bad = a.filter(function (x) { return !/^\d+$/.test(x) || +x < 1 || +x > 14; });
    if (bad.length) return null;
    return a.map(Number).filter(function (x, i, arr) { return arr.indexOf(x) === i; }).sort(function (x, y) { return x - y; }).join(', ');
  };
  /* den, na který web ukazuje oběd: dnes do konce výdeje, jinak nejbližší školní den */
  S.lunchDay = function (D) {
    var t = TS.today();
    if (S.isSchoolDay(D, t) && TS.nowMin() < D.canteen.serveTo) return t;
    return S.nextSchoolDay(D, t, false);
  };
  S.lunchWord = function (d) {
    var dd = TS.diffDays(TS.today(), d);
    return dd === 0 ? 'Dnes' : dd === 1 ? 'Zítra' : TS.cap(S.WDA[TS.wd(d)]);
  };

  /* ---------- kroužky ---------- */
  S.clubWhen = function (c) { return TS.WDN[c.day] + ' ' + TS.hm(c.from) + '–' + TS.hm(c.to); };
  S.EVERY = ['každou neděli', 'každé pondělí', 'každé úterý', 'každou středu', 'každý čtvrtek', 'každý pátek', 'každou sobotu'];
  S.clubEvery = function (c) { return S.EVERY[c.day] + ' ' + TS.hm(c.from) + '–' + TS.hm(c.to); };
  S.isGirl = function (n) { return /[ae]$/i.test(S.firstName(n)); };
  S.clubFor = function (c) { return c.gFrom === c.gTo ? c.gFrom + '. třída' : c.gFrom + '.–' + c.gTo + '. třída'; };
  S.clubPrice = function (c, long) { return c.price ? TS.money(c.price) + (long ? ' za pololetí' : ' / pololetí') : 'zdarma'; };
  S.clubTaken = function (D, id) { return D.apps.filter(function (a) { return a.club === id && a.status === 'ok'; }).length; };
  S.clubWait = function (D, id) { return D.apps.filter(function (a) { return a.club === id && a.status === 'wait'; }).sort(function (a, b) { return a.at - b.at; }); };
  S.clubFree = function (D, c) { return Math.max(0, c.cap - S.clubTaken(D, c.id)); };
  S.clubById = function (D, id) { return D.clubs.find(function (c) { return c.id === id; }) || null; };
  S.clubsSorted = function (D) { return D.clubs.slice().sort(function (a, b) { return a.day - b.day || a.from - b.from; }); };
  S.clubFirst = function (D, c, after) {
    var d = after || TS.today(); if (D.clubSet.start > d) d = D.clubSet.start;
    for (var i = 0; i < 60; i++) { if (TS.wd(d) === c.day && S.isSchoolDay(D, d)) return d; d = TS.addDays(d, 1); }
    return d;
  };
  S.clubEvent = function (D, c, after) {
    var first = S.clubFirst(D, c, after), until = icsD(D.clubSet.end || TS.addDays(first, 120));
    return { first: first, gcal: 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent('Kroužek ' + c.n) + '&dates=' + icsT(first, c.from) + '/' + icsT(first, c.to) +
      '&ctz=Europe%2FPrague&recur=' + encodeURIComponent('RRULE:FREQ=WEEKLY;UNTIL=' + until) + '&location=' + encodeURIComponent(c.place + ', ' + D.info.street + ', ' + D.info.city) };
  };

  /* ---------- lidé ---------- */
  S.initials = function (name) {
    var p = String(name || '').replace(/\b(Mgr|Bc|Ing|PhDr|RNDr|PaedDr|MUDr|Ph\.D|DiS)\.?/g, '').replace(/[.,]/g, ' ').trim().split(/\s+/).filter(Boolean);
    if (!p.length) return '?';
    return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
  };
  S.people = function (D, group, all) { return D.people.filter(function (p) { return (!group || p.group === group) && (all || !p.hidden); }); };
  S.personById = function (D, id) { return D.people.find(function (p) { return p.id === id; }) || null; };
  S.telHref = function (s) { return 'tel:' + String(s || '').replace(/[^\d+]/g, ''); };

  /* ---------- výchozí data ---------- */
  function rng(seed) { return function () { seed = (seed * 1664525 + 1013904223) % 4294967296; return seed / 4294967296; }; }
  function pick(r, arr) { return arr[Math.floor(r() * arr.length)]; }

  function peopleDefaults() {
    var i = 0, P = [
      ['vedeni', 'Mgr. Hana Doležalová', 'ředitelka školy', '', 'český jazyk', 'reditelka', '+420 241 000 331', 'Po domluvě e-mailem nebo přes kancelář', 'Ředitelna, 1. patro'],
      ['vedeni', 'Mgr. Pavel Vojtěch', 'zástupce ředitelky, rozvrhy a suplování', '', 'matematika, fyzika', 'zastupce', '+420 241 000 332', 'Út 13:00–14:30', 'Kancelář zástupců, 1. patro'],
      ['vedeni', 'Mgr. Lenka Šímová', 'zástupkyně ředitelky pro 1. stupeň a zápis', '', 'prvouka', 'zastupkyne', '+420 241 000 335', 'St 13:00–14:30', 'Kancelář zástupců, 1. patro'],
      ['tridni1', 'Mgr. Kateřina Bílková', 'třídní učitelka', '1.A', '', 'bilkova', '', 'Po 13:00–14:00', 'Učebna 1.A, přízemí'],
      ['tridni1', 'Mgr. Martina Hájková', 'třídní učitelka', '1.B', '', 'hajkova', '', 'Út 12:45–13:45', 'Učebna 1.B, přízemí'],
      ['tridni1', 'Mgr. Petra Konečná', 'třídní učitelka', '1.C', '', 'konecna', '', 'Čt 13:00–14:00', 'Učebna 1.C, přízemí'],
      ['tridni1', 'Mgr. Jana Pospíšilová', 'třídní učitelka', '2.A', '', 'pospisilova', '', 'St 12:45–13:45', 'Učebna 2.A, přízemí'],
      ['tridni1', 'Mgr. Eva Machová', 'třídní učitelka', '2.B', 'anglický jazyk', 'machova', '', 'Po 12:45–13:45', 'Učebna 2.B, přízemí'],
      ['tridni1', 'Mgr. Lucie Říhová', 'třídní učitelka', '2.C', '', 'rihova', '', 'Čt 12:45–13:45', 'Učebna 2.C, přízemí'],
      ['tridni1', 'Mgr. Zuzana Kadlecová', 'třídní učitelka', '3.A', '', 'kadlecova', '', 'Út 13:00–14:00', 'Učebna 3.A, 1. patro'],
      ['tridni1', 'Mgr. Ivana Vávrová', 'třídní učitelka', '3.B', 'hudební výchova', 'vavrova', '', 'St 13:30–14:30', 'Učebna 3.B, 1. patro'],
      ['tridni1', 'Mgr. Monika Holubová', 'třídní učitelka', '4.A', '', 'holubova', '', 'Po 14:00–15:00', 'Učebna 4.A, 1. patro'],
      ['tridni1', 'Mgr. Radka Šťastná', 'třídní učitelka', '4.B', 'anglický jazyk', 'stastna', '', 'Čt 14:00–15:00', 'Učebna 4.B, 1. patro'],
      ['tridni1', 'Mgr. Jiří Tichý', 'třídní učitel', '5.A', 'tělesná výchova', 'tichy', '', 'Út 14:00–15:00', 'Učebna 5.A, 1. patro'],
      ['tridni1', 'Mgr. Barbora Křížová', 'třídní učitelka', '5.B', '', 'krizova', '', 'St 14:00–15:00', 'Učebna 5.B, 1. patro'],
      ['tridni2', 'Mgr. Tomáš Kratochvíl', 'třídní učitel', '6.A', 'informatika, matematika', 'kratochvil', '', 'Po 13:30–14:30', 'Učebna robotiky, 2. patro'],
      ['tridni2', 'Mgr. Veronika Šebestová', 'třídní učitelka', '6.B', 'český jazyk, dějepis', 'sebestova', '', 'Út 14:00–15:00', 'Kabinet češtiny, 2. patro'],
      ['tridni2', 'Mgr. Daniel Hrubý', 'třídní učitel', '6.C', 'tělesná výchova, zeměpis', 'hruby', '', 'St 14:30–15:30', 'Kabinet tělocviku'],
      ['tridni2', 'Mgr. Klára Musilová', 'třídní učitelka', '7.A', 'anglický a německý jazyk', 'musilova', '', 'Čt 13:30–14:30', 'Jazyková učebna, 2. patro'],
      ['tridni2', 'Mgr. Roman Pešek', 'třídní učitel', '7.B', 'fyzika, matematika', 'pesek', '', 'Po 14:00–15:00', 'Kabinet fyziky, 2. patro'],
      ['tridni2', 'Mgr. Alena Krejčí', 'třídní učitelka', '8.A', 'přírodopis, chemie', 'krejci', '', 'Út 13:30–14:30', 'Přírodovědná učebna, 2. patro'],
      ['tridni2', 'Mgr. Ondřej Zelenka', 'třídní učitel', '8.B', 'dějepis, občanská výchova', 'zelenka', '', 'St 13:30–14:30', 'Kabinet dějepisu, 2. patro'],
      ['tridni2', 'Mgr. Simona Vlasáková', 'třídní učitelka', '9.A', 'český jazyk, hudební výchova', 'vlasakova', '', 'Čt 14:00–15:00', 'Hudebna, 2. patro'],
      ['tridni2', 'Mgr. Michal Beránek', 'třídní učitel', '9.B', 'matematika, informatika', 'beranek', '', 'Pá 12:30–13:30', 'Počítačová učebna, 2. patro'],
      ['ucitele', 'Mgr. Petr Sýkora', 'učitel', '', 'tělesná výchova, zeměpis', 'sykora', '', 'Út 14:00–15:00', 'Kabinet tělocviku'],
      ['ucitele', 'Mgr. Dana Lukešová', 'učitelka', '', 'výtvarná výchova, pracovní činnosti', 'lukesova', '', 'Čt 13:00–14:00', 'Výtvarná dílna, přízemí'],
      ['ucitele', 'Mgr. Karolína Fischerová', 'učitelka', '', 'anglický jazyk', 'fischerova', '', 'Po 13:30–14:30', 'Jazyková učebna, 2. patro'],
      ['ucitele', 'Mgr. Jakub Bednář', 'učitel a správce sítě', '', 'informatika', 'bednar', '', 'St 13:00–14:00', 'Počítačová učebna, 2. patro'],
      ['ucitele', 'Emily Carter', 'lektorka angličtiny, rodilá mluvčí', '', 'konverzace v angličtině', 'carter', '', 'Po domluvě e-mailem', 'Jazyková učebna, 2. patro'],
      ['poradna', 'Mgr. Irena Dvořáková', 'výchovná poradkyně, volba střední školy', '', 'český jazyk', 'dvorakova', '+420 241 000 336', 'Út 13:00–15:00', 'Kabinet 104, 1. patro'],
      ['poradna', 'Mgr. Filip Matoušek', 'školní metodik prevence', '', 'občanská výchova', 'matousek', '+420 241 000 337', 'St 13:00–14:00', 'Kabinet 104, 1. patro'],
      ['poradna', 'Mgr. Gabriela Hronová', 'speciální pedagožka', '', '', 'hronova', '', 'Po 12:00–14:00', 'Kabinet 105, 1. patro'],
      ['poradna', 'PhDr. Renata Kopecká', 'školní psycholožka', '', '', 'kopecka', '+420 241 000 338', 'Čt 9:00–14:00, po objednání', 'Kabinet 105, 1. patro'],
      ['druzina', 'Bc. Marcela Pavlíková', 'vedoucí vychovatelka, 1. oddělení', '', '', 'druzina', '+420 241 000 340', 'Denně 13:00–14:00 v družině', 'Družina, přízemí'],
      ['druzina', 'Hana Štěpánková', 'vychovatelka, 2. oddělení', '', '', 'stepankova', '+420 241 000 341', '', 'Družina, přízemí'],
      ['druzina', 'Lenka Benešová', 'vychovatelka, 3. oddělení', '', '', 'benesova', '+420 241 000 341', '', 'Družina, přízemí'],
      ['druzina', 'Tereza Vondráčková', 'vychovatelka, 4. oddělení', '', '', 'vondrackova', '+420 241 000 342', '', 'Pavilon u zahrady'],
      ['druzina', 'Mgr. Adéla Kubíčková', 'vychovatelka, 5. oddělení', '', '', 'kubickova', '+420 241 000 342', '', 'Pavilon u zahrady'],
      ['jidelna', 'Marie Šindelářová', 'vedoucí školní jídelny', '', '', 'jidelna', '+420 241 000 334', 'Odhlášky a platby Po–Pá 7:00–10:00', 'Kancelář jídelny, přízemí'],
      ['jidelna', 'Pavla Horáčková', 'hlavní kuchařka', '', '', 'horackova', '', '', 'Školní kuchyně'],
      ['provoz', 'Jitka Malá', 'kancelář školy a stravné', '', '', 'kancelar', '+420 241 000 333', 'Ve školní dny 7:30–15:30', 'Kancelář, přízemí u hlavního vchodu'],
      ['provoz', 'Ing. Věra Holečková', 'ekonomka školy', '', '', 'ekonom', '+420 241 000 330', 'Út a čt 8:00–14:00', 'Kancelář, přízemí'],
      ['provoz', 'Václav Dostál', 'školník a správce budovy', '', '', 'dostal', '+420 241 000 339', '', 'Domovnická služba u hlavního vchodu']];
    return P.map(function (x) {
      return { id: 'p' + (++i), group: x[0], name: x[1], role: x[2], cls: x[3], subj: x[4], email: x[5] ? x[5] + '@zs-slunecnice.cz' : '', phone: x[6], consult: x[7], room: x[8], hidden: false };
    });
  }

  function clubsDefaults() {
    var i = 0, C = [
      ['Robotika LEGO', 'robot', 'var(--sky)', 'Stavíme a programujeme roboty. Na konci pololetí soutěž v bludišti.', 1, 840, 930, 3, 6, 1200, 'Mgr. Tomáš Kratochvíl', 'Učebna robotiky, 2. patro', 15],
      ['Keramika', 'clay', 'var(--coral)', 'Točení na kruhu, glazování a vlastní hrnek na Vánoce.', 2, 810, 900, 1, 5, 1400, 'Mgr. Dana Lukešová', 'Keramická dílna, přízemí', 12],
      ['Pěvecký sbor', 'note', 'var(--sun)', 'Sbor Slunečnice vystupuje na jarmarku a v Braníku.', 2, 840, 930, 2, 9, 0, 'Mgr. Simona Vlasáková', 'Hudebna, 2. patro', 40],
      ['Florbal', 'ball', 'var(--leaf)', 'Tréninky ve velké tělocvičně, zápasy v pražské lize škol.', 3, 900, 990, 4, 9, 900, 'Mgr. Petr Sýkora', 'Velká tělocvična', 24],
      ['Angličtina hrou', 'abc', 'var(--navy)', 'Písničky, hry a první slovíčka s rodilou mluvčí.', 4, 780, 840, 1, 2, 1100, 'Emily Carter', 'Jazyková učebna, 2. patro', 12],
      ['Šachy', 'chess', 'var(--vio)', 'Od pravidel po turnaje. Přijďte si zahrát i s rodiči.', 5, 780, 870, 1, 9, 800, 'Mgr. Roman Pešek', 'Školní knihovna, 2. patro', 16],
      ['Mladý včelař', 'bee', 'var(--sun)', 'Péče o dva školní úly, stáčení medu a včelí stezka pro mladší spolužáky.', 3, 840, 900, 4, 9, 600, 'Mgr. Alena Krejčí', 'Školní zahrada a včelín', 10],
      ['Malí kuchaři', 'chef', 'var(--coral)', 'Vaříme a pečeme podle sezóny, suroviny jsou v ceně kroužku.', 4, 840, 930, 3, 5, 1300, 'Pavla Horáčková', 'Cvičná kuchyňka, přízemí', 10],
      ['Divadelní kroužek', 'mask', 'var(--vio)', 'Improvizace, práce s hlasem a jedno velké představení na konci roku.', 1, 870, 960, 5, 9, 700, 'Mgr. Ondřej Zelenka', 'Aula', 18],
      ['Programování ve Scratchi', 'code', 'var(--sky)', 'Vlastní hry a animace krok za krokem, bez nutnosti psát kód.', 2, 840, 900, 4, 7, 1000, 'Mgr. Jakub Bednář', 'Počítačová učebna, 2. patro', 14],
      ['Atletika', 'run', 'var(--leaf)', 'Běh, skok a hod hravou formou, venku i v tělocvičně.', 4, 900, 990, 1, 4, 900, 'Mgr. Daniel Hrubý', 'Školní hřiště a malá tělocvična', 20],
      ['Výtvarný ateliér', 'brush', 'var(--coral)', 'Malba, grafika a koláže. Výstava prací na jarmarku.', 5, 810, 900, 1, 5, 1200, 'Hana Štěpánková', 'Výtvarná dílna, přízemí', 14]];
    return C.map(function (x) {
      return { id: 'k' + (++i), n: x[0], ic: x[1], color: x[2], p: x[3], day: x[4], from: x[5], to: x[6], gFrom: x[7], gTo: x[8], price: x[9], leader: x[10], place: x[11], cap: x[12], open: true, hidden: false };
    });
  }

  var BOYS = ['Tomáš', 'Jakub', 'Adam', 'Matěj', 'Vojtěch', 'Ondřej', 'Filip', 'Lukáš', 'David', 'Šimon', 'Daniel', 'Martin', 'Kryštof', 'Václav', 'Štěpán', 'Michal', 'Petr', 'Jan', 'Antonín', 'Matyáš', 'Vít', 'Eliáš', 'Sebastian', 'Dominik', 'Josef'];
  var GIRLS = ['Eliška', 'Tereza', 'Anna', 'Adéla', 'Natálie', 'Karolína', 'Kristýna', 'Viktorie', 'Barbora', 'Julie', 'Klára', 'Ema', 'Sofie', 'Amálie', 'Laura', 'Rozálie', 'Veronika', 'Nela', 'Lucie', 'Marie', 'Zuzana', 'Ella', 'Magdaléna', 'Kateřina', 'Johana'];
  var SUR = [['Novák', 'Nováková'], ['Svoboda', 'Svobodová'], ['Novotný', 'Novotná'], ['Dvořák', 'Dvořáková'], ['Černý', 'Černá'], ['Procházka', 'Procházková'], ['Kučera', 'Kučerová'],
    ['Veselý', 'Veselá'], ['Horák', 'Horáková'], ['Němec', 'Němcová'], ['Marek', 'Marková'], ['Pokorný', 'Pokorná'], ['Pospíchal', 'Pospíchalová'], ['Král', 'Králová'], ['Jelínek', 'Jelínková'],
    ['Růžička', 'Růžičková'], ['Fiala', 'Fialová'], ['Sedláček', 'Sedláčková'], ['Zeman', 'Zemanová'], ['Kolář', 'Kolářová'], ['Navrátil', 'Navrátilová'], ['Čermák', 'Čermáková'],
    ['Urban', 'Urbanová'], ['Vaněk', 'Vaňková'], ['Blažek', 'Blažková'], ['Kovář', 'Kovářová'], ['Bartoš', 'Bartošová'], ['Vlček', 'Vlčková'], ['Polák', 'Poláková'], ['Šimek', 'Šimková'],
    ['Matějka', 'Matějková'], ['Toman', 'Tomanová'], ['Bureš', 'Burešová'], ['Valenta', 'Valentová'], ['Havlíček', 'Havlíčková']];
  var MUMS = ['Jana', 'Petra', 'Lucie', 'Martina', 'Kateřina', 'Veronika', 'Markéta', 'Michaela', 'Lenka', 'Zuzana', 'Barbora', 'Hana', 'Eva', 'Monika'];
  var DADS = ['Petr', 'Martin', 'Tomáš', 'Jan', 'Pavel', 'Jiří', 'Michal', 'Lukáš', 'David', 'Ondřej', 'Radek', 'Karel'];
  var PRE = ['602', '603', '604', '605', '606', '607', '608', '702', '720', '721', '723', '724', '725', '728', '731', '732', '733', '734', '736', '737', '739', '774', '775', '776', '777'];
  var NOTES = ['', '', '', '', '', 'Chodí do družiny, 2. oddělení.', 'Alergie na ořechy.', 'Po kroužku chodí domů sám.', 'Sourozenec chodí do stejného kroužku.', 'Chodí do družiny.', 'Vyzvedává babička.'];
  var BYG = {}; S.CLASSES.forEach(function (c) { var g = S.gradeOf(c); (BYG[g] = BYG[g] || []).push(c); });
  function appsDefaults(clubs, Y) {
    var r = rng(7 + Y), out = [], seq = 0, now = Date.now();
    /* počet přijatých a náhradníků podle kroužku */
    var plan = { k1: [15, 2], k2: [11, 0], k3: [18, 0], k4: [14, 0], k5: [12, 1], k6: [7, 0], k7: [6, 0], k8: [10, 2], k9: [9, 0], k10: [12, 0], k11: [13, 0], k12: [10, 0] };
    clubs.forEach(function (c, ci) {
      var p = plan[c.id] || [5, 0], total = p[0] + p[1], used = {};
      for (var j = 0; j < total; j++) {
        var girl = r() < 0.5, first = pick(r, girl ? GIRLS : BOYS), sur = pick(r, SUR), child = first + ' ' + sur[girl ? 1 : 0];
        if (used[child]) { j--; continue; } used[child] = 1;
        var g = c.gFrom + Math.floor(r() * (c.gTo - c.gFrom + 1)), cls = pick(r, BYG[g]);
        var mum = r() < 0.72, pf = pick(r, mum ? MUMS : DADS), parent = pf + ' ' + sur[mum ? 1 : 0];
        var email = TS.slug(pf) + '.' + TS.slug(sur[mum ? 1 : 0]) + '@example.com';
        var phone = '+420 ' + pick(r, PRE) + ' ' + String(100 + Math.floor(r() * 900)) + ' ' + String(100 + Math.floor(r() * 900));
        /* přijaté přihlášky starší, náhradníci a pár nejnovějších z posledních dní */
        var wait = j >= p[0], days = wait ? 1 + r() * 4 : 5 + r() * 26;
        var at = now - Math.round(days * 864e5 + r() * 36e5 * 8);
        out.push({ id: 'a' + (++seq), club: c.id, child: child, cls: cls, parent: parent, email: email, phone: phone, note: pick(r, NOTES), druzina: g <= 4 && r() < 0.6,
          status: wait ? 'wait' : 'ok', at: at, seen: true, vs: String(Y % 100) + TS.pad(ci + 1) + String(1000 + seq).slice(1), log: [{ at: at, t: wait ? 'Přihláška z webu, kroužek plný – náhradník' : 'Přihláška z webu, přijato' }] });
      }
    });
    /* tři nejnovější přihlášky (posledních 30 hodin) jsou nové, ještě je nikdo neviděl */
    var fresh = [['k6', 0.2], ['k3', 0.9], ['k10', 1.25]];
    fresh.forEach(function (f, i) {
      var c = clubs.find(function (x) { return x.id === f[0]; }), a = out.filter(function (x) { return x.club === f[0] && x.status === 'ok'; }).sort(function (x, y) { return y.at - x.at; })[0];
      if (!c || !a) return;
      a.at = now - Math.round(f[1] * 864e5); a.seen = false; a.log = [{ at: a.at, t: 'Přihláška z webu, přijato' }];
    });
    /* dvě zrušené přihlášky */
    ['k2', 'k9'].forEach(function (cid) {
      var a = out.filter(function (x) { return x.club === cid && x.status === 'ok' && x.seen; })[2];
      if (a) { a.status = 'cancel'; a.wasOk = true; a.log.push({ at: a.at + 3 * 864e5, t: 'Zrušeno na žádost rodiče' }); }
    });
    return out;
  }

  function postsDefaults(t, D) {
    var i = 0, foto = D.events.find(function (e) { return e.title === 'Fotografování tříd'; });
    var P = [
      [-1, 'rodice', '', '', 'krouzky', 'Do kroužků se můžete hlásit i během pololetí', 'Kroužky už běží, do mnoha z nich se ale dá přidat i teď. Přihlášku vyplníte online za dvě minuty.',
        'Kroužky letos nabízíme ve dvanácti oborech – od robotiky přes keramiku po školní včelaře. Většina už běží, do mnoha se ale dá přidat i během pololetí.\n\nPřihlášku vyplníte na stránce Kroužky. Když je v kroužku volné místo, potvrzení s platebními údaji vám přijde hned e-mailem. Do plných kroužků zapisujeme náhradníky v pořadí, v jakém přihlášky přišly.\n\nDěti z družiny si vedoucí kroužku vyzvedne a po skončení je do družiny zase přivede. Nemusíte proto nic zařizovat.', false, 'pub'],
      [-2, 'skola', '', '', 'vcely', 'Ochutnejte med ze školního včelína', 'Mladí včelaři letos vytočili 38 kg medu. Sklenice si můžete koupit v kanceláři školy, výtěžek jde na nové úly.',
        'Na školní zahradě máme dva úly a o včely se celý rok starají děti z kroužku Mladý včelař s paní učitelkou Krejčí. Letos z nich vytočily 38 kilogramů lučního medu.\n\nMed plníme do sklenic po 450 g za 160 Kč. Koupit si ho můžete v kanceláři školy ve školní dny 7:30–15:30. Peníze půjdou na třetí úl a nové včelařské kukly pro děti.\n\nNa jaře děti chystají včelí stezku pro mladší spolužáky. Kdo by chtěl včelaření zkusit, může se přihlásit do kroužku.', false, 'pub'],
      [-5, 'rodice', '', '', 'zakovska', 'Absenci omlouvejte jen přes elektronickou žákovskou', 'Omluvenky na papíře už nepřijímáme. V žákovské knížce omluvíte nepřítomnost dvěma kliknutími a třídní učitel ji uvidí hned.',
        'Od tohoto pololetí omlouváme absenci jen přes elektronickou žákovskou knížku. Nepřítomnost omluvte nejpozději do tří dnů od návratu dítěte do školy.\n\nKdyž o absenci víte předem (lékař, rodinná akce), zadejte ji do žákovské rovnou. Uvolnění na víc než dva dny schvaluje ředitelka školy na základě žádosti, kterou dostanete v kanceláři.\n\nPřihlašovací údaje do žákovské dostali všichni rodiče na začátku školního roku. Pokud je nemáte nebo jste je zapomněli, napište třídnímu učiteli nebo do kanceláře školy.', true, 'pub'],
      [-8, 'souteze', '1', '4.B', 'papir', 'Sběr papíru vyhrála 4.B s 1 240 kg', 'Celá škola nasbírala přes 9 tun papíru. Výtěžek 18 600 Kč půjde na nové lavičky do zahrady.',
        'Tři rána u kontejneru, desítky naložených vozíků a jasný vítěz: třída 4.B nasbírala 1 240 kg papíru. Na druhém místě skončila 6.A s 980 kg, třetí byla 2.C s 870 kg.\n\nDohromady jsme odvezli 9,3 tuny papíru. Za výtěžek 18 600 Kč koupíme dvě nové lavičky k venkovní učebně na zahradě.\n\nVítězná třída dostane dort od školní jídelny a jeden den bez domácích úkolů. Děkujeme všem rodičům, kteří papír pomáhali vozit!', false, 'pub'],
      [-11, 'vylety', '2', '6. třídy', 'hory', 'Šesťáci strávili tři dny na Šumavě', 'Tři dny v Kvildě, stezka k pramenům Vltavy a první společný táborák nových šestých tříd.',
        'Šesté třídy se poprvé sešly v novém složení a vyrazily na tři dny na Šumavu. Bydleli jsme v penzionu v Kvildě a každý den jsme byli venku.\n\nPrvní den jsme došli k pramenům Vltavy, druhý den děti čekala hra po stopách pašeráků a večer táborák. Se školní psycholožkou hrály hry na spolupráci, které jim pomůžou v novém kolektivu.\n\nFotky z celého pobytu najdete v žákovské knížce v galerii tříd 6.A, 6.B a 6.C.', false, 'pub'],
      [-15, 'skola', '', '', 'robot', 'Otevřeli jsme učebnu robotiky', 'Díky grantu městské části máme 15 robotických stavebnic a 3D tiskárnu. Učebnu využívá informatika i kroužek robotiky.',
        'Bývalá sborovna ve druhém patře se proměnila v učebnu robotiky. Díky grantu městské části Praha 4 v ní je 15 robotických stavebnic, 3D tiskárna a nové notebooky.\n\nUčebnu využívají hodiny informatiky na 2. stupni a odpoledne kroužek Robotika LEGO pod vedením pana učitele Kratochvíla. Starší žáci v ní připravují roboty na pražskou soutěž v bludišti.\n\nNa dni otevřených dveří si učebnu můžete prohlédnout i s dětmi.', false, 'pub'],
      [-19, 'souteze', '2', '', 'florbal', 'Florbalisté vybojovali 2. místo v lize škol', 'Tým starších žáků prohrál až ve finále o jediný gól. Gratulujeme!',
        'Florbalový tým naší školy ve složení žáků 7.–9. tříd odehrál v pražské lize škol šest zápasů. Po pěti výhrách prohrál až ve finále o jediný gól.\n\nNejvíc gólů za náš tým dal Matěj z 8.B, v brance kraloval Adam z 9.A. Pan učitel Sýkora chválí hlavně týmového ducha.\n\nTréninky florbalového kroužku jsou každou středu od 15:00 ve velké tělocvičně. Nové hráče rádi přivítáme.', false, 'pub'],
      [-23, 'jidelna', '', '', 'zelenina', 'Zeleninu do jídelny vozí rodinná farma', 'Brambory, mrkev a dýně nám teď vozí farma ze Středočeského kraje. Poznáte to i na chuti polévek.',
        'Zeleninu nakupujeme přímo od rodinné farmy ze Středočeského kraje. Dvakrát týdně nám přiveze brambory, mrkev, cibuli, dýně a v sezóně i saláty.\n\nKuchařky z ní vaří polévky, přílohy i salátový bar, který je v jídelně každý den. Kratší cesta znamená čerstvější suroviny a méně obalů.\n\nJídelníček na každý týden najdete na webu v části Jídelníček, včetně alergenů a cen obědů.', false, 'pub'],
      [-27, 'druzina', '1', '', 'hriste', 'Družina má na zahradě nové prolézačky', 'Lanová pyramida a balanční chodník už slouží dětem z družiny i o přestávkách.',
        'Na zahradě za družinou vyrostla lanová pyramida, balanční chodník a dvě hnízdové houpačky. Herní prvky zaplatil zřizovatel školy.\n\nDružina je otevřená ve školní dny 6:30–7:40 a po vyučování do 17:30. Za příznivého počasí jsou děti odpoledne venku, oblečení na ven jim proto nechávejte ve skříňce.\n\nPo 15:00 vyzvedávejte děti přes zvonek u vchodu ze zahrady.', false, 'pub'],
      [-30, 'skola', '', '', 'knihy', 'Do knihovny přibylo 300 nových knih', 'Školní knihovna má nové komiksy, encyklopedie i povinnou četbu. Půjčovat si mohou všichni žáci.',
        'Školní knihovna ve 2. patře se rozrostla o 300 nových titulů. Vybírali je sami žáci v anketě: nejvíc hlasů dostaly komiksy, knihy o přírodě a fantasy.\n\nKnihovna je otevřená v úterý a ve čtvrtek o velké přestávce a po vyučování do 15:00. Půjčovat si mohou všichni žáci na tři týdny.\n\nDěkujeme rodičům, kteří knihovně darovali knihy z domácích polic.', false, 'pub'],
      [0, 'skola', '', '', 'foto', 'Fotografování tříd: jak objednat fotky', 'Fotky tříd a portréty si objednáte v žákovské knížce. Platí se až při převzetí.',
        'Fotograf přijde do školy ' + (foto && foto.from >= t ? S.onDay(foto.from) : 'během dopoledne') + ' a vyfotí každou třídu i portréty dětí, které mají souhlas rodičů.\n\nObjednávku vyplníte v elektronické žákovské knížce do týdne po focení. Společná fotka třídy stojí 60 Kč, sada portrétů 190 Kč.\n\nFotky rozdají třídní učitelé zhruba za tři týdny, platí se při převzetí.', false, 'draft'],
      [-62, 'druzina', '1', '', 'slunce', 'Družina o prázdninách nebude v provozu', 'O letních prázdninách je družina zavřená. Příměstský tábor v naší tělocvičně pořádá místní sportovní klub.',
        'Během letních prázdnin je školní družina zavřená. Provoz začíná první školní den v 6:30.\n\nV tělocvičně a na hřišti pořádá v červenci příměstský tábor místní sportovní klub. Přihlášky vyřizuje přímo pořadatel, škola je nepřijímá.', false, 'hidden']];
    return P.map(function (x) {
      return { id: 'n' + (++i), date: TS.addDays(t, x[0]), cat: x[1], grade: x[2], cls: x[3], art: x[4], title: x[5], perex: x[6], body: x[7], pinned: x[8], status: x[9], updatedAt: Date.now() + x[0] * 864e5 };
    });
  }

  S.defaults = function () {
    var t = TS.today(), Y = S.schoolYear(t);
    var D = {
      info: {
        short: 'ZŠ Slunečnice', place: 'Praha 4 – Braník', legal: 'Základní škola a mateřská škola Slunečnice, příspěvková organizace městské části Praha 4.',
        street: 'Na Slunečnici 12', zip: '147 00', city: 'Praha 4', phone: '+420 241 000 333', email: 'skola@zs-slunecnice.cz',
        office: 'Ve školní dny 7:30–15:30', officeHoliday: 'O prázdninách v úterý 9:00–12:00', druzina: '6:30–17:30', druzinaPhone: '+420 241 000 340',
        ico: '61 385 999', ds: 'ab1cd2e', account: '1234567890/0800', founder: 'Městská část Praha 4',
        transport: '3 minuty pěšky od tramvajové zastávky Přístaviště (linky 3 a 17).', entry: 'Vstup pro rodiče hlavní branou, družina vchodem ze zahrady.',
        excuse: 'Nepřítomnost omlouvejte do tří dnů v elektronické žákovské knížce. Přihlašovací údaje dostanete od třídního učitele.',
        pupils: 420, teachers: 28, classes: 21, hives: 2, since: 1962
      },
      events: calendar(Y),
      menus: {},
      canteen: {
        serveFrom: 690, serveTo: 840,
        m2: 'Oběd č. 1 je v ceně stravného. Oběd č. 2 si můžete přehlásit do 14:00 předchozího dne.',
        rules: 'Obědy odhlaste nejpozději do 14:00 předchozího dne v objednávkovém systému jídelny nebo telefonicky v kanceláři jídelny.\nPrvní den nemoci si oběd můžete vyzvednout do jídlonosiče 11:00–11:30 u zadního vchodu jídelny.\nOd druhého dne nemoci je potřeba oběd odhlásit. Neodhlášený oběd musíme účtovat v plné ceně.\nStravné platíte zálohově převodem do 20. dne předchozího měsíce, přeplatky vracíme v červenci.',
        note: 'Jídelna vaří bez polotovarů a každý den nabízí salátový bar. Dietní stravu připravíme po domluvě s vedoucí jídelny.',
        prices: [{ id: 'c1', label: 'Žáci 7–10 let', price: 38 }, { id: 'c2', label: 'Žáci 11–14 let', price: 42 }, { id: 'c3', label: 'Žáci 15 let a starší', price: 45 },
          { id: 'c4', label: 'Odpolední svačina v družině', price: 14 }, { id: 'c5', label: 'Zaměstnanci školy', price: 35 }, { id: 'c6', label: 'Cizí strávníci', price: 98 }]
      },
      people: peopleDefaults(),
      clubs: clubsDefaults(),
      clubSet: {},
      apps: [],
      zapis: {
        seats: 54, classes: 3, contact: 'p3',
        bring: 'rodný list dítěte\nobčanský průkaz zákonného zástupce\ndoporučení poradny a lékaře, pokud žádáte o odklad',
        how: 'Zápis trvá zhruba 30 minut a probíhá hravou formou. Dítě si s paní učitelkou povídá, kreslí, poznává barvy a tvary a zarecituje básničku. Vy mezitím s kanceláří vyřídíte žádost o přijetí.',
        area: 'Přednostně přijímáme děti ze spádové oblasti školy v Braníku a sourozence našich žáků. Seznam spádových ulic stanoví vyhláška městské části Praha 4.',
        docs: [{ id: 'z1', n: 'Žádost o přijetí k základnímu vzdělávání', note: 'vyplníte i přímo u zápisu' }, { id: 'z2', n: 'Žádost o odklad povinné školní docházky', note: 's doporučením poradny a lékaře' },
          { id: 'z3', n: 'Kritéria přijetí', note: 'podle nich rozhodujeme, když je víc zájemců než míst' }, { id: 'z4', n: 'Desatero pro rodiče předškoláka', note: 'co by dítě mělo před nástupem zvládat' }],
        faq: [
          { id: 'f1', q: 'Musí dítě před nástupem umět číst nebo psát?', a: 'Ne. Čekáme, že dítě zvládne základní sebeobsluhu, vydrží chvíli u jedné činnosti a umí se domluvit. Číst a psát se naučí u nás.' },
          { id: 'f2', q: 'Jak požádat o odklad školní docházky?', a: 'K žádosti potřebujete doporučení školského poradenského zařízení a dětského lékaře nebo klinického psychologa. Žádost podáte přímo u zápisu.' },
          { id: 'f3', q: 'Kdy se dozvíme, jestli bylo dítě přijato?', a: 'Seznam přijatých dětí pod registračními čísly zveřejníme na webu a na dveřích školy do 30 dnů od zápisu.' },
          { id: 'f4', q: 'Můžeme si vybrat třídu nebo paní učitelku?', a: 'Třídy sestavujeme tak, aby byly vyvážené. Přání, třeba aby kamarádi chodili spolu, rádi zohledníme – napište ho do poznámky při registraci.' },
          { id: 'f5', q: 'Mají prvňáčci družinu a obědy?', a: 'Ano. Přihlášku do družiny i jídelny vyřídíte na informační schůzce v červnu. Družina je otevřená ve školní dny 6:30–17:30.' }]
      },
      notice: { on: false, chip: 'Připomínáme', text: '', linkLabel: 'Kalendář akcí', link: 'akce', until: '', tone: 'info' },
      emails: []
    };
    /* kroužky: pololetí podle dneška */
    var pol = D.events.find(function (e) { return e.title === 'Pololetní vysvědčení'; }), last = D.events.find(function (e) { return e.title === 'Konec školního roku'; });
    var second = pol && t > pol.from && TS.parse(t).getMonth() < 6;
    var start = second ? S.nextSchoolDay(D, TS.addDays(pol.from, 1), false) : S.nextSchoolDay(D, nth(Y, 10, 1, 1), true);
    D.clubSet = { term: second ? '2. pololetí' : '1. pololetí', start: TS.weekStart(start), end: second ? last.from : pol.from, notify: 'krouzky@zs-slunecnice.cz', payDays: 14, open: true,
      info: 'Kroužky vedou učitelé naší školy a lektoři, které dobře známe. Děti z družiny si vedoucí vyzvedne a po kroužku je do družiny zase přivede.' };
    D.apps = appsDefaults(D.clubs, Y);
    /* jídelníček: minulý, tento a příští školní týden (příští je vyplněný jen napůl) */
    var cur = TS.weekStart(S.nextSchoolDay(D, t, true));
    [-1, 0, 1].forEach(function (k, wi) {
      var mon = TS.addDays(cur, k * 7), wk = S.blankWeek();
      for (var i = 0; i < 5; i++) {
        var d = TS.addDays(mon, i);
        if (S.volnoOn(D, d)) continue;
        if (k === 1 && i >= 3) continue;
        wk.days[i] = dishDay(DISH[(wi * 5 + i + 5) % 15]);
      }
      wk.updatedAt = Date.now() - ((2 - wi) * 5 * 24 + 3) * 36e5;
      D.menus[mon] = wk;
    });
    D.posts = postsDefaults(t, D);
    /* oznámení v liště: nejbližší akce pro rodiče nebo volno v příštích 45 dnech */
    var soon = S.upcoming(D, t, 0, function (e) { return (e.type === 'rodice' || e.type === 'volno') && e.from >= t && e.from <= TS.addDays(t, 45) && S.evDays(e) < 20; });
    var pickE = soon.filter(function (e) { return e.type === 'rodice'; })[0] || soon[0] ||
      S.upcoming(D, t, 1, function (e) { return e.from >= t && e.from <= TS.addDays(t, 60) && S.evDays(e) < 20; })[0];
    if (pickE) D.notice = { on: true, chip: 'Připomínáme', text: S.noticeFrom(pickE), linkLabel: 'Kalendář akcí', link: 'akce#e-' + pickE.id, until: pickE.to, tone: 'info' };
    /* e-maily, které by web k nejnovějším přihláškám už poslal */
    D.apps.filter(function (a) { return !a.seen; }).sort(function (a, b) { return a.at - b.at; }).forEach(function (a) {
      S.mail(D, 'new_school', a, { at: a.at }); S.mail(D, a.status === 'wait' ? 'waitlist' : 'accepted', a, { at: a.at + 3000 });
    });
    D.emails.forEach(function (e, i) { e.read = i > 1; });
    return D;
  };

  /* ---------- e-maily (přihlášky do kroužků) ---------- */
  S.brand = function (D) { return { name: D.info.short, sub: D.info.place }; };
  S.mailFoot = function (D) { return esc(D.info.legal) + '<br>' + esc(D.info.street + ', ' + D.info.zip + ' ' + D.info.city) + ' · ' + esc(D.info.phone) + ' · ' + esc(D.info.email); };
  S.firstName = function (n) { return String(n || '').trim().split(/\s+/)[0]; };
  S.payDue = function (D, a) { return TS.addDays(TS.iso(new Date(a.at || Date.now())), D.clubSet.payDays || 14); };
  S.appTable = function (D, a, c, extra) {
    var leader = c.leader ? esc(c.leader) : '';
    return TS.mail.table([['Kroužek', '<b>' + esc(c.n) + '</b>'], ['Kdy', esc(S.clubEvery(c))],
      ['Kde', esc(c.place)], leader ? ['Vede', leader] : null, ['Dítě', esc(a.child) + ', ' + esc(a.cls)]].concat(extra || []));
  };
  S.mail = function (D, kind, a, extra) {
    extra = extra || {};
    var c = S.clubById(D, a.club); if (!c) return null;
    var brand = S.brand(D), foot = S.mailFoot(D), html = '', subject = '', box = 'rodice', to = a.email, fn = S.firstName(a.child);
    var ev = S.clubEvent(D, c, kind === 'promoted' ? TS.today() : TS.iso(new Date(a.at || Date.now()))), wait = S.clubWait(D, c.id), pos = wait.indexOf(a) + 1;
    var pay = c.price ? '<div class="k-em-note"><b>Platba za ' + esc(D.clubSet.term) + ': ' + TS.money(c.price) + '</b><br>Účet ' + esc(D.info.account) + ', variabilní symbol <b>' + esc(a.vs) + '</b>, splatnost do ' + TS.fmtDMY(S.payDue(D, a)) + '.</div>' : '<div class="k-em-note">Kroužek je zdarma, nic neplatíte.</div>';
    var cal = '<div class="k-em-btns">' + TS.mail.btn('Přidat do Google Kalendáře', ev.gcal, true) + TS.mail.btn('Všechny kroužky', S.PAGES.krouzky) + '</div>' + TS.mail.attach('krouzek-' + TS.slug(c.n) + '.ics', 'Příloha pro Apple Kalendář a Outlook, každý týden až do konce pololetí');
    var msg = extra.msg ? '<p class="k-em-quote">' + esc(extra.msg) + '</p>' : '';
    if (kind === 'new_school') {
      box = 'skola'; to = D.clubSet.notify;
      subject = 'Nová přihláška: ' + c.n + ' – ' + a.child + ' (' + a.cls + ')';
      html = '<h2>Nová přihláška do kroužku</h2><p>' + (a.status === 'wait' ? 'Kroužek je plný, dítě je zapsané jako <b>náhradník (' + pos + '. v pořadí)</b>. Rodič dostal e-mail, že se mu ozveme, až se místo uvolní.' : 'Místo bylo volné, přihláška je <b>přijatá</b>. Rodič dostal potvrzení s platebními údaji.') + '</p>' +
        TS.mail.table([['Kroužek', '<b>' + esc(c.n) + '</b>, ' + esc(S.clubWhen(c))], ['Dítě', esc(a.child) + ', ' + esc(a.cls)], ['Zákonný zástupce', esc(a.parent)], ['Telefon', esc(a.phone)], ['E-mail', esc(a.email)],
          ['Družina', a.druzina ? 'ano, vyzvednout v družině' : 'ne'], a.note ? ['Poznámka', esc(a.note)] : null, ['Obsazenost', S.clubTaken(D, c.id) + ' z ' + c.cap + ' míst' + (wait.length ? ', ' + wait.length + ' ' + TS.plural(wait.length, 'náhradník', 'náhradníci', 'náhradníků') : '')]]) +
        '<div class="k-em-btns">' + TS.mail.btn('Otevřít v administraci', S.PAGES.sprava + '#krouzky/' + a.id, true) + '</div>';
    } else if (kind === 'accepted' || kind === 'promoted') {
      subject = (kind === 'promoted' ? 'Uvolnilo se místo – ' : 'Přihláška přijata – ') + c.n;
      html = '<h2>' + esc(fn) + ' má místo v kroužku ' + esc(c.n) + '</h2><p>Dobrý den, ' + (kind === 'promoted' ? 'v kroužku se uvolnilo místo a ' + esc(fn) + ' byl' + (S.isGirl(fn) ? 'a' : '') + ' první na řadě. Místo je teď potvrzené.' : 'děkujeme za přihlášku. Místo je potvrzené, těšíme se na první schůzku.') + '</p>' + msg +
        S.appTable(D, a, c, [['První schůzka', TS.cap(TS.fmtLong(ev.first))], ['Cena', S.clubPrice(c, true)]]) + pay +
        (a.druzina ? '<p>' + esc(fn) + ' chodí do družiny – vedoucí kroužku si ' + (S.isGirl(fn) ? 'ji' : 'ho') + ' tam vyzvedne a po skončení zase přivede.</p>' : '<p>Po kroužku čekají děti na rodiče u hlavního vchodu, pokud jste nám nenapsali, že chodí domů samy.</p>') + cal;
    } else if (kind === 'waitlist') {
      subject = 'Kroužek ' + c.n + ' je zatím plný';
      html = '<h2>Zapsali jsme vás jako náhradníka</h2><p>Dobrý den, děkujeme za přihlášku. Kroužek ' + esc(c.n) + ' má všech ' + c.cap + ' míst obsazených, ' + esc(fn) + ' je proto <b>' + pos + '. náhradník</b>. Jakmile se místo uvolní, ozveme se vám e-mailem.</p>' +
        S.appTable(D, a, c) + '<p>Mezitím se můžete podívat na kroužky, kde volná místa ještě máme.</p><div class="k-em-btns">' + TS.mail.btn('Kroužky s volnými místy', S.PAGES.krouzky, true) + '</div>';
    } else if (kind === 'cancelled') {
      subject = 'Přihláška do kroužku ' + c.n + ' je zrušená';
      html = '<h2>Přihlášku jsme zrušili</h2><p>Dobrý den, přihlášku dítěte ' + esc(a.child) + ' do kroužku ' + esc(c.n) + ' jsme zrušili.' + (a.wasOk && c.price ? ' Pokud jste už zaplatili, poměrnou část vám vrátíme na účet, ze kterého platba přišla.' : '') + '</p>' + msg +
        '<div class="k-em-btns">' + TS.mail.btn('Vybrat jiný kroužek', S.PAGES.krouzky, true) + '</div>';
    }
    if (!to) return null;
    return TS.mail.add(D, { box: box, to: to, from: D.info.short + ' <' + D.clubSet.notify + '>', subject: subject, html: TS.mail.wrap(brand, html, foot), ref: a.id, at: extra.at || Date.now() });
  };
  S.log = function (a, t) { a.log = (a.log || []).slice(-14); a.log.push({ at: Date.now(), t: t }); };
})();
