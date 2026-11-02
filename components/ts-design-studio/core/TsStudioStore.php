<?php
/* ==========================================================================
   ts-design-studio – úložiště (PDO, SQLite i MySQL): projekty, verze, záznam akcí.
   Verze ukládá jen textové soubory (vykreslené nebo upravené); binární soubory (písma, obrázky) se berou ze šablony.
   Leady jsou jen v CRM – projekt drží pouze odkaz (lead_ref), audit odkaz na úlohu auditu (audit_ref).
   ========================================================================== */
final class TsStudioStore
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
        $this->db->exec('CREATE TABLE IF NOT EXISTS studio_schema (version INTEGER NOT NULL PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $have = array_map('intval', $this->db->query('SELECT version FROM studio_schema')->fetchAll(PDO::FETCH_COLUMN)); $n = 0;
        foreach (glob(__DIR__ . "/../migrations/*.$kind.sql") as $f) {
            $v = (int) basename($f); if (in_array($v, $have, true)) continue;
            $this->db->exec(file_get_contents($f)); $this->db->prepare('INSERT INTO studio_schema (version, applied_at) VALUES (?, ?)')->execute([$v, self::now()]); $n++;
        }
        return $n;
    }
    public function tx(callable $f)
    {
        if ($this->sqlite) $this->db->exec('BEGIN IMMEDIATE'); else $this->db->beginTransaction();
        try { $r = $f(); $this->sqlite ? $this->db->exec('COMMIT') : $this->db->commit(); return $r; }
        catch (Throwable $e) { $this->sqlite ? $this->db->exec('ROLLBACK') : $this->db->rollBack(); throw $e; }
    }
    private function q(string $sql, array $p = []): PDOStatement { $s = $this->db->prepare($sql); $s->execute($p); return $s; }

    public function createProject(array $p): int
    {
        $t = self::now();
        $this->q('INSERT INTO studio_projects (name, source, lead_ref, audit_ref, template, status, owner_uid, brief, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$p['name'], $p['source'], $p['lead_ref'] ?? '', $p['audit_ref'] ?? '', $p['template'], 'draft', (int) ($p['owner_uid'] ?? 0), json_encode($p['brief'] ?? new stdClass(), JSON_UNESCAPED_UNICODE), $t, $t]);
        return (int) $this->db->lastInsertId();
    }
    public function project(int $id): ?array
    {
        $r = $this->q('SELECT * FROM studio_projects WHERE id = ?', [$id])->fetch(); if (!$r) return null;
        $r['brief'] = json_decode((string) $r['brief'], true) ?: []; return $r;
    }
    public function projects(int $limit = 100): array
    {
        return $this->q('SELECT p.id, p.name, p.source, p.lead_ref, p.audit_ref, p.template, p.status, p.owner_uid, p.current_num, p.created_at, p.updated_at,
            (SELECT MAX(num) FROM studio_versions v WHERE v.project_id = p.id) AS last_num FROM studio_projects p ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . max(1, $limit))->fetchAll();
    }
    public function setProject(int $id, array $f): void
    {
        $set = []; $p = [];
        foreach (['name', 'status', 'current_num', 'owner_uid', 'lead_ref', 'audit_ref'] as $k) if (array_key_exists($k, $f)) { $set[] = "$k = ?"; $p[] = $f[$k]; }
        if (!$set) return; $set[] = 'updated_at = ?'; $p[] = self::now(); $p[] = $id;
        $this->q('UPDATE studio_projects SET ' . implode(', ', $set) . ' WHERE id = ?', $p);
    }
    public function addVersion(int $pid, array $v): int
    {
        return $this->tx(function () use ($pid, $v) {
            $num = (int) $this->q('SELECT COALESCE(MAX(num), 0) + 1 FROM studio_versions WHERE project_id = ?', [$pid])->fetchColumn();
            $this->q('INSERT INTO studio_versions (project_id, num, parent_num, kind, author_uid, author_kind, message, files, content, tokens, checks, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$pid, $num, (int) ($v['parent'] ?? 0), $v['kind'], (int) ($v['uid'] ?? 0), $v['author_kind'] ?? 'human', mb_substr((string) ($v['message'] ?? ''), 0, 500),
                 json_encode($v['files'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($v['content'] ?? new stdClass(), JSON_UNESCAPED_UNICODE), json_encode($v['tokens'] ?? new stdClass(), JSON_UNESCAPED_UNICODE),
                 json_encode($v['checks'] ?? [], JSON_UNESCAPED_UNICODE), $v['status'] ?? 'candidate', self::now()]);
            $this->q('UPDATE studio_projects SET updated_at = ? WHERE id = ?', [self::now(), $pid]);
            return $num;
        });
    }
    public function version(int $pid, int $num): ?array
    {
        $r = $this->q('SELECT * FROM studio_versions WHERE project_id = ? AND num = ?', [$pid, $num])->fetch(); if (!$r) return null;
        foreach (['files', 'content', 'tokens', 'checks'] as $k) $r[$k] = json_decode((string) $r[$k], true) ?: [];
        return $r;
    }
    public function versions(int $pid): array
    {
        return $this->q('SELECT num, parent_num, kind, author_uid, author_kind, message, status, created_at FROM studio_versions WHERE project_id = ? ORDER BY num DESC', [$pid])->fetchAll();
    }
    public function setVersionStatus(int $pid, int $num, string $status): void { $this->q('UPDATE studio_versions SET status = ? WHERE project_id = ? AND num = ?', [$status, $pid, $num]); }
    public function log(int $pid, int $num, int $uid, string $action, string $detail = ''): void
    {
        $this->q('INSERT INTO studio_log (project_id, num, uid, action, detail, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$pid, $num, $uid, $action, mb_substr($detail, 0, 500), self::now()]);
    }
    public function logOf(int $pid, int $limit = 200): array { return $this->q('SELECT num, uid, action, detail, created_at FROM studio_log WHERE project_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$pid])->fetchAll(); }

    /* konverzace s AI a spotřeba modelu (migrace 002) */
    public function addMessage(int $pid, int $num, int $uid, string $role, string $text): void
    {
        $this->q('INSERT INTO studio_messages (project_id, num, uid, role, text, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$pid, $num, $uid, $role, mb_substr($text, 0, 4000), self::now()]);
    }
    public function messages(int $pid, int $limit = 50): array
    {
        return array_reverse($this->q('SELECT num, uid, role, text, created_at FROM studio_messages WHERE project_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$pid])->fetchAll());
    }
    public function addUsage(array $u): void
    {
        $this->q('INSERT INTO studio_usage (day, project_id, uid, kind, model, tokens_in, tokens_out, neurons, ok, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [gmdate('Y-m-d'), (int) ($u['project'] ?? 0), (int) ($u['uid'] ?? 0), (string) $u['kind'], mb_substr((string) $u['model'], 0, 80), (int) ($u['in'] ?? 0), (int) ($u['out'] ?? 0), (int) ($u['neurons'] ?? 0), empty($u['fail']) ? 1 : 0, self::now()]);
    }
    /* spotřeba za den UTC (kvóta Cloudflare se nuluje o půlnoci UTC) */
    public function usageDay(?string $day = null): array
    {
        $r = $this->q('SELECT COUNT(*) AS calls, COALESCE(SUM(neurons), 0) AS neurons, COALESCE(SUM(tokens_in), 0) AS tokens_in, COALESCE(SUM(tokens_out), 0) AS tokens_out FROM studio_usage WHERE day = ?', [$day ?? gmdate('Y-m-d')])->fetch();
        return array_map('intval', $r);
    }

    /* doručení klientovi (migrace 003) */
    public function addDelivery(array $d): int
    {
        $t = self::now(); $cols = array_keys($d);
        $this->q('INSERT INTO studio_deliveries (' . implode(', ', $cols) . ', created_at, updated_at) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', ?, ?)', array_merge(array_values($d), [$t, $t]));
        return (int) $this->db->lastInsertId();
    }
    public function delivery(int $id): ?array { $r = $this->q('SELECT * FROM studio_deliveries WHERE id = ?', [$id])->fetch(); return $r ?: null; }
    public function deliveryByHash(string $h): ?array { $r = $this->q('SELECT * FROM studio_deliveries WHERE token_hash = ?', [$h])->fetch(); return $r ?: null; }
    public function deliveries(int $pid): array { return $this->q('SELECT * FROM studio_deliveries WHERE project_id = ? ORDER BY id DESC', [$pid])->fetchAll(); }
    public function setDelivery(int $id, array $f): void
    {
        $f['updated_at'] = self::now(); $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($f)));
        $this->q("UPDATE studio_deliveries SET $set WHERE id = ?", array_merge(array_values($f), [$id]));
    }
    /* podmíněná změna stavu (ochrana proti souběhu dvou odeslání) → true, když se řádek změnil */
    public function swapDeliveryStatus(int $id, array $from, string $to): bool
    {
        $in = implode(', ', array_fill(0, count($from), '?'));
        return $this->q("UPDATE studio_deliveries SET status = ?, updated_at = ? WHERE id = ? AND status IN ($in)", array_merge([$to, self::now(), $id], $from))->rowCount() === 1;
    }
    public function anonymizeDeliveries(string $before): int
    {
        return $this->q("UPDATE studio_deliveries SET to_email = '', to_name = '', body = '', subject = '', error = '', updated_at = ? WHERE created_at < ? AND to_email <> ''", [self::now(), $before])->rowCount();
    }
}
