-- Simple enrolment: the admin opens enrolment for one course or for all courses; students book a slot.
-- ADD ONLY. The older intake tables (079) are kept as they are but no longer used by the site.
--  enrolment_openings.course_id NULL = all courses. seats NULL = no limit; seats is per course. Bookings past the
--  seats are still taken (status 'waiting') and the admin allocates them later.
--  payment_* columns are ready for paying for a slot later; nothing uses them yet.

CREATE TABLE IF NOT EXISTS enrolment_openings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NULL,
    title VARCHAR(120) NOT NULL,
    seats INT UNSIGNED NULL,
    classes_start DATE NULL,
    closes_at DATETIME NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    KEY idx_eo_course (course_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enrolment_bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    opening_id INT UNSIGNED NOT NULL,
    course_id INT NOT NULL,
    reference VARCHAR(12) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    study_mode ENUM('online','physical') NOT NULL DEFAULT 'online',
    campus ENUM('thika','nairobi') NULL,
    note VARCHAR(500) NULL,
    status ENUM('confirmed','waiting','cancelled') NOT NULL DEFAULT 'confirmed',
    payment_status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
    payment_amount DECIMAL(12,2) NULL,
    payment_reference VARCHAR(60) NULL,
    ip VARCHAR(45) NULL,
    emailed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_eb_reference (reference),
    KEY idx_eb_opening (opening_id, course_id, status),
    KEY idx_eb_email (email),
    KEY idx_eb_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
