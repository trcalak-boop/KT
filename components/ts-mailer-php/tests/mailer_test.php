<?php
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/TsMailer.php';
$dir = t_tmpdir();
$php = PHP_BINARY;
function smtp_server(string $dir, int $port, string $code = '250') {
    $out = "$dir/smtp-$port.txt";
    $p = proc_open([PHP_BINARY, __DIR__ . '/fake_smtp.php', (string) $port, $out, $code], [], $pipes);
    for ($i = 0; $i < 100 && !is_file("$out.ready"); $i++) usleep(50000);
    return [$p, $out];
}
/* zpráva */
$m = new TsMailer(['transport' => 'log', 'from' => 'web@salon.test', 'fromName' => 'Salon Vlna', 'domain' => 'salon.test', 'logFile' => "$dir/mail.log"]);
[$h, $body] = $m->build('jana@example.com', 'Potvrzení – Střih', "Dobrý den,\ntermín platí.", '<p>Dobrý den</p>', 'recepce@salon.test', 'Recepce', 'abc@salon.test');
t_ok(in_array('Message-ID: <abc@salon.test>', $h, true) && in_array('Subject: =?UTF-8?B?' . base64_encode('Potvrzení – Střih') . '?=', $h, true), 'build: hlavičky, UTF-8 předmět');
t_ok(strpos(implode("\n", $h), 'Reply-To: =?UTF-8?B?') !== false && strpos($body, base64_encode('<p>Dobrý den</p>')) !== false, 'build: Reply-To a HTML část');
$r = $m->send('jana@example.com', 'Test', 'text', '<b>html</b>');
t_ok($r['ok'] && $r['stav'] === 'zapsano' && strpos(file_get_contents("$dir/mail.log"), 'To: <jana@example.com>') !== false, 'transport log');
t_eq($m->send("a@b.cz\r\nBcc: x@y.cz", 'x', 't', 'h')['ok'], false, 'odmítne vložení hlaviček do příjemce');
t_eq($m->send('a@b.cz', "x\r\nBcc: y@z.cz", 't', 'h')['ok'], false, 'odmítne vložení hlaviček do předmětu');
try { new TsMailer(['from' => 'neplatny']); t_ok(false, 'neplatný from'); } catch (InvalidArgumentException $e) { t_ok(true, 'neplatný from → výjimka'); }
/* SMTP proti falešnému serveru (bez TLS, s přihlášením) */
$port = 25800 + random_int(0, 150);
[$p, $out] = smtp_server($dir, $port);
$s = new TsMailer(['from' => 'web@salon.test', 'domain' => 'salon.test', 'smtp_host' => '127.0.0.1', 'smtp_port' => $port, 'smtp_secure' => 'none', 'smtp_user' => 'u', 'smtp_pass' => 'tajne-heslo']);
$r = $s->send('jana@example.com', 'Rezervace', ".tečka na začátku\nřádek", '<p>x</p>');
proc_close($p); $log = (string) @file_get_contents($out);
t_ok($r['ok'] && $r['stav'] === 'odeslano' && strpos($r['odpoved'], 'queued') !== false, 'SMTP: odesláno, potvrzení 250');
t_ok(strpos($log, 'MAIL FROM:<web@salon.test>') !== false && strpos($log, 'RCPT TO:<jana@example.com>') !== false && strpos($log, base64_encode('tajne-heslo')) !== false, 'SMTP: obálka a AUTH LOGIN');
/* SMTP odmítnutí */
$port2 = $port + 200; [$p2, $out2] = smtp_server($dir, $port2, '550');
$s2 = new TsMailer(['from' => 'web@salon.test', 'smtp_host' => '127.0.0.1', 'smtp_port' => $port2, 'smtp_secure' => 'none', 'smtp_pass' => 'tajne-heslo']);
$r2 = $s2->send('nikdo@example.com', 'x', 't', 'h'); proc_close($p2);
t_ok(!$r2['ok'] && strpos($r2['chyba'], 'RCPT TO') !== false && strpos($r2['chyba'], 'tajne') === false, 'SMTP: odmítnutý příjemce, chyba bez hesla');
t_ok(!(new TsMailer(['from' => 'a@b.cz', 'smtp_host' => '127.0.0.1', 'smtp_port' => 1, 'smtp_secure' => 'none']))->send('x@y.cz', 's', 't', 'h')['ok'], 'SMTP: server nedostupný');
/* fronta */
$db = new PDO('sqlite:' . "$dir/q.sqlite", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
TsMailQueue::migrate($db); TsMailQueue::migrate($db);
$q = new TsMailQueue($db, $m, ['attempts' => 2, 'backoffMin' => [0]]);
$id = $q->enqueue('jana@example.com', 'Potvrzení', 't', 'h', ['dedupeKey' => 'rez-1-potvrzeni']);
t_eq($q->enqueue('jana@example.com', 'Potvrzení', 't', 'h', ['dedupeKey' => 'rez-1-potvrzeni']), $id, 'fronta: stejný dedupeKey → stejné id');
t_eq($q->process(), 1, 'fronta: zpracováno');
t_eq($q->get($id)['status'], 'zapsano', 'fronta: stav po odeslání');
t_eq($q->process(), 0, 'fronta: odeslané se už neposílá');
$bad = new TsMailQueue($db, new TsMailer(['from' => 'a@b.cz', 'smtp_host' => '127.0.0.1', 'smtp_port' => 1, 'smtp_secure' => 'none']), ['attempts' => 2, 'backoffMin' => [0]]);
$id2 = $bad->enqueue('x@example.com', 'S', 't', 'h');
$bad->process(); t_ok($bad->get($id2)['status'] === 'chyba' && (int) $bad->get($id2)['attempts'] === 1, 'fronta: chyba → další pokus');
$bad->process(); t_eq($bad->get($id2)['status'], 'selhalo', 'fronta: po vyčerpání pokusů selhalo');
