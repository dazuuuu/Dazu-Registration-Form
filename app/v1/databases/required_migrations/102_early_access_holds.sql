-- 102_early_access_holds.sql
-- Early access, seat holds and "Enroll now" that follows the openings, on top of Book now (101). ADD ONLY.
--  enrolment_openings.early_access_at: when early access starts for this opening (empty = enrolment opens minus the early-access hours in settings, 48 by default).
--  enrolment_bookings: invited_at (the invite was sent), hold_until (the seat is held until, invited_at + the hold days, 3 by default),
--      reminder_sent_at (the 24-hour reminder), converted_registration_id / converted_at (the booking became a student).
--      New statuses: converted (the person enrolled) and expired (the hold ended; the seat was released).
ALTER TABLE enrolment_openings ADD COLUMN early_access_at DATETIME NULL;
ALTER TABLE enrolment_bookings MODIFY status ENUM('confirmed','waiting','cancelled','payment_received','converted','expired') NOT NULL DEFAULT 'confirmed';
ALTER TABLE enrolment_bookings ADD COLUMN invited_at DATETIME NULL;
ALTER TABLE enrolment_bookings ADD COLUMN hold_until DATETIME NULL;
ALTER TABLE enrolment_bookings ADD COLUMN reminder_sent_at DATETIME NULL;
ALTER TABLE enrolment_bookings ADD COLUMN converted_registration_id INT NULL;
ALTER TABLE enrolment_bookings ADD COLUMN converted_at DATETIME NULL;
ALTER TABLE enrolment_bookings ADD INDEX idx_eb_hold (status, hold_until);
