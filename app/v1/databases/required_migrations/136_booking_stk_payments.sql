-- Course "Book now" STK push payments (Daraja), separate from `payments` (which requires an
-- existing registration_id). One row per STK attempt; booking/registration are created only
-- once Safaricom confirms payment.
CREATE TABLE IF NOT EXISTS enrolment_stk_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    opening_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    phone2 VARCHAR(30) NULL,
    study_mode VARCHAR(20) NOT NULL,
    campus VARCHAR(20) NULL,
    class_time VARCHAR(10) NULL,
    consent TINYINT(1) NOT NULL DEFAULT 0,
    amount DECIMAL(10,2) NOT NULL,
    merchant_request_id VARCHAR(60) NULL,
    checkout_request_id VARCHAR(60) NULL,
    mpesa_receipt VARCHAR(30) NULL,
    phone_confirmed VARCHAR(20) NULL,
    status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    result_desc VARCHAR(255) NULL,
    booking_id INT UNSIGNED NULL,
    registration_id INT UNSIGNED NULL,
    payment_id INT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_checkout (checkout_request_id),
    KEY idx_status (status),
    KEY idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MySQL has no ADD COLUMN IF NOT EXISTS before 8.0.29; MigrationService tolerates the
-- "Duplicate column" error (1060) on a repeat run.
ALTER TABLE payments ADD COLUMN checkout_request_id VARCHAR(60) NULL AFTER payer_reference;

-- enrolment_bookings.pay_method was VARCHAR(8) (fits 'mpesa'/'bank'); 'mpesa_stk' needs more room.
ALTER TABLE enrolment_bookings MODIFY COLUMN pay_method VARCHAR(20) NULL;
