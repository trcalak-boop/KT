-- ts-ai-consultant 1.8.0 – měsíční počet znaků převedených na řeč (strop placeného hlasu)
CREATE TABLE IF NOT EXISTS ts_ai_speech (month VARCHAR(7) NOT NULL PRIMARY KEY, chars INT NOT NULL DEFAULT 0, requests INT NOT NULL DEFAULT 0) DEFAULT CHARSET=utf8mb4;
