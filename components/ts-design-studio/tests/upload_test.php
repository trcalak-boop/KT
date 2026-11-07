<?php
/* Nahraný návrh a připomínky bez sítě a bez AI: rozbalení a jeho ochrany, přehled ze souborů, kontroly, verze, doručení, brána, připomínky, upozornění, přenos do CRM, retence. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir(); ini_set("error_log", "$dir/php.log");
$mail = new class { public array $sent = []; public bool $fail = false; public function send($to, $s, $t, $h, $r = null, $n = '') { if ($this->fail) return ['ok' => false, 'chyba' => 'x']; $this->sent[] = compact('to', 's', 't', 'h', 'r'); return ['ok' => true]; } };
$mk = function (array $x = []) use ($dir, $mail) { static $n = 0; $n++; return ts_design_studio($x + ['db' => ['dsn' => "sqlite:$dir/u$n.sqlite"], 'uploadDir' => "$dir/navrhy$n", 'deliverySecret' => str_repeat('s', 40), 'previewBase' => 'https://nahledy.test/', 'mailer' => $mail, 'feedbackTo' => ['nam@tasstudio.test'], 'crmUrl' => 'https://web.test/admin/#crm/lead']); };
$HTML = '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pekárna Rohlík</title><meta name="description" content="Popis"><link rel="stylesheet" href="css/styl.css"></head><body><h1>Pekárna</h1><section aria-label="Galerie"><img src="img/a.png" alt="A"></section><h2>Nabídka</h2><h2>Kontakt</h2><a href="dalsi.html">Další</a><a href="#kontakt">Kotva</a><script src="js/app.js"></script></body></html>';
$CSS = ':root{--bg:#fff8ea;--ink:rgb(22,50,79);--acc:#E4572E;--f-disp:"Syne",serif}@font-face{font-family:"Syne";src:url("../fonts/syne.woff2") format("woff2");font-weight:700}body{background:var(--bg);color:var(--ink);font:400 17px/1.5 "Figtree",sans-serif}h1{font-family:var(--f-disp);color:var(--acc)}a{color:var(--acc)}b{color:#e4572e80;background:hsl(0,100%,50%)}i{color:transparent;border-color:#0000}@media (prefers-color-scheme:dark){:root{--bg:#101010;--acc:#ffa26b}}[data-theme="dark"] body{background:#202020}';
$FILES = ['index.html' => $HTML, 'dalsi.html' => '<!doctype html><html lang="cs"><head><title>Další</title></head><body><h2>Tým</h2></body></html>', 'css/styl.css' => $CSS, 'js/app.js' => 'console.log(1)', 'img/a.png' => str_repeat("\x89PNG", 400), 'fonts/syne.woff2' => str_repeat('w', 3000)];
$zipOf = fn (array $f) => TsStudioZip::build($f);
/* ruční ZIP pro nebezpečné názvy (TsStudioZip je odmítá) */
$rawZip = function (array $entries) {
    $data = ''; $cd = ''; $n = 0;
    foreach ($entries as $e) { [$name, $raw] = $e; $attr = $e[2] ?? 0; $flags = $e[3] ?? 0; $crc = crc32($raw); $off = strlen($data);
        $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, 0, 0, 0, $crc, strlen($raw), strlen($raw), strlen($name), 0) . $name . $raw;
        $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flags, 0, 0, 0, $crc, strlen($raw), strlen($raw), strlen($name), 0, 0, 0, 0, $attr, $off) . $name; $n++; }
    return $data . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cd), strlen($data), 0);
};

/* --- rozbalení --- */
$u = TsStudioUpload::unpack($zipOf($FILES), 'navrh.zip');
t_ok($u['errors'] === [] && $u['files']['img/a.png'] === $FILES['img/a.png'] && count($u['files']) === 6, 'ZIP se rozbalí beze ztráty', $u['errors']);
$pre = []; foreach ($FILES as $k => $v) $pre["moje-slozka/$k"] = $v; $pre['__MACOSX/._x'] = 'x'; $pre['moje-slozka/.DS_Store'] = 'x';
$u = TsStudioUpload::unpack($zipOf($pre), 'n.zip'); t_ok(isset($u['files']['index.html']) && !isset($u['files']['.DS_Store']) && count($u['files']) === 6, 'společná složka a systémové soubory pryč', array_keys($u['files']));
t_eq(array_keys(TsStudioUpload::unpack('<!doctype html><title>x</title>', 'navrh.html')['files']), ['index.html'], 'jeden HTML soubor');
t_eq(array_keys(TsStudioUpload::unpack($zipOf(['stranka.html' => '<html>', 'a.css' => 'x']), 'n.zip')['files']), ['a.css', 'index.html'], 'jediné HTML v kořeni se stane index.html');
t_ok(TsStudioUpload::unpack($zipOf(['a.html' => 'x', 'b.html' => 'y']), 'n.zip')['errors'] !== [], 'bez index.html a s více stránkami: chyba');
t_ok(TsStudioUpload::unpack('nesmysl', 'a.bin')['errors'] !== [] && TsStudioUpload::unpack('', 'a.zip')['errors'] !== [] && TsStudioUpload::unpack("PK\x03\x04nesmysl", 'a.zip')['errors'] !== [], 'nesmysl, prázdný soubor, poškozený ZIP');
foreach ([['../evil.html', 'cesta nahoru'], ['a/../../evil.html', 'cesta uprostřed'], ['/etc/x.html', 'absolutní cesta'], ['shell.php', 'PHP'], ['x.phtml', 'PHP varianta'], ['.htaccess', '.htaccess'], ['a/.env', 'skrytý soubor'], ['x.html.php', 'dvojitá přípona'], ['evil.exe', 'program'], ['C:\\x.html', 'cesta Windows'], ['a\\..\\..\\b.html', 'zpětná lomítka']] as [$bad, $why]) {
    $r = TsStudioUpload::unpack($rawZip([['index.html', '<html>'], [$bad, 'x']]), 'n.zip'); t_ok($r['errors'] !== [] && $r['files'] === [], "ZIP odmítnut: $why", $r);
}
t_ok(TsStudioUpload::unpack($rawZip([['index.html', '<html>'], ['l.html', 'x', 0120777 << 16]]), 'n.zip')['errors'] !== [], 'symbolický odkaz odmítnut');
t_ok(TsStudioUpload::unpack($rawZip([['index.html', '<html>', 0, 1]]), 'n.zip')['errors'] !== [], 'šifrovaný ZIP odmítnut');
$z = $rawZip([['index.html', '<html>']]); $bad = substr_replace($z, "X", 41, 1); t_ok(TsStudioUpload::unpack($bad, 'n.zip')['errors'] !== [], 'nesedí kontrolní součet');
$bomb = str_repeat('0', 45000000); $r = TsStudioUpload::unpack($zipOf(['index.html' => '<html>', 'a.txt' => $bomb]), 'n.zip'); t_ok($r['errors'] !== [] && strpos($r['errors'][0], '40 MB') !== false, 'ZIP bomba: strop po rozbalení', $r['errors']); unset($bomb);
$many = ['index.html' => '<html>']; for ($i = 0; $i < 900; $i++) $many["a$i.txt"] = 'x'; t_ok(TsStudioUpload::unpack($zipOf($many), 'n.zip')['errors'] !== [], 'strop počtu souborů');
t_eq(array_keys(TsStudioUpload::unpack($zipOf(['index.html' => '<html>', 'obrázky/můj soubor.png' => 'x']), 'n.zip')['files']), ['index.html', 'obrázky/můj soubor.png'], 'diakritika a mezery v názvu');

/* --- přehled --- */
$sizes = array_map('strlen', $FILES); $an = TsStudioInspect::run(array_filter($FILES, fn ($k) => TsStudioUpload::isText($k), ARRAY_FILTER_USE_KEY), $sizes);
t_ok($an['title'] === 'Pekárna Rohlík' && $an['lang'] === 'cs' && $an['description'] === 'Popis' && count($an['pages']) === 2 && $an['pages'][0]['path'] === 'index.html', 'titulek, jazyk, popis, stránky', $an['pages']);
t_ok($an['sections'] === ['Pekárna', 'Nabídka', 'Kontakt', 'Galerie', 'Tým'], 'sekce z nadpisů a aria-label', $an['sections']);
$c = array_column($an['colors'], null, 'value');
t_ok(isset($c['#fff8ea'], $c['#16324f'], $c['#e4572e'], $c['#ff0000']) && $c['#e4572e']['count'] === 2 && in_array('--acc', $c['#e4572e']['vars'], true) && !isset($c['#101010']), 'barvy: hex, rgb, hsl, alfa, velikost písmen, proměnné, bez tmavého režimu', $an['colors']);
t_eq(array_column($an['colorsDark'], 'value'), ['#101010', '#ffa26b', '#202020'], 'tmavý režim zvlášť');
$f = array_column($an['fonts'], null, 'family');
t_ok($f['Syne']['source'] === 'local' && $f['Syne']['weights'] === ['700'] && $f['Figtree']['source'] === 'missing', 'písma: přiložené, proměnná, chybějící', $an['fonts']);
t_ok(array_filter($an['checks'], fn ($x) => $x['code'] === 'font' && $x['level'] === 'warn' && strpos($x['msg'], 'Figtree') !== false) !== [] && $an['stats']['files'] === 6 && $an['stats']['images'] === 1 && $an['stats']['scripts'] === 1, 'upozornění na chybějící písmo, statistiky');
t_eq(array_values(array_filter($an['checks'], fn ($x) => $x['level'] === 'error')), [], 'kompletní návrh bez chyb');
$g = TsStudioInspect::run(['index.html' => '<!doctype html><html lang="en"><head><meta name="viewport" content="x"><title>T</title><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&amp;family=Inter&amp;display=swap" rel="stylesheet"><style>body{font-family:Inter,Arial,sans-serif}h1{font-family:Georgia,serif}</style></head><body></body></html>'], ['index.html' => 10]);
$f = array_column($g['fonts'], 'source', 'family'); t_ok(($f['Playfair Display'] ?? '') === 'google' && ($f['Inter'] ?? '') === 'google' && ($f['Georgia'] ?? '') === 'system', 'Google Fonts a systémová písma', $g['fonts']);
$b = TsStudioInspect::run(['index.html' => '<!doctype html><html><head><title>x</title></head><body><img src="chybi.png"><a href="/kontakt.html">x</a><img src="data:image/png;base64,AAAA"><a href="https://x.test">y</a><a href="mailto:a@b.cz">z</a>[doplnit]<div style="background:url(chybi2.jpg)"></div></body></html>', 'a/b.css' => 'x{background:url(../c.png)}y{background:url(d.png)}'], ['index.html' => 1, 'a/b.css' => 1, 'c.png' => 1]);
$msgs = implode(' | ', array_column($b['checks'], 'msg')); $codes = array_column($b['checks'], 'code');
t_ok(strpos($msgs, 'není: chybi.png') !== false && strpos($msgs, 'chybi2.jpg') !== false && strpos($msgs, 'a/d.png') !== false && strpos($msgs, '../c.png') === false && in_array('absolute', $codes, true) && in_array('viewport', $codes, true) && in_array('todo', $codes, true) && in_array('lang', $codes, true), 'kontroly: chybějící soubory (relativně), odkaz od kořene, viewport, [doplnit]', $msgs);

/* --- verze a doručení --- */
$s = $mk();
$r = $s->uploadDesign($zipOf($FILES), 'navrh.zip', 1, ['name' => 'Pekárna Rohlík', 'lead_ref' => '42', 'lang' => 'cs']);
t_ok($r['id'] === 1 && $r['num'] === 1 && $r['accepted'] && $r['analysis']['title'] === 'Pekárna Rohlík', 'nahrání: projekt a přijatá verze', $r['checks']);
$v = $s->version(1, 1, 1); $p = $s->get(1, 1)['project'];
t_ok($p['template'] === '_upload' && $p['status'] === 'approved_internal' && (int) $p['current_num'] === 1 && $p['lead_ref'] === '42' && $v['status'] === 'accepted', 'projekt je rovnou připravený k odeslání');
t_ok(isset($v['files']['index.html'], $v['content']['manifest']['img/a.png']) && !isset($v['files']['img/a.png']) && $s->uploadFile($v, 'img/a.png') === $FILES['img/a.png'] && $s->uploadFile($v, 'css/styl.css') === $CSS && $s->uploadFile($v, 'neni.css') === null, 'text ve verzi, ostatní na disku');
$af = $s->allFiles(1, 1, 1); t_ok($af['img/a.png'] === $FILES['img/a.png'] && count($af) === 6 && strlen($s->exportZip(1, 1, 1)) > 500, 'export všech souborů');
$h = $s->preview(1, 1, 1); t_ok(strpos($h, 'Content-Security-Policy') !== false && strpos($h, ':root{--bg') !== false && strpos($h, 'image/') !== false, 'náhled do administrace');
$big = ['index.html' => '<!doctype html><html lang="cs"><head><meta name="viewport" content="w"><title>Velký</title></head><body>' . str_repeat('x', 2200000) . '</body></html>'];
$r2 = $s->uploadDesign($zipOf($big), 'velky.zip', 1, ['project' => 1]);
$v2 = $s->version(1, $r2['num'], 1); t_ok($r2['num'] === 2 && $r2['accepted'] && !isset($v2['files']['index.html']) && isset($v2['content']['manifest']['index.html']) && strlen($s->uploadFile($v2, 'index.html')) > 2000000 && (int) $s->get(1, 1)['project']['current_num'] === 2, 'velký HTML soubor na disku, další verze téhož návrhu', $r2['checks']);
$rb = $s->uploadDesign('<!doctype html><html lang="cs"><head><title>x</title></head><body><script src="https://cdn.test/a.js"></script><img src="chybi.png"></body></html>', 'a.html', 1, ['project' => 1]);
t_ok(!$rb['accepted'] && $s->version(1, $rb['num'], 1)['status'] === 'candidate' && (int) $s->get(1, 1)['project']['current_num'] === 2 && count(TsStudioChecks::errors($rb['checks'])) >= 3, 'návrh s chybami (cizí skript, chybějící soubor, viewport) nejde poslat, přijatá verze se nemění', $rb['checks']);
$threw = false; try { $s->prepareDelivery(1, $rb['num'], 1); } catch (InvalidArgumentException $e) { $threw = true; } t_ok($threw, 'odeslání verze s chybami se odmítne');
$threw = false; try { $s->uploadDesign($rawZip([['index.html', '<html>'], ['x.php', '<?php']]), 'a.zip', 1, ['name' => 'Zlý']); } catch (TsStudioUploadError $e) { $threw = $e->errors; }
t_ok($threw && strpos($threw[0], 'x.php') !== false && count($s->projects(1)) === 1, 'nepovolený obsah: chyba a žádný projekt');
$threw = false; try { $s->uploadDesign('<html>', 'a.html', 1, []); } catch (InvalidArgumentException $e) { $threw = true; } t_ok($threw, 'bez názvu se návrh odmítne');
$deny = $mk(['can' => fn ($u, $a) => false]); $threw = false; try { $deny->uploadDesign('<html>', 'a.html', 1, ['name' => 'x']); } catch (TsStudioDenied $e) { $threw = true; } t_ok($threw, 'oprávnění se kontroluje');
$noDir = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/nd.sqlite"]]); $threw = false; try { $noDir->uploadDesign('<html>', 'a.html', 1, ['name' => 'x']); } catch (InvalidArgumentException $e) { $threw = true; } t_ok($threw, 'bez nastaveného úložiště chyba');

$d = $s->prepareDelivery(1, 2, 1, 14);
t_ok(strpos($d['body'], 'přímo na stránce') !== false && strpos($d['subject'], 'Pekárna Rohlík') !== false && !$d['active'], 'výchozí text: připomínky na stránce; odkaz zatím neplatí');
$url = $s->copyLink($d['id'], 1)['url']; $tok = basename(rtrim($url, '/')); $gate = new TsStudioPreviewGate($s, ['contact' => 'tasstudio.eu']);
$G = fn (string $path, string $m = 'GET', array $post = [], array $h = [], string $q = '') => $gate->handle($m, $path, '10.0.0.1', $post, $h, $q);
$H = ['origin' => 'https://nahledy.test', 'host' => 'nahledy.test', 'sec-fetch-site' => 'same-origin'];

/* --- stránka klienta --- */
[$c0, $hd, $b0] = $G("/$tok/");
t_ok($c0 === 200 && strpos($b0, 'sandbox="allow-scripts"') !== false && strpos($b0, "/$tok/navrh/") !== false && strpos($b0, 'name="text"') !== false && strpos($hd['X-Robots-Tag'], 'noindex') !== false && strpos($hd['Content-Security-Policy'], "form-action 'self'") !== false && !isset($hd['Set-Cookie']) && $hd['Referrer-Policy'] === 'same-origin', 'stránka: návrh v sandboxu, formulář, bez indexace a cookies');
t_ok(strpos($b0, '<script') === false && strpos($hd['Content-Security-Policy'], 'script-src') === false && $s->store()->delivery($d['id'])['views'] == 1, 'stránka bez skriptů, otevření se zaznamená');
t_eq($G("/$tok")[0], 301, 'bez lomítka přesměrování');
[$cd, $hd2, $bd] = $G("/$tok/navrh/");
t_ok($cd === 200 && strpos($bd, 'xxx') !== false && strpos($hd2['Content-Security-Policy'], 'sandbox allow-scripts') !== false && strpos($hd2['Content-Security-Policy'], "connect-src 'none'") !== false && strpos($hd2['Content-Security-Policy'], "frame-ancestors 'self'") !== false && $hd2['X-Frame-Options'] === 'SAMEORIGIN', 'návrh: sandbox i při přímém otevření, bez sítě', $hd2);
t_ok(strpos($bd, 'Návrh webu od') === false, 'do kódu návrhu se nic nevkládá');
foreach (['/navrh/../../etc/passwd', '/navrh/%2e%2e/x', '/navrh/neni.css', '/core/bootstrap.php', '/kit/kit.json'] as $x) t_eq($G("/$tok$x")[0], 404, "soubor mimo návrh = 404: $x");
t_eq($G('/' . str_repeat('a', 32) . '/navrh/')[0], 404, 'cizí token = 404');
$ver = $s->uploadDesign($zipOf($FILES), 'n.zip', 1, ['project' => 1]); $d3 = $s->prepareDelivery(1, $ver['num'], 1); $tok3 = basename(rtrim($s->copyLink($d3['id'], 1)['url'], '/'));
[$cc, $hc, $bc] = $G("/$tok3/navrh/fonts/syne.woff2"); t_ok($cc === 200 && $hc['Content-Type'] === 'font/woff2' && $hc['Access-Control-Allow-Origin'] === '*' && $hc['Cross-Origin-Resource-Policy'] === 'cross-origin', 'písmo pro neprůhledný původ', $hc);
t_eq($G("/$tok3/navrh/css/styl.css")[1]['Content-Type'], 'text/css; charset=utf-8', 'typ souboru');
t_ok($G("/$tok3/navrh/img/a.png")[2] === $FILES['img/a.png'], 'obrázek z úložiště');

/* --- připomínky --- */
$mail->sent = [];
$P = fn (array $post, ?array $h = null, ?string $t = null) => $G('/' . ($t ?? $tok3) . '/pripominka', 'POST', $post, $h ?? $H);
[$c1, $h1] = $P(['text' => "Prosím větší logo\n<script>alert(1)</script>", 'cast' => 'Nabídka', 'jmeno' => 'Jana Testová']);
t_ok($c1 === 303 && strpos($h1['Location'], "/$tok3/?stav=ok") === 0, 'připomínka: přesměrování zpět');
$fb = $s->feedbackOf(1, 1);
t_ok(count($fb) === 1 && $fb[0]['section'] === 'Nabídka' && $fb[0]['author'] === 'Jana Testová' && $fb[0]['status'] === 'new' && $fb[0]['notified_at'] !== '', 'uložená připomínka, upozornění odešlo', $fb);
t_ok(count($mail->sent) === 1 && $mail->sent[0]['to'] === 'nam@tasstudio.test' && strpos($mail->sent[0]['s'], 'Připomínka k návrhu: Pekárna Rohlík') === 0 && strpos($mail->sent[0]['t'], 'Prosím větší logo') !== false && strpos($mail->sent[0]['t'], 'https://web.test/admin/#crm/lead/42') !== false && strpos($mail->sent[0]['h'], '<script>') === false, 'e-mail nám: text, odkaz do CRM, escapované HTML', $mail->sent);
[, , $bb] = $G("/$tok3/", 'GET', [], [], 'stav=ok');
t_ok(strpos($bb, 'Děkujeme, připomínku máme') !== false && strpos($bb, 'Prosím větší logo') !== false && strpos($bb, '<script>alert') === false && strpos($bb, '&lt;script&gt;') !== false, 'klient vidí potvrzení a svou připomínku, escapované');
t_eq($P(['text' => "Prosím větší logo\n<script>alert(1)</script>"])[0], 303, 'dvojí odeslání'); t_eq(count($s->feedbackOf(1, 1)), 1, 'stejný text za chvíli se neuloží dvakrát');
t_ok(strpos($P(['text' => '   '])[1]['Location'], 'stav=chyba') !== false && strpos($P(['text' => str_repeat('x', 3100)])[1]['Location'], 'stav=chyba') !== false, 'prázdný a příliš dlouhý text');
$P(['text' => 'Robot', 'web' => 'http://spam']); t_eq(count($s->feedbackOf(1, 1)), 1, 'past na roboty: neuloží se');
$P(['text' => 'Jiná část', 'cast' => 'Vymyšlená']); t_eq($s->feedbackOf(1, 1)[0]['section'], '', 'neznámá část se zahodí');
t_eq($P(['text' => 'x'], ['origin' => 'https://cizi.test', 'host' => 'nahledy.test'])[0], 403, 'cizí původ 403');
t_eq($P(['text' => 'x'], ['host' => 'nahledy.test'])[0], 403, 'bez Origin 403');
t_eq($P(['text' => 'x'], ['sec-fetch-site' => 'cross-site'] + $H)[0], 403, 'cross-site 403');
t_eq($G('/' . str_repeat('a', 32) . '/pripominka', 'POST', ['text' => 'x'], $H)[0], 404, 'POST s cizím tokenem 404');
t_eq($G("/$tok3/navrh/", 'POST', ['text' => 'x'], $H)[0], 405, 'POST jinam 405');
$mail->sent = [];
[$c2, $h2] = $P(['akce' => 'schvalit', 'jmeno' => 'Jana']);
t_ok(strpos($h2['Location'], 'stav=schvaleno') !== false && strpos($mail->sent[0]['s'], 'Návrh schválen') === 0, 'schválení: potvrzení a e-mail');
$P(['akce' => 'schvalit']); t_eq(count($mail->sent), 1, 'druhé schválení nic nepošle');
[, , $bb] = $G("/$tok3/"); t_ok(strpos($bb, 'Návrh jste schválili') !== false && strpos($bb, 'value="schvalit"') === false, 'po schválení se tlačítko neukáže');
/* limit */
$lim = $s->prepareDelivery(1, $ver['num'], 1); $tl = basename(rtrim($s->copyLink($lim['id'], 1)['url'], '/'));
$codes = []; for ($i = 0; $i < 22; $i++) $codes[] = strpos($P(['text' => "Limit $i"], null, $tl)[1]['Location'], 'stav=limit') !== false;
t_ok(!$codes[19] && $codes[20] && $codes[21], 'nejvýš 20 připomínek za hodinu na odkaz');
/* výpadek pošty */
$mail->fail = true; $ml = $s->prepareDelivery(1, $ver['num'], 1); $tm = basename(rtrim($s->copyLink($ml['id'], 1)['url'], '/'));
[$cm] = $P(['text' => 'Během výpadku'], null, $tm); $row = array_values(array_filter($s->feedbackOf(1, 1), fn ($x) => $x['body'] === 'Během výpadku'))[0];
t_ok($cm === 303 && $row['notified_at'] === '', 'výpadek pošty: připomínka zůstane');
$mail->fail = false; t_ok($s->retryFeedbackMail() >= 1 && $s->store()->feedback((int) $row['id'])['notified_at'] !== '', 'další pokus odešle upozornění');
/* CRM */
$un = $s->feedbackUnsynced(); t_ok(count($un) >= 3 && $un[0]['lead_ref'] === '42' && $un[0]['project_name'] === 'Pekárna Rohlík', 'nepřenesené do CRM s číslem leadu');
$s->markFeedbackSynced(array_column($un, 'id')); t_eq($s->feedbackUnsynced(), [], 'po přenosu nic nezbývá');
$n0 = $s->feedbackOpenCount(); $f0 = $s->setFeedbackDone((int) $un[0]['id'], 1, true);
t_ok($f0['status'] === 'done' && $f0['done_by'] == 1 && $s->feedbackOpenCount() === $n0 - 1, 'označení vyřízené a počet otevřených');
[, , $bb] = $G("/$tok3/"); t_ok(strpos($bb, 'vyřešeno') !== false, 'klient vidí stav');
t_ok($s->setFeedbackDone((int) $un[0]['id'], 1, false)['status'] === 'new', 'znovu otevřít');
/* angličtina, odvolání, prošlý odkaz */
$en = $s->uploadDesign($zipOf($FILES), 'n.zip', 1, ['name' => 'English', 'lang' => 'en']); $de = $s->prepareDelivery($en['id'], 1, 1); $te = basename(rtrim($s->copyLink($de['id'], 1)['url'], '/'));
[, , $be] = $G("/$te/"); t_ok(strpos($be, 'Send comment') !== false && strpos($be, 'lang="en"') !== false && strpos($de['subject'], 'Website design') === 0, 'jazyk stránky a e-mailu podle leadu');
$s->revokeDelivery($de['id'], 1); t_ok($G("/$te/")[0] === 404 && $G("/$te/pripominka", 'POST', ['text' => 'x'], $H)[0] === 404, 'odvolaný odkaz: nic nejde zobrazit ani poslat');
$dx = $s->prepareDelivery($en['id'], 1, 1); $tx = basename(rtrim($s->copyLink($dx['id'], 1)['url'], '/')); $s->store()->setDelivery($dx['id'], ['expires_at' => '2020-01-01 00:00:00']);
t_ok($G("/$tx/")[0] === 404 && $G("/$tx/pripominka", 'POST', ['text' => 'x'], $H)[0] === 404, 'prošlý odkaz také');
/* retence */
$pdo = $s->store()->pdo(); $pdo->exec("UPDATE studio_feedback SET created_at = '2020-01-01 00:00:00' WHERE id = " . (int) $un[0]['id']); $s->purgeOldDeliveries(true);
$af0 = $s->store()->feedback((int) $un[0]['id']); t_ok($af0['body'] === '' && $af0['author'] === '', 'retence: staré připomínky se anonymizují');
/* starší šablonové projekty a doručení beze změny */
$st = $s->createProject(['name' => 'Starý', 'source' => 'new', 'template' => 'salon'], 1); $s->setStatus($st['id'], 'approved_internal', 1); $ds = $s->prepareDelivery($st['id'], $st['version'], 1); $ts = basename(rtrim($s->copyLink($ds['id'], 1)['url'], '/'));
[$cs, $hs, $bs] = $G("/$ts/"); t_ok($cs === 200 && strpos($bs, 'Návrh webu od') !== false && strpos($bs, 'name="text"') === false && $G("/$ts/navrh/")[0] === 404, 'návrh ze šablony se v bráně chová jako dřív');
