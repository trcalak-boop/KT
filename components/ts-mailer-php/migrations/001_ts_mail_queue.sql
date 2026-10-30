-- ts-mailer-php 1.0.0 – fronta e-mailů (SQLite i MySQL/MariaDB). Rollback: DROP TABLE ts_mail_queue;
CREATE TABLE IF NOT EXISTS ts_mail_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  dedupe_key VARCHAR(190) UNIQUE,
  kind VARCHAR(60) NOT NULL DEFAULT 'obecny',
  recipient VARCHAR(254) NOT NULL,
  subject VARCHAR(400) NOT NULL,
  text_body TEXT NOT NULL,
  html_body TEXT NOT NULL,
  reply_to VARCHAR(254), reply_name VARCHAR(200), msgid VARCHAR(250),
  status VARCHAR(12) NOT NULL DEFAULT 'ceka' CHECK (status IN ('ceka','odeslano','predano','zapsano','chyba','selhalo')),
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT, provider_response TEXT,
  next_try_at VARCHAR(19) NOT NULL, locked_until VARCHAR(19), sent_at VARCHAR(19),
  created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL
);
