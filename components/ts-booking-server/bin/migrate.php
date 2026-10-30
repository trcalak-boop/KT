<?php
/* Migrace databáze: php migrate.php <server-config.php>   (před migrací zálohujte databázi) */
require __DIR__ . '/../core/bootstrap.php';
$s = require ($argv[1] ?? ''); $s['db']['autoMigrate'] = false;
$v = ts_booking_store($s)->migrate();
echo $v ? 'Provedeny migrace: ' . implode(', ', $v) . "\n" : "Databáze je aktuální.\n";
