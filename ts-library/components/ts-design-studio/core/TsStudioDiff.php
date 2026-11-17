<?php
/* ==========================================================================
   ts-design-studio – rozdíly mezi verzemi (řádkový diff, LCS) a seznam změněných souborů.
   TsStudioDiff::lines($a, $b) → [['=', 'řádek'], ['-', 'řádek'], ['+', 'řádek'], …]
   TsStudioDiff::unified($a, $b, 'index.html', 3) → text ve formátu unified diff (s kontextem)
   ========================================================================== */
final class TsStudioDiff
{
    const MAX_LINES = 6000;

    public static function lines(string $a, string $b): array
    {
        $A = $a === '' ? [] : explode("\n", str_replace("\r\n", "\n", $a)); $B = $b === '' ? [] : explode("\n", str_replace("\r\n", "\n", $b));
        $p = 0; while ($p < count($A) && $p < count($B) && $A[$p] === $B[$p]) $p++;           /* společný začátek a konec zkrátí výpočet */
        $s = 0; while ($s < count($A) - $p && $s < count($B) - $p && $A[count($A) - 1 - $s] === $B[count($B) - 1 - $s]) $s++;
        $a2 = array_slice($A, $p, count($A) - $p - $s); $b2 = array_slice($B, $p, count($B) - $p - $s);
        $out = []; foreach (array_slice($A, 0, $p) as $l) $out[] = ['=', $l];
        if (count($a2) * count($b2) > self::MAX_LINES * 400) {                                 /* příliš velké: celé nahrazení */
            foreach ($a2 as $l) $out[] = ['-', $l]; foreach ($b2 as $l) $out[] = ['+', $l];
        } else {
            $n = count($a2); $m = count($b2); $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
            for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) $L[$i][$j] = $a2[$i] === $b2[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
            $i = 0; $j = 0;
            while ($i < $n && $j < $m) {
                if ($a2[$i] === $b2[$j]) { $out[] = ['=', $a2[$i]]; $i++; $j++; }
                elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) { $out[] = ['-', $a2[$i]]; $i++; }
                else { $out[] = ['+', $b2[$j]]; $j++; }
            }
            for (; $i < $n; $i++) $out[] = ['-', $a2[$i]]; for (; $j < $m; $j++) $out[] = ['+', $b2[$j]];
        }
        foreach (array_slice($A, count($A) - $s) as $l) $out[] = ['=', $l];
        return $out;
    }

    public static function unified(string $a, string $b, string $name, int $ctx = 3): string
    {
        $d = self::lines($a, $b); $n = count($d);
        $ch = []; foreach ($d as $k => $x) if ($x[0] !== '=') $ch[] = $k;
        if (!$ch) return '';
        $pos = []; $la = 1; $lb = 1;
        foreach ($d as $k => $x) { $pos[$k] = [$la, $lb]; if ($x[0] !== '+') $la++; if ($x[0] !== '-') $lb++; }
        $groups = []; $g = [$ch[0], $ch[0]];                 /* změny blíž než 2× kontext patří do jednoho bloku */
        foreach (array_slice($ch, 1) as $k) { if ($k - $g[1] <= 2 * $ctx + 1) $g[1] = $k; else { $groups[] = $g; $g = [$k, $k]; } }
        $groups[] = $g;
        $out = "--- a/$name\n+++ b/$name\n";
        foreach ($groups as [$f, $l]) {
            $s = max(0, $f - $ctx); $e = min($n, $l + $ctx + 1); $ca = 0; $cb = 0;
            for ($k = $s; $k < $e; $k++) { if ($d[$k][0] !== '+') $ca++; if ($d[$k][0] !== '-') $cb++; }
            $out .= '@@ -' . $pos[$s][0] . ',' . $ca . ' +' . $pos[$s][1] . ',' . $cb . " @@\n";
            for ($k = $s; $k < $e; $k++) $out .= ($d[$k][0] === '=' ? ' ' : $d[$k][0]) . $d[$k][1] . "\n";
        }
        return $out;
    }

    /* souhrn změn mezi dvěma sadami souborů: [cesta => 'přidán'|'odebrán'|'změněn' + počty řádků] */
    public static function files(array $old, array $new): array
    {
        $out = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $p) {
            if (!isset($old[$p])) $out[$p] = ['status' => 'added', 'plus' => substr_count($new[$p], "\n") + 1, 'minus' => 0];
            elseif (!isset($new[$p])) $out[$p] = ['status' => 'removed', 'plus' => 0, 'minus' => substr_count($old[$p], "\n") + 1];
            elseif ($old[$p] !== $new[$p]) { $d = self::lines($old[$p], $new[$p]); $out[$p] = ['status' => 'changed', 'plus' => count(array_filter($d, fn ($x) => $x[0] === '+')), 'minus' => count(array_filter($d, fn ($x) => $x[0] === '-'))]; }
        }
        ksort($out); return $out;
    }
}
