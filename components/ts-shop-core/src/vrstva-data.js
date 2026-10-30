/* VRSTVA oblečení – data ukázky, pravidla e-shopu, e-maily a faktura (sdílí web i administrace) */
(function () {
  'use strict';
  var V = window.VRSTVA = {}, esc = TS.esc, money = TS.money;

  /* ---------- katalog: barvy, kategorie, velikosti ---------- */
  V.COLORS = { cerna: ['Černá', '#1D1D20'], popel: ['Popelavá', '#A3A5A8'], lahev: ['Lahvová zelená', '#2F4A3A'], cihla: ['Cihlová', '#B5553A'], ecru: ['Ecru', '#E8E2D2'], namor: ['Námořní modrá', '#1E2A44'], okr: ['Okrová', '#C99A3B'], svest: ['Švestková', '#5A2E44'] };
  V.CATS = { mikiny: 'Mikiny', tricka: 'Trička', bundy: 'Bundy', kalhoty: 'Kalhoty', doplnky: 'Doplňky' };
  V.CAT1 = { mikiny: 'Mikina', tricka: 'Tričko', bundy: 'Bunda', kalhoty: 'Kalhoty', doplnky: 'Doplněk' };
  V.SHAPES = { hoodie: 'Mikina s kapucí', crew: 'Mikina bez kapuce', tee: 'Tričko', long: 'Triko s dlouhým rukávem', jacket: 'Bunda', pants: 'Kalhoty', jogger: 'Tepláky', cap: 'Kšiltovka', beanie: 'Čepice', sock: 'Ponožky' };
  V.SIZESETS = { std: ['XS', 'S', 'M', 'L', 'XL', 'XXL'], uni: ['UNI'], sock: ['36–39', '40–43', '44–47'] };
  V.ALLSIZES = V.SIZESETS.std.concat(V.SIZESETS.uni, V.SIZESETS.sock);
  V.cname = function (c) { return (V.COLORS[c] || ['?'])[0]; };
  V.chex = function (c) { return (V.COLORS[c] || ['', '#999'])[1]; };

  /* ---------- technické nákresy oblečení (SVG) ---------- */
  var SH = {
    tee: { fill: ['M72 22Q100 36 128 22L168 40L188 82L160 94L148 74L148 204L52 204L52 74L40 94L12 82L32 40Z'], line: 'M72 22Q100 44 128 22', stitch: 'M52 196H148M16 74L43 86M184 74L157 86M76 26Q100 48 124 26' },
    long: { fill: ['M72 22Q100 36 128 22L166 38L190 190L168 194L148 90L148 204L52 204L52 90L32 194L10 190L34 38Z'], line: 'M72 22Q100 44 128 22', stitch: 'M52 196H148M12 180L33 184M188 180L167 184M76 26Q100 48 124 26' },
    crew: { fill: ['M70 24Q100 38 130 24L168 40L190 186L166 192L150 96L150 196L50 196L50 96L34 192L10 186L32 40Z', 'M50 196H150V208H50Z', 'M10 186L34 192L33 204L9 198Z', 'M190 186L166 192L167 204L191 198Z'], line: 'M70 24Q100 46 130 24', stitch: 'M74 28Q100 50 126 28M60 196V208M70 196V208M80 196V208M90 196V208M100 196V208M110 196V208M120 196V208M130 196V208M140 196V208' },
    hoodie: { fill: ['M74 34L38 46L12 186L36 192L52 100L52 196L148 196L148 100L164 192L188 186L162 46L126 34Z', 'M52 196H148V208H52Z', 'M12 186L36 192L35 204L11 198Z', 'M188 186L164 192L165 204L189 198Z', 'M74 34Q66 2 100 0Q134 2 126 34Q100 58 74 34Z', 'M70 140H130L140 178H60Z'], shade: ['M84 32Q82 12 100 10Q118 12 116 32Q100 44 84 32Z'], line: 'M92 44L90 86M108 44L110 86', stitch: 'M64 146H136M74 38Q100 58 126 38M62 196V208M74 196V208M86 196V208M98 196V208M110 196V208M122 196V208M134 196V208', dots: [[90, 88], [110, 88]] },
    jacket: { fill: ['M70 20L34 34L12 170L36 176L50 100L50 206L150 206L150 100L164 176L188 170L166 34L130 20L100 40Z', 'M70 20L84 54L100 40L116 54L130 20L116 12L100 28L84 12Z'], line: 'M100 40V206M60 130H92V168H60ZM108 130H140V168H108ZM110 66H134V88H110Z', stitch: 'M63 135H89M111 135H137M113 71H131M50 198H150M15 162L37 167M185 162L163 167', dots: [[100, 62], [100, 92], [100, 122], [100, 152], [100, 182]] },
    pants: { fill: ['M56 12H144L154 208H110L100 76L90 208H46Z', 'M56 12H144V26H56Z', 'M66 9H71V29H66Z', 'M129 9H134V29H129Z'], line: 'M100 26V72M64 26Q72 48 58 56M136 26Q128 48 142 56', stitch: 'M48 200H90M110 200H152M96 30V64' },
    jogger: { fill: ['M56 12H144L148 190H112L100 76L88 190H52Z', 'M56 12H144V28H56Z', 'M52 190H88L86 208H56Z', 'M112 190H148L144 208H114Z'], line: 'M96 28L92 52M104 28L108 52', stitch: 'M64 36Q74 56 60 64M136 36Q126 56 140 64M58 196H86M114 196H146' },
    cap: { fill: ['M44 134Q46 60 110 58Q172 60 174 134Z', 'M44 134Q30 148 18 162Q70 178 132 162Q160 150 174 134Z'], line: 'M110 60V134M110 60Q78 80 76 134M110 60Q142 80 144 134', stitch: 'M26 158Q74 172 128 158M50 128Q110 118 170 128', dots: [[92, 90], [128, 90]], button: [110, 58] },
    beanie: { fill: ['M50 160Q44 58 100 50Q156 58 150 160Z', 'M42 140H158V184H42Z'], line: 'M52 142V182M62 142V182M72 142V182M82 142V182M92 142V182M108 142V182M118 142V182M128 142V182M138 142V182M148 142V182', stitch: 'M100 52V140M76 62Q68 100 70 140M124 62Q132 100 130 140', patch: true },
    sock: { fill: ['M80 18H124V128Q124 148 142 164Q162 180 152 198Q142 212 118 200L86 180Q70 170 74 148L80 128Z'], shade: ['M142 164Q162 180 152 198Q146 206 134 204Q140 186 142 164Z', 'M80 132H94Q96 156 84 174Q70 164 76 146Z'], line: 'M80 36H124M86 20V34M94 20V34M102 20V34M110 20V34M118 20V34', stitch: 'M124 44V124' }
  };
  /* body pro popisky detailů v hero (souřadnice nákresu 200 × 220) */
  V.HS = { hoodie: [[100, 20], [86, 160], [176, 196]], crew: [[100, 32], [100, 120], [178, 196]], tee: [[100, 32], [100, 130], [172, 84]], long: [[100, 32], [100, 130], [178, 186]],
    jacket: [[100, 26], [122, 78], [76, 150]], pants: [[100, 18], [66, 46], [130, 200]], jogger: [[100, 20], [70, 50], [130, 200]], cap: [[110, 60], [110, 100], [80, 162]],
    beanie: [[100, 64], [60, 162], [100, 158]], sock: [[102, 28], [102, 90], [146, 186]] };
  function lum(h) { var n = parseInt(h.slice(1), 16); return (.299 * (n >> 16) + .587 * ((n >> 8) & 255) + .114 * (n & 255)) / 255; }
  V.flat = function (shape, hex, label) {
    var s = SH[shape] || SH.tee, light = lum(hex) > .55, ink = '#111214', stitch = light ? 'rgba(17,18,20,.5)' : 'rgba(255,255,255,.5)', shade = light ? 'rgba(17,18,20,.1)' : 'rgba(0,0,0,.28)';
    var o = '<svg viewBox="0 0 200 220" ' + (label ? 'role="img" aria-label="' + esc(label) + '"' : 'aria-hidden="true"') + '><g stroke-linejoin="round" stroke-linecap="round">';
    s.fill.forEach(function (p) { o += '<path d="' + p + '" fill="' + hex + '" stroke="' + ink + '" stroke-width="1.6"/>'; });
    (s.shade || []).forEach(function (p) { o += '<path d="' + p + '" fill="' + shade + '" stroke="' + ink + '" stroke-width="1.4"/>'; });
    o += '<path d="' + s.line + '" fill="none" stroke="' + ink + '" stroke-width="1.4"/>';
    o += '<path d="' + s.stitch + '" fill="none" stroke="' + stitch + '" stroke-width="1.2" stroke-dasharray="3 3"/>';
    (s.dots || []).forEach(function (d) { o += '<circle cx="' + d[0] + '" cy="' + d[1] + '" r="2.8" fill="' + ink + '"/>'; });
    if (s.button) o += '<circle cx="' + s.button[0] + '" cy="' + s.button[1] + '" r="5" fill="' + hex + '" stroke="' + ink + '" stroke-width="1.4"/>';
    if (s.patch) o += '<rect x="86" y="150" width="28" height="16" fill="' + ink + '"/><text x="100" y="162" text-anchor="middle" font-family="Unbounded, sans-serif" font-weight="800" font-size="9" fill="#FAFAF8">V</text>';
    return o + '</g></svg>';
  };
  V.pflat = function (p, c, label) { return V.flat(p.sh, V.chex(c || p.colors[0]), label ? p.n + ', ' + V.cname(c || p.colors[0]) : ''); };

  /* ---------- výchozí produkty ---------- */
  var CARE_COTTON = 'Perte naruby na 30 °C s podobnými barvami, nesušte v sušičce a nežehlete výšivku. Bio bavlna se při prvním praní srazí nejvýš o 2 %.';
  function productDefaults() {
    var T = TS.today(), P = [
      { n: 'Mikina OSNOVA', cat: 'mikiny', sh: 'hoodie', price: 1890, mat: 'Bio bavlna · 400 g/m²', fit: 'Volnější střih, delší rukávy', colors: ['popel', 'cerna', 'lahev', 'cihla'], isNew: true, r: 4.9, rv: 212, added: 10,
        desc: 'Naše nejtěžší mikina. Počesaný úplet 400 g/m² hřeje i bez bundy, kapuce má dvojitou vrstvu a drží tvar i po stém praní. Šijeme ji v sériích po 300 kusech v jedné barvě.', feats: ['Kapuce s dvojitou vrstvou', 'Klokaní kapsa, zesílené švy', 'Žebrové manžety s elastanem'] },
      { n: 'Mikina ÚTEK', cat: 'mikiny', sh: 'crew', price: 1590, mat: 'Bio bavlna · 360 g/m²', fit: 'Rovný střih', colors: ['ecru', 'namor', 'svest'], r: 4.8, rv: 96, added: 60,
        desc: 'Mikina bez kapuce, která se vejde pod bundu i pod sako. Výstřih kryje páska, aby se nevytahal, a rovný střih nechává místo pro tričko pod ní.', feats: ['Výstřih s krycí páskou', 'Rovný střih na vrstvení', 'Žebrový lem s elastanem'] },
      { n: 'Tričko ZÁKLAD', cat: 'tricka', sh: 'tee', price: 690, mat: 'Bio bavlna · 180 g/m²', fit: 'Rovný střih', colors: ['ecru', 'cerna', 'okr', 'namor'], tag: 'Bestseller', r: 4.8, rv: 540, added: 400,
        desc: 'Tričko, které jsme navrhli jako první a pořád ho šijeme nejvíc. Hustý úplet 180 g/m² neprosvítá, ramena jsou zpevněná páskou a lem se nekroutí.', feats: ['Zpevněná ramena', 'Úzký žebrový průkrčník', 'Dvojitý prošitý lem'] },
      { n: 'Tričko HEAVY', cat: 'tricka', sh: 'tee', price: 890, sale: { type: 'pct', value: 20, from: TS.addDays(T, -5), to: TS.addDays(T, 9) }, mat: 'Bio bavlna · 240 g/m²', fit: 'Oversize, spadlá ramena', colors: ['cerna', 'lahev', 'cihla'], r: 4.7, rv: 188, added: 120,
        desc: 'Tlustší a volnější bratr trička ZÁKLAD. Úplet 240 g/m² padá rovně, spadlá ramena a delší rukáv dělají z trička kus, který obstojí i samostatně.', feats: ['Spadlá ramena', 'Široký průkrčník 2,5 cm', 'Úplet 240 g/m²'] },
      { n: 'Triko DLOUHÉ', cat: 'tricka', sh: 'long', price: 890, mat: 'Bio bavlna · 200 g/m²', fit: 'Rovný střih', colors: ['ecru', 'namor', 'popel'], r: 4.6, rv: 74, added: 200,
        desc: 'Triko s dlouhým rukávem na první vrstvu. Rukávy jsou o 3 cm delší, aby nevyjížděly z mikiny, a manžety drží tvar díky elastanu.', feats: ['Delší rukávy', 'Manžety s elastanem', 'Rovný střih'] },
      { n: 'Bunda DÍLNA', cat: 'bundy', sh: 'jacket', price: 3490, mat: 'Bavlněný kepr · 320 g/m²', fit: 'Rovný střih, 4 kapsy', colors: ['namor', 'okr', 'cerna'], isNew: true, r: 4.9, rv: 58, added: 6,
        desc: 'Pracovní bunda z pevného kepru, ve které šijeme i my v dílně. Čtyři kapsy, kovové knoflíky a dvojité švy. S každým nošením měkne a dostává vlastní tvar.', feats: ['Kovové knoflíky', 'Náprsní kapsa na telefon', 'Dvě velké spodní kapsy'] },
      { n: 'Bunda STOPA', cat: 'bundy', sh: 'jacket', price: 4290, mat: 'Voskovaná bavlna · 280 g/m²', fit: 'Volnější střih, vatovaná podšívka', colors: ['lahev', 'cerna'], low: true, r: 4.8, rv: 31, added: 90,
        desc: 'Voskovaná bunda do deště a větru s lehkou vatovanou podšívkou. Vosk se dá doma obnovit, sadu na impregnaci vám v dílně přidáme zdarma.', feats: ['Voskovaná bavlna', 'Vatovaná podšívka', 'Knoflíky pod légou'], care: 'Neperte v pračce. Nečistoty otřete vlhkou houbou, vosk obnovte jednou za sezónu. Rádi to uděláme i za vás v dílně.' },
      { n: 'Kalhoty ŠEV', cat: 'kalhoty', sh: 'pants', price: 1990, mat: 'Bavlněný kepr · 300 g/m²', fit: 'Rovný střih, vysoký pas', colors: ['okr', 'cerna', 'namor'], r: 4.7, rv: 120, added: 150,
        desc: 'Rovné kalhoty z kepru s vyšším pasem a hlubokými kapsami. Nohavice mají záložku 4 cm, takže je zkrátíme i prodloužíme zdarma.', feats: ['Vyšší pas', 'Hluboké kapsy', 'Záložka 4 cm na úpravu délky'] },
      { n: 'Tepláky KLID', cat: 'kalhoty', sh: 'jogger', price: 1490, sale: { type: 'price', value: 1190, from: TS.addDays(T, -3), to: TS.addDays(T, 11) }, mat: 'Bio bavlna · 360 g/m²', fit: 'Zúžené nohavice s manžetou', colors: ['popel', 'cerna'], r: 4.8, rv: 260, added: 180,
        desc: 'Tepláky ze stejného úpletu jako mikina ÚTEK. Pas se šňůrkou, zúžené nohavice s manžetou a zadní kapsa na klíče.', feats: ['Pas se šňůrkou', 'Zadní kapsa na klíče', 'Manžety s elastanem'] },
      { n: 'Kšiltovka ŠTÍT', cat: 'doplnky', sh: 'cap', price: 590, mat: 'Bavlněný kepr', fit: 'Nastavitelná přezka', colors: ['cerna', 'ecru', 'cihla'], sizes: 'uni', r: 4.6, rv: 44, added: 240,
        desc: 'Šestidílná kšiltovka z kepru, který zbývá po šití kalhot ŠEV. Mosazná přezka vzadu, vyšitá ventilační očka.', feats: ['Mosazná přezka', 'Vyšitá očka', 'Zbytkový kepr z dílny'], care: 'Perte ručně ve vlažné vodě, sušte na hlavě nebo na misce.' },
      { n: 'Čepice ÚPLET', cat: 'doplnky', sh: 'beanie', price: 490, mat: 'Merino vlna 100 %', fit: 'Žebrový úplet, ohrnovací', colors: ['okr', 'popel', 'svest', 'cerna'], sizes: 'uni', isNew: true, r: 4.9, rv: 83, added: 12,
        desc: 'Čepice z jemné merino vlny, která nekouše a hřeje i za vlhka. Dvojitá ohrnovací manžeta kryje uši.', feats: ['Merino vlna', 'Dvojitá manžeta', 'Našitý štítek V'], care: 'Perte ručně ve vlažné vodě s přípravkem na vlnu, sušte naležato.' },
      { n: 'Ponožky PÁR · 3 ks', cat: 'doplnky', sh: 'sock', price: 390, mat: 'Bio bavlna 80 %', fit: '3 páry v balení', colors: ['ecru', 'cerna', 'cihla'], sizes: 'sock', r: 4.7, rv: 310, added: 300,
        desc: 'Tři páry ponožek s vyztuženou patou a špičkou. Lem nesvírá, ale drží. Barva v názvu platí pro všechny tři páry v balení.', feats: ['Vyztužená pata a špička', 'Lem bez gumy', '3 páry v balení'], care: 'Perte na 40 °C, nesušte v sušičce.' }
    ];
    return P.map(function (p, i) {
      var n = i + 1, sz = V.SIZESETS[p.sizes || 'std'].slice(), stock = {};
      p.colors.forEach(function (c, ci) {
        sz.forEach(function (s, si) {
          var v = (n * 7 + si * 13 + ci * 5) % 17;
          stock[c + '|' + s] = p.low ? 2 + (v % 4) : (v === 0 && (n + ci) % 3 === 0 ? 0 : 4 + v);
        });
      });
      return { id: 'p' + n, n: p.n, cat: p.cat, sh: p.sh, price: p.price, sale: p.sale || null, mat: p.mat, fit: p.fit, desc: p.desc, care: p.care || CARE_COTTON, feats: p.feats,
        colors: p.colors, sizes: sz, stock: stock, isNew: !!p.isNew, tag: p.tag || '', hidden: false, rec: n, added: TS.addDays(T, -p.added), r: p.r, rv: p.rv };
    });
  }

  /* ---------- nastavení obchodu ---------- */
  function settingsDefaults() {
    return {
      freeFrom: 1500, lowStock: 2, cutoff: 14, autoInvoice: true, notify: 'ahoj@vrstva.cz',
      ship: [
        { id: 'zas', name: 'Zásilkovna', note: 'Výdejní místo nebo Z-BOX, doručení do 1–2 dnů', price: 79, on: true },
        { id: 'bal', name: 'Balíkovna', note: 'Pošta nebo box, doručení do 1–2 dnů', price: 69, on: true },
        { id: 'ppl', name: 'PPL na adresu', note: 'Kurýr vám den předem pošle SMS', price: 129, on: true },
        { id: 'osob', name: 'Osobní odběr v Náchodě', note: 'Plhovská 12, Po–Pá 9:00–17:00', price: 0, on: true }],
      pay: { card: { on: true }, transfer: { on: true, due: 5 }, cod: { on: true, fee: 39 } },
      company: { name: 'VRSTVA s.r.o.', street: 'Plhovská 12', zip: '547 01', city: 'Náchod', ico: '55555550', dic: 'CZ55555550', vat: 21, account: '1234567890/0800',
        email: 'ahoj@vrstva.cz', phone: '+420 491 000 999', hours: 'Po–Pá 9:00–17:00', register: 'Společnost je zapsaná v obchodním rejstříku vedeném Krajským soudem v Hradci Králové.' },
      series: { order: 'VR-26-', orderNext: 4831, invoice: 'FV26-', invoiceNext: 318 }
    };
  }
  V.POINTS = {
    zas: ['Náchod, Palachova 1303 (Z-BOX)', 'Náchod, Kamenice 105 (trafika)', 'Hradec Králové, Gočárova 1754', 'Praha 2, Vinohradská 48'],
    bal: ['Pošta Náchod 1, Masarykovo náměstí', 'Balíkovna box Náchod-Běloves', 'Pošta Hradec Králové 2', 'Pošta Praha 3']
  };

  /* ---------- zákazníci ukázky ---------- */
  var CUST = [
    ['Jana Dvořáková', 'jana.dvorakova@example.com', '+420 731 204 518', 'Komenského 412', 'Hradec Králové', '500 03'],
    ['Tomáš Bureš', 'tomas.bures@example.com', '+420 602 771 340', 'Jiráskova 88', 'Náchod', '547 01'],
    ['Lenka Šťastná', 'lenka.stastna@example.com', '+420 777 315 902', 'Palackého 1520', 'Pardubice', '530 02'],
    ['Michal Vávra', 'michal.vavra@example.com', '+420 608 119 457', 'Nádražní 23', 'Trutnov', '541 01'],
    ['Klára Holubová', 'klara.holubova@example.com', '+420 739 640 211', 'Slezská 112', 'Praha 3', '130 00'],
    ['Petr Zeman', 'petr.zeman@example.com', '+420 724 508 663', 'Masarykova 7', 'Brno', '602 00'],
    ['Veronika Malá', 'veronika.mala@example.com', '+420 605 287 114', 'Husova 301', 'Liberec', '460 01'],
    ['Ondřej Kříž', 'ondrej.kriz@example.com', '+420 776 902 435', 'Tyršova 15', 'Hronov', '549 31'],
    ['Barbora Němcová', 'barbora.nemcova@example.com', '+420 732 455 870', 'Sokolská 9', 'Olomouc', '779 00'],
    ['Adam Polák', 'adam.polak@example.com', '+420 603 118 296', 'Riegrova 44', 'Jaroměř', '551 01'],
    ['Tereza Hájková', 'tereza.hajkova@example.com', '+420 721 664 039', 'Dlouhá 18', 'Praha 1', '110 00'],
    ['Jiří Sýkora', 'jiri.sykora@example.com', '+420 775 230 517', 'Smetanova 610', 'Ústí nad Orlicí', '562 01']];
  V.DEMO_ACCOUNT = { email: 'jana.dvorakova@example.com', pass: 'ukazka-2026', name: 'Jana Dvořáková' };

  /* ---------- slevové kódy ---------- */
  function codeDefaults() {
    var T = TS.today(), c = function (o) { return Object.assign({ id: TS.uid('k'), type: 'pct', value: 10, min: 0, from: '', to: '', limit: 0, once: false, used: 0, active: true, note: '', batch: '', createdAt: Date.now() - 864e5 * 40 }, o); };
    var out = [
      c({ code: 'VRSTVA10', type: 'pct', value: 10, once: true, used: 41, note: 'Sleva za odběr newsletteru (starší společný kód)', createdAt: Date.now() - 864e5 * 300 }),
      c({ code: 'PODZIM300', type: 'amount', value: 300, min: 2000, from: TS.addDays(T, -12), to: TS.addDays(T, 14), used: 18, note: 'Podzimní kampaň na Instagramu' }),
      c({ code: 'DOPRAVAZDARMA', type: 'ship', value: 0, min: 800, from: TS.addDays(T, -8), to: TS.addDays(T, 6), used: 27, note: 'Doprava zdarma pro odběratele newsletteru' }),
      c({ code: 'LETO15', type: 'pct', value: 15, from: TS.addDays(T, -110), to: TS.addDays(T, -33), used: 64, note: 'Letní výprodej' }),
      c({ code: 'KAMARAD200', type: 'amount', value: 200, min: 1000, limit: 50, used: 50, note: 'Doporučení kamarádovi' })];
    ['DILNA-4KQ7XM', 'DILNA-9TB2HW', 'DILNA-R6PZ3N', 'DILNA-7WF5JC', 'DILNA-M2XV8D'].forEach(function (k, i) {
      out.push(c({ code: k, type: 'pct', value: 15, limit: 1, once: true, used: i < 2 ? 1 : 0, to: TS.addDays(T, 60), batch: 'Poděkování za opravu v dílně', note: 'Poděkování za opravu v dílně' }));
    });
    return out;
  }

  /* ---------- ceny, slevy, sklad ---------- */
  V.prod = function (D, id) { return D.products.find(function (p) { return p.id === id; }) || null; };
  V.saleOn = function (p, date) {
    var s = p.sale, d = date || TS.today();
    if (!s || !(TS.num(s.value) > 0)) return false;
    if (s.from && d < s.from) return false; if (s.to && d > s.to) return false;
    return true;
  };
  V.priceInfo = function (p, date) {
    var base = TS.num(p.price);
    if (!V.saleOn(p, date)) return { now: base, old: 0, pct: 0, to: '' };
    var now = p.sale.type === 'price' ? Math.round(TS.num(p.sale.value)) : Math.round(base * (100 - TS.num(p.sale.value)) / 100);
    if (now >= base || now <= 0) return { now: base, old: 0, pct: 0, to: '' };
    return { now: now, old: base, pct: Math.round((1 - now / base) * 100), to: p.sale.to || '' };
  };
  V.st = function (p, c, s) { return Math.max(0, TS.num((p.stock || {})[c + '|' + s])); };
  V.colorStock = function (p, c) { return p.sizes.reduce(function (a, s) { return a + V.st(p, c, s); }, 0); };
  V.totalStock = function (p) { return p.colors.reduce(function (a, c) { return a + V.colorStock(p, c); }, 0); };
  V.lastFew = function (D, p) {
    var t = V.totalStock(p), mx = 0; if (!t) return false;
    p.colors.forEach(function (c) { p.sizes.forEach(function (s) { mx = Math.max(mx, V.st(p, c, s)); }); });
    return mx <= D.settings.lowStock;
  };
  /* štítky karty produktu: [text, třída] */
  V.badges = function (D, p) {
    var out = [], pi = V.priceInfo(p);
    if (!V.totalStock(p)) out.push(['Vyprodáno', 'dark']);
    if (pi.old) out.push(['−' + pi.pct + ' %', 'sale']);
    if (p.isNew) out.push(['Novinka', '']);
    if (V.totalStock(p) && V.lastFew(D, p)) out.push(['Poslední kusy', 'dark']);
    if (p.tag) out.push([p.tag, '']);
    return out.slice(0, 3);
  };
  V.visible = function (D) { return D.products.filter(function (p) { return !p.hidden; }); };
  /* nejbližší odeslání a doručení podle uzávěrky */
  V.delivery = function (D) {
    var d = new Date(), h = d.getHours(), ship = new Date(d), cut = D.settings.cutoff;
    if (h >= cut || d.getDay() === 0 || d.getDay() === 6) { do { ship.setDate(ship.getDate() + 1); } while (ship.getDay() === 0 || ship.getDay() === 6); }
    var del = new Date(ship); do { del.setDate(del.getDate() + 1); } while (del.getDay() === 0 || del.getDay() === 6);
    var same = ship.toDateString() === d.toDateString();
    return { same: same, ship: TS.iso(ship), del: TS.iso(del),
      text: (same ? 'Objednejte do ' + cut + ':00 a odešleme ještě dnes.' : 'Odešleme ' + (TS.diffDays(TS.today(), TS.iso(ship)) === 1 ? 'zítra' : TS.WD[ship.getDay()]) + '.') + ' Doručíme <b>' + TS.WD[del.getDay()] + ' ' + TS.fmtDM(TS.iso(del)) + '</b>' };
  };

  /* ---------- doprava, platba, košík ---------- */
  V.shipOf = function (D, id) { return D.settings.ship.find(function (s) { return s.id === id; }) || null; };
  V.shipOn = function (D) { return D.settings.ship.filter(function (s) { return s.on; }); };
  V.payLabel = function (pay, ship) { return pay === 'card' ? 'Kartou online' : pay === 'transfer' ? 'Bankovním převodem' : ship === 'osob' ? 'Při vyzvednutí' : 'Dobírkou'; };
  V.payOn = function (D, ship) {
    var P = D.settings.pay, out = [];
    if (P.card.on) out.push({ id: 'card', name: 'Kartou online', note: 'Platební kartou, Apple Pay nebo Google Pay', fee: 0 });
    if (P.transfer.on) out.push({ id: 'transfer', name: 'Bankovním převodem', note: 'Platební údaje pošleme e-mailem, odešleme po připsání platby', fee: 0 });
    if (P.cod.on) out.push(ship === 'osob' ? { id: 'cod', name: 'Při vyzvednutí', note: 'Hotově nebo kartou na prodejně', fee: 0 } : { id: 'cod', name: 'Dobírkou', note: 'Zaplatíte kurýrovi nebo na výdejním místě', fee: TS.num(P.cod.fee) });
    return out;
  };
  /* řádky košíku s aktuální cenou a skladem */
  V.lines = function (D, cart) {
    return (cart || D.cart || []).map(function (i) {
      var p = V.prod(D, i.pid); if (!p || p.hidden || p.colors.indexOf(i.c) < 0 || p.sizes.indexOf(i.s) < 0) return null;
      var pi = V.priceInfo(p), max = V.st(p, i.c, i.s);
      return { pid: p.id, p: p, n: p.n, c: i.c, s: i.s, q: i.q, max: max, price: pi.now, old: pi.old, sum: pi.now * i.q };
    }).filter(Boolean);
  };
  V.cartCount = function (D) { return (D.cart || []).reduce(function (a, i) { return a + i.q; }, 0); };
  V.calc = function (D, lines, shipId, payId, code) {
    var sub = 0, full = 0;
    lines.forEach(function (l) { sub += l.price * l.q; full += (l.old || l.price) * l.q; });
    var disc = 0, freeShip = false;
    if (code) {
      if (code.type === 'pct') disc = Math.round(sub * TS.num(code.value) / 100);
      else if (code.type === 'amount') disc = Math.min(sub, Math.round(TS.num(code.value)));
      else if (code.type === 'ship') freeShip = true;
    }
    var after = sub - disc, sm = V.shipOf(D, shipId), free = after >= D.settings.freeFrom || freeShip;
    var ship = lines.length && sm ? (free ? 0 : TS.num(sm.price)) : 0;
    var fee = lines.length && payId === 'cod' && shipId !== 'osob' ? TS.num(D.settings.pay.cod.fee) : 0;
    var total = after + ship + fee, base = Math.round(total / 1.21 * 100) / 100;
    return { sub: sub, full: full, saved: full - sub, disc: disc, freeShip: freeShip, ship: ship, shipFull: sm ? TS.num(sm.price) : 0, fee: fee, total: total, base: base, vat: Math.round((total - base) * 100) / 100,
      left: Math.max(0, D.settings.freeFrom - after), after: after };
  };
  V.codeText = function (k) { return k.type === 'pct' ? 'sleva ' + k.value + ' %' : k.type === 'amount' ? 'sleva ' + money(k.value) : 'doprava zdarma'; };
  V.codeState = function (k) {
    var t = TS.today();
    if (!k.active) return ['Vypnutý', 'done'];
    if (k.to && t > k.to) return ['Platnost skončila', 'done'];
    if (k.from && t < k.from) return ['Platí od ' + TS.fmtDM(k.from), 'warn'];
    if (k.limit && k.used >= k.limit) return ['Vyčerpaný', 'done'];
    return ['Aktivní', 'ok'];
  };
  V.findCode = function (D, raw) { var c = String(raw || '').trim().toUpperCase(); return c ? D.codes.find(function (k) { return k.code.toUpperCase() === c; }) || null : null; };
  /* ověření kódu v pokladně: {ok, msg, k} */
  V.checkCode = function (D, raw, sub, email) {
    var c = String(raw || '').trim().toUpperCase();
    if (!c) return { ok: false, msg: 'Zadejte slevový kód.' };
    var k = V.findCode(D, c), t = TS.today();
    if (!k) return { ok: false, msg: 'Kód „' + c + '“ neznáme. Zkontrolujte, prosím, překlepy.' };
    if (!k.active) return { ok: false, msg: 'Kód ' + k.code + ' už neplatí.' };
    if (k.from && t < k.from) return { ok: false, msg: 'Kód ' + k.code + ' začne platit ' + TS.fmtDMY(k.from) + '.' };
    if (k.to && t > k.to) return { ok: false, msg: 'Platnost kódu ' + k.code + ' skončila ' + TS.fmtDMY(k.to) + '.' };
    if (k.limit && k.used >= k.limit) return { ok: false, msg: k.limit === 1 ? 'Kód ' + k.code + ' už byl použitý.' : 'Kód ' + k.code + ' je vyčerpaný.' };
    if (k.min && sub < k.min) return { ok: false, msg: 'Kód ' + k.code + ' platí pro nákup od ' + money(k.min) + '. Do limitu chybí ' + money(k.min - sub) + '.', near: true };
    if (k.once && email && D.orders.some(function (o) { return o.status !== 'cancelled' && o.code && o.code.code === k.code && o.customer.email.toLowerCase() === String(email).trim().toLowerCase(); }))
      return { ok: false, msg: 'Kód ' + k.code + ' jste už jednou použili. Platí jen jednou na zákazníka.' };
    return { ok: true, k: k, msg: 'Kód ' + k.code + ': ' + V.codeText(k) + (k.to ? ', platí do ' + TS.fmtDayMonth(k.to) : '') + '.' };
  };
  V.genCode = function (prefix, len) {
    var A = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', s = '';
    for (var i = 0; i < len; i++) s += A.charAt(Math.floor(Math.random() * A.length));
    return (prefix ? prefix.toUpperCase().replace(/[^A-Z0-9-]/g, '') + (/-$/.test(prefix) ? '' : '-') : '') + s;
  };

  /* ---------- objednávky ---------- */
  V.STATUS = { new: 'Přijatá', paid: 'Zaplacená', packing: 'Připravujeme', shipped: 'Odeslaná', ready: 'K vyzvednutí', delivered: 'Doručená', cancelled: 'Stornovaná' };
  V.statusLabel = function (o) {
    if (o.status === 'new' && o.pay.method !== 'cod' && !o.pay.paid) return 'Čeká na platbu';
    if (o.status === 'delivered' && o.ship.id === 'osob') return 'Vyzvednutá';
    return V.STATUS[o.status];
  };
  V.statusCls = function (o) { return { new: o.pay.method === 'cod' ? 'pending' : 'warn', paid: 'pending', packing: 'accent', shipped: 'ok', ready: 'ok', delivered: 'done', cancelled: 'bad' }[o.status]; };
  V.isOpen = function (o) { return ['new', 'paid', 'packing'].indexOf(o.status) >= 0; };
  /* objednávka čeká na obsluhu (zaplacená nebo dobírka, ještě neodeslaná) */
  V.needsAction = function (o) { return o.status === 'paid' || o.status === 'packing' || (o.status === 'new' && (o.pay.method === 'cod' || o.pay.paid)); };
  V.next = function (o) {
    if (o.status === 'new') return (o.pay.method === 'cod' || o.pay.paid) ? ['packing'] : ['paid'];
    if (o.status === 'paid') return ['packing'];
    if (o.status === 'packing') return [o.ship.id === 'osob' ? 'ready' : 'shipped'];
    if (o.status === 'shipped' || o.status === 'ready') return ['delivered'];
    return [];
  };
  V.vs = function (o) { return o.no.replace(/\D/g, ''); };
  V.trackUrl = function (o) { return 'vrstva-objednavka.html?c=' + encodeURIComponent(o.no) + '&e=' + encodeURIComponent(o.customer.email); };
  V.trackNo = function (ship) {
    var d = function (n) { var s = ''; for (var i = 0; i < n; i++) s += Math.floor(Math.random() * 10); return s; };
    return ship === 'zas' ? 'Z ' + d(3) + ' ' + d(3) + ' ' + d(4) : ship === 'bal' ? 'DR' + d(4) + ' ' + d(4) + ' ' + d(2) + 'U' : d(4) + ' ' + d(4) + ' ' + d(3);
  };
  V.carrier = function (id) { return { zas: 'Zásilkovna', bal: 'Balíkovna', ppl: 'PPL', osob: '' }[id] || ''; };
  V.log = function (o, t, at) { o.log = o.log || []; o.log.push({ at: at || Date.now(), t: t }); };
  V.orderByNo = function (D, no) { var n = String(no || '').trim().toUpperCase().replace(/\s/g, ''); return D.orders.find(function (o) { return o.no.toUpperCase() === n || V.vs(o) === n.replace(/\D/g, '') && n.replace(/\D/g, '').length >= 6; }) || null; };
  V.orderById = function (D, id) { return D.orders.find(function (o) { return o.id === id; }) || null; };
  V.addrText = function (c) { return [c.street, [c.zip, c.city].filter(Boolean).join(' ')].filter(Boolean).join(', '); };
  V.shipText = function (o) { return o.ship.name + (o.ship.point ? ' · ' + o.ship.point : ''); };

  /* sklad: odečtení / vrácení kusů; vrací kombinace, které právě klesly na hranici „dochází“ */
  /* pohyby skladu (naskladnění, odpis, objednávka, storno, vrácení) pro přehled v administraci */
  V.moveLog = function (D, m) { D.moves = D.moves || []; D.moves.unshift(Object.assign({ at: Date.now() }, m)); if (D.moves.length > 80) D.moves.length = 80; };
  V.moveStock = function (D, items, dir, why) {
    var low = [];
    items.forEach(function (i) {
      var p = V.prod(D, i.pid); if (!p) return;
      var k = i.c + '|' + i.s, before = TS.num(p.stock[k]);
      p.stock[k] = Math.max(0, before + dir * i.q);
      if (why) V.moveLog(D, { pid: p.id, n: p.n, c: i.c, s: i.s, q: p.stock[k] - before, why: why, left: p.stock[k] });
      if (dir < 0 && before > D.settings.lowStock && p.stock[k] <= D.settings.lowStock) low.push({ p: p, c: i.c, s: i.s, n: p.stock[k] });
      else if (dir < 0 && p.stock[k] === 0 && before > 0 && before <= D.settings.lowStock) low.push({ p: p, c: i.c, s: i.s, n: 0 });
    });
    return low;
  };

  /* nová objednávka z pokladny webu */
  V.placeOrder = function (D, f) {
    var S = D.settings, lines = V.lines(D, f.cart);
    var short = lines.filter(function (l) { return l.q > l.max; });
    if (!lines.length) return { err: 'Košík je prázdný.' };
    if (short.length) return { err: short.map(function (l) { return l.n + ' (' + V.cname(l.c) + ', ' + l.s + ')' + (l.max ? ': skladem už jen ' + l.max + ' ks' : ': mezitím se vyprodalo'); }).join('; ') + '.' };
    var cc = f.code ? V.checkCode(D, f.code, V.calc(D, lines, f.ship, f.pay).sub, f.customer.email) : null;
    if (cc && !cc.ok) return { err: cc.msg, codeErr: true };
    var k = cc ? cc.k : null, t = V.calc(D, lines, f.ship, f.pay, k), sm = V.shipOf(D, f.ship);
    var o = {
      id: TS.uid('o'), no: S.series.order + String(S.series.orderNext).padStart(5, '0'), createdAt: Date.now(), status: f.pay === 'card' ? 'paid' : 'new',
      items: lines.map(function (l) { return { pid: l.pid, n: l.n, sh: l.p.sh, c: l.c, s: l.s, q: l.q, price: l.price, old: l.old }; }),
      ship: { id: sm.id, name: sm.name, price: t.ship, point: f.point || '' }, pay: { method: f.pay, label: V.payLabel(f.pay, f.ship), paid: f.pay === 'card', fee: t.fee },
      code: k ? { code: k.code, type: k.type, value: k.value, disc: t.disc } : null,
      totals: { sub: t.sub, disc: t.disc, ship: t.ship, fee: t.fee, total: t.total },
      customer: f.customer, delivery: f.delivery || null, note: f.note || '', tracking: '', invoice: null, at: { created: Date.now() }, log: [], internal: '', account: !!f.account
    };
    S.series.orderNext++;
    if (o.pay.paid) o.at.paid = Date.now();
    V.log(o, 'Objednávka z webu');
    if (o.pay.paid) V.log(o, 'Zaplaceno kartou online');
    if (k) k.used++;
    var low = V.moveStock(D, o.items, -1, 'Objednávka ' + o.no);
    D.orders.unshift(o);
    if (f.newsletter) V.subscribe(D, o.customer.email, true);
    V.mail(D, 'new_order', o);
    V.mail(D, 'received', o);
    if (low.length) V.lowMail(D, low, o);
    return { o: o };
  };

  /* změna stavu z administrace (nebo platba kartou ze sledování objednávky) */
  V.advance = function (D, o, to, x) {
    x = x || {}; var notify = x.notify !== false, now = Date.now(), sent = null;
    if (to === 'paid') {
      o.pay.paid = true; o.at.paid = now; if (o.status === 'new') o.status = 'paid';
      V.log(o, x.byCustomer ? 'Zákazník zaplatil kartou online' : 'Platba přijata' + (o.pay.method === 'transfer' ? ' na účet' : ''));
      if (notify) sent = V.mail(D, 'paid', o, x);
    } else if (to === 'packing') {
      o.status = 'packing'; o.at.packing = now; V.log(o, 'Připravujeme k odeslání');
      if (notify) sent = V.mail(D, 'packing', o, x);
    } else if (to === 'shipped') {
      o.status = 'shipped'; o.at.shipped = now; o.tracking = x.tracking || o.tracking || V.trackNo(o.ship.id);
      if (D.settings.autoInvoice && !o.invoice) V.issueInvoice(D, o);
      V.log(o, 'Odesláno (' + V.carrier(o.ship.id) + ', ' + o.tracking + ')');
      if (notify) sent = V.mail(D, 'shipped', o, x);
    } else if (to === 'ready') {
      o.status = 'ready'; o.at.shipped = now;
      if (D.settings.autoInvoice && !o.invoice) V.issueInvoice(D, o);
      V.log(o, 'Připraveno k vyzvednutí'); if (notify) sent = V.mail(D, 'ready', o, x);
    } else if (to === 'delivered') {
      o.status = 'delivered'; o.at.delivered = now;
      if (!o.pay.paid) { o.pay.paid = true; o.at.paid = now; }
      V.log(o, o.ship.id === 'osob' ? 'Vyzvednuto na prodejně' : 'Doručeno');
      if (notify) sent = V.mail(D, 'delivered', o, x);
    } else if (to === 'cancelled') {
      o.status = 'cancelled'; o.at.cancelled = now; o.cancelReason = x.reason || '';
      if (x.restock !== false) V.moveStock(D, o.items, 1, 'Storno ' + o.no);
      if (o.code) { var k = V.findCode(D, o.code.code); if (k && k.used > 0) k.used--; }
      V.log(o, (x.byCustomer ? 'Zrušeno zákazníkem' : 'Stornováno') + (x.reason ? ': ' + x.reason : '') + (x.restock !== false ? ' · kusy vráceny na sklad' : ''));
      if (notify) sent = V.mail(D, 'cancelled', o, x);
      if (x.byCustomer) V.mail(D, 'cancel_staff', o, x);
    }
    return sent;
  };

  /* ---------- faktura ---------- */
  V.issueInvoice = function (D, o) {
    if (o.invoice) return o.invoice;
    var S = D.settings.series, today = TS.today();
    var paidDay = o.at.paid ? TS.iso(new Date(o.at.paid)) : today;
    o.invoice = { no: S.invoice + String(S.invoiceNext).padStart(4, '0'), issued: today, taxDate: o.pay.paid ? paidDay : today, due: TS.addDays(today, TS.num(D.settings.pay.transfer.due) || 5) };
    S.invoiceNext++;
    V.log(o, 'Vystavena faktura ' + o.invoice.no);
    return o.invoice;
  };
  V.invLines = function (o) {
    var L = o.items.map(function (i) { return { name: i.n + ' – ' + V.cname(i.c) + ', ' + i.s, q: i.q, unit: i.price, total: i.price * i.q }; });
    if (o.code && o.code.disc) L.push({ name: 'Sleva – kód ' + o.code.code, q: 1, unit: -o.code.disc, total: -o.code.disc });
    L.push({ name: 'Doprava – ' + o.ship.name + (o.code && o.code.type === 'ship' ? ' (kód ' + o.code.code + ')' : ''), q: 1, unit: o.totals.ship, total: o.totals.ship });
    if (o.totals.fee) L.push({ name: 'Příplatek za dobírku', q: 1, unit: o.totals.fee, total: o.totals.fee });
    var sb = 0, sv = 0;
    L.forEach(function (l) { l.base = Math.round(l.total / 1.21 * 100) / 100; l.vat = Math.round((l.total - l.base) * 100) / 100; sb += l.base; sv += l.vat; });
    return { lines: L, base: Math.round(sb * 100) / 100, vat: Math.round(sv * 100) / 100, total: o.totals.total };
  };
  /* náhled a tisk (HTML) */
  V.invoiceHTML = function (D, o) {
    var C = D.settings.company, inv = o.invoice || { no: '(návrh)', issued: TS.today(), taxDate: TS.today(), due: TS.today() }, L = V.invLines(o), cu = o.customer;
    var m2 = function (n) { return TS.money2(n).replace(/^-/, '\u2212'); };
    return '<div class="k-doc vr-doc"><div class="vd-head"><div><b class="vd-logo">VRSTVA</b><span class="vd-mono">oblečení · Náchod</span></div><div class="vd-title"><b>Faktura – daňový doklad</b><span>č. ' + esc(inv.no) + '</span></div></div>' +
      '<div class="vd-parties"><div><span class="vd-l">Dodavatel</span><b>' + esc(C.name) + '</b><span>' + esc(C.street) + ', ' + esc(C.zip + ' ' + C.city) + '</span><span>IČO ' + esc(C.ico) + ' · DIČ ' + esc(C.dic) + ' · plátce DPH</span><span>' + esc(C.email) + ' · ' + esc(C.phone) + '</span></div>' +
      '<div><span class="vd-l">Odběratel</span><b>' + esc(cu.company || cu.name) + '</b>' + (cu.company ? '<span>' + esc(cu.name) + '</span>' : '') + '<span>' + esc(V.addrText(cu) || 'adresa neuvedena') + '</span>' + (cu.ico ? '<span>IČO ' + esc(cu.ico) + (cu.dic ? ' · DIČ ' + esc(cu.dic) : '') + '</span>' : '') + '<span>' + esc(cu.email) + '</span></div></div>' +
      '<div class="vd-meta"><div><span class="vd-l">Datum vystavení</span><b>' + TS.fmtDMY(inv.issued) + '</b></div><div><span class="vd-l">DUZP</span><b>' + TS.fmtDMY(inv.taxDate) + '</b></div><div><span class="vd-l">' + (o.pay.paid ? 'Uhrazeno' : 'Splatnost') + '</span><b>' + (o.pay.paid ? TS.fmtDMY(TS.iso(new Date(o.at.paid || Date.now()))) : TS.fmtDMY(inv.due)) + '</b></div>' +
      '<div><span class="vd-l">Způsob platby</span><b>' + esc(o.pay.label) + '</b></div><div><span class="vd-l">Variabilní symbol</span><b>' + V.vs(o) + '</b></div><div><span class="vd-l">Objednávka</span><b>' + esc(o.no) + '</b></div></div>' +
      '<table class="vd-items"><thead><tr><th>Položka</th><th>Ks</th><th>Cena/ks</th><th>Základ</th><th>DPH 21 %</th><th>Celkem</th></tr></thead><tbody>' +
      L.lines.map(function (l) { return '<tr><td>' + esc(l.name) + '</td><td>' + l.q + '</td><td>' + m2(l.unit) + '</td><td>' + m2(l.base) + '</td><td>' + m2(l.vat) + '</td><td>' + m2(l.total) + '</td></tr>'; }).join('') + '</tbody></table>' +
      '<div class="vd-sum"><div class="vd-bank">' + (o.pay.paid ? '<b>Uhrazeno ' + esc(o.pay.label.toLowerCase()) + '.</b> Neplaťte prosím znovu.' : 'Číslo účtu <b>' + esc(C.account) + '</b> · VS <b>' + V.vs(o) + '</b>') + '</div>' +
      '<dl><dt>Základ daně 21 %</dt><dd>' + m2(L.base) + '</dd><dt>DPH 21 %</dt><dd>' + m2(L.vat) + '</dd><dt class="vd-tot">' + (o.pay.paid ? 'Celkem uhrazeno' : 'Celkem k úhradě') + '</dt><dd class="vd-tot">' + money(L.total) + '</dd></dl></div>' +
      '<p class="vd-foot">' + esc(C.register) + '</p></div>';
  };
  /* PDF (jsPDF a písma VRSTVY se načtou až při prvním stažení) */
  V.PDF_FONTS = [['manrope-400.ttf', 'Manrope', 'normal'], ['manrope-700.ttf', 'Manrope', 'bold'], ['unbounded-700.ttf', 'Unbounded', 'bold'], ['plex-mono-400.ttf', 'Plex', 'normal']];
  V.invoicePdf = function (D, o) {
    return TS.pdf.load(V.PDF_FONTS).then(function (make) {
      var doc = make(), C = D.settings.company, inv = o.invoice, L = V.invLines(o), cu = o.customer;
      doc.setProperties({ title: 'Faktura ' + inv.no, subject: 'Objednávka ' + o.no, author: C.name, creator: 'VRSTVA' });
      var PW = doc.internal.pageSize.getWidth(), PH = doc.internal.pageSize.getHeight(), Lx = 50, R = PW - 50, CW = R - Lx, LH = 13, BOTTOM = PH - 70;
      var INK = [17, 18, 20], MUT = [98, 100, 107], LINE = [218, 218, 212], COB = [35, 64, 245];
      var font = function (n, size, col, st) { doc.setFont(n, st || 'normal'); doc.setFontSize(size); doc.setTextColor.apply(doc, col || INK); };
      var lab = function (t, x, y, right) { font('Plex', 6.8, MUT); t = t.toUpperCase(); var w = doc.getTextWidth(t) + .6 * (t.length - 1); doc.text(t, right ? x - w : x, y, { charSpace: .6 }); };
      var rule = function (y, w, col, x1, x2) { doc.setDrawColor.apply(doc, col || LINE); doc.setLineWidth(w || .6); doc.line(x1 == null ? Lx : x1, y, x2 == null ? R : x2, y); };
      var para = function (t, x, y, w) { doc.splitTextToSize(String(t), w).forEach(function (l) { doc.text(l, x, y); y += LH; }); return y; };
      var m2 = function (n) { return TS.money2(n).replace(/[\u00a0\u202f]/g, ' ').replace(/^-/, '\u2212'); };
      var note = function () { font('Manrope', 7.5, MUT); doc.text('Ukázkový doklad z ukázkového e-shopu studia T&S. Firma, osoby i všechny údaje jsou smyšlené.', PW / 2, PH - 32, { align: 'center' }); };
      /* hlavička */
      var y = 58;
      doc.setFillColor.apply(doc, COB); doc.rect(Lx, y - 4, 8, 8, 'F');
      font('Unbounded', 22, INK, 'bold'); doc.text('VRSTVA', Lx + 16, y + 6, { charSpace: 3 });
      lab('Oblečení · Náchod', Lx + 16, y + 22);
      font('Manrope', 13, INK, 'bold'); doc.text('Faktura – daňový doklad', R, y + 2, { align: 'right' });
      font('Plex', 10.5, MUT); doc.text('č. ' + inv.no, R, y + 18, { align: 'right' });
      y += 36; rule(y, 1.4, INK);
      /* strany */
      y += 22; var xR = Lx + CW * .52, wL = xR - Lx - 20, wR = R - xR;
      lab('Dodavatel', Lx, y); lab('Odběratel', xR, y); y += 16;
      var yl = y, yr = y;
      font('Manrope', 10, INK, 'bold'); yl = para(C.name, Lx, yl, wL);
      font('Manrope', 9.5); [C.street + ', ' + C.zip + ' ' + C.city, 'IČO ' + C.ico + ' · DIČ ' + C.dic, 'Plátce DPH', C.email + ' · ' + C.phone].forEach(function (t) { yl = para(t, Lx, yl, wL); });
      font('Manrope', 10, INK, 'bold'); yr = para(cu.company || cu.name, xR, yr, wR);
      font('Manrope', 9.5); [cu.company ? cu.name : '', V.addrText(cu), cu.ico ? 'IČO ' + cu.ico + (cu.dic ? ' · DIČ ' + cu.dic : '') : '', cu.email].filter(Boolean).forEach(function (t) { yr = para(t, xR, yr, wR); });
      y = Math.max(yl, yr) + 2; rule(y);
      /* data a platba */
      y += 20; var c3 = [Lx, Lx + CW / 3, Lx + 2 * CW / 3];
      var paidDay = o.at.paid ? TS.fmtDMY(TS.iso(new Date(o.at.paid))) : '';
      var meta = [['Datum vystavení', TS.fmtDMY(inv.issued)], ['Datum usk. zdan. plnění', TS.fmtDMY(inv.taxDate)], [o.pay.paid ? 'Uhrazeno' : 'Datum splatnosti', o.pay.paid ? paidDay : TS.fmtDMY(inv.due)],
        ['Způsob platby', o.pay.label], ['Variabilní symbol', V.vs(o)], ['Číslo objednávky', o.no]];
      meta.forEach(function (m, i) { var x = c3[i % 3], yy = y + Math.floor(i / 3) * 34; lab(m[0], x, yy); font(i === 4 ? 'Plex' : 'Manrope', 10, INK, i === 4 ? 'normal' : 'bold'); doc.text(m[1], x, yy + 14); });
      y += 56; rule(y);
      /* položky */
      var xQ = R - 286, xU = R - 214, xB = R - 142, xV = R - 72;
      var head = function (yy) { lab('Položka', Lx, yy); lab('Ks', xQ, yy, true); lab('Cena/ks', xU, yy, true); lab('Základ', xB, yy, true); lab('DPH 21 %', xV, yy, true); lab('Celkem', R, yy, true); rule(yy + 8, .9, INK); return yy + 8; };
      y = head(y + 24);
      L.lines.forEach(function (l) {
        font('Manrope', 9.5);
        var ls = doc.splitTextToSize(l.name, xQ - 30 - Lx);
        if (y + ls.length * LH + 14 > BOTTOM) { note(); doc.addPage(); y = head(64); font('Manrope', 9.5); }
        var yy = y + 17;
        ls.forEach(function (t, i) { doc.text(t, Lx, yy + i * LH); });
        font('Plex', 9); doc.text(String(l.q), xQ, yy, { align: 'right' }); doc.text(m2(l.unit), xU, yy, { align: 'right' }); doc.text(m2(l.base), xB, yy, { align: 'right' }); doc.text(m2(l.vat), xV, yy, { align: 'right' });
        font('Manrope', 9.5, INK, 'bold'); doc.text(m2(l.total), R, yy, { align: 'right' });
        y = yy + (ls.length - 1) * LH + 9; rule(y);
      });
      /* rekapitulace a úhrada */
      if (y + 170 > BOTTOM) { note(); doc.addPage(); y = 64; }
      var sx = R - 220; y += 22;
      font('Manrope', 9.5, MUT); doc.text('Základ daně 21 %', sx, y); font('Plex', 9.5, MUT); doc.text(m2(L.base), R, y, { align: 'right' }); y += LH + 2;
      font('Manrope', 9.5, MUT); doc.text('DPH 21 %', sx, y); font('Plex', 9.5, MUT); doc.text(m2(L.vat), R, y, { align: 'right' }); y += 10;
      rule(y, 1.4, INK, sx, R); y += 22;
      font('Manrope', 10.5, INK, 'bold'); doc.text(o.pay.paid ? 'Celkem uhrazeno' : 'Celkem k úhradě', sx, y);
      font('Unbounded', 15, INK, 'bold'); doc.text(m2(L.total).replace(',00', ''), R, y, { align: 'right' });
      /* platební údaje vlevo */
      var by = y - 40; doc.setDrawColor.apply(doc, INK); doc.setLineWidth(.8); doc.rect(Lx, by - 14, sx - Lx - 30, 64);
      if (o.pay.paid) { lab('Stav platby', Lx + 12, by + 2); font('Manrope', 10, INK, 'bold'); doc.text('Uhrazeno – ' + o.pay.label.toLowerCase(), Lx + 12, by + 18); font('Manrope', 8.5, MUT); doc.text('Částku prosím neposílejte znovu.', Lx + 12, by + 33); }
      else { lab('Platební údaje', Lx + 12, by + 2); font('Manrope', 9.5); doc.text('Číslo účtu: ', Lx + 12, by + 18); font('Plex', 9.5); doc.text(C.account, Lx + 72, by + 18); font('Manrope', 9.5); doc.text('Variabilní symbol: ', Lx + 12, by + 33); font('Plex', 9.5); doc.text(V.vs(o), Lx + 102, by + 33); }
      /* pata */
      y += 36; rule(y); y += 16; font('Manrope', 8, MUT);
      para(C.register + ' Zboží zůstává až do úplného zaplacení majetkem prodávajícího. Na zboží se vztahuje záruka 24 měsíců a oprava švů a zipů zdarma po dobu 2 let.', Lx, y, CW);
      note();
      doc.save('faktura-' + inv.no + '.pdf');
      return doc;
    });
  };

  /* ---------- e-maily ---------- */
  V.brand = { name: 'VRSTVA', sub: 'oblečení' };
  V.foot = function (D) { var C = D.settings.company; return esc(C.name) + ' · ' + esc(C.street) + ', ' + esc(C.zip + ' ' + C.city) + ' · ' + esc(C.phone) + ' · ' + esc(C.email); };
  V.itemsHTML = function (o, withTotals) {
    var rows = o.items.map(function (i) {
      return '<tr><td><b>' + esc(i.n) + '</b><span>' + esc(V.cname(i.c)) + ' · ' + esc(i.s) + ' · ' + i.q + ' ks</span></td><td>' + money(i.price * i.q) + (i.old ? '<s>' + money(i.old * i.q) + '</s>' : '') + '</td></tr>';
    }).join('');
    if (withTotals) {
      var T = o.totals;
      rows += (T.disc ? '<tr class="sm"><td>Sleva (kód ' + esc(o.code.code) + ')</td><td>−' + money(T.disc) + '</td></tr>' : '') +
        '<tr class="sm"><td>' + esc(o.ship.name) + '</td><td>' + (T.ship ? money(T.ship) : 'zdarma') + '</td></tr>' + (T.fee ? '<tr class="sm"><td>Příplatek za dobírku</td><td>' + money(T.fee) + '</td></tr>' : '') +
        '<tr class="tot"><td>Celkem vč. DPH</td><td>' + money(T.total) + '</td></tr>';
    }
    return '<table class="vr-em-items">' + rows + '</table>';
  };
  function payBox(D, o) {
    var C = D.settings.company;
    return '<div class="k-em-note"><b>Platební údaje</b><br>Číslo účtu: <b>' + esc(C.account) + '</b><br>Částka: <b>' + money(o.totals.total) + '</b><br>Variabilní symbol: <b>' + V.vs(o) + '</b><br>Splatnost: ' + TS.fmtDMY(TS.addDays(TS.iso(new Date(o.createdAt)), TS.num(D.settings.pay.transfer.due) || 5)) + '. Objednávku odešleme hned, jak platba dorazí.</div>';
  }
  /* oslovení v 5. pádě (Jano, Tomáši, Petře) – běžná česká jména, jinak beze změny */
  V.voc = function (full) {
    var n = String(full || '').trim().split(/\s+/)[0] || '', l = n.toLowerCase();
    if (!n) return '';
    if (/[aá]$/.test(l)) return n.slice(0, -1) + 'o';
    if (/(ek)$/.test(l)) return n.slice(0, -2) + 'ku';
    if (/(el)$/.test(l)) return n.slice(0, -2) + 'le';
    if (/[šžčřjcx]$/.test(l) || /ej$/.test(l)) return n + 'i';
    if (/tr$/.test(l)) return n.slice(0, -1) + 'ře';
    if (/k$/.test(l)) return n + 'u';
    if (/[bdfglmnpstvz]$/.test(l)) return n + 'e';
    return n;
  };
  function first(o) { return esc(V.voc(o.customer.name)); }
  V.mail = function (D, kind, o, x) {
    x = x || {};
    var C = D.settings.company, track = TS.mail.btn('Sledovat objednávku', V.trackUrl(o), true), subject, html, box = 'zakaznik', to = o.customer.email;
    var msg = x.msg ? '<p class="k-em-quote">' + esc(x.msg) + '</p>' : '';
    var shipTxt = o.ship.id === 'osob' ? 'Osobní odběr: ' + C.street + ', ' + C.city : V.shipText(o) + (o.delivery || o.ship.id === 'ppl' ? '' : '');
    var addr = o.ship.id === 'ppl' ? V.addrText(o.delivery || o.customer) : '';
    var inv = o.invoice ? TS.mail.attach('faktura-' + o.invoice.no + '.pdf', 'Faktura – daňový doklad · ' + money(o.totals.total), TS.mail.btn('Stáhnout', V.trackUrl(o))) : '';
    if (kind === 'new_order') {
      box = 'eshop'; to = D.settings.notify;
      subject = 'Nová objednávka ' + o.no + ' · ' + money(o.totals.total);
      html = '<h2>Nová objednávka z webu</h2><p>' + (o.pay.paid ? 'Zaplaceno kartou, můžete začít balit.' : o.pay.method === 'cod' ? (o.ship.id === 'osob' ? 'Zákazník zaplatí při vyzvednutí, můžete začít připravovat.' : 'Na dobírku, můžete začít balit.') : 'Čeká na platbu převodem. Až peníze dorazí, označte ji jako zaplacenou.') + '</p>' +
        V.itemsHTML(o, true) + TS.mail.table([['Zákazník', esc(o.customer.name)], ['E-mail', esc(o.customer.email)], ['Telefon', esc(o.customer.phone)], ['Doprava', esc(V.shipText(o))], addr ? ['Adresa', esc(addr)] : null, ['Platba', esc(o.pay.label)], o.code ? ['Slevový kód', esc(o.code.code)] : null, o.note ? ['Poznámka', esc(o.note)] : null]) +
        '<div class="k-em-btns">' + TS.mail.btn('Otevřít v administraci', 'vrstva-sprava.html#objednavky/' + o.id, true) + '</div>';
    } else if (kind === 'received') {
      subject = 'Objednávka ' + o.no + ' je přijatá';
      html = '<h2>Děkujeme, ' + first(o) + '</h2><p>Objednávku <b>' + esc(o.no) + '</b> máme. ' +
        (o.pay.paid ? 'Platba kartou proběhla v pořádku. Zboží začneme hned balit, objednávky do ' + D.settings.cutoff + ':00 odesíláme týž pracovní den.' :
          o.pay.method === 'transfer' ? 'Zboží vám rezervujeme a odešleme hned, jak dorazí platba.' :
            o.ship.id === 'osob' ? 'Až bude připravená k vyzvednutí, napíšeme vám. Zaplatíte na prodejně hotově nebo kartou.' : 'Zaplatíte při převzetí. Až zásilku předáme dopravci, pošleme číslo pro sledování.') + '</p>' +
        (o.pay.method === 'transfer' && !o.pay.paid ? payBox(D, o) : '') + V.itemsHTML(o, true) +
        TS.mail.table([['Doprava', esc(shipTxt)], addr ? ['Adresa', esc(addr)] : null, ['Platba', esc(o.pay.label) + (o.pay.paid ? ' · zaplaceno' : '')]]) +
        '<div class="k-em-btns">' + track + '</div><p>Stav objednávky, fakturu i formulář pro vrácení najdete na stránce Moje objednávky. Stačí číslo objednávky a tento e-mail.</p>';
    } else if (kind === 'paid') {
      subject = 'Platbu jsme přijali – ' + o.no;
      html = '<h2>Platba dorazila</h2><p>Dobrý den, ' + first(o) + ', děkujeme. Platbu ' + money(o.totals.total) + ' za objednávku ' + esc(o.no) + ' jsme přijali a zboží balíme. Číslo zásilky pošleme, jakmile ji předáme dopravci.</p>' + msg + '<div class="k-em-btns">' + track + '</div>';
    } else if (kind === 'packing') {
      subject = 'Objednávku ' + o.no + ' připravujeme';
      html = '<h2>Balíme vaši objednávku</h2><p>Dobrý den, ' + first(o) + ', objednávku ' + esc(o.no) + ' právě připravujeme v dílně v Náchodě. Další zprávu dostanete, až ji předáme ' + (o.ship.id === 'osob' ? 'na prodejnu k vyzvednutí.' : 'dopravci.') + '</p>' + msg + '<div class="k-em-btns">' + track + '</div>';
    } else if (kind === 'shipped') {
      var dl = TS.addDays(x.at ? TS.iso(new Date(x.at)) : TS.today(), 1); while (TS.wd(dl) === 0 || TS.wd(dl) === 6) dl = TS.addDays(dl, 1);
      subject = 'Objednávka ' + o.no + ' je na cestě';
      html = '<h2>Zásilka je na cestě</h2><p>Dobrý den, ' + first(o) + ', objednávku ' + esc(o.no) + ' jsme předali dopravci ' + esc(V.carrier(o.ship.id)) + '. Doručení očekáváme ' + esc(TS.fmtLong(dl)) + '.</p>' + msg +
        TS.mail.table([['Dopravce', esc(V.carrier(o.ship.id))], ['Číslo zásilky', '<span style="font-family:monospace">' + esc(o.tracking) + '</span>'], ['Doručení', esc(o.ship.point || addr || o.ship.name)], o.pay.method === 'cod' && !o.pay.paid ? ['K úhradě při převzetí', money(o.totals.total)] : null]) +
        '<div class="k-em-btns">' + TS.mail.btn('Sledovat zásilku', V.trackUrl(o), true) + '</div>' + (x.withInvoice !== false ? inv : '');
    } else if (kind === 'ready') {
      subject = 'Objednávka ' + o.no + ' je připravená k vyzvednutí';
      html = '<h2>Můžete si ji vyzvednout</h2><p>Dobrý den, ' + first(o) + ', objednávka ' + esc(o.no) + ' na vás čeká v dílně a prodejně na adrese ' + esc(C.street + ', ' + C.zip + ' ' + C.city) + '. Otevřeno máme ' + esc(C.hours) + '. Uložíme ji 7 dní.</p>' + msg +
        TS.mail.table([['Kde', esc(C.street + ', ' + C.city)], ['Otevřeno', esc(C.hours)], ['Platba', o.pay.paid ? 'zaplaceno' : 'při vyzvednutí ' + money(o.totals.total) + ', hotově nebo kartou']]) + '<div class="k-em-btns">' + track + '</div>' + inv;
    } else if (kind === 'delivered') {
      subject = 'Jak vám sedí? Objednávka ' + o.no;
      html = '<h2>' + (o.ship.id === 'osob' ? 'Děkujeme za návštěvu' : 'Objednávka je doručená') + '</h2><p>Dobrý den, ' + first(o) + ', doufáme, že vám nové kusy sedí. Budeme moc rádi za krátké hodnocení, pomůže dalším zákazníkům s výběrem velikosti a nám s dalšími sériemi.</p>' + msg +
        '<div class="k-em-btns">' + TS.mail.btn('Ohodnotit nákup', V.trackUrl(o) + '#hodnoceni', true) + '</div><p>Nesedí velikost? Máte 30 dní na bezplatnou výměnu nebo vrácení. A na švy a zipy platí 2 roky oprav zdarma.</p>';
    } else if (kind === 'cancelled') {
      subject = 'Objednávka ' + o.no + ' je zrušená';
      html = '<h2>Objednávku jsme zrušili</h2><p>Dobrý den, ' + first(o) + ', objednávka ' + esc(o.no) + ' je ' + (x.byCustomer ? 'na vaši žádost ' : '') + 'zrušená.' + (o.cancelReason && !x.byCustomer ? ' Důvod: ' + esc(o.cancelReason) + '.' : '') + '</p>' + msg +
        (o.pay.paid ? '<p>Zaplacenou částku <b>' + money(o.totals.total) + '</b> vám vrátíme ' + (o.pay.method === 'card' ? 'na kartu' : 'na účet, ze kterého přišla') + ' do 5 pracovních dnů.</p>' : '') +
        '<div class="k-em-btns">' + TS.mail.btn('Pokračovat v nákupu', 'vrstva-obchod.html', true) + '</div>';
    } else if (kind === 'cancel_staff') {
      box = 'eshop'; to = D.settings.notify;
      subject = 'Zákazník zrušil objednávku ' + o.no;
      html = '<h2>Objednávka zrušená zákazníkem</h2><p>' + esc(o.customer.name) + ' zrušil(a) objednávku ' + esc(o.no) + ' na stránce Moje objednávky. Kusy jsou zpátky na skladě.</p>' + V.itemsHTML(o, true) +
        '<div class="k-em-btns">' + TS.mail.btn('Otevřít v administraci', 'vrstva-sprava.html#objednavky/' + o.id, true) + '</div>';
    } else if (kind === 'invoice') {
      subject = 'Faktura ' + o.invoice.no + ' k objednávce ' + o.no;
      html = '<h2>Posíláme fakturu</h2><p>Dobrý den, ' + first(o) + ', v příloze je faktura k objednávce ' + esc(o.no) + '. Najdete ji kdykoli i na stránce Moje objednávky.</p>' + msg + inv +
        (o.pay.paid ? '' : payBox(D, o));
    }
    if (!html || !to) return null;
    return TS.mail.add(D, { box: box, to: to, from: 'VRSTVA oblečení <' + C.email + '>', subject: subject, html: TS.mail.wrap(V.brand, html, V.foot(D)), ref: o.id, at: x.at || Date.now() });
  };
  V.lowMail = function (D, low, o, at) {
    var C = D.settings.company;
    return TS.mail.add(D, { box: 'eshop', to: D.settings.notify, from: 'E-shop VRSTVA <' + C.email + '>', at: at || Date.now() + 1000,
      subject: 'Dochází zboží: ' + low.map(function (l) { return l.p.n + ' ' + l.s; }).join(', '),
      html: TS.mail.wrap(V.brand, '<h2>Dochází zásoba</h2><p>Po objednávce ' + esc(o ? o.no : '') + ' kleslo na hranici „dochází“ (' + D.settings.lowStock + ' ks a méně):</p>' +
        TS.mail.table(low.map(function (l) { return [esc(l.p.n), esc(V.cname(l.c)) + ' · ' + esc(l.s) + ' · <b>' + (l.n ? 'zbývá ' + l.n + ' ks' : 'vyprodáno') + '</b>']; })) +
        '<div class="k-em-btns">' + TS.mail.btn('Naskladnit', 'vrstva-sprava.html#sklad', true) + '</div>', V.foot(D)) });
  };

  /* ---------- vrácení, výměny a reklamace ---------- */
  V.RTYPE = { return: 'Vrácení', exchange: 'Výměna', claim: 'Reklamace', repair: 'Oprava zdarma' };
  V.RSTATUS = { new: 'Nová', accepted: 'Čekáme na zásilku', received: 'Zboží přijato', done: 'Vyřízeno', rejected: 'Zamítnuto' };
  V.rCls = { new: 'warn', accepted: 'pending', received: 'accent', done: 'done', rejected: 'bad' };
  V.retById = function (D, id) { return D.returns.find(function (r) { return r.id === id; }) || null; };
  V.addReturn = function (D, f) {
    var r = Object.assign({ id: TS.uid('r'), no: 'R-' + String(D.settings.series.returnNext || 61).padStart(4, '0'), createdAt: Date.now(), status: 'new', log: [] }, f);
    D.settings.series.returnNext = (D.settings.series.returnNext || 61) + 1;
    r.log.push({ at: Date.now(), t: 'Žádost z webu' });
    D.returns.unshift(r);
    V.rmail(D, 'r_new', r); V.rmail(D, 'r_received', r);
    return r;
  };
  V.retItems = function (r) { return '<table class="vr-em-items">' + r.items.map(function (i) { return '<tr><td><b>' + esc(i.n) + '</b><span>' + esc(V.cname(i.c)) + ' · ' + esc(i.s) + ' · ' + i.q + ' ks' + (i.to ? ' → vyměnit za ' + esc(i.to) : '') + '</span></td><td>' + (i.price ? money(i.price * i.q) : '') + '</td></tr>'; }).join('') + '</table>'; };
  V.rmail = function (D, kind, r, x) {
    x = x || {};
    var C = D.settings.company, subject, html, box = 'zakaznik', to = r.email, T = V.RTYPE[r.type].toLowerCase(), msg = x.msg ? '<p class="k-em-quote">' + esc(x.msg) + '</p>' : '';
    var nm = esc(V.voc(r.name));
    var send = '<div class="k-em-note"><b>Kam zboží poslat</b><br>VRSTVA s.r.o., ' + esc(C.street) + ', ' + esc(C.zip + ' ' + C.city) + '<br>Zásilku pošlete zdarma přes Zásilkovnu s kódem <b>' + esc(r.no.replace('R-', 'VR')) + '</b> na kterémkoli výdejním místě, nebo ji přineste osobně.</div>';
    if (kind === 'r_new') {
      box = 'eshop'; to = D.settings.notify;
      subject = 'Nová žádost: ' + V.RTYPE[r.type] + ' ' + r.no + (r.orderNo ? ' (' + r.orderNo + ')' : '');
      html = '<h2>' + V.RTYPE[r.type] + ' – nová žádost z webu</h2>' + (r.items.length ? V.retItems(r) : '') +
        TS.mail.table([['Zákazník', esc(r.name) + ', ' + esc(r.email)], r.orderNo ? ['Objednávka', esc(r.orderNo)] : null, r.product ? ['Výrobek', esc(r.product)] : null, ['Důvod', esc(r.reason || '')], r.text ? ['Popis', esc(r.text)] : null, r.account ? ['Účet pro vrácení', esc(r.account)] : null, r.want ? ['Požaduje', esc(r.want)] : null]) +
        '<div class="k-em-btns">' + TS.mail.btn('Otevřít v administraci', 'vrstva-sprava.html#vraceni/' + r.id, true) + '</div>';
    } else if (kind === 'r_received') {
      subject = 'Žádost ' + r.no + ' máme – ' + T;
      html = '<h2>Žádost máme, ' + nm + '</h2><p>Děkujeme. Žádost o ' + (r.type === 'return' ? 'vrácení' : r.type === 'exchange' ? 'výměnu' : r.type === 'claim' ? 'reklamaci' : 'opravu') + ' (č. ' + esc(r.no) + ') jsme přijali a do 2 pracovních dnů se ozveme s dalším postupem.</p>' + (r.items.length ? V.retItems(r) : '') +
        (r.type === 'return' || r.type === 'exchange' ? send : '') + '<p>Na dotazy odpovídáme na ' + esc(C.email) + ' nebo ' + esc(C.phone) + '.</p>';
    } else if (kind === 'r_accepted') {
      subject = V.RTYPE[r.type] + ' ' + r.no + ': pošlete nám zboží';
      html = '<h2>Žádost schválená</h2><p>Dobrý den, ' + nm + ', ' + (r.type === 'repair' ? 'opravu rádi uděláme zdarma.' : r.type === 'claim' ? 'reklamaci přijímáme k posouzení.' : 'žádost jsme schválili.') + ' Pošlete nám prosím zboží, po doručení ho vyřídíme do 5 pracovních dnů.</p>' + msg + send;
    } else if (kind === 'r_got') {
      subject = 'Zboží k žádosti ' + r.no + ' dorazilo';
      html = '<h2>Zásilka dorazila</h2><p>Dobrý den, ' + nm + ', zboží k žádosti ' + esc(r.no) + ' je u nás v dílně. ' + (r.type === 'exchange' ? 'Novou velikost posíláme obratem.' : r.type === 'return' ? 'Peníze pošleme do 5 pracovních dnů.' : 'Švadleny se na něj podívají a dáme vědět.') + '</p>' + msg;
    } else if (kind === 'r_done') {
      subject = V.RTYPE[r.type] + ' ' + r.no + ' je vyřízená';
      html = '<h2>Hotovo</h2><p>Dobrý den, ' + nm + ', žádost ' + esc(r.no) + ' je vyřízená. ' + (r.type === 'return' ? 'Částku <b>' + money(r.refund || 0) + '</b> jsme poslali ' + (r.account ? 'na účet ' + esc(r.account) : 'zpět stejnou cestou, jakou jste platili') + '.' : r.type === 'exchange' ? 'Vyměněné kusy jsou na cestě' + (r.tracking ? ', číslo zásilky ' + esc(r.tracking) : '') + '.' : r.type === 'repair' ? 'Opravený kus vám posíláme zpátky zdarma.' : 'Reklamaci uznáváme.') + '</p>' + msg;
    } else if (kind === 'r_rejected') {
      subject = V.RTYPE[r.type] + ' ' + r.no + ': nemůžeme vyhovět';
      html = '<h2>Žádosti bohužel nemůžeme vyhovět</h2><p>Dobrý den, ' + nm + ', žádost ' + esc(r.no) + ' jsme posoudili a bohužel jí nemůžeme vyhovět.</p>' + msg + '<p>Pokud s rozhodnutím nesouhlasíte, odpovězte prosím na tento e-mail nebo zavolejte na ' + esc(C.phone) + '.</p>';
    }
    if (!html || !to) return null;
    return TS.mail.add(D, { box: box, to: to, from: 'VRSTVA oblečení <' + C.email + '>', subject: subject, html: TS.mail.wrap(V.brand, html, V.foot(D)), ref: r.id, at: x.at || Date.now() });
  };

  /* ---------- hlídací pes: „dejte mi vědět, až bude skladem“ ---------- */
  V.addWatch = function (D, w) {
    D.watch = D.watch || [];
    var email = String(w.email).trim().toLowerCase(), p = V.prod(D, w.pid);
    if (D.watch.some(function (x) { return !x.done && x.pid === w.pid && x.c === w.c && x.s === w.s && x.email === email; })) return { again: true };
    var x = { id: TS.uid('w'), pid: w.pid, c: w.c, s: w.s, email: email, at: Date.now(), done: 0 };
    D.watch.unshift(x);
    var C = D.settings.company;
    TS.mail.add(D, { box: 'zakaznik', to: email, from: 'VRSTVA oblečení <' + C.email + '>', subject: 'Ohlídáme vám: ' + p.n + ', ' + V.cname(w.c).toLowerCase() + ', ' + w.s, ref: x.id,
      html: TS.mail.wrap(V.brand, '<h2>Dáme vědět, až to ušijeme</h2><p>Dobrý den, velikost <b>' + esc(w.s) + '</b> v barvě ' + esc(V.cname(w.c).toLowerCase()) + ' je teď vyprodaná. Jakmile ji naskladníme, pošleme vám jediný e-mail s odkazem. Nic dalšího vám chodit nebude.</p>' +
        TS.mail.table([['Výrobek', esc(p.n)], ['Barva a velikost', esc(V.cname(w.c)) + ' · ' + esc(w.s)], ['Cena', money(V.priceInfo(p).now)]]) +
        '<div class="k-em-btns">' + TS.mail.btn('Prohlédnout výrobek', 'vrstva-produkt.html?id=' + p.id + '&barva=' + w.c, true) + '</div>', V.foot(D)) });
    return { w: x };
  };
  V.watchers = function (D, pid, c, s) { return (D.watch || []).filter(function (x) { return !x.done && x.pid === pid && (c == null || x.c === c) && (s == null || x.s === s); }); };
  /* po naskladnění: zákazníkům, kteří čekají, odejde e-mail „zpět skladem“; vrací počet */
  V.checkWatch = function (D) {
    var n = 0, C = D.settings.company;
    (D.watch || []).forEach(function (x) {
      if (x.done) return; var p = V.prod(D, x.pid); if (!p || p.hidden || !V.st(p, x.c, x.s)) return;
      x.done = Date.now(); n++;
      TS.mail.add(D, { box: 'zakaznik', to: x.email, from: 'VRSTVA oblečení <' + C.email + '>', subject: 'Zpátky skladem: ' + p.n + ', ' + x.s, ref: x.id,
        html: TS.mail.wrap(V.brand, '<h2>Je to zpátky</h2><p>Dobrý den, ' + esc(p.n) + ' v barvě ' + esc(V.cname(x.c).toLowerCase()) + ' ve velikosti <b>' + esc(x.s) + '</b> je znovu skladem. Série bývají malé, tak ať vám neuteče.</p>' +
          TS.mail.table([['Výrobek', esc(p.n)], ['Barva a velikost', esc(V.cname(x.c)) + ' · ' + esc(x.s)], ['Skladem', V.st(p, x.c, x.s) + ' ks'], ['Cena', money(V.priceInfo(p).now)]]) +
          '<div class="k-em-btns">' + TS.mail.btn('Koupit ' + x.s, 'vrstva-produkt.html?id=' + p.id + '&barva=' + x.c, true) + '</div><p style="font-size:13px;color:#76767E">Tento e-mail jsme poslali jednou, protože jste o něj požádali na stránce výrobku.</p>', V.foot(D)) });
    });
    return n;
  };
  /* zákaznický účet (ukázkový): uložená adresa pro rychlejší pokladnu */
  V.account = function (D, email) {
    email = String(email || '').toLowerCase(); D.accounts = D.accounts || {};
    if (!D.accounts[email]) {
      var o = D.orders.filter(function (x) { return x.customer.email.toLowerCase() === email; }).sort(function (a, b) { return b.createdAt - a.createdAt; })[0], c = o ? o.customer : {};
      D.accounts[email] = { name: c.name || '', phone: c.phone || '', street: c.street || '', city: c.city || '', zip: c.zip || '', news: false };
    }
    return D.accounts[email];
  };

  /* ---------- newsletter ---------- */
  V.subscribe = function (D, email, silent) {
    email = String(email).trim().toLowerCase();
    var ex = D.subscribers.find(function (s) { return s.email === email; });
    if (ex) return { code: ex.code, again: true };
    var T = TS.today(), code = V.genCode('VITEJ', 6);
    D.codes.unshift({ id: TS.uid('k'), code: code, type: 'pct', value: 10, min: 0, from: T, to: TS.addDays(T, 30), limit: 1, once: true, used: 0, active: true, note: 'Newsletter: ' + email, batch: 'Newsletter – uvítací kódy', createdAt: Date.now() });
    D.subscribers.unshift({ email: email, at: Date.now(), code: code });
    var C = D.settings.company;
    TS.mail.add(D, { box: 'zakaznik', to: email, from: 'VRSTVA oblečení <' + C.email + '>', subject: 'Vítejte ve VRSTVĚ – 10 % na první nákup',
      html: TS.mail.wrap(V.brand, '<h2>Vítejte mezi námi</h2><p>Děkujeme za přihlášení. Jednou měsíčně vám napíšeme, co nového šijeme a kdy přijde další série.</p>' +
        '<div class="k-em-note" style="text-align:center">Váš kód na 10 % slevu<br><b style="font-size:22px;letter-spacing:.08em;font-family:monospace">' + code + '</b><br><span style="font-size:13px">Platí 30 dní, jednou, na celý nákup.</span></div>' +
        '<div class="k-em-btns">' + TS.mail.btn('Vybrat si první vrstvu', 'vrstva-obchod.html', true) + '</div><p style="font-size:13px;color:#76767E">Odhlásit se můžete jedním klikem v patičce každého e-mailu.</p>', V.foot(D)) });
    return { code: code };
  };

  /* ---------- rozpad ceny (stránka Jak šijeme) ---------- */
  V.PARTS = {
    p1: [['Látka, nitě a žebrování', 380, '#111214'], ['Šití v Náchodě', 520, '#2340F5'], ['Výšivka, štítky, balení', 90, '#6B7CF8'], ['Doprava a sklad', 110, '#A7B2FB'], ['DPH 21 %', 328, '#A3A5A8'], ['Naše marže (vývoj, provoz, opravy)', 462, '#D3D3CE']],
    p3: [['Látka a nitě', 120, '#111214'], ['Šití v Náchodě', 160, '#2340F5'], ['Štítky a balení', 30, '#6B7CF8'], ['Doprava a sklad', 40, '#A7B2FB'], ['DPH 21 %', 120, '#A3A5A8'], ['Naše marže (vývoj, provoz, opravy)', 220, '#D3D3CE']],
    p6: [['Kepr, podšívka a nitě', 780, '#111214'], ['Šití v Náchodě', 960, '#2340F5'], ['Knoflíky, štítky, balení', 210, '#6B7CF8'], ['Doprava a sklad', 160, '#A7B2FB'], ['DPH 21 %', 606, '#A3A5A8'], ['Naše marže (vývoj, provoz, opravy)', 774, '#D3D3CE']]
  };
  V.SHEETS = {
    p1: [['OSN-04-PO', 'Mikina OSNOVA'], ['Materiál', '100 % bio bavlna, certifikát GOTS'], ['Gramáž', '400 g/m², počesaný rub'], ['Úplet', 'Teplákovina, pleteno v Rakousku'], ['Šití', 'Náchod, CZ · 38 minut'], ['Série', '300 kusů v jedné barvě'], ['Péče', 'Prát naruby na 30 °C, nesušit v sušičce']],
    p3: [['ZAK-04-EC', 'Tričko ZÁKLAD'], ['Materiál', '100 % bio bavlna, certifikát GOTS'], ['Gramáž', '180 g/m², single jersey'], ['Úplet', 'Pleteno v Portugalsku'], ['Šití', 'Náchod, CZ · 14 minut'], ['Série', '500 kusů v jedné barvě'], ['Péče', 'Prát na 30 °C s podobnými barvami']],
    p6: [['DIL-04-NA', 'Bunda DÍLNA'], ['Materiál', '100 % bavlněný kepr'], ['Gramáž', '320 g/m², tkáno v Itálii'], ['Kování', 'Mosazné knoflíky, Jablonec n. N.'], ['Šití', 'Náchod, CZ · 2 h 40 min'], ['Série', '120 kusů v jedné barvě'], ['Péče', 'Prát naruby na 30 °C, sušit volně']]
  };

  /* ---------- ukázkové objednávky relativně k dnešku ---------- */
  function demoOrders(D) {
    var T = TS.today(), S = D.settings, out = [];
    /* [dny zpět, hodina, zákazník, položky [id, barva, velikost, ks], doprava, platba, stav, kód] */
    var plan = [
      [58, 19.2, 0, [['p1', 'cerna', 'S', 1], ['p12', 'cihla', '36–39', 1]], 'zas', 'card', 'delivered'],
      [35, 10.5, 2, [['p7', 'cerna', 'L', 1]], 'ppl', 'card', 'delivered'],
      [27, 21.1, 11, [['p8', 'namor', 'M', 1], ['p3', 'namor', 'M', 1]], 'zas', 'card', 'delivered'],
      [25, 13.4, 9, [['p4', 'cerna', 'L', 2]], 'zas', 'card', 'delivered'],
      [22, 9.3, 5, [['p1', 'popel', 'L', 1], ['p9', 'popel', 'L', 1]], 'ppl', 'transfer', 'delivered'],
      [19, 20.7, 0, [['p6', 'cerna', 'S', 1]], 'zas', 'card', 'delivered'],
      [16, 7.9, 8, [['p11', 'svest', 'UNI', 2]], 'zas', 'card', 'delivered'],
      [13, 18.2, 6, [['p2', 'namor', 'M', 1]], 'bal', 'card', 'delivered'],
      [11, 12.6, 4, [['p3', 'okr', 'M', 1], ['p12', 'cerna', '40–43', 2]], 'zas', 'card', 'delivered'],
      [8, 16.4, 1, [['p9', 'cerna', 'M', 1]], 'osob', 'transfer', 'cancelled'],
      [6, 22.3, 3, [['p1', 'cihla', 'XL', 1]], 'zas', 'card', 'delivered'],
      [5, 11.8, 11, [['p6', 'okr', 'XL', 1], ['p8', 'cerna', 'XL', 1]], 'ppl', 'cod', 'delivered', 'PODZIM300'],
      [4, 14.1, 10, [['p5', 'ecru', 'S', 2]], 'bal', 'card', 'delivered', 'DOPRAVAZDARMA'],
      [3, 9.6, 9, [['p1', 'lahev', 'L', 1], ['p10', 'cerna', 'UNI', 1]], 'zas', 'card', 'delivered'],
      [2, 10.3, 8, [['p7', 'lahev', 'M', 1]], 'ppl', 'transfer', 'shipped'],
      [2, 18.8, 0, [['p2', 'svest', 'S', 1], ['p3', 'ecru', 'S', 1]], 'zas', 'card', 'shipped'],
      [1, 11.0, 7, [['p8', 'okr', 'L', 1]], 'osob', 'card', 'ready'],
      [1, 15.2, 2, [['p4', 'lahev', 'M', 1], ['p9', 'popel', 'S', 1]], 'zas', 'card', 'packing', 'VRSTVA10'],
      [1, 20.5, 6, [['p11', 'okr', 'UNI', 1], ['p12', 'ecru', '36–39', 1]], 'bal', 'cod', 'new'],
      [0, 8.25, 5, [['p6', 'namor', 'L', 1], ['p3', 'cerna', 'L', 2]], 'ppl', 'transfer', 'new'],
      [0, 9.7, 4, [['p1', 'cerna', 'M', 1]], 'zas', 'card', 'paid']];
    var nowH = new Date().getHours() + new Date().getMinutes() / 60;
    plan.forEach(function (x, i) {
      var p0 = x[0], hour = x[0] === 0 ? Math.min(x[1], Math.max(0.2, nowH - 0.3 - (20 - i) * 0.4)) : x[1];
      var created = TS.parse(TS.addDays(T, -p0)).getTime() - 12 * 36e5 + hour * 36e5;
      var c = CUST[x[2]], sm = V.shipOf(D, x[4]);
      var items = x[3].map(function (it) { var p = V.prod(D, it[0]), pi = V.priceInfo(p, TS.addDays(T, -p0)); return { pid: p.id, n: p.n, sh: p.sh, c: it[1], s: it[2], q: it[3], price: pi.now, old: pi.old }; });
      var lines = items.map(function (it) { return { price: it.price, old: it.old, q: it.q }; });
      var k = x[7] ? V.findCode(D, x[7]) : null, t = V.calc(D, lines, x[4], x[5], k);
      var o = { id: 'o' + (i + 1), no: S.series.order + String(S.series.orderNext - plan.length + i).padStart(5, '0'), createdAt: created, status: 'new', items: items,
        ship: { id: sm.id, name: sm.name, price: t.ship, point: x[4] === 'zas' ? V.POINTS.zas[i % 4] : x[4] === 'bal' ? V.POINTS.bal[i % 4] : '' },
        pay: { method: x[5], label: V.payLabel(x[5], x[4]), paid: false, fee: t.fee }, code: k ? { code: k.code, type: k.type, value: k.value, disc: t.disc } : null,
        totals: { sub: t.sub, disc: t.disc, ship: t.ship, fee: t.fee, total: t.total },
        customer: { name: c[0], email: c[1], phone: c[2], street: c[3], city: c[4], zip: c[5], company: '', ico: '', dic: '' }, delivery: null, note: i === 19 ? 'Prosím zabalit jako dárek, bez faktury v balíku.' : '',
        tracking: '', invoice: null, at: { created: created }, log: [{ at: created, t: 'Objednávka z webu' }], internal: '', account: x[2] === 0 };
      if (i === 19) { o.customer.company = 'Zeman design s.r.o.'; o.customer.ico = '12345670'; o.customer.dic = 'CZ12345670'; }
      var st = x[6], h = 36e5, step = created;
      /* kroky obsluhy jen v pracovní době dílny (8–18 h), nikdy v budoucnu */
      var work = function (ts) { var d = new Date(ts), hh = d.getHours() + d.getMinutes() / 60; if (hh < 8) d.setHours(8, 10 + (i * 7) % 40); else if (hh >= 18) { d.setDate(d.getDate() + 1); d.setHours(8, 10 + (i * 7) % 40); } return Math.min(d.getTime(), Date.now() - (25 - i) * 6e5); };
      var mark = function (k2, dt, txt) { step = Math.max(step + 6e5, work(step + dt)); o.at[k2] = step; o.log.push({ at: step, t: txt }); };
      if (x[5] === 'card') { o.pay.paid = true; o.at.paid = created; o.log.push({ at: created, t: 'Zaplaceno kartou online' }); o.status = 'paid'; }
      if (st === 'cancelled') { o.status = 'cancelled'; mark('cancelled', 5 * 24 * h, 'Stornováno: platba nedorazila do 5 dnů · kusy vráceny na sklad'); o.cancelReason = 'platba nedorazila do 5 dnů'; }
      else if (st !== 'new' && st !== 'paid') {
        if (x[5] === 'transfer') { mark('paid', 14 * h, 'Platba přijata na účet'); o.pay.paid = true; o.status = 'paid'; }
        mark('packing', (x[5] === 'card' ? 1.2 : .5) * h, 'Připravujeme k odeslání'); o.status = 'packing';
        if (st !== 'packing') {
          mark('shipped', 2.5 * h, x[4] === 'osob' ? 'Připraveno k vyzvednutí' : 'Odesláno');
          o.status = x[4] === 'osob' ? 'ready' : 'shipped';
          if (x[4] !== 'osob') { o.tracking = x[4] === 'zas' ? 'Z 48' + (i % 10) + ' 55' + (i % 7) + ' 30' + (10 + i) : x[4] === 'bal' ? 'DR0417 22' + (60 + i) + ' 5' + (i % 9) + 'U' : '4057 12' + (80 + i) + ' 3' + (10 + i); o.log[o.log.length - 1].t += ' (' + V.carrier(x[4]) + ', ' + o.tracking + ')'; }
          var sd = TS.iso(new Date(o.at.shipped));
          o.invoice = { no: '', issued: sd, taxDate: o.pay.paid ? TS.iso(new Date(o.at.paid)) : sd, due: TS.addDays(sd, 5) };
          if (st === 'delivered') { mark('delivered', (x[4] === 'osob' ? 26 : 22) * h, x[4] === 'osob' ? 'Vyzvednuto na prodejně' : 'Doručeno'); o.status = 'delivered'; if (!o.pay.paid) { o.pay.paid = true; o.at.paid = o.at.delivered; } }
        }
      }
      if (st === 'delivered' && (i === 2 || i === 5 || i === 8)) o.review = { stars: i === 2 ? 4 : 5, text: i === 5 ? 'Bunda sedí přesně podle tabulky, kepr je pevný a krásně měkne.' : i === 2 ? 'Kalhoty super, jen jsem musela vzít o číslo větší.' : 'Tričko neprosvítá a po praní drží tvar. Objednám další barvy.', at: o.at.delivered + 864e5 };
      out.push(o);
    });
    /* čísla faktur podle data vystavení */
    var withInv = out.filter(function (o) { return o.invoice; }).sort(function (a, b) { return a.at.shipped - b.at.shipped; });
    withInv.forEach(function (o, i) { o.invoice.no = S.series.invoice + String(S.series.invoiceNext - withInv.length + i).padStart(4, '0'); o.log.push({ at: o.at.shipped + 1000, t: 'Vystavena faktura ' + o.invoice.no }); o.log.sort(function (a, b) { return a.at - b.at; }); });
    return out.reverse();
  }
  function demoReturns(D) {
    var o11 = D.orders.find(function (o) { return o.id === 'o11'; }), o2 = D.orders.find(function (o) { return o.id === 'o2'; }), o1 = D.orders.find(function (o) { return o.id === 'o1'; });
    var it = function (o, j, extra) { var i = o.items[j]; return Object.assign({ n: i.n, c: i.c, s: i.s, q: i.q, price: i.price, pid: i.pid }, extra || {}); };
    var d = 864e5, T = TS.today(), at = function (daysBack, hour) { return Math.min(Date.now() - 36e5, TS.parse(TS.addDays(T, -daysBack)).getTime() - 12 * 36e5 + hour * 36e5); };
    return [
      { id: 'r3', no: 'R-0060', type: 'exchange', orderNo: o11.no, orderId: o11.id, name: o11.customer.name, email: o11.customer.email, items: [it(o11, 0, { to: 'XXL' })], reason: 'Malá velikost', text: 'Mikina je v ramenou trochu těsná, prosím o XXL ve stejné barvě.', status: 'new', createdAt: at(1, 21.6), log: [{ at: at(1, 21.6), t: 'Žádost z webu' }] },
      { id: 'r2', no: 'R-0059', type: 'repair', orderNo: o1.no, orderId: o1.id, name: o1.customer.name, email: o1.customer.email, items: [it(o1, 0)], reason: 'Povolený šev', text: 'U klokaní kapsy se párá šev, asi 3 cm. Mikinu nosím skoro denně.', want: 'Oprava', status: 'accepted', createdAt: at(4, 10.3), log: [{ at: at(4, 10.3), t: 'Žádost z webu' }, { at: at(4, 14.2), t: 'Schváleno, zákazník dostal pokyny k odeslání' }] },
      { id: 'r1', no: 'R-0058', type: 'return', orderNo: o2.no, orderId: o2.id, name: o2.customer.name, email: o2.customer.email, items: [it(o2, 0)], reason: 'Barva neodpovídá představě', text: '', account: '', refund: 4290, status: 'done', resolution: 'Vráceno 4 290 Kč na kartu.', createdAt: at(31, 19.4), log: [{ at: at(31, 19.4), t: 'Žádost z webu' }, { at: at(30, 9.1), t: 'Schváleno' }, { at: at(28, 11.5), t: 'Zboží přijato' }, { at: at(28, 15.2), t: 'Vyřízeno: vráceno 4 290 Kč na kartu' }] }];
  }

  V.defaults = function () {
    var D = { products: productDefaults(), settings: settingsDefaults(), codes: codeDefaults(), cart: [], wish: [], subscribers: [], emails: [], watch: [], accounts: {} };
    D.settings.series.returnNext = 61;
    D.hero = { eyebrow: 'Kolekce 04 · Podzim / zima 2026', lead: 'Mikiny, trička a bundy z bio bavlny, které šijeme v naší dílně v Náchodě. Nosíte je roky, a proto na každý kus dáváme dva roky opravy zdarma.', auto: true,
      items: [{ pid: 'p1', c: 'popel' }, { pid: 'p6', c: 'okr' }, { pid: 'p4', c: 'cihla' }] };
    D.orders = demoOrders(D);
    D.returns = demoReturns(D);
    D.moves = [];
    D.orders.slice().reverse().filter(function (o) { return o.createdAt > Date.now() - 6 * 864e5; }).forEach(function (o) {
      o.items.forEach(function (i) { D.moves.unshift({ at: o.createdAt, pid: i.pid, n: i.n, c: i.c, s: i.s, q: -i.q, why: 'Objednávka ' + o.no }); });
      if (o.status === 'cancelled') o.items.forEach(function (i) { D.moves.unshift({ at: o.at.cancelled, pid: i.pid, n: i.n, c: i.c, s: i.s, q: i.q, why: 'Storno ' + o.no }); });
    });
    D.moves.unshift({ at: Date.now() - 2.2 * 864e5, pid: 'p3', n: 'Tričko ZÁKLAD', c: 'cerna', s: 'M', q: 24, why: 'Naskladnění: nová série z dílny' });
    D.moves.unshift({ at: Date.now() - 1.1 * 864e5, pid: 'p5', n: 'Triko DLOUHÉ', c: 'ecru', s: 'L', q: -1, why: 'Odpis: vada látky' });
    D.moves.sort(function (a, b) { return b.at - a.at; });
    /* nízké zásoby pro ukázku */
    var p1 = V.prod(D, 'p1'); p1.stock['popel|XS'] = 1; p1.stock['cihla|M'] = 2; p1.stock['popel|M'] = 9;
    V.prod(D, 'p6').stock['okr|M'] = 1; V.prod(D, 'p3').stock['ecru|M'] = 2; V.prod(D, 'p9').stock['popel|M'] = 0;
    V.prod(D, 'p1').stock['cerna|XXL'] = 0; V.prod(D, 'p4').stock['lahev|XS'] = 0; V.prod(D, 'p7').stock['lahev|S'] = 1;
    /* zákazníci, kteří čekají na vyprodanou velikost */
    D.watch = [{ id: 'w1', pid: 'p9', c: 'popel', s: 'M', email: 'tereza.hajkova@example.com', at: Date.now() - 5 * 864e5, done: 0 },
      { id: 'w2', pid: 'p9', c: 'popel', s: 'M', email: 'adam.polak@example.com', at: Date.now() - 2 * 864e5, done: 0 },
      { id: 'w3', pid: 'p1', c: 'cerna', s: 'XXL', email: 'petr.zeman@example.com', at: Date.now() - 864e5, done: 0 }];
    /* e-maily, které by e-shop k posledním objednávkám už poslal */
    var t0 = Date.now() - 3 * 864e5;
    D.orders.slice().reverse().forEach(function (o) {
      if (o.createdAt < t0 && o.status !== 'shipped') return;
      V.mail(D, 'new_order', o, { at: o.createdAt + 1000 }); V.mail(D, 'received', o, { at: o.createdAt + 2000 });
      if (o.at.paid && o.pay.method === 'transfer') V.mail(D, 'paid', o, { at: o.at.paid + 1000 });
      if (o.status === 'shipped') V.mail(D, 'shipped', o, { at: o.at.shipped + 1000 });
      if (o.status === 'ready') V.mail(D, 'ready', o, { at: o.at.shipped + 1000 });
    });
    var lo = D.orders.find(function (o) { return o.id === 'o18'; });
    V.lowMail(D, [{ p: p1, c: 'cihla', s: 'M', n: 2 }], lo, lo.createdAt + 3000);
    var r3 = D.returns[0]; V.rmail(D, 'r_new', r3, { at: r3.createdAt }); V.rmail(D, 'r_received', r3, { at: r3.createdAt + 1000 });
    D.emails.sort(function (a, b) { return b.at - a.at; });
    D.emails.forEach(function (e, i) { e.read = i > 3; });
    return D;
  };
})();
