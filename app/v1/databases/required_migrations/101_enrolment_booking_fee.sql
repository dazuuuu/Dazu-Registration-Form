-- 101_enrolment_booking_fee.sql
-- "Book now" with a KES 1,500 booking fee, built on simple enrolment (086). ADD ONLY. An opening that already exists keeps working as it does now:
-- an opening with no bookings_open_at and no opens_at is "Enrolment open" while its status is open, exactly as before.
--  enrolment_openings: bookings_open_at (bookings open), opens_at (enrolment opens), closes_at (enrolment closes = end of the intake, already there),
--      classes_start (already there), override_mode auto | open | closed. title is the opening's name (for example "January 2027"). All times are East Africa Time.
--  enrolment_opening_seats: a different number of seats for one course (openings.seats is the number per course).
--  enrolment_bookings: the payment code goes in payment_reference (kept in capitals, never used twice), the amount in payment_amount,
--      and payment_status becomes paid when the office confirms. New status payment_received = a valid code was sent and the seat is held.
--      fee_kind says how the fee counts: registration_fee (Physical) or course_fee_credit (Online).
--  enrolment_interest: people who asked to be told when the next intake opens (no payment).
ALTER TABLE enrolment_openings ADD COLUMN bookings_open_at DATETIME NULL;
ALTER TABLE enrolment_openings ADD COLUMN opens_at DATETIME NULL;
ALTER TABLE enrolment_openings ADD COLUMN override_mode VARCHAR(8) NOT NULL DEFAULT 'auto';

CREATE TABLE IF NOT EXISTS enrolment_opening_seats (
    opening_id INT UNSIGNED NOT NULL,
    course_id INT NOT NULL,
    seats INT UNSIGNED NOT NULL,
    PRIMARY KEY (opening_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE enrolment_bookings MODIFY status ENUM('confirmed','waiting','cancelled','payment_received') NOT NULL DEFAULT 'confirmed';
ALTER TABLE enrolment_bookings ADD COLUMN phone2 VARCHAR(30) NULL;
ALTER TABLE enrolment_bookings ADD COLUMN class_time VARCHAR(10) NULL;
ALTER TABLE enrolment_bookings ADD COLUMN consent TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE enrolment_bookings ADD COLUMN source VARCHAR(120) NULL;
ALTER TABLE enrolment_bookings ADD COLUMN pay_method VARCHAR(8) NULL;
ALTER TABLE enrolment_bookings ADD COLUMN fee_kind VARCHAR(20) NULL;
ALTER TABLE enrolment_bookings ADD COLUMN paid_at DATETIME NULL;
ALTER TABLE enrolment_bookings ADD COLUMN reviewed_by INT NULL;
ALTER TABLE enrolment_bookings ADD COLUMN reviewed_at DATETIME NULL;
ALTER TABLE enrolment_bookings ADD COLUMN reject_reason VARCHAR(255) NULL;
ALTER TABLE enrolment_bookings ADD COLUMN receipt_sent_at DATETIME NULL;
ALTER TABLE enrolment_bookings ADD COLUMN fee_applied_registration_id INT NULL;
ALTER TABLE enrolment_bookings ADD COLUMN token_salt CHAR(32) NULL;
ALTER TABLE enrolment_bookings ADD COLUMN offered_at DATETIME NULL;
ALTER TABLE enrolment_bookings ADD UNIQUE KEY uq_eb_payment_reference (payment_reference);

CREATE TABLE IF NOT EXISTS enrolment_interest (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    created_at DATETIME NOT NULL,
    notified_at DATETIME NULL,
    KEY idx_ei_notified (notified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
