<?php
/* ==========================================================================
   ts-design-studio – přehled nahraného návrhu přímo ze souborů (bez AI): barvy, písma, stránky a sekce, velikost, kontroly.
   TsStudioInspect::run([cesta => text], [všechny cesty => velikost]) → ['title', 'lang', 'description', 'pages', 'sections', 'colors', 'colorsDark', 'fonts', 'stats', 'checks'].
   Nic se nespouští ani nenačítá ze sítě. checks = stejný formát jako TsStudioChecks (error brání odeslání klientovi).
   ========================================================================== */
final class TsStudioInspect
{
    const GENERIC = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'ui-rounded', 'inherit', 'initial', 'unset', 'revert', 'none'];
    const SYSTEM = ['arial', 'helvetica', 'helvetica neue', 'georgia', 'times', 'times new roman', 'courier', 'courier new', 'verdana', 'tahoma', 'trebuchet ms', 'gill sans', 'palatino', 'palatino linotype', 'garamond', 'menlo', 'monaco', 'consolas', 'segoe ui', '-apple-system', 'blinkmacsystemfont', 'roboto', 'ubuntu', 'cantarell', 'sf pro', 'didot', 'iowan old style', 'arial narrow', 'impact', 'lucida grande', 'optima', 'avenir', 'avenir next'];

    public static function run(array $text, array $sizes): array
    {
        $checks = []; $html = []; $css = [];
        foreach ($text as $p => $src) {
            if (preg_match('/\.html?$/i', $p)) $html[$p] = $src;
            elseif (preg_match('/\.css$/i', $p)) $css[$p] = $src;
        }
        ksort($html);
        if (isset($html['index.html'])) $html = ['index.html' => $html['index.html']] + $html;
        /* styly: soubory + bloky <style> + atributy style */
        $style = $css; $googles = [];
        foreach ($html as $p => $src) {
            if (preg_match_all('~<style\b[^>]*>(.*?)</style>~is', $src, $m)) foreach ($m[1] as $i => $b) $style["$p#style$i"] = $b;
            if (preg_match_all('~\sstyle\s*=\s*"([^"]*)"~i', $src, $m)) $style["$p#inline"] = 'x{' . implode(';', array_map(fn ($s) => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m[1])) . '}';
            if (preg_match_all('~href\s*=\s*"([^"]*fonts\.googleapis\.com[^"]*)"~i', $src, $m)) foreach ($m[1] as $u) $googles = array_merge($googles, self::googleFamilies(html_entity_decode($u, ENT_QUOTES | ENT_HTML5)));
        }
        foreach ($style as $b) if (preg_match_all('~@import\s+(?:url\()?["\']?([^"\')]*fonts\.googleapis\.com[^"\')]*)~i', $b, $m)) foreach ($m[1] as $u) $googles = array_merge($googles, self::googleFamilies($u));
        $all = implode("\n", array_map(fn ($b) => (string) preg_replace('~/\*.*?\*/~s', '', (string) preg_replace('~url\(\s*["\']?data:[^)]*\)~i', 'url()', $b)), $style));

        [$light, $dark] = self::splitDark($all);
        $fonts = self::fonts($all, array_unique($googles), $checks);
        $out = ['title' => '', 'lang' => '', 'description' => '', 'pages' => [], 'sections' => [], 'colors' => self::colors($light), 'colorsDark' => self::colors($dark), 'fonts' => $fonts];

        /* stránky */
        $images = 0; $scripts = 0; $todo = 0;
        foreach ($html as $p => $src) {
            $page = self::page($src); $page['path'] = $p; $out['pages'][] = ['path' => $p, 'title' => $page['title'], 'headings' => $page['headings']];
            if ($p === array_key_first($html)) {
                $out['title'] = $page['title']; $out['lang'] = $page['lang']; $out['description'] = $page['description'];
                if ($page['title'] === '') $checks[] = self::c('warn', 'title', $p, 'Stránka nemá titulek.');
                if ($page['lang'] === '') $checks[] = self::c('warn', 'lang', $p, 'Stránka nemá uvedený jazyk (lang).');
                if (!preg_match('/<meta[^>]+name=["\']viewport/i', $src)) $checks[] = self::c('error', 'viewport', $p, 'Chybí meta viewport: na mobilu se návrh zobrazí špatně.');
            }
            foreach ($page['sections'] as $s) if (!in_array($s, $out['sections'], true) && count($out['sections']) < 40) $out['sections'][] = $s;
            $images += $page['images']; $scripts += $page['scripts'];
            $todo += substr_count($src, TsStudioAi::TODO);
        }
        if ($todo) $checks[] = self::c('warn', 'todo', '', "Návrh obsahuje $todo × „" . TsStudioAi::TODO . '“.');

        /* odkazy na soubory návrhu */
        foreach ($html as $p => $src) {
            $clean = preg_replace('~<!--.*?-->|<script\b[^>]*>.*?</script>~is', '', $src);
            /* skript se zdrojem zůstává: prázdné tělo */
            $clean = (string) $clean . implode('', preg_match_all('~<script\b[^>]*\ssrc=[^>]*>~i', $src, $sm) ? $sm[0] : []);
            if (preg_match_all('~\s(?:href|src)\s*=\s*["\']([^"\']*)["\']~i', $clean, $m)) foreach (array_unique($m[1]) as $u) self::ref($p, html_entity_decode($u, ENT_QUOTES | ENT_HTML5), $sizes, $checks);
            if (preg_match_all('~\ssrcset\s*=\s*["\']([^"\']*)["\']~i', $clean, $m)) foreach ($m[1] as $set) foreach (explode(',', $set) as $x) self::ref($p, (string) strtok(trim($x), ' '), $sizes, $checks);
        }
        foreach ($style as $key => $b) {
            $from = (string) explode('#', $key)[0]; $b = (string) preg_replace('~/\*.*?\*/~s', '', $b);
            if (preg_match_all('~url\(\s*["\']?([^"\')]*)["\']?\s*\)~i', $b, $m)) foreach (array_unique($m[1]) as $u) self::ref($from, $u, $sizes, $checks);
        }
        $bytes = array_sum($sizes);
        foreach ($sizes as $p => $n) if ($n > 5000000) $checks[] = self::c('warn', 'big', $p, 'Velký soubor (' . round($n / 1000000, 1) . ' MB), návrh se bude klientovi načítat dlouho.');
        $out['stats'] = ['files' => count($sizes), 'bytes' => $bytes, 'images' => $images, 'scripts' => $scripts, 'todo' => $todo, 'styles' => count($css)];
        $out['checks'] = $checks;
        return $out;
    }
    private static function c(string $lvl, string $code, string $file, string $msg): array { return ['level' => $lvl, 'code' => $code, 'file' => $file, 'line' => 0, 'msg' => $msg]; }

    /* ---------- odkaz na soubor: relativně k souboru, který na něj odkazuje ---------- */
    private static function ref(string $from, string $u, array $sizes, array &$checks): void
    {
        $u = trim($u);
        if ($u === '' || $u[0] === '#' || preg_match('~^(data:|mailto:|tel:|sms:|https?:|//|javascript:)~i', $u)) return;
        $path = rawurldecode((string) preg_replace('~[?#].*$~', '', $u)); if ($path === '') return;
        $base = $path[0] === '/' ? '' : (strpos($from, '/') !== false ? dirname($from) . '/' : '');
        $parts = []; foreach (explode('/', $base . ltrim($path, '/')) as $seg) { if ($seg === '' || $seg === '.') continue; if ($seg === '..') { array_pop($parts); continue; } $parts[] = $seg; }
        $f = implode('/', $parts);
        if ($path[0] === '/') { $checks[] = self::c('error', 'absolute', $from, "Odkaz od kořene domény „{$u}“: v neveřejném odkazu nebude fungovat, použijte relativní cestu."); return; }
        if ($f === '') return;
        if (str_ends_with($path, '/') || !isset($sizes[$f])) { if (isset($sizes[rtrim($f, '/') . '/index.html'])) return; if (!isset($sizes[$f])) $checks[] = self::c('error', 'missing', $from, "Odkaz na soubor, který v návrhu není: $f"); }
    }

    /* ---------- barvy ---------- */
    private static function splitDark(string $css): array
    {
        $dark = ''; $light = ''; $i = 0; $n = strlen($css);
        while ($i < $n) {
            if (!preg_match('~@media[^{]*prefers-color-scheme\s*:\s*dark[^{]*\{~i', $css, $m, PREG_OFFSET_CAPTURE, $i)) { $light .= substr($css, $i); break; }
            $start = $m[0][1]; $open = $start + strlen($m[0][0]); $light .= substr($css, $i, $start - $i);
            $depth = 1; $k = $open; while ($k < $n && $depth > 0) { $c = $css[$k]; if ($c === '{') $depth++; elseif ($c === '}') $depth--; $k++; }
            $dark .= substr($css, $open, max(0, $k - $open - 1)); $i = $k;
        }
        /* pravidla přepínače: [data-theme=dark], .dark … */
        $light = (string) preg_replace_callback('~([^{}]*(?:\[data-(?:theme|mode|rezim)=["\']?dark["\']?\]|(?:^|[\s,])(?:html|body|:root)?\.(?:dark|dark-mode|theme-dark)\b)[^{}]*)\{([^{}]*)\}~i', function ($m) use (&$dark) { $dark .= $m[2] . ';'; return ''; }, $light);
        return [$light, $dark];
    }
    /* → nejčastější barvy (#rrggbb), počet a názvy proměnných */
    private static function colors(string $css): array
    {
        $count = []; $vars = [];
        if (preg_match_all('~(--[\w-]+)\s*:\s*([^;{}]+)~', $css, $m, PREG_SET_ORDER)) foreach ($m as $d) {
            if (($c = self::firstColor($d[2])) !== null && count($vars[$c] ?? []) < 4) $vars[$c][$d[1]] = $d[1];
        }
        if (preg_match_all('~#[0-9a-f]{3,8}\b|rgba?\([^)]*\)|hsla?\([^)]*\)~i', $css, $m)) foreach ($m[0] as $v) {
            if (($c = self::color($v)) !== null) $count[$c] = ($count[$c] ?? 0) + 1;
        }
        arsort($count); $out = [];
        foreach (array_slice($count, 0, 16, true) as $c => $n) $out[] = ['value' => $c, 'count' => $n, 'vars' => array_values($vars[$c] ?? [])];
        return $out;
    }
    private static function firstColor(string $v): ?string { return preg_match('~#[0-9a-f]{3,8}\b|rgba?\([^)]*\)|hsla?\([^)]*\)~i', $v, $m) ? self::color($m[0]) : null; }
    public static function color(string $v): ?string
    {
        $v = strtolower(trim($v));
        if ($v[0] === '#') {
            $h = substr($v, 1); $l = strlen($h);
            if ($l === 3 || $l === 4) { if ($l === 4 && $h[3] === '0') return null; return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]; }
            if ($l === 6) return $v;
            if ($l === 8) return substr($h, 6) === '00' ? null : '#' . substr($h, 0, 6);
            return null;
        }
        if (!preg_match_all('~-?[\d.]+%?~', $v, $m) || count($m[0]) < 3) return null;
        $a = $m[0];
        if (isset($a[3]) && (float) $a[3] === 0.0) return null;
        if ($v[0] === 'r') {
            $c = array_map(fn ($x) => (int) round(str_ends_with($x, '%') ? (float) $x * 2.55 : (float) $x), array_slice($a, 0, 3));
            foreach ($c as $x) if ($x < 0 || $x > 255) return null;
            return sprintf('#%02x%02x%02x', ...$c);
        }
        $h = fmod((float) $a[0], 360) / 360; if ($h < 0) $h += 1; $s = min(1, (float) $a[1] / 100); $l = min(1, (float) $a[2] / 100);
        $q = $l < .5 ? $l * (1 + $s) : $l + $s - $l * $s; $p = 2 * $l - $q;
        $f = function ($t) use ($p, $q) { if ($t < 0) $t += 1; if ($t > 1) $t -= 1; if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t; if ($t < .5) return $q; if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6; return $p; };
        return sprintf('#%02x%02x%02x', (int) round($f($h + 1 / 3) * 255), (int) round($f($h) * 255), (int) round($f($h - 1 / 3) * 255));
    }

    /* ---------- písma ---------- */
    private static function googleFamilies(string $url): array
    {
        $q = (string) parse_url($url, PHP_URL_QUERY); $out = [];
        foreach (explode('&', $q) as $kv) { [$k, $v] = array_pad(explode('=', $kv, 2), 2, ''); if ($k === 'family') foreach (explode('|', urldecode($v)) as $f) $out[] = trim((string) explode(':', $f)[0]); }
        return array_values(array_filter($out));
    }
    private static function fonts(string $css, array $googles, array &$checks): array
    {
        $faces = [];
        if (preg_match_all('~@font-face\s*\{([^}]*)\}~i', $css, $m)) foreach ($m[1] as $b) {
            if (!preg_match('~font-family\s*:\s*["\']?([^"\';]+)~i', $b, $f)) continue;
            $fam = trim($f[1]); $w = preg_match('~font-weight\s*:\s*([^;]+)~i', $b, $x) ? trim($x[1]) : '400';
            $faces[strtolower($fam)] = ['family' => $faces[strtolower($fam)]['family'] ?? $fam, 'weights' => array_values(array_unique(array_merge($faces[strtolower($fam)]['weights'] ?? [], [$w])))];
        }
        $vars = [];
        if (preg_match_all('~(--[\w-]+)\s*:\s*([^;{}]+)~', $css, $m, PREG_SET_ORDER)) foreach ($m as $d) $vars[$d[1]] = trim($d[2]);
        $resolve = function (string $v) use ($vars) { for ($i = 0; $i < 4 && preg_match('~var\(\s*(--[\w-]+)[^)]*\)~', $v, $m); $i++) $v = str_replace($m[0], $vars[$m[1]] ?? '', $v); return $v; };
        $used = [];
        $body = (string) preg_replace('~@font-face\s*\{[^}]*\}~i', '', $css);
        if (preg_match_all('~font-family\s*:\s*([^;{}]+)~i', $body, $m)) foreach ($m[1] as $v) $used[] = $resolve($v);
        if (preg_match_all('~(?<![\w-])font\s*:\s*([^;{}]+)~i', $body, $m)) foreach ($m[1] as $v) { $v = $resolve($v); if (preg_match('~[\d.]+(?:px|rem|em|%|vw|pt)(?:\s*/\s*[\d.]+[\w%]*)?\s+(.+)$~', $v, $x)) $used[] = $x[1]; }
        foreach ($vars as $k => $v) if (preg_match('~font|^--f-~i', $k) && preg_match('~["\']|,~', $v)) $used[] = $v;
        $count = []; $name = [];
        foreach ($used as $list) foreach (array_slice(explode(',', $list), 0, 1) as $x) {
            $x = trim($x, " \t\n\"'"); if ($x === '' || strpos($x, 'var(') !== false || strpos($x, '(') !== false) continue;
            $k = strtolower($x); if (in_array($k, self::GENERIC, true)) continue;
            $count[$k] = ($count[$k] ?? 0) + 1; $name[$k] = $name[$k] ?? $x;
        }
        foreach ($faces as $k => $f) { $count[$k] = $count[$k] ?? 0; $name[$k] = $f['family']; }
        foreach ($googles as $g) { $k = strtolower($g); $count[$k] = $count[$k] ?? 0; $name[$k] = $g; }
        $g = array_map('strtolower', $googles);
        arsort($count); $out = [];
        foreach ($count as $k => $n) {
            $src = isset($faces[$k]) ? 'local' : (in_array($k, $g, true) ? 'google' : (in_array($k, self::SYSTEM, true) ? 'system' : 'missing'));
            if ($src === 'missing') $checks[] = self::c('warn', 'font', '', "Písmo „{$name[$k]}“ se v návrhu používá, ale není přiložené: klient uvidí náhradní písmo.");
            $out[] = ['family' => $name[$k], 'source' => $src, 'used' => $n, 'weights' => $faces[$k]['weights'] ?? []];
        }
        return array_slice($out, 0, 12);
    }

    /* ---------- struktura stránky ---------- */
    private static function page(string $html): array
    {
        $d = new DOMDocument(); $prev = libxml_use_internal_errors(true);
        $small = (string) preg_replace('~(src|href)\s*=\s*"data:[^"]*"~i', '$1=""', $html);
        $d->loadHTML('<?xml encoding="utf-8"?>' . $small, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT); libxml_clear_errors(); libxml_use_internal_errors($prev);
        $txt = fn (?DOMNode $n) => $n ? trim((string) preg_replace('/\s+/u', ' ', $n->textContent)) : '';
        $title = $txt($d->getElementsByTagName('title')->item(0));
        $htmlEl = $d->getElementsByTagName('html')->item(0); $lang = $htmlEl instanceof DOMElement ? $htmlEl->getAttribute('lang') : '';
        $desc = '';
        foreach ($d->getElementsByTagName('meta') as $m) if (strtolower($m->getAttribute('name')) === 'description') $desc = $m->getAttribute('content');
        $x = new DOMXPath($d); $heads = []; $sections = [];
        foreach ($x->query('//h1|//h2') as $h) {
            $t = mb_substr($txt($h), 0, 80); if ($t === '') continue;
            if (count($heads) < 40) $heads[] = ['level' => (int) substr($h->nodeName, 1), 'text' => $t];
            if ($h->nodeName === 'h2' || !$sections) if (!in_array($t, $sections, true)) $sections[] = $t;
        }
        foreach ($x->query('//section[@aria-label]') as $s) { $t = mb_substr(trim($s->getAttribute('aria-label')), 0, 80); if ($t !== '' && !in_array($t, $sections, true)) $sections[] = $t; }
        return ['title' => mb_substr($title, 0, 160), 'lang' => mb_substr($lang, 0, 12), 'description' => mb_substr($desc, 0, 300), 'headings' => $heads, 'sections' => $sections,
            'images' => $d->getElementsByTagName('img')->length, 'scripts' => $d->getElementsByTagName('script')->length];
    }
}
