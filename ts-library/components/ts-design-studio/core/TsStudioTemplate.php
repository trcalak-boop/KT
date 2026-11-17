<?php
/* ==========================================================================
   ts-design-studio – šablona webu: soubory + design tokeny + obsahové sloty.
   templates/<id>/template.json  { id, name, obor[], render[], tokens{ klíč: {var, type: color|font|size, value, label, options?} },
                                   slots{ klíč: {type: text|multiline|url|email|phone|list, max?, label, fields?{...}, items?} },
                                   contrast[[popředí, pozadí]], content{ výchozí obsah } }
   templates/<id>/files/…        soubory projektu; soubory v render[] projdou šablonovacím jazykem, ostatní (písma, obrázky) se kopírují.

   Jazyk šablon (malá podmnožina Mustache, vždy escapuje – raw výstup neexistuje):
     {{klíč}} / {{a.b}}     text slotu (HTML escapovaný)
     {{#seznam}}…{{/seznam}} opakování pro každou položku (uvnitř {{pole}}), u textu = podmínka „není prázdné“
     {{^klíč}}…{{/klíč}}     když je prázdné
   V CSS se blok mezi komentáři @tokens a @end nahradí deklaracemi tokenů (:root{--bg:#…}).
   ========================================================================== */
final class TsStudioTemplate
{
    public array $t; public string $dir; private ?array $vfiles = null; private $vraw = null;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        $j = json_decode((string) @file_get_contents($this->dir . '/template.json'), true);
        if (!is_array($j) || empty($j['id']) || !is_dir($this->dir . '/files')) throw new InvalidArgumentException('šablona nejde načíst: ' . basename($dir));
        $this->t = $j + ['render' => [], 'tokens' => [], 'slots' => [], 'contrast' => [], 'content' => [], 'obor' => []];
    }
    /* šablona bez složky: návrh ze stavebnice (TsDesignStudio::KIT) – binární soubory dodává zdroj $raw */
    public static function virtual(array $t, array $files, callable $raw): self
    {
        $o = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $o->dir = ''; $o->t = $t + ['render' => [], 'tokens' => [], 'slots' => [], 'contrast' => [], 'content' => [], 'obor' => []]; $o->vfiles = $files; $o->vraw = $raw;
        return $o;
    }
    public static function all(string $root): array
    {
        $out = [];
        foreach (glob(rtrim($root, '/') . '/*/template.json') ?: [] as $f) { try { $t = new self(dirname($f)); $out[$t->t['id']] = $t; } catch (Throwable $e) { } }
        ksort($out); return $out;
    }
    public function id(): string { return (string) $this->t['id']; }

    /* všechny soubory šablony (relativní cesty) */
    public function files(): array
    {
        if ($this->vfiles !== null) return $this->vfiles;
        $base = $this->dir . '/files/'; $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile()) $out[] = substr($f->getPathname(), strlen($base));
        sort($out); return $out;
    }
    public function isText(string $path): bool { return (bool) preg_match('/\.(html?|css|js|json|txt|svg|xml|md)$/i', $path); }
    public function raw(string $path): string
    {
        if (strpos($path, '..') !== false) throw new InvalidArgumentException('cesta');
        if ($this->vraw !== null) return (string) ($this->vraw)($path);
        return (string) file_get_contents($this->dir . '/files/' . $path);
    }

    /* ---------- obsah a tokeny: kontrola proti schématu ---------- */
    /* → [vyčištěný obsah, chyby[]]; neznámé klíče se zahodí, chybějící doplní výchozí hodnota */
    public function cleanContent(array $c): array { return self::cleanSlots($this->t['slots'], $this->t['content'], $c); }
    /* stejné schéma používá i stavebnice sekcí (TsStudioKit) */
    public static function cleanSlots(array $slots, array $defaults, array $c): array
    {
        $err = []; $out = [];
        foreach ($slots as $k => $s) {
            $v = array_key_exists($k, $c) ? $c[$k] : ($defaults[$k] ?? (($s['type'] ?? 'text') === 'list' ? [] : ''));
            $out[$k] = self::cleanValue($k, $s, $v, $err);
        }
        return [$out, $err];
    }
    private static function cleanValue(string $k, array $s, $v, array &$err)
    {
        $type = $s['type'] ?? 'text';
        if ($type === 'list') {
            if (!is_array($v)) { $err[] = "$k: má být seznam"; return []; }
            $max = (int) ($s['items'] ?? 12); if (count($v) > $max) { $err[] = "$k: nejvýš $max položek"; $v = array_slice($v, 0, $max); }
            $rows = [];
            foreach (array_values($v) as $i => $row) {
                if (!is_array($row)) { $err[] = "{$k}[{$i}]: má být objekt"; continue; }
                $r = []; foreach ((array) ($s['fields'] ?? []) as $fk => $fs) { $fs = is_array($fs) ? $fs : ['type' => $fs]; $r[$fk] = self::cleanValue("{$k}[{$i}].{$fk}", $fs, $row[$fk] ?? (($fs['type'] ?? 'text') === 'list' ? [] : ''), $err); }
                $rows[] = $r;
            }
            return $rows;
        }
        if (!is_scalar($v)) { $err[] = "$k: má být text"; return ''; }
        $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v) ?? '');
        if ($type !== 'multiline') $v = preg_replace('/\s+/u', ' ', $v);
        $max = (int) ($s['max'] ?? ($type === 'multiline' ? 1200 : 200));
        if (mb_strlen($v) > $max) { $err[] = "$k: nejvýš $max znaků"; $v = mb_substr($v, 0, $max); }
        if ($v === '') return '';
        if ($type === 'url' && !preg_match('~^(https?://[^\s<>"]+|mailto:[^\s<>"]+|tel:[+\d ()-]+|#[\w-]*|[\w./-]+\.html(#[\w-]*)?)$~i', $v)) { $err[] = "$k: neplatná adresa"; return ''; }
        if ($type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $err[] = "$k: neplatný e-mail"; return ''; }
        if ($type === 'phone' && !preg_match('/^\+?[\d ()-]{6,20}$/', $v)) { $err[] = "$k: neplatný telefon"; return ''; }
        if ($type === 'color' && !preg_match('/^#[0-9a-f]{6}$/i', $v)) { $err[] = "$k: barva má být ve tvaru #rrggbb"; return ''; }
        if ($type === 'enum' && !in_array($v, (array) ($s['options'] ?? []), true)) { $err[] = "$k: hodnota mimo nabídku"; return ''; }
        return $v;
    }
    public function cleanTokens(array $tk): array
    {
        $err = []; $out = [];
        foreach ($this->t['tokens'] as $k => $d) {
            $v = (string) ($tk[$k] ?? $d['value']);
            $type = $d['type'] ?? 'color';
            if ($type === 'color' && !preg_match('/^#[0-9a-f]{6}$/i', $v)) { $err[] = "$k: barva ve tvaru #rrggbb"; $v = $d['value']; }
            if ($type === 'font' && !in_array($v, (array) ($d['options'] ?? [$d['value']]), true)) { $err[] = "$k: písmo mimo nabídku šablony"; $v = $d['value']; }
            if ($type === 'size' && !preg_match('/^\d{1,3}(\.\d+)?(px|rem|em|%)$/', $v)) { $err[] = "$k: velikost (px, rem, em, %)"; $v = $d['value']; }
            $out[$k] = strtolower($v) === $v || $type !== 'color' ? $v : strtolower($v);
        }
        return [$out, $err];
    }

    /* ---------- vykreslení ---------- */
    /* → [cesta => obsah] jen textové soubory; binární soubory se odkazují ze šablony (viz TsDesignStudio::fileBytes) */
    public function render(array $content, array $tokens): array
    {
        [$c] = $this->cleanContent($content); [$tk] = $this->cleanTokens($tokens);
        $out = [];
        foreach ($this->files() as $p) {
            if (!$this->isText($p)) continue;
            $src = $this->raw($p);
            if (in_array($p, $this->t['render'], true)) $src = preg_match('/\.css$/i', $p) ? $this->css($src, $tk) : self::mustache($src, $c);
            $out[$p] = $src;
        }
        return $out;
    }
    public function css(string $src, array $tk): string
    {
        $decl = []; foreach ($this->t['tokens'] as $k => $d) $decl[] = $d['var'] . ':' . ($tk[$k] ?? $d['value']);
        $block = '/*@tokens*/:root{' . implode(';', $decl) . '}/*@end*/';
        return preg_match('~/\*@tokens\*/.*?/\*@end\*/~s', $src) ? preg_replace('~/\*@tokens\*/.*?/\*@end\*/~s', str_replace('$', '\$', $block), $src, 1) : $block . "\n" . $src;
    }
    public static function mustache(string $tpl, array $ctx): string
    {
        $get = function (array $stack, string $key) {
            foreach (array_reverse($stack) as $c) {
                if (!is_array($c)) continue;
                if (array_key_exists($key, $c)) return $c[$key];
                $v = $c; $ok = true; foreach (explode('.', $key) as $part) { if (is_array($v) && array_key_exists($part, $v)) $v = $v[$part]; else { $ok = false; break; } }
                if ($ok) return $v;
            }
            return null;
        };
        $render = function (string $t, array $stack) use (&$render, $get): string {
            $re = '~\{\{([#^])\s*([\w.]+)\s*\}\}(.*?)\{\{/\s*\2\s*\}\}~s';
            $t = preg_replace_callback($re, function ($m) use ($stack, $render, $get) {
                $v = $get($stack, $m[2]); $empty = $v === null || $v === '' || $v === [] || $v === false;
                if ($m[1] === '^') return $empty ? $render($m[3], $stack) : '';
                if ($empty) return '';
                if (is_array($v) && array_is_list($v)) { $o = ''; foreach ($v as $item) $o .= $render($m[3], array_merge($stack, [is_array($item) ? $item : ['.' => $item]])); return $o; }
                return $render($m[3], array_merge($stack, [is_array($v) ? $v : []]));
            }, $t);
            return preg_replace_callback('~\{\{\s*([\w.]+)\s*\}\}~', function ($m) use ($stack, $get) {
                $v = $get($stack, $m[1]); return is_scalar($v) ? htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
            }, $t);
        };
        return $render($tpl, [$ctx]);
    }

    /* ---------- kontrast tokenů (WCAG) ---------- */
    /* popředí posunuté k černé (světlé pozadí) nebo k bílé (tmavé pozadí), dokud nemá kontrast alespoň $min */
    public static function ensureContrast(string $fg, string $bg, float $min = 4.5): string
    {
        if (self::contrast($fg, $bg) >= $min) return strtolower($fg);
        $rgb = fn (string $h) => [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))];
        $to = self::contrast('#000000', $bg) >= self::contrast('#ffffff', $bg) ? 0 : 255; $c = $rgb($fg);
        for ($k = 1; $k <= 20; $k++) {
            $x = sprintf('#%02x%02x%02x', ...array_map(fn ($v) => (int) round($v + ($to - $v) * $k / 20), $c));
            if (self::contrast($x, $bg) >= $min) return $x;
        }
        return $to ? '#ffffff' : '#000000';
    }
    public static function contrast(string $a, string $b): float
    {
        $l = function (string $h): float {
            $h = ltrim($h, '#'); $c = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
            $c = array_map(fn ($x) => ($x /= 255) <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        $x = $l($a); $y = $l($b); return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }
}
