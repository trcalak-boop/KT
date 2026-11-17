<?php
/* ==========================================================================
   ts-analytics – úložiště (PDO, SQLite i MySQL): zobrazení stránek, události, limity požadavků.
   IP adresa se neukládá; limity pracují s jejím otiskem (sůl) jen v pevném okně.
   ========================================================================== */
final class TsAnalyticsStore
{
    private PDO $db; private bool $sqlite;
    const COLS = ['ts', 'day', 'vid', 'sid', 'newv', 'kind', 'path', 'ref', 'src', 'med', 'camp', 'lang', 'dev', 'br', 'os', 'ev'];

    public static function open(string $dsn, ?string $user = null, ?string $pass = null): self
    {
        $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $s = new self(); $s->db = $db; $s->sqlite = str_starts_with($dsn, 'sqlite:');
        if ($s->sqlite) { $db->exec('PRAGMA busy_timeout = 5000'); $db->exec('PRAGMA journal_mode = WAL'); }
        return $s;
    }
    public function pdo(): PDO { return $this->db; }

    public function migrate(): int
    {
        $kind = $this->sqlite ? 'sqlite' : 'mysql';
        $this->db->exec('CREATE TABLE IF NOT EXISTS ts_an_schema (version INTEGER NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $have = array_map('intval', $this->db->query('SELECT version FROM ts_an_schema')->fetchAll(PDO::FETCH_COLUMN));
        $n = 0;
        foreach (glob(__DIR__ . "/../migrations/*.$kind.sql") as $f) {
            $v = (int) basename($f);
            if (in_array($v, $have, true)) continue;
            $this->db->exec(file_get_contents($f));
            $this->db->prepare('INSERT INTO ts_an_schema (version, applied_at) VALUES (?, ?)')->execute([$v, date('Y-m-d H:i:s')]);
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
    public function q(string $sql, array $p = []): PDOStatement { $s = $this->db->prepare($sql); $s->execute($p); return $s; }

    /* limity (pevná okna); false = vyčerpáno */
    public function hit(string $bucket, int $limit, int $seconds): bool
    {
        $win = intdiv(time(), $seconds) * $seconds; $key = $bucket . '@' . $win;
        return $this->tx(function () use ($key, $limit, $win) {
            $c = $this->q('SELECT count FROM ts_an_rate WHERE bucket = ?', [$key])->fetchColumn();
            if ($c !== false && (int) $c >= $limit) return false;
            if ($c === false) $this->q('INSERT INTO ts_an_rate (bucket, window_start, count) VALUES (?, ?, 1)', [$key, $win]);
            else $this->q('UPDATE ts_an_rate SET count = count + 1 WHERE bucket = ?', [$key]);
            return true;
        });
    }

    public function add(array $h): void
    {
        $v = []; foreach (self::COLS as $c) $v[] = $h[$c] ?? '';
        $this->q('INSERT INTO ts_an_hits (' . implode(', ', self::COLS) . ') VALUES (' . implode(', ', array_fill(0, count(self::COLS), '?')) . ')', $v);
    }
    /* retence: zobrazení starší než $months měsíců se smažou; staré limity také */
    public function cleanup(int $months): int
    {
        $n = $this->q('DELETE FROM ts_an_hits WHERE day < ?', [date('Y-m-d', strtotime("-$months months"))])->rowCount();
        $this->q('DELETE FROM ts_an_rate WHERE window_start < ?', [time() - 2 * 86400]);
        return $n;
    }
}
