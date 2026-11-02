<?php
/* Sběr a přehled bez sítě: validace vstupu, ochrana (Origin, roboti, velikost, limity), co se ukládá a co ne, retence,
   souhrny (návštěvníci, návštěvy, odchody, délka, kanály, kampaně, zařízení, události, srovnání období), HTML přehledu escapované. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir(); ini_set('error_log', "$dir/php-error.log");
$n = 0;
function an(array $s = []): TsAnalytics { global $dir, $n; $n++; return ts_analytics($s + ['timezone' => 'Europe/Prague', 'salt' => 'test', 'db' => ['dsn' => "sqlite:$dir/a$n.sqlite"]]); }
$UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
$H = ['host' => 'www.studio.test', 'origin' => 'https://www.studio.test', 'user-agent' => $UA, 'content-type' => 'text/plain'];
$V = 'abcdefghij012345'; $S = 'sessionaaaaaaaa1';
function hitp(TsAnalytics $a, array $b, ?array $h = null, string $ip = '10.0.0.1'): int { global $H; return $a->handle('POST', json_encode($b), $h ?? $H, $ip)[0]; }
function rows(TsAnalytics $a): array { return $a->store()->q('SELECT * FROM ts_an_hits ORDER BY id')->fetchAll(); }

/* --- sběr --- */
$a = an();
t_eq(hitp($a, ['t' => 'pv', 'p' => '/cs/kontakt.html?email=jana@example.com#x', 'r' => 'https://www.google.com/search?q=web+studio&email=x', 'u' => ['s' => 'Newsletter', 'm' => 'email', 'c' => 'Podzim 2026'], 'l' => 'cs', 'w' => 390, 'v' => $V, 's' => $S, 'n' => 1]), 204, 'zobrazení stránky → 204');
$r = rows($a)[0];
t_ok($r['path'] === '/cs/kontakt.html' && $r['ref'] === 'google.com' && $r['src'] === 'newsletter' && $r['med'] === 'email' && $r['camp'] === 'Podzim 2026' && $r['lang'] === 'cs' && $r['dev'] === 'm' && $r['newv'] == 1, 'uloží jen cestu bez parametrů, doménu odkazu, UTM, jazyk, typ zařízení', $r);
t_ok($r['br'] === 'Safari' && $r['os'] === 'iOS' && !preg_match('/10\.0\.0\.1|email=|jana/', json_encode($r)), 'prohlížeč a systém jen hrubě; IP ani e-mail z adresy se neuloží', $r);
t_eq(hitp($a, ['t' => 'pv', 'p' => '/cs/', 'r' => 'https://studio.test/cs/x.html', 'v' => $V, 's' => $S]), 204, 'přechod uvnitř webu');
t_eq(rows($a)[1]['ref'], '', 'vlastní doména (i bez www) se jako zdroj nepočítá');
t_eq(hitp($a, ['t' => 'ev', 'e' => 'poptavka', 'p' => '/cs/kontakt.html', 'v' => $V, 's' => $S]), 204, 'událost');
foreach ([['t' => 'pv', 'p' => 'https://jiny.cz/', 'v' => $V, 's' => $S], ['t' => 'pv', 'p' => '/x', 'v' => 'kratke', 's' => $S], ['t' => 'xx', 'p' => '/', 'v' => $V, 's' => $S],
          ['t' => 'ev', 'e' => 'Zlá Událost', 'p' => '/', 'v' => $V, 's' => $S], ['t' => 'pv', 'p' => '/<script>', 'v' => $V, 's' => $S]] as $i => $bad)
    t_eq(hitp($a, $bad), 400, "neplatný vstup $i → 400");
t_eq($a->handle('POST', 'neni json', $H, '10.0.0.1')[0], 400, 'nejde o JSON → 400');
t_eq($a->handle('POST', str_repeat('x', 4500), $H, '10.0.0.1')[0], 413, 'příliš velké tělo → 413');
t_eq($a->handle('GET', '', $H, '10.0.0.1')[0], 405, 'GET → 405');
$h = $H; unset($h['origin']); t_eq(hitp($a, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S], $h), 403, 'bez hlavičky Origin → 403');
t_eq(hitp($a, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S], ['origin' => 'https://cizi.test'] + $H), 403, 'cizí Origin → 403');
$c0 = count(rows($a));
foreach (['Mozilla/5.0 (compatible; Googlebot/2.1)', 'curl/8', 'Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/120', 'Chrome-Lighthouse', ''] as $ua)
    t_eq(hitp($a, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S], ['user-agent' => $ua] + $H), 204, 'robot „' . $ua . '“ → 204, ale nezapočítá se');
t_eq(count(rows($a)), $c0, 'roboti v datech nejsou');
$b = an(['limits' => ['perIpHour' => 2]]);
t_eq([hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S]), hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S]), hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S]), hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S], null, '10.0.0.2')], [204, 204, 429, 204], 'limit na adresu za hodinu');
$b = an(['enabled' => false]); t_ok(hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S]) === 204 && rows($b) === [], 'vypínač: přijme a zahodí');
$b = an(['allowedOrigins' => ['https://studio.test']]);
t_ok(hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S], ['origin' => 'https://studio.test'] + $H) === 204 && hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S]) === 403, 'povolené původy ze seznamu');

/* --- retence --- */
$b = an(); $st = $b->store();
$st->add(['ts' => time(), 'day' => date('Y-m-d', strtotime('-15 months')), 'vid' => $V, 'sid' => $S, 'kind' => 'pv', 'path' => '/stare']);
$st->add(['ts' => time(), 'day' => date('Y-m-d', strtotime('-13 months')), 'vid' => $V, 'sid' => $S, 'kind' => 'pv', 'path' => '/mlade']);
hitp($b, ['t' => 'pv', 'p' => '/', 'v' => $V, 's' => $S]);
t_eq(array_column(rows($b), 'path'), ['/mlade', '/'], 'retence 14 měsíců: starší zobrazení se při sběru smažou (jednou denně)');

/* --- přehled --- */
$c = an(); $st = $c->store(); $today = date('Y-m-d'); $y = date('Y-m-d', strtotime('-1 day'));
$add = function (string $day, string $vid, string $sid, int $ts, string $path, array $x = []) use ($st) { $st->add(['ts' => $ts, 'day' => $day, 'vid' => $vid, 'sid' => $sid, 'kind' => 'pv', 'path' => $path] + $x); };
$t0 = strtotime("$today 10:00");
$add($today, 'v000000000000001', 's000000000000001', $t0, '/cs/', ['ref' => 'google.com', 'newv' => 1, 'lang' => 'cs', 'dev' => 'm', 'br' => 'Safari', 'os' => 'iOS']);
$add($today, 'v000000000000001', 's000000000000001', $t0 + 90, '/cs/kontakt.html');
$st->add(['ts' => $t0 + 120, 'day' => $today, 'vid' => 'v000000000000001', 'sid' => 's000000000000001', 'kind' => 'ev', 'path' => '/cs/kontakt.html', 'ev' => 'poptavka']);
$add($today, 'v000000000000002', 's000000000000002', $t0 + 5, '/cs/', ['src' => 'newsletter', 'med' => 'email', 'camp' => 'Podzim', 'newv' => 1, 'lang' => 'cs', 'dev' => 'd', 'br' => 'Chrome', 'os' => 'Windows']);
$add($today, 'v000000000000003', 's000000000000003', $t0 + 9, '/de/', ['ref' => 'l.instagram.com', 'lang' => 'de', 'dev' => 'm', 'br' => 'Chrome', 'os' => 'Android']);
$add($today, 'v000000000000004', 's000000000000004', $t0 + 9, '/', ['ref' => 'partner.cz', 'lang' => 'en', 'dev' => 'd', 'br' => 'Firefox', 'os' => 'Linux']);
$add($y, 'v000000000000001', 's000000000000009', $t0 - 86400, '/cs/', ['lang' => 'cs', 'dev' => 'm']);
$add(date('Y-m-d', strtotime('-7 days')), 'v000000000000005', 's000000000000010', $t0 - 7 * 86400, '/cs/');
$R = $c->report(date('Y-m-d', strtotime('-6 days')), $today);
$T = $R['totals'];
t_ok($T['visitors'] === 4 && $T['sessions'] === 5 && $T['pageviews'] === 6 && $T['newVisitors'] === 2, 'souhrn: návštěvníci, návštěvy, zobrazení, noví', $T);
t_ok($T['bounceRate'] === 80.0 && $T['avgDuration'] === 120 && $T['pagesPerSession'] === 1.2, 'odchody po 1 stránce, délka návštěvy (jen víc stránek), stránek na návštěvu', $T);
t_ok($R['previous']['sessions'] === 1 && $R['previous']['from'] === date('Y-m-d', strtotime('-13 days')) && count($R['daily']) === 7 && $R['daily'][6]['visitors'] === 4 && $R['daily'][0]['visitors'] === 0, 'srovnání se stejně dlouhým předchozím obdobím, denní řada doplněná o prázdné dny', [$R['previous'], $R['daily'][6]]);
$ch = array_column($R['channels'], 'sessions', 'label');
t_eq($ch, ['Vyhledávače' => 1, 'Kampaně' => 1, 'Sociální sítě' => 1, 'Odkazy z webů' => 1, 'Přímý přístup' => 1], 'kanály podle prvního záznamu návštěvy');
t_ok($R['pages'][0] === ['path' => '/cs/', 'pageviews' => 3, 'visitors' => 2] && $R['campaigns'][0]['label'] === 'newsletter / email · Podzim', 'stránky a kampaně', [$R['pages'][0], $R['campaigns']]);
t_ok($R['events'] === [['event' => 'poptavka', 'count' => 1, 'sessions' => 1, 'rate' => 20.0]] && array_column($R['devices'], 'sessions', 'label') === ['Telefon' => 3, 'Počítač' => 2], 'události s podílem návštěv, zařízení', [$R['events'], $R['devices']]);
$html = TsAnalyticsReport::html($R, ['events' => ['poptavka' => 'Odeslaná poptávka'], 'url' => fn ($d) => "?dni=$d"]);
t_ok(strpos($html, 'Odeslaná poptávka') !== false && strpos($html, '<svg') !== false && strpos($html, 'aria-current="page">7 dní') !== false && strpos($html, '+400') !== false, 'HTML přehledu: události s názvem, graf, vybrané období, srovnání');
$st->add(['ts' => time(), 'day' => $today, 'vid' => 'v000000000000006', 'sid' => 's000000000000006', 'kind' => 'pv', 'path' => '/"><script>alert(1)</script>', 'ref' => '<b>x']);
$html = TsAnalyticsReport::html($c->report($today, $today));
t_ok(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'HTML přehledu escapuje vše (i data podstrčená mimo sběr)');
t_ok(strpos(TsAnalyticsReport::html(an()->report($today, $today)), 'zatím nejsou žádná data') !== false, 'prázdné období: vysvětlení, že se měří jen se souhlasem');
t_eq(TsAnalytics::agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0'), ['Edge', 'Windows'], 'rozpoznání Edge na Windows');
