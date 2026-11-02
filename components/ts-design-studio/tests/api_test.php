<?php
/* ts-design-studio – HTTP API administrace (TsStudioApi::handle) s falešným modelem, bez sítě. */
require dirname(__DIR__, 3) . '/tools/phptest.php';
require dirname(__DIR__) . '/core/bootstrap.php';

$dir = t_tmpdir(); $O = 'https://admin.example';
$answer = ['content' => ['brand' => 'Salon Vlna', 'phone' => '+420 777 111 222', 'phone_href' => 'tel:+420777111222'], 'tokens' => ['accent' => '#4d6b57'], 'notes' => 'Doplnit ceny.'];
$model = new TsStudioFakeModel(['script' => [$answer, ['reply' => 'Hotovo.', 'tokens' => ['accent' => '#335544']]]]);
$S = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/api.sqlite"], 'ai' => ['model' => $model, 'neuronsPerDay' => 5000]]);
$api = new TsStudioApi($S, ['uid' => 1, 'jobsDir' => "$dir/ulohy", 'allowedOrigins' => [$O]]);
$H = ['origin' => $O, 'sec-fetch-site' => 'same-origin', 'content-type' => 'application/json'];
$post = function (string $r, array $b, ?array $h = null) use ($api, $H) { $x = $api->handle('POST', $r, json_encode($b), [], $h ?? $H); if (isset($x[2])) ($x[2])(); return $x; };
$get = fn (string $r, array $q = []) => $api->handle('GET', $r, '', $q, []);
$job = function (array $x) use ($get) { return $get('job', ['id' => $x[1]['job']])[1]; };

/* --- ochrana POST --- */
t_eq($post('create', ['name' => 'X', 'template' => 'salon'], ['origin' => 'https://zly.example', 'content-type' => 'application/json'])[0], 403, 'POST z cizího původu → 403');
t_eq($post('create', ['name' => 'X', 'template' => 'salon'], array_merge($H, ['sec-fetch-site' => 'cross-site']))[0], 403, 'POST cross-site → 403');
t_eq($post('create', ['name' => 'X', 'template' => 'salon'], ['origin' => $O, 'content-type' => 'text/plain'])[0], 415, 'POST bez JSON → 415');
t_eq($api->handle('POST', 'create', '{nejson', [], $H)[0], 400, 'neplatný JSON → 400');
t_eq($api->handle('POST', 'create', str_repeat('x', 1600000), [], $H)[0], 413, 'příliš velké tělo → 413');
t_eq($api->handle('DELETE', 'list', '', [], [])[0], 405, 'jiná metoda → 405');
t_eq($get('neni')[0], 404, 'neznámá trasa → 404');

/* --- šablony, ruční projekt, verze, soubory --- */
[$c, $o] = $get('templates');
t_ok($c === 200 && in_array('salon', array_column($o['templates'], 'id'), true) && in_array('restaurace', array_column($o['templates'], 'id'), true) && $o['ai'] === true, 'šablony a informace o AI');
[$c, $o] = $post('create', ['name' => 'Ruční salon', 'template' => 'salon', 'source' => 'new']);
$pid = $o['id']; t_ok($c === 201 && $o['version'] === 1, 'ruční projekt ze šablony (201, verze 1)');
t_eq($post('create', ['name' => '', 'template' => 'salon'])[0], 400, 'projekt bez názvu → 400');
[$c, $o] = $get('project', ['pid' => $pid]);
t_ok($c === 200 && $o['project']['name'] === 'Ruční salon' && count($o['versions']) === 1 && isset($o['usage']['limit']), 'detail projektu: verze, spotřeba');
[$c, $v] = $get('version', ['pid' => $pid, 'num' => 1]);
$paths = array_column($v['files'], 'path');
t_ok($c === 200 && in_array('index.html', $paths, true) && in_array('assets/fonts/Syne-OFL.txt', $paths, true) && isset($v['schema']['slots']['brand']) && $v['status'] === 'accepted', 'verze: soubory včetně písem ze šablony, schéma slotů, stav');
t_ok(count(array_filter($v['files'], fn ($f) => !$f['text'])) > 0, 'binární soubory označené (text = false)');
[$c, $f] = $get('file', ['pid' => $pid, 'num' => 1, 'path' => 'assets/styles.css']);
t_ok($c === 200 && strpos($f['source'], '/*@tokens*/') !== false, 'zdroj textového souboru');
t_eq($get('file', ['pid' => $pid, 'num' => 1, 'path' => '../core/TsStudioAi.php'])[0], 404, 'soubor mimo verzi → 404');

/* --- ruční úpravy: obsah a barvy, kód, kontroly, přijetí, návrat --- */
[$c, $o] = $post('render', ['pid' => $pid, 'content' => ['brand' => 'Salon Mořská'], 'tokens' => ['accent' => '#225588'], 'message' => 'Název a akcent']);
t_ok($c === 201 && $o['num'] === 2, 'úprava obsahu a barev → verze 2');
$v2 = $get('version', ['pid' => $pid, 'num' => 2])[1];
t_ok($v2['content']['brand'] === 'Salon Mořská' && $v2['tokens']['accent'] === '#225588' && $v2['content']['address'] === $v['content']['address'] && $v2['message'] === 'Název a akcent' && $v2['status'] === 'candidate', 'render: ostatní obsah z výchozí verze, popis, návrh');
$html = $get('file', ['pid' => $pid, 'num' => 2, 'path' => 'index.html'])[1]['source'];
[$c, $o] = $post('patch', ['pid' => $pid, 'base' => 2, 'files' => ['index.html' => str_replace('</main>', '<section class="sec"><h2>Novinka</h2></section></main>', $html)], 'message' => 'Sekce novinka']);
t_ok($c === 201 && $o['num'] === 3 && !TsStudioChecks::errors($o['checks']), 'ruční úprava kódu z verze 2 → verze 3 bez chyb');
[$c, $o] = $post('patch', ['pid' => $pid, 'base' => 3, 'files' => ['index.html' => str_replace('</main>', '<script src="https://zly.example/x.js"></script></main>', $html)]]);
t_ok($c === 201 && in_array('external', array_column(TsStudioChecks::errors($o['checks']), 'code'), true), 'cizí skript → verze s chybou kontrol');
[$c, $o] = $post('accept', ['pid' => $pid, 'num' => 4]);
t_ok($c === 422 && $o['error'] === 'checks' && $o['errors'], 'přijetí verze s chybou → 422 se seznamem chyb');
t_eq($post('patch', ['pid' => $pid, 'files' => ['../evil.php' => 'x']])[0], 400, 'úprava souboru mimo projekt → 400');
t_eq($post('accept', ['pid' => $pid, 'num' => 3])[0], 200, 'přijetí verze 3');
t_eq($post('reject', ['pid' => $pid, 'num' => 4])[0], 200, 'zamítnutí verze 4');
[$c, $d] = $get('diff', ['pid' => $pid, 'a' => 1, 'b' => 3]);
t_ok($c === 200 && $d['files']['index.html']['status'] === 'changed' && strpos($d['diff']['index.html'], '+<section class="sec"><h2>Novinka</h2></section></main>') !== false, 'rozdíl verzí 1 a 3 (sjednocený výpis)', $d['files']);
[$c, $o] = $post('revert', ['pid' => $pid, 'num' => 1]);
t_ok($c === 201 && $o['num'] === 5, 'návrat k verzi 1 → nová verze 5');
t_eq($post('status', ['pid' => $pid, 'status' => 'in_review'])[0], 200, 'stav konceptu: ke kontrole');
t_eq($post('status', ['pid' => $pid, 'status' => 'sent'])[0], 400, 'neplatný stav → 400');
[$c, $p] = $get('preview', ['pid' => $pid, 'num' => 3]);
t_ok($c === 200 && strpos($p['html'], "default-src 'none'") !== false && strpos($p['html'], 'Novinka') !== false, 'náhled verze s CSP');
[$c, $z] = $get('export', ['pid' => $pid, 'num' => 3]);
t_ok($c === 200 && str_starts_with($z['zip'], "PK\x03\x04") && $z['name'] === 'rucni-salon-v3.zip', 'export ZIP s čitelným názvem', $z['name'] ?? '');

/* --- AI přes úlohy --- */
$x = $post('ai-create', ['source' => 'new', 'brief' => ['name' => 'Salon Vlna', 'obor' => 'kadeřnictví']]);
t_eq($x[0], 202, 'návrh AI → 202 s číslem úlohy');
$j = $job($x);
t_ok($j['status'] === 'done' && $j['result']['template'] === 'salon' && isset($j['seconds']) && !isset($j['uid']), 'úloha hotová: výsledek, čas, bez čísla uživatele', $j);
$apid = $j['result']['id'];
$j = $job($post('ai-edit', ['pid' => $apid, 'text' => 'Akcent tmavší.']));
t_ok($j['status'] === 'done' && $j['result']['num'] === 2 && $j['result']['reply'] === 'Hotovo.', 'úprava AI přes úlohu → verze 2');
t_eq($post('ai-edit', ['pid' => $apid, 'text' => '  '])[0], 400, 'prázdný pokyn → 400 bez úlohy');
$x = $post('ai-create', ['source' => 'crm', 'lead' => ['name' => 'Pekárna Kvásek', 'phone' => '+420 606 222 999'], 'brief' => ['obor' => 'kadeřnictví']]);
t_eq($S->get($job($x)['result']['id'], 1)['project']['source'], 'crm', 'návrh z leadu CRM');
t_eq($get('job', ['id' => 'abcd'])[0], 404, 'neplatné číslo úlohy → 404');
$api2 = new TsStudioApi($S, ['uid' => 2, 'jobsDir' => "$dir/ulohy", 'allowedOrigins' => [$O]]);
t_eq($api2->handle('GET', 'job', '', ['id' => $x[1]['job']], [])[0], 404, 'úlohu jiného uživatele nejde přečíst');

$lim = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/lim.sqlite"], 'ai' => ['model' => new TsStudioFakeModel(['script' => [$answer]]), 'neuronsPerDay' => 1]]);
$apiL = new TsStudioApi($lim, ['uid' => 1, 'jobsDir' => "$dir/ulohy", 'allowedOrigins' => [$O]]);
$x = $apiL->handle('POST', 'ai-create', json_encode(['brief' => ['name' => 'A', 'obor' => 'kadeřnictví']]), [], $H); ($x[2])();
$x = $apiL->handle('POST', 'ai-create', json_encode(['brief' => ['name' => 'B', 'obor' => 'kadeřnictví']]), [], $H);
t_ok($x[0] === 429 && $x[1]['kind'] === 'limit' && !isset($x[2]), 'vyčerpaný strop → 429 hned, žádná úloha');
$noai = new TsStudioApi(ts_design_studio(['db' => ['dsn' => "sqlite:$dir/noai.sqlite"]]), ['uid' => 1, 'jobsDir' => "$dir/ulohy", 'allowedOrigins' => [$O]]);
$x = $noai->handle('POST', 'ai-create', json_encode(['brief' => ['name' => 'A']]), [], $H);
t_ok($x[0] === 503 && $x[1]['kind'] === 'disabled' && $noai->handle('GET', 'templates', '', [], [])[1]['ai'] === false, 'bez AI → 503 disabled, šablony hlásí ai = false');
$ro = new TsStudioApi(ts_design_studio(['db' => ['dsn' => "sqlite:$dir/api.sqlite"], 'can' => fn ($u, $a) => $a === 'view']), ['uid' => 7, 'jobsDir' => "$dir/ulohy", 'allowedOrigins' => [$O]]);
t_ok($ro->handle('GET', 'project', '', ['pid' => $pid], [])[0] === 200 && $ro->handle('POST', 'accept', json_encode(['pid' => $pid, 'num' => 3]), [], $H)[0] === 403 && $ro->handle('GET', 'export', '', ['pid' => $pid, 'num' => 3], [])[0] === 403, 'jen prohlížení: detail ano, přijetí a export 403');

/* --- doručení přes API (role sales/designer, falešný odesílač) --- */
final class ApiFakeMail { public array $sent = []; public function send(string $to, string $s, string $t, string $h, ?string $r = null, string $n = ''): array { $this->sent[] = [$to, $s, $t, $r, $n]; return ['ok' => true]; } }
$fm = new ApiFakeMail(); $rolesA = [1 => 'admin', 2 => 'designer', 3 => 'sales'];
$SD = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/del.sqlite"], 'deliverySecret' => str_repeat('s', 40), 'previewBase' => 'https://nahledy.example/', 'mailer' => $fm,
    'can' => fn ($u, $a) => TsStudioRoles::allows($rolesA[$u] ?? '', $a)]);
$mkApi = fn (int $uid) => new TsStudioApi($SD, ['uid' => $uid, 'role' => $rolesA[$uid], 'sender' => ['name' => 'Matouš', 'email' => 'matous@tasstudio.example'], 'jobsDir' => "$dir/ulohy", 'allowedOrigins' => [$O]]);
$des = $mkApi(2); $sal = $mkApi(3);
$P = fn (TsStudioApi $a, string $r, array $b) => $a->handle('POST', $r, json_encode($b), [], $H);
$t = $des->handle('GET', 'templates', '', [], [])[1];
t_ok($t['role'] === 'designer' && in_array('status', $t['actions'], true) && !in_array('deliver', $t['actions'], true) && $t['delivery'] === true, 'šablony: role, povolené akce a zapnuté doručení pro rozhraní');
$dp = $P($des, 'create', ['name' => 'Salon API', 'template' => 'salon'])[1]['id'];
t_eq($P($sal, 'delivery-prepare', ['pid' => $dp, 'num' => 1])[0], 400, 'příprava před schválením → 400');
t_eq($P($sal, 'status', ['pid' => $dp, 'status' => 'approved_internal'])[0], 403, 'sales nemůže schválit → 403');
t_eq($P($des, 'status', ['pid' => $dp, 'status' => 'approved_internal'])[0], 200, 'designer schválí interně');
t_eq($P($des, 'delivery-prepare', ['pid' => $dp, 'num' => 1])[0], 403, 'designer nemůže připravit sdílení → 403');
[$c, $o] = $P($sal, 'delivery-prepare', ['pid' => $dp, 'num' => 1, 'days' => 10]); $did = $o['delivery']['id'];
t_ok($c === 201 && $o['delivery']['status'] === 'prepared' && $o['delivery']['days'] === 10 && !isset($o['delivery']['url']), 'sales připraví sdílení (bez odkazu v odpovědi)');
[$c, $o] = $P($sal, 'delivery-draft', ['id' => $did, 'to_email' => 'klient@example.cz', 'subject' => 'Návrh', 'body' => "Dobrý den,\n{odkaz}\n{jmeno}"]);
t_ok($c === 200 && $o['delivery']['status'] === 'ready_to_send', 'e-mail připravený');
[$c, $o] = $sal->handle('GET', 'delivery', '', ['id' => $did], []);
t_ok($c === 200 && strpos($o['delivery']['message']['text'], '[odkaz se aktivuje odesláním]') !== false && strpos($o['delivery']['message']['text'], 'Matouš') !== false, 'náhled e-mailu: podpis pracovníka, odkaz skrytý do odeslání');
t_eq($P($sal, 'delivery-send', ['id' => $did, 'confirm' => 'jiny@example.cz', 'idem' => 'abcdefgh1'])[0], 400, 'odeslání s nesprávným potvrzením → 400');
[$c, $o] = $P($sal, 'delivery-send', ['id' => $did, 'confirm' => 'klient@example.cz', 'idem' => 'abcdefgh1']);
t_ok($c === 200 && $o['delivery']['status'] === 'sent' && count($fm->sent) === 1 && $fm->sent[0][3] === 'matous@tasstudio.example', 'odesláno: jeden e-mail, Reply-To pracovníka');
$P($sal, 'delivery-send', ['id' => $did, 'confirm' => 'klient@example.cz', 'idem' => 'abcdefgh1']); t_eq(count($fm->sent), 1, 'opakovaný požadavek se stejným klíčem neodešle druhý e-mail');
[$c, $o] = $sal->handle('GET', 'deliveries', '', ['pid' => $dp], []);
t_ok($c === 200 && count($o['deliveries']) === 1 && $o['deliveries'][0]['active'] && !isset($o['deliveries'][0]['token_hash']), 'seznam doručení bez otisků tokenů');
[$c, $o] = $P($sal, 'delivery-link', ['id' => $did]);
t_ok($c === 200 && str_starts_with($o['delivery']['url'], 'https://nahledy.example/'), 'zkopírování odkazu vrátí adresu');
$x = $sal->handle('GET', 'export', '', ['pid' => $dp, 'num' => 1, 'delivery' => $did], []);
t_ok($x[0] === 200 && in_array('delivery.download', array_column($SD->get($dp, 1)['log'], 'action'), true), 'stažení balíčku k doručení zaznamenané');
t_eq($P($des, 'delivery-revoke', ['id' => $did])[0], 403, 'designer nemůže odvolat → 403');
[$c, $o] = $P($sal, 'delivery-revoke', ['id' => $did]); t_ok($c === 200 && $o['delivery']['status'] === 'revoked', 'sales odvolá odkaz');
