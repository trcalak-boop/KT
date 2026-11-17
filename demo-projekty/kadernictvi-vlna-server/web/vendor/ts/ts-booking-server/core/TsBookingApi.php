<?php
/* ==========================================================================
   ts-booking-server – HTTP API rezervací (směrování přes ?r=…, funguje na sdíleném hostingu bez rewrite)
   Veřejné:  GET config · GET availability · POST bookings · POST cancel
   Správa:   POST admin/login · POST admin/logout · GET admin/me · GET admin/bookings · POST admin/status
             POST admin/blocks · POST admin/blocks/delete · GET admin/export · POST admin/anonymize
   Správa vyžaduje přihlášení (TsAuth, relace), CSRF hlavičku X-CSRF u POST a oprávnění podle role.
   Třída nic nevypisuje: handle() vrací [stav HTTP, data] – testovatelné bez webového serveru.
   ========================================================================== */
final class TsBookingApi
{
    public const VERSION = '1.0.0';
    public const POLICY = ['admin' => ['*'], 'staff' => ['booking.view', 'booking.edit', 'booking.blocks'], 'viewer' => ['booking.view']];
    private array $s; private TsBookingStore $store; private array $config; private ?TsAuth $auth; private ?TsMailQueue $queue; private array $opts;

    /* settings: configFile|config, store (TsBookingStore), auth (TsAuth|null), queue (TsMailQueue|null), notify, autoConfirm,
                 rateLimit ['bookings' => [počet, sekund]], minFillSeconds, allowedOrigins, extensions, clock (testy) */
    public function __construct(array $settings)
    {
        $this->s = $settings + ['autoConfirm' => false, 'rateLimit' => ['bookings' => [10, 3600]], 'minFillSeconds' => 3, 'allowedOrigins' => [], 'extensions' => [], 'notify' => [], 'processQueue' => 2];
        $this->store = $settings['store'];
        $this->config = $settings['config'] ?? json_decode((string) file_get_contents($settings['configFile']), true);
        if (!is_array($this->config) || TsBookingEngine::validate($this->config)) throw new RuntimeException('Neplatná konfigurace rezervací: ' . implode('; ', TsBookingEngine::validate((array) $this->config)));
        $this->auth = $settings['auth'] ?? null; $this->queue = $settings['queue'] ?? null;
        $this->opts = ['extensions' => $this->s['extensions']] + (isset($this->s['clock']) ? ['clock' => $this->s['clock']] : []);
    }
    public function engine(array $state = []): TsBookingEngine { return new TsBookingEngine($this->config, $state, $this->opts); }
    private function notifier(): TsBookingNotifier { return new TsBookingNotifier($this->queue, $this->s['notify'], $this->engine()); }

    /* ---------- vstupní bod ---------- */
    public function handle(string $method, string $route, array $query, $body, array $headers): array
    {
        $method = strtoupper($method); $body = is_array($body) ? $body : [];
        try {
            if (strpos($route, 'admin/') === 0) $res = $this->admin($method, substr($route, 6), $query, $body, $headers);
            else $res = $this->publicRoute($method, $route, $query, $body, $headers);
        } catch (Throwable $e) {
            error_log('ts-booking-server: ' . $e->getMessage());
            $res = [500, ['ok' => false, 'error' => 'server']];
        }
        if ($this->queue && $this->s['processQueue'] > 0) { try { $this->queue->process($this->s['processQueue']); } catch (Throwable $e) { } }
        return $res;
    }

    /* ---------- veřejné ---------- */
    private function publicRoute(string $m, string $r, array $q, array $b, array $h): array
    {
        if ($r === 'config' && $m === 'GET') return [200, ['ok' => true, 'config' => $this->publicConfig()]];
        if ($r === 'availability' && $m === 'GET') {
            $e = $this->engine(); $today = $e->today();
            $from = self::date($q['from'] ?? '') ?: $today; $to = self::date($q['to'] ?? '') ?: TsBookingEngine::addDays($today, (int) $this->config['maxDays'] ?: 60);
            if ($from < $today) $from = $today;
            $max = TsBookingEngine::addDays($today, (int) ($this->config['maxDays'] ?? 60) + 1); if ($to > $max) $to = $max;
            if ($to < $from) return [400, ['ok' => false, 'error' => 'range']];
            return [200, ['ok' => true, 'from' => $from, 'to' => $to, 'today' => $today, 'nowMin' => $e->nowMin(), 'state' => $this->store->state($from, $to, true)]];
        }
        if ($r === 'bookings' && $m === 'POST') return $this->createPublic($b, $h);
        if ($r === 'cancel' && $m === 'POST') {
            $res = $this->store->cancelByToken((string) ($b['id'] ?? ''), (string) ($b['token'] ?? ''));
            if (!$res['ok']) return [$res['error'] === 'token' ? 403 : 409, ['ok' => false, 'error' => $res['error']]];
            $this->notifier()->notify('cancelled', $res['booking']);
            return [200, ['ok' => true, 'booking' => self::publicBooking($res['booking'])]];
        }
        return [404, ['ok' => false, 'error' => 'route']];
    }

    public function publicConfig(): array
    {
        $c = $this->config;
        foreach (['resources'] as $k) $c[$k] = array_map(fn($r) => array_intersect_key($r, array_flip(['id', 'name', 'services', 'hours', 'away', 'bookable'])), $c[$k] ?? []);
        unset($c['$comment']);
        return $c;
    }
    private static function publicBooking(array $b): array { return array_intersect_key($b, array_flip(['id', 'date', 'start', 'dur', 'buffer', 'service', 'resource', 'units', 'status', 'price'])); }
    private static function date($s): ?string { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4)) ? $s : null; }
    private static function text($v, int $max): string { $t = is_scalar($v) ? trim((string) $v) : ''; $t = preg_replace('/[^\P{C}\n\t]/u', '', $t); return mb_substr($t, 0, $max); }

    private function createPublic(array $b, array $h): array
    {
        $origin = $h['origin'] ?? '';
        if ($this->s['allowedOrigins'] && $origin !== '' && !in_array($origin, $this->s['allowedOrigins'], true)) return [403, ['ok' => false, 'error' => 'origin']];
        if (!empty($b['hp'])) return [400, ['ok' => false, 'error' => 'robot']];                                   // skryté pole vyplní jen robot
        if (isset($b['openedMs']) && is_numeric($b['openedMs']) && (int) $b['openedMs'] < $this->s['minFillSeconds'] * 1000) return [400, ['ok' => false, 'error' => 'robot']];
        if ($this->auth && !$this->auth->rateLimit('bookings', ...$this->s['rateLimit']['bookings'])) return [429, ['ok' => false, 'error' => 'limit']];
        $err = [];
        $date = self::date($b['date'] ?? ''); if (!$date) $err[] = 'date';
        $name = self::text($b['name'] ?? '', 160); if ($name === '') $err[] = 'name';
        $email = self::text($b['email'] ?? '', 254); if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'email';
        if (empty($b['consent'])) $err[] = 'consent';
        if (!isset($b['start']) || !is_numeric($b['start'])) $err[] = 'start';
        if (!is_string($b['service'] ?? null)) $err[] = 'service';
        if ($err) return [422, ['ok' => false, 'error' => 'fields', 'fields' => $err]];
        $req = ['date' => $date, 'service' => $b['service'], 'start' => (int) $b['start'], 'variant' => (int) ($b['variant'] ?? 0),
                'addons' => array_values(array_filter((array) ($b['addons'] ?? []), 'is_string')), 'resource' => is_string($b['resource'] ?? null) ? $b['resource'] : 'any',
                'units' => isset($b['units']) ? max(1, min(500, (int) $b['units'])) : null, 'name' => $name, 'email' => $email,
                'phone' => self::text($b['phone'] ?? '', 40), 'note' => self::text($b['note'] ?? '', 1000), 'lang' => in_array($b['lang'] ?? 'cs', ['cs', 'en'], true) ? ($b['lang'] ?? 'cs') : 'cs',
                'source' => 'web', 'status' => $this->s['autoConfirm'] ? 'confirmed' : 'pending', 'consentAt' => TsBookingStore::now()];
        if ($req['units'] === null) unset($req['units']);
        $idem = self::text($h['idempotency-key'] ?? '', 80);
        $res = $this->store->create(fn($state) => $this->engine($state), $req, $idem !== '' ? $idem : null, 'web');
        if (!$res['ok']) return [in_array($res['error'], ['service', 'closed'], true) ? 422 : 409, ['ok' => false, 'error' => $res['error']]];
        if (!$res['duplicate']) {
            $n = $this->notifier();
            $n->notify($res['booking']['status'] === 'confirmed' ? 'confirmed' : 'received', $res['booking'], $res['cancelToken']);
            $n->notify('staff_new', $res['booking']);
        }
        return [$res['duplicate'] ? 200 : 201, ['ok' => true, 'duplicate' => $res['duplicate'], 'booking' => self::publicBooking($res['booking']), 'cancelToken' => $res['cancelToken']]];
    }

    /* ---------- správa ---------- */
    private function admin(string $m, string $r, array $q, array $b, array $h): array
    {
        if (!$this->auth) return [503, ['ok' => false, 'error' => 'auth-disabled']];
        $this->auth->start();
        if ($m === 'POST' && $r !== 'login' && !$this->auth->csrfOk($h['x-csrf'] ?? '')) return [403, ['ok' => false, 'error' => 'csrf']];
        if ($r === 'login' && $m === 'POST') {
            if ($s = $this->auth->lockedFor()) return [429, ['ok' => false, 'error' => 'locked', 'seconds' => $s]];
            $u = $this->store->verifyUser((string) ($b['username'] ?? ''), (string) ($b['password'] ?? ''));
            if (!$u) { $this->auth->failedAttempt(); return [401, ['ok' => false, 'error' => 'login']]; }
            $this->auth->resetAttempts(); $this->auth->login($u['id'], $u['role']);
            $this->store->event(null, 'prihlaseni', $u['username']);
            return [200, ['ok' => true, 'user' => $u, 'csrf' => $this->auth->csrfToken()]];
        }
        if ($r === 'logout' && $m === 'POST') { $this->auth->logout(); return [200, ['ok' => true, 'csrf' => $this->auth->csrfToken()]]; }
        if (!$this->auth->loggedIn()) return [401, ['ok' => false, 'error' => 'login', 'csrf' => $this->auth->csrfToken()]];
        $user = $this->store->user($this->auth->userId());
        if (!$user || !$user['active']) { $this->auth->logout(); return [401, ['ok' => false, 'error' => 'login']]; }
        $actor = $user['username'];
        $need = ['me' => 'booking.view', 'bookings' => 'booking.view', 'status' => 'booking.edit', 'blocks' => 'booking.blocks', 'blocks/delete' => 'booking.blocks', 'export' => 'booking.gdpr', 'anonymize' => 'booking.gdpr'][$r] ?? null;
        if ($need === null) return [404, ['ok' => false, 'error' => 'route']];
        if (!$this->auth->can($need, $user['role'])) return [403, ['ok' => false, 'error' => 'permission']];

        if ($r === 'me') return [200, ['ok' => true, 'user' => $user, 'csrf' => $this->auth->csrfToken(), 'can' => array_values(array_filter(['booking.view', 'booking.edit', 'booking.blocks', 'booking.gdpr'], fn($p) => $this->auth->can($p, $user['role'])))]];
        if ($r === 'bookings' && $m === 'GET') {
            $e = $this->engine(); $from = self::date($q['from'] ?? '') ?: TsBookingEngine::addDays($e->today(), -7); $to = self::date($q['to'] ?? '') ?: TsBookingEngine::addDays($e->today(), 60);
            return [200, ['ok' => true, 'bookings' => $this->store->listAdmin($from, $to, in_array($q['status'] ?? '', TsBookingStore::STATUSES, true) ? $q['status'] : null),
                'blocks' => $this->store->state($from, $to)['blocks'], 'today' => $e->today()]];
        }
        if ($r === 'status' && $m === 'POST') {
            $res = $this->store->setStatus((string) ($b['id'] ?? ''), (string) ($b['status'] ?? ''), $actor, self::text($b['note'] ?? '', 300));
            if (!$res['ok']) return [$res['error'] === 'not-found' ? 404 : 409, ['ok' => false] + $res];
            if (in_array($res['booking']['status'], ['confirmed', 'rejected', 'cancelled'], true)) $this->notifier()->notify($res['booking']['status'], $res['booking']);
            return [200, ['ok' => true, 'booking' => $res['booking']]];
        }
        if ($r === 'blocks' && $m === 'POST') {
            $d = self::date($b['date'] ?? ''); $from = (int) ($b['from'] ?? -1); $to = (int) ($b['to'] ?? -1);
            if (!$d || $from < 0 || $to > 1440 || $to <= $from) return [422, ['ok' => false, 'error' => 'fields']];
            $res = $b['resource'] ?? 'all'; if ($res !== 'all' && !$this->engine()->resource((string) $res)) return [422, ['ok' => false, 'error' => 'resource']];
            return [201, ['ok' => true, 'block' => $this->store->addBlock(['date' => $d, 'resource' => $res, 'from' => $from, 'to' => $to, 'reason' => self::text($b['reason'] ?? '', 200)], $actor)]];
        }
        if ($r === 'blocks/delete' && $m === 'POST') return $this->store->deleteBlock((string) ($b['id'] ?? ''), $actor) ? [200, ['ok' => true]] : [404, ['ok' => false, 'error' => 'not-found']];
        if ($r === 'export' && $m === 'GET') { $email = self::text($q['email'] ?? '', 254); return filter_var($email, FILTER_VALIDATE_EMAIL) ? [200, ['ok' => true, 'email' => $email, 'bookings' => $this->store->exportByEmail($email)]] : [422, ['ok' => false, 'error' => 'email']]; }
        if ($r === 'anonymize' && $m === 'POST') return $this->store->anonymize((string) ($b['id'] ?? ''), $actor) ? [200, ['ok' => true]] : [404, ['ok' => false, 'error' => 'not-found']];
        return [405, ['ok' => false, 'error' => 'method']];
    }

    /* ---------- obsluha skutečného HTTP požadavku (public/api.php) ---------- */
    public function serve(): void
    {
        $route = preg_replace('~[^a-z/]~', '', (string) ($_GET['r'] ?? ''));
        $headers = [];
        foreach ($_SERVER as $k => $v) if (strpos($k, 'HTTP_') === 0) $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
        $raw = file_get_contents('php://input');
        $body = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ? json_decode($raw ?: 'null', true) : $_POST;
        if (strlen((string) $raw) > 32768) { $res = [413, ['ok' => false, 'error' => 'size']]; }
        else $res = $this->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $route, $_GET, $body, $headers);
        http_response_code($res[0]);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex');
        echo json_encode($res[1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
