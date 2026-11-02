<?php
/* ts-design-studio – AI vrstva s falešným modelem (bez sítě): zadání → návrh, úpravy, strop, chyby, pokyny pro model. */
require dirname(__DIR__, 3) . '/tools/phptest.php';
require dirname(__DIR__) . '/core/bootstrap.php';

$dir = t_tmpdir();
$mk = function (array $script, int $limit = 0) use ($dir) {
    static $n = 0; $n++;
    $m = new TsStudioFakeModel(['script' => $script]);
    $s = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/ai$n.sqlite"], 'ai' => ['model' => $m, 'neuronsPerDay' => $limit]]);
    return [$s, $m];
};
$salon = new TsStudioTemplate(dirname(__DIR__) . '/templates/salon');

/* --- čtení odpovědi modelu --- */
t_eq(TsStudioAi::parseJson("```json\n{\"a\": 1}\n```"), ['a' => 1], 'JSON v bloku ```json');
t_eq(TsStudioAi::parseJson('Tady je: {"a": "x } {", "b": {"c": 2}} a konec'), ['a' => 'x } {', 'b' => ['c' => 2]], 'JSON uprostřed textu, závorky v řetězci');
t_eq(TsStudioAi::parseJson('{"a": 1'), null, 'useknutý JSON = null');
t_eq(TsStudioAi::parseJson('bez json'), null, 'žádný JSON = null');
$tk = TsStudioAi::normTokens($salon, ['f_disp' => 1, 'f_body' => '"Outfit"', 'accent' => '#123456', 'neznamy' => '#000000', 'ink' => ['x']]);
t_ok($tk['f_disp'] === $salon->t['tokens']['f_disp']['options'][1] && str_starts_with($tk['f_body'], '"Outfit"') && $tk['accent'] === '#123456' && !isset($tk['neznamy']) && !isset($tk['ink']), 'tokeny od modelu: písmo číslem i názvem z nabídky, neznámé klíče pryč');
t_eq(TsStudioAi::todo(['a' => 'x [doplnit]', 'l' => [['n' => 'ok'], ['n' => '[doplnit]']]]), ['a', 'l.1.n'], 'seznam slotů k doplnění');

/* --- vrstva úprav CSS --- */
$css = TsStudioAi::withAiCss("body{}\n", '.x{color:red}');
t_ok(TsStudioAi::aiCss($css) === '.x{color:red}' && substr_count(TsStudioAi::withAiCss($css, '.y{}'), '/*@ai*/') === 1 && TsStudioAi::aiCss(TsStudioAi::withAiCss($css, '.y{}')) === '.y{}', 'vrstva CSS: vložení a nahrazení (jen jedna)');
t_eq(TsStudioAi::withAiCss($css, ''), "body{}\n", 'prázdná vrstva se odstraní');
t_ok(strpos(TsStudioAi::withAiCss('a{}', '/*@ai-end*/ b{} </style>'), '</style') === false && substr_count(TsStudioAi::withAiCss('a{}', '/*@ai-end*/ b{}'), '/*@ai-end*/') === 1, 'vrstva CSS: model nemůže ukončit vrstvu ani blok stylů');

/* --- osnova a pokyny --- */
$html = $salon->render($salon->t['content'], [])['index.html'];
$o = TsStudioAi::outline($html);
t_ok(strpos($o, 'section#cenik.sec.price-sec') !== false && strpos($o, 'header#top.mast') !== false && preg_match('/article\.member ×3/', $o) && strpos($o, 'svg') === false, 'osnova: sekce s id a třídami, opakování ×N, bez SVG', $o);
$b = TsStudioAi::cleanBrief(['name' => 'Studio Vlna', 'obor' => 'kadeřnictví', 'facts' => "Telefon: +420 777 111 222\x07", 'lang' => 'xx<script>']);
t_ok($b['lang'] === 'cs' && $b['facts'] === 'Telefon: +420 777 111 222' && $b['audit'] === null && !$b['rights'], 'zadání: řídicí znaky pryč, neplatný jazyk → cs');
$pm = TsStudioAi::createPrompt($salon, $b);
t_ok(strpos($pm[0]['content'], 'FAKTA NEVYMÝŠLEJ') !== false && strpos($pm[0]['content'], '[doplnit]') !== false && strpos($pm[1]['content'], 'Studio Vlna') !== false
  && strpos($pm[1]['content'], 'prices: seznam ≤8 × {name text ≤40') !== false && strpos($pm[1]['content'], 'f_disp: Písmo nadpisů a logotypu = ') !== false, 'pokyny pro návrh: pravidla faktů, zadání, schéma slotů, tokeny s nabídkou');
t_ok(mb_strlen($pm[0]['content'] . $pm[1]['content']) < 16000, 'pokyny pro návrh salonu pod 16 000 znaků (≈ 4–5 tisíc tokenů)', mb_strlen($pm[0]['content'] . $pm[1]['content']));

/* --- zdroje zadání --- */
$lb = TsStudioAi::briefFromLead(['name' => 'Pekárna U Mlýna', 'phone' => '+420 600 100 200', 'email' => 'pekarna@example.cz', 'website' => 'https://pekarna.example.cz', 'outreach_reason' => 'starý web bez mobilu'], ['Chtějí objednávky dortů']);
t_ok($lb['name'] === 'Pekárna U Mlýna' && strpos($lb['facts'], 'Telefon: +420 600 100 200') !== false && strpos($lb['notes'], 'Chtějí objednávky dortů') !== false, 'zadání z leadu CRM: název, kontakty, poznámky');
$ab = TsStudioAi::briefFromAudit(['url' => 'https://stary.example.cz/', 'devices' => ['mobile' => ['scores' => ['performance' => 41]]], 'recommendations' => [['basis' => 'measurement', 'text' => 'Zmenšete obrázky'], ['basis' => 'ai', 'text' => 'Málo kontrastní tlačítko']]], 'Vaříme od roku 1998.', ['name' => 'Bistro Staré']);
$abc = TsStudioAi::cleanBrief($ab);
t_ok($abc['rights'] && $abc['audit']['issues'] === ['Měření: Zmenšete obrázky', 'AI: Málo kontrastní tlačítko'] && strpos(TsStudioAi::briefText($abc), 'performance 41') !== false, 'zadání redesignu z auditu: zjištění, skóre, převzaté texty = ověřit práva');
t_ok(strpos(TsStudioAi::createPrompt($salon, $abc)[0]['content'], 'ověřit práva') !== false, 'pokyny redesignu: připomenout práva k převzatému obsahu');

/* --- návrh ze zadání --- */
$answer = ['content' => ['brand' => 'Studio Vlna', 'title' => 'Studio Vlna – kadeřnictví Brno', 'logo_pre' => 'VL', 'logo_accent' => 'N', 'logo_post' => 'A', 'phone' => '+420 777 111 222', 'phone_href' => 'tel:+420777111222',
    'prices' => [['name' => 'Dámský střih', 'p1' => '[doplnit]', 'p2' => '[doplnit]', 'p3' => '[doplnit]']], 'cta_url' => 'javascript:alert(1)', 'ed_lead' => '<b>Ahoj</b>'],
    'tokens' => ['accent' => '#0a5c55', 'paper' => '#ffffff', 'ink' => '#f0f0f0', 'f_disp' => 1], 'notes' => 'Doplnit ceny.'];
[$S, $M] = $mk([$answer]);
$r = $S->aiCreate(['name' => 'Studio Vlna', 'obor' => 'kadeřnictví a barvení', 'facts' => 'Telefon: +420 777 111 222'], 1);
$v = $S->version($r['id'], $r['version'], 1); $c = $v['content'];
t_ok(strpos(end($M->calls[0])['content'], '# Dnes') !== false && strpos($M->calls[0][0]['content'], '(jen ze zadání)') !== false && strpos(end($M->calls[0])['content'], 'reviews: seznam ≤3 × {text text ≤220 (jen ze zadání)') !== false, 'pokyny: dnešní datum a označené sloty jen ze zadání');
t_ok($r['template'] === 'salon' && count($M->calls) === 1, 'návrh: šablona podle oboru bez dotazu na model');
t_ok($c['brand'] === 'Studio Vlna' && $c['address'] === '[doplnit]' && $c['email'] === '' && $c['reviews'] === [] && $c['team'] === [] && $c['cta_url'] === '', 'chybějící sloty = [doplnit] / prázdné, nikdy fakta ze vzorové ukázky; javascript: adresa zahozena');
t_ok(strpos(json_encode($v['files'], JSON_UNESCAPED_UNICODE), 'Mánesova') === false && strpos($v['files']['index.html'], '&lt;b&gt;Ahoj&lt;/b&gt;') !== false, 've vykresleném webu není adresa z ukázky Lokna; HTML od modelu escapované');
t_ok($v['tokens']['paper'] === '#ffffff' && TsStudioTemplate::contrast($v['tokens']['ink'], '#ffffff') >= 4.5 && $v['tokens']['ink'] !== '#f0f0f0' && $v['tokens']['accent'] === '#0a5c55' && in_array('ink', $r['tokensFixed'], true), 'text s nízkým kontrastem se ztmaví (pozadí a ostatní barvy zůstanou)', $v['tokens']);
t_ok($v['author_kind'] === 'ai' && $v['status'] === 'accepted' && in_array('address', $r['todo'], true) && in_array('prices.0.p1', $r['todo'], true) && $r['notes'] === 'Doplnit ceny.', 'verze 1 od AI přijatá, seznam k doplnění, poznámka');
$msgs = $S->messages($r['id'], 1);
t_ok(count($msgs) === 2 && $msgs[0]['role'] === 'user' && strpos($msgs[1]['text'], 'K doplnění: ') !== false, 'konverzace: zadání a odpověď uložené');
$u = $S->usage(); t_ok($u['calls'] === 1 && $u['neurons'] > 0 && $u['model'] === 'fake', 'spotřeba zaznamenaná (dotazy, neurony)', $u);
t_eq($S->get($r['id'], 1)['project']['source'], 'new', 'zdroj projektu: nový');

/* ukotvení citátů, recenzí a jmen v zadání */
t_eq(TsStudioTemplate::ensureContrast('#a3b18a', '#fafafa') === '#a3b18a', false, 'světlá šalvějová na světlém pozadí se ztmaví');
t_ok(TsStudioTemplate::contrast(TsStudioTemplate::ensureContrast('#a3b18a', '#fafafa'), '#fafafa') >= 4.5 && TsStudioTemplate::contrast(TsStudioTemplate::ensureContrast('#333333', '#111111'), '#111111') >= 4.5 && TsStudioTemplate::ensureContrast('#000000', '#ffffff') === '#000000', 'úprava kontrastu: ztmavení i zesvětlení, čitelné barvy beze změny');
$inv = ['content' => ['brand' => 'Salon Vlna', 'cover_quote' => '„Vaše vlasy si zaslouží přirozený lesk.“', 'cover_quote_by' => 'Petra Horká', 'ed_pull' => '„Barvíme šetrně, bez amoniaku.“', 'ed_pull_by' => 'Petra Horká',
    'reviews' => [['text' => 'Skvělá práce s barvou, doporučuji.', 'by' => 'Jana N.'], ['text' => 'Nejlepší střih v Brně.', 'by' => 'Eva K.']],
    'team' => [['role' => 'Majitelka', 'name' => 'Petra Horká', 'quote' => '„Miluji přirozené barvy.“', 'bio' => '12 let praxe.', 'days' => 'Po–Pá'], ['role' => 'Stylistka', 'name' => 'Lucie Malá', 'quote' => '', 'bio' => 'x', 'days' => 'x']]]];
[$S, $M] = $mk([$inv]);
$r = $S->aiCreate(['name' => 'Salon Vlna', 'obor' => 'kadeřnictví', 'facts' => "Majitelka: Petra Horká\nRecenze z Google: „Nejlepší střih v Brně.“ – Eva K.\nMotto: Barvíme šetrně, bez amoniaku."], 1);
$c = $S->version($r['id'], 1, 1)['content'];
t_ok($c['cover_quote'] === '[doplnit]' && $c['cover_quote_by'] === 'Petra Horká' && $c['ed_pull'] === '„Barvíme šetrně, bez amoniaku.“', 'vymyšlený citát → [doplnit]; citát a jméno ze zadání zůstanou', $c);
t_ok($c['reviews'][0] === ['text' => '[doplnit]', 'by' => '[doplnit]'] && $c['reviews'][1] === ['text' => 'Nejlepší střih v Brně.', 'by' => 'Eva K.'], 'vymyšlená recenze → [doplnit], recenze ze zadání zůstane', $c['reviews']);
t_ok($c['team'][0]['name'] === 'Petra Horká' && $c['team'][0]['quote'] === '[doplnit]' && $c['team'][1]['name'] === '[doplnit]', 'tým: jméno ze zadání zůstane, vymyšlené jméno a citát → [doplnit]');
t_ok(in_array('cover_quote', $r['dropped'], true) && in_array('reviews.0.text', $r['dropped'], true) && strpos($S->messages($r['id'], 1)[1]['text'], 'bez podkladu') !== false, 'zahozené texty vypsané v odpovědi Studia');

/* výběr šablony modelem, opakování při nečitelném JSON */
[$S, $M] = $mk(['{"template": "restaurace"}', 'tohle není json', $answer]);
$r = $S->aiCreate(['name' => 'Mlýn', 'obor' => 'rodinný podnik', 'audit' => ['url' => 'https://x.cz/', 'issues' => []]], 1);
t_ok($r['template'] === 'restaurace' && count($M->calls) === 3 && strpos(end($M->calls[2])['content'], 'nebyla platný JSON') !== false, 'neznámý obor → šablonu vybere model; nečitelná odpověď → jedno opakování');
t_eq($S->get($r['id'], 1)['project']['source'], 'redesign', 'zadání s auditem = redesign');
[$S] = $mk(['{"template": "neexistuje"}', 'nic', 'zase nic']);
try { $S->aiCreate(['name' => 'X', 'obor' => 'ostatní'], 1); t_ok(false, 'format'); } catch (TsStudioModelError $e) { t_eq($e->kind, 'format', 'dvakrát nečitelná odpověď → chyba format'); }
t_eq($S->projects(1), [], 'při chybě modelu nevznikne projekt');
[$S] = $mk([['fail' => 'limit']]);
try { $S->aiCreate(['name' => 'X', 'obor' => 'kadeřnictví'], 1); t_ok(false, 'limit'); } catch (TsStudioModelError $e) { t_ok($e->kind === 'limit' && $S->usage()['calls'] === 1, 'vyčerpaná kvóta Cloudflare → chyba limit, neúspěšný dotaz zaznamenán'); }
[$S] = $mk([$answer], 1);
$S->aiCreate(['name' => 'A', 'obor' => 'kadeřnictví'], 1);
try { $S->aiCreate(['name' => 'B', 'obor' => 'kadeřnictví'], 1); t_ok(false, 'strop'); } catch (TsStudioModelError $e) { t_ok($e->kind === 'limit' && count($S->projects(1)) === 1, 'denní strop Studia → další dotaz se nepošle'); }
$deny = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/deny.sqlite"], 'ai' => ['model' => new TsStudioFakeModel(['script' => [$answer]])], 'can' => fn ($u, $a) => $a === 'view']);
try { $deny->aiCreate(['name' => 'A', 'obor' => 'kadeřnictví'], 9); t_ok(false, 'role'); } catch (TsStudioDenied $e) { t_ok(true, 'návrh AI jen s oprávněním edit'); }
$cfgd = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/cfg.sqlite"], 'ai' => ['provider' => 'fake', 'model' => '@cf/google/gemma-4-26b-a4b-it', 'script' => [$answer]]]);
t_ok($cfgd->aiCreate(['name' => 'Konfigurace', 'obor' => 'kadeřnictví'], 1)['template'] === 'salon' && $cfgd->usage()['model'] === 'fake', 'konfigurace: název modelu jako řetězec + poskytovatel → adaptér se sestaví');
try { ts_design_studio(['db' => ['dsn' => "sqlite:$dir/cfg2.sqlite"], 'ai' => ['provider' => 'cloudflare', 'model' => '@cf/x', 'apiKeyFile' => "$dir/neni.txt"]]); t_ok(false, 'klíč'); } catch (TsStudioModelError $e) { t_eq($e->kind, 'auth', 'Cloudflare bez souboru s tokenem → chyba auth při sestavení'); }
$noai = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/noai.sqlite"]]);
try { $noai->aiCreate(['name' => 'A', 'obor' => 'kadeřnictví'], 1); t_ok(false, 'disabled'); } catch (TsStudioModelError $e) { t_eq($e->kind, 'disabled', 'bez nastavené AI → chyba disabled'); }

/* --- konverzační úpravy --- */
[$S, $M] = $mk([$answer,
    ['reply' => 'Změnila jsem nadpis a akcent.', 'content' => ['ed_title' => 'Účesy, ve kterých', 'neznamy' => 'x'], 'tokens' => ['accent' => '#aa2244']],
    ['reply' => 'Zvětšila jsem logotyp.', 'css' => '.logotype{font-size:min(20vw,300px)}'],
    ['reply' => 'Upravila jsem úvod.', 'content' => ['ed_em' => 'přirozeně.']],
    ['reply' => 'Pozadí z obrázku.', 'css' => '.cover{background:url(https://zly.example/x.png)}'],
    ['reply' => 'To nejde, nemáme fotky.'],
    ['reply' => 'Šalvějová.', 'content' => ['brand' => 'Studio Vlna', 'ed_em' => 'přirozeně.'], 'tokens' => ['accent' => '#a3b18a', 'paper' => '#FFFFFF']],
]);
$r = $S->aiCreate(['name' => 'Studio Vlna', 'obor' => 'kadeřnictví'], 1); $pid = $r['id'];
$e = $S->aiEdit($pid, 1, 'Nadpis úvodníku změň a akcent dej vínový.');
$v2 = $S->version($pid, $e['num'], 1);
t_ok($e['num'] === 2 && $v2['status'] === 'candidate' && $v2['kind'] === 'ai' && $v2['content']['ed_title'] === 'Účesy, ve kterých' && $v2['tokens']['accent'] === '#aa2244' && strpos($v2['files']['assets/styles.css'], '--ac:#aa2244') !== false, 'úprava obsahu a tokenu → nová verze-kandidát vykreslená ze šablony');
t_ok($e['changed'] === ['ed_title', 'token:accent'] && $e['reply'] === 'Změnila jsem nadpis a akcent.', 'změněné klíče (neznámé slotty zahozené) a odpověď');
t_ok(strpos(end($M->calls[1])['content'], '# Současná pravidla klíčových prvků') !== false && strpos(end($M->calls[1])['content'], '.logotype{') !== false && strpos($M->calls[1][0]['content'], 'nikdy em ani %') !== false, 'pokyny úpravy: současná pravidla klíčových prvků a zákaz em/% u velikostí');
$prompt = end($M->calls[1])['content'];
t_ok(strpos($prompt, 'Nadpis úvodníku změň') !== false && strpos($prompt, 'section#cenik.sec.price-sec') !== false && strpos($prompt, '"brand":"Studio Vlna"') !== false && strpos($prompt, '(prázdná)') !== false, 'pokyny úpravy: pokyn, osnova, současný obsah, vrstva CSS');
$S->accept($pid, 2, 1);
$e = $S->aiEdit($pid, 1, 'Zvětši logotyp.'); $v3 = $S->version($pid, $e['num'], 1);
t_ok(TsStudioAi::aiCss($v3['files']['assets/styles.css']) === '.logotype{font-size:min(20vw,300px)}' && $v3['content'] === $v2['content'] && !TsStudioChecks::errors($e['checks']), 'úprava jen vrstvou CSS (obsah beze změny, kontroly v pořádku)');
$S->accept($pid, 3, 1);
$e = $S->aiEdit($pid, 1, 'Konec nadpisu: přirozeně.'); $v4 = $S->version($pid, $e['num'], 1);
t_ok($v4['content']['ed_em'] === 'přirozeně.' && TsStudioAi::aiCss($v4['files']['assets/styles.css']) === '.logotype{font-size:min(20vw,300px)}', 'nové vykreslení obsahu zachová vrstvu CSS');
$S->accept($pid, 4, 1);
$e = $S->aiEdit($pid, 1, 'Dej na obálku fotku.');
t_ok(in_array('css-external', array_column(TsStudioChecks::errors($e['checks']), 'code'), true), 'cizí zdroj ve vrstvě CSS → chyba kontrol');
try { $S->accept($pid, $e['num'], 1); t_ok(false, 'accept'); } catch (TsStudioChecksFailed $x) { t_ok(true, 'verzi s cizím zdrojem nejde přijmout'); }
$e = $S->aiEdit($pid, 1, 'Přidej fotky týmu.');
t_ok($e['num'] === null && $e['reply'] === 'To nejde, nemáme fotky.', 'bez změny → žádná nová verze, jen odpověď');
$hist = array_column($S->messages($pid, 1), 'role');
t_ok(count($hist) === 12 && $hist[10] === 'user' && $hist[11] === 'assistant', 'konverzace projektu: pokyny i odpovědi uložené', count($hist));
$e = $S->aiEdit($pid, 1, 'Akcent šalvějový.'); $v6 = $S->version($pid, $e['num'], 1);
t_ok($e['changed'] === ['token:accent'] && $v6['tokens']['accent'] !== '#a3b18a' && TsStudioTemplate::contrast($v6['tokens']['accent'], $v6['tokens']['paper']) >= 4.5 && !TsStudioChecks::errors($e['checks']) && strpos($e['reply'], 'čitelnosti') !== false, 'úprava: neměněné sloty se nepočítají, světlý akcent se ztmaví a verze projde kontrolami', $e);
$t = $S->renderVersion($pid, 1, $v4['content'], $v4['tokens'], 'ruční vykreslení');
t_ok(TsStudioAi::aiCss($S->version($pid, $t['num'], 1)['files']['assets/styles.css']) === '.logotype{font-size:min(20vw,300px)}', 'ruční vykreslení ze šablony také zachová vrstvu CSS');
t_ok(in_array('version.ai', array_column($S->get($pid, 1)['log'], 'action'), true), 'záznam akce version.ai');


