-- ts-design-studio – rollback 002 (smaže konverzace s AI a záznam spotřeby)
DROP TABLE IF EXISTS studio_usage; DROP TABLE IF EXISTS studio_messages; DELETE FROM studio_schema WHERE version = 2;
