-- ts-design-studio 0.4.0 – doručení návrhů klientovi: neveřejný odkaz (jen otisk tokenu), e-mail, ruční předání, odvolání (SQLite)
CREATE TABLE IF NOT EXISTS studio_deliveries (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, num INTEGER NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, nonce VARCHAR(32) NOT NULL,
  status VARCHAR(16) NOT NULL, method VARCHAR(10) NOT NULL DEFAULT '', days INTEGER NOT NULL DEFAULT 14, expires_at VARCHAR(19) NOT NULL DEFAULT '', activated_at VARCHAR(19) NOT NULL DEFAULT '',
  revoked_at VARCHAR(19) NOT NULL DEFAULT '', revoked_by INTEGER NOT NULL DEFAULT 0, to_email VARCHAR(190) NOT NULL DEFAULT '', to_name VARCHAR(120) NOT NULL DEFAULT '', subject VARCHAR(200) NOT NULL DEFAULT '',
  body TEXT NOT NULL DEFAULT '', created_by INTEGER NOT NULL DEFAULT 0, sent_by INTEGER NOT NULL DEFAULT 0, sent_at VARCHAR(19) NOT NULL DEFAULT '', error VARCHAR(300) NOT NULL DEFAULT '', idem VARCHAR(64) NOT NULL DEFAULT '',
  views INTEGER NOT NULL DEFAULT 0, first_view_at VARCHAR(19) NOT NULL DEFAULT '', last_view_at VARCHAR(19) NOT NULL DEFAULT '', created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL);
CREATE INDEX IF NOT EXISTS studio_deliveries_project ON studio_deliveries (project_id, id);
