-- ts-booking-server 1.0.0 – schéma rezervací (SQLite). Rollback: 001_booking.down.sql
CREATE TABLE IF NOT EXISTS ts_booking_bookings (
  id TEXT PRIMARY KEY,
  date TEXT NOT NULL, start INTEGER NOT NULL, dur INTEGER NOT NULL, buffer INTEGER NOT NULL DEFAULT 0,
  service TEXT NOT NULL, variant INTEGER NOT NULL DEFAULT 0, addons TEXT,
  resource TEXT, pool_member TEXT, units INTEGER,
  status TEXT NOT NULL CHECK (status IN ('pending','confirmed','done','noshow','rejected','cancelled')),
  price INTEGER,
  name TEXT, email TEXT, phone TEXT, note TEXT, lang TEXT, source TEXT,
  idem_key TEXT UNIQUE, cancel_hash TEXT, consent_at TEXT,
  created_at TEXT NOT NULL, updated_at TEXT NOT NULL, anonymized_at TEXT
);
CREATE INDEX IF NOT EXISTS ix_tsb_date ON ts_booking_bookings(date, status);
CREATE INDEX IF NOT EXISTS ix_tsb_email ON ts_booking_bookings(email);
CREATE TABLE IF NOT EXISTS ts_booking_blocks (
  id TEXT PRIMARY KEY, date TEXT NOT NULL, resource TEXT, from_min INTEGER NOT NULL, to_min INTEGER NOT NULL,
  reason TEXT, created_by TEXT, created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_tsb_blocks_date ON ts_booking_blocks(date);
CREATE TABLE IF NOT EXISTS ts_booking_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id TEXT, action TEXT NOT NULL, actor TEXT NOT NULL,
  old_status TEXT, new_status TEXT, detail TEXT, created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_tsb_events_booking ON ts_booking_events(booking_id);
CREATE TABLE IF NOT EXISTS ts_booking_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, name TEXT NOT NULL, email TEXT,
  role TEXT NOT NULL DEFAULT 'staff', password_hash TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL, last_login_at TEXT
);
