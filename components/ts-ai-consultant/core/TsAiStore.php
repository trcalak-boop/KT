<?php
/* ==========================================================================
   ts-ai-consultant – úložiště (PDO, SQLite i MySQL): konverzace, spotřeba a měsíční rozpočet,
   limity požadavků, leady. Čas se ukládá v pásmu serveru (date_default_timezone_set v konfiguraci).
   Identifikátor konverzace v DB je otisk tokenu z prohlížeče, samotný token se neukládá.
   ========================================================================== */
final class TsAiStore
{
    private PDO $db; private bool $sqlite;

    public static function open(string $dsn, ?string $user = null, ?string $pass = null): self
    {
        $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $s = new self(); $s->db = $db; $s->sqlite = str_starts_with($dsn, 'sqlite:');
        if ($s->sqlite) { $db->exec('PRAGMA busy_timeout = 5000'); $db->exec('PRAGMA journal_mode = WAL'); $db->exec('PRAGMA foreign_keys = ON'); }
        return $s;
    }
    public function pdo(): PDO { return $this->db; }
    public static function now(): string { return date('Y-m-d H:i:s'); }

    public function migrate(): int
    {
        $kind = $this->sqlite ? 'sqlite' : 'mysql';
        $this->db->exec('CREATE TABLE IF NOT EXISTS ts_ai_schema (version INTEGER NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $have = array_map('intval', $this->db->query('SELECT version FROM ts_ai_schema')->fetchAll(PDO::FETCH_COLUMN));
        $n = 0;
        foreach (glob(__DIR__ . "/../migrations/*.$kind.sql") as $f) {
            $v = (int) basename($f);
            if (in_array($v, $have, true)) continue;
            $this->db->exec(file_get_contents($f));
            $this->db->prepare('INSERT INTO ts_ai_schema (version, applied_at) VALUES (?, ?)')->execute([$v, self::now()]);
            $n++;
        }
        return $n;
    }

    private function tx(callable $f)
    {
        if ($this->sqlite) $this->db->exec('BEGIN IMMEDIATE'); else $this->db->beginTransaction();
        try { $r = $f(); $this->sqlite ? $this->db->exec('COMMIT') : $this->db->commit(); return $r; }
        catch (Throwable $e) { $this->sqlite ? $this->db->exec('ROLLBACK') : $this->db->rollBack(); throw $e; }
    }
    private function q(string $sql, array $p = []): PDOStatement { $s = $this->db->prepare($sql); $s->execute($p); return $s; }

    /* ---------- limity požadavků (pevná okna) ---------- */
    /* Zvýší počítadlo v okně; false = limit vyčerpán (počítadlo se nezvýší). */
    public function hit(string $bucket, int $limit, int $seconds): bool
    {
        $win = intdiv(time(), $seconds) * $seconds; $key = $bucket . '@' . $win;
        return $this->tx(function () use ($key, $limit, $win) {
            $c = $this->q('SELECT count FROM ts_ai_rate WHERE bucket = ?', [$key])->fetchColumn();
            if ($c !== false && (int) $c >= $limit) return false;
            if ($c === false) $this->q('INSERT INTO ts_ai_rate (bucket, window_start, count) VALUES (?, ?, 1)', [$key, $win]);
            else $this->q('UPDATE ts_ai_rate SET count = count + 1 WHERE bucket = ?', [$key]);
            return true;
        });
    }

    public function hits(string $bucket, int $seconds): int
    {
        $win = intdiv(time(), $seconds) * $seconds;
        return (int) ($this->q('SELECT count FROM ts_ai_rate WHERE bucket = ?', [$bucket . '@' . $win])->fetchColumn() ?: 0);
    }

    /* ---------- rozpočet: rezervace před voláním, vyúčtování po něm ----------
       Částky v milióntinách USD (celá čísla). Rezervace starší než 10 min se nepočítají (spadlý požadavek). */
    public function month(): string { return gmdate('Y-m'); }
    public function spent(?string $month = null): int
    {
        return (int) $this->q('SELECT COALESCE(SUM(cost_micro), 0) FROM ts_ai_usage WHERE month = ?', [$month ?? $this->month()])->fetchColumn();
    }
    public function reserve(int $micro, int $limitMicro): ?int
    {
        return $this->tx(function () use ($micro, $limitMicro) {
            $m = $this->month(); $fresh = date('Y-m-d H:i:s', time() - 600);
            $held = (int) $this->q('SELECT COALESCE(SUM(micro), 0) FROM ts_ai_reservations WHERE month = ? AND created_at >= ?', [$m, $fresh])->fetchColumn();
            if ($this->spent($m) + $held + $micro > $limitMicro) return null;
            $this->q('INSERT INTO ts_ai_reservations (month, micro, created_at) VALUES (?, ?, ?)', [$m, $micro, self::now()]);
            return (int) $this->db->lastInsertId();
        });
    }
    public function settle(?int $reservation, array $row): void
    {
        $this->tx(function () use ($reservation, $row) {
            if ($reservation) $this->q('DELETE FROM ts_ai_reservations WHERE id = ?', [$reservation]);
            $this->q('INSERT INTO ts_ai_usage (month, conv_id, model, in_tokens, out_tokens, cache_read, cache_write, cost_micro, outcome, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$this->month(), $row['conv'] ?? null, $row['model'] ?? '', $row['in'] ?? 0, $row['out'] ?? 0, $row['cacheRead'] ?? 0, $row['cacheWrite'] ?? 0, $row['cost'] ?? 0, $row['outcome'] ?? 'ok', self::now()]);
        });
    }
    public function report(?string $month = null): array
    {
        $m = $month ?? $this->month();
        $r = $this->q('SELECT outcome, COUNT(*) n, SUM(cost_micro) c, SUM(in_tokens) i, SUM(out_tokens) o, SUM(cache_read) cr FROM ts_ai_usage WHERE month = ? GROUP BY outcome', [$m])->fetchAll();
        return ['month' => $m, 'spentMicro' => $this->spent($m), 'byOutcome' => $r,
            'conversations' => (int) $this->q('SELECT COUNT(DISTINCT conv_id) FROM ts_ai_usage WHERE month = ?', [$m])->fetchColumn()];
    }

    /* ---------- konverzace ---------- */
    public function conversation(string $id): ?array { $r = $this->q('SELECT * FROM ts_ai_conversations WHERE id = ?', [$id])->fetch(); return $r ?: null; }
    public function createConversation(string $id, string $lang, string $ipHash, string $page): void
    {
        $t = self::now(); $this->q('INSERT INTO ts_ai_conversations (id, lang, ip_hash, page, turns, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?)', [$id, $lang, $ipHash, $page, $t, $t]);
    }
    public function addMessage(string $conv, string $role, string $text, string $meta = ''): void
    {
        $t = self::now();
        $this->q('INSERT INTO ts_ai_messages (conv_id, role, text, meta, created_at) VALUES (?, ?, ?, ?, ?)', [$conv, $role, $text, $meta, $t]);
        $this->q('UPDATE ts_ai_conversations SET turns = turns + ?, updated_at = ? WHERE id = ?', [$role === 'user' ? 1 : 0, $t, $conv]);
    }
    public function messages(string $conv, int $last = 100): array
    {
        $r = $this->q('SELECT role, text, meta, created_at FROM ts_ai_messages WHERE conv_id = ? ORDER BY id DESC LIMIT ' . max(1, $last), [$conv])->fetchAll();
        return array_reverse($r);
    }

    /* ---------- leady ---------- */
    public function leadBySubmission(string $sid): ?array { $r = $this->q('SELECT * FROM ts_ai_leads WHERE submission_id = ?', [$sid])->fetch(); return $r ?: null; }
    public function createLead(array $l): array
    {
        try {
            $this->q('INSERT INTO ts_ai_leads (submission_id, conv_id, name, email, phone, company, website, message, transcript, lang, consent_text, consent_at, sink_status, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'ceka\', ?)',
                [$l['submission_id'], $l['conv_id'], $l['name'], $l['email'], $l['phone'] ?: null, $l['company'] ?: null, $l['website'] ?: null, $l['message'], $l['transcript'], $l['lang'], $l['consent_text'], self::now(), self::now()]);
            return ['id' => (int) $this->db->lastInsertId(), 'duplicate' => false];
        } catch (PDOException $e) {
            $b = $this->leadBySubmission($l['submission_id']);
            if ($b) return ['id' => (int) $b['id'], 'duplicate' => true];
            throw $e;
        }
    }
    public function setLeadSink(int $id, string $status, ?string $ref): void { $this->q('UPDATE ts_ai_leads SET sink_status = ?, sink_ref = ? WHERE id = ?', [$status, $ref, $id]); }
    public function lead(int $id): ?array { $r = $this->q('SELECT * FROM ts_ai_leads WHERE id = ?', [$id])->fetch(); return $r ?: null; }

    /* ---------- retence ---------- */
    /* Smaže konverzace starší než $convDays dní a osobní údaje leadů starších než $leadDays (lead už je v CRM / e-mailu). */
    public function cleanup(int $convDays, int $leadDays): array
    {
        $c = date('Y-m-d H:i:s', time() - $convDays * 86400); $l = date('Y-m-d H:i:s', time() - $leadDays * 86400);
        $old = $this->q('SELECT id FROM ts_ai_conversations WHERE updated_at < ?', [$c])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($old as $id) { $this->q('DELETE FROM ts_ai_messages WHERE conv_id = ?', [$id]); $this->q('DELETE FROM ts_ai_conversations WHERE id = ?', [$id]); }
        $n = $this->q("UPDATE ts_ai_leads SET name = 'anonymizováno', email = '', phone = NULL, company = NULL, website = NULL, message = '', transcript = '' WHERE created_at < ? AND email <> ''", [$l])->rowCount();
        $this->q('DELETE FROM ts_ai_rate WHERE window_start < ?', [time() - 2 * 86400]);
        $this->q('DELETE FROM ts_ai_reservations WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        return ['conversations' => count($old), 'leads' => $n];
    }
}
