# Licence převzatého kódu a písem (ověřeno 30. 10. 2026)

| Položka | Kde | Licence | Komerční použití | Poznámka |
|---|---|---|---|---|
| Vlastní kód T&S (kit, ukázky, administrace) | všechny komponenty | interní, T&S Studio | ano | autorský kód studia |
| jsPDF 4.2.1 | `ts-ref-lokna/src/assets/js/`, používá ts-receipts-core | MIT (hlavička souboru) | ano | zachovat licenční hlavičku |
| tFPDF 1.34 + ttfonts.php 1.06 | `ts-pdf-php/core/` | LGPL (verze v souboru neuvedena) | ano, jako knihovna beze změn | **chybí plný text LGPL** – doplnit; úpravy knihovny by musely být pod LGPL |
| FPDF (základ tFPDF) | – | volná licence bez omezení | ano | |
| Písma Outfit, DM Serif Display, Sora, Syne (Lokna) | `ts-ref-lokna/src/assets/fonts/` | SIL OFL 1.1 (metadata TTF + přiložené OFL texty) | ano | písma nelze prodávat samostatně; přiložit OFL |
| Písma PDF v projektu (Bodoni Moda, Figtree, Manrope, IBM Plex Mono, Unbounded, Hanken Grotesk, Archivo) | zdrojový projekt | SIL OFL 1.1 (ověřeno z name tabulky TTF) | ano | do knihovny zatím nepřevzata |

Ověření písem: tabulka `name` (ID 13/14) souborů TTF; u WOFF2 přiložené licenční soubory projektu `web/assets/fonts/licence/`.
Každá komponenta eviduje licenci v `module.json → license` a původ v `origin`.
