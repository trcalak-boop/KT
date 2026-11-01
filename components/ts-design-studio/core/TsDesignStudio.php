<?php
/* ==========================================================================
   ts-design-studio – AI Design Studio (jen administrace): projekty webů ze šablon, verze, kontroly, rozdíly, náhled, export.
   Každá změna (vykreslení z obsahu a tokenů, úprava souborů, AI úprava) vzniká jako NOVÁ verze ve stavu „candidate“;
   přijmout ji jde jen bez chyb kontrol. Návrat k verzi = nová verze se soubory staré. Nic se nenasazuje ani neodesílá.
   Oprávnění: 'can' => fn(int $uid, string $akce, ?array $projekt): bool (akce view|edit|accept|export|status); výchozí = vše.
   ========================================================================== */
final class TsDesignStudio
{
    const SOURCES = ['new', 'redesign', 'crm'];
    const STATUS = ['draft', 'in_review', 'approved_internal', 'archived'];
    private array $c; private TsStudioStore $store; private array $tpl = [];

    public function __construct(array $c)
    {
        $this->c = $c + ['templatesDir' => __DIR__ . '/../templates', 'can' => null];
        $this->store = $c['store'];
    }
    public function store(): TsStudioStore { return $this->store; }
    private function can(int $uid, string $act, ?array $p = null): void
    {
        if (is_callable($this->c['can']) && !($this->c['can'])($uid, $act, $p)) throw new TsStudioDenied($act);
    }
    public function templates(): array
    {
        if (!$this->tpl) $this->tpl = TsStudioTemplate::all($this->c['templatesDir']);
        return $this->tpl;
    }
    public function template(string $id): TsStudioTemplate
    {
        $t = $this->templates()[$id] ?? null; if (!$t) throw new InvalidArgumentException('neznámá šablona ' . $id);
        return $t;
    }
    private function project(int $pid, int $uid, string $act): array
    {
        $p = $this->store->project($pid); if (!$p) throw new InvalidArgumentException('projekt neexistuje');
        $this->can($uid, $act, $p); return $p;
    }

    /* ---------- projekty ---------- */
    /* $p = { name, source: new|redesign|crm, template, lead_ref?, audit_ref?, brief?{} }, obsah a tokeny volitelné (jinak výchozí ze šablony) */
    public function createProject(array $p, int $uid, array $content = [], array $tokens = []): array
    {
        $this->can($uid, 'edit');
        $name = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($p['name'] ?? '')), 0, 160));
        if ($name === '') throw new InvalidArgumentException('chybí název projektu');
        if (!in_array($p['source'] ?? '', self::SOURCES, true)) throw new InvalidArgumentException('zdroj projektu');
        $t = $this->template((string) ($p['template'] ?? ''));
        $pid = $this->store->createProject(['name' => $name, 'source' => $p['source'], 'template' => $t->id(), 'lead_ref' => mb_substr((string) ($p['lead_ref'] ?? ''), 0, 64),
            'audit_ref' => mb_substr((string) ($p['audit_ref'] ?? ''), 0, 64), 'owner_uid' => $uid, 'brief' => (array) ($p['brief'] ?? [])]);
        $this->store->log($pid, 0, $uid, 'project.create', $p['source'] . ' · ' . $t->id());
        $v = $this->renderVersion($pid, $uid, $content + $t->t['content'], $tokens, 'Výchozí verze ze šablony ' . $t->t['name'], 'system');
        if (!TsStudioChecks::errors($v['checks'])) $this->accept($pid, $v['num'], $uid);
        return ['id' => $pid, 'version' => $v['num'], 'checks' => $v['checks']];
    }
    public function projects(int $uid): array { $this->can($uid, 'view'); return $this->store->projects(); }
    public function get(int $pid, int $uid): array
    {
        $p = $this->project($pid, $uid, 'view');
        return ['project' => $p, 'versions' => $this->store->versions($pid), 'log' => $this->store->logOf($pid, 50)];
    }
    public function setStatus(int $pid, string $status, int $uid): void
    {
        $p = $this->project($pid, $uid, 'status');
        if (!in_array($status, self::STATUS, true)) throw new InvalidArgumentException('stav');
        $this->store->setProject($pid, ['status' => $status]); $this->store->log($pid, (int) $p['current_num'], $uid, 'status', $p['status'] . ' → ' . $status);
    }

    /* ---------- verze ---------- */
    /* nová verze vykreslená ze šablony (obsah + tokeny); vrací [num, checks, errors(obsahu/tokenů)] */
    public function renderVersion(int $pid, int $uid, array $content, array $tokens, string $message, string $authorKind = 'human'): array
    {
        $p = $this->project($pid, $uid, 'edit'); $t = $this->template($p['template']);
        [$c, $e1] = $t->cleanContent($content); [$tk, $e2] = $t->cleanTokens($tokens);
        $files = $t->render($c, $tk); $checks = TsStudioChecks::run($files, $t, $tk);
        $num = $this->store->addVersion($pid, ['parent' => (int) $p['current_num'], 'kind' => 'render', 'uid' => $uid, 'author_kind' => $authorKind, 'message' => $message,
            'files' => $files, 'content' => $c, 'tokens' => $tk, 'checks' => $checks]);
        $this->store->log($pid, $num, $uid, 'version.render', $message);
        return ['num' => $num, 'checks' => $checks, 'errors' => array_merge($e1, $e2)];
    }
    /* nová verze z úpravy souborů aktuální (nebo zadané) verze: [cesta => nový obsah | null = smazat] */
    public function patchVersion(int $pid, int $uid, array $changes, string $message, string $authorKind = 'human', ?int $base = null): array
    {
        $p = $this->project($pid, $uid, 'edit'); $t = $this->template($p['template']);
        $b = $this->store->version($pid, $base ?? (int) $p['current_num']); if (!$b) throw new InvalidArgumentException('výchozí verze neexistuje');
        $files = $b['files'];
        foreach ($changes as $path => $src) {
            $path = (string) $path;
            if (!preg_match('~^[a-z0-9][a-z0-9._/-]{0,120}$~i', $path) || strpos($path, '..') !== false || !$t->isText($path)) throw new InvalidArgumentException('nepovolený soubor ' . $path);
            if ($src === null) { unset($files[$path]); continue; }
            $files[$path] = str_replace("\r\n", "\n", (string) $src);
        }
        if ($files === $b['files']) throw new InvalidArgumentException('žádná změna');
        $checks = TsStudioChecks::run($files, $t, $b['tokens']);
        $num = $this->store->addVersion($pid, ['parent' => (int) $b['num'], 'kind' => 'patch', 'uid' => $uid, 'author_kind' => $authorKind, 'message' => $message,
            'files' => $files, 'content' => $b['content'], 'tokens' => $b['tokens'], 'checks' => $checks]);
        $this->store->log($pid, $num, $uid, 'version.patch', $message . ' (' . implode(', ', array_keys($changes)) . ')');
        return ['num' => $num, 'checks' => $checks];
    }
    public function accept(int $pid, int $num, int $uid): void
    {
        $p = $this->project($pid, $uid, 'accept'); $v = $this->store->version($pid, $num);
        if (!$v) throw new InvalidArgumentException('verze neexistuje');
        if ($v['status'] === 'rejected') throw new InvalidArgumentException('zamítnutou verzi nejde přijmout');
        if ($e = TsStudioChecks::errors($v['checks'])) throw new TsStudioChecksFailed($e);
        $this->store->tx(function () use ($pid, $num) { $this->store->setVersionStatus($pid, $num, 'accepted'); $this->store->setProject($pid, ['current_num' => $num]); });
        $this->store->log($pid, $num, $uid, 'version.accept', 'z verze ' . $p['current_num']);
    }
    public function reject(int $pid, int $num, int $uid): void
    {
        $this->project($pid, $uid, 'edit'); $v = $this->store->version($pid, $num);
        if (!$v || $v['status'] !== 'candidate') throw new InvalidArgumentException('zamítnout jde jen návrh verze');
        $this->store->setVersionStatus($pid, $num, 'rejected'); $this->store->log($pid, $num, $uid, 'version.reject');
    }
    /* návrat: nová verze se soubory zvolené verze (historie se nepřepisuje) */
    public function revert(int $pid, int $num, int $uid): array
    {
        $p = $this->project($pid, $uid, 'edit'); $t = $this->template($p['template']); $v = $this->store->version($pid, $num);
        if (!$v) throw new InvalidArgumentException('verze neexistuje');
        $checks = TsStudioChecks::run($v['files'], $t, $v['tokens']);
        $new = $this->store->addVersion($pid, ['parent' => (int) $p['current_num'], 'kind' => 'revert', 'uid' => $uid, 'author_kind' => 'human', 'message' => 'Návrat k verzi ' . $num,
            'files' => $v['files'], 'content' => $v['content'], 'tokens' => $v['tokens'], 'checks' => $checks]);
        $this->store->log($pid, $new, $uid, 'version.revert', 'z verze ' . $num);
        return ['num' => $new, 'checks' => $checks];
    }
    public function version(int $pid, int $num, int $uid): array
    {
        $this->project($pid, $uid, 'view'); $v = $this->store->version($pid, $num); if (!$v) throw new InvalidArgumentException('verze neexistuje');
        return $v;
    }
    /* rozdíl dvou verzí: souhrn souborů + unified diff */
    public function diff(int $pid, int $a, int $b, int $uid): array
    {
        $this->project($pid, $uid, 'view'); $va = $this->store->version($pid, $a); $vb = $this->store->version($pid, $b);
        if (!$va || !$vb) throw new InvalidArgumentException('verze neexistuje');
        $sum = TsStudioDiff::files($va['files'], $vb['files']); $txt = [];
        foreach ($sum as $path => $s) $txt[$path] = TsStudioDiff::unified($va['files'][$path] ?? '', $vb['files'][$path] ?? '', $path);
        return ['files' => $sum, 'diff' => $txt];
    }

    /* ---------- export a náhled ---------- */
    /* všechny soubory verze včetně binárních ze šablony → [cesta => bajty] */
    public function allFiles(int $pid, int $num, int $uid, string $act = 'export'): array
    {
        $p = $this->project($pid, $uid, $act); $t = $this->template($p['template']); $v = $this->store->version($pid, $num);
        if (!$v) throw new InvalidArgumentException('verze neexistuje');
        $out = $v['files'];
        foreach ($t->files() as $f) if (!$t->isText($f) && !isset($out[$f])) $out[$f] = $t->raw($f);
        ksort($out); return $out;
    }
    public function exportZip(int $pid, int $num, int $uid): string
    {
        $files = $this->allFiles($pid, $num, $uid, 'export');
        $this->store->log($pid, $num, $uid, 'export.zip', count($files) . ' souborů');
        return TsStudioZip::build($files);
    }
    /* jedna stránka pro <iframe sandbox srcdoc>: styly, skripty a písma vložené přímo, CSP bez síťových požadavků, odkazy mezi stránkami jako data-page */
    public function preview(int $pid, int $num, int $uid, string $page = 'index.html'): string
    {
        $files = $this->allFiles($pid, $num, $uid, 'view');
        if (!isset($files[$page]) || !preg_match('/\.html$/', $page)) throw new InvalidArgumentException('stránka neexistuje');
        $html = $files[$page];
        $dataUri = function (string $path) use ($files): string {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'gif' => 'image/gif'][$ext] ?? 'application/octet-stream';
            return 'data:' . $mime . ';base64,' . base64_encode($files[$path]);
        };
        $html = preg_replace_callback('~<link\b[^>]*rel="stylesheet"[^>]*href="([^"]+)"[^>]*>~i', function ($m) use ($files, $dataUri) {
            $p = ltrim(preg_replace('~[?#].*$~', '', $m[1]), './'); if (!isset($files[$p])) return '';
            $dir = dirname($p) === '.' ? '' : dirname($p) . '/';
            $css = preg_replace_callback('~url\(\s*["\']?([^"\')]+)["\']?\s*\)~i', function ($u) use ($files, $dir, $dataUri) {
                if (preg_match('~^(data:|https?:|#)~i', $u[1])) return $u[0];
                $q = self::normPath($dir . preg_replace('~[?#].*$~', '', $u[1])); return isset($files[$q]) ? 'url("' . $dataUri($q) . '")' : 'url("")';
            }, $files[$p]);
            return '<style>' . str_replace('</style', '<\/style', $css) . '</style>';
        }, $html);
        $html = preg_replace_callback('~<script\b([^>]*)\ssrc="([^"]+)"([^>]*)>\s*</script>~i', function ($m) use ($files) {
            $p = ltrim(preg_replace('~[?#].*$~', '', $m[2]), './'); return isset($files[$p]) ? '<script>' . str_replace('</script', '<\/script', $files[$p]) . '</script>' : '';
        }, $html);
        $html = preg_replace_callback('~(<img\b[^>]*\ssrc=")([^"]+)(")~i', fn ($m) => isset($files[ltrim($m[2], './')]) ? $m[1] . $dataUri(ltrim($m[2], './')) . $m[3] : $m[0], $html);
        $html = preg_replace('~(<a\b[^>]*\s)href="([\w./-]+\.html)(#[\w-]*)?"~i', '$1href="#" data-page="$2"', $html);
        $csp = "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'none'; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src data: https://fonts.gstatic.com; img-src data:; script-src 'unsafe-inline'; form-action 'none'; base-uri 'none'\">";
        return preg_replace('~<head(\s[^>]*)?>~i', '$0' . $csp, $html, 1);
    }
    private static function normPath(string $p): string
    {
        $out = []; foreach (explode('/', $p) as $seg) { if ($seg === '' || $seg === '.') continue; if ($seg === '..') array_pop($out); else $out[] = $seg; }
        return implode('/', $out);
    }
}

final class TsStudioDenied extends RuntimeException { public function __construct(string $act) { parent::__construct('nedostatečné oprávnění: ' . $act); } }
final class TsStudioChecksFailed extends RuntimeException
{
    public array $errors;
    public function __construct(array $e) { parent::__construct('verze má chyby kontrol (' . count($e) . ')'); $this->errors = $e; }
}
