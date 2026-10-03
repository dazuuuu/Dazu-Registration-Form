-- Certificates + LMS tables. These were only in the old databases/migrations folder (050–052),
-- which the System Update screen does not read, so live databases never got them.
-- Safe to re-run: "already exists" / "duplicate column" errors are skipped by the updater.

CREATE TABLE certificates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    course_id INT NOT NULL,
    certificate_no VARCHAR(50) NOT NULL UNIQUE,
    qr_code_hash VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('requested', 'returned', 'being_prepared', 'awaiting_director_signature', 'issued', 'withdrawn') NOT NULL DEFAULT 'requested',
    snapshot_name VARCHAR(150) NOT NULL,
    snapshot_course VARCHAR(150) NOT NULL,
    snapshot_duration_months INT NOT NULL,
    snapshot_dates VARCHAR(100) NOT NULL,
    taught_skills TEXT NULL,
    tutor_id INT NULL,
    tutor_signature_path VARCHAR(255) NULL,
    tutor_name VARCHAR(150) NULL,
    director_id INT NULL,
    director_signature_path VARCHAR(255) NULL,
    ceo_name VARCHAR(150) NULL,
    generated_pdf_path VARCHAR(255) NULL,
    issued_at TIMESTAMP NULL,
    withdrawn_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cert_registration (registration_id),
    KEY idx_cert_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Databases that already had an older certificates table
ALTER TABLE certificates ADD COLUMN tutor_name VARCHAR(150) NULL AFTER tutor_signature_path;
ALTER TABLE certificates ADD COLUMN ceo_name VARCHAR(150) NULL AFTER director_signature_path;
ALTER TABLE certificates ADD COLUMN generated_pdf_path VARCHAR(255) NULL AFTER ceo_name;

ALTER TABLE courses ADD COLUMN certificate_template VARCHAR(255) NULL;

CREATE TABLE course_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    tutor_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_module_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE module_contents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    type ENUM('assignment', 'question', 'material') NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NULL,
    file_path VARCHAR(255) NULL,
    max_score INT NULL,
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_content_module (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
