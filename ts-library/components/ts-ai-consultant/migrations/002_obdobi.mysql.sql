-- ts-ai-consultant 1.4.0 – období rozpočtu může být den (RRRR-MM-DD)
ALTER TABLE ts_ai_usage MODIFY month VARCHAR(10) NOT NULL;
ALTER TABLE ts_ai_reservations MODIFY month VARCHAR(10) NOT NULL;
