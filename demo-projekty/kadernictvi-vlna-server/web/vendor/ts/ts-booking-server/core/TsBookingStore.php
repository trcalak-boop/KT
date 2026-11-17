<?php
/* ==========================================================================
   ts-booking-server – datová vrstva rezervací (PDO: SQLite nebo MySQL/MariaDB)
   Založení rezervace probíhá v jedné transakci se zámkem zápisu: načte obsazenost dne, znovu ověří termín
   jádrem (TsBookingEngine::prepareBooking) a teprve pak zapíše. Dva souběžné požadavky na stejný čas
   tak nikdy nevytvoří dvě rezervace. Idempotenční klíč zabrání duplicitě při dvojkliku nebo opakovaném požadavku.
   ========================================================================== */
final class TsBookingStore
{
    public const VERSION = '1.0.0';
    public const STATUSES = ['pending', 'confirmed', 'done', 'noshow', 'rejected', 'cancelled'];
    /* povolené přechody stavů (administrace) */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'rejected', 'cancelled'],
        'confirmed' => ['done', 'noshow', 'cancelled'],
        'done' => [], 'noshow' => [], 'rejected' => [], 'cancelled' => [],
    ];
    private PDO $db; private string $driver;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($this->driver === 'sqlite') { $db->exec('PRAGMA foreign_keys = ON'); $db->exec('PRAGMA busy_timeout = 8000'); $db->exec('PRAGMA journal_mode = WAL'); }
    }
    public static function open(string $dsn, ?string $user = null, ?string $pass = null): self
    {
        if (strpos($dsn, 'sqlite:') === 0) { $f = substr($dsn, 7); $dir = dirname($f); if (!is_dir($dir)) @mkdir($dir, 0700, true); $new = !is_file($f); $s = new self(new PDO($dsn)); if ($new) @chmod($f, 0600); return $s; }
        return new self(new PDO($dsn, $user, $pass, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']));
    }
    public function pdo(): PDO { return $this->db; }
    public static function now(): string { return date('Y-m-d H:i:s'); }
    public static function newId(string $p = 'r'): string { return $p . bin2hex(random_bytes(8)); }

    /* ---------- migrace (verze v ts_booking_schema) ---------- */
    public function migrate(): array
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS ts_booking_schema (version INTEGER PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $done = array_map('intval', $this->db->query('SELECT version FROM ts_booking_schema')->fetchAll(PDO::FETCH_COLUMN));
        $applied = [];
        foreach (glob(__DIR__ . '/../migrations/*.' . ($this->driver === 'mysql' ? 'mysql' : 'sqlite') . '.sql') as $f) {
            $v = (int) basename($f); if (in_array($v, $done, true)) continue;
            foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents($f))))) as $sql) $this->db->exec($sql);
            $this->db->prepare('INSERT INTO ts_booking_schema (version, applied_at) VALUES (?, ?)')->execute([$v, self::now()]);
            $applied[] = $v;
        }
        return $applied;
    }

    /* ---------- transakce se zámkem zápisu ---------- */
    public function tx(callable $fn)
    {
        if ($this->driver === 'sqlite') $this->db->exec('BEGIN IMMEDIATE');
        else { $this->db->beginTransaction(); $this->db->query('SELECT id FROM ts_booking_lock WHERE id = 1 FOR UPDATE'); }
        try { $r = $fn(); $this->driver === 'sqlite' ? $this->db->exec('COMMIT') : $this->db->commit(); return $r; }
        catch (Throwable $e) { if ($this->driver === 'sqlite') { try { $this->db->exec('ROLLBACK'); } catch (Throwable $x) { } } elseif ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    /* ---------- převod řádku na rezervaci jádra ---------- */
    private static function row(array $r, bool $public): array
    {
        $b = ['id' => $r['id'], 'date' => $r['date'], 'start' => (int) $r['start'], 'dur' => (int) $r['dur'], 'buffer' => (int) $r['buffer'], 'service' => $r['service'],
              'resource' => $r['resource'], 'status' => $r['status']];
        if ($r['pool_member'] !== null) $b['poolMember'] = $r['pool_member'];
        if ($r['units'] !== null) $b['units'] = (int) $r['units'];
        if ($public) { unset($b['id']); return $b; }
        return $b + ['variant' => (int) $r['variant'], 'addons' => $r['addons'] ? json_decode($r['addons'], true) : [], 'price' => $r['price'] !== null ? (int) $r['price'] : null,
            'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'], 'note' => $r['note'], 'lang' => $r['lang'], 'source' => $r['source'],
            'consentAt' => $r['consent_at'], 'createdAt' => $r['created_at'], 'updatedAt' => $r['updated_at'], 'anonymizedAt' => $r['anonymized_at']];
    }
    private static function blockRow(array $r): array { return ['id' => $r['id'], 'date' => $r['date'], 'resource' => $r['resource'], 'from' => (int) $r['from_min'], 'to' => (int) $r['to_min'], 'reason' => $r['reason']]; }

    /* stav pro jádro: rezervace a blokace v rozsahu dnů; public = bez osobních údajů a id */
    public function state(string $from, string $to, bool $public = false): array
    {
        $q = $this->db->prepare('SELECT * FROM ts_booking_bookings WHERE date BETWEEN ? AND ? ORDER BY date, start'); $q->execute([$from, $to]);
        $bookings = array_map(fn($r) => self::row($r, $public), $q->fetchAll());
        $q = $this->db->prepare('SELECT * FROM ts_booking_blocks WHERE date BETWEEN ? AND ? ORDER BY date, from_min'); $q->execute([$from, $to]);
        $blocks = array_map(function ($r) use ($public) { $b = self::blockRow($r); if ($public) unset($b['id'], $b['reason']); return $b; }, $q->fetchAll());
        if ($public) $bookings = array_values(array_filter($bookings, fn($b) => in_array($b['status'], ['pending', 'confirmed', 'done'], true)));
        return ['bookings' => $bookings, 'blocks' => $blocks];
    }
    public function get(string $id): ?array { $q = $this->db->prepare('SELECT * FROM ts_booking_bookings WHERE id = ?'); $q->execute([$id]); $r = $q->fetch(); return $r ? self::row($r, false) : null; }

    /* ---------- založení rezervace ----------
       $engineFor(array $state): TsBookingEngine – továrna, aby jádro dostalo čerstvý stav uvnitř transakce.
       → ['ok' => true, 'booking', 'cancelToken', 'duplicate'] | ['ok' => false, 'error'] */
    public function create(callable $engineFor, array $req, ?string $idemKey, string $actor = 'web'): array
    {
        return $this->tx(function () use ($engineFor, $req, $idemKey, $actor) {
            if ($idemKey !== null && $idemKey !== '') {
                $q = $this->db->prepare('SELECT id FROM ts_booking_bookings WHERE idem_key = ?'); $q->execute([$idemKey]);
                if ($id = $q->fetchColumn()) return ['ok' => true, 'booking' => $this->get($id), 'cancelToken' => null, 'duplicate' => true];
            }
            $engine = $engineFor($this->state($req['date'], $req['date']));
            $p = $engine->prepareBooking($req);
            if (!$p['ok']) return $p;
            $b = $p['booking']; $id = self::newId(); $token = bin2hex(random_bytes(16)); $now = self::now();
            $this->db->prepare('INSERT INTO ts_booking_bookings (id, date, start, dur, buffer, service, variant, addons, resource, pool_member, units, status, price, name, email, phone, note, lang, source, idem_key, cancel_hash, consent_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $id, $b['date'], (int) $b['start'], (int) $b['dur'], (int) ($b['buffer'] ?? 0), $b['service'], (int) ($b['variant'] ?? 0), json_encode($b['addons'] ?? []),
                $b['resource'] ?? null, ($b['poolMember'] ?? '') !== '' ? $b['poolMember'] : null, $b['units'] ?? null, $b['status'], isset($b['price']) ? (int) $b['price'] : null,
                $b['name'] ?? null, $b['email'] ?? null, $b['phone'] ?? null, $b['note'] ?? null, $b['lang'] ?? null, $b['source'] ?? $actor,
                ($idemKey ?? '') !== '' ? $idemKey : null, hash('sha256', $token), $b['consentAt'] ?? $now, $now, $now]);
            $this->event($id, 'vytvoreno', $actor, null, $b['status']);
            return ['ok' => true, 'booking' => $this->get($id), 'cancelToken' => $token, 'duplicate' => false];
        });
    }

    /* změna stavu s kontrolou povoleného přechodu → ['ok', 'booking', 'old'] */
    public function setStatus(string $id, string $status, string $actor, string $detail = ''): array
    {
        return $this->tx(function () use ($id, $status, $actor, $detail) {
            $b = $this->get($id); if (!$b) return ['ok' => false, 'error' => 'not-found'];
            if (!in_array($status, self::TRANSITIONS[$b['status']] ?? [], true)) return ['ok' => false, 'error' => 'transition', 'from' => $b['status']];
            $this->db->prepare('UPDATE ts_booking_bookings SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, self::now(), $id]);
            $this->event($id, 'stav', $actor, $b['status'], $status, $detail);
            return ['ok' => true, 'booking' => $this->get($id), 'old' => $b['status']];
        });
    }
    /* zrušení zákazníkem přes jednorázový odkaz (token se ukládá jen jako otisk) */
    public function cancelByToken(string $id, string $token): array
    {
        $q = $this->db->prepare('SELECT cancel_hash, status FROM ts_booking_bookings WHERE id = ?'); $q->execute([$id]); $r = $q->fetch();
        if (!$r || !$r['cancel_hash'] || !hash_equals($r['cancel_hash'], hash('sha256', $token))) return ['ok' => false, 'error' => 'token'];
        if (!in_array('cancelled', self::TRANSITIONS[$r['status']] ?? [], true)) return ['ok' => false, 'error' => 'transition', 'from' => $r['status']];
        $res = $this->setStatus($id, 'cancelled', 'zakaznik', 'zrušeno odkazem');
        if ($res['ok']) $this->db->prepare('UPDATE ts_booking_bookings SET cancel_hash = NULL WHERE id = ?')->execute([$id]);
        return $res;
    }

    public function listAdmin(string $from, string $to, ?string $status = null): array
    {
        $sql = 'SELECT * FROM ts_booking_bookings WHERE date BETWEEN ? AND ?' . ($status ? ' AND status = ?' : '') . ' ORDER BY date, start';
        $q = $this->db->prepare($sql); $q->execute($status ? [$from, $to, $status] : [$from, $to]);
        return array_map(fn($r) => self::row($r, false), $q->fetchAll());
    }

    /* ---------- blokace ---------- */
    public function addBlock(array $b, string $actor): array
    {
        $id = self::newId('k');
        $this->db->prepare('INSERT INTO ts_booking_blocks (id, date, resource, from_min, to_min, reason, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $b['date'], $b['resource'] ?? 'all', (int) $b['from'], (int) $b['to'], $b['reason'] ?? '', $actor, self::now()]);
        $this->event(null, 'blokace', $actor, null, null, $b['date'] . ' ' . $b['from'] . '-' . $b['to'] . ' ' . ($b['resource'] ?? 'all'));
        return ['id' => $id] + $b;
    }
    public function deleteBlock(string $id, string $actor): bool
    {
        $q = $this->db->prepare('DELETE FROM ts_booking_blocks WHERE id = ?'); $q->execute([$id]);
        if ($q->rowCount()) $this->event(null, 'blokace-smazana', $actor, null, null, $id);
        return $q->rowCount() > 0;
    }

    /* ---------- GDPR ---------- */
    public function exportByEmail(string $email): array
    {
        $q = $this->db->prepare('SELECT * FROM ts_booking_bookings WHERE lower(email) = lower(?) ORDER BY date'); $q->execute([trim($email)]);
        return array_map(fn($r) => self::row($r, false), $q->fetchAll());
    }
    public function anonymize(string $id, string $actor): bool
    {
        $q = $this->db->prepare('UPDATE ts_booking_bookings SET name = NULL, email = NULL, phone = NULL, note = NULL, cancel_hash = NULL, anonymized_at = ?, updated_at = ? WHERE id = ? AND anonymized_at IS NULL');
        $q->execute([self::now(), self::now(), $id]);
        if ($q->rowCount()) $this->event($id, 'anonymizace', $actor);
        return $q->rowCount() > 0;
    }
    /* anonymizuje rezervace starší než $days dní po termínu → počet */
    public function anonymizeOlderThan(int $days, string $today): int
    {
        $limit = TsBookingEngine::addDays($today, -$days);
        $q = $this->db->prepare('SELECT id FROM ts_booking_bookings WHERE date < ? AND anonymized_at IS NULL'); $q->execute([$limit]); $n = 0;
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $n += (int) $this->anonymize($id, 'retence');
        return $n;
    }

    /* ---------- audit ---------- */
    public function event(?string $bookingId, string $action, string $actor, ?string $old = null, ?string $new = null, string $detail = ''): void
    {
        $this->db->prepare('INSERT INTO ts_booking_events (booking_id, action, actor, old_status, new_status, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$bookingId, $action, $actor, $old, $new, $detail, self::now()]);
    }
    public function events(string $bookingId): array { $q = $this->db->prepare('SELECT action, actor, old_status, new_status, detail, created_at FROM ts_booking_events WHERE booking_id = ? ORDER BY id'); $q->execute([$bookingId]); return $q->fetchAll(); }

    /* ---------- uživatelé administrace ---------- */
    public function createUser(string $username, string $name, string $password, string $role = 'staff', ?string $email = null): int
    {
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) throw new InvalidArgumentException('uživatelské jméno: 3–40 znaků a-z 0-9 . _ -');
        if (mb_strlen($password) < 10) throw new InvalidArgumentException('heslo musí mít aspoň 10 znaků');
        $this->db->prepare('INSERT INTO ts_booking_users (username, name, email, role, password_hash, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)')
            ->execute([$username, $name, $email, $role, password_hash($password, PASSWORD_DEFAULT), self::now()]);
        return (int) $this->db->lastInsertId();
    }
    public function verifyUser(string $username, string $password): ?array
    {
        $q = $this->db->prepare('SELECT * FROM ts_booking_users WHERE username = ? AND active = 1'); $q->execute([$username]); $u = $q->fetch();
        /* stejně dlouhé ověření i pro neexistujícího uživatele (bez úniku informace časem) */
        $ok = password_verify($password, $u ? $u['password_hash'] : '$2y$10$abcdefghijklmnopqrstuuJ9pVqVnN4sHq3C4yH5c0lJxZcHk6p2G');
        if (!$u || !$ok) return null;
        $this->db->prepare('UPDATE ts_booking_users SET last_login_at = ? WHERE id = ?')->execute([self::now(), $u['id']]);
        return ['id' => (int) $u['id'], 'username' => $u['username'], 'name' => $u['name'], 'role' => $u['role']];
    }
    public function user(int $id): ?array { $q = $this->db->prepare('SELECT id, username, name, email, role, active FROM ts_booking_users WHERE id = ?'); $q->execute([$id]); $u = $q->fetch(); return $u ?: null; }
}
