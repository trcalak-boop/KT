<?php
/* ==========================================================================
   ts-design-studio – sestavení Studia ze serverové konfigurace
   $studio = ts_design_studio(['db' => ['dsn' => 'sqlite:/…/private/studio/data/studio.sqlite'], 'can' => fn($uid, $akce, $projekt) => …]);
   ========================================================================== */
require_once __DIR__ . '/TsStudioTemplate.php';
require_once __DIR__ . '/TsStudioDiff.php';
require_once __DIR__ . '/TsStudioChecks.php';
require_once __DIR__ . '/TsStudioZip.php';
require_once __DIR__ . '/TsStudioStore.php';
require_once __DIR__ . '/TsDesignStudio.php';

function ts_design_studio(array $s): TsDesignStudio
{
    if (!empty($s['timezone'])) date_default_timezone_set($s['timezone']);
    static $stores = [];
    $k = $s['db']['dsn'];
    if (!isset($stores[$k])) { $stores[$k] = TsStudioStore::open($k, $s['db']['user'] ?? null, $s['db']['pass'] ?? null); if ($s['db']['autoMigrate'] ?? true) $stores[$k]->migrate(); }
    return new TsDesignStudio(['store' => $stores[$k]] + array_diff_key($s, array_flip(['db', 'timezone'])));
}
