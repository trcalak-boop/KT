<?php
/* Audit webu bez skutečných služeb: falešné měření a hodnocení, kroky úlohy, report (fakta × AI bez čísel), snímky jen s tokenem,
   cache a sdílení běžícího auditu, limity, chyby a opakování, zámek proti souběhu, retence; adaptér PageSpeed proti lokální napodobenině. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir(); ini_set('error_log', "$dir/php-error.log");
$DNS = ['example.cz' => ['93.184.216.34'], 'www.example.cz' => ['93.184.216.34'], 'jiny.cz' => ['93.184.216.36'], 'interni.cz' => ['10.0.0.1']];
$n = 0;
function aud(array $o = [], ?TsAuditFakeMeter &$m = null, ?TsAuditFakeVision &$v = null): TsSiteAudit
{
    global $dir, $n, $DNS; $n++;
    $m = $o['meter'] ?? new TsAuditFakeMeter(); $v = array_key_exists('vision', $o) ? $o['vision'] : new TsAuditFakeVision();
    return ts_site_audit(['db' => ['dsn' => "sqlite:$dir/a$n.sqlite"], 'meter' => $m, 'vision' => $v, 'shotDir' => "$dir/shots$n", 'salt' => 'sul',
        'resolve' => fn ($h) => $DNS[$h] ?? [], 'processAfterResponse' => false] + array_diff_key($o, ['meter' => 1, 'vision' => 1]));
}
$H = ['content-type' => 'application/json', 'host' => 'studio.test'];
function post(TsSiteAudit $a, array $b, string $ip = '1.2.3.4', ?array $h = null): array { global $H; [$c, $o] = $a->handle('POST', 'audit', json_encode($b), [], $h ?? $H, $ip); $o['_code'] = $c; return $o; }
function st(TsSiteAudit $a, string $id): array { global $H; [$c, $o] = $a->handle('GET', 'audit-status', '', ['id' => $id], $H, '1.2.3.4'); $o['_code'] = $c; return $o; }
function run(TsSiteAudit $a, string $id, int $max = 6): array { for ($i = 0; $i < $max; $i++) { $s = st($a, $id); if (in_array($s['status'] ?? '', ['done', 'error'], true)) return $s; $a->advance($id); } return st($a, $id); }

/* --- normalizace odpovědi Lighthouse --- */
$r = TsAuditPageSpeed::normalize(TsAuditFakeMeter::sample('mobile'));
t_eq($r['scores'], ['performance' => 54, 'accessibility' => 81, 'best-practices' => 96, 'seo' => 90], 'skóre kategorií 0–100');
t_eq($r['metrics']['lcp'], ['value' => 4823.4, 'display' => '4,8 s', 'score' => 0.31], 'metrika LCP s hodnotou a zobrazením');
t_eq(array_column($r['issues'], 'id'), ['render-blocking-resources', 'uses-optimized-images', 'image-alt', 'color-contrast'], 'problémy: jen neúspěšné (skóre < 0,9), bez metrik, podle váhy');
t_ok(str_starts_with($r['screenshot'], "\xFF\xD8") && str_starts_with($r['fullpage'], 'RIFF') && $r['issues'][2]['title'] === 'Obrázky nemají atributy [alt]', 'snímky jako bajty, titulky bez HTML');
$bad = TsAuditFakeMeter::sample('mobile'); $bad['lighthouseResult']['runtimeError'] = ['code' => 'FAILED_DOCUMENT_REQUEST'];
try { TsAuditPageSpeed::normalize($bad); t_ok(false, 'chyba načtení webu'); } catch (TsAuditError $e) { t_eq($e->kind, 'site', 'chyba načtení webu (runtimeError) → site'); }

/* --- celý audit přes HTTP --- */
$a = aud([], $m, $v);
$o = post($a, ['url' => 'example.cz', 'lang' => 'cs']);
t_ok($o['_code'] === 202 && preg_match('/^[a-f0-9]{48}$/', $o['id']) && $o['status'] === 'queued' && $o['_advance'] === $o['id'], 'založení: 202, tajný token, další krok po odpovědi');
$id = $o['id'];
$s = st($a, $id); t_ok($s['status'] === 'queued' && $s['progress'] === 5 && $s['url'] === 'https://example.cz/' && $s['_advance'] === $id, 'stav: čeká, postup 5 %');
$a->advance($id); t_eq(st($a, $id)['status'], 'desktop', 'krok 1: mobil změřen');
$a->advance($id); t_eq(st($a, $id)['status'], 'vision', 'krok 2: desktop změřen');
$a->advance($id); $s = st($a, $id);
t_ok($s['status'] === 'done' && $s['progress'] === 100 && !isset($s['_advance']), 'krok 3: hodnocení a report → hotovo');
t_eq($m->calls, [['https://example.cz/', 'mobile', 'cs'], ['https://example.cz/', 'desktop', 'cs']], 'měření: mobil a desktop, jazyk cs');
$rep = $s['report'];
t_ok($rep['source']['name'] === 'Google PageSpeed Insights (Lighthouse)' && $rep['source']['kind'] === 'lab' && $rep['measuredAt'] === '2026-11-01T20:00:00.000Z' && $rep['notes'] === ['lab', 'single', 'aiSubjective'], 'report: zdroj, laboratorní měření, datum měření, poznámky o omezeních');
t_ok($rep['devices']['mobile']['scores']['performance'] === 54 && $rep['devices']['desktop']['scores']['performance'] === 88 && $rep['devices']['mobile']['screenshot'] === true, 'report: skóre mobil/desktop, snímek k dispozici');
$meas = array_values(array_filter($rep['recommendations'], fn ($x) => $x['basis'] === 'measurement')); $ai = array_values(array_filter($rep['recommendations'], fn ($x) => $x['basis'] === 'ai'));
t_ok(count($meas) === 4 && $meas[0]['devices'] === ['mobile', 'desktop'] && $meas[0]['detail'] === 'Potenciální úspora 1 200 ms', 'fakta z měření: bez duplicit mezi zařízeními, s detailem');
t_ok(count($ai) === 2 && !array_filter($ai, fn ($x) => preg_match('/\d/', $x['text'])) && $ai[0]['priority'] === 1 && $rep['ai']['ok'] && $rep['ai']['model'] === 'fake-vision', 'doporučení AI: označená, seřazená, položka s vymyšleným číslem („o 70 %“) zahozena');
t_ok($v->calls[0][2]['device'] === 'desktop' && $v->calls[0][2]['host'] === 'example.cz' && in_array('Efektivně zakódujte obrázky', $v->calls[0][2]['issues'], true), 'hodnocení: snímek desktopu, kontext s nálezy měření');
[$c, $img] = $a->handle('GET', 'audit-shot', '', ['id' => $id, 'd' => 'mobile'], $H, '1.2.3.4');
t_ok($c === 200 && $img['type'] === 'image/jpeg' && str_starts_with($img['image'], "\xFF\xD8"), 'snímek mobilu jen s tokenem úlohy');
[$c] = $a->handle('GET', 'audit-shot', '', ['id' => $id, 'd' => 'desktop', 'full' => 1], $H, '1.2.3.4'); t_eq($c, 200, 'celostránkový snímek desktopu');
[$c] = $a->handle('GET', 'audit-shot', '', ['id' => str_repeat('ab', 24), 'd' => 'mobile'], $H, '1.2.3.4'); t_eq($c, 404, 'cizí token: snímek nedostane');
[$c] = $a->handle('GET', 'audit-shot', '', ['id' => $id, 'd' => '../../etc/passwd'], $H, '1.2.3.4'); t_eq($c, 404, 'zařízení mimo seznam: nic');
t_eq(st($a, str_repeat('cd', 24))['_code'], 404, 'neznámý token: 404');
$db = new PDO("sqlite:$dir/a$n.sqlite");
t_eq((int) $db->query("SELECT COUNT(*) FROM ts_audit_jobs WHERE token_hash = " . $db->quote($id))->fetchColumn(), 0, 'token se neukládá, jen otisk');

/* --- cache a sdílení --- */
$o2 = post($a, ['url' => 'https://example.cz/', 'lang' => 'cs'], '5.6.7.8');
t_ok($o2['status'] === 'done' && $o2['cached'] === true && count($m->calls) === 2 && st($a, $o2['id'])['cached'] === true, 'stejná adresa do 24 h: výsledek z cache s datem měření, bez nového měření');
[$c] = $a->handle('GET', 'audit-shot', '', ['id' => $o2['id'], 'd' => 'mobile'], $H, '5.6.7.8'); t_eq($c, 200, 'cache: snímky sdílené z původního auditu');
$o3 = post($a, ['url' => 'example.cz', 'lang' => 'en'], '5.6.7.8'); t_eq($o3['status'], 'queued', 'jiný jazyk = nové měření (titulky v jazyce)');
$a = aud([], $m, $v);
$x = post($a, ['url' => 'jiny.cz', 'lang' => 'cs'], '1.1.1.1'); $y = post($a, ['url' => 'jiny.cz/', 'lang' => 'cs'], '2.2.2.2');
t_ok(!empty($y['shared']), 'souběžný audit stejné adresy se připojí k běžícímu');
$sy = run($a, $y['id']); $sx = st($a, $x['id']);
t_ok($sy['status'] === 'done' && $sx['status'] === 'done' && count($m->calls) === 2 && $sy['report']['url'] === 'https://jiny.cz/', 'sdílený audit: jediné měření, oba dostanou report');

/* --- validace a limity --- */
$a = aud(['limits' => ['publicPerIpDay' => 2, 'publicPerDay' => 3]], $m, $v);
t_eq(post($a, ['url' => 'http://127.0.0.1'])['reason'], 'ip', 'interní IP odmítnuta (scénář 4)');
t_eq(post($a, ['url' => 'interni.cz'])['reason'], 'private', 'doména mířící do interní sítě odmítnuta');
$a = aud(['limits' => ['publicPerIpDay' => 2, 'publicPerDay' => 3]], $m, $v);
post($a, ['url' => 'example.cz'], '1.0.0.1'); post($a, ['url' => 'jiny.cz'], '1.0.0.1');
t_eq(post($a, ['url' => 'www.example.cz'], '1.0.0.1')['_code'], 429, 'limit auditů na adresu IP za den');
post($a, ['url' => 'www.example.cz'], '1.0.0.2');
t_eq(post($a, ['url' => 'example.cz/kontakt'], '1.0.0.3')['_code'], 429, 'denní limit všech veřejných auditů');
$a = aud(['source' => 'admin', 'limits' => ['publicPerIpDay' => 1, 'adminPerDay' => 5]], $m, $v);
post($a, ['url' => 'example.cz'], '7.7.7.7'); t_eq(post($a, ['url' => 'jiny.cz'], '7.7.7.7')['_code'], 202, 'administrace: bez limitu na adresu IP');
t_eq(post($a, ['url' => 'example.cz'], '1.1.1.1', ['content-type' => 'application/json', 'host' => 'studio.test', 'origin' => 'https://zly.example'])['_code'], 403, 'cizí původ (Origin) odmítnut');
t_eq(aud(['enabled' => false])->handle('POST', 'audit', '{"url":"example.cz"}', [], $H, '1.1.1.1')[0], 503, 'vypnutý audit: 503');
t_eq(ts_site_audit(['db' => ['dsn' => "sqlite:$dir/bezklice.sqlite"], 'meter' => ['apiKeyFile' => "$dir/neni.txt"]])->enabled(), false, 'bez klíče Google: audit vypnutý');

/* --- chyby --- */
$a = aud(['meter' => new TsAuditFakeMeter(['fail' => 'limit'])]);
$o = post($a, ['url' => 'example.cz']); $s = run($a, $o['id']);
t_ok($s['status'] === 'error' && $s['error'] === 'limit', 'vyčerpaná kvóta Google → chyba „limit“ (náhradní text a kontakt ve widgetu)');
$a = aud(['meter' => $tm = new TsAuditFakeMeter(['fail' => 'timeout']), 'maxAttempts' => 2]);
$o = post($a, ['url' => 'example.cz']); $a->advance($o['id']); $s1 = st($a, $o['id']); $a->advance($o['id']); $s2 = st($a, $o['id']);
t_ok($s1['status'] === 'queued' && $s2['status'] === 'error' && $s2['error'] === 'timeout' && count($tm->calls) === 2, 'časový limit: jeden opakovaný pokus, pak chyba');
$a = aud(['meter' => new TsAuditFakeMeter(['fail' => 'site'])]); $o = post($a, ['url' => 'example.cz']);
t_eq(run($a, $o['id'])['error'], 'site', 'web nejde načíst → chyba „site“');
$a = aud(['vision' => new TsAuditFakeVision(['fail' => 'limit'])]); $o = post($a, ['url' => 'example.cz']); $s = run($a, $o['id']);
t_ok($s['status'] === 'done' && !$s['report']['ai']['ok'] && $s['report']['ai']['reason'] === 'error_limit' && count($s['report']['recommendations']) === 4, 'výpadek hodnocení AI: report jen z měření');
$a = aud(['vision' => null]); $o = post($a, ['url' => 'example.cz']); t_eq(run($a, $o['id'])['report']['ai']['reason'], 'off', 'bez modelu: report bez AI části');
$a = aud(['visionPerDay' => 1], $m, $v);
run($a, post($a, ['url' => 'example.cz'])['id']); $s = run($a, post($a, ['url' => 'jiny.cz'], '3.3.3.3')['id']);
t_eq($s['report']['ai']['reason'], 'quota', 'denní strop hodnocení AI (šetří neurony pro Tessu)');
t_eq(TsSiteAudit::parseVision("```json\n{\"items\":[{\"area\":\"Kontrast\",\"text\":\"<b>Zvýrazněte</b> tlačítko.\",\"priority\":9},{\"area\":\"x\",\"text\":\"zvýrazněte tlačítko.\"}]}\n```"), [['area' => 'Kontrast', 'text' => 'Zvýrazněte tlačítko.', 'priority' => 3]], 'výstup modelu: bez HTML, priorita v mezích, bez duplicit');
t_eq(TsSiteAudit::parseVision('Omlouvám se, nemohu.'), [], 'nečitelný výstup modelu → žádná doporučení');
t_ok(strpos(TsSiteAudit::visionPrompt('de'), 'žádná čísla') !== false && strpos(TsSiteAudit::visionPrompt('de'), 'Jazyk textů: de') !== false, 'pokyny modelu: bez čísel, jazyk zadavatele');

/* --- souběh a retence --- */
$a = aud([], $m, $v); $o = post($a, ['url' => 'example.cz']); $j = $a->store()->byToken($o['id']);
t_ok($a->store()->lock((int) $j['id'], 60) && !$a->store()->lock((int) $j['id'], 60), 'zámek: krok nespustí dva souběžné dotazy');
$a = aud([], $m, $v); $o = post($a, ['url' => 'example.cz']); run($a, $o['id']);
$shots = glob("$dir/shots$n/*"); $a->store()->pdo()->exec("UPDATE ts_audit_jobs SET created_at = '2020-01-01 00:00:00'");
t_ok(count($shots) === 4 && $a->cleanup() === 1 && !glob("$dir/shots$n/*") && st($a, $o['id'])['_code'] === 404, 'retence: stará úloha i snímky smazány');

/* --- adaptér PageSpeed proti lokální napodobenině (bez sítě) --- */
$port = 8287; $mock = "$dir/psi-mock.php";
file_put_contents($mock, '<?php require ' . var_export(__DIR__ . '/../core/TsAuditProviders.php', true) . '; require ' . var_export(__DIR__ . '/../core/TsAuditUrl.php', true) . ';
file_put_contents(' . var_export("$dir/psi-req.log", true) . ', $_SERVER["QUERY_STRING"] . "\n", FILE_APPEND);
if (($_GET["key"] ?? "") === "spatny") { http_response_code(400); echo json_encode(["error" => ["code" => 400, "message" => "API key not valid. Please pass a valid API key."]]); exit; }
if (($_GET["key"] ?? "") === "plno") { http_response_code(429); echo "{}"; exit; }
class S { static function sample($s) { return TsAuditFakeMeter::sample($s); } }
require ' . var_export(__DIR__ . '/../core/TsSiteAudit.php', true) . ';
header("Content-Type: application/json"); echo json_encode(S::sample($_GET["strategy"] ?? "mobile"));');
$srv = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", $mock], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes); usleep(700000);
$ps = new TsAuditPageSpeed(['apiKey' => 'test-klic', 'baseUrl' => "http://127.0.0.1:$port/"]);
$r = $ps->measure('https://example.cz/', 'desktop', 'cs');
$q = (string) @file_get_contents("$dir/psi-req.log");
t_ok($r['scores']['performance'] === 88 && $r['device'] === 'desktop' && str_contains($q, 'url=https%3A%2F%2Fexample.cz%2F') && str_contains($q, 'strategy=desktop') && str_contains($q, 'locale=cs')
  && substr_count($q, 'category=') === 4 && str_contains($q, 'key=test-klic'), 'PageSpeed: dotaz s adresou, zařízením, jazykem, 4 kategoriemi a klíčem');
foreach (['spatny' => 'auth', 'plno' => 'limit'] as $k => $kind) {
    try { (new TsAuditPageSpeed(['apiKey' => $k, 'baseUrl' => "http://127.0.0.1:$port/"]))->measure('https://example.cz/', 'mobile', 'cs'); t_ok(false, $kind); }
    catch (TsAuditError $e) { t_eq($e->kind, $kind, "PageSpeed: chyba $kind"); }
}
proc_terminate($srv);
t_ok(strpos((string) @file_get_contents("$dir/php-error.log"), 'test-klic') === false, 'klíč není v logu');

/* --- 1.1.1: titulky z markdownu, obecné přehledy bez hodnoty, vynucené měření, diagnostika AI --- */
t_eq(TsAuditPageSpeed::plain('Prvky `<frame>` nebo `<iframe>` nemají [atribut title](https://dequeuniversity.com/x)'), 'Prvky <frame> nebo <iframe> nemají atribut title', 'titulek: kód zachován jako text, odkaz jen text');
$smp = TsAuditFakeMeter::sample('mobile'); $smp['lighthouseResult']['audits']['network-dependency-tree-insight'] = ['score' => 0, 'title' => 'Strom síťových závislostí', 'scoreDisplayMode' => 'metricSavings'];
$smp['lighthouseResult']['categories']['performance']['auditRefs'][] = ['id' => 'network-dependency-tree-insight', 'weight' => 0];
t_ok(!in_array('network-dependency-tree-insight', array_column(TsAuditPageSpeed::normalize($smp)['issues'], 'id'), true), 'obecný přehled bez zjištěné hodnoty se do nálezů nedostane');
$a = aud(['source' => 'admin', 'vision' => new TsAuditFakeVision(['text' => 'Nemohu posoudit obrázek.'])], $m, $v);
$o = post($a, ['url' => 'example.cz']); $s = run($a, $o['id']);
t_ok($s['report']['ai']['reason'] === 'empty' && $s['report']['ai']['raw'] === 'Nemohu posoudit obrázek.' && $s['report']['ai']['finish'] === 'stop', 'administrace: u prázdného hodnocení diagnostika (výstup modelu)');
$o2 = post($a, ['url' => 'example.cz', 'force' => true]); t_ok($o2['status'] === 'queued' && empty($o2['cached']), 'administrace: vynucené nové měření mimo cache');
$a = aud([], $m, $v); run($a, post($a, ['url' => 'example.cz'])['id']); t_ok(!empty(post($a, ['url' => 'example.cz', 'force' => true], '6.6.6.6')['cached']), 'veřejnost: „force“ se ignoruje (cache)');
t_eq(TsSiteAudit::parseVision('{"items":[{"area":"area name","text":"recommendation","priority":1}]}'), [], 'opsaná šablona z pokynů se zahodí');
