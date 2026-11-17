-- ts-booking-server 1.0.0 – schéma rezervací (MySQL/MariaDB, utf8mb4). Rollback: 001_booking.down.sql
CREATE TABLE IF NOT EXISTS ts_booking_bookings (
  id VARCHAR(40) PRIMARY KEY,
  date CHAR(10) NOT NULL, start INT NOT NULL, dur INT NOT NULL, buffer INT NOT NULL DEFAULT 0,
  service VARCHAR(80) NOT NULL, variant INT NOT NULL DEFAULT 0, addons TEXT,
  resource VARCHAR(80), pool_member VARCHAR(80), units INT,
  status VARCHAR(12) NOT NULL,
  price INT,
  name VARCHAR(160), email VARCHAR(254), phone VARCHAR(40), note TEXT, lang VARCHAR(10), source VARCHAR(40),
  idem_key VARCHAR(80) UNIQUE, cancel_hash CHAR(64), consent_at CHAR(19),
  created_at CHAR(19) NOT NULL, updated_at CHAR(19) NOT NULL, anonymized_at CHAR(19),
  INDEX ix_tsb_date (date, status), INDEX ix_tsb_email (email)
) DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ts_booking_blocks (
  id VARCHAR(40) PRIMARY KEY, date CHAR(10) NOT NULL, resource VARCHAR(80), from_min INT NOT NULL, to_min INT NOT NULL,
  reason VARCHAR(200), created_by VARCHAR(80), created_at CHAR(19) NOT NULL, INDEX ix_tsb_blocks_date (date)
) DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ts_booking_events (
  id INT AUTO_INCREMENT PRIMARY KEY, booking_id VARCHAR(40), action VARCHAR(40) NOT NULL, actor VARCHAR(80) NOT NULL,
  old_status VARCHAR(12), new_status VARCHAR(12), detail TEXT, created_at CHAR(19) NOT NULL, INDEX ix_tsb_events_booking (booking_id)
) DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ts_booking_users (
  id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, email VARCHAR(254),
  role VARCHAR(20) NOT NULL DEFAULT 'staff', password_hash VARCHAR(255) NOT NULL, active TINYINT NOT NULL DEFAULT 1,
  created_at CHAR(19) NOT NULL, last_login_at CHAR(19)
) DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ts_booking_lock (id INT PRIMARY KEY) DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO ts_booking_lock (id) VALUES (1);
