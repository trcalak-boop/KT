<?php
/* ==========================================================================
   ts-design-studio – doručení návrhu klientovi (zadání oddíl 7): schválení nic neodesílá, doručení je samostatný proces.
   prepared (odkaz existuje, ale NEFUNGUJE) → ready_to_send (e-mail připravený) → sending → sent | failed; kdykoli → revoked.
   Ruční cesta: copyLink() aktivuje odkaz bez e-mailu (method 'manual'). Odkaz: previewBase + token + '/'; v databázi jen otisk tokenu,
   token se odvozuje HMAC z tajného klíče ('deliverySecret'), takže samotná databáze odkazy neprozradí.
   Konfigurace TsDesignStudio: 'deliverySecret' (≥ 32 znaků), 'previewBase' (https://nahledy.…/), 'mailer' (objekt s metodou send() jako TsMailer),
   'leadLookup' => fn(string $leadRef): ?array ['email', 'name'] (předvyplnění příjemce z CRM), 'deliveryText' => ['subject', 'body'] (výchozí text).
   Oprávnění: akce 'deliver' (připravit, e-mail, odeslat, odkaz, odvolat), 'status' (interní schválení).
   ========================================================================== */
trait TsStudioDeliveryOps
{
    public static function defaultDeliveryText(): array
    {
        return ['subject' => 'Návrh webu pro {klient} – T&S Studio',
            'body' => "Dobrý den,\n\nposíláme první návrh webu pro {klient}. Prohlédnout si ho můžete zde:\n{odkaz}\n\n"
                . "Odkaz je neveřejný a platí do {datum}. Návrh funguje na počítači i v mobilu; texty označené [doplnit] doplníme podle vašich podkladů.\n"
                . "Stačí odpovědět na tento e-mail – rádi návrh upravíme podle vašich připomínek.\n\nS pozdravem\n{jmeno}\nT&S Studio · tasstudio.eu"];
    }
    private function secret(): string
    {
        $s = (string) ($this->c['deliverySecret'] ?? ''); if (strlen($s) < 32) throw new InvalidArgumentException('doručení není nastavené (deliverySecret)');
        return $s;
    }
    private function tokenOf(array $d): string { return substr(rtrim(strtr(base64_encode(hash_hmac('sha256', $d['project_id'] . '|' . $d['num'] . '|' . $d['nonce'], $this->secret(), true)), '+/', '-_'), '='), 0, 32); }
    public function deliveryUrl(array $d): string { return rtrim((string) ($this->c['previewBase'] ?? ''), '/') . '/' . $this->tokenOf($d) . '/'; }
    private function deliveryOf(int $id, int $uid, string $act = 'deliver'): array
    {
        $d = $this->store->delivery($id); if (!$d) throw new InvalidArgumentException('doručení neexistuje');
        $this->project((int) $d['project_id'], $uid, $act); return $d;
    }
    /* veřejný pohled na doručení (bez otisku a nonce) */
    public function deliveryView(array $d): array
    {
        $active = $this->deliveryActive($d);
        return ['id' => (int) $d['id'], 'num' => (int) $d['num'], 'status' => $d['status'], 'method' => $d['method'], 'days' => (int) $d['days'], 'expires_at' => $d['expires_at'], 'active' => $active,
            'to_email' => $d['to_email'], 'to_name' => $d['to_name'], 'subject' => $d['subject'], 'body' => $d['body'], 'sent_at' => $d['sent_at'], 'error' => $d['error'],
            'revoked_at' => $d['revoked_at'], 'views' => (int) $d['views'], 'first_view_at' => $d['first_view_at'], 'last_view_at' => $d['last_view_at'], 'created_at' => $d['created_at']];
    }
    /* lead z CRM pro zadání AI: 'leadBrief' => fn(string $ref): ?array ['lead' => řádek leadu, 'notes' => [poznámky]] (jen čtení CRM) */
    public function leadBriefFor(string $ref): ?array
    {
        if (!is_callable($this->c['leadBrief'] ?? null)) return null;
        $r = ($this->c['leadBrief'])($ref);
        return is_array($r) && isset($r['lead']) && is_array($r['lead']) ? ['lead' => $r['lead'], 'notes' => array_map('strval', (array) ($r['notes'] ?? []))] : null;
    }
    /* uchování: doručení starší než 'retentionMonths' (výchozí 24) – příjemce a text e-mailu se anonymizují (záznam o doručení zůstane); nejvýš jednou denně */
    public function purgeOldDeliveries(bool $force = false): int
    {
        $months = (int) ($this->c['retentionMonths'] ?? 24); if ($months <= 0) return 0;
        static $done = [];
        $key = spl_object_id($this->store) . '|' . date('Y-m-d');
        if (!$force && isset($done[$key])) return 0;
        $done[$key] = true;
        $before = date('Y-m-d H:i:s', strtotime("-$months months"));
        $this->store->anonymizeFeedback($before);
        return $this->store->anonymizeDeliveries($before);
    }
    public function hasDelivery(): bool { return strlen((string) ($this->c['deliverySecret'] ?? '')) >= 32 && (string) ($this->c['previewBase'] ?? '') !== ''; }
    /* detail pro rozhraní: náhled e-mailu s odkazem (odkaz samotný jen u aktivního doručení – kopírování je samostatná zaznamenaná akce) */
    public function deliveryDetail(int $id, int $uid, string $sender = ''): array
    {
        $d = $this->store->delivery($id); if (!$d) throw new InvalidArgumentException('doručení neexistuje');
        $this->project((int) $d['project_id'], $uid, 'view');
        $m = $this->deliveryMessage($d, $sender); $active = $this->deliveryActive($d);
        if (!$active) { $m['text'] = str_replace($m['url'], '[odkaz se aktivuje odesláním]', $m['text']); unset($m['html']); $m['url'] = ''; } else unset($m['html']);
        return $this->deliveryView($d) + ['message' => $m];
    }
    public function logDownload(int $pid, int $num, int $uid, int $deliveryId): void
    {
        $this->project($pid, $uid, 'export'); $this->store->log($pid, $num, $uid, 'delivery.download', "balíček v$num" . ($deliveryId ? " k doručení $deliveryId" : ''));
    }
    public function deliveryActive(array $d): bool
    {
        return $d['activated_at'] !== '' && $d['revoked_at'] === '' && $d['status'] === 'sent' && $d['expires_at'] > TsStudioStore::now();
    }
    public function deliveries(int $pid, int $uid): array
    {
        $this->project($pid, $uid, 'view');
        return array_map([$this, 'deliveryView'], $this->store->deliveries($pid));
    }

    /* 1. připravit sdílení: jen přijatá verze projektu ve stavu approved_internal; odkaz zatím neaktivní */
    public function prepareDelivery(int $pid, int $num, int $uid, int $days = 14): array
    {
        $p = $this->project($pid, $uid, 'deliver'); $this->secret();
        if ($p['status'] !== 'approved_internal') throw new InvalidArgumentException('sdílet jde jen interně schválený projekt');
        $v = $this->store->version($pid, $num); if (!$v || $v['status'] !== 'accepted') throw new InvalidArgumentException('sdílet jde jen přijatou verzi');
        $days = max(1, min(90, $days)); $nonce = bin2hex(random_bytes(16));
        $txt = self::isUpload($p) ? self::uploadDeliveryText((string) ($p['brief']['lang'] ?? 'cs')) : (array) ($this->c['deliveryText'] ?? []) + self::defaultDeliveryText();
        $lead = ($p['lead_ref'] !== '' && is_callable($this->c['leadLookup'] ?? null)) ? (array) (($this->c['leadLookup'])($p['lead_ref']) ?? []) : [];
        $tmp = ['project_id' => $pid, 'num' => $num, 'nonce' => $nonce];
        $id = $this->store->addDelivery($tmp + ['token_hash' => hash('sha256', $this->tokenOf($tmp)), 'status' => 'prepared', 'days' => $days, 'created_by' => $uid,
            'to_email' => mb_substr((string) ($lead['email'] ?? ''), 0, 190), 'to_name' => mb_substr((string) ($lead['name'] ?? ''), 0, 120),
            'subject' => mb_substr(str_replace('{klient}', $p['name'], (string) $txt['subject']), 0, 200), 'body' => str_replace('{klient}', $p['name'], (string) $txt['body'])]);
        $this->store->log($pid, $num, $uid, 'delivery.prepare', "doručení $id, platnost $days dní");
        return $this->deliveryView($this->store->delivery($id));
    }
    /* 2. e-mail: příjemce, předmět, text (musí obsahovat {odkaz}), platnost → ready_to_send */
    public function draftDelivery(int $id, int $uid, array $f): array
    {
        $d = $this->deliveryOf($id, $uid);
        if (!in_array($d['status'], ['prepared', 'ready_to_send', 'failed'], true)) throw new InvalidArgumentException('doručení už nejde upravit (' . $d['status'] . ')');
        $to = trim((string) ($f['to_email'] ?? '')); $subject = trim(preg_replace('/[\r\n]+/', ' ', (string) ($f['subject'] ?? '')) ?? ''); $body = str_replace("\r\n", "\n", trim((string) ($f['body'] ?? '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || strlen($to) > 190) throw new InvalidArgumentException('neplatný e-mail příjemce');
        if ($subject === '' || mb_strlen($subject) > 200) throw new InvalidArgumentException('předmět je povinný (nejvýš 200 znaků)');
        if (mb_strpos($body, '{odkaz}') === false) throw new InvalidArgumentException('text musí obsahovat {odkaz}');
        if (mb_strlen($body) > 5000) throw new InvalidArgumentException('text je delší než 5 000 znaků');
        $days = max(1, min(90, (int) ($f['days'] ?? $d['days'])));
        $this->store->setDelivery($id, ['to_email' => $to, 'to_name' => mb_substr(trim((string) ($f['to_name'] ?? '')), 0, 120), 'subject' => $subject, 'body' => $body, 'days' => $days, 'status' => 'ready_to_send', 'error' => '']);
        $this->store->log((int) $d['project_id'], (int) $d['num'], $uid, 'delivery.draft', "doručení $id → $to");
        return $this->deliveryView($this->store->delivery($id));
    }
    /* text e-mailu s doplněným odkazem a datem (náhled i odeslání) */
    public function deliveryMessage(array $d, string $sender = ''): array
    {
        $exp = $d['expires_at'] !== '' ? $d['expires_at'] : date('Y-m-d H:i:s', time() + 86400 * (int) $d['days']);
        $map = ['{odkaz}' => $this->deliveryUrl($d), '{datum}' => date('j. n. Y', strtotime($exp)), '{jmeno}' => $sender !== '' ? $sender : 'tým T&S Studio'];
        $text = strtr($d['body'], $map); $subject = strtr($d['subject'], $map);
        $html = '<!doctype html><html lang="cs"><body style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#111">'
            . implode('', array_map(fn ($para) => '<p>' . nl2br(preg_replace_callback('~https?://[^\s<]+~', fn ($m) => '<a href="' . $m[0] . '">' . $m[0] . '</a>', htmlspecialchars($para, ENT_QUOTES, 'UTF-8'))) . '</p>', preg_split("/\n{2,}/", $text)))
            . '</body></html>';
        return ['subject' => $subject, 'text' => $text, 'html' => $html, 'url' => $map['{odkaz}']];
    }
    /* 3. odeslat: SAMOSTATNÉ potvrzení – $confirm musí být opsaná adresa příjemce; $idem chrání proti dvojímu odeslání */
    public function sendDelivery(int $id, int $uid, string $confirm, string $idem, string $replyTo = '', string $sender = ''): array
    {
        $d = $this->deliveryOf($id, $uid);
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $idem)) throw new InvalidArgumentException('chybí klíč idempotence');
        if ($d['idem'] === $idem && in_array($d['status'], ['sending', 'sent'], true)) return $this->deliveryView($d) + ['repeated' => true];
        if ($d['status'] !== 'ready_to_send' && $d['status'] !== 'failed') throw new InvalidArgumentException('nejdřív připravte e-mail (stav ' . $d['status'] . ')');
        if (mb_strtolower(trim($confirm)) !== mb_strtolower($d['to_email'])) throw new InvalidArgumentException('potvrzení nesouhlasí s adresou příjemce');
        $p = $this->store->project((int) $d['project_id']);
        if ($p['status'] !== 'approved_internal') throw new InvalidArgumentException('projekt už není interně schválený');
        $m = $this->c['mailer'] ?? null; if (!is_object($m) || !method_exists($m, 'send')) throw new InvalidArgumentException('odesílání e-mailů není nastavené');
        if (!$this->store->swapDeliveryStatus($id, ['ready_to_send', 'failed'], 'sending')) return $this->deliveryView($this->store->delivery($id)) + ['repeated' => true];
        $this->store->setDelivery($id, ['idem' => $idem]);
        $exp = date('Y-m-d H:i:s', time() + 86400 * (int) $d['days']); $d['expires_at'] = $exp;
        $msg = $this->deliveryMessage($d, $sender);
        try { $r = $m->send($d['to_email'], $msg['subject'], $msg['text'], $msg['html'], $replyTo ?: null, $sender); }
        catch (Throwable $e) { $r = ['ok' => false, 'chyba' => 'výjimka odesílání']; }
        if (!empty($r['ok'])) {
            $this->store->setDelivery($id, ['status' => 'sent', 'method' => 'email', 'activated_at' => TsStudioStore::now(), 'expires_at' => $exp, 'sent_by' => $uid, 'sent_at' => TsStudioStore::now(), 'error' => '']);
            $this->store->log((int) $d['project_id'], (int) $d['num'], $uid, 'delivery.send', "doručení $id → {$d['to_email']}, platnost do $exp");
        } else {
            $err = mb_substr((string) ($r['chyba'] ?? 'neznámá chyba'), 0, 300);
            $this->store->setDelivery($id, ['status' => 'failed', 'error' => $err, 'idem' => '']);
            $this->store->log((int) $d['project_id'], (int) $d['num'], $uid, 'delivery.fail', "doručení $id: $err");
        }
        return $this->deliveryView($this->store->delivery($id));
    }
    /* ruční cesta: aktivuje odkaz bez e-mailu a vrátí ho (zaznamená, kdo a kdy) */
    public function copyLink(int $id, int $uid): array
    {
        $d = $this->deliveryOf($id, $uid);
        if ($d['status'] === 'revoked') throw new InvalidArgumentException('odkaz je odvolaný');
        $p = $this->store->project((int) $d['project_id']);
        if (!$this->deliveryActive($d)) {
            if ($p['status'] !== 'approved_internal') throw new InvalidArgumentException('projekt už není interně schválený');
            if (!in_array($d['status'], ['prepared', 'ready_to_send', 'failed'], true)) throw new InvalidArgumentException('odkaz nejde aktivovat (' . $d['status'] . ')');
            $exp = date('Y-m-d H:i:s', time() + 86400 * (int) $d['days']);
            $this->store->setDelivery($id, ['status' => 'sent', 'method' => 'manual', 'activated_at' => TsStudioStore::now(), 'expires_at' => $exp, 'sent_by' => $uid, 'sent_at' => TsStudioStore::now()]);
        }
        $this->store->log((int) $d['project_id'], (int) $d['num'], $uid, 'delivery.copy_link', "doručení $id");
        $d = $this->store->delivery($id);
        return $this->deliveryView($d) + ['url' => $this->deliveryUrl($d)];
    }
    public function revokeDelivery(int $id, int $uid): array
    {
        $d = $this->deliveryOf($id, $uid);
        if ($d['status'] === 'revoked') return $this->deliveryView($d);
        if ($d['status'] === 'sending') throw new InvalidArgumentException('právě se odesílá – zkuste za chvíli');
        $this->store->setDelivery($id, ['status' => 'revoked', 'revoked_at' => TsStudioStore::now(), 'revoked_by' => $uid]);
        $this->store->log((int) $d['project_id'], (int) $d['num'], $uid, 'delivery.revoke', "doručení $id");
        return $this->deliveryView($this->store->delivery($id));
    }
    /* pro bránu náhledu: aktivní doručení podle tokenu (null = neplatné, prošlé, odvolané nebo neaktivní) + soubory verze */
    public function deliveryForToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{32}$/', $token)) return null;
        $d = $this->store->deliveryByHash(hash('sha256', $token));
        if (!$d || !$this->deliveryActive($d) || !hash_equals($this->tokenOf($d), $token)) return null;
        $p = $this->store->project((int) $d['project_id']); $v = $this->store->version((int) $d['project_id'], (int) $d['num']);
        if (!$p || !$v) return null;
        try { $t = $this->template($p['template']); } catch (InvalidArgumentException $e) { return null; }   /* šablona chybí (např. starší kopie knihovny v bráně) → neutrální 404 */
        return ['delivery' => $d, 'project' => $p, 'version' => $v, 'template' => $t];
    }
    public function touchDelivery(array $d): void
    {
        $now = TsStudioStore::now();
        $this->store->setDelivery((int) $d['id'], ['views' => (int) $d['views'] + 1, 'last_view_at' => $now] + ($d['first_view_at'] === '' ? ['first_view_at' => $now] : []));
    }
}
