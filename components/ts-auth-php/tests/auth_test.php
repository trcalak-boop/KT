<?php
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/TsAuth.php';

$dir = t_tmpdir();
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$a = new TsAuth(['dataDir' => $dir, 'maxAttempts' => 3, 'lockSeconds' => 60,
    'policy' => ['admin' => ['*'], 'sales' => ['crm.*', 'booking.view'], 'viewer' => ['booking.view']]]);
$a->start();
t_ok(session_status() === PHP_SESSION_ACTIVE && is_dir("$dir/relace"), 'relace běží, ukládá se do dataDir/relace');
t_eq(strlen($a->csrfToken()), 48, 'CSRF token 48 znaků');
t_ok(!$a->csrfOk('spatny') && !$a->csrfOk('') && $a->csrfOk($a->csrfToken()), 'csrfOk');
$_SERVER['HTTP_X_CSRF'] = $a->csrfToken(); t_ok($a->csrfOk(), 'csrfOk z hlavičky X-CSRF'); unset($_SERVER['HTTP_X_CSRF']);
t_ok(!$a->loggedIn(), 'nepřihlášen');
$old = $a->csrfToken(); $a->login(5, 'sales');
t_ok($a->loggedIn() && $a->userId() === 5 && $a->role() === 'sales' && $a->csrfToken() !== $old, 'login: uživatel, role, nový CSRF token');
t_ok($a->can('crm.leady') && $a->can('booking.view') && !$a->can('booking.edit') && !$a->can('settings'), 'can: sales');
t_ok($a->can('cokoli', 'admin') && !$a->can('crm.leady', 'viewer') && !$a->can('x', 'neznama'), 'can: admin, viewer, neznámá role');
$_SESSION['t'] = time() - 13 * 3600; session_write_close(); $a->start();
t_ok(!$a->loggedIn(), 'odhlášení po nečinnosti');
$a->login(1); $a->logout(); t_ok(!$a->loggedIn() && $a->csrfToken() !== '', 'logout');
$h = TsAuth::hashPassword('dlouhe-heslo-123');
t_ok(TsAuth::verifyPassword('dlouhe-heslo-123', $h) && !TsAuth::verifyPassword('jine', $h) && !TsAuth::verifyPassword('x', ''), 'hesla');
t_ok($a->strongEnough('1234567890') && !$a->strongEnough('123456789') && !$a->strongEnough(null), 'síla hesla (min. 10)');
t_eq($a->lockedFor(), 0, 'bez pokusů není zamčeno');
$a->failedAttempt(); $a->failedAttempt(); t_eq($a->lockedFor(), 0, '2 špatné pokusy: ještě ne');
$a->failedAttempt(); $l = $a->lockedFor(); t_ok($l > 0 && $l <= 60, 'po 3 pokusech zamčeno (' . $l . ' s)');
$raw = file_get_contents("$dir/pokusy.json"); t_ok(strpos($raw, '203.0.113.7') === false, 'IP se neukládá, jen otisk');
$_SERVER['REMOTE_ADDR'] = '198.51.100.1'; t_eq($a->lockedFor(), 0, 'jiná adresa není zamčená');
$_SERVER['REMOTE_ADDR'] = '203.0.113.7'; $a->resetAttempts(); t_eq($a->lockedFor(), 0, 'resetAttempts');
$r = []; for ($i = 0; $i < 4; $i++) $r[] = $a->rateLimit('poptavka', 3, 3600);
t_eq($r, [true, true, true, false], 'rateLimit 3/h');
t_ok($a->rateLimit('jiny-formular', 3), 'rateLimit: klíče jsou oddělené');
t_ok(TsAuth::writeAtomic("$dir/x/y.txt", 'obsah') && file_get_contents("$dir/x/y.txt") === 'obsah' && count(glob("$dir/x/.tmp-*")) === 0, 'writeAtomic');
try { new TsAuth([]); t_ok(false, 'chybí dataDir'); } catch (InvalidArgumentException $e) { t_ok(true, 'chybí dataDir → výjimka'); }
