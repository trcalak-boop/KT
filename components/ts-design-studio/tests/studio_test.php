<?php
/* Design Studio bez AI a bez sítě: jazyk šablon (escapování), kontrola obsahu a tokenů, kontrast, diff, kontroly kvality,
   ZIP, tok projektu (verze, přijetí, zamítnutí, návrat), náhled pro sandbox, oprávnění, záznam akcí. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir();
$T = new TsStudioTemplate(__DIR__ . '/fixtures/mini');

/* --- jazyk šablon --- */
t_eq(TsStudioTemplate::mustache('<b>{{a}}</b>', ['a' => '<script>x</script> & "q"']), '<b>&lt;script&gt;x&lt;/script&gt; &amp; &quot;q&quot;</b>', 'šablona: vše escapované (žádný raw výstup)');
t_eq(TsStudioTemplate::mustache('{{{a}}}', ['a' => '<i>']), '{&lt;i&gt;}', 'trojité závorky nejsou raw výstup');
t_eq(TsStudioTemplate::mustache('{{#l}}[{{n}}]{{/l}}{{^l}}prázdné{{/l}}', ['l' => [['n' => 1], ['n' => 2]]]), '[1][2]', 'seznam se opakuje');
t_eq(TsStudioTemplate::mustache('{{#l}}x{{/l}}{{^l}}prázdné{{/l}}', ['l' => []]), 'prázdné', 'prázdný seznam → inverzní blok');
t_eq(TsStudioTemplate::mustache('{{a.b}} {{#a}}{{b}}{{/a}} {{neni}}|', ['a' => ['b' => 'B']]), 'B B |', 'tečková cesta, vnoření, neznámý klíč = prázdný');
t_eq(TsStudioTemplate::mustache('{{#t}}<p>{{t}}</p>{{/t}}', ['t' => 'ano']), '<p>ano</p>', 'text jako podmínka „není prázdné“');

/* --- obsah a tokeny --- */
[$c, $e] = $T->cleanContent(['brand' => str_repeat('x', 50), 'cta_url' => 'javascript:alert(1)', 'email' => 'neni', 'phone' => 'abc', 'neznamy' => 'x',
  'services' => array_fill(0, 6, ['name' => "Služba\x07", 'desc' => 'Popis', 'zly' => 1])]);
t_ok(mb_strlen($c['brand']) === 40 && $c['cta_url'] === '' && $c['email'] === '' && $c['phone'] === '' && !isset($c['neznamy']) && count($c['services']) === 4 && $c['services'][0] === ['name' => 'Služba', 'desc' => 'Popis'], 'obsah: délka, javascript: adresa, e-mail, telefon, neznámé klíče, počet položek, řídicí znaky');
t_ok(count($e) >= 5 && in_array('cta_url: neplatná adresa', $e, true), 'obsah: chyby pro uživatele/AI', $e);
[$c] = $T->cleanContent([]); t_eq($c['title'], 'Ahoj světe', 'chybějící slot → výchozí obsah šablony');
[$c, $e] = $T->cleanContent(['tone' => 'red;background:url(x)', 'services' => [['name' => 'A'], 'zly', ['name' => str_repeat('n', 70)]]]);
t_ok($c['tone'] === '' && in_array('tone: barva má být ve tvaru #rrggbb', $e, true) && in_array('services[1]: má být objekt', $e, true) && in_array('services[2].name: nejvýš 60 znaků', $e, true), 'obsah: slot barva jen #rrggbb, chyby v seznamu s indexem položky', $e);
[$c] = $T->cleanContent(['tone' => '#AA00cc']); t_eq($c['tone'], '#AA00cc', 'obsah: platná barva projde');
[$tk, $e] = $T->cleanTokens(['bg' => 'red', 'font' => 'Comic Sans', 'accent' => '#00AA00']);
t_ok($tk['bg'] === '#ffffff' && $tk['font'] === '"Mini Sans",system-ui' && $tk['accent'] === '#00aa00' && count($e) === 2, 'tokeny: barva #rrggbb, písmo jen z nabídky šablony');
t_ok(str_starts_with($T->css('/*@tokens*/:root{--bg:#fff}/*@end*/body{}', ['bg' => '#000000']), '/*@tokens*/:root{--bg:#000000;--ink:#111111;--accent:#c00000;--f:"Mini Sans",system-ui}/*@end*/'), 'tokeny v CSS nahradí blok @tokens');
t_eq(TsStudioTemplate::contrast('#000000', '#ffffff'), 21.0, 'kontrast černá/bílá 21:1');

/* --- diff --- */
$d = TsStudioDiff::lines("a\nb\nc", "a\nB\nc\nd");
t_eq($d, [['=', 'a'], ['-', 'b'], ['+', 'B'], ['=', 'c'], ['+', 'd']], 'řádkový diff');
$u = TsStudioDiff::unified(implode("\n", range(1, 30)), str_replace("\n15\n", "\n15x\n", implode("\n", range(1, 30))), 'x.txt', 3);
t_ok(strpos($u, "@@ -12,7 +12,7 @@\n 12\n 13\n 14\n-15\n+15x\n 16") !== false && substr_count($u, '@@ -') === 1, 'unified diff: jeden blok s kontextem 3 řádky', $u);
$u2 = TsStudioDiff::unified(implode("\n", range(1, 40)), str_replace(["\n5\n", "\n35\n"], ["\n5x\n", "\n35x\n"], implode("\n", range(1, 40))), 'x.txt');
t_eq(substr_count($u2, '@@ -'), 2, 'unified diff: vzdálené změny ve dvou blocích');
t_eq(TsStudioDiff::files(['a' => "1\n2", 'b' => 'x'], ['a' => "1\n3", 'c' => 'y']), ['a' => ['status' => 'changed', 'plus' => 1, 'minus' => 1], 'b' => ['status' => 'removed', 'plus' => 0, 'minus' => 1], 'c' => ['status' => 'added', 'plus' => 1, 'minus' => 0]], 'souhrn změněných souborů');

/* --- kontroly kvality --- */
$ok = $T->render([], []);
t_eq(TsStudioChecks::errors(TsStudioChecks::run($ok, $T)), [], 'výchozí šablona projde kontrolami bez chyb');
$codes = function (array $files) use ($T) { return array_column(TsStudioChecks::run($files, $T), 'code'); };
$bad = $ok; $bad['index.html'] = str_replace('</main>', '', $bad['index.html']); t_ok(in_array('tag', $codes($bad), true), 'kontrola: neuzavřený prvek');
$bad = $ok; $bad['index.html'] = str_replace('<h1>', '</div><h1>', $bad['index.html']); t_ok(in_array('tag', $codes($bad), true), 'kontrola: uzavírací značka bez otevření');
$bad = $ok; $bad['index.html'] .= '{{title}}'; t_ok(in_array('placeholder', $codes($bad), true), 'kontrola: nevyplněný zástupný text');
$bad = $ok; $bad['index.html'] = str_replace('</body>', '<script src="https://zly.example/x.js"></script></body>', $bad['index.html']); t_ok(in_array('external', $codes($bad), true), 'kontrola: cizí skript');
$bad = $ok; $bad['index.html'] = str_replace('</body>', '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter"></body>', $bad['index.html']); t_ok(!in_array('external', $codes($bad), true), 'kontrola: písma Google povolena');
$bad = $ok; $bad['index.html'] = str_replace('href="kontakt.html"', 'href="javascript:alert(1)"', $bad['index.html']); t_ok(in_array('js-url', $codes($bad), true), 'kontrola: odkaz javascript:');
$bad = $ok; $bad['index.html'] = str_replace('</main>', '<iframe src="x"></iframe></main>', $bad['index.html']); t_ok(in_array('tag-forbidden', $codes($bad), true), 'kontrola: iframe zakázán');
$bad = $ok; $bad['index.html'] = str_replace('</main>', '<form action="https://zly.example/sber"></form></main>', $bad['index.html']); t_ok(in_array('form-external', $codes($bad), true), 'kontrola: formulář na cizí adresu');
$bad = $ok; $bad['index.html'] = str_replace('kontakt.html', 'neni.html', $bad['index.html']); t_ok(in_array('link', $codes($bad), true), 'kontrola: odkaz na neexistující soubor');
$bad = $ok; $bad['assets/styles.css'] .= 'a{color:red'; t_ok(in_array('css-braces', $codes($bad), true), 'kontrola: nepárové závorky v CSS');
$bad = $ok; $bad['assets/styles.css'] .= 'html{scroll-behavior:smooth}'; t_ok(!in_array('css-script', $codes($bad), true), 'kontrola: scroll-behavior není skript');
$bad = $ok; $bad['assets/styles.css'] .= 'a{behavior:url(x.htc)}'; t_ok(in_array('css-script', $codes($bad), true), 'kontrola: behavior: (IE) je skript');
$bad = $ok; $bad['assets/app.js'] = "fetch('/x'); document.cookie;"; $r = TsStudioChecks::run($bad, $T);
t_ok(count(array_filter($r, fn ($x) => $x['code'] === 'js-risk')) === 2 && TsStudioChecks::errors($r), 'kontrola: JS – cookie je chyba, síťový požadavek varování');
$bad = $ok; $bad['index.html'] = str_replace('<img', '<img', $bad['index.html']) . ''; $bad['index.html'] = str_replace('</main>', '<img src="assets/fonts/mini.woff2"></main>', $bad['index.html']); t_ok(in_array('img-alt', $codes($bad), true), 'kontrola: obrázek bez alt (varování)');
$r = TsStudioChecks::run($ok, $T, ['ink' => '#eeeeee', 'bg' => '#ffffff']); t_ok(in_array('contrast', array_column(TsStudioChecks::errors($r), 'code'), true), 'kontrola: nízký kontrast tokenů je chyba');

/* --- ZIP --- */
$zip = TsStudioZip::build(['index.html' => '<p>č</p>', 'assets/a.bin' => random_bytes(300), 'assets/b.txt' => str_repeat('opakuj ', 500)]);
file_put_contents("$dir/t.zip", $zip);
$list = shell_exec('unzip -Z1 ' . escapeshellarg("$dir/t.zip") . ' 2>&1'); $one = shell_exec('unzip -p ' . escapeshellarg("$dir/t.zip") . ' index.html 2>&1');
$test = shell_exec('unzip -t ' . escapeshellarg("$dir/t.zip") . ' 2>&1');
t_ok(trim((string) $list) === "assets/a.bin\nassets/b.txt\nindex.html" && $one === '<p>č</p>' && strpos((string) $test, 'No errors') !== false, 'ZIP: platný archiv (unzip -t), názvy a obsah sedí', [$list, $test]);
try { TsStudioZip::build(['../x' => 'y']); t_ok(false, 'ZIP cesta'); } catch (InvalidArgumentException $e) { t_ok(true, 'ZIP: cesta s .. odmítnuta'); }

/* --- tok projektu --- */
mkdir("$dir/tpl"); symlink(__DIR__ . '/fixtures/mini', "$dir/tpl/mini");
$roles = [1 => 'admin', 2 => 'designer', 3 => 'viewer'];
$S = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/s.sqlite"], 'templatesDir' => "$dir/tpl",
  'can' => fn ($uid, $act, $p) => $roles[$uid] === 'admin' || ($roles[$uid] === 'designer' && $act !== 'status') || ($roles[$uid] === 'viewer' && $act === 'view')]);
t_eq(array_keys($S->templates()), ['mini'], 'šablony se načtou ze složky');
$pr = $S->createProject(['name' => 'Kavárna U mostu', 'source' => 'new', 'template' => 'mini', 'brief' => ['obor' => 'kavárna']], 2, ['brand' => 'U mostu']);
$g = $S->get($pr['id'], 2);
t_ok($g['project']['current_num'] === 1 && $g['versions'][0]['status'] === 'accepted' && $g['project']['brief']['obor'] === 'kavárna', 'nový projekt: verze 1 ze šablony, přijatá');
$v1 = $S->version($pr['id'], 1, 2); t_ok(strpos($v1['files']['index.html'], '<title>U mostu</title>') !== false && !isset($v1['files']['assets/fonts/mini.woff2']), 'verze ukládá jen textové soubory s obsahem');
$r = $S->renderVersion($pr['id'], 2, ['brand' => 'U mostu', 'title' => 'Káva & dorty'], ['accent' => '#2a6f97'], 'Nový nadpis a barva', 'ai');
t_ok($r['num'] === 2 && !TsStudioChecks::errors($r['checks']) && $S->get($pr['id'], 2)['project']['current_num'] === 1, 'nová verze je jen návrh (aktuální zůstává 1)');
$S->accept($pr['id'], 2, 2); t_eq($S->get($pr['id'], 2)['project']['current_num'], 2, 'přijetí návrhu bez chyb');
$df = $S->diff($pr['id'], 1, 2, 3);
t_ok($df['files']['index.html']['status'] === 'changed' && strpos($df['diff']['index.html'], '+<h1>Káva &amp; dorty</h1>') !== false && strpos($df['diff']['assets/styles.css'], '--accent:#2a6f97') !== false, 'rozdíl verzí: HTML i tokeny v CSS');
$p3 = $S->patchVersion($pr['id'], 2, ['index.html' => str_replace('</main>', '', $S->version($pr['id'], 2, 2)['files']['index.html'])], 'Rozbitá úprava', 'ai');
try { $S->accept($pr['id'], $p3['num'], 2); t_ok(false, 'chyby blokují'); } catch (TsStudioChecksFailed $e) { t_ok($e->errors[0]['code'] === 'tag', 'verzi s chybou kontrol nejde přijmout'); }
$S->reject($pr['id'], $p3['num'], 2);
try { $S->accept($pr['id'], $p3['num'], 1); t_ok(false, 'zamítnutá'); } catch (InvalidArgumentException $e) { t_ok(true, 'zamítnutou verzi nejde přijmout'); }
try { $S->patchVersion($pr['id'], 2, ['../etc/passwd' => 'x'], 'x'); t_ok(false, 'cesta'); } catch (InvalidArgumentException $e) { t_ok(true, 'úprava: nepovolená cesta odmítnuta'); }
try { $S->patchVersion($pr['id'], 2, ['assets/fonts/x.woff2' => 'x'], 'x'); t_ok(false, 'binární'); } catch (InvalidArgumentException $e) { t_ok(true, 'úprava: binární soubor nejde přepsat textem'); }
$rv = $S->revert($pr['id'], 1, 2); $S->accept($pr['id'], $rv['num'], 2);
t_ok($S->version($pr['id'], $rv['num'], 2)['files'] === $v1['files'] && count($S->get($pr['id'], 2)['versions']) === 4, 'návrat k verzi 1 = nová verze se stejnými soubory, historie zůstává');
/* export a náhled */
$z = $S->exportZip($pr['id'], 2, 2); file_put_contents("$dir/p.zip", $z);
$zl = trim((string) shell_exec('unzip -Z1 ' . escapeshellarg("$dir/p.zip")));
t_ok($zl === "assets/app.js\nassets/fonts/mini.woff2\nassets/styles.css\nindex.html\nkontakt.html" && shell_exec('unzip -p ' . escapeshellarg("$dir/p.zip") . ' assets/fonts/mini.woff2') === "wOF2\x00\x01\x02\x03MINIFONT", 'export ZIP: všechny soubory včetně písma ze šablony');
$pv = $S->preview($pr['id'], 2, 3);
t_ok(strpos($pv, "Content-Security-Policy\" content=\"default-src 'none'") !== false && strpos($pv, '<style>@font-face{font-family:"Mini Sans";src:url("data:font/woff2;base64,') !== false
  && strpos($pv, '<script>document.documentElement.classList.add') !== false && strpos($pv, 'href="#" data-page="kontakt.html"') !== false && strpos($pv, 'assets/styles.css') === false, 'náhled: CSS, JS a písma vložené, CSP bez sítě, odkazy mezi stránkami jako data-page');
/* oprávnění a záznam */
try { $S->accept($pr['id'], 2, 3); t_ok(false, 'viewer'); } catch (TsStudioDenied $e) { t_ok(true, 'prohlížející (viewer) nemůže přijímat verze'); }
try { $S->setStatus($pr['id'], 'approved_internal', 2); t_ok(false, 'designer status'); } catch (TsStudioDenied $e) { t_ok(true, 'designer nemůže měnit stav schválení'); }
$S->setStatus($pr['id'], 'in_review', 1); t_eq($S->get($pr['id'], 1)['project']['status'], 'in_review', 'admin změní stav konceptu');
try { $S->setStatus($pr['id'], 'sent', 1); t_ok(false, 'stav'); } catch (InvalidArgumentException $e) { t_ok(true, 'stav konceptu jen z povoleného seznamu (odeslání je samostatný proces)'); }
$acts = array_column($S->get($pr['id'], 1)['log'], 'action');
foreach (['project.create', 'version.render', 'version.accept', 'version.patch', 'version.reject', 'version.revert', 'export.zip', 'status'] as $a) t_ok(in_array($a, $acts, true), "záznam akce: $a");
try { $S->createProject(['name' => 'x', 'source' => 'hack', 'template' => 'mini'], 1); t_ok(false, 'zdroj'); } catch (InvalidArgumentException $e) { t_ok(true, 'zdroj projektu jen new/redesign/crm'); }

/* --- skutečné šablony: výchozí obsah se vykreslí bez chyb a projde kontrolami --- */
foreach (glob(dirname(__DIR__) . '/templates/*/template.json') as $tj) {
  $tp = new TsStudioTemplate(dirname($tj)); $id = basename(dirname($tj));
  [, $e1] = $tp->cleanContent($tp->t['content']); [, $e2] = $tp->cleanTokens([]);
  t_ok(!$e1 && !$e2, "šablona $id: výchozí obsah a tokeny odpovídají schématu", array_merge($e1, $e2));
  $fs = $tp->render($tp->t['content'], []);
  t_eq(TsStudioChecks::run($fs, $tp, []), [], "šablona $id: kontroly bez nálezů");
  foreach ($tp->t['licenses'] ?? [] as $l) t_ok(in_array($l, $tp->files(), true), "šablona $id: licence $l je součástí šablony");
  t_ok(preg_match('~/\*@tokens\*/:root\{~', $fs['assets/styles.css'] ?? '') === 1, "šablona $id: CSS má blok @tokens");
}

