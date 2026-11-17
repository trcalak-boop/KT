-- Rollback migrace 001 (SMAŽE VŠECHNA DATA REZERVACÍ – předtím zálohovat databázi)
DROP TABLE IF EXISTS ts_booking_events;
DROP TABLE IF EXISTS ts_booking_blocks;
DROP TABLE IF EXISTS ts_booking_bookings;
DROP TABLE IF EXISTS ts_booking_users;
DROP TABLE IF EXISTS ts_booking_lock;
