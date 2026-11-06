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
        return [['site' => $site, 'tokens' => $tokens, 'sections' => $out], $err, $fixed];
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
<title>{{title}}</title><meta name="description" content="{{description}}"><meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="assets/site.css"><script src="assets/app.js" defer></script></head>
', $site);
        $html = $head . '<body class="' . implode(' ', $cls) . '"><a class="skip" href="#obsah">' . htmlspecialchars((string) ($this->kit['skip'][$site['lang']] ?? 'Skip'), ENT_QUOTES) . "</a>\n" . $header . "\n<main id=\"obsah\">\n" . $body . "</main>\n" . $footer . "\n</body></html>\n";
        [$css, $bin] = $this->css($t, $types);
        return ['files' => ['index.html' => $html, 'assets/site.css' => $css, 'assets/app.js' => (string) file_get_contents($this->dir . '/app.js')], 'binary' => $bin, 'plan' => $p, 'errors' => $errors, 'fixed' => $fixed];
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
