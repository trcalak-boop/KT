<?php
/* ==========================================================================
   ts-site-audit – úložiště (PDO, SQLite i MySQL): úlohy auditu a limity požadavků.
   Token úlohy se neukládá, jen jeho otisk (sha256). Snímky jsou soubory ve složce shotDir, ne v databázi.
   ========================================================================== */
final class TsAuditStore
{
    private PDO $db; private bool $sqlite;

    public static function open(string $dsn, ?string $user = null, ?string $pass = null): self
    {
        $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $s = new self(); $s->db = $db; $s->sqlite = str_starts_with($dsn, 'sqlite:');
        if ($s->sqlite) { $db->exec('PRAGMA busy_timeout = 5000'); $db->exec('PRAGMA journal_mode = WAL'); }
        return $s;
    }
    public function pdo(): PDO { return $this->db; }
    public static function now(): string { return date('Y-m-d H:i:s'); }

    public function migrate(): int
    {
        $kind = $this->sqlite ? 'sqlite' : 'mysql';
        $this->db->exec('CREATE TABLE IF NOT EXISTS ts_audit_schema (version INTEGER NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $have = array_map('intval', $this->db->query('SELECT version FROM ts_audit_schema')->fetchAll(PDO::FETCH_COLUMN));
        $n = 0;
        foreach (glob(__DIR__ . "/../migrations/*.$kind.sql") as $f) {
            $v = (int) basename($f);
            if (in_array($v, $have, true)) continue;
            $this->db->exec(file_get_contents($f));
            $this->db->prepare('INSERT INTO ts_audit_schema (version, applied_at) VALUES (?, ?)')->execute([$v, self::now()]);
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

    /* limity (pevná okna); false = vyčerpáno */
    public function hit(string $bucket, int $limit, int $seconds): bool
    {
        $win = intdiv(time(), $seconds) * $seconds; $key = $bucket . '@' . $win;
        return $this->tx(function () use ($key, $limit, $win) {
            $c = $this->q('SELECT count FROM ts_audit_rate WHERE bucket = ?', [$key])->fetchColumn();
            if ($c !== false && (int) $c >= $limit) return false;
            if ($c === false) $this->q('INSERT INTO ts_audit_rate (bucket, window_start, count) VALUES (?, ?, 1)', [$key, $win]);
            else $this->q('UPDATE ts_audit_rate SET count = count + 1 WHERE bucket = ?', [$key]);
            return true;
        });
    }

    public function create(array $j): int
    {
        $t = self::now();
        $this->q('INSERT INTO ts_audit_jobs (token_hash, url, url_hash, lang, source, ip_hash, ref, status, data, created_at, updated_at, finished_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$j['token_hash'], $j['url'], hash('sha256', $j['url']), $j['lang'], $j['source'], $j['ip_hash'] ?? '', $j['ref'] ?? '', $j['status'], json_encode($j['data'] ?? new stdClass(), JSON_UNESCAPED_UNICODE), $t, $t, $j['status'] === 'done' ? $t : null]);
        return (int) $this->db->lastInsertId();
    }
    public function byToken(string $token): ?array { return $this->row('token_hash = ?', [hash('sha256', $token)]); }
    public function byId(int $id): ?array { return $this->row('id = ?', [$id]); }
    private function row(string $where, array $p): ?array
    {
        $r = $this->q("SELECT * FROM ts_audit_jobs WHERE $where", $p)->fetch();
        if (!$r) return null;
        $r['data'] = json_decode((string) $r['data'], true) ?: []; return $r;
    }
    /* poslední hotový audit stejné adresy a jazyka mladší než $hours / rozpracovaný mladší než $minutes */
    public function recentDone(string $url, string $lang, int $hours): ?array
    {
        return $this->row("url_hash = ? AND lang = ? AND status = 'done' AND finished_at >= ? AND data NOT LIKE '%\"copyOf\"%' ORDER BY id DESC LIMIT 1", [hash('sha256', $url), $lang, date('Y-m-d H:i:s', time() - $hours * 3600)]);
    }
    public function running(string $url, string $lang, int $minutes): ?array
    {
        return $this->row("url_hash = ? AND lang = ? AND status NOT IN ('done', 'error') AND created_at >= ? AND data NOT LIKE '%\"copyOf\"%' ORDER BY id DESC LIMIT 1", [hash('sha256', $url), $lang, date('Y-m-d H:i:s', time() - $minutes * 60)]);
    }
    /* krok smí běžet jen jednou: zámek na $seconds (souběžné dotazy na stav krok nespustí podruhé) */
    public function lock(int $id, int $seconds): bool
    {
        return $this->q('UPDATE ts_audit_jobs SET lock_until = ? WHERE id = ? AND lock_until < ? AND status NOT IN (\'done\', \'error\')', [time() + $seconds, $id, time()])->rowCount() === 1;
    }
    public function save(int $id, string $status, array $data, string $error = '', bool $attempt = false): void
    {
        $fin = in_array($status, ['done', 'error'], true) ? self::now() : null;
        $this->q('UPDATE ts_audit_jobs SET status = ?, data = ?, error = ?, lock_until = 0, attempts = ' . ($attempt ? 'attempts + 1' : '0') . ', updated_at = ?, finished_at = COALESCE(?, finished_at) WHERE id = ?',
            [$status, json_encode($data, JSON_UNESCAPED_UNICODE), $error, self::now(), $fin, $id]);
    }
    public function list(int $limit = 50, int $offset = 0): array
    {
        return $this->q('SELECT id, url, lang, source, ref, status, error, created_at, finished_at FROM ts_audit_jobs ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset))->fetchAll();
    }
    public function setRef(int $id, string $ref): void { $this->q('UPDATE ts_audit_jobs SET ref = ? WHERE id = ?', [mb_substr($ref, 0, 64), $id]); }
    /* retence: úlohy starší než $days → smazat (vrací id kvůli mazání snímků) */
    public function cleanup(int $days): array
    {
        $old = $this->q('SELECT id FROM ts_audit_jobs WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)])->fetchAll(PDO::FETCH_COLUMN);
        if ($old) $this->q('DELETE FROM ts_audit_jobs WHERE id IN (' . implode(',', array_map('intval', $old)) . ')');
        $this->q('DELETE FROM ts_audit_rate WHERE window_start < ?', [time() - 3 * 86400]);
        return array_map('intval', $old);
    }
}
