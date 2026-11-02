-- ts-design-studio – rollback 003 (smaže doručení; odkazy klientům přestanou fungovat)
DROP TABLE IF EXISTS studio_deliveries; DELETE FROM studio_schema WHERE version = 3;
