<?php
/* ==========================================================================
   ts-design-studio – stránka klienta u nahraného návrhu (na doméně brány náhledů), bez cookies a bez JavaScriptu:
   /<token>/            návrh v <iframe sandbox> a panel Připomínky (formulář, dřívější připomínky, schválení)
   /<token>/navrh/…     soubory návrhu: CSP sandbox bez přístupu na síť, skripty návrhu smí běžet, ale nedostanou se k panelu
   POST /<token>/pripominka   jen ze stejného původu; po uložení přesměrování zpět (?stav=ok|schvaleno|chyba|limit)
   ========================================================================== */
final class TsStudioClientPage
{
    const CSP_SHELL = "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; frame-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";
    const CSP_DESIGN = "default-src 'none'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data:; media-src 'self'; script-src 'self' 'unsafe-inline'; connect-src 'none'; form-action 'none'; base-uri 'self'; frame-ancestors 'self'; sandbox allow-scripts";
    const TEXT = [
        'cs' => ['title' => 'Návrh webu', 'valid' => 'Neveřejný odkaz, platí do {d}', 'panel' => 'Připomínky', 'intro' => 'Napište, co chcete změnit. Přijde to přímo nám a ozveme se vám.', 'part' => 'Ke které části (nepovinné)', 'whole' => 'Celý návrh', 'text' => 'Vaše připomínka', 'name' => 'Vaše jméno', 'send' => 'Odeslat připomínku',
            'approve' => 'Návrh schvaluji', 'approveHint' => 'Nemáte připomínky? Dejte nám vědět, že návrh schvalujete.', 'approved' => 'Návrh jste schválili {d}.', 'prev' => 'Vaše připomínky', 'done' => 'vyřešeno', 'wait' => 'přijato', 'ok' => 'Děkujeme, připomínku máme.', 'okA' => 'Děkujeme, návrh je schválený. Ozveme se vám.', 'bad' => 'Připomínku se nepodařilo odeslat. Zkontrolujte text a zkuste to znovu.', 'limit' => 'Odeslali jste hodně zpráv najednou. Zkuste to prosím později.', 'frame' => 'Návrh webu', 'jump' => 'Napsat připomínku', 'hint' => 'V návrhu se můžete běžně pohybovat a klikat.'],
        'en' => ['title' => 'Website design', 'valid' => 'Private link, valid until {d}', 'panel' => 'Comments', 'intro' => 'Tell us what you would like to change. It comes straight to us and we will get back to you.', 'part' => 'Which part (optional)', 'whole' => 'The whole design', 'text' => 'Your comment', 'name' => 'Your name', 'send' => 'Send comment',
            'approve' => 'I approve the design', 'approveHint' => 'No comments? Let us know you approve the design.', 'approved' => 'You approved the design on {d}.', 'prev' => 'Your comments', 'done' => 'resolved', 'wait' => 'received', 'ok' => 'Thank you, we have your comment.', 'okA' => 'Thank you, the design is approved. We will be in touch.', 'bad' => 'The comment could not be sent. Please check the text and try again.', 'limit' => 'You have sent many messages at once. Please try again later.', 'frame' => 'Website design', 'jump' => 'Write a comment', 'hint' => 'You can move around and click in the design as usual.'],
    ];
    private TsDesignStudio $s; private array $c;
    public function __construct(TsDesignStudio $s, array $c) { $this->s = $s; $this->c = $c; }

    public function handle(string $method, string $token, string $rest, array $hit, array $base, array $post, array $h, string $query): array
    {
        $file = rawurldecode($rest);
        if ($method === 'POST') return $this->post($token, $hit, $base, $post, $h);
        if ($file === '/' ) return $this->shell($token, $hit, $base, $query);
        if ($file === '/navrh' ) return [301, $base + ['Location' => '/' . $token . '/navrh/'], ''];
        if (str_starts_with($file, '/navrh/')) return $this->design(substr($file, 7), $hit, $base);
        return [404, $base + ['Content-Type' => 'text/html; charset=utf-8'], TsStudioPreviewGate::page('Soubor nenalezen', '')];
    }
    private function post(string $token, array $hit, array $base, array $post, array $h): array
    {
        $o = (string) ($h['origin'] ?? ''); $host = (string) ($h['host'] ?? '');
        if (($h['sec-fetch-site'] ?? '') === 'cross-site' || $o === '' || preg_replace('#^https?://#', '', rtrim($o, '/')) !== $host) return [403, $base + ['Content-Type' => 'text/plain; charset=utf-8'], 'Požadavek nejde ověřit.'];
        $kind = ($post['akce'] ?? '') === 'schvalit' ? 'approve' : 'comment';
        $r = $this->s->addFeedback($hit, ['kind' => $kind, 'body' => $post['text'] ?? '', 'section' => $post['cast'] ?? '', 'author' => $post['jmeno'] ?? '', 'web' => $post['web'] ?? '']);
        $stav = !empty($r['ok']) ? ($kind === 'approve' ? 'schvaleno' : 'ok') : (($r['error'] ?? '') === 'limit' ? 'limit' : 'chyba');
        if (($r['error'] ?? '') === 'robot') $stav = 'ok';                      /* robot nepozná, že ho past chytila */
        return [303, $base + ['Location' => '/' . $token . '/?stav=' . $stav . '#pripominky'], ''];
    }
    private function design(string $path, array $hit, array $base): array
    {
        if ($path === '' || str_ends_with($path, '/')) $path .= 'index.html';
        if (preg_match('~(^|/)\.\.(/|$)|[\x00-\x1F\x7F]~', $path)) return [404, $base + ['Content-Type' => 'text/plain; charset=utf-8'], ''];
        $src = $this->s->uploadFile($hit['version'], $path);
        if ($src === null) return [404, $base + ['Content-Type' => 'text/html; charset=utf-8'], TsStudioPreviewGate::page('Soubor nenalezen', '')];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $type = (TsStudioPreviewGate::MIME + ['htm' => 'text/html; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8', 'map' => 'application/json', 'xml' => 'application/xml', 'avif' => 'image/avif', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf'])[$ext] ?? 'application/octet-stream';
        /* sandbox = neprůhledný původ: písma a moduly se načítají přes CORS, proto ACAO a CORP pro cizí původ. Soubory chrání tajný token v adrese. */
        $hd = ['X-Frame-Options' => 'SAMEORIGIN', 'Content-Security-Policy' => self::CSP_DESIGN, 'Access-Control-Allow-Origin' => '*', 'Cross-Origin-Resource-Policy' => 'cross-origin', 'Cache-Control' => 'private, max-age=300'] + $base;
        return [200, $hd + ['Content-Type' => $type, 'Content-Length' => (string) strlen($src)], $src];
    }
    private function shell(string $token, array $hit, array $base, string $query): array
    {
        $d = $hit['delivery']; $p = $hit['project']; $v = $hit['version'];
        $this->s->touchDelivery($d);
        $lang = (string) ($p['brief']['lang'] ?? 'cs'); $T = self::TEXT[$lang] ?? self::TEXT['en'];
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $date = fn ($s) => date('j. n. Y', strtotime((string) $s));
        parse_str($query, $q); $stav = (string) ($q['stav'] ?? '');
        $msg = ['ok' => ['ok', $T['ok']], 'schvaleno' => ['ok', $T['okA']], 'chyba' => ['bad', $T['bad']], 'limit' => ['bad', $T['limit']]][$stav] ?? null;
        $list = $this->s->store()->feedbackOfDelivery((int) $d['id']); $approved = null; $rows = '';
        foreach (array_reverse($list) as $f) {
            if ($f['kind'] === 'approve') { $approved = $approved ?? $f; continue; }
            if ($f['body'] === '') continue;
            $rows .= '<li><p>' . nl2br($e($f['body'])) . '</p><small>' . $e($date($f['created_at'])) . ($f['section'] !== '' ? ' · ' . $e($f['section']) : '') . ' · ' . $e($f['status'] === 'done' ? $T['done'] : $T['wait']) . '</small></li>';
        }
        $opts = '<option value="">' . $e($T['whole']) . '</option>';
        foreach ((array) ($v['content']['analysis']['sections'] ?? []) as $s) $opts .= '<option value="' . $e($s) . '">' . $e($s) . '</option>';
        $act = '/' . $token . '/pripominka';
        $html = '<!doctype html><html lang="' . $e($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . $e($T['title'] . ': ' . $p['name']) . '</title><style>' . self::css() . '</style></head><body>'
            . '<header class="bar"><b>' . $e($p['name']) . '</b><span>' . $e(str_replace('{d}', $date($d['expires_at']), $T['valid'])) . '</span><a class="jump" href="#pripominky">' . $e($T['jump']) . '</a></header>'
            . '<main class="wrap"><iframe class="dsg" src="/' . $e($token) . '/navrh/" title="' . $e($T['frame']) . '" sandbox="allow-scripts"></iframe>'
            . '<aside class="panel" id="pripominky"><h1>' . $e($T['panel']) . '</h1>'
            . ($msg ? '<p class="msg ' . $msg[0] . '" role="status">' . $e($msg[1]) . '</p>' : '')
            . '<p class="mut">' . $e($T['intro']) . '</p>'
            . '<form method="post" action="' . $e($act) . '"><label>' . $e($T['part']) . '<select name="cast">' . $opts . '</select></label>'
            . '<label>' . $e($T['text']) . '<textarea name="text" rows="5" maxlength="' . TsDesignStudio::FEEDBACK_MAX . '" required></textarea></label>'
            . '<label>' . $e($T['name']) . '<input name="jmeno" maxlength="120" autocomplete="name" value="' . $e($d['to_name']) . '"></label>'
            . '<label class="hp" aria-hidden="true">Web<input name="web" tabindex="-1" autocomplete="off"></label>'
            . '<button type="submit">' . $e($T['send']) . '</button></form>'
            . ($approved ? '<p class="msg ok">' . $e(str_replace('{d}', $date($approved['created_at']), $T['approved'])) . '</p>'
                : '<form method="post" action="' . $e($act) . '" class="apr"><input type="hidden" name="akce" value="schvalit"><input type="hidden" name="jmeno" value="' . $e($d['to_name']) . '"><p class="mut">' . $e($T['approveHint']) . '</p><button type="submit" class="sec">' . $e($T['approve']) . '</button></form>')
            . ($rows ? '<h2>' . $e($T['prev']) . '</h2><ul class="prev">' . $rows . '</ul>' : '')
            . ($this->c['contact'] !== '' ? '<p class="ct">' . $e($this->c['brand'] . ' · ' . $this->c['contact']) . '</p>' : '')
            . '</aside></main></body></html>';
        /* same-origin: formulář jinak pošle Origin: null a ověření původu ho odmítne; token v adrese cizím webům neuteče */
        $hd = ['Content-Security-Policy' => self::CSP_SHELL, 'Content-Type' => 'text/html; charset=utf-8', 'Referrer-Policy' => 'same-origin'] + $base;
        return [200, $hd, $html];
    }
    private static function css(): string
    {
        return ':root{color-scheme:light dark;--b:#f4f4f1;--s:#fff;--i:#141416;--m:#5f5f66;--l:#d6d6d0;--a:#1f5f4a;--o:#fff}@media (prefers-color-scheme:dark){:root{--b:#111113;--s:#1a1a1d;--i:#f0f0ec;--m:#a9a9b0;--l:#37373d;--a:#79c9aa;--o:#0d1f19}}'
            . '*{box-sizing:border-box}html,body{margin:0;height:100%}body{background:var(--b);color:var(--i);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;display:flex;flex-direction:column}'
            . '.bar{display:flex;gap:6px 14px;align-items:center;flex-wrap:wrap;padding:8px 14px;background:var(--s);border-bottom:1px solid var(--l)}.bar span{color:var(--m);font-size:13px}.jump{display:none;margin-left:auto;font-weight:700;color:var(--a);padding:8px 0}'
            . '.wrap{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) 360px}.dsg{width:100%;height:100%;border:0;background:#fff;display:block}'
            . '.panel{background:var(--s);border-left:1px solid var(--l);padding:16px;overflow:auto}.panel h1{font-size:20px;margin:0 0 8px}.panel h2{font-size:15px;margin:20px 0 8px}.mut{color:var(--m);margin:0 0 12px;font-size:14px}'
            . 'label{display:block;font-size:13px;font-weight:650;margin-bottom:10px}input,select,textarea{display:block;width:100%;margin-top:4px;padding:10px;font:inherit;font-weight:400;color:inherit;background:var(--b);border:1px solid var(--l);border-radius:6px;min-height:44px}textarea{resize:vertical}'
            . 'button{width:100%;min-height:46px;border:2px solid var(--a);border-radius:6px;background:var(--a);color:var(--o);font:inherit;font-weight:700;cursor:pointer}button.sec{background:none;color:var(--a)}:focus-visible{outline:3px solid var(--a);outline-offset:2px}'
            . 'form+.msg{margin-top:14px}.apr{margin-top:18px;padding-top:14px;border-top:1px solid var(--l)}.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}.msg{padding:10px 12px;border-radius:6px;font-weight:600;margin:0 0 12px}.msg.ok{background:color-mix(in srgb,var(--a) 16%,transparent)}.msg.bad{background:color-mix(in srgb,#c0392b 18%,transparent)}'
            . '.prev{list-style:none;margin:0;padding:0}.prev li{border-top:1px solid var(--l);padding:10px 0}.prev p{margin:0 0 4px;overflow-wrap:anywhere}.prev small{color:var(--m)}.ct{color:var(--m);font-size:13px;margin:20px 0 0}'
            . '@media (max-width:860px){body{display:block;height:auto}.jump{display:inline-block}.wrap{display:block}.dsg{height:78dvh}.panel{border-left:0;border-top:1px solid var(--l)}}';
    }
}
