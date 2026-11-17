-- ts-design-studio 0.12.0 – připomínky klienta k nahranému návrhu (SQLite)
CREATE TABLE IF NOT EXISTS studio_feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, num INTEGER NOT NULL, delivery_id INTEGER NOT NULL, kind VARCHAR(10) NOT NULL, author VARCHAR(120) NOT NULL DEFAULT '', section VARCHAR(80) NOT NULL DEFAULT '', body TEXT NOT NULL, status VARCHAR(8) NOT NULL DEFAULT 'new', notified_at VARCHAR(19) NOT NULL DEFAULT '', synced_at VARCHAR(19) NOT NULL DEFAULT '', done_by INTEGER NOT NULL DEFAULT 0, done_at VARCHAR(19) NOT NULL DEFAULT '', created_at VARCHAR(19) NOT NULL);
CREATE INDEX IF NOT EXISTS studio_feedback_project ON studio_feedback (project_id, id);
CREATE INDEX IF NOT EXISTS studio_feedback_delivery ON studio_feedback (delivery_id, id);
