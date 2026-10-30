# Bezpečnostní pravidla

- Výstup do HTML vždy escapovat (`TS.esc`, `esc` ve widgetu, `htmlspecialchars` v PHP).
- Stavové požadavky: CSRF token (TsAuth::csrfOk), metoda POST, `SameSite=Strict` cookies, HttpOnly, Secure pod HTTPS.
- Oprávnění kontrolovat na serveru u každé akce (`can()`), ne jen skrýt tlačítko.
- Hesla jen `password_hash`; zámek po 6 špatných pokusech; IP adresy jen jako otisk (sha256 + sůl).
- Formuláře: limit požadavků, skryté pole + časová kontrola, idempotenční klíč (dvojklik, opakované odeslání).
- E-maily: příjemce a předmět bez CR/LF (TsMailer to odmítne), odesílatel na doméně webu, návštěvník jen v Reply-To.
- Tajemství (SMTP heslo, API klíče) nikdy v gitu, HTML ani JS; konfigurace mimo webroot.
- Rezervace: klient nikdy nerozhoduje o dostupnosti – server ji ověří znovu před zápisem.
- Platební údaje se neukládají nikdy; platby jen přes certifikovanou bránu (samostatné schválení).
- Zálohy s osobními údaji: jen lokálně, práva 700/600, nikdy do vzdáleného repozitáře (`~/TS-Studio/zalohy`).
