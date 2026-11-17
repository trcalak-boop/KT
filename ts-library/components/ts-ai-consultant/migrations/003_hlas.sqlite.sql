-- ts-ai-consultant 1.8.0 – měsíční počet znaků převedených na řeč (strop placeného hlasu)
CREATE TABLE IF NOT EXISTS ts_ai_speech (month VARCHAR(7) NOT NULL PRIMARY KEY, chars INTEGER NOT NULL DEFAULT 0, requests INTEGER NOT NULL DEFAULT 0);
