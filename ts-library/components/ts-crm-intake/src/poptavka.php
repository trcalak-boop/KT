<?php
/* ==========================================================================
   POPTÁVKOVÝ FORMULÁŘ – poptávka z webu se uloží jako nový lead do CRM
   ==========================================================================
   1. ověří formulář a ochranu proti robotům,
   2. uloží lead (stav Nový, nikomu nepřiřazený), původní poptávku a marketingový zdroj,
   3. pošle oznámení na tasstudio@tasstudio.eu (přes frontu s opakováním, viz admin/crm-poptavky.php).
   Stejné submission_id (dvojklik, opakovaný požadavek) nový lead nevytvoří.

   Kdyby databáze CRM nešla otevřít, poptávka se neztratí: odejde postaru e-mailem a zapíše se do admin/data/posta.log.
   Odesílatele, příjemce a SMTP nastavuje admin/data/posta.php (viz popis v admin/crm-poptavky.php).
   ========================================================================== */

require __DIR__ . '/admin/lib.php';
require ADM . '/crm-poptavky.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function odpoved($ok, $chyba = '', $kod = 200, $navic = []) {
    http_response_code($kod);
    echo json_encode(($ok ? ['ok' => true] : ['ok' => false, 'chyba' => $chyba]) + $navic);
}
function konec($ok, $chyba = '', $kod = 200) { odpoved($ok, $chyba, $kod); exit; }
function pole($klic, $max, $radky = false) {
    $v = isset($_POST[$klic]) && is_scalar($_POST[$klic]) ? trim((string) $_POST[$klic]) : '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    if (!$radky) $v = preg_replace('/\s+/u', ' ', $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr($v, 0, $max, 'UTF-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') konec(false, 'metoda', 405);

// Ochrana proti robotům: skryté pole musí zůstat prázdné a formulář se nevyplní za méně než 3 sekundy.
// Robot dostane „úspěch“, aby nezkoušel jiné cesty; nic se neuloží.
if (!empty($_POST['web'])) konec(true);
if (isset($_POST['t']) && (int) $_POST['t'] > 0 && (int) $_POST['t'] < 3000) konec(true);

$sluzby = [];
foreach ((array) ($_POST['sluzby'] ?? []) as $s) if (is_scalar($s) && ($s = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $s), 0, 60))) !== '') $sluzby[] = $s;
if (!$sluzby && ($t = pole('type', 300)) !== '') $sluzby = array_values(array_filter(array_map('trim', explode(',', $t))));     // starší verze formuláře
$sluzby = array_slice(array_values(array_unique($sluzby)), 0, 12);

$d = [
    'name'     => pole('name', 120),
    'company'  => pole('company', 160),
    'email'    => pole('email', 160),
    'phone'    => pole('phone', 40),
    'website'  => pole('website', 300),
    'services' => $sluzby,
    'message'  => pole('msg', 5000, true),
    'budget'   => pole('budget', 60),
    'when'     => pole('when', 60),
    'pref'     => pole('pref', 40),
    'language' => strtolower(pole('lang', 8)),
];
if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $d['language'])) $d['language'] = '';
if ($d['name'] === '' || $d['message'] === '' || !filter_var($d['email'], FILTER_VALIDATE_EMAIL) || empty($_POST['consent'])) konec(false, 'pole', 400);

// Identifikátor odeslání z prohlížeče (stejný při opakování). Bez něj (starší stránka) se vytvoří nový, ochrana před dvojím odesláním pak neplatí.
$sid = pole('submission_id', 64);
$d['submission_id'] = preg_match('/^[A-Za-z0-9_-]{16,64}$/', $sid) ? $sid : 'srv-' . bin2hex(random_bytes(12));

// Marketingový zdroj: jen to, co prohlížeč skutečně poslal (viz assets/js/ts.js, ukládá se v sessionStorage, bez cookies).
$utm = function ($k) { return mb_substr(preg_replace('/[^\p{L}\p{N} ._\-+\/:]/u', '', pole($k, 120)), 0, 120); };
$cid = pole('click_id', 16);
$a = [
    'utm_source' => $utm('utm_source'), 'utm_medium' => $utm('utm_medium'), 'utm_campaign' => $utm('utm_campaign'),
    'utm_content' => $utm('utm_content'), 'utm_term' => $utm('utm_term'),
    'click_id' => in_array($cid, ['gclid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id'], true) ? $cid : '',
    'referrer' => poptavka_referrer(pole('referrer', 500)),
    'landing_page' => poptavka_cesta(pole('landing_page', 500)),
    'source_page' => poptavka_cesta(pole('source_page', 500)),
    'submission_page' => poptavka_cesta(pole('submission_page', 500)),
    'first_visit_at' => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $fv = pole('first_visit_at', 40)) ? date('Y-m-d H:i:s', strtotime($fv)) : null,
];

try {
    crm_db();
    $vysl = null;
    $byla = crm_one('SELECT id FROM lead_inquiries WHERE submission_id = ?', [$d['submission_id']]);
    if (!$byla && !poptavka_limit_ok()) konec(false, 'limit', 429);
    $vysl = crm_poptavka_uloz($d, $a);
} catch (Throwable $e) {
    // CRM nejde použít: poptávka se pošle aspoň e-mailem, ať se neztratí, a chyba se zapíše (bez obsahu poptávky).
    posta_log('CRM nedostupné, poptávka jen e-mailem: ' . mb_substr($e->getMessage(), 0, 200));
    $inq = ['id' => 0, 'contact_name' => $d['name'], 'company' => $d['company'], 'email' => $d['email'], 'phone' => $d['phone'], 'website' => $d['website'],
            'services' => json_encode($d['services'], JSON_UNESCAPED_UNICODE), 'message' => $d['message'] . "\n\n(Lead se nepodařilo uložit do CRM, založte ho prosím ručně.)",
            'budget' => $d['budget'], 'start_when' => $d['when'], 'contact_pref' => $d['pref'], 'language' => $d['language'], 'created_at' => crm_ted()];
    list($predmet, $text, $html) = poptavka_email(['id' => 0], $inq, $a + ['channel' => poptavka_kanal($a)]);
    $r = posta_odeslat(posta_prijemce(), $predmet, $text, $html, $d['email'], $d['name'], 'poptavka-nouze.' . bin2hex(random_bytes(6)) . '@' . (posta_domena_webu() ?: 'localhost'));
    konec($r['ok'], $r['ok'] ? '' : 'odeslani', $r['ok'] ? 200 : 500);
}

// Lead je uložený: návštěvník dostane odpověď hned, oznámení se pošle potom (nebo později z fronty).
odpoved(true, '', 200, ['opakovani' => !$vysl['novy']]);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { @ob_flush(); @flush(); }
ignore_user_abort(true);
crm_fronta_zpracuj(3);
