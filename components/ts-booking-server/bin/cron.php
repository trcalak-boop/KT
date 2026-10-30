<?php
/* Pravidelná údržba (cron, např. každých 10 minut): odeslání fronty e-mailů a anonymizace starých rezervací.
   php cron.php <server-config.php> */
require __DIR__ . '/../core/bootstrap.php';
$s = require ($argv[1] ?? ''); $store = ts_booking_store($s);
$sent = ($q = ts_booking_queue($s, $store)) ? $q->process(50) : 0;
$api = ts_booking_api($s); $today = $api->engine()->today();
$anon = isset($s['retentionDays']) ? $store->anonymizeOlderThan((int) $s['retentionDays'], $today) : 0;
echo date('c') . " fronta: $sent, anonymizováno: $anon\n";
