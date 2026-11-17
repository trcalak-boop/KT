<?php
/* Ochrana proti robotům bez sítě: výzva proof-of-work (podpis, platnost, jednorázovost, obtížnost), Turnstile přes falešné ověření,
   povinná hlavička Origin, roboti podle User-Agent, limit nových konverzací, denní strop webu. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir(); mkdir("$dir/data", 0700, true);
ini_set('error_log', "$dir/php-error.log");
$base = ['timezone' => 'Europe/Prague', 'business' => 'Studio Test', 'contactEmail' => 'info@studio.test', 'defaultLang' => 'cs',
  'knowledgeFile' => __DIR__ . '/fixtures/znalosti.json', 'salt' => 'testovaci-sul', 'pricing' => ['fake' => [4.0, 20.0, 0.40, 5.0]]];
$n = 0;
function gw(array $s): TsAiConsultant
{
    global $dir, $n; $n++;
    $say = fn ($req) => ['stop' => 'end', 'text' => 'ok', 'usage' => ['in' => 100, 'out' => 10, 'cacheRead' => 0, 'cacheWrite' => 0]];
    return ts_ai_consultant(['provider' => new TsAiFakeProvider(['script' => array_fill(0, 60, $say)]), 'db' => ['dsn' => "sqlite:$dir/data/g$n.sqlite"]] + $s);
}
$UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
$H = ['content-type' => 'application/json', 'host' => 'studio.test', 'origin' => 'https://studio.test', 'user-agent' => $UA];
function msg(TsAiConsultant $g, array $b, ?array $h = null, string $ip = '10.0.0.1'): array { global $H; [$c, $o] = $g->handle('POST', 'message', json_encode($b), $h ?? $H, $ip); $o['_code'] = $c; return $o; }
function challenge(TsAiConsultant $g, ?array $h = null): array { global $H; return $g->handle('GET', 'challenge', '', $h ?? $H, '10.0.0.1')[1]; }
function solve(array $p): array
{
    for ($n = 0; ; $n++) {
        $h = hash('sha256', $p['c'] . ':' . $n); $bits = 0;
        for ($i = 0; $i < 64 && $bits < $p['d']; $i++) { $v = hexdec($h[$i]); $bits += [4, 3, 2, 2, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0][$v]; if ($v) break; }
        if ($bits >= $p['d']) return $p + ['n' => (string) $n];
    }
}

/* --- proof-of-work --- */
$g = gw(['guard' => ['pow' => 8]] + $base);
$ch = challenge($g);
t_ok($ch['ok'] && preg_match('/^[a-f0-9]{24}$/', $ch['pow']['c']) && $ch['pow']['d'] === 8 && $ch['pow']['e'] > time() && $ch['turnstile'] === null, 'výzva: náhodná, obtížnost, platnost, bez Turnstile');
t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs'])['error'] ?? '', 'challenge', 'nová konverzace bez výzvy → 403 challenge');
$p = solve($ch['pow']);
$a = msg($g, ['text' => 'Ahoj', 'lang' => 'cs', 'proof' => $p]);
t_ok($a['_code'] === 200 && preg_match('/^[a-f0-9]{48}$/', $a['conversation']), 'vyřešená výzva → konverzace založena');
t_eq(msg($g, ['text' => 'Ahoj znovu', 'lang' => 'cs', 'proof' => $p])['error'] ?? '', 'challenge', 'stejná výzva podruhé neprojde (jednorázová)');
t_eq(msg($g, ['text' => 'Další', 'lang' => 'cs', 'conversation' => $a['conversation']])['_code'], 200, 'další zprávy konverzace výzvu nepotřebují');
$q = solve(challenge($g)['pow']); $q['d'] = 4; $q['s'] = 'x';
t_eq(msg($g, ['text' => 'a', 'lang' => 'cs', 'proof' => $q])['error'] ?? '', 'challenge', 'podvržená obtížnost / podpis → odmítnuto');
$q = challenge($g)['pow']; $q['n'] = '0';
$bad = hash('sha256', $q['c'] . ':0'); $ok0 = substr($bad, 0, 2) === '00';
t_ok($ok0 || (msg($g, ['text' => 'a', 'lang' => 'cs', 'proof' => $q])['error'] ?? '') === 'challenge', 'nevyřešená výzva (špatné n) → odmítnuto');
$q = challenge($g)['pow']; $q['e'] = time() - 5; $q = solve($q);
t_eq(msg($g, ['text' => 'a', 'lang' => 'cs', 'proof' => $q])['error'] ?? '', 'challenge', 'prošlá výzva → odmítnuto (podpis nesedí s jinou platností)');

/* --- Origin a roboti --- */
$g = gw(['guard' => ['pow' => 0]] + $base);
$h = $H; unset($h['origin']);
t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs'], $h)['error'] ?? '', 'origin', 'POST bez hlavičky Origin → 403');
t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs'], ['origin' => 'https://cizi.test'] + $H)['error'] ?? '', 'origin', 'cizí Origin → 403');
foreach (['curl/8.4.0', 'python-requests/2.31', 'Mozilla/5.0 (compatible; Googlebot/2.1)', 'Mozilla/5.0 HeadlessChrome/120.0', ''] as $ua)
    t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs'], ['user-agent' => $ua] + $H)['error'] ?? '', 'bot', 'robot podle User-Agent: „' . $ua . '“');
t_eq(challenge($g, ['user-agent' => 'curl/8'] + $H)['error'] ?? '', 'bot', 'výzvu robot nedostane');
t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs'])['_code'], 200, 'běžný prohlížeč se stejným původem projde');
t_eq($g->handle('GET', 'status', '', ['user-agent' => 'curl/8'], '1.1.1.1')[0], 200, 'stav (status) se dá zjistit i bez prohlížeče – nevolá AI');

/* --- Turnstile --- */
$seen = [];
$g = gw(['guard' => ['pow' => 0, 'turnstile' => ['siteKey' => '0xTEST', 'verify' => function ($t) use (&$seen) { $seen[] = $t; return $t === 'dobry-token'; }]]] + $base);
t_eq(challenge($g)['turnstile'], '0xTEST', 'výzva nese veřejný klíč Turnstile');
t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs'])['error'] ?? '', 'challenge', 'bez tokenu Turnstile → odmítnuto');
t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs', 'turnstile' => 'spatny'])['error'] ?? '', 'challenge', 'neplatný token → odmítnuto');
$a = msg($g, ['text' => 'Ahoj', 'lang' => 'cs', 'turnstile' => 'dobry-token']);
t_ok($a['_code'] === 200 && $seen === ['spatny', 'dobry-token'], 'platný token → konverzace; prázdný token se Cloudflaru vůbec neposílá');
$g = gw(['guard' => ['pow' => 0, 'turnstile' => ['siteKey' => '0xTEST', 'secret' => 'tajne', 'verifyUrl' => 'http://127.0.0.1:9/siteverify']]] + $base);
t_eq(msg($g, ['text' => 'Ahoj', 'lang' => 'cs', 'turnstile' => 'cokoli'])['_code'], 200, 'Cloudflare nedostupný → asistent běží dál (zbývají limity), zapsáno do logu');
t_ok(strpos((string) @file_get_contents("$dir/php-error.log"), 'Turnstile nedostupný') !== false, 'výpadek Turnstile zalogován');

/* --- limity --- */
$g = gw(['guard' => ['pow' => 0], 'limits' => ['newConvPerIpHour' => 3]] + $base);
$codes = []; for ($i = 0; $i < 4; $i++) $codes[] = msg($g, ['text' => "n$i", 'lang' => 'cs'])['_code'];
t_eq($codes, [200, 200, 200, 429], 'limit nových konverzací na adresu za hodinu');
$g = gw(['guard' => ['pow' => 0], 'limits' => ['globalDay' => 2, 'newConvPerIpHour' => 0]] + $base);
$codes = []; for ($i = 0; $i < 3; $i++) $codes[] = msg($g, ['text' => "d$i", 'lang' => 'cs'], null, "10.0.1.$i")['_code'];
t_eq($codes, [200, 200, 429], 'denní strop zpráv celého webu (různé adresy)');
$g = gw($base);
$c = (new ReflectionProperty($g, 'c'))->getValue($g);
t_ok($c['guard']['pow'] === 15 && $c['guard']['requireOrigin'] && $c['guard']['blockBots'] && $c['limits']['perIpDay'] === 40 && $c['limits']['perIp10min'] === 12 && $c['limits']['newConvPerIpHour'] === 6, 'výchozí hodnoty: výzva 15 bitů, Origin, filtr robotů, 12/10 min, 40/den, 6 konverzací/h');
