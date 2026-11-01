# ts-site-audit – změny

## 1.1.2 – 2026-11-01
- Vizuální hodnocení: u Gemma 4 vypnuté „přemýšlení“ (`extra.chat_template_kwargs.enable_thinking = false`) – jinak spotřebovalo limit tokenů a odpověď zůstala prázdná; bez zálohy z `reasoning_content`, opsaná šablona z pokynů se zahodí. Ověřeno na stagingu: Gemma 4, Llama 4 Scout i Mistral Small 3.1 čtou text ze snímku správně; Gemma bez přemýšlení ~1 s a ~3–15 neuronů.

## 1.1.1 – 2026-11-01
- Po prvních skutečných měřeních na stagingu: titulky Lighthouse z markdownu (kód `<iframe>` zůstane jako text), obecné přehledy bez zjištěné hodnoty (strom závislostí, rozdělení LCP…) se do nálezů nedostanou.
- Vizuální hodnocení: obsah odpovědi i jako pole částí nebo `reasoning_content`; u prázdného výsledku diagnostika (výstup modelu, důvod ukončení) jen pro administraci. Administrace může vynutit nové měření mimo cache (`force`).

## 1.1.0 – 2026-11-01
- Zobrazení auditu `public/ts-audit.js` + `.css` (bez závislostí): formulář, průběh po krocích, report se skóre, metrikami, snímky a záložkami mobil/počítač, fakta z měření a doporučení AI v oddělených částech, poznámky o omezeních, seznam posledních auditů pro administraci; čeština a angličtina.
- Přístupová cookie (`access`, neveřejný staging), pro administraci `?r=audit-list` a otevření auditu podle čísla úlohy (`job`).

## 1.0.0 – 2026-11-01
- První verze (etapa 5.1, `docs/ai/07-audit-webu-plan.md` v projektu T&S): audit existujícího webu – měření Lighthouse přes Google PageSpeed Insights API (mobil + desktop, 4 kategorie, metriky, neúspěšné audity, snímky), vizuální hodnocení snímku modelem s viděním (Cloudflare Workers AI, volitelné), report oddělující fakta z měření od doporučení AI (bez čísel – položky s číslicemi se zahodí).
- Kontrola adresy proti SSRF (jen http/https a porty 80/443, žádné IP literály ani zvláštní zápisy, žádné přihlašovací údaje, DNS jen na veřejné adresy, lokální domény). Cizí web stahuje Google, ne náš server.
- Úloha po krocích (sdílený hosting bez procesů na pozadí): každý dotaz na stav po odeslání odpovědi posune audit o krok; zámek proti souběhu, opakování při výpadku spojení, cache stejné adresy (24 h), sdílení běžícího auditu, limity (na adresu IP, denně, administrace), denní strop hodnocení AI, retence úloh i snímků. Snímky jen s tajným tokenem úlohy.
- Testy bez skutečných služeb: 51 kontrol adresy, 50 kontrol auditu (falešné měření a hodnocení, adaptér PageSpeed proti lokální napodobenině).
