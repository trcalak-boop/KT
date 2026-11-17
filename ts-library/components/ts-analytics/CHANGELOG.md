# ts-analytics – změny

## 1.0.0 – 2026-11-02
- První verze: sběr zobrazení stránek a událostí (`TsAnalytics::serve`, `public/ts-analytics.js`) jen po souhlasu návštěvníka, cookies prvního řádu `ts_v` (návštěvník, 13 měsíců) a `ts_s` (návštěva, 30 minut), `stop()` je smaže.
- Neukládá IP adresu (jen otisk pro limity), z adresy jen cestu, z odkazující stránky jen doménu; prohlížeč a systém jen hrubě.
- Ochrana: povinná hlavička Origin, roboti podle User-Agent, velikost těla, limity na adresu a den; retence 14 měsíců.
- Přehled pro administraci `TsAnalyticsReport::html`: návštěvníci, návštěvy, zobrazení, odchody po 1 stránce, délka návštěvy se srovnáním s předchozím obdobím, denní graf (SVG), stránky, kanály, odkazující weby, kampaně UTM, akce, jazyky, zařízení, prohlížeče, systémy.
