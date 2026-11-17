# API: ts-auth-php 1.0.0 (třída `TsAuth`)

```php
require 'vendor/ts/ts-auth-php/core/TsAuth.php';
$auth = new TsAuth(['dataDir' => __DIR__ . '/../private/auth',          // mimo webroot nebo za „Require all denied“
                    'policy' => ['admin' => ['*'], 'sales' => ['crm.*', 'booking.view'], 'viewer' => ['booking.view']]]);
$auth->start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auth->csrfOk()) TsAuth::json(['ok' => false, 'chyba' => 'csrf'], 403);
if (!$auth->loggedIn() || !$auth->can('booking.edit')) TsAuth::json(['ok' => false, 'chyba' => 'opravneni'], 403);
```

| Metoda | Popis |
|---|---|
| `__construct(config)` | `dataDir` (povinné), `sessionName` (tsadmin), `idleSeconds` (12 h), `maxAttempts` (6), `lockSeconds` (900), `ipSalt`, `cookiePath`, `minPasswordLength` (10), `policy` |
| `start()` | relace: vlastní složka, strict mode, cookie HttpOnly + SameSite=Strict + Secure pod HTTPS, CSRF token, odhlášení po nečinnosti |
| `loggedIn()`, `userId()`, `role()`, `login(uid, role)`, `logout()` | přihlášení (nové ID relace i CSRF tokenu) |
| `csrfToken()`, `csrfOk(token?)` | token z `X-CSRF` nebo `$_POST['csrf']`, porovnání `hash_equals` |
| `hashPassword()`, `verifyPassword()`, `strongEnough()` | `password_hash` / `password_verify` |
| `lockedFor()`, `failedAttempt()`, `resetAttempts()` | zámek po špatných heslech (otisk IP, ne IP) |
| `rateLimit(key, max, window)` | limit požadavků (např. formuláře) |
| `can(permission, role?)` | oprávnění: přesná shoda, `oblast.*`, `*` |
| `TsAuth::writeAtomic(path, content)`, `TsAuth::json(data, code)` | pomocné |
