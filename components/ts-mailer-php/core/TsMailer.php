<?php
/* ==========================================================================
   ts-mailer-php – odesílání e-mailů (SMTP se STARTTLS/SSL, nebo mail() hostingu) a fronta s opakováním
   Převzato z tasstudio.eu (web/admin/crm-poptavky.php: posta_zprava, posta_odeslat, posta_smtp, crm_fronta_zpracuj)
   a zobecněno: konfigurace místo konstant, fronta ve vlastní tabulce ts_mail_queue (SQLite/MySQL přes PDO).

   Stavy výsledku: 'odeslano' = SMTP server potvrdil (250), 'predano' = mail() zprávu přijal (doručení nepotvrzeno),
   'zapsano' = transport 'log' (vývoj, nic se neodesílá). Chybové texty nikdy neobsahují heslo.
   ========================================================================== */
final class TsMailer
{
    public const VERSION = '1.0.0';
    private array $c;

    /* transport: smtp | mail | log; from, fromName, domain (EHLO a Message-ID), smtp_host, smtp_port, smtp_secure (tls|ssl|none),
       smtp_user, smtp_pass, smtp_timeout, smtp_verify (true), logFile (pro transport log) */
    public function __construct(array $config)
    {
        $this->c = $config + ['transport' => empty($config['smtp_host']) ? 'mail' : 'smtp', 'fromName' => '', 'domain' => 'localhost',
            'smtp_port' => 587, 'smtp_secure' => 'tls', 'smtp_timeout' => 15, 'smtp_verify' => true, 'mailer' => 'T&S Studio'];
        if (!filter_var($this->c['from'] ?? '', FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('TsMailer: neplatný odesílatel (from)');
    }

    public static function header(string $t): string { return '=?UTF-8?B?' . base64_encode($t) . '?='; }

    /* zpráva multipart/alternative (text + HTML) → [hlavičky[], tělo] */
    public function build(string $to, string $subject, string $text, string $html, ?string $replyTo = null, string $replyName = '', ?string $msgid = null): array
    {
        $b = 'ts-' . bin2hex(random_bytes(8));
        $msgid = $msgid ?: bin2hex(random_bytes(10)) . '@' . $this->c['domain'];
        $h = ['Date: ' . date('r'), 'From: ' . self::header($this->c['fromName']) . ' <' . $this->c['from'] . '>', 'To: <' . $to . '>', 'Subject: ' . self::header($subject), 'Message-ID: <' . $msgid . '>',
              'MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="' . $b . '"', 'X-Mailer: ' . $this->c['mailer']];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $h[] = 'Reply-To: ' . self::header($replyName) . ' <' . $replyTo . '>';
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--\r\n";
        return [$h, $body];
    }

    /* → ['ok' => bool, 'stav' => …, 'odpoved' => …, 'chyba' => …] */
    public function send(string $to, string $subject, string $text, string $html, ?string $replyTo = null, string $replyName = '', ?string $msgid = null): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) return ['ok' => false, 'chyba' => 'neplatný příjemce nebo předmět'];
        [$h, $body] = $this->build($to, $subject, $text, $html, $replyTo, $replyName, $msgid);
        $t = $this->c['transport'];
        if ($t === 'log') {
            $ok = @file_put_contents($this->c['logFile'] ?? (sys_get_temp_dir() . '/ts-mailer.log'), implode("\r\n", $h) . "\r\n\r\n" . $body . "\r\n\r\n", FILE_APPEND | LOCK_EX) !== false;
            return $ok ? ['ok' => true, 'stav' => 'zapsano', 'odpoved' => 'zapsáno do logu'] : ['ok' => false, 'chyba' => 'log nelze zapsat'];
        }
        if ($t === 'smtp') return $this->smtp($to, implode("\r\n", $h) . "\r\n\r\n" . $body);
        $extra = array_values(array_filter($h, function ($x) { return !preg_match('/^(To|Subject):/i', $x); }));
        $ok = @mail($to, self::header($subject), $body, implode("\r\n", $extra), '-f' . $this->c['from']);
        if (!$ok) $ok = @mail($to, self::header($subject), $body, implode("\r\n", $extra));
        return $ok ? ['ok' => true, 'stav' => 'predano', 'odpoved' => 'mail() přijal zprávu k odeslání'] : ['ok' => false, 'chyba' => 'mail() zprávu odmítl'];
    }

    private function smtp(string $to, string $data): array
    {
        $n = $this->c; $host = (string) $n['smtp_host']; $port = (int) $n['smtp_port']; $sec = strtolower((string) $n['smtp_secure']);
        $errno = 0; $errstr = '';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => (bool) $n['smtp_verify'], 'verify_peer_name' => (bool) $n['smtp_verify']]]);
        $s = @stream_socket_client(($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) return ['ok' => false, 'chyba' => 'SMTP: spojení se serverem se nepodařilo (' . $errno . ')'];
        stream_set_timeout($s, (int) $n['smtp_timeout']);
        $read = function () use ($s) { $r = ''; while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
        $cmd = function ($c, $expect) use ($s, $read) { if ($c !== null) fwrite($s, $c . "\r\n"); $r = $read(); $code = (int) substr($r, 0, 3); return [in_array($code, (array) $expect, true), trim($r)]; };
        $fail = function ($step, $r) use ($s) { @fwrite($s, "QUIT\r\n"); @fclose($s); return ['ok' => false, 'chyba' => 'SMTP ' . $step . ': ' . mb_substr(preg_replace('/\s+/', ' ', $r), 0, 200)]; };
        $ehlo = 'EHLO ' . $n['domain'];
        [$ok, $r] = $cmd(null, 220); if (!$ok) return $fail('uvítání', $r);
        [$ok, $r] = $cmd($ehlo, 250); if (!$ok) return $fail('EHLO', $r);
        if ($sec === 'tls') {
            [$ok, $r] = $cmd('STARTTLS', 220); if (!$ok) return $fail('STARTTLS', $r);
            if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) return $fail('TLS', 'šifrované spojení se nepodařilo');
            [$ok, $r] = $cmd($ehlo, 250); if (!$ok) return $fail('EHLO', $r);
        }
        if (!empty($n['smtp_user'])) {
            [$ok, $r] = $cmd('AUTH LOGIN', 334); if (!$ok) return $fail('AUTH', $r);
            [$ok, $r] = $cmd(base64_encode((string) $n['smtp_user']), 334); if (!$ok) return $fail('AUTH', 'jméno odmítnuto');
            [$ok, $r] = $cmd(base64_encode((string) ($n['smtp_pass'] ?? '')), 235); if (!$ok) return $fail('AUTH', 'přihlášení odmítnuto');
        }
        [$ok, $r] = $cmd('MAIL FROM:<' . $n['from'] . '>', 250); if (!$ok) return $fail('MAIL FROM', $r);
        [$ok, $r] = $cmd('RCPT TO:<' . $to . '>', [250, 251]); if (!$ok) return $fail('RCPT TO', $r);
        [$ok, $r] = $cmd('DATA', 354); if (!$ok) return $fail('DATA', $r);
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data));
        [$ok, $r] = $cmd($data . "\r\n.", 250); if (!$ok) return $fail('odeslání', $r);
        @fwrite($s, "QUIT\r\n"); @fclose($s);
        return ['ok' => true, 'stav' => 'odeslano', 'odpoved' => mb_substr($r, 0, 200)];
    }
}

/* Fronta e-mailů: zápis do DB nejdřív (e-mail se neztratí), odeslání potom, s opakováním a zámkem proti dvojímu odeslání. */
final class TsMailQueue
{
    private PDO $db; private TsMailer $m; private array $c;
    public function __construct(PDO $db, TsMailer $mailer, array $config = [])
    {
        $this->db = $db; $this->m = $mailer;
        $this->c = $config + ['attempts' => 6, 'backoffMin' => [2, 10, 30, 120, 360], 'lockSeconds' => 120];
    }
    public static function migrate(PDO $db): void { $db->exec(file_get_contents(__DIR__ . '/../migrations/001_ts_mail_queue.sql')); }
    private static function now(): string { return date('Y-m-d H:i:s'); }

    /* dedupeKey: stejný klíč = stejný e-mail se nezařadí dvakrát (idempotence) → id ve frontě */
    public function enqueue(string $to, string $subject, string $text, string $html, array $o = []): int
    {
        $now = self::now();
        $st = $this->db->prepare('INSERT INTO ts_mail_queue (dedupe_key, kind, recipient, subject, text_body, html_body, reply_to, reply_name, msgid, status, attempts, next_try_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'ceka\', 0, ?, ?, ?)');
        try {
            $st->execute([$o['dedupeKey'] ?? null, $o['kind'] ?? 'obecny', $to, $subject, $text, $html, $o['replyTo'] ?? null, $o['replyName'] ?? '', $o['msgid'] ?? null, $now, $now, $now]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            if (!empty($o['dedupeKey'])) { $q = $this->db->prepare('SELECT id FROM ts_mail_queue WHERE dedupe_key = ?'); $q->execute([$o['dedupeKey']]); $id = $q->fetchColumn(); if ($id) return (int) $id; }
            throw $e;
        }
    }
    /* zpracuje nejvýš $max čekajících e-mailů → počet zpracovaných */
    public function process(int $max = 3): int
    {
        $now = self::now();
        $q = $this->db->prepare("SELECT id FROM ts_mail_queue WHERE status IN ('ceka','chyba') AND next_try_at <= ? AND (locked_until IS NULL OR locked_until < ?) ORDER BY id LIMIT " . $max);
        $q->execute([$now, $now]); $done = 0;
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $lock = $this->db->prepare("UPDATE ts_mail_queue SET locked_until = ? WHERE id = ? AND status IN ('ceka','chyba') AND (locked_until IS NULL OR locked_until < ?)");
            $lock->execute([date('Y-m-d H:i:s', time() + $this->c['lockSeconds']), $id, self::now()]);
            if ($lock->rowCount() !== 1) continue;
            $r = $this->db->prepare('SELECT * FROM ts_mail_queue WHERE id = ?'); $r->execute([$id]); $e = $r->fetch(PDO::FETCH_ASSOC);
            try { $res = $this->m->send($e['recipient'], $e['subject'], $e['text_body'], $e['html_body'], $e['reply_to'], (string) $e['reply_name'], $e['msgid']); }
            catch (Throwable $x) { $res = ['ok' => false, 'chyba' => 'výjimka: ' . mb_substr($x->getMessage(), 0, 160)]; }
            $n = (int) $e['attempts'] + 1; $t = self::now();
            if ($res['ok']) {
                $this->db->prepare('UPDATE ts_mail_queue SET status = ?, attempts = ?, provider_response = ?, last_error = NULL, sent_at = ?, locked_until = NULL, updated_at = ? WHERE id = ?')->execute([$res['stav'], $n, $res['odpoved'] ?? null, $t, $t, $id]);
            } else {
                $end = $n >= $this->c['attempts']; $b = $this->c['backoffMin'];
                $next = date('Y-m-d H:i:s', time() + 60 * $b[min($n - 1, count($b) - 1)]);
                $this->db->prepare('UPDATE ts_mail_queue SET status = ?, attempts = ?, last_error = ?, next_try_at = ?, locked_until = NULL, updated_at = ? WHERE id = ?')->execute([$end ? 'selhalo' : 'chyba', $n, $res['chyba'] ?? '?', $next, $t, $id]);
            }
            $done++;
        }
        return $done;
    }
    public function get(int $id): ?array { $r = $this->db->prepare('SELECT * FROM ts_mail_queue WHERE id = ?'); $r->execute([$id]); $x = $r->fetch(PDO::FETCH_ASSOC); return $x ?: null; }
}
