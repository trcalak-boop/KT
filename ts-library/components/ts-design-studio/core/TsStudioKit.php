<?php
/* ==========================================================================
   ts-design-studio – stavebnice sekcí (etapa 6.7a): návrhový plán → statický web na míru.
   Plán: { site: { lang, brand, tagline, title, description, phone, email, address, legal, cta_label, cta_url, header, footer },
           tokens: { barvy světlé i tmavé, písma z katalogu, tvar a rytmus – viz kit/kit.json },
           sections: [ { type, variant, tone, id, nav, content: { sloty sekce } }, … ] }
   kit/kit.json              tokeny, tóny, vlastnosti webu
   kit/base.css              základ (typografie, tóny, hlavička, patička, tlačítka)
   kit/sections/<typ>/       section.json (varianty, sloty, ukázkový obsah), section.html (šablona), section.css
   kit/art/<název>.svg       abstraktní ilustrace (slot typu enum, v šabloně <!--art:{{art}}-->)
   kit/fonts/                písma a licence OFL, katalog fonts.json (tools/sestav_kit_pisma.py)

   clean($plan) → [plán, chyby[]]: neznámé typy, varianty a klíče se zahodí, obsah projde stejným schématem jako sloty šablon.
   render($plan) → ['files' => [cesta => text], 'binary' => [cesta => zdrojový soubor], 'tokens' => …, 'fixed' => [opravené kontrasty]].
   Výstup je vždy escapovaný; CSS a ilustrace pocházejí jen z knihovny, do projektu jdou jen použité sekce a písma.
   ========================================================================== */
final class TsStudioKit
{
    public string $dir; public array $kit; public array $fonts; private array $sec = [];

    public function __construct(?string $dir = null)
    {
        $this->dir = rtrim($dir ?? __DIR__ . '/../kit', '/');
        $this->kit = json_decode((string) file_get_contents($this->dir . '/kit.json'), true, 32, JSON_THROW_ON_ERROR);
        $this->fonts = json_decode((string) file_get_contents($this->dir . '/fonts/fonts.json'), true, 32, JSON_THROW_ON_ERROR);
        foreach (glob($this->dir . '/sections/*/section.json') ?: [] as $f) {
            $j = json_decode((string) file_get_contents($f), true, 32, JSON_THROW_ON_ERROR);
            if (!empty($j['head'])) $j['slots'] = $this->kit['headSlots'] + $j['slots'];
            $this->sec[$j['type']] = $j;
        }
        ksort($this->sec);
    }
    public function sections(): array { return $this->sec; }
    public function arts(): array { return array_map(fn ($f) => basename($f, '.svg'), glob($this->dir . '/art/*.svg') ?: []); }

    /* ---------- tokeny ---------- */
    public function defaults(): array { $o = []; foreach ($this->kit['tokens'] as $k => $d) $o[$k] = $d['value']; return $o; }
    /* → [tokeny, chyby[], opravené kontrasty[]]; odvozené barvy (accent_text, d_accent_text) dopočítá tak, aby byly čitelné na pozadí i kartách */
    public function cleanTokens(array $in): array
    {
        $err = []; $out = [];
        foreach ($this->kit['tokens'] as $k => $d) {
            $v = isset($in[$k]) && is_scalar($in[$k]) ? (string) $in[$k] : (string) $d['value'];
            $ok = match ($d['type']) {
                'color' => (bool) preg_match('/^#[0-9a-f]{6}$/i', $v),
                'font' => isset($this->fonts[$v]) && (empty($d['kinds']) || in_array($this->fonts[$v]['kind'], $d['kinds'], true)),
                'enum' => in_array($v, $d['options'], true),
                default => false };
            if (!$ok) { $err[] = "$k: neplatná hodnota"; $v = (string) $d['value']; }
            $out[$k] = $d['type'] === 'color' ? strtolower($v) : $v;
        }
        $fixed = [];
        foreach (['', 'd_'] as $p) {
            $k = fn (string $x) => $p . $x;
            $fix = function (string $f, string $b) use (&$out, &$fixed, $k) { if (TsStudioTemplate::contrast($out[$k($f)], $out[$k($b)]) < 4.5) { $out[$k($f)] = TsStudioTemplate::ensureContrast($out[$k($f)], $out[$k($b)]); $fixed[$k($f)] = $k($f); } };
            $fix('ink', 'bg');
            /* text musí být čitelný na pozadí i na kartách: když to nejde, karty dostanou barvu pozadí */
            if (TsStudioTemplate::contrast($out[$k('ink')], $out[$k('surface')]) < 4.5) { $out[$k('surface')] = $out[$k('bg')]; $fixed[$k('surface')] = $k('surface'); }
            $fix('muted', 'bg'); $fix('muted', 'surface');
            if (TsStudioTemplate::contrast($out[$k('muted')], $out[$k('bg')]) < 4.5) { $out[$k('muted')] = $out[$k('ink')]; $fixed[$k('muted')] = $k('muted'); }
            $fix('on_accent', 'accent'); $fix('on_band', 'band');
            $at = TsStudioTemplate::ensureContrast(TsStudioTemplate::ensureContrast($out[$p . 'accent'], $out[$p . 'bg']), $out[$p . 'surface']);
            $out[$p . 'accent_text'] = TsStudioTemplate::contrast($at, $out[$p . 'bg']) >= 4.5 && TsStudioTemplate::contrast($at, $out[$p . 'surface']) >= 4.5 ? $at : $out[$p . 'ink'];
            /* tlumený text v pásu: směs s pozadím pásu, která drží kontrast */
            $out[$p . 'band_muted'] = self::mixContrast($out[$p . 'on_band'], $out[$p . 'band']);
            $out[$p . 'accent_muted'] = self::mixContrast($out[$p . 'on_accent'], $out[$p . 'accent']);
        }
        return [$out, $err, array_values($fixed)];
    }
    private static function mixContrast(string $fg, string $bg): string
    {
        $c = fn (string $h) => [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))];
        [$a, $b] = [$c($fg), $c($bg)];
        foreach ([.72, .8, .88, .94] as $t) {
            $x = sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($a[$i] * $t + $b[$i] * (1 - $t)), [0, 1, 2]));
            if (TsStudioTemplate::contrast($x, $bg) >= 4.6) return $x;
        }
        return $fg;
    }

    /* ---------- plán ---------- */
    public function clean(array $plan): array
    {
        $err = [];
        [$site, $e] = TsStudioTemplate::cleanSlots($this->kit['siteSlots'], $this->kit['siteDefaults'], (array) ($plan['site'] ?? [])); foreach ($e as $x) $err[] = "site.$x";
        [$tokens, $e, $fixed] = $this->cleanTokens((array) ($plan['tokens'] ?? [])); foreach ($e as $x) $err[] = "tokens.$x";
        $out = []; $ids = [];
        foreach ((array) ($plan['sections'] ?? []) as $i => $s) {
            $type = is_array($s) ? (string) ($s['type'] ?? '') : '';
            if (!isset($this->sec[$type])) { $err[] = "sections[$i]: neznámý typ sekce"; continue; }
            if (count($out) >= (int) $this->kit['maxSections']) { $err[] = 'sections: nejvýš ' . $this->kit['maxSections'] . ' sekcí'; break; }
            $d = $this->sec[$type];
            $variant = (string) ($s['variant'] ?? $d['default']);
            if (!isset($d['variants'][$variant])) { $err[] = "sections[$i].variant: neznámá varianta"; $variant = $d['default']; }
            $tone = (string) ($s['tone'] ?? 'base');
            if (!in_array($tone, $this->kit['tones'], true)) { $err[] = "sections[$i].tone: neznámý tón"; $tone = 'base'; }
            [$c, $e] = TsStudioTemplate::cleanSlots($d['slots'], [], (array) ($s['content'] ?? [])); foreach ($e as $x) $err[] = "sections[$i].$x";
            $id = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($s['id'] ?? $type))); $id = trim($id, '-') ?: $type;
            $base = $id; for ($n = 2; isset($ids[$id]); $n++) $id = "$base-$n";
            $ids[$id] = true;
            $out[] = ['type' => $type, 'variant' => $variant, 'tone' => $tone, 'id' => $id, 'nav' => mb_substr(trim((string) ($s['nav'] ?? '')), 0, 24), 'content' => $c];
        }
        if (!$out) $err[] = 'sections: plán nemá žádnou sekci';
        foreach ($this->kit['siteDefaults'] as $k => $v) if (($site[$k] ?? '') === '') $site[$k] = $v;
        [$css, $e] = $this->cleanCss((string) ($plan['css'] ?? ''), $out); foreach ($e as $x) $err[] = "css: $x";
        return [['site' => $site, 'tokens' => $tokens, 'sections' => $out, 'css' => $css], $err, $fixed];
    }

    /* ---------- vlastní vrstva CSS ---------- */
    const CSS_PROPS = ['font-size', 'font-weight', 'font-style', 'letter-spacing', 'line-height', 'text-align', 'text-transform', 'text-wrap', 'margin', 'margin-top', 'margin-bottom', 'margin-inline', 'padding', 'padding-block', 'padding-inline', 'padding-top', 'padding-bottom',
        'gap', 'row-gap', 'column-gap', 'max-width', 'min-height', 'border-radius', 'border-width', 'border-top-width', 'border-bottom-width', 'grid-template-columns', 'align-items', 'justify-content', 'justify-items', 'order', 'aspect-ratio'];
    /* jen vybrané vlastnosti a hodnoty (žádné barvy, url, skripty, pozice), selektor vždy uvnitř existující sekce; → [css, chyby] */
    public function cleanCss(string $css, array $sections): array
    {
        if (trim($css) === '') return ['', []];
        $err = []; $css = mb_substr((string) preg_replace('~/\*.*?\*/~s', '', $css), 0, 4000);
        if (preg_match('/[<\\\\]/', $css)) { return ['', ['nepovolené znaky, vrstva zahozena']]; }
        $ids = array_flip(array_column($sections, 'id')); $types = array_flip(array_column($sections, 'type'));
        $selOk = function (string $sel) use ($ids, $types): bool {
            if (!preg_match('/^(\.s-([a-z]+)(--[a-z]+)?|#([a-z0-9-]+))((\s+|\s*>\s*)[a-z0-9]*(\.[a-z0-9_-]+)*(:first-child|:last-child|:hover)?)*$/i', $sel, $m)) return false;
            return ($m[2] ?? '') !== '' ? isset($types[$m[2]]) : isset($ids[$m[4] ?? '']);
        };
        $valOk = function (string $prop, string $v): bool {
            if (preg_match('~url|expression|javascript|var\(|@|!important|["\']|#[0-9a-f]{3}|rgb|hsl~i', $v)) return false;
            if (!preg_match('/^[a-z0-9 .,%()+*\/-]{1,120}$/i', $v)) return false;
            if ($prop === 'font-size' || $prop === 'min-height') { preg_match_all('/([\d.]+)(px|rem|vw)/', $v, $m); foreach ($m[1] as $i => $n) { $px = $m[2][$i] === 'rem' ? $n * 16 : ($m[2][$i] === 'vw' ? $n * 13 : (float) $n); if ($px > ($prop === 'font-size' ? 200 : 800)) return false; } }
            return true;
        };
        $rules = function (string $body) use ($selOk, $valOk, &$err): array {
            $out = []; preg_match_all('/([^{}]+)\{([^{}]*)\}/', $body, $mm, PREG_SET_ORDER);
            foreach ($mm as $m) {
                $sels = array_values(array_filter(array_map('trim', explode(',', $m[1])), $selOk));
                if (!$sels) { $err[] = 'selektor mimo sekci: ' . mb_substr(trim($m[1]), 0, 60); continue; }
                $decl = [];
                foreach (explode(';', $m[2]) as $d) {
                    if (trim($d) === '') continue;
                    [$k, $v] = array_pad(explode(':', $d, 2), 2, ''); $k = strtolower(trim($k)); $v = trim($v);
                    if (in_array($k, self::CSS_PROPS, true) && $valOk($k, $v)) $decl[] = "$k:$v"; else $err[] = 'vlastnost: ' . mb_substr(trim($d), 0, 60);
                }
                if ($decl) $out[] = implode(',', $sels) . '{' . implode(';', $decl) . '}';
            }
            return $out;
        };
        $out = [];
        $rest = preg_replace_callback('/@media\s*\(\s*(max|min)-width\s*:\s*(\d{3,4})px\s*\)\s*\{((?:[^{}]*\{[^{}]*\})*)\s*\}/i', function ($m) use ($rules, &$out) {
            if ($r = $rules($m[3])) $out[] = '@media (' . strtolower($m[1]) . '-width:' . $m[2] . 'px){' . implode('', $r) . '}';
            return '';
        }, $css);
        if (strpos((string) $rest, '@') !== false) { $err[] = 'zahozené at-pravidlo'; $rest = preg_replace('/@[^{;]*(\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}|;)/', '', (string) $rest); }
        $out = array_merge($rules((string) $rest), $out);
        if (count($out) > 40) { $out = array_slice($out, 0, 40); $err[] = 'nejvýš 40 pravidel'; }
        return [implode("\n", $out), $err];
    }

    /* ---------- vykreslení ---------- */
    public function render(array $plan): array
    {
        [$p, $errors, $fixed] = $this->clean($plan);
        $types = array_values(array_unique(array_column($p['sections'], 'type')));
        $body = '';
        foreach ($p['sections'] as $s) $body .= $this->section($s);
        $nav = []; foreach ($p['sections'] as $s) if ($s['nav'] !== '') $nav[] = ['label' => $s['nav'], 'href' => '#' . $s['id']];
        $site = $p['site'] + ['nav' => $nav, 'year' => date('Y')];
        $site['v_' . $site['header']] = true; $site['f_' . $site['footer']] = true;
        $t = $p['tokens'];
        $cls = ['btn-' . $t['btn'], 'h-' . $t['h_case'], 'deco-' . $t['deco'], 'eyebrow-' . $t['eyebrow']];
        $header = TsStudioTemplate::mustache((string) file_get_contents($this->dir . '/header.html'), $site);
        $footer = TsStudioTemplate::mustache((string) file_get_contents($this->dir . '/footer.html'), $site);
        $head = TsStudioTemplate::mustache('<!doctype html>
<html lang="{{lang}}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{title}}</title><meta name="description" content="{{description}}">
<link rel="stylesheet" href="assets/site.css"><script src="assets/app.js" defer></script></head>
', $site);
        $html = $head . '<body class="' . implode(' ', $cls) . '"><a class="skip" href="#obsah">' . htmlspecialchars((string) ($this->kit['skip'][$site['lang']] ?? 'Skip'), ENT_QUOTES) . "</a>\n" . $header . "\n<main id=\"obsah\">\n" . $body . "</main>\n" . $footer . "\n</body></html>\n";
        [$css, $bin] = $this->css($t, $types);
        if ($p['css'] !== '') $css = TsStudioAi::withAiCss($css, $p['css']);
        return ['files' => ['index.html' => $html, 'assets/site.css' => $css, 'assets/app.js' => (string) file_get_contents($this->dir . '/app.js')], 'binary' => $bin, 'plan' => $p, 'errors' => $errors, 'fixed' => $fixed];
    }
    /* binární soubory návrhu podle tokenů (písma a licence) */
    public function binaryFor(array $tokens): array { [$t] = $this->cleanTokens($tokens); return $this->css($t, [])[1]; }
    /* všechna písma stavebnice jako soubory projektu – pro bránu náhledů */
    public function allFontPaths(): array { return array_map(fn ($f) => 'assets/fonts/' . basename($f), array_values(array_filter(glob($this->dir . '/fonts/*') ?: [], fn ($f) => !str_ends_with($f, '.json')))); }
    public function fontBytes(string $path): string
    {
        $f = $this->dir . '/fonts/' . basename($path);
        return str_starts_with($path, 'assets/fonts/') && is_file($f) ? (string) file_get_contents($f) : '';
    }
    private function section(array $s): string
    {
        $d = $this->sec[$s['type']];
        $tpl = (string) file_get_contents($this->dir . '/sections/' . $s['type'] . '/section.html');
        $tpl = str_replace('{{>head}}', (string) file_get_contents($this->dir . '/head.html'), $tpl);
        $ctx = $s['content'] + ['v_' . $s['variant'] => true];
        $inner = TsStudioTemplate::mustache($tpl, $ctx);
        $inner = preg_replace_callback('/<!--art:([a-z0-9-]*)-->/', function ($m) {
            $f = $this->dir . '/art/' . $m[1] . '.svg';
            return $m[1] !== '' && is_file($f) ? trim((string) file_get_contents($f)) : '';
        }, $inner);
        return '<section id="' . $s['id'] . '" class="s s-' . $s['type'] . ' s-' . $s['type'] . '--' . $s['variant'] . ' tone-' . $s['tone'] . '"><div class="wrap">' . trim($inner) . "</div></section>\n";
    }
    private function css(array $t, array $types): array
    {
        $decl = [];
        foreach ($this->kit['cssVars'] as $var => $k) $decl['light'][] = "--$var:" . $t[$k];
        foreach ($this->kit['cssVars'] as $var => $k) $decl['dark'][] = "--$var:" . $t['d_' . $k];
        $shape = [];
        foreach ($this->kit['shape'] as $k => $map) $shape[] = '--' . str_replace('_', '-', $k) . ':' . $map[$t[$k]];
        $bin = []; $faces = ''; $stack = [];
        foreach (['f_display' => 'f-display', 'f_body' => 'f-body', 'f_mono' => 'f-mono'] as $k => $var) {
            $f = $this->fonts[$t[$k]]; $shape[] = "--$var:" . $f['stack']; $stack[$t[$k]] = $f;
        }
        foreach ($stack as $f) {
            foreach ($f['faces'] as $x) {
                $bin['assets/fonts/' . $x['file']] = $this->dir . '/fonts/' . $x['file'];
                $faces .= '@font-face{font-family:"' . $f['family'] . '";src:url("fonts/' . $x['file'] . '") format("woff2");font-style:' . $x['style'] . ';font-weight:' . $x['weight'] . ';font-display:swap' . ($x['unicodeRange'] !== '' ? ';unicode-range:' . $x['unicodeRange'] : '') . "}\n";
            }
            $bin['assets/fonts/' . $f['license']] = $this->dir . '/fonts/' . $f['license'];
        }
        $l = implode(';', $decl['light']); $k = implode(';', $decl['dark']);
        $css = '/*@tokens*/:root{color-scheme:light dark;' . $l . ';' . implode(';', $shape) . "}\n"
            . '@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){' . $k . "}}\n"
            . ':root[data-theme="dark"]{color-scheme:dark;' . $k . "}\n" . ':root[data-theme="light"]{color-scheme:light}' . "\n/*@end*/\n";
        $css .= $faces . (string) file_get_contents($this->dir . '/base.css') . "\n";
        foreach ($types as $type) $css .= '/* sekce: ' . $type . " */\n" . (string) file_get_contents($this->dir . '/sections/' . $type . '/section.css') . "\n";
        return [$css, $bin];
    }

    /* ---------- ukázkový plán pro testy a přehled stavebnice ---------- */
    public function sampleSection(string $type, string $variant, string $tone = 'base'): array
    {
        $d = $this->sec[$type];
        return ['type' => $type, 'variant' => $variant, 'tone' => $tone, 'id' => $type . '-' . $variant, 'nav' => '', 'content' => $d['sample']];
    }
    /* souhrn pro prompt modelu (krátký: šetří kvótu) */
    public function catalog(): array
    {
        $o = [];
        foreach ($this->sec as $t => $d) {
            $sl = [];
            foreach ($d['slots'] as $k => $s) {
                if (($s['type'] ?? 'text') === 'list') $sl[$k] = ['list', (int) ($s['items'] ?? 12), array_keys((array) ($s['fields'] ?? []))];
                else $sl[$k] = ($s['type'] ?? 'text') . (isset($s['max']) ? ':' . $s['max'] : '') . (!empty($s['grounded']) ? ':fakta' : '');
            }
            $o[$t] = ['k čemu' => $d['use'], 'varianty' => $d['variants'], 'sloty' => $sl];
        }
        return $o;
    }
}
