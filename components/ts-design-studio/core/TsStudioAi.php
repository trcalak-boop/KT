<?php
/* ==========================================================================
   ts-design-studio – AI vrstva: zadání (brief) → obsah a tokeny šablony jako JSON; konverzační úprava → změna obsahu, tokenů nebo vrstvy CSS.
   Model nikdy nepíše celé soubory: vrací JSON, který projde schématem šablony (TsStudioTemplate::cleanContent/cleanTokens) a kontrolami verze.
   Fakta (kontakty, ceny, jména, recenze) model nevymýšlí – co v zadání chybí, označí „[doplnit]“; Studio pak vypíše, co je k doplnění.
   Zdroje zadání: A nový projekt (formulář), B redesign z auditu (briefFromAudit), C lead z CRM (briefFromLead).
   ========================================================================== */
final class TsStudioAi
{
    const TODO = '[doplnit]';
    const BRIEF = ['name' => 160, 'obor' => 80, 'audience' => 300, 'style' => 300, 'services' => 1200, 'facts' => 2000, 'texts' => 6000, 'notes' => 1000, 'lang' => 5, 'template' => 40];

    /* ---------- zadání ---------- */
    public static function cleanBrief(array $b): array
    {
        $out = [];
        foreach (self::BRIEF as $k => $max) $out[$k] = trim(mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) ($b[$k] ?? '')) ?? '', 0, $max));
        if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $out['lang'])) $out['lang'] = 'cs';
        $out['audit'] = isset($b['audit']) && is_array($b['audit']) ? $b['audit'] : null;
        $out['rights'] = !empty($b['rights']);   /* převzatý obsah – ověřit práva */
        return $out;
    }
    /* C: lead z CRM (sloupce tabulky leads + poznámky) → zadání; kontakty leadu jsou kontakty budoucího klienta */
    public static function briefFromLead(array $lead, array $notes = []): array
    {
        $facts = array_filter(['Název: ' . ($lead['name'] ?? ''), !empty($lead['phone']) ? 'Telefon: ' . $lead['phone'] : '', !empty($lead['email']) ? 'E-mail: ' . $lead['email'] : '',
            !empty($lead['website']) ? 'Současný web: ' . $lead['website'] : '']);
        return ['name' => (string) ($lead['name'] ?? ''), 'facts' => implode("\n", $facts), 'notes' => trim(($lead['outreach_reason'] ?? '') . "\n" . implode("\n", array_map('strval', $notes)))];
    }
    /* B: hotový audit (report ts-site-audit) + texty, které vložil člověk → zadání redesignu; převzaté texty se označí k ověření práv */
    public static function briefFromAudit(array $report, string $texts = '', array $extra = []): array
    {
        $issues = [];
        foreach ((array) ($report['recommendations'] ?? []) as $r) if (count($issues) < 12) $issues[] = (($r['basis'] ?? '') === 'ai' ? 'AI: ' : 'Měření: ') . mb_substr((string) ($r['text'] ?? ''), 0, 160);
        $scores = $report['devices']['mobile']['scores'] ?? [];
        return $extra + ['audit' => ['url' => (string) ($report['url'] ?? ''), 'scores' => $scores, 'issues' => $issues], 'texts' => $texts, 'rights' => trim($texts) !== ''];
    }
    public static function briefText(array $b): string
    {
        $L = ['name' => 'Název podniku', 'obor' => 'Obor', 'audience' => 'Cílová skupina', 'style' => 'Požadovaný styl', 'services' => 'Služby a nabídka', 'facts' => 'Fakta (kontakty, adresa, otevírací doba, ceny, lidé)',
              'texts' => 'Texty od klienta / ze současného webu (smíš je použít a zkrátit)', 'notes' => 'Poznámky obchodníka'];
        $out = [];
        foreach ($L as $k => $l) if (($b[$k] ?? '') !== '') $out[] = "## $l\n" . $b[$k];
        if (!empty($b['audit'])) {
            $a = $b['audit']; $s = [];
            foreach ((array) ($a['scores'] ?? []) as $k => $v) $s[] = "$k $v";
            $out[] = "## Redesign – audit současného webu " . ($a['url'] ?? '') . "\nSkóre (mobil): " . implode(', ', $s) . "\n- " . implode("\n- ", (array) ($a['issues'] ?? []));
        }
        return implode("\n\n", $out) ?: '(zadání je prázdné)';
    }
    /* výběr šablony: podle oboru (klíčová slova šablony), jinak null = rozhodne model */
    public static function pickTemplate(array $templates, array $b): ?string
    {
        if ($b['template'] !== '' && isset($templates[$b['template']])) return $b['template'];
        $hay = mb_strtolower($b['obor'] . ' ' . $b['name'] . ' ' . $b['services']);
        foreach ($templates as $id => $t) foreach ((array) ($t->t['obor'] ?? []) as $kw) if ($kw !== '' && mb_strpos($hay, mb_strtolower($kw)) !== false) return $id;
        return null;
    }

    /* ---------- pokyny pro model ---------- */
    public static function pickPrompt(array $templates, array $b): array
    {
        $list = [];
        foreach ($templates as $id => $t) $list[] = "- $id: " . $t->t['name'] . ' (' . implode(', ', (array) ($t->t['obor'] ?? [])) . ') – ' . ($t->t['description'] ?? '');
        return [['role' => 'system', 'content' => "Vybíráš šablonu webu pro klienta webového studia. Odpověz jen JSON objektem {\"template\": \"id\"}.\nŠablony:\n" . implode("\n", $list)],
                ['role' => 'user', 'content' => self::briefText($b)]];
    }
    public static function createPrompt(TsStudioTemplate $t, array $b, ?int $now = null): array
    {
        $now = $now ?? time(); $m = (int) date('n', $now);
        $today = date('j. n. Y', $now) . ' (' . ($m <= 2 || $m === 12 ? 'zima' : ($m <= 5 ? 'jaro' : ($m <= 8 ? 'léto' : 'podzim'))) . ')';
        $sys = 'Jsi copywriter a designér webového studia T&S. Vyplňuješ ověřenou šablonu webu „' . $t->t['name'] . '“ pro konkrétního klienta: píšeš texty do slotů a volíš barvy a písma.' . "\n"
            . "Pravidla:\n"
            . '1. Odpověz JEN platným JSON objektem {"content": {…}, "tokens": {…}, "notes": "…"} – žádný text okolo, žádný markdown.' . "\n"
            . '2. content: hodnota pro KAŽDÝ slot ze schématu, v jazyce „' . $b['lang'] . '“, přirozeně, konkrétně pro tohoto klienta, bez frází a bez emoji. Dodrž maximální délky (znaky) a počty položek seznamů. Žádné HTML ani markdown.' . "\n"
            . '3. FAKTA NEVYMÝŠLEJ. Kontakty, adresu, otevírací dobu, ceny, jména lidí, recenze, citace, roky a čísla ber jen ze zadání. Co v zadání chybí, napiš jako „' . self::TODO . '“; u slotů typu phone, email a url nech prázdný řetězec. Recenze a citace bez podkladu = „' . self::TODO . '“.' . "\n"
            . '3b. Sloty označené „(jen ze zadání)“ – citáty, recenze, jména lidí a autoři – smí obsahovat jen text, který v zadání doslova je. Jinak „' . self::TODO . '“ (seznam recenzí a týmu může být i prázdný). Studio to ověřuje a vymyšlené texty zahodí.' . "\n"
            . '4. Vzor ukazuje tón, délky a strukturu původní ukázky – NEKOPÍRUJ z něj fakta ani názvy (jiný podnik).' . "\n"
            . '5. tokens: barvy jako #rrggbb laděné ke stylu a oboru klienta, dostatečný kontrast textu na pozadí (alespoň 4,5:1); písma vyber přesně z nabídky.' . "\n"
            . '6. notes: jedna až dvě věty pro designéra – co je k doplnění nebo ověření.'
            . ($b['rights'] ? "\n7. Texty klienta / ze současného webu můžeš použít, ale jsou převzaté – do notes připiš „převzatý obsah – ověřit práva“." : '');
        $user = "# Dnes\n$today – rok a roční období ber odsud, ne ze vzoru.\n\n# Zadání klienta\n" . self::briefText($b) . "\n\n# Schéma slotů (klíč: typ, max. délka – popis)\n" . self::schemaText($t)
            . "\n\n# Tokeny (klíč: popis = výchozí hodnota)\n" . self::tokensText($t)
            . "\n\n# Vzor (obsah původní ukázky – jen tón a délky)\n" . json_encode($t->t['content'], JSON_UNESCAPED_UNICODE);
        return [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]];
    }
    public static function editPrompt(TsStudioTemplate $t, array $v, string $instruction, array $history = []): array
    {
        $sys = 'Jsi designér webového studia T&S a upravuješ web klienta postavený na šabloně „' . $t->t['name'] . '“ podle pokynu kolegy.' . "\n"
            . 'Odpověz JEN platným JSON objektem {"reply": "…", "content": {…}, "tokens": {…}, "css": "…"} – žádný text okolo.' . "\n"
            . "- reply: krátce česky, co jsi změnil(a), nebo proč pokyn nejde splnit.\n"
            . "- content: JEN sloty, které měníš (seznam vždy celý). Stejná pravidla délek; fakta nevymýšlej (chybějící = „" . self::TODO . "“).\n"
            . "- tokens: JEN měněné barvy (#rrggbb) a písma (přesně z nabídky).\n"
            . "- css: jen když pokyn nejde splnit obsahem ani tokeny (velikosti, mezery, zarovnání, skrytí sekce…). Je to CELÝ obsah vrstvy úprav, která se připojí na konec stylů – zachovej z ní pravidla, která mají zůstat. Používej jen selektory z osnovy stránky, žádné @import, url() ani externí zdroje. Velikosti zadávej v rem, px, vw nebo clamp()/min() a vycházej ze současných pravidel níže (zmenšit = menší hodnota než současná) – nikdy em ani %, ty se počítají z rodiče. Bez změny vynech klíč.\n"
            . "- Barvy: text musí mít vůči pozadí kontrast alespoň 4,5:1, jinak ho Studio samo ztmaví.\n"
            . 'Neměněné klíče vynech.';
        $user = "# Pokyn\n$instruction\n\n# Osnova stránky (prvky a třídy)\n" . self::outline($v['files']['index.html'] ?? '')
            . (($rules = self::cssRules($v['files']['assets/styles.css'] ?? '', (array) ($t->t['cssHints'] ?? []))) !== '' ? "\n\n# Současná pravidla klíčových prvků\n" . $rules : '')
            . "\n\n# Schéma slotů\n" . self::schemaText($t) . "\n\n# Tokeny\n" . self::tokensText($t, $v['tokens'] ?? [])
            . "\n\n# Současný obsah\n" . json_encode($v['content'] ?? [], JSON_UNESCAPED_UNICODE)
            . "\n\n# Současná vrstva úprav CSS\n" . (self::aiCss($v['files']['assets/styles.css'] ?? '') ?: '(prázdná)');
        $msgs = [['role' => 'system', 'content' => $sys]];
        foreach (array_slice($history, -6) as $h) $msgs[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr((string) $h['text'], 0, 600)];
        $msgs[] = ['role' => 'user', 'content' => $user];
        return $msgs;
    }
    public static function schemaText(TsStudioTemplate $t): string
    {
        $out = [];
        foreach ($t->t['slots'] as $k => $s) {
            $type = $s['type'] ?? 'text';
            if ($type === 'list') {
                $f = []; foreach ((array) ($s['fields'] ?? []) as $fk => $fs) $f[] = $fk . ' ' . ($fs['type'] ?? 'text') . (isset($fs['max']) ? ' ≤' . $fs['max'] : '') . (!empty($fs['label']) ? ' (' . $fs['label'] . ')' : '') . (!empty($fs['grounded']) ? ' (jen ze zadání)' : '');
                $out[] = "$k: seznam ≤" . ($s['items'] ?? 12) . ' × {' . implode(', ', $f) . '} – ' . ($s['label'] ?? '');
            } else $out[] = "$k: $type" . (isset($s['max']) ? ' ≤' . $s['max'] : '') . ' – ' . ($s['label'] ?? '') . (!empty($s['grounded']) ? ' (jen ze zadání)' : '');
        }
        return implode("\n", $out);
    }
    public static function tokensText(TsStudioTemplate $t, array $cur = []): string
    {
        $out = [];
        foreach ($t->t['tokens'] as $k => $d) $out[] = "$k: " . ($d['label'] ?? '') . ' = ' . ($cur[$k] ?? $d['value']) . (($d['type'] ?? '') === 'font' ? ' | nabídka: ' . implode(' ‖ ', (array) ($d['options'] ?? [])) : '');
        return implode("\n", $out);
    }
    /* osnova HTML: prvky s id/třídou, odsazené podle hloubky, opakované sourozence sloučí (×N) – model z ní volí selektory pro vrstvu CSS */
    public static function outline(string $html, int $maxLines = 160): string
    {
        if ($html === '') return '';
        $d = new DOMDocument(); $prev = libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING); libxml_clear_errors(); libxml_use_internal_errors($prev);
        $lines = [];
        $walk = function (DOMNode $n, int $depth) use (&$walk, &$lines) {
            $last = null; $count = 0; $at = -1;
            foreach ($n->childNodes as $ch) {
                if (!$ch instanceof DOMElement || in_array($ch->tagName, ['script', 'style', 'svg', 'head', 'br', 'i', 'em', 'b'], true)) continue;
                $sig = $ch->tagName . ($ch->getAttribute('id') !== '' ? '#' . $ch->getAttribute('id') : '') . ($ch->getAttribute('class') !== '' ? '.' . implode('.', preg_split('/\s+/', trim($ch->getAttribute('class')))) : '');
                $named = $ch->getAttribute('id') !== '' || $ch->getAttribute('class') !== '' || in_array($ch->tagName, ['header', 'main', 'section', 'footer', 'nav', 'h1', 'h2', 'h3', 'table'], true);
                if ($sig === $last) { $count++; continue; }
                if ($at >= 0 && $count > 1) $lines[$at] .= " ×$count";
                $last = $sig; $count = 1; $at = -1;
                if ($named) { $at = count($lines); $lines[] = str_repeat('  ', $depth) . $sig; $walk($ch, $depth + 1); } else $walk($ch, $depth);
            }
            if ($at >= 0 && $count > 1) $lines[$at] .= " ×$count";
        };
        $body = $d->getElementsByTagName('body')->item(0); if ($body) $walk($body, 0);
        if (count($lines) > $maxLines) $lines = array_merge(array_slice($lines, 0, $maxLines), ['…']);
        return implode("\n", $lines);
    }

    /* poslední pravidla CSS pro vybrané selektory (bez @media) – model z nich odvodí nové hodnoty */
    public static function cssRules(string $css, array $selectors): string
    {
        $css = preg_replace('~/\*.*?\*/~s', '', $css); $out = [];
        preg_match_all('~([^{}@]+)\{([^{}]*)\}~', $css, $mm, PREG_SET_ORDER);
        foreach ($selectors as $sel) {
            $hit = '';
            foreach ($mm as $m) if (in_array($sel, array_map('trim', explode(',', $m[1])), true)) $hit = $sel . '{' . trim($m[2]) . '}';
            if ($hit !== '') $out[] = mb_substr($hit, 0, 300);
        }
        return implode("\n", $out);
    }

    /* ---------- ukotvení faktů ---------- */
    /* sloty „grounded“ (citáty, recenze, jména) smí obsahovat jen text ze zadání: aspoň 80 % slov (≥ 4 znaky) musí v zadání být, jinak „[doplnit]“ */
    public static function ground(TsStudioTemplate $t, array $content, string $source): array
    {
        $words = fn (string $s) => array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)) ?: [], fn ($w) => mb_strlen($w) >= 4));
        $src = array_flip($words($source)); $dropped = [];
        $ok = function (string $v) use ($words, $src): bool {
            if ($v === '' || mb_strpos($v, self::TODO) !== false) return true;
            $w = $words($v); if (!$w) return true;
            return count(array_filter($w, fn ($x) => isset($src[$x]))) / count($w) >= 0.8;
        };
        foreach ($t->t['slots'] as $k => $s) {
            if (!isset($content[$k])) continue;
            if (($s['type'] ?? '') === 'list') {
                foreach ((array) $content[$k] as $i => $row) foreach ((array) ($s['fields'] ?? []) as $fk => $fs)
                    if (!empty($fs['grounded']) && is_array($row) && !$ok((string) ($row[$fk] ?? ''))) { $content[$k][$i][$fk] = self::TODO; $dropped[] = "$k.$i.$fk"; }
            } elseif (!empty($s['grounded']) && !$ok((string) $content[$k])) { $content[$k] = self::TODO; $dropped[] = $k; }
        }
        return [$content, $dropped];
    }
    public static function briefSource(array $b): string
    {
        return implode("\n", [$b['name'] ?? '', $b['facts'] ?? '', $b['texts'] ?? '', $b['notes'] ?? '', $b['services'] ?? '', $b['audience'] ?? '']);
    }

    /* ---------- odpověď modelu ---------- */
    /* první JSON objekt v textu (i v ```json bloku); null = nečitelné */
    public static function parseJson(string $text): ?array
    {
        $s = preg_replace('~^\s*```(?:json)?\s*|\s*```\s*$~i', '', trim($text));
        $i = strpos($s, '{'); if ($i === false) return null;
        $depth = 0; $in = false; $esc = false;
        for ($k = $i, $n = strlen($s); $k < $n; $k++) {
            $ch = $s[$k];
            if ($in) { if ($esc) $esc = false; elseif ($ch === '\\') $esc = true; elseif ($ch === '"') $in = false; continue; }
            if ($ch === '"') $in = true; elseif ($ch === '{') $depth++;
            elseif ($ch === '}' && --$depth === 0) { $j = json_decode(substr($s, $i, $k - $i + 1), true); return is_array($j) ? $j : null; }
        }
        return null;
    }
    /* tokeny od modelu: písmo i jako číslo položky nabídky; neznámé klíče pryč */
    public static function normTokens(TsStudioTemplate $t, $tk): array
    {
        $out = [];
        foreach ((array) $tk as $k => $v) {
            $d = $t->t['tokens'][$k] ?? null; if (!$d || !is_scalar($v)) continue;
            if (($d['type'] ?? '') === 'font') {
                $opts = (array) ($d['options'] ?? []);
                if (is_numeric($v) && isset($opts[(int) $v])) $v = $opts[(int) $v];
                else foreach ($opts as $o) if (mb_stripos($o, trim((string) $v, " \"'")) === 0 || trim($o) === trim((string) $v)) { $v = $o; break; }
            }
            $out[$k] = trim((string) $v);
        }
        return $out;
    }
    /* sloty k doplnění: „[doplnit]“ nebo prázdné povinné kontakty */
    public static function todo(array $content): array
    {
        $out = [];
        $scan = function ($v, string $path) use (&$scan, &$out) {
            if (is_array($v)) { foreach ($v as $k => $x) $scan($x, $path === '' ? (string) $k : "$path.$k"); return; }
            if (mb_strpos((string) $v, self::TODO) !== false) $out[] = $path;
        };
        $scan($content, '');
        return $out;
    }
    /* vrstva úprav CSS ve stylech šablony */
    public static function aiCss(string $css): string
    {
        return preg_match('~/\*@ai\*/(.*?)/\*@ai-end\*/~s', $css, $m) ? trim($m[1]) : '';
    }
    public static function withAiCss(string $css, string $layer): string
    {
        $css = rtrim(preg_replace('~\n?/\*@ai\*/.*?/\*@ai-end\*/\n?~s', "\n", $css));
        $layer = trim(str_replace(['/*@ai*/', '/*@ai-end*/', '</style'], '', $layer));
        return $layer === '' ? $css . "\n" : $css . "\n/*@ai*/\n" . $layer . "\n/*@ai-end*/\n";
    }
}
