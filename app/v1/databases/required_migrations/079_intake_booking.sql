-- Intake Booking: one set of intakes shared by all courses, bookings for the next intake, early access, holds,
-- waiting list and interest list. All DATETIME columns are UTC; the site compares and shows them in EAT
-- Nothing changes on the website until the admin creates the first intake.

CREATE TABLE IF NOT EXISTS intakes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    short_label VARCHAR(30) NULL,
    bookings_open_at DATETIME NOT NULL,
    early_access_at DATETIME NULL COMMENT 'blank = enrolment opens minus the early access hours',
    enrol_opens_at DATETIME NOT NULL,
    enrol_closes_at DATETIME NOT NULL COMMENT 'closing enrolment is the end of the intake',
    classes_start_on DATE NULL,
    seats_default INT NULL COMMENT 'seats per course, NULL = no limit',
    override_mode ENUM('auto','open','closed') NOT NULL DEFAULT 'auto',
    invites_sent_at DATETIME NULL,
    interest_notified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_intake_dates (enrol_opens_at, enrol_closes_at)
);

CREATE TABLE IF NOT EXISTS intake_course_seats (
    intake_id INT NOT NULL,
    course_id INT NOT NULL,
    seats INT NULL,
    excluded TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (intake_id, course_id),
    INDEX idx_ics_course (course_id)
);

CREATE TABLE IF NOT EXISTS intake_settings (
    id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
    early_access_hours INT NOT NULL DEFAULT 48,
    hold_days INT NOT NULL DEFAULT 3,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Nairobi',
    last_summary_on DATE NULL
);
INSERT IGNORE INTO intake_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS intake_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intake_id INT NULL,
    admin_id INT NULL,
    action VARCHAR(40) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_il_intake (intake_id)
);

CREATE TABLE IF NOT EXISTS intake_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(20) NOT NULL UNIQUE,
    intake_id INT NOT NULL,
    course_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    phone2 VARCHAR(30) NULL,
    mode ENUM('online','physical') NOT NULL DEFAULT 'online',
    class_time VARCHAR(10) NULL,
    campus VARCHAR(30) NULL,
    status ENUM('booked','contacted','invited','converted','expired','cancelled') NOT NULL DEFAULT 'booked',
    token_salt CHAR(32) NOT NULL COMMENT 'the link token is an HMAC of id + salt, the token itself is never stored',
    invited_at DATETIME NULL,
    early_access_until DATETIME NULL,
    hold_until DATETIME NULL,
    reminder_sent_at DATETIME NULL,
    converted_registration_id INT NULL,
    source VARCHAR(120) NULL,
    notes TEXT NULL,
    called_on DATE NULL,
    consent TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ib_intake (intake_id), INDEX idx_ib_course (course_id), INDEX idx_ib_email (email),
    INDEX idx_ib_phone (phone), INDEX idx_ib_status (status), INDEX idx_ib_hold (hold_until)
);

CREATE TABLE IF NOT EXISTS intake_booking_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    actor VARCHAR(60) NOT NULL,
    action VARCHAR(40) NOT NULL,
    details VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ibl_booking (booking_id)
);

CREATE TABLE IF NOT EXISTS intake_waiting_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intake_id INT NOT NULL,
    course_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    mode ENUM('online','physical') NOT NULL DEFAULT 'online',
    class_time VARCHAR(10) NULL,
    campus VARCHAR(30) NULL,
    source VARCHAR(120) NULL,
    status ENUM('waiting','offered','booked','removed') NOT NULL DEFAULT 'waiting',
    offered_at DATETIME NULL,
    booking_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_iwl (intake_id, course_id, status)
);

CREATE TABLE IF NOT EXISTS intake_interest_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at DATETIME NULL,
    INDEX idx_iil_notified (notified_at)
);

CREATE TABLE IF NOT EXISTS intake_rate_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    action VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_irl (ip, action, created_at)
);

-- Trace a student back to the booking they came from (existing columns are not changed).
ALTER TABLE registrations ADD COLUMN booking_id INT NULL;
