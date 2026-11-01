<?php
/* ==========================================================================
   ts-ai-consultant – jednorázová kontrolní stránka pro hosting bez SSH.
   Soubor s náhodným názvem ve webrootu: (new TsAiCheck($settings))->serve();
   Konfigurace: 'check' => ['codeHash' => password_hash(kód), 'until' => 'RRRR-MM-DD'].
   Každá akce vyžaduje kód; 5 špatných pokusů za 15 min = zámek. Hodnota klíče se nikdy nezobrazí.
   Akce:  check  – prostředí (PHP, rozšíření, SDK, klíč, databáze, znalosti, spojení na api.anthropic.com:443) – nic neplatí
          live   – JEDEN skutečný krátký dotaz na Claude (cena v řádu tisícin USD, započte se do rozpočtu)
   ========================================================================== */
final class TsAiCheck
{
    private array $s;
    public function __construct(array $settings) { $this->s = $settings; }

    public function checks(): array
    {
        $s = $this->s; $r = [];
        $r[] = ['PHP ' . PHP_VERSION . ' (potřeba 8.1+)', version_compare(PHP_VERSION, '8.1.0', '>=')];
        foreach (['pdo_sqlite', 'curl', 'json', 'mbstring', 'openssl'] as $e) $r[] = ['rozšíření ' . $e, extension_loaded($e)];
        $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $out = function ($p) use ($doc) { $x = realpath($p); return $x !== false && ($doc === '' || !str_starts_with($x, rtrim($doc, '/') . '/')); };
        $r[] = ['soubor s API klíčem existuje a je mimo webroot (hodnota se nezobrazuje)', ts_ai_api_key($s) !== '' && $out($s['apiKeyFile'] ?? '')];
        $r[] = ['klíč má očekávaný tvar (sk-ant-…)', str_starts_with(ts_ai_api_key($s), 'sk-ant-')];
        $sdk = $s['sdkAutoload'] ?? '';
        $r[] = ['SDK (vendor/autoload.php) je mimo webroot', $sdk !== '' && $out($sdk)];
        $okSdk = false; try { if (!class_exists('Anthropic\\Client') && is_file($sdk)) require_once $sdk; $okSdk = class_exists('Anthropic\\Client') && class_exists('GuzzleHttp\\Client'); } catch (Throwable $e) { }
        $r[] = ['SDK Anthropic a Guzzle se načtou', $okSdk];
        $k = is_file($s['knowledgeFile'] ?? '') ? json_decode((string) file_get_contents($s['knowledgeFile']), true) : null;
        $r[] = ['znalosti: ' . (is_array($k) ? count($k['sections'] ?? []) . ' sekcí, ' . count($k['references'] ?? []) . ' referencí' : 'nenačteny'), is_array($k) && !empty($k['sections']) && $out($s['knowledgeFile'])];
        $r[] = ['cenová pravidla: bez částek', is_array($k) && !empty($k['pricePolicy']['noAmounts'])];
        $dsn = $s['db']['dsn'] ?? ''; $okDb = false;
        try { $st = ts_ai_store($s); $st->pdo()->query('SELECT COUNT(*) FROM ts_ai_usage')->fetchColumn(); $okDb = true; } catch (Throwable $e) { }
        $r[] = ['databáze funguje' . (str_starts_with($dsn, 'sqlite:') ? ' a je mimo webroot' : ''), $okDb && (!str_starts_with($dsn, 'sqlite:') || $out(dirname(substr($dsn, 7))))];
        $probe = $s['check']['probe'] ?? 'ssl://api.anthropic.com:443';      /* jen spojení, žádný dotaz ani klíč */
        $fp = @stream_socket_client($probe, $en, $es, 6); if ($fp) fclose($fp);
        $r[] = ['hosting se spojí s ' . preg_replace('#^\w+://#', '', $probe), (bool) $fp];
        $r[] = ['HTTPS', TsAuth::isHttps() || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)];
        $spent = $okDb ? ts_ai_store($s)->spent() / 1e6 : 0;
        $r[] = [sprintf('rozpočet: utraceno %.4f z %.2f USD tento měsíc', $spent, (float) ($s['monthlyBudgetUsd'] ?? 10)), $spent < (float) ($s['monthlyBudgetUsd'] ?? 10)];
        $r[] = ['model: ' . ($s['claude']['model'] ?? 'claude-opus-5-5'), isset(TsAiConsultant::PRICING[$s['claude']['model'] ?? 'claude-opus-5-5']) || isset($s['pricing'][$s['claude']['model'] ?? ''])];
        return $r;
    }

    /* jeden skutečný dotaz přes bránu (stejné limity, rozpočet a pojistky jako widget) */
    public function live(): array
    {
        $g = ts_ai_consultant($this->s);
        if (!$g->enabled()) return [false, 'asistent je vypnutý (chybí klíč/SDK nebo vyčerpaný rozpočet)'];
        [$code, $o] = $g->handle('POST', 'message', json_encode(['text' => 'Dobrý den, jakou službu mi doporučíte pro nový web kavárny? Odpovězte jednou větou.', 'lang' => 'cs']),
            ['content-type' => 'application/json', 'host' => $_SERVER['HTTP_HOST'] ?? ''], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $fb = $o['reply']['fallback'] ?? '';
        return [$code === 200 && $fb === '', $fb !== '' ? 'náhradní odpověď (' . $fb . '), podrobnosti v logu hostingu' : 'odpověď: ' . mb_substr((string) ($o['reply']['text'] ?? ''), 0, 400)];
    }

    public function serve(): void
    {
        header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex'); header('X-Frame-Options: DENY');
        $c = $this->s['check'] ?? []; $msg = ''; $rows = null;
        $expired = empty($c['codeHash']) || empty($c['until']) || date('Y-m-d') > $c['until'];
        if (!$expired && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $store = ts_ai_store($this->s); $ip = substr(hash('sha256', 'check|' . ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 32);
            if ($store->hits('check-fail:' . $ip, 900) >= 5) $msg = 'Příliš mnoho pokusů, zkuste to za 15 minut.';
            elseif (!password_verify((string) ($_POST['code'] ?? ''), $c['codeHash'])) { $store->hit('check-fail:' . $ip, 1000, 900); $msg = 'Neplatný kód.'; }
            elseif (($_POST['a'] ?? '') === 'live') {
                [$ok, $t] = $this->live(); $msg = ($ok ? 'OK – ' : 'NEPROŠLO – ') . $t;
            } else $rows = $this->checks();
        }
        $e = fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Kontrola AI asistenta</title>'
            . '<style>body{font:16px/1.5 system-ui,sans-serif;max-width:720px;margin:32px auto;padding:0 16px;color:#111}td{padding:6px 8px;border-bottom:1px solid #ddd}.ok{color:#08702a;font-weight:700}.no{color:#b00500;font-weight:700}input,button{font:inherit;padding:10px;min-height:44px}p.m{padding:10px;background:#f4f4f2}</style></head><body><h1>Kontrola AI asistenta</h1>';
        if ($expired) { echo '<p>Kontrola je vypnutá nebo vypršela. Soubor smažte z hostingu.</p></body></html>'; return; }
        if ($msg !== '') echo '<p class="m" role="status">' . $e($msg) . '</p>';
        if ($rows) { echo '<table>'; foreach ($rows as [$t, $ok]) echo '<tr><td class="' . ($ok ? 'ok' : 'no') . '">' . ($ok ? 'OK' : 'CHYBA') . '</td><td>' . $e($t) . '</td></tr>'; echo '</table>'; }
        echo '<form method="post"><p><label>Kontrolní kód<br><input name="code" type="password" autocomplete="off" required></label></p>'
            . '<p><button name="a" value="check">Zkontrolovat prostředí (zdarma)</button> <button name="a" value="live">Jeden skutečný dotaz na Claude (tisíciny USD)</button></p></form>'
            . '<p>Po dokončení tento soubor smažte z hostingu.</p></body></html>';
    }
}
