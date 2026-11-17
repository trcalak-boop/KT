-- ts-site-audit 1.0.0 – úlohy auditu webů a limity (SQLite)
CREATE TABLE IF NOT EXISTS ts_audit_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, token_hash VARCHAR(64) NOT NULL UNIQUE, url VARCHAR(500) NOT NULL, url_hash VARCHAR(64) NOT NULL, lang VARCHAR(5) NOT NULL, source VARCHAR(10) NOT NULL, ip_hash VARCHAR(32) NOT NULL DEFAULT '', ref VARCHAR(64) NOT NULL DEFAULT '', status VARCHAR(10) NOT NULL, lock_until INTEGER NOT NULL DEFAULT 0, attempts INTEGER NOT NULL DEFAULT 0, data TEXT NOT NULL, error VARCHAR(60) NOT NULL DEFAULT '', created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, finished_at VARCHAR(19));
CREATE INDEX IF NOT EXISTS ts_audit_jobs_url ON ts_audit_jobs (url_hash, status);
CREATE INDEX IF NOT EXISTS ts_audit_jobs_created ON ts_audit_jobs (created_at);
CREATE TABLE IF NOT EXISTS ts_audit_rate (bucket VARCHAR(120) NOT NULL PRIMARY KEY, window_start INTEGER NOT NULL, count INTEGER NOT NULL DEFAULT 0);
