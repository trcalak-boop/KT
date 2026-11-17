<?php
/* ==========================================================================
   T&S administrace – poptávky z webu: lead v CRM, marketingový zdroj a oznámení e-mailem
   Volá ho veřejný /poptavka.php (bez přihlášení, jen zápis nové poptávky) a CRM (fronta oznámení).
   Přímo z internetu nepřístupné (viz .htaccess).

   Pořadí při nové poptávce: lead se uloží VŽDY jako první (v jedné transakci s poptávkou, zdrojem
   a záznamem do fronty oznámení). E-mail se posílá až potom; když se nepovede, lead zůstává a fronta
   to zkusí znovu (při další poptávce nebo když někdo otevře CRM).

   Odesílání: když existuje admin/data/posta.php s údaji SMTP, posílá se přes SMTP a „odesláno“ znamená
   potvrzení (kód 250) od poštovního serveru. Bez něj se použije mail() hostingu: ten jen předá zprávu
   serveru webu, a tak se oznámení označí „předáno“ (doručení nepotvrzeno), nikdy „odesláno“.

   admin/data/posta.php (nepovinný, vytvoří se až s přístupy k SMTP; do gitu nepatří):
   <?php return ['smtp_host' => 'smtp.…', 'smtp_port' => 587, 'smtp_secure' => 'tls', 'smtp_user' => '…', 'smtp_pass' => '…',
                 'od' => 'web@tasstudio.eu', 'od_jmeno' => 'Web T&S Studio', 'prijemce' => 'tasstudio@tasstudio.eu'];
   ========================================================================== */
if (!defined('ADM')) { http_response_code(403); exit; }

const POPTAVKY_PRIJEMCE = 'tasstudio@tasstudio.eu';
const POPTAVKY_PREDMET = 'New Project Inquiry — T&S Studio';
const POPTAVKY_POKUSU = 6;                                  // kolikrát se oznámení zkusí odeslat
const POPTAVKY_ODKLAD = [2, 10, 30, 120, 360];              // minuty mezi pokusy
const POPTAVKY_LIMIT = 8;                                   // poptávek z jedné adresy za hodinu (ochrana proti zahlcení)

function posta_nastaveni() {
    static $n = null;
    if ($n === null) { $c = nacti_php(DATA . '/posta.php'); $n = is_array($c) ? $c : []; }
    return $n;
}
function posta_prijemce() { $n = posta_nastaveni(); return filter_var($n['prijemce'] ?? '', FILTER_VALIDATE_EMAIL) ? $n['prijemce'] : POPTAVKY_PRIJEMCE; }
function posta_domena_webu() {
    $d = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return preg_replace('/^www\./', '', preg_replace('/[^a-z0-9.\-]/', '', $d));
}
/* Odesílatel musí patřit doméně webu (SPF/DKIM/DMARC). Adresa návštěvníka jde jen do Reply-To. */
function posta_odesilatel() {
    $n = posta_nastaveni();
    if (filter_var($n['od'] ?? '', FILTER_VALIDATE_EMAIL)) return [$n['od'], $n['od_jmeno'] ?? 'Web T&S Studio'];
    $prij = posta_prijemce(); $dom = posta_domena_webu() ?: 'localhost';
    $dp = strtolower(substr(strrchr($prij, '@'), 1));
    $naDomene = $dp === $dom || substr($dp, -strlen('.' . $dom)) === '.' . $dom;
    return [$naDomene ? $prij : 'web@' . $dom, 'Web T&S Studio'];
}
function posta_log($radek) {
    @file_put_contents(DATA . '/posta.log', date('c') . ' ' . $radek . "\n", FILE_APPEND | LOCK_EX);
}

/* ---------- ochrana proti zahlcení: nejvýš POPTAVKY_LIMIT poptávek z jedné adresy za hodinu (adresa se ukládá jen jako otisk) ---------- */
function poptavka_limit_ok() {
    $f = @fopen(DATA . '/poptavky-limit.json', 'c+'); if (!$f) return true;
    flock($f, LOCK_EX);
    $d = json_decode(stream_get_contents($f) ?: '[]', true) ?: [];
    $ted = time();
    foreach ($d as $k => $t) { $d[$k] = array_values(array_filter((array) $t, function ($x) use ($ted) { return $ted - $x < 3600; })); if (!$d[$k]) unset($d[$k]); }
    $k = _ip(); $ok = count($d[$k] ?? []) < POPTAVKY_LIMIT;
    if ($ok) $d[$k][] = $ted;
    ftruncate($f, 0); rewind($f); fwrite($f, json_encode($d)); fflush($f); flock($f, LOCK_UN); fclose($f);
    return $ok;
}

/* ---------- marketingový zdroj ----------
   Kanál se odvodí jen z toho, co prohlížeč skutečně poslal (UTM, odkazující stránka, název parametru kliknutí z reklamy).
   Nic se nedomýšlí: bez údajů je kanál „nezname“. */
function poptavka_kanal(array $a) {
    $src = strtolower($a['utm_source'] ?? ''); $med = strtolower($a['utm_medium'] ?? ''); $cid = $a['click_id'] ?? '';
    $ref = strtolower((string) parse_url($a['referrer'] ?? '', PHP_URL_HOST)); $ref = preg_replace('/^(www|m|l|lm)\./', '', $ref);
    $placene = (bool) preg_match('/^(cpc|ppc|paid|paidsearch|paid_social|paidsocial|cpm|display|ads?)$/', $med);
    $je = function ($h, array $sez) { foreach ($sez as $x) if ($h === $x || substr($h, -strlen('.' . $x)) === '.' . $x || strpos($h, $x . '.') === 0) return true; return false; };
    if ($cid === 'gclid' || ($placene && preg_match('/google|adwords/', $src))) return 'google_ads';
    if ($cid === 'fbclid' && $placene) return 'meta_ads';
    if ($placene && preg_match('/facebook|instagram|meta|fb|ig/', $src)) return 'meta_ads';
    if (preg_match('/instagram|^ig$/', $src) || $je($ref, ['instagram.com'])) return 'instagram';
    if (preg_match('/facebook|^fb$/', $src) || $je($ref, ['facebook.com', 'fb.com', 'fb.me'])) return 'facebook';
    if (preg_match('/linkedin/', $src) || $je($ref, ['linkedin.com', 'lnkd.in'])) return 'linkedin';
    if ($src !== '' || ($a['utm_campaign'] ?? '') !== '') return 'kampan';
    if ($ref !== '') {
        if ($je($ref, ['google', 'bing.com', 'seznam.cz', 'duckduckgo.com', 'yahoo.com', 'ecosia.org', 'search.brave.com', 'yandex.ru', 'qwant.com'])) return 'organicke';
        if ($je($ref, ['krystoftrcala.studio', 'behance.net', 'dribbble.com'])) return 'portfolio';
        $dom = posta_domena_webu();
        if ($dom !== '' && ($ref === $dom || substr($ref, -strlen('.' . $dom)) === '.' . $dom)) return 'nezname';
        return 'odkaz';
    }
    return ($a['landing_page'] ?? '') !== '' ? 'primo' : 'nezname';
}
function poptavka_cesta($u) {             // jen cesta a UTM parametry z adresy na tomto webu (bez jiných parametrů, které by mohly nést osobní údaje)
    $u = (string) $u; if ($u === '') return null;
    $p = parse_url($u); if ($p === false) return null;
    $cesta = $p['path'] ?? '/';
    if (!preg_match('~^/[A-Za-z0-9/_.\-%]*$~', $cesta)) return null;
    $q = []; parse_str($p['query'] ?? '', $q);
    $q = array_intersect_key($q, array_flip(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'id', 'sluzby', 'podobne']));
    return mb_substr($cesta . ($q ? '?' . http_build_query($q) : ''), 0, 300);
}
function poptavka_referrer($u) {          // odkazující stránka jiného webu: schéma, host a cesta, bez parametrů
    $u = trim((string) $u); if ($u === '' || !preg_match('~^https?://~i', $u)) return null;
    $p = parse_url($u); if (empty($p['host'])) return null;
    return mb_substr(strtolower($p['scheme']) . '://' . strtolower($p['host']) . ($p['path'] ?? ''), 0, 300);
}

/* ---------- uložení poptávky ----------
   $d: name, company, email, phone, website, services[], message, budget, when, pref, language, submission_id
   $a: marketingový zdroj z prohlížeče (viz poptavka.php)
   Vrací ['lead_id' => …, 'inquiry_id' => …, 'novy' => true|false]. Stejné submission_id = stejná poptávka, nový lead nevznikne. */
function crm_poptavka_uloz(array $d, array $a) {
    return crm_tx(function (PDO $db) use ($d, $a) {
        $byla = crm_one('SELECT id, lead_id FROM lead_inquiries WHERE submission_id = ?', [$d['submission_id']]);
        if ($byla) return ['lead_id' => (int) $byla['lead_id'], 'inquiry_id' => (int) $byla['id'], 'novy' => false];
        $ted = crm_ted();
        $nazev = $d['company'] !== '' ? $d['company'] : $d['name'];
        $sluzby = $d['services'];
        $web = $d['website'] !== '' ? (crm_odkaz($d['website']) ?: null) : null;
        $duvod = 'Poptávka z webu' . ($sluzby ? ': ' . implode(', ', $sluzby) : '') . ($d['company'] !== '' ? ' · kontakt ' . $d['name'] : '');
        crm_q('INSERT INTO leads (name, name_norm, phone, phone_norm, email, email_norm, website, domain, outreach_reason, status, priority, assigned_user_id, created_by_user_id, source, created_at, updated_at, last_activity_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'novy\', \'standardni\', NULL, NULL, \'web\', ?, ?, ?)',
            [$nazev, crm_norm_jmeno($nazev), $d['phone'] ?: null, $d['phone'] !== '' ? crm_norm_tel($d['phone']) : null, $d['email'], crm_norm_email($d['email']),
             $web, $web ? crm_domena($web) : null, mb_substr($duvod, 0, 1000), $ted, $ted, $ted]);
        $lead = (int) $db->lastInsertId();
        crm_q('INSERT INTO lead_inquiries (lead_id, submission_id, form, contact_name, company, email, phone, website, services, message, budget, start_when, contact_pref, language, consent_at, created_at)
               VALUES (?, ?, \'kontakt\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lead, $d['submission_id'], $d['name'], $d['company'] ?: null, $d['email'], $d['phone'] ?: null, $d['website'] ?: null, json_encode($sluzby, JSON_UNESCAPED_UNICODE),
             $d['message'], $d['budget'] ?: null, $d['when'] ?: null, $d['pref'] ?: null, $d['language'] ?: null, $ted, $ted]);
        $inq = (int) $db->lastInsertId();
        $a['channel'] = poptavka_kanal($a);
        crm_q('INSERT INTO lead_attribution (lead_id, inquiry_id, channel, site, landing_page, source_page, submission_page, language, utm_source, utm_medium, utm_campaign, utm_content, utm_term, click_id, referrer, first_visit_at, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lead, $inq, $a['channel'], posta_domena_webu() ?: null, $a['landing_page'] ?? null, $a['source_page'] ?? null, $a['submission_page'] ?? null, $d['language'] ?: null,
             $a['utm_source'] ?: null, $a['utm_medium'] ?: null, $a['utm_campaign'] ?: null, $a['utm_content'] ?: null, $a['utm_term'] ?: null, $a['click_id'] ?: null,
             $a['referrer'] ?? null, $a['first_visit_at'] ?? null, $ted]);
        crm_q('INSERT INTO lead_audit_log (lead_id, user_id, action, field, old_value, new_value, created_at) VALUES (?, NULL, \'vytvoren\', \'web\', NULL, ?, ?)', [$lead, $nazev, $ted]);
        crm_q('INSERT INTO lead_notifications (lead_id, inquiry_id, kind, recipient, status, next_try_at, created_at, updated_at) VALUES (?, ?, \'nova_poptavka\', ?, \'ceka\', ?, ?, ?)',
            [$lead, $inq, posta_prijemce(), $ted, $ted, $ted]);
        return ['lead_id' => $lead, 'inquiry_id' => $inq, 'novy' => true];
    });
}

/* ---------- e-mail s oznámením ---------- */
function poptavka_adresa_webu() {
    $dom = posta_domena_webu();
    if ($dom === '' || $dom === 'localhost' || preg_match('/^[\d.]+$/', $dom)) {
        $h = $_SERVER['HTTP_HOST'] ?? 'localhost'; return (je_https() ? 'https://' : 'http://') . $h;
    }
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? $dom);
}
function poptavka_email(array $lead, array $inq, array $attr) {
    $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $sl = json_decode($inq['services'] ?? '[]', true) ?: [];
    $zdroj = CRM_ZDROJE[$attr['channel'] ?? 'nezname'] ?? 'Neznámý';
    $kamp = array_filter([$attr['utm_campaign'] ?? '', ($attr['utm_source'] ?? '') . (($attr['utm_medium'] ?? '') !== '' ? ' / ' . $attr['utm_medium'] : '')]);
    $kdy = date('j. n. Y H:i', strtotime($inq['created_at']));
    $odkaz = poptavka_adresa_webu() . '/admin/#crm/lead/' . (int) $lead['id'];
    $jaz = CRM_JAZYKY[$inq['language'] ?? ''] ?? ($inq['language'] ?: 'neuveden');
    $radky = [
        ['Jméno', $inq['contact_name']], ['Firma', $inq['company'] ?: '–'], ['E-mail', $inq['email']], ['Telefon', $inq['phone'] ?: '–'],
        ['Web', $inq['website'] ?: '–'], ['Služby', $sl ? implode(', ', $sl) : '–'], ['Rozpočet', $inq['budget'] ?: '–'], ['Začátek', $inq['start_when'] ?: '–'],
        ['Preferovaný kontakt', $inq['contact_pref'] ?: '–'], ['Jazyk', $jaz], ['Zdroj', $zdroj], ['Kampaň', $kamp ? implode(' · ', $kamp) : '–'], ['Odesláno', $kdy],
    ];
    $text = "Nová poptávka z webu T&S Studio\n================================\n\nStav leadu: Nový\nPřiřazeno: Nepřiřazeno\n\n";
    foreach ($radky as $r) $text .= $r[0] . ':' . str_repeat(' ', max(1, 21 - mb_strlen($r[0], 'UTF-8'))) . $r[1] . "\n";      // zarovnání podle znaků, ne bajtů
    $text .= "\nPopis projektu:\n" . $inq['message'] . "\n\nLead v administraci: $odkaz\n\n--\nNa tento e-mail stačí odpovědět, odpověď půjde přímo zájemci.\n";
    $tr = '';
    foreach ($radky as $r) $tr .= '<tr><td style="padding:9px 0;border-bottom:1px solid #D9D9D4;color:#7B7B82;font:12px/1.4 Menlo,Consolas,monospace;letter-spacing:.06em;text-transform:uppercase;width:180px;vertical-align:top">' . $e($r[0]) . '</td>'
        . '<td style="padding:9px 0;border-bottom:1px solid #D9D9D4;color:#111113;font:15px/1.45 -apple-system,Segoe UI,Helvetica,Arial,sans-serif">' . ($r[0] === 'E-mail' ? '<a href="mailto:' . $e($r[1]) . '" style="color:#111113">' . $e($r[1]) . '</a>' : $e($r[1])) . '</td></tr>';
    $html = '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e(POPTAVKY_PREDMET) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F1F1EE"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F1EE"><tr><td align="center" style="padding:28px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#FFFFFF;border:1px solid #D9D9D4">'
        . '<tr><td style="background:#111113;padding:22px 28px"><span style="color:#F1F1EE;font:900 22px/1 Arial Black,Arial,sans-serif;letter-spacing:-.02em">T<span style="color:#E10600">&amp;</span>S</span>'
        . '<span style="color:#7B7B82;font:12px Menlo,Consolas,monospace;letter-spacing:.12em;text-transform:uppercase;padding-left:12px">Studio · nová poptávka</span></td></tr>'
        . '<tr><td style="padding:28px 28px 8px"><p style="margin:0 0 6px;color:#E10600;font:12px Menlo,Consolas,monospace;letter-spacing:.12em;text-transform:uppercase">Nová poptávka z webu</p>'
        . '<h1 style="margin:0;color:#111113;font:800 28px/1.15 -apple-system,Segoe UI,Helvetica,Arial,sans-serif">' . $e($inq['contact_name']) . ($inq['company'] ? ' · ' . $e($inq['company']) : '') . '</h1>'
        . '<p style="margin:14px 0 0"><span style="display:inline-block;background:#FBE7E6;color:#B00500;font:700 12px Arial,sans-serif;padding:6px 10px;margin-right:6px">Stav leadu: Nový</span>'
        . '<span style="display:inline-block;background:#F1F1EE;color:#4A4A50;font:700 12px Arial,sans-serif;padding:6px 10px">Přiřazeno: Nepřiřazeno</span></p></td></tr>'
        . '<tr><td style="padding:12px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $tr . '</table></td></tr>'
        . '<tr><td style="padding:8px 28px 4px"><p style="margin:0 0 8px;color:#7B7B82;font:12px Menlo,Consolas,monospace;letter-spacing:.06em;text-transform:uppercase">Popis projektu</p>'
        . '<div style="color:#111113;font:15px/1.6 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;white-space:pre-wrap;border-left:3px solid #E10600;padding-left:14px">' . $e($inq['message']) . '</div></td></tr>'
        . '<tr><td style="padding:24px 28px 30px"><a href="' . $e($odkaz) . '" style="display:inline-block;background:#E10600;color:#FFFFFF;font:700 15px Arial,sans-serif;text-decoration:none;padding:14px 22px">Otevřít lead v administraci →</a>'
        . '<p style="margin:16px 0 0;color:#7B7B82;font:13px/1.5 Arial,sans-serif">Na tento e-mail stačí odpovědět, odpověď půjde přímo zájemci.</p></td></tr>'
        . '</table><p style="margin:14px 0 0;color:#7B7B82;font:12px Arial,sans-serif">Automatická zpráva administrace webu T&amp;S Studio.</p></td></tr></table></body></html>';
    return [POPTAVKY_PREDMET . ' · ' . $inq['contact_name'], $text, $html];
}
function posta_hlavicka($t) { return '=?UTF-8?B?' . base64_encode($t) . '?='; }
function posta_zprava($od, $odJmeno, $komu, $replyTo, $replyJmeno, $predmet, $text, $html, $msgid) {
    $hr = 'ts-' . bin2hex(random_bytes(8));
    $h = ['Date: ' . date('r'), 'From: ' . posta_hlavicka($odJmeno) . " <$od>", 'To: <' . $komu . '>', 'Subject: ' . posta_hlavicka($predmet), 'Message-ID: <' . $msgid . '>',
          'MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="' . $hr . '"', 'X-Mailer: T&S administrace'];
    if ($replyTo) $h[] = 'Reply-To: ' . posta_hlavicka($replyJmeno) . " <$replyTo>";
    $telo = "--$hr\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
          . "--$hr\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$hr--\r\n";
    return [$h, $telo];
}

/* Odeslání: vrací ['ok' => bool, 'stav' => 'odeslano'|'predano', 'odpoved' => …, 'chyba' => …]. Chybové texty nikdy neobsahují heslo. */
function posta_odeslat($komu, $predmet, $text, $html, $replyTo, $replyJmeno, $msgid) {
    list($od, $odJmeno) = posta_odesilatel();
    list($hl, $telo) = posta_zprava($od, $odJmeno, $komu, $replyTo, $replyJmeno, $predmet, $text, $html, $msgid);
    $n = posta_nastaveni();
    if (!empty($n['smtp_host'])) return posta_smtp($n, $od, $komu, implode("\r\n", $hl) . "\r\n\r\n" . $telo);
    // mail(): To a Subject se předávají zvlášť, v dalších hlavičkách být nesmí
    $dalsi = array_values(array_filter($hl, function ($x) { return !preg_match('/^(To|Subject):/i', $x); }));
    $ok = @mail($komu, posta_hlavicka($predmet), $telo, implode("\r\n", $dalsi), '-f' . $od);
    if (!$ok) $ok = @mail($komu, posta_hlavicka($predmet), $telo, implode("\r\n", $dalsi));
    return $ok ? ['ok' => true, 'stav' => 'predano', 'odpoved' => 'mail() přijal zprávu k odeslání'] : ['ok' => false, 'chyba' => 'mail() zprávu odmítl'];
}
function posta_smtp(array $n, $od, $komu, $data) {
    $host = (string) $n['smtp_host']; $port = (int) ($n['smtp_port'] ?? 587); $sec = strtolower((string) ($n['smtp_secure'] ?? 'tls'));
    $errno = 0; $errstr = '';
    $s = @stream_socket_client(($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
    if (!$s) return ['ok' => false, 'chyba' => 'SMTP: spojení se serverem se nepodařilo (' . $errno . ')'];
    stream_set_timeout($s, 15);
    $cti = function () use ($s) { $r = ''; while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $prikaz = function ($c, $ocekavam) use ($s, $cti) { if ($c !== null) fwrite($s, $c . "\r\n"); $r = $cti(); return [(int) substr($r, 0, 3) === $ocekavam || in_array((int) substr($r, 0, 3), (array) $ocekavam, true), trim($r)]; };
    $chyba = function ($krok, $r) use ($s) { @fwrite($s, "QUIT\r\n"); @fclose($s); return ['ok' => false, 'chyba' => 'SMTP ' . $krok . ': ' . mb_substr(preg_replace('/\s+/', ' ', $r), 0, 200)]; };
    $ehlo = 'EHLO ' . (posta_domena_webu() ?: 'localhost');
    list($ok, $r) = $prikaz(null, 220); if (!$ok) return $chyba('uvítání', $r);
    list($ok, $r) = $prikaz($ehlo, 250); if (!$ok) return $chyba('EHLO', $r);
    if ($sec === 'tls') {
        list($ok, $r) = $prikaz('STARTTLS', 220); if (!$ok) return $chyba('STARTTLS', $r);
        if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) return $chyba('TLS', 'šifrované spojení se nepodařilo');
        list($ok, $r) = $prikaz($ehlo, 250); if (!$ok) return $chyba('EHLO', $r);
    }
    if (!empty($n['smtp_user'])) {
        list($ok, $r) = $prikaz('AUTH LOGIN', 334); if (!$ok) return $chyba('AUTH', $r);
        list($ok, $r) = $prikaz(base64_encode((string) $n['smtp_user']), 334); if (!$ok) return $chyba('AUTH', 'jméno odmítnuto');
        list($ok, $r) = $prikaz(base64_encode((string) ($n['smtp_pass'] ?? '')), 235); if (!$ok) return $chyba('AUTH', 'přihlášení odmítnuto');
    }
    list($ok, $r) = $prikaz('MAIL FROM:<' . $od . '>', 250); if (!$ok) return $chyba('MAIL FROM', $r);
    list($ok, $r) = $prikaz('RCPT TO:<' . $komu . '>', [250, 251]); if (!$ok) return $chyba('RCPT TO', $r);
    list($ok, $r) = $prikaz('DATA', 354); if (!$ok) return $chyba('DATA', $r);
    $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data));
    list($ok, $r) = $prikaz($data . "\r\n.", 250); if (!$ok) return $chyba('odeslání', $r);
    @fwrite($s, "QUIT\r\n"); @fclose($s);
    return ['ok' => true, 'stav' => 'odeslano', 'odpoved' => mb_substr($r, 0, 200)];
}

/* ---------- fronta oznámení ----------
   Každé oznámení si nejdřív „zamkne“ jeden proces (locked_until), takže dva souběžné požadavky nepošlou stejný e-mail dvakrát.
   Odeslané nebo předané oznámení se už nikdy znovu neposílá. */
function crm_fronta_zpracuj($max = 3) {
    try {
        $ted = crm_ted();
        $cekajici = crm_all("SELECT id FROM lead_notifications WHERE status IN ('ceka','chyba') AND next_try_at <= ? AND (locked_until IS NULL OR locked_until < ?) ORDER BY id LIMIT " . (int) $max, [$ted, $ted]);
    } catch (Throwable $e) { return 0; }
    $hotovo = 0;
    foreach ($cekajici as $c) {
        $id = (int) $c['id'];
        $zamek = date('Y-m-d H:i:s', time() + 120);
        $mam = crm_tx(function () use ($id, $zamek) {
            $s = crm_q("UPDATE lead_notifications SET locked_until = ? WHERE id = ? AND status IN ('ceka','chyba') AND (locked_until IS NULL OR locked_until < ?)", [$zamek, $id, crm_ted()]);
            return $s->rowCount() === 1;
        });
        if (!$mam) continue;
        $o = crm_one('SELECT * FROM lead_notifications WHERE id = ?', [$id]);
        $lead = crm_one('SELECT * FROM leads WHERE id = ?', [(int) $o['lead_id']]);
        $inq = crm_one('SELECT * FROM lead_inquiries WHERE id = ?', [(int) $o['inquiry_id']]);
        $attr = crm_one('SELECT * FROM lead_attribution WHERE inquiry_id = ?', [(int) $o['inquiry_id']]) ?: [];
        if (!$lead || !$inq) { crm_q("UPDATE lead_notifications SET status = 'selhalo', last_error = 'lead nebo poptávka už neexistuje', locked_until = NULL, updated_at = ? WHERE id = ?", [crm_ted(), $id]); continue; }
        list($predmet, $text, $html) = poptavka_email($lead, $inq, $attr);
        try {
            $msgid = 'poptavka-' . (int) $inq['id'] . '.' . substr(hash('sha256', $inq['submission_id']), 0, 12) . '@' . (posta_domena_webu() ?: 'localhost');
            $r = posta_odeslat($o['recipient'], $predmet, $text, $html, $inq['email'], $inq['contact_name'], $msgid);
        } catch (Throwable $e) { $r = ['ok' => false, 'chyba' => 'výjimka: ' . mb_substr($e->getMessage(), 0, 160)]; }
        $pokus = (int) $o['attempts'] + 1;
        crm_tx(function () use ($id, $r, $pokus) {
            $ted = crm_ted();
            if ($r['ok']) {
                crm_q('UPDATE lead_notifications SET status = ?, attempts = ?, provider_response = ?, last_error = NULL, sent_at = ?, locked_until = NULL, updated_at = ? WHERE id = ?', [$r['stav'], $pokus, $r['odpoved'] ?? null, $ted, $ted, $id]);
            } else {
                $konec = $pokus >= POPTAVKY_POKUSU;
                $dalsi = date('Y-m-d H:i:s', time() + 60 * POPTAVKY_ODKLAD[min($pokus - 1, count(POPTAVKY_ODKLAD) - 1)]);
                crm_q('UPDATE lead_notifications SET status = ?, attempts = ?, last_error = ?, next_try_at = ?, locked_until = NULL, updated_at = ? WHERE id = ?', [$konec ? 'selhalo' : 'chyba', $pokus, $r['chyba'] ?? '?', $dalsi, $ted, $id]);
            }
        });
        posta_log('oznameni #' . $id . ' lead #' . (int) $o['lead_id'] . ' pokus ' . $pokus . ': ' . ($r['ok'] ? $r['stav'] : 'chyba – ' . ($r['chyba'] ?? '?')));
        $hotovo++;
    }
    return $hotovo;
}
