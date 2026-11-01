<?php
/* ts-ai-consultant – údržba (cron, např. jednou za hodinu): odeslání fronty oznámení, retence konverzací a leadů.
   php bin/cron.php /cesta/mimo/webroot/ai-consultant.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../core/bootstrap.php';
$s = require ($argv[1] ?? exit("Použití: php cron.php <konfigurace.php>\n"));
$store = ts_ai_store($s);
$r = $store->cleanup((int) ($s['retention']['conversationDays'] ?? 30), (int) ($s['retention']['leadDays'] ?? 90));
$sent = 0;
if (!empty($s['mail']['from'])) { TsMailQueue::migrate($store->pdo()); $sent = (new TsMailQueue($store->pdo(), new TsMailer($s['mail']), $s['mailQueue'] ?? []))->process(20); }
echo 'smazáno konverzací: ' . $r['conversations'] . ', anonymizováno leadů: ' . $r['leads'] . ', zpracováno e-mailů: ' . $sent . "\n";
