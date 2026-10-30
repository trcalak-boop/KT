<?php
/* ==========================================================================
   T&S administrace – CRM: databáze, migrace, uživatelé a společná pravidla
   Načítá ho lib.php. Přímo z internetu nepřístupné (viz .htaccess).
   Data: SQLite databáze admin/data/crm.sqlite (složka data je z internetu zavřená).
   ========================================================================== */
if (!defined('ADM')) { http_response_code(403); exit; }

define('CRM_DB', DATA . '/crm.sqlite');
define('CRM_ZALOHY', DATA . '/crm-zalohy');
define('CRM_ZALOHY_MAX', 30);

/* Číselníky. Klíče se ukládají do databáze, popisky jsou jen pro zobrazení. */
const CRM_STAVY = ['novy' => 'Nový', 'kontaktovan' => 'Kontaktován', 'v_reseni' => 'V řešení', 'ukonceno' => 'Ukončeno', 'dokonceno' => 'Dokončeno'];
const CRM_AKTIVNI = ['novy', 'kontaktovan', 'v_reseni'];
const CRM_UZAVRENE = ['ukonceno', 'dokonceno'];
const CRM_PRIORITY = ['nizka' => 'Nízká', 'standardni' => 'Standardní', 'vysoka' => 'Vysoká'];
const CRM_TYPY = ['telefon' => 'Telefonát', 'email' => 'E-mail', 'schuzka' => 'Osobní schůzka', 'video' => 'Videohovor', 'jiny' => 'Jiný způsob kontaktu'];
const CRM_VYSLEDKY = ['bez_odpovedi' => 'Bez odpovědi', 'nezastizen' => 'Nezastižen', 'zajem' => 'Zájem', 'potencialni_zajem' => 'Potenciální zájem',
    'vice_informaci' => 'Požaduje více informací', 'cenova_nabidka' => 'Požaduje cenovou nabídku', 'schuzka' => 'Domluvena schůzka',
    'odlozeno' => 'Odloženo na později', 'nezajem' => 'Nezájem', 'jiny' => 'Jiný výsledek'];
const CRM_UKOL_STAVY = ['ceka' => 'Čeká', 'splneno' => 'Splněno', 'zruseno' => 'Zrušeno'];
/* Odkud lead přišel (leads.source) a kanál, ze kterého přišla poptávka z webu (lead_attribution.channel). */
const CRM_PUVOD = ['rucne' => 'Zadaný ručně', 'import' => 'Načtený ze souboru', 'web' => 'Poptávka z webu'];
const CRM_ZDROJE = ['organicke' => 'Vyhledávače (neplacené)', 'primo' => 'Přímá návštěva', 'google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads',
    'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'odkaz' => 'Odkaz z jiného webu', 'portfolio' => 'Portfolio',
    'kampan' => 'Jiná kampaň', 'nezname' => 'Neznámý'];
const CRM_JAZYKY = ['en' => 'Angličtina', 'cs' => 'Čeština', 'de' => 'Němčina'];
const CRM_OZNAMENI_STAVY = ['ceka' => 'Čeká na odeslání', 'odeslano' => 'Odesláno (potvrdil poštovní server)', 'predano' => 'Předáno serveru webu (doručení nepotvrzeno)',
    'chyba' => 'Nepodařilo se, zkusí se znovu', 'selhalo' => 'Selhalo (vyčerpány pokusy)'];
/* Souhlas GDPR (dokument k podpisu): účely, které jde do dokumentu zařadit, a doba platnosti od podpisu. */
const CRM_SOUHLAS_UCELY = ['reference' => 'Zveřejnění zakázky jako reference', 'foto' => 'Fotografie, jméno a citace'];
/* Služby, pro jejichž poskytnutí klient podpisem potvrzuje seznámení se zpracováním osobních údajů (nejde o souhlas, ale o informaci podle čl. 13 GDPR). */
const CRM_SLUZBY = ['web' => 'tvorba webových stránek', 'grafika' => 'grafický design (logo, vizuální identita, tiskoviny)', 'hosting' => 'zajištění webhostingu a domény',
    'sprava' => 'správa, údržba a aktualizace webových stránek'];
/* Příjemci, se kterými správce údaje sdílí (oddíl „Příjemci osobních údajů“ v dokumentu). Údaje podle webu krystoftrcala.studio. */
const CRM_PRIJEMCI = [
    ['nazev' => 'Kryštof Trčala', 'ico' => '299 41 661', 'sidlo' => 'Zlín', 'kontakt' => 'trcala.k@gmail.com, +420 774 131 440, www.krystoftrcala.studio',
     'role' => 'poddodavatel správce, který pro klienty správce zajišťuje grafický design a související služby (zejména fotografie a přípravu tiskových podkladů); správce tyto služby klientovi zprostředkovává. Osobní údaje mu jsou předávány v rozsahu nezbytném pro poskytnutí služby a zpracovává je jako zpracovatel jménem správce a podle jeho pokynů (čl. 28 GDPR).'],
];
const CRM_SOUHLAS_ROKY = 3;
const CRM_SOUHLAS_STAVY = ['pripraveno' => 'Čeká na podpis', 'podepsano' => 'Podepsaný', 'odmitnuto' => 'Klient nesouhlasil', 'odvolano' => 'Odvolaný',
    'ukonceno' => 'Ukončený po vypršení', 'zruseno' => 'Zrušený dokument'];
define('CRM_SOUHLASY_DIR', DATA . '/crm-souhlasy');     // naskenované podepsané souhlasy (složka data je z internetu zavřená)
/* Účty, které migrace založí. Další členy týmu stačí přidat sem (nebo do tabulky users), nic dalšího se nemění. */
const CRM_TYM = [['krystof', 'Kryštof Trčala'], ['matous', 'Matouš Slováček']];

function crm_dostupne() { return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true); }

function crm_db() {
    static $db = null;
    if ($db) return $db;
    if (!crm_dostupne()) throw new RuntimeException('Hosting nemá zapnutou podporu databáze SQLite (PDO SQLite).');
    $novy = !is_file(CRM_DB);
    $db = new PDO('sqlite:' . CRM_DB, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 8]);
    if ($novy) @chmod(CRM_DB, 0600);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 8000');      // dva lidé zároveň: zápis chvíli počká, místo aby selhal
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    crm_migrace($db);
    return $db;
}

/* Zápis v transakci. BEGIN IMMEDIATE zamkne databázi pro zápis hned na začátku,
   takže dvě souběžné úpravy se seřadí za sebe a žádná se neztratí. */
function crm_tx(callable $fn) {
    $db = crm_db();
    $db->exec('BEGIN IMMEDIATE');
    try { $r = $fn($db); $db->exec('COMMIT'); }
    catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    crm_denni_zaloha();
    return $r;
}

function crm_q($sql, array $p = []) { $s = crm_db()->prepare($sql); $s->execute($p); return $s; }
function crm_all($sql, array $p = []) { return crm_q($sql, $p)->fetchAll(); }
function crm_one($sql, array $p = []) { $r = crm_q($sql, $p)->fetch(); return $r === false ? null : $r; }
function crm_val($sql, array $p = []) { $r = crm_q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function crm_ted() { return date('Y-m-d H:i:s'); }

/* ---------- migrace ----------
   Každá migrace se spustí právě jednou (tabulka schema_migrations). Nové verze se přidávají na konec seznamu. */
function crm_migrace(PDO $db) {
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, name TEXT NOT NULL, applied_at TEXT NOT NULL)');
    $hotove = array_map('intval', $db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
    foreach (crm_seznam_migraci() as $v => $m) {
        if (in_array($v, $hotove, true)) continue;
        $prestavba = !empty($m[2]);           // přestavba tabulky: kontrola cizích klíčů se vypne jen na dobu migrace
        if ($prestavba && $hotove) {           // před přestavbou existující databáze se vždy udělá záloha
            try { if (!is_dir(CRM_ZALOHY)) @mkdir(CRM_ZALOHY, 0700, true); $db->exec('VACUUM INTO ' . $db->quote(CRM_ZALOHY . '/pred-migraci-' . $v . '-' . date('Ymd-His') . '.sqlite')); } catch (Throwable $e) { }
        }
        if ($prestavba) $db->exec('PRAGMA foreign_keys = OFF');
        $db->exec('BEGIN IMMEDIATE');
        try {
            if (in_array($v, array_map('intval', $db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)), true)) { $db->exec('COMMIT'); continue; }
            foreach ($m[1] as $sql) is_callable($sql) ? $sql($db) : $db->exec($sql);
            if ($prestavba && $db->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('Migrace ' . $v . ': porušené vazby v databázi.');
            $s = $db->prepare('INSERT INTO schema_migrations (version, name, applied_at) VALUES (?, ?, ?)');
            $s->execute([$v, $m[0], crm_ted()]);
            $db->exec('COMMIT');
        } catch (Throwable $e) { $db->exec('ROLLBACK'); if ($prestavba) $db->exec('PRAGMA foreign_keys = ON'); throw $e; }
        if ($prestavba) $db->exec('PRAGMA foreign_keys = ON');
    }
}
function crm_seznam_migraci() {
    return [
        1 => ['zakladni schema CRM', [
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                email TEXT,
                role TEXT NOT NULL DEFAULT \'admin\',          -- připraveno pro budoucí rozdělení oprávnění
                password_hash TEXT,                             -- osobní heslo; dokud chybí, platí společné heslo administrace
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL,
                last_login_at TEXT)',
            'CREATE TABLE leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                name_norm TEXT NOT NULL DEFAULT \'\',
                phone TEXT, phone_norm TEXT,
                email TEXT, email_norm TEXT,
                website TEXT, domain TEXT,
                outreach_reason TEXT,
                status TEXT NOT NULL DEFAULT \'novy\' CHECK (status IN (\'novy\',\'kontaktovan\',\'v_reseni\',\'ukonceno\',\'dokonceno\')),
                priority TEXT NOT NULL DEFAULT \'standardni\' CHECK (priority IN (\'nizka\',\'standardni\',\'vysoka\')),
                assigned_user_id INTEGER NOT NULL REFERENCES users(id),
                created_by_user_id INTEGER NOT NULL REFERENCES users(id),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL,
                closed_at TEXT,
                closure_reason TEXT,
                archived_at TEXT,
                waiting INTEGER NOT NULL DEFAULT 0,             -- výslovně „čeká bez termínu“
                waiting_note TEXT,
                contact_count INTEGER NOT NULL DEFAULT 0,       -- pokusy o kontakt (bez interních poznámek)
                last_contact_at TEXT,
                last_contact_result TEXT,
                project_id TEXT,                                -- budoucí převod na zakázku (id složky reference)
                version INTEGER NOT NULL DEFAULT 1)',            // ochrana proti přepsání změn druhého uživatele
            'CREATE TABLE lead_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id),
                contact_type TEXT NOT NULL,
                contact_result TEXT NOT NULL,
                notes TEXT,
                contacted_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                updated_by_user_id INTEGER REFERENCES users(id),
                deleted_at TEXT)',
            'CREATE TABLE lead_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                assigned_user_id INTEGER NOT NULL REFERENCES users(id),
                created_by_user_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                description TEXT,
                due_at TEXT NOT NULL,
                due_has_time INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT \'ceka\' CHECK (status IN (\'ceka\',\'splneno\',\'zruseno\')),
                completed_at TEXT,
                completed_by_user_id INTEGER REFERENCES users(id),
                source_contact_id INTEGER REFERENCES lead_contacts(id) ON DELETE SET NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL)',
            'CREATE TABLE lead_notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id),
                content TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT,
                deleted_at TEXT)',
            'CREATE TABLE lead_audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES users(id),
                action TEXT NOT NULL,
                field TEXT,
                old_value TEXT,
                new_value TEXT,
                created_at TEXT NOT NULL)',
            'CREATE INDEX ix_leads_status ON leads(status, archived_at)',
            'CREATE INDEX ix_leads_owner ON leads(assigned_user_id, status)',
            'CREATE INDEX ix_leads_activity ON leads(last_activity_at)',
            'CREATE INDEX ix_leads_domain ON leads(domain)',
            'CREATE INDEX ix_leads_email ON leads(email_norm)',
            'CREATE INDEX ix_leads_phone ON leads(phone_norm)',
            'CREATE INDEX ix_leads_name ON leads(name_norm)',
            'CREATE INDEX ix_contacts_lead ON lead_contacts(lead_id, contacted_at)',
            'CREATE INDEX ix_contacts_time ON lead_contacts(contacted_at)',
            'CREATE INDEX ix_tasks_open ON lead_tasks(status, assigned_user_id, due_at)',
            'CREATE INDEX ix_tasks_lead ON lead_tasks(lead_id, status, due_at)',
            'CREATE INDEX ix_notes_lead ON lead_notes(lead_id)',
            'CREATE INDEX ix_audit_lead ON lead_audit_log(lead_id, created_at)',
            'CREATE INDEX ix_audit_time ON lead_audit_log(created_at)',
            function (PDO $db) {
                $s = $db->prepare('INSERT OR IGNORE INTO users (slug, name, role, created_at) VALUES (?, ?, \'admin\', ?)');
                foreach (CRM_TYM as $u) $s->execute([$u[0], $u[1], crm_ted()]);
            },
        ]],
        2 => ['lead nemusí mít odpovědnou osobu (nepřiřazený lead)', [
            'CREATE TABLE leads_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                name_norm TEXT NOT NULL DEFAULT \'\',
                phone TEXT, phone_norm TEXT,
                email TEXT, email_norm TEXT,
                website TEXT, domain TEXT,
                outreach_reason TEXT,
                status TEXT NOT NULL DEFAULT \'novy\' CHECK (status IN (\'novy\',\'kontaktovan\',\'v_reseni\',\'ukonceno\',\'dokonceno\')),
                priority TEXT NOT NULL DEFAULT \'standardni\' CHECK (priority IN (\'nizka\',\'standardni\',\'vysoka\')),
                assigned_user_id INTEGER REFERENCES users(id),      -- NULL = nepřiřazen, kdokoli ho může převzít
                created_by_user_id INTEGER NOT NULL REFERENCES users(id),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL,
                closed_at TEXT,
                closure_reason TEXT,
                archived_at TEXT,
                waiting INTEGER NOT NULL DEFAULT 0,
                waiting_note TEXT,
                contact_count INTEGER NOT NULL DEFAULT 0,
                last_contact_at TEXT,
                last_contact_result TEXT,
                project_id TEXT,
                version INTEGER NOT NULL DEFAULT 1)',
            'INSERT INTO leads_new SELECT id, name, name_norm, phone, phone_norm, email, email_norm, website, domain, outreach_reason, status, priority, assigned_user_id, created_by_user_id,
                created_at, updated_at, last_activity_at, closed_at, closure_reason, archived_at, waiting, waiting_note, contact_count, last_contact_at, last_contact_result, project_id, version FROM leads',
            'DROP TABLE leads',
            'ALTER TABLE leads_new RENAME TO leads',
            'CREATE INDEX ix_leads_status ON leads(status, archived_at)',
            'CREATE INDEX ix_leads_owner ON leads(assigned_user_id, status)',
            'CREATE INDEX ix_leads_activity ON leads(last_activity_at)',
            'CREATE INDEX ix_leads_domain ON leads(domain)',
            'CREATE INDEX ix_leads_email ON leads(email_norm)',
            'CREATE INDEX ix_leads_phone ON leads(phone_norm)',
            'CREATE INDEX ix_leads_name ON leads(name_norm)',
        ], true],
        3 => ['souhlasy GDPR k leadům', [
            'CREATE TABLE lead_consents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                doc_no TEXT NOT NULL DEFAULT \'\',
                person_name TEXT NOT NULL,
                person_role TEXT,
                company TEXT, company_ico TEXT, company_address TEXT, email TEXT,
                purposes TEXT NOT NULL,                 -- JSON: účely uvedené v dokumentu
                granted TEXT,                           -- JSON: účely, se kterými klient souhlasil (po podpisu)
                status TEXT NOT NULL DEFAULT \'pripraveno\' CHECK (status IN (\'pripraveno\',\'podepsano\',\'odmitnuto\',\'odvolano\',\'ukonceno\',\'zruseno\')),
                valid_years INTEGER NOT NULL DEFAULT 3,
                controller TEXT NOT NULL,               -- JSON: údaje o správci v okamžiku vytvoření dokumentu
                created_by_user_id INTEGER NOT NULL REFERENCES users(id),
                created_at TEXT NOT NULL,
                signed_at TEXT, valid_until TEXT, signed_recorded_by INTEGER REFERENCES users(id),
                revoked_at TEXT, revoked_purposes TEXT, ended_note TEXT,
                scan_file TEXT, scan_name TEXT, scan_mime TEXT, scan_size INTEGER, scan_at TEXT,
                updated_at TEXT NOT NULL)',
            'CREATE INDEX ix_consents_lead ON lead_consents(lead_id, status)',
            'CREATE INDEX ix_consents_valid ON lead_consents(status, valid_until)',
        ]],
        4 => ['potvrzení o zpracování údajů pro poskytnutí služeb', [
            'ALTER TABLE lead_consents ADD COLUMN services TEXT',      // JSON {"k":[klíče CRM_SLUZBY],"jine":"…"}; NULL = dokument potvrzení neobsahuje
        ]],
        5 => ['poptávky z webu: lead bez autora, původ leadu, původní poptávka, marketingový zdroj a fronta oznámení', [
            'CREATE TABLE leads_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                name_norm TEXT NOT NULL DEFAULT \'\',
                phone TEXT, phone_norm TEXT,
                email TEXT, email_norm TEXT,
                website TEXT, domain TEXT,
                outreach_reason TEXT,
                status TEXT NOT NULL DEFAULT \'novy\' CHECK (status IN (\'novy\',\'kontaktovan\',\'v_reseni\',\'ukonceno\',\'dokonceno\')),
                priority TEXT NOT NULL DEFAULT \'standardni\' CHECK (priority IN (\'nizka\',\'standardni\',\'vysoka\')),
                assigned_user_id INTEGER REFERENCES users(id),
                created_by_user_id INTEGER REFERENCES users(id),     -- NULL = lead založil systém (poptávka z webu), ne člověk
                source TEXT NOT NULL DEFAULT \'rucne\' CHECK (source IN (\'rucne\',\'import\',\'web\')),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL,
                closed_at TEXT,
                closure_reason TEXT,
                archived_at TEXT,
                waiting INTEGER NOT NULL DEFAULT 0,
                waiting_note TEXT,
                contact_count INTEGER NOT NULL DEFAULT 0,
                last_contact_at TEXT,
                last_contact_result TEXT,
                project_id TEXT,
                version INTEGER NOT NULL DEFAULT 1)',
            'INSERT INTO leads_new (id, name, name_norm, phone, phone_norm, email, email_norm, website, domain, outreach_reason, status, priority, assigned_user_id, created_by_user_id, source,
                created_at, updated_at, last_activity_at, closed_at, closure_reason, archived_at, waiting, waiting_note, contact_count, last_contact_at, last_contact_result, project_id, version)
                SELECT id, name, name_norm, phone, phone_norm, email, email_norm, website, domain, outreach_reason, status, priority, assigned_user_id, created_by_user_id,
                CASE WHEN EXISTS (SELECT 1 FROM lead_audit_log a WHERE a.lead_id = leads.id AND a.action = \'vytvoren\' AND a.field = \'import\') THEN \'import\' ELSE \'rucne\' END,
                created_at, updated_at, last_activity_at, closed_at, closure_reason, archived_at, waiting, waiting_note, contact_count, last_contact_at, last_contact_result, project_id, version FROM leads',
            'DROP TABLE leads',
            'ALTER TABLE leads_new RENAME TO leads',
            'CREATE INDEX ix_leads_status ON leads(status, archived_at)',
            'CREATE INDEX ix_leads_owner ON leads(assigned_user_id, status)',
            'CREATE INDEX ix_leads_activity ON leads(last_activity_at)',
            'CREATE INDEX ix_leads_domain ON leads(domain)',
            'CREATE INDEX ix_leads_email ON leads(email_norm)',
            'CREATE INDEX ix_leads_phone ON leads(phone_norm)',
            'CREATE INDEX ix_leads_name ON leads(name_norm)',
            'CREATE INDEX ix_leads_source ON leads(source, created_at)',
            'CREATE TABLE lead_inquiries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                submission_id TEXT NOT NULL UNIQUE,         -- ochrana proti dvojímu odeslání (dvojklik, opakovaný požadavek)
                form TEXT NOT NULL DEFAULT \'kontakt\',
                contact_name TEXT NOT NULL,
                company TEXT,
                email TEXT NOT NULL,
                phone TEXT,
                website TEXT,
                services TEXT,                              -- JSON seznam vybraných služeb, jak je viděl návštěvník
                message TEXT NOT NULL,
                budget TEXT,
                start_when TEXT,
                contact_pref TEXT,
                language TEXT,
                consent_at TEXT NOT NULL,
                created_at TEXT NOT NULL)',
            'CREATE INDEX ix_inquiries_lead ON lead_inquiries(lead_id)',
            'CREATE TABLE lead_attribution (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                inquiry_id INTEGER NOT NULL REFERENCES lead_inquiries(id) ON DELETE CASCADE,
                channel TEXT NOT NULL DEFAULT \'nezname\',   -- klíč CRM_ZDROJE
                site TEXT,                                  -- doména webu, na kterém poptávka vznikla
                landing_page TEXT,                          -- první stránka návštěvy
                source_page TEXT,                           -- stránka před formulářem
                submission_page TEXT,                       -- stránka s formulářem
                language TEXT,
                utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, utm_content TEXT, utm_term TEXT,
                click_id TEXT,                              -- jen název parametru (gclid, fbclid…), ne jeho hodnota
                referrer TEXT,                              -- adresa odkazující stránky bez parametrů
                first_visit_at TEXT,
                created_at TEXT NOT NULL)',
            'CREATE INDEX ix_attr_lead ON lead_attribution(lead_id)',
            'CREATE INDEX ix_attr_channel ON lead_attribution(channel)',
            'CREATE INDEX ix_attr_campaign ON lead_attribution(utm_campaign)',
            'CREATE TABLE lead_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                inquiry_id INTEGER NOT NULL REFERENCES lead_inquiries(id) ON DELETE CASCADE,
                kind TEXT NOT NULL,                         -- nova_poptavka
                recipient TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'ceka\' CHECK (status IN (\'ceka\',\'odeslano\',\'predano\',\'chyba\',\'selhalo\')),
                attempts INTEGER NOT NULL DEFAULT 0,
                last_error TEXT,
                provider_response TEXT,
                next_try_at TEXT NOT NULL,
                locked_until TEXT,
                sent_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (inquiry_id, kind))',
            'CREATE INDEX ix_notif_queue ON lead_notifications(status, next_try_at)',
        ], true],
    ];
}

/* ---------- denní záloha databáze (VACUUM INTO vytvoří ucelenou kopii i za provozu) ---------- */
function crm_denni_zaloha() {
    static $hotovo = false;
    if ($hotovo) return; $hotovo = true;
    try {
        if (!is_dir(CRM_ZALOHY)) @mkdir(CRM_ZALOHY, 0700, true);
        $cil = CRM_ZALOHY . '/crm-' . date('Y-m-d') . '.sqlite';
        if (is_file($cil) || !is_writable(CRM_ZALOHY)) return;
        crm_db()->exec('VACUUM INTO ' . crm_db()->quote($cil));
        @chmod($cil, 0600);
        $z = glob(CRM_ZALOHY . '/crm-*.sqlite') ?: []; sort($z);
        while (count($z) > CRM_ZALOHY_MAX) @unlink(array_shift($z));
    } catch (Throwable $e) { /* záloha nesmí zastavit práci */ }
}

/* ---------- uživatelé a přihlášení ---------- */
function crm_uzivatele($i_neaktivni = false) {
    if (!crm_dostupne()) return [];
    try { return crm_all('SELECT id, slug, name, email, role, active, (password_hash IS NOT NULL) AS has_pw FROM users' . ($i_neaktivni ? '' : ' WHERE active = 1') . ' ORDER BY id'); }
    catch (Throwable $e) { return []; }
}
function crm_uzivatel($id) { return $id ? crm_one('SELECT id, slug, name, email, role, active, password_hash FROM users WHERE id = ?', [(int) $id]) : null; }
function crm_uzivatel_slug($slug) { return crm_one('SELECT id, slug, name, email, role, active, password_hash FROM users WHERE slug = ? AND active = 1', [(string) $slug]); }
function crm_uid() { return (int) ($_SESSION['uid'] ?? 0); }
function crm_ja() { static $u = false; if ($u === false) $u = crm_uzivatel(crm_uid()); return $u; }

/* ---------- normalizace pro hledání a kontrolu duplicit ---------- */
function crm_bez_diakritiky($s) {
    $s = mb_strtolower((string) $s, 'UTF-8');
    if (function_exists('iconv')) { $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if ($t !== false) $s = $t; }
    return $s;
}
function crm_norm_jmeno($s) {
    $s = crm_bez_diakritiky($s);
    $s = preg_replace('/\b(s\s*\.?\s*r\s*\.?\s*o|a\s*\.?\s*s|spol|z\s*\.?\s*s|v\s*\.?\s*o\s*\.?\s*s|o\s*\.?\s*p\s*\.?\s*s)\b\.?/', ' ', $s);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}
function crm_norm_tel($s) { $d = preg_replace('/\D/', '', (string) $s); return strlen($d) >= 9 ? substr($d, -9) : $d; }
function crm_norm_email($s) { return mb_strtolower(trim((string) $s), 'UTF-8'); }
function crm_domena($url) {
    $u = trim((string) $url); if ($u === '') return '';
    if (!preg_match('~^[a-z]+://~i', $u)) $u = 'http://' . $u;
    $h = mb_strtolower((string) parse_url($u, PHP_URL_HOST), 'UTF-8');
    return preg_replace('/^www\./', '', $h);
}
function crm_odkaz($url) {      // jen http(s); bez schématu doplní https://
    $u = trim((string) $url); if ($u === '') return '';
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $u)) $u = 'https://' . $u;
    if (!preg_match('~^https?://~i', $u) || !filter_var($u, FILTER_VALIDATE_URL)) return null;
    return mb_substr($u, 0, 300);
}

/* ---------- auditní stopa ---------- */
function crm_audit($lead, $akce, $pole = null, $stare = null, $nove = null) {
    crm_q('INSERT INTO lead_audit_log (lead_id, user_id, action, field, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [(int) $lead, crm_uid() ?: null, $akce, $pole, $stare === null ? null : (string) $stare, $nove === null ? null : (string) $nove, crm_ted()]);
}
function crm_aktivita($lead) { crm_q('UPDATE leads SET last_activity_at = ? WHERE id = ?', [crm_ted(), (int) $lead]); }
