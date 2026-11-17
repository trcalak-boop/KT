<?php
/* Spočítá dotazy scénářů PHP jádrem: php diff_run.php vstup.json výstup.json */
require __DIR__ . '/../core/TsBookingEngine.php';
$in = json_decode(file_get_contents($argv[1]), true);
$ext = ['obed' => fn($c) => $c['t'] < 780 && $c['t'] + $c['dur'] > 720 ? 'obed' : ''];
$out = ['cases' => []];
foreach ($in['cases'] as $case) {
    $e = new TsBookingEngine($case['config'], $case['state'], ['clock' => $in['clock'], 'extensions' => $case['extension'] ? [$ext[$case['extension']]] : []]);
    $res = [];
    foreach ($case['queries'] as [$fn, $args]) {
        if (in_array($fn, ['slots', 'scan', 'dayState', 'freeTimes'], true)) { $o = array_pop($args) ?: []; $args[] = $o; }
        if ($fn === 'nextFree') $args[1] = $args[1] ?: [];
        $res[] = $e->$fn(...$args);
    }
    $out['cases'][] = ['name' => $case['name'], 'actual' => $res];
}
$out['validate'] = array_map(fn($v) => TsBookingEngine::validate($v[0]), $in['validate']);
file_put_contents($argv[2], json_encode($out, JSON_UNESCAPED_UNICODE));
