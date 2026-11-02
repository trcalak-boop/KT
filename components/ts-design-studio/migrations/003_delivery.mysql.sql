-- ts-design-studio 0.4.0 – doručení návrhů klientovi (MySQL)
CREATE TABLE IF NOT EXISTS studio_deliveries (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NOT NULL, num INT NOT NULL, token_hash VARCHAR(64) NOT NULL, nonce VARCHAR(32) NOT NULL,
  status VARCHAR(16) NOT NULL, method VARCHAR(10) NOT NULL DEFAULT '', days INT NOT NULL DEFAULT 14, expires_at VARCHAR(19) NOT NULL DEFAULT '', activated_at VARCHAR(19) NOT NULL DEFAULT '',
  revoked_at VARCHAR(19) NOT NULL DEFAULT '', revoked_by INT NOT NULL DEFAULT 0, to_email VARCHAR(190) NOT NULL DEFAULT '', to_name VARCHAR(120) NOT NULL DEFAULT '', subject VARCHAR(200) NOT NULL DEFAULT '',
  body TEXT NOT NULL, created_by INT NOT NULL DEFAULT 0, sent_by INT NOT NULL DEFAULT 0, sent_at VARCHAR(19) NOT NULL DEFAULT '', error VARCHAR(300) NOT NULL DEFAULT '', idem VARCHAR(64) NOT NULL DEFAULT '',
  views INT NOT NULL DEFAULT 0, first_view_at VARCHAR(19) NOT NULL DEFAULT '', last_view_at VARCHAR(19) NOT NULL DEFAULT '', created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL,
  UNIQUE KEY studio_deliveries_token (token_hash), INDEX studio_deliveries_project (project_id, id)) DEFAULT CHARSET=utf8mb4;
