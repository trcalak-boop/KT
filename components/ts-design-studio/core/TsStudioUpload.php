<?php
/* ==========================================================================
   ts-design-studio – nahraný návrh (etapa 7.1): ZIP nebo jeden HTML soubor → projekt '_upload' s verzemi a přehledem, připomínky klienta.
   Kód návrhu se na serveru nikdy nespouští ani nerozbaluje do webu. Text do 2 MB je ve verzi, ostatní soubory na disku podle otisku ('uploadDir', mimo webroot).
   ZIP čte vlastní čtečka (ZipArchive na hostingu nemusí být): žádné „..“, absolutní cesty, odkazy, šifrování ani nepovolené přípony; strop 40 MB a 800 souborů.
   Konfigurace TsDesignStudio: 'uploadDir', 'feedbackTo' => [e-maily] | fn(array $projekt): array, 'mailer'.
   ========================================================================== */
final class TsStudioUploadError extends InvalidArgumentException
{
    public array $errors;
    public function __construct(array $e) { parent::__construct(implode('; ', array_slice($e, 0, 6))); $this->errors = $e; }
}
final class TsStudioUpload
{
    const EXT = ['html', 'htm', 'css', 'js', 'mjs', 'map', 'json', 'txt', 'xml', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'mp4', 'webm', 'pdf'];
    const TEXT = ['html', 'htm', 'css', 'js', 'mjs', 'map', 'json', 'txt', 'xml', 'svg'];
    const IGNORE = '~(^|/)(__MACOSX/|\.DS_Store$|Thumbs\.db$|desktop\.ini$)~i';
    const MAX_TOTAL = 40000000, MAX_FILES = 800, MAX_INLINE = 2000000;

    /* → ['files' => [cesta => bajty], 'errors' => []] */
    public static function unpack(string $bytes, string $name): array
    {
        if ($bytes === '') return ['files' => [], 'errors' => ['Soubor je prázdný.']];
        if (strlen($bytes) > self::MAX_TOTAL + 1000000) return ['files' => [], 'errors' => ['Soubor je větší než 40 MB.']];
        if (substr($bytes, 0, 4) === "PK\x03\x04" || substr($bytes, 0, 4) === "PK\x05\x06") { [$files, $errors] = self::readZip($bytes); }
        elseif (preg_match('/\.html?$/i', $name) || preg_match('/^\s*(<!doctype|<html)/i', substr($bytes, 0, 200))) { $files = ['index.html' => $bytes]; $errors = []; }
        else return ['files' => [], 'errors' => ['Nahrajte ZIP nebo soubor HTML.']];
        if ($errors) return ['files' => [], 'errors' => $errors];
        /* jedna společná složka v kořeni se odstraní */
        $tops = array_unique(array_map(fn ($p) => strpos($p, '/') === false ? '' : explode('/', $p)[0], array_keys($files)));
        if (count($tops) === 1 && $tops[0] !== '') { $l = strlen($tops[0]) + 1; $n = []; foreach ($files as $p => $b) $n[substr($p, $l)] = $b; $files = $n; }
        if (!isset($files['index.html'])) {
            $root = array_values(array_filter(array_keys($files), fn ($p) => strpos($p, '/') === false && preg_match('/\.html?$/i', $p)));
            if (isset($files['index.htm'])) $root = ['index.htm'];
            if (count($root) !== 1) return ['files' => [], 'errors' => ['V kořeni návrhu chybí index.html.']];
            $files['index.html'] = $files[$root[0]]; unset($files[$root[0]]);
        }
        ksort($files);
        return ['files' => $files, 'errors' => []];
    }
    private static function u16(string $s, int $o): int { return unpack('v', $s, $o)[1]; }
    private static function u32(string $s, int $o): int { return unpack('V', $s, $o)[1]; }
    private static function readZip(string $z): array
    {
        $len = strlen($z); $min = max(0, $len - 66000); $eocd = strrpos($z, "PK\x05\x06", 0);
        if ($eocd === false || $eocd < $min || $eocd + 22 > $len) return [[], ['ZIP je poškozený.']];
        $count = self::u16($z, $eocd + 10); $cdOff = self::u32($z, $eocd + 16);
        if ($count === 0xFFFF || $cdOff === 0xFFFFFFFF) return [[], ['ZIP64 není podporovaný.']];
        if ($count > self::MAX_FILES * 2) return [[], ['ZIP má příliš mnoho souborů.']];
        $files = []; $errors = []; $total = 0; $o = $cdOff;
        for ($i = 0; $i < $count; $i++) {
            if ($o + 46 > $len || substr($z, $o, 4) !== "PK\x01\x02") return [[], ['ZIP je poškozený.']];
            $flags = self::u16($z, $o + 8); $method = self::u16($z, $o + 10); $crc = self::u32($z, $o + 16); $csize = self::u32($z, $o + 20); $usize = self::u32($z, $o + 24);
            $nl = self::u16($z, $o + 28); $xl = self::u16($z, $o + 30); $cl = self::u16($z, $o + 32); $attr = self::u32($z, $o + 38); $lo = self::u32($z, $o + 42);
            $raw = substr($z, $o + 46, $nl); $o += 46 + $nl + $xl + $cl;
            if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'CP437');
            $name = str_replace('\\', '/', $raw);
            if (str_ends_with($name, '/') || preg_match(self::IGNORE, $name)) continue;
            $shown = mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '?', $name), 0, 120);
            if ($name === '' || $name[0] === '/' || preg_match('~(^|/)\.\.(/|$)|[\x00-\x1F\x7F]|^[a-z]:~i', $name)) { $errors[] = "Nepovolená cesta: $shown"; continue; }
            if (($attr >> 16 & 0170000) === 0120000) { $errors[] = "Odkaz v ZIPu není povolený: $shown"; continue; }
            $base = basename($name); $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($base[0] === '.' || !in_array($ext, self::EXT, true)) { $errors[] = "Nepovolený soubor: $shown"; continue; }
            if ($flags & 1) { $errors[] = "Zašifrovaný soubor: $shown"; continue; }
            if ($method !== 0 && $method !== 8) { $errors[] = "Nepodporovaná komprese: $shown"; continue; }
            $total += $usize;
            if ($total > self::MAX_TOTAL) return [[], ['Návrh je po rozbalení větší než 40 MB.']];
            if (count($files) >= self::MAX_FILES) return [[], ['Návrh má víc než 800 souborů.']];
            if ($lo + 30 > $len || substr($z, $lo, 4) !== "PK\x03\x04") return [[], ['ZIP je poškozený.']];
            $data = substr($z, $lo + 30 + self::u16($z, $lo + 26) + self::u16($z, $lo + 28), $csize);
            if ($method === 8) { $data = $usize === 0 ? '' : @gzinflate($data, $usize + 1); if ($data === false) { $errors[] = "Soubor nejde rozbalit: $shown"; continue; } }
            if (strlen($data) !== $usize || crc32($data) !== $crc) { $errors[] = "Poškozený soubor: $shown"; continue; }
            $n = implode('/', array_filter(explode('/', $name), fn ($s) => $s !== '' && $s !== '.'));
            $files[$n] = $data;
        }
        return [$files, array_slice($errors, 0, 20)];
    }
    public static function isText(string $path): bool { return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TEXT, true); }
    /* soubor podle otisku: stejný obsah jen jednou */
    public static function put(string $dir, string $bytes): string
    {
        $h = hash('sha256', $bytes); $d = rtrim($dir, '/') . '/' . substr($h, 0, 2); $f = "$d/$h";
        if (is_file($f)) return $h;
        if (!is_dir($d) && !@mkdir($d, 0700, true) && !is_dir($d)) throw new RuntimeException('úložiště návrhů nejde vytvořit');
        $t = $f . '.' . bin2hex(random_bytes(4)); if (file_put_contents($t, $bytes) !== strlen($bytes) || !rename($t, $f)) { @unlink($t); throw new RuntimeException('soubor návrhu nejde uložit'); }
        return $h;
    }
    public static function get(string $dir, string $hash): ?string
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hash)) return null;
        $f = rtrim($dir, '/') . '/' . substr($hash, 0, 2) . "/$hash"; return is_file($f) ? (string) file_get_contents($f) : null;
    }
}

/* operace nad projektem – část TsDesignStudio */
trait TsStudioUploadOps
{
    public static function isUpload(array $p): bool { return ($p['template'] ?? '') === self::UPLOAD; }
    private function uploadDir(): string { $d = (string) ($this->c['uploadDir'] ?? ''); if ($d === '') throw new InvalidArgumentException('nahrávání návrhů není nastavené (uploadDir)'); return $d; }
    /* nahrání: $p = { project? (číslo, další verze), name, lead_ref, lang, message }. Bez chyb kontrol je verze rovnou přijatá a jde poslat klientovi. */
    public function uploadDesign(string $bytes, string $fileName, int $uid, array $p = []): array
    {
        $this->can($uid, 'edit'); $dir = $this->uploadDir();
        $pid = (int) ($p['project'] ?? 0);
        $proj = $pid ? $this->project($pid, $uid, 'edit') : null;
        if ($proj && !self::isUpload($proj)) throw new InvalidArgumentException('projekt není nahraný návrh');
        $name = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($p['name'] ?? '')), 0, 160));
        if (!$proj && $name === '') throw new InvalidArgumentException('chybí název návrhu');
        $u = TsStudioUpload::unpack($bytes, $fileName);
        if ($u['errors']) throw new TsStudioUploadError($u['errors']);
        $text = []; $inline = []; $manifest = []; $sizes = [];
        foreach ($u['files'] as $path => $b) {
            $sizes[$path] = strlen($b); $isText = TsStudioUpload::isText($path);
            if ($isText) $text[$path] = $b;
            if ($isText && strlen($b) <= TsStudioUpload::MAX_INLINE && mb_check_encoding($b, 'UTF-8')) { $inline[$path] = $b; continue; }
            $manifest[$path] = ['sha' => TsStudioUpload::put($dir, $b), 'size' => strlen($b)];
        }
        $an = TsStudioInspect::run($text, $sizes);
        /* kontroly kvality: velikost a odkazy řeší přehled (relativní cesty, soubory na disku); skripty a zástupné texty jen upozorní */
        $base = TsStudioChecks::run(array_filter($text, fn ($p) => mb_check_encoding($text[$p], 'UTF-8'), ARRAY_FILTER_USE_KEY), null, []);
        $base = array_filter($base, fn ($c) => !in_array($c['code'], ['size', 'size-total', 'link', 'title', 'lang', 'viewport', 'path'], true));
        $base = array_map(fn ($c) => in_array($c['code'], ['js-risk', 'js-brackets', 'placeholder', 'tag'], true) ? ['level' => 'warn'] + $c : $c, $base);
        $checks = array_merge(array_values($base), $an['checks']); unset($an['checks']);
        $lang = (string) ($p['lang'] ?? ''); if (!preg_match('/^[a-z]{2}$/', $lang)) $lang = 'cs';
        if (!$proj) {
            $pid = $this->store->createProject(['name' => $name, 'source' => 'crm', 'template' => self::UPLOAD, 'lead_ref' => mb_substr((string) ($p['lead_ref'] ?? ''), 0, 64), 'owner_uid' => $uid, 'brief' => ['lang' => $lang]]);
            $this->store->log($pid, 0, $uid, 'project.create', 'nahraný návrh');
            $proj = $this->store->project($pid);
        }
        $msg = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($p['message'] ?? '')), 0, 300)) ?: 'Nahraný návrh: ' . mb_substr($fileName, 0, 120);
        $num = $this->store->addVersion($pid, ['parent' => (int) $proj['current_num'], 'kind' => 'upload', 'uid' => $uid, 'author_kind' => 'human', 'message' => $msg, 'files' => $inline,
            'content' => ['analysis' => $an, 'manifest' => $manifest, 'upload' => ['name' => mb_substr($fileName, 0, 160), 'size' => strlen($bytes)]], 'tokens' => [], 'checks' => $checks]);
        $this->store->log($pid, $num, $uid, 'version.upload', count($sizes) . ' souborů');
        $errs = TsStudioChecks::errors($checks);
        if (!$errs) {
            $this->store->tx(function () use ($pid, $num) { $this->store->setVersionStatus($pid, $num, 'accepted'); $this->store->setProject($pid, ['current_num' => $num, 'status' => 'approved_internal']); });
        }
        return ['id' => $pid, 'num' => $num, 'accepted' => !$errs, 'analysis' => $an, 'checks' => $checks];
    }
    /* soubor verze nahraného návrhu pro bránu; null = není */
    public function uploadFile(array $version, string $path): ?string
    {
        if (isset($version['files'][$path])) return $version['files'][$path];
        $m = $version['content']['manifest'][$path] ?? null;
        return $m ? TsStudioUpload::get((string) ($this->c['uploadDir'] ?? ''), (string) $m['sha']) : null;
    }
    public function uploadPaths(array $version): array { return array_merge(array_keys($version['files']), array_keys((array) ($version['content']['manifest'] ?? []))); }

    /* ---------- připomínky klienta ---------- */
    const FEEDBACK_MAX = 3000;
    /* z brány: $i = { kind: comment|approve, body, section, author, web (past na roboty) } → ['ok' => bool, 'error' => input|limit|robot, 'id'] */
    public function addFeedback(array $hit, array $i): array
    {
        $d = $hit['delivery']; $v = $hit['version']; $pid = (int) $d['project_id'];
        if (trim((string) ($i['web'] ?? '')) !== '') return ['ok' => false, 'error' => 'robot'];
        $kind = ($i['kind'] ?? '') === 'approve' ? 'approve' : 'comment';
        $clean = fn ($s, int $max) => mb_substr(trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", (string) $s))), 0, $max);
        $body = $clean($i['body'] ?? '', self::FEEDBACK_MAX + 1);
        if (mb_strlen($body) > self::FEEDBACK_MAX) return ['ok' => false, 'error' => 'input'];
        if ($kind === 'comment' && $body === '') return ['ok' => false, 'error' => 'input'];
        $sections = (array) ($v['content']['analysis']['sections'] ?? []);
        $section = $clean($i['section'] ?? '', 80); if (!in_array($section, $sections, true)) $section = '';
        $author = (string) preg_replace('/\s+/u', ' ', $clean($i['author'] ?? '', 120)); if ($author === '') $author = (string) $d['to_name'];
        $now = time();
        if ($this->store->feedbackCount((int) $d['id'], date('Y-m-d H:i:s', $now - 3600)) >= 20 || $this->store->feedbackCount((int) $d['id'], date('Y-m-d H:i:s', $now - 86400)) >= 60) return ['ok' => false, 'error' => 'limit'];
        foreach ($this->store->feedbackOfDelivery((int) $d['id']) as $r) {
            if ($kind === 'approve' && $r['kind'] === 'approve') return ['ok' => true, 'id' => (int) $r['id'], 'repeated' => true];
            if ($kind === 'comment' && $r['body'] === $body && $r['created_at'] > date('Y-m-d H:i:s', $now - 600)) return ['ok' => true, 'id' => (int) $r['id'], 'repeated' => true];
        }
        $id = $this->store->addFeedback(['project_id' => $pid, 'num' => (int) $d['num'], 'delivery_id' => (int) $d['id'], 'kind' => $kind, 'author' => $author, 'section' => $section, 'body' => $body]);
        $this->store->log($pid, (int) $d['num'], 0, 'feedback.' . $kind, "doručení {$d['id']}");
        $this->notifyFeedback($id);
        return ['ok' => true, 'id' => $id];
    }
    /* e-mail nám; když selže, připomínka zůstane a zkusí se znovu (retryFeedbackMail) */
    public function notifyFeedback(int $id): bool
    {
        $f = $this->store->feedback($id); if (!$f) return false;
        $p = $this->store->project((int) $f['project_id']); $d = $this->store->delivery((int) $f['delivery_id']);
        $to = $this->c['feedbackTo'] ?? [];
        if (is_callable($to)) $to = $to($p);
        $to = array_values(array_unique(array_filter(array_map('trim', (array) $to), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        $m = $this->c['mailer'] ?? null;
        if (!$to || !is_object($m) || !method_exists($m, 'send')) return false;
        $approve = $f['kind'] === 'approve';
        $subject = ($approve ? 'Návrh schválen: ' : 'Připomínka k návrhu: ') . $p['name'];
        $who = trim($f['author'] . ($d && $d['to_email'] !== '' ? ' <' . $d['to_email'] . '>' : ''));
        $text = ($approve ? "Klient schválil návrh." : "Klient poslal připomínku k návrhu.") . "\n\nNávrh: {$p['name']} (verze {$f['num']})\nOd: $who"
            . ($f['section'] !== '' ? "\nČást: {$f['section']}" : '') . ($f['body'] !== '' ? "\n\n{$f['body']}" : '')
            . (!empty($this->c['crmUrl']) && $p['lead_ref'] !== '' ? "\n\nV CRM: " . rtrim((string) $this->c['crmUrl'], '/') . '/' . rawurlencode($p['lead_ref']) : '');
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="cs"><body style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#111">' . nl2br($e($text)) . '</body></html>';
        $ok = true;
        foreach ($to as $addr) {
            try { $r = $m->send($addr, $subject, $text, $html, $d && $d['to_email'] !== '' ? $d['to_email'] : null, 'T&S návrhy'); }
            catch (Throwable $x) { $r = ['ok' => false]; }
            if (empty($r['ok'])) $ok = false;
        }
        if ($ok) $this->store->setFeedback($id, ['notified_at' => TsStudioStore::now()]);
        else error_log('ts-studio: upozornění na připomínku ' . $id . ' se neodeslalo');
        return $ok;
    }
    public function retryFeedbackMail(): int
    {
        $n = 0; foreach ($this->store->feedbackUnnotified() as $f) if ($this->notifyFeedback((int) $f['id'])) $n++;
        return $n;
    }
    /* pro CRM */
    public function feedbackOf(int $pid, int $uid): array { $this->project($pid, $uid, 'view'); return $this->store->feedbackOfProject($pid); }
    public function feedbackUnsynced(): array { return $this->store->feedbackUnsynced(); }
    public function markFeedbackSynced(array $ids): void { foreach ($ids as $id) $this->store->setFeedback((int) $id, ['synced_at' => TsStudioStore::now()]); }
    public function setFeedbackDone(int $id, int $uid, bool $done): array
    {
        $f = $this->store->feedback($id); if (!$f) throw new InvalidArgumentException('připomínka neexistuje');
        $this->project((int) $f['project_id'], $uid, 'edit');
        $this->store->setFeedback($id, ['status' => $done ? 'done' : 'new', 'done_by' => $done ? $uid : 0, 'done_at' => $done ? TsStudioStore::now() : '']);
        $this->store->log((int) $f['project_id'], (int) $f['num'], $uid, 'feedback.' . ($done ? 'done' : 'reopen'), "připomínka $id");
        return $this->store->feedback($id);
    }
    public function feedbackOpenCount(): int { return $this->store->feedbackOpenCount(); }
    public static function uploadDeliveryText(string $lang): array
    {
        if ($lang === 'en') return ['subject' => 'Website design for {klient} – T&S Studio', 'body' => "Hello,\n\nwe are sending the website design for {klient}. You can view it here:\n{odkaz}\n\n"
            . "The link is private and valid until {datum}. You can write your comments directly on that page, or approve the design there.\n\nBest regards\n{jmeno}\nT&S Studio · tasstudio.eu"];
        return ['subject' => 'Návrh webu pro {klient} – T&S Studio', 'body' => "Dobrý den,\n\nposíláme návrh webu pro {klient}. Prohlédnout si ho můžete zde:\n{odkaz}\n\n"
            . "Odkaz je neveřejný a platí do {datum}. Připomínky můžete napsat přímo na stránce s návrhem a tam ho můžete i schválit.\n\nS pozdravem\n{jmeno}\nT&S Studio · tasstudio.eu"];
    }
}
