<?php
/* ==========================================================================
   ts-site-audit – měřicí a hodnoticí služby (adaptéry). Audit zná jen rozhraní:
   TsAuditMeter::measure(url, 'mobile'|'desktop', lang) → normalizovaný výsledek Lighthouse (viz TsAuditPageSpeed::normalize)
   TsAuditVision::review(jpegBytes, lang, context)       → ['items' => [['area' =>, 'text' =>, 'priority' => 1–3]], 'usage' => ['in' =>, 'out' =>]]
   Chyby: TsAuditError (kind: 'auth' | 'limit' | 'timeout' | 'connection' | 'site' | 'request' | 'other').
   ========================================================================== */
final class TsAuditError extends RuntimeException
{
    public string $kind;
    public function __construct(string $kind, string $message = '') { parent::__construct($message); $this->kind = $kind; }
}

interface TsAuditMeter { public function measure(string $url, string $strategy, string $lang): array; }
interface TsAuditVision { public function review(string $jpeg, string $lang, array $context): array; public function model(): string; }

function ts_audit_http(string $method, string $url, array $headers, ?string $body, int $timeout, int $connect = 8): array
{
    if (!function_exists('curl_init')) throw new TsAuditError('other', 'chybí rozšíření curl');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $connect, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_errno($ch);
    if ($raw === false || $err) throw new TsAuditError($err === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'connection', 'spojení (curl ' . $err . ')');
    return [$code, (string) $raw];
}

/* Google PageSpeed Insights API v5 (Lighthouse). Stránku stahuje Google ze svých serverů, ne náš server.
   'apiKey' => klíč omezený jen na PageSpeed Insights API (bez klíče je sdílená kvóta obvykle vyčerpaná). */
final class TsAuditPageSpeed implements TsAuditMeter
{
    const CATEGORIES = ['performance', 'accessibility', 'best-practices', 'seo'];
    const METRICS = ['first-contentful-paint' => 'fcp', 'largest-contentful-paint' => 'lcp', 'total-blocking-time' => 'tbt', 'cumulative-layout-shift' => 'cls', 'speed-index' => 'si'];
    private array $c;
    public function __construct(array $c)
    {
        $this->c = $c + ['baseUrl' => 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed', 'timeout' => 100, 'issuesPerCategory' => 5];
        if (empty($this->c['apiKey'])) throw new TsAuditError('auth', 'chybí klíč Google API');
    }
    public function measure(string $url, string $strategy, string $lang): array
    {
        $q = 'url=' . rawurlencode($url) . '&strategy=' . ($strategy === 'desktop' ? 'desktop' : 'mobile') . '&locale=' . rawurlencode($lang);
        foreach (['PERFORMANCE', 'ACCESSIBILITY', 'BEST_PRACTICES', 'SEO'] as $cat) $q .= '&category=' . $cat;
        [$code, $raw] = ts_audit_http('GET', $this->c['baseUrl'] . '?' . $q . '&key=' . rawurlencode($this->c['apiKey']), ['Accept: application/json'], null, (int) $this->c['timeout']);
        $j = json_decode($raw, true);
        if ($code === 200 && is_array($j) && isset($j['lighthouseResult'])) return self::normalize($j, (int) $this->c['issuesPerCategory']);
        $msg = is_array($j) ? (string) ($j['error']['message'] ?? '') : '';
        if ($code === 429) throw new TsAuditError('limit', 'http 429');
        if ($code === 400 && stripos($msg, 'API key') !== false || $code === 403) throw new TsAuditError('auth', 'http ' . $code);
        if ($code === 400 || $code === 500 && stripos($msg, 'Lighthouse') !== false) throw new TsAuditError('site', mb_substr(preg_replace('/key=[^&\s]+/', 'key=…', $msg), 0, 200));
        throw new TsAuditError($code >= 500 ? 'connection' : 'request', 'http ' . $code);
    }

    /* Lighthouse JSON → jen to, co audit potřebuje (skóre 0–100, metriky, problémy, snímky jako bajty JPEG/WEBP). */
    public static function normalize(array $j, int $per = 5): array
    {
        $L = $j['lighthouseResult']; $A = $L['audits'] ?? [];
        if (!empty($L['runtimeError']['code']) && $L['runtimeError']['code'] !== 'NO_ERROR') throw new TsAuditError('site', (string) $L['runtimeError']['code']);
        $scores = []; $issues = [];
        foreach (self::CATEGORIES as $cat) {
            $c = $L['categories'][$cat] ?? null; if (!$c) continue;
            $scores[$cat] = $c['score'] === null ? null : (int) round($c['score'] * 100);
            $list = [];
            foreach ($c['auditRefs'] ?? [] as $ref) {
                $a = $A[$ref['id']] ?? null;
                if (!$a || !isset($a['score']) || $a['score'] === null || $a['score'] >= 0.9 || !in_array($a['scoreDisplayMode'] ?? '', ['binary', 'numeric', 'metricSavings'], true)) continue;
                if (($ref['group'] ?? '') === 'metrics') continue;
                $list[] = ['id' => (string) $ref['id'], 'title' => trim(strip_tags((string) ($a['title'] ?? ''))), 'display' => (string) ($a['displayValue'] ?? ''), 'weight' => (float) ($ref['weight'] ?? 0), 'score' => (float) $a['score']];
            }
            usort($list, fn ($x, $y) => [$y['weight'], $x['score']] <=> [$x['weight'], $y['score']]);
            foreach (array_slice($list, 0, $per) as $i) { unset($i['weight']); $issues[] = ['category' => $cat] + $i; }
        }
        $metrics = [];
        foreach (self::METRICS as $id => $k) if (isset($A[$id]['numericValue'])) $metrics[$k] = ['value' => round((float) $A[$id]['numericValue'], 3), 'display' => (string) ($A[$id]['displayValue'] ?? ''), 'score' => $A[$id]['score'] ?? null];
        $img = function (?string $data): ?string {
            if (!$data || !preg_match('~^data:image/(jpeg|webp|png);base64,(.+)$~s', $data, $m)) return null;
            $b = base64_decode($m[2], true); return $b !== false && strlen($b) > 50 ? $b : null;
        };
        return ['scores' => $scores, 'metrics' => $metrics, 'issues' => $issues,
            'screenshot' => $img($A['final-screenshot']['details']['data'] ?? null), 'fullpage' => $img($A['full-page-screenshot']['details']['screenshot']['data'] ?? null),
            'finalUrl' => (string) ($L['finalDisplayedUrl'] ?? $L['finalUrl'] ?? ''), 'fetchTime' => (string) ($L['fetchTime'] ?? ''),
            'lighthouse' => (string) ($L['lighthouseVersion'] ?? ''), 'warnings' => array_slice(array_map('strval', $L['runWarnings'] ?? []), 0, 5),
            'device' => (string) ($L['configSettings']['formFactor'] ?? '')];
    }
}

/* Vizuální hodnocení snímku modelem s viděním přes OpenAI-kompatibilní rozhraní Cloudflare Workers AI.
   Formát obrázku (image_url s data URI) a model ověřit na stagingu (krok 5.2) – proto vše v konfiguraci. */
final class TsAuditCloudflareVision implements TsAuditVision
{
    private array $c;
    public function __construct(array $c)
    {
        $this->c = $c + ['model' => '@cf/google/gemma-4-26b-a4b-it', 'timeout' => 60, 'maxTokens' => 700, 'temperature' => 0.3];
        if (empty($this->c['apiKey'])) throw new TsAuditError('auth', 'chybí token Cloudflare');
        if (empty($this->c['baseUrl'])) $this->c['baseUrl'] = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode((string) ($this->c['accountId'] ?? '')) . '/ai/v1';
    }
    public function model(): string { return $this->c['model']; }
    public function review(string $jpeg, string $lang, array $context): array
    {
        $mime = substr($jpeg, 0, 4) === 'RIFF' ? 'image/webp' : (substr($jpeg, 0, 4) === "\x89PNG" ? 'image/png' : 'image/jpeg');
        $body = json_encode(['model' => $this->c['model'], 'max_tokens' => (int) $this->c['maxTokens'], 'temperature' => (float) $this->c['temperature'],
            'messages' => [['role' => 'system', 'content' => TsSiteAudit::visionPrompt($lang)],
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => TsSiteAudit::visionContext($context, $lang)], ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode($jpeg)]]]]]], JSON_UNESCAPED_UNICODE);
        [$code, $raw] = ts_audit_http('POST', rtrim($this->c['baseUrl'], '/') . '/chat/completions', ['Content-Type: application/json', 'Authorization: Bearer ' . $this->c['apiKey']], $body, (int) $this->c['timeout']);
        $j = json_decode($raw, true);
        if ($code === 401 || $code === 403) throw new TsAuditError('auth', 'http ' . $code);
        if ($code === 429 || ($code !== 200 && is_array($j) && preg_match('/allocation|quota|neurons/i', json_encode($j)))) throw new TsAuditError('limit', 'http ' . $code);
        if ($code !== 200 || !isset($j['choices'][0]['message']['content'])) throw new TsAuditError('request', 'http ' . $code);
        $u = $j['usage'] ?? [];
        return ['items' => TsSiteAudit::parseVision((string) $j['choices'][0]['message']['content']), 'usage' => ['in' => (int) ($u['prompt_tokens'] ?? 0), 'out' => (int) ($u['completion_tokens'] ?? 0)]];
    }
}

/* Falešné služby pro testy: nikam se nepřipojují. 'results' => ['mobile' => [...normalizovaný výsledek], 'desktop' => [...]] nebo 'fail' => 'limit'. */
final class TsAuditFakeMeter implements TsAuditMeter
{
    public array $calls = []; private array $c;
    public function __construct(array $c = []) { $this->c = $c; }
    public function measure(string $url, string $strategy, string $lang): array
    {
        $this->calls[] = [$url, $strategy, $lang];
        if (!empty($this->c['fail'])) throw new TsAuditError($this->c['fail'], 'simulovaná chyba měření');
        if (isset($this->c['results'][$strategy])) return $this->c['results'][$strategy];
        return TsAuditPageSpeed::normalize(self::sample($strategy));
    }
    /* Lighthouse JSON ve zkráceném tvaru (struktura jako skutečná odpověď API). */
    public static function sample(string $strategy): array
    {
        $jpeg = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 80) . "\xFF\xD9";
        $mob = $strategy !== 'desktop';
        return ['lighthouseResult' => ['lighthouseVersion' => '12.0.0', 'fetchTime' => '2026-11-01T20:00:00.000Z', 'finalDisplayedUrl' => 'https://example.cz/',
            'configSettings' => ['formFactor' => $mob ? 'mobile' : 'desktop'], 'runWarnings' => [],
            'categories' => [
                'performance' => ['score' => $mob ? 0.54 : 0.88, 'auditRefs' => [['id' => 'largest-contentful-paint', 'weight' => 25, 'group' => 'metrics'], ['id' => 'render-blocking-resources', 'weight' => 0], ['id' => 'uses-optimized-images', 'weight' => 0], ['id' => 'unused-javascript', 'weight' => 0]]],
                'accessibility' => ['score' => 0.81, 'auditRefs' => [['id' => 'color-contrast', 'weight' => 7], ['id' => 'image-alt', 'weight' => 10], ['id' => 'html-has-lang', 'weight' => 7]]],
                'best-practices' => ['score' => 0.96, 'auditRefs' => [['id' => 'is-on-https', 'weight' => 5]]],
                'seo' => ['score' => 0.9, 'auditRefs' => [['id' => 'meta-description', 'weight' => 1]]]],
            'audits' => [
                'largest-contentful-paint' => ['score' => $mob ? 0.31 : 0.8, 'numericValue' => $mob ? 4823.4 : 1650.2, 'displayValue' => $mob ? '4,8 s' : '1,7 s', 'scoreDisplayMode' => 'numeric'],
                'cumulative-layout-shift' => ['score' => 0.95, 'numericValue' => 0.041, 'displayValue' => '0,041', 'scoreDisplayMode' => 'numeric'],
                'total-blocking-time' => ['score' => 0.6, 'numericValue' => 410, 'displayValue' => '410 ms', 'scoreDisplayMode' => 'numeric'],
                'render-blocking-resources' => ['score' => 0.2, 'displayValue' => 'Potenciální úspora 1 200 ms', 'title' => 'Odstraňte zdroje blokující vykreslení', 'scoreDisplayMode' => 'metricSavings'],
                'uses-optimized-images' => ['score' => 0.4, 'displayValue' => 'Potenciální úspora 340 KiB', 'title' => 'Efektivně zakódujte obrázky', 'scoreDisplayMode' => 'metricSavings'],
                'unused-javascript' => ['score' => 0.95, 'title' => 'Omezte nepoužívaný JavaScript', 'scoreDisplayMode' => 'metricSavings'],
                'color-contrast' => ['score' => 0, 'title' => 'Barvy pozadí a popředí nemají dostatečný kontrast', 'scoreDisplayMode' => 'binary'],
                'image-alt' => ['score' => 0, 'title' => 'Obrázky nemají atributy <code>[alt]</code>', 'scoreDisplayMode' => 'binary'],
                'html-has-lang' => ['score' => 1, 'title' => 'Prvek html má atribut lang', 'scoreDisplayMode' => 'binary'],
                'is-on-https' => ['score' => 1, 'title' => 'Používá HTTPS', 'scoreDisplayMode' => 'binary'],
                'meta-description' => ['score' => 1, 'title' => 'Dokument má metapopis', 'scoreDisplayMode' => 'binary'],
                'final-screenshot' => ['details' => ['data' => 'data:image/jpeg;base64,' . base64_encode($jpeg)]],
                'full-page-screenshot' => ['details' => ['screenshot' => ['data' => 'data:image/webp;base64,' . base64_encode('RIFF' . str_repeat("\x01", 80))]]]]]];
    }
}

final class TsAuditFakeVision implements TsAuditVision
{
    public array $calls = []; private array $c;
    public function __construct(array $c = []) { $this->c = $c; }
    public function model(): string { return 'fake-vision'; }
    public function review(string $jpeg, string $lang, array $context): array
    {
        $this->calls[] = [strlen($jpeg), $lang, $context];
        if (!empty($this->c['fail'])) throw new TsAuditError($this->c['fail'], 'simulovaná chyba hodnocení');
        $text = $this->c['text'] ?? '{"items":[{"area":"hierarchie","text":"Hlavní nadpis splývá s pozadím, zvětšete kontrast a velikost.","priority":1},{"area":"výzva k akci","text":"Tlačítko poptávky není nad ohybem stránky vidět.","priority":2},{"area":"rychlost","text":"Web je o 70 % pomalejší než konkurence.","priority":1}]}';
        return ['items' => TsSiteAudit::parseVision($text), 'usage' => ['in' => 1500, 'out' => 200]];
    }
}
