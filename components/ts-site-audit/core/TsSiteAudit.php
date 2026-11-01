<?php
/* ==========================================================================
   ts-site-audit – audit existujícího webu: měření (Lighthouse přes Google PageSpeed), snímky, vizuální hodnocení AI.
   POST ?r=audit         { url, lang?, ref? }          → { ok, id, status, cached? }   (id = tajný token úlohy)
   GET  ?r=audit-status&id=…                           → { ok, status, progress, report? , error? }
   GET  ?r=audit-shot&id=…&d=mobile|desktop[&full=1]   → obrázek (jen pro držitele tokenu)
   Jen administrace ('source' => 'admin'): GET ?r=audit-list → { ok, items }, u audit-status a audit-shot místo id i job=<číslo úlohy>.
   'access' => ['cookie' => …, 'hash' => sha256(klíč)] – odpoví jen prohlížeči s touto cookie (neveřejný staging).

   Hosting bez procesů na pozadí: úloha běží PO KROCÍCH (mobil → desktop → hodnocení → report). Každý dotaz na stav
   po odeslání odpovědi posune úlohu o jeden krok (zámek proti souběhu), žádný požadavek netrvá déle než jedno měření.
   Report odděluje FAKTA (měření se zdrojem a datem) od DOPORUČENÍ AI (subjektivní, bez čísel – čísla se zahodí).
   Stejná adresa: hotový výsledek mladší než cacheHours se znovu neměří (s datem měření), běžící audit se sdílí.
   ========================================================================== */
final class TsSiteAudit
{
    const STEPS = ['queued' => 5, 'desktop' => 40, 'vision' => 75, 'done' => 100, 'error' => 100];
    private array $c; private TsAuditStore $store; private ?TsAuditMeter $meter; private ?TsAuditVision $vision;

    public function __construct(array $c)
    {
        $this->c = $c + ['enabled' => true, 'source' => 'public', 'defaultLang' => 'cs', 'shotDir' => '', 'salt' => 'ts-audit', 'cacheHours' => 24,
            'retentionDays' => 30, 'visionPerDay' => 30, 'maxAttempts' => 2, 'lockSeconds' => 150, 'allowedOrigins' => [], 'resolve' => null, 'processAfterResponse' => true];
        $this->c['limits'] = ($c['limits'] ?? []) + ['publicPerIpDay' => 2, 'publicPerDay' => 20, 'adminPerDay' => 100];
        $this->store = $c['store']; $this->meter = $c['meter'] ?? null; $this->vision = $c['vision'] ?? null;
    }
    public function store(): TsAuditStore { return $this->store; }
    public function enabled(): bool { return (bool) $this->c['enabled'] && $this->meter !== null; }

    /* ---------- HTTP ---------- */
    public function serve(): void
    {
        $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $raw = $m === 'POST' ? (string) file_get_contents('php://input', false, null, 0, 4000) : '';
        $h = []; foreach ($_SERVER as $k => $v) if (str_starts_with($k, 'HTTP_')) $h[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
        if (isset($_SERVER['CONTENT_TYPE'])) $h['content-type'] = $_SERVER['CONTENT_TYPE'];
        [$code, $out] = $this->handle($m, (string) ($_GET['r'] ?? ''), $raw, $_GET, $h, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        http_response_code($code);
        header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin'); header('Cache-Control: private, no-store');
        if (isset($out['image'])) { header('Content-Type: ' . $out['type']); header('Content-Length: ' . strlen($out['image'])); echo $out['image']; return; }
        $next = $out['_advance'] ?? null; unset($out['_advance']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($next !== null && $this->c['processAfterResponse']) {           /* další krok až po odeslání odpovědi */
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            ignore_user_abort(true); @set_time_limit((int) $this->c['lockSeconds'] + 20);
            try { $this->advance($next); } catch (Throwable $e) { error_log('ts-audit: ' . get_class($e) . ': ' . $e->getMessage()); }
        }
    }

    /* → [HTTP kód, odpověď]; '_advance' = token úlohy, kterou má serve() po odpovědi posunout */
    public function handle(string $method, string $route, string $raw, array $get, array $headers, string $ip): array
    {
        try {
            if (!$this->accessOk($headers)) return [403, ['ok' => false, 'error' => 'access']];
            if (!$this->originOk($headers)) return [403, ['ok' => false, 'error' => 'origin']];
            $admin = $this->c['source'] === 'admin';
            if ($admin && $route === 'audit-list' && $method === 'GET') return [200, ['ok' => true, 'items' => $this->store->list(50)]];
            if ($admin && !empty($get['job']) && in_array($route, ['audit-status', 'audit-shot'], true) && $method === 'GET') {
                $j = $this->store->byId((int) $get['job']);
                if (!$j) return [404, ['ok' => false, 'error' => 'id']];
                if ($route === 'audit-shot') { $img = $this->shotFor($j, (string) ($get['d'] ?? 'mobile'), !empty($get['full'])); return $img ? [200, $img] : [404, ['ok' => false, 'error' => 'shot']]; }
                return [200, $this->statusOf($j)];
            }
            if ($route === 'audit' && $method === 'POST') {
                if (stripos($headers['content-type'] ?? '', 'application/json') !== 0) return [415, ['ok' => false, 'error' => 'type']];
                $b = json_decode($raw, true); if (!is_array($b)) return [400, ['ok' => false, 'error' => 'json']];
                $r = $this->create((string) ($b['url'] ?? ''), ['ip' => $ip, 'lang' => (string) ($b['lang'] ?? ''), 'ref' => (string) ($b['ref'] ?? ''), 'force' => $admin && !empty($b['force'])]);
                $code = $r['ok'] ? 202 : ($r['error'] === 'limit' ? 429 : ($r['error'] === 'disabled' ? 503 : 422));
                if ($r['ok'] && $r['status'] !== 'done') $r['_advance'] = $r['id'];
                return [$code, $r];
            }
            if ($route === 'audit-status' && $method === 'GET') {
                $s = $this->status((string) ($get['id'] ?? ''));
                if (!$s['ok']) return [404, $s];
                if (!in_array($s['status'], ['done', 'error'], true)) $s['_advance'] = (string) $get['id'];
                return [200, $s];
            }
            if ($route === 'audit-shot' && $method === 'GET') {
                $img = $this->screenshot((string) ($get['id'] ?? ''), (string) ($get['d'] ?? 'mobile'), !empty($get['full']));
                return $img ? [200, $img] : [404, ['ok' => false, 'error' => 'shot']];
            }
            return [404, ['ok' => false, 'error' => 'route']];
        } catch (Throwable $e) {
            error_log('ts-audit: ' . get_class($e) . ': ' . $e->getMessage());
            return [500, ['ok' => false, 'error' => 'server']];
        }
    }
    private function accessOk(array $h): bool
    {
        $a = $this->c['access'] ?? null; if (!$a) return true;
        $v = '';
        foreach (explode(';', (string) ($h['cookie'] ?? '')) as $part) { [$k, $x] = array_pad(explode('=', trim($part), 2), 2, ''); if ($k === $a['cookie']) $v = urldecode($x); }
        return $v !== '' && hash_equals((string) $a['hash'], hash('sha256', $v));
    }
    private function originOk(array $h): bool
    {
        $o = $h['origin'] ?? ''; if ($o === '') return true;
        if ($this->c['allowedOrigins']) return in_array(rtrim($o, '/'), $this->c['allowedOrigins'], true);
        return preg_replace('#^https?://#', '', rtrim($o, '/')) === ($h['host'] ?? '');
    }
    private function lang(string $l): string { $l = strtolower(substr(trim($l), 0, 2)); return preg_match('/^[a-z]{2}$/', $l) ? $l : $this->c['defaultLang']; }

    /* ---------- založení ---------- */
    public function create(string $rawUrl, array $ctx = []): array
    {
        if (!$this->enabled()) return ['ok' => false, 'error' => 'disabled'];
        $u = TsAuditUrl::check($rawUrl, $this->c['resolve']);
        if (!$u['ok']) return ['ok' => false, 'error' => 'url', 'reason' => $u['error']];
        $lang = $this->lang((string) ($ctx['lang'] ?? '')); $iph = substr(hash('sha256', $this->c['salt'] . '|' . ($ctx['ip'] ?? '')), 0, 32);
        $L = $this->c['limits']; $admin = $this->c['source'] === 'admin';
        if (!$admin && !$this->store->hit('ip:' . $iph, (int) $L['publicPerIpDay'], 86400)) return ['ok' => false, 'error' => 'limit'];
        if ($this->store->hit('cleanup', 1, 86400)) $this->cleanup();
        $token = bin2hex(random_bytes(24));
        $base = ['token_hash' => hash('sha256', $token), 'url' => $u['url'], 'lang' => $lang, 'source' => $this->c['source'], 'ip_hash' => $iph, 'ref' => mb_substr((string) ($ctx['ref'] ?? ''), 0, 64)];
        if (empty($ctx['force']) && $done = $this->store->recentDone($u['url'], $lang, (int) $this->c['cacheHours'])) {
            $this->store->create($base + ['status' => 'done', 'data' => ['report' => $done['data']['report'], 'shotsOf' => (int) ($done['data']['shotsOf'] ?? $done['id']), 'cached' => true]]);
            return ['ok' => true, 'id' => $token, 'status' => 'done', 'cached' => true];
        }
        if ($run = $this->store->running($u['url'], $lang, 15)) {
            $this->store->create($base + ['status' => 'queued', 'data' => ['copyOf' => (int) $run['id']]]);
            return ['ok' => true, 'id' => $token, 'status' => 'queued', 'shared' => true];
        }
        if (!$this->store->hit($admin ? 'admin-day' : 'public-day', (int) ($admin ? $L['adminPerDay'] : $L['publicPerDay']), 86400)) return ['ok' => false, 'error' => 'limit'];
        $this->store->create($base + ['status' => 'queued', 'data' => []]);
        return ['ok' => true, 'id' => $token, 'status' => 'queued'];
    }

    /* ---------- stav ---------- */
    public function status(string $token): array
    {
        $j = preg_match('/^[a-f0-9]{48}$/', $token) ? $this->store->byToken($token) : null;
        if (!$j) return ['ok' => false, 'error' => 'id'];
        return $this->statusOf($j);
    }
    private function statusOf(array $j): array
    {
        $out = ['ok' => true, 'status' => $j['status'], 'progress' => self::STEPS[$j['status']] ?? 5, 'url' => $j['url']];
        if ($j['status'] === 'done') { $out['report'] = $j['data']['report'] ?? null; if (!empty($j['data']['cached'])) $out['cached'] = true; }
        if ($j['status'] === 'error') $out['error'] = $j['error'];
        return $out;
    }

    /* ---------- jeden krok úlohy ---------- */
    public function advance(string $token): void
    {
        $j = $this->store->byToken($token);
        if ($j) $this->step($j);
    }
    private function step(array $j): void
    {
        if (in_array($j['status'], ['done', 'error'], true)) return;
        $d = $j['data'];
        if (isset($d['copyOf'])) {                                     /* sdílený běžící audit stejné adresy */
            $src = $this->store->byId((int) $d['copyOf']);
            if (!$src) { $this->store->save((int) $j['id'], 'error', $d, 'lost'); return; }
            if (!in_array($src['status'], ['done', 'error'], true)) { $this->step($src); $src = $this->store->byId((int) $d['copyOf']); }
            if ($src['status'] === 'done') $this->store->save((int) $j['id'], 'done', ['report' => $src['data']['report'], 'shotsOf' => (int) $src['id']]);
            elseif ($src['status'] === 'error') $this->store->save((int) $j['id'], 'error', $d, $src['error']);
            else $this->store->save((int) $j['id'], $src['status'], $d);
            return;
        }
        if (!$this->store->lock((int) $j['id'], (int) $this->c['lockSeconds'])) return;
        $id = (int) $j['id'];
        try {
            if ($j['status'] === 'queued' || $j['status'] === 'desktop') {
                $dev = $j['status'] === 'queued' ? 'mobile' : 'desktop';
                $r = $this->meter->measure($j['url'], $dev, $j['lang']);
                $d[$dev] = $this->keepShots($id, $dev, $r);
                $this->store->save($id, $dev === 'mobile' ? 'desktop' : 'vision', $d);
                return;
            }
            if ($j['status'] === 'vision') {
                $d['ai'] = $this->review($id, $j, $d);
                $d['report'] = $this->report($j, $d);
                $this->store->save($id, 'done', ['report' => $d['report'], 'shotsOf' => $id]);
            }
        } catch (TsAuditError $e) {
            error_log('ts-audit: měření ' . $e->kind . ': ' . preg_replace('/key=[^&\s]+/', 'key=…', $e->getMessage()));
            $retry = in_array($e->kind, ['timeout', 'connection'], true) && (int) $j['attempts'] + 1 < (int) $this->c['maxAttempts'];
            if ($retry) $this->store->save($id, $j['status'], $d, '', true);
            else $this->store->save($id, 'error', $d, $e->kind);
        }
    }
    private function keepShots(int $id, string $dev, array $r): array
    {
        foreach (['screenshot' => '', 'fullpage' => '-full'] as $k => $suf) {
            $b = $r[$k] ?? null; unset($r[$k]);
            $r['has' . ucfirst($k)] = false;
            if ($b && $this->c['shotDir'] !== '') {
                if (!is_dir($this->c['shotDir'])) @mkdir($this->c['shotDir'], 0700, true);
                $r['has' . ucfirst($k)] = @file_put_contents($this->shotPath($id, $dev, $suf !== '', self::imgType($b)), $b, LOCK_EX) !== false;
                $r[$k . 'Type'] = self::imgType($b);
            }
        }
        return $r;
    }
    private function shotPath(int $id, string $dev, bool $full, string $type): string
    {
        return rtrim($this->c['shotDir'], '/') . '/' . $id . '-' . $dev . ($full ? '-full' : '') . '.' . (explode('/', $type)[1] ?? 'jpg');
    }
    public static function imgType(string $b): string { return substr($b, 0, 4) === 'RIFF' ? 'image/webp' : (substr($b, 0, 4) === "\x89PNG" ? 'image/png' : 'image/jpeg'); }

    private function review(int $id, array $j, array $d): array
    {
        if (!$this->vision) return ['ok' => false, 'reason' => 'off'];
        $dev = !empty($d['desktop']['hasScreenshot']) ? 'desktop' : (!empty($d['mobile']['hasScreenshot']) ? 'mobile' : null);
        if (!$dev) return ['ok' => false, 'reason' => 'noshot'];
        if (!$this->store->hit('vision-day', (int) $this->c['visionPerDay'], 86400)) return ['ok' => false, 'reason' => 'quota'];
        $img = (string) @file_get_contents($this->shotPath($id, $dev, false, $d[$dev]['screenshotType'] ?? 'image/jpeg'));
        try {
            $r = $this->vision->review($img, $j['lang'], ['host' => parse_url($j['url'], PHP_URL_HOST), 'device' => $dev,
                'issues' => array_column(array_slice($d[$dev]['issues'] ?? [], 0, 6), 'title')]);
            $out = ['ok' => (bool) $r['items'], 'items' => $r['items'], 'device' => $dev, 'model' => $this->vision->model(), 'usage' => $r['usage'] ?? [], 'reason' => $r['items'] ? '' : 'empty'];
            if (!$r['items']) { $out['raw'] = (string) ($r['raw'] ?? ''); $out['finish'] = (string) ($r['finish'] ?? ''); }     /* diagnostika pro administraci */
            return $out;
        } catch (TsAuditError $e) {
            error_log('ts-audit: hodnocení ' . $e->kind . ': ' . $e->getMessage());
            return ['ok' => false, 'reason' => 'error_' . $e->kind, 'raw' => mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /* ---------- report: fakta × doporučení AI ---------- */
    private function report(array $j, array $d): array
    {
        $dev = []; $recs = []; $seen = [];
        foreach (['mobile', 'desktop'] as $k) {
            if (empty($d[$k])) continue;
            $m = $d[$k];
            $dev[$k] = ['scores' => $m['scores'], 'metrics' => $m['metrics'], 'issues' => $m['issues'], 'screenshot' => (bool) ($m['hasScreenshot'] ?? false), 'fullpage' => (bool) ($m['hasFullpage'] ?? false)];
            foreach ($m['issues'] as $i) {
                if (isset($seen[$i['id']])) { $recs[$seen[$i['id']]]['devices'][] = $k; continue; }
                $seen[$i['id']] = count($recs);
                $recs[] = ['basis' => 'measurement', 'category' => $i['category'], 'text' => $i['title'], 'detail' => $i['display'], 'devices' => [$k]];
            }
        }
        $ai = $d['ai'] ?? ['ok' => false];
        foreach ($ai['items'] ?? [] as $it) $recs[] = ['basis' => 'ai', 'area' => $it['area'], 'text' => $it['text'], 'priority' => $it['priority'], 'devices' => [$ai['device']]];
        $m0 = $d['mobile'] ?? $d['desktop'] ?? [];
        return ['url' => $j['url'], 'finalUrl' => $m0['finalUrl'] ?? '', 'lang' => $j['lang'], 'measuredAt' => $m0['fetchTime'] ?? '',
            'source' => ['name' => 'Google PageSpeed Insights (Lighthouse)', 'version' => $m0['lighthouse'] ?? '', 'kind' => 'lab'],
            'devices' => $dev, 'recommendations' => $recs,
            'ai' => ['ok' => (bool) ($ai['ok'] ?? false), 'model' => $ai['model'] ?? '', 'reason' => $ai['reason'] ?? '', 'device' => $ai['device'] ?? '', 'usage' => $ai['usage'] ?? []]
                + ($this->c['source'] === 'admin' && !empty($ai['raw']) ? ['raw' => $ai['raw'], 'finish' => $ai['finish'] ?? ''] : []),
            'warnings' => array_values(array_unique(array_merge($d['mobile']['warnings'] ?? [], $d['desktop']['warnings'] ?? []))),
            'notes' => ['lab', 'single', 'aiSubjective']];
    }

    public function screenshot(string $token, string $dev, bool $full): ?array
    {
        $j = preg_match('/^[a-f0-9]{48}$/', $token) ? $this->store->byToken($token) : null;
        return $j ? $this->shotFor($j, $dev, $full) : null;
    }
    private function shotFor(array $j, string $dev, bool $full): ?array
    {
        if ($j['status'] !== 'done' || !in_array($dev, ['mobile', 'desktop'], true) || $this->c['shotDir'] === '') return null;
        $sid = (int) ($j['data']['shotsOf'] ?? $j['id']);
        foreach (['image/jpeg', 'image/webp', 'image/png'] as $t) {
            $p = $this->shotPath($sid, $dev, $full, $t);
            if (is_file($p)) return ['image' => (string) file_get_contents($p), 'type' => $t];
        }
        return null;
    }

    public function cleanup(): int
    {
        $ids = $this->store->cleanup((int) $this->c['retentionDays']);
        if ($this->c['shotDir'] !== '') foreach ($ids as $id) foreach (glob(rtrim($this->c['shotDir'], '/') . '/' . $id . '-*') ?: [] as $f) @unlink($f);
        return count($ids);
    }

    /* ---------- vizuální hodnocení: pokyny a kontrola výstupu ---------- */
    public static function visionPrompt(string $lang): string
    {
        return "Jsi zkušený webdesignér a UX konzultant. Posuzuješ JEN to, co je vidět na snímku obrazovky webu.\n"
            . "Napiš 3 až 6 konkrétních, proveditelných doporučení (vizuální hierarchie, čitelnost a kontrast, výzva k akci, důvěryhodnost, rozvržení, použitelnost).\n"
            . "Pravidla: žádná čísla, procenta, časy ani skóre; neodhaduj rychlost, výkon ani SEO; nehodnoť nic, co na snímku není vidět; nepiš obecné fráze.\n"
            . "Odpověz jen JSON bez dalšího textu: {\"items\":[{\"area\":\"krátký název oblasti\",\"text\":\"doporučení v 1–2 větách\",\"priority\":1}]}, priority 1 = nejdůležitější, 3 = drobnost.\n"
            . 'Jazyk textů: ' . $lang . '.';
    }
    public static function visionContext(array $c, string $lang): string
    {
        $t = 'Web: ' . ($c['host'] ?? '') . ', zařízení: ' . ($c['device'] ?? '') . '.';
        if (!empty($c['issues'])) $t .= "\nMěření už našlo (neopakuj): " . implode('; ', array_map('strval', $c['issues'])) . '.';
        return $t;
    }
    /* JSON z odpovědi modelu → nejvýš 6 položek; položky s číslicemi se zahodí (AI nesmí vymýšlet metriky) */
    public static function parseVision(string $text): array
    {
        $t = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));
        $a = strpos($t, '{'); $b = strrpos($t, '}');
        $j = $a !== false && $b > $a ? json_decode(substr($t, $a, $b - $a + 1), true) : null;
        $out = []; $seen = [];
        foreach ((array) ($j['items'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $txt = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($it['text'] ?? ''))));
            $area = trim(mb_substr(strip_tags((string) ($it['area'] ?? '')), 0, 40));
            if ($txt === '' || preg_match('/\d/u', $txt . $area) || isset($seen[mb_strtolower($txt)])) continue;
            if (mb_strlen($txt) < 20 || preg_match('/^(doporučení v|recommendation|krátký název|area name)/iu', $txt . ' ' . $area) && mb_strlen($txt) < 40) continue;   /* opsaná šablona z pokynů */
            $seen[mb_strtolower($txt)] = true;
            $out[] = ['area' => $area, 'text' => mb_substr($txt, 0, 300), 'priority' => max(1, min(3, (int) ($it['priority'] ?? 2)))];
            if (count($out) >= 6) break;
        }
        usort($out, fn ($x, $y) => $x['priority'] <=> $y['priority']);
        return $out;
    }
}
