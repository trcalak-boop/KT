# ts-site-audit – změny

## 1.0.0 – 2026-11-01
- První verze (etapa 5.1, `docs/ai/07-audit-webu-plan.md` v projektu T&S): audit existujícího webu – měření Lighthouse přes Google PageSpeed Insights API (mobil + desktop, 4 kategorie, metriky, neúspěšné audity, snímky), vizuální hodnocení snímku modelem s viděním (Cloudflare Workers AI, volitelné), report oddělující fakta z měření od doporučení AI (bez čísel – položky s číslicemi se zahodí).
- Kontrola adresy proti SSRF (jen http/https a porty 80/443, žádné IP literály ani zvláštní zápisy, žádné přihlašovací údaje, DNS jen na veřejné adresy, lokální domény). Cizí web stahuje Google, ne náš server.
- Úloha po krocích (sdílený hosting bez procesů na pozadí): každý dotaz na stav po odeslání odpovědi posune audit o krok; zámek proti souběhu, opakování při výpadku spojení, cache stejné adresy (24 h), sdílení běžícího auditu, limity (na adresu IP, denně, administrace), denní strop hodnocení AI, retence úloh i snímků. Snímky jen s tajným tokenem úlohy.
- Testy bez skutečných služeb: 51 kontrol adresy, 50 kontrol auditu (falešné měření a hodnocení, adaptér PageSpeed proti lokální napodobenině).
