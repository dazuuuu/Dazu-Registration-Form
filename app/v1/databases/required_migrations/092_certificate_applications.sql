-- Apply for a certificate: the student's application, the tutor's lines, extended end dates, and the audit log. ADD ONLY.
--  No existing certificate, payment or mark is changed. New columns on certificates are nullable or have a default.
--  certificate_applications: ONE application per student and course (UNIQUE). A returned application is sent again by the same row.
--    status: pending (waiting for the tutor), returned (with a note, student can apply again), approved (certificate made, not sent), sent.
--    print_name = how the student wants the name printed; lines_text = the tutor's lines under the name (max 300 letters, saved on the certificate as taught_skills).
--  course_end_dates: the tutor moves the end date of one student's course (for example after a break), with a reason. The latest row counts.
--  certificate_events: audit log (applied, returned, approved, sent, resent, end_extended, new_version ...).
--  certificates.version / replaced_by_id / application_id: a mistake is fixed by a NEW version; the old one is withdrawn ("Replaced") and its number is never reused.
CREATE TABLE IF NOT EXISTS certificate_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    registration_id INT NOT NULL,
    course_id INT NOT NULL,
    tutor_user_id INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    print_name VARCHAR(150) NOT NULL,
    name_confirmed_at DATETIME NULL,
    lines_text TEXT NULL,
    return_note VARCHAR(500) NULL,
    certificate_id INT NULL,
    applied_at DATETIME NOT NULL,
    returned_at DATETIME NULL,
    approved_at DATETIME NULL,
    sent_at DATETIME NULL,
    last_sent_at DATETIME NULL,
    send_count INT NOT NULL DEFAULT 0,
    reminded_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_certapp_student_course (student_user_id, course_id),
    KEY idx_certapp_status (status, applied_at),
    KEY idx_certapp_tutor (tutor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS course_end_dates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    course_id INT NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(300) NOT NULL,
    actor_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ced_student (student_user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS certificate_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT NULL,
    student_user_id INT NULL,
    course_id INT NULL,
    actor_user_id INT NULL,
    event VARCHAR(40) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_certev_app (application_id),
    KEY idx_certev_student (student_user_id, course_id, event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE certificates ADD COLUMN application_id INT UNSIGNED NULL;
ALTER TABLE certificates ADD COLUMN version INT NOT NULL DEFAULT 1;
ALTER TABLE certificates ADD COLUMN replaced_by_id INT UNSIGNED NULL;
