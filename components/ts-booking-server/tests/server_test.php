<?php
/* Testy serverové části: databáze, transakční založení, idempotence, ochrany formuláře, zrušení odkazem,
   e-maily přes frontu, správa (přihlášení, CSRF, role, stavy, blokace), GDPR, režim capacity. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/bootstrap.php';
$dir = t_tmpdir();
$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
$week = []; for ($d = 0; $d < 7; $d++) $week[] = ['open' => $d >= 1 && $d <= 5, 'from' => 540, 'to' => 1020];
$cfg = ['mode' => 'resources', 'step' => 30, 'leadMin' => 60, 'maxDays' => 30, 'hours' => $week,
  'services' => [['id' => 'strih', 'name' => 'Střih', 'durations' => [60], 'prices' => [600], 'buffer' => 0]],
  'resources' => [['id' => 'jana', 'name' => 'Jana', 'services' => '*'], ['id' => 'eva', 'name' => 'Eva', 'services' => '*']]];
file_put_contents("$dir/cfg.json", json_encode($cfg));
$settings = ['db' => ['dsn' => "sqlite:$dir/data/r.sqlite"], 'configFile' => "$dir/cfg.json", 'authDir' => "$dir/auth", 'cookiePath' => '/',
  'rateLimit' => ['bookings' => [10, 3600]], 'minFillSeconds' => 3, 'clock' => ['today' => '2026-11-04', 'nowMin' => 500],
  'mail' => ['transport' => 'log', 'logFile' => "$dir/mail.log", 'from' => 'rezervace@salon.test', 'fromName' => 'Salon', 'domain' => 'salon.test'],
  'notify' => ['business' => 'Salon Test', 'staffEmail' => 'provoz@salon.test', 'cancelUrl' => 'https://salon.test/zrusit?id={id}&token={token}', 'adminUrl' => 'https://salon.test/sprava']];
$api = ts_booking_api($settings); $store = ts_booking_store($settings);
t_eq($store->migrate(), [], 'migrace: podruhé nic neprovede');
$call = function ($m, $r, $body = [], $h = [], $q = []) use (&$api) { return $api->handle($m, $r, $q, $body, $h); };
$form = ['date' => '2026-11-05', 'service' => 'strih', 'start' => 600, 'resource' => 'any', 'name' => 'Jana Nováková', 'email' => 'jana@example.com', 'phone' => '777 123 456', 'consent' => true, 'openedMs' => 9000, 'hp' => ''];

/* veřejné API */
[$st, $res] = $call('GET', 'config'); t_ok($st === 200 && $res['config']['services'][0]['id'] === 'strih', 'GET config');
[$st, $res] = $call('POST', 'bookings', $form, ['idempotency-key' => 'k1']);
t_ok($st === 201 && $res['booking']['resource'] === 'jana' && $res['booking']['status'] === 'pending' && strlen($res['cancelToken']) === 32, 'POST bookings → 201, přidělena Jana, čeká na potvrzení');
$id1 = $res['booking']['id']; $tok1 = $res['cancelToken'];
t_ok(!isset($res['booking']['name']) && !isset($res['booking']['email']), 'odpověď bez osobních údajů');
[$st, $res] = $call('POST', 'bookings', $form, ['idempotency-key' => 'k1']);
t_ok($st === 200 && $res['duplicate'] && $res['booking']['id'] === $id1 && $res['cancelToken'] === null, 'stejný idempotenční klíč → stejná rezervace, žádná nová');
[$st, $res] = $call('POST', 'bookings', $form, ['idempotency-key' => 'k2']); t_ok($st === 201 && $res['booking']['resource'] === 'eva', 'druhá rezervace na 10:00 → Eva');
[$st, $res] = $call('POST', 'bookings', $form, ['idempotency-key' => 'k3']); t_eq([$st, $res['error']], [409, 'taken'], 'třetí rezervace na 10:00 → 409 obsazeno');
[$st, $res] = $call('POST', 'bookings', ['start' => 'x'] + $form); t_ok($st === 422 && in_array('start', $res['fields'], true), 'neplatný čas → 422');
[$st, $res] = $call('POST', 'bookings', ['email' => 'neplatny', 'consent' => false] + $form); t_ok($st === 422 && $res['fields'] === ['email', 'consent'], 'e-mail a souhlas povinné');
[$st, $res] = $call('POST', 'bookings', ['hp' => 'http://spam'] + $form); t_eq($st, 400, 'skryté pole vyplněné → robot');
[$st, $res] = $call('POST', 'bookings', ['openedMs' => 800] + $form); t_eq($st, 400, 'formulář odeslaný za 0,8 s → robot');
[$st, $res] = $call('POST', 'bookings', ['date' => '2026-11-04', 'start' => 540] + $form, ['idempotency-key' => 'k4']); t_eq([$st, $res['error']], [409, 'past'], 'dnes 9:00 při čase 8:20 a předstihu 60 min → 409 past');
[$st, $res] = $call('POST', 'bookings', ['date' => '2026-11-07'] + $form); t_eq([$st, $res['error']], [422, 'closed'], 'sobota zavřeno → 422');
[$st, $res] = $call('POST', 'bookings', ['date' => '2026-12-30'] + $form); t_eq([$st, $res['error']], [422, 'closed'], 'mimo maxDays → 422');
[$st, $res] = $call('POST', 'bookings', ['service' => 'nic'] + $form); t_eq([$st, $res['error']], [422, 'service'], 'neznámá služba → 422');
[$st, $res] = $call('GET', 'availability', [], [], ['from' => '2026-11-01', 'to' => '2027-12-31']);
t_ok($st === 200 && $res['from'] === '2026-11-04' && $res['to'] === '2026-12-05' && count($res['state']['bookings']) === 2, 'availability: rozsah oříznut na dnes…maxDays, 2 rezervace');
t_ok(!isset($res['state']['bookings'][0]['name']) && !isset($res['state']['bookings'][0]['id']) && !isset($res['state']['bookings'][0]['email']), 'availability bez jmen, e-mailů a id');
/* limit požadavků: 10 za hodinu – započítává se každý pokus o rezervaci (i neplatný), roboti zachycení dřív se nepočítají */
[$st, $res] = $call('POST', 'bookings', ['start' => 660] + $form, ['idempotency-key' => 'k5']); t_eq($st, 429, 'limit rezervací z jedné adresy → 429');
$_SERVER['REMOTE_ADDR'] = '198.51.100.21';
/* e-maily */
$log = file_get_contents("$dir/mail.log");
t_ok(substr_count($log, 'To: <jana@example.com>') === 2 && substr_count($log, 'To: <provoz@salon.test>') === 2, 'e-maily: zákaznici (přijato) + provozovna, dvojklik bez dalšího e-mailu');
t_ok(strpos($log, base64_encode('Rezervace přijata – Salon Test')) !== false || strpos($log, '=?UTF-8?B?' . base64_encode('Rezervace přijata – Salon Test')) !== false, 'předmět „Rezervace přijata“');
t_ok(strpos(base64_decode(implode('', array_slice(explode("\r\n\r\n", explode('Content-Type: text/plain', $log)[1]), 1, 1))), 'zrusit?id=' . $id1) !== false, 'odkaz pro zrušení v e-mailu');
/* zrušení odkazem */
[$st, $res] = $call('POST', 'cancel', ['id' => $id1, 'token' => str_repeat('0', 32)]); t_eq($st, 403, 'špatný token → 403');
[$st, $res] = $call('POST', 'cancel', ['id' => $id1, 'token' => $tok1]); t_ok($st === 200 && $res['booking']['status'] === 'cancelled', 'zrušení odkazem');
[$st, $res] = $call('POST', 'cancel', ['id' => $id1, 'token' => $tok1]); t_eq($st, 403, 'odkaz je jednorázový');
[$st, $res] = $call('POST', 'bookings', $form, ['idempotency-key' => 'k6']); t_ok($st === 201 && $res['booking']['resource'] === 'jana', 'po zrušení je 10:00 u Jany zase volno');
t_ok(strpos(file_get_contents("$dir/mail.log"), '=?UTF-8?B?' . base64_encode('Rezervace zrušena – Salon Test') . '?=') !== false, 'e-mail o zrušení');

/* správa */
$store->createUser('majitel', 'Majitelka', 'dlouhe-heslo-123', 'admin', 'majitel@salon.test');
$store->createUser('recepce', 'Recepce', 'recepce-heslo-99', 'viewer');
try { $store->createUser('x', 'X', 'kratke'); t_ok(false, 'krátké heslo'); } catch (InvalidArgumentException $e) { t_ok(true, 'createUser odmítne krátké jméno/heslo'); }
[$st, $res] = $call('GET', 'admin/bookings'); t_eq($st, 401, 'správa bez přihlášení → 401');
[$st, $res] = $call('POST', 'admin/login', ['username' => 'majitel', 'password' => 'spatne']); t_eq($st, 401, 'špatné heslo → 401');
[$st, $res] = $call('POST', 'admin/login', ['username' => 'majitel', 'password' => 'dlouhe-heslo-123']); t_ok($st === 200 && $res['user']['role'] === 'admin', 'přihlášení');
$csrf = $res['csrf'];
[$st, $res] = $call('GET', 'admin/bookings', [], [], ['from' => '2026-11-01', 'to' => '2026-11-30']); t_ok($st === 200 && count($res['bookings']) === 3 && $res['bookings'][0]['name'] === 'Jana Nováková', 'seznam rezervací s údaji (jen správa)');
$pending = array_values(array_filter($res['bookings'], fn($b) => $b['status'] === 'pending'));
[$st, $res] = $call('POST', 'admin/status', ['id' => $pending[0]['id'], 'status' => 'confirmed']); t_eq([$st, $res['error']], [403, 'csrf'], 'POST bez CSRF → 403');
[$st, $res] = $call('POST', 'admin/status', ['id' => $pending[0]['id'], 'status' => 'confirmed'], ['x-csrf' => $csrf]); t_ok($st === 200 && $res['booking']['status'] === 'confirmed', 'potvrzení rezervace');
[$st, $res] = $call('POST', 'admin/status', ['id' => $pending[0]['id'], 'status' => 'pending'], ['x-csrf' => $csrf]); t_eq([$st, $res['error']], [409, 'transition'], 'nepovolený přechod stavu → 409');
t_ok(strpos(file_get_contents("$dir/mail.log"), '=?UTF-8?B?' . base64_encode('Rezervace potvrzena – Salon Test') . '?=') !== false, 'e-mail o potvrzení');
$ev = $store->events($pending[0]['id']); t_ok(count($ev) === 2 && $ev[1]['actor'] === 'majitel' && $ev[1]['new_status'] === 'confirmed', 'auditní záznam (kdo, co)');
[$st, $res] = $call('POST', 'admin/blocks', ['date' => '2026-11-06', 'resource' => 'all', 'from' => 540, 'to' => 1020, 'reason' => 'školení'], ['x-csrf' => $csrf]); t_eq($st, 201, 'blokace celého dne');
$blk = $res['block']['id'];
[$st, $res] = $call('POST', 'bookings', ['date' => '2026-11-06'] + $form, ['idempotency-key' => 'k7']); t_eq([$st, $res['error']], [422, 'closed'], 'zablokovaný den → nelze rezervovat');
[$st, $res] = $call('GET', 'availability', [], [], ['from' => '2026-11-06', 'to' => '2026-11-06']); t_ok(count($res['state']['blocks']) === 1 && !isset($res['state']['blocks'][0]['reason']), 'blokace ve veřejné dostupnosti bez důvodu');
[$st, $res] = $call('POST', 'admin/blocks/delete', ['id' => $blk], ['x-csrf' => $csrf]); t_eq($st, 200, 'smazání blokace');
[$st, $res] = $call('POST', 'admin/blocks', ['date' => '2026-11-06', 'resource' => 'nikdo', 'from' => 540, 'to' => 600], ['x-csrf' => $csrf]); t_eq($st, 422, 'blokace neznámého zdroje → 422');
[$st, $res] = $call('GET', 'admin/export', [], [], ['email' => 'JANA@example.com']); t_ok($st === 200 && count($res['bookings']) === 3, 'GDPR export podle e-mailu');
[$st, $res] = $call('POST', 'admin/anonymize', ['id' => $id1], ['x-csrf' => $csrf]); t_ok($st === 200 && $store->get($id1)['name'] === null && $store->get($id1)['anonymizedAt'] !== null, 'anonymizace');
[$st, $res] = $call('POST', 'admin/logout', [], ['x-csrf' => $csrf]); t_eq($st, 200, 'odhlášení');
[$st, $res] = $call('POST', 'admin/login', ['username' => 'recepce', 'password' => 'recepce-heslo-99']); $csrf2 = $res['csrf'];
[$st, $res] = $call('GET', 'admin/bookings'); t_eq($st, 200, 'viewer: smí číst');
[$st, $res] = $call('POST', 'admin/status', ['id' => $pending[0]['id'], 'status' => 'done'], ['x-csrf' => $csrf2]); t_eq([$st, $res['error']], [403, 'permission'], 'viewer: nesmí měnit stav');
[$st, $res] = $call('GET', 'admin/export', [], [], ['email' => 'jana@example.com']); t_eq($st, 403, 'viewer: nesmí GDPR export');
$call('POST', 'admin/logout', [], ['x-csrf' => $csrf2]);
for ($i = 0; $i < 6; $i++) $call('POST', 'admin/login', ['username' => 'majitel', 'password' => 'spatne' . $i]);
[$st, $res] = $call('POST', 'admin/login', ['username' => 'majitel', 'password' => 'dlouhe-heslo-123']); t_eq([$st, $res['error']], [429, 'locked'], 'zámek po 6 špatných heslech');
/* retence */
$store->pdo()->exec("UPDATE ts_booking_bookings SET date = '2023-01-10' WHERE idem_key = 'k2'");
t_eq($store->anonymizeOlderThan(730, '2026-11-04'), 1, 'retence: anonymizace rezervací starších než 730 dní');
/* režim capacity */
$cap = ['mode' => 'capacity', 'step' => 30, 'hours' => $week, 'services' => [['id' => 'stul', 'name' => 'Stůl', 'durations' => [120]]], 'capacity' => ['units' => 10, 'lastBeforeCloseMin' => 60, 'todayLeadMin' => 60]];
file_put_contents("$dir/cap.json", json_encode($cap));
$api2 = ts_booking_api(['configFile' => "$dir/cap.json", 'db' => ['dsn' => "sqlite:$dir/data/cap.sqlite"]] + $settings);
$r1 = $api2->handle('POST', 'bookings', [], ['service' => 'stul', 'units' => 8, 'resource' => null] + $form, ['idempotency-key' => 'c1']);
$r2 = $api2->handle('POST', 'bookings', [], ['service' => 'stul', 'units' => 3] + $form, ['idempotency-key' => 'c2']);
$r3 = $api2->handle('POST', 'bookings', [], ['service' => 'stul', 'units' => 2] + $form, ['idempotency-key' => 'c3']);
t_ok($r1[0] === 201 && $r1[1]['booking']['units'] === 8 && empty($r1[1]['booking']['resource']), 'capacity: 8 míst');
t_eq([$r2[0], $r2[1]['error']], [409, 'capacity'], 'capacity: dalších 3 se nevejde');
t_eq($r3[0], 201, 'capacity: 2 místa ještě ano');
/* neplatná konfigurace */
file_put_contents("$dir/bad.json", json_encode(['mode' => 'resources']));
try { ts_booking_api(['configFile' => "$dir/bad.json"] + $settings); t_ok(false, 'neplatná konfigurace'); } catch (RuntimeException $e) { t_ok(strpos($e->getMessage(), 'hours') !== false, 'neplatná konfigurace → výjimka s popisem'); }
