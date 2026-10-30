# API: ts-crm-intake (referenční, PHP)
`src/poptavka.php` – veřejný endpoint (POST JSON/form) → lead v CRM. Ochrany: skryté pole, časová kontrola, `submission_id` (idempotence), limit 8 poptávek/h z jedné adresy (otisk). `src/crm-poptavky.php` – `crm_poptavka_uloz(data, attribution)`, kanál z UTM/referrer/click-id (bez hodnot), fronta oznámení. `src/crm-lib.php` – schéma a migrace 1–5 (`leads, lead_inquiries, lead_attribution, lead_notifications, lead_consents …`).
Vázané na CRM T&S; pro nové projekty použít `ts-auth-php` + `ts-mailer-php` a vlastní adaptér (další etapa).
