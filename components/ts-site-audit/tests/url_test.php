<?php
/* Kontrola adresy auditovaného webu: SSRF (interní sítě, zvláštní zápisy IP, přihlašovací údaje, porty), normalizace. DNS podstrčené. */
require __DIR__ . '/../../../tools/phptest.php';
require __DIR__ . '/../core/TsAuditUrl.php';
$dns = ['example.cz' => ['93.184.216.34'], 'www.tasstudio.eu' => ['37.9.175.212', '2a00:1ed0:2:0:1:5bef:c8f1:1'], 'interni.example.cz' => ['10.0.0.5'],
  'mix.example.cz' => ['93.184.216.34', '192.168.1.1'], 'v6lokal.example.cz' => ['fd00::1'], 'meta.example.cz' => ['169.254.169.254'],
  'xn--kok-9na.cz' => ['93.184.216.35'], 'cgnat.example.cz' => ['100.64.1.1'], 'mapped.example.cz' => ['::ffff:127.0.0.1']];
$r = fn ($h) => $dns[$h] ?? [];
$c = fn ($u) => TsAuditUrl::check($u, $r);
t_eq($c('example.cz'), ['ok' => true, 'url' => 'https://example.cz/', 'host' => 'example.cz'], 'bez schématu → https, cesta /');
t_eq($c('HTTPS://WWW.TasStudio.eu/cs/?a=1#kotva')['url'], 'https://www.tasstudio.eu/cs/?a=1', 'malá písmena hostitele, bez fragmentu, s dotazem');
t_eq($c('http://example.cz:80/x')['url'], 'http://example.cz/x', 'výchozí port se vynechá');
t_eq($c('https://bad.de')['error'] ?? 'ok', 'dns', 'doména z písmen a–f není IP (bad.de)');
foreach ([
  'http://127.0.0.1' => 'ip', 'http://localhost' => 'host', 'http://localhost.localdomain' => 'private', 'http://[::1]/' => 'ip', 'http://2130706433' => 'ip',
  'http://0x7f.0.0.1' => 'ip', 'http://127.1' => 'ip', 'http://017700000001' => 'ip', 'http://169.254.169.254/latest/meta-data' => 'ip',
  'https://user:heslo@example.cz' => 'userinfo', 'https://example.cz@interni.example.cz' => 'userinfo', 'https://example.cz:22' => 'port',
  'https://example.cz:8080' => 'port', 'ftp://example.cz' => 'scheme', 'file:///etc/passwd' => 'scheme', 'gopher://example.cz' => 'scheme',
  'javascript:alert(1)' => 'host', 'https://interni.example.cz' => 'private', 'https://mix.example.cz' => 'private', 'https://v6lokal.example.cz' => 'private',
  'https://meta.example.cz' => 'private', 'https://cgnat.example.cz' => 'private', 'https://mapped.example.cz' => 'private', 'https://neexistuje.example.cz' => 'dns',
  'https://server.internal' => 'private', 'https://router.lan' => 'private', 'https://web.test' => 'private', '' => 'empty', "https://exa mple.cz" => 'long',
  'https://' . str_repeat('a', 600) . '.cz' => 'long', 'https://-zly-.cz' => 'host', 'https://exam_ple.cz' => 'host',
] as $u => $e) t_eq($c($u)['error'] ?? 'ok', $e, 'odmítnuto: ' . substr($u, 0, 60));
if (function_exists('idn_to_ascii')) t_eq($c('https://kočka.cz')['host'] ?? '', 'xn--kok-9na.cz', 'IDN doména → punycode');
else t_ok(true, 'IDN přeskočeno (bez intl)');
foreach (['10.1.2.3', '172.31.0.1', '192.168.0.1', '127.0.0.53', '0.0.0.0', '224.0.0.1', '::1', 'fe80::1', 'fc00::5'] as $ip) t_ok(TsAuditUrl::blocked($ip), "blokováno $ip");
foreach (['93.184.216.34', '172.32.0.1', '100.128.0.1', '8.8.8.8', '2a00:1450:4001::1'] as $ip) t_ok(!TsAuditUrl::blocked($ip), "veřejná $ip");
