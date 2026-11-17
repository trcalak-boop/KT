<?php
/* ==========================================================================
   ts-analytics – vlastní měření návštěvnosti (bez služeb třetích stran)
   POST <vstup>   { t: 'pv'|'ev', p: cesta, r?: odkud přišel, u?: {s, m, c} (UTM), l?: jazyk, w?: šířka okna, v: návštěvník, s: návštěva, n?: 1 = nový, e?: událost }
                  → 204. Posílá ho public/ts-analytics.js (sendBeacon / fetch keepalive), až když návštěvník souhlasí s měřením.
   report($od, $do) → souhrn pro administraci (TsAnalyticsReport::html).

   Ochrana dat a zneužití:
   - IP adresa se neukládá (jen její otisk pro limity v pevném okně), z odkazující stránky jen doména, z adresy stránky jen cesta (bez parametrů),
   - návštěvník a návštěva = náhodná ID z cookies prvního řádu (ts_v, ts_s), která nastaví až skript po souhlasu,
   - roboti podle User-Agent, požadavek bez hlavičky Origin nebo z cizího webu, příliš velké tělo a překročené limity se zahodí,
   - retence: zobrazení starší než retentionMonths (výchozí 14) se mažou nejvýš jednou denně při sběru.
   ========================================================================== */
final class TsAnalytics
{
    private array $c; private TsAnalyticsStore $store;

    public function __construct(array $c)
    {
        $this->c = $c + ['enabled' => true, 'salt' => 'ts-an', 'allowedOrigins' => [], 'requireOrigin' => true, 'retentionMonths' => 14,
            'limits' => [], 'ownHosts' => [], 'timezone' => '', 'blockBots' => true];
        $this->c['limits'] += ['perIpHour' => 600, 'globalDay' => 50000];
        $this->store = $c['store'];
    }
    public function store(): TsAnalyticsStore { return $this->store; }

    /* ---------- sběr ---------- */
    public function serve(): void
    {
        $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $raw = $m === 'POST' ? (string) file_get_contents('php://input', false, null, 0, 5000) : '';
        $h = []; foreach ($_SERVER as $k => $v) if (str_starts_with($k, 'HTTP_')) $h[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
        if (isset($_SERVER['CONTENT_TYPE'])) $h['content-type'] = $_SERVER['CONTENT_TYPE'];
        [$code, $out] = $this->handle($m, $raw, $h, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        http_response_code($code); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
        if ($out !== null) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($out); }
    }

    /* → [HTTP kód, tělo|null] */
    public function handle(string $method, string $raw, array $h, string $ip): array
    {
        try {
            if (!$this->c['enabled']) return [204, null];
            if ($method !== 'POST') return [405, ['ok' => false, 'error' => 'method']];
            if (!$this->originOk($h)) return [403, ['ok' => false, 'error' => 'origin']];
            if ($this->c['blockBots'] && self::isBot((string) ($h['user-agent'] ?? ''))) return [204, null];                      /* robotům nic nehlásit, jen nezapočítat */
            if (strlen($raw) > 4000) return [413, ['ok' => false, 'error' => 'size']];
            $b = json_decode($raw, true);
            if (!is_array($b)) return [400, ['ok' => false, 'error' => 'json']];
            $hit = $this->clean($b, (string) ($h['user-agent'] ?? ''), (string) ($h['host'] ?? ''));
            if ($hit === null) return [400, ['ok' => false, 'error' => 'data']];
            $iph = substr(hash('sha256', $this->c['salt'] . '|' . $ip), 0, 24);
            if (!$this->store->hit('ip:' . $iph, (int) $this->c['limits']['perIpHour'], 3600) || !$this->store->hit('global', (int) $this->c['limits']['globalDay'], 86400)) return [429, ['ok' => false, 'error' => 'limit']];
            $this->store->add($hit);
            if ($this->store->hit('cleanup', 1, 86400)) $this->store->cleanup((int) $this->c['retentionMonths']);
            return [204, null];
        } catch (Throwable $e) {
            error_log('ts-analytics: ' . get_class($e) . ': ' . $e->getMessage());
            return [500, ['ok' => false, 'error' => 'server']];
        }
    }
    private function originOk(array $h): bool
    {
        $o = rtrim((string) ($h['origin'] ?? ''), '/');
        if ($o === '') return !$this->c['requireOrigin'];
        if ($this->c['allowedOrigins']) return in_array($o, $this->c['allowedOrigins'], true);
        $host = (string) ($h['host'] ?? '');
        return $host !== '' && preg_replace('#^https?://#', '', $o) === $host;
    }
    public static function isBot(string $ua): bool
    {
        return $ua === '' || (bool) preg_match('~bot\b|bot/|crawl|spider|slurp|curl|wget|python|httpclient|okhttp|go-http|java/|libwww|scrapy|headless|phantom|puppeteer|playwright|selenium|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|preview|facebookexternalhit|embedly|whatsapp|telegram~i', $ua);
    }

    /* vstup → řádek tabulky, nebo null */
    private function clean(array $b, string $ua, string $host): ?array
    {
        $t = (string) ($b['t'] ?? ''); $vid = (string) ($b['v'] ?? ''); $sid = (string) ($b['s'] ?? '');
        if (!in_array($t, ['pv', 'ev'], true) || !preg_match('/^[a-z0-9]{16}$/', $vid) || !preg_match('/^[a-z0-9]{16}$/', $sid)) return null;
        $path = (string) ($b['p'] ?? '');
        $path = preg_replace('~[?#].*$~s', '', $path);
        if ($path === '' || $path[0] !== '/' || strlen($path) > 200 || preg_match('~[\x00-\x1F\x7F<>"\\\\]~', $path)) return null;
        $ev = '';
        if ($t === 'ev') { $ev = (string) ($b['e'] ?? ''); if (!preg_match('/^[a-z0-9_]{1,40}$/', $ev)) return null; }
        $ref = '';
        if (!empty($b['r']) && is_string($b['r'])) {
            $rh = strtolower((string) parse_url($b['r'], PHP_URL_HOST));
            $rh = preg_replace('/^www\./', '', $rh);
            $own = array_map(fn ($x) => preg_replace('/^www\./', '', strtolower($x)), array_merge((array) $this->c['ownHosts'], [$host]));
            if ($rh !== '' && preg_match('/^[a-z0-9.-]{1,100}$/', $rh) && !in_array($rh, $own, true)) $ref = $rh;
        }
        $u = is_array($b['u'] ?? null) ? $b['u'] : [];
        $txt = fn ($v, int $max) => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F<>"]/u', '', is_scalar($v) ? (string) $v : '') ?? ''), 0, $max);
        $w = (int) ($b['w'] ?? 0);
        [$br, $os] = self::agent($ua);
        return ['ts' => time(), 'day' => date('Y-m-d'), 'vid' => $vid, 'sid' => $sid, 'newv' => !empty($b['n']) ? 1 : 0, 'kind' => $t, 'path' => mb_substr($path, 0, 200),
            'ref' => $ref, 'src' => mb_strtolower($txt($u['s'] ?? '', 60)), 'med' => mb_strtolower($txt($u['m'] ?? '', 60)), 'camp' => $txt($u['c'] ?? '', 80),
            'lang' => preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', (string) ($b['l'] ?? '')) ? (string) $b['l'] : '',
            'dev' => $w <= 0 ? '' : ($w < 768 ? 'm' : ($w < 1100 ? 't' : 'd')), 'br' => $br, 'os' => $os, 'ev' => $ev];
    }
    /* rodina prohlížeče a systém (jen hrubě, plný User-Agent se neukládá) */
    public static function agent(string $ua): array
    {
        $br = preg_match('~Edg/~', $ua) ? 'Edge' : (preg_match('~OPR/|Opera~', $ua) ? 'Opera' : (preg_match('~SamsungBrowser~', $ua) ? 'Samsung' : (preg_match('~Firefox/|FxiOS~', $ua) ? 'Firefox'
            : (preg_match('~Chrome/|CriOS~', $ua) ? 'Chrome' : (preg_match('~Safari/~', $ua) ? 'Safari' : 'Jiný')))));
        $os = preg_match('~Android~', $ua) ? 'Android' : (preg_match('~iPhone|iPad|iPod~', $ua) ? 'iOS' : (preg_match('~Windows~', $ua) ? 'Windows'
            : (preg_match('~Mac OS X|Macintosh~', $ua) ? 'macOS' : (preg_match('~Linux|CrOS~', $ua) ? 'Linux' : 'Jiný'))));
        return [$br, $os];
    }

    /* ---------- přehled ---------- */
    const SEARCH = '~(^|\.)(google|bing|seznam|duckduckgo|yahoo|yandex|ecosia|baidu|naver|brave|qwant|startpage)\.~';
    const SOCIAL = '~(^|\.)(facebook|fb|instagram|linkedin|lnkd|t|x|twitter|tiktok|pinterest|youtube|reddit|threads)\.(com|co|me|net|in)$|^m\.facebook\.com$|^l\.instagram\.com$~';

    public static function channel(array $first): string
    {
        if ($first['src'] !== '' || $first['camp'] !== '') return 'Kampaně';
        if ($first['ref'] === '') return 'Přímý přístup';
        if (preg_match(self::SEARCH, $first['ref'])) return 'Vyhledávače';
        if (preg_match(self::SOCIAL, $first['ref'])) return 'Sociální sítě';
        return 'Odkazy z webů';
    }

    /* souhrn za období (dny včetně, Y-m-d) */
    public function report(string $from, string $to): array
    {
        $q = fn (string $sql, array $p = []) => $this->store->q($sql, $p)->fetchAll();
        $tot = function (string $a, string $b) use ($q): array {
            $r = $q("SELECT COUNT(CASE WHEN kind = 'pv' THEN 1 END) pv, COUNT(DISTINCT vid) visitors, COUNT(DISTINCT sid) sessions, COUNT(DISTINCT CASE WHEN newv = 1 THEN vid END) newv FROM ts_an_hits WHERE day BETWEEN ? AND ?", [$a, $b])[0];
            $s = $q("SELECT COUNT(*) n, SUM(CASE WHEN pv = 1 THEN 1 ELSE 0 END) bounce, SUM(CASE WHEN pv > 1 THEN dur ELSE 0 END) dur, SUM(CASE WHEN pv > 1 THEN 1 ELSE 0 END) multi
                     FROM (SELECT sid, COUNT(CASE WHEN kind = 'pv' THEN 1 END) pv, MAX(ts) - MIN(ts) dur FROM ts_an_hits WHERE day BETWEEN ? AND ? GROUP BY sid) x", [$a, $b])[0];
            $n = (int) $s['n'];
            return ['pageviews' => (int) $r['pv'], 'visitors' => (int) $r['visitors'], 'sessions' => (int) $r['sessions'], 'newVisitors' => (int) $r['newv'],
                'pagesPerSession' => $n ? round((int) $r['pv'] / $n, 2) : 0.0, 'bounceRate' => $n ? round((int) $s['bounce'] / $n * 100, 1) : 0.0,
                'avgDuration' => (int) $s['multi'] ? (int) round((int) $s['dur'] / (int) $s['multi']) : 0];
        };
        $days = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day')); $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));
        $daily = []; for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) $daily[$d] = ['day' => $d, 'pageviews' => 0, 'visitors' => 0, 'sessions' => 0];
        foreach ($q("SELECT day, COUNT(CASE WHEN kind = 'pv' THEN 1 END) pv, COUNT(DISTINCT vid) v, COUNT(DISTINCT sid) s FROM ts_an_hits WHERE day BETWEEN ? AND ? GROUP BY day", [$from, $to]) as $r)
            if (isset($daily[$r['day']])) $daily[$r['day']] = ['day' => $r['day'], 'pageviews' => (int) $r['pv'], 'visitors' => (int) $r['v'], 'sessions' => (int) $r['s']];
        $pages = array_map(fn ($r) => ['path' => $r['path'], 'pageviews' => (int) $r['pv'], 'visitors' => (int) $r['v']],
            $q("SELECT path, COUNT(*) pv, COUNT(DISTINCT vid) v FROM ts_an_hits WHERE kind = 'pv' AND day BETWEEN ? AND ? GROUP BY path ORDER BY pv DESC, path LIMIT 20", [$from, $to]));
        /* zdroj, jazyk a zařízení podle prvního záznamu návštěvy */
        $first = $q("SELECT h.* FROM ts_an_hits h JOIN (SELECT sid, MIN(id) mid FROM ts_an_hits WHERE day BETWEEN ? AND ? GROUP BY sid) f ON h.id = f.mid", [$from, $to]);
        $count = function (callable $key) use ($first): array {
            $o = []; foreach ($first as $r) { $k = $key($r); if ($k === null) continue; $o[$k] = ($o[$k] ?? 0) + 1; }
            arsort($o); $out = []; foreach (array_slice($o, 0, 15, true) as $k => $n) $out[] = ['label' => (string) $k, 'sessions' => $n]; return $out;
        };
        $devName = ['m' => 'Telefon', 't' => 'Tablet', 'd' => 'Počítač', '' => 'Neznámé'];
        $ev = $q("SELECT ev, COUNT(*) n, COUNT(DISTINCT sid) s FROM ts_an_hits WHERE kind = 'ev' AND day BETWEEN ? AND ? GROUP BY ev ORDER BY n DESC", [$from, $to]);
        $sessions = count($first);
        return ['from' => $from, 'to' => $to, 'days' => $days, 'totals' => $tot($from, $to), 'previous' => ['from' => $prevFrom, 'to' => $prevTo] + $tot($prevFrom, $prevTo),
            'daily' => array_values($daily), 'pages' => $pages,
            'channels' => $count(fn ($r) => self::channel($r)),
            'referrers' => $count(fn ($r) => $r['ref'] !== '' ? $r['ref'] : null),
            'campaigns' => $count(fn ($r) => $r['src'] !== '' || $r['camp'] !== '' ? trim($r['src'] . ($r['med'] !== '' ? ' / ' . $r['med'] : '') . ($r['camp'] !== '' ? ' · ' . $r['camp'] : '')) : null),
            'languages' => $count(fn ($r) => $r['lang'] !== '' ? $r['lang'] : 'neuvedeno'),
            'devices' => $count(fn ($r) => $devName[$r['dev']] ?? 'Neznámé'),
            'browsers' => $count(fn ($r) => $r['br'] ?: 'Jiný'), 'systems' => $count(fn ($r) => $r['os'] ?: 'Jiný'),
            'events' => array_map(fn ($r) => ['event' => $r['ev'], 'count' => (int) $r['n'], 'sessions' => (int) $r['s'], 'rate' => $sessions ? round((int) $r['s'] / $sessions * 100, 1) : 0.0], $ev)];
    }
}
