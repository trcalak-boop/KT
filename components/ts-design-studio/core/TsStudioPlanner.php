<?php
/* ==========================================================================
   ts-design-studio – návrh na míru (etapa 6.7b): zadání → návrhový plán od modelu → stavebnice (TsStudioKit).
   Model vrací jen JSON plán; nikdy HTML. Po něm běží postupně:
   normalize (tolerance k odpovědi modelu) → clean (schéma) → ground (fakta jen ze zadání) → repair (hotová kostra webu, funkční odkazy) → diversify (odlišnost).
   ========================================================================== */
final class TsStudioPlanner
{
    const DIRECTIONS = [
        'klidný a čistý: hodně vzduchu, jemná typografie, zdrženlivé barvy, tenké linky',
        'výrazný a sebevědomý: velká typografie, kontrastní pásy, odvážný akcent',
        'teplý a osobní: vyprávění, měkké tvary, přívětivé písmo, hřejivé barvy',
    ];
    const SIMILAR = 0.75;

    /* ---------- pokyny pro model ---------- */
    public static function catalogText(TsStudioKit $kit): string
    {
        $slot = function (string $k, array $s): string {
            $t = $s['type'] ?? 'text';
            $g = !empty($s['grounded']) ? '*' : '';
            if ($t === 'enum') return $k . $g . ':ilustrace';
            return $k . $g . ($t === 'text' || $t === 'multiline' ? ':' . ($s['max'] ?? 200) : ':' . $t);
        };
        $out = [];
        foreach ($kit->sections() as $type => $d) {
            $sl = [];
            foreach ($d['slots'] as $k => $s) {
                if (($s['type'] ?? '') === 'list') {
                    $f = []; foreach ((array) $s['fields'] as $fk => $fs) {
                        if (($fs['type'] ?? '') === 'list') { $sub = []; foreach ((array) $fs['fields'] as $gk => $gs) $sub[] = $slot($gk, $gs); $f[] = $fk . '[≤' . ($fs['items'] ?? 8) . ']{' . implode(', ', $sub) . '}'; }
                        else $f[] = $slot($fk, $fs);
                    }
                    $sl[] = $k . '[≤' . ($s['items'] ?? 12) . ']{' . implode(', ', $f) . '}';
                } else $sl[] = $slot($k, $s);
            }
            $v = []; foreach ($d['variants'] as $id => $x) $v[] = "$id ($x)";
            $out[] = "$type – {$d['use']}\n  varianty: " . implode(' | ', $v) . "\n  sloty: " . implode(', ', $sl);
        }
        return implode("\n", $out);
    }
    public static function tokensText(TsStudioKit $kit): string
    {
        $out = [];
        foreach ($kit->kit['tokens'] as $k => $d) if ($d['type'] === 'enum') $out[] = "$k: " . implode('|', $d['options']) . ' – ' . $d['label'];
        $f = []; foreach ($kit->fonts as $id => $x) $f[] = "$id ({$x['kind']}, {$x['note']})";
        return 'Barvy #rrggbb: ' . implode(', ', array_keys(array_filter($kit->kit['tokens'], fn ($d) => $d['type'] === 'color'))) . " (d_ = tmavý režim)\n"
            . implode("\n", $out) . "\nPísma: f_display (nadpisy), f_body (text, ne display), f_mono (štítky, jen mono) – přesně tato id:\n" . implode("\n", $f);
    }
    public static function createPrompt(TsStudioKit $kit, array $b, string $direction = '', array $avoid = [], ?int $now = null): array
    {
        $now = $now ?? time();
        $sys = 'Jsi art director a copywriter webového studia T&S. Navrhuješ pro konkrétního klienta jednostránkový web NA MÍRU ze stavebnice sekcí.' . "\n"
            . "Pravidla:\n"
            . '1. Odpověz JEN platným JSON objektem {"site": {…}, "tokens": {…}, "sections": [{"type", "variant", "tone", "id", "nav", "content": {…}}], "css": "", "notes": "…"}. Žádný text okolo, žádný markdown, žádné HTML.' . "\n"
            . '2. Strukturu odvoď z potřeb tohoto klienta: vyber 5 až 10 sekcí, které mu opravdu pomohou splnit cíl, a seřaď je podle toho, co návštěvník hledá. Nepoužívej sekci jen proto, že existuje. První je hero (nad ním smí být jen notice), sekce contact nesmí chybět.' . "\n"
            . '3. Vizuál odvoď ze stylu a oboru klienta: vlastní paleta pro světlý i tmavý režim, dvojice písem, tvary, rytmus. Střídej tóny sekcí (base, surface, band, accent), ale band nebo accent nejvýš dvakrát. Text musí mít kontrast aspoň 4,5:1.' . "\n"
            . '4. Texty piš v jazyce „' . $b['lang'] . '“, konkrétně pro tohoto klienta, bez frází a bez emoji. Dodrž maximální délky (číslo u slotu = znaky) a počty položek. Nepoužité sloty vynech.' . "\n"
            . '5. FAKTA NEVYMÝŠLEJ. Sloty s hvězdičkou (*) a site.phone, site.email, site.address, site.legal smí obsahovat jen údaje ze zadání: ceny, časy, jména, recenze, citáty, čísla. Co v zadání chybí, napiš „' . TsStudioAi::TODO . '“ nebo celou sekci (reviews, quote, team, stats) vynech. Čísla a roky nepiš ani do volných textů, pokud nejsou v zadání.' . "\n"
            . '6. Odkazy: kotvy na id sekcí (#kontakt) nebo mailto:/tel: ze zadání. nav = krátký název do menu (3 až 6 sekcí), jinde prázdné. id krátce malými písmeny bez diakritiky.' . "\n"
            . '7. css: nejvýš pár pravidel, jen když charakter návrhu nejde dosáhnout tokeny. Selektory začínají .s-typ nebo #id sekce; bez barev, url() a @import; velikosti v rem, px nebo clamp().' . "\n"
            . '8. notes: jedna až dvě věty pro designéra: proč tento směr a co doplnit.'
            . (!empty($b['rights']) ? "\n9. Texty klienta jsou převzaté: do notes připiš „převzatý obsah – ověřit práva“." : '');
        $user = '# Dnes: ' . date('j. n. Y', $now) . "\n\n# Zadání klienta\n" . TsStudioAi::briefText($b)
            . ($direction !== '' ? "\n\n# Směr tohoto návrhu (požadavky klienta na styl mají přednost)\n$direction" : '')
            . ($avoid ? "\n\n# Vyhni se podobnosti s předchozími návrhy (jiná písma, paleta i rozvržení)\n- " . implode("\n- ", array_slice($avoid, 0, 4)) : '')
            . "\n\n# Sekce (typ – k čemu; * = jen ze zadání; ilustrace: " . implode('|', $kit->arts()) . ")\n" . self::catalogText($kit)
            . "\n\n# Tokeny\n" . self::tokensText($kit);
        return [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]];
    }
    public static function editPrompt(TsStudioKit $kit, array $plan, array $tokens, string $instruction, array $history = []): array
    {
        $sys = 'Jsi art director webového studia T&S a upravuješ návrh webu ze stavebnice podle pokynu kolegy. Odpověz JEN platným JSON objektem:' . "\n"
            . '{"reply": "krátce česky, co se změnilo", "site": {jen měněné}, "tokens": {jen měněné}, "css": "celá vrstva, jen když ji měníš", "ops": [{"op": "set", "id": "…", "variant": "…", "tone": "…", "nav": "…", "content": {jen měněné sloty, seznam vždy celý}}, {"op": "add", "after": "id nebo prázdné", "section": {"type", "variant", "tone", "id", "nav", "content"}}, {"op": "remove", "id": "…"}, {"op": "move", "id": "…", "after": "id nebo prázdné = na začátek"}]}' . "\n"
            . 'Neměněné vynech. Fakta (* a kontakty) nevymýšlej, chybějící = „' . TsStudioAi::TODO . '“. Text musí mít kontrast aspoň 4,5:1. V css žádné barvy, url() ani @import.';
        $sum = [];
        foreach ($plan['sections'] as $s) $sum[] = ['id' => $s['id'], 'type' => $s['type'], 'variant' => $s['variant'], 'tone' => $s['tone'], 'nav' => $s['nav'], 'content' => array_filter($s['content'], fn ($x) => $x !== '' && $x !== [])];
        $used = array_values(array_unique(array_column($plan['sections'], 'type')));
        $user = "# Pokyn\n$instruction\n\n# Současný plán\n" . json_encode(['site' => $plan['site'], 'sections' => $sum, 'css' => $plan['css'] ?? ''], JSON_UNESCAPED_UNICODE)
            . "\n\n# Současné tokeny\n" . json_encode(array_intersect_key($tokens, $kit->kit['tokens']), JSON_UNESCAPED_UNICODE)
            . "\n\n# Sekce (* = jen ze zadání)\n" . self::catalogText($kit) . "\n\n# Možnosti tokenů\n" . self::tokensText($kit) . "\n\nPoužité typy: " . implode(', ', $used);
        $msgs = [['role' => 'system', 'content' => $sys]];
        foreach (array_slice($history, -4) as $h) $msgs[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr((string) $h['text'], 0, 400)];
        $msgs[] = ['role' => 'user', 'content' => $user];
        return $msgs;
    }

    /* ---------- tolerance k odpovědi modelu ---------- */
    public static function normalize(TsStudioKit $kit, array $j): array
    {
        $tk = [];
        foreach ((array) ($j['tokens'] ?? []) as $k => $v) {
            if (!is_scalar($v)) continue;
            $v = trim((string) $v);
            if (($kit->kit['tokens'][$k]['type'] ?? '') === 'font') $v = self::fontId($kit, $v);
            $tk[$k] = $v;
        }
        $sections = [];
        foreach ((array) ($j['sections'] ?? []) as $s) { if (is_array($s)) $sections[] = self::section($kit, $s); }
        return ['site' => (array) ($j['site'] ?? []), 'tokens' => $tk, 'sections' => $sections, 'css' => is_string($j['css'] ?? null) ? $j['css'] : ''];
    }
    private static function fontId(TsStudioKit $kit, string $v): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim(explode(',', $v)[0], " \"'"))), '-');
        if (isset($kit->fonts[$slug])) return $slug;
        foreach ($kit->fonts as $id => $f) if (strcasecmp($f['family'], trim(explode(',', $v)[0], " \"'")) === 0) return $id;
        return $v;
    }
    /* sloty přímo v sekci (bez „content“) se přesunou; prázdné hodnoty pryč */
    private static function section(TsStudioKit $kit, array $s): array
    {
        if (!isset($s['content']) || !is_array($s['content'])) $s['content'] = array_diff_key($s, array_flip(['type', 'variant', 'tone', 'id', 'nav', 'content']));
        return $s;
    }

    /* ---------- fakta jen ze zadání ---------- */
    private static function digits(string $s): array { preg_match_all('/\d+/', $s, $m); return array_values(array_diff($m[0], ['0', '00'])); }
    /* hodnota sedí na zdroj: aspoň 80 % slov (≥ 4 znaky) a všechna čísla (0 a 00 u časů se nepočítají) */
    public static function groundedOk(string $v, string $source): bool
    {
        if ($v === '' || mb_strpos($v, TsStudioAi::TODO) !== false) return true;
        $words = fn (string $s) => array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)) ?: [], fn ($w) => mb_strlen($w) >= 4 && !ctype_digit($w)));
        $src = array_flip($words($source)); $w = $words($v);
        if ($w && count(array_filter($w, fn ($x) => isset($src[$x]))) / count($w) < 0.8) return false;
        return !array_diff(self::digits($v), self::digits($source));
    }
    /* → [plán, zahozené cesty, podezřelá čísla ve volných textech] */
    public static function ground(TsStudioKit $kit, array $p, string $source): array
    {
        $dropped = []; $suspect = [];
        $srcDigits = preg_replace('/\D+/', '', $source); $srcLow = mb_strtolower($source);
        $contact = function (string $type, string $v) use ($srcDigits, $srcLow): bool {
            if ($v === '') return true;
            if ($type === 'phone') { $d = preg_replace('/\D+/', '', $v); $d = preg_replace('/^(00)?420/', '', $d); return $d !== '' && strpos($srcDigits, $d) !== false; }
            return mb_strpos($srcLow, mb_strtolower($v)) !== false;
        };
        foreach (['phone' => 'phone', 'email' => 'email'] as $k => $type) if (!$contact($type, (string) ($p['site'][$k] ?? ''))) { $p['site'][$k] = ''; $dropped[] = "site.$k"; }
        foreach (['address', 'legal'] as $k) if (!self::groundedOk((string) ($p['site'][$k] ?? ''), $source)) { $p['site'][$k] = ''; $dropped[] = "site.$k"; }
        $check = function (array $slots, array $c, string $path) use (&$check, &$dropped, &$suspect, $source, $contact): array {
            foreach ($slots as $k => $s) {
                if (!array_key_exists($k, $c)) continue;
                $type = $s['type'] ?? 'text';
                if ($type === 'list') { foreach ((array) $c[$k] as $i => $row) if (is_array($row)) $c[$k][$i] = $check((array) ($s['fields'] ?? []), $row, "$path.$k.$i"); continue; }
                $v = (string) $c[$k];
                if (in_array($type, ['phone', 'email'], true)) { if (!$contact($type, $v)) { $c[$k] = ''; $dropped[] = "$path.$k"; } continue; }
                if (!empty($s['grounded'])) { if (!self::groundedOk($v, $source)) { $c[$k] = TsStudioAi::TODO; $dropped[] = "$path.$k"; } continue; }
                if (in_array($type, ['text', 'multiline'], true) && array_diff(self::digits($v), self::digits($source))) $suspect[] = "$path.$k";
            }
            return $c;
        };
        foreach ($p['sections'] as $i => $s) $p['sections'][$i]['content'] = $check($kit->sections()[$s['type']]['slots'], $s['content'], $s['id']);
        return [$p, $dropped, $suspect];
    }

    /* ---------- kostra webu ---------- */
    /* co se opravilo → seznam pro designéra */
    public static function repair(TsStudioKit $kit, array $p, array $b): array
    {
        $notes = []; $todo = TsStudioAi::TODO;
        $bad = fn (string $v) => $v === '' || mb_strpos($v, $todo) !== false;
        /* položky bez podkladu a sekce bez obsahu */
        $keep = [];
        foreach ($p['sections'] as $s) {
            $c = &$s['content'];
            if ($s['type'] === 'reviews') $c['items'] = array_values(array_filter($c['items'], fn ($r) => !$bad($r['text']) && !$bad($r['author'])));
            if ($s['type'] === 'team') $c['items'] = array_values(array_filter($c['items'], fn ($r) => !$bad($r['name'])));
            if ($s['type'] === 'stats') $c['items'] = array_values(array_filter($c['items'], fn ($r) => !$bad($r['value'])));
            $empty = match ($s['type']) {
                'quote' => $bad($c['quote']), 'hero' => $c['title'] === '', 'notice' => $c['text'] === '', 'text' => !$c['paragraphs'], 'cta' => $c['title'] === '',
                'contact' => false, default => isset($c['items']) && !$c['items'] };
            unset($c);
            if ($empty) { $notes[] = 'sekce „' . $s['id'] . '“ bez podkladů odstraněna'; continue; }
            $keep[] = $s;
        }
        /* pořadí: upozornění a úvod nahoře, jeden úvod */
        $hero = null; $top = []; $rest = [];
        foreach ($keep as $s) {
            if ($s['type'] === 'hero') { if ($hero === null) $hero = $s; continue; }
            if ($s['type'] === 'notice' && !$top && $hero === null) { $top[] = $s; continue; }
            $rest[] = $s;
        }
        if ($hero === null) {
            $notes[] = 'doplněn úvod stránky';
            $hero = ['type' => 'hero', 'variant' => 'center', 'tone' => 'base', 'id' => 'uvod', 'nav' => '', 'content' => ['title' => (string) ($b['name'] ?? $p['site']['brand'] ?? ''), 'lead' => (string) ($p['site']['tagline'] ?? '')]];
        }
        $list = array_merge($top, [$hero], $rest);
        /* kontakt nesmí chybět */
        $hasContact = false; foreach ($list as $s) if ($s['type'] === 'contact') $hasContact = true;
        if (!$hasContact) {
            $notes[] = 'doplněna sekce kontaktu';
            $list[] = ['type' => 'contact', 'variant' => 'split', 'tone' => 'surface', 'id' => 'kontakt', 'nav' => ($p['site']['lang'] ?? 'cs') === 'cs' ? 'Kontakt' : 'Contact',
                'content' => ['title' => ($p['site']['lang'] ?? 'cs') === 'cs' ? 'Kontakt' : 'Contact', 'address' => $p['site']['address'] ?? $todo, 'phone' => $p['site']['phone'] ?? '', 'email' => $p['site']['email'] ?? '']];
        }
        $p['sections'] = $list;
        [$p] = $kit->clean($p + ['css' => '']) + [1 => []];
        /* údaje webu */
        if (($p['site']['brand'] ?? '') === '') $p['site']['brand'] = mb_substr((string) ($b['name'] ?? ''), 0, 40);
        if (($p['site']['title'] ?? '') === '') $p['site']['title'] = $p['site']['brand'];
        /* odkazy: kotvy jen na existující sekce */
        $ids = array_flip(array_column($p['sections'], 'id'));
        $contact = null; foreach ($p['sections'] as $s) if ($s['type'] === 'contact') $contact = '#' . $s['id'];
        $fix = function (array $slots, array $c) use (&$fix, $ids, $contact, &$notes): array {
            foreach ($slots as $k => $s) {
                if (!array_key_exists($k, $c)) continue;
                if (($s['type'] ?? '') === 'list') { foreach ((array) $c[$k] as $i => $row) if (is_array($row)) $c[$k][$i] = $fix((array) ($s['fields'] ?? []), $row); continue; }
                if (($s['type'] ?? '') === 'url' && isset($c[$k][0]) && $c[$k][0] === '#' && !isset($ids[substr($c[$k], 1)])) $c[$k] = $contact ?? '';
                /* popisek bez odkazu: tlačítko vede na kontakt */
                if (str_ends_with($k, '_url') && $c[$k] === '') { $l = substr($k, 0, -4) . '_label'; if (isset($c[$l]) && $c[$l] !== '') $c[$k] = $contact ?? ''; }
            }
            return $c;
        };
        foreach ($p['sections'] as $i => $s) $p['sections'][$i]['content'] = $fix($kit->sections()[$s['type']]['slots'], $s['content']);
        $p['site'] = $fix($kit->kit['siteSlots'], $p['site']);
        /* menu: aspoň něco, když model nic nenavrhl (česky podle názvů sekcí) */
        if (count(array_filter(array_column($p['sections'], 'nav'))) < 2 && ($p['site']['lang'] ?? 'cs') === 'cs') {
            $n = 0;
            foreach ($p['sections'] as $i => $s) if ($s['nav'] === '' && !in_array($s['type'], ['hero', 'notice', 'cta', 'quote'], true) && $n < 5) { $p['sections'][$i]['nav'] = mb_substr($kit->sections()[$s['type']]['label'], 0, 24); $n++; }
        }
        return [$p, $notes];
    }

    /* ---------- odlišnost návrhů ---------- */
    public static function signature(array $plan, array $tokens): array
    {
        $layout = []; foreach ($plan['sections'] as $s) $layout[] = $s['type'] . '/' . $s['variant'];
        return ['layout' => $layout, 'tones' => array_column($plan['sections'], 'tone'), 'display' => $tokens['f_display'] ?? '', 'body' => $tokens['f_body'] ?? '', 'accent' => $tokens['accent'] ?? '', 'bg' => $tokens['bg'] ?? '', 'radius' => $tokens['radius'] ?? '', 'btn' => $tokens['btn'] ?? '', 'h_case' => $tokens['h_case'] ?? ''];
    }
    private static function hsl(string $h): array
    {
        $r = hexdec(substr($h, 1, 2)) / 255; $g = hexdec(substr($h, 3, 2)) / 255; $b = hexdec(substr($h, 5, 2)) / 255;
        $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2; $d = $max - $min;
        if ($d < .02) return [0.0, 0.0, $l];
        $s = $d / (1 - abs(2 * $l - 1)); $hue = $max === $r ? fmod(($g - $b) / $d + 6, 6) : ($max === $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4);
        return [$hue * 60, $s, $l];
    }
    /* 0 = jiné, 1 = stejné: rozvržení 40 %, písma 20 %, paleta 25 %, tvar 15 % */
    public static function similarity(array $a, array $b): float
    {
        $x = array_unique($a['layout']); $y = array_unique($b['layout']);
        $layout = ($x || $y) ? count(array_intersect($x, $y)) / max(1, count(array_unique(array_merge($x, $y)))) : 1;
        $fonts = (($a['display'] === $b['display']) ? .6 : 0) + (($a['body'] === $b['body']) ? .4 : 0);
        $pal = 0.5;
        if (preg_match('/^#[0-9a-f]{6}$/', $a['accent'] . '') && preg_match('/^#[0-9a-f]{6}$/', $b['accent'] . '') && $a['bg'] !== '' && $b['bg'] !== '') {
            [$h1, $s1] = self::hsl($a['accent']); [$h2, $s2] = self::hsl($b['accent']); [, , $l1] = self::hsl($a['bg']); [, , $l2] = self::hsl($b['bg']);
            $dh = abs($h1 - $h2); $dh = min($dh, 360 - $dh) / 180; if ($s1 < .1 || $s2 < .1) $dh = abs($s1 - $s2) < .1 ? 0 : 1;
            $pal = max(0, 1 - $dh) * .7 + (1 - abs($l1 - $l2)) * .3;
        }
        $shape = (($a['radius'] === $b['radius']) + ($a['btn'] === $b['btn']) + ($a['h_case'] === $b['h_case'])) / 3;
        return round($layout * .4 + $fonts * .2 + $pal * .25 + $shape * .15, 3);
    }
    /* příliš podobný návrh: jiné varianty rozvržení a tvar, obsah a paleta zůstanou (bez dalšího dotazu na model) */
    public static function diversify(TsStudioKit $kit, array $p, array $tokens, array $others): array
    {
        $max = 0.0; $sig = self::signature($p, $tokens);
        foreach ($others as $o) $max = max($max, self::similarity($sig, $o));
        if ($max < self::SIMILAR) return [$p, $tokens, $max, false];
        foreach ($p['sections'] as $i => $s) {
            if ($i % 2 === 0 && $s['type'] !== 'hero') continue;
            $v = array_keys($kit->sections()[$s['type']]['variants']); $n = array_search($s['variant'], $v, true);
            $p['sections'][$i]['variant'] = $v[((int) $n + 1) % count($v)];
        }
        $next = function (string $k) use ($kit, &$tokens) { $o = $kit->kit['tokens'][$k]['options']; $n = array_search($tokens[$k] ?? '', $o, true); $tokens[$k] = $o[((int) $n + 1) % count($o)]; };
        foreach (['radius', 'btn', 'deco', 'h_scale'] as $k) $next($k);
        $sig = self::signature($p, $tokens); $after = 0.0;
        foreach ($others as $o) $after = max($after, self::similarity($sig, $o));
        return [$p, $tokens, $after, true];
    }

    /* ---------- úprava plánu ---------- */
    /* operace od modelu → [plán, seznam změn] */
    public static function applyEdit(TsStudioKit $kit, array $p, array $j): array
    {
        $changed = [];
        $idx = function (string $id) use (&$p) { foreach ($p['sections'] as $i => $s) if ($s['id'] === $id) return $i; return null; };
        if (is_array($j['site'] ?? null) && $j['site']) { $p['site'] = array_intersect_key($j['site'], $kit->kit['siteSlots']) + $p['site']; $changed[] = 'site'; }
        foreach ((array) ($j['ops'] ?? []) as $op) {
            if (!is_array($op)) continue;
            $id = (string) ($op['id'] ?? ''); $i = $id !== '' ? $idx($id) : null;
            switch ($op['op'] ?? '') {
                case 'set':
                    if ($i === null) break;
                    foreach (['variant', 'tone', 'nav'] as $k) if (isset($op[$k]) && is_scalar($op[$k])) $p['sections'][$i][$k] = (string) $op[$k];
                    if (is_array($op['content'] ?? null)) $p['sections'][$i]['content'] = $op['content'] + $p['sections'][$i]['content'];
                    $changed[] = "sekce:$id"; break;
                case 'remove':
                    if ($i === null) break;
                    array_splice($p['sections'], $i, 1); $changed[] = "odebráno:$id"; break;
                case 'add':
                    if (!is_array($op['section'] ?? null)) break;
                    $a = (string) ($op['after'] ?? ''); $at = $a !== '' && ($x = $idx($a)) !== null ? $x + 1 : count($p['sections']);
                    array_splice($p['sections'], $at, 0, [self::section($kit, $op['section'])]); $changed[] = 'přidáno:' . (string) ($op['section']['type'] ?? ''); break;
                case 'move':
                    if ($i === null) break;
                    $s = $p['sections'][$i]; array_splice($p['sections'], $i, 1);
                    $a = (string) ($op['after'] ?? ''); $at = $a !== '' && ($x = $idx($a)) !== null ? $x + 1 : 0;
                    array_splice($p['sections'], $at, 0, [$s]); $changed[] = "přesun:$id"; break;
            }
        }
        if (isset($j['css']) && is_string($j['css'])) { $p['css'] = $j['css']; $changed[] = 'css'; }
        return [$p, $changed];
    }
}
