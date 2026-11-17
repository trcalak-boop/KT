<?php
/* ==========================================================================
   T&S administrace – Návštěvnost (vlastní měření ts-analytics). Jen pro přihlášené.
   Data jsou jen od návštěvníků, kteří v liště cookies povolili měření; konfigurace a databáze v /private/stat/.
   Google Analytics (je-li v Kontaktech a údajích vyplněné ID) má vlastní přehled v analytics.google.com.
   ========================================================================== */
require __DIR__ . '/lib.php';
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
start_relace();
if (!prihlasen()) { header('Location: ./', true, 303); exit; }
$cfg = dirname(WEBROOT) . '/private/stat/analytics.php';
$v = json_decode((string) @file_get_contents(WEBROOT . '/vendor/ts/ts-analytics/module.json'), true)['version'] ?? '1';
$dni = (int) ($_GET['dni'] ?? 30); if (!in_array($dni, [7, 30, 90, 365], true)) $dni = 30;     // bez ?dni= výchozích 30 dní
$html = '<p>Měření není nastavené (chybí <code>/private/stat/analytics.php</code>).</p>';
if (is_file($cfg)) {
    require WEBROOT . '/vendor/ts/ts-analytics/core/bootstrap.php';
    try {
        $an = ts_analytics(require $cfg);
        $html = TsAnalyticsReport::html($an->report(date('Y-m-d', strtotime('-' . ($dni - 1) . ' days')), date('Y-m-d')), [
            'periods' => [7, 30, 90, 365], 'url' => fn (int $d) => 'navstevnost.php?dni=' . $d,
            'events' => ['poptavka' => 'Odeslaná poptávka (formulář)', 'tessa_zprava' => 'Rozhovor s Tessou'],
        ]);
    } catch (Throwable $e) { error_log('navstevnost: ' . $e->getMessage()); $html = '<p>Přehled se nepodařilo načíst. Podrobnosti jsou v logu hostingu.</p>'; }
}
$ga = (nacti_nastaveni() ?? [])['ga4'] ?? '';
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Návštěvnost · Administrace T&amp;S Studio</title>
<link rel="stylesheet" href="../vendor/ts/ts-analytics/public/ts-analytics-report.css?v=<?= htmlspecialchars($v) ?>">
<style>:root{--paper:#f1f1ee;--surface:#fff;--ink:#111113;--line:#d9d9d4;--red:#e10600}
@media (prefers-color-scheme:dark){:root{--paper:#111113;--surface:#18181b;--ink:#f1f1ee;--line:#34343a}}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}main{max-width:1180px;margin:0 auto;padding:24px 16px 64px}
a.zpet{color:inherit;font-size:14px}.pozn{font-size:14px;max-width:760px}.pozn a{color:inherit}</style></head>
<body><main><a class="zpet" href="./">← Zpět do administrace</a>
<?= $html ?>
<p class="pozn">Počítají se jen návštěvníci, kteří v liště cookies povolili měření návštěvnosti; ostatní v číslech nejsou. Údaje se uchovávají 14 měsíců, bez IP adres.
<?= $ga !== '' ? 'Google Analytics (' . htmlspecialchars($ga) . ') má podrobnější přehled na <a href="https://analytics.google.com/" target="_blank" rel="noopener">analytics.google.com</a>.' : 'Google Analytics zapnete vyplněním ID měření v záložce Kontakty a údaje.' ?></p>
</main></body></html>
