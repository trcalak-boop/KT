<?php
/* ==========================================================================
   ts-analytics – přehled návštěvnosti jako HTML (administrace). Bez JavaScriptu, graf je inline SVG.
   TsAnalyticsReport::html($an->report($od, $do), ['events' => ['poptavka' => 'Odeslaná poptávka', …], 'periods' => [7, 30, 90], 'url' => fn(int $dni): string])
   Styl: public/ts-analytics-report.css (třídy .tsan-*). Vše escapované.
   ========================================================================== */
final class TsAnalyticsReport
{
    private static function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private static function n(int|float $v): string { return number_format($v, is_float($v) && floor($v) != $v ? 1 : 0, ',', "\u{00a0}"); }
    private static function dur(int $s): string { return $s < 60 ? $s . ' s' : intdiv($s, 60) . ' min ' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) . ' s'; }
    private static function delta(float $now, float $prev, bool $lowerBetter = false): string
    {
        if ($prev <= 0) return $now > 0 ? '<span class="tsan-d">nové</span>' : '';
        $p = round(($now - $prev) / $prev * 100);
        $cls = $p == 0 ? '' : (($p > 0) !== $lowerBetter ? ' tsan-up' : ' tsan-down');
        return '<span class="tsan-d' . $cls . '">' . ($p > 0 ? '+' : '') . $p . "\u{00a0}%</span>";
    }

    public static function html(array $r, array $o = []): string
    {
        $o += ['events' => [], 'periods' => [7, 30, 90], 'url' => null, 'title' => 'Návštěvnost'];
        $t = $r['totals']; $p = $r['previous'];
        $fmt = fn (string $d) => date('j. n. Y', strtotime($d));
        $h = '<div class="tsan">';
        $h .= '<div class="tsan-head"><h2>' . self::e($o['title']) . '</h2><p class="tsan-range">' . self::e($fmt($r['from']) . ' – ' . $fmt($r['to'])) . ' <span>(srovnání s předchozími ' . (int) $r['days'] . ' dny)</span></p>';
        if (is_callable($o['url'])) {
            $h .= '<nav class="tsan-periods" aria-label="Období">';
            foreach ($o['periods'] as $d) $h .= '<a href="' . self::e(($o['url'])($d)) . '"' . ((int) $r['days'] === (int) $d ? ' aria-current="page"' : '') . '>' . (int) $d . ' dní</a>';
            $h .= '</nav>';
        }
        $h .= '</div>';
        $cards = [['Návštěvníci', self::n($t['visitors']), self::delta($t['visitors'], $p['visitors'])], ['Návštěvy', self::n($t['sessions']), self::delta($t['sessions'], $p['sessions'])],
            ['Zobrazení stránek', self::n($t['pageviews']), self::delta($t['pageviews'], $p['pageviews'])], ['Stránek na návštěvu', self::n((float) $t['pagesPerSession']), self::delta($t['pagesPerSession'], $p['pagesPerSession'])],
            ['Odchod po 1 stránce', self::n((float) $t['bounceRate']) . "\u{00a0}%", self::delta($t['bounceRate'], $p['bounceRate'], true)], ['Průměrná délka návštěvy', self::dur($t['avgDuration']), self::delta($t['avgDuration'], $p['avgDuration'])]];
        $h .= '<div class="tsan-cards">';
        foreach ($cards as [$l, $v, $d]) $h .= '<div class="tsan-card"><p class="tsan-l">' . self::e($l) . '</p><p class="tsan-v">' . self::e($v) . '</p>' . $d . '</div>';
        $h .= '</div>';
        $h .= self::chart($r['daily']);
        if ($t['sessions'] === 0) $h .= '<p class="tsan-empty">Za toto období zatím nejsou žádná data. Měří se jen návštěvníci, kteří v liště cookies povolili měření návštěvnosti.</p>';
        $ev = array_map(fn ($x) => ['label' => $o['events'][$x['event']] ?? $x['event'], 'value' => $x['count'], 'extra' => self::n((float) $x['rate']) . "\u{00a0}% návštěv"], $r['events']);
        $h .= '<div class="tsan-grid">';
        $h .= self::table('Nejnavštěvovanější stránky', 'Stránka', 'Zobrazení', array_map(fn ($x) => ['label' => $x['path'], 'value' => $x['pageviews'], 'extra' => self::n($x['visitors']) . ' návštěvníků'], $r['pages']), true);
        $h .= self::table('Odkud přicházejí', 'Kanál', 'Návštěvy', self::rows($r['channels']));
        $h .= self::table('Weby, které na vás odkazují', 'Doména', 'Návštěvy', self::rows($r['referrers']));
        $h .= self::table('Kampaně (UTM)', 'Zdroj / médium · kampaň', 'Návštěvy', self::rows($r['campaigns']));
        $h .= self::table('Akce návštěvníků', 'Akce', 'Počet', $ev);
        $h .= self::table('Jazyk webu', 'Jazyk', 'Návštěvy', self::rows($r['languages']));
        $h .= self::table('Zařízení', 'Typ', 'Návštěvy', self::rows($r['devices']));
        $h .= self::table('Prohlížeče', 'Prohlížeč', 'Návštěvy', self::rows($r['browsers']));
        $h .= self::table('Systémy', 'Systém', 'Návštěvy', self::rows($r['systems']));
        $h .= '</div></div>';
        return $h;
    }
    private static function rows(array $a): array { return array_map(fn ($x) => ['label' => $x['label'], 'value' => $x['sessions']], $a); }
    private static function table(string $title, string $col, string $vcol, array $rows, bool $mono = false): string
    {
        $h = '<section class="tsan-box"><h3>' . self::e($title) . '</h3>';
        if (!$rows) return $h . '<p class="tsan-none">Zatím bez dat.</p></section>';
        $max = max(1, max(array_column($rows, 'value')));
        $h .= '<table><thead><tr><th scope="col">' . self::e($col) . '</th><th scope="col" class="tsan-num">' . self::e($vcol) . '</th></tr></thead><tbody>';
        foreach ($rows as $x) {
            $h .= '<tr><td><span class="tsan-bar" style="width:' . round($x['value'] / $max * 100, 1) . '%"></span><span class="tsan-lbl' . ($mono ? ' tsan-mono' : '') . '">' . self::e($x['label']) . '</span>'
                . (isset($x['extra']) ? '<span class="tsan-x">' . self::e($x['extra']) . '</span>' : '') . '</td><td class="tsan-num">' . self::e(self::n($x['value'])) . '</td></tr>';
        }
        return $h . '</tbody></table></section>';
    }
    /* denní průběh: návštěvníci (plocha) a zobrazení stránek (čára) */
    private static function chart(array $daily): string
    {
        $n = count($daily); if ($n < 2) return '';
        $W = 960; $H = 220; $pl = 36; $pb = 26; $pt = 10;
        $max = max(1, max(array_column($daily, 'pageviews')), max(array_column($daily, 'visitors')));
        $step = 10 ** max(0, (int) floor(log10($max))); $top = (int) (ceil($max / $step) * $step); if ($top < 4) $top = 4;
        $x = fn (int $i) => round($pl + $i * ($W - $pl - 8) / ($n - 1), 1); $y = fn (int $v) => round($pt + ($H - $pt - $pb) * (1 - $v / $top), 1);
        $vis = []; $pv = [];
        foreach ($daily as $i => $d) { $vis[] = $x($i) . ',' . $y($d['visitors']); $pv[] = $x($i) . ',' . $y($d['pageviews']); }
        $s = '<figure class="tsan-chart"><svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Denní návštěvnost: návštěvníci a zobrazení stránek" preserveAspectRatio="none">';
        for ($k = 0; $k <= 4; $k++) { $v = (int) round($top * $k / 4); $yy = $y($v); $s .= '<line class="tsan-grid-l" x1="' . $pl . '" x2="' . ($W - 8) . '" y1="' . $yy . '" y2="' . $yy . '"/><text class="tsan-ax" x="' . ($pl - 6) . '" y="' . ($yy + 4) . '" text-anchor="end">' . $v . '</text>'; }
        $s .= '<polygon class="tsan-area" points="' . $x(0) . ',' . $y(0) . ' ' . implode(' ', $vis) . ' ' . $x($n - 1) . ',' . $y(0) . '"/>';
        $s .= '<polyline class="tsan-line-v" points="' . implode(' ', $vis) . '"/><polyline class="tsan-line-p" points="' . implode(' ', $pv) . '"/>';
        $every = max(1, (int) ceil($n / 8));
        foreach ($daily as $i => $d) if ($i % $every === 0 || $i === $n - 1) $s .= '<text class="tsan-ax" x="' . $x($i) . '" y="' . ($H - 6) . '" text-anchor="middle">' . date('j. n.', strtotime($d['day'])) . '</text>';
        $s .= '</svg><figcaption><span class="tsan-k tsan-k-v"></span>Návštěvníci <span class="tsan-k tsan-k-p"></span>Zobrazení stránek</figcaption></figure>';
        return $s;
    }
}
