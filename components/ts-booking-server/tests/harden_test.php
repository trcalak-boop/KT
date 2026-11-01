<?php
/* ts_booking_harden: HTTPS povinné mimo localhost, zúžení open_basedir (zápis mimo povolené složky selže). */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir(); mkdir("$dir/web"); mkdir("$dir/sub/test", 0755, true); mkdir("$dir/private/booking-test/data", 0700, true);
file_put_contents("$dir/web/PRODUKCE.txt", 'produkce');
$_SERVER['REMOTE_ADDR'] = '203.0.113.9'; unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']); $_SERVER['SERVER_PORT'] = 80;
ob_start(); $r = ts_booking_harden(['requireHttps' => true]); $out = ob_get_clean();
t_ok($r === false && strpos($out, '"https"') !== false, 'bez HTTPS z internetu → odmítnuto');
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https'; t_ok(ts_booking_harden(['requireHttps' => true]), 'HTTPS přes proxy (X-Forwarded-Proto) → povoleno');
unset($_SERVER['HTTP_X_FORWARDED_PROTO']); $_SERVER['HTTPS'] = 'on'; t_ok(ts_booking_harden(['requireHttps' => true]), 'HTTPS → povoleno');
ob_start(); $r = ts_booking_harden(['paths' => ["$dir/neexistuje"]]); ob_end_clean(); t_eq($r, false, 'neexistující povolená složka → odmítnuto (bezpečně zavřeno)');
/* zúžení open_basedir v samostatném procesu (nastavení platí do konce procesu) */
$code = '<?php require ' . var_export(realpath(__DIR__ . '/../core/bootstrap.php'), true) . '; $d = ' . var_export($dir, true) . ';
ts_booking_harden(["paths" => [$d . "/sub/test", $d . "/private/booking-test"]]);
echo json_encode(["web_zapis" => @file_put_contents($d . "/web/HACK.txt", "x") !== false, "web_cteni" => @file_get_contents($d . "/web/PRODUKCE.txt") !== false,
 "web_seznam" => @scandir($d . "/web") !== false, "private_zapis" => @file_put_contents($d . "/private/booking-test/data/ok.txt", "x") !== false,
 "webroot_cteni" => @is_dir($d . "/sub/test"), "sqlite" => (function () use ($d) { try { new PDO("sqlite:" . $d . "/private/booking-test/data/t.sqlite"); return true; } catch (Throwable $e) { return false; } })(),
 "sqlite_mimo" => (function () use ($d) { try { new PDO("sqlite:" . $d . "/web/t.sqlite"); return true; } catch (Throwable $e) { return false; } })()]);';
file_put_contents("$dir/sonda.php", $code);
$res = json_decode(shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$dir/sonda.php")), true);
t_ok($res && !$res['web_zapis'] && !$res['web_cteni'] && !$res['web_seznam'], 'open_basedir: produkční /web nejde zapsat, číst ani vypsat', json_encode($res));
t_ok($res && $res['private_zapis'] && $res['webroot_cteni'] && $res['sqlite'], 'open_basedir: /private/booking-test a webroot fungují, SQLite v private ano');
t_ok($res && !$res['sqlite_mimo'], 'open_basedir: SQLite databázi mimo povolené složky nejde otevřít');
t_ok(!is_file("$dir/web/HACK.txt") && file_get_contents("$dir/web/PRODUKCE.txt") === 'produkce', 'produkční soubor beze změny');
