-- ts-site-audit – rollback (smaže úlohy a výsledky; snímky ve složce shotDir smazat ručně)
DROP TABLE IF EXISTS ts_audit_rate; DROP TABLE IF EXISTS ts_audit_jobs; DELETE FROM ts_audit_schema WHERE version = 1;
