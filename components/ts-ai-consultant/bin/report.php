<?php
/* ts-ai-consultant – přehled útraty a výsledků za měsíc (bez obsahu konverzací).
   php bin/report.php /cesta/mimo/webroot/ai-consultant.php [RRRR-MM] */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../core/bootstrap.php';
$s = require ($argv[1] ?? exit("Použití: php report.php <konfigurace.php> [RRRR-MM]\n"));
$r = ts_ai_store($s)->report($argv[2] ?? null);
printf("Měsíc %s: utraceno %.4f USD z %.2f USD, konverzací %d\n", $r['month'], $r['spentMicro'] / 1e6, (float) ($s['monthlyBudgetUsd'] ?? 10), $r['conversations']);
foreach ($r['byOutcome'] as $o) printf("  %-18s %5d×  %.4f USD  (vstup %d, výstup %d, cache %d tokenů)\n", $o['outcome'], $o['n'], $o['c'] / 1e6, $o['i'], $o['o'], $o['cr']);
