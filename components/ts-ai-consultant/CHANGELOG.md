# ts-ai-consultant – změny

## 1.0.1 – 2026-11-01
- Oprava: text, který model napíše před voláním nástroje kontaktu (doporučení, reference, cenová pravidla), se už neztratí – odpověď skládá text ze všech kol.
- Formulář jen na výslovné přání návštěvníka; na otázky asistent nejdřív odpoví a formulář nabídne otázkou (zjištěno testem na test.tasstudio.eu).

## 1.0.0 – 2026-11-01
- První verze (Etapa 3): brána `TsAiConsultant` (status, message, lead), adaptér `TsAiProvider` (Claude přes oficiální PHP SDK s časovými limity, falešný pro testy), `TsAiStore` (SQLite/MySQL, migrace 001: konverzace, spotřeba, rezervace rozpočtu, limity, leady), náhradní odpovědi v 18 jazycích (`TsAiTexts`), kontrolní stránka `TsAiCheck`, widget `public/ts-ai-widget.*`, CLI `cron.php` a `report.php`.
