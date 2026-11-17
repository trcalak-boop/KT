<?php
/* Stavebnice sekcí bez sítě a bez AI: katalog, ukázkový obsah každé sekce, každá varianta projde kontrolami kvality, escapování,
   neznámé typy a varianty, tokeny a kontrast, do projektu jdou jen použité sekce a písma, licence písem, bez cizích zdrojů. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$k = new TsStudioKit();
$S = $k->sections();
t_ok(count($S) >= 20, 'stavebnice má alespoň 20 typů sekcí (' . count($S) . ')');
t_ok(array_sum(array_map(fn ($d) => count($d['variants']), $S)) >= 40, 'alespoň 40 variant rozvržení');
foreach (['news', 'menu', 'documents', 'catalog', 'steps'] as $t) t_ok(isset($S[$t]), "pro školu a e-shop: sekce $t");
t_ok(count($k->arts()) >= 8, 'abstraktní ilustrace');

/* --- každá sekce a varianta --- */
foreach ($S as $t => $d) {
    t_ok(isset($d['variants'][$d['default']]) && !empty($d['use']) && !empty($d['label']), "$t: popis a výchozí varianta");
    t_ok(is_file($k->dir . "/sections/$t/section.html") && is_file($k->dir . "/sections/$t/section.css"), "$t: šablona a styl");
    [, $e] = TsStudioTemplate::cleanSlots($d['slots'], [], $d['sample']);
    t_eq($e, [], "$t: ukázkový obsah odpovídá schématu");
    t_eq(array_values(array_diff(array_keys($d['sample']), array_keys($d['slots']))), [], "$t: ukázka nemá sloty mimo schéma");
    foreach (array_keys($d['variants']) as $v) {
        $r = $k->render(['site' => ['brand' => 'Test', 'title' => 'Test – ukázka', 'lang' => 'cs'], 'sections' => [$k->sampleSection($t, $v)]]);
        t_eq($r['errors'], [], "$t/$v: bez chyb plánu");
        $c = TsStudioChecks::run($r['files']);
        t_eq(array_values(array_map(fn ($x) => $x['code'] . ' ' . $x['msg'], $c)), [], "$t/$v: kontroly kvality bez nálezu");
        t_ok(strpos($r['files']['index.html'], "s-$t--$v") !== false && !preg_match('/\{\{|<!--art/', $r['files']['index.html']), "$t/$v: vykresleno bez zástupných značek");
    }
    $css = (string) file_get_contents($k->dir . "/sections/$t/section.css");
    t_ok(!preg_match('~url\(\s*["\']?(https?:)?//|@import~i', $css), "$t: styl bez cizích zdrojů");
}

/* --- plán: co neprojde --- */
[$p, $e] = $k->clean(['site' => ['brand' => 'A', 'lang' => 'xx', 'email' => 'neni', 'cta_url' => 'javascript:alert(1)'], 'sections' => [
    ['type' => 'neexistuje'], ['type' => 'hero', 'variant' => 'zla', 'tone' => 'neon', 'content' => ['title' => 'Nadpis', 'art' => '../../etc', 'cta_url' => 'javascript:x', 'neznamy' => 1]],
    ['type' => 'hero', 'id' => 'hero', 'content' => []], 'text']]);
t_ok(count($p['sections']) === 2 && $p['sections'][0]['variant'] === 'split' && $p['sections'][0]['tone'] === 'base' && $p['sections'][0]['content']['art'] === '' && $p['sections'][0]['content']['cta_url'] === '' && !isset($p['sections'][0]['content']['neznamy']), 'neznámý typ, varianta, tón, ilustrace, adresa a klíč se zahodí', $p['sections']);
t_ok($p['sections'][0]['id'] === 'hero' && $p['sections'][1]['id'] === 'hero-2', 'ID sekcí jsou jedinečná');
t_ok($p['site']['lang'] === 'cs' && $p['site']['email'] === '' && $p['site']['cta_url'] === '' && count($e) >= 6, 'chyby pro AI a uživatele', $e);
[, $e] = $k->clean(['sections' => []]); t_ok(in_array('sections: plán nemá žádnou sekci', $e, true), 'prázdný plán se nahlásí');
[$p, $e] = $k->clean(['sections' => array_fill(0, 30, ['type' => 'text'])]); t_ok(count($p['sections']) === 16, 'nejvýš 16 sekcí');

/* --- escapování --- */
$x = '<script>alert(1)</script>"\'';
$r = $k->render(['site' => ['brand' => $x, 'title' => $x, 'description' => $x], 'sections' => [['type' => 'hero', 'id' => '"><img src=x onerror=y>', 'nav' => $x, 'content' => ['title' => $x, 'lead' => $x, 'facts' => [['label' => $x, 'value' => $x]]]], ['type' => 'swatches', 'content' => ['items' => [['color' => 'red;background:url(//x)', 'name' => $x]]]]]]);
$h = $r['files']['index.html'];
t_ok(strpos($h, '<script>alert') === false && strpos($h, '<img') === false && strpos($h, 'url(//x)') === false && strpos($h, '&lt;script&gt;') !== false, 'vše z plánu je escapované, barva jen #rrggbb, ID bezpečné');

/* --- tokeny --- */
[$t, $e] = $k->cleanTokens(['bg' => 'red', 'f_display' => 'comic-sans', 'f_body' => 'syne', 'f_mono' => 'sora', 'radius' => '999', 'accent' => '#AABBCC']);
t_ok($t['bg'] === '#fbfaf7' && $t['f_display'] === 'sora' && $t['f_body'] === 'figtree' && $t['f_mono'] === 'jetbrains-mono' && $t['radius'] === '4' && $t['accent'] === '#aabbcc' && count($e) === 5, 'tokeny: barva, písmo z katalogu podle druhu, výčet', $e);
[$t, , $fixed] = $k->cleanTokens(['bg' => '#808080', 'surface' => '#888888', 'ink' => '#8a8a8a', 'muted' => '#909090', 'accent' => '#858585', 'on_accent' => '#8f8f8f', 'band' => '#7a7a7a', 'on_band' => '#808080', 'd_bg' => '#777777', 'd_ink' => '#868686']);
foreach (['', 'd_'] as $q) foreach ([['ink', 'bg'], ['muted', 'bg'], ['ink', 'surface'], ['muted', 'surface'], ['on_accent', 'accent'], ['on_band', 'band'], ['band_muted', 'band'], ['accent_muted', 'accent'], ['accent_text', 'bg']] as [$f, $b])
    t_ok(TsStudioTemplate::contrast($t[$q . $f], $t[$q . $b]) >= 4.5, "kontrast $q$f na $q$b opravený i u nepoužitelné palety (" . TsStudioTemplate::contrast($t[$q . $f], $t[$q . $b]) . ')');
t_ok(count($fixed) >= 4, 'opravené kontrasty se hlásí');
[$t] = $k->cleanTokens([]);
foreach (['', 'd_'] as $q) foreach ([['ink', 'bg'], ['muted', 'bg'], ['muted', 'surface'], ['on_accent', 'accent'], ['on_band', 'band'], ['accent_text', 'surface']] as [$f, $b]) t_ok(TsStudioTemplate::contrast($t[$q . $f], $t[$q . $b]) >= 4.5, "výchozí paleta: $q$f na $q$b");

/* --- do projektu jde jen to, co se používá --- */
$r = $k->render(['site' => ['brand' => 'A', 'title' => 'A b'], 'tokens' => ['f_display' => 'marcellus', 'f_body' => 'figtree', 'f_mono' => 'azeret-mono'], 'sections' => [$k->sampleSection('faq', 'accordion')]]);
$css = $r['files']['assets/site.css'];
t_ok(strpos($css, 'sekce: faq') !== false && strpos($css, 'sekce: hero') === false && strpos($css, '.hero-in') === false, 'jen styl použitých sekcí');
$fam = array_unique(array_map(fn ($f) => explode('-', basename($f))[0], array_keys($r['binary'])));
t_ok(count(array_filter(array_keys($r['binary']), fn ($f) => str_ends_with($f, '-OFL.txt'))) === 3 && !array_filter(array_keys($r['binary']), fn ($f) => str_contains($f, 'sora') || str_contains($f, 'syne')), 'jen použitá písma a jejich licence', $fam);
t_ok(!array_filter($r['binary'], fn ($src) => !is_file($src)), 'soubory písem existují');
preg_match_all('~url\("fonts/([^"]+)"\)~', $css, $m);
t_ok($m[1] && !array_diff(array_map(fn ($f) => 'assets/fonts/' . $f, $m[1]), array_keys($r['binary'])), 'každé @font-face má svůj soubor');
foreach ($k->fonts as $id => $f) t_ok(is_file($k->dir . '/fonts/' . $f['license']) && $f['faces'] && !array_filter($f['faces'], fn ($x) => !is_file($k->dir . '/fonts/' . $x['file'])), "písmo $id: soubory a licence");
t_ok(strpos($r['files']['index.html'], 'lang="cs"') !== false && strpos($r['files']['index.html'], 'noindex') === false, 'jazyk; export bez noindex (náhledy chrání brána)');
t_ok(!preg_match('~\b(localStorage|sessionStorage|fetch|XMLHttpRequest|document\.cookie)\b~', $r['files']['assets/app.js']), 'skript webu bez úložiště a sítě');
t_eq(array_values(array_map(fn ($x) => $x['code'], TsStudioChecks::run($r['files']))), [], 'celý web bez nálezu kontrol');

/* --- katalog pro prompt --- */
$c = json_encode($k->catalog(), JSON_UNESCAPED_UNICODE);
t_ok(strpos($c, 'fakta') !== false && strpos($c, 'hero') !== false && strlen($c) < 16000, 'katalog pro model je krátký a značí sloty s fakty (' . strlen($c) . ' B)');

/* --- celé weby z plánu: škola a e-shop (náhrada za samostatné šablony Slunečnice a Vrstva) --- */
$pages = [];
foreach (['skola', 'eshop'] as $n) {
    $r = $k->render(json_decode((string) file_get_contents(__DIR__ . "/fixtures/plan-$n.json"), true, 32, JSON_THROW_ON_ERROR));
    t_eq($r['errors'], [], "plán $n: bez chyb");
    t_eq(array_values(array_map(fn ($x) => $x['msg'], TsStudioChecks::run($r['files']))), [], "plán $n: kontroly kvality bez nálezu");
    $pages[$n] = $r;
}
t_ok(strpos($pages['skola']['files']['index.html'], 's-menu--days') !== false && strpos($pages['eshop']['files']['index.html'], 's-catalog--grid') !== false, 'škola má jídelníček, e-shop katalog');
t_ok(array_keys($pages['skola']['binary']) !== array_keys($pages['eshop']['binary']) && $pages['skola']['files']['assets/site.css'] !== $pages['eshop']['files']['assets/site.css'], 'dva plány = jiná písma, jiný styl');
t_ok(strpos($pages['skola']['files']['assets/site.css'], '.ca-i') === false && strpos($pages['eshop']['files']['assets/site.css'], '.me-g') === false, 'každý web má jen své sekce');

