-- ts-design-studio – rollback (smaže projekty, verze a záznam akcí)
DROP TABLE IF EXISTS studio_log; DROP TABLE IF EXISTS studio_versions; DROP TABLE IF EXISTS studio_projects; DELETE FROM studio_schema WHERE version = 1;
