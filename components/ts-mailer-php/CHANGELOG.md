# ts-mailer-php – změny

## 1.1.0 – 2026-10-31
- `redirectTo`: testovací režim – každý e-mail jde jen do testovací schránky, předmět „[TEST → původní příjemce]“, hlavička X-TS-Original-To.
- `probe()`: kontrola SMTP serveru bez přihlášení (spojení, uvítání, šifrování, nabídka AUTH LOGIN).

## 1.0.0 – 2026-10-30
- První verze v knihovně (Etapa 1). Původ a kontrolní součty: module.json → origin.
