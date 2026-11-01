<?php
/* Kontrolní stránka: kód povinný, zámek po 5 pokusech, vypršení, klíč se nezobrazí, kontroly prostředí bez sítě k AI. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir(); mkdir("$dir/web"); mkdir("$dir/private/data", 0700, true);
$_SERVER['DOCUMENT_ROOT'] = "$dir/web"; $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_HOST'] = 'localhost';
$KEY = 'sk-ant-api03-KONTROLA-falesny';
file_put_contents("$dir/private/klic.txt", $KEY);
$s = ['provider' => 'fake', 'apiKeyFile' => "$dir/private/klic.txt", 'sdkAutoload' => "$dir/private/vendor/autoload.php", 'knowledgeFile' => __DIR__ . '/fixtures/znalosti.json',
  'db' => ['dsn' => "sqlite:$dir/private/data/ai.sqlite"], 'monthlyBudgetUsd' => 10, 'pricing' => ['fake' => [0, 0, 0, 0]], 'contactEmail' => 'x@y.test',
  'check' => ['codeHash' => password_hash('KOD-AI-1', PASSWORD_DEFAULT), 'until' => '2099-01-01', 'probe' => 'tcp://127.0.0.1:9']];
function page(array $s, array $post): string { $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET'; $_POST = $post; ob_start(); (new TsAiCheck($s))->serve(); return ob_get_clean(); }
$h = page($s, []);
t_ok(strpos($h, 'Kontrolní kód') !== false && strpos($h, '<table') === false, 'bez kódu jen formulář');
$h = page($s, ['code' => 'spatne', 'a' => 'check']); t_ok(strpos($h, 'Neplatný kód') !== false && strpos($h, '<table') === false, 'špatný kód odmítnut');
$h = page($s, ['code' => 'KOD-AI-1', 'a' => 'check']);
t_ok(strpos($h, '<table') !== false && strpos($h, $KEY) === false, 'správný kód: kontroly, klíč se nezobrazí');
t_ok(preg_match('/OK<\/td><td>soubor s API klíčem existuje a je mimo webroot/', $h) && preg_match('/poskytovatel AI: fake/', $h) && strpos($h, 'SDK Anthropic') === false, 'kontrola klíče OK; SDK se kontroluje jen u Claude');
$hc = page(['provider' => 'claude'] + $s, ['code' => 'KOD-AI-1', 'a' => 'check']);
t_ok(preg_match('/CHYBA<\/td><td>SDK Anthropic a Guzzle se načtou/', $hc) === 1, 'u Claude chybějící SDK nahlášeno');
$hf = page(['provider' => 'cloudflare', 'cloudflare' => ['accountId' => str_repeat('a', 32)], 'budget' => ['limit' => 9500, 'period' => 'day', 'unit' => 'neuron']] + $s, ['code' => 'KOD-AI-1', 'a' => 'check']);
t_ok(preg_match('/OK<\/td><td>Cloudflare: ID účtu vyplněné/', $hf) && preg_match('/rozpočet: využito 0.0000 z 9500 neuron \(dnes, UTC\)/', $hf) && preg_match('/model: @cf\/meta\/llama-3.3-70b-instruct-fp8-fast/', $hf), 'Cloudflare: účet, denní kvóta v neuronech, model s ceníkem');
t_ok(preg_match('/OK<\/td><td>znalosti: 1 sekcí, 2 referencí/', $h) && preg_match('/OK<\/td><td>databáze funguje a je mimo webroot/', $h), 'znalosti a databáze');
t_ok(preg_match('/CHYBA<\/td><td>hosting se spojí s 127.0.0.1:9/', $h), 'kontrola spojení: adresa z konfigurace (v testu localhost, žádná síť)');
$h = page($s, ['code' => 'KOD-AI-1', 'a' => 'live']); t_ok(strpos($h, 'OK – odpověď: (Zkušební režim bez AI)') !== false, 'jeden dotaz přes bránu (falešný poskytovatel)');
for ($i = 0; $i < 4; $i++) page($s, ['code' => 'x', 'a' => 'check']);
$h = page($s, ['code' => 'KOD-AI-1', 'a' => 'check']); t_ok(strpos($h, 'Příliš mnoho pokusů') !== false && strpos($h, '<table') === false, 'po 5 špatných pokusech zámek i pro správný kód');
$s['check']['until'] = '2020-01-01'; $h = page($s, ['code' => 'KOD-AI-1', 'a' => 'check']); t_ok(strpos($h, 'vypršela') !== false && strpos($h, '<table') === false, 'po vypršení nic neukáže');
$s2 = $s; $s2['apiKeyFile'] = "$dir/web/klic.txt"; copy("$dir/private/klic.txt", "$dir/web/klic.txt"); $s2['check']['until'] = '2099-01-01'; $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
$h = page($s2, ['code' => 'KOD-AI-1', 'a' => 'check']); t_ok(preg_match('/CHYBA<\/td><td>soubor s API klíčem existuje a je mimo webroot/', $h), 'klíč ve webrootu nahlášen jako chyba');
