<?php
/* ==========================================================================
   ts-design-studio – sestavení Studia ze serverové konfigurace
   $studio = ts_design_studio(['db' => ['dsn' => 'sqlite:/…/private/studio/data/studio.sqlite'], 'can' => fn($uid, $akce, $projekt) => …,
       'ai' => ['provider' => 'cloudflare', 'apiKeyFile' => '/…/private/cloudflare-token.txt', 'accountIdFile' => '/…/private/cloudflare-ucet.txt', 'neuronsPerDay' => 3000]]);
   ========================================================================== */
require_once __DIR__ . '/TsStudioTemplate.php';
require_once __DIR__ . '/TsStudioKit.php';
require_once __DIR__ . '/TsStudioDiff.php';
require_once __DIR__ . '/TsStudioChecks.php';
require_once __DIR__ . '/TsStudioZip.php';
require_once __DIR__ . '/TsStudioStore.php';
require_once __DIR__ . '/TsStudioModels.php';
require_once __DIR__ . '/TsStudioAi.php';
require_once __DIR__ . '/TsStudioPlanner.php';
require_once __DIR__ . '/TsStudioDelivery.php';
require_once __DIR__ . '/TsStudioInspect.php';
require_once __DIR__ . '/TsStudioUpload.php';
require_once __DIR__ . '/TsDesignStudio.php';
require_once __DIR__ . '/TsStudioApi.php';
require_once __DIR__ . '/TsStudioPreviewGate.php';
require_once __DIR__ . '/TsStudioClientPage.php';
require_once __DIR__ . '/TsStudioRoles.php';

function ts_design_studio(array $s): TsDesignStudio
{
    if (!empty($s['timezone'])) date_default_timezone_set($s['timezone']);
    static $stores = [];
    $k = $s['db']['dsn'];
    if (!isset($stores[$k])) { $stores[$k] = TsStudioStore::open($k, $s['db']['user'] ?? null, $s['db']['pass'] ?? null); if ($s['db']['autoMigrate'] ?? true) $stores[$k]->migrate(); }
    /* 'model' v konfiguraci = název modelu (řetězec) pro adaptér; hotový objekt TsStudioModel se použije přímo (testy) */
    if (!empty($s['ai']) && !(($s['ai']['model'] ?? null) instanceof TsStudioModel)) $s['ai']['model'] = match ($s['ai']['provider'] ?? 'cloudflare') {
        'cloudflare' => new TsStudioCloudflareModel($s['ai']), 'fake' => new TsStudioFakeModel($s['ai']),
        default => throw new InvalidArgumentException('neznámý poskytovatel AI ' . $s['ai']['provider']) };
    /* e-mail klientovi: 'mail' => konfigurace TsMailer (knihovna ts-mailer-php vedle této komponenty: vendor/ts/ts-mailer-php) */
    if (!empty($s['mail']) && empty($s['mailer'])) {
        if (!class_exists('TsMailer')) require_once __DIR__ . '/../../ts-mailer-php/core/TsMailer.php';
        $s['mailer'] = new TsMailer($s['mail']);
    }
    return new TsDesignStudio(['store' => $stores[$k]] + array_diff_key($s, array_flip(['db', 'timezone', 'mail'])));
}
