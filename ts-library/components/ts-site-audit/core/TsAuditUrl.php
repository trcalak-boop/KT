<?php
/* ==========================================================================
   ts-site-audit – kontrola adresy auditovaného webu (ochrana proti SSRF a zneužití).
   Cizí stránku stahuje měřicí služba (Google PageSpeed), ne náš server – adresu přesto ověřujeme,
   aby nešlo auditovat interní síť ani podstrčit zvláštní zápisy adres.
   TsAuditUrl::check('example.cz/stranka') → ['ok' => true, 'url' => 'https://example.cz/stranka', 'host' => 'example.cz']
                                          | ['ok' => false, 'error' => 'scheme' | 'host' | 'ip' | 'port' | 'userinfo' | 'private' | 'dns' | 'long' | 'empty']
   ========================================================================== */
final class TsAuditUrl
{
    /* privátní, rezervované a speciální rozsahy (IPv4 i IPv6) */
    const BLOCKED = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8'];
    const LOCAL_TLD = ['localhost', 'local', 'internal', 'intranet', 'lan', 'home', 'corp', 'test', 'invalid', 'example', 'onion', 'arpa', 'localdomain', 'home.arpa'];

    /* $resolve: fn(string $host): string[] (IP adresy); výchozí = DNS (A + AAAA). V testech se podstrčí. */
    public static function check(string $raw, ?callable $resolve = null): array
    {
        $raw = trim($raw);
        if ($raw === '') return ['ok' => false, 'error' => 'empty'];
        if (strlen($raw) > 500 || preg_match('/[\x00-\x20\x7F\\\\]/', $raw)) return ['ok' => false, 'error' => 'long'];
        if (!preg_match('~^[a-z][a-z0-9+.\-]*://~i', $raw)) $raw = 'https://' . $raw;
        $scheme = strtolower(substr($raw, 0, strpos($raw, ':')));
        if (!in_array($scheme, ['http', 'https'], true)) return ['ok' => false, 'error' => 'scheme'];
        $p = parse_url($raw);
        if ($p === false || empty($p['host'])) return ['ok' => false, 'error' => 'host'];
        if (isset($p['user']) || isset($p['pass'])) return ['ok' => false, 'error' => 'userinfo'];
        if (isset($p['port']) && !in_array((int) $p['port'], [80, 443], true)) return ['ok' => false, 'error' => 'port'];
        $host = strtolower(rtrim($p['host'], '.'));
        if (str_starts_with($host, '[') || filter_var($host, FILTER_VALIDATE_IP)) return ['ok' => false, 'error' => 'ip'];
        if (preg_match('/^(?:0x[0-9a-f]+|\d+)(?:\.(?:0x[0-9a-f]+|\d+)){0,3}$/i', $host)) return ['ok' => false, 'error' => 'ip'];   /* 2130706433, 0x7f.0.0.1, 127.1 … */
        if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $host)) { $a = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46); if ($a === false) return ['ok' => false, 'error' => 'host']; $host = $a; }
        if (strlen($host) > 253 || !preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9\-]{1,62}$/', $host)) return ['ok' => false, 'error' => 'host'];
        $tld = substr($host, strrpos($host, '.') + 1);
        if (in_array($tld, self::LOCAL_TLD, true)) return ['ok' => false, 'error' => 'private'];
        $ips = $resolve ? (array) $resolve($host) : self::dns($host);
        if (!$ips) return ['ok' => false, 'error' => 'dns'];
        foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP) || self::blocked($ip)) return ['ok' => false, 'error' => 'private'];
        $path = (string) ($p['path'] ?? '/'); if ($path === '') $path = '/';
        $url = $scheme . '://' . $host . (isset($p['port']) && !in_array((int) $p['port'], [$scheme === 'https' ? 443 : 80], true) ? ':' . $p['port'] : '') . $path . (isset($p['query']) ? '?' . $p['query'] : '');
        return ['ok' => true, 'url' => $url, 'host' => $host];
    }

    public static function dns(string $host): array
    {
        $ips = [];
        foreach ([DNS_A, DNS_AAAA] as $t) foreach (@dns_get_record($host, $t) ?: [] as $r) { $ips[] = $r['ip'] ?? $r['ipv6'] ?? ''; }
        return array_values(array_filter(array_unique($ips)));
    }

    public static function blocked(string $ip): bool
    {
        $bin = @inet_pton($ip); if ($bin === false) return true;
        foreach (self::BLOCKED as $cidr) {
            [$net, $bits] = explode('/', $cidr); $nb = inet_pton($net);
            if (strlen($nb) !== strlen($bin)) continue;
            $bits = (int) $bits; $bytes = intdiv($bits, 8); $rest = $bits % 8;
            if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) continue;
            if ($rest === 0) return true;
            $mask = chr((0xFF << (8 - $rest)) & 0xFF);
            if ((ord($bin[$bytes]) & ord($mask)) === (ord($nb[$bytes]) & ord($mask))) return true;
        }
        return false;
    }
}
