# API: ts-mailer-php 1.0.0 (`TsMailer`, `TsMailQueue`)

```php
$mailer = new TsMailer(['from' => 'rezervace@salon.cz', 'fromName' => 'Salon', 'domain' => 'salon.cz',
                        'smtp_host' => 'smtp.…', 'smtp_port' => 587, 'smtp_secure' => 'tls', 'smtp_user' => '…', 'smtp_pass' => '…']);  // konfigurace mimo git a webroot
$q = new TsMailQueue($pdo, $mailer);  TsMailQueue::migrate($pdo);
$q->enqueue($email, 'Potvrzení rezervace', $text, $html, ['dedupeKey' => 'rez-' . $id . '-potvrzeni', 'replyTo' => 'recepce@salon.cz']);
$q->process(3);   // při požadavku nebo z cronu
```

| | |
|---|---|
| `TsMailer::send(to, subject, text, html, replyTo?, replyName?, msgid?)` | `['ok', 'stav' => odeslano \| predano \| zapsano, 'odpoved', 'chyba']` |
| transport | `smtp` (výchozí, když je `smtp_host`), `mail` (mail() hostingu – doručení nepotvrzeno), `log` (vývoj, `logFile`) |
| `TsMailer::build(...)` | `[hlavičky, tělo]` multipart/alternative UTF-8 |
| `TsMailQueue::enqueue(...)` | id; stejný `dedupeKey` = stejný e-mail jen jednou |
| `TsMailQueue::process(max)` | zámek, odeslání, opakování (`attempts` 6, `backoffMin` [2,10,30,120,360]), stav `selhalo` |
| tabulka | `migrations/001_ts_mail_queue.sql` (rollback: `DROP TABLE ts_mail_queue`) |
