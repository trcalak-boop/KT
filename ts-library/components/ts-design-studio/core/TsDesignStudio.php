<?php
/* ==========================================================================
   ts-design-studio – AI Design Studio (jen administrace): projekty webů ze šablon, verze, kontroly, rozdíly, náhled, export.
   Každá změna (vykreslení z obsahu a tokenů, úprava souborů, AI úprava) vzniká jako NOVÁ verze ve stavu „candidate“;
   přijmout ji jde jen bez chyb kontrol. Návrat k verzi = nová verze se soubory staré. Nic se nenasazuje ani neodesílá.
   Oprávnění: 'can' => fn(int $uid, string $akce, ?array $projekt): bool (akce view|edit|accept|export|status); výchozí = vše.
   AI (volitelné): 'ai' => ['model' => TsStudioModel, 'neuronsPerDay' => 3000] – návrh ze zadání (aiCreate) a konverzační úpravy (aiEdit), viz TsStudioAi.
   ========================================================================== */
final class TsDesignStudio
{
    use TsStudioDeliveryOps;   /* doručení klientovi – core/TsStudioDelivery.php */
    use TsStudioUploadOps;     /* nahraný návrh a připomínky – core/TsStudioUpload.php */
    const SOURCES = ['new', 'redesign', 'crm'];
    const STATUS = ['draft', 'in_review', 'approved_internal', 'archived'];
    const UPLOAD = '_upload';   /* nahraný hotový návrh (ZIP, HTML) */
    const KIT = '_kit';     /* projekt ze stavebnice (návrh na míru): content = plán, tokens = tokeny stavebnice */
    private array $c; private TsStudioStore $store; private array $tpl = []; private ?TsStudioKit $kit = null;

    public function __construct(array $c)
    {
        $this->c = $c + ['templatesDir' => __DIR__ . '/../templates', 'can' => null, 'ai' => null];
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
    public function kit(): TsStudioKit { return $this->kit ??= new TsStudioKit(); }
    public function template(string $id): TsStudioTemplate
    {
        if ($id === self::UPLOAD) return TsStudioTemplate::virtual(['id' => self::UPLOAD, 'name' => 'Nahraný návrh'], [], fn (string $p) => '');
        if ($id === self::KIT) { $k = $this->kit(); return TsStudioTemplate::virtual(['id' => self::KIT, 'name' => 'Návrh na míru'], $k->allFontPaths(), fn (string $p) => $k->fontBytes($p)); }
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
        if (in_array($p['template'] ?? '', [self::KIT, self::UPLOAD], true)) throw new InvalidArgumentException('návrh na míru vzniká jen ze zadání');
        $t = $this->template((string) ($p['template'] ?? ''));
        $pid = $this->store->createProject(['name' => $name, 'source' => $p['source'], 'template' => $t->id(), 'lead_ref' => mb_substr((string) ($p['lead_ref'] ?? ''), 0, 64),
            'audit_ref' => mb_substr((string) ($p['audit_ref'] ?? ''), 0, 64), 'owner_uid' => $uid, 'brief' => (array) ($p['brief'] ?? [])]);
        $this->store->log($pid, 0, $uid, 'project.create', $p['source'] . ' · ' . $t->id());
        $v = $this->renderVersion($pid, $uid, $content + $t->t['content'], $tokens, (string) ($p['message'] ?? 'Výchozí verze ze šablony ' . $t->t['name']), (string) ($p['author'] ?? 'system'));
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
        $p = $this->project($pid, $uid, 'edit');
        if ($p['template'] === self::KIT) return $this->renderKit($p, $uid, $content, $tokens, $message, $authorKind);
        $t = $this->template($p['template']);
        [$c, $e1] = $t->cleanContent($content); [$tk, $e2] = $t->cleanTokens($tokens);
        $files = $t->render($c, $tk);
        /* vrstva úprav CSS (/*@ai*\/…) z aktuální verze přežije nové vykreslení; ostatní ruční úpravy souborů ne */
        $cur = $p['current_num'] ? $this->store->version($pid, (int) $p['current_num']) : null;
        if ($cur && isset($files['assets/styles.css']) && ($layer = TsStudioAi::aiCss($cur['files']['assets/styles.css'] ?? '')) !== '') $files['assets/styles.css'] = TsStudioAi::withAiCss($files['assets/styles.css'], $layer);
        $checks = TsStudioChecks::run($files, $t, $tk);
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

    /* ---------- AI ---------- */
    /* návrh ze zadání: výběr šablony, obsah a tokeny od modelu → nový projekt s verzí 1 (autor ai). $p = { source: new|redesign|crm, lead_ref?, audit_ref? } */
    public function aiCreate(array $brief, int $uid, array $p = []): array
    {
        $this->can($uid, 'edit'); $b = TsStudioAi::cleanBrief($brief);
        if ($b['name'] === '') throw new InvalidArgumentException('chybí název podniku');
        $tid = TsStudioAi::pickTemplate($this->templates(), $b);
        if ($tid === null) {
            $j = $this->ask(TsStudioAi::pickPrompt($this->templates(), $b), 'pick', 0, $uid, ['maxTokens' => 60, 'temperature' => 0]);
            $tid = isset($this->templates()[(string) ($j['template'] ?? '')]) ? (string) $j['template'] : array_key_first($this->templates());
        }
        $t = $this->template($tid);
        $j = $this->ask(TsStudioAi::createPrompt($t, $b), 'create', 0, $uid);
        [$content, $errors] = self::fill($t, (array) ($j['content'] ?? []));
        [$content, $dropped] = TsStudioAi::ground($t, $content, TsStudioAi::briefSource($b));
        [$tokens, $fixed] = self::safeTokens($t, TsStudioAi::normTokens($t, $j['tokens'] ?? []));
        $notes = trim(mb_substr((string) ($j['notes'] ?? ''), 0, 600));
        $r = $this->createProject(['name' => $b['name'], 'source' => $p['source'] ?? ($b['audit'] ? 'redesign' : 'new'), 'template' => $tid, 'lead_ref' => $p['lead_ref'] ?? '', 'audit_ref' => $p['audit_ref'] ?? '',
            'brief' => $b, 'message' => 'Návrh AI ze zadání (' . $this->modelName() . ')', 'author' => 'ai'], $uid, $content, $tokens);
        $todo = TsStudioAi::todo($content);
        $this->store->addMessage($r['id'], 0, $uid, 'user', "Zadání:\n" . TsStudioAi::briefText($b));
        $this->store->addMessage($r['id'], $r['version'], 0, 'assistant', trim($notes . ($fixed ? "\nKvůli čitelnosti jsem upravila barvy: " . implode(', ', $fixed) . '.' : '') . ($dropped ? "\nCitáty a jména bez podkladu v zadání jsem nahradila [doplnit]: " . implode(', ', $dropped) . '.' : '') . ($todo ? "\nK doplnění: " . implode(', ', $todo) . '.' : '')));
        return $r + ['template' => $tid, 'notes' => $notes, 'todo' => $todo, 'errors' => $errors, 'tokensFixed' => $fixed, 'dropped' => $dropped, 'rights' => $b['rights']];
    }
    /* konverzační úprava aktuální verze: změny obsahu, tokenů a vrstvy CSS → jedna nová verze-kandidát (autor ai) */
    public function aiEdit(int $pid, int $uid, string $instruction): array
    {
        $p = $this->project($pid, $uid, 'edit');
        if ($p['template'] === self::KIT) return $this->kitEdit($p, $uid, $instruction);
        $t = $this->template($p['template']);
        $instruction = trim(mb_substr($instruction, 0, 1500)); if ($instruction === '') throw new InvalidArgumentException('prázdný pokyn');
        $base = $this->store->version($pid, (int) $p['current_num']); if (!$base) throw new InvalidArgumentException('projekt nemá přijatou verzi');
        $hist = $this->store->messages($pid, 6);
        $this->store->addMessage($pid, (int) $base['num'], $uid, 'user', $instruction);
        $j = $this->ask(TsStudioAi::editPrompt($t, $base, $instruction, $hist), 'edit', $pid, $uid);
        $reply = trim(mb_substr((string) ($j['reply'] ?? ''), 0, 800));
        $dc = is_array($j['content'] ?? null) ? array_intersect_key($j['content'], $t->t['slots']) : [];
        $dt = TsStudioAi::normTokens($t, $j['tokens'] ?? []);
        /* jen skutečné změny (model často pošle i neměněné sloty) */
        [$cleanDc] = $t->cleanContent($dc + $base['content']); foreach ($dc as $k => $x) if (($cleanDc[$k] ?? null) === ($base['content'][$k] ?? null)) unset($dc[$k]);
        foreach ($dt as $k => $x) if (strtolower($x) === strtolower((string) ($base['tokens'][$k] ?? ''))) unset($dt[$k]);
        $hasCss = isset($j['css']) && is_string($j['css']);
        if (!$dc && !$dt && !$hasCss) { $this->store->addMessage($pid, (int) $base['num'], 0, 'assistant', $reply ?: 'Beze změny.'); return ['num' => null, 'reply' => $reply ?: 'Beze změny.', 'checks' => [], 'changed' => []]; }
        [$c, $e1] = $t->cleanContent($dc + $base['content']); [$tk, $e2] = $t->cleanTokens($dt + $base['tokens']);
        [$c, $dropped] = TsStudioAi::ground($t, $c, TsStudioAi::briefSource((array) $p['brief']) . "\n" . $instruction . "\n" . json_encode($base['content'], JSON_UNESCAPED_UNICODE));
        [$tk, $fixed] = self::safeTokens($t, $tk);
        if ($fixed) $reply = trim($reply . ' Kvůli čitelnosti jsem ztmavila/zesvětlila: ' . implode(', ', $fixed) . '.');
        if ($dropped) $reply = trim($reply . ' Citáty a jména bez podkladu jsem nahradila [doplnit]: ' . implode(', ', $dropped) . '.');
        $files = ($dc || $dt || $fixed || $dropped) ? $t->render($c, $tk) + $base['files'] : $base['files'];
        $layer = $hasCss ? $j['css'] : TsStudioAi::aiCss($base['files']['assets/styles.css'] ?? '');
        if (isset($files['assets/styles.css'])) $files['assets/styles.css'] = TsStudioAi::withAiCss($files['assets/styles.css'], $layer);
        if ($files === $base['files'] && $c === $base['content'] && $tk === $base['tokens']) { $this->store->addMessage($pid, (int) $base['num'], 0, 'assistant', $reply ?: 'Beze změny.'); return ['num' => null, 'reply' => $reply ?: 'Beze změny.', 'checks' => [], 'changed' => []]; }
        $checks = TsStudioChecks::run($files, $t, $tk);
        $changed = array_merge(array_keys($dc), array_map(fn ($k) => "token:$k", array_keys($dt)), $hasCss ? ['css'] : []);
        $msg = 'AI: ' . mb_substr($instruction, 0, 200);
        $num = $this->store->addVersion($pid, ['parent' => (int) $base['num'], 'kind' => 'ai', 'uid' => $uid, 'author_kind' => 'ai', 'message' => $msg, 'files' => $files, 'content' => $c, 'tokens' => $tk, 'checks' => $checks]);
        $this->store->log($pid, $num, $uid, 'version.ai', implode(', ', $changed));
        $this->store->addMessage($pid, $num, 0, 'assistant', $reply);
        return ['num' => $num, 'reply' => $reply, 'checks' => $checks, 'changed' => $changed, 'errors' => array_merge($e1, $e2), 'todo' => TsStudioAi::todo($c)];
    }
    /* ---------- návrh na míru ze stavebnice ---------- */
    /* popis stavebnice pro editor plánu v administraci */
    public function kitSchema(): array
    {
        $k = $this->kit(); $sec = [];
        foreach ($k->sections() as $t => $d) $sec[$t] = ['label' => $d['label'], 'use' => $d['use'], 'variants' => $d['variants'], 'default' => $d['default'], 'slots' => $d['slots']];
        $fonts = []; foreach ($k->fonts as $id => $f) $fonts[$id] = ['family' => $f['family'], 'kind' => $f['kind']];
        return ['kit' => true, 'tokens' => $k->kit['tokens'], 'fonts' => $fonts, 'site' => $k->kit['siteSlots'], 'tones' => $k->kit['tones'], 'sections' => $sec];
    }
    private function renderKit(array $p, int $uid, array $plan, array $tokens, string $message, string $authorKind, string $kind = 'render'): array
    {
        $r = $this->kit()->render($plan + ['tokens' => $tokens]);
        $checks = TsStudioChecks::run($r['files'], $this->template(self::KIT), []);
        $clean = array_diff_key($r['plan'], ['tokens' => 1]);
        $num = $this->store->addVersion((int) $p['id'], ['parent' => (int) $p['current_num'], 'kind' => $kind, 'uid' => $uid, 'author_kind' => $authorKind, 'message' => $message,
            'files' => $r['files'], 'content' => $clean, 'tokens' => $r['plan']['tokens'], 'checks' => $checks]);
        $this->store->log((int) $p['id'], $num, $uid, 'version.' . $kind, $message);
        return ['num' => $num, 'checks' => $checks, 'errors' => $r['errors'], 'fixed' => $r['fixed']];
    }
    /* podpisy nedávných návrhů na míru (pro odlišnost) */
    private function recentSignatures(int $limit = 8): array
    {
        $out = [];
        foreach ($this->store->projects() as $x) {
            if ($x['template'] !== self::KIT || !$x['current_num'] || count($out) >= $limit) continue;
            $v = $this->store->version((int) $x['id'], (int) $x['current_num']);
            if ($v && !empty($v['content']['sections'])) $out[] = TsStudioPlanner::signature($v['content'], $v['tokens']);
        }
        return $out;
    }
    private static function describe(array $s): string { return "písma {$s['display']} a {$s['body']}, akcent {$s['accent']}, sekce " . implode(', ', array_slice($s['layout'], 0, 6)); }
    /* jeden plán od modelu → vyčištěný, ukotvený a opravený */
    private function kitPlan(array $b, int $uid, string $direction, array $others): array
    {
        $kit = $this->kit();
        $j = $this->ask(TsStudioPlanner::createPrompt($kit, $b, $direction, array_map([self::class, 'describe'], $others)), 'kit', 0, $uid, ['maxTokens' => 4000, 'temperature' => 0.7]);
        $n = TsStudioPlanner::normalize($kit, $j);
        $n['site'] += ['lang' => $b['lang']];
        [$p, $errors] = $kit->clean($n);
        [$p, $dropped, $suspect] = TsStudioPlanner::ground($kit, $p, TsStudioAi::briefSource($b));
        [$p, $repaired] = TsStudioPlanner::repair($kit, $p, $b);
        [$p, $tokens, $sim, $varied] = TsStudioPlanner::diversify($kit, $p, $p['tokens'], $others);
        unset($p['tokens']);
        return ['plan' => $p, 'tokens' => $tokens, 'errors' => $errors, 'dropped' => $dropped, 'suspect' => $suspect, 'repaired' => $repaired, 'similarity' => $sim, 'varied' => $varied, 'notes' => trim(mb_substr((string) ($j['notes'] ?? ''), 0, 600))];
    }
    /* zadání → projekt s 1 až 3 směry: první je přijatý, ostatní čekají jako návrhy verzí. Když model cestou selže, zůstanou hotové směry. */
    public function kitCreate(array $brief, int $uid, array $p = [], int $directions = 1): array
    {
        $this->checkAi($uid); $b = TsStudioAi::cleanBrief($brief);
        if ($b['name'] === '') throw new InvalidArgumentException('chybí název podniku');
        $directions = max(1, min(3, $directions)); $others = $this->recentSignatures(); $pid = 0; $out = []; $failed = null;
        for ($i = 0; $i < $directions; $i++) {
            $dir = $directions > 1 ? TsStudioPlanner::DIRECTIONS[$i] : '';
            try { $x = $this->kitPlan($b, $uid, $dir, $others); }
            catch (TsStudioModelError $e) { if (!$pid) throw $e; $failed = $e->kind; break; }
            if (!$pid) {
                $pid = $this->store->createProject(['name' => $b['name'], 'source' => $p['source'] ?? ($b['audit'] ? 'redesign' : 'new'), 'template' => self::KIT, 'lead_ref' => mb_substr((string) ($p['lead_ref'] ?? ''), 0, 64), 'audit_ref' => mb_substr((string) ($p['audit_ref'] ?? ''), 0, 64), 'owner_uid' => $uid, 'brief' => $b]);
                $this->store->log($pid, 0, $uid, 'project.create', ($p['source'] ?? 'new') . ' · návrh na míru');
                $this->store->addMessage($pid, 0, $uid, 'user', "Zadání:\n" . TsStudioAi::briefText($b));
            }
            $proj = $this->store->project($pid);
            $v = $this->renderKit($proj, $uid, $x['plan'], $x['tokens'], 'Návrh AI' . ($dir !== '' ? ' – směr ' . ($i + 1) . ': ' . explode(':', $dir)[0] : '') . ' (' . $this->modelName() . ')', 'ai', 'ai');
            if ($i === 0 && !TsStudioChecks::errors($v['checks'])) $this->accept($pid, $v['num'], $uid);
            $full = $this->store->version($pid, $v['num']); $todo = TsStudioAi::todo($full['content']);
            $this->store->addMessage($pid, $v['num'], 0, 'assistant', self::kitNote($x, $v, $todo));
            $others[] = TsStudioPlanner::signature($full['content'], $full['tokens']);
            $out[] = ['num' => $v['num'], 'direction' => $dir, 'checks' => $v['checks'], 'errors' => array_merge($x['errors'], $v['errors']), 'todo' => $todo, 'dropped' => $x['dropped'], 'suspect' => $x['suspect'], 'repaired' => $x['repaired'], 'similarity' => $x['similarity'], 'varied' => $x['varied'], 'notes' => $x['notes']];
        }
        return ['id' => $pid, 'template' => self::KIT, 'version' => $out[0]['num'], 'versions' => $out, 'checks' => $out[0]['checks'], 'todo' => $out[0]['todo'], 'notes' => $out[0]['notes'], 'errors' => $out[0]['errors'], 'dropped' => $out[0]['dropped'], 'failed' => $failed, 'rights' => $b['rights']];
    }
    private static function kitNote(array $x, array $v, array $todo): string
    {
        return trim($x['notes']
            . ($v['fixed'] ? "\nKvůli čitelnosti jsem upravila barvy: " . implode(', ', $v['fixed']) . '.' : '')
            . ($x['dropped'] ? "\nÚdaje bez podkladu v zadání jsem nepoužila: " . implode(', ', array_slice($x['dropped'], 0, 12)) . '.' : '')
            . ($x['suspect'] ? "\nOvěřte čísla ve volném textu: " . implode(', ', array_slice($x['suspect'], 0, 8)) . '.' : '')
            . ($x['repaired'] ? "\nOpravy kostry: " . implode('; ', $x['repaired']) . '.' : '')
            . ($x['varied'] ? "\nNávrh byl moc podobný dřívějšímu, změnila jsem rozvržení a tvary." : '')
            . ($todo ? "\nK doplnění: " . implode(', ', array_slice($todo, 0, 12)) . '.' : ''));
    }
    private function kitEdit(array $p, int $uid, string $instruction): array
    {
        $pid = (int) $p['id']; $kit = $this->kit();
        $instruction = trim(mb_substr($instruction, 0, 1500)); if ($instruction === '') throw new InvalidArgumentException('prázdný pokyn');
        $base = $this->store->version($pid, (int) $p['current_num']); if (!$base) throw new InvalidArgumentException('projekt nemá přijatou verzi');
        $hist = $this->store->messages($pid, 4);
        $this->store->addMessage($pid, (int) $base['num'], $uid, 'user', $instruction);
        $j = $this->ask(TsStudioPlanner::editPrompt($kit, $base['content'], $base['tokens'], $instruction, $hist), 'kit-edit', $pid, $uid, ['maxTokens' => 3000]);
        $reply = trim(mb_substr((string) ($j['reply'] ?? ''), 0, 800));
        [$plan, $changed] = TsStudioPlanner::applyEdit($kit, $base['content'], $j);
        $dt = TsStudioPlanner::normalize($kit, ['tokens' => $j['tokens'] ?? []])['tokens'];
        foreach ($dt as $k => $x) if (!isset($kit->kit['tokens'][$k]) || strtolower($x) === strtolower((string) ($base['tokens'][$k] ?? ''))) unset($dt[$k]);
        if ($dt) $changed[] = 'tokeny: ' . implode(', ', array_keys($dt));
        if (!$changed) { $this->store->addMessage($pid, (int) $base['num'], 0, 'assistant', $reply ?: 'Beze změny.'); return ['num' => null, 'reply' => $reply ?: 'Beze změny.', 'checks' => [], 'changed' => []]; }
        $brief = TsStudioAi::cleanBrief((array) $p['brief']);
        $source = TsStudioAi::briefSource($brief) . "\n" . $instruction . "\n" . json_encode($base['content'], JSON_UNESCAPED_UNICODE);
        [$clean, $errors] = $kit->clean($plan + ['tokens' => $dt + $base['tokens']]);
        [$clean, $dropped, $suspect] = TsStudioPlanner::ground($kit, $clean, $source);
        [$clean, $repaired] = TsStudioPlanner::repair($kit, $clean, $brief);
        $tokens = $clean['tokens']; unset($clean['tokens']);
        $v = $this->renderKit($p, $uid, $clean, $tokens, 'AI: ' . mb_substr($instruction, 0, 200), 'ai', 'ai');
        $full = $this->store->version($pid, $v['num']);
        if ($full['files'] === $base['files']) { /* verze bez rozdílu se nenabízí */ $this->store->setVersionStatus($pid, $v['num'], 'rejected'); $this->store->addMessage($pid, (int) $base['num'], 0, 'assistant', $reply ?: 'Beze změny.'); return ['num' => null, 'reply' => $reply ?: 'Beze změny.', 'checks' => [], 'changed' => []]; }
        $todo = TsStudioAi::todo($full['content']);
        if ($dropped) $reply = trim($reply . ' Údaje bez podkladu jsem nepoužila: ' . implode(', ', array_slice($dropped, 0, 8)) . '.');
        if ($repaired) $reply = trim($reply . ' ' . implode('; ', $repaired) . '.');
        $this->store->addMessage($pid, $v['num'], 0, 'assistant', $reply);
        return ['num' => $v['num'], 'reply' => $reply, 'checks' => $v['checks'], 'changed' => $changed, 'errors' => array_merge($errors, $v['errors']), 'todo' => $todo, 'dropped' => $dropped, 'suspect' => $suspect];
    }

    public function hasAi(): bool { return ($this->c['ai']['model'] ?? null) instanceof TsStudioModel; }
    /* předběžná kontrola před spuštěním úlohy (oprávnění, nastavená AI, denní strop) – chyba se vrátí hned, ne až v úloze */
    public function checkAi(int $uid): void
    {
        $this->can($uid, 'edit');
        if (!$this->hasAi()) throw new TsStudioModelError('disabled', 'AI není nastavená');
        $limit = (int) ($this->c['ai']['neuronsPerDay'] ?? 0);
        if ($limit > 0 && $this->store->usageDay()['neurons'] >= $limit) throw new TsStudioModelError('limit', 'denní strop AI Studia vyčerpán');
    }
    public function messages(int $pid, int $uid): array { $this->project($pid, $uid, 'view'); return $this->store->messages($pid); }
    public function usage(): array { $u = $this->store->usageDay(); return $u + ['limit' => (int) ($this->c['ai']['neuronsPerDay'] ?? 0), 'model' => $this->modelName()]; }
    private function modelName(): string { return isset($this->c['ai']['model']) ? $this->c['ai']['model']->model() : ''; }
    /* jeden dotaz na model s denním stropem, záznamem spotřeby a jedním opakováním při nečitelném JSON */
    private function ask(array $messages, string $kind, int $pid, int $uid, array $opt = []): array
    {
        $m = $this->c['ai']['model'] ?? null; if (!$m instanceof TsStudioModel) throw new TsStudioModelError('disabled', 'AI není nastavená');
        $limit = (int) ($this->c['ai']['neuronsPerDay'] ?? 0);
        if ($limit > 0 && $this->store->usageDay()['neurons'] >= $limit) throw new TsStudioModelError('limit', 'denní strop AI Studia vyčerpán');
        for ($try = 0; $try < 2; $try++) {
            try { $r = $m->complete($messages, $opt); }
            catch (TsStudioModelError $e) { $this->store->addUsage(['project' => $pid, 'uid' => $uid, 'kind' => $kind, 'model' => $m->model(), 'fail' => 1]); throw $e; }
            $this->store->addUsage(['project' => $pid, 'uid' => $uid, 'kind' => $kind, 'model' => $m->model(), 'in' => $r['in'], 'out' => $r['out'], 'neurons' => $m->neurons($r['in'], $r['out'])]);
            $j = TsStudioAi::parseJson($r['text']); if ($j !== null) return $j;
            $messages[] = ['role' => 'assistant', 'content' => mb_substr($r['text'], 0, 2000)];
            $messages[] = ['role' => 'user', 'content' => 'Odpověď nebyla platný JSON' . ($r['finish'] === 'length' ? ' (byla useknutá – zkrať texty)' : '') . '. Pošli znovu jen platný JSON objekt podle zadání.'];
        }
        throw new TsStudioModelError('format', 'model nevrátil platný JSON');
    }
    /* obsah od modelu: chybějící sloty = „[doplnit]“ (kontakty a odkazy prázdné), nikdy fakta ze vzorové ukázky */
    private static function fill(TsStudioTemplate $t, array $c): array
    {
        foreach ($t->t['slots'] as $k => $s) {
            if (array_key_exists($k, $c)) continue;
            $type = $s['type'] ?? 'text';
            $c[$k] = $type === 'list' ? [] : (in_array($type, ['url', 'email', 'phone', 'color'], true) ? '' : ($k === 'lang' ? ($t->t['content']['lang'] ?? 'cs') : TsStudioAi::TODO));
        }
        [$clean, $err] = $t->cleanContent($c);
        return [$clean, $err];
    }
    /* dvojice tokenů z „contrast“ pod 4,5:1 → popředí se ztmaví/zesvětlí, dokud není čitelné (odstín zůstane) */
    private static function safeTokens(TsStudioTemplate $t, array $tk): array
    {
        [$tk] = $t->cleanTokens($tk); $fixed = [];
        foreach ($t->t['contrast'] as [$fg, $bg]) if (TsStudioTemplate::contrast($tk[$fg], $tk[$bg]) < 4.5) { $tk[$fg] = TsStudioTemplate::ensureContrast($tk[$fg], $tk[$bg]); $fixed[$fg] = $fg; }
        return [$tk, array_values($fixed)];
    }

    /* ---------- export a náhled ---------- */
    /* všechny soubory verze včetně binárních ze šablony → [cesta => bajty] */
    public function allFiles(int $pid, int $num, int $uid, string $act = 'export'): array
    {
        $p = $this->project($pid, $uid, $act); $t = $this->template($p['template']); $v = $this->store->version($pid, $num);
        if (!$v) throw new InvalidArgumentException('verze neexistuje');
        $out = $v['files'];
        if (self::isUpload($p)) {
            foreach ((array) ($v['content']['manifest'] ?? []) as $f => $m) $out[$f] = (string) TsStudioUpload::get($this->uploadDir(), (string) $m['sha']);
            ksort($out); return $out;
        }
        if ($p['template'] === self::KIT) {
            foreach ($this->kit()->binaryFor($v['tokens']) as $f => $src) if (!isset($out[$f])) $out[$f] = (string) file_get_contents($src);
            ksort($out); return $out;
        }
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
