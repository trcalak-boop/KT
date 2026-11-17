<?php
/* ==========================================================================
   ts-design-studio – kontroly verze před přijetím (zadání: syntax, odkazy, bezpečnost, kontrast, velikost).
   TsStudioChecks::run($files, $template?, $tokens?) → [['level' => 'error'|'warn', 'code', 'file', 'line', 'msg'], …]
   Chyba (error) brání přijetí verze, varování (warn) jen upozorní. Kontroly nespouštějí žádný kód.
   ========================================================================== */
final class TsStudioChecks
{
    const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
    const OPTIONAL_CLOSE = ['p', 'li', 'dt', 'dd', 'option', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot', 'colgroup'];
    const ALLOWED_EXTERNAL = ['https://fonts.googleapis.com/', 'https://fonts.gstatic.com/'];
    const MAX_FILE = 400000; const MAX_TOTAL = 3000000;

    public static function run(array $files, ?TsStudioTemplate $tpl = null, array $tokens = []): array
    {
        $r = []; $total = 0;
        if (!isset($files['index.html'])) $r[] = self::e('error', 'no-index', '', 0, 'Chybí index.html.');
        foreach ($files as $path => $src) {
            $total += strlen($src);
            if (!preg_match('~^[a-z0-9][a-z0-9._/-]{0,120}$~i', $path) || strpos($path, '..') !== false) { $r[] = self::e('error', 'path', $path, 0, 'Nepovolený název souboru.'); continue; }
            if (strlen($src) > self::MAX_FILE) $r[] = self::e('error', 'size', $path, 0, 'Soubor je větší než ' . (self::MAX_FILE / 1000) . ' kB.');
            if (preg_match('/\.html?$/i', $path)) $r = array_merge($r, self::html($path, $src, $files, $tpl));
            elseif (preg_match('/\.css$/i', $path)) $r = array_merge($r, self::css($path, $src));
            elseif (preg_match('/\.js$/i', $path)) $r = array_merge($r, self::js($path, $src));
        }
        if ($total > self::MAX_TOTAL) $r[] = self::e('error', 'size-total', '', 0, 'Projekt je větší než 3 MB.');
        if ($tpl) foreach ($tpl->t['contrast'] as [$fg, $bg]) {
            $a = $tokens[$fg] ?? $tpl->t['tokens'][$fg]['value'] ?? null; $b = $tokens[$bg] ?? $tpl->t['tokens'][$bg]['value'] ?? null;
            if ($a && $b && ($c = TsStudioTemplate::contrast($a, $b)) < 4.5) $r[] = self::e($c < 3 ? 'error' : 'warn', 'contrast', 'assets/styles.css', 0, "Kontrast $fg na $bg je $c:1 (WCAG AA vyžaduje 4,5:1).");
        }
        return $r;
    }
    public static function errors(array $r): array { return array_values(array_filter($r, fn ($x) => $x['level'] === 'error')); }
    private static function e(string $lvl, string $code, string $file, int $line, string $msg): array { return ['level' => $lvl, 'code' => $code, 'file' => $file, 'line' => $line, 'msg' => $msg]; }
    private static function line(string $src, int $off): int { return substr_count(substr($src, 0, $off), "\n") + 1; }

    private static function html(string $path, string $src, array $files, ?TsStudioTemplate $tpl): array
    {
        $r = [];
        if (!preg_match('/<!doctype html>/i', $src)) $r[] = self::e('warn', 'doctype', $path, 1, 'Chybí <!doctype html>.');
        if (!preg_match('/<html[^>]*\slang="[a-z-]+"/i', $src)) $r[] = self::e('warn', 'lang', $path, 1, 'Prvek <html> nemá atribut lang.');
        if (!preg_match('/<meta[^>]+name="viewport"/i', $src)) $r[] = self::e('warn', 'viewport', $path, 1, 'Chybí meta viewport (mobil).');
        if (!preg_match('~<title>[^<]{2,}</title>~i', $src)) $r[] = self::e('warn', 'title', $path, 1, 'Chybí titulek stránky.');
        if (preg_match('/\{\{\s*[#^\/]?[\w.]+\s*\}\}/', $src, $m, PREG_OFFSET_CAPTURE)) $r[] = self::e('error', 'placeholder', $path, self::line($src, $m[0][1]), 'Nevyplněný zástupný text ' . $m[0][0] . '.');
        /* párování značek (bez obsahu <script>/<style> a komentářů) */
        $clean = preg_replace_callback('~<!--.*?-->|<(script|style)\b[^>]*>.*?</\1>~is', fn ($m) => str_repeat("\n", substr_count($m[0], "\n")) . (isset($m[1]) && $m[1] ? '<' . $m[1] . '></' . $m[1] . '>' : ''), $src);
        $stack = [];
        preg_match_all('~<(/?)([a-z][a-z0-9-]*)\b([^>]*)>~i', $clean, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($mm as $m) {
            $tag = strtolower($m[2][0]); $close = $m[1][0] === '/'; $self = str_ends_with(rtrim($m[3][0]), '/');
            if (in_array($tag, self::VOID, true) || $self) continue;
            if (!$close) { $stack[] = [$tag, $m[0][1]]; continue; }
            while ($stack && end($stack)[0] !== $tag && in_array(end($stack)[0], self::OPTIONAL_CLOSE, true)) array_pop($stack);
            if ($stack && end($stack)[0] === $tag) array_pop($stack);
            else $r[] = self::e('error', 'tag', $path, self::line($clean, $m[0][1]), "Uzavírací </$tag> bez otevření.");
        }
        foreach ($stack as [$tag, $off]) if (!in_array($tag, self::OPTIONAL_CLOSE, true) && !in_array($tag, ['html', 'head', 'body'], true)) $r[] = self::e('error', 'tag', $path, self::line($clean, $off), "Neuzavřený prvek <$tag>.");
        /* odkazy na soubory projektu a cizí zdroje */
        preg_match_all('~\s(href|src)\s*=\s*"([^"]*)"~i', $src, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($mm as $m) {
            $u = $m[2][0]; $ln = self::line($src, $m[0][1]);
            if ($u === '' || $u[0] === '#' || preg_match('~^(mailto|tel):~i', $u)) continue;
            if (preg_match('~^javascript:~i', $u)) { $r[] = self::e('error', 'js-url', $path, $ln, 'Odkaz javascript: není povolen.'); continue; }
            if (preg_match('~^(https?:)?//~i', $u)) {
                $isRes = strtolower($m[1][0]) === 'src' || preg_match('~<link[^>]+rel="stylesheet"[^>]*$~i', substr($src, max(0, $m[0][1] - 200), 200));
                if ($isRes && !array_filter(self::ALLOWED_EXTERNAL, fn ($a) => str_starts_with($u, $a))) $r[] = self::e('error', 'external', $path, $ln, 'Cizí zdroj ' . mb_substr($u, 0, 80) . ' (povolená jen písma Google).');
                continue;
            }
            $f = ltrim(preg_replace('~[?#].*$~', '', $u), './');
            if ($f !== '' && !isset($files[$f]) && !($tpl && in_array($f, $tpl->files(), true))) $r[] = self::e('error', 'link', $path, $ln, "Odkaz na neexistující soubor $f.");
        }
        if (preg_match('~<(iframe|object|embed|base)\b~i', $src, $m, PREG_OFFSET_CAPTURE)) $r[] = self::e('error', 'tag-forbidden', $path, self::line($src, $m[0][1]), 'Prvek <' . $m[1][0] . '> není v návrhu povolen.');
        if (preg_match('~<form\b[^>]*action="(https?:)?//~i', $src, $m, PREG_OFFSET_CAPTURE)) $r[] = self::e('error', 'form-external', $path, self::line($src, $m[0][1]), 'Formulář nesmí odesílat na cizí adresu.');
        if (preg_match('~\son[a-z]+\s*=~i', $clean, $m, PREG_OFFSET_CAPTURE)) $r[] = self::e('warn', 'inline-handler', $path, self::line($clean, $m[0][1]), 'Obsluha události v atributu (on…=) – lépe v assets/app.js.');
        preg_match_all('~<img\b(?![^>]*\salt=)[^>]*>~i', $src, $mm, PREG_OFFSET_CAPTURE);
        foreach ($mm[0] as $m) $r[] = self::e('warn', 'img-alt', $path, self::line($src, $m[1]), 'Obrázek bez atributu alt.');
        return $r;
    }
    private static function css(string $path, string $src): array
    {
        $r = []; $s = preg_replace(['~/\*.*?\*/~s', '~"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'~s'], ['', '""'], $src);
        if (substr_count($s, '{') !== substr_count($s, '}')) $r[] = self::e('error', 'css-braces', $path, 0, 'Nepárové složené závorky v CSS.');
        if (preg_match('~@import\s+(url\()?["\']?(https?:)?//~i', $src, $m, PREG_OFFSET_CAPTURE) && !preg_match('~fonts\.googleapis\.com~', $m[0][0])) $r[] = self::e('error', 'css-import', $path, self::line($src, $m[0][1]), 'Import cizího stylu.');
        if (preg_match('~url\(\s*["\']?(https?:)?//(?!fonts\.gstatic\.com)~i', $src, $m, PREG_OFFSET_CAPTURE)) $r[] = self::e('error', 'css-external', $path, self::line($src, $m[0][1]), 'Cizí zdroj v url().');
        if (preg_match('~(?<![\w-])(expression\s*\(|behavior\s*:)|javascript:~i', $src, $m, PREG_OFFSET_CAPTURE)) $r[] = self::e('error', 'css-script', $path, self::line($src, $m[0][1]), 'Skript v CSS.');
        return $r;
    }
    private static function js(string $path, string $src): array
    {
        $r = [];
        foreach (['~\beval\s*\(~' => 'eval()', '~new\s+Function\s*\(~' => 'new Function()', '~document\.cookie~' => 'document.cookie', '~\b(localStorage|sessionStorage|indexedDB)\b~' => 'úložiště prohlížeče',
                  '~\b(fetch|XMLHttpRequest|WebSocket|EventSource|navigator\.sendBeacon)\b~' => 'síťový požadavek', '~\b(window\.)?(parent|top|opener)\s*\.~' => 'přístup k nadřazenému oknu', '~import\s*\(|importScripts~' => 'načítání kódu'] as $re => $what)
            if (preg_match($re, $src, $m, PREG_OFFSET_CAPTURE)) $r[] = self::e(in_array($what, ['eval()', 'new Function()', 'document.cookie', 'přístup k nadřazenému oknu', 'načítání kódu'], true) ? 'error' : 'warn', 'js-risk', $path, self::line($src, $m[0][1]), "Riziko v JavaScriptu: $what.");
        $s = preg_replace(['~/\*.*?\*/~s', '~(?<![:\\\\])//[^\n]*~', '~"(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`~s'], ['', '', '""'], $src);
        foreach (['(' => ')', '[' => ']', '{' => '}'] as $o => $c) if (substr_count($s, $o) !== substr_count($s, $c)) { $r[] = self::e('error', 'js-brackets', $path, 0, "Nepárové závorky $o$c v JavaScriptu."); break; }
        return $r;
    }
}
