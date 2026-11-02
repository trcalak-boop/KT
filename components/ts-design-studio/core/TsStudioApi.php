<?php
/* ==========================================================================
   ts-design-studio – HTTP API administrace Studia (JSON). Přihlášení a role řeší vstupní bod: předá číslo uživatele ('uid').
   $api = new TsStudioApi($studio, ['uid' => 1, 'jobsDir' => '/…/private/studio/data/ulohy', 'allowedOrigins' => ['https://…']]); $api->serve();
   Dlouhé dotazy na model (ai-create, ai-edit) běží jako úloha: odpověď 202 s číslem úlohy hned, práce po fastcgi_finish_request, klient se ptá ?r=job.
   Každý POST musí mít JSON a povolený původ (Origin + Sec-Fetch-Site), jinak 403/415.
   ========================================================================== */
final class TsStudioApi
{
    private TsDesignStudio $s; private array $c;

    public function __construct(TsDesignStudio $studio, array $c)
    {
        $this->s = $studio;
        /* 'role' a 'sender' (['name', 'email'] přihlášeného – podpis a Reply-To e-mailu klientovi) dodává vstupní bod */
        /* 'csrf' => fn (string $token): bool – kontrola tokenu relace (hlavička X-CSRF) u každého POST; 'enabled' => false = vypínač (503) */
        $this->c = $c + ['uid' => 0, 'role' => '', 'sender' => [], 'jobsDir' => '', 'allowedOrigins' => [], 'processAfterResponse' => true, 'jobSeconds' => 300, 'maxBody' => 1500000, 'csrf' => null, 'enabled' => true];
    }

    public function serve(): void
    {
        $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $raw = $m === 'POST' ? (string) file_get_contents('php://input', false, null, 0, (int) $this->c['maxBody'] + 1) : '';
        $h = []; foreach ($_SERVER as $k => $v) if (str_starts_with($k, 'HTTP_')) $h[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
        if (isset($_SERVER['CONTENT_TYPE'])) $h['content-type'] = $_SERVER['CONTENT_TYPE'];
        [$code, $out, $after] = $this->handle($m, (string) ($_GET['r'] ?? ''), $raw, $_GET, $h) + [2 => null];
        http_response_code($code);
        header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer'); header('Cache-Control: private, no-store'); header('X-Robots-Tag: noindex, nofollow');
        if (isset($out['zip'])) {
            header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $out['name'] . '"'); header('Content-Length: ' . strlen($out['zip'])); echo $out['zip']; return;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($after) {
            if ($this->c['processAfterResponse'] && function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            ignore_user_abort(true); @set_time_limit((int) $this->c['jobSeconds']);
            $after();
        }
    }

    /* → [HTTP kód, odpověď, volitelně práce po odeslání odpovědi] */
    public function handle(string $method, string $route, string $raw, array $get, array $headers): array
    {
        $uid = (int) $this->c['uid']; $S = $this->s;
        $pid = (int) ($get['pid'] ?? 0); $num = (int) ($get['num'] ?? 0);
        try {
            if (!$this->c['enabled']) return [503, ['ok' => false, 'error' => 'disabled']];
            if ($method === 'POST') {
                if (!$this->originOk($headers)) return [403, ['ok' => false, 'error' => 'origin']];
                if (is_callable($this->c['csrf']) && !($this->c['csrf'])((string) ($headers['x-csrf'] ?? ''))) return [403, ['ok' => false, 'error' => 'csrf']];
                if (stripos($headers['content-type'] ?? '', 'application/json') !== 0) return [415, ['ok' => false, 'error' => 'type']];
                if (strlen($raw) > (int) $this->c['maxBody']) return [413, ['ok' => false, 'error' => 'size']];
                $b = json_decode($raw, true); if (!is_array($b)) return [400, ['ok' => false, 'error' => 'json']];
                $pid = (int) ($b['pid'] ?? 0); $num = (int) ($b['num'] ?? 0);
                switch ($route) {
                    case 'create':
                        $r = $S->createProject(['name' => (string) ($b['name'] ?? ''), 'source' => (string) ($b['source'] ?? 'new'), 'template' => (string) ($b['template'] ?? '')], $uid);
                        return [201, ['ok' => true] + $r];
                    case 'ai-create':
                        $src = in_array($b['source'] ?? '', TsDesignStudio::SOURCES, true) ? $b['source'] : 'new';
                        $leadRef = mb_substr(trim((string) ($b['lead_ref'] ?? '')), 0, 64);
                        $fromCrm = $src === 'crm' && $leadRef !== '' ? $S->leadBriefFor($leadRef) : null;      /* lead přímo z CRM (jen čtení) */
                        if ($src === 'crm' && $leadRef !== '' && $fromCrm === null) return [400, ['ok' => false, 'error' => 'input', 'detail' => 'lead v CRM nenalezen']];
                        if ($fromCrm) $b['lead'] = $fromCrm['lead'] + (array) ($b['lead'] ?? []);
                        if ($fromCrm) $b['leadNotes'] = array_merge((array) ($fromCrm['notes'] ?? []), (array) ($b['leadNotes'] ?? []));
                        $brief = $src === 'crm' ? array_filter((array) ($b['brief'] ?? []), fn ($v) => $v !== '' && $v !== null) + TsStudioAi::briefFromLead((array) ($b['lead'] ?? []), (array) ($b['leadNotes'] ?? []))
                               : ($src === 'redesign' && isset($b['audit']) ? TsStudioAi::briefFromAudit((array) $b['audit'], (string) ($b['texts'] ?? ''), (array) ($b['brief'] ?? [])) : (array) ($b['brief'] ?? []));
                        $S->checkAi($uid);
                        return $this->job(fn () => $S->aiCreate($brief, $uid, ['source' => $src, 'lead_ref' => $leadRef, 'audit_ref' => (string) ($b['audit_ref'] ?? '')]));
                    case 'ai-edit':
                        $S->get($pid, $uid); $S->checkAi($uid);
                        $text = (string) ($b['text'] ?? ''); if (trim($text) === '') return [400, ['ok' => false, 'error' => 'input', 'detail' => 'prázdný pokyn']];
                        return $this->job(fn () => $S->aiEdit($pid, $uid, $text));
                    case 'render':
                        $v = $S->version($pid, (int) ($b['base'] ?? 0) ?: (int) $S->get($pid, $uid)['project']['current_num'], $uid);
                        $r = $S->renderVersion($pid, $uid, (array) ($b['content'] ?? []) + $v['content'], (array) ($b['tokens'] ?? []) + $v['tokens'], self::msg($b, 'Úprava obsahu a barev'));
                        return [201, ['ok' => true] + $r];
                    case 'patch':
                        $r = $S->patchVersion($pid, $uid, (array) ($b['files'] ?? []), self::msg($b, 'Ruční úprava kódu'), 'human', isset($b['base']) ? (int) $b['base'] : null);
                        return [201, ['ok' => true] + $r];
                    case 'accept': $S->accept($pid, $num, $uid); return [200, ['ok' => true]];
                    case 'reject': $S->reject($pid, $num, $uid); return [200, ['ok' => true]];
                    case 'revert': return [201, ['ok' => true] + $S->revert($pid, $num, $uid)];
                    case 'status': $S->setStatus($pid, (string) ($b['status'] ?? ''), $uid); return [200, ['ok' => true]];
                    case 'delivery-prepare': return [201, ['ok' => true, 'delivery' => $S->prepareDelivery($pid, $num, $uid, (int) ($b['days'] ?? 14))]];
                    case 'delivery-draft': return [200, ['ok' => true, 'delivery' => $S->draftDelivery((int) ($b['id'] ?? 0), $uid, $b)]];
                    case 'delivery-send':
                        $sd = (array) $this->c['sender'];
                        $d = $S->sendDelivery((int) ($b['id'] ?? 0), $uid, (string) ($b['confirm'] ?? ''), (string) ($b['idem'] ?? ''), (string) ($sd['email'] ?? ''), (string) ($sd['name'] ?? ''));
                        return [$d['status'] === 'failed' ? 502 : 200, ['ok' => $d['status'] !== 'failed', 'delivery' => $d] + ($d['status'] === 'failed' ? ['error' => 'mail', 'detail' => $d['error']] : [])];
                    case 'delivery-link': return [200, ['ok' => true, 'delivery' => $S->copyLink((int) ($b['id'] ?? 0), $uid)]];
                    case 'delivery-revoke': return [200, ['ok' => true, 'delivery' => $S->revokeDelivery((int) ($b['id'] ?? 0), $uid)]];
                }
                return [404, ['ok' => false, 'error' => 'route']];
            }
            if ($method !== 'GET') return [405, ['ok' => false, 'error' => 'method']];
            switch ($route) {
                case 'templates':
                    $out = []; foreach ($S->templates() as $id => $t) $out[] = ['id' => $id, 'name' => $t->t['name'], 'obor' => $t->t['obor'] ?? [], 'description' => $t->t['description'] ?? ''];
                    $role = (string) $this->c['role'];
                    return [200, ['ok' => true, 'templates' => $out, 'ai' => $S->hasAi(), 'role' => $role, 'actions' => $role !== '' ? TsStudioRoles::actions($role) : array_keys(TsStudioRoles::MATRIX), 'delivery' => $S->hasDelivery()]];
                case 'deliveries':
                    return [200, ['ok' => true, 'deliveries' => $S->deliveries($pid, $uid)]];
                case 'delivery':
                    $d = $S->deliveryDetail((int) ($get['id'] ?? 0), $uid, (string) (((array) $this->c['sender'])['name'] ?? ''));
                    return [200, ['ok' => true, 'delivery' => $d]];
                case 'list':
                    $S->purgeOldDeliveries();
                    return [200, ['ok' => true, 'projects' => $S->projects($uid), 'usage' => $S->usage()]];
                case 'project':
                    $g = $S->get($pid, $uid);
                    return [200, ['ok' => true, 'project' => $g['project'], 'versions' => $g['versions'], 'log' => $g['log'], 'messages' => $S->messages($pid, $uid), 'usage' => $S->usage()]];
                case 'version':
                    $v = $S->version($pid, $num, $uid); $t = $S->template($S->get($pid, $uid)['project']['template']);
                    $files = []; foreach ($S->allFiles($pid, $num, $uid, 'view') as $p => $src) $files[] = ['path' => $p, 'size' => strlen($src), 'text' => $t->isText($p)];
                    return [200, ['ok' => true, 'num' => $v['num'], 'parent' => $v['parent_num'], 'kind' => $v['kind'], 'status' => $v['status'], 'author' => $v['author_kind'], 'message' => $v['message'], 'created' => $v['created_at'],
                        'files' => $files, 'content' => $v['content'], 'tokens' => $v['tokens'], 'checks' => $v['checks'], 'todo' => TsStudioAi::todo($v['content']),
                        'schema' => ['slots' => $t->t['slots'], 'tokens' => $t->t['tokens']]]];
                case 'file':
                    $v = $S->version($pid, $num, $uid); $p = (string) ($get['path'] ?? '');
                    if (!isset($v['files'][$p])) return [404, ['ok' => false, 'error' => 'file']];
                    return [200, ['ok' => true, 'path' => $p, 'source' => $v['files'][$p]]];
                case 'diff':
                    return [200, ['ok' => true] + $S->diff($pid, (int) ($get['a'] ?? 0), (int) ($get['b'] ?? 0), $uid)];
                case 'preview':
                    return [200, ['ok' => true, 'html' => $S->preview($pid, $num, $uid, (string) ($get['page'] ?? 'index.html'))]];
                case 'export':
                    $p = $S->get($pid, $uid)['project'];
                    $slug = self::slug($p['name']);
                    $zip = $S->exportZip($pid, $num, $uid);
                    if (!empty($get['delivery'])) $S->logDownload($pid, $num, $uid, (int) $get['delivery']);
                    return [200, ['zip' => $zip, 'name' => $slug . '-v' . $num . '.zip']];
                case 'job':
                    $id = (string) ($get['id'] ?? ''); $f = rtrim((string) $this->c['jobsDir'], '/') . "/$id.json";
                    if (!preg_match('/^[0-9a-f]{16}$/', $id) || !is_file($f)) return [404, ['ok' => false, 'error' => 'job']];
                    $j = json_decode((string) file_get_contents($f), true) ?: [];
                    if (($j['uid'] ?? null) !== $uid) return [404, ['ok' => false, 'error' => 'job']];
                    unset($j['uid']); return [200, ['ok' => true] + $j];
                case 'usage':
                    return [200, ['ok' => true, 'usage' => $S->usage()]];
            }
            return [404, ['ok' => false, 'error' => 'route']];
        }
        catch (TsStudioChecksFailed $e) { return [422, ['ok' => false, 'error' => 'checks', 'errors' => $e->errors]]; }
        catch (TsStudioDenied $e) { return [403, ['ok' => false, 'error' => 'denied']]; }
        catch (TsStudioModelError $e) { return [$e->kind === 'limit' ? 429 : 503, ['ok' => false, 'error' => 'ai', 'kind' => $e->kind, 'detail' => $e->getMessage()]]; }
        catch (InvalidArgumentException $e) { return [400, ['ok' => false, 'error' => 'input', 'detail' => $e->getMessage()]]; }
    }

    /* název souboru bez diakritiky (vlastní tabulka – iconv přepisuje na různých systémech různě) */
    public static function slug(string $s): string
    {
        $s = strtr(mb_strtolower($s), ['á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'ë' => 'e', 'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ř' => 'r', 'ŕ' => 'r',
            'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ý' => 'y', 'ž' => 'z', 'ß' => 'ss', 'ą' => 'a', 'ę' => 'e', 'ł' => 'l', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', 'ć' => 'c', 'ń' => 'n']);
        return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-') ?: 'web';
    }
    private static function msg(array $b, string $def): string { return trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($b['message'] ?? '')), 0, 300)) ?: $def; }
    private function originOk(array $h): bool
    {
        if (($h['sec-fetch-site'] ?? 'same-origin') !== 'same-origin') return false;
        return in_array((string) ($h['origin'] ?? ''), (array) $this->c['allowedOrigins'], true);
    }
    /* úloha: soubor ve jobsDir (jen pro stejného uživatele), stará se po dni mažou; práce poběží až po odeslání odpovědi */
    private function job(callable $work): array
    {
        $dir = rtrim((string) $this->c['jobsDir'], '/'); if ($dir === '') throw new InvalidArgumentException('chybí jobsDir');
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        foreach (glob($dir . '/*.json') ?: [] as $f) if (filemtime($f) < time() - 86400) @unlink($f);
        $id = bin2hex(random_bytes(8)); $f = "$dir/$id.json"; $uid = (int) $this->c['uid'];
        file_put_contents($f, json_encode(['uid' => $uid, 'status' => 'running', 'started' => time()]));
        $after = function () use ($work, $f, $uid) {
            $t0 = microtime(true); $sec = fn () => round(microtime(true) - $t0, 1);
            try { $r = ['status' => 'done', 'result' => $work()]; }
            catch (TsStudioModelError $e) { $r = ['status' => 'error', 'error' => $e->kind, 'detail' => mb_substr($e->getMessage(), 0, 300)]; }
            catch (InvalidArgumentException | TsStudioDenied $e) { $r = ['status' => 'error', 'error' => 'input', 'detail' => mb_substr($e->getMessage(), 0, 300)]; }
            catch (Throwable $e) { error_log('ts-studio: ' . get_class($e) . ': ' . $e->getMessage()); $r = ['status' => 'error', 'error' => 'internal']; }
            file_put_contents($f, json_encode(['uid' => $uid] + $r + ['seconds' => $sec()], JSON_UNESCAPED_UNICODE));
        };
        return [202, ['ok' => true, 'job' => $id], $after];
    }
}
