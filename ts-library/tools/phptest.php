<?php
/* Minimální testovací běh pro tests/*_test.php (spouští tools/ts.py test). Poslední řádek výstupu: RESULT pass=N fail=M */
ob_start(); $GLOBALS["__pass"] = 0; $GLOBALS["__fail"] = 0;   /* výstup do bufferu: relace PHP v CLI se jinak po prvním výpisu nespustí */
function t_ok($c, string $msg): bool { if ($c) $GLOBALS['__pass']++; else { $GLOBALS['__fail']++; echo "FAIL $msg\n"; } return (bool) $c; }
function t_eq($a, $b, string $msg): bool { $s = $a === $b; if ($s) $GLOBALS['__pass']++; else { $GLOBALS['__fail']++; echo 'FAIL ' . $msg . ' | dostal ' . substr(var_export($a, true), 0, 300) . ' | čekal ' . substr(var_export($b, true), 0, 300) . "\n"; } return $s; }
function t_tmpdir(): string { $d = sys_get_temp_dir() . '/tsphp-' . bin2hex(random_bytes(5)); mkdir($d, 0700, true); register_shutdown_function(function () use ($d) { t_rm($d); }); return $d; }
function t_rm(string $d): void { if (!is_dir($d)) return; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); } @rmdir($d); }
function t_source(string $key): string { $s = json_decode(file_get_contents(__DIR__ . '/sources.json'), true); return $s[$key]; }
register_shutdown_function(function () { echo 'RESULT pass=' . $GLOBALS['__pass'] . ' fail=' . $GLOBALS['__fail'] . "\n"; });
set_exception_handler(function ($e) { $GLOBALS['__fail']++; echo 'FAIL výjimka: ' . $e->getMessage() . ' @' . $e->getLine() . "\n"; });
