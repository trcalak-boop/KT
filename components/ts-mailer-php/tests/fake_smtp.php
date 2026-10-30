<?php
/* Falešný SMTP server pro testy: přijme jednu zprávu a uloží ji do souboru. php fake_smtp.php <port> <výstup> [kód-RCPT] */
[$_, $port, $out] = $argv; $rcpt = $argv[3] ?? '250';
$srv = stream_socket_server("tcp://127.0.0.1:$port", $e, $es); if (!$srv) exit(1);
file_put_contents("$out.ready", '1');
$c = @stream_socket_accept($srv, 20); if (!$c) exit(2);
$w = function ($l) use ($c) { fwrite($c, $l . "\r\n"); };
$w('220 test ESMTP'); $log = ''; $data = false; $msg = '';
while (($l = fgets($c)) !== false) {
  if ($data) { if (rtrim($l, "\r\n") === '.') { $data = false; $w('250 2.0.0 Ok: queued as TEST1'); continue; } $msg .= $l; continue; }
  $log .= $l; $u = strtoupper(substr($l, 0, 4));
  if ($u === 'EHLO') { $w('250-test'); $w('250 AUTH LOGIN'); }
  elseif ($u === 'AUTH') $w('334 VXNlcm5hbWU6');
  elseif (strpos($log, 'AUTH') !== false && substr_count($log, "\n") === 3) $w('334 UGFzc3dvcmQ6');
  elseif (strpos($log, 'AUTH') !== false && substr_count($log, "\n") === 4) $w('235 ok');
  elseif ($u === 'MAIL') $w('250 ok');
  elseif ($u === 'RCPT') $w($rcpt . ' rcpt');
  elseif ($u === 'DATA') { $w('354 go'); $data = true; }
  elseif ($u === 'QUIT') { $w('221 bye'); break; }
  else $w('250 ok');
}
file_put_contents($out, $log . "\n--DATA--\n" . $msg);
