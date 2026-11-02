<?php
/* ts-design-studio – doručení klientovi a brána náhledu (bez sítě, falešný odesílač e-mailů). Scénáře zadání 9–11, akceptace E7, role. */
require dirname(__DIR__, 3) . '/tools/phptest.php';
require dirname(__DIR__) . '/core/bootstrap.php';

final class FakeMail
{
    public array $sent = []; public bool $fail = false;
    public function send(string $to, string $subject, string $text, string $html, ?string $replyTo = null, string $replyName = ''): array
    {
        if ($this->fail) return ['ok' => false, 'chyba' => 'SMTP RCPT: adresa odmítnuta'];
        $this->sent[] = compact('to', 'subject', 'text', 'html', 'replyTo', 'replyName'); return ['ok' => true, 'stav' => 'odeslano'];
    }
}
$dir = t_tmpdir(); $mail = new FakeMail(); $SECRET = str_repeat('k', 40);
$roles = [1 => 'admin', 2 => 'designer', 3 => 'sales', 4 => 'viewer'];
$S = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/d.sqlite"], 'deliverySecret' => $SECRET, 'previewBase' => 'https://nahledy.example/', 'mailer' => $mail,
    'leadLookup' => fn ($ref) => $ref === 'L7' ? ['email' => 'petra@salon.example', 'name' => 'Petra Horká'] : null,
    'can' => fn ($uid, $act) => TsStudioRoles::allows($roles[$uid] ?? '', $act)]);
$gate = new TsStudioPreviewGate($S, ['failDir' => "$dir/brana", 'maxFails' => 5, 'contact' => 'tasstudio.eu']);
$tok = fn (string $url) => explode('/', rtrim(parse_url($url, PHP_URL_PATH), '/'))[1];

/* --- role --- */
t_ok(TsStudioRoles::allows('sales', 'deliver') && !TsStudioRoles::allows('designer', 'deliver') && TsStudioRoles::allows('designer', 'status') && !TsStudioRoles::allows('sales', 'edit') && !TsStudioRoles::allows('neznama', 'edit') && TsStudioRoles::allows('neznama', 'view'), 'role: sales doručuje, designer schvaluje, neznámá role jen prohlíží');
t_eq(TsStudioRoles::actions('viewer'), ['view'], 'akce prohlížejícího');

/* --- příprava jen po interním schválení --- */
$pid = $S->createProject(['name' => 'Salon Vlna', 'source' => 'crm', 'template' => 'salon', 'lead_ref' => 'L7'], 2)['id'];
try { $S->prepareDelivery($pid, 1, 3); t_ok(false, 'bez schválení'); } catch (InvalidArgumentException $e) { t_ok(true, 'sdílet nejde před interním schválením'); }
try { $S->setStatus($pid, 'approved_internal', 3); t_ok(false, 'sales schvaluje'); } catch (TsStudioDenied $e) { t_ok(true, 'sales nemůže interně schválit'); }
$S->setStatus($pid, 'approved_internal', 2);
t_eq($S->deliveries($pid, 1), [], 'scénář 9: interní schválení samo nevytvoří žádné doručení ani odkaz');
t_eq($mail->sent, [], 'scénář 9: po schválení žádný e-mail');
try { $S->prepareDelivery($pid, 1, 2); t_ok(false, 'designer'); } catch (TsStudioDenied $e) { t_ok(true, 'designer nemůže připravit sdílení'); }
$r2 = $S->renderVersion($pid, 2, ['brand' => 'Salon Vlna 2'] + $S->version($pid, 1, 1)['content'], [], 'kandidát');
try { $S->prepareDelivery($pid, $r2['num'], 3); t_ok(false, 'kandidát'); } catch (InvalidArgumentException $e) { t_ok(true, 'sdílet nejde nepřijatou verzi (návrh)'); }
$d = $S->prepareDelivery($pid, 1, 3, 14);
t_ok($d['status'] === 'prepared' && !$d['active'] && $d['to_email'] === 'petra@salon.example' && $d['to_name'] === 'Petra Horká' && strpos($d['subject'], 'Salon Vlna') !== false && strpos($d['body'], '{odkaz}') !== false, 'připravené doručení: neaktivní, příjemce z CRM, výchozí text s názvem klienta', $d);
$row = $S->store()->delivery($d['id']);
t_ok(strlen($row['token_hash']) === 64 && strpos(json_encode($row), $tok($S->deliveryUrl($row))) === false, 'v databázi jen otisk tokenu, token samotný ne');
$url = $S->deliveryUrl($row); $T = $tok($url);
t_ok(str_starts_with($url, 'https://nahledy.example/') && strlen($T) === 32, 'odkaz na samostatném originu, token 32 znaků');
t_eq($gate->handle('GET', "/$T/", '1.1.1.1')[0], 404, 'připravený (neaktivní) odkaz nefunguje');
t_ok(strpos($S->deliveryDetail($d['id'], 3)['message']['text'], '[odkaz se aktivuje odesláním]') !== false, 'náhled e-mailu před odesláním neprozradí odkaz');

/* --- e-mail a samostatné potvrzení --- */
try { $S->draftDelivery($d['id'], 3, ['to_email' => 'neni', 'subject' => 'x', 'body' => '{odkaz}']); t_ok(false, 'email'); } catch (InvalidArgumentException $e) { t_ok(true, 'neplatný e-mail příjemce odmítnut'); }
try { $S->draftDelivery($d['id'], 3, ['to_email' => 'petra@salon.example', 'subject' => 'x', 'body' => 'bez odkazu']); t_ok(false, 'odkaz'); } catch (InvalidArgumentException $e) { t_ok(true, 'text bez {odkaz} odmítnut'); }
try { $S->sendDelivery($d['id'], 3, 'petra@salon.example', 'idem-12345678'); t_ok(false, 'bez draftu'); } catch (InvalidArgumentException $e) { t_ok(true, 'odeslat nejde bez připraveného e-mailu'); }
$d = $S->draftDelivery($d['id'], 3, ['to_email' => 'petra@salon.example', 'to_name' => 'Petra', 'subject' => "Návrh\r\nBcc: x@y.cz", 'body' => "Dobrý den,\n\nnávrh: {odkaz}\nPlatí do {datum}.\n\n{jmeno}", 'days' => 7]);
t_ok($d['status'] === 'ready_to_send' && $d['subject'] === 'Návrh Bcc: x@y.cz' && $d['days'] === 7, 'e-mail připravený (zalomení v předmětu odstraněno – žádné vložené hlavičky)');
t_eq($mail->sent, [], 'scénář 10: připravený e-mail se sám neodešle');
try { $S->sendDelivery($d['id'], 3, 'jina@adresa.example', 'idem-12345678'); t_ok(false, 'confirm'); } catch (InvalidArgumentException $e) { t_ok(true, 'scénář 10: bez správného potvrzení (opsaná adresa) se neodešle'); }
try { $S->sendDelivery($d['id'], 3, 'petra@salon.example', 'x'); t_ok(false, 'idem'); } catch (InvalidArgumentException $e) { t_ok(true, 'bez klíče idempotence se neodešle'); }
try { $S->sendDelivery($d['id'], 2, 'petra@salon.example', 'idem-12345678'); t_ok(false, 'designer send'); } catch (TsStudioDenied $e) { t_ok(true, 'designer nemůže odeslat'); }
t_eq($mail->sent, [], 'po odmítnutých pokusech stále žádný e-mail');
$d = $S->sendDelivery($d['id'], 3, ' Petra@Salon.example ', 'idem-12345678', 'matous@tasstudio.example', 'Matouš');
t_ok($d['status'] === 'sent' && $d['method'] === 'email' && $d['active'] && count($mail->sent) === 1, 'odesláno po potvrzení: aktivní odkaz, jeden e-mail');
$m = $mail->sent[0];
t_ok($m['to'] === 'petra@salon.example' && strpos($m['text'], $url) !== false && strpos($m['text'], '{') === false && strpos($m['text'], 'Matouš') !== false && $m['replyTo'] === 'matous@tasstudio.example' && strpos($m['html'], '<a href="' . $url . '">') !== false, 'e-mail: odkaz, datum a podpis doplněné, Reply-To pracovníka, HTML s odkazem', $m['text']);
$again = $S->sendDelivery($d['id'], 3, 'petra@salon.example', 'idem-12345678');
t_ok(!empty($again['repeated']) && count($mail->sent) === 1, 'dvojí odeslání se stejným klíčem = jeden e-mail');
try { $S->sendDelivery($d['id'], 3, 'petra@salon.example', 'idem-jiny-0000'); t_ok(false, 'resend'); } catch (InvalidArgumentException $e) { t_ok(count($mail->sent) === 1, 'odeslané doručení nejde odeslat znovu jiným klíčem'); }
t_ok(abs(strtotime($d['expires_at']) - (time() + 7 * 86400)) < 120, 'platnost se počítá od odeslání (7 dní)');

/* --- brána náhledu --- */
[$c, $h, $b] = $gate->handle('GET', "/$T", '2.2.2.2');
t_ok($c === 301 && $h['Location'] === "/$T/", 'adresa bez lomítka → přesměrování (relativní odkazy webu)');
[$c, $h, $b] = $gate->handle('GET', "/$T/", '2.2.2.2');
t_ok($c === 200 && strpos($b, 'Lokna hair studio') !== false && strpos($b, 'neveřejný odkaz platí do') !== false && $h['X-Robots-Tag'] === 'noindex, nofollow, noarchive' && strpos($h['Content-Security-Policy'], "default-src 'none'") === 0 && !isset($h['Set-Cookie']), 'náhled: obsah verze, pruh s platností, noindex, CSP, bez cookies');
[$c, $h, $b] = $gate->handle('GET', "/$T/assets/styles.css", '2.2.2.2');
t_ok($c === 200 && str_starts_with($h['Content-Type'], 'text/css') && strpos($b, '/*@tokens*/') !== false, 'styly verze');
[$c, $h, $b] = $gate->handle('GET', "/$T/assets/fonts/syne-latin.woff2", '2.2.2.2');
t_ok($c === 200 && $h['Content-Type'] === 'font/woff2' && strlen($b) > 1000, 'písmo ze šablony (binární)');
t_eq($gate->handle('GET', "/$T/../../core/TsStudioAi.php", '2.2.2.2')[0], 404, 'průchod adresáři → 404');
t_eq($gate->handle('GET', "/$T/template.json", '2.2.2.2')[0], 404, 'soubor mimo verzi (template.json) → 404');
t_eq($gate->handle('POST', "/$T/", '2.2.2.2')[0], 405, 'POST → 405');
[$c, $h, $b] = $gate->handle('GET', '/robots.txt', '2.2.2.2'); t_ok($c === 200 && strpos($b, 'Disallow: /') !== false, 'robots.txt zakazuje vše');
$row = $S->store()->delivery($d['id']); t_ok((int) $row['views'] === 1 && $row['first_view_at'] !== '', 'počet otevření (jen index.html, bez IP)');
[$c, , $b] = $gate->handle('GET', '/' . str_repeat('A', 32) . '/', '3.3.3.3');
t_ok($c === 404 && strpos($b, 'Salon') === false && strpos($b, 'Odkaz neplatí') !== false, 'neznámý token: neutrální stránka bez informací o projektu');
for ($i = 0; $i < 5; $i++) $gate->handle('GET', '/' . str_repeat('B', 31) . $i . '/', '4.4.4.4');
t_eq($gate->handle('GET', "/$T/", '4.4.4.4')[0], 429, 'po 5 neplatných pokusech z jedné IP blokace (i pro platný odkaz)');
t_eq($gate->handle('GET', "/$T/", '5.5.5.5')[0], 200, 'jiná IP blokovaná není');
t_ok(count(glob("$dir/brana/*.cnt")) > 0 && strpos(implode('', array_map('basename', glob("$dir/brana/*.cnt"))), '4.4.4.4') === false, 'počítadla pokusů bez IP v názvu');
$other = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/d.sqlite"], 'deliverySecret' => str_repeat('z', 40), 'previewBase' => 'https://x/']);
t_eq((new TsStudioPreviewGate($other))->handle('GET', "/$T/", '6.6.6.6')[0], 404, 'jiný tajný klíč → token neplatí (otisk v databázi nestačí)');

/* --- odvolání (scénář 11) --- */
try { $S->revokeDelivery($d['id'], 4); t_ok(false, 'viewer'); } catch (TsStudioDenied $e) { t_ok(true, 'prohlížející nemůže odvolat'); }
$d = $S->revokeDelivery($d['id'], 3);
t_ok($d['status'] === 'revoked' && !$d['active'] && $gate->handle('GET', "/$T/", '7.7.7.7')[0] === 404, 'scénář 11: odvolaný odkaz okamžitě nefunguje');
try { $S->copyLink($d['id'], 3); t_ok(false, 'copy revoked'); } catch (InvalidArgumentException $e) { t_ok(true, 'odvolaný odkaz nejde znovu aktivovat'); }
$acts = array_column($S->get($pid, 1)['log'], 'action');
foreach (['status', 'delivery.prepare', 'delivery.draft', 'delivery.send', 'delivery.revoke'] as $a) t_ok(in_array($a, $acts, true), "záznam: $a");

/* --- ruční cesta, selhání SMTP, expirace --- */
$d2 = $S->prepareDelivery($pid, 1, 1, 30);
$L = $S->copyLink($d2['id'], 1);
t_ok($L['status'] === 'sent' && $L['method'] === 'manual' && $L['active'] && $gate->handle('GET', '/' . $tok($L['url']) . '/', '8.8.8.8')[0] === 200 && count($mail->sent) === 1, 'ruční cesta: zkopírování odkazu ho aktivuje, e-mail se neposílá');
t_ok(in_array('delivery.copy_link', array_column($S->get($pid, 1)['log'], 'action'), true), 'záznam: delivery.copy_link (kdo a kdy)');
$S->logDownload($pid, 1, 3, $d2['id']); t_ok(in_array('delivery.download', array_column($S->get($pid, 1)['log'], 'action'), true), 'záznam: stažení balíčku');
$d3 = $S->prepareDelivery($pid, 1, 3); $S->draftDelivery($d3['id'], 3, ['to_email' => 'spatna@salon.example', 'subject' => 'Návrh', 'body' => '{odkaz}']);
$mail->fail = true; $f = $S->sendDelivery($d3['id'], 3, 'spatna@salon.example', 'idem-fail-0001');
t_ok($f['status'] === 'failed' && !$f['active'] && strpos($f['error'], 'odmítnuta') !== false && in_array('delivery.fail', array_column($S->get($pid, 1)['log'], 'action'), true), 'selhání SMTP: stav failed s chybou, odkaz neaktivní, záznam');
$mail->fail = false; $f = $S->sendDelivery($d3['id'], 3, 'spatna@salon.example', 'idem-fail-0002');
t_ok($f['status'] === 'sent' && count($mail->sent) === 2, 'po selhání lze odeslat znovu (nový klíč)');
$S->store()->setDelivery($d3['id'], ['expires_at' => date('Y-m-d H:i:s', time() - 60)]);
t_eq($gate->handle('GET', '/' . $tok($S->deliveryUrl($S->store()->delivery($d3['id']))) . '/', '9.9.9.9')[0], 404, 'prošlý odkaz nefunguje');
$d5 = $S->prepareDelivery($pid, 1, 3); $S->draftDelivery($d5['id'], 3, ['to_email' => 'petra@salon.example', 'subject' => 'Návrh', 'body' => '{odkaz}']);
$S->setStatus($pid, 'draft', 2);
try { $S->prepareDelivery($pid, 1, 3); t_ok(false, 'draft prepare'); } catch (InvalidArgumentException $e) { t_ok(true, 'po vrácení do konceptu nejde připravit nové sdílení'); }
try { $S->sendDelivery($d5['id'], 3, 'petra@salon.example', 'idem-draft-0001'); t_ok(false, 'draft send'); } catch (InvalidArgumentException $e) { t_ok(count($mail->sent) === 2, 'po vrácení do konceptu nejde odeslat připravený e-mail'); }
$noCfg = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/d.sqlite"]]);
t_ok(!$noCfg->hasDelivery() && $S->hasDelivery(), 'bez tajného klíče a adresy náhledů je doručení vypnuté');

/* --- skutečný TsMailer (ts-mailer-php) přes konfiguraci 'mail': transport log, testovací přesměrování redirectTo --- */
$SM = ts_design_studio(['db' => ['dsn' => "sqlite:$dir/m.sqlite"], 'deliverySecret' => $SECRET, 'previewBase' => 'https://nahledy.example/',
    'mail' => ['from' => 'studio@tasstudio.example', 'fromName' => 'T&S Studio', 'transport' => 'log', 'logFile' => "$dir/posta.log", 'redirectTo' => 'test@tasstudio.example']]);
$mp = $SM->createProject(['name' => 'Mailer', 'source' => 'new', 'template' => 'restaurace'], 1)['id']; $SM->setStatus($mp, 'approved_internal', 1);
$md = $SM->prepareDelivery($mp, 1, 1); $SM->draftDelivery($md['id'], 1, ['to_email' => 'klient@example.cz', 'subject' => 'Návrh', 'body' => "Odkaz: {odkaz}"]);
$r = $SM->sendDelivery($md['id'], 1, 'klient@example.cz', 'idem-real-mailer');
$log = (string) @file_get_contents("$dir/posta.log");
t_ok($r['status'] === 'sent' && strpos($log, 'To: <test@tasstudio.example>') !== false && strpos($log, 'X-TS-Original-To: <klient@example.cz>') !== false, 'TsMailer z konfigurace: testovací režim pošle e-mail jen na testovací adresu');

/* --- uchování: po 24 měsících se příjemce anonymizuje --- */
$S->store()->setDelivery($d['id'], ['created_at' => date('Y-m-d H:i:s', strtotime('-25 months'))]);
$n = $S->purgeOldDeliveries(true); $old = $S->store()->delivery($d['id']);
t_ok($n >= 1 && $old['to_email'] === '' && $old['body'] === '' && $old['status'] === 'revoked', 'starší než 24 měsíců: příjemce a text smazány, záznam zůstal', $n);
t_ok($S->store()->delivery($d3['id'])['to_email'] === 'spatna@salon.example', 'novější doručení beze změny');
