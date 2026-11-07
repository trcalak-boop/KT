<?php
/* ==========================================================================
   ts-design-studio – brána neveřejného náhledu pro klienta (samostatný origin, např. https://nahledy.tasstudio.eu/<token>/…).
   Vydá soubory JEDNÉ verze jen pro aktivní doručení (aktivované, neprošlé, neodvolané). Bez cookies, bez indexace, přísná CSP.
   Neplatný odkaz = neutrální 404 bez informací o projektu; pokusy o hádání tokenu omezené podle IP (otisk IP v souborech, ne v databázi).
   $gate = new TsStudioPreviewGate($studio, ['failDir' => '/…/private/…/brana', 'contact' => 'tasstudio.eu · +420 …']); $gate->serve();
   ========================================================================== */
final class TsStudioPreviewGate
{
    const MIME = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'json' => 'application/json; charset=utf-8', 'txt' => 'text/plain; charset=utf-8',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ico' => 'image/x-icon'];
    const CSP = "default-src 'none'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'none'";
    private TsDesignStudio $s; private array $c;

    public function __construct(TsDesignStudio $studio, array $c = [])
    {
        $this->s = $studio;
        $this->c = $c + ['failDir' => '', 'maxFails' => 30, 'contact' => '', 'brand' => 'T&S Studio'];
    }

    public function serve(): void
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $h = ['origin' => (string) ($_SERVER['HTTP_ORIGIN'] ?? ''), 'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''), 'sec-fetch-site' => (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')];
        [$code, $headers, $body] = $this->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $_POST, $h, (string) ($_SERVER['QUERY_STRING'] ?? ''));
        http_response_code($code);
        foreach ($headers as $k => $v) header("$k: $v");
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $body;
    }

    /* → [HTTP kód, hlavičky, tělo] */
    public function handle(string $method, string $path, string $ip, array $post = [], array $h = [], string $query = ''): array
    {
        $base = ['X-Robots-Tag' => 'noindex, nofollow, noarchive', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => self::CSP, 'Cross-Origin-Resource-Policy' => 'same-origin'];
        if ($path === '/robots.txt') return [200, $base + ['Content-Type' => 'text/plain; charset=utf-8'], "User-agent: *\nDisallow: /\n"];
        $isPost = $method === 'POST' && preg_match('~^/[A-Za-z0-9_-]{32}/pripominka$~', $path);
        if ($method !== 'GET' && $method !== 'HEAD' && !$isPost) return [405, $base + ['Allow' => 'GET, HEAD', 'Content-Type' => 'text/plain; charset=utf-8'], 'Metoda není povolena.'];
        if ($this->blocked($ip)) return [429, $base + ['Content-Type' => 'text/html; charset=utf-8', 'Retry-After' => '3600'], self::page('Příliš mnoho pokusů', 'Zkuste to prosím později.')];
        if (!preg_match('~^/([A-Za-z0-9_-]{32})(/.*)?$~', $path, $m)) return $this->notFound($base, $ip);
        $hit = $this->s->deliveryForToken($m[1]);
        if (!$hit) return $this->notFound($base, $ip);
        if (!isset($m[2]) || $m[2] === '') return [301, $base + ['Location' => '/' . $m[1] . '/'], ''];
        if (TsDesignStudio::isUpload($hit['project'])) return (new TsStudioClientPage($this->s, $this->c))->handle($method, $m[1], $m[2], $hit, $base, $post, $h, $query);
        $file = ltrim(rawurldecode($m[2]), '/'); if ($file === '' || str_ends_with($file, '/')) $file .= 'index.html';
        if (strpos($file, '..') !== false || !preg_match('~^[a-z0-9][a-z0-9._/-]{0,160}$~i', $file)) return [404, $base + ['Content-Type' => 'text/html; charset=utf-8'], self::page('Soubor nenalezen', '')];
        $t = $hit['template']; $files = $hit['version']['files'];
        if (isset($files[$file])) $src = $files[$file];
        elseif (!$t->isText($file) && in_array($file, $t->files(), true)) $src = $t->raw($file);
        else return [404, $base + ['Content-Type' => 'text/html; charset=utf-8'], self::page('Soubor nenalezen', '')];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)); $type = self::MIME[$ext] ?? 'application/octet-stream';
        if ($ext === 'html') {
            if ($file === 'index.html') $this->s->touchDelivery($hit['delivery']);
            $src = $this->banner($src, $hit['delivery']);
        }
        $h = $base + ['Content-Type' => $type, 'Content-Length' => (string) strlen($src)];
        if ($type === 'application/octet-stream') $h['Content-Disposition'] = 'attachment';
        return [200, $h, $src];
    }

    /* úzký pruh nad návrhem (vložený bránou, ne do souborů verze) */
    private function banner(string $html, array $d): string
    {
        $exp = date('j. n. Y', strtotime($d['expires_at']));
        $txt = htmlspecialchars('Návrh webu od ' . $this->c['brand'] . ' · neveřejný odkaz platí do ' . $exp . ($this->c['contact'] !== '' ? ' · ' . $this->c['contact'] : ''), ENT_QUOTES, 'UTF-8');
        $bar = '<div style="position:relative;z-index:2147483647;margin:0;padding:6px 12px;background:#111;color:#f4f4f1;font:13px/1.4 system-ui,-apple-system,Segoe UI,sans-serif;text-align:center" role="note">' . $txt . '</div>';
        return preg_match('~<body\b[^>]*>~i', $html) ? preg_replace('~<body\b[^>]*>~i', '$0' . $bar, $html, 1) : $bar . $html;
    }
    private function notFound(array $base, string $ip): array
    {
        $this->fail($ip);
        return [404, $base + ['Content-Type' => 'text/html; charset=utf-8'], self::page('Odkaz neplatí', 'Tento odkaz neexistuje, vypršel nebo byl zrušen. Pro nový odkaz kontaktujte prosím toho, kdo vám ho poslal.')];
    }
    private function failFile(string $ip): string { return rtrim((string) $this->c['failDir'], '/') . '/' . hash('sha256', $ip . '|' . gmdate('Y-m-d-H')) . '.cnt'; }
    private function blocked(string $ip): bool
    {
        if ($this->c['failDir'] === '' || $ip === '') return false;
        $f = $this->failFile($ip); return is_file($f) && (int) file_get_contents($f) >= (int) $this->c['maxFails'];
    }
    private function fail(string $ip): void
    {
        if ($this->c['failDir'] === '' || $ip === '') return;
        $dir = rtrim((string) $this->c['failDir'], '/'); if (!is_dir($dir)) @mkdir($dir, 0700, true);
        foreach (glob($dir . '/*.cnt') ?: [] as $old) if (filemtime($old) < time() - 7200) @unlink($old);
        $f = $this->failFile($ip); $n = is_file($f) ? (int) file_get_contents($f) : 0; @file_put_contents($f, (string) ($n + 1), LOCK_EX);
    }
    public static function page(string $title, string $text): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); $x = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . $t . '</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f4f1;color:#141416;font:16px/1.6 system-ui,-apple-system,Segoe UI,sans-serif}main{max-width:32rem;padding:24px}h1{font-size:24px;margin:0 0 8px}@media (prefers-color-scheme:dark){body{background:#111113;color:#f0f0ec}}</style>'
            . '</head><body><main><h1>' . $t . '</h1><p>' . $x . '</p></main></body></html>';
    }
}
