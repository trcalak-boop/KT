<?php
/* Návrh na míru s falešným modelem (bez sítě): prompt, tolerance k odpovědi, fakta jen ze zadání, kostra webu, vrstva CSS, odlišnost,
   projekt se směry, úpravy podle připomínek, náhled, export, brána, strop a chyby modelu. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir();
$kit = new TsStudioKit();
$planOf = fn (string $n) => json_decode((string) file_get_contents(__DIR__ . "/fixtures/plan-$n.json"), true, 32, JSON_THROW_ON_ERROR);
$skola = $planOf('skola'); $eshop = $planOf('eshop');
$briefOf = fn (array $plan, string $name) => ['name' => $name, 'obor' => 'test', 'goals' => 'Aby se lidé ozvali', 'texts' => json_encode($plan, JSON_UNESCAPED_UNICODE), 'lang' => 'cs'];
$mk = function (array $script, int $limit = 0) use ($dir) {
    static $n = 0; $n++;
    $m = new TsStudioFakeModel(['script' => $script]);
    return [ts_design_studio(['db' => ['dsn' => "sqlite:$dir/p$n.sqlite"], 'ai' => ['model' => $m, 'neuronsPerDay' => $limit]]), $m];
};

/* --- prompt --- */
$b = TsStudioAi::cleanBrief(['name' => 'Firma', 'goals' => 'Víc objednávek', 'likes' => 'čisté weby', 'dislikes' => 'kýč', 'lang' => 'cs']);
$msgs = TsStudioPlanner::createPrompt($kit, $b, TsStudioPlanner::DIRECTIONS[1], ['písma x a y']);
$all = $msgs[0]['content'] . $msgs[1]['content'];
t_ok(strpos($all, 'Víc objednávek') !== false && strpos($all, 'čisté weby') !== false && strpos($all, 'kýč') !== false, 'prompt: cíle, co se líbí a nelíbí');
t_ok(strpos($all, 'výrazný a sebevědomý') !== false && strpos($all, 'písma x a y') !== false, 'prompt: směr a čemu se vyhnout');
foreach (array_keys($kit->sections()) as $t) t_ok(strpos($msgs[1]['content'], "\n$t – ") !== false, "prompt: sekce $t");
t_ok(strpos($all, 'FAKTA NEVYMÝŠLEJ') !== false && strpos($all, 'facts[≤4]{label:30, value*:50}') !== false && strpos($all, 'dishes[≤8]') !== false, 'prompt: hvězdička u faktů, vnořené seznamy');
$tok = (int) ceil(mb_strlen($all) / 4);
t_ok($tok < 6500, "prompt je úsporný: asi $tok tokenů vstupu (" . (int) ceil($tok * 9091 / 1e6) . ' neuronů)');

/* --- tolerance k odpovědi --- */
$n = TsStudioPlanner::normalize($kit, ['tokens' => ['f_display' => 'DM Serif Display', 'f_body' => '"Figtree", sans-serif', 'f_mono' => 'jetbrains-mono', 'radius' => 10, 'bad' => ['x']], 'sections' => [['type' => 'hero', 'title' => 'Bez obalu'], 'x'], 'css' => ['ne']]);
t_ok($n['tokens']['f_display'] === 'dm-serif-display' && $n['tokens']['f_body'] === 'figtree' && $n['tokens']['radius'] === '10' && !isset($n['tokens']['bad']), 'tokeny: název písma → id, číslo → text');
t_ok(count($n['sections']) === 1 && $n['sections'][0]['content']['title'] === 'Bez obalu' && $n['css'] === '', 'sekce bez „content“, nesmyslné položky pryč');

/* --- fakta jen ze zadání --- */
t_ok(TsStudioPlanner::groundedOk('Po–Pá 8:00–16:00', 'otevřeno po až pá 8-16 hodin') && !TsStudioPlanner::groundedOk('Po–Pá 9:00–18:00', 'otevřeno po až pá 8-16 hodin'), 'čas: 8:00 sedí na „8-16“, vymyšlený čas ne');
t_ok(TsStudioPlanner::groundedOk('1 490 Kč', 'kurz stojí 1 490 Kč') && !TsStudioPlanner::groundedOk('1 590 Kč', 'kurz stojí 1 490 Kč'), 'cena se kontroluje po číslech');
$src = "Pekárna Veveří. Telefon 600 111 222, pekarna@example.cz, Veveří 12, Brno. Žitný chléb 98 Kč. Jana Nováková je majitelka.";
[$p] = $kit->clean(['site' => ['brand' => 'Pekárna', 'phone' => '+420 600 111 222', 'email' => 'jiny@example.cz', 'address' => 'Falešná 99, Praha'], 'sections' => [
    ['type' => 'pricelist', 'id' => 'cenik', 'content' => ['title' => 'Ceník', 'items' => [['name' => 'Žitný', 'price' => '98 Kč'], ['name' => 'Rohlík', 'price' => '7 Kč']]]],
    ['type' => 'reviews', 'id' => 'ohlasy', 'content' => ['items' => [['text' => 'Nejlepší pekárna široko daleko, doporučuji všem známým!', 'author' => 'Karel Novák']]]],
    ['type' => 'team', 'id' => 'tym', 'content' => ['items' => [['name' => 'Jana Nováková', 'role' => 'majitelka'], ['name' => 'Petr Vymyšlený', 'role' => 'pekař']]]],
    ['type' => 'quote', 'id' => 'citat', 'content' => ['quote' => 'Pečeme s láskou od roku 1999', 'author' => 'Neznámý Autor']],
    ['type' => 'stats', 'id' => 'cisla', 'content' => ['items' => [['value' => '25 let', 'label' => 'tradice']]]],
    ['type' => 'text', 'id' => 'text', 'content' => ['paragraphs' => [['text' => 'Pečeme už 40 let a máme 3 pece.']]]],
    ['type' => 'contact', 'id' => 'kontakt', 'content' => ['phone' => '+420 777 000 000', 'email' => 'pekarna@example.cz']]]]);
[$g, $dropped, $suspect] = TsStudioPlanner::ground($kit, $p, $src);
t_ok($g['site']['phone'] === '+420 600 111 222' && $g['site']['email'] === '' && $g['site']['address'] === '', 'kontakty webu: telefon se zadání (i s předvolbou), cizí e-mail a adresa pryč', $g['site']);
$sec = array_column($g['sections'], null, 'id');
t_ok($sec['cenik']['content']['items'][0]['price'] === '98 Kč' && $sec['cenik']['content']['items'][1]['price'] === TsStudioAi::TODO, 'vymyšlená cena → [doplnit]');
t_ok($sec['kontakt']['content']['phone'] === '' && $sec['kontakt']['content']['email'] === 'pekarna@example.cz', 'kontakt v sekci: jen ze zadání');
t_ok(in_array('text.paragraphs.0.text', $suspect, true), 'čísla ve volném textu se hlásí k ověření', $suspect);
[$r, $notes] = TsStudioPlanner::repair($kit, $g, ['name' => 'Pekárna']);
$ids = array_column($r['sections'], 'id');
t_ok(!array_intersect(['ohlasy', 'citat', 'cisla'], $ids), 'recenze, citát a čísla bez podkladu = sekce zmizí', $ids);
$sec = array_column($r['sections'], null, 'id');
t_eq(array_column($sec['tym']['content']['items'], 'name'), ['Jana Nováková'], 'vymyšlený člen týmu pryč');

/* --- kostra webu --- */
[$p] = $kit->clean(['site' => ['lang' => 'cs', 'cta_label' => 'Napsat', 'cta_url' => '#neni'], 'sections' => [
    ['type' => 'faq', 'id' => 'dotazy', 'content' => ['items' => [['q' => 'A?', 'a' => 'B']]]], ['type' => 'news', 'id' => 'prazdne', 'content' => ['items' => []]],
    ['type' => 'notice', 'id' => 'pozor', 'content' => ['text' => 'Pozor']], ['type' => 'cta', 'id' => 'vyzva', 'content' => ['title' => 'Pojďte', 'cta_label' => 'Tam', 'cta_url' => '#neexistuje', 'cta2_label' => 'Sem', 'cta2_url' => '']]]]);
[$r, $notes] = TsStudioPlanner::repair($kit, $p, ['name' => 'Moje firma']);
t_eq(array_column($r['sections'], 'type'), ['notice', 'hero', 'faq', 'cta', 'contact'], 'chybějící úvod a kontakt doplněny, upozornění nahoru, prázdná sekce pryč');
t_ok($r['site']['brand'] === 'Moje firma' && $r['site']['title'] === 'Moje firma' && count($notes) >= 3, 'název webu ze zadání, opravy se hlásí', $notes);
$sec = array_column($r['sections'], null, 'id');
t_ok($sec['vyzva']['content']['cta_url'] === '#kontakt' && $sec['vyzva']['content']['cta2_url'] === '#kontakt' && $r['site']['cta_url'] === '#kontakt', 'kotvy jen na existující sekce', $sec['vyzva']['content']);
t_ok(count(array_filter(array_column($r['sections'], 'nav'))) >= 2, 'bez menu od modelu se doplní');
[$p] = $kit->clean(['sections' => [['type' => 'notice', 'content' => ['text' => 'X']], ['type' => 'hero', 'content' => ['title' => 'A']], ['type' => 'hero', 'id' => 'druhy', 'content' => ['title' => 'B']], ['type' => 'contact', 'content' => []]]]);
[$r] = TsStudioPlanner::repair($kit, $p, []);
t_eq(array_column($r['sections'], 'type'), ['notice', 'hero', 'contact'], 'upozornění smí nad úvod, druhý úvod pryč');

/* --- vrstva CSS --- */
$S = [['id' => 'uvod', 'type' => 'hero'], ['id' => 'cenik', 'type' => 'pricelist']];
[$c, $e] = $kit->cleanCss('.s-hero h1{font-size:clamp(40px,8vw,120px);letter-spacing:-.02em} #cenik .pl-p{font-weight:800} @media (max-width:600px){.s-hero h1{font-size:40px}}', $S);
t_ok($e === [] && strpos($c, '.s-hero h1{font-size:clamp(40px,8vw,120px);letter-spacing:-.02em}') !== false && strpos($c, '#cenik .pl-p{font-weight:800}') !== false && strpos($c, '@media (max-width:600px){.s-hero h1{font-size:40px}}') !== false, 'povolená pravidla a @media projdou', [$c, $e]);
foreach (['body{font-size:10px}' => 'mimo sekci', ':root{--bg:#000}' => 'tokeny', '.s-team h3{font-size:10px}' => 'sekce, která v návrhu není', '#neni{margin:0}' => 'neexistující id', '.s-hero *{margin:0}' => 'hvězdička', '.hd{display:none}' => 'hlavička',
          '.s-hero{color:#000}' => 'barva', '.s-hero{background:url(https://x.test/a.png)}' => 'url', '.s-hero{position:fixed}' => 'pozice', '.s-hero{font-size:900px}' => 'obří písmo', '.s-hero{margin:var(--x)}' => 'proměnná', '.s-hero{padding:1px!important}' => 'important',
          '@import "https://x.test/a.css";' => 'import', '@font-face{font-family:x;src:url(//x.test/a)}' => 'font-face', '.s-hero{width:expression(alert(1))}' => 'expression', '</style><script>alert(1)</script>' => 'značka'] as $bad => $why) {
    [$c] = $kit->cleanCss($bad, $S); t_eq($c, '', "CSS se zahodí: $why");
}
[$c, $e] = $kit->cleanCss('.s-hero h1{font-size:40px;color:red}', $S); t_ok($c === '.s-hero h1{font-size:40px}' && count($e) === 1, 'z pravidla zmizí jen nepovolená vlastnost');
$r = $kit->render(['site' => ['brand' => 'A', 'title' => 'A b'], 'css' => '.s-hero h1{font-size:40px}body{display:none}', 'sections' => [['type' => 'hero', 'content' => ['title' => 'A']]]]);
t_ok(strpos($r['files']['assets/site.css'], "/*@ai*/\n.s-hero h1{font-size:40px}\n/*@ai-end*/") !== false && strpos($r['files']['assets/site.css'], 'body{display:none}') === false, 'vrstva je ve stylech jen vyčištěná');

/* --- odlišnost --- */
$cl = fn (array $plan) => $kit->clean($plan)[0];
$A = $cl($skola); $E = $cl($eshop);
$sa = TsStudioPlanner::signature($A, $A['tokens']); $se = TsStudioPlanner::signature($E, $E['tokens']);
t_eq(TsStudioPlanner::similarity($sa, $sa), 1.0, 'stejný návrh = podobnost 1');
t_ok(TsStudioPlanner::similarity($sa, $se) < 0.4, 'škola × e-shop: nízká podobnost (' . TsStudioPlanner::similarity($sa, $se) . ')');
[$p1, $t1, $sim, $varied] = TsStudioPlanner::diversify($kit, $A, $A['tokens'], [$se]);
t_ok(!$varied && $p1['sections'] === $A['sections'], 'odlišný návrh se nemění');
[$p1, $t1, $sim, $varied] = TsStudioPlanner::diversify($kit, $A, $A['tokens'], [$sa]);
t_ok($varied && $sim < TsStudioPlanner::SIMILAR && $p1['sections'] !== $A['sections'] && $t1['radius'] !== $A['tokens']['radius'] && array_column($p1['sections'], 'content') === array_column($A['sections'], 'content'), "příliš podobný: jiné rozvržení a tvar, obsah stejný (podobnost $sim)");
t_eq(array_values(array_map(fn ($x) => $x['msg'], TsStudioChecks::run($kit->render($p1 + ['tokens' => $t1])['files']))), [], 'pozměněný návrh stále bez nálezu');

/* --- projekt z návrhu --- */
[$s, $m] = $mk([$skola]);
$r = $s->kitCreate($briefOf($skola, 'ZŠ Pod Lipami'), 1);
t_ok($r['id'] > 0 && $r['template'] === TsDesignStudio::KIT && count($r['versions']) === 1 && !TsStudioChecks::errors($r['checks']), 'zadání → projekt ze stavebnice', $r['checks']);
$g = $s->get($r['id'], 1);
t_ok((int) $g['project']['current_num'] === $r['version'] && $g['versions'][0]['author_kind'] === 'ai', 'první návrh je přijatá verze od AI');
$v = $s->version($r['id'], $r['version'], 1);
t_ok(strpos($v['files']['index.html'], 's-menu--days') !== false && isset($v['content']['sections'][0]['type']) && isset($v['tokens']['accent_text']) && !isset($v['content']['tokens']), 'verze: soubory, plán, tokeny');
t_eq(count($v['content']['sections']), count($skola['sections']), 'žádná sekce se neztratila, když fakta sedí');
t_eq($r['todo'], [], 'nic k doplnění');
$files = $s->allFiles($r['id'], $r['version'], 1);
t_ok(isset($files['assets/fonts/Outfit-OFL.txt']) && !isset($files['assets/fonts/Syne-OFL.txt']) && count(array_filter(array_keys($files), fn ($f) => str_ends_with($f, '.woff2'))) >= 3 && count($files) < 16, 'export: jen použitá písma a jejich licence', array_keys($files));
t_ok(strlen($s->exportZip($r['id'], $r['version'], 1)) > 10000, 'ZIP');
$h = $s->preview($r['id'], $r['version'], 1);
t_ok(strpos($h, 'Content-Security-Policy') !== false && strpos($h, 'font/woff2;base64') !== false && strpos($h, '<link') === false && strpos($h, 'src="assets') === false, 'náhled: vše vložené, CSP');
$t = $s->template(TsDesignStudio::KIT);
t_ok(!$t->isText('assets/fonts/x.woff2') && in_array('assets/fonts/Outfit-OFL.txt', $t->files(), true) && strlen($t->raw(array_values(array_filter($t->files(), fn ($f) => str_ends_with($f, '.woff2')))[0])) > 1000 && $t->raw('assets/fonts/neni.woff2') === '', 'brána náhledů: písma přes virtuální šablonu, neznámý soubor prázdný');
$threw = false; try { $s->createProject(['name' => 'X', 'source' => 'new', 'template' => TsDesignStudio::KIT], 1); } catch (InvalidArgumentException $e) { $threw = true; }
t_ok($threw, 'projekt ze stavebnice nejde založit bez zadání (přes výběr šablony)');
t_ok(array_key_exists('restaurace', $s->templates()) && !array_key_exists(TsDesignStudio::KIT, $s->templates()), 'v nabídce šablon stavebnice není');
$rv = $s->revert($r['id'], $r['version'], 1); t_ok($s->version($r['id'], $rv['num'], 1)['content'] === $v['content'], 'návrat k verzi funguje');
$pv = $s->patchVersion($r['id'], 1, ['assets/site.css' => $v['files']['assets/site.css'] . "\n/* ruční */"], 'Ruční úprava'); t_ok($pv['num'] > 0, 'ruční úprava souboru funguje');
$rr = $s->renderVersion($r['id'], 1, array_replace_recursive($v['content'], ['site' => ['brand' => 'Nový název']]), $v['tokens'], 'Ruční změna obsahu');
t_ok(strpos($s->version($r['id'], $rr['num'], 1)['files']['index.html'], 'Nový název') !== false, 'ruční změna obsahu se znovu vykreslí');

/* --- odpověď modelu: oprava a chyby --- */
[$s, $m] = $mk(['tohle není json', $skola]);
$r = $s->kitCreate($briefOf($skola, 'Škola'), 1); t_ok($r['id'] > 0 && count($m->calls) === 2, 'nečitelná odpověď: jedno opakování');
[$s, $m] = $mk(['nic', 'nic']);
$threw = ''; try { $s->kitCreate($briefOf($skola, 'Škola'), 1); } catch (TsStudioModelError $e) { $threw = $e->kind; }
t_ok($threw === 'format' && $s->projects(1) === [], 'dvakrát nečitelné: chyba, žádný projekt');
[$s, $m] = $mk([['fail' => 'limit']]);
$threw = ''; try { $s->kitCreate($briefOf($skola, 'Škola'), 1); } catch (TsStudioModelError $e) { $threw = $e->kind; }
t_ok($threw === 'limit' && $s->projects(1) === [], 'kvóta: chyba, žádný projekt');
[$s, $m] = $mk([$skola], 1);
$s->kitCreate($briefOf($skola, 'Škola'), 1); $n = count($m->calls);
$threw = ''; try { $s->kitCreate($briefOf($skola, 'Škola 2'), 1); } catch (TsStudioModelError $e) { $threw = $e->kind; }
t_ok($threw === 'limit' && count($m->calls) === $n, 'denní strop Studia: model se nevolá');
$threw = false; try { $s->kitCreate(['lang' => 'cs'], 1); } catch (InvalidArgumentException $e) { $threw = true; } catch (TsStudioModelError $e) { $threw = true; }
t_ok($threw, 'zadání bez názvu se odmítne');
[$s, $m] = $mk([['sections' => [['type' => 'neznamy']], 'tokens' => ['bg' => 'red']]]);
$r = $s->kitCreate(['name' => 'Malá firma', 'lang' => 'cs'], 1);
$v = $s->version($r['id'], $r['version'], 1);
t_ok(array_column($v['content']['sections'], 'type') === ['hero', 'contact'] && !TsStudioChecks::errors($r['checks']) && $r['errors'], 'skoro prázdná odpověď: i tak funkční kostra a chyby k vidění', $r['errors']);
t_ok(strpos($s->messages($r['id'], 1)[1]['text'] ?? '', 'doplněn úvod') !== false, 'designér dostane přehled oprav');

/* --- tři směry --- */
[$s, $m] = $mk([$skola, $eshop, $planOf('skola')]);
$r = $s->kitCreate($briefOf($skola + ['x' => $eshop], 'Tři směry'), 1, [], 3);
t_eq(count($r['versions']), 3, 'tři směry = tři verze');
t_ok(strpos($m->calls[0][1]['content'], 'klidný a čistý') !== false && strpos($m->calls[1][1]['content'], 'výrazný') !== false && strpos($m->calls[2][1]['content'], 'teplý') !== false, 'každý směr má vlastní zadání');
t_ok(strpos($m->calls[1][1]['content'], 'Vyhni se podobnosti') !== false && strpos($m->calls[1][1]['content'], 'outfit') !== false, 'další směr ví, čemu se vyhnout');
$g = $s->get($r['id'], 1);
$st = array_column($g['versions'], 'status', 'num');
t_ok($st[$r['versions'][0]['num']] === 'accepted' && $st[$r['versions'][1]['num']] === 'candidate' && $st[$r['versions'][2]['num']] === 'candidate', 'první přijatý, ostatní k výběru');
t_ok($r['versions'][2]['varied'] === true && $r['versions'][1]['varied'] === false, 'stejný plán jako první směr se automaticky pozmění');
$s->accept($r['id'], $r['versions'][1]['num'], 1); t_eq((int) $s->get($r['id'], 1)['project']['current_num'], $r['versions'][1]['num'], 'jiný směr jde přijmout');
[$s, $m] = $mk([$skola, ['fail' => 'limit']]);
$r = $s->kitCreate($briefOf($skola, 'Část'), 1, [], 3);
t_ok(count($r['versions']) === 1 && $r['failed'] === 'limit' && $r['id'] > 0, 'kvóta cestou: hotový směr zůstane a selhání se nahlásí');
[$s, $m] = $mk([$skola, $skola]);
$s->kitCreate($briefOf($skola, 'První'), 1); $r = $s->kitCreate($briefOf($skola, 'Druhý'), 1);
t_ok($r['versions'][0]['varied'] === true && strpos($m->calls[1][1]['content'], 'Vyhni se podobnosti') !== false, 'další projekt je odlišný od dřívějších');

/* --- úpravy podle připomínek --- */
$first = $skola;
[$s, $m] = $mk([$first, ['reply' => 'Hotovo', 'tokens' => ['accent' => '#0b5fff', 'radius' => '0', 'neznamy' => 'x'], 'site' => ['tagline' => 'Nový popis'], 'css' => '.s-hero h1{font-size:50px}body{margin:0}',
    'ops' => [['op' => 'set', 'id' => 'hero', 'variant' => 'cover', 'content' => ['title' => 'Nový nadpis']], ['op' => 'remove', 'id' => 'krouzky'], ['op' => 'move', 'id' => 'kontakt', 'after' => 'hero'],
              ['op' => 'add', 'after' => 'novinky', 'section' => ['type' => 'faq', 'id' => 'dotazy', 'content' => ['items' => [['q' => 'Kdy?', 'a' => 'Brzy']]]]], ['op' => 'set', 'id' => 'neni'], ['op' => 'nesmysl']]]]);
$r = $s->kitCreate($briefOf($first, 'Škola'), 1); $pid = $r['id']; $base = $r['version'];
$e = $s->aiEdit($pid, 1, 'Udělej to výraznější a přidej dotazy');
$v = $s->version($pid, $e['num'], 1); $ids = array_column($v['content']['sections'], 'id'); $sec = array_column($v['content']['sections'], null, 'id');
t_ok($sec['hero']['variant'] === 'cover' && $sec['hero']['content']['title'] === 'Nový nadpis' && $sec['hero']['content']['lead'] !== '', 'připomínka: změna varianty a slotu, ostatní zůstává');
t_ok(!in_array('krouzky', $ids, true) && array_search('kontakt', $ids) === array_search('hero', $ids) + 1 && array_search('dotazy', $ids) === array_search('novinky', $ids) + 1, 'odebrání, přesun, přidání sekce', $ids);
t_ok($v['tokens']['accent'] === '#0b5fff' && $v['tokens']['radius'] === '0' && !isset($v['tokens']['neznamy']) && $v['content']['site']['tagline'] === 'Nový popis' && $v['content']['css'] === '.s-hero h1{font-size:50px}', 'tokeny, údaje webu, vrstva CSS vyčištěná');
t_ok($v['status'] === 'candidate' && (int) $s->get($pid, 1)['project']['current_num'] === $base && $e['reply'] === 'Hotovo', 'úprava je nová verze ke schválení, přijatá verze se nemění');
t_ok(strpos($m->calls[1][1]['content'] ?? '', 'x') !== false && strpos(end($m->calls[1])['content'], 'Udělej to výraznější') !== false && strpos(end($m->calls[1])['content'], '"hero"') !== false, 'model dostane pokyn a současný plán');
$tok = (int) ceil(mb_strlen(implode(' ', array_column($m->calls[1], 'content'))) / 4); t_ok($tok < 8500, "úprava je úsporná: asi $tok tokenů vstupu");
$s->accept($pid, $e['num'], 1);
[$s2, $m2] = $mk([$first, ['reply' => 'Nic', 'ops' => []]]); $r2 = $s2->kitCreate($briefOf($first, 'Škola'), 1);
$x = $s2->aiEdit($r2['id'], 1, 'Je to dobré?'); t_ok($x['num'] === null && $x['reply'] === 'Nic', 'bez změny žádná nová verze');
[$s3, $m3] = $mk([$first, ['reply' => 'Stejné', 'tokens' => ['accent' => $first['tokens']['accent']], 'ops' => [['op' => 'set', 'id' => 'hero', 'variant' => 'split']]]]); $r3 = $s3->kitCreate($briefOf($first, 'Škola'), 1);
$x = $s3->aiEdit($r3['id'], 1, 'Nech to být'); t_ok($x['num'] === null, 'operace bez skutečného rozdílu: žádná nabídka nové verze');
[$s4, $m4] = $mk([$first, ['reply' => 'Přidáno', 'ops' => [['op' => 'set', 'id' => 'kontakt', 'content' => ['phone' => '+420 777 123 456']]]], ['reply' => 'Přidáno', 'ops' => [['op' => 'set', 'id' => 'kontakt', 'content' => ['phone' => '+420 777 123 456']]]]]);
$r4 = $s4->kitCreate($briefOf($first, 'Škola'), 1);
$x = $s4->aiEdit($r4['id'], 1, 'Dej tam lepší telefon');
t_ok($x['dropped'] !== [] && array_column($s4->version($r4['id'], $x['num'], 1)['content']['sections'], null, 'id')['kontakt']['content']['phone'] === '', 'vymyšlený telefon v úpravě se nepoužije', $x);
$x = $s4->aiEdit($r4['id'], 1, 'Telefon je +420 777 123 456');
t_ok(array_column($s4->version($r4['id'], $x['num'], 1)['content']['sections'], null, 'id')['kontakt']['content']['phone'] === '+420 777 123 456', 'údaj z pokynu kolegy platí');

/* --- šablony fungují dál --- */
[$s, $m] = $mk([['content' => [], 'tokens' => []]]);
$r = $s->aiCreate(['name' => 'Bistro', 'obor' => 'restaurace', 'lang' => 'cs'], 1); t_ok($r['template'] === 'restaurace' && $r['id'] > 0, 'návrh ze šablony beze změny');
