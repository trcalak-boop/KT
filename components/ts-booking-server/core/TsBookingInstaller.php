<?php
/* ==========================================================================
   ts-booking-server – jednorázový webový instalátor (pro hosting bez SSH)
   Kontrola prostředí · inicializace databáze · kontrola SMTP bez přihlášení · zkušební e-mail · vytvoření
   PRVNÍHO administrátora. Každá akce vyžaduje instalační kód (v konfiguraci je jen jeho otisk), platí jen do
   install.until a jen přes HTTPS. Po vytvoření administrátora se vytvoření dalšího účtu zamkne (install.lock).
   Soubor ve webrootu, který instalátor volá, se po instalaci SMAŽE.

   Použití (soubor ve webrootu s náhodným názvem):
     require __DIR__ . '/vendor/ts/ts-booking-server/core/bootstrap.php';
     require __DIR__ . '/vendor/ts/ts-booking-server/core/TsBookingInstaller.php';
     (new TsBookingInstaller(require '/cesta/k/private/booking-server.php'))->serve();
   ========================================================================== */
final class TsBookingInstaller
{
    public const VERSION = '1.0.0';
    private array $s; private string $dataDir;

    public function __construct(array $settings)
    {
        $this->s = $settings;
        $this->dataDir = $settings['install']['dataDir'] ?? dirname($settings['authDir'] ?? (sys_get_temp_dir() . '/x'));
    }
    private function lockFile(): string { return $this->dataDir . '/install.lock'; }
    public function locked(): bool { return is_file($this->lockFile()); }
    private static function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
    private static function inside(string $path, string $root): bool { $p = realpath($path) ?: ((realpath(dirname($path)) ?: dirname($path)) . '/' . basename($path)); $r = realpath($root) ?: $root; return $r !== '' && strpos($p, rtrim($r, '/') . '/') === 0; }

    /* ---------- kontrola prostředí (bez hesel a bez absolutních cest ve výstupu) ---------- */
    public function checks(): array
    {
        $s = $this->s; $root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $dbFile = strpos($s['db']['dsn'], 'sqlite:') === 0 ? substr($s['db']['dsn'], 7) : null;
        $c = [];
        $c[] = ['PHP ' . PHP_VERSION . ' (potřeba 8.1+)', version_compare(PHP_VERSION, '8.1.0', '>=')];
        foreach (['pdo_sqlite', 'mbstring', 'openssl', 'session', 'json', 'filter'] as $x) $c[] = ['rozšíření ' . $x, extension_loaded($x)];
        $c[] = ['HTTPS', TsAuth::isHttps() || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)];
        $c[] = ['konfigurace rezervací existuje a je platná', is_file($s['configFile']) && !TsBookingEngine::validate((array) json_decode((string) @file_get_contents($s['configFile']), true))];
        $c[] = ['konfigurace rezervací je mimo webroot', $root !== '' && !self::inside($s['configFile'], $root)];
        $c[] = ['složka dat je zapisovatelná', is_dir($this->dataDir) ? is_writable($this->dataDir) : @mkdir($this->dataDir, 0700, true)];
        if ($dbFile) $c[] = ['databáze SQLite je mimo webroot', $root !== '' && !self::inside($dbFile, $root)];
        $c[] = ['relace správy jsou mimo webroot', isset($s['authDir']) && $root !== '' && !self::inside($s['authDir'], $root)];
        $mail = $s['mail'] ?? [];
        $c[] = ['e-maily: režim ' . ($mail['transport'] ?? (empty($mail['smtp_host']) ? 'mail' : 'smtp')) . (empty($mail['redirectTo']) ? ' (BEZ přesměrování do testovací schránky)' : ' + přesměrování do testovací schránky'), !empty($mail['redirectTo'])];
        $c[] = ['SMTP heslo je vyplněné (hodnota se nezobrazuje)', !empty($mail['smtp_pass'])];
        return $c;
    }
    private function smtpMailer(): TsMailer
    {
        $m = $this->s['mail']; $m['transport'] = 'smtp';
        if (empty($m['redirectTo']) && !empty($this->s['install']['testRecipient'])) $m['redirectTo'] = $this->s['install']['testRecipient'];
        return new TsMailer($m);
    }
    private function hasAdmin(TsBookingStore $st): bool { return (int) $st->pdo()->query("SELECT COUNT(*) FROM ts_booking_users WHERE role = 'admin' AND active = 1")->fetchColumn() > 0; }

    /* ---------- akce (testovatelné bez HTTP) → [ok, zpráva] ---------- */
    public function action(string $act, array $p): array
    {
        $st = ts_booking_store(['db' => ['autoMigrate' => false] + $this->s['db']] + $this->s);
        if ($act === 'status') return [true, 'Kód ověřen.'];
        if ($act === 'migrate') { $v = $st->migrate(); TsMailQueue::migrate($st->pdo()); return [true, $v ? 'Databáze vytvořena (migrace ' . implode(', ', $v) . ').' : 'Databáze už je aktuální.']; }
        if ($act === 'probe') {
            $r = $this->smtpMailer()->probe();
            return [$r['ok'], $r['ok'] ? 'SMTP server odpovídá (kód ' . $r['uvitani'] . ', šifrování ' . ($r['sifrovani'] ? 'ano' : 'ne') . ', AUTH LOGIN ' . ($r['auth_login'] ? 'ano' : 'ne') . '). Přihlášení se nezkoušelo.' : 'SMTP: ' . ($r['chyba'] ?? 'neodpověděl správně')];
        }
        if ($act === 'testmail') {
            if (empty($this->s['mail']['smtp_pass'])) return [false, 'Nejdřív doplňte SMTP heslo do neveřejné konfigurace.'];
            $to = $this->s['install']['testRecipient'] ?? null; if (!$to) return [false, 'Chybí install.testRecipient.'];
            $r = $this->smtpMailer()->send($to, 'Zkušební e-mail – rezervace (test)', "Toto je zkušební e-mail z instalátoru rezervací.\nČas: " . date('j. n. Y H:i'), '<p>Toto je zkušební e-mail z instalátoru rezervací.</p>');
            return [$r['ok'], $r['ok'] ? 'Zkušební e-mail odeslán (' . ($r['stav'] ?? '') . '). Zkontrolujte testovací schránku.' : 'Odeslání selhalo: ' . ($r['chyba'] ?? '?')];
        }
        if ($act === 'admin') {
            $st->migrate();
            if ($this->locked() || $this->hasAdmin($st)) return [false, 'Administrátor už existuje – další účet instalátor nevytvoří.'];
            $pw = (string) ($p['password'] ?? '');
            if ($pw !== (string) ($p['password2'] ?? '')) return [false, 'Hesla se neshodují.'];
            if (mb_strlen($pw) < 12) return [false, 'Heslo musí mít aspoň 12 znaků.'];
            try { $st->createUser(trim((string) ($p['username'] ?? '')), trim((string) ($p['name'] ?? '')) ?: 'Administrátor', $pw, 'admin', filter_var($p['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null); }
            catch (Throwable $e) { return [false, $e instanceof InvalidArgumentException ? $e->getMessage() : 'Účet se nepodařilo vytvořit.']; }
            @file_put_contents($this->lockFile(), date('c') . "\n"); @chmod($this->lockFile(), 0600);
            $st->event(null, 'instalace-admin', 'instalator', null, null, (string) $p['username']);
            return [true, 'Administrátor vytvořen. Vytváření dalších účtů je zamčené. Nyní SMAŽTE instalační soubor z webu.'];
        }
        return [false, 'Neznámá akce.'];
    }

    /* ---------- HTTP ---------- */
    public function serve(): void
    {
        ini_set('display_errors', '0');
        header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");
        $inst = $this->s['install'] ?? [];
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        if (empty($inst['codeHash']) || empty($inst['until']) || date('Y-m-d') > $inst['until']) { http_response_code(404); echo 'Instalátor není aktivní.'; return; }
        if (!TsAuth::isHttps() && !$local) { http_response_code(403); echo 'Instalátor funguje jen přes HTTPS.'; return; }
        if (!is_dir($this->dataDir)) @mkdir($this->dataDir, 0700, true);
        $auth = new TsAuth(['dataDir' => $this->dataDir, 'sessionName' => 'tsinstall', 'maxAttempts' => 5, 'lockSeconds' => 900, 'cookiePath' => $this->s['cookiePath'] ?? '/']);
        $auth->start();
        $msg = null; $authorized = false;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if ($s = $auth->lockedFor()) $msg = [false, 'Příliš mnoho špatných kódů. Zkuste to za ' . ceil($s / 60) . ' min.'];
            elseif (!$auth->csrfOk($_POST['csrf'] ?? '')) $msg = [false, 'Platnost stránky vypršela, načtěte ji znovu.'];
            elseif (!password_verify((string) ($_POST['code'] ?? ''), $inst['codeHash'])) { $auth->failedAttempt(); $msg = [false, 'Nesprávný instalační kód.']; }
            else { $authorized = true; $auth->resetAttempts(); try { $msg = $this->action((string) ($_POST['act'] ?? ''), $_POST); } catch (Throwable $e) { error_log('ts-booking-installer: akce ' . preg_replace('/[^a-z]/', '', (string) ($_POST['act'] ?? '')) . ' selhala (' . get_class($e) . ')'); $msg = [false, 'Akce selhala (podrobnosti v logu serveru).']; } }
        }
        /* bez platného kódu se nezobrazí ani kontrola prostředí – jen formulář pro kód */
        $this->page($auth->csrfToken(), $msg, $authorized);
    }

    private function page(string $csrf, ?array $msg, bool $authorized): void
    {
        $rows = ''; if ($authorized) foreach ($this->checks() as [$t, $ok]) $rows .= '<li class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? '✔ ' : '✘ ') . self::e($t) . '</li>';
        $form = function ($act, $label, $extra = '') use ($csrf) {
            return '<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="' . self::e($csrf) . '"><input type="hidden" name="act" value="' . $act . '">' . $extra .
                '<label>Instalační kód <input type="password" name="code" required autocomplete="off"></label><button>' . self::e($label) . '</button></form>';
        };
        $admin = $this->locked() ? '<p class="ok">Administrátor je vytvořený – tento krok je zamčený.</p>' : $form('admin', 'Vytvořit administrátora',
            '<label>Uživatelské jméno <input name="username" required pattern="[a-z0-9._-]{3,40}"></label><label>Celé jméno <input name="name" required></label><label>E-mail (nepovinný) <input name="email" type="email"></label>' .
            '<label>Heslo (min. 12 znaků) <input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Heslo znovu <input type="password" name="password2" required minlength="12" autocomplete="new-password"></label>');
        $body = $authorized
            ? '<h2>1. Kontrola prostředí</h2><ul>' . $rows . '</ul>' . '<h2>2. Databáze</h2>' . $form('migrate', 'Vytvořit / zkontrolovat databázi') .
              '<h2>3. SMTP server (bez přihlášení)</h2>' . $form('probe', 'Ověřit spojení se SMTP serverem') .
              '<h2>4. Zkušební e-mail (po doplnění hesla)</h2><p>Odešle jeden e-mail do testovací schránky přes SMTP.</p>' . $form('testmail', 'Odeslat zkušební e-mail') .
              '<h2>5. Administrátor</h2>' . $admin
            : '<h2>Instalační kód</h2><p>Kontrola a všechny kroky instalace se zobrazí až po zadání platného kódu.</p>' . $form('status', 'Pokračovat');
        echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Instalace rezervací</title>' .
            '<style>body{font:15px/1.5 system-ui,sans-serif;max-width:720px;margin:24px auto;padding:0 16px;color:#1d1d20}h1{font-size:1.4rem}h2{font-size:1.05rem;margin:22px 0 8px}ul{padding:0;list-style:none}.ok{color:#1f7a4d}.bad{color:#b42318}' .
            'form{display:grid;gap:8px;padding:14px;border:1px solid #ddd;border-radius:10px;margin:8px 0}label{display:grid;gap:4px}input{font:inherit;padding:8px;border:1px solid #bbb;border-radius:6px}button{font:inherit;font-weight:600;padding:10px;border:0;border-radius:6px;background:#2f5bea;color:#fff;cursor:pointer}.msg{padding:10px 14px;border-radius:8px;background:#f4f4f6}.warn{background:#fdf1dc;padding:10px 14px;border-radius:8px}</style></head><body>' .
            '<h1>Instalace rezervací (test)</h1><p class="warn">Jednorázový instalátor. Po dokončení <b>smažte instalační soubor z webu</b>. Platí do ' . self::e($this->s['install']['until']) . '.</p>' .
            ($msg ? '<p class="msg ' . ($msg[0] ? 'ok' : 'bad') . '" role="status">' . self::e($msg[1]) . '</p>' : '') . $body . '</body></html>';
    }
}
