<?php
/* ==========================================================================
   ts-auth-php – přihlášení, relace, CSRF, ochrana proti hádání hesla, limity požadavků, role
   Převzato z administrace tasstudio.eu (web/admin/lib.php: start_relace, prihlasit, odhlasit, csrf_ok,
   _pokusy_uprav, zamceno, neuspech, zapis_atomicky; crm-poptavky.php: poptavka_limit_ok) a zobecněno
   do třídy s konfigurací. Bez databáze: stav se drží v souborech v $dataDir (mimo webroot nebo za „Require all denied“).
   Oprávnění (can) jsou nová: v původním projektu se role nekontrolovaly.
   ========================================================================== */
final class TsAuth
{
    public const VERSION = '1.0.0';
    private array $c;

    /* dataDir (povinné), sessionName, idleSeconds, maxAttempts, lockSeconds, ipSalt, cookiePath, minPasswordLength, policy */
    public function __construct(array $config)
    {
        if (empty($config['dataDir'])) throw new InvalidArgumentException('TsAuth: chybí dataDir');
        $this->c = $config + ['sessionName' => 'tsadmin', 'idleSeconds' => 12 * 3600, 'maxAttempts' => 6, 'lockSeconds' => 15 * 60,
            'ipSalt' => 'ts', 'cookiePath' => null, 'minPasswordLength' => 10, 'policy' => ['admin' => ['*']]];
        $this->c['dataDir'] = rtrim($this->c['dataDir'], '/');
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
        if (($_SERVER['SERVER_PORT'] ?? '') == 443) return true;
        return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /* ---------- relace ---------- */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $dir = $this->c['dataDir'] . '/relace';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string) ($this->c['idleSeconds'] + 600));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $path = $this->c['cookiePath'] ?? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/') . '/';
        session_name($this->c['sessionName']);
        session_set_cookie_params(['lifetime' => 0, 'path' => $path, 'secure' => self::isHttps(), 'httponly' => true, 'samesite' => 'Strict']);
        session_start();
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
        if (!empty($_SESSION['ok']) && time() - ($_SESSION['t'] ?? 0) > $this->c['idleSeconds']) $_SESSION = ['csrf' => bin2hex(random_bytes(24))];
        if (!empty($_SESSION['ok'])) $_SESSION['t'] = time();
    }
    public function loggedIn(): bool { return !empty($_SESSION['ok']); }
    public function userId(): int { return (int) ($_SESSION['uid'] ?? 0); }
    public function role(): string { return (string) ($_SESSION['role'] ?? ''); }
    public function login(int $uid, string $role = 'admin'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) session_regenerate_id(true);
        $_SESSION['ok'] = true; $_SESSION['t'] = time(); $_SESSION['uid'] = $uid; $_SESSION['role'] = $role;
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    /* ---------- CSRF: token z hlavičky X-CSRF nebo pole csrf ---------- */
    public function csrfToken(): string { return (string) ($_SESSION['csrf'] ?? ''); }
    public function csrfOk(?string $token = null): bool
    {
        $t = $token ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? ''));
        return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
    }

    /* ---------- hesla ---------- */
    public static function hashPassword(string $p): string { return password_hash($p, PASSWORD_DEFAULT); }
    public static function verifyPassword(string $p, string $hash): bool { return $hash !== '' && password_verify($p, $hash); }
    public function strongEnough($p): bool { return is_string($p) && mb_strlen($p) >= $this->c['minPasswordLength']; }

    /* ---------- ochrana proti hádání hesla (otisk IP, ne IP samotná) ---------- */
    public function fingerprint(): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '?') . '|' . $this->c['ipSalt']); }
    private function withJson(string $file, callable $fn)
    {
        $f = @fopen($this->c['dataDir'] . '/' . $file, 'c+');
        if (!$f) { $empty = []; return $fn($empty); }
        flock($f, LOCK_EX);
        $d = json_decode(stream_get_contents($f) ?: '[]', true) ?: [];
        $out = $fn($d);
        ftruncate($f, 0); rewind($f); fwrite($f, json_encode($d)); fflush($f); flock($f, LOCK_UN); fclose($f);
        return $out;
    }
    private function attempts(callable $fn)
    {
        $lock = $this->c['lockSeconds'];
        return $this->withJson('pokusy.json', function (&$d) use ($fn, $lock) {
            foreach ($d as $k => $v) if (time() - ($v['od'] ?? 0) > $lock) unset($d[$k]);
            return $fn($d);
        });
    }
    /* počet sekund do odemčení, 0 = lze se přihlásit */
    public function lockedFor(): int
    {
        $k = $this->fingerprint(); $max = $this->c['maxAttempts']; $lock = $this->c['lockSeconds'];
        return $this->attempts(function (&$d) use ($k, $max, $lock) { $v = $d[$k] ?? null; return $v && $v['n'] >= $max ? max(1, $lock - (time() - $v['od'])) : 0; });
    }
    public function failedAttempt(): void
    {
        $k = $this->fingerprint();
        $this->attempts(function (&$d) use ($k) { if (empty($d[$k])) $d[$k] = ['n' => 0, 'od' => time()]; $d[$k]['n']++; return null; });
    }
    public function resetAttempts(): void { $k = $this->fingerprint(); $this->attempts(function (&$d) use ($k) { unset($d[$k]); return null; }); }

    /* ---------- limit požadavků: nejvýš $max za $window sekund pro klíč (např. formulář) a otisk IP ---------- */
    public function rateLimit(string $key, int $max, int $window = 3600): bool
    {
        $k = $this->fingerprint(); $now = time();
        return $this->withJson('limit-' . preg_replace('/[^a-z0-9_-]/i', '', $key) . '.json', function (&$d) use ($k, $max, $window, $now) {
            foreach ($d as $i => $t) { $d[$i] = array_values(array_filter((array) $t, function ($x) use ($now, $window) { return $now - $x < $window; })); if (!$d[$i]) unset($d[$i]); }
            $ok = count($d[$k] ?? []) < $max;
            if ($ok) $d[$k][] = $now;
            return $ok;
        });
    }

    /* ---------- oprávnění: policy = ['role' => ['oprávnění', 'oblast.*', '*']] ---------- */
    public function can(string $permission, ?string $role = null): bool
    {
        $perms = $this->c['policy'][$role ?? $this->role()] ?? [];
        foreach ($perms as $p) {
            if ($p === '*' || $p === $permission) return true;
            if (substr($p, -2) === '.*' && strpos($permission, substr($p, 0, -1)) === 0) return true;
        }
        return false;
    }

    /* ---------- pomocné ---------- */
    public static function writeAtomic(string $path, string $content): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
        $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(6));
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) return false;
        @chmod($tmp, 0644);
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        return true;
    }
    public static function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
