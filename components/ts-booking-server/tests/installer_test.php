<?php
/* Instalátor: kontrola prostředí, databáze, SMTP bez přihlášení, zkušební e-mail (bez hesla odmítnut), jednorázový administrátor. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
require __DIR__ . '/../core/TsBookingInstaller.php';
$dir = t_tmpdir(); mkdir("$dir/web"); mkdir("$dir/private/data", 0700, true);
$_SERVER['DOCUMENT_ROOT'] = "$dir/web"; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$week = []; for ($d = 0; $d < 7; $d++) $week[] = ['open' => $d >= 1 && $d <= 5, 'from' => 540, 'to' => 1020];
file_put_contents("$dir/private/cfg.json", json_encode(['mode' => 'resources', 'hours' => $week, 'services' => [['id' => 's', 'durations' => [60]]], 'resources' => [['id' => 'r', 'services' => '*']]]));
$s = ['timezone' => 'Europe/Prague', 'db' => ['dsn' => "sqlite:$dir/private/data/r.sqlite"], 'configFile' => "$dir/private/cfg.json", 'authDir' => "$dir/private/data/auth",
  'mail' => ['transport' => 'log', 'logFile' => "$dir/private/data/mail.log", 'redirectTo' => 'test@salon.test', 'from' => 'rezervace@salon.test', 'domain' => 'salon.test', 'smtp_host' => '127.0.0.1', 'smtp_port' => 1, 'smtp_secure' => 'none'],
  'install' => ['codeHash' => password_hash('KOD-123', PASSWORD_DEFAULT), 'until' => '2099-01-01', 'testRecipient' => 'test@salon.test']];
$i = new TsBookingInstaller($s);
$chk = []; foreach ($i->checks() as [$t, $ok]) $chk[$t] = $ok;
t_ok($chk['konfigurace rezervací existuje a je platná'] && $chk['konfigurace rezervací je mimo webroot'] && $chk['databáze SQLite je mimo webroot'] && $chk['relace správy jsou mimo webroot'], 'kontrola: konfigurace, databáze a relace mimo webroot');
t_ok($chk['složka dat je zapisovatelná'] && $chk['HTTPS'], 'kontrola: zápis dat, HTTPS (lokálně povoleno)');
t_ok($chk['SMTP heslo je vyplněné (hodnota se nezobrazuje)'] === false, 'kontrola: SMTP heslo zatím chybí');
$s2 = $s; $s2['db']['dsn'] = "sqlite:$dir/web/r.sqlite";
$c2 = []; foreach ((new TsBookingInstaller($s2))->checks() as [$t, $ok]) $c2[$t] = $ok;
t_eq($c2['databáze SQLite je mimo webroot'], false, 'kontrola odhalí databázi ve webrootu');
[$ok, $m] = $i->action('migrate', []); t_ok($ok && strpos($m, 'migrace 1') !== false, 'inicializace databáze');
[$ok, $m] = $i->action('migrate', []); t_ok($ok && strpos($m, 'aktuální') !== false, 'inicializace podruhé nic nemění');
[$ok, $m] = $i->action('probe', []); t_eq($ok, false, 'SMTP probe: nedostupný server se nahlásí');
[$ok, $m] = $i->action('testmail', []); t_ok(!$ok && strpos($m, 'heslo') !== false, 'zkušební e-mail bez hesla odmítnut');
[$ok, $m] = $i->action('admin', ['username' => 'majitel', 'name' => 'M', 'password' => 'kratke', 'password2' => 'kratke']); t_ok(!$ok && strpos($m, '12') !== false, 'krátké heslo odmítnuto');
[$ok, $m] = $i->action('admin', ['username' => 'majitel', 'name' => 'M', 'password' => 'dlouhe-heslo-2026', 'password2' => 'jine-heslo-2026']); t_ok(!$ok && strpos($m, 'neshodují') !== false, 'neshodná hesla odmítnuta');
[$ok, $m] = $i->action('admin', ['username' => 'majitel', 'name' => 'Majitelka', 'password' => 'dlouhe-heslo-2026', 'password2' => 'dlouhe-heslo-2026']); t_ok($ok && $i->locked(), 'administrátor vytvořen a instalace zamčena');
$u = ts_booking_store($s)->verifyUser('majitel', 'dlouhe-heslo-2026'); t_ok($u && $u['role'] === 'admin', 'administrátor se může přihlásit');
[$ok, $m] = $i->action('admin', ['username' => 'druhy', 'name' => 'X', 'password' => 'dlouhe-heslo-2026', 'password2' => 'dlouhe-heslo-2026']); t_ok(!$ok && strpos($m, 'už existuje') !== false, 'druhý administrátor přes instalátor nejde');
t_ok(!is_file("$dir/web/r.sqlite") && count(glob("$dir/web/*")) === 0, 'do webrootu se nic nezapsalo');
