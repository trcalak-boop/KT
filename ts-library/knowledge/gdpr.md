# GDPR v komponentách

- Rezervační a poptávkové formuláře: povinný souhlas se zpracováním pro vyřízení (ne marketing), odkaz na zásady ochrany údajů.
- Ukládat jen nutné údaje (jméno, kontakt, termín, poznámka); marketingový souhlas oddělit.
- Retence: definovat pro každý projekt (např. rezervace 24 měsíců po termínu) – v kódu tasstudio.eu zatím **není definovaná**.
- Export a smazání na žádost: CRM tasstudio.eu má (`crm_export`, `crm_smazat`); rezervační backend to musí mít také.
- Demo úložiště (localStorage) nesmí obsahovat skutečné osobní údaje klientů.
- Logy: bez plných IP adres a hesel; atribuce bez hodnot click-id a bez parametrů URL (vzor: ts-crm-intake).
