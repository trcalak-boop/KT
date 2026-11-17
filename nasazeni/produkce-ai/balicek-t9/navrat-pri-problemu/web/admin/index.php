<?php
/* ==========================================================================
   T&S administrace – adresa webu + /admin
   Tento soubor není potřeba upravovat.
   ========================================================================== */
require __DIR__ . '/lib.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
start_relace();
$rezim = !heslo_nastavene() ? 'instalace' : (prihlasen() ? 'editor' : 'prihlaseni');
if ($rezim === 'editor') { $_z = []; $_w = []; oznac_verzi_dat($_z, $_w); }   // stránky nahrané ručně dostanou aktuální verzi dat
// připravené podklady: složka reference/<id>/ se souborem projekt.json – nová zakázka,
// nebo aktualizace zakázky, která už na webu je (nový popis, galerie…)
$pripravene = [];
if ($rezim === 'editor') {
    $naWebu = reference_na_webu();
    foreach (glob(WEBROOT . '/reference/*/projekt.json') ?: [] as $pf) {
        $c = cisti_projekt(json_decode((string) @file_get_contents($pf), true), $_ch);
        if (!$c || $c['id'] !== basename(dirname($pf))) continue;
        if (isset($naWebu[$c['id']])) {
            if (!podklady_se_lisi($c, $naWebu[$c['id']])) { @unlink($pf); continue; }   // na webu už je přesně tohle
            $c['_aktualizace'] = true;
        }
        $pripravene[] = $c;
    }
}
$csrf = $_SESSION['csrf'];
// osobní účty (CRM): přihlášení pod jménem, v administraci víme, kdo co dělá
$uzivatele = crm_uzivatele();
$ja = ($rezim === 'editor' && crm_uid()) ? crm_uzivatel(crm_uid()) : null;
$https = je_https();
$verze = function ($f) { return @filemtime(WEBROOT . '/' . $f) ?: time(); };
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0B0B0C">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="T&amp;S Admin">
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="../apple-touch-icon.png">
<meta name="robots" content="noindex, nofollow">
<title>Administrace webu T&amp;S</title>
<link rel="icon" href="../favicon.ico?v=2" sizes="32x32">
<link rel="icon" href="../favicon.svg?v=2" type="image/svg+xml">
<link rel="stylesheet" href="../assets/fonts/hlavni.css">
<?php if ($rezim === 'editor'): ?><link rel="stylesheet" href="crm.css?v=<?= @filemtime(__DIR__ . '/crm.css') ?>"><?php endif; ?>
<style>
:root{--red:#E10600;--red-d:#B00500;--tint:#FDECEB;--ink:#0B0B0C;--ink3:#2A2A2D;--paper:#fff;--p2:#F6F4F4;--p3:#EFECEC;--line:#E2DEDE;--muted:#5F5A5A;--ok:#0F8A4B;--ok-t:#E3F4EA;--warn-t:#FFF3D1;
  --fd:"Archivo","Arial Black",Arial,sans-serif;--fb:"Hanken Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;--fm:"JetBrains Mono",ui-monospace,Menlo,monospace}
*,*::before,*::after{box-sizing:border-box}
[hidden]{display:none!important}
html,body{margin:0}
body{font:15px/1.5 var(--fb);color:var(--ink);background:var(--p2);-webkit-font-smoothing:antialiased}
h1,h2,h3{font-family:var(--fd);font-stretch:118%;letter-spacing:-.02em;line-height:1.1;margin:0}
button,input,textarea,select{font:inherit;color:inherit}
a{color:inherit}
:focus-visible{outline:3px solid var(--red);outline-offset:2px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border:1.5px solid var(--ink);background:var(--paper);border-radius:999px;padding:.55rem 1rem;font-weight:600;font-size:.9rem;cursor:pointer;text-decoration:none;white-space:nowrap;line-height:1.2}
.btn:hover{background:var(--ink);color:var(--paper)}
.btn.red{background:var(--red);border-color:var(--red);color:#fff}
.btn.red:hover{background:var(--red-d);border-color:var(--red-d)}
.btn.small{padding:.35rem .7rem;font-size:.82rem}
.btn.ghost{border-color:var(--line)}
.btn.ghost:hover{border-color:var(--ink)}
.btn.danger{border-color:#F1C7C5;color:var(--red-d)}
.btn.danger:hover{background:var(--red);border-color:var(--red);color:#fff}
.btn[disabled]{opacity:.45;pointer-events:none}
.ico-btn{width:32px;height:32px;display:inline-grid;place-items:center;border:1px solid var(--line);background:var(--paper);border-radius:8px;cursor:pointer;padding:0;font-size:1rem;line-height:1;flex:none}
.ico-btn:hover{border-color:var(--ink)}
.ico-btn[disabled]{opacity:.3;pointer-events:none}
.hint{color:var(--muted);font-size:.84rem;line-height:1.45}
.hint b{color:var(--ink)}
.toast{position:fixed;left:50%;bottom:1.2rem;transform:translateX(-50%);background:var(--ink);color:#fff;padding:.7rem 1rem;border-radius:10px;font-weight:600;font-size:.9rem;z-index:60;box-shadow:0 12px 30px -10px rgba(0,0,0,.4);max-width:92vw;text-align:center}
.toast.bad{background:var(--red-d)}

/* přihlášení */
.auth{min-height:100vh;display:grid;place-items:center;padding:1.2rem;background:var(--ink)}
.auth-card{width:min(440px,100%);background:#fff;border-radius:16px;padding:clamp(1.4rem,5vw,2.2rem);display:grid;gap:1rem}
.auth-logo{font:900 2rem/1 var(--fd);font-stretch:125%;letter-spacing:-.04em}
.auth-logo span{color:var(--red)}
.auth h1{font-size:1.5rem}
.auth label{display:block;font-weight:700;font-size:.86rem;margin-bottom:.35rem}
.auth input,.auth select{width:100%;border:1.5px solid var(--line);border-radius:8px;padding:.7rem .8rem;font-size:1rem;background:#fff}
.auth input:focus{outline:none;border-color:var(--ink);box-shadow:0 0 0 3px rgba(11,11,12,.08)}
.auth .btn{width:100%;padding:.8rem 1rem;font-size:1rem}
.msg{padding:.7rem .85rem;border-radius:8px;font-size:.88rem;line-height:1.45}
.msg.err{background:var(--tint);color:var(--red-d)}
.msg.warn{background:var(--warn-t);color:#5E4200}
.msg.ok{background:var(--ok-t);color:var(--ok)}

/* horní lišta */
.top{position:sticky;top:0;z-index:20;background:var(--ink);color:#fff}
.top-row{display:flex;align-items:center;gap:.8rem 1.4rem;flex-wrap:wrap;padding:.7rem 1.2rem}
.brand{display:flex;align-items:baseline;gap:.6rem;text-decoration:none}
.brand b{font:900 1.5rem/1 var(--fd);font-stretch:125%;letter-spacing:-.04em}
.brand b span{color:var(--red)}
.brand small{font:500 .68rem/1 var(--fm);letter-spacing:.12em;text-transform:uppercase;color:#A29D9D}
.tabs{display:flex;gap:.2rem;flex-wrap:wrap}
.tabs button{background:transparent;border:0;color:#CFCACA;font-weight:600;padding:.5rem .75rem;border-radius:8px;cursor:pointer}
.tabs button:hover{color:#fff;background:#1F1F22}
.tabs button[aria-selected="true"]{background:#fff;color:var(--ink)}
.save{margin-left:auto;display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.status{font-size:.85rem;color:#A29D9D;display:flex;align-items:center;gap:.45rem}
.status i{width:9px;height:9px;border-radius:50%;background:var(--ok)}
.status.dirty{color:#FFD9D6}.status.dirty i{background:var(--red)}
.top .btn{border-color:#3A3A3F;background:transparent;color:#fff}
.top .btn:hover{background:#fff;color:var(--ink);border-color:#fff}
.top .btn.red{background:var(--red);border-color:var(--red)}
.top .btn.red:hover{background:#fff;color:var(--red-d);border-color:#fff}
@media (max-width:700px){
  .top-row{gap:.5rem .8rem;padding:.55rem .8rem}
  .brand small{display:none}
  .tabs{order:3;width:100%;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}
  .tabs button{white-space:nowrap}
  .save{gap:.4rem}
  .status span{display:none}
  .top .btn{padding:.45rem .8rem}
}
.banner{display:flex;align-items:center;gap:.8rem 1.2rem;flex-wrap:wrap;padding:.8rem 1.2rem;font-size:.92rem;border-bottom:1px solid var(--line)}
.banner.warn{background:var(--warn-t);color:#5E4200}
.banner.err{background:var(--tint);color:var(--red-d)}
.banner .sp{flex:1}

/* rozložení */
.layout{display:grid;grid-template-columns:340px minmax(0,1fr);min-height:calc(100vh - 60px)}
@media (max-width:900px){.layout{grid-template-columns:1fr}}
.list{background:#fff;border-right:1px solid var(--line);padding:1.2rem;display:flex;flex-direction:column;gap:.9rem}
@media (min-width:901px){.list{position:sticky;top:60px;height:calc(100vh - 60px);overflow:auto}}
.list-head{display:flex;justify-content:space-between;align-items:center;gap:.6rem}
.list-head h2{font-size:1.2rem}
.plist{list-style:none;margin:0;padding:0;display:grid;gap:.45rem}
.prow{display:grid;grid-template-columns:auto 56px minmax(0,1fr) auto;align-items:center;gap:.6rem;padding:.5rem;border:1.5px solid var(--line);border-radius:10px;background:#fff;cursor:pointer}
.prow:hover{border-color:#BDB7B7}
.prow.sel{border-color:var(--ink);box-shadow:0 0 0 2px var(--ink) inset}
.prow.off{opacity:.55}
.prow .no{font:600 .72rem/1 var(--fm);color:var(--muted);width:1.3rem;text-align:right}
.thumb{width:56px;height:35px;border-radius:5px;overflow:hidden;background:var(--p3);display:grid;place-items:center;font:800 .8rem/1 var(--fd);color:#fff}
.thumb img{width:100%;height:100%;object-fit:cover;object-position:top;display:block}
.prow b{display:block;font-weight:700;font-size:.92rem;line-height:1.25;overflow-wrap:anywhere}
.prow small{display:flex;flex-wrap:wrap;gap:.25rem;margin-top:.25rem}
.tag{font:600 .66rem/1 var(--fm);letter-spacing:.04em;text-transform:uppercase;padding:.22rem .38rem;border-radius:4px;background:var(--p2);color:var(--muted)}
.tag.home{background:var(--tint);color:var(--red-d)}
.tag.hid{background:#EDEDED;color:#777}
.tag.new{background:var(--ok-t);color:var(--ok)}
.prow .moves{display:flex;flex-direction:column;gap:.2rem}
.prow .moves .ico-btn{width:26px;height:22px;border-radius:6px;font-size:.7rem}
.editor{padding:1.4rem clamp(1rem,3vw,2.4rem) 5rem;display:grid;gap:1.2rem;align-content:start;max-width:1100px}
.ed-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.8rem}
.ed-head h1{font-size:clamp(1.5rem,3vw,2.1rem);overflow-wrap:anywhere}
.ed-head .acts{display:flex;gap:.5rem;flex-wrap:wrap}
.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:clamp(1rem,2.4vw,1.5rem)}
.card>h2{font-size:1.05rem;display:flex;align-items:center;gap:.6rem}
.card>h2 .n{width:24px;height:24px;border-radius:50%;background:var(--ink);color:#fff;display:inline-grid;place-items:center;font:700 .75rem/1 var(--fm);flex:none}
.card>.hint{margin-top:.35rem}
.cache-row{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem 1rem;margin-top:.9rem}
.cache-row .hint{margin:0;color:#1E7A46;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem 1.2rem;margin-top:1.1rem}
.grid .full{grid-column:1/-1}
@media (max-width:700px){.grid{grid-template-columns:1fr}}
.f label,.f .lbl{display:block;font-weight:700;font-size:.86rem;margin-bottom:.35rem}
.f label i{color:var(--red);font-style:normal}
.f input[type=text],.f input[type=url],.f input[type=email],.f input[type=password],.f textarea{width:100%;border:1.5px solid var(--line);border-radius:8px;padding:.6rem .7rem;background:#fff}
.f input:focus,.f textarea:focus{outline:none;border-color:var(--ink);box-shadow:0 0 0 3px rgba(11,11,12,.08)}
.f textarea{resize:vertical;min-height:90px;line-height:1.5}
.f textarea.big{min-height:220px}
.f .help{display:block;margin-top:.3rem;color:var(--muted);font-size:.8rem;line-height:1.4}
.f .count{float:right;font:500 .74rem/1.6 var(--fm);color:var(--muted)}
.f .count.over{color:var(--red)}
.f.err input,.f.err textarea{border-color:var(--red)}
.errmsg{color:var(--red);font-size:.8rem;font-weight:600;margin-top:.3rem}
.switches{display:grid;gap:.6rem;margin-top:1.1rem}
.sw{display:flex;gap:.8rem;align-items:flex-start;padding:.75rem .85rem;border:1.5px solid var(--line);border-radius:10px;cursor:pointer}
.sw:hover{border-color:#BDB7B7}
.sw input{width:20px;height:20px;accent-color:var(--red);margin:.1rem 0 0;flex:none}
.sw b{display:block;font-size:.92rem}
.sw span{display:block;color:var(--muted);font-size:.82rem;line-height:1.4}
.chips{display:flex;flex-wrap:wrap;gap:.4rem}
.chip{position:relative;display:inline-flex;align-items:center;gap:.35rem;border:1.5px solid var(--line);border-radius:999px;padding:.35rem .7rem;font-weight:600;font-size:.85rem;cursor:pointer;background:#fff;user-select:none}
.chip input{position:absolute;opacity:0;pointer-events:none}
.chip:has(input:checked){background:var(--ink);border-color:var(--ink);color:#fff}
.chip:has(input:focus-visible){outline:3px solid var(--red);outline-offset:2px}
.tags{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;border:1.5px solid var(--line);border-radius:8px;padding:.4rem;background:#fff}
.tags:focus-within{border-color:var(--ink)}
.tags .t{display:inline-flex;align-items:center;gap:.3rem;background:var(--p2);border-radius:6px;padding:.3rem .3rem .3rem .6rem;font-weight:600;font-size:.86rem}
.tags .t button{border:0;background:transparent;cursor:pointer;width:22px;height:22px;border-radius:5px;line-height:1}
.tags .t button:hover{background:var(--red);color:#fff}
.tags input{flex:1;min-width:140px;border:0!important;box-shadow:none!important;padding:.35rem!important;outline:none}
.colors{display:flex;flex-wrap:wrap;gap:.7rem;align-items:center}
.col{display:flex;align-items:center;gap:.35rem;border:1.5px solid var(--line);border-radius:10px;padding:.35rem}
.col input[type=color]{width:38px;height:34px;border:0;padding:0;background:none;cursor:pointer}
.col input[type=text]{width:88px;font:500 .85rem var(--fm);padding:.4rem;border:1.5px solid var(--line);border-radius:7px}
.prevs{display:grid;grid-template-columns:minmax(0,1fr) 190px;gap:1.2rem;margin-top:1.1rem;align-items:start}
@media (max-width:700px){.prevs{grid-template-columns:1fr}}
.pv-head{display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.4rem;font-size:.86rem}
.src{font:500 .7rem/1 var(--fm);letter-spacing:.06em;text-transform:uppercase;padding:.25rem .45rem;border-radius:4px;background:var(--p2);color:var(--muted)}
.src.own{background:var(--ok-t);color:var(--ok)}
.pbox{position:relative;overflow:hidden;border-radius:8px;background:var(--p3);border:1px solid var(--line)}
.pbox.desk{aspect-ratio:16/10}
.pbox.mob{aspect-ratio:390/844;border-radius:18px}
.pbox img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top;display:block}
.pbox iframe{position:absolute;left:0;top:0;border:0;transform-origin:0 0;pointer-events:none;background:#fff}
.pbox .ph{position:absolute;inset:0;display:grid;place-content:center;text-align:center;padding:1rem;gap:.3rem;color:var(--muted);font-size:.85rem}
.pbox .ph b{font:800 1rem/1.2 var(--fd);color:var(--ink)}
.pv .row{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.6rem}
.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.9rem;margin-top:1.1rem}
.gi{border:1.5px solid var(--line);border-radius:10px;overflow:hidden;background:#fff;display:flex;flex-direction:column}
.gi .im{aspect-ratio:1.414;background:var(--p3);position:relative}
.gi .im img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.gi .bd{padding:.55rem;display:grid;gap:.45rem}
.gi input{width:100%;border:1.5px solid var(--line);border-radius:7px;padding:.45rem .55rem;font-size:.85rem}
.fn{font:500 .7rem/1.3 var(--fm);color:var(--muted);overflow-wrap:anywhere}
.gi .ops{display:flex;gap:.3rem}
.gi .ops .sp{flex:1}
.files{display:grid;gap:.6rem;margin-top:1.1rem}
.fr{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.7rem;align-items:center;border:1.5px solid var(--line);border-radius:10px;padding:.55rem .7rem;background:#fff}
.fr .ext{width:40px;height:48px;border-radius:5px;background:var(--red);color:#fff;display:grid;place-items:end center;padding-bottom:.4rem;font:700 .62rem/1 var(--fm);clip-path:polygon(0 0,70% 0,100% 22%,100% 100%,0 100%)}
.fr input{width:100%;border:1.5px solid var(--line);border-radius:7px;padding:.45rem .55rem}
.fr .fn{margin-top:.25rem}
.fr .ops{display:flex;gap:.3rem}
.rz{display:grid;gap:.5rem;margin-top:.4rem}
.rzr{display:grid;grid-template-columns:minmax(0,1fr) 9rem auto;gap:.5rem;align-items:center}
.rzr input{width:100%;border:1.5px solid var(--line);border-radius:7px;padding:.5rem .6rem}
.rzr .ops{display:flex;gap:.3rem}
@media (max-width:560px){.rzr{grid-template-columns:minmax(0,1fr) 6.5rem}.rzr .ops{grid-column:1/-1;justify-content:flex-end}}
.addrow{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-top:1rem}
.drop{border:2px dashed #CFC9C9;border-radius:10px;padding:.9rem;text-align:center;color:var(--muted);font-size:.86rem}
.drop.over{border-color:var(--red);background:var(--tint);color:var(--red-d)}
.pend{font:600 .66rem/1 var(--fm);text-transform:uppercase;letter-spacing:.04em;color:var(--ok);background:var(--ok-t);padding:.2rem .35rem;border-radius:4px}
.idline{display:flex;flex-wrap:wrap;gap:.4rem .8rem;align-items:center;margin-top:1rem;padding:.7rem .8rem;border-radius:8px;background:var(--p2);font-size:.85rem}
.idline code{font:500 .82rem var(--fm);background:#fff;border:1px solid var(--line);padding:.15rem .4rem;border-radius:5px;overflow-wrap:anywhere}
.empty{padding:3rem 1rem;text-align:center;color:var(--muted)}
.page{padding:1.6rem clamp(1rem,3vw,2.4rem) 5rem;max-width:880px;display:grid;gap:1.2rem;align-content:start}
.page h1{font-size:clamp(1.5rem,3vw,2.1rem)}
.page p{margin:.6rem 0 0}
.steps{margin:.8rem 0 0;padding-left:1.3rem;display:grid;gap:.5rem}
.steps li::marker{font-weight:700;color:var(--red)}
table.zal{width:100%;border-collapse:collapse;margin-top:1rem;font-size:.9rem}
table.zal th,table.zal td{text-align:left;padding:.6rem .5rem;border-bottom:1px solid var(--line);vertical-align:middle}
table.zal th{font:500 .7rem/1.2 var(--fm);letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
table.zal td:last-child{text-align:right}
@media (max-width:560px){table.zal th:nth-child(2),table.zal td:nth-child(2){display:none}}
dialog{border:0;border-radius:14px;padding:0;max-width:min(560px,94vw);box-shadow:0 30px 80px -20px rgba(0,0,0,.5)}
dialog::backdrop{background:rgba(11,11,12,.55)}
.dlg{padding:1.4rem 1.5rem;display:grid;gap:.8rem}
.dlg h2{font-size:1.3rem}
.dlg .acts{display:flex;justify-content:flex-end;gap:.5rem;flex-wrap:wrap;margin-top:.4rem}
.bar{height:8px;border-radius:4px;background:var(--p3);overflow:hidden}
.bar i{display:block;height:100%;width:0;background:var(--red);transition:width .2s ease}
.tr-l{border:1px solid var(--line);border-radius:10px;padding:.6rem .9rem;margin-top:.7rem}
.tr-l summary{cursor:pointer;display:flex;flex-wrap:wrap;gap:.5rem;align-items:baseline}
.tr-st{font:600 .74rem/1 var(--fm);padding:.25rem .45rem;border-radius:4px;background:var(--p2)}
.tr-st.bad{background:#FBE4E1;color:#A2433A}.tr-st.ok{background:#E3F1E7;color:#2D7A4F}
.tr-h{grid-column:1/-1;font-weight:700;font-size:.84rem;margin-top:.6rem}
.tr-f .tr-src{display:block;font-size:.8rem;color:var(--muted);margin-bottom:.25rem;white-space:pre-line;max-height:5.5em;overflow:hidden}
.tr-f input,.tr-f textarea{width:100%}
</style>
<link rel="stylesheet" href="mobil.css?v=<?= @filemtime(__DIR__ . '/mobil.css') ?>">
</head>
<body>
<?php if ($rezim !== 'editor'): ?>
<main class="auth">
  <form class="auth-card" id="authForm" autocomplete="on">
    <div class="auth-logo">T<span>&amp;</span>S</div>
    <?php if ($rezim === 'instalace'): ?>
      <h1>První spuštění administrace</h1>
      <p class="hint">Zadejte instalační kód ze souboru <b>PRISTUP-DO-ADMINU.txt</b> a zvolte si heslo. Heslo pak budete používat pro přihlášení z jakéhokoli počítače.</p>
      <div><label for="kod">Instalační kód</label><input id="kod" name="kod" autocomplete="off" required placeholder="XXXX-XXXX-XXXX-XXXX"></div>
      <div><label for="heslo">Nové heslo (aspoň 10 znaků)</label><input id="heslo" name="heslo" type="password" autocomplete="new-password" required minlength="10"></div>
      <div><label for="heslo2">Heslo ještě jednou</label><input id="heslo2" name="heslo2" type="password" autocomplete="new-password" required minlength="10"></div>
    <?php else: ?>
      <h1>Administrace webu</h1>
      <?php if ($uzivatele): ?>
      <div><label for="uzivatel">Kdo se přihlašuje</label>
        <select id="uzivatel" name="uzivatel" required class="auth-sel" autocomplete="username">
          <?php foreach ($uzivatele as $u): ?><option value="<?= htmlspecialchars($u['slug']) ?>"><?= htmlspecialchars($u['name']) ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div><label for="heslo">Heslo</label><input id="heslo" name="heslo" type="password" autocomplete="current-password" required autofocus></div>
    <?php endif; ?>
    <?php if (!$https): ?><p class="msg warn">Připojení není šifrované (chybí https://). Heslo raději zadávejte až na adrese začínající https://.</p><?php endif; ?>
    <p class="msg err" id="authErr" role="alert" hidden></p>
    <button class="btn red" type="submit"><?= $rezim === 'instalace' ? 'Nastavit heslo a pokračovat' : 'Přihlásit se' ?></button>
    <?php if ($rezim === 'prihlaseni'): ?><p class="hint">Zapomenuté heslo: přes FTP smažte soubor <b>admin/data/heslo.php</b> a nastavte nové heslo pomocí instalačního kódu.</p><?php endif; ?>
    <p class="hint"><a href="../">← Zpět na web</a></p>
  </form>
</main>
<script>
(function () {
  var CSRF = <?= json_encode($csrf) ?>, mode = <?= json_encode($rezim) ?>;
  var f = document.getElementById('authForm'), err = document.getElementById('authErr'), btn = f.querySelector('button');
  var sel = document.getElementById('uzivatel'); try { var k = localStorage.getItem('ts-admin-kdo'); if (sel && k) { sel.value = k; document.getElementById('heslo').focus(); } } catch (x) { }
  f.addEventListener('submit', function (e) {
    e.preventDefault(); err.hidden = true; btn.disabled = true;
    var d = {}; Array.prototype.forEach.call(f.querySelectorAll('input,select'), function (i) { d[i.name] = i.value; });
    try { if (d.uzivatel) localStorage.setItem('ts-admin-kdo', d.uzivatel); } catch (x) { }
    fetch('api.php?a=' + (mode === 'instalace' ? 'instalace' : 'prihlasit'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify(d) })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j.ok) location.reload(); else { err.textContent = j.text || 'Nepovedlo se.'; err.hidden = false; btn.disabled = false; } })
      .catch(function () { err.textContent = 'Server neodpovídá. Zkuste to prosím znovu.'; err.hidden = false; btn.disabled = false; });
  });
})();
</script>
<?php else: ?>
<header class="top">
  <div class="top-row">
    <a class="brand" href="../" target="_blank" title="Otevřít web"><b>T<span>&amp;</span>S</b><small>Administrace</small></a>
    <b class="mtitle" id="mTitle" aria-hidden="true"></b>
    <nav class="tabs" role="tablist">
      <button type="button" role="tab" aria-selected="true" data-tab="ref">Reference</button>
      <button type="button" role="tab" aria-selected="false" data-tab="crm">CRM / Leady<span class="nb" id="crmBadge" hidden></span></button>
      <button type="button" role="tab" aria-selected="false" data-tab="kontakty">Kontakty a údaje</button>
      <button type="button" role="tab" aria-selected="false" data-tab="zalohy">Zálohy</button>
      <button type="button" role="tab" aria-selected="false" data-tab="ucet">Účet a nápověda</button>
    </nav>
    <div class="save">
      <?php if ($ja): ?><span class="who" id="whoChip" title="Přihlášen(a): <?= htmlspecialchars($ja['name']) ?>"><i><?= htmlspecialchars(implode('', array_map(function ($w) { return mb_substr($w, 0, 1); }, explode(' ', $ja['name'])))) ?></i><span><?= htmlspecialchars(explode(' ', $ja['name'])[0]) ?></span></span><?php endif; ?>
      <span class="status" id="status"><i></i><span>Vše uloženo</span></span>
      <?php if (is_file(dirname(WEBROOT) . '/private/stat/analytics.php')): ?><a class="btn web-btn" href="navstevnost.php" title="Návštěvnost webu (vlastní měření se souhlasem)">Návštěvnost</a><?php endif; ?>
      <a class="btn web-btn" href="../" target="_blank">Web ↗</a>
      <button type="button" class="btn red" id="btnSave"><span class="l">Uložit změny</span><span class="s">Uložit</span></button>
    </div>
  </div>
</header>
<div class="banner err" id="loadErr" hidden><b>Soubor data/reference.js nejde přečíst</b> (nejspíš obsahuje chybu po ruční úpravě). Obnovte prosím poslední zálohu v záložce Zálohy.</div>
<div class="banner err" id="pravaErr" hidden></div>
<?php if ($ja && empty($ja['password_hash'])): ?><div class="banner warn" id="pwBanner"><span><b><?= htmlspecialchars(explode(' ', $ja['name'])[0]) ?>, nastavte si prosím vlastní heslo.</b> Zatím se přihlašujete společným heslem administrace, takže by se za vás mohl přihlásit i někdo jiný. Vlastní heslo zajistí, že u leadů a kontaktů bude vždy správné jméno.</span><span class="sp"></span><button type="button" class="btn small" id="pwBannerBtn">Nastavit heslo</button></div><?php endif; ?>
<?php if (!$https): ?><div class="banner warn">Administrace běží bez šifrování (http://). Až bude mít web certifikát, otevírejte ji přes https://.</div><?php endif; ?>
<div class="banner warn" id="aktBanner" hidden><span>Pro zakázku <b id="aktNames"></b> jsou nahrané nové podklady (popis, funkce, galerie). Pořadí a zobrazení na webu zůstanou, jak je máte.</span><span class="sp"></span><button type="button" class="btn small" id="aktYes">Použít nové podklady</button><button type="button" class="btn small ghost" id="aktNo">Ponechat současnou verzi</button></div>
<div class="banner warn" id="pripBanner" hidden><span>Do seznamu jsme přidali připravenou zakázku <b id="pripNames"></b>. Zkontrolujte ji a klikněte na <b>Uložit</b>, pak se ukáže na webu.</span></div>
<div class="banner warn" id="draftBanner" hidden><span>V tomto prohlížeči jsou <b>neuložené změny</b> z <span id="draftTime"></span>.</span><span class="sp"></span><button type="button" class="btn small" id="draftYes">Obnovit je</button><button type="button" class="btn small ghost" id="draftNo">Zahodit</button></div>

<main>
  <section class="layout" id="tab-ref" role="tabpanel">
    <aside class="list">
      <div class="list-head"><h2>Zakázky</h2><button type="button" class="btn red small" id="btnAdd">+ Nová zakázka</button></div>
      <ol class="plist" id="plist"></ol>
      <p class="hint"><b>Pořadí v seznamu = pořadí na webu.</b> Šipkami zakázky přesunete. Štítek „Hlavní str.“ znamená, že se zakázka ukáže i na hlavní stránce.</p>
    </aside>
    <div class="m-back"><button type="button" id="refBack"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg>Zakázky</button></div>
    <div class="editor" id="editor"></div>
  </section>

  <section class="crm" id="tab-crm" role="tabpanel" hidden aria-label="CRM / Leady"><?php if (!crm_dostupne()): ?><div class="page"><div class="card"><h2>CRM teď nejde spustit</h2><p class="hint">Hosting nemá zapnutou podporu databáze SQLite (rozšíření PHP <b>pdo_sqlite</b>). Zapněte ho prosím v administraci hostingu (nastavení PHP), pak CRM poběží samo.</p></div></div><?php endif; ?></section>

  <section class="page" id="tab-kontakty" role="tabpanel" hidden>
    <h1>Kontakty a údaje</h1>
    <p class="hint">Ukážou se v sekci Poptávka, v patičce a na stránce Ochrana osobních údajů. Co necháte prázdné, na webu se nezobrazí, nebo se ukáže „doplníme“. Na uvedený e-mail chodí i poptávky z formuláře.</p>
    <div class="card"><div class="grid" id="nastForm"></div></div>
  </section>

  <section class="page" id="tab-zalohy" role="tabpanel" hidden>
    <h1>Zálohy</h1>
    <p class="hint">Před každým uložením se automaticky uloží záloha předchozího stavu (posledních 60). Když se něco pokazí, vrátíte web jedním kliknutím. Obrázky a soubory smazaných zakázek jsou v koši a při obnovení se vrátí také.</p>
    <div class="card"><table class="zal"><thead><tr><th>Kdy</th><th>Důvod</th><th>Zakázek</th><th></th></tr></thead><tbody id="zalList"><tr><td colspan="4" class="hint">Načítám…</td></tr></tbody></table></div>
  </section>

  <section class="page" id="tab-ucet" role="tabpanel" hidden>
    <h1>Účet a nápověda</h1>
    <div class="card" id="cacheCard">
      <h2>Mezipaměť webu</h2>
      <p class="hint">Prohlížeče si obrázky a soubory webu ukládají, aby se stránky načítaly rychleji. Po <b>Uložit změny</b> se nová verze ukáže všem hned sama. Tlačítko použijte, když se někde pořád ukazuje stará verze (třeba po nahrání souborů přes FTP). Všechny obrázky, soubory, styly a skripty webu dostanou novou adresu a každý návštěvník si je při příštím načtení stránky stáhne znovu.</p>
      <div class="cache-row"><button type="button" class="btn red" id="btnCache">Obnovit mezipaměť webu</button><span class="hint" id="cacheInfo" role="status" hidden></span></div>
    </div>
    <div class="card">
      <h2>Jak přidat zakázku</h2>
      <ol class="steps">
        <li>Klikněte na <b>+ Nová zakázka</b> a vyplňte název a obor.</li>
        <li>Vložte <b>odkaz na web</b>. Náhled webu se vytvoří sám a po uložení se uloží i na server.</li>
        <li>Napište krátký popis (na kartu) a podrobný popis (do detailu zakázky).</li>
        <li>Přidejte obrázky do galerie a soubory ke stažení (brand manuál, tisková data…). Velké obrázky se samy zmenší.</li>
        <li>Zaškrtněte, jestli se má zakázka ukázat i na <b>hlavní stránce</b>.</li>
        <li>Klikněte na <b>Uložit změny</b>. Hned potom jsou změny vidět na webu.</li>
      </ol>
      <p class="hint" style="margin-top:.8rem">Chcete zakázku jen schovat? Vypněte „Zobrazit na webu“. Zůstane uložená a kdykoli ji vrátíte.</p>
    </div>
    <div class="card" id="pwCard">
      <h2><?= $ja ? 'Vaše heslo (' . htmlspecialchars($ja['name']) . ')' : 'Změna hesla' ?></h2>
      <?php if ($ja): ?><p class="hint"><?= empty($ja['password_hash']) ? 'Zatím nemáte vlastní heslo. Jako současné heslo zadejte společné heslo administrace a zvolte si své. Pak se budete přihlašovat svým jménem a heslem.' : 'Každý z týmu má vlastní heslo. Tady měníte jen to svoje.' ?></p><?php endif; ?>
      <form class="grid" id="pwForm">
        <div class="f full"><label for="pw0">Současné heslo</label><input type="password" id="pw0" autocomplete="current-password" required></div>
        <div class="f"><label for="pw1">Nové heslo (aspoň 10 znaků)</label><input type="password" id="pw1" autocomplete="new-password" required minlength="10"></div>
        <div class="f"><label for="pw2">Nové heslo znovu</label><input type="password" id="pw2" autocomplete="new-password" required minlength="10"></div>
        <div class="full"><button class="btn" type="submit">Změnit heslo</button></div>
      </form>
    </div>
    <div class="card">
      <h2>Odhlášení</h2>
      <p class="hint">Na cizím počítači se po práci vždy odhlaste.</p>
      <p><button type="button" class="btn" id="btnLogout">Odhlásit se</button></p>
    </div>
  </section>
</main>

<!-- telefon: spodní navigace jako v aplikaci (na počítači skrytá) -->
<nav class="mbar" id="mbar" aria-label="Hlavní navigace">
  <?php if (crm_dostupne()): ?>
  <a href="#crm" data-m="prehled"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 9.8V20h13V9.8"/><path d="M10 20v-5.5h4V20"/></svg><span>Přehled</span></a>
  <a href="#crm/leady" data-m="leady"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3 19.5a6 6 0 0 1 12 0"/><path d="M15.5 4.8a3.2 3.2 0 0 1 0 6.4M17.5 13.6a6 6 0 0 1 3.5 5.9"/></svg><span>Leady</span></a>
  <a href="#crm/ukoly" data-m="ukoly"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="m8.5 12.2 2.4 2.4 4.7-5"/></svg><span>Úkoly</span><b class="nb" id="mBadge" hidden></b></a>
  <button type="button" data-m="ref"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg><span>Reference</span></button>
  <?php else: ?>
  <button type="button" data-m="ref"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg><span>Reference</span></button>
  <button type="button" data-m="kontakty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16a3.4 3.4 0 0 1 6.4 0M14.5 10h4M14.5 13.5h3"/></svg><span>Kontakty</span></button>
  <button type="button" data-m="zalohy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3.5V8h4.5M12 7.5V12l3 2"/></svg><span>Zálohy</span></button>
  <?php endif; ?>
  <button type="button" data-m="vice" aria-haspopup="dialog" aria-controls="moreSheet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg><span>Více</span></button>
</nav>
<dialog class="sheet" id="moreSheet" aria-label="Další možnosti"><div class="sh">
  <?php if ($ja): ?><div class="sh-who"><i><?= htmlspecialchars(implode('', array_map(function ($w) { return mb_substr($w, 0, 1); }, explode(' ', $ja['name'])))) ?></i><div><b><?= htmlspecialchars($ja['name']) ?></b><small>Přihlášen(a) do administrace</small></div></div><?php endif; ?>
  <div class="sh-list">
    <?php if (crm_dostupne()): ?><button type="button" data-go="kontakty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16a3.4 3.4 0 0 1 6.4 0M14.5 10h4M14.5 13.5h3"/></svg><span>Kontakty a údaje</span></button>
    <button type="button" data-go="zalohy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3.5V8h4.5M12 7.5V12l3 2"/></svg><span>Zálohy</span></button><?php endif; ?>
    <button type="button" data-go="ucet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg><span>Účet a nápověda</span></button>
    <?php if (is_file(dirname(WEBROOT) . '/private/stat/analytics.php')): ?><a href="navstevnost.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg><span>Návštěvnost</span></a><?php endif; ?>
    <a href="../" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg><span>Otevřít web</span></a>
  </div>
  <div class="sh-list"><button type="button" data-go="odhlasit" class="danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16.5 5.5 12 10 7.5M5.5 12H15"/></svg><span>Odhlásit se</span></button></div>
  <button type="button" class="btn ghost sh-x" data-close>Zavřít</button>
</div></dialog>

<dialog id="progDlg"><div class="dlg"><h2 id="progTitle">Ukládám…</h2><p class="hint" id="progText"></p><div class="bar"><i id="progBar"></i></div></div></dialog>
<dialog id="doneDlg"><div class="dlg">
  <h2>Uloženo</h2>
  <p class="hint" id="doneText">Změny už jsou na webu.</p>
  <div class="acts"><a class="btn" id="doneView" href="../" target="_blank">Zobrazit na webu ↗</a><button type="button" class="btn red" id="doneOk">Hotovo</button></div>
</div></dialog>
<dialog id="loginDlg"><form class="dlg" id="loginForm">
  <h2>Přihlaste se znovu</h2>
  <p class="hint">Přihlášení vypršelo. Vaše rozpracované změny zůstávají, po přihlášení se uloží.</p>
  <?php if ($uzivatele): ?><div class="f"><label for="relogKdo">Kdo se přihlašuje</label><select id="relogKdo" class="sel"><?php foreach ($uzivatele as $u): ?><option value="<?= htmlspecialchars($u['slug']) ?>"<?= $ja && $ja['id'] == $u['id'] ? ' selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="f"><label for="relog">Heslo</label><input type="password" id="relog" autocomplete="current-password" required></div>
  <p class="msg err" id="relogErr" hidden></p>
  <div class="acts"><button class="btn red" type="submit">Přihlásit a pokračovat</button></div>
</form></dialog>

<script src="../data/reference.js?v=<?= $verze('data/reference.js') ?>"></script>
<script src="../data/nastaveni.js?v=<?= $verze('data/nastaveni.js') ?>"></script>
<script>
window.TS_ADMIN = { csrf: <?= json_encode($csrf) ?>, ja: <?= json_encode($ja ? ['id' => (int) $ja['id'], 'name' => $ja['name'], 'slug' => $ja['slug']] : null, JSON_UNESCAPED_UNICODE) ?>, crm: <?= json_encode(crm_dostupne()) ?>, nast: <?= json_encode(NAST, JSON_UNESCAPED_UNICODE) ?>, pripravene: <?= json_encode($pripravene, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?> };
</script>
<script src="admin.js?v=<?= @filemtime(__DIR__ . '/admin.js') ?>"></script>
<?php if (crm_dostupne()): ?><script src="crm.js?v=<?= @filemtime(__DIR__ . '/crm.js') ?>"></script><?php endif; ?>
<?php endif; ?>
</body>
</html>
