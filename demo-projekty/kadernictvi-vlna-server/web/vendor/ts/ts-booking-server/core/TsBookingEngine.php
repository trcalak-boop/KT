<?php
/* ==========================================================================
   ts-booking-server – PHP port jádra ts-booking-core (volné časy, kolize, pool, kapacita, prepareBooking)
   Chování je shodné s components/ts-booking-core/core/booking-core.js – ověřují tests/differential_test.py
   (stejná konfigurace a data → stejné výsledky JS i PHP, data z ukázek Lokna, Motoria, Kořen).
   Rozdíl proti JS: „dnes“ a „teď“ se počítají v časovém pásmu provozovny (config.ics.timezone), ne zařízení.

   Neupravovat bez nové verze obou jader a rozdílových testů.
   ========================================================================== */
final class TsBookingEngine
{
    public const VERSION = '1.0.0';
    public const DEFAULTS = [
        'mode' => 'resources', 'step' => 15, 'minStep' => 5, 'gap' => 0, 'leadMin' => 0, 'leadScope' => 'all-days', 'adminMinStart' => 'none',
        'maxDays' => 60, 'activeStatuses' => ['pending', 'confirmed'], 'hours' => null, 'closures' => [], 'fullDayBlockCloses' => true,
        'blocksIncludeBuffer' => true, 'services' => [], 'addons' => [], 'resources' => [], 'pool' => null,
        'spread' => ['perDay' => 2, 'minGapMin' => 90], 'capacity' => null,
        'ics' => ['prodId' => '-//T&S Studio//ts-booking-core//CS', 'uidDomain' => 'booking.local', 'timezone' => 'Europe/Prague', 'alarmMin' => 120],
    ];

    private array $c; private array $s; private array $ext;
    /** @var callable */ private $clockToday; /** @var callable */ private $clockNow;

    /* state = ['bookings' => [...], 'blocks' => [...]]; opts: clock => ['today' => 'YYYY-MM-DD', 'nowMin' => int] | callable, extensions => [callable(array $ctx): string] */
    public function __construct(array $config, array $state = [], array $opts = [])
    {
        $this->c = self::merge($config);
        $this->s = $state + ['bookings' => [], 'blocks' => []];
        $this->ext = $opts['extensions'] ?? [];
        $tz = new DateTimeZone($this->c['ics']['timezone'] ?: 'Europe/Prague');
        $clk = $opts['clock'] ?? null;
        if (is_array($clk)) { $t = $clk['today']; $n = (int) $clk['nowMin']; $this->clockToday = fn() => $t; $this->clockNow = fn() => $n; }
        else {
            $this->clockToday = fn() => (new DateTimeImmutable('now', $tz))->format('Y-m-d');
            $this->clockNow = function () use ($tz) { $d = new DateTimeImmutable('now', $tz); return (int) $d->format('G') * 60 + (int) $d->format('i'); };
        }
    }

    public static function merge(array $cfg): array
    {
        $c = self::DEFAULTS;
        foreach ($cfg as $k => $v) if ($v !== null || !array_key_exists($k, $c)) $c[$k] = $v;
        $c['ics'] = ($cfg['ics'] ?? []) + self::DEFAULTS['ics'];
        $c['spread'] = ($cfg['spread'] ?? []) + self::DEFAULTS['spread'];
        return $c;
    }
    public function config(): array { return $this->c; }
    public function setState(array $state): void { $this->s = $state + ['bookings' => [], 'blocks' => []]; }
    public function today(): string { return ($this->clockToday)(); }
    public function nowMin(): int { return ($this->clockNow)(); }

    /* ---------- datum (řetězce YYYY-MM-DD, poledne UTC kvůli změně času) ---------- */
    private static function d(string $s): DateTimeImmutable { return new DateTimeImmutable($s . ' 12:00:00', new DateTimeZone('UTC')); }
    public static function addDays(string $s, int $n): string { return self::d($s)->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d'); }
    public static function diffDays(string $a, string $b): int { return (int) round((self::d($b)->getTimestamp() - self::d($a)->getTimestamp()) / 86400); }
    public static function wd(string $s): int { return (int) self::d($s)->format('w'); }
    public static function hm(int $m): string { return intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT); }

    /* ---------- validace (shodné hlášky s JS) ---------- */
    public static function validate(array $cfg): array
    {
        $e = []; $c = self::merge($cfg);
        if (!in_array($c['mode'], ['resources', 'capacity'], true)) $e[] = 'mode: resources | capacity';
        if (!is_array($c['hours']) || count($c['hours']) !== 7) $e[] = 'hours: pole 7 dnů (0 = neděle)';
        else foreach ($c['hours'] as $i => $h) if ($h && !empty($h['open']) && !($h['to'] > $h['from'])) $e[] = "hours[$i]: to musí být větší než from";
        if (!(($c['step'] ?? 0) > 0)) $e[] = 'step: kladné číslo';
        $ids = [];
        foreach ($c['services'] ?: [] as $i => $s) {
            if (empty($s['id'])) $e[] = "services[$i]: chybí id";
            if (isset($ids['s:' . ($s['id'] ?? '')])) $e[] = 'services: duplicitní id ' . $s['id']; $ids['s:' . ($s['id'] ?? '')] = 1;
            if (empty($s['durations']) || !is_array($s['durations'])) $e[] = "services[$i]: durations";
        }
        foreach ($c['resources'] ?: [] as $i => $r) {
            if (empty($r['id'])) $e[] = "resources[$i]: chybí id";
            if (isset($ids['r:' . ($r['id'] ?? '')])) $e[] = 'resources: duplicitní id ' . $r['id']; $ids['r:' . ($r['id'] ?? '')] = 1;
            if (($r['services'] ?? null) !== '*' && !is_array($r['services'] ?? null)) $e[] = "resources[$i]: services = \"*\" nebo pole id";
        }
        if ($c['mode'] === 'resources' && empty($c['resources'])) $e[] = 'resources: alespoň jeden zdroj';
        if ($c['mode'] === 'capacity' && !(($c['capacity']['units'] ?? 0) > 0)) $e[] = 'capacity.units: kladné číslo';
        return $e;
    }

    /* ---------- pomocné ---------- */
    private function active(array $b): bool { return in_array($b['status'] ?? '', $this->c['activeStatuses'], true); }
    private static function blockAll(array $k): bool { return !isset($k['resource']) || $k['resource'] === 'all'; }
    public function service(?string $id): ?array { foreach ($this->c['services'] ?: [] as $s) if ($s['id'] === $id) return $s; return null; }
    public function resource(?string $id): ?array { foreach ($this->c['resources'] ?: [] as $r) if ($r['id'] === $id) return $r; return null; }
    public function resourceIndex(string $id): int { foreach (array_values($this->c['resources'] ?: []) as $i => $r) if ($r['id'] === $id) return $i; return 0; }
    public function canDo(?array $r, string $svc): bool { return $r !== null && (($r['services'] ?? null) === '*' || in_array($svc, (array) ($r['services'] ?? []), true)); }
    public function resourcesFor(string $svc): array { return array_values(array_filter($this->c['resources'] ?: [], fn($r) => ($r['bookable'] ?? true) !== false && $this->canDo($r, $svc))); }

    public function calc(?string $svcId, $variant = 0, array $addons = []): array
    {
        $s = $this->service($svcId); if (!$s) return ['dur' => 0, 'price' => 0, 'buffer' => 0];
        $n = count($s['durations']) - 1;
        $v = max(0, min($n, is_numeric($variant) ? (int) $variant : 0));
        $dur = $s['durations'][$v]; $price = ($s['prices'] ?? [])[$v] ?? 0; $price = $price ?: 0;
        foreach ($addons as $id) {
            foreach ($this->c['addons'] ?: [] as $x) {
                if ($x['id'] === $id && in_array($svcId, $x['services'] ?? [], true)) {
                    $dur += $x['durations'][min($v, count($x['durations']) - 1)];
                    $pp = $x['prices'] ?? [0]; $price += ($pp[min($v, count($pp) - 1)] ?? 0) ?: 0;
                    break;
                }
            }
        }
        return ['dur' => $dur, 'price' => $price, 'buffer' => $s['buffer'] ?? 0];
    }

    /* ---------- otevírací doba ---------- */
    public function closureFor(string $date): ?array { foreach ($this->c['closures'] ?: [] as $k) if ($date >= $k['from'] && $date <= $k['to']) return $k; return null; }
    public function dayHours(string $date): array
    {
        $k = $this->closureFor($date);
        if ($k) return !empty($k['open']) && !empty($k['hours']) ? ['open' => true, 'from' => $k['hours'][0], 'to' => $k['hours'][1], 'note' => $k['note'] ?? null] : ['open' => false, 'note' => $k['note'] ?? null];
        $h = $this->c['hours'][self::wd($date)] ?? null;
        if (!$h || empty($h['open']) || $h['to'] <= $h['from']) return ['open' => false];
        if ($this->c['fullDayBlockCloses']) foreach ($this->s['blocks'] as $b) if (self::blockAll($b) && $b['date'] === $date && $b['from'] <= $h['from'] && $b['to'] >= $h['to']) return ['open' => false];
        return ['open' => true, 'from' => $h['from'], 'to' => $h['to']];
    }
    public function away(?array $r, string $date): ?array { foreach (($r['away'] ?? null) ?: [] as $a) if ($date >= $a['from'] && $date <= $a['to']) return $a; return null; }
    public function window(?array $r, string $date): ?array
    {
        if (!$r || ($r['bookable'] ?? true) === false) return null;
        $d = $this->dayHours($date); if (!$d['open']) return null;
        $a = $d['from']; $b = $d['to'];
        if (!empty($r['hours'])) { $h = $r['hours'][self::wd($date)] ?? null; if (!$h || empty($h['open'])) return null; $a = max($a, $h['from']); $b = min($b, $h['to']); }
        if ($this->away($r, $date)) return null;
        return $b > $a ? [$a, $b] : null;
    }

    /* ---------- obsazenost ---------- */
    public function busy(string $resId, string $date, ?string $except = null): array
    {
        $out = [];
        foreach ($this->s['bookings'] as $b) {
            if ($b['date'] !== $date || ($b['resource'] ?? null) !== $resId || !$this->active($b) || ($except !== null && ($b['id'] ?? null) === $except)) continue;
            $out[] = [$b['start'], $b['start'] + $b['dur'] + ($b['buffer'] ?? 0) + $this->c['gap']];
        }
        return $out;
    }
    public function blocksFor(string $resId, string $date): array
    {
        $out = [];
        foreach ($this->s['blocks'] as $k) if ($k['date'] === $date && (($k['resource'] ?? null) === $resId || self::blockAll($k))) $out[] = [$k['from'], $k['to']];
        return $out;
    }
    public function inRange(string $date): bool { $dd = self::diffDays($this->today(), $date); return $dd >= 0 && $dd <= $this->c['maxDays']; }
    public function minStart(string $date, bool $admin = false): ?int
    {
        if ($admin && $this->c['adminMinStart'] !== 'now') return null;
        $lead = $admin ? 0 : (int) ($this->c['leadMin'] ?: 0);
        if ($this->c['leadScope'] === 'today-only') return $date === $this->today() ? $this->nowMin() + $lead : null;
        return $this->nowMin() + $lead - self::diffDays($this->today(), $date) * 1440;
    }

    /* o: variant, addons, admin, except → [['t','ok','why']] */
    public function scan(string $date, string $resId, string $svcId, array $o = []): array
    {
        $r = $this->resource($resId); $w = $this->window($r, $date); if (!$w) return [];
        $k = $this->calc($svcId, $o['variant'] ?? 0, $o['addons'] ?? []); if (!$k['dur']) return [];
        $step = max($this->c['minStep'], $this->c['step']); $dur = $k['dur']; $after = $k['buffer'] + $this->c['gap'];
        $ms = $this->minStart($date, !empty($o['admin'])); $except = $o['except'] ?? null;
        $busy = $this->busy($resId, $date, $except); $blk = $this->blocksFor($resId, $date);
        $pool = !empty($this->c['pool']) && isset($this->c['pool']['members']) ? (count($this->c['pool']['members']) ?: 1) : 0;
        $all = $pool ? array_values(array_filter($this->s['bookings'], fn($b) => $b['date'] === $date && $this->active($b) && ($except === null || ($b['id'] ?? null) !== $except))) : [];
        $out = [];
        for ($t = $w[0]; $t + $dur <= $w[1]; $t += $step) {
            $why = ''; $end = $t + $dur + $after;
            if ($ms !== null && $t < $ms) $why = 'past';
            else {
                foreach ($busy as $b) if ($t < $b[1] && $b[0] < $end) { $why = 'busy'; break; }
                if ($why === '') foreach ($blk as $b) if ($t < $b[1] && $b[0] < ($this->c['blocksIncludeBuffer'] ? $end : $t + $dur)) { $why = 'block'; break; }
                if ($why === '' && $pool) { $n = 0; foreach ($all as $b) if ($t < $b['start'] + $b['dur'] && $b['start'] < $t + $dur) $n++; if ($n >= $pool) $why = 'pool'; }
                if ($why === '') foreach ($this->ext as $x) { $why = (string) $x(['engine' => $this, 'date' => $date, 't' => $t, 'dur' => $dur, 'resource' => $r, 'service' => $svcId, 'opts' => $o]); if ($why !== '') break; }
            }
            $out[] = ['t' => $t, 'ok' => $why === '', 'why' => $why];
        }
        return $out;
    }
    public function freeTimes(string $date, string $resId, string $svcId, array $o = []): array { return array_values(array_map(fn($s) => $s['t'], array_filter($this->scan($date, $resId, $svcId, $o), fn($s) => $s['ok']))); }

    /* o: variant, addons, resource (id|'any'), admin, except → [['t', 'resources' => [...]]] */
    public function slots(string $date, string $svcId, array $o = []): array
    {
        $admin = !empty($o['admin']);
        if (!$admin && !$this->inRange($date)) return [];
        if (!$this->calc($svcId, $o['variant'] ?? 0, $o['addons'] ?? [])['dur']) return [];
        $rid = $o['resource'] ?? 'any';
        if ($rid === 'any' || !$rid) $rs = $this->resourcesFor($svcId);
        else { $r = $this->resource($rid); $rs = $r && ($admin || ($r['bookable'] ?? true) !== false) && $this->canDo($r, $svcId) ? [$r] : []; }
        $map = [];
        foreach ($rs as $r) foreach ($this->freeTimes($date, $r['id'], $svcId, $o) as $t) $map[$t][] = $r['id'];
        ksort($map, SORT_NUMERIC);
        $out = []; foreach ($map as $t => $ids) $out[] = ['t' => (int) $t, 'resources' => $ids];
        return $out;
    }
    public function pickResource(string $date, array $ids): ?string
    {
        $load = function ($id) use ($date) { $s = 0; foreach ($this->s['bookings'] as $b) if ($b['date'] === $date && ($b['resource'] ?? null) === $id && $this->active($b)) $s += $b['dur']; return $s; };
        usort($ids, fn($a, $b) => ($load($a) <=> $load($b)) ?: ($this->resourceIndex($a) <=> $this->resourceIndex($b)));
        return $ids[0] ?? null;
    }
    public function pickPoolMember(string $date, int $time, int $dur, ?string $except = null): string
    {
        $ds = array_values(array_filter($this->s['bookings'], fn($b) => $b['date'] === $date && $this->active($b) && ($except === null || ($b['id'] ?? null) !== $except)));
        $free = array_values(array_filter($this->c['pool']['members'] ?? [], function ($m) use ($ds, $time, $dur) {
            foreach ($ds as $b) if (($b['poolMember'] ?? null) === $m && $time < $b['start'] + $b['dur'] && $b['start'] < $time + $dur) return false;
            return true;
        }));
        $cnt = function ($m) use ($ds) { $n = 0; foreach ($ds as $d) if (($d['poolMember'] ?? null) === $m) $n++; return $n; };
        usort($free, fn($a, $b) => $cnt($a) <=> $cnt($b));
        return $free[0] ?? '';
    }
    public function dayState(string $date, string $svcId, array $o = []): array
    {
        if ($date < $this->today()) return ['s' => 'past', 'n' => 0];
        if (!$this->inRange($date) || !$this->dayHours($date)['open']) return ['s' => 'closed', 'n' => 0];
        $n = count($this->slots($date, $svcId, $o));
        return ['s' => $n ? 'free' : 'full', 'n' => $n];
    }
    public function nextFree(string $svcId, array $o, int $limit): array
    {
        $out = []; $t0 = $this->today(); $per = $this->c['spread']['perDay']; $gap = $this->c['spread']['minGapMin'];
        for ($i = 0; $i <= $this->c['maxDays'] && count($out) < $limit; $i++) {
            $d = self::addDays($t0, $i); $taken = 0; $last = -999;
            foreach ($this->slots($d, $svcId, $o) as $x) {
                if (count($out) >= $limit || $taken >= $per || $x['t'] - $last < $gap) continue;
                $out[] = ['date' => $d, 't' => $x['t'], 'resource' => count($x['resources']) > 1 ? $this->pickResource($d, $x['resources']) : $x['resources'][0]];
                $taken++; $last = $x['t'];
            }
        }
        return $out;
    }
    public function conflicts(string $date, int $start, int $dur, int $buffer, string $resId, ?string $except = null): array
    {
        $end = $start + $dur + $buffer; $items = [];
        foreach ($this->s['bookings'] as $b) if ($b['date'] === $date && ($b['resource'] ?? null) === $resId && $this->active($b) && ($except === null || ($b['id'] ?? null) !== $except) && $start < $b['start'] + $b['dur'] + ($b['buffer'] ?? 0) && $b['start'] < $end) $items[] = ['type' => 'booking', 'id' => $b['id'], 'start' => $b['start'], 'name' => $b['name'] ?? ''];
        foreach ($this->s['blocks'] as $k) if ($k['date'] === $date && (($k['resource'] ?? null) === $resId || self::blockAll($k)) && $start < $k['to'] && $k['from'] < $end) $items[] = ['type' => 'block', 'reason' => $k['reason'] ?? ''];
        $r = $this->resource($resId); $w = $this->window($r, $date);
        return ['items' => $items, 'inHours' => (bool) $w && $start >= $w[0] && $start + $dur <= $w[1], 'away' => $r ? $this->away($r, $date) : null];
    }

    /* ---------- kapacita ---------- */
    private function capDur(?string $svc): int { $s = $this->service($svc); return $s ? $s['durations'][0] : 0; }
    public function capacityLoad(string $date, int $t, int $dur, ?string $except = null): int
    {
        $sample = $this->c['capacity']['sampleMin'] ?? 15; $max = 0;
        $bs = array_filter($this->s['bookings'], fn($b) => $b['date'] === $date && $this->active($b) && ($except === null || ($b['id'] ?? null) !== $except));
        for ($m = $t; $m < $t + $dur; $m += $sample) {
            $s = 0; foreach ($bs as $b) { $e = $b['start'] + $this->capDur($b['service'] ?? null); if ($b['start'] <= $m && $m < $e) $s += ($b['units'] ?? 1) ?: 1; }
            if ($s > $max) $max = $s;
        }
        return $max;
    }
    public function capacityTimes(string $date, int $units, string $svcId, ?string $except = null): array
    {
        $cap = $this->c['capacity'] ?: []; $h = $this->dayHours($date); if (!$h['open']) return [];
        $dur = $this->capDur($svcId);
        $st = isset($cap['serviceStart'][$svcId]) ? max($h['from'], $cap['serviceStart'][$svcId]) : $h['from'];
        $last = $h['to'] - ($cap['lastBeforeCloseMin'] ?? 0); $now = $this->nowMin(); $today = $date === $this->today(); $out = [];
        for ($t = $st; $t <= $last; $t += ($cap['step'] ?? $this->c['step'])) {
            $left = $cap['units'] - $this->capacityLoad($date, $t, min($dur, $h['to'] - $t), $except);
            $out[] = ['t' => $t, 'ok' => $left >= $units && !($today && $t < $now + ($cap['todayLeadMin'] ?? 0)), 'left' => $left];
        }
        return $out;
    }

    /* ---------- založení rezervace (stejná pravidla jako JS; na serveru se volá v transakci) ---------- */
    public function prepareBooking(array $req): array
    {
        $o = ['variant' => $req['variant'] ?? 0, 'addons' => $req['addons'] ?? [], 'admin' => !empty($req['admin']), 'except' => $req['except'] ?? null];
        if (!$this->service($req['service'] ?? null)) return ['ok' => false, 'error' => 'service'];
        $b = $req; unset($b['admin'], $b['except']);
        $b['status'] = $req['status'] ?? $this->c['activeStatuses'][0];
        if ($this->c['mode'] === 'capacity') {
            $units = (int) ($req['units'] ?? 1) ?: 1; $slot = null;
            foreach ($this->capacityTimes($req['date'], $units, $req['service'], $o['except']) as $x) if ($x['t'] === (int) $req['start']) { $slot = $x; break; }
            if (!$slot) return ['ok' => false, 'error' => 'closed'];
            if (!$slot['ok']) return ['ok' => false, 'error' => 'capacity'];
            $b['units'] = $units; $b['dur'] = $this->capDur($req['service']); unset($b['resource'], $b['variant'], $b['addons']);
            return ['ok' => true, 'booking' => $b];
        }
        if (!$o['admin'] && !$this->inRange($req['date'])) return ['ok' => false, 'error' => 'closed'];
        $cand = !empty($req['resource']) && $req['resource'] !== 'any' ? [$req['resource']] : array_map(fn($r) => $r['id'], $this->resourcesFor($req['service']));
        $why = 'closed'; $free = [];
        foreach ($cand as $id) {
            $s = null; foreach ($this->scan($req['date'], $id, $req['service'], $o) as $x) if ($x['t'] === (int) $req['start']) { $s = $x; break; }
            if ($s && !$s['ok'] && $why === 'closed') $why = in_array($s['why'], ['busy', 'block'], true) ? 'taken' : $s['why'];
            if ($s && $s['ok']) $free[] = $id;
        }
        if (!$free) return ['ok' => false, 'error' => $why];
        $calc = $this->calc($req['service'], $o['variant'], $o['addons']);
        $b['resource'] = count($free) > 1 ? $this->pickResource($req['date'], $free) : $free[0];
        $b['dur'] = $calc['dur']; $b['buffer'] = $calc['buffer']; $b['price'] = $calc['price'];
        if (!empty($this->c['pool']['members'])) $b['poolMember'] = $this->pickPoolMember($req['date'], (int) $req['start'], $calc['dur'], $o['except']);
        return ['ok' => true, 'booking' => $b];
    }
}
