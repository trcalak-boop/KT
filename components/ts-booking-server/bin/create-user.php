<?php
/* Vytvoření uživatele správy: php create-user.php <server-config.php> <uživatel> <role admin|staff|viewer> "<Jméno>" [e-mail]
   Heslo se zadá na vstupu (neukládá se do historie příkazů). */
require __DIR__ . '/../core/bootstrap.php';
[$_, $cfg, $user, $role, $name] = array_pad($argv, 5, null);
if (!$cfg || !$user || !in_array($role, ['admin', 'staff', 'viewer'], true) || !$name) { fwrite(STDERR, "Použití: php create-user.php <server-config.php> <uživatel> <admin|staff|viewer> \"Jméno\" [e-mail]\n"); exit(2); }
fwrite(STDERR, 'Heslo (aspoň 10 znaků): '); $pass = trim((string) fgets(STDIN));
$id = ts_booking_store(require $cfg)->createUser($user, $name, $pass, $role, $argv[5] ?? null);
echo "Uživatel $user (#$id, $role) vytvořen.\n";
